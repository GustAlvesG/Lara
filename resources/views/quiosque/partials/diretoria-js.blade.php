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

  function dMontaLista(dados) {
    D.nome = dados.director.name;

    var alvo = $('#dDocumentos');
    alvo.innerHTML = '';

    $('#tituloDoc').textContent = 'Assinatura da Diretoria';
    $('#subtituloDoc').textContent = D.nome;
    $('#dOla').textContent = 'Olá, ' + D.nome.split(' ')[0];
    $('#dResumo').textContent = dados.documents.length
      ? 'Toque no documento para ler e assinar. ' + dados.documents.length + ' pendente(s).'
      : 'Tudo em dia.';
    $('#dVazio').classList.toggle('hidden', dados.documents.length > 0);

    // Montado por DOM: título e nomes vêm do cadastro, e não viram HTML.
    dados.documents.forEach(function (doc) {
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
        + (doc.others.length ? ' · ' + assinaram + ' de ' + doc.others.length + ' outro(s) já assinaram' : '');
      texto.appendChild(detalhe);

      var selo = document.createElement('span');
      selo.className = 'selo';
      selo.textContent = 'Assinar';

      b.appendChild(texto);
      b.appendChild(selo);
      b.addEventListener('click', function () { dAbre(doc.signer_id, b); });

      alvo.appendChild(b);
    });

    mostra('tela-d-lista');
    dReiniciaOcioso();
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
