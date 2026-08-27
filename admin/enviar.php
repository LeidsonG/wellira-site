<?php
/**
 * Envio de foto de dentro do editor da oferta.
 *
 * Responde JSON. O arquivo sobe por fetch, sozinho, o formulário da oferta
 * nunca é enviado junto, então nada do texto digitado corre risco.
 *
 * A validação continua sendo receber_uploads(), a mesma função da tela de
 * upload. Este arquivo é só a porta de entrada em JSON.
 */

require_once __DIR__ . '/../inc/admin-funcoes.php';
require_once __DIR__ . '/../inc/auth.php';

// Responde e encerra.
function responder(array $dados, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Atalho para os casos em que só há o que recusar.
function recusar(string $mensagem, int $codigo): void
{
    responder(['ok' => false, 'nomes' => [], 'erros' => [$mensagem]], $codigo);
}

sessao_iniciar();

// Sem redirecionamento de propósito: fetch seguiria o Location e receberia
// HTML de login, indistinguível de erro para o JavaScript.
if (!autenticado() || sessao_expirada()) {
    if (sessao_expirada()) {
        encerrar_sessao();
    }
    recusar('Sua sessão expirou. Recarregue a página e entre de novo.', 401);
}
$_SESSION['visto_em'] = time();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    recusar('Envio inválido.', 405);
}

// Precisa vir antes do CSRF: post_max_size estourado zera $_POST, e o CSRF
// falharia primeiro com "sessão expirada".
if (post_estourou()) {
    recusar(
        'Foto grande demais. O servidor aceita no máximo '
        . round(ini_bytes((string) ini_get('post_max_size')) / 1048576, 1)
        . ' MB por envio. Mande uma de cada vez, ou reduza o tamanho da imagem.',
        413
    );
}

if (!csrf_ok()) {
    recusar('A página ficou aberta tempo demais. Recarregue e tente de novo.', 400);
}

// Só imagem. Vídeo continua exclusivamente pela tela de upload.
$resultado = receber_uploads($_FILES['arquivo'] ?? [], 'imagem');

responder([
    'ok'    => $resultado['nomes'] !== [],
    'nomes' => $resultado['nomes'],
    'erros' => $resultado['erros'],
]);
