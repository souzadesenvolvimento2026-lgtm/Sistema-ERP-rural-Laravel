#!/usr/bin/env bash
set -Eeuo pipefail

REPOSITORY_DIR="${REPOSITORY_DIR:-/home/higor/Sistema-ERP-rural-Laravel}"
DEPLOY_REF="${1:-origin/refactor/separacao-regras-negocio}"

cd "$REPOSITORY_DIR"

git fetch --prune origin

if [[ "$DEPLOY_REF" == origin/* ]]; then
    BRANCH="${DEPLOY_REF#origin/}"
    git checkout "$BRANCH"
    git pull --ff-only origin "$BRANCH"
else
    git rev-parse --verify "$DEPLOY_REF^{commit}" >/dev/null
fi

./deploy/development.sh "$DEPLOY_REF"
