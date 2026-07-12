#!/usr/bin/env bash
set -Eeuo pipefail

REPOSITORY_DIR="${REPOSITORY_DIR:-/home/higor/Sistema-ERP-rural-Laravel}"
DEPLOY_REF="${1:-origin/refactor/separacao-regras-negocio}"
SUDO_KEEPALIVE_PID=""

cleanup() {
    if [[ -n "$SUDO_KEEPALIVE_PID" ]]; then
        kill "$SUDO_KEEPALIVE_PID" 2>/dev/null || true
    fi
}

trap cleanup EXIT

cd "$REPOSITORY_DIR"

git fetch --prune origin

if [[ "$DEPLOY_REF" == origin/* ]]; then
    BRANCH="${DEPLOY_REF#origin/}"
    REMOTE_COMMIT="$(git ls-remote origin "refs/heads/$BRANCH" | awk '{print $1}')"

    if [[ -z "$REMOTE_COMMIT" ]]; then
        echo "Branch remota nao encontrada no GitHub: origin/$BRANCH" >&2
        exit 1
    fi

    git checkout "$BRANCH"
    git pull --ff-only origin "$BRANCH"

    LOCAL_COMMIT="$(git rev-parse HEAD)"
    TRACKING_COMMIT="$(git rev-parse "origin/$BRANCH")"

    if [[ "$LOCAL_COMMIT" != "$REMOTE_COMMIT" || "$TRACKING_COMMIT" != "$REMOTE_COMMIT" ]]; then
        echo "Servidor nao chegou ao commit mais recente do GitHub." >&2
        echo "GitHub: $REMOTE_COMMIT" >&2
        echo "Local:  $LOCAL_COMMIT" >&2
        echo "Origin: $TRACKING_COMMIT" >&2
        exit 1
    fi

    DEPLOY_REF="origin/$BRANCH"
else
    REMOTE_COMMIT="$(git rev-parse --verify "$DEPLOY_REF^{commit}")"
fi

echo "Atualizacao pronta para deploy."
echo "Referencia: $DEPLOY_REF"
echo "Commit:     $REMOTE_COMMIT"

sudo -v
while true; do
    sudo -n true 2>/dev/null || true
    sleep 45
done &
SUDO_KEEPALIVE_PID="$!"

./deploy/development.sh "$DEPLOY_REF"
