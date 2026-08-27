#!/usr/bin/env bash
# =============================================================================
# Servidor de desenvolvimento
# =============================================================================
# Repõe duas coisas que o `php -S` sozinho ignora, mas valem em produção:
#
#   1. Roteamento. tools/dev-router.php reproduz o .htaccess: reescrita de
#      /<slug> para oferta.php e os bloqueios de acesso.
#
#   2. Limites de upload. Sem os -d abaixo (que repetem o .user.ini), o PHP
#      local cai nos 2M padrão e a aba Imagens anuncia "até 2 MB cada".

set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORTA="${1:-8000}"

if ! command -v php >/dev/null 2>&1; then
  echo "ERRO: o php nao esta instalado." >&2
  exit 1
fi

echo "Painel:      http://localhost:${PORTA}/admin/"
echo "Institucional: http://localhost:${PORTA}/"
echo "Oferta:      http://localhost:${PORTA}/vitalane"
echo

cd "$RAIZ"
exec php -S "localhost:${PORTA}" \
  -d upload_max_filesize=128M \
  -d post_max_size=136M \
  tools/dev-router.php
