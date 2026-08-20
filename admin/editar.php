<?php
/**
 * Formulário de criação e edição de oferta.
 *
 * Organizado na ordem em que os blocos aparecem na página final, para que a
 * cliente consiga se localizar sem precisar decorar nomes de campo. Cada seção
 * opcional diz explicitamente o que acontece se ficar vazia — é a regra
 * "campo vazio = bloco some", dita na língua dela.
 */

require_once __DIR__ . '/../inc/admin-funcoes.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/layout.php';

exigir_login();
sessao_iniciar();

$slug  = (string) ($_GET['slug'] ?? '');
$novo  = ($slug === '');
$erros = [];

if ($novo) {
    $o = ['status' => 'rascunho', 'indexar' => true];
} else {
    $o = carregar_oferta($slug);
    if ($o === null) {
        header('Location: /admin/?erro=' . rawurlencode('Oferta não encontrada.'));
        exit;
    }
}

// Uma tentativa de salvar que falhou devolve os dados digitados pela sessão,
// para que a cliente não perca o texto que acabou de escrever.
if (!empty($_SESSION['form_devolvido'])) {
    $o     = $_SESSION['form_devolvido']['oferta'] ?? $o;
    $erros = $_SESSION['form_devolvido']['erros'] ?? [];
    $slug  = $_SESSION['form_devolvido']['slug'] ?? $slug;
    unset($_SESSION['form_devolvido']);
}

/** Valor de um campo simples, pronto para o atributo value. */
function v(array $o, string $campo, string $padrao = ''): string
{
    return e((string) ($o[$campo] ?? $padrao));
}

$autor = (array) ($o['autor'] ?? []);
$linhas_nao = (array) ($o['nao_e_para_voce'] ?? []);
$selos = (array) ($o['selos'] ?? []);
$faq   = (array) ($o['faq'] ?? []);

// Sempre sobra um par de campos em branco no fim das listas, para acrescentar
// item sem precisar de JavaScript nem recarregar a página.
$linhas_nao[] = '';
$selos[]      = ['icone' => 'escudo', 'titulo' => '', 'texto' => ''];
$faq[]        = ['pergunta' => '', 'resposta' => ''];

painel_topo($novo ? 'Nova oferta' : 'Editar oferta', true, true);
painel_aviso('erro', $erros);
?>

<div class="cabeca">
  <h1><?= $novo ? 'Nova oferta' : 'Editar oferta' ?></h1>
  <?php if (!$novo): ?>
    <a class="botao botao-fraco" href="/<?= e($slug) ?>" target="_blank" rel="noopener">Ver página</a>
  <?php endif; ?>
</div>

<form method="post" action="/admin/salvar.php" class="formulario">
  <?= csrf_campo() ?>
  <input type="hidden" name="slug_original" value="<?= e($novo ? '' : $slug) ?>">

  <!-- ==================== Publicação ==================== -->
  <fieldset>
    <legend>Publicação</legend>

    <div class="campo">
      <label>Situação</label>
      <div class="radios">
        <label class="radio">
          <input type="radio" name="status" value="rascunho"
                 <?= ($o['status'] ?? 'rascunho') !== 'publicado' ? 'checked' : '' ?>>
          <span><strong>Rascunho</strong> — só você vê. A página responde "não encontrada".</span>
        </label>
        <label class="radio">
          <input type="radio" name="status" value="publicado"
                 <?= ($o['status'] ?? '') === 'publicado' ? 'checked' : '' ?>>
          <span><strong>Publicada</strong> — no ar, qualquer pessoa com o link acessa.</span>
        </label>
      </div>
    </div>

    <div class="campo">
      <label class="check">
        <input type="checkbox" name="indexar" value="1"
               <?= ($o['indexar'] ?? true) !== false ? 'checked' : '' ?>>
        <span>Deixar esta página aparecer no Google</span>
      </label>
      <p class="ajuda">
        Desmarque em páginas de teste. Ofertas de verdade devem ficar marcadas,
        senão ninguém encontra a página pela busca.
      </p>
    </div>
  </fieldset>

  <!-- ==================== Endereço e topo ==================== -->
  <fieldset>
    <legend>Topo da página</legend>

    <div class="campo">
      <label for="titulo">Título <span class="obrig">obrigatório</span></label>
      <input type="text" id="titulo" name="titulo" value="<?= v($o, 'titulo') ?>"
             maxlength="200" required>
      <p class="ajuda">A frase grande no alto da página. É o que mais influencia o clique.</p>
    </div>

    <div class="campo">
      <label for="slug">Endereço da página</label>
      <div class="prefixo">
        <span>wellira.online/</span>
        <input type="text" id="slug" name="slug" value="<?= e($slug) ?>"
               maxlength="64" pattern="[a-z0-9-]+"
               placeholder="deixe em branco para gerar do título">
      </div>
      <p class="ajuda">
        Só letras minúsculas, números e hífen.
        <?php if (!$novo): ?>
          <strong>Cuidado:</strong> mudar o endereço quebra os links que já foram
          divulgados. A página antiga deixa de existir.
        <?php endif; ?>
      </p>
    </div>

    <div class="campo">
      <label for="eyebrow">Etiqueta acima do título</label>
      <input type="text" id="eyebrow" name="eyebrow" value="<?= v($o, 'eyebrow') ?>"
             maxlength="80" placeholder="Wellness · Reviewed">
      <p class="ajuda">Texto pequeno em destaque. Vazio: some.</p>
    </div>

    <div class="campo">
      <label for="subtitulo">Linha de apoio</label>
      <input type="text" id="subtitulo" name="subtitulo" value="<?= v($o, 'subtitulo') ?>"
             maxlength="300">
      <p class="ajuda">Uma frase abaixo do título. Vazio: some.</p>
    </div>
  </fieldset>

  <!-- ==================== Vídeo ==================== -->
  <fieldset>
    <legend>Vídeo</legend>

    <div class="campo">
      <label for="video">Link do YouTube ou arquivo enviado</label>
      <input type="text" id="video" name="video" value="<?= v($o, 'video') ?>"
             maxlength="500" placeholder="https://www.youtube.com/watch?v=...">
      <p class="ajuda">
        Cole o endereço do YouTube, ou
        <a href="/admin/upload.php?destino=video" target="_blank" rel="noopener">envie um arquivo MP4</a>
        e cole aqui o nome que aparecer. Vazio: a página fica sem vídeo.
      </p>
    </div>

    <div class="campo">
      <label for="video_legenda">Legenda do vídeo</label>
      <input type="text" id="video_legenda" name="video_legenda"
             value="<?= v($o, 'video_legenda') ?>" maxlength="150"
             placeholder="Watch: the full breakdown · 6:24">
      <p class="ajuda">Aparece sobre o botão de play. Confira a duração antes de escrever.</p>
    </div>

    <div class="campo">
      <label for="video_poster">Imagem de capa do vídeo</label>
      <input type="text" id="video_poster" name="video_poster"
             value="<?= v($o, 'video_poster') ?>" maxlength="200">
      <p class="ajuda">
        Nome do arquivo já enviado.
        <a href="/admin/upload.php?destino=imagem" target="_blank" rel="noopener">Enviar imagem</a>.
        Vazio no YouTube: usa a capa do próprio vídeo.
      </p>
    </div>
  </fieldset>

  <!-- ==================== Botão ==================== -->
  <fieldset>
    <legend>Botão de compra</legend>

    <div class="campo">
      <label for="link">Link do fornecedor <span class="obrig">obrigatório</span></label>
      <input type="url" id="link" name="link" value="<?= v($o, 'link') ?>"
             maxlength="500" required placeholder="https://...">
      <p class="ajuda">Para onde o botão leva. Precisa começar com https://</p>
    </div>

    <div class="campo">
      <label for="botao_texto">Texto do botão</label>
      <input type="text" id="botao_texto" name="botao_texto"
             value="<?= v($o, 'botao_texto', 'See the Official Site') ?>" maxlength="80">
    </div>

    <div class="dupla">
      <div class="campo">
        <label for="botao_sub">Linha sob o primeiro botão</label>
        <input type="text" id="botao_sub" name="botao_sub" value="<?= v($o, 'botao_sub') ?>"
               maxlength="200">
      </div>
      <div class="campo">
        <label for="botao_sub2">Linha sob o segundo botão</label>
        <input type="text" id="botao_sub2" name="botao_sub2" value="<?= v($o, 'botao_sub2') ?>"
               maxlength="200">
      </div>
    </div>
  </fieldset>

  <!-- ==================== Texto de venda ==================== -->
  <fieldset>
    <legend>Texto de venda</legend>

    <div class="campo">
      <label for="texto_titulo">Título da seção</label>
      <input type="text" id="texto_titulo" name="texto_titulo"
             value="<?= v($o, 'texto_titulo') ?>" maxlength="120"
             placeholder="The honest version">
    </div>

    <div class="campo">
      <label for="texto">Texto <span class="obrig">obrigatório para publicar</span></label>
      <textarea id="texto" name="texto" rows="18"><?= e((string) ($o['texto'] ?? '')) ?></textarea>
      <p class="ajuda">
        Deixe <strong>uma linha em branco</strong> entre parágrafos.
        Para criar um subtítulo, comece a linha com <code>## </code>.
      </p>
    </div>
  </fieldset>

  <!-- ==================== Autor ==================== -->
  <fieldset>
    <legend>Quem escreve <span class="opcional">seção opcional</span></legend>
    <p class="ajuda ajuda-topo">Sem o texto abaixo, a seção inteira some da página.</p>

    <div class="dupla">
      <div class="campo">
        <label for="autor_nome">Nome</label>
        <input type="text" id="autor_nome" name="autor_nome"
               value="<?= e((string) ($autor['nome'] ?? '')) ?>" maxlength="80">
      </div>
      <div class="campo">
        <label for="autor_cargo">Cargo</label>
        <input type="text" id="autor_cargo" name="autor_cargo"
               value="<?= e((string) ($autor['cargo'] ?? '')) ?>" maxlength="80">
      </div>
    </div>

    <div class="campo">
      <label for="autor_foto">Foto</label>
      <input type="text" id="autor_foto" name="autor_foto"
             value="<?= e((string) ($autor['foto'] ?? '')) ?>" maxlength="200">
      <p class="ajuda">
        Nome do arquivo já enviado.
        <a href="/admin/upload.php?destino=imagem" target="_blank" rel="noopener">Enviar imagem</a>.
        Vazio: aparece a inicial do nome.
      </p>
    </div>

    <div class="campo">
      <label for="autor_texto">Texto</label>
      <textarea id="autor_texto" name="autor_texto" rows="5"><?= e((string) ($autor['texto'] ?? '')) ?></textarea>
    </div>

    <div class="campo">
      <label for="autor_titulo">Título da seção</label>
      <input type="text" id="autor_titulo" name="autor_titulo"
             value="<?= v($o, 'autor_titulo') ?>" maxlength="120"
             placeholder="Why I'm sharing this">
    </div>
  </fieldset>

  <!-- ==================== Não é para você ==================== -->
  <fieldset>
    <legend>"Isto não é para você se…" <span class="opcional">seção opcional</span></legend>
    <p class="ajuda ajuda-topo">
      Dizer para quem o produto <em>não</em> serve aumenta a confiança de quem
      continua lendo. Sem nenhuma linha preenchida, a seção some.
    </p>

    <div class="campo">
      <label for="nao_e_para_voce_titulo">Título da seção</label>
      <input type="text" id="nao_e_para_voce_titulo" name="nao_e_para_voce_titulo"
             value="<?= v($o, 'nao_e_para_voce_titulo') ?>" maxlength="120"
             placeholder="This isn't for you if…">
    </div>

    <?php foreach ($linhas_nao as $i => $linha): ?>
      <div class="campo">
        <label>Linha <?= $i + 1 ?></label>
        <input type="text" name="nao_e_para_voce[]" value="<?= e((string) $linha) ?>"
               maxlength="200">
      </div>
    <?php endforeach; ?>

    <div class="campo">
      <label for="nao_e_para_voce_nota">Observação final</label>
      <textarea id="nao_e_para_voce_nota" name="nao_e_para_voce_nota" rows="3"><?= e((string) ($o['nao_e_para_voce_nota'] ?? '')) ?></textarea>
    </div>
  </fieldset>

  <!-- ==================== Selos ==================== -->
  <fieldset>
    <legend>Selos de confiança <span class="opcional">seção opcional</span></legend>
    <p class="ajuda ajuda-topo">
      <strong>Não escreva promessa de garantia, devolução ou reembolso.</strong>
      Quem responde por isso é o fornecedor, e a Wellira não pode prometer no
      lugar dele.
    </p>

    <?php foreach ($selos as $i => $selo): ?>
      <div class="grupo">
        <div class="tripla">
          <div class="campo">
            <label>Ícone</label>
            <select name="selo_icone[]">
              <?php foreach (array_keys(ICONES) as $nome): ?>
                <option value="<?= e($nome) ?>"
                  <?= ($selo['icone'] ?? '') === $nome ? 'selected' : '' ?>><?= e($nome) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo">
            <label>Título do selo <?= $i + 1 ?></label>
            <input type="text" name="selo_titulo[]"
                   value="<?= e((string) ($selo['titulo'] ?? '')) ?>" maxlength="60">
          </div>
          <div class="campo">
            <label>Linha de apoio</label>
            <input type="text" name="selo_texto[]"
                   value="<?= e((string) ($selo['texto'] ?? '')) ?>" maxlength="100">
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </fieldset>

  <!-- ==================== FAQ ==================== -->
  <fieldset>
    <legend>Perguntas frequentes <span class="opcional">seção opcional</span></legend>
    <p class="ajuda ajuda-topo">
      Pergunta e resposta precisam estar preenchidas para o item aparecer.
      Sobre devolução, remeta aos termos do fornecedor sem citar prazo.
    </p>

    <?php foreach ($faq as $i => $item): ?>
      <div class="grupo">
        <div class="campo">
          <label>Pergunta <?= $i + 1 ?></label>
          <input type="text" name="faq_pergunta[]"
                 value="<?= e((string) ($item['pergunta'] ?? '')) ?>" maxlength="200">
        </div>
        <div class="campo">
          <label>Resposta</label>
          <textarea name="faq_resposta[]" rows="3"><?= e((string) ($item['resposta'] ?? '')) ?></textarea>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="campo">
      <label for="faq_titulo">Título da seção</label>
      <input type="text" id="faq_titulo" name="faq_titulo" value="<?= v($o, 'faq_titulo') ?>"
             maxlength="120" placeholder="Common questions">
    </div>
  </fieldset>

  <!-- ==================== SEO e rodapé ==================== -->
  <fieldset>
    <legend>Busca e avisos legais</legend>

    <div class="campo">
      <label for="titulo_aba">Título na aba do navegador</label>
      <input type="text" id="titulo_aba" name="titulo_aba" value="<?= v($o, 'titulo_aba') ?>"
             maxlength="120">
      <p class="ajuda">Vazio: usa o título da página.</p>
    </div>

    <div class="campo">
      <label for="meta_descricao">Descrição para o Google</label>
      <textarea id="meta_descricao" name="meta_descricao" rows="2" maxlength="200"><?= v($o, 'meta_descricao') ?></textarea>
      <p class="ajuda">O trecho que aparece sob o título no resultado da busca. Até 160 caracteres.</p>
    </div>

    <div class="campo">
      <label for="avisos_legais">Avisos legais desta oferta</label>
      <textarea id="avisos_legais" name="avisos_legais" rows="4"><?= e((string) ($o['avisos_legais'] ?? '')) ?></textarea>
      <p class="ajuda">
        Vai no rodapé, depois do aviso padrão que toda oferta já tem.
        Use para exigência específica do fornecedor.
      </p>
    </div>
  </fieldset>

  <div class="barra-salvar">
    <button type="submit" name="acao" value="salvar">Salvar</button>
    <button type="submit" name="acao" value="salvar_ver" class="botao-fraco">Salvar e ver a página</button>
    <a class="cancelar" href="/admin/">Cancelar</a>
  </div>
</form>

<?php painel_rodape(); ?>
