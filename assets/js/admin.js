/* =============================================================================
   Painel — abas, campos repetíveis e atalhos
   =============================================================================
   Sem framework, como o resto do projeto. São três comportamentos pequenos e
   independentes; nenhum deles é pré-requisito do outro.

   MELHORIA PROGRESSIVA: o formulário nasce inteiro visível no HTML. É o
   JavaScript que esconde as seções e liga as abas. Se ele falhar ou demorar, a
   cliente vê um formulário longo — que é exatamente o que existia antes — em
   vez de uma tela com campos inacessíveis e um botão de salvar que envia
   metade da oferta.
   ========================================================================== */

(function () {
  'use strict';

  // ---------------------------------------------------------------------------
  // Abas
  // ---------------------------------------------------------------------------

  var form = document.querySelector('[data-abas]');

  if (form) {
    var secoes = Array.prototype.slice.call(form.querySelectorAll('[data-secao]'));
    var barra  = document.querySelector('.abas');

    if (secoes.length && barra) {
      var botoes = [];

      secoes.forEach(function (secao, i) {
        var botao = document.createElement('button');
        botao.type = 'button';
        botao.className = 'aba';
        botao.textContent = secao.getAttribute('data-secao');
        botao.setAttribute('aria-controls', secao.id);

        botao.addEventListener('click', function () { abrir(i); });
        barra.appendChild(botao);
        botoes.push(botao);
      });

      function abrir(indice) {
        secoes.forEach(function (s, i) { s.hidden = (i !== indice); });
        botoes.forEach(function (b, i) {
          b.classList.toggle('ativa', i === indice);
          b.setAttribute('aria-selected', i === indice ? 'true' : 'false');
        });
        // Guardar a aba faz diferença no uso real: ela salva, volta e quer
        // continuar de onde estava, não recomeçar do topo.
        try { sessionStorage.setItem('wellira_aba', String(indice)); } catch (e) {}
        marcarPreenchidas();
      }

      /**
       * Bolinha na aba que tem conteúdo.
       *
       * A regra do template é "campo vazio = bloco some da página". Sem um
       * sinal na aba, descobrir quais seções vão aparecer exige abrir uma por
       * uma — que é justamente o trabalho que as abas vieram evitar.
       */
      function marcarPreenchidas() {
        secoes.forEach(function (secao, i) {
          var campos = secao.querySelectorAll('input[type="text"], input[type="url"], textarea');
          var tem = Array.prototype.some.call(campos, function (c) {
            return c.value.trim() !== '';
          });
          botoes[i].classList.toggle('tem-conteudo', tem);
        });
      }

      var salva = 0;
      try { salva = parseInt(sessionStorage.getItem('wellira_aba'), 10) || 0; } catch (e) {}
      abrir(salva < secoes.length ? salva : 0);

      form.addEventListener('input', marcarPreenchidas);

      // Um campo obrigatório vazio numa aba escondida trava o envio sem que a
      // cliente veja o motivo: o navegador tenta focar um campo com display
      // none e desiste calado. Abrir a aba do campo resolve.
      form.addEventListener('invalid', function (evento) {
        var secao = evento.target.closest('[data-secao]');
        if (!secao) return;
        var i = secoes.indexOf(secao);
        if (i >= 0 && secoes[i].hidden) {
          abrir(i);
          setTimeout(function () { evento.target.focus(); }, 0);
        }
      }, true);
    }
  }

  // ---------------------------------------------------------------------------
  // Campos repetíveis
  // ---------------------------------------------------------------------------
  //
  // Antes o formulário imprimia os itens existentes mais UM em branco. Para
  // acrescentar dois itens era preciso salvar entre um e outro, e nada dizia
  // isso na tela. Agora a lista cresce sob demanda.

  document.addEventListener('click', function (evento) {
    var add = evento.target.closest('[data-adicionar]');
    if (add) {
      var lista = document.getElementById(add.getAttribute('data-adicionar'));
      var molde = document.getElementById(add.getAttribute('data-molde'));
      if (!lista || !molde) return;

      var novo = molde.content.cloneNode(true);
      lista.appendChild(novo);

      var campo = lista.lastElementChild.querySelector('input, textarea, select');
      if (campo) campo.focus();
      renumerar(lista);
      return;
    }

    var rem = evento.target.closest('[data-remover]');
    if (rem) {
      var item = rem.closest('[data-item]');
      if (!item) return;
      var pai = item.parentElement;
      item.remove();
      renumerar(pai);
    }
  });

  /** Mantém "Linha 1, Linha 2..." coerente depois de adicionar ou remover. */
  function renumerar(lista) {
    if (!lista) return;
    Array.prototype.forEach.call(lista.querySelectorAll('[data-numero]'), function (el, i) {
      el.textContent = String(i + 1);
    });
  }

  // ---------------------------------------------------------------------------
  // Copiar o prompt do ChatGPT
  // ---------------------------------------------------------------------------

  document.addEventListener('click', function (evento) {
    var botao = evento.target.closest('[data-copiar]');
    if (!botao) return;

    var origem = document.getElementById(botao.getAttribute('data-copiar'));
    if (!origem) return;

    var texto = origem.value !== undefined ? origem.value : origem.textContent;
    var rotulo = botao.textContent;

    function avisar(ok) {
      botao.textContent = ok ? '✓ Copiado' : 'Selecione e copie';
      setTimeout(function () { botao.textContent = rotulo; }, 2000);
    }

    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(texto).then(function () { avisar(true); },
                                                function () { avisar(false); });
    } else {
      // Sem HTTPS o navegador bloqueia a área de transferência. Selecionar o
      // texto pelo menos deixa o Ctrl+C a um toque.
      if (origem.select) { origem.select(); }
      avisar(false);
    }
  });
  // ---------------------------------------------------------------------------
  // Interruptores de seção
  // ---------------------------------------------------------------------------
  //
  // O grupo escurece quando desligado, mas os campos continuam habilitados: a
  // cliente pode preparar uma seção com calma e só então ligá-la. Desabilitar
  // os campos os deixaria de fora do envio, e o conteúdo se perderia no salvar.

  function pintar(caixa) {
    var grupo = document.getElementById(caixa.getAttribute('data-alvo'));
    if (grupo) grupo.classList.toggle('desligado', !caixa.checked);
  }

  Array.prototype.forEach.call(document.querySelectorAll('[data-alvo]'), function (caixa) {
    pintar(caixa);
    caixa.addEventListener('change', function () { pintar(caixa); });
  });
})();
