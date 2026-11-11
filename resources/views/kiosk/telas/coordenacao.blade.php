{{--
    Kiosk › Coordenação: contratos a assinar como contraparte e o lote para a gerência.
    Os ids e data-* são lidos por resources/js/kiosk.js: renomear aqui exige renomear lá.
--}}
<!-- ===== FILA DO COORDENADOR ===== -->
<section class="screen" id="s-coord">
  <div class="screen-body">
    <p class="eyebrow">Coordenação · <span id="coordSector">Comercial</span></p>
    <h2 class="title">Contratos a assinar</h2>
    <p class="subtitle">Assinados pelo freelancer, aguardando sua assinatura</p>
    <div class="clist mt" id="coordList"></div>
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

    <div class="lote-sum k-band" id="loteSum"></div>
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
