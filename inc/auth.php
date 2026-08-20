<?php
/**
 * Sessão, login e proteção contra abuso do painel.
 *
 * O painel fica numa hospedagem compartilhada, exposto à internet, e dá acesso
 * de escrita a arquivos servidos publicamente. Tudo aqui parte disso: quem
 * achar /admin vai tentar entrar.
 *
 * Não há usuário, só senha. A cliente é a única pessoa que usa o painel, e um
 * campo a menos é um campo a menos para ela errar.
 */

require_once __DIR__ . '/config.php';

/** Quantas tentativas erradas antes de bloquear, e por quanto tempo. */
const LOGIN_MAX_TENTATIVAS = 5;
const LOGIN_JANELA_SEG     = 900;   // 15 min de contagem
const LOGIN_BLOQUEIO_SEG   = 900;   // 15 min de castigo

/**
 * Inicia a sessão com cookie endurecido.
 *
 * Precisa rodar antes de qualquer saída. Os três atributos do cookie cobrem os
 * três jeitos clássicos de roubar sessão: HttpOnly impede JavaScript de ler,
 * SameSite impede o navegador de enviá-lo num POST vindo de outro site, e
 * Secure impede que ele trafegue em claro.
 */
function sessao_iniciar(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Atrás do proxy da hospedagem compartilhada, $_SERVER['HTTPS'] costuma vir
    // vazio mesmo com o site em HTTPS. Sem checar o cabeçalho do proxy, o
    // cookie nunca receberia o atributo Secure em produção.
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,          // morre ao fechar o navegador
        'path'     => '/admin',   // não acompanha as páginas públicas
        'httponly' => true,
        'secure'   => $https,
        'samesite' => 'Lax',
    ]);

    // Nome próprio: o PHPSESSID padrão anuncia a tecnologia para quem varre.
    session_name('wellira_painel');
    session_start();
}

/** A sessão atual está autenticada? */
function autenticado(): bool
{
    return !empty($_SESSION['autenticado']);
}

/**
 * Exige login. Redireciona para a tela de entrada quando não houver.
 *
 * Guarda o destino pretendido para devolver a pessoa ao lugar certo depois de
 * entrar — sem isso, quem clica num link salvo de edição cai sempre na lista.
 */
function exigir_login(): void
{
    sessao_iniciar();

    if (!autenticado()) {
        $_SESSION['destino'] = $_SERVER['REQUEST_URI'] ?? '/admin/';
        header('Location: /admin/login.php');
        exit;
    }

    // Sessão parada por muito tempo é sessão esquecida aberta. Duas horas
    // cobrem uma tarde de trabalho sem obrigar a relogar a cada oferta.
    if (isset($_SESSION['visto_em']) && time() - $_SESSION['visto_em'] > 7200) {
        encerrar_sessao();
        header('Location: /admin/login.php?expirou=1');
        exit;
    }
    $_SESSION['visto_em'] = time();
}

/** Lê o hash da senha. Devolve null quando o painel ainda não foi configurado. */
function hash_senha(): ?string
{
    if (!is_file(ARQUIVO_SENHA)) {
        return null;
    }
    $hash = require ARQUIVO_SENHA;
    return (is_string($hash) && $hash !== '') ? $hash : null;
}

/**
 * Confere a senha e abre a sessão.
 *
 * Devolve string de erro quando falha, ou null em caso de sucesso.
 */
function tentar_login(string $senha): ?string
{
    sessao_iniciar();

    if (($espera = bloqueio_restante()) > 0) {
        return 'Muitas tentativas. Tente de novo em ' . ceil($espera / 60) . ' minuto(s).';
    }

    $hash = hash_senha();
    if ($hash === null) {
        return 'O painel ainda não tem senha configurada. Ver README.';
    }

    if (!password_verify($senha, $hash)) {
        registrar_tentativa();
        return 'Senha incorreta.';
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

/**
 * Token da sessão, criado sob demanda.
 *
 * Sem ele, uma página maliciosa aberta noutra aba conseguiria fazer o navegador
 * da cliente enviar um POST autenticado — apagando ou reescrevendo uma oferta
 * sem que ela clicasse em nada aqui.
 */
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

/**
 * Exige token válido. Encerra a requisição quando não bate.
 *
 * hash_equals compara em tempo constante: um == comum vazaria, pela diferença
 * de tempo de resposta, quantos caracteres iniciais do token estavam certos.
 */
function csrf_validar(): void
{
    sessao_iniciar();
    $enviado = (string) ($_POST['csrf'] ?? '');
    $sessao  = (string) ($_SESSION['csrf'] ?? '');

    if ($sessao === '' || !hash_equals($sessao, $enviado)) {
        http_response_code(400);
        exit('Sessão expirada ou pedido inválido. Volte, recarregue a página e tente de novo.');
    }
}

// ---------------------------------------------------------------------------
// Limite de tentativas
// ---------------------------------------------------------------------------
//
// Contagem em arquivo, por IP. Sem banco de dados não há onde mais guardar, e
// deixar o formulário aceitar tentativas infinitas transforma qualquer senha
// fraca em questão de tempo.

/** Caminho do contador do IP atual. */
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
}

/** Zera a contagem depois de um login bem-sucedido. */
function limpar_tentativas(): void
{
    $arquivo = arquivo_tentativas();
    if (is_file($arquivo)) {
        @unlink($arquivo);
    }
}
