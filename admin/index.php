<?php
/**
 * Lista das ofertas, a tela em que a cliente passa a maior parte do tempo.
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
            <div class="acoes-grade">
              <a class="botao botao-fraco larga" href="/admin/editar.php?slug=<?= e($o['slug']) ?>">Abrir</a>
            </div>
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
          <?php /* Grade 2x2, na ordem em que ela usa: em cima o par do dia a dia
                   (editar / ver como ficou), embaixo o par ocasional (duplicar /
                   excluir). Antes eram quatro links em fila: no celular
                   quebravam em qualquer ponto, e "Excluir" tanto podia cair ao
                   lado de "Duplicar" quanto sozinho na linha seguinte. */ ?>
          <td class="acoes">
            <div class="acoes-grade">
              <a class="botao botao-fraco" href="/admin/editar.php?slug=<?= e($o['slug']) ?>">Editar</a>
              <?php /* Rascunho não existe em /<slug> e o cookie do painel não
                       acompanha a página pública: sem a prévia, este link
                       entregava um 404 para a oferta que a cliente acabou de
                       escrever, como se o painel estivesse quebrado. */ ?>
              <?php if ($o['status'] === 'publicado'): ?>
                <a class="botao botao-fraco" href="/<?= e($o['slug']) ?>" target="_blank" rel="noopener">Ver página</a>
              <?php else: ?>
                <a class="botao botao-fraco" href="/admin/previa.php?slug=<?= e($o['slug']) ?>" target="_blank" rel="noopener">Ver prévia</a>
              <?php endif; ?>
              <form method="post" action="/admin/acoes.php">
                <?= csrf_campo() ?>
                <input type="hidden" name="slug" value="<?= e($o['slug']) ?>">
                <input type="hidden" name="acao" value="duplicar">
                <button type="submit" class="botao botao-fraco">Duplicar</button>
              </form>
              <?php /* A mensagem vai num data-attribute, não em JS embutido: título
                       com apóstrofo ("Here's what changed") fechava a string do
                       confirm() e o handler morria com erro de sintaxe, o
                       formulário então enviava SEM perguntar nada.

                       O name="confirmado" é a segunda tranca, do lado do servidor:
                       quem apaga o campo é o próprio confirm(), então um POST de
                       excluir sem ele significa que a pergunta não foi respondida
                       (JS desligado, script quebrado, formulário forjado) e
                       acoes.php recusa. Clique acidental precisa vencer as duas. */ ?>
              <form method="post" action="/admin/acoes.php"
                    data-confirmar="Excluir a oferta &quot;<?= e($o['titulo']) ?>&quot;?&#10;&#10;A página sai do ar. Uma cópia de segurança fica guardada.">
                <?= csrf_campo() ?>
                <input type="hidden" name="slug" value="<?= e($o['slug']) ?>">
                <input type="hidden" name="acao" value="excluir">
                <button type="submit" class="botao botao-fraco perigo">Excluir</button>
              </form>
            </div>
          </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php painel_rodape(); ?>
