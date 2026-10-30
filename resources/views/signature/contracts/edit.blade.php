{{--
    Editor do contrato. O formulário manda tudo de uma vez (ContractContent
    normaliza); a prévia ao lado vem do servidor (rota `preview`), do mesmo
    renderizador do documento final — o JavaScript não numera nada.

    As linhas repetíveis (parte, variável, considerando, cláusula, item,
    signatário) são as mesmas partials do servidor, clonadas de <template>
    com a chave trocada. Linha nova já nasce com uid (a chave), para a
    remissão a ela valer desde a primeira prévia.
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
@endphp
<x-app-layout :bootstrap-grid="false">
    @include('signature.contracts.partials.styles')

    <x-page wide>
        <x-page-title :title="($contract->number ? $contract->number . ' — ' : '') . $contract->title" :back="route('signature-contracts.show', $contract)">
            {{ $contract->statusLabel() }} · setor {{ $contract->sector_name ?? '—' }}
            @if($contract->isAddendum()) · aditivo de {{ $contract->parent?->number }} @endif
            <x-slot:actions>
                {{-- Só na tela larga, onde a prévia fica ao lado: escondê-la dá a largura toda ao texto. --}}
                <button type="button" data-preview-toggle class="hidden rounded-full bg-subtle px-4 py-2 text-sm font-bold text-ink hover:bg-line xl:inline-flex">Ocultar prévia</button>
                <x-secondary-button-a href="{{ route('signature-contracts.show', $contract) }}">Ver contrato</x-secondary-button-a>
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
                        <button type="button" data-resolve="{{ route('signature-contracts.comments.resolve', [$contract, $comment]) }}"
                                class="shrink-0 rounded-full bg-surface px-3 py-1 text-xs font-bold text-ink hover:bg-subtle">Resolvida</button>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="ct-layout grid grid-cols-1 gap-6 xl:grid-cols-12" data-layout>
            <form id="ctForm" method="POST" action="{{ route('signature-contracts.update', $contract) }}" class="min-w-0 space-y-6 xl:col-span-7">
                @csrf
                @method('PUT')
                <input type="hidden" name="next" value="" data-next>

                {{-- 1. Dados gerais --}}
                <section class="{{ $card }}">
                    <p class="{{ $h }}">Dados gerais</p>
                    <div>
                        <label class="{{ $l }}" for="title">Título</label>
                        <textarea id="title" name="title" rows="1" class="{{ $f }} resize-none text-base font-bold" maxlength="200" data-autogrow
                                  placeholder="Contrato de prestação de serviços">{{ $content['title'] ?? '' }}</textarea>
                        <p class="mt-1 text-xs text-ink-3">Sai no alto, à direita: "TÍTULO, QUE ENTRE SI CELEBRAM CLUBE DOS FUNCIONÁRIOS DA CSN E A CONTRATADA".</p>
                    </div>
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
                    <p class="text-xs text-ink-3">Salvar guarda a contratada no cadastro: no próximo contrato, é só buscar.</p>
                    <div class="space-y-3" data-list="party">
                        @foreach($content['parties'] ?? [] as $parte)
                            @include('signature.contracts.partials.party', ['k' => $parte['uid'], 'p' => $parte])
                        @endforeach
                    </div>
                </section>

                {{-- 3. Variáveis --}}
                <section class="{{ $card }}">
                    <div class="flex items-center justify-between gap-2">
                        <p class="{{ $h }}">Variáveis</p>
                        <button type="button" data-add="variable" class="{{ $add }}">+ Variável</button>
                    </div>
                    <p class="text-xs text-ink-3">
                        Valor, prazo, percentual, datas: preencha uma vez e use no texto pelo botão "Inserir variável".
                        Valores, números e percentuais saem com o extenso. Os dados das partes e do clube já são variáveis prontas.
                    </p>
                    <div class="space-y-2" data-list="variable">
                        @foreach($content['variables'] ?? [] as $i => $variavel)
                            @include('signature.contracts.partials.variable', ['k' => 'v' . $i, 'v' => $variavel])
                        @endforeach
                    </div>
                </section>

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

                {{-- 6. Signatários --}}
                <section class="{{ $card }}">
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

                {{-- Ações --}}
                <div class="sticky bottom-3 z-10 space-y-3 rounded-card bg-surface p-4 shadow-card">
                    @if(!$revisando)
                        <details>
                            <summary class="cursor-pointer text-xs font-bold text-grena-ink">Recado para a Gerência (opcional, vai com o envio)</summary>
                            <textarea name="note" rows="2" maxlength="2000" class="{{ $f }} mt-2" placeholder="Ex.: ajustei as cláusulas 3 e 5 conforme pedido."></textarea>
                        </details>
                    @endif
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <span class="mr-auto text-xs text-ink-3" data-save-state></span>
                        <x-secondary-button type="submit">{{ $revisando ? 'Salvar ajustes' : 'Salvar' }}</x-secondary-button>
                        @if(!$revisando)
                            <x-primary-button type="submit" data-submit-review>Salvar e enviar à Gerência</x-primary-button>
                        @endif
                    </div>
                </div>
            </form>

            {{-- Prévia e problemas --}}
            <aside class="min-w-0 xl:col-span-5">
                <div class="space-y-4 xl:sticky xl:top-20">
                    @if($aiEnabled && auth()->user()->can('aiReview', $contract))
                        <div class="max-h-[45vh] overflow-y-auto rounded-card">
                            @include('signature.contracts.partials.ai-review', ['podeDecidir' => false, 'noEditor' => true])
                        </div>
                    @endif
                    <div class="rounded-card bg-surface p-5 shadow-card">
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <p class="{{ $h }}">Conferência</p>
                            <button type="button" data-preview class="{{ $add }}">Atualizar prévia</button>
                        </div>
                        @include('signature.contracts.partials.problems', ['problems' => session('contractProblems') ?: $problems])
                    </div>
                    <div class="max-h-[78vh] overflow-y-auto rounded-card shadow-card">
                        <div class="ct-paper" data-preview-paper>{!! $preview !!}</div>
                    </div>
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
            'urls' => [
                'preview' => route('signature-contracts.preview', $contract),
                'library' => route('signature-contracts.library', $contract),
                'libraryStore' => route('signature-contract-library.store'),
                'parties' => route('signature-contracts.parties'),
            ],
        ],
    ])
</x-app-layout>
