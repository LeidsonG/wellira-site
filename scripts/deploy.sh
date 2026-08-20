#!/usr/bin/env bash
#
# Deploy para a HostGator por rsync sobre SSH.
#
# ⚠️  A REGRA MAIS IMPORTANTE DESTE ARQUIVO
#
# As ofertas, os vídeos e as imagens que a cliente cria pelo painel existem
# SOMENTE no servidor. Não estão no git, não estão na sua máquina, e não há
# outra cópia. Um --delete sem exclusão apaga o trabalho dela para sempre.
#
# Por isso este script:
#   1. nunca envia dados/ofertas, assets/videos nem assets/img/uploads
#   2. nunca envia inc/senha.php, que é do servidor
#   3. roda em modo simulação por padrão — só envia de verdade com --real
#
set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RAIZ"

# ---------------------------------------------------------------------------
# Credenciais
# ---------------------------------------------------------------------------
# Ficam em deploy.conf, fora do git. Modelo em scripts/deploy.conf.exemplo.

CONF="$RAIZ/deploy.conf"
if [[ ! -f "$CONF" ]]; then
  echo "ERRO: falta o arquivo deploy.conf na raiz do projeto." >&2
  echo "Copie scripts/deploy.conf.exemplo para deploy.conf e preencha." >&2
  exit 1
fi
# shellcheck source=/dev/null
source "$CONF"

: "${SSH_USUARIO:?defina SSH_USUARIO em deploy.conf}"
: "${SSH_HOST:?defina SSH_HOST em deploy.conf}"
: "${SSH_PORTA:=22}"
: "${DESTINO:?defina DESTINO em deploy.conf (ex: /home/usuario/public_html)}"

# ---------------------------------------------------------------------------
# O que NÃO vai
# ---------------------------------------------------------------------------
#
# Conteúdo da cliente e segredos vêm primeiro, porque são os que doem.

EXCLUIR=(
  # --- Conteúdo da cliente: existe só no servidor ---
  --exclude 'dados/ofertas/'
  --exclude 'dados/backups/'
  --exclude 'dados/cliques/'
  --exclude 'dados/tentativas-*.json'
  --exclude 'assets/videos/*'
  --exclude 'assets/img/uploads/*'

  # --- Segredos ---
  --exclude 'inc/senha.php'
  --exclude 'deploy.conf'
  --exclude '.env*'

  # --- Não pertence a produção ---
  --exclude '.git/'
  --exclude '.gitignore'
  --exclude 'scripts/'
  --exclude 'tools/'
  --exclude '*.md'
  --exclude '.nojekyll'

  # --- Protótipos estáticos: existem para a prévia do GitHub Pages ---
  --exclude 'vitalane.html'
  --exclude 'hydrasource.html'

  # --- Lixo de editor e sistema ---
  --exclude '.DS_Store'
  --exclude 'Thumbs.db'
  --exclude '*.swp'
  --exclude '*.tmp'
  --exclude '.vscode/'
  --exclude '.idea/'
)

# As pastas de conteúdo são excluídas com barra ('dados/ofertas/') e não com
# '/*': assim o rsync nem cria nem toca a pasta, mas os .htaccess que a protegem
# continuam sendo enviados, porque são versionados e moram um nível acima.

# ---------------------------------------------------------------------------
# Simulação por padrão
# ---------------------------------------------------------------------------

MODO_REAL=0
USAR_DELETE=0

for arg in "$@"; do
  case "$arg" in
    --real)   MODO_REAL=1 ;;
    --delete) USAR_DELETE=1 ;;
    *) echo "Argumento desconhecido: $arg" >&2; exit 1 ;;
  esac
done

OPCOES=(-avz --human-readable --itemize-changes
        -e "ssh -p ${SSH_PORTA}"
        --chmod=D755,F644)

if [[ $USAR_DELETE -eq 1 ]]; then
  OPCOES+=(--delete)
  echo "⚠️  MODO --delete: arquivos que não existem aqui serão APAGADOS lá."
  echo "    As pastas de conteúdo da cliente estão excluídas e não serão tocadas."
fi

if [[ $MODO_REAL -eq 0 ]]; then
  OPCOES+=(--dry-run)
fi

# ---------------------------------------------------------------------------
# Envio
# ---------------------------------------------------------------------------

echo "Origem : $RAIZ/"
echo "Destino: ${SSH_USUARIO}@${SSH_HOST}:${DESTINO}/"
echo

rsync "${OPCOES[@]}" "${EXCLUIR[@]}" "$RAIZ/" "${SSH_USUARIO}@${SSH_HOST}:${DESTINO}/"

echo
if [[ $MODO_REAL -eq 0 ]]; then
  echo "──────────────────────────────────────────────────────────────"
  echo "SIMULAÇÃO. Nada foi enviado."
  echo "Confira a lista acima. Se estiver certa, rode de novo com --real"
  echo "──────────────────────────────────────────────────────────────"
else
  echo "Enviado."
  echo
  echo "Confira no servidor, se for o primeiro deploy:"
  echo "  - inc/senha.php existe? (gere com: php tools/gerar-hash.php \"senha\")"
  echo "  - dados/, dados/backups/, dados/cliques/ têm permissão de escrita?"
  echo "  - assets/videos/ e assets/img/uploads/ têm permissão de escrita?"
fi
