{{--
    Validação de contratos pela Gerência no tablet do balcão.

    Página própria (não é a tela de assinatura): entrada por matrícula + PIN,
    lista dos contratos em validação, leitura com "o que mudou", observação
    por cláusula TOCANDO nela, e Aprovar / Devolver. Desde 04/11/2026 também:
    "Revisão do Claude" (pedir a análise, com trechos sensíveis e trechos
    anexados, e levar cada apontamento para a observação da cláusula) e
    "Editar texto" (título e o texto das cláusulas; partes, variáveis e
    signatários seguem no computador). O texto do contrato vem pronto do
    servidor (ContractRenderer), como na tela web — aqui nada é montado.

    O tablet não guarda nada: as observações em andamento ficam só na
    memória da página, e a entrada cai sozinha depois de um tempo parado.

    Desde 11/11/2026: visual em resources/css/quiosque-gerencia.css (faixa da
    caneta, em pé e deitado) e comportamento em resources/js/quiosque-gerencia.js,
    ambos pelo Vite. Os ids daqui são lidos pelo JS: renomear exige renomear lá.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#152652">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="robots" content="noindex, nofollow">
  <title>Validação de contratos — Gerência</title>
  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800|unbounded:500,600,700|jetbrains-mono:500,600&display=swap" rel="stylesheet" />
  @include('signature.contracts.partials.styles')
  <script>window.GERENCIA = {
    urls: @json($urls),
    ociosoSegundos: {{ (int) $idleSeconds }},
    // O visto precisa de um mínimo de pontos (a mesma régua do servidor).
    minPontosVisto: {{ (int) config('signature.evidence.min_initials_points', 8) }},
  };</script>
  @vite(['resources/css/quiosque-gerencia.css', 'resources/js/quiosque-gerencia.js'])
</head>
<body>
<div class="app">
  <div class="topbar q-band">
    <div class="brand-logo"><img src="{{ asset('images/logo.png') }}" alt=""></div>
    <div class="who"><b id="quem">Validação de contratos</b><span id="sub">Gerência</span></div>
    <button type="button" class="btn btn-ghost btn-small" id="btnSair" style="display:none;">Sair</button>
  </div>

  {{-- 1. Entrada --}}
  <section class="screen on" id="tela-entrar">
    <div class="g-login">
    <div class="g-hero q-band">
      <div class="brand-logo"><img src="{{ asset('images/logo.png') }}" alt="CFCSN"></div>
      <p class="eyebrow">O Lara · Contratos</p>
      <h1>Validação de contratos</h1>
      <p>Leia o contrato, comente cláusula por cláusula e aprove ou devolva para ajustes.</p>
      <svg class="pen" viewBox="0 0 180 22" fill="none" aria-hidden="true"><path d="M2 15c14-10 22-12 26-6s-6 10 2 9 14-14 22-11-2 11 6 10 16-12 26-9 4 8 14 6 20-7 28-6 14 4 22 2 18-5 28-5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
    </div>
    <div class="scroll"><div class="center">
      <h2>Entrar</h2>
      <p class="lead">Gerência: entre com a sua matrícula e o PIN do Lara.</p>
      <form id="formEntrar" autocomplete="off">
        <label class="lbl" for="matricula">Matrícula</label>
        <input class="field" id="matricula" inputmode="numeric" maxlength="20" required>
        <label class="lbl" for="pin">PIN</label>
        <input class="field" id="pin" type="password" inputmode="numeric" maxlength="20" required>
        <div class="err" id="erroEntrar"></div>
        <div class="row">
          <a class="btn btn-ghost" href="{{ $urls['kiosk'] }}">Voltar</a>
          <button type="submit" class="btn btn-primary" id="btnEntrar">Entrar</button>
        </div>
      </form>
    </div></div>
    </div>
  </section>

  {{-- 2. Lista --}}
  <section class="screen" id="tela-lista">
    <div class="scroll">
      <h1>Aguardando validação</h1>
      <p class="lead" id="listaResumo"></p>
      <div id="lista"></div>
    </div>
    <div class="foot">
      <button type="button" class="btn btn-ghost" id="btnAtualizar">Atualizar</button>
    </div>
  </section>

  {{-- 3. Contrato --}}
  <section class="screen" id="tela-contrato">
    <div class="scroll" id="contratoScroll">
      <button type="button" class="btn btn-ghost btn-small back" id="btnVoltar">← Lista</button>
      <h1 id="cTitulo"></h1>
      <p class="meta c-meta" id="cMeta"></p>
      <div class="tools">
        <button type="button" class="btn btn-ghost btn-small" id="btnIA" style="display:none;">Revisão do Claude</button>
        <button type="button" class="btn btn-ghost btn-small" id="btnEditar">Editar texto</button>
      </div>
      <div id="cAvisos"></div>
      <div class="ct-paper" id="cTexto"></div>
    </div>
    <div class="foot">
      <button type="button" class="btn btn-ghost" id="btnDevolver">Devolver</button>
      <button type="button" class="btn btn-ok" id="btnAprovar">Aprovar</button>
    </div>
  </section>
</div>

<div class="sheet-bg" id="fundo"></div>

{{-- Observação numa cláusula --}}
<div class="sheet" id="folhaClausula">
  <h2 id="fcTitulo"></h2>
  <p class="meta">A observação vai para quem redigiu, junto da cláusula, quando você devolver.</p>
  <textarea class="field" id="fcTexto" maxlength="3000" placeholder="O que precisa mudar nesta cláusula?"></textarea>
  <div class="row">
    <button type="button" class="btn btn-ghost" data-fechar>Cancelar</button>
    <button type="button" class="btn btn-ghost" id="fcApagar">Apagar</button>
    <button type="button" class="btn btn-primary" id="fcSalvar">Guardar</button>
  </div>
</div>

{{-- Devolver --}}
<div class="sheet" id="folhaDevolver">
  <h2>Devolver para ajustes</h2>
  <div id="fdResumo"></div>
  <label class="lbl" for="fdNota">Observação geral</label>
  <textarea class="field" id="fdNota" maxlength="3000" placeholder="Opcional se já há observação nas cláusulas"></textarea>
  <div class="err" id="fdErro"></div>
  <div class="row">
    <button type="button" class="btn btn-ghost" data-fechar>Voltar</button>
    <button type="button" class="btn btn-primary" id="fdConfirmar">Devolver</button>
  </div>
</div>

{{-- Aprovar --}}
<div class="sheet" id="folhaAprovar">
  <h2>Aprovar o contrato?</h2>
  <p class="lead">O contrato ganha o número e o documento de assinatura é gerado em rascunho, para quem redigiu conferir e congelar.</p>
  <div id="faAviso"></div>
  {{-- Visto da Gerência (04/11/2026): obrigatório; vai em todas as páginas do documento. --}}
  @include('signature.contracts.partials.initials-pad', ['id' => 'faVisto'])
  <label class="lbl" for="faNota">Observação (opcional)</label>
  <textarea class="field" id="faNota" maxlength="3000"></textarea>
  <div class="err" id="faErro"></div>
  <div class="row">
    <button type="button" class="btn btn-ghost" data-fechar>Voltar</button>
    <button type="button" class="btn btn-ok" id="faConfirmar">Aprovar</button>
  </div>
</div>

{{-- Revisão pelo Claude --}}
<div class="sheet" id="folhaIA">
  <h2>Revisão do Claude</h2>
  <div id="iaUltima"></div>
  <p class="meta">Sugestões automáticas: confira antes de usar. O texto do contrato vai à Anthropic (empresa do Claude); os trechos com valores, CPF, documentos ou endereço só vão se você marcar.</p>
  <div id="iaSensiveis"></div>
  <label class="lbl">Anexar trechos ao pedido (opcional)</label>
  <p class="meta" id="iaExtrasAjuda"></p>
  <div id="iaExtras"></div>
  <button type="button" class="btn btn-ghost btn-small" id="iaMaisTrecho">+ Outro trecho</button>
  <div class="err" id="iaErro"></div>
  <div class="row">
    <button type="button" class="btn btn-ghost" data-fechar>Fechar</button>
    <button type="button" class="btn btn-primary" id="iaAnalisar">Analisar com o Claude</button>
  </div>
</div>

{{-- Editar o texto --}}
<div class="sheet" id="folhaEditar">
  <h2>Editar o texto</h2>
  <p class="meta">Título do contrato e o texto das cláusulas. Partes, variáveis e signatários são ajustados no computador. Na decisão, fica registrado como ajuste da Gerência.</p>
  <div class="ed-barra">
    <button type="button" data-cmd="bold" title="Negrito"><b>N</b></button>
    <button type="button" data-cmd="italic" title="Itálico"><i>I</i></button>
    <button type="button" data-cmd="underline" title="Sublinhado"><u>S</u></button>
  </div>
  <label class="lbl" for="edTitulo">Título do contrato</label>
  <input class="field small" id="edTitulo" maxlength="200">
  <div id="edClausulas"></div>
  <div class="err" id="edErro"></div>
  {{-- Salvar automático (05/11/2026): grava sozinho enquanto se digita; alguém salvou antes, avisa. --}}
  <p class="meta" id="edEstado" aria-live="polite">Salvo automaticamente enquanto você digita.</p>
  <button type="button" class="btn btn-ghost hidden" id="edForcar">Gravar o meu texto por cima</button>
  <div class="row">
    <button type="button" class="btn btn-ghost" data-fechar>Fechar</button>
    <button type="button" class="btn btn-primary" id="edSalvar">Salvar texto</button>
  </div>
</div>

<div class="toast" id="toast"></div>

</body>
</html>
