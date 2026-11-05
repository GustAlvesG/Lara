{{--
    Novo contrato, em passos como o documento comum (signature.documents.
    partials.steps): 1. Contrato (setor, modelo, título); 2. Contratado (a
    outra parte, com busca no cadastro). Ao criar, o contratado já entra como
    parte e como quem assina pela contratada; o editor abre nas variáveis,
    cláusulas e signatários (04/11/2026, pedido do usuário).
--}}
@php
    $field = 'w-full rounded-xl border border-line-strong bg-surface px-4 py-2.5 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
    $label = 'mb-1 block text-sm font-bold text-ink';
    $card = 'space-y-5 rounded-card bg-surface p-6 shadow-card';
    $tipo = old('party.kind', 'pj');
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page narrow>
        <x-page-title title="Novo contrato" :back="route('contracts.index')">
            Primeiro o contrato e o contratado; depois as variáveis, as cláusulas e quem assina.
        </x-page-title>

        @include('partials.alerts')

        @if($sectors->isEmpty())
            <x-empty-state icon="x">
                Você não está em nenhum setor. Peça à TI para incluí-lo no seu setor antes de redigir contratos —
                o contrato pertence ao setor de quem o redige.
            </x-empty-state>
        @else
            <form method="POST" action="{{ route('contracts.store') }}" class="space-y-5" data-contract-create>
                @csrf

                {{-- 1. Contrato --}}
                <section class="{{ $card }}" data-step="Contrato" data-step-keys="sector_id,model_id,title">
                    <div>
                        <label for="sector_id" class="{{ $label }}">Setor</label>
                        <select name="sector_id" id="sector_id" class="{{ $field }}" required>
                            @foreach($sectors as $sector)
                                <option value="{{ $sector->id }}" @selected((int) old('sector_id') === $sector->id)>{{ $sector->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-ink-3">Só o setor escolhido (e a Gerência) vê este contrato.</p>
                        @error('sector_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="model_id" class="{{ $label }}">Modelo</label>
                        <select name="model_id" id="model_id" class="{{ $field }}">
                            <option value="">Em branco</option>
                            @foreach($models as $model)
                                <option value="{{ $model->id }}" data-sector="{{ $model->sector_id }}" @selected((int) old('model_id') === $model->id)>
                                    {{ $model->name }} — {{ $model->sector_name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-ink-3">O modelo traz título, termos das partes, variáveis (sem valor), considerandos e cláusulas.</p>
                    </div>

                    <div>
                        <label for="title" class="{{ $label }}">Título</label>
                        <input type="text" name="title" id="title" maxlength="200" value="{{ old('title') }}" class="{{ $field }}"
                               placeholder="Contrato de prestação de serviços (em branco = o do modelo)">
                        @error('title')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                </section>

                {{-- 2. Contratado --}}
                <section class="{{ $card }}" data-step="Contratado" data-step-keys="party">
                    <div class="rounded-xl bg-subtle p-3">
                        <label for="party_search" class="mb-1 block text-xs font-bold text-ink-2">Buscar nos Prestadores (Contratos → Prestadores)</label>
                        <input type="search" id="party_search" data-member-search data-party-search class="{{ $field }}" placeholder="Nome, CNPJ ou CPF">
                        <div data-party-results class="mt-2 space-y-1"></div>
                    </div>

                    <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Tipo do contratado">
                        @foreach(['pj' => 'Empresa (CNPJ)', 'pf' => 'Pessoa física (CPF)'] as $valor => $rotulo)
                            <label class="cursor-pointer">
                                <input type="radio" name="party[kind]" value="{{ $valor }}" class="peer sr-only" data-party-kind @checked($tipo === $valor)>
                                <span class="inline-block rounded-full bg-subtle px-4 py-2 text-sm font-bold text-ink peer-checked:bg-grena peer-checked:text-white">{{ $rotulo }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="party_name" class="{{ $label }}">Nome / razão social</label>
                            <input type="text" id="party_name" name="party[name]" value="{{ old('party.name') }}" class="{{ $field }}" maxlength="200" required>
                            @error('party.name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="party_document" class="{{ $label }}" data-doc-label>{{ $tipo === 'pf' ? 'CPF' : 'CNPJ' }}</label>
                            <input type="text" id="party_document" name="party[document]" value="{{ old('party.document') }}" class="{{ $field }}" maxlength="20" required>
                            @error('party.document')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="party_phone" class="{{ $label }}">Telefone</label>
                            <input type="text" id="party_phone" name="party[phone]" value="{{ old('party.phone') }}" class="{{ $field }}" maxlength="30">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="party_address" class="{{ $label }}">Endereço (sede ou residência)</label>
                            <input type="text" id="party_address" name="party[address]" value="{{ old('party.address') }}" class="{{ $field }}" maxlength="300" required
                                   placeholder="Rua, nº, bairro, cidade/UF, CEP">
                            @error('party.address')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>
                        <div data-only="pj" @class(['grid grid-cols-1 gap-4 sm:col-span-2 sm:grid-cols-2', 'hidden' => $tipo === 'pf'])>
                            <div>
                                <label for="party_rep_name" class="{{ $label }}">Representante (quem assina pela empresa)</label>
                                <input type="text" id="party_rep_name" name="party[rep_name]" value="{{ old('party.rep_name') }}" class="{{ $field }}" maxlength="200" @required($tipo === 'pj')>
                                @error('party.rep_name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="party_rep_cpf" class="{{ $label }}">CPF do representante</label>
                                <input type="text" id="party_rep_cpf" name="party[rep_cpf]" value="{{ old('party.rep_cpf') }}" class="{{ $field }}" maxlength="14" inputmode="numeric" @required($tipo === 'pj')>
                                @error('party.rep_cpf')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div data-only="pf" @class(['sm:col-span-2', 'hidden' => $tipo !== 'pf'])>
                            <label for="party_gender" class="{{ $label }}">Concordância ("inscrito/inscrita")</label>
                            <select id="party_gender" name="party[gender]" class="{{ $field }}">
                                <option value="m" @selected(old('party.gender', 'm') === 'm')>Masculino</option>
                                <option value="f" @selected(old('party.gender') === 'f')>Feminino</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="party_email" class="{{ $label }}">E-mail</label>
                            <input type="email" id="party_email" name="party[email]" value="{{ old('party.email') }}" class="{{ $field }}" maxlength="150">
                            <p class="mt-1 text-xs text-ink-3">Para mandar o link de assinatura a distância a quem assina pela contratada.</p>
                            @error('party.email')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="party_details" class="{{ $label }}">Qualificação complementar (opcional)</label>
                            <textarea id="party_details" name="party[details]" rows="2" class="{{ $field }}" maxlength="500"
                                      placeholder="Pessoa física: brasileira, casada, empresária, RG nº …   Empresa: Inscrição Estadual nº …">{{ old('party.details') }}</textarea>
                        </div>
                    </div>
                    <p class="text-xs text-ink-3">
                        O contratado fica guardado em Prestadores para os próximos contratos e já entra em "Quem assina".
                        Fiadores e outras partes se incluem no editor.
                    </p>
                </section>

                @include('signature.documents.partials.steps')

                <div class="flex flex-wrap justify-end gap-2" data-step-actions>
                    <x-primary-button>Criar e ir para as cláusulas</x-primary-button>
                </div>
            </form>
        @endif
    </x-page>

    <script>
        (function () {
            var form = document.querySelector('[data-contract-create]');
            if (!form) return;

            // O modelo é do setor: ao trocar o setor, só os modelos dele ficam escolhíveis.
            var setor = document.getElementById('sector_id');
            var modelo = document.getElementById('model_id');
            function filtra() {
                Array.prototype.forEach.call(modelo.options, function (op) {
                    if (!op.dataset.sector) return;
                    op.hidden = op.dataset.sector !== setor.value;
                    if (op.hidden && op.selected) modelo.value = '';
                });
            }
            setor.addEventListener('change', filtra);
            filtra();

            // Empresa × pessoa física: troca os campos do representante e o rótulo do documento.
            function tipo() {
                var marcado = form.querySelector('[data-party-kind]:checked');
                return marcado ? marcado.value : 'pj';
            }
            function aplicaTipo() {
                var t = tipo();
                form.querySelectorAll('[data-only]').forEach(function (bloco) {
                    var mostra = bloco.getAttribute('data-only') === t;
                    bloco.classList.toggle('hidden', !mostra);
                    bloco.querySelectorAll('input').forEach(function (campo) {
                        if (bloco.getAttribute('data-only') === 'pj') campo.required = mostra;
                    });
                });
                form.querySelector('[data-doc-label]').textContent = t === 'pf' ? 'CPF' : 'CNPJ';
            }
            form.querySelectorAll('[data-party-kind]').forEach(function (r) { r.addEventListener('change', aplicaTipo); });
            aplicaTipo();

            // Busca no cadastro de contratadas (a mesma do editor).
            var busca = form.querySelector('[data-party-search]');
            var caixa = form.querySelector('[data-party-results]');
            var lista = [];
            var espera = null;
            function escapa(t) {
                return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            }
            busca.addEventListener('input', function () {
                clearTimeout(espera);
                var termo = busca.value.trim();
                if (termo.length < 2) { caixa.innerHTML = ''; return; }
                espera = setTimeout(function () {
                    fetch(@json(route('contracts.parties')) + '?q=' + encodeURIComponent(termo), { headers: { 'Accept': 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (res) {
                            lista = res;
                            caixa.innerHTML = res.length
                                ? res.map(function (p, i) {
                                    return '<button type="button" class="block w-full rounded-lg bg-surface px-3 py-1.5 text-left text-sm hover:bg-line" data-party-pick="' + i + '">'
                                        + escapa(p.name) + ' <span class="text-xs text-ink-3">' + escapa(p.document) + '</span></button>';
                                }).join('')
                                : '<p class="px-1 text-xs text-ink-3">Nada no cadastro. Preencha abaixo: ao criar, fica guardado.</p>';
                        });
                }, 350);
            });
            caixa.addEventListener('click', function (e) {
                var b = e.target.closest('[data-party-pick]');
                if (!b) return;
                var p = lista[+b.getAttribute('data-party-pick')] || {};
                var radio = form.querySelector('[data-party-kind][value="' + (p.kind === 'pf' ? 'pf' : 'pj') + '"]');
                if (radio) radio.checked = true;
                ['name', 'document', 'address', 'details', 'rep_name', 'rep_cpf', 'email', 'phone', 'gender'].forEach(function (c) {
                    var el = form.querySelector('[name="party[' + c + ']"]');
                    if (el && p[c] != null) el.value = p[c];
                });
                aplicaTipo();
                caixa.innerHTML = '';
                busca.value = '';
            });
        })();
    </script>
</x-app-layout>
