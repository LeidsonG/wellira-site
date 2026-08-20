<?php
/**
 * Sitemap XML, montado na hora a partir das ofertas em dados/ofertas/.
 *
 * É gerado a cada requisição em vez de ser um arquivo estático porque a cliente
 * cria e despublica ofertas pelo painel, sem passar por deploy. Um sitemap.xml
 * fixo no repositório envelheceria no primeiro produto novo, e mandar o Google
 * a URLs que já viraram 404 custa reputação de rastreamento.
 *
 * O custo é irrelevante para o plano compartilhado: são alguns json_decode de
 * arquivos pequenos, e só o robô do buscador chega aqui.
 *
 * Entram apenas ofertas publicadas e indexáveis — os mesmos critérios que
 * oferta.php aplica na meta robots. Se divergissem, o sitemap estaria
 * anunciando páginas que pedem para não ser indexadas, que é o tipo de sinal
 * contraditório que o Search Console reporta como erro.
 */

require_once __DIR__ . '/inc/funcoes.php';

header('Content-Type: application/xml; charset=utf-8');

/** Páginas fixas, com a prioridade relativa que fazem sentido no site. */
$paginas = [
    ['/',                  '1.0'],
    ['/privacy-policy/',   '0.3'],
    ['/terms-of-service/', '0.3'],
    ['/contact/',          '0.5'],
];

foreach (listar_ofertas() as $slug) {
    $oferta = carregar_oferta($slug);
    if ($oferta === null) {
        continue;
    }
    if (($oferta['status'] ?? 'rascunho') !== 'publicado') {
        continue;
    }
    if (($oferta['indexar'] ?? true) === false) {
        continue;
    }
    $paginas[] = ['/' . $slug, '0.8'];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($paginas as [$caminho, $prioridade]): ?>
  <url>
    <loc><?= e(SITE_URL . $caminho) ?></loc>
    <changefreq>weekly</changefreq>
    <priority><?= e($prioridade) ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
