/* =============================================================================
   Galeria de imagens da oferta
   =============================================================================
   Carrossel sem biblioteca, sem requisição externa e sem autoplay.

   MELHORIA PROGRESSIVA: o trilho é uma área de rolagem nativa com scroll-snap,
   feita no CSS. Arrastar com o dedo já funciona com este arquivo ausente ou
   quebrado — o que se perde são as setas, os pontinhos e o teclado. Por isso o
   script começa marcando a galeria como "pronta": é essa classe que revela as
   setas, que nascem escondidas no CSS.

   O ÍNDICE ATUAL SAI SEMPRE DA POSIÇÃO DE ROLAGEM, nunca de um contador
   interno. O visitante também arrasta o trilho com o dedo, e um contador
   próprio começaria a mentir no primeiro swipe — pontinho aceso na foto errada
   e seta escondida no meio da galeria.
   ========================================================================== */

(function () {
  'use strict';

  var galerias = document.querySelectorAll('[data-galeria]');
  if (!galerias.length) return;

  /* Consultado uma vez só: a preferência do sistema não muda no meio da visita,
     e ler matchMedia a cada clique não traria nada. */
  var SEM_ANIMACAO = !!(window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches);

  Array.prototype.forEach.call(galerias, iniciar);

  /**
   * Liga uma galeria. Cada uma vive por si — a página pode ter várias, e
   * nenhuma sabe da existência da outra.
   */
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

    /* O PHP entrega a caixa vazia (ou pode não entregar caixa nenhuma): quem
       cria um botão por foto é aqui, porque sem JavaScript eles não navegariam
       para lugar algum. */
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

      /* Sem isto o navegador rolaria o container alguns pixels por tecla, e o
         scroll-snap puxaria de volta: o teclado pareceria travado. Aqui a seta
         anda uma foto inteira, igual ao clique. */
      evento.preventDefault();
      irPara(destino);
    });

    // -------------------------------------------------------------------------
    // Sincronização com a rolagem
    // -------------------------------------------------------------------------

    var aguardando = false;

    trilho.addEventListener('scroll', function () {
      /* O evento de scroll dispara dezenas de vezes por gesto. Um quadro de
         animação por vez basta para os pontinhos acompanharem o dedo sem
         recalcular posição a cada disparo. */
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

    /**
     * Distância que o trilho precisa rolar para o item ficar no começo da área
     * visível.
     *
     * offsetLeft do item e do trilho são medidos a partir do mesmo ancestral
     * posicionado (.galeria-carrossel), então a subtração dá a posição do item
     * dentro do conteúdo do trilho — independente de gap, padding ou de quantas
     * fotos vieram antes.
     */
    function deslocamento(item) {
      return item.offsetLeft - trilho.offsetLeft;
    }

    /** Índice da foto mais próxima do centro da área visível. */
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

    /** Rola até a foto pedida, ignorando pedidos fora das pontas. */
    function irPara(indice) {
      if (indice < 0) indice = 0;
      if (indice > itens.length - 1) indice = itens.length - 1;

      var destino = deslocamento(itens[indice]);

      /* Com "reduzir movimento" ligado a rolagem é seca, e a atribuição direta
         de scrollLeft também cobre navegador sem scrollTo por opções. */
      if (SEM_ANIMACAO || !trilho.scrollTo) {
        trilho.scrollLeft = destino;
      } else {
        trilho.scrollTo({ left: destino, behavior: 'smooth' });
      }

      /* Rolar para onde já se está não dispara evento de scroll — sem esta
         chamada o estado poderia ficar desatualizado nesse caso. */
      sincronizar();
    }

    /** Acende o pontinho da foto atual e esconde a seta que não tem para onde ir. */
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
