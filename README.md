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
- [ ] Remover os comentários de aviso que acompanham essas tags
- [ ] **Remover a nota `.video-nota`** sob o vídeo (texto em português, só para a
      fase de aprovação) e a regra correspondente no CSS
- [ ] Substituir os produtos fictícios (Vitalane, HydraSource) pelos reais
- [ ] Substituir foto, nome e história na seção "Why I'm sharing this"
- [ ] Preencher as páginas legais com os dados reais da empresa
- [ ] Voltar os caminhos de assets para absolutos (`/assets/...`) quando o
      roteamento por PHP entrar e as ofertas passarem a viver em `/<slug>/`

Busca rápida para conferir se sobrou algo:

```bash
grep -rn "noindex" . --include="*.html"
ls robots.txt 2>/dev/null && echo "robots.txt AINDA EXISTE"
```

---

## Estrutura

```
index.html          Página institucional (raiz segura)
vitalane.html       Oferta — categoria saúde (todos os blocos ligados)
hydrasource.html    Oferta — categoria eletrônico (blocos opcionais desligados)
404.html            Página de erro
assets/css/         Folha de estilo única
robots.txt          ⚠️ temporário, ver checklist acima
```

As duas ofertas usam **o mesmo template e o mesmo CSS**. A diferença entre elas
demonstra a regra que sustenta o projeto: **campo vazio = bloco some da página**.
Assim um único layout atende todo o catálogo, de suplemento a eletrodoméstico,
sem que nenhuma página nasça com seção vazia.

## Rodando local

```bash
python3 -m http.server 8000
```

Para ver o comportamento mobile (CTA fixo no rodapé, cards em coluna única),
abra o DevTools e ative o modo dispositivo — `F12`, depois `Ctrl+Shift+M`.

## Convenções

Branches: trabalho em `staging`, `main` só recebe merge do que foi validado.
Commits seguem Conventional Commits em português.
