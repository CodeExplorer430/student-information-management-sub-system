#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BASE_URL="${DEPLOY_SMOKE_BASE_URL:-}"
CODECEPT_ARGS=()
TMP_DIR=""
usage() {
    printf 'Usage: %s [--url=<base-url>] [codeception args...]\n' "$(basename "$0")" >&2
}

log_failure() {
    php "${ROOT_DIR}/scripts/log-operations-event.php" \
        error \
        deployment.smoke.failed \
        "Deployment smoke failed." \
        deployment-smoke >/dev/null 2>&1 || true
}

fail() {
    log_failure
    printf 'Deployment smoke failed: %s\n' "$1" >&2
    exit 1
}

cleanup() {
    if [[ -n "${TMP_DIR}" && -d "${TMP_DIR}" ]]; then
        rm -rf "${TMP_DIR}"
    fi
}

trap cleanup EXIT
trap 'log_failure' ERR

for ARGUMENT in "$@"; do
    case "${ARGUMENT}" in
        --url=*)
            BASE_URL="${ARGUMENT#*=}"
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            CODECEPT_ARGS+=("${ARGUMENT}")
            ;;
    esac
done

BASE_URL="${BASE_URL%/}"

if [[ -z "${BASE_URL}" ]]; then
    usage
    fail 'DEPLOY_SMOKE_BASE_URL is required.'
fi

require_env() {
    local name="$1"
    local value="${!name:-}"

    if [[ -z "${value}" ]]; then
        fail "${name} is required."
    fi
}

require_env DEPLOY_SMOKE_ADMIN_EMAIL
require_env DEPLOY_SMOKE_ADMIN_PASSWORD
require_env DEPLOY_SMOKE_STUDENT_EMAIL
require_env DEPLOY_SMOKE_STUDENT_PASSWORD

TMP_DIR="$(mktemp -d)"

fetch_response() {
    local path="$1"
    local header_file="$2"
    local body_file="$3"

    curl \
        --silent \
        --show-error \
        --location \
        --max-time 20 \
        --dump-header "${header_file}" \
        --output "${body_file}" \
        --write-out '%{http_code}' \
        "${BASE_URL}${path}"
}

assert_header_present() {
    local header_file="$1"
    local header_name="$2"

    if ! grep -qi "^${header_name}:" "${header_file}"; then
        fail "Missing required header [${header_name}] on /login."
    fi
}

assert_header_contains() {
    local header_file="$1"
    local header_name="$2"
    local expected="$3"

    if ! grep -i "^${header_name}:" "${header_file}" | tr -d '\r' | grep -Fqi "${expected}"; then
        fail "Header [${header_name}] on /login did not contain [${expected}]."
    fi
}

assert_json_health() {
    local body_file="$1"
    local endpoint="$2"

    php -r '
        $path = $argv[1];
        $endpoint = $argv[2];
        $payload = json_decode((string) file_get_contents($path), true);

        if (!is_array($payload)) {
            fwrite(STDERR, "Health payload for {$endpoint} was not valid JSON.\n");
            exit(1);
        }

        if (($payload["status"] ?? null) !== "pass") {
            fwrite(STDERR, "Health payload for {$endpoint} did not report pass.\n");
            exit(1);
        }

        if (!is_string($payload["request_id"] ?? null) || $payload["request_id"] === "") {
            fwrite(STDERR, "Health payload for {$endpoint} did not include request_id.\n");
            exit(1);
        }

        if ($endpoint === "/health/ready" && !is_array($payload["checks"] ?? null)) {
            fwrite(STDERR, "Readiness payload did not include checks.\n");
            exit(1);
        }
    ' "${body_file}" "${endpoint}" || fail "Invalid JSON health response for ${endpoint}."
}

printf 'Deployment smoke target: %s\n' "${BASE_URL}"

for ENDPOINT in /health/live /health/ready; do
    HEADER_FILE="${TMP_DIR}/$(basename "${ENDPOINT}").headers"
    BODY_FILE="${TMP_DIR}/$(basename "${ENDPOINT}").body"
    STATUS_CODE="$(fetch_response "${ENDPOINT}" "${HEADER_FILE}" "${BODY_FILE}")"

    if [[ "${STATUS_CODE}" != "200" ]]; then
        fail "${ENDPOINT} returned HTTP ${STATUS_CODE}."
    fi

    assert_header_present "${HEADER_FILE}" "Content-Type"
    assert_header_contains "${HEADER_FILE}" "Content-Type" "application/json"
    assert_header_present "${HEADER_FILE}" "X-Request-Id"
    assert_json_health "${BODY_FILE}" "${ENDPOINT}"
done

LOGIN_HEADERS="${TMP_DIR}/login.headers"
LOGIN_BODY="${TMP_DIR}/login.body"
LOGIN_STATUS="$(fetch_response '/login' "${LOGIN_HEADERS}" "${LOGIN_BODY}")"

if [[ "${LOGIN_STATUS}" != "200" ]]; then
    fail "/login returned HTTP ${LOGIN_STATUS}."
fi

assert_header_present "${LOGIN_HEADERS}" "X-Frame-Options"
assert_header_contains "${LOGIN_HEADERS}" "X-Frame-Options" "DENY"
assert_header_present "${LOGIN_HEADERS}" "X-Content-Type-Options"
assert_header_contains "${LOGIN_HEADERS}" "X-Content-Type-Options" "nosniff"
assert_header_present "${LOGIN_HEADERS}" "Referrer-Policy"
assert_header_contains "${LOGIN_HEADERS}" "Referrer-Policy" "strict-origin-when-cross-origin"
assert_header_present "${LOGIN_HEADERS}" "Permissions-Policy"
assert_header_contains "${LOGIN_HEADERS}" "Permissions-Policy" "geolocation=(), microphone=(), camera=()"
assert_header_present "${LOGIN_HEADERS}" "Content-Security-Policy"
assert_header_contains "${LOGIN_HEADERS}" "Content-Security-Policy" "default-src 'self'"
assert_header_present "${LOGIN_HEADERS}" "X-Request-Id"
assert_header_present "${LOGIN_HEADERS}" "Set-Cookie"
assert_header_contains "${LOGIN_HEADERS}" "Set-Cookie" "HttpOnly"
assert_header_contains "${LOGIN_HEADERS}" "Set-Cookie" "SameSite=Lax"

if [[ "${BASE_URL}" == https://* ]]; then
    assert_header_contains "${LOGIN_HEADERS}" "Set-Cookie" "Secure"
fi

printf 'Header and health checks passed.\n'

cd "${ROOT_DIR}"
DEPLOY_SMOKE_BASE_URL="${BASE_URL}" \
APP_URL="${BASE_URL}" \
vendor/bin/codecept run DeploymentSmoke \
    -o "modules: config: PhpBrowser: url: '${BASE_URL}'" \
    "${CODECEPT_ARGS[@]}"

php "${ROOT_DIR}/scripts/log-operations-event.php" \
    info \
    deployment.smoke.completed \
    "Deployment smoke completed." \
    deployment-smoke >/dev/null 2>&1 || true
