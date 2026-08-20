<?php
/**
 * Envio de vídeo e imagem.
 *
 * Tela separada de propósito: o formulário da oferta é longo, e juntar upload
 * com ele faria a cliente perder tudo o que digitou se o envio falhasse por
 * tamanho ou timeout — o que, com vídeo em conexão doméstica, acontece.
 *
 * Aqui ela envia, copia o nome gerado e cola no campo da oferta.
 */

require_once __DIR__ . '/../inc/admin-funcoes.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/layout.php';

exigir_login();

$destino = ($_GET['destino'] ?? 'imagem') === 'video' ? 'video' : 'imagem';
$erro    = null;
$enviado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $destino = ($_POST['destino'] ?? 'imagem') === 'video' ? 'video' : 'imagem';

    $resultado = receber_upload($_FILES['arquivo'] ?? [], $destino);
    if (isset($resultado['erro'])) {
        $erro = $resultado['erro'];
    } else {
        $enviado = $resultado['nome'];
    }
}

$limite = $destino === 'video' ? MAX_UPLOAD_VIDEO : MAX_UPLOAD_IMAGEM;

painel_topo($destino === 'video' ? 'Enviar vídeo' : 'Enviar imagem');
?>

<div class="cabeca">
  <h1><?= $destino === 'video' ? 'Enviar vídeo' : 'Enviar imagem' ?></h1>
</div>

<?php
painel_aviso('erro', $erro);

if ($enviado !== null) {
    echo '<div class="aviso aviso-ok">';
    echo '<p><strong>Arquivo enviado.</strong> Copie o nome abaixo e cole no campo da oferta:</p>';
    echo '<p class="nome-arquivo"><code>' . e($enviado) . '</code></p>';
    echo '</div>';
}
?>

<form method="post" enctype="multipart/form-data" class="formulario">
  <?= csrf_campo() ?>
  <input type="hidden" name="destino" value="<?= e($destino) ?>">

  <fieldset>
    <legend><?= $destino === 'video' ? 'Arquivo de vídeo' : 'Arquivo de imagem' ?></legend>

    <div class="campo">
      <label for="arquivo">Escolha o arquivo</label>
      <input type="file" id="arquivo" name="arquivo" required
             accept="<?= $destino === 'video' ? 'video/mp4' : 'image/jpeg,image/png,image/webp' ?>">
      <p class="ajuda">
        <?= $destino === 'video'
            ? 'Somente MP4.'
            : 'JPG, PNG ou WebP.' ?>
        Limite de <?= round($limite / 1048576) ?> MB.
        O arquivo é conferido pelo conteúdo, não pelo nome — renomear a extensão não funciona.
      </p>
    </div>

    <button type="submit">Enviar</button>
  </fieldset>
</form>

<p class="voltar"><a href="/admin/">&larr; Voltar para as ofertas</a></p>

<?php painel_rodape(); ?>
