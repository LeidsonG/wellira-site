<?php
/**
 * Saída para o site do fornecedor: /go/<slug>
 *
 * Soma o clique e redireciona, escondendo o link de afiliado do HTML.
 * 302, não 301: 301 ficaria gravado no navegador mesmo após trocar o link.
 */

require_once __DIR__ . '/inc/funcoes.php';

$slug   = (string) ($_GET['slug'] ?? '');
$oferta = carregar_oferta($slug);

if ($oferta === null || ($oferta['status'] ?? 'rascunho') !== 'publicado') {
    nao_encontrado();
}

$link = link_seguro((string) ($oferta['link'] ?? ''));
if ($link === null) {
    nao_encontrado();
}

somar_clique($slug);

// noindex explícito: sem isto o buscador pode guardar a URL de saída e passar a
// oferecê-la na busca no lugar da página da oferta.
header('X-Robots-Tag: noindex, nofollow', true);
header('Cache-Control: no-store, private');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Location: ' . $link, true, 302);
exit;
