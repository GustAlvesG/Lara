{{--
    Financeiro, nível 2: os contratos de um lote e a baixa de pagamento.

    Só os contratos pagáveis do lote aparecem — o que a gerência recusou saiu
    do lote e voltou para a fila do coordenador. Por isso o cabeçalho mostra o
    total **a pagar**, que pode ser menor que o total que a diretoria aprovou.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Serviços / Contratos') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    {{-- Tabela larga: sem o cap de largura ela deixa de ser cortada. --}}
    <div class="max-w-full mx-auto sm:px-6 lg:px-8">

        <div class="mb-6">
            <a href="{{ route('freelancer-services.finance') }}"
               class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 dark:text-gray-400 hover:text-[#A00001] dark:hover:text-red-400 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                Todos os lotes
            </a>
        </div>

        <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">
                    Lote #{{ $batch->id }} · Financeiro
                </h1>
                <p class="text-gray-500 dark:text-gray-400 font-medium">
                    {{ $services->count() }} contrato(s) aprovado(s) pela diretoria em
                    {{ $batch->director_decided_at?->format('d/m/Y H:i') ?? '—' }}.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('freelancer-services.finance.print', $batch) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold text-gray-700 dark:text-gray-200
                          bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:border-gray-300 shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Imprimir relação
                </a>
                <span class="px-4 py-2 rounded-xl text-sm font-bold
                    {{ $pendingTotal > 0
                        ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'
                        : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' }}">
                    {{ $pendingTotal > 0 ? 'A pagar' : 'Quitado' }}
                </span>
            </div>
        </div>

        @include('freelancer.services.partials.finance-pix-warning')

        @include('freelancer.services.partials.tabs', ['activeTab' => 'freelancer-services.finance'])

        @include('partials.alerts')

        <div class="mb-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-6">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">A pagar</p>
                <p class="mt-1 text-2xl font-extrabold text-gray-900 dark:text-white">R$ {{ number_format($pendingTotal, 2, ',', '.') }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-6">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Pago</p>
                <p class="mt-1 text-2xl font-extrabold text-emerald-600 dark:text-emerald-400">R$ {{ number_format($paidTotal, 2, ',', '.') }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-6">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Total do lote</p>
                <p class="mt-1 text-2xl font-extrabold text-gray-900 dark:text-white">R$ {{ number_format($pendingTotal + $paidTotal, 2, ',', '.') }}</p>
            </div>
        </div>

        {{-- Trilha das aprovações, resumida. A relação impressa traz a versão
             completa, contrato a contrato. --}}
        <div class="mb-6 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
            <span class="font-bold text-gray-900 dark:text-white">Trâmite:</span>
            montado por {{ $batch->createdBy->name ?? '—' }}
            @if($batch->sent_at) · enviado {{ $batch->sent_at->format('d/m/Y H:i') }} @endif
            · gerência {{ $batch->reviewedBy->name ?? '—' }}
            @if($batch->reviewed_at) em {{ $batch->reviewed_at->format('d/m/Y H:i') }} @endif
            · diretoria {{ $batch->director_email ?? '—' }}
            @if($batch->director_decided_at)
                em {{ $batch->director_decided_at->format('d/m/Y H:i') }}
            @endif
            @if($batch->directorDecidedBy)
                (registrado por {{ $batch->directorDecidedBy->name }})
            @endif
        </div>

        @include('freelancer.services.partials.finance-table', [
            'emptyText' => 'Nenhum contrato pagável neste lote.',
        ])
    </div>
</div>
</x-app-layout>
