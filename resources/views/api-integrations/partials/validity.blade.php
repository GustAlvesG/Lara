{{-- Validade do token, escolhida na geração e na rotação (obrigatória).
     $prefix diferencia os ids quando há mais de um formulário na tela. --}}
@php($prefix = $prefix ?? 'gen')
<div x-data="{ v: @js(old('validade', '')) }" class="flex flex-col gap-2">
    <x-input-label :for="$prefix . '-validade'" value="Validade" />
    <x-select-input :id="$prefix . '-validade'" name="validade" required x-model="v">
        <option value="" disabled>Escolha…</option>
        @foreach (\App\Http\Controllers\ApiIntegrationController::VALIDITY_DAYS as $days)
            <option value="{{ $days }}">{{ $days }} dias (até {{ now()->addDays($days)->format('d/m/Y') }})</option>
        @endforeach
        <option value="data">Até uma data…</option>
        <option value="sem">Sem validade</option>
    </x-select-input>
    <template x-if="v === 'data'">
        <x-text-input type="date" name="validade_data" :value="old('validade_data')" :min="now()->addDay()->toDateString()" required />
    </template>
    <p x-show="v === 'sem'" x-cloak class="text-xs text-warn">Sem validade, o token só para de valer quando alguém revogar. Prefira uma data.</p>
</div>
