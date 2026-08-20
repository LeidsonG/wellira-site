<?php
/**
 * Validação, gravação e upload do painel.
 *
 * Regra que organiza este arquivo: nada que venha do formulário é gravado como
 * chegou. Todo campo passa por normalizar_oferta(), que decide o tipo, corta o
 * tamanho e descarta o que não reconhece. O JSON no disco é sempre resultado
 * desta função, nunca do $_POST.
 */

require_once __DIR__ . '/funcoes.php';

/**
 * Grava um arquivo sem risco de deixá-lo pela metade.
 *
 * Escreve num temporário e só então renomeia. rename() é atômico no mesmo
 * sistema de arquivos: ou o arquivo antigo continua inteiro, ou o novo aparece
 * inteiro. Um file_put_contents interrompido no meio (timeout, disco cheio,
 * queda) deixaria a oferta corrompida e a página no ar quebrada.
 */
function escrever_atomico(string $destino, string $conteudo): bool
{
    $temporario = $destino . '.tmp' . bin2hex(random_bytes(4));

    if (@file_put_contents($temporario, $conteudo, LOCK_EX) === false) {
        return false;
    }
    if (!@rename($temporario, $destino)) {
        @unlink($temporario);
        return false;
    }
    @chmod($destino, 0644);
    return true;
}

// ---------------------------------------------------------------------------
// Backup
// ---------------------------------------------------------------------------

/**
 * Copia a versão atual da oferta antes de sobrescrevê-la.
 *
 * O conteúdo da cliente existe só no servidor e não passa por git. Salvar por
 * cima de um texto que levou uma hora para escrever é o erro mais provável do
 * painel, e o único sem volta.
 */
function fazer_backup(string $slug): void
{
    $origem = DIR_OFERTAS . '/' . $slug . '.json';
    if (!is_file($origem) || !garantir_pasta(DIR_BACKUPS)) {
        return;
    }

    @copy($origem, DIR_BACKUPS . '/' . $slug . '.' . date('Ymd-His') . '.json');

    // Poda: mantém só as N mais recentes. Sem isso a pasta cresce sem fim e
    // consome os inodes do plano compartilhado, que são contados.
    $copias = glob(DIR_BACKUPS . '/' . $slug . '.*.json') ?: [];
    sort($copias);
    foreach (array_slice($copias, 0, max(0, count($copias) - BACKUPS_POR_OFERTA)) as $velho) {
        @unlink($velho);
    }
}

/** Lista as cópias de uma oferta, da mais recente para a mais antiga. */
function listar_backups(string $slug): array
{
    if (!slug_valido($slug)) {
        return [];
    }
    $copias = glob(DIR_BACKUPS . '/' . $slug . '.*.json') ?: [];
    rsort($copias);
    return $copias;
}

// ---------------------------------------------------------------------------
// Normalização
// ---------------------------------------------------------------------------

/** Texto de uma linha: sem quebras, sem espaço nas pontas, com teto de tamanho. */
function limpar_linha($valor, int $max = 300): string
{
    $texto = is_string($valor) ? $valor : '';
    $texto = str_replace(["\r", "\n", "\t"], ' ', $texto);
    $texto = trim(preg_replace('/ {2,}/', ' ', $texto) ?? '');
    return substr($texto, 0, $max);
}

/** Texto de vários parágrafos: mantém quebras, normaliza fim de linha. */
function limpar_bloco($valor, int $max = 20000): string
{
    $texto = is_string($valor) ? $valor : '';
    $texto = str_replace(["\r\n", "\r"], "\n", $texto);
    return substr(trim($texto), 0, $max);
}

/**
 * Monta o array final da oferta a partir do formulário.
 *
 * Campo vazio é removido, não gravado como string vazia: é isso que faz o
 * template esconder o bloco inteiro ("campo vazio = bloco some"). Gravar ""
 * deixaria uma seção com título e nada dentro.
 */
function normalizar_oferta(array $post): array
{
    $o = [];

    $o['status']  = ($post['status'] ?? 'rascunho') === 'publicado' ? 'publicado' : 'rascunho';
    $o['indexar'] = !empty($post['indexar']);

    // --- Topo ---------------------------------------------------------------
    $campos_linha = [
        'eyebrow'        => 80,
        'titulo'         => 200,
        'titulo_aba'     => 120,
        'subtitulo'      => 300,
        'meta_descricao' => 200,
        'video'          => 500,
        'video_legenda'  => 150,
        'video_poster'   => 200,
        'botao_texto'    => 80,
        'botao_sub'      => 200,
        'botao_sub2'     => 200,
        'texto_titulo'   => 120,
        'autor_titulo'   => 120,
        'nao_e_para_voce_titulo' => 120,
        'faq_titulo'     => 120,
    ];
    foreach ($campos_linha as $campo => $max) {
        $valor = limpar_linha($post[$campo] ?? '', $max);
        if ($valor !== '') {
            $o[$campo] = $valor;
        }
    }

    // O link é o único campo sem o qual a oferta não existe, então é validado
    // aqui e não apenas conferido na exibição.
    $link = link_seguro((string) ($post['link'] ?? ''));
    if ($link !== null) {
        $o['link'] = substr($link, 0, 500);
    }

    // --- Blocos de texto ----------------------------------------------------
    foreach (['texto' => 20000, 'nao_e_para_voce_nota' => 600, 'avisos_legais' => 3000] as $campo => $max) {
        $valor = limpar_bloco($post[$campo] ?? '', $max);
        if ($valor !== '') {
            $o[$campo] = $valor;
        }
    }

    // --- Autor --------------------------------------------------------------
    $autor = [
        'nome'  => limpar_linha($post['autor_nome'] ?? '', 80),
        'cargo' => limpar_linha($post['autor_cargo'] ?? '', 80),
        'foto'  => limpar_linha($post['autor_foto'] ?? '', 200),
        'texto' => limpar_bloco($post['autor_texto'] ?? '', 2000),
    ];
    // O template só imprime a seção quando há texto; sem ele o resto é inútil.
    if ($autor['texto'] !== '') {
        $o['autor'] = array_filter($autor, fn($v) => $v !== '');
    }

    // --- "This isn't for you if" -------------------------------------------
    $linhas = [];
    foreach ((array) ($post['nao_e_para_voce'] ?? []) as $linha) {
        $linha = limpar_linha($linha, 200);
        if ($linha !== '') {
            $linhas[] = $linha;
        }
    }
    if ($linhas) {
        $o['nao_e_para_voce'] = array_slice($linhas, 0, 10);
    }

    // --- Selos --------------------------------------------------------------
    $selos = [];
    foreach ((array) ($post['selo_titulo'] ?? []) as $i => $titulo) {
        $titulo = limpar_linha($titulo, 60);
        if ($titulo === '') {
            continue;
        }
        $icone = (string) ($post['selo_icone'][$i] ?? 'escudo');
        $selos[] = [
            // Ícone só pode ser um dos nomes conhecidos: o valor vira SVG na
            // página, e aceitar texto livre aqui seria injeção de marcação.
            'icone'  => isset(ICONES[$icone]) ? $icone : 'escudo',
            'titulo' => $titulo,
            'texto'  => limpar_linha($post['selo_texto'][$i] ?? '', 100),
        ];
    }
    if ($selos) {
        $o['selos'] = array_slice($selos, 0, 6);
    }

    // --- FAQ ----------------------------------------------------------------
    $faq = [];
    foreach ((array) ($post['faq_pergunta'] ?? []) as $i => $pergunta) {
        $pergunta = limpar_linha($pergunta, 200);
        $resposta = limpar_bloco($post['faq_resposta'][$i] ?? '', 2000);
        if ($pergunta !== '' && $resposta !== '') {
            $faq[] = ['pergunta' => $pergunta, 'resposta' => $resposta];
        }
    }
    if ($faq) {
        $o['faq'] = array_slice($faq, 0, 12);
    }

    return $o;
}

/**
 * O que impede a oferta de ser publicada.
 *
 * Devolve lista de mensagens, vazia quando está tudo certo. Rascunho pode ficar
 * incompleto — só a publicação exige o mínimo, porque é ela que expõe a página.
 */
function validar_oferta(array $o, string $slug, bool $novo): array
{
    $erros = [];

    if (!slug_valido($slug)) {
        $erros[] = 'O endereço da página deve ter só letras minúsculas, números e hífen.';
    }
    if ($novo && is_file(DIR_OFERTAS . '/' . $slug . '.json')) {
        $erros[] = 'Já existe uma oferta com esse endereço.';
    }
    if (($o['titulo'] ?? '') === '') {
        $erros[] = 'O título é obrigatório.';
    }
    if (($o['link'] ?? '') === '') {
        $erros[] = 'O link do botão é obrigatório e precisa começar com http:// ou https://';
    }
    if (($o['status'] ?? '') === 'publicado' && ($o['texto'] ?? '') === '') {
        $erros[] = 'Para publicar, escreva o texto de venda.';
    }

    return $erros;
}

/** Grava a oferta no disco, com backup da versão anterior. */
function salvar_oferta(string $slug, array $oferta): bool
{
    if (!slug_valido($slug) || !garantir_pasta(DIR_OFERTAS)) {
        return false;
    }
    fazer_backup($slug);

    $json = json_encode(
        $oferta,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if ($json === false) {
        return false;
    }
    return escrever_atomico(DIR_OFERTAS . '/' . $slug . '.json', $json . "\n");
}

/** Apaga uma oferta, guardando antes uma cópia. */
function excluir_oferta(string $slug): bool
{
    if (!slug_valido($slug)) {
        return false;
    }
    $arquivo = DIR_OFERTAS . '/' . $slug . '.json';
    if (!is_file($arquivo)) {
        return false;
    }
    fazer_backup($slug);
    return @unlink($arquivo);
}

/** Sugere um slug livre a partir de um desejado, somando -2, -3... se ocupado. */
function slug_livre(string $desejado): string
{
    $base = gerar_slug($desejado) ?: 'oferta';
    $base = substr($base, 0, 60);
    $slug = $base;
    $n    = 2;

    while (is_file(DIR_OFERTAS . '/' . $slug . '.json')) {
        $slug = $base . '-' . $n++;
    }
    return $slug;
}

// ---------------------------------------------------------------------------
// Upload
// ---------------------------------------------------------------------------

/** Converte "2M", "8M", "512K" do php.ini em bytes. */
function ini_bytes(string $valor): int
{
    $valor = trim($valor);
    if ($valor === '') {
        return 0;
    }
    $numero = (int) $valor;
    switch (strtolower(substr($valor, -1))) {
        case 'g': return $numero * 1024 * 1024 * 1024;
        case 'm': return $numero * 1024 * 1024;
        case 'k': return $numero * 1024;
        default:  return $numero;
    }
}

/**
 * Limite real de upload, em bytes.
 *
 * O teto que vale é o MENOR entre o nosso e os dois do PHP. A hospedagem
 * compartilhada costuma vir com upload_max_filesize de 2M e post_max_size de
 * 8M, muito abaixo dos 64 MB que queríamos para vídeo — anunciar o nosso número
 * faria a cliente tentar de novo e de novo um envio que nunca ia passar.
 */
function limite_upload(string $genero): int
{
    $nosso = ($genero === 'video') ? MAX_UPLOAD_VIDEO : MAX_UPLOAD_IMAGEM;

    $limites = [$nosso];
    foreach (['upload_max_filesize', 'post_max_size'] as $chave) {
        $bytes = ini_bytes((string) ini_get($chave));
        if ($bytes > 0) {
            $limites[] = $bytes;
        }
    }
    return min($limites);
}

/**
 * O POST chegou vazio por exceder post_max_size?
 *
 * Quando o corpo passa de post_max_size, o PHP descarta tudo: $_POST e $_FILES
 * voltam vazios. Sem detectar isso aqui, a validação de CSRF é a primeira a
 * falhar e a cliente recebe "Sessão expirada" ao enviar um vídeo grande — uma
 * mensagem que não tem nada a ver com o que aconteceu e que ela não tem como
 * decifrar.
 */
function post_estourou(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }
    if (!empty($_POST) || !empty($_FILES)) {
        return false;
    }
    $enviado = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $teto    = ini_bytes((string) ini_get('post_max_size'));

    return $teto > 0 && $enviado > $teto;
}

/**
 * Descobre o tipo real do arquivo lendo os primeiros bytes.
 *
 * A extensão do nome não vale nada: qualquer pessoa renomeia um .php para .mp4
 * antes de enviar. E a pasta de destino é servida publicamente. Isto lê a
 * assinatura binária real e é o que decide a extensão gravada no disco — o nome
 * que veio do navegador é descartado inteiro.
 *
 * Complementa, e não substitui, o bloqueio de execução de PHP no .htaccess das
 * pastas de upload.
 */
function tipo_por_assinatura(string $caminho): ?string
{
    $f = @fopen($caminho, 'rb');
    if ($f === false) {
        return null;
    }
    $cabecalho = (string) fread($f, 16);
    fclose($f);

    if (strlen($cabecalho) < 12) {
        return null;
    }

    if (substr($cabecalho, 0, 3) === "\xFF\xD8\xFF") {
        return 'jpg';
    }
    if (substr($cabecalho, 0, 8) === "\x89PNG\r\n\x1A\n") {
        return 'png';
    }
    if (substr($cabecalho, 0, 4) === 'RIFF' && substr($cabecalho, 8, 4) === 'WEBP') {
        return 'webp';
    }
    // MP4 e parentes: a caixa "ftyp" começa no offset 4, depois do tamanho.
    if (substr($cabecalho, 4, 4) === 'ftyp') {
        return 'mp4';
    }

    return null;
}

/**
 * Recebe um arquivo enviado pelo painel.
 *
 * Devolve ['nome' => ...] em caso de sucesso, ou ['erro' => ...].
 * $genero é 'video' ou 'imagem'.
 */
function receber_upload(array $arquivo, string $genero): array
{
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['erro' => 'Nenhum arquivo enviado.'];
    }
    $erro = $arquivo['error'] ?? 1;
    if ($erro === UPLOAD_ERR_INI_SIZE || $erro === UPLOAD_ERR_FORM_SIZE) {
        return ['erro' => 'Arquivo grande demais. O limite do servidor é '
                        . round(limite_upload($genero) / 1048576, 1) . ' MB.'];
    }
    if ($erro !== UPLOAD_ERR_OK) {
        return ['erro' => 'O envio falhou. Tente de novo; se insistir, o arquivo pode ser grande demais.'];
    }
    // Garante que o caminho veio mesmo de um upload HTTP desta requisição, e
    // não é um caminho do sistema de arquivos forjado no formulário.
    if (!is_uploaded_file($arquivo['tmp_name'] ?? '')) {
        return ['erro' => 'Envio inválido.'];
    }

    $tipo = tipo_por_assinatura($arquivo['tmp_name']);
    if ($tipo === null) {
        return ['erro' => 'Formato não reconhecido. Aceitamos MP4, JPG, PNG e WebP.'];
    }

    if ($genero === 'video') {
        $permitidos = ['mp4'];
        $pasta      = DIR_VIDEOS;
        $rotulo     = 'vídeo MP4';
    } else {
        $permitidos = ['jpg', 'png', 'webp'];
        $pasta      = DIR_UPLOADS;
        $rotulo     = 'imagem JPG, PNG ou WebP';
    }
    $limite = limite_upload($genero);

    if (!in_array($tipo, $permitidos, true)) {
        return ['erro' => 'Aqui só entra ' . $rotulo . '.'];
    }
    if (($arquivo['size'] ?? 0) > $limite) {
        return ['erro' => 'Arquivo grande demais. O limite é ' . round($limite / 1048576) . ' MB.'];
    }
    if (!garantir_pasta($pasta)) {
        return ['erro' => 'Não consegui gravar na pasta de arquivos.'];
    }

    // Nome gerado aqui, nunca aproveitado do envio: elimina de uma vez path
    // traversal, colisão e caractere estranho no nome.
    $nome    = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $tipo;
    $destino = $pasta . '/' . $nome;

    if (!@move_uploaded_file($arquivo['tmp_name'], $destino)) {
        return ['erro' => 'Não consegui salvar o arquivo.'];
    }
    @chmod($destino, 0644);

    return ['nome' => $nome];
}
