<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Assinatura de Contratos — Freelancers</title>
  <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />
  <script>window.KIOSK = {
    csrf: @json(csrf_token()),
    headerImg: @json(asset('images/freelancer/cabecalho.png')),
    footerImg: @json(asset('images/freelancer/rodape.png')),
  };</script>
@verbatim
  <style>
  :root{
    --bg:#efe9e8; --surface:#ffffff; --surface-2:#faf6f5; --surface-3:#f2ecea;
    --border:#e8dedd; --border-strong:#d8cbc9;
    --ink:#1f1819; --ink-2:#6d6062; --ink-3:#9a8e90;
    --brand:#A00001; --brand-strong:#7c0001; --brand-tint:#fbe9e8; --on-brand:#fff;
    --success:#157a58; --success-tint:#e0f2ea;
    --warning:#a3560a; --warning-tint:#fbedd9;
    --paper:#fffefb; --paper-ink:#241f1a; --paper-line:#c9bfb4;
    --shadow: 0 1px 2px rgba(31,24,25,.04), 0 8px 24px -12px rgba(31,24,25,.18);
    --shadow-lg: 0 24px 60px -24px rgba(31,24,25,.45);
    --r:18px; --r-lg:26px; --tap:68px;
    --sans: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    --serif: ui-serif, Georgia, "Times New Roman", serif;
    --ease: cubic-bezier(.22,.61,.36,1);
  }
  @media (prefers-color-scheme: dark){
    :root{
      --bg:#0e0b0c; --surface:#1d1718; --surface-2:#241d1e; --surface-3:#2b2223;
      --border:#352a2b; --border-strong:#463739;
      --ink:#f3ebec; --ink-2:#ab9d9f; --ink-3:#7d7072;
      --brand:#e23a3b; --brand-strong:#c22526; --brand-tint:#33191a;
      --success:#3ecf9a; --success-tint:#14312a;
      --warning:#e0a353; --warning-tint:#33230f;
      --paper:#f4efe7; --paper-ink:#241f1a; --paper-line:#b8ad9f;
      --shadow: 0 1px 2px rgba(0,0,0,.4), 0 10px 30px -14px rgba(0,0,0,.7);
      --shadow-lg: 0 30px 70px -20px rgba(0,0,0,.8);
    }
  }
  :root[data-theme="light"]{
    --bg:#efe9e8; --surface:#ffffff; --surface-2:#faf6f5; --surface-3:#f2ecea;
    --border:#e8dedd; --border-strong:#d8cbc9; --ink:#1f1819; --ink-2:#6d6062; --ink-3:#9a8e90;
    --brand:#A00001; --brand-strong:#7c0001; --brand-tint:#fbe9e8;
    --success:#157a58; --success-tint:#e0f2ea; --warning:#a3560a; --warning-tint:#fbedd9;
    --paper:#fffefb; --paper-ink:#241f1a; --paper-line:#c9bfb4;
  }
  :root[data-theme="dark"]{
    --bg:#0e0b0c; --surface:#1d1718; --surface-2:#241d1e; --surface-3:#2b2223;
    --border:#352a2b; --border-strong:#463739; --ink:#f3ebec; --ink-2:#ab9d9f; --ink-3:#7d7072;
    --brand:#e23a3b; --brand-strong:#c22526; --brand-tint:#33191a;
    --success:#3ecf9a; --success-tint:#14312a; --warning:#e0a353; --warning-tint:#33230f;
    --paper:#f4efe7; --paper-ink:#241f1a; --paper-line:#b8ad9f;
  }
  *{box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
  html,body{margin:0;padding:0;height:100%;overscroll-behavior:none;}
  body{font-family:var(--sans);background:var(--bg);color:var(--ink);-webkit-font-smoothing:antialiased;}
  button{font-family:inherit;}

  .app{position:relative;display:flex;flex-direction:column;height:100dvh;max-width:680px;margin:0 auto;background:var(--surface);box-shadow:var(--shadow-lg);overflow:hidden;}
  .topbar{flex:0 0 auto;display:flex;align-items:center;gap:10px;padding:14px 18px;border-bottom:1px solid var(--border);background:var(--surface);z-index:5;}
  .brand-dot{width:34px;height:34px;border-radius:10px;flex:0 0 auto;background:var(--brand);color:var(--on-brand);display:grid;place-items:center;font-weight:800;font-size:15px;}
  .topbar .who{flex:1 1 auto;min-width:0;}
  .topbar .who b{font-size:13.5px;display:block;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .topbar .who span{font-size:11.5px;color:var(--ink-2);}
  .pill{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:700;font-variant-numeric:tabular-nums;padding:5px 10px;border-radius:999px;border:1px solid var(--border-strong);background:var(--surface-2);color:var(--ink-2);}
  .pill svg{width:13px;height:13px;}
  .pill.warn{color:var(--warning);border-color:color-mix(in srgb,var(--warning) 35%, var(--border));background:var(--warning-tint);}
  .icon-btn{border:none;background:transparent;color:var(--ink-2);cursor:pointer;width:40px;height:40px;border-radius:12px;display:grid;place-items:center;flex:0 0 auto;}
  .icon-btn:hover{background:var(--surface-2);color:var(--ink);}
  .icon-btn svg{width:22px;height:22px;}

  .screens{position:relative;flex:1 1 auto;overflow:hidden;}
  .screen{position:absolute;inset:0;display:flex;flex-direction:column;padding:22px 22px 0;overflow-y:auto;-webkit-overflow-scrolling:touch;opacity:0;visibility:hidden;transform:translateX(28px);transition:opacity .32s var(--ease), transform .38s var(--ease), visibility 0s .38s;}
  .screen.active{opacity:1;visibility:visible;transform:none;transition:opacity .34s var(--ease), transform .4s var(--ease);}
  .screen.leaving-left{transform:translateX(-28px);opacity:0;}
  @media (prefers-reduced-motion: reduce){.screen{transition:opacity .001s;transform:none;}.screen.leaving-left{transform:none;}}
  .screen::-webkit-scrollbar{width:0;}

  .eyebrow{font-size:11.5px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;color:var(--brand);margin:0 0 6px;}
  .title{font-size:26px;font-weight:750;line-height:1.15;margin:0;text-wrap:balance;letter-spacing:-.3px;}
  .subtitle{font-size:14.5px;color:var(--ink-2);margin:8px 0 0;line-height:1.45;}
  .screen-body{flex:1 1 auto;}
  .screen-foot{position:sticky;bottom:0;flex:0 0 auto;padding:16px 0 20px;background:linear-gradient(to top, var(--surface) 68%, transparent);display:flex;flex-direction:column;gap:10px;margin-top:8px;}

  .btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-height:var(--tap);padding:0 22px;border-radius:var(--r);font-size:17px;font-weight:700;cursor:pointer;border:1px solid transparent;transition:transform .12s var(--ease), background .18s, box-shadow .18s, opacity .18s;user-select:none;width:100%;}
  .btn:active{transform:scale(.975);}
  .btn svg{width:20px;height:20px;}
  .btn-primary{background:var(--brand);color:var(--on-brand);box-shadow:0 10px 22px -12px color-mix(in srgb,var(--brand) 80%, transparent);}
  .btn-primary:hover{background:var(--brand-strong);}
  .btn-ghost{background:var(--surface);color:var(--ink);border-color:var(--border-strong);}
  .btn-ghost:hover{background:var(--surface-2);}
  .btn-quiet{background:transparent;color:var(--ink-2);min-height:52px;font-size:15px;}
  .btn-quiet:hover{color:var(--ink);}
  .btn[disabled]{opacity:.42;pointer-events:none;}

  .display{text-align:center;margin:6px 0 4px;}
  .display .val{font-size:34px;font-weight:750;letter-spacing:2px;font-variant-numeric:tabular-nums;min-height:44px;}
  .display .val.placeholder{color:var(--ink-3);letter-spacing:6px;}
  .display .hint{font-size:13px;color:var(--ink-2);margin-top:4px;min-height:18px;}
  .dots{display:flex;gap:14px;justify-content:center;margin:14px 0 6px;}
  .dot{width:16px;height:16px;border-radius:50%;border:2px solid var(--border-strong);transition:.18s var(--ease);}
  .dot.on{background:var(--brand);border-color:var(--brand);transform:scale(1.06);}
  .keypad{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:14px;}
  .key{min-height:64px;border-radius:var(--r);border:1px solid var(--border);background:var(--surface-2);color:var(--ink);font-size:26px;font-weight:650;cursor:pointer;font-variant-numeric:tabular-nums;transition:transform .1s var(--ease), background .15s;}
  .key:hover{background:var(--surface-3);}
  .key:active{transform:scale(.94);background:var(--brand-tint);}
  .key.aux{font-size:15px;font-weight:700;color:var(--ink-2);background:transparent;border-color:transparent;}
  .key.aux:hover{background:var(--surface-2);}

  .menu-card{display:flex;align-items:center;gap:16px;width:100%;text-align:left;padding:20px;border-radius:var(--r-lg);border:1px solid var(--border);background:var(--surface);cursor:pointer;box-shadow:var(--shadow);transition:transform .14s var(--ease), border-color .18s;margin-bottom:12px;}
  .menu-card:active{transform:scale(.985);}
  .menu-card:hover{border-color:var(--border-strong);}
  .menu-card .ic{width:52px;height:52px;border-radius:15px;flex:0 0 auto;display:grid;place-items:center;background:var(--brand-tint);color:var(--brand);}
  .menu-card .ic svg{width:26px;height:26px;}
  .menu-card .tx b{font-size:18px;display:block;}
  .menu-card .tx span{font-size:13.5px;color:var(--ink-2);}
  .menu-card .chev{margin-left:auto;color:var(--ink-3);}

  .person{display:flex;align-items:center;gap:14px;padding:18px;border-radius:var(--r-lg);background:var(--surface-2);border:1px solid var(--border);margin-bottom:18px;}
  .avatar{width:54px;height:54px;border-radius:50%;flex:0 0 auto;display:grid;place-items:center;background:var(--brand);color:var(--on-brand);font-weight:750;font-size:21px;}
  .person b{font-size:19px;display:block;letter-spacing:-.2px;}
  .person span{font-size:13.5px;color:var(--ink-2);font-variant-numeric:tabular-nums;}

  .clist{display:flex;flex-direction:column;gap:12px;}
  .contract{padding:16px 18px;border-radius:var(--r);border:1px solid var(--border);background:var(--surface);box-shadow:var(--shadow);}
  .contract .row1{display:flex;align-items:center;gap:10px;margin-bottom:6px;}
  .contract .fn{font-size:16.5px;font-weight:700;}
  .contract .loc{font-size:13.5px;color:var(--ink-2);margin:2px 0 0;}
  .contract .when{font-size:13px;color:var(--ink-2);font-variant-numeric:tabular-nums;margin-top:8px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
  .contract .acts{display:flex;gap:8px;margin-top:14px;flex-wrap:wrap;}
  .contract .acts .btn{width:auto;flex:1 1 auto;min-height:52px;font-size:15px;padding:0 16px;}
  .chip{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:750;padding:5px 11px;border-radius:999px;margin-left:auto;white-space:nowrap;}
  .chip::before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor;}
  .chip.unsigned{color:var(--ink-2);background:var(--surface-3);}
  .chip.await{color:var(--warning);background:var(--warning-tint);}

  /* Lote de aprovação */
  .contract.pick{cursor:pointer;transition:border-color .15s var(--ease), transform .12s var(--ease);}
  .contract.pick:active{transform:scale(.99);}
  .contract.pick.sel{border-color:var(--brand);background:var(--brand-tint);}
  .lote-sum{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px;border-radius:var(--r);
            background:var(--surface-2);border:1px solid var(--border);margin:18px 0 6px;}
  .lote-sum b{font-size:17px;} .lote-sum span{font-size:13px;color:var(--ink-2);}
  .lote-sum .tot{font-size:20px;font-weight:800;color:var(--brand);font-variant-numeric:tabular-nums;}
  .lote-h{font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:var(--ink-2);margin:22px 0 10px;}
  .reject-note{font-size:12.5px;color:var(--warning);margin-top:6px;line-height:1.35;}

  .steps{display:flex;gap:6px;margin:2px 0 18px;}
  .steps i{height:5px;flex:1;border-radius:99px;background:var(--border-strong);transition:.3s var(--ease);}
  .steps i.done{background:var(--brand);}
  .chipset{display:flex;flex-wrap:wrap;gap:10px;}
  .opt{flex:1 1 auto;min-width:44%;min-height:64px;border-radius:var(--r);border:1.5px solid var(--border);background:var(--surface-2);color:var(--ink);font-size:16px;font-weight:650;cursor:pointer;transition:.14s var(--ease);display:flex;align-items:center;justify-content:center;gap:8px;padding:0 14px;}
  .opt:active{transform:scale(.97);}
  .opt.sel{border-color:var(--brand);background:var(--brand-tint);color:var(--brand);}
  .fn-opt{min-width:100%;justify-content:space-between;}
  .fn-opt .price{font-size:14px;color:var(--ink-2);font-variant-numeric:tabular-nums;}
  .fn-opt.sel .price{color:var(--brand);}
  .field-label{font-size:12.5px;font-weight:750;color:var(--ink-2);text-transform:uppercase;letter-spacing:.6px;margin:0 0 7px;}
  .txt-input{width:100%;min-height:60px;border-radius:var(--r);border:1.5px solid var(--border);background:var(--surface-2);color:var(--ink);font-size:17px;padding:0 18px;font-family:inherit;}
  .txt-input:focus{outline:none;border-color:var(--brand);background:var(--surface);}
  .form-grid{display:flex;flex-direction:column;gap:16px;margin-top:18px;}
  .frow{display:flex;flex-direction:column;}

  .receipt{border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;background:var(--surface);box-shadow:var(--shadow);}
  .receipt .head{background:var(--brand);color:var(--on-brand);padding:16px 20px;}
  .receipt .head .fn{font-size:19px;font-weight:750;}
  .receipt .head .fl{font-size:13.5px;opacity:.9;}
  .rrow{display:flex;justify-content:space-between;align-items:baseline;padding:13px 20px;border-bottom:1px solid var(--border);gap:14px;}
  .rrow:last-child{border-bottom:none;}
  .rrow .k{font-size:13.5px;color:var(--ink-2);}
  .rrow .v{font-size:15px;font-weight:650;text-align:right;font-variant-numeric:tabular-nums;}
  .rrow.total{background:var(--surface-2);}
  .rrow.total .k{font-weight:750;color:var(--ink);font-size:15px;}
  .rrow.total .v{font-size:24px;font-weight:800;color:var(--brand);}
  .note{font-size:12.5px;color:var(--ink-2);margin-top:2px;}

  .banner{display:flex;gap:12px;padding:14px 16px;border-radius:var(--r);margin:16px 0 0;background:var(--warning-tint);border:1px solid color-mix(in srgb,var(--warning) 30%, var(--border));}
  .banner svg{width:22px;height:22px;flex:0 0 auto;color:var(--warning);}
  .banner b{font-size:14px;color:var(--warning);display:block;}
  .banner p{margin:3px 0 0;font-size:13px;color:var(--ink-2);line-height:1.4;}

  /* ---------- Documento base do contrato ---------- */
  .doc{background:var(--paper);color:var(--paper-ink);border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow);overflow:hidden;margin-top:16px;font-family:var(--serif);}
  .doc-header-img img{display:block;width:100%;height:auto;}
  .doc-title{text-align:center;font-size:16px;font-weight:800;margin:18px 22px 6px;font-family:var(--sans);}
  .doc-body{padding:6px 26px 4px;}
  .doc-body p{margin:0 0 12px;font-size:14px;text-align:justify;line-height:1.62;}
  .doc-place{margin-top:16px !important;}
  .doc-signatures{display:flex;flex-direction:column;align-items:center;gap:26px;margin:30px 0 10px;}
  .doc-sign-block{width:min(340px,88%);text-align:center;}
  .sig-slot{position:relative;height:92px;border-radius:8px;background:rgba(160,0,1,.03);border:1.5px dashed var(--paper-line);touch-action:none;overflow:hidden;margin-bottom:2px;}
  .sig-slot canvas{position:absolute;inset:0;width:100%;height:100%;}
  .sig-slot .sig-ph{position:absolute;inset:0;display:grid;place-items:center;color:#a99;font-size:13px;pointer-events:none;font-family:var(--sans);transition:opacity .2s;}
  .sig-slot.filled{border-style:solid;border-color:var(--brand);}
  .doc-sign-line{border-top:1.5px solid var(--paper-ink);}
  .doc-sign-name{font-size:13.5px;font-weight:800;margin-top:6px;font-family:var(--sans);}
  .doc-sign-role{font-size:11.5px;color:#6b6156;font-family:var(--sans);margin-top:2px;}
  /* Assinatura já registrada da outra parte, exibida sobre a mesma altura do slot. */
  .doc-sign-img{height:92px;display:grid;place-items:center;overflow:hidden;}
  .doc-sign-img img{max-height:88px;max-width:100%;}
  .doc-sign-empty{height:92px;}
  .doc-sign-note{font-family:var(--sans);font-size:10.5px;color:#6b6156;margin-top:4px;line-height:1.35;}
  .doc-footer-img{margin-top:16px;}
  .doc-footer-img img{display:block;width:100%;height:auto;}

  .success{position:absolute;inset:0;z-index:20;background:var(--surface);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;padding:30px;text-align:center;opacity:0;visibility:hidden;transition:opacity .3s var(--ease);}
  .success.show{opacity:1;visibility:visible;}
  .checkmark{width:104px;height:104px;border-radius:50%;background:var(--success-tint);display:grid;place-items:center;margin-bottom:6px;}
  .checkmark svg{width:56px;height:56px;color:var(--success);stroke-dasharray:48;stroke-dashoffset:48;}
  .success.show .checkmark svg{animation:draw .5s .15s var(--ease) forwards;}
  .success.show .checkmark{animation:pop .4s var(--ease);}
  @keyframes draw{to{stroke-dashoffset:0;}}
  @keyframes pop{0%{transform:scale(.6);opacity:0;}60%{transform:scale(1.08);}100%{transform:scale(1);opacity:1;}}
  @media (prefers-reduced-motion: reduce){.success.show .checkmark svg{animation:none;stroke-dashoffset:0;}.success.show .checkmark{animation:none;}}
  .success h2{font-size:24px;margin:6px 0 0;font-weight:750;}
  .success p{font-size:15px;color:var(--ink-2);margin:4px 0 0;max-width:340px;line-height:1.45;}

  .toast{position:absolute;left:50%;bottom:24px;transform:translate(-50%,20px);background:var(--ink);color:var(--surface);padding:13px 20px;border-radius:999px;font-size:14px;font-weight:600;box-shadow:var(--shadow-lg);z-index:30;opacity:0;transition:.3s var(--ease);pointer-events:none;max-width:88%;text-align:center;}
  .toast.show{opacity:1;transform:translate(-50%,0);}
  .toast.err{background:var(--brand);color:#fff;}
  </style>
@endverbatim
</head>
<body>
@verbatim
<div class="app">

  <!-- Top bar -->
  <div class="topbar" id="topbar" style="display:none">
    <div class="brand-dot">C</div>
    <div class="who"><b id="opName">—</b><span id="ctxLine">Sessão de atendimento</span></div>
    <span class="pill" id="timerPill" title="Tempo restante da sessão">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v4l3 2"/></svg>
      <span id="timerVal">30:00</span>
    </span>
    <span class="pill" id="countPill" title="Contratos nesta sessão">0/5</span>
    <button class="icon-btn" id="themeBtn" aria-label="Alternar tema" title="Tema">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
    </button>
    <button class="icon-btn" id="exitBtn" aria-label="Encerrar sessão" title="Encerrar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7M13 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h7"/></svg>
    </button>
  </div>

  <div class="screens" id="screens">

    <!-- ===== LOGIN (matrícula + PIN) ===== -->
    <section class="screen active" id="s-login">
      <div class="screen-body" style="display:flex;flex-direction:column;justify-content:center;min-height:100%">
        <div style="text-align:center">
          <div class="brand-dot" style="width:60px;height:60px;border-radius:18px;font-size:26px;margin:0 auto 16px">C</div>
          <p class="eyebrow" style="text-align:center">Contratos CFCSN</p>
        </div>

        <!-- passo 1: matrícula -->
        <div id="loginStep1">
          <h2 class="title" style="text-align:center">Entrar</h2>
          <p class="subtitle" style="text-align:center">Informe sua matrícula</p>
          <div class="display" style="margin-top:18px"><div class="val placeholder" id="matVal">matrícula</div></div>
          <div class="keypad" id="matKeypad"></div>
          <div class="screen-foot">
            <button class="btn btn-primary" id="matNext" disabled>Continuar</button>
          </div>
        </div>

        <!-- passo 2: PIN -->
        <div id="loginStep2" style="display:none">
          <h2 class="title" style="text-align:center">PIN de acesso</h2>
          <p class="subtitle" style="text-align:center">Matrícula <b id="matEcho"></b> · digite seu PIN de 6 dígitos</p>
          <div class="dots" id="pinDotsLogin" style="margin-top:20px"></div>
          <div class="hint" id="loginHint" style="text-align:center;color:var(--brand);font-size:13px;min-height:18px">&nbsp;</div>
          <div class="keypad" id="pinKeypadLogin"></div>
          <div class="screen-foot">
            <button class="btn-quiet btn" id="matBack">Trocar matrícula</button>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== MODO (só para quem é operador E coordenador) ===== -->
    <section class="screen" id="s-mode">
      <div class="screen-body">
        <p class="eyebrow">Bem-vindo</p>
        <h2 class="title">O que você vai fazer agora?</h2>
        <p class="subtitle">Você pode atender freelancers e assinar como coordenador.</p>
        <div id="modeList" style="margin-top:20px"></div>
      </div>
      <div class="screen-foot">
        <button class="btn-quiet btn" id="modeExit">Sair</button>
      </div>
    </section>

    <!-- ===== FILA DO COORDENADOR ===== -->
    <section class="screen" id="s-coord">
      <div class="screen-body">
        <p class="eyebrow">Coordenação · <span id="coordSector">Comercial</span></p>
        <h2 class="title">Contratos a assinar</h2>
        <p class="subtitle">Assinados pelo freelancer, aguardando sua assinatura</p>
        <div class="clist" id="coordList" style="margin-top:18px"></div>
      </div>
      <div class="screen-foot">
        <button class="btn btn-primary" id="coordLote">Lote para a gerência</button>
        <button class="btn btn-ghost" id="coordReload">Atualizar lista</button>
      </div>
    </section>

    <!-- ===== LOTE DE APROVAÇÃO ===== -->
    <section class="screen" id="s-lote">
      <div class="screen-body">
        <p class="eyebrow">Coordenação · <span id="loteSector">Comercial</span></p>
        <h2 class="title">Lote para a gerência</h2>
        <p class="subtitle">Inclua os contratos já assinados pelas duas partes e envie para aprovação.</p>

        <div class="lote-sum" id="loteSum"></div>
        <div class="clist" id="loteDraft"></div>

        <h3 class="lote-h">Disponíveis para incluir</h3>
        <div class="clist" id="loteAvail"></div>
      </div>
      <div class="screen-foot">
        <button class="btn btn-primary" id="loteAdd" disabled>Incluir selecionados</button>
        <button class="btn btn-ghost" id="loteSend" disabled>Enviar para aprovação</button>
        <button class="btn-quiet btn" id="loteBack">Voltar aos contratos</button>
      </div>
    </section>

    <!-- ===== CPF ===== -->
    <section class="screen" id="s-cpf">
      <div class="screen-body">
        <p class="eyebrow">Atendimento</p>
        <h2 class="title">Localizar freelancer</h2>
        <p class="subtitle">Digite os 11 números do CPF</p>
        <div class="display" style="margin-top:22px">
          <div class="val placeholder" id="cpfVal">•••.•••.•••-••</div>
          <div class="hint" id="cpfHint">&nbsp;</div>
        </div>
        <div class="keypad" id="cpfKeypad"></div>
      </div>
      <div class="screen-foot">
        <button class="btn btn-primary" id="cpfNext" disabled>Buscar freelancer</button>
      </div>
    </section>

    <!-- ===== CADASTRO ===== -->
    <section class="screen" id="s-cadastro">
      <div class="screen-body">
        <p class="eyebrow">CPF não cadastrado</p>
        <h2 class="title">Cadastrar freelancer</h2>
        <p class="subtitle">A chave PIX será o próprio CPF. E-mail é opcional.</p>
        <div class="form-grid" id="cadForm">
          <div class="frow"><span class="field-label">Nome completo *</span><input class="txt-input" data-f="name" autocomplete="off"></div>
          <div class="frow"><span class="field-label">RG *</span><input class="txt-input" data-f="rg" autocomplete="off"></div>
          <div class="frow"><span class="field-label">Nacionalidade *</span><input class="txt-input" data-f="nacionality" autocomplete="off" value="Brasileira"></div>
          <div class="frow"><span class="field-label">Estado civil *</span><input class="txt-input" data-f="civil_status" autocomplete="off"></div>
          <div class="frow"><span class="field-label">E-mail (opcional)</span><input class="txt-input" data-f="email" type="email" autocomplete="off"></div>
          <div class="frow"><span class="field-label">Endereço *</span><input class="txt-input" data-f="address" autocomplete="off"></div>
          <div class="frow"><span class="field-label">Telefone *</span><input class="txt-input" data-f="telephone" inputmode="tel" autocomplete="off"></div>
        </div>
      </div>
      <div class="screen-foot">
        <button class="btn btn-primary" id="cadSave">Cadastrar e continuar</button>
        <button class="btn-quiet btn" data-go="cpf">Cancelar</button>
      </div>
    </section>

    <!-- ===== COMPLETAR CADASTRO ===== -->
    <section class="screen" id="s-completar">
      <div class="screen-body">
        <p class="eyebrow">Cadastro incompleto</p>
        <h2 class="title">Completar cadastro</h2>
        <p class="subtitle">Preencha os dados que faltam para liberar o contrato. E-mail é opcional.</p>
        <div class="form-grid" id="compForm">
          <div class="frow"><span class="field-label">Nome completo *</span><input class="txt-input" data-f="name" autocomplete="off"></div>
          <div class="frow"><span class="field-label">RG *</span><input class="txt-input" data-f="rg" autocomplete="off"></div>
          <div class="frow"><span class="field-label">Nacionalidade *</span><input class="txt-input" data-f="nacionality" autocomplete="off"></div>
          <div class="frow"><span class="field-label">Estado civil *</span><input class="txt-input" data-f="civil_status" autocomplete="off"></div>
          <div class="frow"><span class="field-label">E-mail (opcional)</span><input class="txt-input" data-f="email" type="email" autocomplete="off"></div>
          <div class="frow"><span class="field-label">Endereço *</span><input class="txt-input" data-f="address" autocomplete="off"></div>
          <div class="frow"><span class="field-label">Telefone *</span><input class="txt-input" data-f="telephone" inputmode="tel" autocomplete="off"></div>
        </div>
      </div>
      <div class="screen-foot">
        <button class="btn btn-primary" id="compSave">Salvar e continuar</button>
        <button class="btn-quiet btn" data-go="menu">Cancelar</button>
      </div>
    </section>

    <!-- ===== MENU ===== -->
    <section class="screen" id="s-menu">
      <div class="screen-body">
        <p class="eyebrow">Freelancer</p>
        <div class="person" style="margin-top:8px">
          <div class="avatar" id="menuAvatar">M</div>
          <div><b id="menuName">—</b><span id="menuCpf">—</span></div>
        </div>
        <div id="menuIncomplete" style="display:none;margin:4px 0 14px;padding:14px 16px;border-radius:14px;border:1px solid var(--warning);background:color-mix(in srgb, var(--warning) 12%, transparent)">
          <b style="display:block;color:var(--warning)">Cadastro incompleto</b>
          <span class="subtitle" id="menuMissing" style="display:block;margin:4px 0 10px"></span>
          <button class="btn btn-primary" id="cardCompletar" style="width:100%">Completar cadastro</button>
        </div>
        <button class="menu-card" id="cardNovo">
          <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg></span>
          <span class="tx"><b>Novo contrato</b><span>Registrar um turno de trabalho</span></span>
          <span class="chev"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></span>
        </button>
        <button class="menu-card" id="cardContratos">
          <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6M9 16h6M9 8h6M5 3h14a1 1 0 0 1 1 1v16l-3-2-3 2-3-2-3 2V4a1 1 0 0 1 1-1z"/></svg></span>
          <span class="tx"><b>Meus contratos</b><span>Assinar contratos em aberto</span></span>
          <span class="chev"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></span>
        </button>
      </div>
      <div class="screen-foot">
        <button class="btn-quiet btn" data-go="cpf">Trocar de freelancer</button>
      </div>
    </section>

    <!-- ===== NOVO CONTRATO ===== -->
    <section class="screen" id="s-novo">
      <div class="screen-body">
        <div class="steps" id="novoSteps"><i class="done"></i><i></i><i></i><i></i><i></i></div>
        <div class="nstep" data-step="0">
          <h2 class="title">Quando o turno começou?</h2>
          <p class="subtitle">Dia em que o serviço teve início</p>
          <div class="chipset" style="margin-top:22px">
            <button class="opt" data-day="hoje">Hoje</button>
            <button class="opt" data-day="ontem">Ontem</button>
          </div>
          <input type="date" class="txt-input" id="dayCustom" style="display:none;margin-top:12px">
        </div>
        <div class="nstep" data-step="1" style="display:none">
          <h2 class="title">Hora de início</h2>
          <p class="subtitle">Quando o freelancer começou</p>
          <div class="display" style="margin-top:20px"><div class="val placeholder" id="startVal">--:--</div></div>
          <div class="keypad" id="startKeypad"></div>
        </div>
        <div class="nstep" data-step="2" style="display:none">
          <h2 class="title">Hora de término</h2>
          <p class="subtitle">Se for menor que o início, o turno vira o dia</p>
          <div class="display" style="margin-top:20px"><div class="val placeholder" id="endVal">--:--</div></div>
          <div class="keypad" id="endKeypad"></div>
        </div>
        <div class="nstep" data-step="3" style="display:none">
          <h2 class="title">Função</h2>
          <p class="subtitle">Selecione o serviço executado</p>
          <div class="chipset" id="fnList" style="margin-top:20px;flex-direction:column"></div>
        </div>
        <div class="nstep" data-step="4" style="display:none">
          <h2 class="title">Local ou evento</h2>
          <p class="subtitle">Onde o serviço foi prestado</p>
          <input type="text" class="txt-input" id="locInput" placeholder="Ex.: Confraternização — Salão Nobre" style="margin-top:20px" autocomplete="off">
        </div>
      </div>
      <div class="screen-foot">
        <button class="btn btn-primary" id="novoNext" disabled>Continuar</button>
        <button class="btn-quiet btn" id="novoBack">Voltar</button>
      </div>
    </section>

    <!-- ===== PRÉVIA ===== -->
    <section class="screen" id="s-previa">
      <div class="screen-body">
        <p class="eyebrow">Confira antes de gravar</p>
        <h2 class="title">Prévia do contrato</h2>
        <p class="subtitle">Valor e término calculados pelo servidor</p>
        <div class="receipt" id="previaReceipt" style="margin-top:18px"></div>
        <div id="previaWarn"></div>
      </div>
      <div class="screen-foot">
        <button class="btn btn-primary" id="registrarBtn">Registrar contrato</button>
        <button class="btn-quiet btn" data-go="menu">Descartar</button>
      </div>
    </section>

    <!-- ===== CONTRATOS ===== -->
    <section class="screen" id="s-contratos">
      <div class="screen-body">
        <p class="eyebrow">Freelancer · <span id="ctName">—</span></p>
        <h2 class="title">Meus contratos</h2>
        <p class="subtitle">Apenas contratos que ainda aceitam a assinatura</p>
        <div class="clist" id="clist" style="margin-top:18px"></div>
      </div>
      <div class="screen-foot">
        <button class="btn-quiet btn" data-go="menu">Voltar ao menu</button>
      </div>
    </section>

    <!-- ===== ASSINAR (documento base + assinatura posicionada) ===== -->
    <section class="screen" id="s-assinar">
      <div class="screen-body">
        <p class="eyebrow" id="signEyebrow">Assinatura do freelancer</p>
        <h2 class="title">Confira e assine o documento</h2>
        <p class="subtitle" id="signSub">Role o contrato e assine no campo do Contratado. A assinatura é definitiva.</p>
        <div id="docHost"></div>
      </div>
      <div class="screen-foot">
        <div style="display:flex;gap:10px">
          <button class="btn btn-ghost" id="sigClear" style="flex:1">Limpar</button>
          <button class="btn btn-primary" id="sigConfirm" style="flex:1" disabled>Confirmar assinatura</button>
        </div>
        <button class="btn-quiet btn" id="sigCancel">Cancelar</button>
      </div>
    </section>

    <!-- ===== PIN (assinar / liberar limite semanal) ===== -->
    <section class="screen" id="s-pin">
      <div class="screen-body" style="display:flex;flex-direction:column;justify-content:center;min-height:100%">

        <!-- passo 1, só no limite semanal: como o coordenador vai liberar -->
        <div id="pinChoiceStep" style="display:none">
          <div style="text-align:center">
            <p class="eyebrow" style="text-align:center">Liberação do coordenador</p>
            <h2 class="title" style="text-align:center">Quem libera é o Comercial</h2>
            <p class="subtitle" style="text-align:center">Só um coordenador do setor <b id="pinMatSector">Comercial</b> pode autorizar este registro</p>
          </div>
          <div id="pinChoiceExtra"></div>
          <div class="hint" id="pinChoiceHint" style="text-align:center;color:var(--brand);font-size:13px;min-height:18px">&nbsp;</div>
          <div style="margin-top:16px">
            <button class="btn btn-primary" id="pinChoicePresent">O coordenador está aqui · usar o PIN dele</button>
            <button class="btn btn-ghost" id="pinChoiceEmail" style="margin-top:10px">Ninguém presente · enviar código por e-mail</button>
          </div>
        </div>

        <!-- passo 2a (só no caminho do PIN): matrícula do coordenador -->
        <div id="pinMatStep" style="display:none">
          <div style="text-align:center">
            <p class="eyebrow" style="text-align:center">Liberação do coordenador</p>
            <h2 class="title" style="text-align:center">Matrícula do coordenador</h2>
            <p class="subtitle" style="text-align:center">Do coordenador do Comercial que está liberando</p>
          </div>
          <div id="pinMatExtra"></div>
          <div class="display" style="margin-top:18px"><div class="val placeholder" id="pinMatVal">matrícula</div></div>
          <div class="hint" id="pinMatHint" style="text-align:center;color:var(--brand);font-size:13px;min-height:18px">&nbsp;</div>
          <div class="keypad" id="pinMatKeypad"></div>
          <div style="margin-top:14px">
            <button class="btn btn-primary" id="pinMatNext" disabled>Continuar</button>
          </div>
        </div>

        <!-- passo 2: PIN -->
        <div id="pinPinStep">
          <div style="text-align:center">
            <p class="eyebrow" style="text-align:center" id="pinEyebrow">Confirmação do operador</p>
            <h2 class="title" style="text-align:center" id="pinTitle">Digite seu PIN</h2>
            <p class="subtitle" style="text-align:center" id="pinSub">A assinatura fica registrada como auxiliada por você</p>
          </div>
          <div id="pinExtra"></div>
          <div class="dots" id="pinDotsOp" style="margin-top:22px"></div>
          <div class="hint" id="pinOpHint" style="text-align:center;color:var(--brand);font-size:13px;min-height:18px">&nbsp;</div>
          <div class="keypad" id="pinKeypadOp"></div>
        </div>
      </div>
      <div class="screen-foot">
        <button class="btn-quiet btn" id="pinCancel">Voltar</button>
      </div>
    </section>

  </div>

  <div class="success" id="success">
    <div class="checkmark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg></div>
    <h2 id="successTitle">Contrato assinado</h2>
    <p id="successMsg">Assinatura registrada. Pronto para o próximo freelancer.</p>
  </div>

  <div class="toast" id="toast"></div>
</div>

<script>
(function(){
  "use strict";
  const $ = s => document.querySelector(s);
  const $$ = s => Array.from(document.querySelectorAll(s));
  const K = window.KIOSK;

  /* ---------- Theme ---------- */
  const root = document.documentElement;
  function effDark(){ return root.getAttribute('data-theme') ? root.getAttribute('data-theme')==='dark' : window.matchMedia('(prefers-color-scheme: dark)').matches; }
  $('#themeBtn').addEventListener('click', ()=>{ root.setAttribute('data-theme', effDark()?'light':'dark'); if(current==='s-assinar') sizeCanvas(); });

  /* ---------- HTTP ---------- */
  async function api(method, url, body){
    const opt = { method, headers:{ 'X-CSRF-TOKEN':K.csrf, 'Accept':'application/json' } };
    if(body){ opt.headers['Content-Type']='application/json'; opt.body=JSON.stringify(body); }
    const res = await fetch(url, opt);
    let data=null; try{ data=await res.json(); }catch(_){}
    if(res.status===419){ handleExpired((data&&data.error)||'Sessão expirada.'); throw {handled:true}; }
    return { ok:res.ok, status:res.status, data:data||{} };
  }

  /* ---------- State ---------- */
  const S = { operator:null, mode:null, freelancer:null, functions:[], draft:{}, signing:null,
              signature:null, pinMode:null, timer:null, remaining:1800, count:0 };

  /* ---------- Helpers ---------- */
  const digits = s => (s||'').replace(/\D/g,'');
  function fmtCpf(d){ d=(d||'').padEnd(11,'•'); return `${d.slice(0,3)}.${d.slice(3,6)}.${d.slice(6,9)}-${d.slice(9,11)}`; }
  function brl(n){ return 'R$ ' + Number(n).toFixed(2).replace('.',','); }
  function initials(name){ const p=(name||'').trim().split(/\s+/); return ((p[0]||'')[0]||'')+((p[1]||'')[0]||''); }
  function fmtDur(min){ const h=Math.floor(min/60), m=min%60; return m? `${h}h${String(m).padStart(2,'0')}` : `${h}h`; }
  function isoLocal(off){ const t=new Date(); t.setDate(t.getDate()+(off||0)); return `${t.getFullYear()}-${String(t.getMonth()+1).padStart(2,'0')}-${String(t.getDate()).padStart(2,'0')}`; }
  function brFromIso(iso){ if(!iso) return ''; const [y,m,d]=iso.split('-'); return `${d}/${m}/${y}`; }
  function nextDayIso(iso){ const [y,m,d]=iso.split('-').map(Number); const t=new Date(y,m-1,d+1); return `${t.getFullYear()}-${String(t.getMonth()+1).padStart(2,'0')}-${String(t.getDate()).padStart(2,'0')}`; }
  function validTime(t){ if(!/^\d{2}:\d{2}$/.test(t)) return false; const [h,m]=t.split(':').map(Number); return h<24 && m<60; }
  function esc(s){ return (s==null?'':String(s)).replace(/[&<>"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
  function calc(start, end, block){
    const [sh,sm]=start.split(':').map(Number), [eh,em]=end.split(':').map(Number);
    let s=sh*60+sm, e=eh*60+em; const crosses=e<=s; if(crosses) e+=1440;
    const dur=e-s, blocks=Math.floor(dur/15);
    return {crosses,dur,blocks,paidMin:blocks*15,price:blocks*block};
  }

  /* ---------- Navigation ---------- */
  let current='s-login';
  function go(id){
    if(id===current) return;
    const from=$('#'+current), to=$('#'+id);
    from.classList.add('leaving-left'); from.classList.remove('active');
    setTimeout(()=>from.classList.remove('leaving-left'),420);
    to.classList.add('active'); to.scrollTop=0; current=id;
    $('#topbar').style.display = (id==='s-login') ? 'none' : 'flex';
    updateCtx();
  }
  $$('[data-go]').forEach(b=> b.addEventListener('click', ()=> go('s-'+b.dataset.go)));
  function updateCtx(){
    const map={'s-mode':'Escolha o modo','s-coord':'Contratos pendentes','s-lote':'Lote de aprovação','s-cpf':'Localizar freelancer','s-cadastro':'Cadastro','s-menu':'Atendimento','s-novo':'Novo contrato','s-previa':'Prévia','s-contratos':'Contratos','s-assinar':'Assinatura','s-pin':'Confirmação'};
    if(S.mode==='coordinator'){ $('#ctxLine').textContent='Coordenação · '+(S.operator&&S.operator.coordinator_sector||'Comercial'); return; }
    $('#ctxLine').textContent = S.freelancer ? S.freelancer.name : (map[current]||'Sessão de atendimento');
  }

  /* ---------- Toast ---------- */
  let toastT;
  function toast(msg, err){ const t=$('#toast'); t.textContent=msg; t.classList.toggle('err',!!err); t.classList.add('show'); clearTimeout(toastT); toastT=setTimeout(()=>t.classList.remove('show'),2600); }

  /* ---------- Keypad / dots ---------- */
  function buildKeypad(container, onDigit, onBack){
    container.innerHTML='';
    ['1','2','3','4','5','6','7','8','9','','0','back'].forEach(k=>{
      const b=document.createElement('button');
      if(k===''){ b.style.visibility='hidden'; b.className='key aux'; container.appendChild(b); return; }
      if(k==='back'){ b.className='key aux'; b.setAttribute('aria-label','Apagar');
        b.innerHTML='<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zM15 9l-4 6M11 9l4 6"/></svg>';
        b.addEventListener('click', onBack); }
      else { b.className='key'; b.textContent=k; b.addEventListener('click', ()=>onDigit(k)); }
      container.appendChild(b);
    });
  }
  function renderDots(container, len, count){ container.innerHTML=''; for(let i=0;i<len;i++){ const d=document.createElement('div'); d.className='dot'+(i<count?' on':''); container.appendChild(d);} }

  /* ---------- Session timer ---------- */
  function applySession(s){ if(!s) return; S.remaining=s.remaining_seconds; S.count=s.count;
    // O coordenador não tem teto de contratos por sessão — some com o contador.
    $('#countPill').style.display = s.counts_contracts===false ? 'none' : 'inline-flex';
    $('#countPill').textContent=`${s.count}/${s.max}`; $('#countPill').classList.toggle('warn', s.count>=s.max); renderTimer(); }
  function renderTimer(){ const m=Math.floor(S.remaining/60), sec=S.remaining%60; $('#timerVal').textContent=`${m}:${String(sec).padStart(2,'0')}`; $('#timerPill').classList.toggle('warn', S.remaining<=300); }
  function startTimer(){ clearInterval(S.timer); S.timer=setInterval(()=>{ S.remaining--; renderTimer(); if(S.remaining<=0){ handleExpired('Sessão expirada (30 min).'); } },1000); }
  function handleExpired(reason){ clearInterval(S.timer); S.freelancer=null; S.operator=null; S.mode=null; resetLogin(); go('s-login'); toast(reason,true); }

  /* ---------- Login (matrícula + PIN) ---------- */
  let matBuf='', pinLoginBuf='';
  function resetLogin(){ matBuf=''; pinLoginBuf=''; renderMat(); $('#loginStep2').style.display='none'; $('#loginStep1').style.display='block'; $('#loginHint').innerHTML='&nbsp;'; }
  buildKeypad($('#matKeypad'),
    d=>{ if(matBuf.length<5){ matBuf+=d; renderMat(); } },
    ()=>{ matBuf=matBuf.slice(0,-1); renderMat(); });
  function renderMat(){ const el=$('#matVal'); el.textContent=matBuf||'matrícula'; el.classList.toggle('placeholder',!matBuf); $('#matNext').disabled=matBuf.length<1; }
  renderMat();
  $('#matNext').addEventListener('click', ()=>{ $('#matEcho').textContent=matBuf; $('#loginStep1').style.display='none'; $('#loginStep2').style.display='block'; pinLoginBuf=''; renderDots($('#pinDotsLogin'),6,0); $('#loginHint').innerHTML='&nbsp;'; });
  $('#matBack').addEventListener('click', ()=>{ $('#loginStep2').style.display='none'; $('#loginStep1').style.display='block'; });
  buildKeypad($('#pinKeypadLogin'),
    d=>{ if(pinLoginBuf.length<6){ pinLoginBuf+=d; renderDots($('#pinDotsLogin'),6,pinLoginBuf.length); if(pinLoginBuf.length===6) submitLogin(); } },
    ()=>{ pinLoginBuf=pinLoginBuf.slice(0,-1); renderDots($('#pinDotsLogin'),6,pinLoginBuf.length); $('#loginHint').innerHTML='&nbsp;'; });
  renderDots($('#pinDotsLogin'),6,0);
  async function submitLogin(){
    try{
      const r=await api('POST','/kiosk/login',{ matricula:matBuf, pin:pinLoginBuf });
      if(r.ok){ startSession(r.data.operator, r.data.session); }
      else { $('#loginHint').textContent=r.data.error||'Não foi possível entrar.'; pinLoginBuf=''; renderDots($('#pinDotsLogin'),6,0); }
    }catch(e){ if(!e.handled) toast('Falha de conexão.',true); pinLoginBuf=''; renderDots($('#pinDotsLogin'),6,0); }
  }
  async function doLogout(){ try{ await api('POST','/kiosk/logout'); }catch(e){} clearInterval(S.timer); S.freelancer=null; S.operator=null; S.mode=null; resetLogin(); go('s-login'); }
  $('#exitBtn').addEventListener('click', doLogout);
  $('#modeExit').addEventListener('click', doLogout);

  /* ---------- Modo de uso (atendimento x coordenação) ---------- */
  /**
   * Quem só tem um papel entra direto nele — o servidor já resolve o modo no
   * login. Só quem acumula atendimento e coordenação vê a tela de escolha.
   */
  function startSession(op, sess){
    S.operator=op; $('#opName').textContent=op.name; applySession(sess); startTimer();
    if(sess.mode) enterMode(sess.mode); else renderModePicker();
  }
  function enterMode(mode){
    S.mode=mode;
    if(mode==='coordinator'){ $('#coordSector').textContent=S.operator.coordinator_sector||'Comercial'; openCoord(); }
    else { S.freelancer=null; resetCpf(); go('s-cpf'); }
  }
  const MODE_CARDS={
    operator:{ title:'Atender freelancer', sub:'Cadastrar, registrar contrato e colher a assinatura',
      icon:'<path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7z"/>' },
    coordinator:{ title:'Assinar como coordenador', sub:'Contratos assinados pelo freelancer aguardando você',
      icon:'<path stroke-linecap="round" stroke-linejoin="round" d="M15.2 4.8l4 4L8 20H4v-4L15.2 4.8zM13.5 6.5l4 4"/>' }
  };
  function renderModePicker(){
    const host=$('#modeList'); host.innerHTML='';
    (S.operator.modes||[]).forEach(m=>{
      const meta=MODE_CARDS[m]; if(!meta) return;
      const b=document.createElement('button'); b.className='menu-card';
      b.innerHTML=`<span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">${meta.icon}</svg></span>
        <span class="tx"><b>${meta.title}</b><span>${meta.sub}</span></span>
        <span class="chev"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></span>`;
      b.addEventListener('click', ()=> chooseMode(m));
      host.appendChild(b);
    });
    go('s-mode');
  }
  async function chooseMode(mode){
    try{
      const r=await api('POST','/kiosk/mode',{ mode });
      if(r.ok){ applySession(r.data.session); enterMode(mode); }
      else toast(r.data.error||'Não foi possível abrir esse modo.',true);
    }catch(e){ if(!e.handled) toast('Falha de conexão.',true); }
  }

  /* ---------- CPF ---------- */
  let cpfBuf='';
  function resetCpf(){ cpfBuf=''; renderCpf(); }
  buildKeypad($('#cpfKeypad'),
    d=>{ if(cpfBuf.length<11){ cpfBuf+=d; renderCpf(); } },
    ()=>{ cpfBuf=cpfBuf.slice(0,-1); renderCpf(); });
  function renderCpf(){ const el=$('#cpfVal'); el.textContent=fmtCpf(cpfBuf); el.classList.toggle('placeholder',cpfBuf.length===0);
    $('#cpfHint').textContent = cpfBuf.length===11?'Toque em Buscar':' '; $('#cpfNext').disabled=cpfBuf.length!==11; }
  resetCpf();
  $('#cpfNext').addEventListener('click', async ()=>{
    try{
      const r=await api('GET','/kiosk/freelancer/'+cpfBuf);
      if(r.ok && r.data.found){ S.freelancer=r.data.freelancer; openMenu(); }
      else { openCadastro(cpfBuf); }
    }catch(e){ if(!e.handled) toast('Falha de conexão.',true); }
  });

  /* ---------- Cadastro ---------- */
  function openCadastro(cpf){ S.newCpf=cpf; $$('#cadForm [data-f]').forEach(i=>{ if(i.dataset.f!=='nacionality') i.value=''; }); go('s-cadastro'); }
  $('#cadSave').addEventListener('click', async ()=>{
    const payload={cpf:S.newCpf}; let missing=false;
    $$('#cadForm [data-f]').forEach(i=>{ payload[i.dataset.f]=i.value.trim(); });
    ['name','rg','nacionality','civil_status','address','telephone'].forEach(f=>{ if(!payload[f]) missing=true; });
    if(missing){ toast('Preencha os campos obrigatórios (*).',true); return; }
    if(!payload.email) delete payload.email;
    try{
      const r=await api('POST','/kiosk/freelancer',payload);
      if(r.status===201){ S.freelancer=r.data.freelancer; toast('Freelancer cadastrado.'); openMenu(); }
      else { toast((r.data && (r.data.message || firstError(r.data)))||'Não foi possível cadastrar.',true); }
    }catch(e){ if(!e.handled) toast('Falha de conexão.',true); }
  });
  function firstError(data){ if(data && data.errors){ const k=Object.keys(data.errors)[0]; return data.errors[k][0]; } return data && data.error; }

  /* ---------- Menu ---------- */
  function openMenu(){
    const f=S.freelancer;
    $('#menuName').textContent=f.name; $('#menuCpf').textContent=fmtCpf(f.cpf); $('#menuAvatar').textContent=initials(f.name).toUpperCase();
    // Cadastro incompleto trava a geração de contrato: some o card de novo
    // contrato e mostra o aviso com o atalho para completar os dados.
    const incomplete = f.complete === false;
    const labels = f.missing_field_labels || [];
    $('#menuIncomplete').style.display = incomplete ? 'block' : 'none';
    $('#menuMissing').textContent = labels.length ? ('Faltam: '+labels.join(', ')+'.') : '';
    $('#cardNovo').style.display = incomplete ? 'none' : '';
    go('s-menu');
  }
  $('#cardNovo').addEventListener('click', startNovo);
  $('#cardContratos').addEventListener('click', openContratos);
  $('#cardCompletar').addEventListener('click', openCompletar);

  /* ---------- Completar cadastro ---------- */
  function openCompletar(){
    const f=S.freelancer;
    $$('#compForm [data-f]').forEach(i=>{ i.value = f[i.dataset.f] || ''; });
    go('s-completar');
  }
  $('#compSave').addEventListener('click', async ()=>{
    const f=S.freelancer; const payload={ cpf:f.cpf }; let missing=false;
    $$('#compForm [data-f]').forEach(i=>{ payload[i.dataset.f]=i.value.trim(); });
    ['name','rg','nacionality','civil_status','address','telephone'].forEach(k=>{ if(!payload[k]) missing=true; });
    if(missing){ toast('Preencha os campos obrigatórios (*).',true); return; }
    if(!payload.email) delete payload.email;
    try{
      const r=await api('PUT','/kiosk/freelancer/'+f.id,payload);
      if(r.ok){ S.freelancer=r.data.freelancer; toast('Cadastro atualizado.'); openMenu(); }
      else { toast((r.data && (r.data.message||firstError(r.data)))||'Não foi possível salvar.',true); }
    }catch(e){ if(!e.handled) toast('Falha de conexão.',true); }
  });

  /* ---------- Novo contrato ---------- */
  let nstep=0;
  async function startNovo(){
    S.draft={ day:null, dayIso:'', start:'', end:'', fn:null, loc:'' };
    if(!S.functions.length){ try{ const r=await api('GET','/kiosk/functions'); if(r.ok) S.functions=r.data; }catch(e){ if(e.handled) return; } }
    nstep=0; showStep(); go('s-novo');
  }
  function showStep(){
    $$('#s-novo .nstep').forEach(el=> el.style.display=(+el.dataset.step===nstep)?'block':'none');
    $$('#novoSteps i').forEach((el,i)=> el.classList.toggle('done', i<=nstep));
    $('#novoBack').textContent = nstep===0?'Cancelar':'Voltar';
    $('#novoNext').textContent = nstep===4?'Ver prévia':'Continuar';
    validateStep();
  }
  function validateStep(){ const d=S.draft; let ok=false;
    if(nstep===0) ok=!!d.dayIso;
    if(nstep===1) ok=validTime(d.start);
    if(nstep===2) ok=validTime(d.end);
    if(nstep===3) ok=!!d.fn;
    if(nstep===4) ok=d.loc.trim().length>1;
    $('#novoNext').disabled=!ok;
  }
  $$('#s-novo [data-day]').forEach(b=> b.addEventListener('click', ()=>{
    $$('#s-novo [data-day]').forEach(x=>x.classList.remove('sel')); b.classList.add('sel');
    if(b.dataset.day==='outra'){ $('#dayCustom').style.display='block'; S.draft.dayIso=$('#dayCustom').value||''; }
    else { $('#dayCustom').style.display='none'; S.draft.dayIso = b.dataset.day==='hoje'?isoLocal(0):isoLocal(-1); }
    S.draft.day=b.dataset.day; validateStep();
  }));
  $('#dayCustom').addEventListener('change', ()=>{ S.draft.dayIso=$('#dayCustom').value; validateStep(); });

  function timeKeypad(container, field, valEl){
    buildKeypad(container,
      d=>{ let cur=digits(S.draft[field]); if(cur.length>=4) cur=''; cur+=d; setTime(field,cur,valEl); },
      ()=>{ setTime(field, digits(S.draft[field]).slice(0,-1), valEl); });
  }
  function setTime(field, dg, valEl){
    let disp='--:--';
    if(dg.length===1) disp=`${dg}_:__`; else if(dg.length===2) disp=`${dg}:__`;
    else if(dg.length===3) disp=`${dg.slice(0,2)}:${dg[2]}_`; else if(dg.length>=4) disp=`${dg.slice(0,2)}:${dg.slice(2,4)}`;
    S.draft[field] = dg.length>=4 ? `${dg.slice(0,2)}:${dg.slice(2,4)}` : (dg.length>=3?`${dg.slice(0,2)}:${dg.slice(2)}`:dg);
    valEl.textContent=disp; valEl.classList.toggle('placeholder', dg.length<4); validateStep();
  }
  timeKeypad($('#startKeypad'),'start',$('#startVal'));
  timeKeypad($('#endKeypad'),'end',$('#endVal'));

  function renderFnList(){ const c=$('#fnList'); c.innerHTML='';
    S.functions.forEach(f=>{ const b=document.createElement('button'); b.className='opt fn-opt'+(S.draft.fn&&S.draft.fn.id===f.id?' sel':'');
      b.innerHTML=`<span>${esc(f.name)}</span><span class="price">${brl(f.price)}/15min</span>`;
      b.addEventListener('click', ()=>{ S.draft.fn=f; renderFnList(); validateStep(); }); c.appendChild(b); }); }
  $('#locInput').addEventListener('input', ()=>{ S.draft.loc=$('#locInput').value; validateStep(); });

  $('#novoNext').addEventListener('click', ()=>{
    if(nstep<4){ nstep++; if(nstep===3) renderFnList(); if(nstep===4) $('#locInput').value=S.draft.loc||''; showStep(); }
    else showPrevia();
  });
  $('#novoBack').addEventListener('click', ()=>{ if(nstep===0) go('s-menu'); else { nstep--; showStep(); } });

  /* ---------- Prévia ---------- */
  function rrow(k,v){ return `<div class="rrow"><span class="k">${k}</span><span class="v">${v}</span></div>`; }
  function showPrevia(){
    const d=S.draft, r=calc(d.start,d.end,d.fn.price);
    const endIso = r.crosses ? nextDayIso(d.dayIso) : d.dayIso;
    let h=`<div class="head"><div class="fn">${esc(d.fn.name)}</div><div class="fl">${esc(S.freelancer.name)}</div></div>`;
    h+=rrow('Local', esc(d.loc));
    h+=rrow('Início', `${brFromIso(d.dayIso)} · ${d.start}`);
    h+=rrow('Término', `${brFromIso(endIso)} · ${d.end}`+(r.crosses?' <span style="color:var(--warning)">(vira o dia)</span>':''));
    if(r.paidMin!==r.dur){ h+=rrow('Duração real', fmtDur(r.dur)); h+=rrow('Horas pagas', `${fmtDur(r.paidMin)} <span class="note">(${r.blocks} blocos)</span>`); }
    else h+=rrow('Duração', `${fmtDur(r.dur)} <span class="note">(${r.blocks} blocos de 15 min)</span>`);
    h+=`<div class="rrow total"><span class="k">Valor a pagar</span><span class="v">${brl(r.price)}</span></div>`;
    $('#previaReceipt').innerHTML=h; $('#previaWarn').innerHTML=''; go('s-previa');
  }
  function servicePayload(){ const d=S.draft; return { freelancer_id:S.freelancer.id, function_freelancer_id:d.fn.id, location:d.loc.trim(), start_date:d.dayIso, start_time:d.start, end_time:d.end }; }
  $('#registrarBtn').addEventListener('click', ()=> submitService(false));
  /**
   * Acima do limite de 7 dias o servidor devolve 409 e o contrato só é gravado
   * com a matrícula e o PIN do coordenador do Comercial — não com os do
   * operador. O 401 diz, em `step`, se é a matrícula ou o PIN que está errado.
   */
  async function submitService(confirmWeekly, pin, matricula){
    const payload=servicePayload();
    // Sem matrícula quando a liberação é pelo código: ele vale para qualquer
    // coordenador do Comercial, então não há quem identificar.
    if(confirmWeekly){ payload.confirm_weekly_limit=true; payload.coordinator_matricula=matricula||null; payload.coordinator_pin=pin; }
    try{
      const r=await api('POST','/kiosk/service',payload);
      if(r.status===201){ applySession(r.data.session); toast('Contrato registrado · '+brl(r.data.service.price)); openMenu(); return true; }
      // Cadastro ficou incompleto (ex.: alterado noutra tela): abre o formulário
      // de completar em vez de deixar o contrato ser gravado sem dados.
      if(r.status===422 && r.data && r.data.incomplete_freelancer){ S.freelancer=r.data.freelancer; toast(r.data.error||'Cadastro incompleto.',true); openCompletar(); return false; }
      if(r.status===409){ openPin('weekly', r.data.message, r.data.coordinator_sector); return false; }
      if(r.status===401){ weeklyAuthFailed(r.data); return false; }
      toast((r.data && (r.data.message||firstError(r.data)))||'Não foi possível registrar.',true); return false;
    }catch(e){ if(!e.handled) toast('Falha de conexão.',true); return false; }
  }
  /** Erro na liberação: volta para a matrícula ou só limpa o segredo, conforme o passo. */
  function weeklyAuthFailed(data){
    const msg=(data && data.error)||'Não foi possível liberar.';
    if(!data || data.step==='pin'){ $('#pinOpHint').textContent=msg; resetPinOp(); return; }
    showPinMatStep(); $('#pinMatHint').textContent=msg;
  }

  /* ---------- Contratos ---------- */
  async function openContratos(){
    $('#ctName').textContent=S.freelancer.name;
    const list=$('#clist'); list.innerHTML='<p class="subtitle" style="text-align:center;padding:20px 0">Carregando…</p>';
    go('s-contratos');
    try{
      const r=await api('GET',`/kiosk/freelancer/${S.freelancer.id}/services`);
      if(!r.ok){ list.innerHTML='<p class="subtitle" style="text-align:center;padding:20px 0">Erro ao carregar.</p>'; return; }
      renderContratos(r.data);
    }catch(e){ if(e.handled) return; }
  }
  function renderContratos(items){
    const list=$('#clist'); list.innerHTML='';
    if(!items.length){ list.innerHTML='<p class="subtitle" style="text-align:center;padding:30px 0">Nenhum contrato aguardando assinatura.</p>'; return; }
    items.forEach(c=>{
      const chip = c.status_label==='Não assinado' ? '<span class="chip unsigned">Não assinado</span>' : `<span class="chip await">${esc(c.status_label)}</span>`;
      const el=document.createElement('div'); el.className='contract';
      el.innerHTML=`<div class="row1"><span class="fn">${esc(c.function||'—')}</span>${chip}</div>
        <div class="loc">${esc(c.location)}</div>
        <div class="when"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M5 11h14M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z"/></svg>
        ${c.start_date_br} · ${c.start_time}–${c.end_time} · <b>${brl(c.price)}</b></div>
        <div class="acts"><button class="btn btn-primary" data-sign>Assinar</button></div>`;
      el.querySelector('[data-sign]').addEventListener('click', ()=> openSign(c));
      list.appendChild(el);
    });
  }

  /* ---------- Fila do coordenador ---------- */
  async function openCoord(){
    const list=$('#coordList'); list.innerHTML='<p class="subtitle" style="text-align:center;padding:20px 0">Carregando…</p>';
    go('s-coord');
    try{
      const r=await api('GET','/kiosk/coordinator/services');
      if(!r.ok){ list.innerHTML='<p class="subtitle" style="text-align:center;padding:20px 0">Erro ao carregar.</p>'; return; }
      renderCoord(r.data);
    }catch(e){ if(e.handled) return; list.innerHTML='<p class="subtitle" style="text-align:center;padding:20px 0">Erro ao carregar.</p>'; }
  }
  $('#coordReload').addEventListener('click', openCoord);
  function renderCoord(items){
    const list=$('#coordList'); list.innerHTML='';
    if(!items.length){ list.innerHTML='<p class="subtitle" style="text-align:center;padding:30px 0">Nenhum contrato aguardando sua assinatura.</p>'; return; }
    items.forEach(c=>{
      const who = c.freelancer ? c.freelancer.name : '—';
      const el=document.createElement('div'); el.className='contract';
      el.innerHTML=`<div class="row1"><span class="fn">${esc(who)}</span><span class="chip await">${esc(c.status_label)}</span></div>
        <div class="loc">${esc(c.function||'—')} · ${esc(c.location)}</div>
        <div class="when"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M5 11h14M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z"/></svg>
        ${c.start_date_br} · ${c.start_time}–${c.end_time} · <b>${brl(c.price)}</b></div>
        ${c.freelancer_signed_at_br?`<div class="when">Freelancer assinou em ${c.freelancer_signed_at_br}</div>`:''}
        <div class="acts"><button class="btn btn-primary" data-sign>Conferir e assinar</button></div>`;
      el.querySelector('[data-sign]').addEventListener('click', ()=> openCoordSign(c));
      list.appendChild(el);
    });
    // A fila vem limitada pelo servidor; avisa que pode haver mais atrás.
    if(items.length>=50){
      const p=document.createElement('p'); p.className='subtitle'; p.style.textAlign='center';
      p.textContent='Mostrando os 50 contratos mais antigos. Assine e atualize a lista para ver os próximos.';
      list.appendChild(p);
    }
  }

  /* ---------- Lote de aprovação ----------
     O coordenador monta o lote aqui e envia; quem aprova é a gerência, e só
     pela web — o tablet não tem tela de aprovação de propósito. */
  let loteSel = [];
  $('#coordLote').addEventListener('click', openLote);
  $('#loteBack').addEventListener('click', ()=> openCoord());

  async function openLote(){
    $('#loteSector').textContent = S.operator && S.operator.coordinator_sector || 'Comercial';
    loteSel = [];
    $('#loteSum').innerHTML='<span>Carregando…</span>';
    $('#loteDraft').innerHTML=''; $('#loteAvail').innerHTML='';
    go('s-lote');
    try{
      const r=await api('GET','/kiosk/coordinator/batch');
      if(!r.ok){ $('#loteSum').innerHTML='<span>Erro ao carregar.</span>'; return; }
      renderLote(r.data.draft, r.data.available);
    }catch(e){ if(e.handled) return; $('#loteSum').innerHTML='<span>Erro ao carregar.</span>'; }
  }

  function loteLine(c){
    return `<div class="row1"><span class="fn">${esc(c.freelancer||'—')}</span></div>
      <div class="loc">${esc(c.function||'—')} · ${esc(c.location)}</div>
      <div class="when">${c.start_date_br} · ${c.start_time}–${c.end_time} · <b>${brl(c.price)}</b></div>
      ${c.rejection_reason?`<div class="reject-note">Recusado antes: ${esc(c.rejection_reason)}</div>`:''}`;
  }

  function renderLote(draft, available){
    const count = draft ? draft.count : 0, total = draft ? draft.total : 0;
    $('#loteSum').innerHTML = count
      ? `<div><b>${count} contrato${count>1?'s':''}</b><span> no lote</span></div><div class="tot">${brl(total)}</div>`
      : `<div><b>Lote vazio</b><span> selecione abaixo para incluir</span></div>`;

    const dh=$('#loteDraft'); dh.innerHTML='';
    (draft ? draft.services : []).forEach(c=>{
      const el=document.createElement('div'); el.className='contract';
      el.innerHTML=loteLine(c)+`<div class="acts"><button class="btn btn-ghost" data-rm>Retirar do lote</button></div>`;
      el.querySelector('[data-rm]').addEventListener('click', ()=> removeFromLote(c.id));
      dh.appendChild(el);
    });

    const ah=$('#loteAvail'); ah.innerHTML='';
    if(!available.length){
      ah.innerHTML='<p class="subtitle" style="text-align:center;padding:20px 0">Nenhum contrato disponível para incluir.</p>';
    } else {
      available.forEach(c=>{
        const el=document.createElement('div'); el.className='contract pick';
        el.innerHTML=loteLine(c);
        el.addEventListener('click', ()=>{
          const i=loteSel.indexOf(c.id);
          if(i>=0) loteSel.splice(i,1); else loteSel.push(c.id);
          el.classList.toggle('sel', i<0);
          syncLoteButtons();
        });
        ah.appendChild(el);
      });
    }
    syncLoteButtons(count);
  }
  function syncLoteButtons(count){
    $('#loteAdd').disabled = loteSel.length===0;
    $('#loteAdd').textContent = loteSel.length ? `Incluir ${loteSel.length} no lote` : 'Incluir selecionados';
    if(count!==undefined) $('#loteSend').disabled = count===0;
  }

  $('#loteAdd').addEventListener('click', async ()=>{
    if(!loteSel.length) return;
    try{
      const r=await api('POST','/kiosk/coordinator/batch/items',{ services:loteSel });
      if(r.ok){ toast(r.data.added===1?'1 contrato incluído.':`${r.data.added} contratos incluídos.`); loteSel=[]; openLote(); }
      else toast(r.data.error||'Não foi possível incluir.',true);
    }catch(e){ if(!e.handled) toast('Falha de conexão.',true); }
  });

  async function removeFromLote(id){
    try{
      const r=await api('DELETE','/kiosk/coordinator/batch/items/'+id);
      if(r.ok){ toast('Contrato retirado do lote.'); openLote(); }
      else toast(r.data.error||'Não foi possível retirar.',true);
    }catch(e){ if(!e.handled) toast('Falha de conexão.',true); }
  }

  $('#loteSend').addEventListener('click', ()=> openPin('send-batch'));
  async function submitSendBatch(pin){
    try{
      const r=await api('POST','/kiosk/coordinator/batch/send',{ pin });
      if(r.ok){ applySession(r.data.session);
        showSuccess('Lote enviado', `${r.data.batch.count} contrato(s) · ${brl(r.data.batch.total)} enviados para a aprovação da gerência.`, openCoord); }
      else if(r.status===401){ $('#pinOpHint').textContent='PIN inválido.'; resetPinOp(); }
      else { toast(r.data.error||'Não foi possível enviar o lote.',true); openLote(); }
    }catch(e){ if(!e.handled) toast('Falha de conexão.',true); resetPinOp(); }
  }

  /* ---------- Documento base + assinatura posicionada ---------- */
  const MESES=['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
  function money(n){ return Number(n).toFixed(2).replace('.',','); }
  function longToday(){ const t=new Date(); return `${t.getDate()} de ${MESES[t.getMonth()]} de ${t.getFullYear()}`; }
  function longFromIso(iso){ if(!iso) return longToday(); const [y,m,d]=iso.split('-').map(Number); return `${d} de ${MESES[m-1]} de ${y}`; }

  const SIG_SLOT = `<div class="sig-slot" id="sigSlot"><canvas id="sigCanvas"></canvas><div class="sig-ph" id="sigPh">Assine aqui com o dedo</div></div>`;

  /**
   * Documento base = modelo do "Contrato Autônomo de Serviços de Freelancer"
   * do Clube dos Funcionários da CSN. As variáveis ((...)) do modelo são
   * preenchidas com os dados do freelancer (f) e do serviço (c).
   *
   * `role` diz qual dos dois campos recebe o canvas: 'freelancer' assina no
   * campo do CONTRATADO; 'coordinator' assina no do CONTRATANTE e já vê o
   * traço do freelancer no campo de baixo. Mantido em sincronia com o parcial
   * do painel (freelancer/services/partials/contract-document.blade.php).
   */
  function buildDocument(c, f, role){
    const nome = esc(f.name);
    const cpf = fmtCpf(f.cpf);
    const asCoord = role==='coordinator';
    // A data do contrato é a da assinatura do freelancer (ou hoje, se ainda não assinou).
    const dataDoc = asCoord ? longFromIso(c.freelancer_signed_date) : longToday();

    const blocoContratante = asCoord
      ? `${SIG_SLOT}
            <div class="doc-sign-line"></div>
            <div class="doc-sign-name">CLUBE DOS FUNCIONARIOS DA CSN</div>
            <div class="doc-sign-role">CONTRATANTE · ${esc(S.operator?S.operator.name:'')}</div>`
      : `<div class="doc-sign-empty"></div>
            <div class="doc-sign-line"></div>
            <div class="doc-sign-name">CLUBE DOS FUNCIONARIOS DA CSN</div>
            <div class="doc-sign-role">CONTRATANTE — assinatura do coordenador (pendente)</div>`;

    const marcaFreelancer = asCoord
      ? (c.freelancer_signature_url
          ? `<div class="doc-sign-img"><img src="${esc(c.freelancer_signature_url)}" alt="Assinatura do freelancer"></div>`
          : `<div class="doc-sign-empty"></div>`)
      : SIG_SLOT;
    // Contratos assinados pelo bot (antes do kiosk) não têm traço: o aviso evita
    // que o coordenador ache que a assinatura se perdeu.
    const notaFreelancer = (asCoord && c.freelancer_signed_at_br)
      ? `<div class="doc-sign-note">Assinado em ${esc(c.freelancer_signed_at_br)}${c.freelancer_signature_url?'':' · registrado sem desenho'}</div>`
      : '';

    return `
    <div class="doc" id="docSheet">
      <div class="doc-header-img">
        <img src="${KIOSK.headerImg}" alt="Clube dos Funcionários">
      </div>
      <div class="doc-title">Contrato Autônomo de Serviços de Freelancer</div>
      <div class="doc-body">
        <p>Por este particular instrumento contratual de serviço autônomo de freelancer, firmado entre as partes, de um lado, <b>CLUBE DOS FUNCIONARIOS DA COMPANHIA SIDERURGICA NACIONAL</b>, empresa estabelecida na Rua - General Oswaldo Pinto da Veiga, 231, Volta Redonda – RJ, a seguir denominada simplesmente CONTRATANTE, e, de outro lado <b>${nome}</b>, ${esc(f.nacionality||'—')}, ${esc(f.civil_status||'—')}, titular do CPF: ${cpf} e do RG nº ${esc(f.rg||'—')}, residente e domiciliado ${esc(f.address||'—')} a seguir denominado simplesmente FREELANCER, fica justo e acordado o contrato de serviço autônomo freelancer nos seguintes termos:</p>
        <p><b>1- DO OBJETO:</b> O objeto do presente contrato trata-se da prestação de serviços, na modalidade de trabalho autônomo, sem vínculo de emprego, pelo FREELANCER, ao CONTRATANTE, conforme artigo 442-B, da CLT. O (a) FREELANCER (a) <b>${esc(c.function||'—')}</b> com todas as atribuições que lhe são peculiares, bem como as que vierem a ser designadas por meio de instruções do CONTRATANTE.</p>
        <p><b>2- DO VALOR:</b> O CONTRATANTE paga, neste ato, ao FREELANCER, pelos serviços ora prestados, o valor de <b>R$ ${money(c.price)}</b>, por dia, previamente acordado, no horário de <b>${c.start_time}</b> ás <b>${c.end_time}</b> servindo a assinatura no presente termo, como recibo do pagamento.</p>
        <p><b>3- DO PRAZO DE VIGÊNCIA:</b> O presente contrato de serviços de freelancer tem a validade de 1 (Um) dia, no qual, ao final, o serviço do FREELANCER já deverá ter se concluído, ficando as partes compromissadas até o termino do contrato. O prazo terá início na data de <b>${c.start_date_br}</b> sendo regido por tempo determinado, finalizando na data de <b>${c.end_date_br}</b>.</p>
        <p><b>4- Da Ausência de Vínculo Empregatício:</b> A prestação de serviços estabelecida no presente contrato tem natureza autônoma (cível), de forma que não implica em qualquer vínculo empregatício do FREELANCER pelos serviços prestados ao CONTRATANTE, uma vez que eventuais e sem a subordinação, exigidos para caracterização do vínculo de emprego (artigo 3º da CLT).</p>
        <p><b>5- DOS DESCONTOS:</b> O CONTRATANTE poderá descontar dos haveres do FREELANCER, além dos descontos legais ou expressamente autorizados, os prejuízos por ele causados, por dolo ou culpa, sem prejuízo da penalidade que a ação ou omissão comportar.</p>
        <p><b>6-</b> O FREELANCER deve se portar de forma adequada quando da prestação dos serviços, respeitando as orientações quanto ao uso do celular no horário de prestação dos serviços, atrasos, indisciplinas, devendo respeitar o contido nos seus regimentos internos e ao senso comum de educação e urbanidade.</p>
        <p><b>7-</b> Em caso de o FREELANCER exercer o serviço contratado por período superior a 6 (Seis) horas diárias, o CONTRATANTE, por livre e espontânea vontade, fornecerá ao FREELANCER uma refeição diária, sem que haja desconto do valor previsto na cláusula 2.</p>
        <p><b>8- DO FORO DE ELEIÇÃO:</b> As partes elegem o foro de Volta Redonda, como único competente para dirimir quaisquer litígios oriundos do presente contrato.</p>
        <p>E assim por estarem de pleno acordo com o contido neste instrumento, CONTRATANTE e FREELANCER o firmam consoante os ditames legais.</p>
        <p class="doc-place"><b>Volta Redonda-RJ, ${dataDoc}</b></p>
        <div class="doc-signatures">
          <div class="doc-sign-block">
            ${blocoContratante}
          </div>
          <div class="doc-sign-block">
            ${marcaFreelancer}
            <div class="doc-sign-line"></div>
            <div class="doc-sign-name">${nome}</div>
            <div class="doc-sign-role">FREELANCER · CPF ${cpf}</div>
            ${notaFreelancer}
          </div>
        </div>
      </div>
      <div class="doc-footer-img">
        <img src="${KIOSK.footerImg}" alt="Endereços e contatos do Clube dos Funcionários">
      </div>
    </div>`;
  }
  /** Assinatura do freelancer, conduzida pelo operador. */
  function openSign(c){
    $('#signEyebrow').textContent='Assinatura do freelancer';
    $('#signSub').textContent='Role o contrato e assine no campo do Contratado. A assinatura é definitiva.';
    openDocument(c, S.freelancer, 'freelancer');
  }

  /** Assinatura do coordenador, sobre um contrato que o freelancer já assinou. */
  function openCoordSign(c){
    $('#signEyebrow').textContent='Assinatura do coordenador';
    $('#signSub').textContent='Confira os dados e assine no campo do Contratante. A assinatura é definitiva e libera o contrato para entrar num lote de aprovação.';
    openDocument(c, c.freelancer, 'coordinator');
  }

  function openDocument(c, f, role){
    S.signing=c; S.signature=null;
    $('#docHost').innerHTML=buildDocument(c, f, role);
    $('#sigConfirm').disabled=true;
    bindCanvas();
    go('s-assinar');
    requestAnimationFrame(()=>{ sizeCanvas(); });
  }
  $('#sigCancel').addEventListener('click', ()=> go(S.mode==='coordinator' ? 's-coord' : 's-contratos'));

  /* ---------- Signature canvas ---------- */
  let canvas=null, ctx=null, drawing=false, hasInk=false, last=null;
  function bindCanvas(){
    canvas=$('#sigCanvas'); ctx=canvas.getContext('2d'); drawing=false; hasInk=false;
    canvas.addEventListener('pointerdown', e=>{ drawing=true; last=ptr(e); e.preventDefault(); });
    canvas.addEventListener('pointermove', e=>{ if(!drawing) return; const p=ptr(e); ctx.beginPath(); ctx.moveTo(last.x,last.y); ctx.lineTo(p.x,p.y); ctx.stroke(); last=p; if(!hasInk){ hasInk=true; $('#sigPh').style.opacity='0'; $('#sigSlot').classList.add('filled'); $('#sigConfirm').disabled=false; } e.preventDefault(); });
  }
  function ptr(e){ const r=canvas.getBoundingClientRect(); return {x:e.clientX-r.left, y:e.clientY-r.top}; }
  window.addEventListener('pointerup', ()=> drawing=false);
  function sizeCanvas(){ if(!canvas) return; const rect=canvas.getBoundingClientRect(); if(!rect.width) return; const dpr=window.devicePixelRatio||1;
    canvas.width=rect.width*dpr; canvas.height=rect.height*dpr; ctx.setTransform(1,0,0,1,0,0); ctx.scale(dpr,dpr);
    ctx.lineJoin='round'; ctx.lineCap='round'; ctx.lineWidth=2.6; ctx.strokeStyle='#1a1512'; }
  $('#sigClear').addEventListener('click', ()=>{ if(!ctx) return; ctx.clearRect(0,0,canvas.width,canvas.height); hasInk=false; $('#sigPh').style.opacity='1'; $('#sigSlot').classList.remove('filled'); $('#sigConfirm').disabled=true; });
  $('#sigConfirm').addEventListener('click', ()=>{ if(!hasInk) return; S.signature=canvas.toDataURL('image/png'); openPin(S.mode==='coordinator'?'sign-coord':'sign'); });
  window.addEventListener('resize', ()=>{ if(current==='s-assinar') sizeCanvas(); });

  /* ---------- PIN (sign / sign-coord / send-batch / weekly) ---------- */
  let pinBuf='', pinMatBuf='', weeklyMsg='', weeklyVia=null;
  function resetPinOp(){ pinBuf=''; renderDots($('#pinDotsOp'),6,0); $('#pinOpHint').innerHTML='&nbsp;'; }
  function weeklyBanner(msg){ return `<div class="banner" style="margin:14px 0 0"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3l-8-14a2 2 0 0 0-3.4 0z"/></svg><div><b>Limite semanal excedido</b><p>${esc(msg||'')}</p></div></div>`; }
  function openPin(mode, extraMsg, sector){
    S.pinMode=mode; resetPinOp(); $('#pinExtra').innerHTML='';
    if(mode==='weekly'){
      // Quem libera não é quem está operando o tablet. Primeiro se decide como:
      // com o coordenador presente (matrícula + PIN dele) ou com o código que o
      // sistema manda para todos os coordenadores do Comercial.
      weeklyMsg=extraMsg||''; weeklyVia=null; pinMatBuf=''; renderPinMat();
      $('#pinMatSector').textContent=sector||'Comercial';
      $('#pinChoiceExtra').innerHTML=weeklyBanner(weeklyMsg);
      $('#pinMatExtra').innerHTML=weeklyBanner(weeklyMsg);
      $('#pinChoiceHint').innerHTML='&nbsp;'; $('#pinMatHint').innerHTML='&nbsp;';
      showPinChoiceStep(); go('s-pin'); return;
    }
    showPinPinStep();
    if(mode==='sign'){ $('#pinEyebrow').textContent='Confirmação do operador'; $('#pinTitle').textContent='Digite seu PIN'; $('#pinSub').textContent='A assinatura fica registrada como auxiliada por você'; $('#pinCancel').textContent='Voltar ao documento'; }
    else if(mode==='sign-coord'){ $('#pinEyebrow').textContent='Confirmação do operador'; $('#pinTitle').textContent='Digite seu PIN'; $('#pinSub').textContent='O contrato será assinado como coordenador, em seu nome'; $('#pinCancel').textContent='Voltar ao documento'; }
    else { $('#pinEyebrow').textContent='Confirmação do operador'; $('#pinTitle').textContent='Confirmar envio do lote'; $('#pinSub').textContent='O lote vai para a aprovação da gerência e não pode mais ser alterado'; $('#pinCancel').textContent='Voltar ao lote'; }
    go('s-pin');
  }
  // Um passo de cada vez dentro da tela de PIN.
  function showWeeklyStep(id, cancelLabel){
    ['pinChoiceStep','pinMatStep','pinPinStep'].forEach(s => $('#'+s).style.display = (s===id ? 'block' : 'none'));
    if(cancelLabel) $('#pinCancel').textContent=cancelLabel;
  }
  // Na escolha, "Voltar" abandona a liberação e devolve a prévia.
  function showPinChoiceStep(){ showWeeklyStep('pinChoiceStep','Cancelar'); }
  function showPinMatStep(){ showWeeklyStep('pinMatStep','Voltar'); }
  function showPinPinStep(){ showWeeklyStep('pinPinStep'); }
  function renderPinMat(){ const el=$('#pinMatVal'); el.textContent=pinMatBuf||'matrícula'; el.classList.toggle('placeholder',!pinMatBuf); $('#pinMatNext').disabled=pinMatBuf.length<1; }
  buildKeypad($('#pinMatKeypad'),
    d=>{ if(pinMatBuf.length<5){ pinMatBuf+=d; renderPinMat(); $('#pinMatHint').innerHTML='&nbsp;'; } },
    ()=>{ pinMatBuf=pinMatBuf.slice(0,-1); renderPinMat(); $('#pinMatHint').innerHTML='&nbsp;'; });
  renderPinMat();
  /** Mesmo teclado para os dois caminhos: o PIN dele ou o código do e-mail. */
  function goToCoordSecretStep(title, sub){
    resetPinOp();
    $('#pinEyebrow').textContent='Liberação do coordenador';
    $('#pinTitle').textContent=title;
    $('#pinSub').textContent=sub;
    $('#pinExtra').innerHTML=weeklyBanner(weeklyMsg);
    $('#pinCancel').textContent = weeklyVia==='code' ? 'Voltar' : 'Trocar matrícula';
    showPinPinStep();
  }
  $('#pinChoicePresent').addEventListener('click', ()=>{
    weeklyVia='pin'; pinMatBuf=''; renderPinMat(); $('#pinMatHint').innerHTML='&nbsp;'; showPinMatStep();
  });
  $('#pinMatNext').addEventListener('click', ()=>{
    goToCoordSecretStep('PIN do coordenador', 'Matrícula ' + pinMatBuf + ' · o PIN é do coordenador, não do operador');
  });
  /**
   * Nenhum coordenador no local: o sistema manda os mesmos 6 dígitos para TODOS
   * os coordenadores do Comercial e qualquer um deles dita. Não se escolhe
   * destinatário — quem opera não precisa saber quem está de plantão. O código
   * nunca volta para esta tela: só os e-mails mascarados.
   */
  $('#pinChoiceEmail').addEventListener('click', async ()=>{
    const btn=$('#pinChoiceEmail'); btn.disabled=true; const label=btn.textContent; btn.textContent='Enviando…';
    const d=S.draft;
    try{
      const r=await api('POST','/kiosk/service/weekly-limit-code',{
        freelancer_id:S.freelancer.id, start_date:d.dayIso });
      if(r.ok){
        weeklyVia='code'; pinMatBuf='';
        toast('Código enviado aos coordenadores do Comercial');
        goToCoordSecretStep('Código de liberação',
          'Enviado para ' + r.data.sent_to + ' · vale até ' + r.data.expires_at + ' · peça que ditem o número');
        return;
      }
      $('#pinChoiceHint').textContent=(r.data && r.data.error)||'Não foi possível enviar o código.';
    }catch(e){ if(!e.handled) $('#pinChoiceHint').textContent='Falha de conexão.'; }
    finally{ btn.textContent=label; btn.disabled=false; }
  });
  buildKeypad($('#pinKeypadOp'),
    d=>{ if(pinBuf.length<6){ pinBuf+=d; renderDots($('#pinDotsOp'),6,pinBuf.length); if(pinBuf.length===6) submitPin(); } },
    ()=>{ pinBuf=pinBuf.slice(0,-1); renderDots($('#pinDotsOp'),6,pinBuf.length); $('#pinOpHint').innerHTML='&nbsp;'; });
  $('#pinCancel').addEventListener('click', ()=>{
    if(S.pinMode==='sign'||S.pinMode==='sign-coord') return go('s-assinar');
    if(S.pinMode==='send-batch') return go('s-lote');
    // No limite semanal o "Voltar" recua um passo por vez, até a prévia. Pelo
    // código não há matrícula no meio: volta direto para a escolha.
    if($('#pinPinStep').style.display!=='none'){
      $('#pinMatHint').innerHTML='&nbsp;'; $('#pinChoiceHint').innerHTML='&nbsp;';
      return weeklyVia==='code' ? showPinChoiceStep() : showPinMatStep();
    }
    if($('#pinMatStep').style.display!=='none'){ $('#pinChoiceHint').innerHTML='&nbsp;'; return showPinChoiceStep(); }
    go('s-previa');
  });
  async function submitPin(){
    const pin=pinBuf;
    // Pelo código não vai matrícula: ele vale para qualquer coordenador.
    if(S.pinMode==='weekly'){ await submitService(true, pin, weeklyVia==='code' ? null : pinMatBuf); return; }
    if(S.pinMode==='sign-coord'){ await submitCoordSign(pin); return; }
    if(S.pinMode==='send-batch'){ await submitSendBatch(pin); return; }
    try{
      const r=await api('POST',`/kiosk/service/${S.signing.id}/sign`,{ pin, signature:S.signature });
      if(r.ok){ applySession(r.data.session); showSuccess('Contrato assinado', `Assinatura de ${S.freelancer.name} registrada, auxiliada por ${S.operator.name}. O atendimento será encerrado.`); }
      else if(r.status===401){ $('#pinOpHint').textContent='PIN inválido.'; resetPinOp(); }
      else if(r.status===409){ toast(r.data.error||'Contrato já assinado.',true); go('s-contratos'); }
      else { toast(firstError(r.data)||'Não foi possível assinar.',true); resetPinOp(); }
    }catch(e){ if(!e.handled) toast('Falha de conexão.',true); resetPinOp(); }
  }
  async function submitCoordSign(pin){
    const who = S.signing.freelancer ? S.signing.freelancer.name : 'o freelancer';
    try{
      const r=await api('POST',`/kiosk/service/${S.signing.id}/sign-coordinator`,{ pin, signature:S.signature });
      if(r.ok){ applySession(r.data.session);
        showSuccess('Contrato assinado', `Contrato de ${who} assinado por ${S.operator.name}. Já pode entrar num lote para a gerência.`, openCoord); }
      else if(r.status===401){ $('#pinOpHint').textContent='PIN inválido.'; resetPinOp(); }
      else if(r.status===409){ toast(r.data.error||'Contrato já assinado.',true); openCoord(); }
      else { toast(firstError(r.data)||'Não foi possível assinar.',true); resetPinOp(); }
    }catch(e){ if(!e.handled) toast('Falha de conexão.',true); resetPinOp(); }
  }

  /* ---------- Success ---------- */
  /** `after` decide para onde a tela volta; por padrão, próximo atendimento. */
  function showSuccess(title,msg,after){ $('#successTitle').textContent=title; $('#successMsg').textContent=msg; $('#success').classList.add('show');
    setTimeout(()=>{ $('#success').classList.remove('show'); S.freelancer=null; S.signing=null; S.signature=null;
      if(after) after(); else { resetCpf(); go('s-cpf'); } },2800); }

  /* ---------- Resume session on load ---------- */
  (async function init(){
    try{ const r=await api('GET','/kiosk/session');
      if(r.ok && r.data.active){ startSession(r.data.operator, r.data.session); }
    }catch(e){}
  })();

})();
</script>
@endverbatim
</body>
</html>
