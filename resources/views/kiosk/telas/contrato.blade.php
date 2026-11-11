{{--
    Kiosk › Contrato: novo turno (passos), prévia, lista, aditivo e comissão de venda.
    Os ids e data-* são lidos por resources/js/kiosk.js: renomear aqui exige renomear lá.
--}}
<!-- ===== NOVO CONTRATO ===== -->
<section class="screen" id="s-novo">
  <div class="screen-body">
    <div class="steps" id="novoSteps"><i class="done"></i><i></i><i></i><i></i><i></i><i></i></div>
    <div class="nstep" data-step="0">
      <h2 class="title">Quando o turno começou?</h2>
      <p class="subtitle">Dia em que o serviço teve início</p>
      <div class="chipset mt">
        <button class="opt" data-day="hoje">Hoje</button>
        <button class="opt" data-day="ontem">Ontem</button>
      </div>
      <input type="date" class="txt-input" id="dayCustom" style="display:none;margin-top:12px">
    </div>
    <div class="nstep" data-step="1" style="display:none">
      <h2 class="title">Hora de início</h2>
      <p class="subtitle">Quando o freelancer começou</p>
      <div class="display mt-lg"><div class="val placeholder" id="startVal">--:--</div></div>
      <div class="keypad" id="startKeypad"></div>
    </div>
    <div class="nstep" data-step="2" style="display:none">
      <h2 class="title">Hora de término</h2>
      <p class="subtitle">Se for menor que o início, o turno vira o dia</p>
      <div class="display mt-lg"><div class="val placeholder" id="endVal">--:--</div></div>
      <div class="keypad" id="endKeypad"></div>
    </div>
    <div class="nstep" data-step="3" style="display:none">
      <h2 class="title">Função</h2>
      <p class="subtitle">Selecione o serviço executado</p>
      <div class="chipset col mt" id="fnList"></div>
    </div>
    <div class="nstep" data-step="4" style="display:none">
      <h2 class="title">Local ou evento</h2>
      <p class="subtitle">Onde o serviço foi prestado</p>
      <input type="text" class="txt-input mt" id="locInput" placeholder="Ex.: Confraternização — Salão Nobre" autocomplete="off">
      <label class="field-label gap" for="descInput">Descrição / justificativa <span class="optional">(opcional)</span></label>
      <textarea class="txt-input" id="descInput" rows="3" maxlength="2000" placeholder="Esclarecimentos sobre o serviço. Não vai ao contrato." style="min-height:96px" autocomplete="off"></textarea>
    </div>
    <div class="nstep" data-step="5" style="display:none">
      <h2 class="title">Valor do contrato</h2>
      <p class="subtitle">O padrão é por horas, calculado pela função e pelo período</p>
      <div class="chipset col mt" id="pricingModes"></div>
      <div id="fixedBox" style="display:none">
        <div class="display mt-lg"><div class="val placeholder" id="fixedVal">R$ 0,00</div></div>
        <div class="hint-err" id="fixedHint">&nbsp;</div>
        <div class="keypad" id="fixedKeypad"></div>
      </div>
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
    <p class="subtitle">Término calculado pelo servidor; o valor também, salvo no contrato de valor fixo</p>
    <div class="receipt" id="previaReceipt"></div>
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
    <p class="subtitle">Contratos a assinar e contratos assinados cujo turno mudou</p>
    <div class="clist mt" id="clist"></div>
  </div>
  <div class="screen-foot">
    <button class="btn-quiet btn" data-go="menu">Voltar ao menu</button>
  </div>
</section>

<!-- ===== ADITIVO (turno mudou depois da assinatura) ===== -->
<section class="screen" id="s-aditivo">
  <div class="screen-body">
    <p class="eyebrow">Aditivo de contrato</p>
    <div class="steps" id="aditSteps"><i class="done"></i><i></i><i></i></div>
    <div class="adit-base" id="aditBase"></div>

    <div class="astep" data-step="0">
      <h2 class="title">Novo horário de início</h2>
      <p class="subtitle">Como o turno ficou de fato</p>
      <div class="display mt-lg"><div class="val placeholder" id="aditStartVal">--:--</div></div>
      <div class="keypad" id="aditStartKeypad"></div>
    </div>
    <div class="astep" data-step="1" style="display:none">
      <h2 class="title">Novo horário de término</h2>
      <p class="subtitle">Se for menor que o início, o turno vira o dia</p>
      <div class="display mt-lg"><div class="val placeholder" id="aditEndVal">--:--</div></div>
      <div class="keypad" id="aditEndKeypad"></div>
    </div>
    <div class="astep" data-step="2" style="display:none">
      <h2 class="title">Local ou evento</h2>
      <p class="subtitle">Deixe como está se o local não mudou</p>
      <input type="text" class="txt-input mt" id="aditLocInput" autocomplete="off">
    </div>
  </div>
  <div class="screen-foot">
    <button class="btn btn-primary" id="aditNext" disabled>Continuar</button>
    <button class="btn-quiet btn" id="aditBack">Voltar</button>
  </div>
</section>

<!-- ===== PRÉVIA DO ADITIVO ===== -->
<section class="screen" id="s-adit-previa">
  <div class="screen-body">
    <p class="eyebrow">Confira antes de gerar</p>
    <h2 class="title">Prévia do aditivo</h2>
    <p class="subtitle">O aditivo substitui o contrato original: o valor abaixo passa a ser o único a pagar por este turno.</p>
    <div class="receipt" id="aditReceipt"></div>
    <div id="aditWarn"></div>
  </div>
  <div class="screen-foot">
    <button class="btn btn-primary" id="aditRegistrar">Gerar aditivo</button>
    <button class="btn-quiet btn" id="aditDescartar">Descartar</button>
  </div>
</section>

<!-- ===== COMISSÃO DE VENDA (aditivo do garçom, ao fim do expediente) ===== -->
<section class="screen" id="s-comissao">
  <div class="screen-body">
    <p class="eyebrow">Comissão de venda</p>
    <div class="steps" id="comSteps"><i class="done"></i><i></i><i></i></div>
    <div class="adit-base" id="comBase"></div>

    <div class="cstep" data-step="0">
      <h2 class="title">Critério da comissão</h2>
      <p class="subtitle">Como esta comissão foi combinada</p>
      <div class="chipset col mt" id="comMethods"></div>
    </div>
    <div class="cstep" data-step="1" style="display:none">
      <h2 class="title">Apurar vendas</h2>
      <p class="subtitle">Login do vendedor no MultiVendas e o período do turno. Já vêm preenchidos — corrija se precisar.</p>
      <div class="form-grid">
        <div class="frow"><span class="field-label">Login do vendedor</span>
          <input class="txt-input" id="comLogin" autocomplete="off" autocapitalize="off" spellcheck="false"></div>
        <div class="frow"><span class="field-label">Início do período</span>
          <input type="datetime-local" class="txt-input" id="comFrom"></div>
        <div class="frow"><span class="field-label">Fim do período</span>
          <input type="datetime-local" class="txt-input" id="comTo"></div>
      </div>
      <button class="btn btn-primary" id="comFetch" style="margin-top:18px">Consultar vendas</button>
      <div id="comReportBox"></div>
    </div>
    <div class="cstep" data-step="2" style="display:none">
      <h2 class="title">Valor vendido</h2>
      <p class="subtitle" id="comSalesSub">Total das vendas do turno</p>
      <div class="display mt-lg"><div class="val placeholder" id="comSalesVal">R$ 0,00</div></div>
      <div class="hint-err" id="comSalesHint">&nbsp;</div>
      <div class="keypad" id="comKeypad"></div>
    </div>
  </div>
  <div class="screen-foot">
    <button class="btn btn-primary" id="comNext" disabled>Continuar</button>
    <button class="btn-quiet btn" id="comBack">Voltar</button>
  </div>
</section>

<!-- ===== PRÉVIA DA COMISSÃO ===== -->
<section class="screen" id="s-com-previa">
  <div class="screen-body">
    <p class="eyebrow">Confira antes de gerar</p>
    <h2 class="title">Prévia da comissão</h2>
    <p class="subtitle">A comissão é paga <b>além</b> do contrato do turno, não no lugar dele.</p>
    <div class="receipt" id="comReceipt"></div>
    <div id="comWarn"></div>
    {{-- Alterou o valor que o MultiVendas apurou? Diga por quê. A justificativa
         não é log: ela é impressa na cláusula 2 do termo, ao lado do Anexo I
         com o relatório original, e é o que o freelancer lê antes de assinar
         um número diferente do apurado. --}}
    <div id="comReasonBox" style="display:none;margin-top:18px">
      <div class="frow">
        <span class="field-label">Justificativa da alteração *</span>
        <textarea class="txt-input" id="comReason" rows="3"
          placeholder="Ex.: venda cancelada após o fechamento do caixa, conferida com a coordenação."></textarea>
        <span class="hint" id="comReasonHint" style="font-size:13px;color:var(--ink-2);margin-top:7px">&nbsp;</span>
      </div>
    </div>
  </div>
  <div class="screen-foot">
    <button class="btn btn-primary" id="comRegistrar">Gerar comissão</button>
    <button class="btn-quiet btn" id="comDescartar">Descartar</button>
  </div>
</section>
