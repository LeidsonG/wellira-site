<?php
/**
 * Funções de apoio ao roteamento e à renderização das ofertas.
 *
 * Compatível com PHP 8.0+, sem depender de mbstring.
 */

require_once __DIR__ . '/config.php';

// Escapa texto para saída em HTML. Toda saída derivada de conteúdo da
// cliente passa por aqui, contra XSS.
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Só minúsculas, números e hífen, começando e terminando por alfanumérico.
// Primeira barreira contra path traversal.
function slug_valido(string $slug): bool
{
    return (bool) preg_match('/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/', $slug);
}

// Converte um título livre em slug utilizável na URL.
function gerar_slug(string $titulo): string
{
    $mapa = [
        'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n',
    ];
    $s = strtr(strtolower($titulo), $mapa);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}

// Lê uma oferta do disco. Null quando o slug é inválido, o arquivo não
// existe ou o JSON está corrompido, sempre resultando num 404 simples.
function carregar_oferta(string $slug): ?array
{
    if (!slug_valido($slug)) {
        return null;
    }

    // basename() é redundante depois de slug_valido(), mantido como segunda barreira.
    $arquivo = DIR_OFERTAS . '/' . basename($slug) . '.json';

    if (!is_file($arquivo)) {
        return null;
    }

    $conteudo = file_get_contents($arquivo);
    if ($conteudo === false) {
        return null;
    }

    $dados = json_decode($conteudo, true);
    return is_array($dados) ? $dados : null;
}

/** Lista os slugs de todas as ofertas existentes, publicadas ou não. */
function listar_ofertas(): array
{
    $lista = [];
    foreach (glob(DIR_OFERTAS . '/*.json') ?: [] as $arquivo) {
        $slug = basename($arquivo, '.json');
        if (slug_valido($slug)) {
            $lista[] = $slug;
        }
    }
    sort($lista);
    return $lista;
}

// Extrai o ID de um vídeo do YouTube, aceitando tanto o ID puro quanto a URL completa.
function youtube_id(string $valor): ?string
{
    $valor = trim($valor);

    if (preg_match('~(?:youtu\.be/|v=|embed/|shorts/|/v/)([A-Za-z0-9_-]{11})~', $valor, $m)) {
        return $m[1];
    }
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $valor)) {
        return $valor;
    }
    return null;
}

// Monta o bloco do vídeo como fachada (miniatura + botão): o player real só
// carrega quando o visitante clica, mantendo a página leve.
function render_video(array $oferta): string
{
    $valor = trim((string) ($oferta['video'] ?? ''));
    if ($valor === '') {
        return '';
    }

    $legenda = (string) ($oferta['video_legenda'] ?? '');
    $poster  = trim((string) ($oferta['video_poster'] ?? ''));

    if (substr(strtolower($valor), -4) === '.mp4') {
        $tipo = 'mp4';
        $src  = URL_VIDEOS . '/' . rawurlencode(basename($valor));
    } else {
        $id = youtube_id($valor);
        if ($id === null) {
            return '';
        }
        $tipo = 'youtube';
        $src  = $id;
    }

    $estilo = '';
    if ($poster !== '') {
        // A capa mora em assets/img/uploads; o fallback cobre pôster antigo
        // enviado para a pasta de vídeos.
        $nome = basename($poster);
        $url  = is_file(DIR_UPLOADS . '/' . $nome)
              ? URL_UPLOADS . '/' . rawurlencode($nome)
              : URL_VIDEOS . '/' . rawurlencode($nome);
        $estilo = ' style="background-image:url(\'' . e($url) . '\')"';
    }

    $html  = '<button class="video" type="button" aria-label="Play video"'
           . ' data-tipo="' . e($tipo) . '" data-src="' . e($src) . '"' . $estilo . '>';
    $html .= '<span class="video-play" aria-hidden="true"></span>';
    if ($legenda !== '') {
        $html .= '<span class="video-label">' . e($legenda) . '</span>';
    }
    $html .= '</button>';

    return $html;
}

// O nome é de um arquivo de imagem aceitável? Sem barra, sem ponto duplo e
// com extensão conhecida, o nome vira caminho de arquivo e URL.
function nome_imagem_valido(string $nome): bool
{
    return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,120}\.(jpe?g|png|webp)$/i', $nome)
        && strpos($nome, '..') === false;
}

// Lê a lista de imagens da oferta, já limpa. Aceita tanto o formato do
// painel quanto uma string solta com o nome do arquivo.
function imagens_da_oferta(array $oferta): array
{
    if (($oferta['mostrar_imagens'] ?? true) === false) {
        return [];
    }

    $itens = [];
    foreach ((array) ($oferta['imagens'] ?? []) as $imagem) {
        if (is_string($imagem)) {
            $imagem = ['arquivo' => $imagem];
        }
        if (!is_array($imagem)) {
            continue;
        }

        $arquivo = basename(trim((string) ($imagem['arquivo'] ?? '')));
        if ($arquivo === '' || !nome_imagem_valido($arquivo)) {
            continue;
        }

        $itens[] = [
            'arquivo' => $arquivo,
            'legenda' => trim((string) ($imagem['legenda'] ?? '')),
        ];
        if (count($itens) >= MAX_IMAGENS) {
            break;
        }
    }
    return $itens;
}

// Monta a etiqueta <img> de um item da galeria. A primeira imagem não é
// adiada, ela costuma ser o LCP da página; da segunda em diante, lazy.
function galeria_img(array $item, bool $primeira): string
{
    return '<img src="' . e(URL_UPLOADS . '/' . rawurlencode($item['arquivo'])) . '"'
         . ' alt="' . e($item['legenda']) . '"'
         . ($primeira ? ' loading="eager" fetchpriority="high"' : ' loading="lazy"')
         . ' decoding="async">';
}

// Monta o bloco de imagens do produto, no mesmo lugar do vídeo. Uma imagem
// sai como figura simples; de duas em diante vira carrossel (rola por
// arrasto mesmo sem JS; setas e pontinhos vêm de assets/js/galeria.js).
function render_galeria(array $oferta): string
{
    $itens = imagens_da_oferta($oferta);
    if (!$itens) {
        return '';
    }

    $figura = function (array $item, bool $primeira, string $classe): string {
        $html = '<figure class="' . e($classe) . '">' . galeria_img($item, $primeira);
        if ($item['legenda'] !== '') {
            $html .= '<figcaption>' . e($item['legenda']) . '</figcaption>';
        }
        return $html . '</figure>';
    };

    if (count($itens) === 1) {
        return $figura($itens[0], true, 'galeria galeria-unica');
    }

    $html = '<div class="galeria galeria-carrossel" data-galeria>'
          . '<div class="galeria-trilho" data-trilho tabindex="0" role="group" aria-label="Product images">';
    foreach ($itens as $i => $item) {
        $html .= $figura($item, $i === 0, 'galeria-item');
    }
    $html .= '</div>'
           . '<button class="galeria-seta galeria-anterior" type="button" data-ir="-1" aria-label="Previous image"></button>'
           . '<button class="galeria-seta galeria-proxima" type="button" data-ir="1" aria-label="Next image"></button>'
           . '<div class="galeria-pontos" data-pontos></div>'
           . '</div>';

    return $html;
}

// Transforma o texto livre digitado no painel em HTML: linha em branco separa
// parágrafo, linha iniciada por "## " vira subtítulo. Tudo escapado antes.
function paragrafos(string $texto): string
{
    $blocos = preg_split('/\R\s*\R/', trim($texto)) ?: [];
    $html   = '';

    foreach ($blocos as $bloco) {
        $bloco = trim($bloco);
        if ($bloco === '') {
            continue;
        }
        if (strpos($bloco, '## ') === 0) {
            $html .= '<h3>' . e(substr($bloco, 3)) . "</h3>\n";
        } else {
            $html .= '<p>' . nl2br(e($bloco)) . "</p>\n";
        }
    }
    return $html;
}

// Devolve os avisos legais que vão ao rodapé da oferta. O primeiro é
// constante de config.php; os demais vêm do campo livre da cliente.
function avisos(array $oferta): array
{
    $saida = ['<p>' . AVISO_BASE . '</p>'];

    $extra = trim((string) ($oferta['avisos_legais'] ?? ''));
    if ($extra !== '') {
        $saida[] = paragrafos($extra);
    }
    return $saida;
}

/** Ícone de selo, escolhido por nome no painel para não expor SVG à cliente. */
function icone(string $nome): string
{
    $caminho = ICONES[$nome] ?? ICONES['escudo'];
    return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
         . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
         . $caminho . '</svg>';
}

// Valida o destino do botão. Só http e https passam, contra um
// "javascript:" gravado no painel virar execução de script.
function link_seguro(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }
    $esquema = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($esquema, ['http', 'https'], true) ? $url : null;
}

/** Envia o 404 e encerra, reaproveitando a página de erro estática. */
function nao_encontrado(): void
{
    http_response_code(404);
    $pagina = dirname(__DIR__) . '/404.html';
    if (is_file($pagina)) {
        readfile($pagina);
    } else {
        echo 'Not found';
    }
    exit;
}


/** Cria a pasta se faltar. */
function garantir_pasta(string $caminho): bool
{
    if (is_dir($caminho)) {
        return true;
    }
    return @mkdir($caminho, 0755, true);
}

// ---------------------------------------------------------------------------
// Cliques (S3)
// ---------------------------------------------------------------------------

/** Quantos cliques a oferta acumulou. */
function ler_cliques(string $slug): int
{
    if (!slug_valido($slug)) {
        return 0;
    }
    $arquivo = DIR_CLIQUES . '/' . $slug . '.txt';
    return is_file($arquivo) ? (int) file_get_contents($arquivo) : 0;
}

// Soma um clique. Trava o arquivo para dois cliques simultâneos não se perderem.
function somar_clique(string $slug): void
{
    if (!slug_valido($slug) || !garantir_pasta(DIR_CLIQUES)) {
        return;
    }
    $f = @fopen(DIR_CLIQUES . '/' . $slug . '.txt', 'c+');
    if ($f === false) {
        return;
    }
    if (flock($f, LOCK_EX)) {
        $atual = (int) stream_get_contents($f);
        ftruncate($f, 0);
        rewind($f);
        fwrite($f, (string) ($atual + 1));
        fflush($f);
        flock($f, LOCK_UN);
    }
    fclose($f);
}
