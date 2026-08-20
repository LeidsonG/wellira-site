<?php
/**
 * Funções de apoio ao roteamento e à renderização das ofertas.
 *
 * Compatível com PHP 8.0+ e sem depender de mbstring — a versão do PHP na
 * hospedagem compartilhada pode ser trocada pelo cPanel a qualquer momento, e
 * nem todo plano traz a extensão habilitada.
 */

require_once __DIR__ . '/config.php';

/**
 * Escapa texto para saída em HTML.
 *
 * Toda saída derivada de conteúdo da cliente passa por aqui. Como o painel
 * grava o que ela digitar, tratar esse conteúdo como confiável abriria XSS
 * numa página pública.
 */
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Um slug só é aceito se contiver exclusivamente minúsculas, números e hífen,
 * começando e terminando por caractere alfanumérico.
 *
 * Isso é a primeira barreira contra path traversal: sem ponto e sem barra,
 * "../../etc/passwd" nunca chega a virar um caminho de arquivo.
 */
function slug_valido(string $slug): bool
{
    return (bool) preg_match('/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/', $slug);
}

/**
 * Converte um título livre em slug utilizável na URL.
 * Usado pelo painel para preencher o endereço automaticamente.
 */
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

/**
 * Lê uma oferta do disco.
 *
 * Devolve null quando o slug é inválido, o arquivo não existe ou o JSON está
 * corrompido — nunca lança exceção, porque qualquer um desses casos deve
 * resultar simplesmente num 404, não num erro exposto ao visitante.
 */
function carregar_oferta(string $slug): ?array
{
    if (!slug_valido($slug)) {
        return null;
    }

    // basename() é redundante depois de slug_valido(), e é mantido de propósito:
    // se alguém afrouxar a validação acima no futuro, esta linha ainda segura.
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

/**
 * Extrai o ID de um vídeo do YouTube.
 *
 * Aceita tanto o ID puro quanto a URL completa colada da barra de endereços,
 * porque é isso que a cliente naturalmente vai fazer.
 */
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

/**
 * Monta o bloco do vídeo.
 *
 * O player não é carregado junto com a página: o que sai daqui é uma fachada
 * (miniatura + botão), e o vídeo real só é buscado quando o visitante clica.
 * Isso mantém a página leve em 4G e evita que o YouTube deposite cookies em
 * quem nunca deu play.
 */
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
        $url    = URL_VIDEOS . '/' . rawurlencode(basename($poster));
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

/**
 * Transforma o texto livre digitado no painel em HTML.
 *
 * Regras deliberadamente mínimas, para que a cliente não precise aprender
 * marcação: linha em branco separa parágrafo, e uma linha iniciada por "## "
 * vira subtítulo. Tudo é escapado antes de virar HTML.
 */
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

/**
 * Devolve os avisos legais que vão ao rodapé da oferta.
 *
 * O primeiro é constante confiável de config.php, com HTML proposital. Os
 * demais vêm do campo livre preenchido pela cliente, então passam por
 * paragrafos(), que escapa antes de virar HTML.
 */
function avisos(array $oferta): array
{
    // Cada item já sai como bloco HTML pronto. O aviso base é constante e ganha
    // o <p> aqui; o campo livre volta de paragrafos() já com os próprios <p>.
    // Embrulhar os dois no template geraria <p> dentro de <p>, que é inválido.
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

/**
 * Valida o destino do botão.
 *
 * Só http e https passam. Sem isso, um "javascript:" gravado no painel viraria
 * execução de script na página pública.
 */
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

/**
 * Soma um clique.
 *
 * Abre com 'c' e trava o arquivo: dois visitantes clicando no mesmo instante
 * leriam o mesmo número e gravariam o mesmo valor, perdendo um clique.
 */
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
