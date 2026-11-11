/*
 | Tablet da Gerência (/assinatura/kiosk/gerencia) — validação de contratos.
 |
 | Saiu de dentro de resources/views/quiosque/gerencia.blade.php em 11/11/2026
 | e passou a ser entrada própria do Vite. O código é o mesmo; os três valores
 | que vinham do Blade (URLs, ociosidade e o mínimo de pontos do visto) chegam
 | por window.GERENCIA, definido no <head> antes deste módulo rodar. O quadro
 | do visto (window.laraInitialsPad) vem do partial
 | signature/contracts/partials/initials-pad, já no HTML quando isto roda.
 |
 | As classes que este arquivo monta estão em resources/css/quiosque-gerencia.css.
 */
(function () {
  // Dados do servidor: definidos no <head> da página (window.GERENCIA).
  var CFG = window.GERENCIA;
  var URLS = CFG.urls;
  var OCIOSO = CFG.ociosoSegundos * 1000;
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var $ = function (s) { return document.querySelector(s); };

  var atual = null;        // contrato aberto
  var obs = {};            // uid da cláusula => observação (só na memória)
  var clausulas = {};      // uid => rótulo
  var batimento = null;
  var parado = null;

  function esc(t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; }

  function tela(id) {
    document.querySelectorAll('.screen').forEach(function (s) { s.classList.toggle('on', s.id === id); });
    $('#btnSair').style.display = id === 'tela-entrar' ? 'none' : '';
  }

  function api(url, metodo, corpo) {
    return fetch(url, {
      method: metodo || 'GET',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: corpo ? JSON.stringify(corpo) : undefined
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (r.status === 401 && j.ended) { encerra(j.error); throw new Error('ended'); }
        if (r.status === 419) { location.reload(); throw new Error('csrf'); }
        if (!r.ok) { var e = new Error(j.error || (j.message) || 'Falha na comunicação com o servidor (código ' + r.status + ').'); e.dados = j; throw e; }
        return j;
      });
    }, function () { throw new Error('Sem resposta do servidor. Confira a rede do tablet.'); });
  }

  function aviso(texto) {
    var t = $('#toast');
    t.textContent = texto;
    t.classList.add('on');
    setTimeout(function () { t.classList.remove('on'); }, 3500);
  }

  function fecharFolhas() {
    document.querySelectorAll('.sheet, .sheet-bg').forEach(function (el) { el.classList.remove('on'); });
  }
  function abrirFolha(id) { fecharFolhas(); $('#fundo').classList.add('on'); $(id).classList.add('on'); }

  // ---- Entrada e saída ----
  $('#formEntrar').addEventListener('submit', function (e) {
    e.preventDefault();
    $('#erroEntrar').textContent = '';
    $('#btnEntrar').disabled = true;
    api(URLS.login, 'POST', { matricula: $('#matricula').value, pin: $('#pin').value })
      .then(function (j) { $('#pin').value = ''; mostraLista(j); comecaBatimento(); })
      .catch(function (e) { if (e.message !== 'ended') $('#erroEntrar').textContent = e.message; })
      .then(function () { $('#btnEntrar').disabled = false; });
  });

  function encerra(mensagem) {
    clearInterval(batimento);
    atual = null; obs = {};
    fecharFolhas();
    $('#quem').textContent = 'Validação de contratos';
    $('#sub').textContent = 'Gerência';
    $('#matricula').value = ''; $('#pin').value = '';
    $('#erroEntrar').textContent = mensagem || '';
    tela('tela-entrar');
  }

  $('#btnSair').addEventListener('click', function () {
    if (atual && Object.keys(obs).length && !confirm('Sair? As observações ainda não enviadas se perdem.')) return;
    api(URLS.logout, 'POST').catch(function () {}).then(function () { encerra(''); });
  });

  function comecaBatimento() {
    clearInterval(batimento);
    // Mantém a entrada viva enquanto alguém usa, e atualiza a lista.
    batimento = setInterval(function () {
      api(URLS.pending).then(function (j) { if ($('#tela-lista').classList.contains('on')) mostraLista(j); }).catch(function () {});
    }, 60000);
  }

  // Parado por muito tempo: a entrada cai (o tablet fica no balcão).
  function mexeu() {
    clearTimeout(parado);
    parado = setTimeout(function () {
      if (!$('#tela-entrar').classList.contains('on')) {
        api(URLS.logout, 'POST').catch(function () {}).then(function () { encerra('A entrada foi encerrada por falta de uso.'); });
      }
    }, OCIOSO);
  }
  ['touchstart', 'click', 'keydown', 'scroll'].forEach(function (ev) { document.addEventListener(ev, mexeu, { passive: true, capture: true }); });
  mexeu();

  // ---- Lista ----
  function mostraLista(j) {
    $('#quem').textContent = j.user.name;
    $('#sub').textContent = 'Gerência — validação de contratos';
    var lista = j.contracts || [];
    $('#listaResumo').textContent = lista.length
      ? lista.length + (lista.length === 1 ? ' contrato aguardando.' : ' contratos aguardando.') + ' Toque para ler.'
      : '';
    $('#lista').innerHTML = lista.length ? lista.map(function (c) {
      return '<button type="button" class="card" data-abrir="' + c.id + '">'
        + '<b>' + esc(c.title) + '</b>'
        + (c.counterparty ? esc(c.counterparty) + '<br>' : '')
        + '<span class="meta">Setor ' + esc(c.sector || '—') + ' · ' + esc(c.author || '—') + ' · enviado ' + esc(c.submitted_at || '') + '</span><br>'
        + (c.resubmission ? '<span class="tag warn">Reenvio (versão ' + c.version + ')</span>' : '')
        + (c.addendum ? '<span class="tag">Aditivo</span>' : '')
        + '</button>';
    }).join('') : '<div class="box soft">Nenhum contrato aguardando validação.</div>';
    tela('tela-lista');
  }

  $('#btnAtualizar').addEventListener('click', function () { api(URLS.pending).then(mostraLista).catch(function (e) { if (e.message !== 'ended') aviso(e.message); }); });

  $('#lista').addEventListener('click', function (e) {
    var b = e.target.closest('[data-abrir]');
    if (b) abre(b.dataset.abrir);
  });

  // ---- Contrato ----
  function abre(id) {
    api(URLS.show.replace('__ID__', id)).then(function (c) {
      obs = {};
      mostraContrato(c);
      $('#contratoScroll').scrollTop = 0;
      tela('tela-contrato');
    }).catch(function (e) { if (e.message !== 'ended') { aviso(e.message); api(URLS.pending).then(mostraLista).catch(function () {}); } });
  }

  // Desenha o contrato. As observações (obs) ficam: são por uid de cláusula,
  // que editar o texto não muda.
  function mostraContrato(c) {
    {
      atual = c; clausulas = {};
      c.clauses.forEach(function (cl) { clausulas[cl.uid] = cl.label; });
      Object.keys(obs).forEach(function (u) { if (!clausulas[u]) delete obs[u]; });
      $('#cTitulo').textContent = c.title;
      $('#cMeta').textContent = [c.counterparty, 'Setor ' + (c.sector || '—'), 'por ' + (c.author || '—'), 'versão ' + c.version].filter(Boolean).join(' · ');

      var avisos = '';
      if (c.note) avisos += '<div class="box soft"><b>Recado de quem enviou:</b><br>' + esc(c.note) + '</div>';
      if (c.errors.length) {
        avisos += '<div class="box danger"><b>Não dá para aprovar ainda:</b><ul class="plain">'
          + c.errors.map(function (m) { return '<li>' + esc(m) + '</li>'; }).join('') + '</ul></div>';
      }
      if (c.diff) {
        var linhas = c.diff.fields.map(function (f) { return '<li>' + esc(f) + '</li>'; }).join('')
          + c.diff.removed.map(function (t) { return '<li><del>Cláusula removida: ' + esc(t) + '</del></li>'; }).join('')
          + c.diff.clauses.map(function (cl) {
              return '<li><b>Cláusula ' + cl.number + ' – ' + esc(cl.title) + '</b>' + (cl.status === 'added' ? ' (nova)' : '')
                + cl.items.map(function (it) { return '<div>' + it.html + '</div>'; }).join('') + '</li>';
            }).join('');
        avisos += '<details class="box warn ct-diff" open><summary>O que mudou desde a versão ' + c.diff.since + ' (devolvida)</summary><ul class="plain">' + linhas + '</ul></details>';
      }
      if (c.attachments.length) {
        avisos += '<div class="box soft"><b>Anexos</b><ul class="plain">' + c.attachments.map(function (a) {
          return '<li><a target="_blank" rel="noopener" href="' + URLS.attachment.replace('__ID__', c.id).replace('__ANEXO__', a.id) + '">' + esc(a.label) + '</a></li>';
        }).join('') + '</ul></div>';
      }
      $('#cAvisos').innerHTML = avisos;
      $('#cTexto').innerHTML = c.html;
      $('#btnAprovar').disabled = c.errors.length > 0;
      $('#btnIA').style.display = c.ai && c.ai.enabled ? '' : 'none';
      if (c.ai && c.ai.review) {
        $('#btnIA').textContent = 'Revisão do Claude · ' + c.ai.review.verdict
        + (c.ai.review.legal && c.ai.review.legal.seek_lawyer ? ' · risco jurídico alto' : '')
        + (c.ai.review.current ? '' : ' (texto mudou)');
      } else {
        $('#btnIA').textContent = 'Revisão do Claude';
      }
      marcaClausulas();
    }
  }

  // ---- Revisão pelo Claude ----
  var GRAVIDADE = { alta: 'Grave', media: 'Atenção', baixa: 'Detalhe' };

  function trechoExtra(n) {
    var lim = atual.ai.limits;
    return '<div class="box soft" data-extra>'
      + '<input class="field small" data-extra-title maxlength="' + lim.title + '" placeholder="Título (ex.: Proposta comercial)">'
      + '<textarea class="field" data-extra-text maxlength="' + lim.text + '" placeholder="Cole aqui o trecho" style="margin-top:8px;"></textarea>'
      + '</div>';
  }

  function mostraIA() {
    var ai = atual.ai, r = ai.review, h = '';
    if (r) {
      var kind = { ok: 'ok', warn: 'warn', danger: 'danger' }[r.kind] || '';
      h += '<div class="box soft"><span class="pill ' + kind + '">' + esc(r.verdict) + '</span> '
        + '<span class="meta">pedida por ' + esc(r.by || '—') + ' em ' + esc(r.at || '') + '</span>'
        + (r.current ? '' : '<div class="meta" style="color:var(--warning);font-weight:700;">O texto mudou depois desta revisão.</div>')
        + '<p style="white-space:pre-line;margin:10px 0 0;">' + esc(r.summary) + '</p>'
        + (r.withheld.length ? '<p class="meta"><b>Não enviados:</b> ' + esc(r.withheld.join('; ')) + '.</p>' : '')
        + (r.extras.length ? '<p class="meta"><b>Trechos anexados:</b> ' + esc(r.extras.join('; ')) + '.</p>' : '')
        + '</div>';
      // Revisão jurídica (04/11/2026): risco, fraquezas e, no risco alto, procurar o jurídico.
      if (r.legal) {
        var lk = { ok: 'ok', warn: 'warn', danger: 'danger' }[r.legal.kind] || '';
        h += '<div class="box ' + (r.legal.seek_lawyer ? 'danger' : 'soft') + '"><b>Revisão jurídica</b> <span class="pill ' + lk + '">' + esc(r.legal.label) + '</span>'
          + (r.legal.seek_lawyer ? '<p style="margin:10px 0 0;font-weight:800;color:var(--danger);">Procure um jurídico especializado antes de aprovar este contrato.</p>' : '')
          + (r.legal.summary ? '<p style="white-space:pre-line;margin:10px 0 0;">' + esc(r.legal.summary) + '</p>' : '')
          + r.legal.weaknesses.map(function (f, i) {
              return '<div class="achado" style="background:var(--surface);margin-top:10px;">'
                + (f.clause > 0 ? '<div class="meta"><b>Cláusula ' + f.clause + '</b></div>' : '')
                + esc(f.point)
                + (f.suggestion ? '<div class="meta" style="margin-top:4px;"><b>Sugestão:</b> ' + esc(f.suggestion) + '</div>' : '')
                + '<button type="button" class="linkbtn" data-levar-j="' + i + '">Levar para a observação ' + (f.uid ? 'da cláusula' : 'geral') + '</button>'
                + '</div>';
            }).join('')
          + '<p class="meta">Orientação automática, não é parecer jurídico.</p></div>';
      }
      r.findings.forEach(function (a, i) {
        h += '<div class="achado"><div class="meta"><b>' + esc(GRAVIDADE[a.severity] || a.severity) + '</b>'
          + (a.clause > 0 ? ' · Cláusula ' + a.clause : '') + (a.where ? ' · ' + esc(a.where) : '') + '</div>'
          + esc(a.problem)
          + (a.suggestion ? '<div class="meta" style="margin-top:4px;"><b>Sugestão:</b> ' + esc(a.suggestion) + '</div>' : '')
          + '<button type="button" class="linkbtn" data-levar="' + i + '">Levar para a observação ' + (a.uid ? 'da cláusula' : 'geral') + '</button>'
          + '</div>';
      });
    } else {
      h += '<p class="lead">O Claude lê o contrato inteiro e aponta inconsistências (partes, valores por extenso, prazos, remissões, cláusulas contraditórias ou faltando), com sugestão de alteração.</p>';
    }
    $('#iaUltima').innerHTML = h;

    $('#iaSensiveis').innerHTML = ai.sensitive.length
      ? '<div class="box soft"><b>Trechos com dados sensíveis — ficam de fora, a menos que você marque</b>'
        + ai.sensitive.map(function (t) {
            return '<label class="check"><input type="checkbox" value="' + esc(t.key) + '" data-incluir><span>' + esc(t.label) + ' <span class="meta">(' + esc(t.reasons) + ')</span></span></label>';
          }).join('') + '</div>'
      : '';

    $('#iaExtrasAjuda').textContent = 'Cole um texto de referência (proposta do fornecedor, contrato anterior, orientação): o Claude confere a minuta contra ele. Até '
      + ai.limits.extras + ' trechos. Esse texto também vai à Anthropic.';
    $('#iaExtras').innerHTML = trechoExtra(0);
    $('#iaMaisTrecho').disabled = false;
    $('#iaErro').textContent = '';
    $('#iaAnalisar').textContent = r ? (r.current ? 'Analisar de novo' : 'Analisar o texto atual') : 'Analisar com o Claude';
  }

  $('#btnIA').addEventListener('click', function () { mostraIA(); abrirFolha('#folhaIA'); });

  $('#iaMaisTrecho').addEventListener('click', function () {
    var n = document.querySelectorAll('#iaExtras [data-extra]').length;
    if (n >= atual.ai.limits.extras) return;
    $('#iaExtras').insertAdjacentHTML('beforeend', trechoExtra(n));
    if (n + 1 >= atual.ai.limits.extras) $('#iaMaisTrecho').disabled = true;
  });

  $('#iaUltima').addEventListener('click', function (e) {
    var b = e.target.closest('[data-levar], [data-levar-j]');
    if (!b) return;
    var a;
    if (b.dataset.levarJ !== undefined) {
      var f = atual.ai.review.legal.weaknesses[+b.dataset.levarJ];
      a = { uid: f.uid, problem: 'Jurídico: ' + f.point, suggestion: f.suggestion };
    } else {
      a = atual.ai.review.findings[+b.dataset.levar];
    }
    var texto = a.problem + (a.suggestion ? ' Sugestão: ' + a.suggestion : '');
    if (a.uid && clausulas[a.uid]) {
      obs[a.uid] = (obs[a.uid] ? obs[a.uid] + '\n' : '') + texto;
      marcaClausulas();
    } else {
      var nota = $('#fdNota');
      nota.value = (nota.value.trim() ? nota.value.trim() + '\n' : '') + texto;
    }
    b.textContent = 'Levado ✓';
    b.disabled = true;
  });

  $('#iaAnalisar').addEventListener('click', function () {
    var botao = $('#iaAnalisar');
    var incluir = Array.prototype.map.call(document.querySelectorAll('#iaSensiveis [data-incluir]:checked'), function (el) { return el.value; });
    var extras = Array.prototype.map.call(document.querySelectorAll('#iaExtras [data-extra]'), function (el) {
      return { title: el.querySelector('[data-extra-title]').value, text: el.querySelector('[data-extra-text]').value };
    }).filter(function (x) { return x.text.trim() !== ''; });

    botao.disabled = true;
    botao.textContent = 'Analisando… até um minuto';
    $('#iaErro').textContent = '';
    api(URLS.aiReview.replace('__ID__', atual.id), 'POST', { include: incluir, extras: extras })
      .then(function (j) {
        atual.ai = j.ai;
        mostraContrato(atual);
        mostraIA();
        aviso(j.message);
        $('#folhaIA').scrollTop = 0;
      })
      .catch(function (e) { if (e.message !== 'ended') $('#iaErro').textContent = e.message; })
      .then(function () { botao.disabled = false; if (botao.textContent.indexOf('Analisando') === 0) botao.textContent = 'Analisar com o Claude'; });
  });

  // ---- Editar o texto ----
  function itemEd(it) {
    return '<div class="ed-item' + (it.level === 2 ? ' n2' : '') + '" data-item data-uid="' + esc(it.uid || '') + '">'
      + '<div class="ed-texto" contenteditable="true">' + (it.html || '') + '</div>'
      + '<div class="ed-lado">'
      + '<select data-nivel><option value="1"' + (it.level === 2 ? '' : ' selected') + '>Item</option><option value="2"' + (it.level === 2 ? ' selected' : '') + '>Subitem</option></select>'
      + '<button type="button" data-tirar>Tirar</button>'
      + '</div></div>';
  }

  // Salvar automático do texto (05/11/2026): a revisão que o tablet abriu.
  var edRevisao = 0;
  var edEspera = null;
  var edConflito = false;
  var edSalvando = false;

  function mostraEditar() {
    var ed = atual.edit;
    edRevisao = ed.revision || 0;
    edConflito = false;
    $('#edForcar').classList.add('hidden');
    $('#edEstado').textContent = 'Salvo automaticamente enquanto você digita.';
    $('#edTitulo').value = ed.title;
    $('#edClausulas').innerHTML = ed.clauses.map(function (c) {
      return '<div class="ed-clausula" data-clausula="' + esc(c.uid) + '">'
        + '<label class="lbl" style="margin-top:0;">Cláusula ' + c.number + '</label>'
        + '<input class="field small" data-titulo maxlength="200" value="' + esc(c.title) + '" placeholder="Título da cláusula">'
        + '<div data-itens>' + c.items.map(itemEd).join('') + '</div>'
        + '<button type="button" class="linkbtn" data-mais-item>+ Item</button>'
        + '</div>';
    }).join('');
    $('#edErro').textContent = '';
  }

  $('#btnEditar').addEventListener('click', function () { mostraEditar(); abrirFolha('#folhaEditar'); });

  // Os botões de formatação não podem tirar o foco do texto.
  document.querySelectorAll('.ed-barra [data-cmd]').forEach(function (b) {
    b.addEventListener('mousedown', function (e) { e.preventDefault(); });
    b.addEventListener('touchstart', function (e) { e.preventDefault(); document.execCommand(b.dataset.cmd, false, null); }, { passive: false });
    b.addEventListener('click', function () { document.execCommand(b.dataset.cmd, false, null); });
  });

  $('#edClausulas').addEventListener('click', function (e) {
    if (e.target.closest('[data-tirar]')) {
      var item = e.target.closest('[data-item]');
      var lista = item.parentNode;
      if (lista.querySelectorAll('[data-item]').length > 1) item.remove();
      else aviso('A cláusula precisa de pelo menos um item.');
      return;
    }
    if (e.target.closest('[data-mais-item]')) {
      var bloco = e.target.closest('[data-clausula]').querySelector('[data-itens]');
      bloco.insertAdjacentHTML('beforeend', itemEd({ uid: '', level: 1, html: '' }));
      bloco.lastElementChild.querySelector('.ed-texto').focus();
    }
  });

  $('#edClausulas').addEventListener('change', function (e) {
    if (e.target.matches('[data-nivel]')) e.target.closest('[data-item]').classList.toggle('n2', e.target.value === '2');
  });

  // Colar: só o texto (o servidor saneia de novo).
  $('#edClausulas').addEventListener('paste', function (e) {
    if (!e.target.closest('.ed-texto')) return;
    e.preventDefault();
    document.execCommand('insertText', false, (e.clipboardData || window.clipboardData).getData('text/plain'));
  });

  function corpoDoTexto() {
    return {
      title: $('#edTitulo').value,
      clauses: Array.prototype.map.call(document.querySelectorAll('#edClausulas [data-clausula]'), function (c) {
        return {
          uid: c.dataset.clausula,
          title: c.querySelector('[data-titulo]').value,
          items: Array.prototype.map.call(c.querySelectorAll('[data-item]'), function (it) {
            return { uid: it.dataset.uid || null, level: +it.querySelector('[data-nivel]').value, html: it.querySelector('.ed-texto').innerHTML };
          })
        };
      })
    };
  }

  // Os itens novos ganham uid no servidor: guardar evita uid novo a cada gravação.
  function guardaUids(edit) {
    (edit.clauses || []).forEach(function (c) {
      var bloco = document.querySelector('#edClausulas [data-clausula="' + c.uid + '"]');
      if (!bloco) return;
      var itens = bloco.querySelectorAll('[data-item]');
      (c.items || []).forEach(function (it, i) {
        if (itens[i] && !itens[i].dataset.uid && it.uid) itens[i].dataset.uid = it.uid;
      });
    });
  }

  // auto: grava e continua na folha; forca: grava por cima depois do aviso de conflito.
  function salvaTexto(auto, forca) {
    if (!atual || (edConflito && !forca)) return Promise.resolve();
    if (edSalvando) {
      clearTimeout(edEspera);
      edEspera = setTimeout(function () { salvaTexto(auto, forca); }, 1000);
      return Promise.resolve();
    }
    var corpo = corpoDoTexto();
    corpo.revision = edRevisao;
    corpo.auto = auto;
    if (forca) corpo.force = true;
    edSalvando = true;
    $('#edErro').textContent = '';
    $('#edEstado').textContent = 'Salvando…';
    return api(URLS.update.replace('__ID__', atual.id), 'POST', corpo)
      .then(function (j) {
        edRevisao = j.contract.edit.revision;
        edConflito = false;
        $('#edForcar').classList.add('hidden');
        $('#edEstado').textContent = 'Salvo automaticamente às ' + j.saved_at + '.';
        if (auto) {
          guardaUids(j.contract.edit);
          // A leitura atrás da folha acompanha o texto salvo.
          mostraContrato(j.contract);
          edPendente = false;
        } else {
          fecharFolhas();
          mostraContrato(j.contract);
          aviso(j.message);
        }
      })
      .catch(function (e) {
        if (e.message === 'ended') return;
        $('#edErro').textContent = e.message;
        $('#edEstado').textContent = 'Não salvo.';
        if (e.dados && e.dados.conflict) {
          edConflito = true;
          $('#edForcar').classList.remove('hidden');
        }
      })
      .then(function () { edSalvando = false; });
  }

  var edPendente = false;
  $('#folhaEditar').addEventListener('input', function () {
    if (edConflito) return;
    edPendente = true;
    $('#edEstado').textContent = 'Alterações ainda não salvas…';
    clearTimeout(edEspera);
    edEspera = setTimeout(function () { salvaTexto(true, false); }, 3000);
  });

  // Fechar a folha com algo por salvar: grava antes (o tablet não pergunta).
  $('#folhaEditar [data-fechar]').addEventListener('click', function () {
    if (!edPendente || edConflito) return;
    clearTimeout(edEspera);
    salvaTexto(true, false);
  });

  $('#edForcar').addEventListener('click', function () {
    if (!confirm('Gravar o seu texto por cima do que a outra pessoa salvou?')) return;
    salvaTexto(true, true);
  });

  $('#edSalvar').addEventListener('click', function () {
    var botao = $('#edSalvar');
    clearTimeout(edEspera);
    botao.disabled = true;
    salvaTexto(false, false).then(function () { botao.disabled = false; });
  });

  $('#btnVoltar').addEventListener('click', function () {
    if (Object.keys(obs).length && !confirm('Voltar à lista? As observações ainda não enviadas se perdem.')) return;
    atual = null; obs = {};
    api(URLS.pending).then(mostraLista).catch(function () {});
  });

  // Tocar numa cláusula abre a observação dela.
  var uidAberto = null;
  $('#cTexto').addEventListener('click', function (e) {
    if (e.target.closest('a')) return;
    var sec = e.target.closest('[data-clause]');
    if (!sec) return;
    uidAberto = sec.dataset.clause;
    $('#fcTitulo').textContent = clausulas[uidAberto] || 'Cláusula';
    $('#fcTexto').value = obs[uidAberto] || '';
    abrirFolha('#folhaClausula');
    setTimeout(function () { $('#fcTexto').focus(); }, 50);
  });

  function marcaClausulas() {
    document.querySelectorAll('#cTexto [data-clause]').forEach(function (sec) {
      sec.classList.toggle('commented', !!obs[sec.dataset.clause]);
    });
  }

  $('#fcSalvar').addEventListener('click', function () {
    var t = $('#fcTexto').value.trim();
    if (t) obs[uidAberto] = t; else delete obs[uidAberto];
    marcaClausulas(); fecharFolhas();
  });
  $('#fcApagar').addEventListener('click', function () { delete obs[uidAberto]; marcaClausulas(); fecharFolhas(); });

  document.querySelectorAll('[data-fechar]').forEach(function (b) { b.addEventListener('click', fecharFolhas); });
  $('#fundo').addEventListener('click', fecharFolhas);

  // ---- Decidir ----
  $('#btnDevolver').addEventListener('click', function () {
    var uids = Object.keys(obs);
    $('#fdResumo').innerHTML = uids.length
      ? '<div class="box warn"><b>' + uids.length + (uids.length === 1 ? ' observação' : ' observações') + ' nas cláusulas:</b><ul class="plain">'
        + uids.map(function (u) { return '<li><b>' + esc(clausulas[u]) + ':</b> ' + esc(obs[u]) + '</li>'; }).join('') + '</ul></div>'
      : '<p class="meta">Nenhuma observação em cláusula. Para comentar uma cláusula, toque nela no texto.</p>';
    $('#fdErro').textContent = '';
    abrirFolha('#folhaDevolver');
  });

  $('#fdConfirmar').addEventListener('click', function () {
    var nota = $('#fdNota').value.trim();
    if (!nota && !Object.keys(obs).length) { $('#fdErro').textContent = 'Escreva uma observação geral ou comente uma cláusula.'; return; }
    decide('return', nota, $('#fdConfirmar'), $('#fdErro'));
  });

  $('#btnAprovar').addEventListener('click', function () {
    $('#faAviso').innerHTML = Object.keys(obs).length
      ? '<div class="box warn">Você comentou ' + Object.keys(obs).length + ' cláusula(s). Aprovando, essas observações não são enviadas — para pedir mudança, use Devolver.</div>'
      : '';
    $('#faErro').textContent = '';
    window.laraInitialsPad($('#faVisto')).clear();
    abrirFolha('#folhaAprovar');
  });

  $('#faConfirmar').addEventListener('click', function () {
    var visto = window.laraInitialsPad($('#faVisto'));
    if (visto.points() < CFG.minPontosVisto) {
      $('#faErro').textContent = 'Faça o seu visto no quadro antes de aprovar.';
      return;
    }
    decide('approve', $('#faNota').value.trim(), $('#faConfirmar'), $('#faErro'), { initials: visto.data(), initials_strokes: visto.strokes() });
  });

  function decide(decisao, nota, botao, erro, extra) {
    botao.disabled = true;
    var corpo = { decision: decisao, note: nota || null, comments: decisao === 'return' ? obs : {} };
    Object.keys(extra || {}).forEach(function (k) { corpo[k] = extra[k]; });
    api(URLS.decide.replace('__ID__', atual.id), 'POST', corpo)
      .then(function (j) {
        fecharFolhas();
        $('#fdNota').value = ''; $('#faNota').value = '';
        atual = null; obs = {};
        aviso(j.message + (j.warnings && j.warnings.length ? ' ' + j.warnings.join(' ') : ''));
        return api(URLS.pending).then(mostraLista);
      })
      .catch(function (e) {
        if (e.message === 'ended') return;
        erro.textContent = e.message + (e.dados && e.dados.problems && e.dados.problems.length ? ' ' + e.dados.problems.join(' ') : '');
      })
      .then(function () { botao.disabled = false; });
  }

  // Entrada ainda valendo (recarregou a página): vai direto para a lista.
  // Sem entrada, a tela de matrícula abre limpa (sem o "a sua entrada terminou").
  api(URLS.pending).then(function (j) { mostraLista(j); comecaBatimento(); })
    .catch(function () { $('#erroEntrar').textContent = ''; });
})();
