{{--
    Validação de contratos pela Gerência no tablet do balcão.

    Página própria (não é a tela de assinatura): entrada por matrícula + PIN,
    lista dos contratos em validação, leitura com "o que mudou", observação
    por cláusula TOCANDO nela, e Aprovar / Devolver. Ajustar o texto é no
    computador. O texto do contrato vem pronto do servidor
    (ContractRenderer), como na tela web — aqui nada é montado.

    O tablet não guarda nada: as observações em andamento ficam só na
    memória da página, e a entrada cai sozinha depois de um tempo parado.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="robots" content="noindex, nofollow">
  <title>Validação de contratos — Gerência</title>
  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800|unbounded:500,600,700&display=swap" rel="stylesheet" />
  @include('signature.contracts.partials.styles')
  <style>
  /* A paleta do quiosque (quiosque/index.blade.php). */
  :root{
    --bg:#f5f3f3; --surface:#ffffff; --surface-2:#faf8f8; --surface-3:#eeeaea;
    --border:#e3ddde; --border-strong:#d0c7c9;
    --ink:#1e1215; --ink-2:#5a4a4e; --ink-3:#8c7c80;
    --brand:#8a1538; --brand-strong:#6f0f2c; --brand-tint:#f6e3e9; --on-brand:#fff;
    --success:#147a45; --success-tint:#ddf3e6;
    --warning:#935700; --warning-tint:#fbefd8;
    --danger:#c22b2b; --danger-tint:#fce4e4;
    --shadow: 0 1px 2px rgba(31,24,25,.04), 0 8px 24px -12px rgba(31,24,25,.18);
    --shadow-lg: 0 24px 60px -24px rgba(31,24,25,.45);
    --r:18px; --tap:64px;
    --sans: "Figtree", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
    --display: "Unbounded", var(--sans);
  }
  *{box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
  html,body{margin:0;padding:0;height:100%;overscroll-behavior:none;}
  body{font-family:var(--sans);background:var(--bg);color:var(--ink);-webkit-font-smoothing:antialiased;}
  button,input,textarea{font-family:inherit;}
  .app{display:flex;flex-direction:column;height:100dvh;max-width:900px;margin:0 auto;background:var(--surface);box-shadow:var(--shadow-lg);overflow:hidden;}
  .topbar{flex:0 0 auto;display:flex;align-items:center;gap:12px;padding:14px 18px;border-bottom:1px solid var(--border);}
  .brand-dot{width:40px;height:40px;border-radius:12px;background:var(--brand);color:var(--on-brand);display:grid;place-items:center;font-weight:800;}
  .who{flex:1 1 auto;min-width:0;}
  .who b{display:block;font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .who span{font-size:12.5px;color:var(--ink-2);}
  .screen{flex:1 1 auto;display:none;flex-direction:column;min-height:0;}
  .screen.on{display:flex;}
  .scroll{flex:1 1 auto;overflow-y:auto;-webkit-overflow-scrolling:touch;padding:18px;}
  .foot{flex:0 0 auto;display:flex;gap:12px;padding:14px 18px calc(14px + env(safe-area-inset-bottom,0px));border-top:1px solid var(--border);background:var(--surface);}
  h1,h2{font-family:var(--display);font-weight:600;letter-spacing:-.01em;margin:0 0 8px;}
  h1{font-size:22px;} h2{font-size:17px;}
  .lead{font-size:16px;color:var(--ink-2);line-height:1.5;margin:0 0 16px;}
  .btn{flex:1 1 auto;min-height:var(--tap);border:none;border-radius:var(--r);font-size:17px;font-weight:800;cursor:pointer;padding:0 18px;}
  .btn:active{transform:scale(.985);}
  .btn[disabled]{opacity:.4;cursor:not-allowed;}
  .btn-primary{background:var(--brand);color:var(--on-brand);}
  .btn-ok{background:var(--success);color:#fff;}
  .btn-ghost{background:var(--surface-2);color:var(--ink-2);border:1px solid var(--border-strong);flex:0 0 auto;}
  .btn-small{min-height:44px;font-size:14px;border-radius:999px;flex:0 0 auto;}
  .field{width:100%;min-height:60px;border-radius:var(--r);border:2px solid var(--border-strong);background:var(--surface-2);font-size:20px;padding:12px 16px;color:var(--ink);}
  .field:focus{outline:none;border-color:var(--brand);}
  textarea.field{min-height:140px;font-size:17px;line-height:1.45;resize:vertical;}
  label.lbl{display:block;font-weight:700;margin:14px 0 8px;}
  .err{color:var(--danger);font-weight:600;font-size:15px;margin-top:12px;}
  .center{max-width:420px;margin:0 auto;padding-top:8vh;}
  .card{display:block;width:100%;text-align:left;border:1px solid var(--border-strong);background:var(--surface-2);border-radius:var(--r);padding:16px 18px;margin-bottom:12px;cursor:pointer;color:var(--ink);font-size:15px;}
  .card:active{background:var(--surface-3);}
  .card b{display:block;font-size:17px;margin-bottom:4px;}
  .meta{font-size:13.5px;color:var(--ink-2);}
  .tag{display:inline-block;font-size:12px;font-weight:700;border-radius:999px;padding:3px 10px;margin-right:6px;background:var(--brand-tint);color:var(--brand-strong);}
  .tag.warn{background:var(--warning-tint);color:var(--warning);}
  .box{border-radius:var(--r);padding:14px 16px;margin-bottom:14px;font-size:15px;line-height:1.5;}
  .box.warn{background:var(--warning-tint);}
  .box.danger{background:var(--danger-tint);}
  .box.soft{background:var(--surface-2);border:1px solid var(--border);}
  details.box summary{font-weight:700;cursor:pointer;min-height:32px;}
  .ct-paper{padding:20px 18px;border:1px solid var(--border);}
  .ct-paper .ct-clause{position:relative;padding:4px 8px 8px;margin:0 -8px;cursor:pointer;}
  .ct-paper .ct-clause:active{background:#faf3f5;}
  .ct-paper .ct-clause.commented{background:var(--warning-tint);box-shadow:inset 4px 0 0 var(--warning);}
  .ct-paper .ct-clause h3::after{content:"Tocar para comentar";float:right;font-size:11px;font-weight:600;color:var(--ink-3);}
  .ct-paper .ct-clause.commented h3::after{content:"Com observação";color:var(--warning);}
  .sheet-bg{position:fixed;inset:0;background:rgba(30,18,21,.45);display:none;z-index:20;}
  .sheet-bg.on{display:block;}
  .sheet{position:fixed;left:50%;transform:translateX(-50%);bottom:0;width:min(900px,100%);max-height:88dvh;overflow-y:auto;background:var(--surface);border-radius:24px 24px 0 0;padding:22px 20px calc(18px + env(safe-area-inset-bottom,0px));z-index:21;display:none;}
  .sheet.on{display:block;}
  .row{display:flex;gap:12px;margin-top:16px;}
  .toast{position:fixed;left:50%;top:18px;transform:translateX(-50%);background:var(--ink);color:#fff;padding:14px 20px;border-radius:14px;font-weight:700;z-index:30;display:none;max-width:90%;text-align:center;}
  .toast.on{display:block;}
  ul.plain{margin:6px 0 0;padding-left:20px;}
  </style>
</head>
<body>
<div class="app">
  <div class="topbar">
    <div class="brand-dot">CF</div>
    <div class="who"><b id="quem">Validação de contratos</b><span id="sub">Gerência</span></div>
    <button type="button" class="btn btn-ghost btn-small" id="btnSair" style="display:none;">Sair</button>
  </div>

  {{-- 1. Entrada --}}
  <section class="screen on" id="tela-entrar">
    <div class="scroll"><div class="center">
      <h1>Validação de contratos</h1>
      <p class="lead">Gerência: entre com a sua matrícula e o PIN do Lara.</p>
      <form id="formEntrar" autocomplete="off">
        <label class="lbl" for="matricula">Matrícula</label>
        <input class="field" id="matricula" inputmode="numeric" maxlength="20" required>
        <label class="lbl" for="pin">PIN</label>
        <input class="field" id="pin" type="password" inputmode="numeric" maxlength="20" required>
        <div class="err" id="erroEntrar"></div>
        <div class="row">
          <a class="btn btn-ghost" href="{{ $urls['kiosk'] }}" style="display:grid;place-items:center;text-decoration:none;">Voltar</a>
          <button type="submit" class="btn btn-primary" id="btnEntrar">Entrar</button>
        </div>
      </form>
    </div></div>
  </section>

  {{-- 2. Lista --}}
  <section class="screen" id="tela-lista">
    <div class="scroll">
      <h1>Aguardando validação</h1>
      <p class="lead" id="listaResumo"></p>
      <div id="lista"></div>
    </div>
    <div class="foot">
      <button type="button" class="btn btn-ghost" id="btnAtualizar">Atualizar</button>
    </div>
  </section>

  {{-- 3. Contrato --}}
  <section class="screen" id="tela-contrato">
    <div class="scroll" id="contratoScroll">
      <button type="button" class="btn btn-ghost btn-small" id="btnVoltar" style="margin-bottom:14px;">← Lista</button>
      <h1 id="cTitulo"></h1>
      <p class="meta" id="cMeta" style="margin:0 0 14px;"></p>
      <div id="cAvisos"></div>
      <div class="ct-paper" id="cTexto"></div>
    </div>
    <div class="foot">
      <button type="button" class="btn btn-ghost" id="btnDevolver">Devolver</button>
      <button type="button" class="btn btn-ok" id="btnAprovar">Aprovar</button>
    </div>
  </section>
</div>

<div class="sheet-bg" id="fundo"></div>

{{-- Observação numa cláusula --}}
<div class="sheet" id="folhaClausula">
  <h2 id="fcTitulo"></h2>
  <p class="meta">A observação vai para quem redigiu, junto da cláusula, quando você devolver.</p>
  <textarea class="field" id="fcTexto" maxlength="3000" placeholder="O que precisa mudar nesta cláusula?"></textarea>
  <div class="row">
    <button type="button" class="btn btn-ghost" data-fechar>Cancelar</button>
    <button type="button" class="btn btn-ghost" id="fcApagar">Apagar</button>
    <button type="button" class="btn btn-primary" id="fcSalvar">Guardar</button>
  </div>
</div>

{{-- Devolver --}}
<div class="sheet" id="folhaDevolver">
  <h2>Devolver para ajustes</h2>
  <div id="fdResumo"></div>
  <label class="lbl" for="fdNota">Observação geral</label>
  <textarea class="field" id="fdNota" maxlength="3000" placeholder="Opcional se já há observação nas cláusulas"></textarea>
  <div class="err" id="fdErro"></div>
  <div class="row">
    <button type="button" class="btn btn-ghost" data-fechar>Voltar</button>
    <button type="button" class="btn btn-primary" id="fdConfirmar">Devolver</button>
  </div>
</div>

{{-- Aprovar --}}
<div class="sheet" id="folhaAprovar">
  <h2>Aprovar o contrato?</h2>
  <p class="lead">O contrato ganha o número e o documento de assinatura é gerado em rascunho, para quem redigiu conferir e congelar.</p>
  <div id="faAviso"></div>
  <label class="lbl" for="faNota">Observação (opcional)</label>
  <textarea class="field" id="faNota" maxlength="3000"></textarea>
  <div class="err" id="faErro"></div>
  <div class="row">
    <button type="button" class="btn btn-ghost" data-fechar>Voltar</button>
    <button type="button" class="btn btn-ok" id="faConfirmar">Aprovar</button>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
(function () {
  var URLS = @json($urls);
  var OCIOSO = {{ (int) $idleSeconds }} * 1000;
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
      atual = c; obs = {}; clausulas = {};
      c.clauses.forEach(function (cl) { clausulas[cl.uid] = cl.label; });
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
      $('#contratoScroll').scrollTop = 0;
      tela('tela-contrato');
    }).catch(function (e) { if (e.message !== 'ended') { aviso(e.message); api(URLS.pending).then(mostraLista).catch(function () {}); } });
  }

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
    abrirFolha('#folhaAprovar');
  });

  $('#faConfirmar').addEventListener('click', function () {
    decide('approve', $('#faNota').value.trim(), $('#faConfirmar'), $('#faErro'));
  });

  function decide(decisao, nota, botao, erro) {
    botao.disabled = true;
    api(URLS.decide.replace('__ID__', atual.id), 'POST', { decision: decisao, note: nota || null, comments: decisao === 'return' ? obs : {} })
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
</script>
</body>
</html>
