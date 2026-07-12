#!/usr/bin/env bash
set -Eeuo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REF="${1:-origin/main}"
APPROVAL_DIR="$REPO_DIR/.deploy"
APPROVAL_FILE="$APPROVAL_DIR/homologacao.aprovada"

if [[ "$REF" != "origin/main" ]]; then
    echo "A aprovacao deve apontar exatamente para origin/main." >&2
    exit 1
fi

git -C "$REPO_DIR" fetch origin main
COMMIT="$(git -C "$REPO_DIR" rev-parse "$REF^{commit}")"

echo "Commit candidato: $COMMIT"
echo "Confirme antes: CI verde, build aprovado e homologacao validada no navegador."
read -r -p "Digite HOMOLOGACAO APROVADA para liberar este commit: " CONFIRMACAO

if [[ "$CONFIRMACAO" != "HOMOLOGACAO APROVADA" ]]; then
    echo "Aprovacao cancelada." >&2
    exit 1
fi

mkdir -p "$APPROVAL_DIR"
printf '%s\n' "$COMMIT" > "$APPROVAL_FILE"
chmod 600 "$APPROVAL_FILE"
echo "Homologacao registrada para $COMMIT."
