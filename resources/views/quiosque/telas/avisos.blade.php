{{--
    Tablet de assinatura › os diálogos sobre qualquer tela: recusa, sair do
    documento, inatividade e erro. Ids lidos por quiosque/js/nucleo.
--}}
<!-- Recusa -->
<div class="sheet" id="sheetRecusa">
  <div class="card">
    <h3>Recusar a assinatura</h3>
    <p>O documento não será assinado. Se quiser, diga o motivo — é opcional.</p>
    <textarea id="motivoRecusa" placeholder="Motivo (opcional)"></textarea>
    <div class="row">
      <button type="button" class="btn btn-ghost" id="cancelarRecusa">Voltar</button>
      <button type="button" class="btn btn-primary danger" id="confirmarRecusa">Recusar</button>
    </div>
  </div>
</div>

<!-- Voltar do primeiro passo: sair do documento sem assinar nem recusar -->
<div class="sheet" id="sheetSair">
  <div class="card">
    <h3>Sair deste documento?</h3>
    <p id="textoSair">O documento não será assinado agora, e continua pendente.</p>
    <div class="row">
      <button type="button" class="btn btn-ghost" id="cancelarSair">Continuar aqui</button>
      <button type="button" class="btn btn-primary" id="confirmarSair">Sair</button>
    </div>
  </div>
</div>

<!-- Aviso de inatividade -->
<div class="sheet" id="sheetInatividade">
  <div class="card">
    <h3>Ainda está aí?</h3>
    <p id="textoInatividade">A sessão será encerrada em instantes por inatividade.</p>
    <div class="row">
      <button type="button" class="btn btn-primary" id="continuarSessao">Continuar</button>
    </div>
  </div>
</div>

<!-- Erro genérico -->
<div class="sheet" id="sheetErro">
  <div class="card">
    <h3 id="erroTitulo">Não foi possível continuar</h3>
    <p id="erroTexto"></p>
    <div class="row">
      <button type="button" class="btn btn-primary" id="fecharErro">Entendi</button>
    </div>
  </div>
</div>
