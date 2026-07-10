#!/usr/bin/env bash
set -Eeuo pipefail

REPOSITORY_DIR="${REPOSITORY_DIR:-/home/higor/Sistema-ERP-rural-Laravel}"
APP_DIR="${APP_DIR:-/var/www/erp-rural/Sistema-ERP-rural-Laravel}"
DEPLOY_REF="${1:-origin/main}"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/farmfort}"
STAMP="$(date +%Y%m%d-%H%M%S)"
ARCHIVE="$(mktemp --suffix=.tar)"
RELEASE="$(mktemp -d)"
MAINTENANCE=0

cleanup() {
    rm -f "$ARCHIVE"
    rm -rf "$RELEASE"
}

on_error() {
    status=$?
    echo "Deploy interrompido (codigo $status). Backup: $BACKUP_ROOT/$STAMP" >&2
    if (( MAINTENANCE )); then
        sudo -u "$WEB_USER" php "$APP_DIR/artisan" up || true
    fi
    exit "$status"
}

trap cleanup EXIT
trap on_error ERR

for command in git rsync php composer npm sudo tar; do
    command -v "$command" >/dev/null || { echo "Comando ausente: $command" >&2; exit 1; }
done

cd "$REPOSITORY_DIR"
git fetch --prune origin
git rev-parse --verify "$DEPLOY_REF^{commit}" >/dev/null
git archive --format=tar --output="$ARCHIVE" "$DEPLOY_REF"
tar -xf "$ARCHIVE" -C "$RELEASE"

php -l "$RELEASE/artisan" >/dev/null
composer validate --no-check-publish --working-dir="$RELEASE"

sudo mkdir -p "$BACKUP_ROOT/$STAMP"
sudo rsync -a --delete \
    --no-owner --no-group --no-perms \
    --exclude='.env' \
    --exclude='storage/' \
    --exclude='vendor/' \
    --exclude='node_modules/' \
    --exclude='public/uploads/' \
    "$APP_DIR/" "$BACKUP_ROOT/$STAMP/"

sudo -u "$WEB_USER" php "$APP_DIR/artisan" down --retry=15
MAINTENANCE=1

sudo rsync -a --delete \
    --no-owner --no-group --no-perms \
    --exclude='.env' \
    --exclude='storage/' \
    --exclude='vendor/' \
    --exclude='node_modules/' \
    --exclude='public/uploads/' \
    "$RELEASE/" "$APP_DIR/"

sudo chown "$(id -un):$WEB_GROUP" "$APP_DIR"
sudo chmod 750 "$APP_DIR"
sudo chown -R "$(id -un):$WEB_GROUP" "$APP_DIR/vendor" "$APP_DIR/node_modules" "$APP_DIR/public/build" 2>/dev/null || true

composer install --working-dir="$APP_DIR" --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci --prefix "$APP_DIR" --ignore-scripts
npm run build --prefix "$APP_DIR"

sudo chown -R "$WEB_USER:$WEB_GROUP" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
sudo find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 775 {} +
sudo find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type f -exec chmod 664 {} +

sudo -u "$WEB_USER" php "$APP_DIR/artisan" migrate --force
sudo -u "$WEB_USER" php "$APP_DIR/artisan" optimize:clear
sudo -u "$WEB_USER" php "$APP_DIR/artisan" view:cache
sudo -u "$WEB_USER" php "$APP_DIR/artisan" route:list >/dev/null
sudo -u "$WEB_USER" php "$APP_DIR/artisan" up
MAINTENANCE=0

echo "Deploy concluido: $DEPLOY_REF"
echo "Backup: $BACKUP_ROOT/$STAMP"
