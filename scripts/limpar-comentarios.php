<?php
/**
 * Remove comentários dos arquivos que o visitante consegue baixar.
 *
 * Roda sobre a CÓPIA temporária montada pelo deploy, nunca sobre o repositório:
 * o código comentado continua sendo a fonte única de verdade.
 *
 * O que é limpo, e por quê só isso:
 *
 *   CSS e JS  → o navegador baixa o arquivo inteiro. Todo comentário ali é
 *               público.
 *   HTML      → idem, vai no corpo da resposta.
 *   PHP       → NÃO é limpo. O servidor executa e envia só o resultado; o
 *               comentário nunca chega ao visitante. Removê-lo não esconderia
 *               nada de ninguém e tiraria a documentação justamente de onde ela
 *               é mais necessária: no dia em que algo quebrar em produção, é
 *               esse arquivo que alguém vai abrir.
 *
 * Uso: php scripts/limpar-comentarios.php <pasta>
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$raiz = $argv[1] ?? '';
if ($raiz === '' || !is_dir($raiz)) {
    fwrite(STDERR, "Uso: php scripts/limpar-comentarios.php <pasta>\n");
    exit(1);
}

/**
 * Tira comentários de bloco e de linha inteira.
 *
 * Conservador de propósito. Não mexe em comentário que venha DEPOIS de código
 * na mesma linha: distinguir `// nota` de `https://exemplo.com` exige entender
 * strings e expressões regulares do JavaScript, e um removedor esperto que erra
 * uma vez quebra o site inteiro em silêncio. O ganho seria de alguns bytes.
 */
function limpar_codigo(string $conteudo): string
{
    // Bloco /* ... */ — em CSS e JS não há literal com essa sequência aqui.
    $conteudo = preg_replace('~/\*.*?\*/~s', '', $conteudo) ?? $conteudo;

    // Linhas que são só comentário, com ou sem indentação.
    $conteudo = preg_replace('~^[ \t]*//.*$~m', '', $conteudo) ?? $conteudo;

    // Sobras: três ou mais quebras viram duas.
    return preg_replace('~\n{3,}~', "\n\n", $conteudo) ?? $conteudo;
}

/** Tira comentários HTML. */
function limpar_html(string $conteudo): string
{
    $conteudo = preg_replace('~<!--(?!\[if).*?-->~s', '', $conteudo) ?? $conteudo;
    return preg_replace('~\n{3,}~', "\n\n", $conteudo) ?? $conteudo;
}

$iterador = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)
);

$tratados = 0;
$economia = 0;

foreach ($iterador as $arquivo) {
    if (!$arquivo->isFile()) {
        continue;
    }
    $extensao = strtolower($arquivo->getExtension());
    if (!in_array($extensao, ['css', 'js', 'html'], true)) {
        continue;
    }

    $caminho = $arquivo->getPathname();
    $antes   = (string) file_get_contents($caminho);
    $depois  = ($extensao === 'html') ? limpar_html($antes) : limpar_codigo($antes);

    if ($depois === $antes) {
        continue;
    }

    file_put_contents($caminho, $depois);
    $tratados++;
    $economia += strlen($antes) - strlen($depois);

    printf("  %-42s -%d bytes\n",
        ltrim(str_replace($raiz, '', $caminho), '/'),
        strlen($antes) - strlen($depois));
}

printf("\n%d arquivo(s) limpo(s), %.1f KB a menos.\n", $tratados, $economia / 1024);
printf("Os .php mantêm os comentários: o visitante nunca os recebe.\n");
