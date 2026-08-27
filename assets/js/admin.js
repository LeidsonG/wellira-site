/* =============================================================================
   Painel, abas, campos repetíveis e atalhos
   =============================================================================
   Sem framework, como o resto do projeto. Três comportamentos independentes.

   MELHORIA PROGRESSIVA: o formulário nasce inteiro visível no HTML. É o
   JavaScript que esconde as seções e liga as abas. Se ele falhar, a cliente
   vê um formulário longo em vez de campos inacessíveis.
   ========================================================================== */

(function () {
  'use strict';

  // ---------------------------------------------------------------------------
  // Ajudantes
  // ---------------------------------------------------------------------------

  // Cria um elemento com texto seguro, textContent, nunca innerHTML.
  function el(tag, classe, texto) {
    var n = document.createElement(tag);
    if (classe) n.className = classe;
    if (texto !== undefined && texto !== null) n.textContent = texto;
    return n;
  }

  // Endereço público de um arquivo enviado pelo painel. encodeURIComponent
  // impede que ".." ou "/" no nome escapem da pasta de uploads.
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
        // A aba viaja no formulário e volta na URL depois de salvar.
        var campo = document.getElementById('aba-atual');
        if (campo) campo.value = String(indice);
        marcarPreenchidas();
      }

      // Bolinha na aba que tem conteúdo, seguindo "campo vazio = bloco some".
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

  document.addEventListener('click', function (evento) {
    var add = evento.target.closest('[data-adicionar]');
    if (add) {
      var lista = document.getElementById(add.getAttribute('data-adicionar'));
      var molde = document.getElementById(add.getAttribute('data-molde'));
      if (!lista || !molde) return;

      // Cinto e suspensório: cobre o clique que escapa antes do botão repintar.
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

  // Mantém "Linha 1, Linha 2..." coerente depois de adicionar ou remover.
  function renumerar(lista) {
    if (!lista) return;
    Array.prototype.forEach.call(lista.querySelectorAll('[data-numero]'), function (el, i) {
      el.textContent = String(i + 1);
    });
  }

  // Numera também ao ABRIR a página: o PHP imprime "1" em toda linha, tanto
  // gravada quanto no <template>.
  Array.prototype.forEach.call(document.querySelectorAll('.repetivel'), renumerar);

  // ---------------------------------------------------------------------------
  // Teto de itens (data-max)
  // ---------------------------------------------------------------------------
  //
  // Só vale para a lista que declara o teto (hoje, imagens via MAX_IMAGENS).
  // As demais listas repetíveis continuam sem limite.

  // O teto da lista, ou null quando não há (atributo ausente/inválido).
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

  // Como chamar o que a lista guarda, na mensagem do teto. Sai do rótulo da
  // aba ("Imagens" -> "imagens"); data-max-rotulo tem precedência.
  function rotuloDaLista(lista) {
    var proprio = lista.getAttribute('data-max-rotulo');
    if (proprio) return proprio;
    var secao = lista.closest('[data-secao]');
    var nome = secao ? secao.getAttribute('data-secao') : '';
    return nome ? nome.toLowerCase() : 'itens';
  }

  // O botão "+ Adicionar" que aponta para esta lista.
  function botaoDaLista(lista) {
    var achado = null;
    Array.prototype.forEach.call(document.querySelectorAll('[data-adicionar]'), function (b) {
      if (b.getAttribute('data-adicionar') === lista.id) achado = b;
    });
    return achado;
  }

  // Liga ou desliga o botão de adicionar conforme o teto, e explica por quê.
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
      // role=status precisa existir ANTES do texto para o leitor de tela anunciar.
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
      // Sem HTTPS o navegador bloqueia a área de transferência.
      if (origem.select) { origem.select(); }
      avisar(false);
    }
  });
  // ---------------------------------------------------------------------------
  // Interruptores de seção
  // ---------------------------------------------------------------------------
  //
  // O grupo escurece quando desligado, mas os campos continuam habilitados,
  // para não perder o conteúdo no envio.

  function pintar(caixa) {
    var grupo = document.getElementById(caixa.getAttribute('data-alvo'));
    if (grupo) grupo.classList.toggle('desligado', !caixa.checked);
  }

  Array.prototype.forEach.call(document.querySelectorAll('[data-alvo]'), function (caixa) {
    pintar(caixa);
    caixa.addEventListener('change', function () { pintar(caixa); });
  });

  // ---------------------------------------------------------------------------
  // Chave de liga/desliga ao lado de um campo de uma linha
  // ---------------------------------------------------------------------------
  //
  // Diferente do interruptor de seção, aqui desligar DESABILITA o campo: não
  // é enviado, o salvar grava vazio, e vazio faz o bloco sumir da página.

  document.addEventListener('click', function (evento) {
    var chave = evento.target.closest('[data-chave]');
    if (!chave) return;

    var campo = document.getElementById(chave.getAttribute('aria-controls'));
    if (!campo) return;

    var ligando = chave.getAttribute('aria-checked') !== 'true';
    chave.setAttribute('aria-checked', ligando ? 'true' : 'false');

    var caixa = chave.closest('.campo-chave');
    if (caixa) caixa.classList.toggle('desligado', !ligando);

    if (ligando) {
      campo.disabled = false;
      if (campo.value === '' && chave.dataset.guardado) {
        campo.value = chave.dataset.guardado;
      }
      delete chave.dataset.guardado;
      campo.focus();
    } else {
      if (campo.value !== '') chave.dataset.guardado = campo.value;
      // Esvaziar não é redundante com o disabled: o campo precisa MOSTRAR o
      // que vai valer no salvar.
      campo.value = '';
      campo.disabled = true;
    }

    // Avisa a bolinha da aba, que conta campos preenchidos.
    campo.dispatchEvent(new Event('input', { bubbles: true }));
  });

  // ---------------------------------------------------------------------------
  // Prefixo fixo do link do fornecedor (http:// ou https://)
  // ---------------------------------------------------------------------------

  (function () {
    var caixa    = document.querySelector('[data-link-http]');
    var prefixo  = document.querySelector('[data-link-prefixo]');
    if (!caixa || !prefixo) return;
    caixa.addEventListener('change', function () {
      prefixo.textContent = caixa.checked ? 'http://' : 'https://';
    });
  })();

  // ---------------------------------------------------------------------------
  // Prévia do ícone do selo, ao lado do menu
  // ---------------------------------------------------------------------------
  //
  // innerHTML aqui é seguro e deliberado: o traçado vem de inc/config.php,
  // não do formulário.
  (function () {
    var tag = document.getElementById('icones-svg');
    var tracados;
    try { tracados = tag ? JSON.parse(tag.textContent) : {}; } catch (erro) { tracados = {}; }

    function desenharIcone(select) {
      var previa = select.parentElement.querySelector('[data-icone-previa]');
      var tracado = tracados[select.value];
      if (!previa || !tracado) return;
      previa.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
        'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + tracado + '</svg>';
    }

    document.addEventListener('change', function (evento) {
      if (evento.target.matches('[data-icone-select]')) desenharIcone(evento.target);
    });

    // Redesenha depois de qualquer adicionar/remover.
    document.addEventListener('click', function (evento) {
      if (!evento.target.closest('[data-adicionar], [data-remover]')) return;
      setTimeout(function () {
        document.querySelectorAll('[data-icone-select]').forEach(desenharIcone);
      }, 0);
    });
  })();

  // ---------------------------------------------------------------------------
  // Imagens da oferta, miniatura viva e envio por trás
  // ---------------------------------------------------------------------------
  //
  // O campo guarda o NOME DO ARQUIVO; a miniatura viva mostra na hora se o
  // nome digitado bate com um arquivo real. O envio acontece por fetch para
  // /admin/enviar.php, sem NUNCA submeter o formulário da oferta inteira.

  var listaImagens = document.getElementById('lista-imagens');

  if (listaImagens) {
    // Todos os campos de nome de arquivo, na ordem em que aparecem na tela.
    function camposArquivo() {
      return Array.prototype.slice.call(
        listaImagens.querySelectorAll('input[name="imagem_arquivo[]"]')
      );
    }

    // Põe (ou tira) a imagem que o campo aponta na miniatura da linha. O src
    // só é reatribuído quando o endereço muda de fato, para não disparar um
    // pedido a cada tecla digitada.
    function atualizarMiniatura(campo) {
      var item = campo.closest('[data-item]');
      if (!item) return;
      var img = item.querySelector('[data-miniatura]');
      if (!img) return;

      var nome = campo.value.trim();
      var url  = nome === '' ? '' : urlUpload(nome);

      var botao = item.querySelector('[data-enviar-botao]');
      if (botao) botao.textContent = nome === '' ? 'Enviar foto' : 'Trocar foto';

      if (img.getAttribute('data-atual') === url) return;
      img.setAttribute('data-atual', url);

      // O aviso de arquivo quebrado se refere ao endereço anterior.
      item.classList.remove('item-imagem-quebrada');

      if (url === '') {
        img.hidden = true;
        img.removeAttribute('src');
        return;
      }
      img.hidden = false;
      img.src = url;
    }

    // Põe um nome de arquivo no primeiro campo vazio (depois de `aPartirDe`,
    // a linha de origem, para não inverter a ordem escolhida) ou numa linha
    // nova. Devolve true se entrou.
    function inserirArquivo(nome, aPartirDe) {
      var campos = camposArquivo();
      var alvo = null;
      var inicio = 0;

      if (aPartirDe) {
        var pos = campos.indexOf(campoDoItem(aPartirDe));
        if (pos !== -1) inicio = pos + 1;
      }

      for (var i = inicio; i < campos.length; i++) {
        if (campos[i].value.trim() === '') { alvo = campos[i]; break; }
      }

      if (!alvo && !listaCheia(listaImagens)) {
        // Cabe crescer, respeitando o teto.
        var molde = document.getElementById('molde-imagens');
        if (!molde) return false;
        listaImagens.appendChild(molde.content.cloneNode(true));
        renumerar(listaImagens);
        var novos = camposArquivo();
        alvo = novos[novos.length - 1];
      }

      if (!alvo) {
        // Último recurso: campo vazio ANTES da linha de origem, para a foto
        // já enviada não ficar órfã no servidor.
        for (var j = 0; j < inicio && j < campos.length; j++) {
          if (campos[j].value.trim() === '') { alvo = campos[j]; break; }
        }
      }
      if (!alvo) return false;

      alvo.value = nome;
      atualizarMiniatura(alvo);
      atualizarTeto(listaImagens);

      // Evento sintético: faz a bolinha da aba e a prévia reagirem como se ela tivesse digitado.
      alvo.dispatchEvent(new Event('input', { bubbles: true }));

      // Rola até a linha preenchida, que pode cair fora da tela num lote.
      var item = alvo.closest('[data-item]');
      if (item && item.scrollIntoView) {
        item.scrollIntoView({ block: 'center', behavior: 'smooth' });
      }
      return true;
    }

    // Quantos nomes ainda cabem: campos vazios existentes + espaço até o teto.
    function vagas() {
      var vazios = camposArquivo().filter(function (c) {
        return c.value.trim() === '';
      }).length;

      var teto = tetoDaLista(listaImagens);
      if (teto === null) return Infinity;   // lista sem teto: cabe o que vier

      var cabeCrescer = Math.max(0, teto - listaImagens.querySelectorAll('[data-item]').length);
      return vazios + cabeCrescer;
    }

    // ---- Envio, um por linha ----
    //
    // Cada linha envia a sua foto, pelo botão ou arrastando em cima dela.
    // Tudo por delegação no container, para linha clonada já chegar funcionando.

    function campoDoItem(item) {
      return item ? item.querySelector('input[name="imagem_arquivo[]"]') : null;
    }

    // Escreve o nome numa linha específica e avisa o resto do painel.
    function definirArquivo(item, nome) {
      var campo = campoDoItem(item);
      if (!campo) return false;
      campo.value = nome;
      atualizarMiniatura(campo);
      campo.dispatchEvent(new Event('input', { bubbles: true }));
      return true;
    }

    function dizer(item, texto, erro) {
      var aviso = item ? item.querySelector('[data-enviar-estado]') : null;
      if (!aviso) return;
      // Limpa com '' em vez de hidden: região viva escondida não é anunciada ao reaparecer.
      aviso.textContent = texto;
      aviso.classList.toggle('enviar-estado-erro', erro === true);
    }

    // Trava a linha durante o envio, inclusive contra um segundo envio nela.
    function travarEnvio(item, travado) {
      if (!item) return;
      var campo = item.querySelector('[data-enviar-campo]');
      var botao = item.querySelector('[data-enviar-botao]');
      if (campo) campo.disabled = travado;
      if (botao) {
        botao.disabled = travado;
        botao.classList.toggle('enviando', travado);
      }
      // O × também trava, contra deixar o arquivo já subido sem lugar para onde ir.
      var remover = item.querySelector('[data-remover]');
      if (remover) remover.disabled = travado;

      item.classList.toggle('item-enviando', travado);
    }

    function plural(n, singular, pluralForma) {
      return n + ' ' + (n === 1 ? singular : pluralForma);
    }

    // Quantas fotos este envio pode aproveitar: a própria linha sempre conta
    // (troca ou entra vazia), o resto segue a regra normal de vagas().
    function cabemNesteEnvio(item) {
      var campo = campoDoItem(item);
      var jaTem = campo && campo.value.trim() !== '';
      var livres = vagas();
      return livres === Infinity ? Infinity : livres + (jaTem ? 1 : 0);
    }

    // Sobe os arquivos escolhidos por UMA linha: o primeiro fica nela, os
    // demais seguem para os campos vazios adiante.
    function enviarPorLinha(item, escolhidos) {
      if (!item || !escolhidos.length) return;

      // O token sai do formulário da oferta, que já o tem.
      var campoCsrf = document.querySelector('[data-abas] input[name="csrf"]');
      if (!campoCsrf || !campoCsrf.value) {
        dizer(item, 'Esta página perdeu a credencial do envio. Recarregue a página e tente de novo.', true);
        return;
      }

      var livres = cabemNesteEnvio(item);

      if (livres <= 0) {
        dizer(item, 'A oferta já está no máximo de ' + tetoDaLista(listaImagens) +
              ' imagens. Remova uma da lista para poder enviar outra.', true);
        return;
      }

      // O corte é ANTES de enviar, não depois, contra arquivo órfão no servidor.
      var enviar   = escolhidos.slice(0, livres);
      var sobraram = escolhidos.length - enviar.length;

      var dados = new FormData();
      dados.append('csrf', campoCsrf.value);
      dados.append('destino', 'imagem');
      enviar.forEach(function (a) { dados.append('arquivo[]', a); });

      travarEnvio(item, true);
      dizer(item, 'Enviando ' + plural(enviar.length, 'foto', 'fotos') + '…', false);

      fetch('/admin/enviar.php', {
        method: 'POST',
        body: dados,
        credentials: 'same-origin'   // a sessão do painel é o que autentica
      }).then(function (resposta) {
        // Lê como texto e traduz: sessão expirada devolve HTML de login, não JSON.
        return resposta.text().then(function (bruto) {
          try {
            return JSON.parse(bruto);
          } catch (e) {
            throw new Error('resposta-invalida');
          }
        });
      }).then(function (dadosResposta) {
        var nomes = Array.isArray(dadosResposta.nomes) ? dadosResposta.nomes : [];
        var erros = Array.isArray(dadosResposta.erros) ? dadosResposta.erros : [];
        var entraram = 0;
        // A linha pode ter sido apagada enquanto o arquivo subia; nesse caso
        // todas seguem pelo caminho normal da lista.
        var primeira = item.isConnected !== false;

        nomes.forEach(function (nome) {
          if (typeof nome !== 'string' || nome === '') return;
          // A primeira vai para ESTA linha: é o ponto do envio por linha.
          var entrou = primeira ? definirArquivo(item, nome) : inserirArquivo(nome, item);
          if (entrou) { entraram++; primeira = false; }
        });

        if (entraram) atualizarTeto(listaImagens);

        var partes = [];
        if (entraram) partes.push(plural(entraram, 'foto entrou', 'fotos entraram') + '.');
        erros.forEach(function (e2) { if (typeof e2 === 'string' && e2) partes.push(e2); });
        if (sobraram) {
          partes.push((sobraram === 1 ? 'Não coube 1 foto' : 'Não couberam ' + sobraram + ' fotos') +
                      ': a oferta aceita no máximo ' + tetoDaLista(listaImagens) + '.');
        }
        if (!partes.length) partes.push('Nada foi enviado. Tente de novo.');

        // Vermelho só quando NADA entrou: sucesso parcial é sucesso.
        dizer(item, partes.join('\n'), entraram === 0);
      }).catch(function (erro) {
        dizer(item, erro && erro.message === 'resposta-invalida'
          ? 'O servidor respondeu de um jeito inesperado. Sua sessão pode ter expirado: recarregue a página e entre de novo.'
          : 'Não foi possível enviar. Verifique a conexão e tente de novo.', true);
      }).then(function () {
        travarEnvio(item, false);
        var campo = item.querySelector('[data-enviar-campo]');
        // Sem isto, escolher a MESMA foto de novo não dispara 'change'.
        if (campo) campo.value = '';
      });
    }

    // Só o que o servidor aceita; pasta arrastada não vira arquivo.
    function somenteImagens(lista) {
      return Array.prototype.slice.call(lista || []).filter(function (a) {
        return a && a.type && /^image\//.test(a.type);
      });
    }

    // ---- Ligações ----

    listaImagens.addEventListener('input', function (evento) {
      if (evento.target.name !== 'imagem_arquivo[]') return;
      atualizarMiniatura(evento.target);
    });

    // O botão é quem aparece; o <input type="file"> fica escondido.
    listaImagens.addEventListener('click', function (evento) {
      var botao = evento.target.closest('[data-enviar-botao]');
      if (!botao || botao.disabled) return;
      var item = botao.closest('[data-item]');
      var campo = item && item.querySelector('[data-enviar-campo]');
      if (campo) campo.click();
    });

    listaImagens.addEventListener('change', function (evento) {
      var campo = evento.target;
      if (!campo.hasAttribute || !campo.hasAttribute('data-enviar-campo')) return;
      var escolhidos = somenteImagens(campo.files);
      if (!escolhidos.length) { campo.value = ''; return; }
      enviarPorLinha(campo.closest('[data-item]'), escolhidos);
    });

    // ---- Arrastar e soltar ----
    //
    // A linha inteira é o alvo, não só a moldura da foto.

    listaImagens.addEventListener('dragover', function (evento) {
      var item = evento.target.closest('[data-item]');
      if (!item || item.classList.contains('item-enviando')) return;
      // Sem preventDefault o navegador recusa o soltar.
      evento.preventDefault();
      evento.dataTransfer.dropEffect = 'copy';
      item.classList.add('item-soltar');
    });

    listaImagens.addEventListener('dragleave', function (evento) {
      var item = evento.target.closest('[data-item]');
      if (!item) return;
      // dragleave também dispara ao passar entre filhos da mesma linha.
      if (evento.relatedTarget && item.contains(evento.relatedTarget)) return;
      item.classList.remove('item-soltar');
    });

    listaImagens.addEventListener('drop', function (evento) {
      var item = evento.target.closest('[data-item]');
      if (!item) return;
      evento.preventDefault();
      item.classList.remove('item-soltar');
      if (item.classList.contains('item-enviando')) return;

      var arquivos = somenteImagens(evento.dataTransfer && evento.dataTransfer.files);
      if (!arquivos.length) {
        dizer(item, 'Isso não é uma imagem. Solte um arquivo JPG, PNG ou WebP.', true);
        return;
      }
      enviarPorLinha(item, arquivos);
    });

    // Soltar FORA de uma linha abriria o arquivo no lugar da página, levando a
    // oferta ainda não salva junto. Impede o padrão no documento.
    ['dragover', 'drop'].forEach(function (nome) {
      document.addEventListener(nome, function (evento) {
        var vindoDeArquivo = evento.dataTransfer &&
          Array.prototype.indexOf.call(evento.dataTransfer.types || [], 'Files') !== -1;
        if (vindoDeArquivo && !evento.defaultPrevented) evento.preventDefault();
      });
    });

    // 'error' de <img> não borbulha, então é ouvido na fase de captura.
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
  // Monta uma miniatura com o texto REAL dos campos, sempre por textContent.

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

    // Traçados dos ícones, publicados pelo PHP a partir da constante ICONES.
    var ICONES = (function () {
      var tag = document.getElementById('icones-svg');
      try { return tag ? JSON.parse(tag.textContent) : {}; } catch (e) { return {}; }
    })();

    // Monta o SVG de um selo. innerHTML é seguro aqui: o traçado vem de
    // inc/config.php, não do formulário.
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

    // Marcador do que ainda não foi preenchido.
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

        // Mesmas regras do paragrafos() em inc/funcoes.php. Quebras
        // normalizadas antes de dividir, pois o JS não tem equivalente a \R.
        var normalizado = corpo.replace(/\r\n?/g, '\n');
        var blocos = normalizado.split(/\n[ \t]*\n/);

        // TODOS os blocos são desenhados; quem limita a altura é o CSS.
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

        // Todas as perguntas com suas respostas, não só a primeira aberta.
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
            item.appendChild(el('p', 'previa-faq-falta', 'Sem resposta: esta pergunta não aparece'));
          }
          lista.appendChild(item);
        });
        caixa.appendChild(lista);
      },

      imagens: function (caixa) {
        if (!ligado('imagens')) { caixa.appendChild(vazio('Seção desligada: não aparece')); return; }

        // Arquivo e legenda são lidos juntos, pelo índice.
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
          // "1 sozinha, 2+ carrossel" é regra do template, não do painel.
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

    // Uma imagem da prévia, com a legenda por baixo como na página.
    function figura(item, classe) {
      var fig = el('figure', classe);

      var img = document.createElement('img');
      img.className = 'previa-imagem';
      img.src = urlUpload(item.arquivo);
      img.alt = '';
      img.loading = 'lazy';

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
    // Adicionar/remover item de lista não dispara input no formulário.
    document.addEventListener('click', function (ev) {
      if (ev.target.closest('[data-adicionar], [data-remover]')) {
        setTimeout(desenhar, 0);
      }
    });
  }
})();
