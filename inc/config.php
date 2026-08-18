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

/**
 * ⚠️ PREVIEW — enquanto o site roda no GitHub Pages com produtos fictícios,
 * as páginas saem com noindex. Trocar para false antes de publicar em produção.
 * Ver checklist no README.md.
 */
const BLOQUEAR_INDEXACAO = true;
