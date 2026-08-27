<?php
/**
 * Template único das páginas de oferta.
 *
 * Recebe o slug pela reescrita do .htaccess (/<slug> → oferta.php?slug=<slug>)
 * e monta a página a partir do JSON correspondente em dados/ofertas/.
 *
 * A regra que sustenta o layout é "campo vazio = bloco some": cada seção
 * opcional só é impressa se houver conteúdo.
 */

require_once __DIR__ . '/inc/funcoes.php';

$slug   = (string) ($_GET['slug'] ?? '');
$oferta = carregar_oferta($slug);

if ($oferta === null) {
    nao_encontrado();
}

// PREVIA_ADMIN é definida por admin/previa.php, que já exigiu login.
$previa = defined('PREVIA_ADMIN');

// Rascunho é invisível ao público. Na prévia do painel ele aparece, com
// tarja e sem chance de ser indexado.
if (!$previa && ($oferta['status'] ?? 'rascunho') !== 'publicado') {
    nao_encontrado();
}

$destino = link_seguro((string) ($oferta['link'] ?? ''));
if ($destino === null && !$previa) {
    // Sem destino válido não há oferta. A prévia é a exceção: lá o botão
    // fica inerte e a tarja avisa o que falta.
    nao_encontrado();
}

// Os botões apontam para a saída própria, que conta o clique e só então
// manda ao fornecedor. Na prévia o botão vai direto ao fornecedor.
$link = $previa ? ($destino ?? '#') : '/go/' . $slug;

$titulo      = (string) ($oferta['titulo'] ?? '');
$botao       = (string) ($oferta['botao_texto'] ?? 'See the Official Site');
$meta        = (string) ($oferta['meta_descricao'] ?? ($oferta['subtitulo'] ?? ''));
$titulo_aba  = trim((string) ($oferta['titulo_aba'] ?? $titulo));

$url_canonica = SITE_URL . '/' . $slug;

// Ofertas de demonstração ficam fora do índice sem sair do ar. Ausente = indexável.
$indexar = !$previa && ($oferta['indexar'] ?? true) !== false;

// Montada antes do <head> para decidir se carrega o script do carrossel.
$galeria     = render_galeria($oferta);
$tem_carrossel = strpos($galeria, 'galeria-carrossel') !== false;

// Botão de ação, repetido ao longo da página.
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

// Atributo de classe da próxima seção, alternando a faixa de fundo. Decidido
// na hora de imprimir porque toda seção é opcional.
function faixa(): string
{
    static $n = 0;
    return $n++ % 2 === 1 ? ' class="band"' : '';
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
<link rel="canonical" href="<?= e($url_canonica) ?>">
<?php if (!$indexar): ?>
<meta name="robots" content="noindex, follow">
<?php endif; ?>

<meta property="og:type" content="article">
<meta property="og:site_name" content="Wellira">
<meta property="og:url" content="<?= e($url_canonica) ?>">
<meta property="og:title" content="<?= e($titulo_aba) ?>">
<?php if ($meta !== ''): ?>
<meta property="og:description" content="<?= e($meta) ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">

<link rel="icon" type="image/png" href="/assets/img/favicon.png">
<link rel="apple-touch-icon" href="/assets/img/favicon.png">

<link rel="stylesheet" href="/assets/css/style.css">
<script src="/assets/js/ids.js" defer></script>
<script src="/assets/js/rastreamento.js" defer></script>
<?php if ($tem_carrossel): ?>
<script src="/assets/js/galeria.js" defer></script>
<?php endif; ?>
<?php if ($previa): ?>
<?php /* Estilo da tarja fica aqui, não em style.css: visitante público nunca carrega. */ ?>
<style>
.tarja-previa {
  position: sticky; top: 0; z-index: 50;
  background: #b8431f; color: #fff;
  font: 600 0.9rem/1.4 system-ui, sans-serif;
  padding: 0.7rem 1.25rem; text-align: center;
}
.tarja-previa a { color: #fff; }
.tarja-previa span { font-weight: 400; opacity: .9; display: block; }
</style>
<?php endif; ?>
</head>
<body>

<?php if ($previa): ?>
<div class="tarja-previa">
  <?= ($oferta['status'] ?? 'rascunho') === 'publicado'
      ? 'Prévia de uma oferta que já está no ar'
      : 'Prévia de rascunho — esta página ainda não está no ar' ?>
  <?php if ($destino === null): ?>
    <span>Falta o link do fornecedor: os botões desta página ainda não levam a lugar nenhum.
      <a href="/admin/editar.php?slug=<?= e($slug) ?>&amp;aba=3">Preencher agora</a></span>
  <?php else: ?>
    <span>Só você enxerga esta tela. <a href="/admin/editar.php?slug=<?= e($slug) ?>">Voltar para a edição</a></span>
  <?php endif; ?>
</div>
<?php endif; ?>

<header class="site-head">
  <div class="wrap">
    <span class="logo"><img class="logo-mark" src="/assets/img/favicon.png" alt="" width="26" height="26">Well<span>ira</span></span>
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

      <?= $galeria ?>

      <?= cta($link, $botao, $oferta['botao_sub'] ?? null) ?>
    </div>
  </section>

  <?php if (!empty($oferta['beneficios'])): ?>
  <section<?= faixa() ?>>
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
  <section<?= faixa() ?>>
    <div class="wrap">
      <?php if (!empty($oferta['texto_titulo'])): ?>
        <h2><?= e($oferta['texto_titulo']) ?></h2>
      <?php endif; ?>
      <?= paragrafos((string) $oferta['texto']) ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (($oferta['mostrar_nao_e_para_voce'] ?? true) !== false && !empty($oferta['nao_e_para_voce'])): ?>
  <section<?= faixa() ?>>
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

  <section<?= faixa() ?>>
    <div class="wrap">
      <?php if (($oferta['mostrar_selos'] ?? true) !== false && !empty($oferta['selos'])): ?>
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

      <?= cta($link, $botao, $oferta['botao_sub'] ?? null) ?>
    </div>
  </section>

  <?php if (($oferta['mostrar_faq'] ?? true) !== false && !empty($oferta['faq'])): ?>
  <section<?= faixa() ?>>
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

      <?= cta($link, $botao, $oferta['botao_sub'] ?? null) ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (($oferta['mostrar_autor'] ?? true) !== false && !empty($oferta['autor']['texto'])): $a = $oferta['autor']; ?>
  <section<?= faixa() ?>>
    <div class="wrap">
      <h2><?= e($oferta['autor_titulo'] ?? "Why I'm sharing this") ?></h2>
      <div class="author">
        <?php if (!empty($a['foto'])):
          // "/" no início é arquivo do projeto; senão, nome enviado pelo painel (só o basename).
          $foto = $a['foto'][0] === '/'
                ? $a['foto']
                : URL_UPLOADS . '/' . rawurlencode(basename($a['foto']));
        ?>
          <img class="author-photo" src="<?= e($foto) ?>"
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

</main>

<footer class="legal">
  <div class="wrap">
    <nav class="legal-links">
      <a href="/privacy-policy/">Privacy Policy</a>
      <a href="/terms-of-service/">Terms of Service</a>
      <a href="/contact/">Contact</a>
    </nav>
    <?php foreach (avisos($oferta) as $aviso): ?>
      <?= $aviso /* bloco pronto: base é constante, extra já passou por paragrafos() */ ?>
    <?php endforeach; ?>
    <p>&copy; <?= date('Y') ?> Wellira. All rights reserved.</p>
  </div>
</footer>

<div class="sticky-cta">
  <a class="btn" href="<?= e($link) ?>" rel="nofollow sponsored noopener"><?= e($botao) ?> &rarr;</a>
</div>

<script>
// Fachada do vídeo: o player só carrega quando o visitante clica.
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
