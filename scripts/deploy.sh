#!/usr/bin/env bash
#
# Deploy para a HostGator por FTPS explícito (porta 21), usando lftp.
#
# ⚠️  A REGRA MAIS IMPORTANTE DESTE ARQUIVO
#
# As ofertas, os vídeos, as imagens e a SENHA do painel existem SOMENTE no
# servidor. Não estão no git, não estão na sua máquina, e não há outra cópia.
# Um espelhamento com remoção, sem exclusões, apaga o trabalho da cliente para
# sempre.
#
# Por isso este script:
#   1. nunca envia dados/, assets/videos nem assets/img/uploads
#   2. roda em modo simulação por padrão — só envia de verdade com --real
#   3. só remove arquivo remoto com --limpar, e mesmo assim respeitando (1)
#
# ⚠️  FTP puro manda usuário e senha em texto claro pela rede. Este script usa
# FTPS explícito (AUTH TLS) e recusa cair para FTP sem criptografia.
#
set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RAIZ"

if ! command -v lftp >/dev/null 2>&1; then
  cat >&2 <<'FIM'
ERRO: o lftp não está instalado.

  Ubuntu/Debian/WSL:  sudo apt install lftp
  macOS (Homebrew):   brew install lftp

O rsync não serve aqui: a hospedagem oferece FTP, não SSH.
FIM
  exit 1
fi

# ---------------------------------------------------------------------------
# Credenciais
# ---------------------------------------------------------------------------

CONF="$RAIZ/deploy.conf"
if [[ ! -f "$CONF" ]]; then
  echo "ERRO: falta o arquivo deploy.conf na raiz do projeto." >&2
  echo "Copie scripts/deploy.conf.exemplo para deploy.conf e preencha." >&2
  exit 1
fi
# shellcheck source=/dev/null
source "$CONF"

: "${FTP_USUARIO:?defina FTP_USUARIO em deploy.conf}"
: "${FTP_HOST:?defina FTP_HOST em deploy.conf}"
: "${FTP_PORTA:=21}"
: "${DESTINO:=/}"

# A senha não fica no deploy.conf: é perguntada na hora, para não acabar salva
# em disco nem no histórico do shell.
if [[ -z "${FTP_SENHA:-}" ]]; then
  read -rsp "Senha FTP de ${FTP_USUARIO}: " FTP_SENHA
  echo
fi

# ---------------------------------------------------------------------------
# O que NÃO vai
# ---------------------------------------------------------------------------
# Conteúdo da cliente e segredos primeiro, porque são os que doem.

EXCLUIR=(
  # --- Conteúdo da cliente e senha: existem só no servidor ---
  --exclude-glob 'dados/*'
  --exclude-glob 'assets/videos/*'
  --exclude-glob 'assets/img/uploads/*'

  # --- Segredos e controle de versão ---
  --exclude-glob 'deploy.conf'
  --exclude-glob '.env*'
  --exclude-glob '.git/'
  --exclude-glob '.gitignore'

  # --- Não pertence a produção ---
  --exclude-glob 'scripts/'
  --exclude-glob 'tools/'
  --exclude-glob '*.md'
  --exclude-glob '.nojekyll'

  # --- Protótipos estáticos: existem para a prévia do GitHub Pages ---
  --exclude-glob 'vitalane.html'
  --exclude-glob 'hydrasource.html'

  # --- Lixo de editor e sistema ---
  --exclude-glob '.DS_Store'
  --exclude-glob 'Thumbs.db'
  --exclude-glob '*.swp'
  --exclude-glob '*.tmp'
  --exclude-glob '.vscode/'
  --exclude-glob '.idea/'
)

# 'dados/*' e não 'dados/': assim a pasta é criada no servidor e o .htaccess que
# a protege sobe junto, mas nada de dentro dela é tocado. Mesma lógica nas
# pastas de upload.

# ---------------------------------------------------------------------------
# Modo
# ---------------------------------------------------------------------------

MODO_REAL=0
LIMPAR=0

for arg in "$@"; do
  case "$arg" in
    --real)   MODO_REAL=1 ;;
    --limpar) LIMPAR=1 ;;
    *) echo "Argumento desconhecido: $arg" >&2
       echo "Uso: $0 [--real] [--limpar]" >&2; exit 1 ;;
  esac
done

OPCOES=(--verbose --parallel=3 --no-perms)

[[ $LIMPAR    -eq 1 ]] && OPCOES+=(--delete)
[[ $MODO_REAL -eq 0 ]] && OPCOES+=(--dry-run)

if [[ $LIMPAR -eq 1 ]]; then
  echo "⚠️  MODO --limpar: arquivos que não existem aqui serão APAGADOS lá."
  echo "    dados/, assets/videos/ e assets/img/uploads/ estão excluídos e não serão tocados."
  echo
fi

# ---------------------------------------------------------------------------
# Envio
# ---------------------------------------------------------------------------

echo "Origem : $RAIZ/"
echo "Destino: ftps://${FTP_USUARIO}@${FTP_HOST}:${FTP_PORTA}${DESTINO}"
echo

# TLS: três ajustes, e o terceiro merece explicação.
#
# ssl-force + ssl-protect-data recusam a conexão se o servidor não oferecer TLS,
# em vez de continuar em texto claro — vale para o login e para os dados.
#
# check-hostname no, com verify-certificate SIM: o servidor apresenta um
# certificado legítimo da HostGator (*.hostgator.com.br, emitido pela Sectigo),
# que não cobre ftp.wellira.online. É o normal em hospedagem compartilhada, e
# não há hostname alternativo utilizável: o reverso do IP aponta para Cloudflare
# e o hostname do servidor resolve para outro endereço.
#
# A saída fácil seria "verify-certificate no", que aceitaria QUALQUER
# certificado, inclusive um autoassinado de quem estivesse no meio do caminho.
# Manter a verificação da cadeia e dispensar só a conferência do nome exige que
# o certificado seja emitido por uma autoridade confiável — barra bem mais alta
# do que desligar tudo, e sem custo de manutenção.
lftp -u "${FTP_USUARIO},${FTP_SENHA}" \
     -e "set ftp:ssl-force true;
         set ftp:ssl-protect-data true;
         set ssl:verify-certificate yes;
         set ssl:check-hostname no;
         set ftp:passive-mode true;
         set net:max-retries 2;
         set net:timeout 20;
         mirror --reverse ${OPCOES[*]} ${EXCLUIR[*]} '$RAIZ/' '${DESTINO}';
         bye" \
     -p "${FTP_PORTA}" "${FTP_HOST}"

echo
if [[ $MODO_REAL -eq 0 ]]; then
  echo "──────────────────────────────────────────────────────────────"
  echo "SIMULAÇÃO. Nada foi enviado."
  echo "Confira a lista acima. Se estiver certa, rode de novo com --real"
  echo "──────────────────────────────────────────────────────────────"
else
  echo "Enviado."
  echo
  echo "Se for o primeiro deploy, confira no servidor:"
  echo "  - dados/senha.php existe? (gere com: php tools/gerar-hash.php \"senha\")"
  echo "  - dados/ tem permissão de escrita? (o painel cria as subpastas sozinho)"
  echo "  - assets/videos/ e assets/img/uploads/ têm permissão de escrita?"
  echo "  - https://${FTP_HOST}/admin abre a tela de login?"
fi
