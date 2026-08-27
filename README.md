# Wellira

Site de landing pages de produto para uma cliente real, no ar desde agosto de
2026 em `wellira.online`, hospedado em plano compartilhado (HostGator).

Cada oferta vira uma página em `/<slug>`: título, vídeo e/ou galeria de fotos,
texto de venda e botões que levam ao site do fornecedor. A cliente cria,
edita e publica essas páginas sozinha, por um painel administrativo próprio,
sem tocar em código.

## Stack, deliberadamente mínima

PHP puro, sem framework e sem build step. Cada oferta é um arquivo JSON, sem
banco de dados. Deploy por SFTP com chave SSH, porque a hospedagem não dá
shell. A decisão foi conter dependência: hospedagem compartilhada tem
CPU/IO limitados, e a cliente não tem equipe técnica por trás.

## Destaques técnicos

- **Painel sem framework, com as mesmas garantias de um**: sessão
  endurecida (`HttpOnly`, `SameSite`, `Secure`), CSRF com `hash_equals` em
  todo POST, limite de tentativas de login por IP, e troca de senha pela
  própria cliente sem depender de suporte.
- **Upload validado por assinatura binária**, não por extensão: o arquivo é
  lido byte a byte antes de tocar o disco, então renomear um `.php` para
  `.jpg` não passa. Nome gravado é sempre gerado no servidor, nunca
  aproveitado do envio.
- **Escrita atômica com backup automático**: toda gravação passa por
  arquivo temporário + `rename()`, e guarda as 10 versões anteriores de
  cada oferta antes de sobrescrever. Conteúdo da cliente não existe em
  outro lugar.
- **SEO tratado como regra de produto, não afterthought**: sitemap gerado
  dinamicamente a partir do que está publicado, `noindex` e `X-Robots-Tag`
  aplicados de forma consistente entre página, painel e robots.txt, e
  canonical único por domínio.
- **Deploy com rede de segurança**: script por SFTP roda em simulação por
  padrão, protege as pastas com conteúdo da cliente contra remoção
  acidental, e falha explicitamente se a sincronização parar no meio.
- **Template único, guiado por uma regra**: campo vazio remove o bloco
  inteiro da página. Um layout atende de suplemento a eletrodoméstico sem
  nenhuma seção nascer vazia.

## Estrutura

```
oferta.php, go.php, sitemap.php   Roteamento e template público
admin/                            Painel (autenticação, edição, upload)
inc/                              Validação, sessão, renderização
assets/                           CSS, JS e uploads da cliente
dados/                            Ofertas, backups e cliques (fora do repo)
scripts/, tools/                  Deploy e utilitários de desenvolvimento
```

## Rodando localmente

```bash
./scripts/dev.sh
```

Abre `http://localhost:8000/vitalane` (oferta de exemplo) e
`http://localhost:8000/admin` (painel). O script repõe o roteamento e os
limites de upload que o servidor embutido do PHP ignora.

## Documentação

`GUIA-PAINEL.md` é o manual de uso do painel, escrito para a cliente.
