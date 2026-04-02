#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STEP="validator"
BACKUP_ID=""
PUSH_REMOTE=false
SMOKE_URL=""

# Scheduled backups should use php bin/console backup:run [--push-remote]
# rather than this release-oriented workflow.

for ARGUMENT in "$@"; do
    case "${ARGUMENT}" in
        --push-remote)
            PUSH_REMOTE=true
            ;;
        --smoke-url=*)
            SMOKE_URL="${ARGUMENT#*=}"
            ;;
        *)
            printf 'Usage: %s [--push-remote] [--smoke-url=<base-url>]\n' "$(basename "$0")" >&2
            exit 1
            ;;
    esac
done

log_failure() {
    php "${ROOT_DIR}/scripts/log-release-event.php" \
        error \
        "Release check failed." \
        "${STEP}" \
        "${BACKUP_ID}" \
        release.failed >/dev/null 2>&1 || true
}

fail_release() {
    local exit_code="${1:-1}"

    log_failure
    printf 'Release check failed during: %s\n' "${STEP}" >&2

    if [[ -n "${BACKUP_ID}" ]]; then
        printf 'Rollback guidance:\n' >&2
        printf '  php bin/console backup:restore %s\n' "${BACKUP_ID}" >&2
        printf '  php bin/console env:check\n' >&2
        printf '  php bin/console health:check\n' >&2
    fi

    exit "${exit_code}"
}

trap 'fail_release $?' ERR

cd "${ROOT_DIR}"

STEP="validator"
bash "${ROOT_DIR}/scripts/run-validator.sh" composer check

STEP="backup:create"
BACKUP_OUTPUT="$(php "${ROOT_DIR}/bin/console" backup:create)"
printf '%s\n' "${BACKUP_OUTPUT}"
BACKUP_ID="$(printf '%s\n' "${BACKUP_OUTPUT}" | sed -n 's/^Backup ID: //p' | head -n 1)"

if [[ -z "${BACKUP_ID}" ]]; then
    STEP="backup:parse"
    fail_release 1
fi

STEP="backup:verify"
php "${ROOT_DIR}/bin/console" backup:verify "${BACKUP_ID}"

if [[ "${PUSH_REMOTE}" == true ]]; then
    STEP="backup:export"
    php "${ROOT_DIR}/bin/console" backup:export "${BACKUP_ID}"

    STEP="backup:push"
    php "${ROOT_DIR}/bin/console" backup:push "${BACKUP_ID}"
fi

STEP="migrate"
php "${ROOT_DIR}/bin/console" migrate

STEP="env:check"
php "${ROOT_DIR}/bin/console" env:check

STEP="health:check"
php "${ROOT_DIR}/bin/console" health:check

STEP="health:check --json"
php "${ROOT_DIR}/bin/console" health:check --json

if [[ -n "${SMOKE_URL}" ]]; then
    STEP="deployment-smoke"
    DEPLOY_SMOKE_BASE_URL="${SMOKE_URL}" bash "${ROOT_DIR}/scripts/deployment-smoke.sh"
fi

STEP="release:complete"
php "${ROOT_DIR}/scripts/log-release-event.php" \
    info \
    "Release check completed." \
    "${STEP}" \
    "${BACKUP_ID}" \
    release.completed >/dev/null 2>&1 || true

printf 'Release check completed successfully.\n'
printf 'Backup ID: %s\n' "${BACKUP_ID}"
printf 'HTTP verification: GET %s/health/ready\n' "${SMOKE_URL:-${APP_URL:-http://127.0.0.1:8000}}"
