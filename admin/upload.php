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

// Precisa vir ANTES do csrf_validar(): com o corpo acima de post_max_size o PHP
// descarta $_POST inteiro, e a validação de CSRF falharia primeiro, devolvendo
// "Sessão expirada" para o que na verdade é um arquivo grande demais.
if (post_estourou()) {
    $erro = 'Arquivo grande demais. O servidor aceita no máximo '
          . round(ini_bytes((string) ini_get('post_max_size')) / 1048576, 1)
          . ' MB por envio.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $destino = ($_POST['destino'] ?? 'imagem') === 'video' ? 'video' : 'imagem';

    $resultado = receber_upload($_FILES['arquivo'] ?? [], $destino);
    if (isset($resultado['erro'])) {
        $erro = $resultado['erro'];
    } else {
        $enviado = $resultado['nome'];
    }
}

$limite = limite_upload($destino);

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
        Limite de <?= round($limite / 1048576, 1) ?> MB.
      </p>
      <p class="ajuda">
        Se aparecer <em>"formato não reconhecido"</em>, o arquivo não é de
        verdade do tipo que o nome diz. Trocar o final do nome não converte o
        arquivo. É preciso salvá-lo no formato certo pelo programa que o criou.
      </p>
      <?php if ($destino === 'video' && $limite < MAX_UPLOAD_VIDEO): ?>
        <p class="ajuda">
          <strong>O servidor limita mais do que gostaríamos.</strong> Para vídeo
          maior, use o YouTube: cole o endereço no campo de vídeo da oferta, em
          vez de enviar o arquivo.
        </p>
      <?php endif; ?>
    </div>

    <button type="submit">Enviar</button>
  </fieldset>
</form>

<p class="voltar"><a href="/admin/">&larr; Voltar para as ofertas</a></p>

<?php painel_rodape(); ?>
