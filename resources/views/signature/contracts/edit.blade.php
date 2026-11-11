{{--
    Editor do contrato. O formulário manda tudo de uma vez (ContractContent
    normaliza); a prévia ao lado vem do servidor (rota `preview`), do mesmo
    renderizador do documento final — o JavaScript não numera nada.

    As linhas repetíveis (parte, variável, considerando, cláusula, item,
    signatário) são as mesmas partials do servidor, clonadas de <template>
    com a chave trocada. Linha nova já nasce com uid (a chave), para a
    remissão a ela valer desde a primeira prévia.

    05/11/2026 (pedidos da apresentação do módulo):
      - as duas colunas rolam cada uma por si (`split-xl`/`split-pane`, app.css);
      - a coluna da direita é em abas — Prévia (a primeira), Conferência e Claude —
        para a conferência e a revisão não ficarem entre o texto e a prévia;
      - digitar numa cláusula leva a prévia até ela (editor-js, `rolaPrevia`);
      - salvar automático (`contracts.autosave`) com a revisão do texto (`revision`):
        se alguém salvou antes, a tela avisa em vez de gravar por cima;
      - Variáveis: "Preenchimento" no lugar de "Valor", variável comum pronta, o
        [[marcador]] digitado vira variável sozinho, o tipo sai do nome, onde cada
        uma é usada e o quadro "Falta preencher".
--}}
@php
    use App\Models\SignatureContract as C;

    $f = 'w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:border-grena focus:ring-2 focus:ring-grena-tint';
    $l = 'mb-1 block text-xs font-bold text-ink-2';
    $card = 'space-y-4 rounded-card bg-surface p-5 shadow-card';
    $h = 'font-display text-base font-semibold text-ink';
    $add = 'rounded-full bg-subtle px-3 py-1.5 text-xs font-bold text-ink hover:bg-line';
    $revisando = $contract->status === C::STATUS_IN_REVIEW;
    $comentariosPorClausula = $openComments->whereNotNull('clause_uid')->groupBy('clause_uid');
    $comentariosGerais = $openComments->whereNull('clause_uid');
    $uidsAtuais = collect($content['clauses'] ?? [])->pluck('uid')->all();
    // Observação de cláusula que foi apagada depois: aparece junto das gerais.
    $orfas = $openComments->whereNotNull('clause_uid')->reject(fn ($c) => in_array($c->clause_uid, $uidsAtuais, true));
    $tab = 'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-[13px] font-bold text-ink-2 transition aria-selected:bg-surface aria-selected:text-ink aria-selected:shadow-card';
    $problemasIniciais = session('contractProblems') ?: $problems;
    $errosIniciais = collect($problemasIniciais)->where('level', 'error')->count();
    $avisosIniciais = collect($problemasIniciais)->where('level', 'warning')->count();
    $comClaude = $aiEnabled && auth()->user()->can('aiReview', $contract);
    // A aba que abre: a Conferência quando o envio foi recusado; senão a prévia.
    $abaInicial = session('contractProblems') ? 'conferencia' : 'previa';
@endphp
<x-app-layout :bootstrap-grid="false">
    @include('signature.contracts.partials.styles')

    <x-page wide>
        <x-page-title :title="($contract->number ? $contract->number . ' — ' : '') . $contract->title" :back="route('contracts.show', $contract)">
            {{ $contract->statusLabel() }} · setor {{ $contract->sector_name ?? '—' }}
            @if($contract->isAddendum()) · aditivo de {{ $contract->parent?->number }} @endif
            <x-slot:actions>
                {{-- Só na tela larga, onde a prévia fica ao lado: escondê-la dá a largura toda ao texto. --}}
                <button type="button" data-preview-toggle class="hidden rounded-full bg-subtle px-4 py-2 text-sm font-bold text-ink hover:bg-line xl:inline-flex">Ocultar prévia</button>
                <x-secondary-button-a href="{{ route('contracts.show', $contract) }}">Ver contrato</x-secondary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        @if($revisando)
            <div class="rounded-2xl bg-warn-soft px-5 py-3 text-sm text-ink">
                <b>Ajuste da Gerência.</b> O contrato está em validação: o que você salvar aqui vira uma versão nova,
                registrada como ajuste da Gerência, quando você aprovar ou devolver na tela do contrato.
            </div>
        @endif

        @if($comentariosGerais->isNotEmpty() || $orfas->isNotEmpty())
            <div class="space-y-2 rounded-card bg-surface p-5 shadow-card">
                <p class="{{ $h }}">Observações da Gerência</p>
                @foreach($comentariosGerais->concat($orfas) as $comment)
                    <div class="flex items-start justify-between gap-3 rounded-xl bg-warn-soft px-3 py-2 text-sm text-ink" data-comment="{{ $comment->id }}">
                        <div>
                            <span class="text-xs font-bold text-warn">{{ $comment->author_name }}{{ $comment->clause_uid ? ' · sobre uma cláusula que foi removida' : '' }}</span>
                            <p class="whitespace-pre-line">{{ $comment->body }}</p>
                        </div>
                        <button type="button" data-resolve="{{ route('contracts.comments.resolve', [$contract, $comment]) }}"
                                class="shrink-0 rounded-full bg-surface px-3 py-1 text-xs font-bold text-ink hover:bg-subtle">Resolvida</button>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Salvar automático: alguém gravou antes (ou o contrato mudou de situação). --}}
        <div class="hidden rounded-2xl border-2 border-danger bg-danger-soft px-5 py-4 text-sm text-ink" data-conflict role="alert">
            <p class="font-bold" data-conflict-message></p>
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" data-conflict-reload class="rounded-full bg-surface px-4 py-2 text-sm font-bold text-ink hover:bg-subtle">Recarregar e ver o que foi salvo</button>
                <button type="button" data-conflict-force class="rounded-full bg-danger px-4 py-2 text-sm font-bold text-white hover:opacity-90">Gravar o meu texto por cima</button>
            </div>
            <p class="mt-2 text-xs text-ink-2" data-conflict-hint>Recarregar descarta o que você digitou desde então. Gravar por cima apaga o que a outra pessoa salvou.</p>
        </div>

        <div class="ct-layout split-xl grid grid-cols-1 gap-6 xl:grid-cols-12" data-layout>
            {{-- Em passos livres, como o documento comum (04/11/2026): Contratado → Variáveis → Cláusulas → Quem assina.
                 Grava de qualquer passo e volta no mesmo (`passo`). --}}
            <form id="ctForm" method="POST" action="{{ route('contracts.update', $contract) }}" class="lara-form split-pane min-w-0 space-y-6 xl:col-span-7"
                  data-steps-free data-step-start="{{ max(0, min(3, (int) request('passo', 0))) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="next" value="" data-next>
                <input type="hidden" name="passo" value="{{ (int) request('passo', 0) }}" data-step-current>
                {{-- A revisão do texto que esta tela abriu: o salvar confere se ninguém gravou depois. --}}
                <input type="hidden" name="revision" value="{{ (int) $contract->content_revision }}" data-revision>

                <div class="space-y-6" data-step="Contratado" data-step-keys="title,establishment,contractor_term,parties,validity">
                {{-- 1. Dados gerais --}}
                <section class="{{ $card }}">
                    <p class="{{ $h }}">Dados gerais</p>
                    <div>
                        <label class="{{ $l }}" for="title">Título</label>
                        <textarea id="title" name="title" rows="1" class="{{ $f }} resize-none text-base font-bold" maxlength="200" data-autogrow
                                  placeholder="Contrato de prestação de serviços">{{ $content['title'] ?? '' }}</textarea>
                        <p class="mt-1 text-xs text-ink-3">É o nome do contrato nas listas e na busca. Não sai no documento: o cabeçalho é sempre "CONTRATO DE PRESTAÇÃO DE SERVIÇO QUE ENTRE SI FAZEM: CLUBE DOS FUNCIONÁRIOS DA CSN E …".</p>
                    </div>
                    {{-- Vigência (05/11/2026): prazo a partir da assinatura ou data de término; avisa a 60, 30 e 15 dias do fim. --}}
                    @php $vigencia = \App\Services\Signature\Contracts\ContractContent::validity($content['validity'] ?? null); @endphp
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="{{ $l }}" for="validity_months">Vigência (meses a partir da assinatura)</label>
                            <input type="number" id="validity_months" name="validity[months]" min="1" max="{{ \App\Services\Signature\Contracts\ContractContent::MAX_VALIDITY_MONTHS }}"
                                   value="{{ $vigencia['months'] }}" class="{{ $f }}" placeholder="12">
                        </div>
                        <div>
                            <label class="{{ $l }}" for="validity_until">ou termina em (data fixa)</label>
                            <input type="date" id="validity_until" name="validity[until]" value="{{ $vigencia['until'] }}" class="{{ $f }}">
                        </div>
                    </div>
                    <p class="text-xs text-ink-3">
                        O término conta do dia em que todos terminarem de assinar. A data fixa, se preenchida, vale no lugar do prazo.
                        Quem redigiu e o setor são avisados a 60, 30 e 15 dias do fim. Em branco, o contrato fica sem vigência e sem aviso.
                    </p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <label class="{{ $l }}" for="establishment">Contratante (CNPJ do clube)</label>
                            <select id="establishment" name="establishment" class="{{ $f }} pr-10">
                                @foreach($establishments as $valor => $rotulo)
                                    <option value="{{ $valor }}" @selected(($content['establishment'] ?? '') === $valor)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="{{ $l }}" for="contractor_term">Termo do clube</label>
                            <input type="text" id="contractor_term" name="contractor_term" value="{{ $content['contractor_term'] ?? 'CONTRATANTE' }}" class="{{ $f }} uppercase" maxlength="40" placeholder="CONTRATANTE, LOCADOR…">
                        </div>
                    </div>
                    <p class="text-xs text-ink-3">
                        O clube é representado pelo presidente:
                        <b>{{ $president['name'] ?? 'sem titular na área "' . config('signature.contracts.president_area') . '" (Assinaturas → Diretoria)' }}</b>.
                        Ele assina pelo clube sem precisar ser incluído nos signatários.
                    </p>
                </section>

                {{-- 2. Partes --}}
                <section class="{{ $card }}">
                    <div class="flex items-center justify-between gap-2">
                        <p class="{{ $h }}">Contratada e fiadores</p>
                        <button type="button" data-add="party" class="{{ $add }}">+ Parte</button>
                    </div>
                    <p class="text-xs text-ink-3">Salvar guarda a contratada em Prestadores: no próximo contrato, é só buscar.</p>
                    <div class="space-y-3" data-list="party">
                        @foreach($content['parties'] ?? [] as $parte)
                            @include('signature.contracts.partials.party', ['k' => $parte['uid'], 'p' => $parte])
                        @endforeach
                    </div>
                </section>
                </div>

                {{-- 3. Variáveis --}}
                <section class="{{ $card }}" data-step="Variáveis" data-step-keys="variables">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="{{ $h }}">Variáveis</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <label for="ctCommonVariable" class="sr-only">Variável comum</label>
                            <select id="ctCommonVariable" data-common-variable class="rounded-full border border-line-strong bg-surface py-1.5 pl-3 pr-9 text-xs font-bold text-ink">
                                <option value="">Variável comum…</option>
                                @foreach($commonVariables as $i => $comum)
                                    <option value="{{ $i }}">{{ $comum['label'] }}</option>
                                @endforeach
                            </select>
                            <button type="button" data-add="variable" class="{{ $add }}">+ Variável</button>
                        </div>
                    </div>
                    <p class="text-xs text-ink-3">
                        O que muda de um contrato para outro — prazo, datas, percentuais, quantias. Em cada variável, preencha só
                        o campo de baixo do nome: é o que entra no texto. Data se escolhe no calendário; quantia e percentual, só o número
                        (saem com o extenso). Para mudar o nome ou o tipo, abra <b>Nome e tipo da variável</b>.
                        Use no texto pelo "Inserir variável", ou escreva direto na cláusula entre colchetes duplos, como
                        <code class="font-mono">[[Prazo de vigência]]</code>: a variável aparece aqui sozinha, com o tipo pelo nome.
                        Os dados das partes e do clube já são variáveis prontas.
                    </p>
                    {{-- Preenchido pelo editor: as variáveis ainda sem preenchimento, com atalho para cada uma. --}}
                    <div class="hidden rounded-xl bg-warn-soft px-3 py-2" data-var-missing></div>
                    <div class="space-y-2" data-list="variable">
                        @foreach($content['variables'] ?? [] as $i => $variavel)
                            @include('signature.contracts.partials.variable', ['k' => 'v' . $i, 'v' => $variavel])
                        @endforeach
                    </div>
                </section>

                <div class="space-y-6" data-step="Cláusulas" data-step-keys="considerations,clauses">
                {{-- 4. Considerandos --}}
                <section class="{{ $card }}">
                    <div class="flex items-center justify-between gap-2">
                        <p class="{{ $h }}">Considerandos <span class="text-xs font-normal text-ink-3">(opcional)</span></p>
                        <button type="button" data-add="consideration" class="{{ $add }}">+ Considerando</button>
                    </div>
                    <div class="space-y-2" data-list="consideration">
                        @foreach($content['considerations'] ?? [] as $i => $html)
                            @include('signature.contracts.partials.consideration', ['k' => 'c' . $i, 'html' => $html])
                        @endforeach
                    </div>
                </section>

                {{-- 5. Cláusulas --}}
                <section class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 px-1">
                        <p class="{{ $h }}">Cláusulas</p>
                        <div class="relative flex gap-2" data-library-wrap data-library-home>
                            <button type="button" data-library-open class="{{ $add }}">Da biblioteca…</button>
                            <button type="button" data-add="clause" class="rounded-full bg-grena px-3 py-1.5 text-xs font-bold text-white hover:bg-grena-hover">+ Cláusula</button>
                            <div data-library-panel class="absolute right-0 top-9 z-20 hidden max-h-96 w-[min(28rem,90vw)] overflow-y-auto rounded-2xl border border-line bg-surface p-3 shadow-card"></div>
                        </div>
                    </div>
                    <p class="px-1 text-xs text-ink-3">
                        A numeração (CLÁUSULA PRIMEIRA, 1.1, 1.1.1) é automática. Para citar outra cláusula, use "Inserir remissão":
                        o número acompanha se a ordem mudar.
                    </p>
                    <div class="space-y-4" data-list="clause">
                        @foreach($content['clauses'] ?? [] as $clausula)
                            @include('signature.contracts.partials.clause', [
                                'k' => $clausula['uid'],
                                'cl' => $clausula,
                                'comments' => $comentariosPorClausula->get($clausula['uid'], collect()),
                            ])
                        @endforeach
                    </div>
                    {{-- O mesmo par de botões no fim da lista: a cláusula entra por último. --}}
                    <div class="relative flex flex-wrap justify-center gap-2 rounded-2xl border border-dashed border-line-strong p-3" data-library-wrap>
                        <button type="button" data-add="clause" class="rounded-full bg-grena px-3 py-1.5 text-xs font-bold text-white hover:bg-grena-hover">+ Cláusula no fim</button>
                        <button type="button" data-library-open class="{{ $add }}">Da biblioteca…</button>
                    </div>
                </section>
                </div>

                {{-- 6. Signatários --}}
                <section class="{{ $card }}" data-step="Quem assina" data-step-keys="signers">
                    <div class="flex items-center justify-between gap-2">
                        <p class="{{ $h }}">Quem assina</p>
                        <button type="button" data-add="signer" class="{{ $add }}">+ Signatário</button>
                    </div>
                    <p class="text-xs text-ink-3">
                        O presidente assina pelo clube automaticamente. Inclua quem assina pela contratada, os fiadores, outros
                        diretores e as testemunhas. Diretores assinam no tablet com matrícula e PIN, depois de quem é de fora da diretoria.
                    </p>
                    <div class="space-y-3" data-list="signer">
                        @foreach($content['signers'] ?? [] as $signer)
                            @include('signature.contracts.partials.signer', ['k' => $signer['uid'], 's' => $signer])
                        @endforeach
                    </div>
                </section>

                @include('signature.documents.partials.steps')

                {{-- Ações --}}
                <div class="sticky bottom-3 z-10 space-y-3 rounded-card bg-surface p-4 shadow-card">
                    @if(!$revisando)
                        <details>
                            <summary class="cursor-pointer text-xs font-bold text-grena-ink">Recado para a Gerência (opcional, vai com o envio)</summary>
                            <textarea name="note" rows="2" maxlength="2000" class="{{ $f }} mt-2" placeholder="Ex.: ajustei as cláusulas 3 e 5 conforme pedido."></textarea>
                        </details>
                    @endif
                    <div class="flex flex-wrap items-center justify-end gap-2" data-step-actions>
                        <span class="mr-auto text-xs text-ink-3" data-save-state aria-live="polite">
                            Salvo automaticamente enquanto você digita.
                        </span>
                        <x-secondary-button type="submit">{{ $revisando ? 'Salvar ajustes' : 'Salvar' }}</x-secondary-button>
                        @if(!$revisando)
                            <x-primary-button type="submit" data-submit-review>Salvar e enviar à Gerência</x-primary-button>
                        @endif
                    </div>
                </div>
            </form>

            {{-- Prévia, Conferência e Claude em abas (05/11/2026): a prévia é a primeira e nada fica no caminho dela. --}}
            <aside class="split-pane min-w-0 xl:col-span-5" data-side>
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex w-fit max-w-full gap-1 overflow-x-auto rounded-full bg-subtle p-1" role="tablist" aria-label="Ao lado do texto">
                            <button type="button" role="tab" data-side-tab="previa" aria-selected="{{ $abaInicial === 'previa' ? 'true' : 'false' }}" class="{{ $tab }}">Prévia</button>
                            <button type="button" role="tab" data-side-tab="conferencia" aria-selected="{{ $abaInicial === 'conferencia' ? 'true' : 'false' }}" class="{{ $tab }}">
                                Conferência
                                <span data-problem-count @class([
                                    'rounded-full px-2 text-[11px]',
                                    'bg-danger text-white' => $errosIniciais > 0,
                                    'bg-warn-soft text-warn' => $errosIniciais === 0 && $avisosIniciais > 0,
                                    'bg-ok-soft text-ok' => $errosIniciais === 0 && $avisosIniciais === 0,
                                ])>{{ $errosIniciais > 0 ? $errosIniciais : ($avisosIniciais > 0 ? $avisosIniciais : '✓') }}</span>
                            </button>
                            @if($comClaude)
                                <button type="button" role="tab" data-side-tab="claude" aria-selected="false" class="{{ $tab }}">
                                    Claude
                                    @if($aiReview)
                                        <span class="h-2 w-2 rounded-full {{ $aiReviewCurrent ? 'bg-ok' : 'bg-warn' }}" aria-label="{{ $aiReviewCurrent ? 'revisão do texto atual' : 'o texto mudou depois da revisão' }}"></span>
                                    @endif
                                </button>
                            @endif
                        </div>
                        <button type="button" data-preview class="{{ $add }}">Atualizar prévia</button>
                    </div>

                    <div data-side-panel="previa" @class(['hidden' => $abaInicial !== 'previa'])>
                        <div class="rounded-card shadow-card">
                            <div class="ct-paper" data-preview-paper>{!! $preview !!}</div>
                        </div>
                    </div>

                    <div data-side-panel="conferencia" @class(['hidden' => $abaInicial !== 'conferencia'])>
                        <div class="rounded-card bg-surface p-5 shadow-card">
                            <p class="{{ $h }} mb-3">Conferência</p>
                            @include('signature.contracts.partials.problems', ['problems' => $problemasIniciais])
                        </div>
                    </div>

                    @if($comClaude)
                        <div data-side-panel="claude" class="hidden">
                            @include('signature.contracts.partials.ai-review', ['podeDecidir' => false, 'noEditor' => true, 'aberto' => true])
                        </div>
                    @endif
                </div>
            </aside>
        </div>
    </x-page>

    {{-- Moldes das linhas novas (__K__ = chave/uid; __C__ = chave da cláusula do item). --}}
    <template id="tpl-party">@include('signature.contracts.partials.party', ['k' => '__K__', 'p' => ['uid' => '__K__']])</template>
    <template id="tpl-variable">@include('signature.contracts.partials.variable', ['k' => '__K__', 'v' => []])</template>
    <template id="tpl-consideration">@include('signature.contracts.partials.consideration', ['k' => '__K__', 'html' => ''])</template>
    <template id="tpl-clause">@include('signature.contracts.partials.clause', ['k' => '__K__', 'cl' => ['uid' => '__K__', 'items' => []], 'comments' => collect()])</template>
    <template id="tpl-item">@include('signature.contracts.partials.item', ['c' => '__C__', 'k' => '__K__', 'i' => ['uid' => '__K__', 'level' => 1]])</template>
    <template id="tpl-signer">@include('signature.contracts.partials.signer', ['k' => '__K__', 's' => ['uid' => '__K__', 'party' => 'testemunha']])</template>

    @include('signature.contracts.partials.editor-js', [
        'ctConfig' => [
            'csrf' => csrf_token(),
            'sectorId' => $contract->sector_id,
            'tokens' => $tokens,
            'outline' => collect($outline)->map(fn ($o, $uid) => ['uid' => $uid] + $o)->values(),
            'revision' => (int) $contract->content_revision,
            // Palpite do tipo pelo nome e as variáveis comuns (ContractVariables::GUESS/COMMON).
            'guess' => \App\Services\Signature\Contracts\ContractVariables::GUESS,
            // O campo do preenchimento de cada tipo (09/11/2026).
            'inputs' => \App\Services\Signature\Contracts\ContractVariables::INPUTS,
            'common' => $commonVariables,
            'usage' => (object) $variableUsage,
            'urls' => [
                'autosave' => route('contracts.autosave', $contract),
                'preview' => route('contracts.preview', $contract),
                'library' => route('contracts.library', $contract),
                'libraryStore' => route('contract-library.store'),
                'parties' => route('contracts.parties'),
            ],
        ],
    ])
</x-app-layout>
