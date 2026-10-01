<x-app-layout>
<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8">
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">Validação da coordenação</h1>
            <p class="text-ink-2 font-medium">
                Contratos da redação 2 já assinados pelo freelancer. Abra cada um, leia até o fim e valide com o seu PIN —
                validado, o contrato fica disponível para a montagem de lote.
            </p>
        </div>

        @include('freelancer.services.partials.tabs')
        @include('partials.alerts')

        <x-search-bar placeholder="Freelancer, CPF ou evento/local" class="mb-6" />

        @if($awaitingRelease > 0)
            <div class="mb-6 p-4 bg-subtle border border-line text-ink-2 rounded-lg text-sm">
                🕗 {{ $awaitingRelease }} contrato(s) assinado(s) ainda esperam o fim do dia do turno. Entram nesta fila às
                {{ str_pad((string) \App\Models\FreelancerService::RELEASE_HOUR, 2, '0', STR_PAD_LEFT) }}h da manhã seguinte,
                quando não cabe mais aditivo.
            </div>
        @endif

        {{-- Sem caixas de seleção nem ação em massa, de propósito: a validação é
             de cada documento, lido até o fim. A linha só abre o contrato. --}}
        <div class="bg-surface rounded-2xl shadow-card border border-line overflow-hidden">
            @if($services->isEmpty())
                <div class="px-6 py-16 text-center">
                    @if(filled(request('q')))
                        <p class="text-lg font-bold text-ink">Nenhum contrato aguardando validação com essa busca</p>
                    @else
                    <p class="text-lg font-bold text-ink">Nenhum contrato aguardando validação</p>
                    <p class="text-sm text-ink-2 mt-1">
                        Os contratos aparecem aqui na manhã seguinte ao turno, depois de assinados pelo freelancer.
                    </p>
                    @endif
                </div>
            @else
                <div class="px-6 py-4 border-b border-line text-sm text-ink-2">
                    {{ $services->total() }} contrato(s) aguardando · os mais antigos primeiro
                </div>
                <div class="divide-y divide-line">
                    @foreach($services as $service)
                        <a href="{{ route('freelancer-validation.show', $service) }}"
                           class="flex items-center justify-between gap-6 px-6 py-5 hover:bg-subtle transition">
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-ink-3 font-mono">
                                    {{ $service->isAmendment() ? 'Termo' : 'Contrato' }} #{{ $service->id }}
                                </p>
                                <p class="font-extrabold text-ink">
                                    {{ $service->freelancer->name ?? '—' }}
                                    @if($service->kindLabel())
                                        <span class="ml-1 px-2 py-0.5 rounded-full text-[11px] font-bold align-middle
                                            {{ $service->isCommissionAmendment() ? 'bg-ok-soft text-ok' : 'bg-grena-tint text-grena-ink' }}">
                                            {{ $service->kindLabel() }}
                                        </span>
                                    @endif
                                </p>
                                <p class="text-sm text-ink-2">
                                    {{ $service->functionFreelancer->name ?? '—' }} · {{ $service->location }}
                                    · {{ $service->start_date?->format('d/m/Y') }}
                                </p>
                                <p class="text-xs text-ink-3 mt-0.5">
                                    Assinado pelo freelancer em {{ $service->freelancer_signed_at?->format('d/m/Y H:i') }}
                                </p>
                            </div>
                            <div class="flex items-center gap-6 shrink-0">
                                <div class="text-right">
                                    <p class="text-xs font-bold uppercase tracking-wider text-ink-3">Valor</p>
                                    <p class="text-lg font-extrabold text-ink tabular-nums">
                                        R$ {{ number_format((float) $service->price, 2, ',', '.') }}
                                    </p>
                                </div>
                                <span class="px-4 py-2 rounded-xl text-sm font-bold text-white bg-grena">Abrir e validar</span>
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
