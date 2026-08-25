<?php
/**
 * Prévia de uma oferta pelo painel.
 *
 * Existe por causa de um beco sem saída: rascunho não aparece em /<slug> (é o
 * que o rascunho significa), e o cookie da sessão do painel tem path=/admin,
 * então a página pública não tem como reconhecer a cliente logada nem abrir
 * exceção para ela. O resultado era o botão "Ver página" de um rascunho cair
 * num 404, comportamento correto para o visitante, e indistinguível de um
 * defeito para quem acabou de escrever a oferta.
 *
 * A saída é servir a MESMA página, com o MESMO template, de dentro do painel.
 * Aqui a sessão vale, o login é exigido antes de qualquer coisa, e a página sai
 * com tarja de aviso e fora do alcance dos buscadores.
 *
 * Não há aqui um segundo caminho de renderização: se houvesse, a prévia
 * mostraria uma página que não é a que vai ao ar, e prévia que mente é pior do
 * que prévia nenhuma.
 */

require_once __DIR__ . '/../inc/admin-funcoes.php';
require_once __DIR__ . '/../inc/auth.php';

exigir_login();

$slug = (string) ($_GET['slug'] ?? '');

if (!slug_valido($slug) || carregar_oferta($slug) === null) {
    header('Location: /admin/?erro=' . rawurlencode('Oferta não encontrada.'));
    exit;
}

// Cinto e suspensório contra indexação: o .htaccess de admin/ já manda
// noindex, e a página também sai com a meta robots porque $previa desliga o
// indexar. Um rascunho vazado para a busca é conteúdo pela metade no nome do
// domínio, e sai do índice muito mais devagar do que entrou.
header('X-Robots-Tag: noindex, nofollow', true);
header('Cache-Control: no-store, private');

/** Lida por oferta.php: é o que libera o rascunho e liga a tarja. */
define('PREVIA_ADMIN', true);

$_GET['slug'] = $slug;
require dirname(__DIR__) . '/oferta.php';
