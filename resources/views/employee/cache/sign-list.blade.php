<x-cache-sign-layout title="Meus Cachês" heading="Meus cachês" :employee="$employee">

    <section>
        <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3">Aguardando a sua assinatura</h2>

        @if($pending->isEmpty())
            <div class="bg-white rounded-2xl shadow p-6 text-center text-gray-500">
                Nenhum cachê aguardando assinatura.
            </div>
        @else
            <div class="space-y-3">
                @foreach($pending as $cache)
                    <a href="{{ route('employee-caches.sign.show', $cache) }}"
                       class="block bg-white rounded-2xl shadow-lg p-5 hover:shadow-xl transition border-l-4 border-[#A00001]">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-extrabold text-lg leading-tight">{{ $cache->location }}</p>
                                <p class="text-sm text-gray-500">
                                    {{ $cache->functionFreelancer->name }} · {{ $cache->event_date->format('d/m/Y') }}
                                </p>
                                <p class="text-sm text-gray-500 mt-1">
                                    Previsto: <strong>{{ $cache->formattedExpectedPeriod() }}</strong>
                                </p>
                            </div>
                            <span class="shrink-0 px-4 py-2 bg-[#A00001] text-white rounded-xl text-sm font-bold">Assinar</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    @if($signed->isNotEmpty())
        <section>
            <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3">Já assinados</h2>

            <div class="space-y-3">
                @foreach($signed as $cache)
                    <div class="bg-white rounded-2xl shadow p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-bold leading-tight">{{ $cache->location }}</p>
                                <p class="text-sm text-gray-500">
                                    {{ $cache->functionFreelancer->name }} · {{ $cache->event_date->format('d/m/Y') }}
                                </p>
                                <p class="text-sm text-gray-500 mt-1">
                                    Você informou <strong>{{ $cache->formattedActualPeriod() }}</strong>
                                    · faixa de {{ $cache->hours }}h
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="font-extrabold">R$ {{ number_format($cache->price, 2, ',', '.') }}</p>
                                <p class="text-xs font-bold mt-1
                                    {{ $cache->isPaid() ? 'text-green-700' : ($cache->hasDivergence() && !$cache->isPayable() ? 'text-amber-700' : 'text-gray-500') }}">
                                    {{ $cache->statusLabel() }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</x-cache-sign-layout>
