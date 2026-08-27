<?php
/**
 * Sitemap XML, montado na hora a partir das ofertas em dados/ofertas/.
 *
 * Gerado a cada requisição porque a cliente cria e despublica ofertas pelo
 * painel, sem deploy. Entram só ofertas publicadas e indexáveis, os mesmos
 * critérios que oferta.php aplica na meta robots.
 */

require_once __DIR__ . '/inc/funcoes.php';

header('Content-Type: application/xml; charset=utf-8');

// Páginas fixas, com a prioridade relativa que fazem sentido no site.
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
