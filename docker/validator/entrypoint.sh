#!/usr/bin/env bash

set -euo pipefail

export COMPOSER_ALLOW_SUPERUSER=1
export HOME="${HOME:-/tmp/validator-home}"
export COMPOSER_HOME="${COMPOSER_HOME:-/tmp/composer-home}"
export COMPOSER_CACHE_DIR="${COMPOSER_CACHE_DIR:-/tmp/composer-cache}"
export XDG_CACHE_HOME="${XDG_CACHE_HOME:-/tmp/xdg-cache}"

mkdir -p "${HOME}" "${COMPOSER_HOME}" "${COMPOSER_CACHE_DIR}" "${XDG_CACHE_HOME}"

if [[ $# -eq 0 ]]; then
    set -- composer check
fi

composer install --no-interaction --prefer-dist

exec "$@"
