{{--
    Kiosk › Entrada no tablet (matrícula + PIN) e a escolha do modo de quem é operador e coordenador.
    Os ids e data-* são lidos por resources/js/kiosk.js: renomear aqui exige renomear lá.
--}}
<!-- ===== LOGIN (matrícula + PIN) ===== -->
<section class="screen active" id="s-login">
  <div class="screen-body">
    <div class="k-hero k-band">
      <div class="brand-logo"><img src="{{ asset('images/logo.png') }}" alt="CFCSN"></div>
      <p class="eyebrow">O Lara · Freelancers</p>
      <h1>Contratos de freelancers</h1>
      <p>Registro do turno, conferência da chave PIX e assinatura no tablet.</p>
      <div class="k-punch" aria-hidden="true"><i class="on"></i><i class="on"></i><i class="on"></i><i></i><i></i><i></i><i></i><i></i></div>
    </div>

    <!-- passo 1: matrícula -->
    <div class="login-step" id="loginStep1">
      <h2 class="title">Entrar</h2>
      <p class="subtitle">Informe sua matrícula</p>
      <div class="display mt-lg"><div class="val placeholder" id="matVal">matrícula</div></div>
      <div class="keypad" id="matKeypad"></div>
      <div class="screen-foot">
        <button class="btn btn-primary" id="matNext" disabled>Continuar</button>
      </div>
    </div>

    <!-- passo 2: PIN -->
    <div class="login-step" id="loginStep2" style="display:none">
      <h2 class="title">PIN de acesso</h2>
      <p class="subtitle">Matrícula <b id="matEcho"></b> · digite seu PIN de 6 dígitos</p>
      <div class="dots" id="pinDotsLogin"></div>
      <div class="hint-err" id="loginHint">&nbsp;</div>
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
    <div id="modeList" class="mt-lg"></div>
  </div>
  <div class="screen-foot">
    <button class="btn-quiet btn" id="modeExit">Sair</button>
  </div>
</section>
