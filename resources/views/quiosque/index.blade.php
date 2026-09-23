<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Assinatura de Documentos — CFCSN</title>
  <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />

  {{--
    Tela do tablet do balcão. Arquivo único, JavaScript próprio, sem build —
    o mesmo arranjo de resources/views/kiosk/index.blade.php, que é a outra
    tela de tablet deste projeto.

    NADA é guardado no aparelho: sem localStorage, sem IndexedDB. O estado vive
    em memória e é zerado ao fim de cada atendimento. Todo horário exibido vem
    do servidor.

    A câmera exige HTTPS (getUserMedia só funciona em origem segura). Ver o
    README do módulo.
  --}}
  <script>window.QUIOSQUE = {
    csrf: @json(csrf_token()),
    rotas: {
      consumir: @json(route('quiosque.consume')),
      sessao: @json(route('quiosque.session')),
      encerrar: @json(route('quiosque.leave')),
      // Os caminhos por documento são montados na hora, com o id que a leitura
      // do QR devolveu — o tablet nunca escolhe um id.
      visualizado: @json(route('quiosque.viewed', ['signatureDocument' => '__DOC__'])),
      identidade: @json(route('quiosque.identity', ['signatureDocument' => '__DOC__'])),
      assinar: @json(route('quiosque.sign', ['signatureDocument' => '__DOC__'])),
      recusar: @json(route('quiosque.refuse', ['signatureDocument' => '__DOC__'])),
    },
    prefixoQr: @json(\App\Models\SignatureRequest::TOKEN_PREFIX),
    minPontos: {{ (int) config('signature.evidence.min_stroke_points', 30) }},
    clube: 'Clube dos Funcionários da CSN',
    // Modo sem HTTPS: entrada por código digitado e conclusão sem foto quando
    // não há câmera. Ver o bloco "Modo sem HTTPS" em config/signature.php.
    codigoManual: {{ config('signature.manual_code.enabled') ? 'true' : 'false' }},
    podePularFoto: {{ config('signature.evidence.skip_photo_without_camera') ? 'true' : 'false' }},
    motivoSemCamera: @json(\App\Models\SignatureEvidence::PHOTO_SKIP_NO_CAMERA),
  };</script>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

@verbatim
  <style>
  :root{
    --bg:#efe9e8; --surface:#ffffff; --surface-2:#faf6f5; --surface-3:#f2ecea;
    --border:#e8dedd; --border-strong:#d8cbc9;
    --ink:#1f1819; --ink-2:#6d6062; --ink-3:#9a8e90;
    --brand:#A00001; --brand-strong:#7c0001; --brand-tint:#fbe9e8; --on-brand:#fff;
    --success:#157a58; --success-tint:#e0f2ea;
    --warning:#a3560a; --warning-tint:#fbedd9;
    --danger:#b3261e; --danger-tint:#fbe7e5;
    --paper:#fffefb; --paper-ink:#241f1a;
    --shadow: 0 1px 2px rgba(31,24,25,.04), 0 8px 24px -12px rgba(31,24,25,.18);
    --shadow-lg: 0 24px 60px -24px rgba(31,24,25,.45);
    --r:18px; --r-lg:26px; --tap:72px;
    --sans: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    --ease: cubic-bezier(.22,.61,.36,1);
  }
  *{box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
  html,body{margin:0;padding:0;height:100%;overscroll-behavior:none;}
  body{font-family:var(--sans);background:var(--bg);color:var(--ink);-webkit-font-smoothing:antialiased;}
  button{font-family:inherit;}

  .app{position:relative;display:flex;flex-direction:column;height:100dvh;max-width:820px;margin:0 auto;background:var(--surface);box-shadow:var(--shadow-lg);overflow:hidden;}

  .topbar{flex:0 0 auto;display:flex;align-items:center;gap:12px;padding:16px 20px;border-bottom:1px solid var(--border);background:var(--surface);z-index:5;}
  .brand-dot{width:40px;height:40px;border-radius:12px;flex:0 0 auto;background:var(--brand);color:var(--on-brand);display:grid;place-items:center;font-weight:800;font-size:16px;}
  .topbar .who{flex:1 1 auto;min-width:0;}
  .topbar .who b{font-size:15px;display:block;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .topbar .who span{font-size:12.5px;color:var(--ink-2);}
  .pill{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:700;font-variant-numeric:tabular-nums;padding:7px 12px;border-radius:999px;border:1px solid var(--border-strong);background:var(--surface-2);color:var(--ink-2);}
  .pill.warn{color:var(--warning);border-color:color-mix(in srgb,var(--warning) 35%, var(--border));background:var(--warning-tint);}

  .screens{position:relative;flex:1 1 auto;overflow:hidden;}
  .screen{position:absolute;inset:0;display:none;flex-direction:column;}
  .screen.on{display:flex;}

  .pad{padding:24px 22px;}
  .grow{flex:1 1 auto;overflow-y:auto;-webkit-overflow-scrolling:touch;}
  .foot{flex:0 0 auto;padding:16px 22px calc(16px + env(safe-area-inset-bottom,0px));border-top:1px solid var(--border);background:var(--surface);display:flex;gap:12px;}

  h1{font-size:26px;line-height:1.2;margin:0 0 8px;}
  h2{font-size:20px;line-height:1.25;margin:0 0 6px;}
  p.lead{font-size:16px;color:var(--ink-2);line-height:1.5;margin:0 0 18px;}

  .btn{flex:1 1 auto;min-height:var(--tap);border:none;border-radius:var(--r);font-size:18px;font-weight:800;cursor:pointer;display:grid;place-items:center;transition:transform .12s var(--ease),opacity .12s;}
  .btn:active{transform:scale(.985);}
  .btn[disabled]{opacity:.4;cursor:not-allowed;}
  .btn-primary{background:var(--brand);color:var(--on-brand);}
  .btn-ghost{background:var(--surface-2);color:var(--ink-2);border:1px solid var(--border-strong);flex:0 0 auto;padding:0 22px;}
  .btn-danger{background:transparent;color:var(--danger);border:1px solid color-mix(in srgb,var(--danger) 30%, var(--border));flex:0 0 auto;padding:0 20px;}

  /* Espera / leitor de QR */
  .wait{flex:1 1 auto;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:28px;}
  .wait .logo{width:96px;height:96px;border-radius:28px;background:var(--brand);color:var(--on-brand);display:grid;place-items:center;font-size:36px;font-weight:800;margin-bottom:22px;}
  #reader{width:min(78vw,340px);border-radius:var(--r-lg);overflow:hidden;border:2px solid var(--border-strong);background:#000;}
  #reader video{display:block;width:100%;}
  .wait .hint{margin-top:18px;font-size:16px;color:var(--ink-2);max-width:420px;line-height:1.5;}
  .wait .err{margin-top:14px;font-size:14px;color:var(--danger);max-width:420px;line-height:1.45;}

  /* Documento */
  .doc-scroll{flex:1 1 auto;overflow-y:auto;background:var(--surface-3);padding:16px;-webkit-overflow-scrolling:touch;}
  .doc-page{display:block;margin:0 auto 14px;background:#fff;box-shadow:var(--shadow);max-width:100%;}
  .doc-status{font-size:13px;color:var(--ink-2);padding:0 22px 10px;}

  /* Identidade */
  .cpf-box{display:flex;flex-direction:column;align-items:center;gap:16px;padding:10px 0 4px;}
  .cpf-input{width:min(90%,340px);height:78px;text-align:center;font-size:34px;font-weight:800;letter-spacing:10px;border-radius:var(--r);border:2px solid var(--border-strong);background:var(--surface-2);color:var(--ink);font-variant-numeric:tabular-nums;}
  .cpf-input:focus{outline:none;border-color:var(--brand);}
  .keypad{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;width:min(90%,340px);}
  .key{min-height:64px;border-radius:16px;border:1px solid var(--border-strong);background:var(--surface-2);font-size:24px;font-weight:700;color:var(--ink);cursor:pointer;}
  .key:active{background:var(--surface-3);}

  /* Aceite */
  .accept{display:flex;gap:14px;align-items:flex-start;padding:18px;border-radius:var(--r);border:2px solid var(--border-strong);background:var(--surface-2);cursor:pointer;}
  .accept.on{border-color:var(--brand);background:var(--brand-tint);}
  .accept input{width:30px;height:30px;flex:0 0 auto;accent-color:var(--brand);}
  .accept span{font-size:16px;line-height:1.45;}

  /* Assinatura */
  .sig-wrap{flex:1 1 auto;padding:16px 16px 0;display:flex;flex-direction:column;}
  .sig-pad{flex:1 1 auto;border-radius:var(--r);border:2px dashed var(--border-strong);background:var(--paper);position:relative;touch-action:none;overflow:hidden;}
  .sig-pad canvas{position:absolute;inset:0;width:100%;height:100%;touch-action:none;}
  .sig-line{position:absolute;left:8%;right:8%;bottom:28%;border-bottom:2px solid var(--border-strong);pointer-events:none;}
  .sig-label{position:absolute;left:8%;bottom:calc(28% - 26px);font-size:13px;color:var(--ink-3);pointer-events:none;}

  /* Foto */
  .cam-wrap{flex:1 1 auto;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;padding:22px;text-align:center;}
  .cam-view{width:min(82vw,380px);aspect-ratio:3/4;border-radius:var(--r-lg);overflow:hidden;background:#000;border:2px solid var(--border-strong);}
  .cam-view video{width:100%;height:100%;object-fit:cover;}
  .cam-count{font-size:42px;font-weight:800;color:var(--brand);font-variant-numeric:tabular-nums;}

  /* Sucesso */
  .done{flex:1 1 auto;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:28px;gap:14px;}
  .done .check{width:104px;height:104px;border-radius:50%;background:var(--success-tint);color:var(--success);display:grid;place-items:center;font-size:52px;font-weight:800;}

  .note{border-radius:var(--r);padding:14px 16px;font-size:14.5px;line-height:1.5;}
  .note-warn{background:var(--warning-tint);color:var(--warning);}
  .note-info{background:var(--surface-2);color:var(--ink-2);border:1px solid var(--border);}
  .note-danger{background:var(--danger-tint);color:var(--danger);}

  /* Diálogos */
  .sheet{position:absolute;inset:0;background:rgba(31,24,25,.55);display:none;align-items:center;justify-content:center;padding:24px;z-index:20;}
  .sheet.on{display:flex;}
  .sheet .card{background:var(--surface);border-radius:var(--r-lg);padding:24px;width:min(92%,460px);box-shadow:var(--shadow-lg);}
  .sheet h3{margin:0 0 8px;font-size:20px;}
  .sheet p{margin:0 0 16px;font-size:15px;color:var(--ink-2);line-height:1.5;}
  .sheet textarea{width:100%;min-height:96px;border-radius:14px;border:1px solid var(--border-strong);padding:12px;font-family:inherit;font-size:15px;background:var(--surface-2);color:var(--ink);}
  .sheet .row{display:flex;gap:10px;margin-top:16px;}

  .hidden{display:none !important;}
  </style>
</head>
<body>
<div class="app">

  <div class="topbar">
    <div class="brand-dot">CF</div>
    <div class="who">
      <b id="tituloDoc">Assinatura de documentos</b>
      <span id="subtituloDoc">Clube dos Funcionários da CSN</span>
    </div>
    <div class="pill hidden" id="relogio">--:--</div>
  </div>

  <div class="screens">

    <!-- 1. Espera -->
    <section class="screen on" id="tela-espera">
      <div class="wait">
        <div class="logo">CF</div>
        <h1>Assinatura de documentos</h1>
        <p class="lead">Aponte a câmera para o QR Code exibido pelo atendente.</p>
        <div id="reader"></div>
        <div class="hint" id="dicaLeitor">Procurando o código…</div>
        <div class="err hidden" id="erroLeitor"></div>
        <button type="button" class="btn btn-ghost hidden" id="trocarCamera" style="margin-top:16px;">Trocar de câmera</button>
        <button type="button" class="btn btn-ghost hidden" id="abrirCodigo" style="margin-top:12px;">Digitar código</button>
      </div>
    </section>

    <!-- 1b. Entrada por código digitado (tablet sem câmera) -->
    <section class="screen" id="tela-codigo">
      <div class="pad grow">
        <h2>Digite o código</h2>
        <p class="lead">Peça o código ao atendente. São 8 caracteres.</p>

        <div class="cpf-box">
          <input class="cpf-input" id="codigoInput" style="letter-spacing:6px;font-size:28px;"
                 inputmode="text" autocomplete="off" maxlength="8" readonly>
          <div class="keypad" id="tecladoCodigo" style="grid-template-columns:repeat(6,1fr);"></div>
        </div>

        <p class="note note-danger hidden" id="erroCodigo" style="margin-top:18px;"></p>
      </div>
      <div class="foot">
        <button type="button" class="btn btn-ghost" id="voltarEspera">Voltar</button>
        <button type="button" class="btn btn-primary" id="btnCodigo" disabled>Abrir documento</button>
      </div>
    </section>

    <!-- 2. Documento -->
    <section class="screen" id="tela-documento">
      <div class="pad" style="padding-bottom:10px;">
        <h2 id="docTitulo">Documento</h2>
        <p class="lead" style="margin-bottom:0;">Leia o documento até o fim para continuar.</p>
      </div>
      <div class="doc-scroll" id="docScroll"></div>
      <div class="doc-status" id="docStatus">Carregando o documento…</div>
      <div class="foot">
        <button type="button" class="btn btn-danger" data-recusar>Recusar</button>
        <button type="button" class="btn btn-primary" id="btnLido" disabled>Role até o fim</button>
      </div>
    </section>

    <!-- 3. Identidade -->
    <section class="screen" id="tela-identidade">
      <div class="pad grow">
        <h2>Confirme sua identidade</h2>
        <p class="lead" id="identidadeInstrucao">Digite os quatro primeiros dígitos do seu CPF.</p>

        <div class="cpf-box">
          <input class="cpf-input" id="cpfInput" inputmode="numeric" autocomplete="off" maxlength="11" readonly>
          <div class="keypad" id="teclado"></div>
        </div>

        <p class="note note-danger hidden" id="erroIdentidade" style="margin-top:18px;"></p>
      </div>
      <div class="foot">
        <button type="button" class="btn btn-danger" data-recusar>Recusar</button>
        <button type="button" class="btn btn-primary" id="btnIdentidade" disabled>Confirmar</button>
      </div>
    </section>

    <!-- 4. Aceite -->
    <section class="screen" id="tela-aceite">
      <div class="pad grow">
        <h2>Aceite dos termos</h2>
        <p class="lead">Confirme que leu e concorda com o documento apresentado.</p>

        <label class="accept" id="aceiteBox">
          <input type="checkbox" id="aceiteCheck">
          <span>Li e concordo com os termos deste documento.</span>
        </label>

        <label class="accept hidden" id="viaBox" style="margin-top:14px;">
          <input type="checkbox" id="viaCheck">
          <span>Quero receber uma via assinada no meu e-mail cadastrado.</span>
        </label>

        <div class="note note-info" id="avisoFoto" style="margin-top:18px;">
          Ao confirmar a assinatura, sua foto será registrada pela câmera frontal do tablet,
          junto com data, hora e local do atendimento. Esses dados ficam guardados como prova
          da assinatura e não são usados para outra finalidade.
        </div>
      </div>
      <div class="foot">
        <button type="button" class="btn btn-danger" data-recusar>Recusar</button>
        <button type="button" class="btn btn-primary" id="btnAceite" disabled>Continuar</button>
      </div>
    </section>

    <!-- 5. Assinatura -->
    <section class="screen" id="tela-assinatura">
      <div class="pad" style="padding-bottom:6px;">
        <h2>Assine no espaço abaixo</h2>
        <p class="lead" style="margin-bottom:0;">Use o dedo ou a caneta do tablet.</p>
      </div>
      <div class="sig-wrap">
        <div class="sig-pad" id="sigPad">
          <canvas id="sigCanvas"></canvas>
          <div class="sig-line"></div>
          <div class="sig-label" id="sigNome"></div>
        </div>
      </div>
      <div class="foot">
        <button type="button" class="btn btn-ghost" id="btnLimpar">Limpar</button>
        <button type="button" class="btn btn-primary" id="btnAssinar" disabled>Confirmar assinatura</button>
      </div>
    </section>

    <!-- 6. Foto -->
    <section class="screen" id="tela-foto">
      <div class="cam-wrap">
        <h2>Registro da assinatura</h2>
        <p class="lead" style="margin-bottom:4px;">Olhe para a câmera. A foto será tirada automaticamente.</p>
        <div class="cam-view"><video id="camVideo" playsinline muted></video></div>
        <div class="cam-count" id="camContagem">3</div>
        <p class="note note-danger hidden" id="erroFoto"></p>
      </div>
    </section>

    <!-- 7. Conclusão -->
    <section class="screen" id="tela-sucesso">
      <div class="done">
        <div class="check">✓</div>
        <h1>Assinatura concluída</h1>
        <p class="lead" id="sucessoDetalhe">Obrigado. Você pode devolver o tablet ao atendente.</p>
        <div class="note note-info" id="sucessoVia">
          A via assinada fica disponível com o atendente.
        </div>
      </div>
      <div class="foot">
        <button type="button" class="btn btn-primary" id="btnFechar">Concluir</button>
      </div>
    </section>

    <!-- Recusa -->
    <div class="sheet" id="sheetRecusa">
      <div class="card">
        <h3>Recusar a assinatura</h3>
        <p>O documento não será assinado. Se quiser, diga o motivo — é opcional.</p>
        <textarea id="motivoRecusa" placeholder="Motivo (opcional)"></textarea>
        <div class="row">
          <button type="button" class="btn btn-ghost" id="cancelarRecusa" style="flex:1;">Voltar</button>
          <button type="button" class="btn btn-primary" id="confirmarRecusa" style="background:var(--danger);">Recusar</button>
        </div>
      </div>
    </div>

    <!-- Aviso de inatividade -->
    <div class="sheet" id="sheetInatividade">
      <div class="card">
        <h3>Ainda está aí?</h3>
        <p id="textoInatividade">A sessão será encerrada em instantes por inatividade.</p>
        <div class="row">
          <button type="button" class="btn btn-primary" id="continuarSessao" style="flex:1;">Continuar</button>
        </div>
      </div>
    </div>

    <!-- Erro genérico -->
    <div class="sheet" id="sheetErro">
      <div class="card">
        <h3 id="erroTitulo">Não foi possível continuar</h3>
        <p id="erroTexto"></p>
        <div class="row">
          <button type="button" class="btn btn-primary" id="fecharErro" style="flex:1;">Entendi</button>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
(function () {
  'use strict';

  var CFG = window.QUIOSQUE;
  var $ = function (sel) { return document.querySelector(sel); };

  /*
   * O estado do atendimento vive AQUI, em memória, e é zerado no fim. Nada vai
   * para localStorage nem IndexedDB: o tablet é compartilhado, e o documento
   * da pessoa anterior não pode sobreviver a ela.
   */
  var S = null;

  function estadoLimpo() {
    return {
      documentoId: null,
      titulo: null,
      pdfUrl: null,
      signatario: null,
      regras: { identity_check: 'partial', requires_photo: true },
      cpfDigitado: '',
      leitura: { inicio: null, segundos: 0, ateOFim: false },
      tracos: [],
      assinaturaPng: null,
      fotoJpeg: null,
      // Motivo de não haver foto, quando o modelo a exigia. Ver a etapa da foto.
      semFoto: null,
      aceitou: false,
      querVia: false,
      sessao: { restante: 0, aviso: 60 },
    };
  }

  /* ---------------------------------------------------------------------
   | Telas
   |---------------------------------------------------------------------*/

  function mostra(id) {
    document.querySelectorAll('.screen').forEach(function (s) { s.classList.remove('on'); });
    $('#' + id).classList.add('on');
  }

  function erro(mensagem, titulo) {
    $('#erroTitulo').textContent = titulo || 'Não foi possível continuar';
    $('#erroTexto').textContent = mensagem;
    $('#sheetErro').classList.add('on');
  }

  $('#fecharErro').addEventListener('click', function () {
    $('#sheetErro').classList.remove('on');
  });

  /* ---------------------------------------------------------------------
   | Conversa com o servidor
   |---------------------------------------------------------------------*/

  function rota(nome) {
    return CFG.rotas[nome].replace('__DOC__', S ? S.documentoId : '');
  }

  function api(metodo, url, corpo) {
    return fetch(url, {
      method: metodo,
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CFG.csrf,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: corpo ? JSON.stringify(corpo) : undefined,
    }).then(function (resposta) {
      return resposta.json().catch(function () { return {}; }).then(function (dados) {
        if (!resposta.ok) {
          var falha = new Error(dados.error || 'Falha na comunicação com o servidor.');
          falha.status = resposta.status;
          falha.sessaoEncerrada = !!dados.session_ended || resposta.status === 419;
          throw falha;
        }

        return dados;
      });
    });
  }

  /* ---------------------------------------------------------------------
   | Leitor de QR
   |---------------------------------------------------------------------*/

  var leitor = null;
  var cameras = [];
  var cameraAtual = 0;
  var lendo = false;

  /**
   * Há câmera utilizável? Em HTTP comum a resposta é não, porque
   * `navigator.mediaDevices` simplesmente não existe fora de origem segura.
   */
  function temCamera() {
    return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
  }

  function iniciaLeitor() {
    /*
     * O botão do código aparece ANTES de qualquer checagem de câmera: é
     * justamente quando a câmera falha que ele precisa estar lá. Uma câmera
     * que existe mas não foca também é um atendimento parado.
     */
    $('#abrirCodigo').classList.toggle('hidden', !CFG.codigoManual);

    if (typeof Html5Qrcode === 'undefined') {
      $('#erroLeitor').textContent = 'Leitor de QR indisponível. Verifique a conexão do tablet.';
      $('#erroLeitor').classList.remove('hidden');
      return;
    }

    if (!temCamera()) {
      /*
       * getUserMedia só existe em origem segura. É o erro mais provável numa
       * instalação nova — e, com o modo sem HTTPS ligado, deixa de ser um beco
       * sem saída: a pessoa digita o código que o atendente dita.
       */
      $('#erroLeitor').innerHTML = CFG.codigoManual
        ? 'A câmera não está disponível neste tablet. Toque em <b>Digitar código</b> e peça o código ao atendente.'
        : 'A câmera não está disponível. O endereço precisa ser <b>HTTPS</b> '
          + 'e a permissão de câmera precisa estar concedida ao navegador do tablet.';
      $('#erroLeitor').classList.remove('hidden');
      $('#dicaLeitor').textContent = '';
      return;
    }

    leitor = leitor || new Html5Qrcode('reader', { verbose: false });

    Html5Qrcode.getCameras().then(function (lista) {
      cameras = lista || [];

      if (!cameras.length) {
        throw new Error('Nenhuma câmera encontrada.');
      }

      $('#trocarCamera').classList.toggle('hidden', cameras.length < 2);

      // Traseira primeiro: é a que fica virada para a tela do atendente.
      var traseira = cameras.findIndex(function (c) {
        return /back|rear|traseira|environment/i.test(c.label || '');
      });

      cameraAtual = traseira >= 0 ? traseira : 0;

      return ligaCamera();
    }).catch(function (e) {
      $('#erroLeitor').textContent = 'Não foi possível abrir a câmera: ' + (e.message || e);
      $('#erroLeitor').classList.remove('hidden');
    });
  }

  function ligaCamera() {
    if (lendo) {
      return Promise.resolve();
    }

    lendo = true;

    return leitor.start(
      cameras[cameraAtual].id,
      { fps: 10, qrbox: { width: 240, height: 240 } },
      aoLerQr,
      function () { /* leituras falhas são o normal enquanto procura */ }
    );
  }

  function paraLeitor() {
    if (!leitor || !lendo) {
      return Promise.resolve();
    }

    lendo = false;

    return leitor.stop().catch(function () {});
  }

  $('#trocarCamera').addEventListener('click', function () {
    paraLeitor().then(function () {
      cameraAtual = (cameraAtual + 1) % cameras.length;
      return ligaCamera();
    });
  });

  var processandoQr = false;

  function aoLerQr(texto) {
    if (processandoQr) {
      return;
    }

    /*
     * O leitor IGNORA o que não for do formato deste sistema. Um QR de
     * cardápio, de nota fiscal ou de um site qualquer não vira requisição — e,
     * principalmente, o tablet nunca navega para uma URL que apareceu num QR.
     */
    if (texto.indexOf(CFG.prefixoQr) !== 0) {
      $('#dicaLeitor').textContent = 'Este código não é de assinatura. Peça o QR ao atendente.';
      return;
    }

    processandoQr = true;
    $('#dicaLeitor').textContent = 'Abrindo o documento…';

    api('POST', CFG.rotas.consumir, { payload: texto })
      .then(function (dados) {
        return paraLeitor().then(function () { abreAtendimento(dados); });
      })
      .catch(function (e) {
        $('#dicaLeitor').textContent = 'Aponte a câmera para o QR Code exibido pelo atendente.';
        erro(e.message, 'QR Code não aceito');
      })
      .then(function () {
        // Pausa curta para não reprocessar o mesmo quadro.
        setTimeout(function () { processandoQr = false; }, 1500);
      });
  }

  /* ---------------------------------------------------------------------
   | Entrada por código digitado (tablet sem câmera)
   |---------------------------------------------------------------------*/

  var codigoDigitado = '';

  /*
   * O mesmo alfabeto do servidor, sem 0/O e 1/I/L. Escrito aqui porque o
   * teclado precisa desenhar as teclas — se divergir do servidor, a pessoa
   * digita um caractere que o código nunca teria.
   */
  var ALFABETO_CODIGO = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

  (function montaTecladoCodigo() {
    var alvo = $('#tecladoCodigo');

    ALFABETO_CODIGO.split('').forEach(function (c) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'key';
      b.style.minHeight = '52px';
      b.style.fontSize = '19px';
      b.textContent = c;
      b.dataset.char = c;
      alvo.appendChild(b);
    });

    ['apagar', 'limpar'].forEach(function (acao) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'key';
      b.style.minHeight = '52px';
      b.textContent = acao === 'apagar' ? '⌫' : 'C';
      b.dataset.acao = acao;
      alvo.appendChild(b);
    });

    alvo.addEventListener('click', function (ev) {
      var alvoTecla = ev.target;

      if (alvoTecla.dataset.acao === 'limpar') {
        codigoDigitado = '';
      } else if (alvoTecla.dataset.acao === 'apagar') {
        codigoDigitado = codigoDigitado.slice(0, -1);
      } else if (alvoTecla.dataset.char && codigoDigitado.length < 8) {
        codigoDigitado += alvoTecla.dataset.char;
      } else {
        return;
      }

      $('#codigoInput').value = codigoDigitado.length > 4
        ? codigoDigitado.slice(0, 4) + ' ' + codigoDigitado.slice(4)
        : codigoDigitado;

      $('#erroCodigo').classList.add('hidden');
      $('#btnCodigo').disabled = codigoDigitado.length !== 8;
    });
  })();

  $('#abrirCodigo').addEventListener('click', function () {
    codigoDigitado = '';
    $('#codigoInput').value = '';
    $('#btnCodigo').disabled = true;
    $('#erroCodigo').classList.add('hidden');

    // A câmera para enquanto a tela do código está aberta: duas coisas
    // disputando o atendimento produziriam duas sessões.
    paraLeitor();
    mostra('tela-codigo');
  });

  $('#voltarEspera').addEventListener('click', function () {
    mostra('tela-espera');
    iniciaLeitor();
  });

  $('#btnCodigo').addEventListener('click', function () {
    $('#btnCodigo').disabled = true;

    api('POST', CFG.rotas.consumir, { code: codigoDigitado })
      .then(function (dados) {
        abreAtendimento(dados);
      })
      .catch(function (e) {
        codigoDigitado = '';
        $('#codigoInput').value = '';
        $('#erroCodigo').textContent = e.message;
        $('#erroCodigo').classList.remove('hidden');
      });
  });

  /* ---------------------------------------------------------------------
   | Atendimento
   |---------------------------------------------------------------------*/

  function abreAtendimento(dados) {
    S = estadoLimpo();
    S.documentoId = dados.document.id;
    S.titulo = dados.document.title;
    S.pdfUrl = dados.document.pdf_url;
    S.signatario = dados.signer;
    S.regras = dados.rules;
    S.sessao.restante = dados.session.remaining_seconds;
    S.sessao.aviso = dados.session.warning_seconds;

    $('#tituloDoc').textContent = S.titulo;
    $('#subtituloDoc').textContent = S.signatario.name;
    $('#docTitulo').textContent = S.titulo;
    $('#sigNome').textContent = S.signatario.name;
    $('#relogio').classList.remove('hidden');

    $('#avisoFoto').classList.toggle('hidden', !S.regras.requires_photo);

    // A via por e-mail só é oferecida a quem tem e-mail cadastrado. O tablet
    // sabe SE existe, nunca QUAL é.
    $('#viaBox').classList.toggle('hidden', !S.signatario.has_email);

    iniciaContagem();
    iniciaBatimento();

    mostra('tela-documento');
    carregaPdf();
  }

  /* Volta à estaca zero — e apaga tudo o que era da pessoa atendida. */
  function encerraAtendimento(opcoes) {
    opcoes = opcoes || {};

    pararContagem();
    pararBatimento();
    desligaCamera();
    limpaCanvas();

    $('#docScroll').innerHTML = '';
    $('#cpfInput').value = '';
    $('#codigoInput').value = '';
    $('#erroCodigo').classList.add('hidden');
    codigoDigitado = '';
    $('#aceiteCheck').checked = false;
    $('#aceiteBox').classList.remove('on');
    $('#viaCheck').checked = false;
    $('#viaBox').classList.remove('on');
    $('#viaBox').classList.add('hidden');
    $('#btnAceite').disabled = true;
    $('#btnIdentidade').disabled = true;
    $('#btnLido').disabled = true;
    $('#btnLido').textContent = 'Role até o fim';
    $('#erroIdentidade').classList.add('hidden');
    $('#motivoRecusa').value = '';
    $('#tituloDoc').textContent = 'Assinatura de documentos';
    $('#subtituloDoc').textContent = CFG.clube;
    $('#relogio').classList.add('hidden');
    $('#sheetRecusa').classList.remove('on');
    $('#sheetInatividade').classList.remove('on');

    S = null;

    mostra('tela-espera');
    $('#dicaLeitor').textContent = 'Procurando o código…';

    if (opcoes.mensagem) {
      erro(opcoes.mensagem, opcoes.titulo || 'Sessão encerrada');
    }

    iniciaLeitor();
  }

  function trataFalha(e) {
    if (e && e.sessaoEncerrada) {
      encerraAtendimento({ mensagem: e.message, titulo: 'Sessão encerrada' });
      return true;
    }

    return false;
  }

  /* ---------------------------------------------------------------------
   | Tempo de sessão e batimento
   |---------------------------------------------------------------------*/

  var timerContagem = null;
  var timerBatimento = null;

  function iniciaContagem() {
    pararContagem();

    timerContagem = setInterval(function () {
      if (!S) {
        return;
      }

      S.sessao.restante--;

      var min = Math.floor(Math.max(0, S.sessao.restante) / 60);
      var seg = Math.max(0, S.sessao.restante) % 60;

      $('#relogio').textContent = min + ':' + (seg < 10 ? '0' : '') + seg;
      $('#relogio').classList.toggle('warn', S.sessao.restante <= S.sessao.aviso);

      if (S.sessao.restante === S.sessao.aviso) {
        $('#textoInatividade').textContent = 'A sessão será encerrada em '
          + S.sessao.aviso + ' segundos. Toque em continuar para seguir assinando.';
        $('#sheetInatividade').classList.add('on');
      }

      if (S.sessao.restante <= 0) {
        encerraAtendimento({
          mensagem: 'O tempo do atendimento terminou. Peça ao atendente que gere um novo QR Code.',
          titulo: 'Tempo esgotado',
        });
      }
    }, 1000);
  }

  function pararContagem() {
    clearInterval(timerContagem);
    timerContagem = null;
  }

  /*
   * Batimento: pergunta ao servidor se a sessão continua valendo. É o que faz
   * o cancelamento pelo painel derrubar o tablet sem broadcasting — e também o
   * que corrige a contagem regressiva, que é do servidor e não do relógio
   * daqui.
   */
  function iniciaBatimento() {
    pararBatimento();

    timerBatimento = setInterval(function () {
      if (!S) {
        return;
      }

      api('GET', CFG.rotas.sessao)
        .then(function (dados) {
          S.sessao.restante = dados.session.remaining_seconds;
        })
        .catch(function (e) {
          trataFalha(e);
        });
    }, 5000);
  }

  function pararBatimento() {
    clearInterval(timerBatimento);
    timerBatimento = null;
  }

  $('#continuarSessao').addEventListener('click', function () {
    $('#sheetInatividade').classList.remove('on');
  });

  /* ---------------------------------------------------------------------
   | Documento (PDF.js)
   |---------------------------------------------------------------------*/

  function carregaPdf() {
    var alvo = $('#docScroll');
    alvo.innerHTML = '';

    S.leitura.inicio = Date.now();

    if (typeof pdfjsLib === 'undefined') {
      $('#docStatus').textContent = 'Não foi possível carregar o leitor de PDF.';
      return;
    }

    pdfjsLib.GlobalWorkerOptions.workerSrc =
      'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    $('#docStatus').textContent = 'Carregando o documento…';

    pdfjsLib.getDocument({ url: S.pdfUrl, withCredentials: true }).promise
      .then(function (pdf) {
        var largura = Math.min(alvo.clientWidth - 32, 760);
        var pendentes = [];

        for (var n = 1; n <= pdf.numPages; n++) {
          pendentes.push(desenhaPagina(pdf, n, largura, alvo));
        }

        return Promise.all(pendentes).then(function () {
          $('#docStatus').textContent = 'Documento com ' + pdf.numPages + ' página(s). Role até o fim.';
          verificaRolagem();
        });
      })
      .catch(function () {
        $('#docStatus').textContent = 'Não foi possível abrir o documento. Chame o atendente.';
      });
  }

  function desenhaPagina(pdf, numero, largura, alvo) {
    return pdf.getPage(numero).then(function (pagina) {
      var base = pagina.getViewport({ scale: 1 });
      var escala = largura / base.width;
      var viewport = pagina.getViewport({ scale: escala });

      var canvas = document.createElement('canvas');
      canvas.className = 'doc-page';

      // Densidade da tela: sem isso o texto sai borrado no tablet.
      var dpr = Math.min(window.devicePixelRatio || 1, 2);
      canvas.width = Math.floor(viewport.width * dpr);
      canvas.height = Math.floor(viewport.height * dpr);
      canvas.style.width = viewport.width + 'px';
      canvas.style.height = viewport.height + 'px';

      alvo.appendChild(canvas);

      return pagina.render({
        canvasContext: canvas.getContext('2d'),
        viewport: viewport,
        transform: dpr !== 1 ? [dpr, 0, 0, dpr, 0, 0] : null,
      }).promise;
    });
  }

  function verificaRolagem() {
    var el = $('#docScroll');
    var chegouAoFim = el.scrollTop + el.clientHeight >= el.scrollHeight - 24;

    // Documento curto, que cabe inteiro na tela: não há o que rolar.
    if (el.scrollHeight <= el.clientHeight + 24) {
      chegouAoFim = true;
    }

    if (chegouAoFim && S && !S.leitura.ateOFim) {
      S.leitura.ateOFim = true;
      $('#btnLido').disabled = false;
      $('#btnLido').textContent = 'Li o documento';
    }
  }

  $('#docScroll').addEventListener('scroll', verificaRolagem);

  $('#btnLido').addEventListener('click', function () {
    S.leitura.segundos = Math.round((Date.now() - S.leitura.inicio) / 1000);

    api('POST', rota('visualizado'), {
      read_seconds: S.leitura.segundos,
      scrolled_to_end: S.leitura.ateOFim,
    }).catch(function (e) { trataFalha(e); });

    if (S.regras.identity_check === 'none') {
      mostra('tela-aceite');
      return;
    }

    preparaIdentidade();
    mostra('tela-identidade');
  });

  /* ---------------------------------------------------------------------
   | Identidade
   |---------------------------------------------------------------------*/

  function preparaIdentidade() {
    var completo = S.regras.identity_check === 'full';

    $('#identidadeInstrucao').textContent = completo
      ? 'Digite o seu CPF completo, apenas números.'
      : 'Digite os quatro primeiros dígitos do seu CPF.';

    $('#cpfInput').maxLength = completo ? 11 : 4;
    S.cpfDigitado = '';
    $('#cpfInput').value = '';
    $('#btnIdentidade').disabled = true;
  }

  (function montaTeclado() {
    var teclas = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'limpar', '0', 'apagar'];
    var alvo = $('#teclado');

    teclas.forEach(function (t) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'key';
      b.textContent = t === 'apagar' ? '⌫' : (t === 'limpar' ? 'C' : t);
      b.dataset.tecla = t;
      alvo.appendChild(b);
    });

    alvo.addEventListener('click', function (ev) {
      var tecla = ev.target.dataset.tecla;

      if (!tecla || !S) {
        return;
      }

      if (tecla === 'limpar') {
        S.cpfDigitado = '';
      } else if (tecla === 'apagar') {
        S.cpfDigitado = S.cpfDigitado.slice(0, -1);
      } else {
        var limite = S.regras.identity_check === 'full' ? 11 : 4;

        if (S.cpfDigitado.length < limite) {
          S.cpfDigitado += tecla;
        }
      }

      $('#cpfInput').value = S.cpfDigitado;
      $('#erroIdentidade').classList.add('hidden');

      var completo = S.regras.identity_check === 'full';
      $('#btnIdentidade').disabled = S.cpfDigitado.length !== (completo ? 11 : 4);
    });
  })();

  $('#btnIdentidade').addEventListener('click', function () {
    $('#btnIdentidade').disabled = true;

    api('POST', rota('identidade'), { cpf: S.cpfDigitado })
      .then(function () {
        mostra('tela-aceite');
      })
      .catch(function (e) {
        if (trataFalha(e)) {
          return;
        }

        S.cpfDigitado = '';
        $('#cpfInput').value = '';
        $('#erroIdentidade').textContent = e.message;
        $('#erroIdentidade').classList.remove('hidden');
      });
  });

  /* ---------------------------------------------------------------------
   | Aceite
   |---------------------------------------------------------------------*/

  $('#aceiteCheck').addEventListener('change', function () {
    var marcado = $('#aceiteCheck').checked;

    $('#aceiteBox').classList.toggle('on', marcado);
    $('#btnAceite').disabled = !marcado;

    if (S) {
      S.aceitou = marcado;
    }
  });

  $('#viaCheck').addEventListener('change', function () {
    var marcado = $('#viaCheck').checked;

    $('#viaBox').classList.toggle('on', marcado);

    if (S) {
      S.querVia = marcado;
    }
  });

  $('#btnAceite').addEventListener('click', function () {
    mostra('tela-assinatura');
    preparaCanvas();
  });

  /* ---------------------------------------------------------------------
   | Assinatura
   |---------------------------------------------------------------------*/

  var ctx = null;
  var desenhando = false;
  var tracoAtual = null;
  var inicioTraco = null;

  function preparaCanvas() {
    var canvas = $('#sigCanvas');
    var caixa = $('#sigPad').getBoundingClientRect();
    var dpr = Math.min(window.devicePixelRatio || 1, 2);

    canvas.width = Math.floor(caixa.width * dpr);
    canvas.height = Math.floor(caixa.height * dpr);

    ctx = canvas.getContext('2d');
    ctx.scale(dpr, dpr);
    ctx.lineWidth = 2.6;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#1b1b1b';

    S.tracos = [];
    S.assinaturaPng = null;
    $('#btnAssinar').disabled = true;
  }

  function limpaCanvas() {
    var canvas = $('#sigCanvas');

    if (ctx) {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
    }

    if (S) {
      S.tracos = [];
      S.assinaturaPng = null;
    }

    $('#btnAssinar').disabled = true;
  }

  function ponto(ev) {
    var caixa = $('#sigCanvas').getBoundingClientRect();

    return {
      x: Math.round((ev.clientX - caixa.left) * 10) / 10,
      y: Math.round((ev.clientY - caixa.top) * 10) / 10,
      // Tempo RELATIVO ao início do traço, em milissegundos. Relativo de
      // propósito: o relógio do tablet não entra na prova, e o que interessa
      // é o movimento da mão.
      t: Date.now() - inicioTraco,
      p: typeof ev.pressure === 'number' ? Math.round(ev.pressure * 100) / 100 : null,
    };
  }

  function contaPontos() {
    return S.tracos.reduce(function (total, traco) { return total + traco.points.length; }, 0);
  }

  (function ligaCanvas() {
    var canvas = $('#sigCanvas');

    canvas.addEventListener('pointerdown', function (ev) {
      if (!ctx) {
        return;
      }

      ev.preventDefault();
      canvas.setPointerCapture(ev.pointerId);

      desenhando = true;
      inicioTraco = inicioTraco || Date.now();
      tracoAtual = { points: [] };

      var p = ponto(ev);
      tracoAtual.points.push(p);

      ctx.beginPath();
      ctx.moveTo(p.x, p.y);
    });

    canvas.addEventListener('pointermove', function (ev) {
      if (!desenhando) {
        return;
      }

      ev.preventDefault();

      var p = ponto(ev);
      tracoAtual.points.push(p);

      ctx.lineTo(p.x, p.y);
      ctx.stroke();
    });

    function encerraTraco() {
      if (!desenhando) {
        return;
      }

      desenhando = false;

      if (tracoAtual && tracoAtual.points.length) {
        S.tracos.push(tracoAtual);
      }

      tracoAtual = null;

      // O mínimo do servidor é o mesmo daqui — a config vai para a tela para
      // que os dois números não divirjam em silêncio.
      $('#btnAssinar').disabled = contaPontos() < CFG.minPontos;
    }

    canvas.addEventListener('pointerup', encerraTraco);
    canvas.addEventListener('pointercancel', encerraTraco);
    canvas.addEventListener('pointerleave', encerraTraco);
  })();

  $('#btnLimpar').addEventListener('click', function () {
    limpaCanvas();
    inicioTraco = null;
  });

  $('#btnAssinar').addEventListener('click', function () {
    S.assinaturaPng = $('#sigCanvas').toDataURL('image/png');

    if (!S.regras.requires_photo) {
      envia();
      return;
    }

    /*
     * Modelo pede foto e o aparelho não tem câmera (ambiente sem HTTPS). Com a
     * flag ligada, a assinatura segue e a AUSÊNCIA é registrada com o motivo —
     * que vai para a evidência e para o manifesto. Sem a flag, o servidor
     * recusa, e é ele quem decide: o tablet não tem como se autorizar.
     */
    if (!temCamera() && CFG.podePularFoto) {
      S.semFoto = CFG.motivoSemCamera;
      envia();
      return;
    }

    mostra('tela-foto');
    iniciaFoto();
  });

  /* ---------------------------------------------------------------------
   | Foto
   |---------------------------------------------------------------------*/

  var streamFoto = null;

  function iniciaFoto() {
    $('#erroFoto').classList.add('hidden');

    if (!temCamera()) {
      $('#erroFoto').textContent = 'Câmera indisponível (é preciso HTTPS). Chame o atendente.';
      $('#erroFoto').classList.remove('hidden');
      return;
    }

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
      .then(function (stream) {
        streamFoto = stream;
        var video = $('#camVideo');
        video.srcObject = stream;

        return video.play().then(function () { contagemDaFoto(); });
      })
      .catch(function () {
        $('#erroFoto').textContent = 'Não foi possível abrir a câmera frontal. Chame o atendente.';
        $('#erroFoto').classList.remove('hidden');
      });
  }

  function contagemDaFoto() {
    var restante = 3;
    $('#camContagem').textContent = restante;

    var t = setInterval(function () {
      restante--;
      $('#camContagem').textContent = restante > 0 ? restante : '📷';

      if (restante <= 0) {
        clearInterval(t);
        capturaFoto();
      }
    }, 1000);
  }

  function capturaFoto() {
    var video = $('#camVideo');
    var canvas = document.createElement('canvas');

    // Quadrada e reduzida, como as fotos do cadastro de freelancer: é retrato
    // de reconhecimento, não fotografia.
    var lado = Math.min(video.videoWidth || 480, video.videoHeight || 480);
    canvas.width = 600;
    canvas.height = 600;

    var ctxFoto = canvas.getContext('2d');
    ctxFoto.drawImage(
      video,
      ((video.videoWidth || lado) - lado) / 2,
      ((video.videoHeight || lado) - lado) / 2,
      lado, lado, 0, 0, 600, 600
    );

    S.fotoJpeg = canvas.toDataURL('image/jpeg', 0.82);

    desligaCamera();
    envia();
  }

  function desligaCamera() {
    if (streamFoto) {
      streamFoto.getTracks().forEach(function (t) { t.stop(); });
      streamFoto = null;
    }

    var video = $('#camVideo');

    if (video) {
      video.srcObject = null;
    }
  }

  /* ---------------------------------------------------------------------
   | Envio
   |---------------------------------------------------------------------*/

  function envia() {
    $('#sucessoDetalhe').textContent = 'Gravando a assinatura…';
    mostra('tela-sucesso');

    api('POST', rota('assinar'), {
      signature: S.assinaturaPng,
      strokes: S.tracos,
      photo: S.fotoJpeg,
      photo_skipped_reason: S.semFoto,
      accepted: true,
      wants_copy: S.querVia,
      read_seconds: S.leitura.segundos,
      scrolled_to_end: S.leitura.ateOFim,
      viewport: {
        w: window.innerWidth,
        h: window.innerHeight,
        orientation: window.innerWidth > window.innerHeight ? 'landscape' : 'portrait',
      },
    })
      .then(function (dados) {
        $('#sucessoDetalhe').textContent = 'Assinado em ' + dados.signed_at
          + '. Você pode devolver o tablet ao atendente.';

        $('#sucessoVia').textContent = S.querVia
          ? 'A via assinada será enviada ao seu e-mail cadastrado em alguns instantes.'
          : 'A via assinada fica disponível com o atendente.';

        pararContagem();
        pararBatimento();

        // Volta sozinho à tela de espera — o balcão não pode depender de
        // alguém lembrar de tocar em "concluir".
        setTimeout(function () {
          if (S) {
            encerraAtendimento();
          }
        }, 12000);
      })
      .catch(function (e) {
        if (trataFalha(e)) {
          return;
        }

        // A assinatura NÃO foi gravada: volta para o traço, em vez de deixar a
        // pessoa achando que assinou.
        mostra('tela-assinatura');
        erro(e.message, 'A assinatura não foi registrada');
      });
  }

  $('#btnFechar').addEventListener('click', function () {
    encerraAtendimento();
  });

  /* ---------------------------------------------------------------------
   | Recusa
   |---------------------------------------------------------------------*/

  document.querySelectorAll('[data-recusar]').forEach(function (botao) {
    botao.addEventListener('click', function () {
      $('#sheetRecusa').classList.add('on');
    });
  });

  $('#cancelarRecusa').addEventListener('click', function () {
    $('#sheetRecusa').classList.remove('on');
  });

  $('#confirmarRecusa').addEventListener('click', function () {
    var motivo = $('#motivoRecusa').value.trim();

    api('POST', rota('recusar'), { reason: motivo || null })
      .then(function () {
        encerraAtendimento({
          mensagem: 'A assinatura foi recusada. Devolva o tablet ao atendente.',
          titulo: 'Assinatura recusada',
        });
      })
      .catch(function (e) {
        if (!trataFalha(e)) {
          erro(e.message);
        }
      });
  });

  /* ---------------------------------------------------------------------
   | Início
   |---------------------------------------------------------------------*/

  // Uma sessão pode sobreviver a um recarregamento acidental da página: o
  // cookie continua valendo. Retoma do começo do documento, que é o único
  // ponto seguro para recomeçar.
  api('GET', CFG.rotas.sessao)
    .then(function (dados) { abreAtendimento(dados); })
    .catch(function () { iniciaLeitor(); });

  // Recarregar por engano no meio do atendimento não pode deixar traço na
  // tela seguinte.
  window.addEventListener('pagehide', function () {
    desligaCamera();
  });
})();
</script>
@endverbatim
</body>
</html>
