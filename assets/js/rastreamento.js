/* =============================================================================
   Meta Pixel + Google Analytics 4
   =============================================================================
   Arquivo único: páginas institucionais são .html estáticas e as ofertas vêm
   de oferta.php, trocar um ID aqui vale para todas.

   Os IDs NÃO ficam neste arquivo: vêm de assets/js/ids.js, fora do
   repositório público, contra Pixel conhecido receber evento falso.

   Sem ids.js o rastreamento simplesmente não roda, que é o padrão seguro.
   ========================================================================== */

(function () {
  'use strict';

  // ---------------------------------------------------------------------------
  // Configuração
  // ---------------------------------------------------------------------------

  var IDS = window.WELLIRA_IDS || {};

  var META_PIXEL_ID = IDS.pixel || '';
  var GA4_ID = IDS.ga4 || '';

  // Só rastreia no domínio de produção, para o servidor local não somar às estatísticas.
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
    // Trecho oficial da Meta, mantido como eles publicam.
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

  // O clique que leva ao fornecedor é o evento que mais importa: não há como
  // registrar a compra, que acontece no site dele. Disparado na captura,
  // antes de a navegação começar.
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
