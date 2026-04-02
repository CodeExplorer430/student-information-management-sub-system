#!/usr/bin/env bash

set -euo pipefail

APP_ROOT=""
RELEASE_DIR=""
PUBLIC_URL=""
KEEP_RELEASES=5
PHP_BIN="php"
COMPOSER_BIN="composer"
PUSH_REMOTE=false

usage() {
    printf 'Usage: %s --app-root=<path> --release-dir=<path> [--public-url=<url>] [--php-bin=<bin>] [--composer-bin=<bin>] [--keep-releases=<n>] [--push-remote]\n' "$(basename "$0")" >&2
}

for ARGUMENT in "$@"; do
    case "${ARGUMENT}" in
        --app-root=*)
            APP_ROOT="${ARGUMENT#*=}"
            ;;
        --release-dir=*)
            RELEASE_DIR="${ARGUMENT#*=}"
            ;;
        --public-url=*)
            PUBLIC_URL="${ARGUMENT#*=}"
            ;;
        --keep-releases=*)
            KEEP_RELEASES="${ARGUMENT#*=}"
            ;;
        --php-bin=*)
            PHP_BIN="${ARGUMENT#*=}"
            ;;
        --composer-bin=*)
            COMPOSER_BIN="${ARGUMENT#*=}"
            ;;
        --push-remote)
            PUSH_REMOTE=true
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            usage
            printf 'Unknown argument: %s\n' "${ARGUMENT}" >&2
            exit 1
            ;;
    esac
done

if [[ -z "${APP_ROOT}" || -z "${RELEASE_DIR}" ]]; then
    usage
    exit 1
fi

if ! [[ "${KEEP_RELEASES}" =~ ^[0-9]+$ ]] || [[ "${KEEP_RELEASES}" -lt 1 ]]; then
    printf 'keep-releases must be a positive integer.\n' >&2
    exit 1
fi

APP_ROOT="${APP_ROOT%/}"
RELEASE_DIR="${RELEASE_DIR%/}"
SHARED_DIR="${APP_ROOT}/shared"
CURRENT_LINK="${APP_ROOT}/current"
BACKUP_ID=""

require_path() {
    local path="$1"

    if [[ ! -e "${path}" ]]; then
        printf 'Required path missing: %s\n' "${path}" >&2
        exit 1
    fi
}

ensure_dir() {
    local path="$1"

    mkdir -p "${path}"
}

link_shared_path() {
    local relative_path="$1"
    local source_path="${SHARED_DIR}/${relative_path}"
    local target_path="${RELEASE_DIR}/${relative_path}"

    ensure_dir "$(dirname "${source_path}")"
    ensure_dir "$(dirname "${target_path}")"

    rm -rf "${target_path}"
    ln -s "${source_path}" "${target_path}"
}

prune_old_releases() {
    local current_target="$1"

    mapfile -t release_paths < <(find "${APP_ROOT}/releases" -mindepth 1 -maxdepth 1 -type d | sort)

    if [[ "${#release_paths[@]}" -le "${KEEP_RELEASES}" ]]; then
        return
    fi

    local delete_count=$(( ${#release_paths[@]} - KEEP_RELEASES ))
    local deleted=0

    for release_path in "${release_paths[@]}"; do
        if [[ "$(readlink -f "${release_path}")" == "${current_target}" ]]; then
            continue
        fi

        rm -rf "${release_path}"
        deleted=$((deleted + 1))

        if [[ "${deleted}" -ge "${delete_count}" ]]; then
            break
        fi
    done
}

require_path "${RELEASE_DIR}"
ensure_dir "${APP_ROOT}/releases"
ensure_dir "${SHARED_DIR}"
ensure_dir "${SHARED_DIR}/storage/logs"
ensure_dir "${SHARED_DIR}/storage/framework/sessions"
ensure_dir "${SHARED_DIR}/storage/app/private/uploads"
ensure_dir "${SHARED_DIR}/storage/app/public/id-cards"
ensure_dir "${SHARED_DIR}/storage/backups/exports"
require_path "${SHARED_DIR}/.env"

cd "${RELEASE_DIR}"

COMPOSER_ALLOW_SUPERUSER=1 "${COMPOSER_BIN}" install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader

rm -f "${RELEASE_DIR}/.env"
ln -s "${SHARED_DIR}/.env" "${RELEASE_DIR}/.env"

link_shared_path "storage/logs"
link_shared_path "storage/framework/sessions"
link_shared_path "storage/app/private/uploads"
link_shared_path "storage/app/public/id-cards"
link_shared_path "storage/backups"

BACKUP_OUTPUT="$("${PHP_BIN}" "${RELEASE_DIR}/bin/console" backup:create)"
printf '%s\n' "${BACKUP_OUTPUT}"
BACKUP_ID="$(printf '%s\n' "${BACKUP_OUTPUT}" | sed -n 's/^Backup ID: //p' | head -n 1)"

if [[ -z "${BACKUP_ID}" ]]; then
    printf 'Failed to parse backup ID from backup:create output.\n' >&2
    exit 1
fi

"${PHP_BIN}" "${RELEASE_DIR}/bin/console" backup:verify "${BACKUP_ID}"

if [[ "${PUSH_REMOTE}" == true ]]; then
    "${PHP_BIN}" "${RELEASE_DIR}/bin/console" backup:export "${BACKUP_ID}"
    "${PHP_BIN}" "${RELEASE_DIR}/bin/console" backup:push "${BACKUP_ID}"
fi

"${PHP_BIN}" "${RELEASE_DIR}/bin/console" migrate
"${PHP_BIN}" "${RELEASE_DIR}/bin/console" env:check
"${PHP_BIN}" "${RELEASE_DIR}/bin/console" health:check
"${PHP_BIN}" "${RELEASE_DIR}/bin/console" health:check --json

TEMP_LINK="${APP_ROOT}/.current-tmp"
ln -sfn "${RELEASE_DIR}" "${TEMP_LINK}"
mv -Tf "${TEMP_LINK}" "${CURRENT_LINK}"

CURRENT_TARGET="$(readlink -f "${CURRENT_LINK}")"
prune_old_releases "${CURRENT_TARGET}"

printf 'Deployment completed successfully.\n'
printf 'Current release: %s\n' "${CURRENT_TARGET}"
printf 'Backup ID: %s\n' "${BACKUP_ID}"

if [[ -n "${PUBLIC_URL}" ]]; then
    printf 'Public readiness URL: %s/health/ready\n' "${PUBLIC_URL%/}"
fi
