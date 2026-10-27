{{--
    Novo contrato: o setor dono (só ele vê o contrato, além da Gerência), o
    título e, se quiser, um modelo do setor para começar.
--}}
@php
    $field = 'w-full rounded-xl border border-line-strong bg-surface px-4 py-2.5 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page narrow>
        <x-page-title title="Novo contrato" :back="route('signature-contracts.index')">
            Comece em branco ou a partir de um modelo do seu setor.
        </x-page-title>

        @include('partials.alerts')

        @if($sectors->isEmpty())
            <x-empty-state icon="x">
                Você não está em nenhum setor. Peça à TI para incluí-lo no seu setor antes de redigir contratos —
                o contrato pertence ao setor de quem o redige.
            </x-empty-state>
        @else
            <form method="POST" action="{{ route('signature-contracts.store') }}" class="space-y-5 rounded-card bg-surface p-6 shadow-card">
                @csrf

                <div>
                    <label for="sector_id" class="mb-1 block text-sm font-bold text-ink">Setor</label>
                    <select name="sector_id" id="sector_id" class="{{ $field }}" required>
                        @foreach($sectors as $sector)
                            <option value="{{ $sector->id }}" @selected((int) old('sector_id') === $sector->id)>{{ $sector->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-ink-3">Só o setor escolhido (e a Gerência) vê este contrato.</p>
                    @error('sector_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="model_id" class="mb-1 block text-sm font-bold text-ink">Modelo</label>
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
                    <label for="title" class="mb-1 block text-sm font-bold text-ink">Título</label>
                    <input type="text" name="title" id="title" maxlength="200" value="{{ old('title') }}" class="{{ $field }}"
                           placeholder="Contrato de prestação de serviços (em branco = o do modelo)">
                    @error('title')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                <div class="flex justify-end">
                    <x-primary-button>Criar e começar a redigir</x-primary-button>
                </div>
            </form>
        @endif
    </x-page>

    <script>
        // O modelo é do setor: ao trocar o setor, só os modelos dele ficam escolhíveis.
        (function () {
            var setor = document.getElementById('sector_id');
            var modelo = document.getElementById('model_id');
            if (!setor || !modelo) return;
            function filtra() {
                Array.prototype.forEach.call(modelo.options, function (op) {
                    if (!op.dataset.sector) return;
                    op.hidden = op.dataset.sector !== setor.value;
                    if (op.hidden && op.selected) modelo.value = '';
                });
            }
            setor.addEventListener('change', filtra);
            filtra();
        })();
    </script>
</x-app-layout>
