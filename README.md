# Wellira — site de landing pages

Site de páginas de produto para a Wellira. Cada oferta vive em `/<slug>` e reúne
título, vídeo, texto e botões que levam ao site do fornecedor, onde a compra é
concluída. A raiz `/` é uma página institucional, para que quem apagar o caminho
da URL encontre um site legítimo em vez de um erro.

Hospedagem: HostGator (plano compartilhado, cPanel). Deploy por FTPS.

---

## 🚨 ANTES DE PUBLICAR NA HOSTGATOR

> **A indexação já está configurada para produção.** O site é aberto aos
> buscadores; o que fica de fora é decidido caso a caso (ver *Indexação* abaixo).
> Não há mais bloqueio geral para desligar.

- [ ] **Gerar a senha provisória do painel** e gravar em `dados/senha.php` no servidor
      (`php tools/gerar-hash.php "senha"`)
- [ ] Conferir permissão de escrita em `dados/`, `assets/videos/` e
      `assets/img/uploads/`
- [ ] Rodar `./scripts/deploy.sh` (simulação) e conferir a lista antes do `--real`
- [ ] **Remover a nota `.video-nota`** sob o vídeo (texto em português, só para a
      fase de aprovação) e a regra correspondente no CSS
- [ ] Substituir os produtos fictícios (Vitalane, HydraSource) pelos reais —
      e, ao criar a oferta real, **não** copiar o `"indexar": false` deles
- [ ] Apagar os gêmeos estáticos `vitalane.html` e `hydrasource.html`, que só
      existem para a prévia do GitHub Pages
- [ ] Substituir foto, nome e história na seção "Why I'm sharing this"
- [ ] Conferir que `SITE_URL` em `inc/config.php` bate com o domínio real
- [ ] Voltar os caminhos de assets para absolutos (`/assets/...`) quando o
      roteamento por PHP entrar e as ofertas passarem a viver em `/<slug>/`
- [ ] **Preencher `META_PIXEL_ID` e `GA4_ID`** em `assets/js/rastreamento.js`
      — sem isso o rastreamento não carrega
- [ ] Após o primeiro deploy: registrar o site no **Google Search Console** e
      enviar `https://wellira.online/sitemap.xml`
- [ ] Conferir o Pixel com a extensão **Meta Pixel Helper** numa página de
      oferta real, e o evento `Lead` clicando no botão

Busca rápida para conferir se sobrou algo:

```bash
grep -rn "noindex" . --include="*.html"          # só os gêmeos e o 404
grep -rn '"indexar": false' dados/ofertas/       # nenhuma oferta real aqui
```

### Indexação

Aberto por padrão. Fica fora do índice:

| O quê | Como |
|---|---|
| Ofertas de demonstração | `"indexar": false` no JSON da oferta |
| Gêmeos estáticos (`vitalane.html`, `hydrasource.html`) | `meta robots` na página |
| `404.html` | `meta robots` na página |
| `/admin/`, `/dados/`, `/inc/`, `/tools/` | `robots.txt` |

Oferta sem o campo `indexar` **é indexável** — o padrão favorece a oferta real.
O `sitemap.xml` é gerado por `sitemap.php` a partir dos mesmos critérios.

---

## Estrutura

A raiz do repositório **é** a raiz do site — o que a hospedagem serve a partir de
`public_html`. Isso mantém o deploy por FTPS trivial e é o que o GitHub Pages
exige para a prévia. O que não é público fica separado por pasta:

```
─ Servido pela web ────────────────────────────────────────────
index.html              Página institucional (a "raiz segura")
oferta.php              Template das ofertas — recebe /<slug> do .htaccess
sitemap.php             Gera /sitemap.xml a partir das ofertas publicadas
go.php                  Saída /go/<slug>: conta o clique e redireciona
admin/                  Painel administrativo (PT-BR, protegido por senha)
404.html                Página de erro
.htaccess               Roteamento, HTTPS, Options -Indexes, bloqueios
robots.txt              Aberto aos buscadores; aponta o sitemap
assets/css/             Folha de estilo do site e do painel
assets/js/rastreamento.js  Meta Pixel + GA4 — IDs no topo do arquivo
assets/img/favicon.png  Marca — favicon e ícone do cabeçalho
assets/videos/          Vídeos enviados pela cliente (fora do repositório)
assets/img/uploads/     Imagens enviadas pela cliente (fora do repositório)
privacy-policy/  ┐
terms-of-service/├─ páginas legais, cada uma como pasta com index.html
contact/         ┘

─ No servidor, mas bloqueado para a web ───────────────────────
inc/config.php          Aviso base, ícones dos selos, caminhos, limites
inc/funcoes.php         Carregamento, escape, vídeo, validações
inc/auth.php            Sessão, login, CSRF, limite de tentativas
inc/admin-funcoes.php   Normalização, gravação atômica, backup, upload
dados/senha.php         Hash da senha — FORA do repositório, gravável pelo painel
dados/ofertas/          Uma oferta por arquivo JSON (fora do repositório)
dados/backups/          Versões anteriores de cada oferta (fora do repositório)
dados/cliques/          Contador por oferta (fora do repositório)

─ Só no repositório, NÃO enviar no deploy ─────────────────────
tools/dev-router.php    Reproduz o .htaccess no servidor embutido do PHP
tools/ofertas-exemplo/  Ofertas de exemplo, para popular um ambiente novo
tools/gerar-hash.php    Gera o hash da senha do painel
scripts/deploy.sh       Deploy por FTPS (lftp), com o conteúdo da cliente protegido
README.md · GUIA-PAINEL.md

─ Temporário, sai depois da aprovação ─────────────────────────
vitalane.html           Protótipo estático da oferta
hydrasource.html        Protótipo estático da oferta
```

As páginas legais são **pastas com index.html**, não arquivos soltos: a regra de
roteamento captura qualquer caminho de uma palavra que não exista em disco, então
`/privacy-policy` viraria uma busca por oferta. Como diretório, a condição `!-d`
impede a reescrita.

As duas ofertas de exemplo usam **o mesmo template e o mesmo CSS**. A diferença
entre elas demonstra a regra que sustenta o projeto: **campo vazio = bloco some
da página**. Assim um único layout atende todo o catálogo, de suplemento a
eletrodoméstico, sem que nenhuma página nasça com seção vazia.


## Painel administrativo

Fica em `/admin`, é em português e protegido por senha. A cliente cria, edita,
duplica e publica ofertas por ele, sem FTP. Guia de uso dela: `GUIA-PAINEL.md`.

### Instalar a senha

Não há página de instalação de propósito — página de setup é porta que alguém
esquece aberta. O hash é gerado na linha de comando e copiado para o servidor:

```bash
php tools/gerar-hash.php "uma-senha-longa-de-verdade"
```

Grave a saída em **`dados/senha.php`**. O arquivo está no `.gitignore` e é
excluído do deploy: nasce e vive no servidor.

Fica em `dados/` e não em `inc/` porque **a cliente troca a própria senha pelo
painel** (`/admin/senha.php`), então o arquivo precisa ser gravável pelo PHP.
Deixar código executável gravável pelo servidor web é o que transforma qualquer
falha de escrita em execução remota; `dados/` já é gravável e já está fechado
para a web.

Na prática: entregue uma senha provisória e peça que ela troque no primeiro
acesso.

### Permissões de escrita no servidor

O painel grava em quatro lugares. Sem permissão neles, o login funciona mas
salvar falha:

```
dados/ofertas/        dados/backups/        dados/cliques/
assets/videos/        assets/img/uploads/
```

As pastas de `dados/` são criadas sozinhas na primeira gravação, desde que
`dados/` seja gravável.

### O que protege o painel

| Camada | Contra |
|---|---|
| `password_hash()` + limite de 5 tentativas/15 min por IP | força bruta |
| Cookie `HttpOnly` + `SameSite` + `Secure`, path `/admin` | roubo de sessão |
| `session_regenerate_id()` no login | session fixation |
| Token CSRF em todo POST, via `hash_equals` | ação forjada por outra aba |
| Excluir e duplicar só por POST | robô seguindo link |
| Upload validado por **magic bytes**, extensão vinda da assinatura | PHP disfarçado de imagem |
| `.htaccess` sem execução de PHP nas pastas de upload | o mesmo, em profundidade |
| Escrita atômica (temporário + `rename`) | arquivo corrompido por timeout |
| Backup das 10 versões anteriores | erro de edição da cliente |
| Troca de senha exige a senha atual + `session_regenerate_id()` | sessão esquecida aberta virar troca de dono |

## Deploy

Por **FTPS explícito** (porta 21), com `lftp` — a hospedagem oferece FTP, não
SSH. Instale com `sudo apt install lftp`.

```bash
./scripts/deploy.sh              # simulação — mostra o que iria, sem enviar
./scripts/deploy.sh --real       # envia
./scripts/deploy.sh --real --limpar   # remove também o que não existe mais aqui
```

Credenciais em `deploy.conf` na raiz (modelo em `scripts/deploy.conf.exemplo`).
A senha não fica no arquivo: o script pergunta na hora.

> O script força TLS e **recusa a conexão** se o servidor não oferecer. FTP puro
> manda usuário e senha em texto claro pela rede.

> ⚠️ O script **nunca** envia o conteúdo de `dados/` (ofertas, backups, cliques
> e a senha), `assets/videos/` nem `assets/img/uploads/`. Esse conteúdo
> existe só no servidor e não tem outra cópia. As exclusões valem inclusive com
> `--delete`.

## Rastreamento (Meta Pixel + GA4)

Os IDs ficam no topo de `assets/js/rastreamento.js`. Vazios, nada carrega.

Arquivo único de propósito: as institucionais são `.html` estáticas e as ofertas
saem do `oferta.php` — trecho repetido em cada página faria alguma ficar para
trás na hora de trocar um ID.

**Só dispara em `wellira.online`.** A trava de domínio evita que a prévia do
GitHub Pages e o servidor local somem às estatísticas. Não é cosmético: a Meta
aprende com quem converte, e ensiná-la com os próprios testes piora a entrega.

**O painel não é rastreado.**

### Eventos

| Evento | Quando | Para quê |
|---|---|---|
| `PageView` / GA4 padrão | toda página | volume e origem do tráfego |
| `Lead` (Meta) + `clique_oferta` (GA4) | clique no botão que vai ao fornecedor | é o sinal que a Meta usa para otimizar |

A compra acontece no site do fornecedor, fora do nosso alcance — o clique é o
sinal mais próximo dela que conseguimos registrar. Sem esse evento a campanha
otimiza por visita, e visita barata não é venda.

## Rodando local

Com PHP, para testar o roteamento das ofertas e o painel:

```bash
php -S localhost:8000 tools/dev-router.php
```

O `tools/dev-router.php` existe porque o servidor embutido do PHP não lê `.htaccess`.
Ele reproduz a reescrita `/<slug>` → `oferta.php` e os bloqueios de acesso, para
que o comportamento local seja o mesmo da HostGator. **Não vai para produção.**

As ofertas ficam em `dados/ofertas/*.json`, fora do repositório. Para popular um
ambiente novo:

```bash
cp tools/ofertas-exemplo/*.json dados/ofertas/
```

Depois abra `localhost:8000/vitalane` e `localhost:8000/hydrasource`.

Só para conferir HTML e CSS, sem PHP:

```bash
python3 -m http.server 8000
```

Para ver o comportamento mobile (CTA fixo no rodapé, cards em coluna única),
abra o DevTools e ative o modo dispositivo — `F12`, depois `Ctrl+Shift+M`.

## Convenções

Branches: trabalho em `staging`, `main` só recebe merge do que foi validado.
Commits seguem Conventional Commits em português.
