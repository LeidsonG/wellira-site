<?php
/**
 * Prévia de uma oferta pelo painel.
 *
 * Rascunho não aparece em /<slug>, e o cookie de sessão (path=/admin) não
 * acompanha a página pública. A saída é servir o MESMO template de dentro do
 * painel: login exigido, tarja de aviso, fora do alcance dos buscadores.
 */

require_once __DIR__ . '/../inc/admin-funcoes.php';
require_once __DIR__ . '/../inc/auth.php';

exigir_login();

$slug = (string) ($_GET['slug'] ?? '');

if (!slug_valido($slug) || carregar_oferta($slug) === null) {
    header('Location: /admin/?erro=' . rawurlencode('Oferta não encontrada.'));
    exit;
}

// Cinto e suspensório contra indexação, somando ao .htaccess de admin/.
header('X-Robots-Tag: noindex, nofollow', true);
header('Cache-Control: no-store, private');

// Lida por oferta.php: libera o rascunho e liga a tarja.
define('PREVIA_ADMIN', true);

$_GET['slug'] = $slug;
require dirname(__DIR__) . '/oferta.php';
