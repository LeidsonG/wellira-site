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

    // A pasta de sessões do cPanel nesta conta aponta para um caminho que não
    // existe, e o PHP falha calado: a sessão nunca grava, o token de CSRF some
    // entre uma requisição e outra, e o login responde 400 sem explicar nada.
    // Gravar dentro da conta resolve e sobrevive a troca de versão do PHP.
    if (is_dir(DIR_SESSOES) || @mkdir(DIR_SESSOES, 0700, true)) {
        session_save_path(DIR_SESSOES);
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

/**
 * Lê usuário e hash do arquivo de credenciais.
 *
 * Devolve null quando o painel ainda não foi configurado.
 */
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

/**
 * Confere usuário e senha e abre a sessão.
 *
 * Devolve string de erro quando falha, ou null em caso de sucesso.
 */
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

/** Tamanho mínimo aceito. O painel fica exposto: senha curta cai em varredura. */
const SENHA_MINIMA = 10;

/**
 * Troca a senha do painel.
 *
 * Existe para que a cliente não dependa de ninguém: a primeira senha é entregue
 * provisória e ela troca sozinha no primeiro acesso.
 *
 * Devolve string de erro, ou null em caso de sucesso.
 */
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
    // saída no momento em que o arquivo é lido, os cabeçalhos são enviados
    // junto, e todo header() posterior deixa de funcionar. Foi assim que o
    // redirecionamento pós-login parou de funcionar em produção.
    $conteudo = "<?php\n"
              . "// Credenciais do painel. Alteradas pelo próprio painel.\n"
              . "// NUNCA versionar este arquivo — o repositório é público.\n"
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

    limpar_tentativas_velhas();
}

/**
 * Remove contadores de IPs que já saíram da janela.
 *
 * Sem isto os arquivos só somem no login bem-sucedido daquele mesmo IP, ou
 * seja: nunca, no caso de quem só erra. Um varredor rodando de endereços
 * diferentes criaria um arquivo por IP, sem teto — e plano compartilhado conta
 * inodes, não só espaço. A limpeza roda em 1 de cada 20 tentativas para não
 * varrer a pasta a cada requisição.
 */
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
