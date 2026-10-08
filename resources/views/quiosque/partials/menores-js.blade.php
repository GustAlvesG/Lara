{{--
    JavaScript do autoatendimento do Termo de Menores. Incluído DENTRO do IIFE
    de quiosque/index.blade.php (usa $, api, mostra, erro, abreAtendimento,
    encerraAtendimento, iniciaLeitor, paraLeitor e o estado S de lá), só no
    modo "menores".

    Como no resto do quiosque, nada vai para localStorage/IndexedDB: o estado
    do atendimento (M) vive em memória e é zerado no início de cada um.

    Ganchos chamados pelo quiosque: menoresInicia (carga da página),
    menoresPareado (QR de pareamento lido), menoresTelaVerde (assinatura
    gravada) e menoresDepoisDoAtendimento (fim da sessão do documento).
--}}
@verbatim
  /* ---------------------------------------------------------------------
   | Termo de Menores — autoatendimento
   |---------------------------------------------------------------------*/

  var M = null;
  var mRotas = CFG.menores.rotas;
  var mUltimoToque = Date.now();
  var mTimerOcioso = null;
  var mTimerEstado = null;

  // Telas de antes do documento: inatividade nelas devolve o tablet ao início.
  var M_TELAS_OCIOSAS = ['tela-m-responsavel', 'tela-m-cpf', 'tela-m-dados-resp', 'tela-m-menores', 'tela-m-dados-menor'];

  function mLimpo() {
    return {
      termo: null,
      titulo: '',
      responsavel: null,
      cpf: '',
      lista: null,
      // O que faltava no cadastro e a pessoa digitou (null = pulou).
      respEmail: null,
      respRg: null,
      dadosRespVistos: false,
      menor: null,
      menorCpf: null,
      menorRg: null,
    };
  }

  function mTelaAtual() {
    var tela = document.querySelector('.screen.on');

    return tela ? tela.id : '';
  }

  /*
   * Falha de uma chamada do autoatendimento: tablet despareado volta ao
   * pareamento; atendimento encerrado (tentativas, prazo, termo trocado) volta
   * ao início com o aviso; o resto é mostrado onde a pessoa está.
   */
  function mFalha(e, alvo) {
    // 419 fora de sessão de documento = token CSRF vencido (o tablet fica
    // aberto o dia todo): recarregar traz um novo, e o pareamento continua.
    if (e && e.status === 419) {
      window.location.reload();
      return true;
    }

    if (e && e.despareado) {
      CFG.menores.pareado = false;
      mMostraPareamento(e.message);
      return true;
    }

    if (e && e.recomecar) {
      menoresInicio({ mensagem: e.message, titulo: 'Atendimento encerrado' });
      return true;
    }

    if (alvo) {
      alvo.textContent = e.message;
      alvo.classList.remove('hidden');
    } else {
      erro(e.message);
    }

    return false;
  }

  /* Pareamento: a tela de espera do quiosque, com o leitor de QR. */
  function mMostraPareamento(mensagem) {
    mParaTimers();
    M = mLimpo();

    $('#tela-espera h1').textContent = 'Parear este tablet';
    $('#tela-espera p.lead').textContent =
      'Aponte a câmera para o QR de pareamento exibido no computador (Assinaturas → Termo de Menores → Tablet).';
    $('#tituloDoc').textContent = 'Termo de Menores';
    $('#subtituloDoc').textContent = 'Tablet sem pareamento';
    $('#dicaLeitor').textContent = 'Procurando o código…';
    // Sem câmera (modo sem HTTPS): o código de pareamento digitado.
    $('#tela-codigo p.lead').textContent = 'Digite o código de pareamento exibido no computador. São 8 caracteres.';
    $('#btnCodigo').textContent = 'Parear';

    mostra('tela-espera');
    iniciaLeitor();

    if (mensagem) {
      erro(mensagem, 'Tablet sem pareamento');
    }
  }

  function menoresInicia() {
    if (!CFG.menores.pareado) {
      mMostraPareamento();
      return;
    }

    menoresInicio();
  }

  function menoresPareado() {
    CFG.menores.pareado = true;
    paraLeitor();
    menoresInicio();
  }

  /* O começo de todo atendimento: o número do título. */
  function menoresInicio(opcoes) {
    opcoes = opcoes || {};
    M = mLimpo();

    $('#mTitulo').value = '';
    $('#mCpf').value = '';
    $('#mEmail').value = '';
    $('#mRgResp').value = '';
    $('#mCpfMenor').value = '';
    $('#mRgMenor').value = '';
    $('#mAdultos').innerHTML = '';
    $('#mMenores').innerHTML = '';
    $('#vFoto').removeAttribute('src');
    ['#mErroTitulo', '#mErroCpf', '#mErroDadosMenor'].forEach(function (id) { $(id).classList.add('hidden'); });
    $('#mBtnTitulo').disabled = false;
    $('#tituloDoc').textContent = 'Termo de Menores';
    $('#relogio').classList.add('hidden');

    mostra('tela-m-inicio');
    mAtualizaEstado();
    mIniciaTimers();

    if (opcoes.mensagem) {
      erro(opcoes.mensagem, opcoes.titulo || 'Atenção');
    }
  }

  /* Termo do dia (e se o tablet continua pareado). */
  function mAtualizaEstado() {
    return api('GET', mRotas.estado)
      .then(function (r) {
        var temTermo = !!r.term;

        M.termo = temTermo ? r.term.name : null;
        $('#mEvento').textContent = temTermo ? r.term.name : '';
        $('#mEvento').classList.toggle('hidden', !temTermo);
        $('#mSemTermo').classList.toggle('hidden', temTermo);
        $('#mBoxTitulo').classList.toggle('hidden', !temTermo);
        $('#mBtnTitulo').classList.toggle('hidden', !temTermo);
        $('#subtituloDoc').textContent = temTermo ? r.term.name : CFG.clube;
      })
      .catch(function (e) { mFalha(e); });
  }

  function mIniciaTimers() {
    mParaTimers();

    // A tela inicial acompanha o termo do dia (virada do dia, termo novo).
    mTimerEstado = setInterval(function () {
      if (mTelaAtual() === 'tela-m-inicio' && $('#mTitulo').value === '') {
        mAtualizaEstado();
      }
    }, 60000);

    mTimerOcioso = setInterval(function () {
      var tela = mTelaAtual();
      var ocioso = Date.now() - mUltimoToque > CFG.menores.ociosoSegundos * 1000;
      var comTitulo = tela === 'tela-m-inicio' && $('#mTitulo').value !== '';

      if (ocioso && (M_TELAS_OCIOSAS.indexOf(tela) !== -1 || comTitulo)) {
        mEncerraFluxo();
        menoresInicio();
      }
    }, 5000);
  }

  function mParaTimers() {
    clearInterval(mTimerOcioso);
    clearInterval(mTimerEstado);
    mTimerOcioso = null;
    mTimerEstado = null;
  }

  ['pointerdown', 'keydown'].forEach(function (evento) {
    document.addEventListener(evento, function () { mUltimoToque = Date.now(); }, true);
  });

  // Esquece, no servidor, o atendimento deste tablet (título e responsável).
  function mEncerraFluxo() {
    api('POST', mRotas.encerrar).catch(function () {});
  }

  /* ---- Título ---- */

  function mEnviaTitulo() {
    var titulo = $('#mTitulo').value.trim();

    $('#mErroTitulo').classList.add('hidden');

    if (!titulo) {
      $('#mErroTitulo').textContent = 'Digite o número do título.';
      $('#mErroTitulo').classList.remove('hidden');
      return;
    }

    $('#mBtnTitulo').disabled = true;

    api('POST', mRotas.titulo, { title: titulo })
      .then(function (r) {
        M.titulo = r.title;
        M.termo = r.term;
        mMontaAdultos(r.adults);
        mostra('tela-m-responsavel');
      })
      .catch(function (e) { mFalha(e, $('#mErroTitulo')); })
      .then(function () { $('#mBtnTitulo').disabled = false; });
  }

  $('#mBtnTitulo').addEventListener('click', mEnviaTitulo);
  $('#mTitulo').addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter') { mEnviaTitulo(); }
  });

  document.querySelectorAll('[data-m-inicio]').forEach(function (b) {
    b.addEventListener('click', function () { mEncerraFluxo(); menoresInicio(); });
  });

  /* ---- Responsável ---- */

  // Montado por DOM, com textContent: os nomes vêm do cadastro.
  function mBotaoPessoa(nome, detalhe, selo) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'pessoa';

    var texto = document.createElement('span');
    texto.textContent = nome;

    if (detalhe) {
      var pequeno = document.createElement('small');
      pequeno.textContent = detalhe;
      texto.appendChild(pequeno);
    }

    b.appendChild(texto);

    if (selo) {
      var s = document.createElement('span');
      s.className = 'selo';
      s.textContent = selo;
      b.appendChild(s);
    }

    return b;
  }

  function mMontaAdultos(adultos) {
    var alvo = $('#mAdultos');
    alvo.innerHTML = '';

    adultos.forEach(function (pessoa) {
      var b = mBotaoPessoa(pessoa.name);

      b.addEventListener('click', function () {
        M.responsavel = pessoa;
        M.cpf = '';
        $('#mCpf').value = '';
        $('#mBtnCpf').disabled = true;
        $('#mErroCpf').classList.add('hidden');
        $('#mCpfInstrucao').textContent = pessoa.name + ', digite o seu CPF completo, apenas números.';
        mostra('tela-m-cpf');
      });

      alvo.appendChild(b);
    });
  }

  /* ---- CPF ---- */

  (function mMontaTeclado() {
    var alvo = $('#mTeclado');

    ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'limpar', '0', 'apagar'].forEach(function (t) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'key';
      b.textContent = t === 'apagar' ? '⌫' : (t === 'limpar' ? 'C' : t);
      b.dataset.tecla = t;
      alvo.appendChild(b);
    });

    alvo.addEventListener('click', function (ev) {
      var tecla = ev.target.dataset.tecla;

      if (!tecla || !M) {
        return;
      }

      if (tecla === 'limpar') {
        M.cpf = '';
      } else if (tecla === 'apagar') {
        M.cpf = M.cpf.slice(0, -1);
      } else if (M.cpf.length < 11) {
        M.cpf += tecla;
      }

      $('#mCpf').value = M.cpf;
      $('#mErroCpf').classList.add('hidden');
      $('#mBtnCpf').disabled = M.cpf.length !== 11;
    });
  })();

  $('#mVoltarResponsavel').addEventListener('click', function () {
    M.cpf = '';
    mostra('tela-m-responsavel');
  });

  $('#mBtnCpf').addEventListener('click', function () {
    $('#mBtnCpf').disabled = true;

    api('POST', mRotas.responsavel, { person: M.responsavel.id, cpf: M.cpf })
      .then(function (r) {
        M.cpf = '';
        $('#mCpf').value = '';
        M.lista = r;
        M.termo = r.term;

        var falta = r.responsible.missing || [];

        if (falta.length && !M.dadosRespVistos) {
          mAbreDadosResponsavel(falta);
          return;
        }

        mMontaMenores(r);
      })
      .catch(function (e) {
        M.cpf = '';
        $('#mCpf').value = '';
        mFalha(e, $('#mErroCpf'));
      });
  });

  /* ---- O que falta do responsável ---- */

  function mAbreDadosResponsavel(falta) {
    $('#mBoxEmail').classList.toggle('hidden', falta.indexOf('email') === -1);
    $('#mBoxRgResp').classList.toggle('hidden', falta.indexOf('rg') === -1);
    mostra('tela-m-dados-resp');
  }

  $('#mBtnDadosResp').addEventListener('click', function () {
    var email = $('#mEmail');

    if (email.value.trim() && !email.checkValidity()) {
      erro('O e-mail parece incompleto. Corrija ou toque em Pular.', 'Confira o e-mail');
      return;
    }

    M.respEmail = email.value.trim() || null;
    M.respRg = $('#mRgResp').value.trim() || null;
    M.dadosRespVistos = true;
    mMontaMenores(M.lista);
  });

  $('#mPularResp').addEventListener('click', function () {
    M.respEmail = null;
    M.respRg = null;
    M.dadosRespVistos = true;
    mMontaMenores(M.lista);
  });

  /* ---- Menores ---- */

  function mMontaMenores(r) {
    var alvo = $('#mMenores');
    alvo.innerHTML = '';

    $('#mSemMenores').classList.toggle('hidden', r.minors.length > 0);

    r.minors.forEach(function (menor) {
      var b = mBotaoPessoa(menor.name, menor.age + ' anos', menor.authorized ? 'Já autorizado ✓' : null);

      if (menor.authorized) {
        b.classList.add('feito');
      }

      b.addEventListener('click', function () {
        if (menor.authorized) {
          erro(menor.name + ' já está autorizado(a) neste evento. Está tudo certo.', 'Já autorizado');
          return;
        }

        M.menor = menor;
        M.menorCpf = null;
        M.menorRg = null;

        if ((menor.missing || []).length) {
          mAbreDadosMenor(menor);
          return;
        }

        mCriaDocumento();
      });

      alvo.appendChild(b);
    });

    mostra('tela-m-menores');
  }

  $('#mSair').addEventListener('click', function () {
    mEncerraFluxo();
    menoresInicio();
  });

  /* ---- O que falta do menor ---- */

  function mAbreDadosMenor(menor) {
    $('#mDadosMenorTitulo').textContent = 'Dados de ' + menor.name;
    $('#mBoxCpfMenor').classList.toggle('hidden', menor.missing.indexOf('cpf') === -1);
    $('#mBoxRgMenor').classList.toggle('hidden', menor.missing.indexOf('rg') === -1);
    $('#mCpfMenor').value = '';
    $('#mRgMenor').value = '';
    $('#mErroDadosMenor').classList.add('hidden');
    mostra('tela-m-dados-menor');
  }

  $('#mCpfMenor').addEventListener('input', function (ev) {
    ev.target.value = MASCARAS.cpf(ev.target.value);
  });

  $('#mVoltarMenores').addEventListener('click', function () {
    mostra('tela-m-menores');
  });

  $('#mBtnDadosMenor').addEventListener('click', function () {
    M.menorCpf = $('#mCpfMenor').value.trim() || null;
    M.menorRg = $('#mRgMenor').value.trim() || null;
    mCriaDocumento();
  });

  $('#mPularMenor').addEventListener('click', function () {
    M.menorCpf = null;
    M.menorRg = null;
    mCriaDocumento();
  });

  /* ---- Documento: gerado pelo servidor; daqui em diante é o quiosque ---- */

  var mGerando = false;

  function mCriaDocumento() {
    if (mGerando) {
      return;
    }

    mGerando = true;
    $('#mErroDadosMenor').classList.add('hidden');

    api('POST', mRotas.documento, {
      minor: M.menor.id,
      responsible_email: M.respEmail,
      responsible_rg: M.respRg,
      minor_cpf: M.menorCpf,
      minor_rg: M.menorRg,
    })
      .then(function (r) {
        if (r.already_authorized) {
          erro(M.menor.name + ' já está autorizado(a) neste evento. Está tudo certo.', 'Já autorizado');
          return mRecarregaMenores();
        }

        // O documento existe e a sessão do tablet está aberta (cookie
        // lara_sign): leitura, aceite, traço e foto são os do quiosque.
        abreAtendimento(r);
      })
      .catch(function (e) {
        var naTelaDeDados = mTelaAtual() === 'tela-m-dados-menor';
        mFalha(e, naTelaDeDados ? $('#mErroDadosMenor') : null);
      })
      .then(function () { mGerando = false; });
  }

  function mRecarregaMenores() {
    return api('GET', mRotas.lista)
      .then(function (r) { M.lista = r; mMontaMenores(r); })
      .catch(function (e) { mFalha(e); });
  }

  /* ---- Tela verde ---- */

  function menoresTelaVerde(dados) {
    var menor = (M && M.menor) || null;
    var responsavel = (M && M.responsavel) || null;

    $('#vMenor').textContent = menor ? menor.name : '';
    $('#vIdade').textContent = menor ? menor.age + ' anos' : '';
    $('#vResponsavel').textContent = responsavel ? responsavel.name : (S && S.signatario ? S.signatario.name : '');
    $('#vEvento').textContent = (M && M.termo) || '';
    $('#vHora').textContent = 'Assinado em ' + dados.signed_at;

    // A foto que acabou de ser tirada, da memória — não vai buscar no servidor.
    if (S && S.fotoJpeg) {
      $('#vFoto').src = S.fotoJpeg;
      $('#vFoto').classList.remove('hidden');
    } else {
      $('#vFoto').removeAttribute('src');
      $('#vFoto').classList.add('hidden');
    }

    $('#relogio').classList.add('hidden');
    mostra('tela-verde');
  }

  $('#vOutro').addEventListener('click', function () {
    encerraAtendimento({ proximo: 'lista' });
  });

  $('#vConcluir').addEventListener('click', function () {
    encerraAtendimento({ proximo: 'inicio' });
  });

  /*
   * Fim da sessão do documento (o quiosque já limpou o que era dele):
   * "Autorizar outro menor" volta à lista com o mesmo responsável; o resto —
   * Concluir, recusa, tempo esgotado — volta ao início e esquece o
   * atendimento.
   */
  function menoresDepoisDoAtendimento(opcoes) {
    opcoes = opcoes || {};

    $('#vFoto').removeAttribute('src');
    mUltimoToque = Date.now();

    if (opcoes.proximo === 'lista' && M && M.responsavel) {
      M.menor = null;
      M.menorCpf = null;
      M.menorRg = null;
      mRecarregaMenores();
      return;
    }

    mEncerraFluxo();
    menoresInicio(opcoes);
  }
@endverbatim
