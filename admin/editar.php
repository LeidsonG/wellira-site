<?php
/**
 * Formulário de criação e edição de oferta.
 *
 * Dividido em abas, na mesma ordem em que os blocos aparecem na página final.
 * Cada seção opcional segue a regra "campo vazio = bloco some".
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
    // Assinatura já vem preenchida (sempre a mesma pessoa). As demais seções
    // opcionais nascem desligadas, para a oferta mínima ser só topo + texto.
    $o = [
        'status'                   => 'rascunho',
        'indexar'                  => true,
        'autor'                    => AUTOR_PADRAO,
        'autor_titulo'             => AUTOR_TITULO_PADRAO,
        'mostrar_imagens'          => false,
        'mostrar_nao_e_para_voce'  => false,
        'mostrar_selos'            => false,
        'mostrar_faq'              => false,
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

// Valor de um campo simples, pronto para o atributo value.
function v(array $o, string $campo, string $padrao = ''): string
{
    return e((string) ($o[$campo] ?? $padrao));
}

$autor = (array) ($o['autor'] ?? []);
if ($autor === []) {
    $autor = AUTOR_PADRAO;
}

// A seção está ligada? Ausente vale ligada, para não sumir seção de oferta antiga.
function ligada(array $o, string $secao): bool
{
    return ($o['mostrar_' . $secao] ?? true) !== false;
}

// Interruptor de seção: o texto continua gravado, só não é impresso.
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
// Campo de uma linha com chave de liga/desliga ao lado. Não existe campo novo
// no JSON: desligada, o input fica disabled e o navegador não o envia, então
// o salvar grava vazio e o bloco some, igual à regra "campo vazio = some".
function campo_chaveavel(string $id, string $rotulo, string $valor,
                         int $max, string $placeholder): void
{
    $ligado = trim($valor) !== '';
    ?>
    <div class="campo">
      <label for="<?= e($id) ?>"><?= e($rotulo) ?></label>
      <div class="campo-chave<?= $ligado ? '' : ' desligado' ?>">
        <input type="text" id="<?= e($id) ?>" name="<?= e($id) ?>"
               value="<?= e($valor) ?>" maxlength="<?= $max ?>"
               <?= $placeholder !== '' ? 'placeholder="' . e($placeholder) . '"' : '' ?>
               <?= $ligado ? '' : 'disabled' ?>>
        <?php // role="switch", não checkbox: controle da tela, não dado da oferta. ?>
        <button type="button" class="chave" role="switch"
                aria-checked="<?= $ligado ? 'true' : 'false' ?>"
                aria-controls="<?= e($id) ?>"
                data-chave
                title="Usar esta linha na página">
          <span class="chave-trilho" aria-hidden="true"></span>
          <span class="visualmente-oculto">Usar "<?= e($rotulo) ?>" na página</span>
        </button>
      </div>
    </div>
    <?php
}

// Uma linha da lista de imagens. A mesma função desenha as linhas gravadas e
// o <template> que o JavaScript clona, para não duplicar o HTML dos campos.
function linha_imagem(array $imagem = []): void
{
    $arquivo = (string) ($imagem['arquivo'] ?? '');
    $legenda = (string) ($imagem['legenda'] ?? '');
    ?>
    <div class="item item-bloco item-imagem" data-item>
      <span class="item-num"><span data-numero>1</span></span>

      <div class="foto-lugar" data-foto-lugar>
        <img class="item-miniatura" data-miniatura alt="" hidden>
        <span class="foto-vazia" data-foto-vazia aria-hidden="true">+</span>
      </div>

      <div class="item-campos empilhado">
        <?php // Sem JS é a única forma de apontar um arquivo já enviado; com JS o CSS esconde. ?>
        <input type="text" name="imagem_arquivo[]" class="campo-arquivo"
               value="<?= e($arquivo) ?>" maxlength="200"
               placeholder="20260824-a1b2c3.jpg" aria-label="Nome do arquivo">

        <input type="text" name="imagem_legenda[]" value="<?= e($legenda) ?>"
               maxlength="150" placeholder="Descrição em inglês (aparece sob a imagem)"
               aria-label="Descrição da imagem">
      </div>

      <?php // Só aparece com JavaScript: o envio é por fetch. ?>
      <div class="foto-envio">
        <input type="file" accept="image/jpeg,image/png,image/webp" multiple
               data-enviar-campo hidden tabindex="-1" aria-hidden="true">
        <button type="button" class="botao botao-fraco foto-botao" data-enviar-botao>
          <?= $arquivo === '' ? 'Enviar foto' : 'Trocar foto' ?>
        </button>
      </div>

      <button type="button" class="remover perigo" data-remover title="Remover esta imagem">×</button>

      <p class="foto-estado" data-enviar-estado role="status"></p>
    </div>
    <?php
}

$linhas_nao = array_values((array) ($o['nao_e_para_voce'] ?? []));
$selos      = array_values((array) ($o['selos'] ?? []));
$faq        = array_values((array) ($o['faq'] ?? []));
$imagens    = array_values((array) ($o['imagens'] ?? []));

// Oferta nova começa com uma linha de cada lista, só para não abrir vazia.
if (!$linhas_nao) { $linhas_nao = ['']; }
if (!$selos)      { $selos      = [['icone' => 'escudo', 'titulo' => '', 'texto' => '']]; }
if (!$faq)        { $faq        = [['pergunta' => '', 'resposta' => '']]; }
if (!$imagens)    { $imagens    = [['arquivo' => '', 'legenda' => '']]; }

// O link é gravado inteiro, mas o campo separa "resto do endereço" de uma
// casinha para o caso raro de não ser https.
$link_atual     = (string) ($o['link'] ?? '');
$link_http_only = strtolower((string) parse_url($link_atual, PHP_URL_SCHEME)) === 'http';
$link_resto     = preg_replace('#^https?://#i', '', $link_atual);

// Endereço para ver a página: rascunho vai para a prévia do painel,
// publicada vai direto à página real.
function endereco_para_ver(array $o, string $slug): string
{
    return ($o['status'] ?? 'rascunho') === 'publicado'
        ? '/' . rawurlencode($slug)
        : '/admin/previa.php?slug=' . rawurlencode($slug);
}

// Previsão de como a seção fica na página, montada pelo JS a partir dos
// campos (sempre por textContent). Só o invólucro sai do PHP.
function exemplo(string $qual): void
{
    ?>
    <div class="exemplo">
      <span class="exemplo-rotulo">Como fica na página</span>
      <div class="previa" data-previa="<?= e($qual) ?>"></div>
    </div>
    <?php
}

// Prompt que a cliente cola no ChatGPT para escrever o texto de venda no
// formato que o template aceita.
$prompt_chatgpt = <<<'TXT'
Write the sales copy for a product landing page. Follow these rules exactly.

FORMAT (very important):
- Plain text only. No markdown, no bold, no italics, no bullet lists.
- Separate paragraphs with one blank line.
- To create a subheading, start the line with "## " followed by the text.

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

THE PRODUCT:
[DEIXE A DESCRIÇÃO OU TEXTO DO PRODUTO AQUI]

Length: 300 to 900 words.
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
  <div class="cabeca-lado">
    <?php $publicada = ($o['status'] ?? 'rascunho') === 'publicado'; ?>
    <span class="etiqueta <?= $publicada ? 'etiqueta-ok' : 'etiqueta-fraca' ?>">
      <?= $publicada ? 'No ar' : 'Rascunho' ?>
    </span>
    <?php if (!$novo): ?>
      <a class="botao botao-fraco" href="<?= e(endereco_para_ver($o, $slug)) ?>"
         target="_blank" rel="noopener">
        <?= $publicada ? 'Ver página ↗' : 'Ver prévia ↗' ?>
      </a>
    <?php endif; ?>
  </div>
</div>

<script type="application/json" id="icones-svg"><?= json_encode(ICONES, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>

<nav class="abas" role="tablist" aria-label="Seções da oferta"></nav>

<form method="post" action="/admin/salvar.php" class="formulario" data-abas>
  <?= csrf_campo() ?>
  <input type="hidden" name="slug_original" value="<?= e($novo ? '' : $slug) ?>">

  <input type="hidden" name="aba" id="aba-atual" value="<?= (int) ($_GET['aba'] ?? 0) ?>">

  <!-- ==================== 2. Topo ==================== -->
  <section id="sec-topo" data-secao="Topo">
    <div class="campo">
      <label for="titulo">Título <span class="obrig">obrigatório</span></label>
      <input type="text" id="titulo" name="titulo" value="<?= v($o, 'titulo') ?>"
             maxlength="200" required>
      <p class="ajuda">Frase grande no alto.</p>
    </div>

    <div class="campo">
      <label for="slug">Endereço da página</label>
      <div class="prefixo">
        <span>wellira.example/</span>
        <input type="text" id="slug" name="slug" value="<?= e($slug) ?>"
               maxlength="64" pattern="[a-z0-9-]+"
               placeholder="se vazio, gera igual ao título">
      </div>
      <p class="ajuda">
        Minúsculas, números e hífen.
        <?php if (!$novo): ?>
          <strong>Cuidado:</strong> mudar quebra os links já divulgados.
        <?php endif; ?>
      </p>
    </div>

    <div class="dupla">
      <?php
      campo_chaveavel('eyebrow', 'Etiqueta acima do título',
                      (string) ($o['eyebrow'] ?? ''), 80, 'Wellness · Reviewed');
      campo_chaveavel('subtitulo', 'Linha de apoio',
                      (string) ($o['subtitulo'] ?? ''), 300, '');
      ?>
    </div>
    <?php exemplo('topo'); ?>
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
        e cole aqui o nome do arquivo (Ex: "video.mp4").
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
        Vídeos do <b>YouTube</b>: Deixe-o vazio. Ou
        <a href="/admin/upload.php?destino=imagem" target="_blank" rel="noopener">Envie uma imagem</a>
        e coloque o nome do arquivo enviado (Ex: "imagem.jpg"). 
      </p>
    </div>
    <?php exemplo('video'); ?>
  </section>

  <!-- ==================== 4. Imagens ==================== -->
  <section id="sec-imagens" data-secao="Imagens" hidden>
    <?php interruptor($o, 'imagens', 'grupo-imagens'); ?>
    <div id="grupo-imagens" class="grupo-alternavel<?= ligada($o, 'imagens') ? '' : ' desligado' ?>">
    <p class="ajuda ajuda-topo">
      Se a oferta tiver vídeo e fotos, o vídeo vem primeiro.
      <strong>Uma foto aparece sozinha; duas ou mais viram um carrossel</strong>. 
      No máximo <?= MAX_IMAGENS ?>.
    </p>

    <div id="lista-imagens" class="repetivel" data-max="<?= MAX_IMAGENS ?>">
      <?php foreach ($imagens as $imagem):
        // JSON antigo ou editado à mão pode trazer só o nome do arquivo.
        if (is_string($imagem)) { $imagem = ['arquivo' => $imagem]; }
        linha_imagem((array) $imagem);
      endforeach; ?>
    </div>

    <template id="molde-imagens"><?php linha_imagem(); ?></template>

    <button type="button" class="adicionar" data-adicionar="lista-imagens" data-molde="molde-imagens">
      + Adicionar imagem
    </button>

    <p class="ajuda">
      Envie ou arraste o arquivo em cima dela. JPG, PNG ou WebP, até 
      <?= round(limite_upload('imagem') / 1048576, 1) ?> MB cada.
    </p>

    <p class="ajuda">
      A descrição é o que o Google e o leitor de tela enxergam da foto, e é a
      linha impressa embaixo dela. Escreva em inglês, como o resto da página.
    </p>

    <?php exemplo('imagens'); ?>
    </div>
  </section>

  <!-- ==================== 5. Botão ==================== -->
  <section id="sec-botao" data-secao="Botão" hidden>
    <div class="campo">
      <label for="link_resto">Link do fornecedor <span class="obrig">obrigatório</span></label>
      <div class="prefixo">
        <span data-link-prefixo><?= $link_http_only ? 'http://' : 'https://' ?></span>
        <input type="text" id="link_resto" name="link_resto" value="<?= e($link_resto) ?>"
               maxlength="490" required placeholder="affiliate.exemplo.com/produto">
      </div>
      <label class="check">
        <input type="checkbox" name="link_http" value="1" data-link-http
               <?= $link_http_only ? 'checked' : '' ?>>
        <span>Ative se o link for http.</span>
      </label>
      <p class="ajuda">
        Coloque o link do fornecedor e se atente se começa com http ou https.
      </p>
    </div>

    <div class="campo">
      <label for="botao_texto">Texto do botão</label>
      <input type="text" id="botao_texto" name="botao_texto"
             value="<?= v($o, 'botao_texto', 'See the Official Site') ?>" maxlength="80">
    </div>

    <?php
    campo_chaveavel('botao_sub', 'Linha de apoio sob o botão',
                    (string) ($o['botao_sub'] ?? ''), 200, '');
    ?>
    <p class="ajuda">
      Botão que o cliente clica para ir ao site do fornecedor. A linha de apoio é opcional, e aparece embaixo do botão.
    </p>
    <?php exemplo('botao'); ?>
  </section>

  <!-- ==================== 6. Texto ==================== -->
  <section id="sec-texto" data-secao="Texto" hidden>
    <div class="campo">
      <label for="texto_titulo">Título da seção <span class="obrig">obrigatório para publicar</span></label>
      <input type="text" id="texto_titulo" name="texto_titulo"
             value="<?= v($o, 'texto_titulo') ?>" maxlength="120" placeholder="The simple method that's saving the day for a lot of people.">
    </div>

    <div class="campo">
      <label for="texto">Texto de venda <span class="obrig">obrigatório para publicar</span></label>
      <textarea id="texto" name="texto" rows="22"
                placeholder="Exemplo:&#10;&#10;Most people try three or four things before finding this.&#10;&#10;It's a simple routine, and it doesn't need any special equipment.&#10;&#10;## Why it works&#10;&#10;..."><?= e((string) ($o['texto'] ?? '')) ?></textarea>
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
    <?php exemplo('texto'); ?>
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
             value="<?= v($o, 'nao_e_para_voce_titulo', "This isn't for you if") ?>"
             maxlength="120">
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
    <?php exemplo('nao'); ?>
    </div>
  </section>

  <!-- ==================== 8. Selos ==================== -->
  <section id="sec-selos" data-secao="Selos" hidden>
    <?php interruptor($o, 'selos', 'grupo-selos'); ?>
    <div id="grupo-selos" class="grupo-alternavel<?= ligada($o, 'selos') ? '' : ' desligado' ?>">
    <p class="ajuda ajuda-topo">Selos são usados para destacar algumas coisas importante sobre o produto.
    </p>

    <div id="lista-selos" class="repetivel">
      <?php foreach ($selos as $selo): ?>
        <div class="item item-bloco" data-item>
          <span class="item-num"><span data-numero>1</span></span>
          <div class="item-campos">
            <div class="campo-icone">
              <select name="selo_icone[]" aria-label="Ícone" data-icone-select>
                <?php foreach (array_keys(ICONES) as $nome): ?>
                  <option value="<?= e($nome) ?>" <?= (($selo['icone'] ?? '') ?: 'escudo') === $nome ? 'selected' : '' ?>><?= e($nome) ?></option>
                <?php endforeach; ?>
              </select>
              <span class="icone-previa" data-icone-previa aria-hidden="true">
                <?= icone(((string) ($selo['icone'] ?? '')) ?: 'escudo') ?>
              </span>
            </div>
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
          <div class="campo-icone">
            <select name="selo_icone[]" aria-label="Ícone" data-icone-select>
              <?php foreach (array_keys(ICONES) as $nome): ?>
                <option value="<?= e($nome) ?>" <?= $nome === 'escudo' ? 'selected' : '' ?>><?= e($nome) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="icone-previa" data-icone-previa aria-hidden="true"><?= icone('escudo') ?></span>
          </div>
          <input type="text" name="selo_titulo[]" maxlength="60" aria-label="Título do selo">
          <input type="text" name="selo_texto[]" maxlength="100" aria-label="Linha de apoio">
        </div>
        <button type="button" class="remover" data-remover title="Remover este selo">×</button>
      </div>
    </template>

    <button type="button" class="adicionar" data-adicionar="lista-selos" data-molde="molde-selos">
      + Adicionar selo
    </button>
    <?php exemplo('selos'); ?>
    </div>
  </section>

  <!-- ==================== 9. FAQ ==================== -->
  <section id="sec-faq" data-secao="FAQ" hidden>
    <?php interruptor($o, 'faq', 'grupo-faq'); ?>
    <div id="grupo-faq" class="grupo-alternavel<?= ligada($o, 'faq') ? '' : ' desligado' ?>">
    <p class="ajuda ajuda-topo">
      Pergunta e resposta precisam estar preenchidas para o item aparecer.
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
    <?php exemplo('faq'); ?>
    </div>
  </section>

  <!-- ==================== 10. Autor ==================== -->
  <section id="sec-autor" data-secao="Autor" hidden>
    <?php interruptor($o, 'autor', 'grupo-autor'); ?>
    <div id="grupo-autor" class="grupo-alternavel<?= ligada($o, 'autor') ? '' : ' desligado' ?>">
    <p class="ajuda ajuda-topo">
      Preenchido com a assinatura padrão da Wellira. Edite se precisar, ou
      desligue no interruptor acima para esta oferta específica.
      Aparece no fim da página, depois das perguntas frequentes.
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
    <?php exemplo('autor'); ?>
    </div>
  </section>

  <!-- ==================== 11. Busca ==================== -->
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

  <!-- ==================== 12. Publicação ==================== -->
  <section id="sec-publicacao" data-secao="Publicação" hidden>
    <div class="campo">
      <label>Situação</label>
      <div class="radios">
        <label class="radio">
          <input type="radio" name="status" value="rascunho"
                 <?= ($o['status'] ?? 'rascunho') !== 'publicado' ? 'checked' : '' ?>>
          <span><strong>Rascunho</strong><br><small>Fora do ar: quem abrir o endereço recebe "página não encontrada". Você confere pelo botão <em>Ver prévia</em>, aqui em cima.</small></span>
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

  <div class="barra-salvar">
    <button type="submit" name="acao" value="salvar">Salvar</button>
    <?php if (!$novo): ?>
      <button type="submit" name="acao" value="salvar_ver" class="botao-fraco">
        <?= $publicada ? 'Salvar e abrir a página ↗' : 'Salvar e ver a prévia ↗' ?>
      </button>
    <?php endif; ?>
    <a class="cancelar" href="/admin/">← Voltar para as ofertas</a>
  </div>
</form>

<?php painel_rodape(); ?>
