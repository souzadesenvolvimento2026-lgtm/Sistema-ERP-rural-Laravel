#!/usr/bin/env bash
set -Eeuo pipefail

TARGET_DIR="${FARMFORT_TARGET_DIR:-/var/www/erp-rural/Sistema-ERP-rural-Laravel}"
PENDING_FILE="$TARGET_DIR/storage/framework/farmfort-smoke-pendente"

if [[ ! -f "$PENDING_FILE" ]]; then
    echo "Nao existe teste rapido pendente para esta instalacao." >&2
    exit 1
fi

echo "Confirme no navegador:"
echo "1. Login abriu e autenticou."
echo "2. Dashboard carregou."
echo "3. Mapa dos talhoes abriu."
echo "4. Uma exclusao de teste foi salva e depois removida."
echo "5. Financeiro, Fiscal, Compras e Patrimonio abriram."
echo "6. Logs do Laravel e do Nginx foram consultados sem erro novo."
read -r -p "Digite APROVADO para concluir o deploy: " CONFIRMACAO

if [[ "$CONFIRMACAO" != "APROVADO" ]]; then
    echo "O deploy continua pendente de validacao." >&2
    exit 1
fi

sudo rm -f "$PENDING_FILE"
echo "Teste rapido confirmado. Deploy concluido."
