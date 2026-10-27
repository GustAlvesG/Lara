{{-- Campos do lote, no "novo lote" e na edição. `$key` separa os ids quando
     há vários formulários na página; `$lot` é o lote em edição ou null. --}}
@php
    $dt = fn ($value) => $value ? $value->format('Y-m-d\TH:i') : '';
    $money = fn ($value) => $value === null ? '' : number_format((float) $value, 2, '.', '');
@endphp
<div class="sm:col-span-2"><x-input-label for="{{ $key }}-name" value="Nome" /><x-text-input id="{{ $key }}-name" name="name" class="mt-1" :value="$lot->name ?? $suggestedName" required maxlength="255" /></div>
<div><x-input-label for="{{ $key }}-sequence" value="Ordem" /><x-text-input type="number" min="1" id="{{ $key }}-sequence" name="sequence" class="mt-1" :value="$lot->sequence ?? $suggestedSequence" required /></div>
<div><x-input-label for="{{ $key }}-quantity" value="Quantidade" /><x-text-input type="number" min="0" id="{{ $key }}-quantity" name="quantity" class="mt-1" :value="$lot->quantity ?? ''" required /></div>
<div><x-input-label for="{{ $key }}-price_member" value="Preço sócio (R$)" /><x-text-input type="number" step="0.01" min="0" id="{{ $key }}-price_member" name="price_member" class="mt-1" :value="$money($lot?->price_member)" required /></div>
<div>
    <x-input-label for="{{ $key }}-price_non_member" value="Preço não sócio (R$)" />
    <x-text-input type="number" step="0.01" min="0" id="{{ $key }}-price_non_member" name="price_non_member" class="mt-1" :value="$money($lot?->price_non_member)" placeholder="Só sócios" />
</div>
<div class="sm:col-span-2"><x-input-label for="{{ $key }}-starts_at" value="Abre em (opcional)" /><x-text-input type="datetime-local" id="{{ $key }}-starts_at" name="starts_at" class="mt-1" :value="$dt($lot?->starts_at)" /></div>
<div class="sm:col-span-2"><x-input-label for="{{ $key }}-ends_at" value="Fecha em (opcional)" /><x-text-input type="datetime-local" id="{{ $key }}-ends_at" name="ends_at" class="mt-1" :value="$dt($lot?->ends_at)" /></div>
