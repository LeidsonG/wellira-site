<?php
/**
 * Configuração central do site.
 *
 * Não há banco de dados: cada oferta é um arquivo JSON em DIR_OFERTAS,
 * escrito pelo painel administrativo.
 */

// ---------------------------------------------------------------------------
// Caminhos
// ---------------------------------------------------------------------------

/**
 * Pasta dos dados.
 *
 * Por padrão fica dentro do site, protegida pelo .htaccess de dados/.
 * Em hospedagem onde se possa gravar acima do public_html, mover para fora
 * é mais seguro — basta trocar por algo como dirname(__DIR__, 2) . '/dados'.
 * O restante do código não precisa mudar.
 */
define('DIR_DADOS',   dirname(__DIR__) . '/dados');
define('DIR_OFERTAS', DIR_DADOS . '/ofertas');

/** Pasta pública dos vídeos enviados pela cliente. */
define('DIR_VIDEOS', dirname(__DIR__) . '/assets/videos');
define('URL_VIDEOS', '/assets/videos');

/** Pasta pública das imagens enviadas pela cliente (foto de autoria, pôsteres). */
define('DIR_UPLOADS', dirname(__DIR__) . '/assets/img/uploads');
define('URL_UPLOADS', '/assets/img/uploads');

/**
 * Cópias automáticas de cada oferta, gravadas antes de toda sobrescrita.
 *
 * O conteúdo da cliente existe só no servidor e não passa por git. Sem isto,
 * apagar um parágrafo por engano e salvar é irreversível.
 */
define('DIR_BACKUPS', DIR_DADOS . '/backups');

/** Quantas versões anteriores guardar por oferta. */
const BACKUPS_POR_OFERTA = 10;

/** Contadores de clique por oferta (S3), um arquivo por slug. */
define('DIR_CLIQUES', DIR_DADOS . '/cliques');

/**
 * Onde o PHP grava as sessões do painel.
 *
 * Não usamos o padrão da hospedagem de propósito. Nesta conta ele aponta para
 * /var/cpanel/php/sessions/ea-php83, que não existe nem é gravável — e sem
 * gravar sessão o login nunca completa: o token de CSRF é gerado, some, e o
 * envio seguinte é recusado. O sintoma é um 400 que não explica nada.
 *
 * Apontar para dentro da conta resolve sem depender de chamado no suporte, e
 * sobrevive a uma troca de versão do PHP pelo cPanel, que reescreve o padrão.
 * A pasta fica sob dados/, que o .htaccess de lá já fecha para a web.
 */
define('DIR_SESSOES', DIR_DADOS . '/sessoes');

/**
 * Hash da senha do painel.
 *
 * Mora em dados/, e não em inc/, porque a cliente troca a própria senha pelo
 * painel — o arquivo precisa ser gravável pelo PHP. Deixar código executável
 * gravável pelo servidor web é o que transforma qualquer falha de escrita em
 * execução remota; dados/ já é gravável e já está fechado para a web pelo
 * .htaccess de lá.
 *
 * Fora do git de propósito: o repositório é público. A primeira senha vem de
 * `php tools/gerar-hash.php`. Ver README.
 */
define('ARQUIVO_SENHA', DIR_DADOS . '/senha.php');

// ---------------------------------------------------------------------------
// Limites de upload
// ---------------------------------------------------------------------------

/**
 * Teto por arquivo. O plano compartilhado tem I/O limitado e o vídeo é servido
 * pelo próprio Apache, sem streaming — arquivo grande derruba a página em 4G
 * antes de derrubar o servidor.
 */
const MAX_UPLOAD_VIDEO  = 128 * 1024 * 1024; // 128 MB
const MAX_UPLOAD_IMAGEM = 4 * 1024 * 1024;   // 4 MB

// ---------------------------------------------------------------------------
// Avisos legais
// ---------------------------------------------------------------------------

/**
 * Aviso presente em toda oferta, sem exceção.
 *
 * Não é compliance, é proteção direta: é o que deixa registrado que quem
 * responde por pagamento, entrega, devolução e garantia é o fornecedor. Sem
 * isso, o comprador insatisfeito com a entrega cobra da Wellira.
 */
const AVISO_BASE = 'Orders are completed on the manufacturer\'s website, which is solely '
                 . 'responsible for payment, shipping, returns and warranty.';

/**
 * Avisos adicionais são campo livre por oferta (chave "avisos_legais" no JSON),
 * escritos pela cliente no painel.
 *
 * A alternativa descartada era um mapa de categoria → texto fixo. Ela existiu
 * até 18/08/2026 e foi removida: com os avisos de FDA/DSHEA e "Results vary"
 * dispensados pelo cliente, o mapa não injetava mais nada — virou configuração
 * que não fazia efeito. O campo livre cobre qualquer produto futuro sem exigir
 * que alguém preveja a categoria dele.
 */

// ---------------------------------------------------------------------------
// Ícones disponíveis para os selos de confiança
// ---------------------------------------------------------------------------

/**
 * O painel oferece esta lista num select, para que a cliente escolha o ícone
 * sem precisar lidar com SVG.
 */
const ICONES = [
    'garantia' => '<path d="M12 3 4 6.5v5c0 4.6 3.3 8.4 8 9.5 4.7-1.1 8-4.9 8-9.5v-5L12 3Z"/><path d="m9 12 2 2 4-4"/>',
    'escudo'   => '<path d="M12 3 4 6.5v5c0 4.6 3.3 8.4 8 9.5 4.7-1.1 8-4.9 8-9.5v-5L12 3Z"/>',
    'envio'    => '<path d="M2 8h11v8H2zM13 11h4l3 3v2h-7z"/><circle cx="6" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>',
    'fabrica'  => '<path d="M5 21V8l7-5 7 5v13z"/><path d="M9 21v-6h6v6"/>',
    'retorno'  => '<path d="M3 12a9 9 0 1 0 3-6.7M3 4v4h4"/>',
    'cadeado'  => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
];

// ---------------------------------------------------------------------------
// Identidade pública do site
// ---------------------------------------------------------------------------

/**
 * Endereço canônico, sem barra final.
 *
 * Toda página declara <link rel="canonical"> apontando para cá, e o sitemap é
 * montado a partir disso. É o que impede o Google de tratar as prévias do
 * GitHub Pages e as variações de URL (com/sem www, com/sem barra) como páginas
 * concorrentes: o conteúdo pode ser servido de vários endereços, mas só um
 * conta para a busca.
 */
const SITE_URL = 'https://wellira.online';
