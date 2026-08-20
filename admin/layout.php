<?php
/**
 * Cabeçalho e rodapé comuns às telas do painel.
 *
 * O painel é em português: quem usa é a cliente, e o inglês fica só no site
 * público.
 */

/** Abre a página. $titulo aparece na aba; $largo dá mais espaço ao formulário. */
function painel_topo(string $titulo, bool $logado = true, bool $largo = false): void
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
</head>
<body<?= $largo ? ' class="largo"' : '' ?>>

<header class="topo">
  <div class="wrap">
    <a class="marca" href="/admin/">Well<span>ira</span> <em>painel</em></a>
    <?php if ($logado): ?>
      <nav>
        <a href="/admin/">Ofertas</a>
        <a href="/admin/editar.php">Nova oferta</a>
        <a class="sair" href="/admin/sair.php">Sair</a>
      </nav>
    <?php endif; ?>
  </div>
</header>

<main class="wrap">
<?php
}

/** Fecha a página. */
function painel_rodape(): void
{
    ?>
</main>

<footer class="rodape">
  <div class="wrap">
    Painel da Wellira. As alterações entram no ar assim que você salva uma
    oferta como <strong>publicada</strong>.
  </div>
</footer>

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
