{{-- Cartão de um lote na lista do Financeiro. Espera `$batch` com os agregados
     `payable_count`, `paid_count`, `payable_total` e `paid_total`. --}}
@php
    $pagos = $batch->financePaidCount();
    $total = $batch->financePayableCount();
    $aPagar = (float) $batch->payable_total - (float) $batch->paid_total;

    $tom = match (true) {
        $batch->isFullyPaid() => 'bg-ok-soft text-ok',
        $batch->isPartiallyPaid() => 'bg-grena-tint text-grena-ink',
        default => 'bg-warn-soft text-warn',
    };
@endphp

<a data-search="" href="{{ route('freelancer-services.finance.batch', $batch) }}"
   class="block bg-surface rounded-2xl shadow-card border border-line
          hover:border-grena hover:shadow-pop transition p-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex items-center gap-3">
                <h3 class="text-xl font-extrabold text-ink">Lote #{{ $batch->id }}</h3>
                <span class="px-3 py-1 rounded-lg text-xs font-bold {{ $tom }}">{{ $batch->financeStatusLabel() }}</span>
            </div>
            <p class="mt-1 text-sm text-ink-2">
                Aprovado pela diretoria em
                <b class="text-ink">{{ $batch->director_decided_at?->format('d/m/Y H:i') ?? '—' }}</b>
                @if($batch->createdBy)
                    · montado por {{ $batch->createdBy->name }}
                @endif
            </p>
            <p class="mt-1 text-sm text-ink-2">
                {{ $pagos }} de {{ $total }} contrato(s) pago(s)
            </p>
        </div>

        <div class="text-right shrink-0">
            @if($aPagar > 0)
                <p class="text-xs font-bold uppercase tracking-wider text-ink-3">A pagar</p>
                <p class="text-2xl font-extrabold text-ink">R$ {{ number_format($aPagar, 2, ',', '.') }}</p>
            @else
                <p class="text-xs font-bold uppercase tracking-wider text-ink-3">Pago</p>
                <p class="text-2xl font-extrabold text-ok">R$ {{ number_format((float) $batch->paid_total, 2, ',', '.') }}</p>
            @endif
            <p class="mt-1 text-xs text-ink-3">
                total do lote R$ {{ number_format((float) $batch->payable_total, 2, ',', '.') }}
            </p>
        </div>
    </div>
</a>
