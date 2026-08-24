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
$enviados = [];

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

    // Vários de uma vez: a galeria de uma oferta tem três, quatro fotos, e
    // enviar uma por vez significava repetir o formulário inteiro a cada foto.
    // Um arquivo recusado não cancela os outros — a lista de erros sai ao lado
    // da lista do que entrou.
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
        // Ela não precisa mais copiar nome nenhum: o editor lista as imagens
        // enviadas e basta clicar. O nome fica à vista mesmo assim, porque é o
        // que aparece no campo depois e porque o painel não deve esconder da
        // cliente o que gravou no servidor dela.
        echo '<p>Volte para a oferta, abra a aba <strong>Imagens</strong> e clique '
           . 'na foto que quiser usar — ela já aparece lá.</p>';
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
      <?php /* Imagem aceita seleção múltipla; vídeo não. Não é falta de
               simetria: cada MP4 come dezenas de MB do post_max_size, e dois
               num envio só estouram o limite do servidor sem que nada explique
               por quê. Foto de produto tem alguns KB — a galeria inteira cabe
               numa seleção. */ ?>
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
