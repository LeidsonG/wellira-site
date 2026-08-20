<?php
/**
 * Saída para o site do fornecedor: /go/<slug>
 *
 * Some o clique e redireciona. Duas coisas de uma vez:
 *
 * - a cliente descobre qual página converte sem depender de analytics externo,
 *   que ela não saberia configurar nem ler
 * - o link de afiliado deixa de aparecer no HTML da página, então trocar de
 *   fornecedor não exige reescrever nada além do JSON da oferta
 *
 * O redirecionamento é 302, não 301: 301 fica gravado no navegador do visitante
 * e continuaria mandando para o fornecedor antigo mesmo depois de a cliente
 * trocar o link.
 */

require_once __DIR__ . '/inc/admin-funcoes.php';

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
