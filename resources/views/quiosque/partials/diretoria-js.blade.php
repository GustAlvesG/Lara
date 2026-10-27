{{--
    Lógica da Assinatura da Diretoria no tablet, incluída DENTRO da IIFE do
    quiosque (usa mostra, api, erro, abreAtendimento, iniciaLeitor e
    paraLeitor de lá). Só no modo balcão. Ver DirectorKioskController.

    Ganchos no quiosque, todos atrás de CFG.diretoria:
      - botão "Assinatura da Diretoria" da tela de espera;
      - encerraAtendimento → diretoriaDepoisDoAtendimento: volta à lista
        enquanto a entrada do diretor valer;
      - diretoriaAtiva(): textos de fim de sessão sem "peça ao atendente".

    Nada fica no aparelho: a matrícula e o PIN saem dos campos assim que são
    enviados, e a lista é zerada no "Sair".
--}}
@verbatim
  /* ---------------------------------------------------------------------
   | Assinatura da Diretoria
   |---------------------------------------------------------------------*/

  var D = { ativo: false, nome: null, timerOcioso: null };
  var dRotas = CFG.diretoria.rotas;

  function diretoriaAtiva() {
    return !!D.ativo;
  }

  // Sem toque por um tempo na lista, a entrada do diretor acaba: o próximo a
  // pegar o tablet não pode ver nem abrir os documentos dele.
  function dReiniciaOcioso() {
    clearTimeout(D.timerOcioso);

    D.timerOcioso = setTimeout(function () {
      if ($('#tela-d-lista').classList.contains('on')) {
        dSair();
      }
    }, CFG.diretoria.ociosoSegundos * 1000);
  }

  function dLimpaEntrada() {
    $('#dMatricula').value = '';
    $('#dPin').value = '';
    $('#dErroEntrar').classList.add('hidden');
    $('#dBtnEntrar').disabled = false;
  }

  $('#abrirDiretoria').addEventListener('click', function () {
    dLimpaEntrada();
    paraLeitor();
    mostra('tela-d-entrar');
    $('#dMatricula').focus();
  });

  // Validação de contratos pela Gerência: página própria do tablet (quiosque/gerencia).
  $('#abrirGerencia').addEventListener('click', function () {
    paraLeitor();
    location.href = CFG.gerencia;
  });

  $('#dVoltar').addEventListener('click', function () {
    dLimpaEntrada();
    mostra('tela-espera');
    iniciaLeitor();
  });

  $('#dPin').addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter') {
      $('#dBtnEntrar').click();
    }
  });

  $('#dBtnEntrar').addEventListener('click', function () {
    var matricula = $('#dMatricula').value.trim();
    var pin = $('#dPin').value;

    if (!matricula || !pin) {
      $('#dErroEntrar').textContent = 'Informe a matrícula e o PIN.';
      $('#dErroEntrar').classList.remove('hidden');
      return;
    }

    $('#dBtnEntrar').disabled = true;

    api('POST', dRotas.entrar, { matricula: matricula, pin: pin })
      .then(function (dados) {
        dLimpaEntrada();
        D.ativo = true;
        dMontaLista(dados);
      })
      .catch(function (e) {
        // O PIN errado não fica no campo.
        $('#dPin').value = '';
        $('#dBtnEntrar').disabled = false;
        $('#dErroEntrar').textContent = e.message;
        $('#dErroEntrar').classList.remove('hidden');
      });
  });

  // Marcados para o lote (signer_id => true). Zerado ao sair.
  var dMarcados = {};
  var dLoteMax = 20;
  var dAba = 'pendentes';

  function dAtualizaBotaoLote() {
    var n = Object.keys(dMarcados).length;

    $('#dAssinarLote').disabled = n === 0 || n > dLoteMax;
    $('#dAssinarLote').textContent = n > dLoteMax
      ? 'No máximo ' + dLoteMax + ' de uma vez'
      : (n ? 'Assinar ' + n + ' selecionado(s)' : 'Assinar selecionados');
  }

  function dMostraAba(aba) {
    dAba = aba;
    $('#dAbaPendentes').classList.toggle('on', aba === 'pendentes');
    $('#dAbaAssinados').classList.toggle('on', aba === 'assinados');
    $('#dPainelPendentes').classList.toggle('hidden', aba !== 'pendentes');
    $('#dPainelAssinados').classList.toggle('hidden', aba !== 'assinados');
    $('#dAssinarLote').classList.toggle('hidden', aba !== 'pendentes' || !$('#dDocumentos').querySelector('input:not([disabled])'));
  }

  $('#dAbaPendentes').addEventListener('click', function () { dMostraAba('pendentes'); });
  $('#dAbaAssinados').addEventListener('click', function () { dMostraAba('assinados'); });

  // Qualquer toque na lista conta como atividade.
  $('#tela-d-lista').addEventListener('click', function () {
    if ($('#tela-d-lista').classList.contains('on')) {
      dReiniciaOcioso();
    }
  });

  $('#dMarcarTodos').addEventListener('click', function () {
    var caixas = $('#dDocumentos').querySelectorAll('input:not([disabled])');
    var todas = Array.prototype.every.call(caixas, function (c) { return c.checked; });

    caixas.forEach(function (c) {
      c.checked = !todas;
      c.dispatchEvent(new Event('change'));
    });
  });

  function dMontaLista(dados) {
    D.nome = dados.director.name;
    dLoteMax = dados.batch_max || dLoteMax;

    var alvo = $('#dDocumentos');
    alvo.innerHTML = '';

    $('#tituloDoc').textContent = 'Assinatura da Diretoria';
    $('#subtituloDoc').textContent = D.nome;
    $('#dOla').textContent = 'Olá, ' + D.nome.split(' ')[0];
    $('#dResumo').textContent = dados.documents.length
      ? 'Toque no documento para ler e assinar, ou marque vários e assine de uma vez. '
        + dados.documents.length + ' pendente(s).'
      : 'Tudo em dia.';
    $('#dVazio').classList.toggle('hidden', dados.documents.length > 0);
    $('#dAbaPendentes').textContent = 'Pendentes (' + dados.documents.length + ')';

    // Só continuam marcados os que seguem pendentes e podem ir no lote.
    var aindaMarcados = {};

    // Montado por DOM: título e nomes vêm do cadastro, e não viram HTML.
    dados.documents.forEach(function (doc) {
      var item = document.createElement('div');
      item.className = 'd-item';

      var marca = document.createElement('label');
      marca.className = 'd-marca' + (doc.batch ? '' : ' off');

      var caixa = document.createElement('input');
      caixa.type = 'checkbox';
      caixa.disabled = !doc.batch;
      caixa.checked = !!(doc.batch && dMarcados[doc.signer_id]);
      caixa.setAttribute('aria-label', 'Marcar ' + doc.title);

      if (caixa.checked) {
        aindaMarcados[doc.signer_id] = true;
        item.classList.add('marcado');
      }

      caixa.addEventListener('change', function () {
        if (caixa.checked) {
          dMarcados[doc.signer_id] = true;
        } else {
          delete dMarcados[doc.signer_id];
        }

        item.classList.toggle('marcado', caixa.checked);
        dAtualizaBotaoLote();
      });

      marca.appendChild(caixa);

      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'pessoa';

      var texto = document.createElement('span');
      texto.textContent = doc.title;

      var detalhe = document.createElement('small');
      var assinaram = doc.others.filter(function (o) { return o.signed; }).length;
      detalhe.textContent = doc.area
        + ' · gerado por ' + (doc.created_by || 'não registrado')
        + (doc.expires_at ? ' · até ' + doc.expires_at : '')
        + (doc.others.length ? ' · ' + assinaram + ' de ' + doc.others.length + ' outro(s) já assinaram' : '')
        + (doc.batch ? '' : ' · tem perguntas: assine sozinho');
      texto.appendChild(detalhe);

      var selo = document.createElement('span');
      selo.className = 'selo';
      selo.textContent = 'Assinar';

      b.appendChild(texto);
      b.appendChild(selo);
      b.addEventListener('click', function () { dAbre(doc.signer_id, b); });

      item.appendChild(marca);
      item.appendChild(b);
      alvo.appendChild(item);
    });

    dMarcados = aindaMarcados;
    $('#dMarcarTodos').classList.toggle('hidden', dados.documents.filter(function (d) { return d.batch; }).length < 2);
    dAtualizaBotaoLote();

    dMontaAssinados(dados.signed || []);

    mostra('tela-d-lista');
    dMostraAba(dAba);
    dReiniciaOcioso();
  }

  // "Já assinados": acompanhamento, só leitura.
  function dMontaAssinados(lista) {
    var alvo = $('#dAssinados');
    alvo.innerHTML = '';

    $('#dAbaAssinados').textContent = 'Já assinados (' + lista.length + ')';
    $('#dVazioAssinados').classList.toggle('hidden', lista.length > 0);

    lista.forEach(function (doc) {
      var linha = document.createElement('div');
      linha.className = 'pessoa' + (doc.done ? ' feito' : '');

      var texto = document.createElement('span');
      texto.textContent = doc.title;

      var detalhe = document.createElement('small');
      detalhe.textContent = (doc.area ? doc.area + ' · ' : '') + 'você assinou em ' + doc.signed_at;
      texto.appendChild(detalhe);

      var situacao = document.createElement('small');
      situacao.textContent = doc.situation;
      texto.appendChild(situacao);

      var selo = document.createElement('span');
      selo.className = 'selo' + (doc.done ? '' : ' andamento');
      selo.textContent = doc.done ? 'Concluído' : 'Em andamento';

      linha.appendChild(texto);
      linha.appendChild(selo);
      alvo.appendChild(linha);
    });
  }

  $('#dAssinarLote').addEventListener('click', function () {
    var ids = Object.keys(dMarcados).map(Number);

    if (!ids.length) {
      return;
    }

    // Um só: é a assinatura de sempre (com a via por e-mail, se houver).
    if (ids.length === 1) {
      dAbre(ids[0], $('#dAssinarLote'));
      return;
    }

    $('#dAssinarLote').disabled = true;
    clearTimeout(D.timerOcioso);

    api('POST', dRotas.lote, { signers: ids })
      .then(function (dados) { dAbreLote(dados); })
      .catch(function (e) {
        if (e.diretorEncerrado) {
          dEncerra(e.message);
          return;
        }

        erro(e.message, 'Não foi possível abrir');
        dAtualiza();
      });
  });

  /* ---------------------------------------------------------------------
   | Assinatura em lote: lê um por um, aceita, assina e fotografa uma vez
   |---------------------------------------------------------------------*/

  function dAbreLote(dados) {
    S = estadoLimpo();
    S.lote = { docs: dados.documents, idx: 0, leituras: {} };
    S.titulo = dados.documents.length + ' documentos';
    S.signatario = { name: dados.signer.name, role: '', has_email: false };
    // Identidade = o PIN da entrada; foto sempre (diretor). Sem perguntas: o
    // servidor só aceita no lote documento sem formulário.
    S.regras = {
      identity_check: 'none',
      requires_photo: true,
      requires_initials: !!dados.requires_initials,
      identity_confirmed: true,
    };
    S.sessao.restante = dados.remaining_seconds;
    S.sessao.aviso = dados.warning_seconds || 60;

    $('#tituloDoc').textContent = 'Assinatura em lote';
    $('#subtituloDoc').textContent = S.signatario.name;
    $('#sigNome').textContent = S.signatario.name;
    $('#relogio').classList.remove('hidden');
    $('#avisoFoto').classList.toggle('hidden', !vaiTirarFoto());
    $('#fotoBox').classList.toggle('hidden', !vaiTirarFoto());
    $('#viaBox').classList.add('hidden');
    $('#aceiteTexto').textContent = 'Li e concordo com os termos destes ' + dados.documents.length + ' documentos.';

    // Recusar é de um documento: para isso, o diretor abre aquele sozinho.
    document.querySelectorAll('[data-recusar]').forEach(function (b) { b.classList.add('hidden'); });

    // Sem batimento: o do quiosque é da sessão `lara_sign`, que o lote não usa.
    iniciaContagem();
    loteMostra(0);
  }

  function loteMostra(i) {
    var doc = S.lote.docs[i];

    S.lote.idx = i;
    S.pdfUrl = doc.pdf_url;
    $('#docTitulo').textContent = 'Documento ' + (i + 1) + ' de ' + S.lote.docs.length + ': ' + doc.title;

    mostra('tela-documento');
    carregaPdf();
  }

  function loteLido() {
    var doc = S.lote.docs[S.lote.idx];

    S.lote.leituras[doc.signer_id] = { read_seconds: S.leitura.segundos, scrolled_to_end: S.leitura.ateOFim };

    api('POST', dRotas.loteVisualizado.replace('__SIGNER__', doc.signer_id), {
      read_seconds: S.leitura.segundos,
      scrolled_to_end: S.leitura.ateOFim,
    }).catch(loteFalha);

    if (S.lote.idx < S.lote.docs.length - 1) {
      loteMostra(S.lote.idx + 1);
      return;
    }

    mostra('tela-aceite');
  }

  // Voltar no documento: ao anterior; no primeiro, sair do lote.
  function loteVoltaDocumento() {
    if (S.lote.idx > 0) {
      loteMostra(S.lote.idx - 1);
      return;
    }

    pedeSair();
  }

  // Entrada do diretor ou sessão do documento acabou: volta à lista.
  function loteFalha(e) {
    if (e && (e.diretorEncerrado || e.status === 409)) {
      encerraAtendimento({ mensagem: e.message, titulo: 'Sessão encerrada' });
    }
  }

  function loteEnvia() {
    var ids = S.lote.docs.map(function (d) { return d.signer_id; });

    $('#sucessoDetalhe').textContent = 'Gravando as assinaturas…';
    $('#sucessoVia').textContent = '';
    mostra('tela-sucesso');

    api('POST', dRotas.loteAssinar, {
      signers: ids,
      reads: S.lote.leituras,
      signature: S.assinaturaPng,
      strokes: S.tracosAssinatura,
      initials: S.vistoPng,
      initials_strokes: S.vistoPng ? S.tracosVisto : null,
      photo: S.fotoJpeg,
      photo_skipped_reason: S.semFoto,
      photo_consent: S.autorizouImagem,
      accepted: true,
      viewport: {
        w: window.innerWidth,
        h: window.innerHeight,
        orientation: window.innerWidth > window.innerHeight ? 'landscape' : 'portrait',
      },
    })
      .then(function (dados) {
        var falhas = dados.results.filter(function (r) { return !r.ok; });

        $('#sucessoDetalhe').textContent = dados.signed + ' de ' + ids.length
          + ' documento(s) assinado(s) em ' + dados.signed_at + '. Toque em Concluir para voltar aos seus documentos.';
        $('#sucessoVia').style.whiteSpace = 'pre-line';
        $('#sucessoVia').textContent = falhas.length
          ? 'Não assinados — abra de novo na lista:\n' + falhas.map(function (f) {
            return '• ' + (f.title || 'Documento') + ': ' + f.error;
          }).join('\n')
          : 'Todos assinados. Acompanhe em "Já assinados".';

        pararContagem();
        dMarcados = {};

        setTimeout(function () {
          if (S) {
            encerraAtendimento();
          }
        }, falhas.length ? 30000 : 12000);
      })
      .catch(function (e) {
        if (e.diretorEncerrado) {
          encerraAtendimento({ mensagem: e.message, titulo: 'Sessão encerrada' });
          return;
        }

        // Nada gravado (erro de validação ou rede): volta ao traço.
        abreAssinatura();
        erro(e.message, 'As assinaturas não foram registradas');
      });
  }

  function dAbre(signerId, botao) {
    botao.disabled = true;
    clearTimeout(D.timerOcioso);

    api('POST', dRotas.abrir.replace('__SIGNER__', signerId))
      .then(function (dados) {
        // Mesma sessão do QR: daqui em diante é o atendimento de sempre.
        abreAtendimento(dados);
      })
      .catch(function (e) {
        botao.disabled = false;

        if (e.diretorEncerrado) {
          dEncerra(e.message);
          return;
        }

        erro(e.message, 'Não foi possível abrir');
        dAtualiza();
      });
  }

  function dAtualiza() {
    return api('GET', dRotas.pendentes)
      .then(function (dados) { dMontaLista(dados); })
      .catch(function (e) { dEncerra(e.message); });
  }

  // A entrada do diretor acabou (sair, tempo, servidor): volta à espera.
  function dEncerra(mensagem) {
    D.ativo = false;
    D.nome = null;
    clearTimeout(D.timerOcioso);
    $('#dDocumentos').innerHTML = '';
    $('#dAssinados').innerHTML = '';
    dMarcados = {};
    dAba = 'pendentes';
    $('#tituloDoc').textContent = 'Assinatura de documentos';
    $('#subtituloDoc').textContent = CFG.clube;
    mostra('tela-espera');
    iniciaLeitor();

    if (mensagem) {
      erro(mensagem, 'Entrada encerrada');
    }
  }

  function dSair() {
    api('POST', dRotas.sair).catch(function () {}).then(function () { dEncerra(); });
  }

  $('#dSair').addEventListener('click', dSair);

  /*
   * Fim de um atendimento (assinou, recusou, tempo esgotado) com a entrada do
   * diretor ainda valendo: volta à lista atualizada, com a mensagem, se houver.
   */
  function diretoriaDepoisDoAtendimento(opcoes) {
    dAtualiza().then(function () {
      if (opcoes && opcoes.mensagem) {
        erro(opcoes.mensagem, opcoes.titulo || 'Atendimento encerrado');
      }
    });
  }
@endverbatim
