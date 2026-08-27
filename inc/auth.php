<?php
/**
 * Sessão, login e proteção contra abuso do painel.
 *
 * Não há usuário, só senha: a cliente é a única pessoa que usa o painel.
 */

require_once __DIR__ . '/config.php';

// Quantas tentativas erradas antes de bloquear, e por quanto tempo.
const LOGIN_MAX_TENTATIVAS = 5;
const LOGIN_JANELA_SEG     = 900;   // 15 min de contagem
const LOGIN_BLOQUEIO_SEG   = 900;   // 15 min de castigo

/**
 * Inicia a sessão com cookie endurecido: HttpOnly, SameSite e Secure.
 * Precisa rodar antes de qualquer saída.
 */
function sessao_iniciar(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (is_dir(DIR_SESSOES) || @mkdir(DIR_SESSOES, 0700, true)) {
        session_save_path(DIR_SESSOES);
    }

    // Atrás do proxy da hospedagem, $_SERVER['HTTPS'] pode vir vazio mesmo em HTTPS.
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,          // morre ao fechar o navegador
        'path'     => '/admin',   // não acompanha as páginas públicas
        'httponly' => true,
        'secure'   => $https,
        'samesite' => 'Lax',
    ]);

    session_name('wellira_painel');
    session_start();
}

/** A sessão atual está autenticada? */
function autenticado(): bool
{
    return !empty($_SESSION['autenticado']);
}

// Sessão parada por 2h expira. Extraída à parte porque admin/enviar.php
// também precisa checar o prazo, mas responde JSON em vez de redirecionar.
function sessao_expirada(): bool
{
    return isset($_SESSION['visto_em']) && time() - $_SESSION['visto_em'] > 7200;
}

// Exige login, redirecionando para a tela de entrada quando não houver.
// Guarda o destino pretendido para devolver a pessoa ao lugar certo depois.
function exigir_login(): void
{
    sessao_iniciar();

    if (!autenticado()) {
        $_SESSION['destino'] = $_SERVER['REQUEST_URI'] ?? '/admin/';
        header('Location: /admin/login.php');
        exit;
    }

    if (sessao_expirada()) {
        encerrar_sessao();
        header('Location: /admin/login.php?expirou=1');
        exit;
    }
    $_SESSION['visto_em'] = time();
}

// Lê usuário e hash do arquivo de credenciais. Null quando ainda não configurado.
function credenciais(): ?array
{
    if (!is_file(ARQUIVO_SENHA)) {
        return null;
    }
    $dados = require ARQUIVO_SENHA;

    if (!is_array($dados)) {
        return null;
    }
    $usuario = (string) ($dados['usuario'] ?? '');
    $hash    = (string) ($dados['hash'] ?? '');

    return ($usuario !== '' && $hash !== '') ? ['usuario' => $usuario, 'hash' => $hash] : null;
}

// Confere usuário e senha e abre a sessão. Erro em string, ou null se ok.
function tentar_login(string $usuario, string $senha): ?string
{
    sessao_iniciar();

    if (($espera = bloqueio_restante()) > 0) {
        return 'Muitas tentativas. Tente de novo em ' . ceil($espera / 60) . ' minuto(s).';
    }

    $cred = credenciais();
    if ($cred === null) {
        return 'O painel ainda não tem acesso configurado. Ver README.';
    }

    // As duas conferências acontecem sempre, mesmo com o usuário errado, e a
    // mensagem de erro é a mesma nos dois casos. Responder "usuário não existe"
    // entregaria metade da credencial a quem está testando nomes.
    $usuario_ok = hash_equals($cred['usuario'], $usuario);
    $senha_ok   = password_verify($senha, $cred['hash']);

    if (!$usuario_ok || !$senha_ok) {
        registrar_tentativa();
        return 'Usuário ou senha incorretos.';
    }

    limpar_tentativas();

    // Troca o identificador da sessão no momento em que ela ganha privilégio.
    // É o que impede fixation: um id plantado antes do login deixa de valer.
    session_regenerate_id(true);
    $_SESSION['autenticado'] = true;
    $_SESSION['visto_em']    = time();
    $_SESSION['csrf']        = bin2hex(random_bytes(32));

    return null;
}

const SENHA_MINIMA = 10;

// Troca a senha do painel. Erro em string, ou null se ok.
function trocar_senha(string $atual, string $nova, string $confirmacao, ?string $novo_usuario = null): ?string
{
    $cred = credenciais();
    if ($cred === null) {
        return 'O painel ainda não tem acesso configurado.';
    }

    // Exigir a senha atual é o que impede que uma sessão esquecida aberta num
    // computador emprestado vire troca de dono da conta.
    if (!password_verify($atual, $cred['hash'])) {
        registrar_tentativa();
        return 'A senha atual está incorreta.';
    }

    $usuario = $cred['usuario'];
    if ($novo_usuario !== null && trim($novo_usuario) !== '') {
        $usuario = trim($novo_usuario);
        if (!preg_match('/^[A-Za-z0-9._-]{3,32}$/', $usuario)) {
            return 'O usuário deve ter de 3 a 32 caracteres, só letras, números, ponto, hífen ou sublinhado.';
        }
    }
    if ($nova !== $confirmacao) {
        return 'A nova senha e a confirmação não são iguais.';
    }
    if (strlen($nova) < SENHA_MINIMA) {
        return 'A nova senha precisa ter pelo menos ' . SENHA_MINIMA . ' caracteres.';
    }
    if ($nova === $atual) {
        return 'A nova senha precisa ser diferente da atual.';
    }

    // Sem espaço nem quebra antes do <?php: qualquer byte fora das tags vira
    // saída assim que o arquivo é lido, e os cabeçalhos param de funcionar.
    $conteudo = "<?php\n"
              . "// Credenciais do painel. Alteradas pelo próprio painel.\n"
              . "// NUNCA versionar este arquivo, o repositório é público.\n"
              . "return [\n"
              . "    'usuario' => " . var_export($usuario, true) . ",\n"
              . "    'hash'    => " . var_export(password_hash($nova, PASSWORD_DEFAULT), true) . ",\n"
              . "];\n";

    // Escrita atômica: uma gravação interrompida no meio deixaria o arquivo de
    // senha truncado, e ninguém mais conseguiria entrar no painel.
    $temporario = ARQUIVO_SENHA . '.tmp' . bin2hex(random_bytes(4));
    if (@file_put_contents($temporario, $conteudo, LOCK_EX) === false) {
        return 'Não consegui gravar a nova senha. Verifique a permissão da pasta dados/ no servidor.';
    }
    if (!@rename($temporario, ARQUIVO_SENHA)) {
        @unlink($temporario);
        return 'Não consegui gravar a nova senha. Verifique a permissão da pasta dados/ no servidor.';
    }
    @chmod(ARQUIVO_SENHA, 0600);

    limpar_tentativas();

    // Identificador novo depois da troca: se alguém tinha a sessão antiga, ela
    // deixa de valer.
    session_regenerate_id(true);

    return null;
}

/** Derruba a sessão por completo, inclusive o cookie. */
function encerrar_sessao(): void
{
    sessao_iniciar();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'httponly' => true,
            'secure'   => $p['secure'],
            'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------

// Token CSRF da sessão, criado sob demanda.
function csrf_token(): string
{
    sessao_iniciar();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Campo pronto para colar dentro de todo <form method="post">. */
function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

// Confere o token CSRF. hash_equals compara em tempo constante, contra timing attack.
function csrf_ok(): bool
{
    sessao_iniciar();
    $enviado = (string) ($_POST['csrf'] ?? '');
    $sessao  = (string) ($_SESSION['csrf'] ?? '');

    return $sessao !== '' && hash_equals($sessao, $enviado);
}

// A conferência acima, na forma que a maioria das telas usa: falhou, morre aqui.
function csrf_validar(): void
{
    if (!csrf_ok()) {
        http_response_code(400);
        exit('Sessão expirada ou pedido inválido. Volte, recarregue a página e tente de novo.');
    }
}

// ---------------------------------------------------------------------------
// Limite de tentativas
// ---------------------------------------------------------------------------
//
// Contagem em arquivo, por IP, já que não há banco de dados.

// Caminho do contador do IP atual.
function arquivo_tentativas(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido');
    return DIR_DADOS . '/tentativas-' . hash('sha256', $ip) . '.json';
}

/** Segundos restantes de bloqueio. Zero quando liberado. */
function bloqueio_restante(): int
{
    $arquivo = arquivo_tentativas();
    if (!is_file($arquivo)) {
        return 0;
    }
    $d = json_decode((string) file_get_contents($arquivo), true);
    if (!is_array($d)) {
        return 0;
    }

    $ultima = (int) ($d['ultima'] ?? 0);
    $contagem = (int) ($d['contagem'] ?? 0);

    // Passada a janela sem novas tentativas, a contagem morre sozinha.
    if (time() - $ultima > LOGIN_JANELA_SEG) {
        return 0;
    }
    if ($contagem < LOGIN_MAX_TENTATIVAS) {
        return 0;
    }
    return max(0, LOGIN_BLOQUEIO_SEG - (time() - $ultima));
}

/** Soma uma tentativa errada. */
function registrar_tentativa(): void
{
    $arquivo  = arquivo_tentativas();
    $contagem = 0;

    if (is_file($arquivo)) {
        $d = json_decode((string) file_get_contents($arquivo), true);
        if (is_array($d) && time() - (int) ($d['ultima'] ?? 0) <= LOGIN_JANELA_SEG) {
            $contagem = (int) ($d['contagem'] ?? 0);
        }
    }

    @file_put_contents(
        $arquivo,
        json_encode(['contagem' => $contagem + 1, 'ultima' => time()]),
        LOCK_EX
    );

    limpar_tentativas_velhas();
}

// Remove contadores de IPs que já saíram da janela. Roda em 1 de cada 20
// tentativas, para não varrer a pasta a cada requisição.
function limpar_tentativas_velhas(): void
{
    if (random_int(1, 20) !== 1) {
        return;
    }
    $limite = time() - max(LOGIN_JANELA_SEG, LOGIN_BLOQUEIO_SEG);

    foreach (glob(DIR_DADOS . '/tentativas-*.json') ?: [] as $arquivo) {
        if ((int) @filemtime($arquivo) < $limite) {
            @unlink($arquivo);
        }
    }
}

/** Zera a contagem depois de um login bem-sucedido. */
function limpar_tentativas(): void
{
    $arquivo = arquivo_tentativas();
    if (is_file($arquivo)) {
        @unlink($arquivo);
    }
}
