<?php
/**
 * Troca da senha do painel.
 *
 * A cliente recebe uma senha provisória e troca por aqui no primeiro acesso,
 * sem depender de suporte técnico.
 */

require_once __DIR__ . '/../inc/funcoes.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/layout.php';

exigir_login();

$erro = null;
$ok   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $erro = trocar_senha(
        (string) ($_POST['atual'] ?? ''),
        (string) ($_POST['nova'] ?? ''),
        (string) ($_POST['confirmacao'] ?? '')
    );
    $ok = ($erro === null);
}

painel_topo('Trocar senha');
?>

<div class="cabeca">
  <h1>Trocar senha</h1>
</div>

<?php
painel_aviso('erro', $erro);
if ($ok) {
    painel_aviso('ok', 'Senha alterada. Use a nova na próxima vez que entrar.');
}
?>

<form method="post" class="formulario" autocomplete="off">
  <?= csrf_campo() ?>

  <fieldset>
    <legend>Nova senha</legend>

    <div class="campo">
      <label for="atual">Senha atual</label>
      <input type="password" id="atual" name="atual" required autocomplete="current-password">
    </div>

    <div class="campo">
      <label for="nova">Nova senha</label>
      <input type="password" id="nova" name="nova" required
             minlength="<?= SENHA_MINIMA ?>" autocomplete="new-password">
      <p class="ajuda">
        Pelo menos <?= SENHA_MINIMA ?> caracteres. Uma frase de que você lembre,
        com números, funciona melhor do que uma palavra curta complicada.
      </p>
    </div>

    <div class="campo">
      <label for="confirmacao">Repita a nova senha</label>
      <input type="password" id="confirmacao" name="confirmacao" required
             autocomplete="new-password">
    </div>

    <button type="submit">Trocar senha</button>
  </fieldset>
</form>

<p class="voltar"><a href="/admin/">&larr; Voltar para as ofertas</a></p>

<?php painel_rodape(); ?>
