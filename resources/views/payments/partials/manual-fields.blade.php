{{-- Campos da baixa manual (forma, data e comprovante), comuns à tela do
     agendamento de quadra e à da reserva social. O valor e os agendamentos
     vêm de cada tela. --}}
<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    <div>
        <x-input-label for="manual_payment_method" value="Forma de pagamento" />
        <select name="payment_method" id="manual_payment_method" required
                class="mt-1 h-11 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint">
            <option value="">Escolha…</option>
            @foreach (\App\Models\SchedulePayment::MANUAL_METHODS as $value => $label)
                <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-input-label for="manual_paid_at" value="Pago em" />
        <x-text-input type="datetime-local" id="manual_paid_at" name="paid_at" class="mt-1" :value="old('paid_at', now()->format('Y-m-d\TH:i'))" max="{{ now()->format('Y-m-d\TH:i') }}" />
    </div>
    <div class="sm:col-span-2">
        <x-input-label for="manual_notes" value="Comprovante / observação" />
        <x-text-input id="manual_notes" name="manual_notes" class="mt-1" :value="old('manual_notes')" maxlength="500" placeholder="Ex.: Pix ID E123…, recibo 456" />
    </div>
</div>
