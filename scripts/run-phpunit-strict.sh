#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

"${ROOT_DIR}/vendor/bin/phpunit" \
    --fail-on-warning \
    --fail-on-risky \
    --fail-on-deprecation \
    --fail-on-phpunit-deprecation \
    --fail-on-phpunit-warning \
    --fail-on-notice \
    --display-warnings \
    --display-deprecations \
    --display-phpunit-deprecations \
    --display-notices \
    --display-errors \
    "$@"
