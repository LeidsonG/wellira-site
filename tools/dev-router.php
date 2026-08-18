<?php
/**
 * Roteador de DESENVOLVIMENTO — não vai para produção.
 *
 * O servidor embutido do PHP (`php -S`) não lê .htaccess, então este arquivo
 * reproduz localmente o que o Apache fará na HostGator: a reescrita de
 * /<slug> para oferta.php e os bloqueios de acesso a arquivos internos.
 *
 * Uso:
 *   php -S localhost:8000 tools/dev-router.php
 *
 * O script de deploy exclui este arquivo do envio.
 */

/** Raiz do site — este arquivo vive um nível abaixo dela. */
define('RAIZ', dirname(__DIR__));

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

// Normaliza a barra final, como faz o .htaccess (exceto em pastas reais).
if (strlen($caminho) > 1 && substr($caminho, -1) === '/' && !is_dir(RAIZ . $caminho)) {
    header('Location: ' . rtrim($caminho, '/'), true, 301);
    return true;
}

// Pasta real com index.html (páginas legais).
if (substr($caminho, -1) === '/' && is_file(RAIZ . $caminho . 'index.html')) {
    require RAIZ . $caminho . 'index.html';
    return true;
}

// Arquivo real no disco: deixa o servidor embutido entregar.
if ($caminho !== '/' && is_file(RAIZ . $caminho)) {
    return false;
}

// Raiz.
if ($caminho === '' || $caminho === '/') {
    require RAIZ . '/index.html';
    return true;
}

// Oferta: /<slug>
if (preg_match('~^/([a-z0-9-]+)/?$~', $caminho, $m)) {
    $_GET['slug'] = $m[1];
    require RAIZ . '/oferta.php';
    return true;
}

http_response_code(404);
require RAIZ . '/404.html';
return true;
