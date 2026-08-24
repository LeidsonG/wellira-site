<?php
/**
 * Envio de foto de dentro do editor da oferta.
 *
 * Responde JSON e existe para um motivo específico: o formulário da oferta é
 * longo, e um <input type="file"> DENTRO dele faria o navegador mandar a oferta
 * inteira junto com o arquivo. Se o envio falhasse por tamanho ou por timeout —
 * o que acontece em conexão doméstica —, a cliente perderia tudo o que digitou.
 * É a mesma razão pela qual admin/upload.php nasceu como tela separada.
 *
 * Aqui o arquivo sobe por fetch, sozinho, e o formulário da oferta nunca é
 * enviado. Volta o nome gravado, que o JavaScript escreve no campo. Nada do que
 * ela escreveu corre risco.
 *
 * A tela admin/upload.php continua existindo e continua sendo a saída de quem
 * está sem JavaScript — e é a única forma de enviar vídeo.
 *
 * Nenhuma validação nova mora aqui: quem decide o que entra continua sendo
 * receber_uploads(), a mesma função da tela de upload, com a checagem por
 * assinatura binária. Este arquivo é só a porta de entrada em JSON.
 */

require_once __DIR__ . '/../inc/admin-funcoes.php';
require_once __DIR__ . '/../inc/auth.php';

/** Responde e encerra. */
function responder(array $dados, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    // Resposta de painel nunca deve ser embutida nem farejada por tipo.
    header('X-Content-Type-Options: nosniff');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Atalho para os casos em que só há o que recusar. */
function recusar(string $mensagem, int $codigo): void
{
    responder(['ok' => false, 'nomes' => [], 'erros' => [$mensagem]], $codigo);
}

sessao_iniciar();

// Sem redirecionamento de propósito: exigir_login() manda um Location para a
// tela de entrada, e o fetch seguiria esse redirecionamento e receberia o HTML
// do login — que para o JavaScript é indistinguível de qualquer outra falha. Um
// 401 com texto em português é o que permite dizer à cliente o que aconteceu.
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

// Precisa vir ANTES do CSRF: com o corpo acima de post_max_size o PHP descarta
// $_POST inteiro, e a conferência de CSRF falharia primeiro — devolvendo
// "sessão expirada" para o que na verdade é arquivo grande demais.
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

// Só imagem. Vídeo continua exclusivamente pela tela de upload: são dezenas de
// MB por arquivo, e um envio desses precisa da tela inteira dedicada a ele, com
// o aviso do limite do servidor à vista.
$resultado = receber_uploads($_FILES['arquivo'] ?? [], 'imagem');

responder([
    'ok'    => $resultado['nomes'] !== [],
    'nomes' => $resultado['nomes'],
    'erros' => $resultado['erros'],
]);
