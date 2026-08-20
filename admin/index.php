<?php
/**
 * Lista das ofertas — a tela em que a cliente passa a maior parte do tempo.
 *
 * Prioriza as três ações que ela repete: abrir para editar, duplicar e ver a
 * página no ar.
 */

require_once __DIR__ . '/../inc/admin-funcoes.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/layout.php';

exigir_login();

$slugs   = listar_ofertas();
$ofertas = [];

foreach ($slugs as $slug) {
    $o = carregar_oferta($slug);
    if ($o === null) {
        // JSON corrompido não pode sumir da lista em silêncio: se a cliente não
        // enxerga o arquivo, ela nunca descobre que a página caiu.
        $ofertas[] = ['slug' => $slug, 'quebrada' => true];
        continue;
    }
    $ofertas[] = [
        'slug'      => $slug,
        'titulo'    => (string) ($o['titulo'] ?? '(sem título)'),
        'status'    => (string) ($o['status'] ?? 'rascunho'),
        'indexar'   => ($o['indexar'] ?? true) !== false,
        'cliques'   => ler_cliques($slug),
        'alterada'  => filemtime(DIR_OFERTAS . '/' . $slug . '.json') ?: 0,
        'quebrada'  => false,
    ];
}

// Mais recentes primeiro: a oferta em que ela mexeu por último é a que ela
// provavelmente quer abrir de novo.
usort($ofertas, fn($a, $b) => ($b['alterada'] ?? 0) <=> ($a['alterada'] ?? 0));

painel_topo('Ofertas');

painel_aviso('ok', $_GET['ok'] ?? null);
painel_aviso('erro', $_GET['erro'] ?? null);
?>

<div class="cabeca">
  <h1>Ofertas</h1>
  <a class="botao" href="/admin/editar.php">+ Nova oferta</a>
</div>

<?php if (!$ofertas): ?>
  <div class="vazio">
    <p><strong>Nenhuma oferta ainda.</strong></p>
    <p>Clique em <em>Nova oferta</em> para criar a primeira página.</p>
  </div>
<?php else: ?>
  <table class="lista">
    <thead>
      <tr>
        <th>Oferta</th>
        <th>Situação</th>
        <th class="num">Cliques</th>
        <th>Ações</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($ofertas as $o): ?>
      <tr>
        <?php if ($o['quebrada']): ?>
          <td colspan="3">
            <strong><?= e($o['slug']) ?></strong>
            <span class="etiqueta etiqueta-erro">arquivo com defeito</span>
            <div class="caminho">Não consegui ler este arquivo. Restaure um backup.</div>
          </td>
          <td class="acoes">
            <a href="/admin/editar.php?slug=<?= e($o['slug']) ?>">Abrir</a>
          </td>
        <?php else: ?>
          <td>
            <strong><?= e($o['titulo']) ?></strong>
            <div class="caminho">/<?= e($o['slug']) ?></div>
          </td>
          <td>
            <?php if ($o['status'] === 'publicado'): ?>
              <span class="etiqueta etiqueta-ok">no ar</span>
            <?php else: ?>
              <span class="etiqueta">rascunho</span>
            <?php endif; ?>
            <?php if (!$o['indexar']): ?>
              <span class="etiqueta etiqueta-fraca" title="Não aparece no Google">fora do Google</span>
            <?php endif; ?>
          </td>
          <td class="num"><?= number_format($o['cliques'], 0, ',', '.') ?></td>
          <td class="acoes">
            <a href="/admin/editar.php?slug=<?= e($o['slug']) ?>">Editar</a>
            <a href="/<?= e($o['slug']) ?>" target="_blank" rel="noopener">Ver página</a>
            <form method="post" action="/admin/acoes.php" class="em-linha">
              <?= csrf_campo() ?>
              <input type="hidden" name="slug" value="<?= e($o['slug']) ?>">
              <input type="hidden" name="acao" value="duplicar">
              <button type="submit" class="link">Duplicar</button>
            </form>
            <form method="post" action="/admin/acoes.php" class="em-linha"
                  onsubmit="return confirm('Excluir a oferta &quot;<?= e($o['titulo']) ?>&quot;?\n\nA página sai do ar. Uma cópia de segurança fica guardada.');">
              <?= csrf_campo() ?>
              <input type="hidden" name="slug" value="<?= e($o['slug']) ?>">
              <input type="hidden" name="acao" value="excluir">
              <button type="submit" class="link perigo">Excluir</button>
            </form>
          </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php painel_rodape(); ?>
