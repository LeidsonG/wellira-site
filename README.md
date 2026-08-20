# Wellira — site de landing pages

Site de páginas de produto para a Wellira. Cada oferta vive em `/<slug>` e reúne
título, vídeo, texto e botões que levam ao site do fornecedor, onde a compra é
concluída. A raiz `/` é uma página institucional, para que quem apagar o caminho
da URL encontre um site legítimo em vez de um erro.

Hospedagem: HostGator (plano compartilhado, cPanel). Deploy por SFTP com chave SSH.

> **Este repositório é público.** Nada de credencial, chave, ID de medição ou
> caminho de servidor entra aqui — nem em código, nem em documentação, nem em
> mensagem de commit. O que é segredo vive só no servidor e no `.gitignore`:
> `deploy.conf`, `dados/senha.php` e `assets/js/ids.js`.

---

## Estado

**No ar desde 20/08/2026** em `wellira.online`. O WordPress que ocupava o
`public_html` foi substituído, com backup completo guardado fora do servidor.

O que ainda falta está em `todo.md`. O resumo: nenhuma oferta real publicada,
o Google Analytics não foi configurado, e a seção "Why I'm sharing this" ainda
usa uma persona de demonstração.

---

## Indexação

Aberto por padrão. Fica fora do índice:

| O quê | Como |
|---|---|
| Ofertas de demonstração | `"indexar": false` no JSON da oferta |
| Gêmeos estáticos (`vitalane.html`, `hydrasource.html`) | `meta robots` na página |
| `404.html` | `meta robots` na página |
| `/go/<slug>` | `robots.txt` + `X-Robots-Tag` |

Oferta sem o campo `indexar` **é indexável** — o padrão favorece a oferta real.
O `sitemap.xml` é gerado por `sitemap.php` a partir dos mesmos critérios.

---

## Estrutura

A raiz do repositório **é** a raiz do site — o que a hospedagem serve a partir de
`public_html`. Isso mantém o deploy trivial e é o que o GitHub Pages
exige para a prévia. O que não é público fica separado por pasta:

```
─ Servido pela web ────────────────────────────────────────────
index.html              Página institucional (a "raiz segura")
oferta.php              Template das ofertas — recebe /<slug> do .htaccess
sitemap.php             Gera /sitemap.xml a partir das ofertas publicadas
go.php                  Saída /go/<slug>: conta o clique e redireciona
admin/                  Painel administrativo (PT-BR, usuário + senha)
404.html                Página de erro
.htaccess               Roteamento, HTTPS, Options -Indexes, bloqueios
robots.txt              Aberto aos buscadores; aponta o sitemap
assets/css/             Folha de estilo do site e do painel
assets/js/rastreamento.js  Meta Pixel + GA4 (IDs vêm de ids.js, fora do repo)
assets/js/ids.js           IDs de medição — FORA do repositório
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
dados/senha.php         Usuário + hash — FORA do repositório, gravável pelo painel
dados/ofertas/          Uma oferta por arquivo JSON (fora do repositório)
dados/backups/          Versões anteriores de cada oferta (fora do repositório)
dados/cliques/          Contador por oferta (fora do repositório)

─ Só no repositório, NÃO enviar no deploy ─────────────────────
tools/dev-router.php    Reproduz o .htaccess no servidor embutido do PHP
tools/ofertas-exemplo/  Ofertas de exemplo, para popular um ambiente novo
tools/gerar-hash.php    Gera o hash da senha do painel
scripts/deploy.sh       Deploy por SFTP (lftp + chave), com o conteúdo protegido
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

Fica em `/admin`, é em português e pede **usuário e senha**. A cliente cria,
edita, duplica e publica ofertas por ele, sem tocar em arquivo. Guia de uso
dela: `GUIA-PAINEL.md`.

### Instalar o acesso

Não há página de instalação de propósito — página de setup é porta que alguém
esquece aberta. As credenciais são geradas na linha de comando:

```bash
php tools/gerar-hash.php <usuario> "<senha-longa>" > dados/senha.php
```

A saída é só o conteúdo do arquivo, sem cabeçalho: **qualquer byte antes do
`<?php` vira saída quando o arquivo é lido**, os cabeçalhos vão junto, e todo
`header()` posterior deixa de funcionar. Foi assim que o redirecionamento do
login parou de funcionar em produção uma vez.

Envie o arquivo ao servidor e deixe-o com permissão `600`.

Ele fica em `dados/` e não em `inc/` porque **a cliente troca o próprio acesso
pelo painel**, então precisa ser gravável pelo PHP. Deixar código executável
gravável pelo servidor web é o que transforma qualquer falha de escrita em
execução remota; `dados/` já é gravável e já está fechado para a web.

Na prática: entregue credenciais provisórias e peça que ela troque no primeiro
acesso, em *Trocar acesso*.

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
| Sessão gravada dentro da conta, não no caminho padrão do cPanel | pasta padrão inexistente derrubava o login |
| Troca de senha exige a senha atual + `session_regenerate_id()` | sessão esquecida aberta virar troca de dono |

## Deploy

Por **SFTP com chave SSH**, usando `lftp` (`sudo apt install lftp`).

A conta não tem shell — `rsync` precisa executar um processo do outro lado e
não serve. O SFTP autentica com a mesma chave, e o `mirror` do lftp já compara
tamanho e data, enviando só o que mudou.

```bash
./scripts/deploy.sh              # simulação — mostra o que iria, sem enviar
./scripts/deploy.sh --real       # envia
./scripts/deploy.sh --real --limpar   # remove também o que não existe mais aqui
```

Configuração em `deploy.conf` na raiz (modelo em `scripts/deploy.conf.exemplo`).
**Não há senha em lugar nenhum**: quem autentica é a chave SSH indicada ali.

> ⚠️ O script **nunca** envia o conteúdo de `dados/` (ofertas, backups, cliques
> e as credenciais), `assets/videos/` nem `assets/img/uploads/`. Esse conteúdo
> existe só no servidor e não tem outra cópia. As exclusões valem inclusive com
> `--limpar`.

O `lftp` sai com código 0 mesmo quando o espelhamento aborta no meio — já
aconteceu, e o site ficou fora do ar com o WordPress pela metade. Por isso o
script lê a saída e falha explicitamente quando remove arquivos sem enviar
nenhum.

### Fluxo de manutenção

```bash
git checkout staging                          # 1. sempre em staging

php -S localhost:8000 tools/dev-router.php    # 2. validar no navegador

git add <arquivos> && git commit              # 3. commitar
git push origin staging                       #    (atualiza a prévia do Pages)

git checkout main && git merge staging        # 4. promover
git push origin main

./scripts/deploy.sh                           # 5. simulação — conferir a lista
./scripts/deploy.sh --real                    # 6. enviar

git checkout staging                          # 7. voltar para o trabalho
```

**Quando usar `--limpar`:** só quando você APAGOU um arquivo do projeto e ele
precisa sumir do servidor. No dia a dia não é necessário — o envio normal
sobrescreve o que mudou e ignora o resto.

**O que a cliente cria nunca é tocado.** Pode subir quantas vezes quiser: as
ofertas, os vídeos, as imagens e as credenciais dela ficam onde estão.

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
