<?php
/**
 * Envio de vídeo e imagem.
 *
 * Tela separada do formulário da oferta, para não perder o texto digitado se
 * o upload falhar por tamanho ou timeout. Ela envia, copia o nome gerado e
 * cola no campo da oferta.
 */

require_once __DIR__ . '/../inc/admin-funcoes.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/layout.php';

exigir_login();

$destino = ($_GET['destino'] ?? 'imagem') === 'video' ? 'video' : 'imagem';
$erro    = null;
$enviados = [];

// Precisa vir antes do csrf_validar(): post_max_size estourado zera $_POST,
// e o CSRF falharia primeiro com "Sessão expirada".
if (post_estourou()) {
    $erro = 'Arquivo grande demais. O servidor aceita no máximo '
          . round(ini_bytes((string) ini_get('post_max_size')) / 1048576, 1)
          . ' MB por envio.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $destino = ($_POST['destino'] ?? 'imagem') === 'video' ? 'video' : 'imagem';

    // Vários de uma vez: um arquivo recusado não cancela os outros.
    $resultado = receber_uploads($_FILES['arquivo'] ?? [], $destino);
    $enviados  = $resultado['nomes'];
    $erro      = $resultado['erros'] ?: null;
}

$limite = limite_upload($destino);

painel_topo($destino === 'video' ? 'Enviar vídeo' : 'Enviar imagem');
?>

<div class="cabeca">
  <h1><?= $destino === 'video' ? 'Enviar vídeo' : 'Enviar imagem' ?></h1>
</div>

<?php
painel_aviso('erro', $erro);

if ($enviados) {
    echo '<div class="aviso aviso-ok">';
    echo '<p><strong>' . count($enviados) . (count($enviados) === 1 ? ' arquivo enviado.' : ' arquivos enviados.') . '</strong></p>';
    foreach ($enviados as $nome) {
        echo '<p class="nome-arquivo"><code>' . e($nome) . '</code></p>';
    }
    if ($destino === 'imagem') {
        echo '<p>Volte para a oferta, abra a aba <strong>Imagens</strong> e clique '
           . 'na foto que quiser usar, ela já aparece lá.</p>';
    } else {
        echo '<p>Copie o nome acima e cole no campo de vídeo da oferta.</p>';
    }
    echo '</div>';
}
?>

<form method="post" enctype="multipart/form-data" class="formulario">
  <?= csrf_campo() ?>
  <input type="hidden" name="destino" value="<?= e($destino) ?>">

  <fieldset>
    <legend><?= $destino === 'video' ? 'Arquivo de vídeo' : 'Arquivo de imagem' ?></legend>

    <div class="campo">
      <label for="arquivo"><?= $destino === 'video' ? 'Escolha o arquivo' : 'Escolha as imagens' ?></label>
      <?php // Imagem aceita seleção múltipla; vídeo não, cada MP4 come dezenas de MB. ?>
      <input type="file" id="arquivo" name="arquivo<?= $destino === 'imagem' ? '[]' : '' ?>" required
             <?= $destino === 'imagem' ? 'multiple' : '' ?>
             accept="<?= $destino === 'video' ? 'video/mp4' : 'image/jpeg,image/png,image/webp' ?>">
      <p class="ajuda">
        <?= $destino === 'video'
            ? 'Somente MP4.'
            : 'JPG, PNG ou WebP. Pode escolher várias de uma vez.' ?>
        Limite de <?= round($limite / 1048576, 1) ?> MB<?= $destino === 'imagem' ? ' por imagem' : '' ?>.
      </p>
      <?php if ($destino === 'imagem'): ?>
        <p class="ajuda">
          O servidor também tem um teto para o envio inteiro
          (<?= round(ini_bytes((string) ini_get('post_max_size')) / 1048576, 1) ?> MB somando tudo).
          Se escolher muitas fotos pesadas de uma vez, mande em duas levas.
        </p>
      <?php endif; ?>
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
