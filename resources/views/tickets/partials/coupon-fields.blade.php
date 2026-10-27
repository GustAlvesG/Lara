{{-- Campos do cupom (novo e edição). `$key` separa os ids; `$coupon` é o
     cupom em edição ou null; `$event` traz os tipos. --}}
@php
    use App\Models\Ticket\TicketCoupon;

    $select = 'mt-1 h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-ink';
    $dt = fn ($value) => $value ? $value->format('Y-m-d\TH:i') : '';
    $chosen = array_map('intval', old('ticket_type_ids', $coupon?->ticket_type_ids ?? []));
@endphp
<div><x-input-label for="{{ $key }}-code" value="Código" /><x-text-input id="{{ $key }}-code" name="code" class="mt-1 font-mono uppercase" :value="$coupon->code ?? ''" required maxlength="40" placeholder="EX.: SOCIO10" /></div>
<div>
    <x-input-label for="{{ $key }}-kind" value="Desconto em" />
    <select id="{{ $key }}-kind" name="kind" class="{{ $select }}">
        @foreach (TicketCoupon::KIND_LABELS as $value => $label)
            <option value="{{ $value }}" @selected(($coupon->kind ?? TicketCoupon::KIND_PERCENT) === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div><x-input-label for="{{ $key }}-value" value="Valor (% ou R$)" /><x-text-input type="number" step="0.01" min="0.01" id="{{ $key }}-value" name="value" class="mt-1" :value="$coupon ? number_format((float) $coupon->value, 2, '.', '') : ''" required /></div>
<div>
    <x-input-label for="{{ $key }}-audience" value="Público" />
    <select id="{{ $key }}-audience" name="audience" class="{{ $select }}">
        @foreach (TicketCoupon::AUDIENCE_LABELS as $value => $label)
            <option value="{{ $value }}" @selected(($coupon->audience ?? TicketCoupon::AUDIENCE_ALL) === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="sm:col-span-2"><x-input-label for="{{ $key }}-description" value="Descrição (opcional, aparece no site)" /><x-text-input id="{{ $key }}-description" name="description" class="mt-1" :value="$coupon->description ?? ''" maxlength="255" /></div>
<div><x-input-label for="{{ $key }}-max_uses" value="Limite de ingressos" /><x-text-input type="number" min="1" id="{{ $key }}-max_uses" name="max_uses" class="mt-1" :value="$coupon->max_uses ?? ''" placeholder="Sem limite" /></div>
<div></div>
<div class="sm:col-span-2"><x-input-label for="{{ $key }}-starts_at" value="Vale a partir de (opcional)" /><x-text-input type="datetime-local" id="{{ $key }}-starts_at" name="starts_at" class="mt-1" :value="$dt($coupon?->starts_at)" /></div>
<div class="sm:col-span-2"><x-input-label for="{{ $key }}-ends_at" value="Vale até (opcional)" /><x-text-input type="datetime-local" id="{{ $key }}-ends_at" name="ends_at" class="mt-1" :value="$dt($coupon?->ends_at)" /></div>
<fieldset class="sm:col-span-4">
    <legend class="text-sm font-medium text-ink-2">Tipos de ingresso (nenhum marcado = todos)</legend>
    <div class="mt-2 flex flex-wrap gap-4">
        @foreach ($event->types as $type)
            <label class="flex items-center gap-2 text-sm text-ink-2"><input type="checkbox" name="ticket_type_ids[]" value="{{ $type->id }}" @checked(in_array($type->id, $chosen, true))> {{ $type->fullName() }}</label>
        @endforeach
    </div>
</fieldset>
