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
        // A aba viaja no formulário: o servidor a devolve na URL depois de
        // salvar. Guardá-la no navegador fazia a cliente abrir uma oferta e
        // cair na aba que estava aberta em outra.
        var campo = document.getElementById('aba-atual');
        if (campo) campo.value = String(indice);
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

      var inicial = 0;
      var campoAba = document.getElementById('aba-atual');
      if (campoAba) {
        inicial = parseInt(campoAba.value, 10) || 0;
      }
      abrir(inicial >= 0 && inicial < secoes.length ? inicial : 0);

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

  // ---------------------------------------------------------------------------
  // Previsão de como a seção fica na página
  // ---------------------------------------------------------------------------
  //
  // Monta uma miniatura com o texto REAL dos campos, acompanhando a digitação.
  // Tudo é escrito com textContent: o conteúdo da cliente nunca é interpretado
  // como marcação, mesmo que ela cole algo com < ou >.

  var previas = Array.prototype.slice.call(document.querySelectorAll('[data-previa]'));

  if (previas.length) {

    function val(id) {
      var el = document.getElementById(id);
      return el ? el.value.trim() : '';
    }

    function valores(nome) {
      return Array.prototype.map.call(
        document.getElementsByName(nome),
        function (el) { return el.value.trim(); }
      ).filter(function (v) { return v !== ''; });
    }

    function ligado(nome) {
      var c = document.querySelector('input[name="mostrar_' + nome + '"]');
      return !c || c.checked;
    }

    /** Cria um elemento com texto seguro. */
    function el(tag, classe, texto) {
      var n = document.createElement(tag);
      if (classe) n.className = classe;
      if (texto !== undefined && texto !== null) n.textContent = texto;
      return n;
    }

    /** Marcador do que ainda não foi preenchido. */
    function vazio(rotulo) {
      return el('p', 'previa-vazio', rotulo);
    }

    var montar = {
      topo: function (caixa) {
        var etiqueta = val('eyebrow'), titulo = val('titulo'), sub = val('subtitulo');
        if (!titulo) { caixa.appendChild(vazio('Escreva o título para ver aqui')); return; }
        if (etiqueta) caixa.appendChild(el('span', 'previa-pill', etiqueta));
        caixa.appendChild(el('h3', 'previa-h1', titulo));
        if (sub) caixa.appendChild(el('p', 'previa-lede', sub));
      },

      video: function (caixa) {
        var v = val('video');
        if (!v) { caixa.appendChild(vazio('Sem vídeo: o bloco não aparece')); return; }
        var caixaV = el('div', 'previa-video');
        caixaV.appendChild(el('span', 'previa-play'));
        caixa.appendChild(caixaV);
        var leg = val('video_legenda');
        caixa.appendChild(el('p', 'previa-legenda', leg || 'sem legenda'));
      },

      botao: function (caixa) {
        caixa.appendChild(el('span', 'previa-botao', (val('botao_texto') || 'See the Official Site') + ' →'));
        var sub = val('botao_sub');
        if (sub) caixa.appendChild(el('p', 'previa-legenda', sub));
      },

      texto: function (caixa) {
        var titulo = val('texto_titulo'), corpo = val('texto');
        if (!corpo) { caixa.appendChild(vazio('Escreva o texto de venda para ver aqui')); return; }
        if (titulo) caixa.appendChild(el('h3', 'previa-h2', titulo));

        // Mesmas regras do paragrafos() em inc/funcoes.php: linha em branco
        // separa parágrafo, "## " no começo do bloco vira subtítulo.
        //
        // As quebras são normalizadas antes de dividir. O PHP usa \R, que
        // cobre \r\n e \r; o JS não tem equivalente, e texto colado do Word
        // ou de um editor do Windows chega com \r\n.
        var normalizado = corpo.replace(/\r\n?/g, '\n');
        var blocos = normalizado.split(/\n[ \t]*\n/);

        // TODOS os blocos são desenhados, e não só os primeiros. Cortar em
        // quatro escondia justamente os subtítulos, que num texto de venda
        // aparecem depois da abertura — e a prévia parecia ignorar o "##".
        // Quem limita a altura é o CSS, com rolagem própria.
        blocos.forEach(function (b) {
          b = b.trim();
          if (b === '') return;
          if (b.indexOf('## ') === 0) {
            caixa.appendChild(el('h4', 'previa-h3', b.slice(3)));
          } else {
            caixa.appendChild(el('p', 'previa-p', b.length > 150 ? b.slice(0, 150) + '…' : b));
          }
        });
      },

      autor: function (caixa) {
        if (!ligado('autor')) { caixa.appendChild(vazio('Seção desligada: não aparece')); return; }
        var texto = val('autor_texto');
        if (!texto) { caixa.appendChild(vazio('Sem texto: o bloco não aparece')); return; }

        var titulo = val('autor_titulo');
        if (titulo) caixa.appendChild(el('h3', 'previa-h2', titulo));

        var linha = el('div', 'previa-autor');
        var foto = val('autor_foto');
        if (foto) {
          var img = document.createElement('img');
          img.className = 'previa-foto';
          img.src = foto.charAt(0) === '/' ? foto : '/assets/img/uploads/' + foto;
          img.alt = '';
          linha.appendChild(img);
        }
        var txt = el('div', 'previa-autor-txt');
        var nome = val('autor_nome');
        if (nome) txt.appendChild(el('strong', null, nome));
        var cargo = val('autor_cargo');
        if (cargo) txt.appendChild(el('span', 'previa-cargo', cargo));
        txt.appendChild(el('p', 'previa-p', texto.length > 160 ? texto.slice(0, 160) + '…' : texto));
        linha.appendChild(txt);
        caixa.appendChild(linha);
      },

      nao: function (caixa) {
        if (!ligado('nao_e_para_voce')) { caixa.appendChild(vazio('Seção desligada: não aparece')); return; }
        var linhas = valores('nao_e_para_voce[]');
        if (!linhas.length) { caixa.appendChild(vazio('Nenhuma linha: o bloco não aparece')); return; }
        var titulo = val('nao_e_para_voce_titulo');
        caixa.appendChild(el('h3', 'previa-h2', titulo || "This isn't for you if…"));
        var ul = el('ul', 'previa-lista');
        linhas.slice(0, 5).forEach(function (t) { ul.appendChild(el('li', null, t)); });
        caixa.appendChild(ul);
      },

      selos: function (caixa) {
        if (!ligado('selos')) { caixa.appendChild(vazio('Seção desligada: não aparece')); return; }
        var titulos = valores('selo_titulo[]');
        if (!titulos.length) { caixa.appendChild(vazio('Nenhum selo: o bloco não aparece')); return; }
        var textos = Array.prototype.map.call(document.getElementsByName('selo_texto[]'),
                                              function (e2) { return e2.value.trim(); });
        var grade = el('div', 'previa-selos');
        titulos.slice(0, 3).forEach(function (t, i) {
          var s2 = el('div', 'previa-selo');
          s2.appendChild(el('strong', null, t));
          if (textos[i]) s2.appendChild(el('span', null, textos[i]));
          grade.appendChild(s2);
        });
        caixa.appendChild(grade);
      },

      faq: function (caixa) {
        if (!ligado('faq')) { caixa.appendChild(vazio('Seção desligada: não aparece')); return; }
        var perguntas = valores('faq_pergunta[]');
        if (!perguntas.length) { caixa.appendChild(vazio('Nenhuma pergunta: o bloco não aparece')); return; }
        caixa.appendChild(el('h3', 'previa-h2', val('faq_titulo') || 'Common questions'));
        perguntas.slice(0, 4).forEach(function (q) {
          caixa.appendChild(el('div', 'previa-faq', '▸ ' + q));
        });
      }
    };

    function desenhar() {
      previas.forEach(function (caixa) {
        var qual = caixa.getAttribute('data-previa');
        if (!montar[qual]) return;
        caixa.textContent = '';
        montar[qual](caixa);
      });
    }

    desenhar();
    document.addEventListener('input', desenhar);
    document.addEventListener('change', desenhar);
    // Adicionar ou remover item de lista não dispara input no formulário.
    document.addEventListener('click', function (ev) {
      if (ev.target.closest('[data-adicionar], [data-remover]')) {
        setTimeout(desenhar, 0);
      }
    });
  }
})();
