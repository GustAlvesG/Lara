{{--
    Telas da Assinatura da Diretoria, incluídas em quiosque/index.blade.php só
    no modo balcão. A lógica está em quiosque/js/diretoria.

    Ordem: espera → "Assinatura da Diretoria" → matrícula + PIN → lista dos
    documentos pendentes → [quiosque: leitura, aceite, traço, (visto), foto] →
    volta à lista enquanto a entrada do diretor valer.

    Na lista (26/10/2026): marcar vários e "Assinar selecionados" (lote: lê um
    por um, um aceite, um traço, uma foto) e a aba "Já assinados", para
    acompanhar o que falta e a via final.
--}}
@verbatim
    <!-- D1. Entrada do diretor: matrícula + PIN do Lara -->
    <section class="screen" id="tela-d-entrar">
      <div class="pad grow">
        <h2>Assinatura da Diretoria</h2>
        <p class="lead">Entre com a sua matrícula e o seu PIN do Lara.</p>

        <div class="q">
          <label class="q-title" for="dMatricula">Matrícula</label>
          <input class="q-input" type="text" id="dMatricula" inputmode="numeric" autocomplete="off"
                 autocapitalize="off" spellcheck="false" maxlength="20">
        </div>
        <div class="q">
          <label class="q-title" for="dPin">PIN</label>
          <input class="q-input" type="password" id="dPin" inputmode="numeric" autocomplete="off" maxlength="20">
        </div>

        <p class="note note-info">Ainda não tem PIN? Cadastre no seu Perfil, no Lara.</p>
        <p class="note note-danger hidden" id="dErroEntrar" style="margin-top:14px;"></p>
      </div>
      <div class="foot">
        <button type="button" class="btn btn-ghost" id="dVoltar">Voltar</button>
        <button type="button" class="btn btn-primary" id="dBtnEntrar">Entrar</button>
      </div>
    </section>

    <!-- D2. Documentos do diretor: pendentes (avulso ou em lote) e já assinados -->
    <section class="screen" id="tela-d-lista">
      <div class="pad grow">
        <h2 id="dOla">Seus documentos</h2>
        <div class="abas">
          <button type="button" class="aba on" id="dAbaPendentes">Pendentes</button>
          <button type="button" class="aba" id="dAbaAssinados">Já assinados</button>
        </div>

        <div id="dPainelPendentes">
          <p class="lead" id="dResumo">Toque no documento para ler e assinar.</p>
          <button type="button" class="btn btn-ghost hidden" id="dMarcarTodos" style="width:100%;margin-bottom:12px;min-height:56px;font-size:16px;">Marcar todos</button>
          <div class="lista" id="dDocumentos"></div>
          <p class="note note-info hidden" id="dVazio">Nenhum documento pendente da sua assinatura.</p>
        </div>

        <div id="dPainelAssinados" class="hidden">
          <p class="lead">Os que você assinou nos últimos 90 dias e em que pé estão.</p>
          <div class="lista" id="dAssinados"></div>
          <p class="note note-info hidden" id="dVazioAssinados">Nenhum documento assinado por você nos últimos 90 dias.</p>
        </div>
      </div>
      <div class="foot">
        <button type="button" class="btn btn-ghost" id="dSair">Sair</button>
        <!-- Assinatura em lote: os marcados são lidos um por um e assinados de uma vez. -->
        <button type="button" class="btn btn-primary hidden" id="dAssinarLote" disabled>Assinar selecionados</button>
      </div>
    </section>

    <!-- D3. Lote aberto (05/11/2026): ver um por um é opcional. Não → direto ao aceite e à assinatura. -->
    <section class="screen" id="tela-d-lote-ver">
      <div class="pad grow">
        <h2>Assinatura em lote</h2>
        <p class="lead" id="dLoteVerResumo">Você vai assinar vários documentos de uma vez.</p>
        <p class="lead" style="font-weight:600;">Deseja ver cada documento individualmente?</p>
        <div class="lista" id="dLoteVerLista"></div>
      </div>
      <div class="foot">
        <button type="button" class="btn btn-ghost" id="dLoteVerVoltar">Voltar</button>
        <button type="button" class="btn btn-ghost" id="dLoteVerNao">Não, assinar</button>
        <button type="button" class="btn btn-primary" id="dLoteVerSim">Sim, ver um por um</button>
      </div>
    </section>
@endverbatim
