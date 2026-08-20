<?php
/**
 * Recebe o formulário e grava a oferta.
 *
 * Não imprime nada: valida, grava e redireciona. Assim um F5 depois de salvar
 * não reenvia o formulário e não cria oferta duplicada.
 */

require_once __DIR__ . '/../inc/admin-funcoes.php';
require_once __DIR__ . '/../inc/auth.php';

exigir_login();
sessao_iniciar();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/');
    exit;
}
csrf_validar();

$slug_original = (string) ($_POST['slug_original'] ?? '');
$novo          = ($slug_original === '');

$oferta = normalizar_oferta($_POST);

// Slug: o que ela digitou, ou gerado do título quando deixou em branco.
$slug = gerar_slug((string) ($_POST['slug'] ?? ''));
if ($slug === '') {
    $slug = $novo
        ? slug_livre((string) ($oferta['titulo'] ?? ''))
        : $slug_original;
}

$renomeando = (!$novo && $slug !== $slug_original);
$erros = validar_oferta($oferta, $slug, $novo || $renomeando);

if ($erros) {
    // Devolve o que foi digitado para a cliente não perder o texto.
    $_SESSION['form_devolvido'] = ['oferta' => $oferta, 'erros' => $erros, 'slug' => $slug];
    $destino = $novo ? '/admin/editar.php' : '/admin/editar.php?slug=' . rawurlencode($slug_original);
    header('Location: ' . $destino);
    exit;
}

if (!salvar_oferta($slug, $oferta)) {
    $_SESSION['form_devolvido'] = [
        'oferta' => $oferta,
        'erros'  => ['Não consegui gravar o arquivo. Verifique a permissão da pasta dados/ofertas no servidor.'],
        'slug'   => $slug,
    ];
    header('Location: /admin/editar.php' . ($novo ? '' : '?slug=' . rawurlencode($slug_original)));
    exit;
}

// Renomear é criar no endereço novo e remover o antigo. A remoção vem por
// último e só depois da gravação confirmada: se algo falhar no meio, a oferta
// continua existindo no endereço velho em vez de desaparecer.
if ($renomeando) {
    excluir_oferta($slug_original);
}

$mensagem = $novo ? 'Oferta criada.' : 'Alterações salvas.';
if (($oferta['status'] ?? '') === 'publicado') {
    $mensagem .= ' A página está no ar em /' . $slug;
} else {
    $mensagem .= ' Continua como rascunho, fora do ar.';
}

$acao = (string) ($_POST['acao'] ?? '');

if ($acao === 'salvar_ver' && ($oferta['status'] ?? '') === 'publicado') {
    header('Location: /' . $slug);
    exit;
}

// Salvar mantém a cliente no formulário, na mesma aba. Voltar para a lista a
// cada gravação obrigava a reabrir a oferta e reencontrar o lugar — e o texto
// de venda é escrito em várias sessões, salvando pelo caminho.
$aba = (int) ($_POST['aba'] ?? 0);

header('Location: /admin/editar.php?slug=' . rawurlencode($slug)
     . '&aba=' . $aba
     . '&ok=' . rawurlencode($mensagem));
exit;
