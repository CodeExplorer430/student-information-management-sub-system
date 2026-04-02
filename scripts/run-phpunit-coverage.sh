#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MINIMUM_LINE_COVERAGE="${PHPUNIT_COVERAGE_MIN_LINE:-100}"
MINIMUM_METHOD_COVERAGE="${PHPUNIT_COVERAGE_MIN_METHOD:-100}"
MINIMUM_CLASS_COVERAGE="${PHPUNIT_COVERAGE_MIN_CLASS:-100}"
PHP_MEMORY_LIMIT="${PHPUNIT_MEMORY_LIMIT:-512M}"
BUILD_DIR="${ROOT_DIR}/build/coverage"
CLOVER_FILE="${BUILD_DIR}/clover.xml"
HTML_DIR="${BUILD_DIR}/html"

coverage_runner=("php" "-d" "memory_limit=${PHP_MEMORY_LIMIT}")

if php -m | grep -qi '^xdebug$'; then
    export XDEBUG_MODE=coverage
elif php -m | grep -qi '^pcov$'; then
    :
elif command -v phpdbg >/dev/null 2>&1 && phpdbg -qrr "${ROOT_DIR}/vendor/bin/phpunit" --version >/dev/null 2>&1; then
    coverage_runner=("phpdbg" "-d" "memory_limit=${PHP_MEMORY_LIMIT}" "-qrr")
else
    cat >&2 <<'EOF'
No compatible local PHPUnit coverage driver is available.

Install Xdebug or PCOV for the active PHP CLI runtime, or provide a compatible phpdbg binary.
CI installs Xdebug automatically and will enforce the repository coverage floor there as well.
EOF
    exit 1
fi

rm -rf "${BUILD_DIR}"
mkdir -p "${HTML_DIR}"

phpunit_args=(
    "--fail-on-warning"
    "--fail-on-risky"
    "--fail-on-deprecation"
    "--fail-on-phpunit-deprecation"
    "--fail-on-phpunit-warning"
    "--fail-on-notice"
    "--display-warnings"
    "--display-deprecations"
    "--display-phpunit-deprecations"
    "--display-notices"
    "--display-errors"
    "--coverage-clover" "${CLOVER_FILE}"
    "--coverage-html" "${HTML_DIR}"
    "--coverage-text=php://stdout"
    "--only-summary-for-coverage-text"
)

"${coverage_runner[@]}" "${ROOT_DIR}/vendor/bin/phpunit" "${phpunit_args[@]}" "$@"
php "${ROOT_DIR}/scripts/check-coverage-threshold.php" \
    "${CLOVER_FILE}" \
    "${MINIMUM_LINE_COVERAGE}" \
    "${MINIMUM_METHOD_COVERAGE}" \
    "${MINIMUM_CLASS_COVERAGE}"
