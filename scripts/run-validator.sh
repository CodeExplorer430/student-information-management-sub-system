#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMAGE_TAG="${SIMS_VALIDATOR_IMAGE:-sims-validator:php84}"
HOST_CACHE_DIR="${TMPDIR:-/tmp}/sims-validator-composer-cache"

if ! command -v docker >/dev/null 2>&1; then
    cat >&2 <<'EOF'
Docker is required to run the repo-owned validator.

Install Docker, then rerun `composer check`, or use a coverage-capable host runtime and call `composer check:strict` directly.
EOF
    exit 1
fi

mkdir -p "${HOST_CACHE_DIR}"

if [[ $# -eq 0 ]]; then
    set -- composer check
fi

docker build --tag "${IMAGE_TAG}" --file "${ROOT_DIR}/docker/validator/Dockerfile" "${ROOT_DIR}"

docker run --rm \
    --init \
    --user "$(id -u):$(id -g)" \
    --env HOME=/tmp/validator-home \
    --env COMPOSER_HOME=/tmp/composer-home \
    --env COMPOSER_CACHE_DIR=/tmp/composer-cache \
    --env XDG_CACHE_HOME=/tmp/xdg-cache \
    --env SIMS_VALIDATOR=1 \
    --volume "${ROOT_DIR}:/workspace" \
    --volume "${HOST_CACHE_DIR}:/tmp/composer-cache" \
    --workdir /workspace \
    "${IMAGE_TAG}" \
    "$@"
