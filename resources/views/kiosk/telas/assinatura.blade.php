{{--
    Kiosk › Assinatura: conferência da chave PIX, documento com o campo de assinatura, PIN e jantar.
    Os ids e data-* são lidos por resources/js/kiosk.js: renomear aqui exige renomear lá.
--}}
<!-- ===== CONFERÊNCIA DA CHAVE PIX (passo obrigatório antes de assinar) ===== -->
<section class="screen" id="s-pix">
  <div class="screen-body">
    <p class="eyebrow">Antes de assinar</p>
    <h2 class="title">Confira a chave PIX</h2>
    <p class="subtitle">É para esta chave que o pagamento deste contrato vai. Mostre ao freelancer e pergunte se é a dele.</p>

    <div class="receipt" id="pixCard"></div>
    <div id="pixNote"></div>

    <!-- Correção da chave: só aparece quando o freelancer diz que está errada -->
    <div id="pixForm" style="display:none">
      <h3 class="lote-h">Nova chave PIX</h3>
      <p class="field-label">Tipo da chave</p>
      <div class="chipset" id="pixTypes"></div>
      <div class="frow" style="margin-top:18px">
        <span class="field-label" id="pixInputLabel">Chave</span>
        <input class="txt-input" id="pixInput" autocomplete="off" autocapitalize="off" spellcheck="false">
      </div>
      <div class="hint-err left" id="pixHint">&nbsp;</div>
    </div>
  </div>
  <div class="screen-foot" id="pixFootView">
    <button class="btn btn-primary" id="pixConfirm">A chave está correta</button>
    <button class="btn btn-ghost" id="pixWrong">A chave está errada · atualizar</button>
    <button class="btn-quiet btn" id="pixCancel">Cancelar</button>
  </div>
  <div class="screen-foot" id="pixFootEdit" style="display:none">
    <button class="btn btn-primary" id="pixSave">Salvar nova chave</button>
    <button class="btn-quiet btn" id="pixEditCancel">Manter a chave atual</button>
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
    <div class="btn-row">
      <button class="btn btn-ghost" id="sigClear">Limpar</button>
      <button class="btn btn-primary" id="sigConfirm" disabled>Confirmar assinatura</button>
    </div>
    <button class="btn-quiet btn" id="sigCancel">Cancelar</button>
  </div>
</section>

<!-- ===== PIN (assinar / liberar limite semanal) ===== -->
<section class="screen" id="s-pin">
  <div class="screen-body centered">

    <!-- passo 1, só no limite semanal: como o coordenador vai liberar -->
    <div id="pinChoiceStep" style="display:none">
      <div class="center">
        <p class="eyebrow">Liberação do coordenador</p>
        <h2 class="title">Quem libera é o Comercial</h2>
        <p class="subtitle">Só um coordenador do setor <b id="pinMatSector">Comercial</b> pode autorizar este registro</p>
      </div>
      <div id="pinChoiceExtra"></div>
      <div class="hint-err" id="pinChoiceHint">&nbsp;</div>
      <div class="mt-md">
        <button class="btn btn-primary" id="pinChoicePresent">O coordenador está aqui · usar o PIN dele</button>
        <button class="btn btn-ghost stack-sm" id="pinChoiceEmail">Ninguém presente · enviar código por e-mail</button>
      </div>
    </div>

    <!-- passo 2a (só no caminho do PIN): matrícula do coordenador -->
    <div id="pinMatStep" style="display:none">
      <div class="center">
        <p class="eyebrow">Liberação do coordenador</p>
        <h2 class="title">Matrícula do coordenador</h2>
        <p class="subtitle">Do coordenador do Comercial que está liberando</p>
      </div>
      <div id="pinMatExtra"></div>
      <div class="display mt-lg"><div class="val placeholder" id="pinMatVal">matrícula</div></div>
      <div class="hint-err" id="pinMatHint">&nbsp;</div>
      <div class="keypad" id="pinMatKeypad"></div>
      <div class="mt-md">
        <button class="btn btn-primary" id="pinMatNext" disabled>Continuar</button>
      </div>
    </div>

    <!-- passo 2: PIN -->
    <div id="pinPinStep">
      <div class="center">
        <p class="eyebrow" id="pinEyebrow">Confirmação do operador</p>
        <h2 class="title" id="pinTitle">Digite seu PIN</h2>
        <p class="subtitle" id="pinSub">A assinatura fica registrada como auxiliada por você</p>
      </div>
      <div id="pinExtra"></div>
      <div class="dots" id="pinDotsOp"></div>
      <div class="hint-err" id="pinOpHint">&nbsp;</div>
      <div class="keypad" id="pinKeypadOp"></div>
    </div>
  </div>
  <div class="screen-foot">
    <button class="btn-quiet btn" id="pinCancel">Voltar</button>
  </div>
</section>

<!-- ===== JANTAR (perguntado logo depois da assinatura) =====
     Turno de 6h ou mais que alcança a janela do jantar dá direito à refeição.
     Quem decide se esta tela aparece é o servidor (`needs_dinner_answer`);
     aqui só se registra o que o freelancer responder. -->
<section class="screen" id="s-janta">
  <div class="screen-body centered">
    <div class="center">
      <p class="eyebrow">Contrato assinado</p>
      <h2 class="title">Vai jantar?</h2>
      <p class="subtitle" id="jantarSub">Este turno dá direito à refeição. Pergunte ao freelancer: a cozinha prepara os pratos por esta resposta.</p>
    </div>
    <div class="receipt" id="jantarCard"></div>
    <div class="hint-err" id="jantarHint">&nbsp;</div>
  </div>
  <div class="screen-foot">
    <div class="btn-row">
      <button class="btn btn-ghost" id="jantarNao">Não vai jantar</button>
      <button class="btn btn-primary" id="jantarSim">Sim, vai jantar</button>
    </div>
    <button class="btn-quiet btn" id="jantarDepois">Responder depois</button>
  </div>
</section>
