/* =============================================================================
   Meta Pixel + Google Analytics 4
   =============================================================================
   Arquivo único de propósito. As páginas institucionais são .html estáticas
   (para a prévia do GitHub Pages funcionar sem PHP) e as ofertas são geradas
   por oferta.php — se cada uma trouxesse o próprio trecho, trocar um ID
   exigiria editar tudo e alguma página ficaria para trás.

   Os IDs NÃO ficam neste arquivo: vêm de assets/js/ids.js, que está no
   .gitignore. O repositório é público, e embora o Pixel apareça no inspetor de
   qualquer visitante — ele identifica a conta, não dá acesso a ela — deixá-lo
   num repositório aberto o entrega a quem varre o GitHub em massa. Pixel
   conhecido recebe evento falso, e evento falso ensina a Meta a mostrar o
   anúncio para o público errado.

   Sem ids.js o rastreamento simplesmente não roda, que é o padrão seguro.
   ========================================================================== */

(function () {
  'use strict';

  // ---------------------------------------------------------------------------
  // Configuração
  // ---------------------------------------------------------------------------

  var IDS = window.WELLIRA_IDS || {};

  /** ID do Meta Pixel (Gerenciador de Eventos → Fontes de dados). */
  var META_PIXEL_ID = IDS.pixel || '';

  /** ID de métrica do GA4 (Admin → Fluxos de dados). */
  var GA4_ID = IDS.ga4 || '';

  /**
   * Só rastreia no domínio de produção.
   *
   * Sem esta trava, a prévia do GitHub Pages e o servidor local somariam às
   * estatísticas. O efeito não é cosmético: a Meta aprende com quem converte,
   * e ensiná-la com os nossos próprios testes piora a entrega dos anúncios.
   */
  var DOMINIOS = ['wellira.online', 'www.wellira.online'];

  if (DOMINIOS.indexOf(window.location.hostname) === -1) {
    return;
  }
  if (!META_PIXEL_ID && !GA4_ID) {
    return;
  }

  // ---------------------------------------------------------------------------
  // Google Analytics 4
  // ---------------------------------------------------------------------------

  if (GA4_ID) {
    var tag = document.createElement('script');
    tag.async = true;
    tag.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(GA4_ID);
    document.head.appendChild(tag);

    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', GA4_ID);
  }

  // ---------------------------------------------------------------------------
  // Meta Pixel
  // ---------------------------------------------------------------------------

  if (META_PIXEL_ID) {
    /* Trecho oficial da Meta, mantido como eles publicam. Reescrevê-lo "mais
       bonito" é o tipo de mudança que quebra em silêncio numa atualização. */
    !function (f, b, e, v, n, t, s) {
      if (f.fbq) return; n = f.fbq = function () {
        n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments);
      };
      if (!f._fbq) f._fbq = n;
      n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = [];
      t = b.createElement(e); t.async = !0; t.src = v;
      s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s);
    }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');

    window.fbq('init', META_PIXEL_ID);
    window.fbq('track', 'PageView');
  }

  // ---------------------------------------------------------------------------
  // Clique no botão de compra
  // ---------------------------------------------------------------------------

  /**
   * O clique que leva ao fornecedor é o evento que mais importa.
   *
   * Não há como registrar a compra: ela acontece no site do fornecedor, fora do
   * nosso alcance. O clique é o sinal mais próximo disso que temos, e é ele que
   * a Meta usa para aprender a quem mostrar o anúncio. Sem este evento, a
   * campanha otimiza por visita — e visita barata não é venda.
   *
   * O disparo acontece na captura, antes de a navegação começar: o clique num
   * link sai da página, e um envio iniciado tarde demais não chega a ser feito.
   */
  document.addEventListener('click', function (evento) {
    var alvo = evento.target.closest('a[href^="/go/"]');
    if (!alvo) return;

    var oferta = alvo.getAttribute('href').replace('/go/', '');

    if (window.fbq) {
      window.fbq('track', 'Lead', { content_name: oferta });
    }
    if (window.gtag) {
      window.gtag('event', 'clique_oferta', {
        oferta: oferta,
        transport_type: 'beacon'   // sobrevive à saída da página
      });
    }
  }, true);
})();
