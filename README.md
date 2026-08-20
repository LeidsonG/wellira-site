# Wellira — site de landing pages

Site de páginas de produto para a Wellira. Cada oferta vive em `/<slug>` e reúne
título, vídeo, texto e botões que levam ao site do fornecedor, onde a compra é
concluída. A raiz `/` é uma página institucional, para que quem apagar o caminho
da URL encontre um site legítimo em vez de um erro.

Hospedagem: HostGator (plano compartilhado, cPanel). Deploy por SFTP.

---

## 🚨 ANTES DE PUBLICAR NA HOSTGATOR

> **A indexação já está configurada para produção.** O site é aberto aos
> buscadores; o que fica de fora é decidido caso a caso (ver *Indexação* abaixo).
> Não há mais bloqueio geral para desligar.

- [ ] **Gerar a senha do painel** e gravar em `inc/senha.php` no servidor
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
- [ ] Após o primeiro deploy: registrar o site no **Google Search Console** e
      enviar `https://wellira.online/sitemap.xml`

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
`public_html`. Isso mantém o deploy por SFTP trivial e é o que o GitHub Pages
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
assets/css/             Folha de estilo única
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
inc/senha.php           Hash da senha — FORA do repositório, criado no servidor
dados/ofertas/          Uma oferta por arquivo JSON (fora do repositório)
dados/backups/          Versões anteriores de cada oferta (fora do repositório)
dados/cliques/          Contador por oferta (fora do repositório)

─ Só no repositório, NÃO enviar no deploy ─────────────────────
tools/dev-router.php    Reproduz o .htaccess no servidor embutido do PHP
tools/ofertas-exemplo/  Ofertas de exemplo, para popular um ambiente novo
tools/gerar-hash.php    Gera o hash da senha do painel
scripts/deploy.sh       Deploy por rsync, com o conteúdo da cliente protegido
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
duplica e publica ofertas por ele, sem SFTP. Guia de uso dela: `GUIA-PAINEL.md`.

### Instalar a senha

Não há página de instalação de propósito — página de setup é porta que alguém
esquece aberta. O hash é gerado na linha de comando e copiado para o servidor:

```bash
php tools/gerar-hash.php "uma-senha-longa-de-verdade"
```

Grave a saída em **`inc/senha.php`**. O arquivo está no `.gitignore` e é
excluído do deploy: ele nasce e vive no servidor.

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

## Deploy

```bash
./scripts/deploy.sh              # simulação — mostra o que iria, sem enviar
./scripts/deploy.sh --real       # envia
```

Credenciais em `deploy.conf` na raiz (modelo em `scripts/deploy.conf.exemplo`).

> ⚠️ O script **nunca** envia `dados/ofertas/`, `dados/backups/`,
> `dados/cliques/`, `assets/videos/` nem `assets/img/uploads/`. Esse conteúdo
> existe só no servidor e não tem outra cópia. As exclusões valem inclusive com
> `--delete`.

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
