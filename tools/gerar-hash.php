<?php
/**
 * Gera o arquivo de credenciais do painel.
 *
 * Roda na linha de comando, na máquina do Leidson — nunca no servidor. Uma
 * página de instalação que cria senha é uma porta que alguém esquece aberta;
 * este script não existe em produção porque tools/ não vai no deploy.
 *
 *   php tools/gerar-hash.php <usuario> "<senha>" > dados/senha.php
 *
 * A saída é SÓ o conteúdo do arquivo, sem instrução nem linha em branco. Isso é
 * proposital: a primeira versão imprimia um cabeçalho explicativo, e recortá-lo
 * com `tail` deixou uma quebra de linha antes do <?php. Esse byte vira saída
 * quando o arquivo é lido, os cabeçalhos vão junto, e todo header() posterior
 * deixa de funcionar — o login passou a autenticar sem redirecionar, mostrando
 * uma página em branco. Um byte invisível, meia hora de investigação.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Só pela linha de comando.');
}

$usuario = $argv[1] ?? '';
$senha   = $argv[2] ?? '';

if ($usuario === '' || $senha === '') {
    fwrite(STDERR, "Uso: php tools/gerar-hash.php <usuario> \"<senha>\" > dados/senha.php\n");
    exit(1);
}
if (!preg_match('/^[A-Za-z0-9._-]{3,32}$/', $usuario)) {
    fwrite(STDERR, "Usuário inválido: de 3 a 32 caracteres, letras, números, ponto, hífen ou sublinhado.\n");
    exit(1);
}
if (strlen($senha) < 10) {
    fwrite(STDERR, "Senha curta demais. Use pelo menos 10 caracteres.\n");
    fwrite(STDERR, "O painel fica exposto na internet: senha curta cai em varredura.\n");
    exit(1);
}

// PASSWORD_DEFAULT acompanha o que a versão do PHP considera seguro hoje. Não
// fixamos o algoritmo de propósito: quando a hospedagem atualizar, senhas novas
// já nascem no formato mais forte.
$hash = password_hash($senha, PASSWORD_DEFAULT);

echo "<?php\n";
echo "// Credenciais do painel. Geradas por tools/gerar-hash.php.\n";
echo "// NUNCA versionar este arquivo — o repositório é público.\n";
echo "return [\n";
echo "    'usuario' => ", var_export($usuario, true), ",\n";
echo "    'hash'    => ", var_export($hash, true), ",\n";
echo "];\n";
