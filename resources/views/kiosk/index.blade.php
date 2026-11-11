<!DOCTYPE html>
<html lang="pt-BR">
{{--
    Kiosk de contratos dos freelancers (/kiosk) — o tablet do atendimento.
    Autenticação própria (matrícula + PIN, ver KioskController), fora do layout
    do Lara. Desde 11/11/2026 a página é só o esqueleto:
      - telas em resources/views/kiosk/telas/*.blade.php (uma por etapa do fluxo);
      - visual em resources/css/kiosk.css (cores do Lara, faixa do Freelancers);
      - comportamento em resources/js/kiosk.js (o mesmo código que morava aqui).
--}}
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="theme-color" content="#54270e">
  <title>Contratos de freelancers — O Lara</title>
  <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />
  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|unbounded:500,600|jetbrains-mono:500,600&display=swap" rel="stylesheet" />
  <script>window.KIOSK = {
    csrf: @json(csrf_token()),
    headerImg: @json(asset('images/freelancer/cabecalho.png')),
    footerImg: @json(asset('images/freelancer/rodape.png')),
    // Tamanho mínimo da justificativa da alteração do valor apurado. Vem da
    // constante do model porque o script roda em bloco literal (sem Blade), e
    // um 10 digitado à mão aqui divergiria em silêncio da regra do servidor.
    salesReasonMin: {{ \App\Models\FreelancerService::SALES_ADJUSTMENT_REASON_MIN }},
    // Teto do valor fixo do contrato — o mesmo que o servidor confere.
    maxFixedPrice: {{ \App\Models\FreelancerService::MAX_FIXED_PRICE }},
  };</script>
  @vite(['resources/css/kiosk.css', 'resources/js/kiosk.js'])
</head>
<body>
<div class="app">

  {{-- Barra de cima: aparece depois da entrada (o JS liga e desliga). --}}
  <div class="topbar k-band" id="topbar" style="display:none">
    <div class="brand-logo"><img src="{{ asset('images/logo.png') }}" alt=""></div>
    <div class="who"><b id="opName">—</b><span id="ctxLine">Sessão de atendimento</span></div>
    <span class="pill" id="timerPill" title="Tempo restante da sessão">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v4l3 2"/></svg>
      <span id="timerVal">30:00</span>
    </span>
    <span class="pill" id="countPill" title="Contratos nesta sessão">0/5</span>
    <button class="icon-btn" id="themeBtn" aria-label="Alternar tema" title="Tema">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
    </button>
    <button class="icon-btn" id="exitBtn" aria-label="Encerrar sessão" title="Encerrar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7M13 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h7"/></svg>
    </button>
  </div>

  <div class="screens" id="screens">
    @include('kiosk.telas.entrada')
    @include('kiosk.telas.coordenacao')
    @include('kiosk.telas.atendimento')
    @include('kiosk.telas.contrato')
    @include('kiosk.telas.assinatura')
  </div>

  <div class="success" id="success" role="status">
    <div class="checkmark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg></div>
    <h2 id="successTitle">Contrato assinado</h2>
    <p id="successMsg">Assinatura registrada. Pronto para o próximo freelancer.</p>
  </div>

  <div class="toast" id="toast" role="status" aria-live="polite"></div>
</div>
</body>
</html>
