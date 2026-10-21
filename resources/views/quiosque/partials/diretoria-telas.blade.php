{{--
    Telas da Assinatura da Diretoria, incluídas em quiosque/index.blade.php só
    no modo balcão. A lógica está em diretoria-js.blade.php.

    Ordem: espera → "Assinatura da Diretoria" → matrícula + PIN → lista dos
    documentos pendentes → [quiosque: leitura, aceite, traço, (visto), foto] →
    volta à lista enquanto a entrada do diretor valer.
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

    <!-- D2. Documentos pendentes da assinatura do diretor -->
    <section class="screen" id="tela-d-lista">
      <div class="pad grow">
        <h2 id="dOla">Seus documentos</h2>
        <p class="lead" id="dResumo">Toque no documento para ler e assinar.</p>
        <div class="lista" id="dDocumentos"></div>
        <p class="note note-info hidden" id="dVazio">Nenhum documento pendente da sua assinatura.</p>
      </div>
      <div class="foot">
        <button type="button" class="btn btn-primary" id="dSair">Sair</button>
      </div>
    </section>
@endverbatim
