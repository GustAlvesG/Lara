<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Serviços / Contratos') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Validação da coordenação</h1>
            <p class="text-gray-500 dark:text-gray-400 font-medium">
                Contratos da redação 2 já assinados pelo freelancer. Abra cada um, leia até o fim e valide com o seu PIN —
                validado, o contrato fica disponível para a montagem de lote.
            </p>
        </div>

        @include('freelancer.services.partials.tabs')
        @include('partials.alerts')

        @if($awaitingRelease > 0)
            <div class="mb-6 p-4 bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 rounded-lg text-sm">
                🕗 {{ $awaitingRelease }} contrato(s) assinado(s) ainda esperam o fim do dia do turno. Entram nesta fila às
                {{ str_pad((string) \App\Models\FreelancerService::RELEASE_HOUR, 2, '0', STR_PAD_LEFT) }}h da manhã seguinte,
                quando não cabe mais aditivo.
            </div>
        @endif

        {{-- Sem caixas de seleção nem ação em massa, de propósito: a validação é
             de cada documento, lido até o fim. A linha só abre o contrato. --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
            @if($services->isEmpty())
                <div class="px-6 py-16 text-center">
                    <p class="text-lg font-bold text-gray-900 dark:text-white">Nenhum contrato aguardando validação</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Os contratos aparecem aqui na manhã seguinte ao turno, depois de assinados pelo freelancer.
                    </p>
                </div>
            @else
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 text-sm text-gray-500 dark:text-gray-400">
                    {{ $services->total() }} contrato(s) aguardando · os mais antigos primeiro
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($services as $service)
                        <a href="{{ route('freelancer-validation.show', $service) }}"
                           class="flex items-center justify-between gap-6 px-6 py-5 hover:bg-gray-50 dark:hover:bg-gray-700/40 transition">
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-gray-400 dark:text-gray-500 font-mono">
                                    {{ $service->isAmendment() ? 'Termo' : 'Contrato' }} #{{ $service->id }}
                                </p>
                                <p class="font-extrabold text-gray-900 dark:text-white">
                                    {{ $service->freelancer->name ?? '—' }}
                                    @if($service->kindLabel())
                                        <span class="ml-1 px-2 py-0.5 rounded-full text-[11px] font-bold align-middle
                                            {{ $service->isCommissionAmendment() ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' }}">
                                            {{ $service->kindLabel() }}
                                        </span>
                                    @endif
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $service->functionFreelancer->name ?? '—' }} · {{ $service->location }}
                                    · {{ $service->start_date?->format('d/m/Y') }}
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                    Assinado pelo freelancer em {{ $service->freelancer_signed_at?->format('d/m/Y H:i') }}
                                </p>
                            </div>
                            <div class="flex items-center gap-6 shrink-0">
                                <div class="text-right">
                                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Valor</p>
                                    <p class="text-lg font-extrabold text-gray-900 dark:text-white tabular-nums">
                                        R$ {{ number_format((float) $service->price, 2, ',', '.') }}
                                    </p>
                                </div>
                                <span class="px-4 py-2 rounded-xl text-sm font-bold text-white bg-[#A00001]">Abrir e validar</span>
                            </div>
                        </a>
                    @endforeach
                </div>
                <div class="px-6 py-4">
                    {{ $services->links() }}
                </div>
            @endif
        </div>

    </div>
</div>
</x-app-layout>
