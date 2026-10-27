{{-- Forma, data e observação do pagamento recebido por fora da eRede. --}}
@php($select = 'mt-1 h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-ink')
<div>
    <x-input-label for="{{ $key }}-method" value="Forma de pagamento" />
    <select id="{{ $key }}-method" name="payment_method" class="{{ $select }}" required>
        <option value="">Escolha…</option>
        @foreach ($methods as $value => $label)
            <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div><x-input-label for="{{ $key }}-paid_at" value="Recebido em" /><x-text-input type="datetime-local" id="{{ $key }}-paid_at" name="paid_at" class="mt-1" :value="old('paid_at', now()->format('Y-m-d\TH:i'))" /></div>
<div class="sm:col-span-2"><x-input-label for="{{ $key }}-notes" value="Observação (opcional)" /><x-text-input id="{{ $key }}-notes" name="manual_notes" class="mt-1" :value="old('manual_notes')" maxlength="500" placeholder="Ex.: Pix recebido na conta do clube, comprovante no e-mail" /></div>
