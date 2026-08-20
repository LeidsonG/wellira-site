<?php
/**
 * Ações de uma linha da lista: duplicar e excluir.
 *
 * Só aceita POST com token. Se fossem links GET, bastaria a cliente abrir uma
 * página maliciosa — ou um robô seguir um link — para apagar uma oferta.
 */

require_once __DIR__ . '/../inc/admin-funcoes.php';
require_once __DIR__ . '/../inc/auth.php';

exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/');
    exit;
}
csrf_validar();

$slug = (string) ($_POST['slug'] ?? '');
$acao = (string) ($_POST['acao'] ?? '');

if (!slug_valido($slug)) {
    header('Location: /admin/?erro=' . rawurlencode('Oferta inválida.'));
    exit;
}

switch ($acao) {
    case 'duplicar':
        $original = carregar_oferta($slug);
        if ($original === null) {
            $resposta = ['erro', 'Não consegui ler a oferta original.'];
            break;
        }
        // A cópia nasce como rascunho, sempre. Duplicar é o primeiro passo de
        // uma oferta nova: publicar junto colocaria no ar uma página com o
        // texto e o link do produto errado.
        $original['status'] = 'rascunho';
        $original['titulo'] = 'Cópia de ' . (string) ($original['titulo'] ?? '');

        $novo = slug_livre($slug);
        $resposta = salvar_oferta($novo, $original)
            ? ['ok', 'Cópia criada como rascunho. Abra e ajuste o texto e o link.']
            : ['erro', 'Não consegui criar a cópia.'];
        break;

    case 'excluir':
        $resposta = excluir_oferta($slug)
            ? ['ok', 'Oferta excluída. Uma cópia de segurança ficou guardada no servidor.']
            : ['erro', 'Não consegui excluir a oferta.'];
        break;

    default:
        $resposta = ['erro', 'Ação desconhecida.'];
}

header('Location: /admin/?' . $resposta[0] . '=' . rawurlencode($resposta[1]));
exit;
