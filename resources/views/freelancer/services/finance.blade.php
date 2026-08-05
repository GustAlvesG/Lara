{{--
    Financeiro, nível 1: a lista de LOTES aprovados pela diretoria.

    A diretoria aprova um bloco de contratos e é esse bloco que o financeiro
    quita — por isso a tela não abre mais numa lista solta de contratos. O
    pagamento acontece dentro do lote (finance-batch).
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Serviços / Contratos') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Financeiro</h1>
            <p class="text-gray-500 dark:text-gray-400 font-medium">
                Lotes aprovados pela diretoria, prontos para pagamento. Abra um lote para dar baixa.
            </p>
        </div>

        @include('freelancer.services.partials.finance-pix-warning')

        @include('freelancer.services.partials.tabs')

        @include('partials.alerts')

        <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-6">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">A pagar</p>
                <p class="mt-1 text-2xl font-extrabold text-gray-900 dark:text-white">R$ {{ number_format($pendingTotal, 2, ',', '.') }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 p-6">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Pago</p>
                <p class="mt-1 text-2xl font-extrabold text-emerald-600 dark:text-emerald-400">R$ {{ number_format($paidTotal, 2, ',', '.') }}</p>
            </div>
        </div>

        {{-- Escapes: nada de pagável pode ficar invisível por não estar em lote. --}}
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <a href="{{ route('freelancer-services.finance.all') }}"
               class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-bold text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:border-gray-300 shadow-sm transition">
                Ver todos os contratos
            </a>
            @if($orphanCount > 0)
                <a href="{{ route('freelancer-services.finance.orphans') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold text-amber-800 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 hover:bg-amber-100 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0l-7.1 12.25A2 2 0 005 19z"></path></svg>
                    {{ $orphanCount }} contrato(s) sem lote
                </a>
            @endif
        </div>

        @if($aPagar->isEmpty() && $quitados->isEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-12 text-center">
                <p class="text-gray-500 dark:text-gray-400">
                    Nenhum lote aprovado pela diretoria até o momento.
                </p>
                <p class="mt-2 text-sm text-gray-400 dark:text-gray-500">
                    Os contratos chegam aqui depois de passar pelo coordenador, pela gerência e pela diretoria.
                </p>
            </div>
        @endif

        @if($aPagar->isNotEmpty())
            <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                Aguardando pagamento ({{ $aPagar->count() }})
            </h2>
            <div class="mb-8 space-y-3">
                @foreach($aPagar as $batch)
                    @include('freelancer.services.partials.finance-batch-card', ['batch' => $batch])
                @endforeach
            </div>
        @endif

        @if($quitados->isNotEmpty())
            <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                Quitados ({{ $quitados->count() }})
            </h2>
            <div class="space-y-3">
                @foreach($quitados as $batch)
                    @include('freelancer.services.partials.finance-batch-card', ['batch' => $batch])
                @endforeach
            </div>
        @endif
    </div>
</div>
</x-app-layout>
