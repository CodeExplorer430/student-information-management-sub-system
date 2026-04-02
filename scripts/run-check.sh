#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [[ "${SIMS_VALIDATOR:-0}" == "1" ]]; then
    exec composer check:strict "$@"
fi

exec bash "${ROOT_DIR}/scripts/run-validator.sh" composer check "$@"
