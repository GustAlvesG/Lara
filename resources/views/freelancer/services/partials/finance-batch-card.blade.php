{{-- Cartão de um lote na lista do Financeiro. Espera `$batch` com os agregados
     `payable_count`, `paid_count`, `payable_total` e `paid_total`. --}}
@php
    $pagos = $batch->financePaidCount();
    $total = $batch->financePayableCount();
    $aPagar = (float) $batch->payable_total - (float) $batch->paid_total;

    $tom = match (true) {
        $batch->isFullyPaid() => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        $batch->isPartiallyPaid() => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
        default => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    };
@endphp

<a href="{{ route('freelancer-services.finance.batch', $batch) }}"
   class="block bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700
          hover:border-[#A00001] dark:hover:border-red-400 hover:shadow-xl transition p-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex items-center gap-3">
                <h3 class="text-xl font-extrabold text-gray-900 dark:text-white">Lote #{{ $batch->id }}</h3>
                <span class="px-3 py-1 rounded-lg text-xs font-bold {{ $tom }}">{{ $batch->financeStatusLabel() }}</span>
            </div>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Aprovado pela diretoria em
                <b class="text-gray-700 dark:text-gray-300">{{ $batch->director_decided_at?->format('d/m/Y H:i') ?? '—' }}</b>
                @if($batch->createdBy)
                    · montado por {{ $batch->createdBy->name }}
                @endif
            </p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $pagos }} de {{ $total }} contrato(s) pago(s)
            </p>
        </div>

        <div class="text-right shrink-0">
            @if($aPagar > 0)
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">A pagar</p>
                <p class="text-2xl font-extrabold text-gray-900 dark:text-white">R$ {{ number_format($aPagar, 2, ',', '.') }}</p>
            @else
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Pago</p>
                <p class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400">R$ {{ number_format((float) $batch->paid_total, 2, ',', '.') }}</p>
            @endif
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                total do lote R$ {{ number_format((float) $batch->payable_total, 2, ',', '.') }}
            </p>
        </div>
    </div>
</a>
