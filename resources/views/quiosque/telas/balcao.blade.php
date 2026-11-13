{{--
    Tablet de assinatura › as telas do atendimento (balcão e, no modo
    "menores", depois do documento gerado): espera com o leitor de QR, código
    digitado, formulário, leitura, identidade, aceite, traço, foto e conclusão.
    Os ids, as classes e os data-* são lidos pelo JS em quiosque/js/nucleo:
    renomear aqui exige renomear lá.
--}}
<!-- 1. Espera -->
<section class="screen on" id="tela-espera">
  <div class="wait">
    <div class="q-hero q-band">
      <div class="brand-logo"><img src="{{ asset('images/logo.png') }}" alt="CFCSN"></div>
      <p class="eyebrow">O Lara · Assinaturas</p>
      <h1>Assinatura de documentos</h1>
      <p class="lead">Aponte a câmera para o QR Code exibido pelo atendente.</p>
      <svg class="pen" viewBox="0 0 180 22" fill="none" aria-hidden="true"><path d="M2 15c14-10 22-12 26-6s-6 10 2 9 14-14 22-11-2 11 6 10 16-12 26-9 4 8 14 6 20-7 28-6 14 4 22 2 18-5 28-5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
    </div>
    <div class="wait-body">
      <div class="viewfinder"><span class="vf-c1"></span><span class="vf-c2"></span><div id="reader"></div></div>
      <div class="hint" id="dicaLeitor">Procurando o código…</div>
      <div class="err hidden" id="erroLeitor"></div>
      <div class="wait-actions">
        <button type="button" class="btn btn-ghost hidden" id="trocarCamera">Trocar de câmera</button>
        <button type="button" class="btn btn-ghost hidden" id="abrirCodigo">Digitar código</button>
        <!-- Só no balcão: o diretor entra com matrícula e PIN e vê os documentos dele. -->
        <button type="button" class="btn btn-ghost hidden" id="abrirDiretoria">Assinatura da Diretoria</button>
        <!-- Só no balcão: a Gerência entra com matrícula e PIN e valida os contratos (página própria). -->
        <button type="button" class="btn btn-ghost hidden" id="abrirGerencia">Validação de contratos (Gerência)</button>
      </div>
    </div>
  </div>
</section>

<!-- 1b. Entrada por código digitado (tablet sem câmera) -->
<section class="screen" id="tela-codigo">
  <div class="pad grow">
    <h2>Digite o código</h2>
    <p class="lead">Peça o código ao atendente. São 8 caracteres.</p>

    <div class="cpf-box">
      <input class="cpf-input code" id="codigoInput" inputmode="text" autocomplete="off" maxlength="8" readonly aria-label="Código">
      <div class="keypad wide" id="tecladoCodigo"></div>
    </div>

    <p class="note note-danger hidden mt-lg" id="erroCodigo"></p>
  </div>
  <div class="foot">
    <button type="button" class="btn btn-ghost" id="voltarEspera">Voltar</button>
    <button type="button" class="btn btn-primary" id="btnCodigo" disabled>Abrir documento</button>
  </div>
</section>

<!-- 1c. Formulário: o que o modelo pergunta a quem assina, antes da leitura -->
<section class="screen" id="tela-formulario">
  <div class="pad grow" id="formScroll">
    <h2>Antes de ler, responda</h2>
    <p class="lead">Suas respostas entram no documento que você vai ler e assinar em seguida.</p>
    <!-- Conferência do atendente: o recado de quando ele devolve as respostas para correção. -->
    <p class="note note-info hidden" id="avisoConferencia"></p>
    <div id="formCampos"></div>
    <p class="note note-danger hidden" id="erroFormulario"></p>
  </div>
  <div class="foot">
    <!-- Sempre há como voltar: aqui, o primeiro passo, voltar é sair do documento. -->
    <button type="button" class="btn btn-ghost" data-sair-documento>Voltar</button>
    <button type="button" class="btn btn-danger" data-recusar>Recusar</button>
    <button type="button" class="btn btn-primary" id="btnFormulario">Continuar</button>
  </div>
</section>

<!-- 2. Documento -->
<section class="screen" id="tela-documento">
  <div class="pad tight">
    <h2 id="docTitulo">Documento</h2>
    <p class="lead flush">Leia o documento até o fim para continuar.</p>
    <!-- Todos os signatários e quem já assinou: a assinatura de quem assinou também aparece no documento. -->
    <div class="signatarios hidden" id="docSignatarios" aria-label="Signatários deste documento"></div>
  </div>
  <div class="doc-scroll" id="docScroll"></div>
  <div class="doc-status" id="docStatus">Carregando o documento…</div>
  <div class="foot">
    <!-- Com perguntas, "Corrigir respostas" é o voltar; sem elas, "Voltar" sai do documento
         (no lote da diretoria, volta ao documento anterior). -->
    <button type="button" class="btn btn-ghost" id="btnVoltarDocumento">Voltar</button>
    <button type="button" class="btn btn-danger" data-recusar>Recusar</button>
    <button type="button" class="btn btn-ghost hidden" id="btnCorrigir">Corrigir respostas</button>
    <button type="button" class="btn btn-primary" id="btnLido" disabled>Role até o fim</button>
  </div>
</section>

<!-- 3. Identidade -->
<section class="screen" id="tela-identidade">
  <div class="pad grow">
    <h2>Confirme sua identidade</h2>
    <p class="lead" id="identidadeInstrucao">Digite os quatro primeiros dígitos do seu CPF.</p>

    <div class="cpf-box">
      <input class="cpf-input" id="cpfInput" inputmode="numeric" autocomplete="off" maxlength="11" readonly aria-label="CPF ou código">
      <div class="keypad" id="teclado"></div>
    </div>

    <!-- Só na conferência por código enviado por e-mail. O primeiro toque reenvia o código;
         o segundo abre o documento de identidade no lugar dele (13/11/2026). -->
    <button type="button" class="btn btn-ghost hidden mt-md" id="btnNaoRecebi">Não recebi o código</button>

    <div class="hidden mt-lg" id="docIdentidadeBox">
      <p class="note note-info">Sem o código, você pode seguir com um documento de identidade com foto
        (RG, CNH ou outro). Fotografe o documento ou escolha o arquivo.</p>
      <div class="mt-md" style="display:flex;gap:12px;flex-wrap:wrap">
        <button type="button" class="btn btn-primary" id="btnFotografarDocumento">Fotografar documento</button>
        <button type="button" class="btn btn-ghost" id="btnArquivoDocumento">Escolher arquivo</button>
      </div>
      <!-- `capture` abre a câmera traseira direto; funciona também sem HTTPS. -->
      <input type="file" class="hidden" id="inputFotoDocumento" accept="image/*" capture="environment">
      <input type="file" class="hidden" id="inputArquivoDocumento" accept="image/jpeg,image/png,application/pdf">
      <p class="note hidden mt-md" id="statusDocumento"></p>
    </div>

    <p class="note note-danger hidden mt-lg" id="erroIdentidade"></p>
  </div>
  <div class="foot">
    <button type="button" class="btn btn-ghost" id="btnVoltarIdentidade">Voltar</button>
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
      <span id="aceiteTexto">Li e concordo com os termos deste documento.</span>
    </label>

    <!-- Autorização da foto: obrigatória para continuar quando o modelo pede foto e o tablet tem câmera.
         O texto é posto pelo script, a partir de CFG.textoAutorizacaoFoto. -->
    <label class="accept hidden" id="fotoBox">
      <input type="checkbox" id="fotoCheck">
      <span id="fotoTexto"></span>
    </label>

    <label class="accept hidden" id="viaBox">
      <input type="checkbox" id="viaCheck">
      <span>Quero receber uma via assinada no meu e-mail cadastrado.</span>
    </label>

    <!-- Conferência do atendente: a assinatura espera a aprovação das respostas. -->
    <div class="note note-info hidden mt-lg" id="aguardaConferencia">
      Aguardando o atendente conferir as suas respostas. Assim que ele aprovar, você poderá continuar.
    </div>

    <div class="note note-info mt-lg" id="avisoFoto">
      Ao confirmar a assinatura, a foto é tirada pela câmera frontal do tablet e registrada
      junto com data, hora e local do atendimento, como prova da assinatura.
    </div>
  </div>
  <div class="foot">
    <button type="button" class="btn btn-ghost" id="btnVoltarAceite">Voltar</button>
    <button type="button" class="btn btn-danger" data-recusar>Recusar</button>
    <button type="button" class="btn btn-primary" id="btnAceite" disabled>Continuar</button>
  </div>
</section>

<!-- 5. Assinatura -->
<section class="screen" id="tela-assinatura">
  <div class="pad tight">
    <h2 id="sigTitulo">Assine no espaço abaixo</h2>
    <p class="lead flush" id="sigLead">Use o dedo ou a caneta do tablet.</p>
  </div>
  <div class="sig-wrap">
    <div class="sig-pad" id="sigPad">
      <canvas id="sigCanvas"></canvas>
      <div class="sig-line"></div>
      <div class="sig-label" id="sigNome"></div>
    </div>
  </div>
  <div class="foot">
    <button type="button" class="btn btn-ghost" id="btnVoltarAssinatura">Voltar</button>
    <button type="button" class="btn btn-ghost" id="btnLimpar">Limpar</button>
    <button type="button" class="btn btn-primary" id="btnAssinar" disabled>Confirmar assinatura</button>
  </div>
</section>

<!-- 6. Foto -->
<section class="screen" id="tela-foto">
  <div class="cam-wrap grow">
    <div class="cam-text">
      <h2 id="fotoTitulo">Registro da assinatura</h2>
      <p class="lead" id="fotoLead">Ajeite-se de frente para a câmera e toque em <b>Tirar foto</b>. A foto sai depois de 3 segundos.</p>
    </div>
    <div class="cam-view">
      <video id="camVideo" playsinline muted></video>
      <!-- Prévia: a foto tirada, antes de ser usada. -->
      <img id="fotoPrevia" class="hidden" alt="Foto tirada">
    </div>
    <div class="cam-side">
      <!-- A contagem só começa no toque: a pessoa decide quando está pronta. -->
      <button type="button" class="btn btn-primary cam-btn hidden" id="btnFoto">Tirar foto</button>
      <div class="cam-count hidden" id="camContagem">3</div>
      <p class="note note-danger hidden" id="erroFoto"></p>
    </div>
  </div>
  <div class="foot">
    <button type="button" class="btn btn-ghost" id="btnVoltarFoto">Voltar</button>
    <button type="button" class="btn btn-ghost hidden" id="btnOutraFoto">Tirar nova foto</button>
    <button type="button" class="btn btn-primary hidden" id="btnUsarFoto">Usar esta foto</button>
  </div>
</section>

<!-- 7. Conclusão -->
<section class="screen" id="tela-sucesso">
  <div class="done">
    <div class="check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg></div>
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
