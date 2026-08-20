<?php
/**
 * Cabeçalho e rodapé comuns às telas do painel.
 *
 * O painel é em português: quem usa é a cliente, e o inglês fica só no site
 * público.
 */

/** Abre a página. $titulo aparece na aba; $largo dá mais espaço ao formulário. */
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
      /* Marca o item da página aberta.
         Sem isso o menu é uma fileira de links idênticos, e a única pista de
         onde a pessoa está é o título da página — que no celular fica abaixo
         da dobra. */
      // Compara o caminho inteiro. basename() não serve: em /admin/ ele devolve
      // "admin", que não é nome de arquivo nenhum, e a lista nunca casava.
      $aqui = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
      $itens = [
          ['/admin/',            'Ofertas',       ['/admin/', '/admin/index.php']],
          // Só acende em oferta NOVA. Editando uma existente, "Nova oferta"
          // aceso diria que ela está criando algo, que é o oposto do que faz.
          ['/admin/editar.php',  'Nova oferta',   empty($_GET['slug']) ? ['/admin/editar.php'] : []],
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

/** Fecha a página. */
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
/* Confirmação de ação destrutiva.
   O texto vem do atributo data-confirmar e não de JavaScript gerado em PHP:
   interpolar o título dentro de confirm('...') quebrava a string sempre que ele
   tinha apóstrofo, e um handler com erro de sintaxe não impede envio nenhum. */
document.addEventListener('submit', function (evento) {
  var mensagem = evento.target.getAttribute && evento.target.getAttribute('data-confirmar');
  if (mensagem && !window.confirm(mensagem)) {
    evento.preventDefault();
  }
});
</script>

</body>
</html>
<?php
}

/** Caixa de aviso. $tipo: 'erro', 'ok' ou 'info'. */
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
