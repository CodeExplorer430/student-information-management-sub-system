#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_CS_FIXER_PHP_BIN:-}"

if [[ -z "${PHP_BIN}" ]]; then
    if command -v php8.4 >/dev/null 2>&1; then
        PHP_BIN="php8.4"
    else
        PHP_BIN="php"
    fi
fi

OUTPUT_FILE="$(mktemp)"
trap 'rm -f "${OUTPUT_FILE}"' EXIT

set +e
"${PHP_BIN}" "${ROOT_DIR}/vendor/bin/php-cs-fixer" "$@" >"${OUTPUT_FILE}" 2>&1
STATUS=$?
set -e

awk '
    BEGIN {
        skipping = 0
    }
    /^You are running PHP CS Fixer on PHP / {
        skipping = 1
        next
    }
    skipping == 1 && /^$/ {
        skipping = 0
        next
    }
    skipping == 0 {
        print
    }
' "${OUTPUT_FILE}"

exit "${STATUS}"
