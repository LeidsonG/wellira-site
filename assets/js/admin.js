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
  // Ajudantes
  // ---------------------------------------------------------------------------
  //
  // Ficam aqui, no topo do IIFE, porque são usados por mais de um bloco (o
  // envio de imagens e a prévia). Antes o el() morava dentro do bloco da prévia; com
  // 'use strict' uma função declarada dentro de um bloco não sai dele, e
  // duplicar a mesma função em dois lugares é o começo de duas versões dela.

  /** Cria um elemento com texto seguro — textContent, nunca innerHTML. */
  function el(tag, classe, texto) {
    var n = document.createElement(tag);
    if (classe) n.className = classe;
    if (texto !== undefined && texto !== null) n.textContent = texto;
    return n;
  }

  /**
   * Endereço público de um arquivo enviado pelo painel.
   *
   * O nome vem de um campo de texto: a cliente digita, cola, ou copia de um
   * e-mail. encodeURIComponent garante que espaço, acento ou "&" no nome virem
   * um caminho válido em vez de quebrarem a URL — e impede que ".." ou "/"
   * colados no campo escapem da pasta de uploads.
   */
  function urlUpload(nome) {
    return '/assets/img/uploads/' + encodeURIComponent(nome);
  }

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

      // O botão já fica desabilitado ao encher, e navegador não dispara clique
      // em botão desabilitado. Esta guarda é o cinto do suspensório: cobre o
      // clique que escapa entre encher a lista e o botão ser repintado.
      if (listaCheia(lista)) { atualizarTeto(lista); return; }

      var novo = molde.content.cloneNode(true);
      lista.appendChild(novo);

      var campo = lista.lastElementChild.querySelector('input, textarea, select');
      if (campo) campo.focus();
      renumerar(lista);
      atualizarTeto(lista);
      return;
    }

    var rem = evento.target.closest('[data-remover]');
    if (rem) {
      var item = rem.closest('[data-item]');
      if (!item) return;
      var pai = item.parentElement;
      item.remove();
      renumerar(pai);
      atualizarTeto(pai);
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
  // Teto de itens (data-max)
  // ---------------------------------------------------------------------------
  //
  // Só vale para a lista que DECLARA o teto. O PHP publica data-max a partir de
  // uma constante (hoje MAX_IMAGENS, em inc/config.php) e o salvar corta o que
  // passar disso — silenciosamente. Sem aviso na tela, a cliente escolheria a
  // nona foto, salvaria, e ela simplesmente não estaria lá.
  //
  // As outras listas repetíveis (selos, FAQ, "não é para você") não declaram
  // data-max e continuam crescendo sem limite, exatamente como antes.

  /**
   * O teto da lista, ou null quando não há.
   *
   * Atributo ausente, vazio, "oito" ou "8 fotos" viram null — "sem limite" —
   * em vez de um número chutado: travar a lista num valor inventado esconderia
   * conteúdo da cliente por causa de um erro de digitação no PHP.
   */
  function tetoDaLista(lista) {
    if (!lista) return null;
    var bruto = lista.getAttribute('data-max');
    if (bruto === null || !/^\d+$/.test(bruto.trim())) return null;
    var n = parseInt(bruto, 10);
    return n > 0 ? n : null;
  }

  function listaCheia(lista) {
    var teto = tetoDaLista(lista);
    return teto !== null && lista.querySelectorAll('[data-item]').length >= teto;
  }

  /**
   * Como chamar o que a lista guarda, na mensagem do teto.
   *
   * Sai do rótulo da aba que contém a lista ("Imagens" -> "imagens"), para o
   * aviso não ficar preso a esta lista específica. data-max-rotulo tem
   * precedência, se um dia o PHP precisar de uma palavra diferente da aba.
   */
  function rotuloDaLista(lista) {
    var proprio = lista.getAttribute('data-max-rotulo');
    if (proprio) return proprio;
    var secao = lista.closest('[data-secao]');
    var nome = secao ? secao.getAttribute('data-secao') : '';
    return nome ? nome.toLowerCase() : 'itens';
  }

  /** O botão "+ Adicionar" que aponta para esta lista. */
  function botaoDaLista(lista) {
    var achado = null;
    Array.prototype.forEach.call(document.querySelectorAll('[data-adicionar]'), function (b) {
      if (b.getAttribute('data-adicionar') === lista.id) achado = b;
    });
    return achado;
  }

  /**
   * Liga ou desliga o botão de adicionar conforme o teto, e explica por quê.
   *
   * Desabilitar sem dizer nada transforma o teto em bug aos olhos de quem usa:
   * o botão continua ali e simplesmente para de responder.
   */
  function atualizarTeto(lista) {
    var teto = tetoDaLista(lista);
    if (teto === null) return;          // lista sem teto: nada muda

    var botao = botaoDaLista(lista);
    if (!botao) return;

    var cheia = lista.querySelectorAll('[data-item]').length >= teto;
    botao.disabled = cheia;

    var aviso = botao.nextElementSibling;
    if (!aviso || !aviso.classList.contains('teto-aviso')) {
      aviso = el('p', 'teto-aviso');
      // role=status: a mensagem nasce vazia e o CSS a esconde enquanto estiver
      // assim. O leitor de tela só anuncia mudança de região viva que já
      // existia — criá-la junto com o texto não anunciaria nada.
      aviso.setAttribute('role', 'status');
      botao.parentNode.insertBefore(aviso, botao.nextSibling);
    }
    aviso.textContent = cheia
      ? 'Máximo de ' + teto + ' ' + rotuloDaLista(lista) + '. Remova uma para acrescentar outra.'
      : '';
  }

  Array.prototype.forEach.call(document.querySelectorAll('[data-adicionar]'), function (b) {
    var lista = document.getElementById(b.getAttribute('data-adicionar'));
    if (lista) atualizarTeto(lista);
  });

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
  // Imagens da oferta — miniatura viva e envio por trás
  // ---------------------------------------------------------------------------
  //
  // A lista de imagens é uma lista repetível como as outras, mas o campo guarda
  // o NOME DO ARQUIVO — e nome de arquivo é a coisa mais fácil de errar do
  // painel inteiro (um dígito trocado, a extensão .jpeg no lugar de .jpg). Sem
  // retorno na tela, o erro só aparece quando a página publicada mostra um
  // quadrado vazio. Daí a miniatura viva em cada linha.
  //
  // O envio acontece aqui mesmo, sem sair da oferta, mas NUNCA submetendo este
  // formulário: o arquivo vai por fetch para /admin/enviar.php e só o nome
  // devolvido entra no campo. É o motivo de admin/upload.php ter nascido em
  // página separada — um <input type="file"> dentro de um formulário de vinte
  // campos faz a cliente perder tudo o que digitou se o envio estourar o
  // tamanho ou o tempo. Enviando por trás, o texto dela nunca sai da tela.

  var listaImagens = document.getElementById('lista-imagens');

  if (listaImagens) {
    /** Todos os campos de nome de arquivo, na ordem em que aparecem na tela. */
    function camposArquivo() {
      return Array.prototype.slice.call(
        listaImagens.querySelectorAll('input[name="imagem_arquivo[]"]')
      );
    }

    /**
     * Põe (ou tira) a imagem que o campo aponta na miniatura da linha.
     *
     * O src só é reatribuído quando o endereço muda de verdade: cada
     * atribuição dispara um pedido novo ao servidor, e a cliente digita o nome
     * caractere por caractere — sem esta guarda seriam ~25 pedidos por nome,
     * todos com 404, e a miniatura piscaria a cada tecla.
     */
    function atualizarMiniatura(campo) {
      var item = campo.closest('[data-item]');
      if (!item) return;
      var img = item.querySelector('[data-miniatura]');
      if (!img) return;

      var nome = campo.value.trim();
      var url  = nome === '' ? '' : urlUpload(nome);

      if (img.getAttribute('data-atual') === url) return;
      img.setAttribute('data-atual', url);

      // O aviso de arquivo quebrado se refere ao endereço anterior: como o
      // endereço mudou, ele volta a valer só se o novo também falhar.
      item.classList.remove('item-imagem-quebrada');

      if (url === '') {
        img.hidden = true;
        img.removeAttribute('src');
        return;
      }
      img.hidden = false;
      img.src = url;
    }

    /**
     * Põe um nome de arquivo na lista: no primeiro campo vazio, ou numa linha
     * nova se todos já estiverem preenchidos.
     *
     * Devolve true se entrou. O false só acontece se o teto tiver sido
     * alcançado entre a conferência de vagas e a chegada da resposta do
     * servidor — quem chama precisa saber para contar direito o que entrou.
     */
    function inserirArquivo(nome) {
      var campos = camposArquivo();
      var alvo = null;

      for (var i = 0; i < campos.length; i++) {
        if (campos[i].value.trim() === '') { alvo = campos[i]; break; }
      }

      if (!alvo) {
        // Lista cheia e nenhum campo livre: criar a linha aqui furaria o teto
        // que o botão "+ Adicionar imagem" respeita, e o salvar cortaria a
        // sobra depois sem avisar. Note que a lista pode estar CHEIA e ainda
        // ter campo vazio — nesse caso o laço acima já achou o alvo, porque
        // preencher um campo existente não faz a lista crescer.
        if (listaCheia(listaImagens)) return false;

        var molde = document.getElementById('molde-imagens');
        if (!molde) return false;
        listaImagens.appendChild(molde.content.cloneNode(true));
        renumerar(listaImagens);
        var novos = camposArquivo();
        alvo = novos[novos.length - 1];
        if (!alvo) return false;
      }

      alvo.value = nome;
      atualizarMiniatura(alvo);
      atualizarTeto(listaImagens);

      // Um evento 'input' sintético faz o resto do painel reagir como se ela
      // tivesse digitado: a bolinha da aba e a prévia "Como fica na página"
      // já escutam input no documento. Chamar desenhar() daqui exigiria
      // alcançar uma função que só existe quando há [data-previa] na tela —
      // acoplaria este bloco a um que pode não ter carregado.
      alvo.dispatchEvent(new Event('input', { bubbles: true }));

      // Rola até a linha preenchida: com a lista longa, o botão de enviar fica
      // abaixo dela e a linha que acabou de receber o nome está fora da tela.
      // Sem o rolar, o envio parece não ter feito nada.
      var item = alvo.closest('[data-item]');
      if (item && item.scrollIntoView) {
        item.scrollIntoView({ block: 'center', behavior: 'smooth' });
      }
      return true;
    }

    /**
     * Quantos nomes ainda cabem na lista.
     *
     * São duas fontes: os campos que já existem e estão vazios (preencher um
     * deles não faz a lista crescer) e o espaço que falta para o teto. Por isso
     * não basta olhar o número de itens.
     */
    function vagas() {
      var vazios = camposArquivo().filter(function (c) {
        return c.value.trim() === '';
      }).length;

      var teto = tetoDaLista(listaImagens);
      if (teto === null) return Infinity;   // lista sem teto: cabe o que vier

      var cabeCrescer = Math.max(0, teto - listaImagens.querySelectorAll('[data-item]').length);
      return vazios + cabeCrescer;
    }

    // ---- Envio por trás ----

    var envio      = document.querySelector('[data-enviar]');
    var envioCampo = document.querySelector('[data-enviar-campo]');
    var envioBotao = document.querySelector('[data-enviar-botao]');
    var envioAviso = document.querySelector('[data-enviar-estado]');

    function dizer(texto, erro) {
      if (!envioAviso) return;
      // O elemento nasce vazio e com role="status"; é o texto entrando que faz
      // o leitor de tela anunciar. Por isso limpamos com '' em vez de hidden:
      // região viva escondida não é anunciada quando reaparece.
      envioAviso.textContent = texto;
      envioAviso.classList.toggle('enviar-estado-erro', erro === true);
    }

    /** Trava a interface durante o envio — inclusive contra um segundo envio. */
    function travarEnvio(travado) {
      if (envioCampo) envioCampo.disabled = travado;
      // <label> não tem estado desabilitado; o que impede o clique é o input
      // desabilitado logo acima. A classe é só a aparência disso.
      if (envioBotao) {
        envioBotao.classList.toggle('enviando', travado);
        envioBotao.setAttribute('aria-disabled', travado ? 'true' : 'false');
      }
    }

    function plural(n, singular, pluralForma) {
      return n + ' ' + (n === 1 ? singular : pluralForma);
    }

    if (envio && envioCampo) {
      envioCampo.addEventListener('change', function () {
        var escolhidos = Array.prototype.slice.call(envioCampo.files || []);
        if (!escolhidos.length) return;

        // O token sai do formulário da oferta, que já o tem. Inventar um aqui
        // seria inventar uma sessão: sem ele o endpoint recusa, e é melhor
        // dizer isso antes de gastar o upload dela.
        var campoCsrf = document.querySelector('[data-abas] input[name="csrf"]');
        if (!campoCsrf || !campoCsrf.value) {
          envioCampo.value = '';
          dizer('Não foi possível preparar o envio nesta página. Use o link de envio abaixo.', true);
          return;
        }

        var livres = vagas();

        if (livres <= 0) {
          envioCampo.value = '';
          dizer('A oferta já está no máximo de ' + tetoDaLista(listaImagens) +
                ' imagens. Remova uma da lista acima para poder enviar outra.', true);
          return;
        }

        // O corte é ANTES de enviar, não depois. Mandar cinco e aproveitar duas
        // deixaria três arquivos órfãos ocupando espaço na hospedagem da
        // cliente para sempre — ninguém apagaria, porque ninguém saberia.
        var enviar = escolhidos.slice(0, livres);
        var sobraram = escolhidos.length - enviar.length;

        var dados = new FormData();
        dados.append('csrf', campoCsrf.value);
        dados.append('destino', 'imagem');
        enviar.forEach(function (a) { dados.append('arquivo[]', a); });

        travarEnvio(true);
        dizer('Enviando ' + plural(enviar.length, 'foto', 'fotos') + '…', false);

        fetch('/admin/enviar.php', {
          method: 'POST',
          body: dados,
          credentials: 'same-origin'   // a sessão do painel é o que autentica
        }).then(function (resposta) {
          // .json() falha sozinho num corpo que não é JSON, mas a mensagem do
          // navegador não serve para a cliente. Lemos como texto e traduzimos:
          // sessão expirada devolve a página de login, que é HTML.
          return resposta.text().then(function (bruto) {
            try {
              return JSON.parse(bruto);
            } catch (e) {
              throw new Error('resposta-invalida');
            }
          });
        }).then(function (dadosResposta) {
          var nomes  = Array.isArray(dadosResposta.nomes)  ? dadosResposta.nomes  : [];
          var erros  = Array.isArray(dadosResposta.erros)  ? dadosResposta.erros  : [];
          var entraram = 0;

          nomes.forEach(function (nome) {
            if (typeof nome === 'string' && nome !== '' && inserirArquivo(nome)) entraram++;
          });

          var partes = [];
          if (entraram) partes.push(plural(entraram, 'foto entrou', 'fotos entraram') + ' na lista.');
          // Os erros já vêm do servidor com o nome do arquivo na frente.
          erros.forEach(function (e2) { if (typeof e2 === 'string' && e2) partes.push(e2); });
          if (sobraram) {
            partes.push((sobraram === 1 ? 'Não coube 1 foto' : 'Não couberam ' + sobraram + ' fotos') +
                        ': a oferta aceita no máximo ' + tetoDaLista(listaImagens) + '.');
          }
          if (!partes.length) partes.push('Nada foi enviado. Tente de novo ou use o link de envio abaixo.');

          // Vermelho só quando NADA entrou: sucesso parcial é sucesso, e pintar
          // de erro a linha que diz "2 fotos entraram" assusta à toa.
          dizer(partes.join('\n'), entraram === 0);
        }).catch(function (erro) {
          dizer(erro && erro.message === 'resposta-invalida'
            ? 'O servidor respondeu de um jeito inesperado. Sua sessão pode ter expirado: abra o link de envio abaixo numa aba nova e entre de novo.'
            : 'Não foi possível enviar. Verifique a conexão e tente de novo, ou use o link de envio abaixo.', true);
        }).then(function () {
          travarEnvio(false);
          // Sem isto, escolher a MESMA foto de novo não dispara 'change' e o
          // botão parece quebrado — o navegador considera que nada mudou.
          envioCampo.value = '';
        });
      });
    }

    // ---- Ligações ----

    listaImagens.addEventListener('input', function (evento) {
      if (evento.target.name !== 'imagem_arquivo[]') return;
      atualizarMiniatura(evento.target);
    });

    // Nome inexistente é o erro mais provável aqui, e é silencioso: sem aviso a
    // cliente só descobriria abrindo a página publicada. O evento 'error' de
    // <img> não borbulha, então é preciso ouvi-lo na fase de captura — o que
    // tem a vantagem de já valer para as linhas clonadas do <template>.
    document.addEventListener('error', function (evento) {
      var img = evento.target;
      if (!img || !img.hasAttribute || !img.hasAttribute('data-miniatura')) return;
      img.hidden = true;
      var item = img.closest('[data-item]');
      if (item) item.classList.add('item-imagem-quebrada');
    }, true);

    camposArquivo().forEach(atualizarMiniatura);
  }

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

    /** Traçados dos ícones, publicados pelo PHP a partir da constante ICONES. */
    var ICONES = (function () {
      var tag = document.getElementById('icones-svg');
      try { return tag ? JSON.parse(tag.textContent) : {}; } catch (e) { return {}; }
    })();

    /**
     * Monta o SVG de um selo.
     *
     * innerHTML aqui é seguro e deliberado: o traçado vem de inc/config.php,
     * não do formulário. O nome do ícone ainda é validado contra a lista antes
     * de ser usado como chave.
     */
    function icone(nome) {
      if (!ICONES[nome]) return null;
      var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('viewBox', '0 0 24 24');
      svg.setAttribute('width', '22');
      svg.setAttribute('height', '22');
      svg.setAttribute('fill', 'none');
      svg.setAttribute('stroke', 'currentColor');
      svg.setAttribute('stroke-width', '1.8');
      svg.setAttribute('stroke-linecap', 'round');
      svg.setAttribute('stroke-linejoin', 'round');
      svg.innerHTML = ICONES[nome];
      return svg;
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
        var moldura = el('div', 'previa-nao');
        var ul = el('ul', 'previa-lista');
        linhas.slice(0, 6).forEach(function (t) { ul.appendChild(el('li', null, t)); });
        moldura.appendChild(ul);
        caixa.appendChild(moldura);

        var nota = val('nao_e_para_voce_nota');
        if (nota) {
          caixa.appendChild(el('p', 'previa-nota', nota.length > 120 ? nota.slice(0, 120) + '…' : nota));
        }
      },

      selos: function (caixa) {
        if (!ligado('selos')) { caixa.appendChild(vazio('Seção desligada: não aparece')); return; }
        var titulos = valores('selo_titulo[]');
        if (!titulos.length) { caixa.appendChild(vazio('Nenhum selo: o bloco não aparece')); return; }
        var textos = Array.prototype.map.call(document.getElementsByName('selo_texto[]'),
                                              function (e2) { return e2.value.trim(); });
        var nomes = Array.prototype.map.call(document.getElementsByName('selo_icone[]'),
                                             function (e2) { return e2.value; });
        var grade = el('div', 'previa-selos');
        titulos.slice(0, 3).forEach(function (t, i) {
          var s2 = el('div', 'previa-selo');
          var ico = icone(nomes[i]);
          if (ico) s2.appendChild(ico);
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

        // Todas as perguntas com suas respostas, e não só a primeira aberta.
        // Imitar o acordeão da página escondia o conteúdo que ela precisa
        // reler — e conferir o texto é a única razão de a prévia existir.
        var respostas = Array.prototype.map.call(document.getElementsByName('faq_resposta[]'),
                                                 function (e2) { return e2.value.trim(); });
        var lista = el('div', 'previa-faq-lista');

        perguntas.forEach(function (q, i) {
          var item = el('div', 'previa-faq');
          item.appendChild(el('span', 'previa-faq-p', q));

          var r = respostas[i] || '';
          if (r) {
            item.appendChild(el('p', 'previa-faq-r', r.length > 220 ? r.slice(0, 220) + '…' : r));
          } else {
            // Pergunta sem resposta não vira item na página. Dizer isso aqui
            // evita ela publicar achando que a pergunta apareceu.
            item.appendChild(el('p', 'previa-faq-falta', 'Sem resposta: esta pergunta não aparece'));
          }
          lista.appendChild(item);
        });
        caixa.appendChild(lista);
      },

      imagens: function (caixa) {
        if (!ligado('imagens')) { caixa.appendChild(vazio('Seção desligada: não aparece')); return; }

        // Arquivo e legenda são lidos juntos, pelo índice. valores() descarta
        // os vazios e serviria para o arquivo, mas aplicá-lo à legenda
        // desalinharia o par assim que uma imagem ficasse sem legenda.
        var arquivos = Array.prototype.map.call(document.getElementsByName('imagem_arquivo[]'),
                                                function (e2) { return e2.value.trim(); });
        var legendas = Array.prototype.map.call(document.getElementsByName('imagem_legenda[]'),
                                                function (e2) { return e2.value.trim(); });

        var itens = [];
        arquivos.forEach(function (nome, i) {
          if (nome !== '') itens.push({ arquivo: nome, legenda: legendas[i] || '' });
        });

        if (!itens.length) { caixa.appendChild(vazio('Sem imagens: o bloco não aparece')); return; }

        if (itens.length === 1) {
          caixa.appendChild(figura(itens[0], 'previa-imagem-unica'));
          // A regra "1 sozinha, 2+ carrossel" é do template, não do painel: sem
          // esta linha ela não teria como saber por que as setas sumiram ao
          // apagar a segunda imagem.
          caixa.appendChild(el('p', 'previa-nota-imagens', '1 imagem: aparece sozinha, sem setas'));
          return;
        }

        var palco = el('div', 'previa-carrossel');
        palco.appendChild(el('span', 'previa-seta', '‹'));

        var fileira = el('div', 'previa-carrossel-fileira');
        itens.forEach(function (it) { fileira.appendChild(figura(it, 'previa-slide')); });
        palco.appendChild(fileira);

        palco.appendChild(el('span', 'previa-seta', '›'));
        caixa.appendChild(palco);

        var pontos = el('div', 'previa-pontos');
        itens.forEach(function (_, i) {
          pontos.appendChild(el('span', i === 0 ? 'previa-ponto ativo' : 'previa-ponto'));
        });
        caixa.appendChild(pontos);

        caixa.appendChild(el('p', 'previa-nota-imagens',
          itens.length + ' imagens: vira carrossel com setas e pontinhos'));
      }
    };

    /** Uma imagem da prévia, com a legenda por baixo como na página. */
    function figura(item, classe) {
      var fig = el('figure', classe);

      var img = document.createElement('img');
      img.className = 'previa-imagem';
      img.src = urlUpload(item.arquivo);
      img.alt = '';
      img.loading = 'lazy';

      // Deixar o ícone de imagem quebrada do navegador aqui seria ambíguo:
      // pode ser nome errado ou upload ainda não feito. O texto tira a dúvida.
      img.onerror = function () {
        if (!img.parentNode) return;
        img.parentNode.replaceChild(el('span', 'previa-imagem-falta', 'arquivo não encontrado'), img);
      };
      fig.appendChild(img);

      if (item.legenda) fig.appendChild(el('figcaption', 'previa-legenda', item.legenda));
      return fig;
    }

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
