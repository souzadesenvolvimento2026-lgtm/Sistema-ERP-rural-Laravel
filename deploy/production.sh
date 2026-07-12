#!/usr/bin/env bash
set -Eeuo pipefail

REF="${1:-}"
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET_DIR="${FARMFORT_TARGET_DIR:-/var/www/erp-rural/Sistema-ERP-rural-Laravel}"
BACKUP_DIR="${FARMFORT_BACKUP_DIR:-/home/higor/backups/farmfort}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
EXPECTED_DB_VERSION="${EXPECTED_DB_VERSION:-11.8}"
APPROVAL_FILE="$REPO_DIR/.deploy/homologacao.aprovada"
BUILD_DIR=""
ROLLBACK_DIR=""
APP_BACKUP=""
DB_BACKUP=""
ROLLBACK_READY=0
MAINTENANCE_ACTIVE=0

fail() {
    echo "ERRO: $*" >&2
    exit 1
}

require_file() {
    [[ -f "$1" ]] || fail "Arquivo obrigatorio ausente: $1"
}

require_dir() {
    [[ -d "$1" ]] || fail "Diretorio obrigatorio ausente: $1"
}

env_value() {
    local file="$1"
    local key="$2"
    local value
    value="$(grep -E "^${key}=" "$file" | tail -n 1 | cut -d= -f2-)"
    value="${value%\"}"
    value="${value#\"}"
    printf '%s' "$value"
}

as_www_data() {
    sudo -u www-data "$@"
}

cleanup() {
    cd "$REPO_DIR" >/dev/null 2>&1 || true
    if [[ -n "$BUILD_DIR" && -d "$BUILD_DIR" ]]; then
        git -C "$REPO_DIR" worktree remove --force "$BUILD_DIR" >/dev/null 2>&1 || true
    fi
    if [[ -n "$ROLLBACK_DIR" && -d "$ROLLBACK_DIR" ]]; then
        sudo rm -rf "$ROLLBACK_DIR" >/dev/null 2>&1 || true
    fi
}

rollback() {
    local exit_code="$1"
    trap - ERR
    set +e

    echo "Falha apos a entrada em manutencao. Iniciando rollback automatico." >&2

    if [[ "$ROLLBACK_READY" -eq 1 ]]; then
        ROLLBACK_DIR="$(mktemp -d /tmp/farmfort-rollback-XXXXXX)"
        sudo tar -xzf "$APP_BACKUP" -C "$ROLLBACK_DIR"
        sudo rsync -a --delete "$ROLLBACK_DIR/$(basename "$TARGET_DIR")/" "$TARGET_DIR/"

        sudo cp "$TARGET_DIR/.env" "$BUILD_DIR/.env"
        sudo chown "$(id -u):$(id -g)" "$BUILD_DIR/.env"
        "$PHP_BIN" "$BUILD_DIR/artisan" farmfort:restaurar-banco "$DB_BACKUP" --no-interaction
    fi

    if [[ -f "$TARGET_DIR/artisan" ]]; then
        as_www_data "$PHP_BIN" "$TARGET_DIR/artisan" up
    fi

    echo "Rollback executado. Verifique os logs antes de tentar novamente." >&2
    exit "$exit_code"
}

on_error() {
    local exit_code=$?
    if [[ "$MAINTENANCE_ACTIVE" -eq 1 ]]; then
        rollback "$exit_code"
    fi
    exit "$exit_code"
}

trap on_error ERR
trap cleanup EXIT

[[ "$REF" == "origin/main" ]] || fail "Uso permitido: ./deploy/production.sh origin/main"

command -v git >/dev/null || fail "git nao encontrado."
command -v "$PHP_BIN" >/dev/null || fail "PHP nao encontrado."
command -v "$COMPOSER_BIN" >/dev/null || fail "Composer nao encontrado."
command -v "$NPM_BIN" >/dev/null || fail "npm nao encontrado."
command -v rsync >/dev/null || fail "rsync nao encontrado."
command -v curl >/dev/null || fail "curl nao encontrado."

require_file "$TARGET_DIR/.env"
require_dir "$TARGET_DIR/storage"
require_dir "$TARGET_DIR/public/uploads"
require_file "$REPO_DIR/.env.testing"
require_file "$APPROVAL_FILE"

git -C "$REPO_DIR" fetch origin main
COMMIT="$(git -C "$REPO_DIR" rev-parse 'origin/main^{commit}')"
APPROVED_COMMIT="$(tr -d '[:space:]' < "$APPROVAL_FILE")"
[[ "$APPROVED_COMMIT" == "$COMMIT" ]] || fail "Este commit ainda nao foi aprovado em homologacao."

BUILD_DIR="$(mktemp -d /tmp/farmfort-build-XXXXXX)"
git -C "$REPO_DIR" worktree add --detach "$BUILD_DIR" "$COMMIT"

require_file "$BUILD_DIR/composer.lock"
require_file "$BUILD_DIR/package-lock.json"
require_file "$BUILD_DIR/database/schema/farmflow-schema.sql"
cp "$REPO_DIR/.env.testing" "$BUILD_DIR/.env.testing"

PRODUCTION_DB="$(env_value "$TARGET_DIR/.env" DB_DATABASE)"
TEST_DB="$(env_value "$BUILD_DIR/.env.testing" DB_DATABASE)"
[[ -n "$PRODUCTION_DB" ]] || fail "DB_DATABASE nao definido na producao."
[[ -n "$TEST_DB" ]] || fail "DB_DATABASE nao definido em .env.testing."
[[ "$PRODUCTION_DB" != "$TEST_DB" ]] || fail "Os testes nao podem usar o banco de producao."

cd "$BUILD_DIR"
"$COMPOSER_BIN" validate --no-check-publish
"$COMPOSER_BIN" install --no-interaction --prefer-dist --no-progress
"$NPM_BIN" ci
"$NPM_BIN" run build
"$PHP_BIN" artisan farmfort:verificar-banco --mariadb-version="$EXPECTED_DB_VERSION" --env=testing --no-interaction
"$PHP_BIN" artisan test
"$PHP_BIN" artisan route:list
"$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress

TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
sudo install -d -m 750 -o "$(id -un)" -g "$(id -gn)" "$BACKUP_DIR"
APP_BACKUP="$BACKUP_DIR/aplicacao-$TIMESTAMP.tar.gz"
DB_BACKUP="$BACKUP_DIR/banco-$TIMESTAMP.sql"

sudo tar -czf "$APP_BACKUP" -C "$(dirname "$TARGET_DIR")" "$(basename "$TARGET_DIR")"
cp "$TARGET_DIR/.env" "$BUILD_DIR/.env"
"$PHP_BIN" artisan farmfort:backup-banco "$DB_BACKUP" --no-interaction
ROLLBACK_READY=1

MAINTENANCE_ACTIVE=1
as_www_data "$PHP_BIN" "$TARGET_DIR/artisan" down --retry=60

sudo rsync -a --delete \
    --exclude='.env' \
    --exclude='.env.testing' \
    --exclude='.git/' \
    --exclude='node_modules/' \
    --exclude='storage/' \
    --exclude='public/uploads/' \
    "$BUILD_DIR/" "$TARGET_DIR/"

as_www_data "$PHP_BIN" "$TARGET_DIR/artisan" migrate --force --no-interaction
as_www_data "$PHP_BIN" "$TARGET_DIR/artisan" optimize:clear
as_www_data "$PHP_BIN" "$TARGET_DIR/artisan" config:cache
as_www_data "$PHP_BIN" "$TARGET_DIR/artisan" route:cache
as_www_data "$PHP_BIN" "$TARGET_DIR/artisan" view:cache

sudo chown -R www-data:www-data "$TARGET_DIR/storage" "$TARGET_DIR/bootstrap/cache" "$TARGET_DIR/public/uploads"
sudo find "$TARGET_DIR/storage" "$TARGET_DIR/bootstrap/cache" "$TARGET_DIR/public/uploads" -type d -exec chmod 775 {} \;
sudo find "$TARGET_DIR/storage" "$TARGET_DIR/bootstrap/cache" "$TARGET_DIR/public/uploads" -type f -exec chmod 664 {} \;

as_www_data "$PHP_BIN" "$TARGET_DIR/artisan" up

APP_URL="$(env_value "$TARGET_DIR/.env" APP_URL)"
[[ -n "$APP_URL" ]] || fail "APP_URL nao definido na producao."
curl --fail --silent --show-error --max-time 20 "${APP_URL%/}/login" >/dev/null
curl --fail --silent --show-error --max-time 20 "${APP_URL%/}/up" >/dev/null

printf 'Commit: %s\nPublicado em: %s\n' "$COMMIT" "$(date --iso-8601=seconds)" | sudo tee "$TARGET_DIR/storage/framework/farmfort-smoke-pendente" >/dev/null
sudo chown www-data:www-data "$TARGET_DIR/storage/framework/farmfort-smoke-pendente"
rm -f "$APPROVAL_FILE"
MAINTENANCE_ACTIVE=0

echo "Arquivos publicados e verificacoes HTTP concluidas."
echo "O deploy ainda depende do teste rapido no navegador."
echo "Execute: ./deploy/confirm-smoke.sh"
echo "Monitore: tail -f $TARGET_DIR/storage/logs/laravel.log"
echo "Monitore: sudo tail -f /var/log/nginx/erp-rural.error.log"
