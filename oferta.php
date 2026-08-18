<?php
/**
 * Template único das páginas de oferta.
 *
 * Recebe o slug pela reescrita do .htaccess (/<slug> → oferta.php?slug=<slug>)
 * e monta a página a partir do JSON correspondente em dados/ofertas/.
 *
 * A regra que sustenta o layout é "campo vazio = bloco some": cada seção
 * opcional só é impressa se houver conteúdo. É o que permite um único template
 * atender de suplemento a eletrodoméstico sem que nenhuma página nasça com
 * seção vazia.
 */

require_once __DIR__ . '/inc/funcoes.php';

$slug   = (string) ($_GET['slug'] ?? '');
$oferta = carregar_oferta($slug);

if ($oferta === null) {
    nao_encontrado();
}

// Rascunho é invisível ao público: a cliente monta a página com calma e só
// divulga o link depois de publicar.
if (($oferta['status'] ?? 'rascunho') !== 'publicado') {
    nao_encontrado();
}

$link = link_seguro((string) ($oferta['link'] ?? ''));
if ($link === null) {
    // Sem destino válido não há oferta — melhor um 404 do que uma página com
    // botão que não leva a lugar nenhum.
    nao_encontrado();
}

$titulo      = (string) ($oferta['titulo'] ?? '');
$botao       = (string) ($oferta['botao_texto'] ?? 'See the Official Site');
$categoria   = (string) ($oferta['categoria'] ?? 'outro');
$meta        = (string) ($oferta['meta_descricao'] ?? ($oferta['subtitulo'] ?? ''));
$titulo_aba  = trim((string) ($oferta['titulo_aba'] ?? $titulo));

/** Botão de ação, repetido ao longo da página. */
function cta(string $link, string $texto, ?string $sub = null): string
{
    $html  = '<div class="cta-block">';
    $html .= '<a class="btn" href="' . e($link) . '" rel="nofollow sponsored noopener">'
           . e($texto) . ' &rarr;</a>';
    if ($sub !== null && $sub !== '') {
        $html .= '<p class="btn-sub">' . e($sub) . '</p>';
    }
    $html .= '</div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo_aba) ?> | Wellira</title>
<?php if ($meta !== ''): ?>
<meta name="description" content="<?= e($meta) ?>">
<?php endif; ?>
<?php if (BLOQUEAR_INDEXACAO): ?>
<!-- ⚠️ Bloqueio de indexação ativo. Trocar BLOQUEAR_INDEXACAO para false em
     inc/config.php antes de publicar em produção. Ver README.md. -->
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>

<meta property="og:type" content="article">
<meta property="og:site_name" content="Wellira">
<meta property="og:title" content="<?= e($titulo_aba) ?>">
<?php if ($meta !== ''): ?>
<meta property="og:description" content="<?= e($meta) ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">

<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<header class="site-head">
  <div class="wrap">
    <a class="logo" href="/">Well<span>ira</span></a>
  </div>
</header>

<main>

  <section class="hero">
    <div class="wrap">
      <?php if (!empty($oferta['eyebrow'])): ?>
        <span class="eyebrow"><?= e($oferta['eyebrow']) ?></span>
      <?php endif; ?>

      <h1><?= e($titulo) ?></h1>

      <?php if (!empty($oferta['subtitulo'])): ?>
        <p class="lede"><?= e($oferta['subtitulo']) ?></p>
      <?php endif; ?>

      <?= render_video($oferta) ?>

      <?= cta($link, $botao, $oferta['botao_sub'] ?? null) ?>
    </div>
  </section>

  <?php if (!empty($oferta['beneficios'])): ?>
  <section class="band">
    <div class="wrap">
      <?php if (!empty($oferta['beneficios_titulo'])): ?>
        <h2><?= e($oferta['beneficios_titulo']) ?></h2>
      <?php endif; ?>
      <div class="cards">
        <?php foreach ($oferta['beneficios'] as $item): ?>
          <article class="card">
            <h3><?= e($item['titulo'] ?? '') ?></h3>
            <p><?= e($item['texto'] ?? '') ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($oferta['texto'])): ?>
  <section>
    <div class="wrap">
      <?php if (!empty($oferta['texto_titulo'])): ?>
        <h2><?= e($oferta['texto_titulo']) ?></h2>
      <?php endif; ?>
      <?= paragrafos((string) $oferta['texto']) ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($oferta['como_funciona'])): ?>
  <section class="band">
    <div class="wrap">
      <h2><?= e($oferta['como_funciona_titulo'] ?? 'How it works') ?></h2>
      <ol class="steps">
        <?php foreach ($oferta['como_funciona'] as $passo): ?>
          <li>
            <h3><?= e($passo['titulo'] ?? '') ?></h3>
            <p><?= e($passo['texto'] ?? '') ?></p>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($oferta['autor']['texto'])): $a = $oferta['autor']; ?>
  <section>
    <div class="wrap">
      <h2><?= e($oferta['autor_titulo'] ?? "Why I'm sharing this") ?></h2>
      <div class="author">
        <?php if (!empty($a['foto'])): ?>
          <img class="author-photo" src="<?= e(URL_UPLOADS . '/' . rawurlencode(basename($a['foto']))) ?>"
               alt="<?= e($a['nome'] ?? '') ?>" width="88" height="88">
        <?php else: ?>
          <div class="author-photo" aria-hidden="true"><?= e(substr((string) ($a['nome'] ?? 'W'), 0, 1)) ?></div>
        <?php endif; ?>
        <div>
          <?php if (!empty($a['nome'])): ?>
            <div class="author-name"><?= e($a['nome']) ?></div>
          <?php endif; ?>
          <?php if (!empty($a['cargo'])): ?>
            <div class="author-role"><?= e($a['cargo']) ?></div>
          <?php endif; ?>
          <?= paragrafos((string) $a['texto']) ?>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($oferta['nao_e_para_voce'])): ?>
  <section class="band">
    <div class="wrap">
      <h2><?= e($oferta['nao_e_para_voce_titulo'] ?? "This isn't for you if…") ?></h2>
      <div class="not-for-you">
        <ul>
          <?php foreach ($oferta['nao_e_para_voce'] as $linha): ?>
            <li><?= e($linha) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php if (!empty($oferta['nao_e_para_voce_nota'])): ?>
        <p class="muted nota-apos-lista"><?= e($oferta['nao_e_para_voce_nota']) ?></p>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <section>
    <div class="wrap">
      <?php if (!empty($oferta['selos'])): ?>
        <ul class="badges">
          <?php foreach ($oferta['selos'] as $selo): ?>
            <li>
              <?= icone((string) ($selo['icone'] ?? 'escudo')) ?>
              <strong><?= e($selo['titulo'] ?? '') ?></strong>
              <span><?= e($selo['texto'] ?? '') ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?= cta($link, $botao, $oferta['botao_sub2'] ?? null) ?>
    </div>
  </section>

  <?php if (!empty($oferta['faq'])): ?>
  <section class="band">
    <div class="wrap">
      <h2><?= e($oferta['faq_titulo'] ?? 'Common questions') ?></h2>
      <div class="faq">
        <?php foreach ($oferta['faq'] as $i => $item): ?>
          <details<?= $i === 0 ? ' open' : '' ?>>
            <summary><?= e($item['pergunta'] ?? '') ?></summary>
            <?= paragrafos((string) ($item['resposta'] ?? '')) ?>
          </details>
        <?php endforeach; ?>
      </div>

      <?= cta($link, $botao) ?>
    </div>
  </section>
  <?php endif; ?>

</main>

<footer class="legal">
  <div class="wrap">
    <nav class="legal-links">
      <a href="/privacy-policy/">Privacy Policy</a>
      <a href="/terms-of-service/">Terms of Service</a>
      <a href="/contact/">Contact</a>
    </nav>
    <?php foreach (disclaimers($categoria) as $aviso): ?>
      <p><?= $aviso /* constante confiável de config.php, com HTML proposital */ ?></p>
    <?php endforeach; ?>
    <p>&copy; <?= date('Y') ?> Wellira. All rights reserved.</p>
  </div>
</footer>

<div class="sticky-cta">
  <a class="btn" href="<?= e($link) ?>" rel="nofollow sponsored noopener"><?= e($botao) ?> &rarr;</a>
</div>

<script>
/* Fachada do vídeo: o player só é carregado quando o visitante clica.
   Mantém a página leve em 4G e evita cookies de terceiro em quem nunca deu play. */
document.addEventListener('click', function (evento) {
  var alvo = evento.target.closest('.video');
  if (!alvo) return;

  var tipo = alvo.getAttribute('data-tipo');
  var src  = alvo.getAttribute('data-src');
  var caixa = document.createElement('div');
  caixa.className = 'video video-ativo';

  if (tipo === 'mp4') {
    var video = document.createElement('video');
    video.src = src;
    video.controls = true;
    video.autoplay = true;
    video.setAttribute('playsinline', '');
    caixa.appendChild(video);
  } else {
    var frame = document.createElement('iframe');
    frame.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(src) + '?autoplay=1&rel=0';
    frame.title = 'Product video';
    frame.allow = 'accelerometer; autoplay; encrypted-media; picture-in-picture';
    frame.allowFullscreen = true;
    caixa.appendChild(frame);
  }

  alvo.replaceWith(caixa);
});
</script>

</body>
</html>
