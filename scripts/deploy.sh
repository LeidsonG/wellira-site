#!/usr/bin/env bash
#
# Deploy para a HostGator por SFTP com chave SSH, usando lftp.
#
# ⚠️  A REGRA MAIS IMPORTANTE DESTE ARQUIVO
#
# As ofertas, os vídeos, as imagens e a SENHA do painel existem SOMENTE no
# servidor. Não estão no git, não estão na sua máquina, e não há outra cópia.
# Um espelhamento com remoção, sem exclusões, apaga o trabalho da cliente para
# sempre.
#
# Por isso este script:
#   1. nunca envia o conteúdo de dados/, assets/videos nem assets/img/uploads
#   2. roda em simulação por padrão — só envia de verdade com --real
#   3. só remove arquivo remoto com --limpar, e mesmo assim respeitando (1)
#
# POR QUE SFTP E NÃO rsync: a conta é de plano compartilhado e não tem shell
# ("Shell access is not enabled on your account"). O rsync precisa executar um
# processo do outro lado; o SFTP não. A mesma chave SSH autentica os dois, então
# não se perde nada além da velocidade do algoritmo delta — e o mirror do lftp
# já compara tamanho e data, enviando só o que mudou.
#
set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RAIZ"

if ! command -v lftp >/dev/null 2>&1; then
  echo "ERRO: o lftp não está instalado.  sudo apt install lftp" >&2
  exit 1
fi

# ---------------------------------------------------------------------------
# Configuração
# ---------------------------------------------------------------------------

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
: "${SSH_PORTA:=2222}"
: "${SSH_CHAVE:?defina SSH_CHAVE em deploy.conf}"
: "${DESTINO:?defina DESTINO em deploy.conf}"

CHAVE="${SSH_CHAVE/#\~/$HOME}"
if [[ ! -f "$CHAVE" ]]; then
  echo "ERRO: chave não encontrada em $CHAVE" >&2
  exit 1
fi

# ---------------------------------------------------------------------------
# O que NÃO vai
# ---------------------------------------------------------------------------

# ⚠️ NUNCA comece esta lista com --include-glob. No lftp, se a PRIMEIRA regra
# é um include, o padrão inverte: tudo que não casa com include algum fica
# excluído. Foi exatamente isso que aconteceu aqui — o deploy dizia "Enviado"
# sem transferir um único arquivo, e com --limpar ainda apagava do servidor o
# que não estava na lista de includes. Os .htaccess das pastas de conteúdo,
# que eram a razão dos includes, agora sobem por put explícito após o mirror.
EXCLUIR=(
  # --- Infraestrutura do servidor: NÃO é nossa, e --limpar apagaria ---
  #
  # .well-known é onde o AutoSSL põe o arquivo de validação do domínio. Apagar
  # essa pasta não derruba o site na hora: derruba quando o certificado vencer,
  # porque a renovação falha calada. E como o .htaccess força HTTPS, "certificado
  # vencido" significa site inacessível.
  #
  # cgi-bin e error_log são criados pelo cPanel e ele espera encontrá-los.
  --exclude-glob '.well-known/'
  --exclude-glob 'cgi-bin/'
  --exclude-glob 'error_log'

  # --- Conteúdo da cliente e senha: existem só no servidor ---
  --exclude-glob 'dados/*'
  --exclude-glob 'assets/videos/*'
  --exclude-glob 'assets/img/uploads/*'

  # --- Segredos e controle de versão ---
  --exclude-glob 'deploy.conf'
  --exclude-glob '.env*'
  --exclude-glob '.git/'
  --exclude-glob '.gitignore'

  # Configuração local do Claude Code. Apareceu numa simulação indo para o
  # servidor: não faz nada em produção, e pasta oculta com nome de ferramenta é
  # exatamente o tipo de coisa que um scanner procura.
  --exclude-glob '.claude/'

  # --- Não pertence a produção ---
  --exclude-glob 'scripts/'
  --exclude-glob 'tools/'
  --exclude-glob '*.md'

  # --- Protótipo estático: demonstração fora do fluxo normal de oferta ---
  --exclude-glob 'vitalane.html'

  # --- Lixo de editor e sistema ---
  --exclude-glob '.DS_Store'
  --exclude-glob 'Thumbs.db'
  --exclude-glob '*.swp'
  --exclude-glob '*.tmp'
  --exclude-glob '.vscode/'
  --exclude-glob '.idea/'
)

# ---------------------------------------------------------------------------
# Modo
# ---------------------------------------------------------------------------

MODO_REAL=0
LIMPAR=0

for arg in "$@"; do
  case "$arg" in
    --real)   MODO_REAL=1 ;;
    --limpar) LIMPAR=1 ;;
    *) echo "Uso: $0 [--real] [--limpar]" >&2; exit 1 ;;
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

SAIDA="$(mktemp)"
trap 'rm -f "$SAIDA"' EXIT

echo "Origem : $RAIZ/"
echo "Destino: sftp://${SSH_USUARIO}@${SSH_HOST}:${SSH_PORTA}${DESTINO}"
echo "Chave  : $CHAVE"
echo

# O lftp fala SFTP through de um ssh que ele mesmo executa. Passar a chave e a
# porta pelo connect-program é o que permite autenticar sem senha — não há
# prompt, e nada de credencial fica no deploy.conf.
# O usuário vai no próprio "open", com vírgula e nada depois: é assim que o lftp
# entende "sem senha, quem autentica é o ssh". Passado por -u na linha de
# comando, ele ainda pede senha e a conexão morre antes de começar.
lftp -e "set sftp:connect-program 'ssh -a -x -i $CHAVE -p $SSH_PORTA';
         set sftp:auto-confirm yes;
         set net:max-retries 5;
         set net:timeout 120;
         set net:reconnect-interval-base 5;
         open -u ${SSH_USUARIO}, sftp://${SSH_HOST};
         mirror --reverse ${OPCOES[*]} ${EXCLUIR[*]} '$RAIZ/' '${DESTINO}';
         !echo '--- htaccess das pastas de conteudo ---';
         put -O '${DESTINO}/dados' '$RAIZ/dados/.htaccess';
         put -O '${DESTINO}/assets/videos' '$RAIZ/assets/videos/.htaccess';
         put -O '${DESTINO}/assets/img/uploads' '$RAIZ/assets/img/uploads/.htaccess';
         bye" 2>&1 | tee "$SAIDA"

# O lftp sai com 0 mesmo quando o mirror aborta no meio. Aconteceu no primeiro
# deploy real: ele removeu o WordPress, parou antes de enviar um único arquivo,
# e o script anunciou "Enviado" — o site ficou fora do ar com um WordPress sem
# miolo. Conferir a saída é a única forma de saber que terminou de verdade.
if grep -qiE "^(mirror: )?(fatal|error)|Login failed|No such file|Access failed|interrupt" "$SAIDA"; then
  echo
  echo "❌ O lftp relatou erro. NÃO confie no resultado — releia a saída acima." >&2
  rm -f "$SAIDA"
  exit 1
fi

# Remoção sem nenhum envio é o sintoma exato do mirror que aborta no meio.
# Deploy sem transferência nenhuma, por outro lado, é normal quando nada mudou —
# por isso a condição exige as duas coisas juntas.
if [[ $MODO_REAL -eq 1 ]] \
   && grep -qi "^Removing" "$SAIDA" \
   && ! grep -q "Transferring file" "$SAIDA"; then
  echo
  echo "❌ Removeu arquivos remotos e não enviou nenhum: o mirror abortou no meio." >&2
  echo "   Rode de novo — o mirror é incremental e retoma de onde parou." >&2
  rm -f "$SAIDA"
  exit 1
fi
rm -f "$SAIDA"

echo
if [[ $MODO_REAL -eq 0 ]]; then
  echo "──────────────────────────────────────────────────────────────"
  echo "SIMULAÇÃO. Nada foi enviado."
  echo "Confira a lista acima. Se estiver certa, rode de novo com --real"
  echo "──────────────────────────────────────────────────────────────"
else
  echo "Enviado."
  echo
  echo "Se for o primeiro deploy, falta:"
  echo "  - dados/senha.php  (senha do painel — ver README)"
  echo "  - permissão de escrita em dados/, assets/videos/, assets/img/uploads/"
  echo "  - abrir https://${SSH_HOST}/admin e conferir a tela de login"
fi
