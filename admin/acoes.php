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
        // A cópia nasce sempre como rascunho.
        $original['status'] = 'rascunho';
        $original['titulo'] = 'Cópia de ' . (string) ($original['titulo'] ?? '');

        $novo = slug_livre($slug);
        $resposta = salvar_oferta($novo, $original)
            ? ['ok', 'Cópia criada como rascunho. Abra e ajuste o texto e o link.']
            : ['erro', 'Não consegui criar a cópia.'];
        break;

    case 'excluir':
        // Segunda tranca contra clique acidental: "confirmado" só existe
        // depois do confirm() do navegador. Sem ele, a pergunta é refeita aqui.
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

// Tela de "tem certeza?", usada quando a confirmação do navegador não veio.
// POST de propósito, para não virar link GET que basta abrir para apagar.
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
