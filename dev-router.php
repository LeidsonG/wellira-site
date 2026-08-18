<?php
/**
 * Roteador de DESENVOLVIMENTO — não vai para produção.
 *
 * O servidor embutido do PHP (`php -S`) não lê .htaccess, então este arquivo
 * reproduz localmente o que o Apache fará na HostGator: a reescrita de
 * /<slug> para oferta.php e os bloqueios de acesso a arquivos internos.
 *
 * Uso:
 *   php -S localhost:8000 dev-router.php
 *
 * O script de deploy exclui este arquivo do envio.
 */

$caminho = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Espelha o <FilesMatch> do .htaccess: arquivos internos não são servidos.
if (preg_match('~^/(dados|inc)/~', $caminho) || preg_match('~\.(json|md|log|bak|sql)$~i', $caminho)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

// Arquivos ocultos.
if (preg_match('~/\.~', $caminho)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

// Arquivo real no disco: deixa o servidor embutido entregar.
if ($caminho !== '/' && is_file(__DIR__ . $caminho)) {
    return false;
}

// Raiz.
if ($caminho === '' || $caminho === '/') {
    require __DIR__ . '/index.html';
    return true;
}

// Oferta: /<slug>
if (preg_match('~^/([a-z0-9-]+)/?$~', $caminho, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/oferta.php';
    return true;
}

http_response_code(404);
require __DIR__ . '/404.html';
return true;
