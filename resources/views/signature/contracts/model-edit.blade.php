{{--
    Editar um modelo de contrato do setor (06/11/2026), pela Biblioteca. É o
    editor do contrato (as mesmas partials e o editor-js), no modo modelo:

      - partes só com papel, tipo e termo; quem assina só com o papel, o
        cargo e o diretor; variáveis sem "Preenchimento" — o resto é do
        contrato (ContractService::modelOnly descarta o que vier);
      - a Conferência é a do modelo (ContractRenderer::problems com $model);
      - sem salvar automático e sem envio à Gerência: grava no botão Salvar.

    Gravar muda só o modelo: os contratos feitos com ele têm a própria cópia.
--}}
@php
    $f = 'w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:border-grena focus:ring-2 focus:ring-grena-tint';
    $l = 'mb-1 block text-xs font-bold text-ink-2';
    $card = 'space-y-4 rounded-card bg-surface p-5 shadow-card';
    $h = 'font-display text-base font-semibold text-ink';
    $add = 'rounded-full bg-subtle px-3 py-1.5 text-xs font-bold text-ink hover:bg-line';
    $tab = 'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-[13px] font-bold text-ink-2 transition aria-selected:bg-surface aria-selected:text-ink aria-selected:shadow-card';
    $erros = collect($problems)->where('level', 'error')->count();
    $avisos = collect($problems)->where('level', 'warning')->count();
    $vigencia = \App\Services\Signature\Contracts\ContractContent::validity($content['validity'] ?? null);
@endphp
<x-app-layout :bootstrap-grid="false">
    @include('signature.contracts.partials.styles')

    <x-page wide>
        <x-page-title :title="'Modelo — ' . $model->name" :back="route('contract-library.index')">
            Modelo do setor {{ $model->sector_name ?? '—' }}
            · por {{ $model->updated_by_name ?? $model->created_by_name ?? '—' }}.
            Salvar muda os próximos contratos; os já feitos com o modelo não mudam.
            <x-slot:actions>
                <button type="button" data-preview-toggle class="hidden rounded-full bg-subtle px-4 py-2 text-sm font-bold text-ink hover:bg-line xl:inline-flex">Ocultar prévia</button>
                <x-secondary-button-a href="{{ route('contract-library.index') }}">Voltar à biblioteca</x-secondary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        <div class="ct-layout split-xl grid grid-cols-1 gap-6 xl:grid-cols-12" data-layout>
            <form id="ctForm" method="POST" action="{{ route('contract-library.models.update', $model) }}" class="split-pane min-w-0 space-y-6 xl:col-span-7"
                  data-steps-free data-step-start="{{ max(0, min(3, (int) request('passo', 0))) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="next" value="" data-next>
                <input type="hidden" name="passo" value="{{ (int) request('passo', 0) }}" data-step-current>

                <div class="space-y-6" data-step="Partes" data-step-keys="name,title,establishment,contractor_term,parties,validity">
                <section class="{{ $card }}">
                    <p class="{{ $h }}">Dados gerais do modelo</p>
                    <div>
                        <label class="{{ $l }}" for="name">Nome do modelo</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $model->name) }}" maxlength="150" required class="{{ $f }} font-bold" placeholder="Prestação de serviços">
                        <p class="mt-1 text-xs text-ink-3">É como o modelo aparece em Novo contrato.</p>
                        @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="{{ $l }}" for="title">Título do contrato</label>
                        <textarea id="title" name="title" rows="1" class="{{ $f }} resize-none" maxlength="200" data-autogrow
                                  placeholder="Contrato de prestação de serviços">{{ $content['title'] ?? '' }}</textarea>
                        <p class="mt-1 text-xs text-ink-3">O título com que o contrato feito pelo modelo nasce (dá para mudar no contrato).</p>
                    </div>
                    <div>
                        <label class="{{ $l }}" for="validity_months">Vigência padrão (meses a partir da assinatura, opcional)</label>
                        <input type="number" id="validity_months" name="validity[months]" min="1" max="{{ \App\Services\Signature\Contracts\ContractContent::MAX_VALIDITY_MONTHS }}"
                               value="{{ $vigencia['months'] }}" class="{{ $f }} sm:w-48" placeholder="12">
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
                </section>

                <section class="{{ $card }}">
                    <div class="flex items-center justify-between gap-2">
                        <p class="{{ $h }}">Contratada e fiadores</p>
                        <button type="button" data-add="party" class="{{ $add }}">+ Parte</button>
                    </div>
                    <p class="text-xs text-ink-3">No modelo, cada parte guarda só o papel, o tipo e o termo (CONTRATADA, LOCATÁRIO…).</p>
                    <div class="space-y-3" data-list="party">
                        @foreach($content['parties'] ?? [] as $parte)
                            @include('signature.contracts.partials.party', ['k' => $parte['uid'], 'p' => $parte, 'modelo' => true])
                        @endforeach
                    </div>
                </section>
                </div>

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
                        O modelo guarda o nome e o tipo de cada variável; o preenchimento é feito em cada contrato.
                        Escrever <code class="font-mono">[[Prazo de vigência]]</code> numa cláusula cria a variável sozinha.
                    </p>
                    <div class="space-y-2" data-list="variable">
                        @foreach($content['variables'] ?? [] as $i => $variavel)
                            @include('signature.contracts.partials.variable', ['k' => 'v' . $i, 'v' => $variavel, 'modelo' => true])
                        @endforeach
                    </div>
                </section>

                <div class="space-y-6" data-step="Cláusulas" data-step-keys="considerations,clauses">
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
                        A numeração (CLÁUSULA PRIMEIRA, 1.1, 1.1.1) é automática. Para citar outra cláusula, use "Inserir remissão".
                    </p>
                    <div class="space-y-4" data-list="clause">
                        @foreach($content['clauses'] ?? [] as $clausula)
                            @include('signature.contracts.partials.clause', ['k' => $clausula['uid'], 'cl' => $clausula, 'comments' => collect()])
                        @endforeach
                    </div>
                    <div class="relative flex flex-wrap justify-center gap-2 rounded-2xl border border-dashed border-line-strong p-3" data-library-wrap>
                        <button type="button" data-add="clause" class="rounded-full bg-grena px-3 py-1.5 text-xs font-bold text-white hover:bg-grena-hover">+ Cláusula no fim</button>
                        <button type="button" data-library-open class="{{ $add }}">Da biblioteca…</button>
                    </div>
                </section>
                </div>

                <section class="{{ $card }}" data-step="Quem assina" data-step-keys="signers">
                    <div class="flex items-center justify-between gap-2">
                        <p class="{{ $h }}">Quem assina</p>
                        <button type="button" data-add="signer" class="{{ $add }}">+ Signatário</button>
                    </div>
                    <p class="text-xs text-ink-3">
                        Quem assina nos contratos feitos com o modelo: o papel (contratada, fiador, testemunha…) e o cargo que sai sob o nome.
                        O presidente assina pelo clube automaticamente. Sem ninguém aqui, o contrato nasce com quem assina pela contratada.
                    </p>
                    <div class="space-y-3" data-list="signer">
                        @foreach($content['signers'] ?? [] as $signer)
                            @include('signature.contracts.partials.signer', ['k' => $signer['uid'], 's' => $signer, 'modelo' => true])
                        @endforeach
                    </div>
                </section>

                @include('signature.documents.partials.steps')

                <div class="sticky bottom-3 z-10 space-y-3 rounded-card bg-surface p-4 shadow-card">
                    <div class="flex flex-wrap items-center justify-end gap-2" data-step-actions>
                        <span class="mr-auto text-xs text-ink-3" data-save-state aria-live="polite">O modelo grava quando você clica em Salvar.</span>
                        <x-primary-button type="submit">Salvar modelo</x-primary-button>
                    </div>
                </div>
            </form>

            <aside class="split-pane min-w-0 xl:col-span-5" data-side>
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex w-fit max-w-full gap-1 overflow-x-auto rounded-full bg-subtle p-1" role="tablist" aria-label="Ao lado do texto">
                            <button type="button" role="tab" data-side-tab="previa" aria-selected="true" class="{{ $tab }}">Prévia</button>
                            <button type="button" role="tab" data-side-tab="conferencia" aria-selected="false" class="{{ $tab }}">
                                Conferência
                                <span data-problem-count @class([
                                    'rounded-full px-2 text-[11px]',
                                    'bg-danger text-white' => $erros > 0,
                                    'bg-warn-soft text-warn' => $erros === 0 && $avisos > 0,
                                    'bg-ok-soft text-ok' => $erros === 0 && $avisos === 0,
                                ])>{{ $erros > 0 ? $erros : ($avisos > 0 ? $avisos : '✓') }}</span>
                            </button>
                        </div>
                        <button type="button" data-preview class="{{ $add }}">Atualizar prévia</button>
                    </div>

                    <div data-side-panel="previa">
                        <p class="mb-2 text-xs text-ink-3">As variáveis e os dados da contratada aparecem em branco: é o contrato que os preenche.</p>
                        <div class="rounded-card shadow-card">
                            <div class="ct-paper" data-preview-paper>{!! $preview !!}</div>
                        </div>
                    </div>

                    <div data-side-panel="conferencia" class="hidden">
                        <div class="rounded-card bg-surface p-5 shadow-card">
                            <p class="{{ $h }} mb-3">Conferência do modelo</p>
                            @include('signature.contracts.partials.problems', ['problems' => $problems])
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </x-page>

    <template id="tpl-party">@include('signature.contracts.partials.party', ['k' => '__K__', 'p' => ['uid' => '__K__'], 'modelo' => true])</template>
    <template id="tpl-variable">@include('signature.contracts.partials.variable', ['k' => '__K__', 'v' => [], 'modelo' => true])</template>
    <template id="tpl-consideration">@include('signature.contracts.partials.consideration', ['k' => '__K__', 'html' => ''])</template>
    <template id="tpl-clause">@include('signature.contracts.partials.clause', ['k' => '__K__', 'cl' => ['uid' => '__K__', 'items' => []], 'comments' => collect()])</template>
    <template id="tpl-item">@include('signature.contracts.partials.item', ['c' => '__C__', 'k' => '__K__', 'i' => ['uid' => '__K__', 'level' => 1]])</template>
    <template id="tpl-signer">@include('signature.contracts.partials.signer', ['k' => '__K__', 's' => ['uid' => '__K__', 'party' => 'testemunha'], 'modelo' => true])</template>

    @include('signature.contracts.partials.editor-js', [
        'ctConfig' => [
            'csrf' => csrf_token(),
            'sectorId' => $model->sector_id,
            'tokens' => $tokens,
            'outline' => collect($outline)->map(fn ($o, $uid) => ['uid' => $uid] + $o)->values(),
            'revision' => 0,
            'guess' => \App\Services\Signature\Contracts\ContractVariables::GUESS,
            'common' => $commonVariables,
            'usage' => (object) $variableUsage,
            'urls' => [
                // Sem salvar automático no modelo: grava no botão.
                'autosave' => null,
                'preview' => route('contract-library.models.preview', $model),
                'library' => route('contract-library.models.clauses', $model),
                'libraryStore' => route('contract-library.store'),
                'parties' => route('contracts.parties'),
            ],
        ],
    ])
</x-app-layout>
