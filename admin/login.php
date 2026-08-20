<?php
/**
 * Tela de entrada do painel.
 *
 * Só senha, sem usuário: a cliente é a única pessoa que usa isto.
 */

require_once __DIR__ . '/../inc/funcoes.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/layout.php';

sessao_iniciar();

// Já entrou? Não faz sentido mostrar o formulário de novo.
if (autenticado()) {
    header('Location: /admin/');
    exit;
}

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $erro = tentar_login(
        (string) ($_POST['usuario'] ?? ''),
        (string) ($_POST['senha'] ?? '')
    );

    if ($erro === null) {
        // Volta para onde a pessoa tentou ir antes de ser barrada. O destino é
        // conferido para não virar redirecionamento aberto: só caminho interno
        // do painel, nunca URL absoluta para outro site.
        $destino = (string) ($_SESSION['destino'] ?? '/admin/');
        unset($_SESSION['destino']);
        if (!preg_match('~^/admin/[A-Za-z0-9._/?=&-]*$~', $destino)) {
            $destino = '/admin/';
        }
        header('Location: ' . $destino);
        exit;
    }
}

painel_topo('Entrar', false);
?>

<div class="caixa-login">
  <h1>Painel Wellira</h1>
  <p class="sub">Entre para criar e editar as páginas de oferta.</p>

  <?php
  if (!empty($_GET['expirou'])) {
      painel_aviso('info', 'Sua sessão expirou por inatividade. Entre de novo.');
  }
  if (!empty($_GET['saiu'])) {
      painel_aviso('ok', 'Você saiu do painel.');
  }
  painel_aviso('erro', $erro);

  if (credenciais() === null) {
      painel_aviso('erro', 'O painel ainda não tem acesso configurado no servidor. '
                         . 'Gere as credenciais com tools/gerar-hash.php e grave em dados/senha.php.');
  }
  ?>

  <form method="post" autocomplete="off">
    <?= csrf_campo() ?>
    <label for="usuario">Usuário</label>
    <input type="text" id="usuario" name="usuario" required autofocus
           autocomplete="username" autocapitalize="none" spellcheck="false">

    <label for="senha">Senha</label>
    <input type="password" id="senha" name="senha" required
           autocomplete="current-password">
    <button type="submit">Entrar</button>
  </form>
</div>

<?php painel_rodape(); ?>
