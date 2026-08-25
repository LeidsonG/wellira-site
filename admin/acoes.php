<?php
/**
 * Ações de uma linha da lista: duplicar e excluir.
 *
 * Só aceita POST com token. Se fossem links GET, bastaria a cliente abrir uma
 * página maliciosa, ou um robô seguir um link, para apagar uma oferta.
 */

require_once __DIR__ . '/../inc/admin-funcoes.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/layout.php';

exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/');
    exit;
}
csrf_validar();

$slug = (string) ($_POST['slug'] ?? '');
$acao = (string) ($_POST['acao'] ?? '');

if (!slug_valido($slug)) {
    header('Location: /admin/?erro=' . rawurlencode('Oferta inválida.'));
    exit;
}

switch ($acao) {
    case 'duplicar':
        $original = carregar_oferta($slug);
        if ($original === null) {
            $resposta = ['erro', 'Não consegui ler a oferta original.'];
            break;
        }
        // A cópia nasce como rascunho, sempre. Duplicar é o primeiro passo de
        // uma oferta nova: publicar junto colocaria no ar uma página com o
        // texto e o link do produto errado.
        $original['status'] = 'rascunho';
        $original['titulo'] = 'Cópia de ' . (string) ($original['titulo'] ?? '');

        $novo = slug_livre($slug);
        $resposta = salvar_oferta($novo, $original)
            ? ['ok', 'Cópia criada como rascunho. Abra e ajuste o texto e o link.']
            : ['erro', 'Não consegui criar a cópia.'];
        break;

    case 'excluir':
        // Segunda tranca contra clique acidental. O campo "confirmado" não sai
        // do HTML: quem o cria é o próprio confirm() do navegador, depois do
        // sim. Sem ele, o pedido chegou sem ninguém ter respondido nada, JS
        // desligado, script quebrado por um erro anterior na página, ou POST
        // forjado, e aí a pergunta é refeita aqui, no servidor.
        //
        // Perguntar de novo em vez de recusar não é preciosismo: recusar seco
        // deixaria a cliente sem nenhuma forma de excluir uma oferta no dia em
        // que o JavaScript falhar, e a mensagem de erro não diria o que fazer.
        if (empty($_POST['confirmado'])) {
            confirmar_exclusao($slug);
            exit;
        }
        $resposta = excluir_oferta($slug)
            ? ['ok', 'Oferta excluída. Uma cópia de segurança ficou guardada no servidor.']
            : ['erro', 'Não consegui excluir a oferta.'];
        break;

    default:
        $resposta = ['erro', 'Ação desconhecida.'];
}

header('Location: /admin/?' . $resposta[0] . '=' . rawurlencode($resposta[1]));
exit;

/**
 * Tela de "tem certeza?", usada quando a confirmação do navegador não veio.
 *
 * É um POST que devolve HTML de propósito: repetir o pedido num link GET
 * colocaria "apagar oferta" num endereço que basta abrir, que é exatamente o
 * que a regra de ação destrutiva só por POST existe para impedir.
 */
function confirmar_exclusao(string $slug): void
{
    $o      = carregar_oferta($slug);
    $titulo = $o !== null ? (string) ($o['titulo'] ?? $slug) : $slug;

    painel_topo('Excluir oferta');
    ?>
    <div class="cabeca"><h1>Excluir esta oferta?</h1></div>

    <div class="vazio" style="text-align:left">
      <p><strong><?= e($titulo) ?></strong></p>
      <p class="caminho">/<?= e($slug) ?></p>
      <p>A página sai do ar. Uma cópia de segurança fica guardada no servidor.</p>

      <div class="acoes-grade" style="max-width:22rem;margin-top:1.2rem">
        <a class="botao botao-fraco" href="/admin/">Cancelar</a>
        <form method="post" action="/admin/acoes.php">
          <?= csrf_campo() ?>
          <input type="hidden" name="slug" value="<?= e($slug) ?>">
          <input type="hidden" name="acao" value="excluir">
          <input type="hidden" name="confirmado" value="1">
          <button type="submit" class="botao botao-fraco perigo">Sim, excluir</button>
        </form>
      </div>
    </div>
    <?php
    painel_rodape();
}
