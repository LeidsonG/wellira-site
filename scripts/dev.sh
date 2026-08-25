#!/usr/bin/env bash
# =============================================================================
# Servidor de desenvolvimento
# =============================================================================
# Existe para que o local se comporte como a HostGator em duas coisas que o
# `php -S` sozinho não faz:
#
#   1. Roteamento. O servidor embutido não lê .htaccess, então tools/dev-router.php
#      reproduz a reescrita /<slug> -> oferta.php e os bloqueios de acesso.
#
#   2. Limites de upload. O servidor embutido também não lê .user.ini, e a
#      instalação padrão do PHP traz upload_max_filesize = 2M. O painel mostra
#      sempre o limite REAL (o menor entre o teto do código e o do servidor),
#      então sem estes -d a aba Imagens anuncia "até 2 MB cada" e parece que a
#      constante de 8 MB não está valendo. Os valores repetem o .user.ini.
#
# Era um comando longo demais para digitar de memória, e digitado pela metade
# reintroduzia justamente o sintoma que ele existe para evitar.

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
