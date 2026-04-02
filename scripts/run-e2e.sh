#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SERVER_LOG="${ROOT_DIR}/tests/_output/php-server.log"
E2E_DB="${ROOT_DIR}/storage/database/acceptance.sqlite"
SERVER_PID=""
DEFAULT_HOST="127.0.0.1"
DEFAULT_PORT="18081"
MAX_PORT_ATTEMPTS="20"
PHP_MEMORY_LIMIT="${PHP_RUNTIME_MEMORY_LIMIT:-512M}"

cleanup() {
    if [[ -n "${SERVER_PID}" ]] && kill -0 "${SERVER_PID}" 2>/dev/null; then
        kill "${SERVER_PID}" 2>/dev/null || true
        wait "${SERVER_PID}" 2>/dev/null || true
    fi
}

trap cleanup EXIT

build_url() {
    local host="$1"
    local port="$2"

    printf 'http://%s:%s' "${host}" "${port}"
}

resolve_target() {
    if [[ -n "${E2E_APP_URL:-}" ]]; then
        APP_URL="${E2E_APP_URL%/}"
        local host_port="${APP_URL#http://}"
        APP_HOST="${host_port%%:*}"
        APP_PORT="${host_port##*:}"
        return
    fi

    APP_HOST="${E2E_HOST:-${DEFAULT_HOST}}"
    APP_PORT="${E2E_PORT:-${DEFAULT_PORT}}"
    APP_URL="$(build_url "${APP_HOST}" "${APP_PORT}")"
}

seed_database() {
    APP_ENV=testing \
    APP_DEBUG=false \
    APP_URL="${APP_URL}" \
    APP_KEY=testing-key \
    APP_TIMEZONE=Asia/Manila \
    DB_DRIVER=sqlite \
    DB_DATABASE="${E2E_DB}" \
    SESSION_NAME=simse2esession \
    SESSION_LIFETIME=120 \
    DEFAULT_PASSWORD=Password123! \
    php -d "memory_limit=${PHP_MEMORY_LIMIT}" "${ROOT_DIR}/bin/console" reset-db >/dev/null
}

start_server() {
    local healthcheck_url="${APP_URL}/login"

    : >"${SERVER_LOG}"
    APP_ENV=testing \
    APP_DEBUG=false \
    APP_URL="${APP_URL}" \
    APP_KEY=testing-key \
    APP_TIMEZONE=Asia/Manila \
    DB_DRIVER=sqlite \
    DB_DATABASE="${E2E_DB}" \
    SESSION_NAME=simse2esession \
    SESSION_LIFETIME=120 \
    DEFAULT_PASSWORD=Password123! \
    php -d "memory_limit=${PHP_MEMORY_LIMIT}" -S "${APP_HOST}:${APP_PORT}" -t "${ROOT_DIR}/public" >"${SERVER_LOG}" 2>&1 &
    SERVER_PID=$!

    for attempt in $(seq 1 15); do
        if curl --silent --fail --output /dev/null "${healthcheck_url}"; then
            return 0
        fi

        if ! kill -0 "${SERVER_PID}" 2>/dev/null; then
            wait "${SERVER_PID}" 2>/dev/null || true
            SERVER_PID=""
            return 1
        fi

        sleep 1
    done

    cleanup
    SERVER_PID=""

    return 1
}

mkdir -p "${ROOT_DIR}/tests/_output"
mkdir -p "${ROOT_DIR}/storage/database"

rm -f "${E2E_DB}"
touch "${E2E_DB}"

resolve_target
seed_database

if [[ -n "${E2E_PORT:-}" || -n "${E2E_APP_URL:-}" ]]; then
    if ! start_server; then
        echo "Timed out waiting for the local PHP server at ${APP_URL}." >&2
        if [[ -f "${SERVER_LOG}" ]]; then
            echo "---- php-server.log ----" >&2
            cat "${SERVER_LOG}" >&2
        fi

        exit 1
    fi
else
    server_started=false

    for candidate_port in $(seq "${DEFAULT_PORT}" "$((DEFAULT_PORT + MAX_PORT_ATTEMPTS - 1))"); do
        APP_HOST="${DEFAULT_HOST}"
        APP_PORT="${candidate_port}"
        APP_URL="$(build_url "${APP_HOST}" "${APP_PORT}")"

        if start_server; then
            server_started=true
            break
        fi
    done

    if [[ "${server_started}" != "true" ]]; then
        echo "Unable to start the local PHP server for acceptance tests after ${MAX_PORT_ATTEMPTS} port attempts." >&2
        if [[ -f "${SERVER_LOG}" ]]; then
            echo "---- php-server.log ----" >&2
            cat "${SERVER_LOG}" >&2
        fi

        exit 1
    fi
fi

cd "${ROOT_DIR}"
APP_URL="${APP_URL}" \
E2E_APP_URL="${APP_URL}" \
vendor/bin/codecept run Acceptance \
    -o "modules: config: PhpBrowser: url: '${APP_URL}'" \
    "$@"
