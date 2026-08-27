<?php
/**
 * Cabeçalho e rodapé comuns às telas do painel.
 *
 * O painel é em português: quem usa é a cliente, e o inglês fica só no site
 * público.
 */

// Abre a página. $titulo aparece na aba; $largo dá mais espaço ao formulário.
function painel_topo(string $titulo, bool $logado = true, bool $largo = false, string $classe = ''): void
{
    ?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($titulo) ?> · Painel Wellira</title>
<link rel="icon" type="image/png" href="/assets/img/favicon.png">
<?php // Marca JS antes do CSS, pois admin.js é defer e chegaria tarde. ?>
<script>document.documentElement.className += ' js';</script>
<link rel="stylesheet" href="/assets/css/admin.css">
<script src="/assets/js/admin.js" defer></script>
</head>
<?php $classes = trim(($largo ? 'largo ' : '') . $classe); ?>
<body<?= $classes !== '' ? ' class="' . e($classes) . '"' : '' ?>>

<?php if ($logado): ?>
<header class="topo">
  <div class="wrap">
    <a class="marca" href="/admin/"><img class="marca-icone" src="/assets/img/favicon.png"
         alt="" width="26" height="26">Well<span>ira</span> <em>painel</em></a>
      <?php
      // Marca o item da página aberta. basename() não serve aqui: em /admin/
      // ele devolve "admin", e a lista nunca casaria.
      $aqui = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
      $itens = [
          ['/admin/',            'Ofertas',       ['/admin/', '/admin/index.php']],
          ['/admin/senha.php',   'Trocar acesso', ['/admin/senha.php']],
      ];
      ?>
      <nav>
        <?php foreach ($itens as [$href, $rotulo, $casa]): ?>
          <?php $ativo = in_array($aqui, $casa, true); ?>
          <a href="<?= e($href) ?>"<?= $ativo ? ' class="ativo" aria-current="page"' : '' ?>><?= e($rotulo) ?></a>
        <?php endforeach; ?>
        <a class="sair" href="/admin/sair.php">Sair</a>
      </nav>
  </div>
</header>
<?php endif; ?>

<main class="wrap">
<?php
}

// Fecha a página.
function painel_rodape(bool $logado = true): void
{
    ?>
</main>

<?php if ($logado): ?>
<footer class="rodape">
  <div class="wrap">
    Painel da Wellira. As alterações entram no ar assim que você salva uma
    oferta como <strong>publicada</strong>.
  </div>
</footer>
<?php endif; ?>

<script>
// Confirmação de ação destrutiva, lida do atributo data-confirmar.
document.addEventListener('submit', function (evento) {
  var formulario = evento.target;
  var mensagem = formulario.getAttribute && formulario.getAttribute('data-confirmar');
  if (!mensagem) { return; }
  if (!window.confirm(mensagem)) {
    evento.preventDefault();
    return;
  }
  // Carimba a resposta que o servidor exige para excluir.
  if (!formulario.querySelector('input[name="confirmado"]')) {
    var marca = document.createElement('input');
    marca.type = 'hidden';
    marca.name = 'confirmado';
    marca.value = '1';
    formulario.appendChild(marca);
  }
});
</script>

</body>
</html>
<?php
}

// Caixa de aviso. $tipo: 'erro', 'ok' ou 'info'.
function painel_aviso(string $tipo, $mensagens): void
{
    $lista = is_array($mensagens) ? $mensagens : [$mensagens];
    $lista = array_filter($lista, fn($m) => trim((string) $m) !== '');
    if (!$lista) {
        return;
    }
    echo '<div class="aviso aviso-' . e($tipo) . '">';
    if (count($lista) === 1) {
        echo '<p>' . e(reset($lista)) . '</p>';
    } else {
        echo '<ul>';
        foreach ($lista as $m) {
            echo '<li>' . e($m) . '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';
}
