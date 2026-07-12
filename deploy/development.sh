#!/usr/bin/env bash
set -Eeuo pipefail

REPOSITORY_DIR="${REPOSITORY_DIR:-/home/higor/Sistema-ERP-rural-Laravel}"
APP_DIR="${APP_DIR:-/var/www/erp-rural/Sistema-ERP-rural-Laravel}"
DEPLOY_REF="${1:-}"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/farmfort-dev}"
RUN_TESTS="${RUN_TESTS:-0}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-0}"
STAMP="$(date +%Y%m%d-%H%M%S)"
ARCHIVE="$(mktemp --suffix=.tar)"
RELEASE="$(mktemp -d)"
BACKUP_DIR="$BACKUP_ROOT/$STAMP"
TARGET_COMMIT=""
MAINTENANCE=0

cleanup() {
    rm -f "$ARCHIVE"
    rm -rf "$RELEASE"
}

rollback() {
    local status=$?

    echo "Deploy dev interrompido (codigo $status)." >&2

    if (( MAINTENANCE )); then
        echo "Restaurando backup: $BACKUP_DIR" >&2
        sudo rsync -a --delete \
            --no-owner --no-group --no-perms \
            --exclude='.env' \
            --exclude='storage/' \
            --exclude='public/uploads/' \
            "$BACKUP_DIR/" "$APP_DIR/" || true

        sudo -u "$WEB_USER" php "$APP_DIR/artisan" up || true
    fi

    exit "$status"
}

trap cleanup EXIT
trap rollback ERR

require_command() {
    command -v "$1" >/dev/null || {
        echo "Comando ausente: $1" >&2
        exit 1
    }
}

require_path() {
    local path="$1"
    local description="$2"

    [[ -e "$path" ]] || {
        echo "Arquivo obrigatorio ausente: $description ($path)" >&2
        exit 1
    }
}

if [[ -z "$DEPLOY_REF" ]]; then
    echo "Uso: ./deploy/development.sh origin/nome-da-branch" >&2
    exit 1
fi

for command in composer git npm php rsync sudo tar; do
    require_command "$command"
done

require_path "$REPOSITORY_DIR/.git" "repositorio Git de origem"
require_path "$APP_DIR/.env" ".env do destino"
require_path "$APP_DIR/storage" "storage do destino"
require_path "$APP_DIR/public/uploads" "public/uploads"

cd "$REPOSITORY_DIR"
git fetch --prune origin
TARGET_COMMIT="$(git rev-parse --verify "$DEPLOY_REF^{commit}")"
echo "Preparando deploy dev de $DEPLOY_REF"
echo "Commit alvo: $TARGET_COMMIT"
git archive --format=tar --output="$ARCHIVE" "$DEPLOY_REF"
tar -xf "$ARCHIVE" -C "$RELEASE"

require_path "$RELEASE/composer.lock" "composer.lock"
require_path "$RELEASE/package-lock.json" "package-lock.json"
require_path "$RELEASE/artisan" "artisan"

cd "$RELEASE"
composer install --prefer-dist --no-interaction --optimize-autoloader
npm ci
npm run build

if [[ "$RUN_TESTS" == "1" ]]; then
    APP_KEY="${APP_KEY:-base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=}" php artisan test
fi

composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
rm -rf node_modules

sudo mkdir -p "$BACKUP_DIR"
sudo rsync -a --delete \
    --no-owner --no-group --no-perms \
    --exclude='.env' \
    --exclude='storage/' \
    --exclude='public/uploads/' \
    "$APP_DIR/" "$BACKUP_DIR/"

sudo -u "$WEB_USER" php "$APP_DIR/artisan" down --retry=5
MAINTENANCE=1

sudo rsync -a --delete \
    --no-owner --no-group --no-perms \
    --exclude='.env' \
    --exclude='storage/' \
    --exclude='public/uploads/' \
    "$RELEASE/" "$APP_DIR/"

sudo chown "$WEB_USER:$WEB_GROUP" "$APP_DIR" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
sudo chown -R "$WEB_USER:$WEB_GROUP" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
sudo find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 775 {} +
sudo find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type f -exec chmod 664 {} +

if [[ "$RUN_MIGRATIONS" == "1" ]]; then
    sudo -u "$WEB_USER" php "$APP_DIR/artisan" migrate --force
fi

sudo -u "$WEB_USER" php "$APP_DIR/artisan" optimize:clear
sudo -u "$WEB_USER" php "$APP_DIR/artisan" route:list >/dev/null
sudo -u "$WEB_USER" php "$APP_DIR/artisan" up
MAINTENANCE=0

printf '%s\n' "$TARGET_COMMIT" | sudo tee "$APP_DIR/.deploy-commit" >/dev/null
sudo chown "$WEB_USER:$WEB_GROUP" "$APP_DIR/.deploy-commit"

PUBLISHED_COMMIT="$(sudo cat "$APP_DIR/.deploy-commit")"
if [[ "$PUBLISHED_COMMIT" != "$TARGET_COMMIT" ]]; then
    echo "Deploy copiou arquivos, mas o marcador publicado nao confere." >&2
    echo "Esperado:  $TARGET_COMMIT" >&2
    echo "Publicado: $PUBLISHED_COMMIT" >&2
    exit 1
fi

echo "Deploy dev concluido: $DEPLOY_REF"
echo "Commit publicado: $PUBLISHED_COMMIT"
echo "Backup: $BACKUP_DIR"
