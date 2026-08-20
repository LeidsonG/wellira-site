<?php
/**
 * Formulário de criação e edição de oferta.
 *
 * Dividido em abas, na mesma ordem em que os blocos aparecem na página final.
 * A versão anterior era um formulário único de rolagem longa: para conferir o
 * FAQ depois de mexer no título era preciso atravessar a tela inteira, e as
 * listas (linhas, selos, perguntas) só ofereciam um campo em branco por vez —
 * acrescentar dois itens exigia salvar no meio.
 *
 * Cada seção opcional diz o que acontece se ficar vazia. É a regra
 * "campo vazio = bloco some", dita na língua da cliente.
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
    // Oferta nova já nasce com a assinatura preenchida: é sempre a mesma
    // pessoa, e obrigar a redigitar produz divergência entre páginas.
    $o = [
        'status'       => 'rascunho',
        'indexar'      => true,
        'autor'        => AUTOR_PADRAO,
        'autor_titulo' => AUTOR_TITULO_PADRAO,
    ];
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
if ($autor === []) {
    $autor = AUTOR_PADRAO;
}

/** A seção está ligada? Ausente vale ligada, para não sumir seção de oferta antiga. */
function ligada(array $o, string $secao): bool
{
    return ($o['mostrar_' . $secao] ?? true) !== false;
}

/**
 * Interruptor de seção.
 *
 * Substitui a regra antiga de "deixe em branco para sumir": ela obrigava a
 * apagar o conteúdo para esconder o bloco, e religar custava redigitar tudo.
 * Aqui o texto fica gravado e a caixa escurece, deixando visível o que vai e o
 * que não vai para a página.
 */
function interruptor(array $o, string $secao, string $alvo): void
{
    $on = ligada($o, $secao);
    ?>
    <label class="interruptor">
      <input type="checkbox" name="mostrar_<?= e($secao) ?>" value="1"
             data-alvo="<?= e($alvo) ?>" <?= $on ? 'checked' : '' ?>>
      <span class="interruptor-trilho" aria-hidden="true"></span>
      <span class="interruptor-texto">
        <strong>Mostrar esta seção na página</strong>
        <small>Desligada, o conteúdo fica guardado mas não aparece para o visitante.</small>
      </span>
    </label>
    <?php
}
$linhas_nao = array_values((array) ($o['nao_e_para_voce'] ?? []));
$selos      = array_values((array) ($o['selos'] ?? []));
$faq        = array_values((array) ($o['faq'] ?? []));

// Oferta nova começa com uma linha de cada lista, só para não abrir vazia.
// As demais a cliente acrescenta no botão — não há mais limite de uma por vez.
if (!$linhas_nao) { $linhas_nao = ['']; }
if (!$selos)      { $selos      = [['icone' => 'escudo', 'titulo' => '', 'texto' => '']]; }
if (!$faq)        { $faq        = [['pergunta' => '', 'resposta' => '']]; }

/**
 * Prompt que a cliente cola no ChatGPT.
 *
 * Ela já usa o ChatGPT para escrever o texto de venda. Sem instrução, o que
 * volta vem com markdown que o template não entende: **negrito**, listas com
 * hífen, títulos com # em vez de ##. O texto sai na página com os asteriscos
 * visíveis, e alguém tem que limpar à mão.
 *
 * O prompt ensina o formato aceito e as regras que o fornecedor impõe. Fica
 * aqui, e não no guia, porque é aqui que ela está quando precisa dele.
 */
$prompt_chatgpt = <<<'TXT'
Write the sales copy for a product landing page. Follow these rules exactly.

FORMAT (very important):
- Plain text only. No markdown, no bold, no italics, no bullet lists.
- Separate paragraphs with one blank line.
- To create a subheading, start the line with "## " followed by the text.
- Use 3 to 5 subheadings across the piece.

LANGUAGE:
- US English, written for an American reader.
- Imperial units (lbs, oz, in) and MM/DD/YYYY dates.
- Conversational and specific. Short sentences. No corporate voice.

WHAT YOU MUST NEVER WRITE:
- No guarantee, refund, return window or "money-back" of any kind.
- No promises of results ("lose X lbs", "works without diet or exercise",
  "works for everyone", "guaranteed").
- No step-by-step "how it works" section.
- No medical claims. Do not say the product treats, cures or prevents anything.

TONE THAT WORKS HERE:
- Honest about limitations. Say who the product is NOT for.
- Mention the drawbacks you would tell a friend about.
- The reader should feel informed, not pushed.

THE PRODUCT:
[describe the product here: what it is, who it is for, what the supplier says]

Length: 600 to 900 words.
TXT;

painel_topo($novo ? 'Nova oferta' : 'Editar oferta', true, true);

painel_aviso('erro', $erros);
if (!empty($_GET['ok'])) {
    painel_aviso('ok', (string) $_GET['ok']);
}
?>

<div class="cabeca">
  <div>
    <h1><?= $novo ? 'Nova oferta' : 'Editar oferta' ?></h1>
    <?php if (!$novo): ?>
      <p class="cabeca-sub">/<?= e($slug) ?></p>
    <?php endif; ?>
  </div>
  <?php if (!$novo): ?>
    <a class="botao botao-fraco" href="/<?= e($slug) ?>" target="_blank" rel="noopener">Ver página ↗</a>
  <?php endif; ?>
</div>

<nav class="abas" role="tablist" aria-label="Seções da oferta"></nav>

<form method="post" action="/admin/salvar.php" class="formulario" data-abas>
  <?= csrf_campo() ?>
  <input type="hidden" name="slug_original" value="<?= e($novo ? '' : $slug) ?>">

  <?php /* A aba aberta viaja no formulário e volta na URL. Guardá-la só no
           navegador fazia a cliente cair numa aba de outra oferta. */ ?>
  <input type="hidden" name="aba" id="aba-atual" value="<?= (int) ($_GET['aba'] ?? 0) ?>">

  <!-- ==================== 1. Publicação ==================== -->
  <section id="sec-publicacao" data-secao="Publicação">
    <div class="campo">
      <label>Situação</label>
      <div class="radios">
        <label class="radio">
          <input type="radio" name="status" value="rascunho"
                 <?= ($o['status'] ?? 'rascunho') !== 'publicado' ? 'checked' : '' ?>>
          <span><strong>Rascunho</strong><br><small>Só você vê. Quem abrir o endereço recebe "página não encontrada".</small></span>
        </label>
        <label class="radio">
          <input type="radio" name="status" value="publicado"
                 <?= ($o['status'] ?? '') === 'publicado' ? 'checked' : '' ?>>
          <span><strong>Publicada</strong><br><small>No ar. Qualquer pessoa com o link acessa.</small></span>
        </label>
      </div>
    </div>

    <div class="campo">
      <label class="check">
        <input type="checkbox" name="indexar" value="1"
               <?= ($o['indexar'] ?? true) !== false ? 'checked' : '' ?>>
        <span>Deixar esta página aparecer no Google</span>
      </label>
      <p class="ajuda">Desmarque só em páginas de teste. Ofertas de verdade devem ficar marcadas.</p>
    </div>
  </section>

  <!-- ==================== 2. Topo ==================== -->
  <section id="sec-topo" data-secao="Topo" hidden>
    <div class="campo">
      <label for="titulo">Título <span class="obrig">obrigatório</span></label>
      <input type="text" id="titulo" name="titulo" value="<?= v($o, 'titulo') ?>"
             maxlength="200" required>
      <p class="ajuda">A frase grande no alto. É o que mais influencia o clique.</p>
    </div>

    <div class="campo">
      <label for="slug">Endereço da página</label>
      <div class="prefixo">
        <span>wellira.online/</span>
        <input type="text" id="slug" name="slug" value="<?= e($slug) ?>"
               maxlength="64" pattern="[a-z0-9-]+"
               placeholder="deixe vazio para gerar do título">
      </div>
      <p class="ajuda">
        Minúsculas, números e hífen.
        <?php if (!$novo): ?>
          <strong>Cuidado:</strong> mudar quebra os links já divulgados.
        <?php endif; ?>
      </p>
    </div>

    <div class="dupla">
      <div class="campo">
        <label for="eyebrow">Etiqueta acima do título</label>
        <input type="text" id="eyebrow" name="eyebrow" value="<?= v($o, 'eyebrow') ?>"
               maxlength="80" placeholder="Wellness · Reviewed">
      </div>
      <div class="campo">
        <label for="subtitulo">Linha de apoio</label>
        <input type="text" id="subtitulo" name="subtitulo" value="<?= v($o, 'subtitulo') ?>"
               maxlength="300">
      </div>
    </div>
    <p class="ajuda">Vazios, os dois somem da página.</p>
  </section>

  <!-- ==================== 3. Vídeo ==================== -->
  <section id="sec-video" data-secao="Vídeo" hidden>
    <div class="campo">
      <label for="video">Link do YouTube <span class="dica">recomendado</span></label>
      <input type="text" id="video" name="video" value="<?= v($o, 'video') ?>"
             maxlength="500" placeholder="https://www.youtube.com/watch?v=...">
      <p class="ajuda">
        Cole o endereço do YouTube. Ou
        <a href="/admin/upload.php?destino=video" target="_blank" rel="noopener">envie um MP4</a>
        e cole aqui o nome do arquivo. Vazio: a página fica sem vídeo.
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
      <label for="video_poster">Imagem de capa</label>
      <input type="text" id="video_poster" name="video_poster"
             value="<?= v($o, 'video_poster') ?>" maxlength="200">
      <p class="ajuda">
        Nome do arquivo já enviado.
        <a href="/admin/upload.php?destino=imagem" target="_blank" rel="noopener">Enviar imagem</a>.
        Vazio no YouTube: usa a capa do próprio vídeo.
      </p>
    </div>
  </section>

  <!-- ==================== 4. Botão ==================== -->
  <section id="sec-botao" data-secao="Botão" hidden>
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
        <label for="botao_sub">Linha sob o 1º botão</label>
        <input type="text" id="botao_sub" name="botao_sub" value="<?= v($o, 'botao_sub') ?>" maxlength="200">
      </div>
      <div class="campo">
        <label for="botao_sub2">Linha sob o 2º botão</label>
        <input type="text" id="botao_sub2" name="botao_sub2" value="<?= v($o, 'botao_sub2') ?>" maxlength="200">
      </div>
    </div>
  </section>

  <!-- ==================== 5. Texto ==================== -->
  <section id="sec-texto" data-secao="Texto" hidden>
    <div class="campo">
      <label for="texto_titulo">Título da seção</label>
      <input type="text" id="texto_titulo" name="texto_titulo"
             value="<?= v($o, 'texto_titulo') ?>" maxlength="120" placeholder="The honest version">
    </div>

    <div class="campo">
      <label for="texto">Texto de venda <span class="obrig">obrigatório para publicar</span></label>
      <textarea id="texto" name="texto" rows="22"><?= e((string) ($o['texto'] ?? '')) ?></textarea>
      <p class="ajuda">
        <strong>Uma linha em branco</strong> separa parágrafos.
        Linha começando com <code>## </code> vira subtítulo. Não use asterisco nem hífen de lista.
      </p>
    </div>

    <details class="prompt-caixa">
      <summary>📋 Prompt para o ChatGPT escrever este texto</summary>
      <p class="ajuda ajuda-topo">
        Copie, cole no ChatGPT e substitua a linha do produto. O prompt já carrega
        as regras de formato e o que o fornecedor não permite escrever.
      </p>
      <textarea id="prompt-gpt" class="prompt-texto" rows="10" readonly><?= e($prompt_chatgpt) ?></textarea>
      <button type="button" class="botao botao-fraco" data-copiar="prompt-gpt">Copiar prompt</button>
    </details>
  </section>

  <!-- ==================== 6. Autor ==================== -->
  <section id="sec-autor" data-secao="Autor" hidden>
    <?php interruptor($o, 'autor', 'grupo-autor'); ?>
    <div id="grupo-autor" class="grupo-alternavel<?= ligada($o, 'autor') ? '' : ' desligado' ?>">
    <p class="ajuda ajuda-topo">
      Preenchido com a assinatura padrão da Wellira. Edite se precisar, ou
      desligue no interruptor acima para esta oferta específica.
    </p>

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
      <label for="autor_texto">Texto</label>
      <textarea id="autor_texto" name="autor_texto" rows="6"><?= e((string) ($autor['texto'] ?? '')) ?></textarea>
    </div>

    <div class="dupla">
      <div class="campo">
        <label for="autor_foto">Foto</label>
        <input type="text" id="autor_foto" name="autor_foto"
               value="<?= e((string) ($autor['foto'] ?? '')) ?>" maxlength="200">
        <p class="ajuda">
          <a href="/admin/upload.php?destino=imagem" target="_blank" rel="noopener">Enviar imagem</a>.
          Vazio: aparece a inicial do nome.
        </p>
      </div>
      <div class="campo">
        <label for="autor_titulo">Título da seção</label>
        <input type="text" id="autor_titulo" name="autor_titulo"
               value="<?= v($o, 'autor_titulo', AUTOR_TITULO_PADRAO) ?>" maxlength="120">
      </div>
    </div>
    </div>
  </section>

  <!-- ==================== 7. Não é para você ==================== -->
  <section id="sec-nao" data-secao="Não é para você" hidden>
    <?php interruptor($o, 'nao_e_para_voce', 'grupo-nao'); ?>
    <div id="grupo-nao" class="grupo-alternavel<?= ligada($o, 'nao_e_para_voce') ? '' : ' desligado' ?>">
    <p class="ajuda ajuda-topo">
      Dizer para quem o produto <em>não</em> serve aumenta a confiança de quem
      continua lendo.
    </p>

    <div class="campo">
      <label for="nao_e_para_voce_titulo">Título da seção</label>
      <input type="text" id="nao_e_para_voce_titulo" name="nao_e_para_voce_titulo"
             value="<?= v($o, 'nao_e_para_voce_titulo') ?>" maxlength="120"
             placeholder="This isn't for you if…">
    </div>

    <div id="lista-nao" class="repetivel">
      <?php foreach ($linhas_nao as $linha): ?>
        <div class="item" data-item>
          <span class="item-num"><span data-numero>1</span></span>
          <input type="text" name="nao_e_para_voce[]" value="<?= e((string) $linha) ?>"
                 maxlength="200" placeholder="You're looking for an overnight fix">
          <button type="button" class="remover" data-remover title="Remover esta linha">×</button>
        </div>
      <?php endforeach; ?>
    </div>

    <template id="molde-nao">
      <div class="item" data-item>
        <span class="item-num"><span data-numero>1</span></span>
        <input type="text" name="nao_e_para_voce[]" maxlength="200">
        <button type="button" class="remover" data-remover title="Remover esta linha">×</button>
      </div>
    </template>

    <button type="button" class="adicionar" data-adicionar="lista-nao" data-molde="molde-nao">
      + Adicionar linha
    </button>

    <div class="campo" style="margin-top:1.2rem">
      <label for="nao_e_para_voce_nota">Observação final</label>
      <textarea id="nao_e_para_voce_nota" name="nao_e_para_voce_nota" rows="3"><?= e((string) ($o['nao_e_para_voce_nota'] ?? '')) ?></textarea>
    </div>
    </div>
  </section>

  <!-- ==================== 8. Selos ==================== -->
  <section id="sec-selos" data-secao="Selos" hidden>
    <?php interruptor($o, 'selos', 'grupo-selos'); ?>
    <div id="grupo-selos" class="grupo-alternavel<?= ligada($o, 'selos') ? '' : ' desligado' ?>">
    <p class="ajuda ajuda-topo">
      <strong>Nunca escreva garantia, devolução ou reembolso.</strong>
      Quem responde por isso é o fornecedor, e a Wellira não pode prometer no lugar dele.
    </p>

    <div id="lista-selos" class="repetivel">
      <?php foreach ($selos as $selo): ?>
        <div class="item item-bloco" data-item>
          <span class="item-num"><span data-numero>1</span></span>
          <div class="item-campos">
            <select name="selo_icone[]" aria-label="Ícone">
              <?php foreach (array_keys(ICONES) as $nome): ?>
                <option value="<?= e($nome) ?>" <?= ($selo['icone'] ?? '') === $nome ? 'selected' : '' ?>><?= e($nome) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="selo_titulo[]" value="<?= e((string) ($selo['titulo'] ?? '')) ?>"
                   maxlength="60" placeholder="Ships from the USA" aria-label="Título do selo">
            <input type="text" name="selo_texto[]" value="<?= e((string) ($selo['texto'] ?? '')) ?>"
                   maxlength="100" placeholder="No overseas wait times" aria-label="Linha de apoio">
          </div>
          <button type="button" class="remover" data-remover title="Remover este selo">×</button>
        </div>
      <?php endforeach; ?>
    </div>

    <template id="molde-selos">
      <div class="item item-bloco" data-item>
        <span class="item-num"><span data-numero>1</span></span>
        <div class="item-campos">
          <select name="selo_icone[]" aria-label="Ícone">
            <?php foreach (array_keys(ICONES) as $nome): ?>
              <option value="<?= e($nome) ?>"><?= e($nome) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="selo_titulo[]" maxlength="60" aria-label="Título do selo">
          <input type="text" name="selo_texto[]" maxlength="100" aria-label="Linha de apoio">
        </div>
        <button type="button" class="remover" data-remover title="Remover este selo">×</button>
      </div>
    </template>

    <button type="button" class="adicionar" data-adicionar="lista-selos" data-molde="molde-selos">
      + Adicionar selo
    </button>
    </div>
  </section>

  <!-- ==================== 9. FAQ ==================== -->
  <section id="sec-faq" data-secao="FAQ" hidden>
    <?php interruptor($o, 'faq', 'grupo-faq'); ?>
    <div id="grupo-faq" class="grupo-alternavel<?= ligada($o, 'faq') ? '' : ' desligado' ?>">
    <p class="ajuda ajuda-topo">
      Pergunta e resposta precisam estar preenchidas para o item aparecer.
      Sobre devolução, remeta aos termos do fornecedor sem citar prazo.
    </p>

    <div class="campo">
      <label for="faq_titulo">Título da seção</label>
      <input type="text" id="faq_titulo" name="faq_titulo" value="<?= v($o, 'faq_titulo') ?>"
             maxlength="120" placeholder="Common questions">
    </div>

    <div id="lista-faq" class="repetivel">
      <?php foreach ($faq as $item): ?>
        <div class="item item-bloco" data-item>
          <span class="item-num"><span data-numero>1</span></span>
          <div class="item-campos empilhado">
            <input type="text" name="faq_pergunta[]" value="<?= e((string) ($item['pergunta'] ?? '')) ?>"
                   maxlength="200" placeholder="What happens when I click?" aria-label="Pergunta">
            <textarea name="faq_resposta[]" rows="3" placeholder="Resposta" aria-label="Resposta"><?= e((string) ($item['resposta'] ?? '')) ?></textarea>
          </div>
          <button type="button" class="remover" data-remover title="Remover esta pergunta">×</button>
        </div>
      <?php endforeach; ?>
    </div>

    <template id="molde-faq">
      <div class="item item-bloco" data-item>
        <span class="item-num"><span data-numero>1</span></span>
        <div class="item-campos empilhado">
          <input type="text" name="faq_pergunta[]" maxlength="200" aria-label="Pergunta">
          <textarea name="faq_resposta[]" rows="3" aria-label="Resposta"></textarea>
        </div>
        <button type="button" class="remover" data-remover title="Remover esta pergunta">×</button>
      </div>
    </template>

    <button type="button" class="adicionar" data-adicionar="lista-faq" data-molde="molde-faq">
      + Adicionar pergunta
    </button>
    </div>
  </section>

  <!-- ==================== 10. Busca ==================== -->
  <section id="sec-busca" data-secao="Busca" hidden>
    <div class="campo">
      <label for="titulo_aba">Título na aba do navegador</label>
      <input type="text" id="titulo_aba" name="titulo_aba" value="<?= v($o, 'titulo_aba') ?>" maxlength="120">
      <p class="ajuda">Vazio: usa o título da página.</p>
    </div>

    <div class="campo">
      <label for="meta_descricao">Descrição para o Google</label>
      <textarea id="meta_descricao" name="meta_descricao" rows="2" maxlength="200"><?= v($o, 'meta_descricao') ?></textarea>
      <p class="ajuda">O trecho sob o título no resultado da busca. Até 160 caracteres.</p>
    </div>

    <div class="campo">
      <label for="avisos_legais">Avisos legais desta oferta</label>
      <textarea id="avisos_legais" name="avisos_legais" rows="4"><?= e((string) ($o['avisos_legais'] ?? '')) ?></textarea>
      <p class="ajuda">Vai no rodapé, depois do aviso padrão. Use para exigência específica do fornecedor.</p>
    </div>
  </section>

  <?php /* Um botão principal só. Antes eram "Salvar" e "Salvar e continuar",
           que faziam quase a mesma coisa com nomes parecidos — e o primeiro
           jogava de volta para a lista no meio da escrita. Agora salvar
           mantém a cliente onde ela está, que é o que um editor deve fazer. */ ?>
  <div class="barra-salvar">
    <button type="submit" name="acao" value="salvar">Salvar</button>
    <?php if (!$novo): ?>
      <button type="submit" name="acao" value="salvar_ver" class="botao-fraco">Salvar e abrir a página ↗</button>
    <?php endif; ?>
    <a class="cancelar" href="/admin/">← Voltar para as ofertas</a>
  </div>
</form>

<?php painel_rodape(); ?>
