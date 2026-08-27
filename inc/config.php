<?php
/**
 * Configuração central do site.
 *
 * Não há banco de dados: cada oferta é um arquivo JSON em DIR_OFERTAS.
 */

// ---------------------------------------------------------------------------
// Caminhos
// ---------------------------------------------------------------------------

// Pasta dos dados, cada oferta é um arquivo JSON aqui dentro.
define('DIR_DADOS',   dirname(__DIR__) . '/dados');
define('DIR_OFERTAS', DIR_DADOS . '/ofertas');

// Pasta pública dos vídeos enviados pela cliente.
define('DIR_VIDEOS', dirname(__DIR__) . '/assets/videos');
define('URL_VIDEOS', '/assets/videos');

// Pasta pública das imagens enviadas pela cliente.
define('DIR_UPLOADS', dirname(__DIR__) . '/assets/img/uploads');
define('URL_UPLOADS', '/assets/img/uploads');

// Cópia automática de cada oferta, gravada antes de toda sobrescrita.
define('DIR_BACKUPS', DIR_DADOS . '/backups');
const BACKUPS_POR_OFERTA = 10;

// Contadores de clique por oferta, um arquivo por slug.
define('DIR_CLIQUES', DIR_DADOS . '/cliques');

// Sessões do painel, dentro de dados/ (já fechado para a web pelo .htaccess).
define('DIR_SESSOES', DIR_DADOS . '/sessoes');

// Hash da senha do painel. Fica em dados/, gravável pelo PHP, e fora do git.
define('ARQUIVO_SENHA', DIR_DADOS . '/senha.php');

// ---------------------------------------------------------------------------
// Limites de upload
// ---------------------------------------------------------------------------

const MAX_UPLOAD_VIDEO  = 128 * 1024 * 1024; // 128 MB
const MAX_UPLOAD_IMAGEM = 8 * 1024 * 1024;   // 8 MB

// Quantas imagens uma oferta pode exibir na galeria.
const MAX_IMAGENS = 8;

// ---------------------------------------------------------------------------
// Avisos legais
// ---------------------------------------------------------------------------

// Presente em toda oferta, sem exceção: deixa registrado que quem responde
// por pagamento, entrega e garantia é o fornecedor.
const AVISO_BASE = 'Orders are completed on the manufacturer\'s website, which is solely '
                 . 'responsible for payment, shipping, returns and warranty.';

// Avisos adicionais são campo livre por oferta ("avisos_legais" no JSON).

// ---------------------------------------------------------------------------
// Ícones disponíveis para os selos de confiança
// ---------------------------------------------------------------------------

const ICONES = [
    'garantia'   => '<path d="M12 3 4 6.5v5c0 4.6 3.3 8.4 8 9.5 4.7-1.1 8-4.9 8-9.5v-5L12 3Z"/><path d="m9 12 2 2 4-4"/>',
    'escudo'     => '<path d="M12 3 4 6.5v5c0 4.6 3.3 8.4 8 9.5 4.7-1.1 8-4.9 8-9.5v-5L12 3Z"/>',
    'envio'      => '<path d="M2 8h11v8H2zM13 11h4l3 3v2h-7z"/><circle cx="6" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>',
    'fabrica'    => '<path d="M5 21V8l7-5 7 5v13z"/><path d="M9 21v-6h6v6"/>',
    'retorno'    => '<path d="M3 12a9 9 0 1 0 3-6.7M3 4v4h4"/>',
    'cadeado'    => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
    'verificado' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
    'natural'    => '<path d="M6 18C6 10 10 6 18 6c0 8-4 12-12 12Z"/><path d="M8 16c2-4 5-7 8-9"/>',
    'rapidez'    => '<circle cx="12" cy="12" r="9"/><path d="M12 6.5v5.5l4 2"/>',
    'destaque'   => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26"/>',
];

// ---------------------------------------------------------------------------
// Assinatura padrão das ofertas
// ---------------------------------------------------------------------------

// Quem assina a seção "Why I'm sharing this". Fixo de propósito, é a mesma
// pessoa em todas as ofertas; a cliente edita ou desliga pelo painel.
const AUTOR_PADRAO = [
    'nome'  => 'Charlotte Hayes',
    'cargo' => 'Founder & editor',
    'foto'  => '/assets/img/charlotte.jpg',
    'texto' => "I bought this with my own money before writing anything about it. "
             . "I don't recommend products I haven't lived with, and I don't publish the ones "
             . "that disappoint me.",
];

/** Título padrão da seção de assinatura. */
const AUTOR_TITULO_PADRAO = "Why I'm sharing this";

// ---------------------------------------------------------------------------
// Identidade pública do site
// ---------------------------------------------------------------------------

// Endereço canônico, sem barra final. Usado no <link rel="canonical"> e no sitemap.
const SITE_URL = 'https://wellira.online';
