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
// Categorias e disclaimers
// ---------------------------------------------------------------------------

/**
 * Cada categoria injeta automaticamente os avisos que lhe cabem.
 *
 * O objetivo é que a cliente nunca precise decidir qual texto legal usar:
 * ela escolhe a categoria no painel e o rodapé se monta sozinho. Colar o
 * aviso da FDA num eletrodoméstico deixa de ser possível, e esquecê-lo num
 * suplemento também.
 */
const CATEGORIAS = [
    'saude' => [
        'rotulo'      => 'Saúde / suplemento',
        'disclaimers' => ['resultados'],
    ],
    'beleza' => [
        'rotulo'      => 'Beleza / cuidado pessoal',
        'disclaimers' => ['resultados'],
    ],
    'eletronico' => [
        'rotulo'      => 'Eletrônico / casa',
        'disclaimers' => [],
    ],
    'outro' => [
        'rotulo'      => 'Outro',
        'disclaimers' => [],
    ],
];

/** Textos dos disclaimers, em inglês (o site público é para os EUA). */
const DISCLAIMERS = [
    'resultados' => '<strong>Results vary.</strong> Individual results are not typical '
                  . 'and depend on diet, activity and other personal factors. Nothing here '
                  . 'is a substitute for medical advice, diagnosis or treatment.',
];

/** Aviso presente em toda oferta, independente da categoria. */
const AVISO_BASE = 'Orders are completed on the manufacturer\'s website, which is solely '
                 . 'responsible for payment, shipping, returns and warranty.';

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
