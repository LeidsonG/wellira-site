<?php
/**
 * Gera o hash da senha do painel.
 *
 * Roda na linha de comando, na máquina do Leidson — nunca no servidor. Uma
 * página de instalação que cria senha é uma porta que alguém esquece aberta;
 * este script não existe em produção porque tools/ não vai no deploy.
 *
 *   php tools/gerar-hash.php "a-senha-escolhida"
 *
 * Copie a saída para inc/senha.php, que está no .gitignore.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Só pela linha de comando.');
}

$senha = $argv[1] ?? '';

if ($senha === '') {
    fwrite(STDERR, "Uso: php tools/gerar-hash.php \"sua-senha\"\n");
    exit(1);
}
if (strlen($senha) < 12) {
    fwrite(STDERR, "Senha curta demais. Use pelo menos 12 caracteres.\n");
    fwrite(STDERR, "O painel fica exposto na internet: senha de 8 caracteres cai em varredura.\n");
    exit(1);
}

// PASSWORD_DEFAULT acompanha o que a versão do PHP considera seguro hoje. Não
// fixamos o algoritmo de propósito: quando a hospedagem atualizar, senhas novas
// já nascem no formato mais forte.
$hash = password_hash($senha, PASSWORD_DEFAULT);

echo "\nGrave isto em inc/senha.php (o arquivo está no .gitignore):\n\n";
echo "<?php\n";
echo "// Hash da senha do painel. Gerado por tools/gerar-hash.php.\n";
echo "// NUNCA versionar este arquivo — o repositório é público.\n";
echo "return " . var_export($hash, true) . ";\n";
echo "\n";
