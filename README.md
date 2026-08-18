# Wellira — site de landing pages

Site de páginas de produto para a Wellira. Cada oferta vive em `/<slug>` e reúne
título, vídeo, texto e botões que levam ao site do fornecedor, onde a compra é
concluída. A raiz `/` é uma página institucional, para que quem apagar o caminho
da URL encontre um site legítimo em vez de um erro.

Hospedagem: HostGator (plano compartilhado, cPanel). Deploy por SFTP.

---

## 🚨 ANTES DE PUBLICAR NA HOSTGATOR

> **O site está com a indexação BLOQUEADA de propósito.** Enquanto roda no GitHub
> Pages para aprovação, os produtos são fictícios e não podem ser confundidos com
> ofertas reais. Se estes bloqueios subirem para o domínio definitivo, **o site
> real jamais será encontrado no Google.**

- [ ] **Apagar o `robots.txt`** da raiz (ele contém `Disallow: /`)
- [ ] **Remover a tag `<meta name="robots" content="noindex, nofollow">`** de:
      `index.html`, `vitalane.html`, `hydrasource.html`, `404.html`
- [ ] **Trocar `BLOQUEAR_INDEXACAO` para `false`** em `inc/config.php`
- [ ] **Não enviar a pasta `tools/`** no deploy
- [ ] Remover os comentários de aviso que acompanham essas tags
- [ ] **Remover a nota `.video-nota`** sob o vídeo (texto em português, só para a
      fase de aprovação) e a regra correspondente no CSS
- [ ] Substituir os produtos fictícios (Vitalane, HydraSource) pelos reais
- [ ] Substituir foto, nome e história na seção "Why I'm sharing this"
- [ ] Preencher o estado/país da lei aplicável nos Termos (último marcador)
- [ ] Voltar os caminhos de assets para absolutos (`/assets/...`) quando o
      roteamento por PHP entrar e as ofertas passarem a viver em `/<slug>/`

Busca rápida para conferir se sobrou algo:

```bash
grep -rn "noindex" . --include="*.html"
ls robots.txt 2>/dev/null && echo "robots.txt AINDA EXISTE"
```

---

## Estrutura

A raiz do repositório **é** a raiz do site — o que a hospedagem serve a partir de
`public_html`. Isso mantém o deploy por SFTP trivial e é o que o GitHub Pages
exige para a prévia. O que não é público fica separado por pasta:

```
─ Servido pela web ────────────────────────────────────────────
index.html              Página institucional (a "raiz segura")
oferta.php              Template das ofertas — recebe /<slug> do .htaccess
404.html                Página de erro
.htaccess               Roteamento, HTTPS, Options -Indexes, bloqueios
robots.txt              ⚠️ temporário, ver checklist acima
assets/css/             Folha de estilo única
assets/img/favicon.png  Marca — favicon e ícone do cabeçalho
assets/videos/          Vídeos enviados pela cliente (fora do repositório)
assets/img/uploads/     Imagens enviadas pela cliente (fora do repositório)
privacy-policy/  ┐
terms-of-service/├─ páginas legais, cada uma como pasta com index.html
contact/         ┘

─ No servidor, mas bloqueado para a web ───────────────────────
inc/config.php          Aviso base, ícones dos selos, caminhos
inc/funcoes.php         Carregamento, escape, vídeo, validações
dados/ofertas/          Uma oferta por arquivo JSON (fora do repositório)

─ Só no repositório, NÃO enviar no deploy ─────────────────────
tools/dev-router.php    Reproduz o .htaccess no servidor embutido do PHP
tools/ofertas-exemplo/  Ofertas de exemplo, para popular um ambiente novo
README.md

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


## Rodando local

Com PHP, para testar o roteamento das ofertas:

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
