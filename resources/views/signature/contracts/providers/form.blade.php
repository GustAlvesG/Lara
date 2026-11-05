{{--
    Cadastro e edição de um prestador (05/11/2026). Os mesmos campos do contratado na
    criação do contrato; o documento é único no cadastro. Editar não muda contrato já
    redigido (a parte fica copiada dentro dele).
--}}
@php
    $field = 'w-full rounded-xl border border-line-strong bg-surface px-4 py-2.5 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
    $label = 'mb-1 block text-sm font-bold text-ink';
    $card = 'space-y-5 rounded-card bg-surface p-6 shadow-card';
    $editando = $provider->exists;
    $tipo = old('kind', $provider->kind ?: 'pj');
    $v = fn (string $c) => old($c, in_array($c, \App\Models\SignatureContractParty::DATA_FIELDS, true) ? $provider->field($c) : $provider->{$c});
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page narrow>
        <x-page-title :title="$editando ? $provider->name : 'Novo prestador'" :back="route('contract-providers.index')">
            @if($editando)
                {{ $provider->active ? 'Ativo' : 'Inativo' }}
                @if($provider->created_by_name) · cadastrado por {{ $provider->created_by_name }} @endif
                @if($provider->updated_by_name) · atualizado por {{ $provider->updated_by_name }} em {{ $provider->updated_at?->format('d/m/Y H:i') }} @endif
            @else
                Fica disponível na busca do contratado ao redigir um contrato.
            @endif
        </x-page-title>

        @include('partials.alerts')

        <form method="POST" action="{{ $editando ? route('contract-providers.update', $provider) : route('contract-providers.store') }}" class="space-y-5" data-provider-form>
            @csrf
            @if($editando) @method('PUT') @endif

            <section class="{{ $card }}">
                <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Tipo do prestador">
                    @foreach(['pj' => 'Empresa (CNPJ)', 'pf' => 'Pessoa física (CPF)'] as $valor => $rotulo)
                        <label class="cursor-pointer">
                            <input type="radio" name="kind" value="{{ $valor }}" class="peer sr-only" data-party-kind @checked($tipo === $valor)>
                            <span class="inline-block rounded-full bg-subtle px-4 py-2 text-sm font-bold text-ink peer-checked:bg-grena peer-checked:text-white">{{ $rotulo }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="name" class="{{ $label }}">Nome / razão social</label>
                        <input type="text" id="name" name="name" value="{{ $v('name') }}" class="{{ $field }}" maxlength="200" required>
                        @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="document" class="{{ $label }}" data-doc-label>{{ $tipo === 'pf' ? 'CPF' : 'CNPJ' }}</label>
                        <input type="text" id="document" name="document" value="{{ old('document', $editando ? $provider->formattedDocument() : '') }}" class="{{ $field }}" maxlength="20" required>
                        @error('document')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="phone" class="{{ $label }}">Telefone</label>
                        <input type="text" id="phone" name="phone" value="{{ $v('phone') }}" class="{{ $field }}" maxlength="30">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="address" class="{{ $label }}">Endereço (sede ou residência)</label>
                        <input type="text" id="address" name="address" value="{{ $v('address') }}" class="{{ $field }}" maxlength="300" required
                               placeholder="Rua, nº, bairro, cidade/UF, CEP">
                        @error('address')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div data-only="pj" @class(['grid grid-cols-1 gap-4 sm:col-span-2 sm:grid-cols-2', 'hidden' => $tipo === 'pf'])>
                        <div>
                            <label for="rep_name" class="{{ $label }}">Representante (quem assina pela empresa)</label>
                            <input type="text" id="rep_name" name="rep_name" value="{{ $v('rep_name') }}" class="{{ $field }}" maxlength="200" @required($tipo === 'pj')>
                            @error('rep_name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="rep_cpf" class="{{ $label }}">CPF do representante</label>
                            <input type="text" id="rep_cpf" name="rep_cpf" value="{{ $v('rep_cpf') }}" class="{{ $field }}" maxlength="14" inputmode="numeric" @required($tipo === 'pj')>
                            @error('rep_cpf')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div data-only="pf" @class(['sm:col-span-2', 'hidden' => $tipo !== 'pf'])>
                        <label for="gender" class="{{ $label }}">Concordância ("inscrito/inscrita")</label>
                        <select id="gender" name="gender" class="{{ $field }}">
                            <option value="m" @selected($v('gender') !== 'f')>Masculino</option>
                            <option value="f" @selected($v('gender') === 'f')>Feminino</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="email" class="{{ $label }}">E-mail</label>
                        <input type="email" id="email" name="email" value="{{ $v('email') }}" class="{{ $field }}" maxlength="150">
                        <p class="mt-1 text-xs text-ink-3">Para mandar o link de assinatura a distância a quem assina pelo prestador.</p>
                        @error('email')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="details" class="{{ $label }}">Qualificação complementar (opcional)</label>
                        <textarea id="details" name="details" rows="2" class="{{ $field }}" maxlength="500"
                                  placeholder="Pessoa física: brasileira, casada, empresária, RG nº …   Empresa: Inscrição Estadual nº …">{{ $v('details') }}</textarea>
                    </div>
                </div>
            </section>

            <div class="flex flex-wrap justify-end gap-2">
                <x-secondary-button-a href="{{ route('contract-providers.index') }}">Voltar</x-secondary-button-a>
                <x-primary-button>{{ $editando ? 'Salvar' : 'Cadastrar' }}</x-primary-button>
            </div>
        </form>

        @if($editando)
            <section class="{{ $card }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-display text-base font-semibold text-ink">Contratos com este prestador</p>
                    <form method="POST" action="{{ route('contract-providers.toggle', $provider) }}"
                          onsubmit="return confirm('{{ $provider->active ? 'Inativar? O prestador some da busca do contrato.' : 'Reativar o prestador?' }}')">
                        @csrf
                        <button type="submit" class="text-sm font-bold {{ $provider->active ? 'text-danger' : 'text-ok' }} hover:underline">{{ $provider->active ? 'Inativar prestador' : 'Reativar prestador' }}</button>
                    </form>
                </div>
                @php $visiveis = ($contracts ?? collect())->filter(fn ($c) => auth()->user()->can('view', $c)); @endphp
                @forelse($visiveis as $c)
                    <p class="text-sm">
                        <a href="{{ route('contracts.show', $c) }}" class="font-bold text-grena-ink hover:underline">{{ $c->number ?? 'Sem número' }} — {{ $c->title }}</a>
                        <span class="text-xs text-ink-2">· {{ $c->statusLabel() }} · {{ $c->sector_name }}</span>
                    </p>
                @empty
                    <p class="text-sm text-ink-2">Nenhum contrato que você possa ver usa este prestador.</p>
                @endforelse
            </section>
        @endif
    </x-page>

    <script>
        (function () {
            var form = document.querySelector('[data-provider-form]');
            if (!form) return;
            function aplicaTipo() {
                var marcado = form.querySelector('[data-party-kind]:checked');
                var t = marcado ? marcado.value : 'pj';
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
        })();
    </script>
</x-app-layout>
