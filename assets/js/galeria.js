/* =============================================================================
   Galeria de imagens da oferta
   =============================================================================
   Carrossel sem biblioteca, sem requisição externa e sem autoplay.

   MELHORIA PROGRESSIVA: o trilho é uma área de rolagem nativa com scroll-snap,
   feita no CSS. Arrastar com o dedo já funciona sem este script; o que se
   perde são as setas, os pontinhos e o teclado.

   O ÍNDICE ATUAL SAI SEMPRE DA POSIÇÃO DE ROLAGEM, nunca de um contador
   interno, para não mentir quando o visitante arrasta o trilho.
   ========================================================================== */

(function () {
  'use strict';

  var galerias = document.querySelectorAll('[data-galeria]');
  if (!galerias.length) return;

  // Consultado uma vez só: a preferência do sistema não muda no meio da visita.
  var SEM_ANIMACAO = !!(window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches);

  Array.prototype.forEach.call(galerias, iniciar);

  // Liga uma galeria. Cada uma vive por si, a página pode ter várias.
  function iniciar(galeria) {
    var trilho = galeria.querySelector('[data-trilho]');
    if (!trilho) return;

    var itens = Array.prototype.slice.call(trilho.querySelectorAll('.galeria-item'));
    if (itens.length < 2) return;   // uma foto só não é carrossel

    var setas = Array.prototype.slice.call(galeria.querySelectorAll('[data-ir]'));
    var caixaPontos = galeria.querySelector('[data-pontos]');
    var pontos = [];

    // -------------------------------------------------------------------------
    // Pontinhos
    // -------------------------------------------------------------------------

    // O PHP entrega a caixa vazia; o botão por foto nasce aqui, sem JS eles
    // não navegariam para lugar algum.
    if (caixaPontos) {
      itens.forEach(function (item, i) {
        var ponto = document.createElement('button');
        ponto.type = 'button';
        ponto.className = 'galeria-ponto';
        ponto.setAttribute('aria-label', 'Image ' + (i + 1));
        ponto.addEventListener('click', function () { irPara(i); });
        caixaPontos.appendChild(ponto);
        pontos.push(ponto);
      });
    }

    // -------------------------------------------------------------------------
    // Setas
    // -------------------------------------------------------------------------

    setas.forEach(function (seta) {
      var passo = parseInt(seta.getAttribute('data-ir'), 10) || 0;
      seta.addEventListener('click', function () {
        irPara(indiceVisivel() + passo);
      });
    });

    // -------------------------------------------------------------------------
    // Teclado (o trilho tem tabindex="0")
    // -------------------------------------------------------------------------

    trilho.addEventListener('keydown', function (evento) {
      var destino = null;

      if (evento.key === 'ArrowRight')     destino = indiceVisivel() + 1;
      else if (evento.key === 'ArrowLeft') destino = indiceVisivel() - 1;
      else if (evento.key === 'Home')      destino = 0;
      else if (evento.key === 'End')       destino = itens.length - 1;
      else return;

      // Sem isto o navegador rolaria alguns pixels e o scroll-snap puxaria de
      // volta, travando o teclado. Aqui a seta anda uma foto inteira.
      evento.preventDefault();
      irPara(destino);
    });

    // -------------------------------------------------------------------------
    // Sincronização com a rolagem
    // -------------------------------------------------------------------------

    var aguardando = false;

    trilho.addEventListener('scroll', function () {
      // O evento dispara dezenas de vezes por gesto; um quadro por vez basta.
      if (aguardando) return;
      aguardando = true;

      if (!window.requestAnimationFrame) { aguardando = false; sincronizar(); return; }
      window.requestAnimationFrame(function () { aguardando = false; sincronizar(); });
    });

    // Girar o telefone muda a largura do slide e, com ela, qual foto está no meio.
    window.addEventListener('resize', sincronizar);

    galeria.classList.add('galeria-pronta');
    sincronizar();

    // -------------------------------------------------------------------------
    // Auxiliares
    // -------------------------------------------------------------------------

    // Distância que o trilho precisa rolar para o item ficar no começo da
    // área visível (offsetLeft do item e do trilho, mesmo ancestral posicionado).
    function deslocamento(item) {
      return item.offsetLeft - trilho.offsetLeft;
    }

    // Índice da foto mais próxima do centro da área visível.
    function indiceVisivel() {
      var centro = trilho.scrollLeft + trilho.clientWidth / 2;
      var melhor = 0;
      var menor = Infinity;

      for (var i = 0; i < itens.length; i++) {
        var distancia = Math.abs(deslocamento(itens[i]) + itens[i].offsetWidth / 2 - centro);
        if (distancia < menor) { menor = distancia; melhor = i; }
      }
      return melhor;
    }

    // Rola até a foto pedida, ignorando pedidos fora das pontas.
    function irPara(indice) {
      if (indice < 0) indice = 0;
      if (indice > itens.length - 1) indice = itens.length - 1;

      var destino = deslocamento(itens[indice]);

      // "Reduzir movimento" ligado, ou sem scrollTo: atribuição direta.
      if (SEM_ANIMACAO || !trilho.scrollTo) {
        trilho.scrollLeft = destino;
      } else {
        trilho.scrollTo({ left: destino, behavior: 'smooth' });
      }

      // Rolar para onde já se está não dispara evento de scroll.
      sincronizar();
    }

    // Acende o pontinho da foto atual e esconde a seta sem para onde ir.
    function sincronizar() {
      var indice = indiceVisivel();

      pontos.forEach(function (ponto, i) {
        ponto.classList.toggle('ativo', i === indice);
        if (i === indice) ponto.setAttribute('aria-current', 'true');
        else ponto.removeAttribute('aria-current');
      });

      setas.forEach(function (seta) {
        var passo = parseInt(seta.getAttribute('data-ir'), 10) || 0;
        var alvo = indice + passo;
        seta.classList.toggle('galeria-seta-oculta', alvo < 0 || alvo > itens.length - 1);
      });
    }
  }
})();
