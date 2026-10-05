<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Súmulas">
            Escolha uma partida para ver a súmula e a atuação minutada de cada jogador.
        </x-page-title>

        @include('partials.alerts')

        <x-search-bar name="busca" placeholder="Buscar time, equipe, competição ou local" :filters="['modalidade', 'status']">
            <x-slot:controls>
                <select name="modalidade" class="h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint w-full sm:w-auto">
                    <option value="">Todas as modalidades</option>
                    @foreach($modalidades as $modalidade)
                        <option value="{{ $modalidade->slug }}" @selected(request('modalidade') === $modalidade->slug)>{{ $modalidade->nome }}</option>
                    @endforeach
                </select>
                <select name="status" class="h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint w-full sm:w-auto">
                    <option value="">Todos os status</option>
                    @foreach(\App\Models\Placar\Jogo::STATUSES as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', $status) }}</option>
                    @endforeach
                </select>
            </x-slot:controls>
        </x-search-bar>

        <div class="overflow-hidden rounded-card bg-surface shadow-card">
            @if($jogos->isEmpty())
                <div class="p-12 text-center">
                    <p class="text-ink-2">Nenhuma partida encontrada com estes filtros.</p>
                </div>
            @else
                <ul class="divide-y divide-line">
                    @foreach($jogos as $jogo)
                    <li class="p-4 flex items-center justify-between gap-4 hover:bg-subtle transition">
                        <div class="min-w-0">
                            <a href="{{ route('placar.scout.sumula', $jogo) }}" class="font-semibold text-ink hover:underline">
                                {{ $jogo->timeCasa->nomeExibicaoResolvido() }} <span class="text-ink-3">x</span> {{ $jogo->timeFora->nomeExibicaoResolvido() }}
                            </a>
                            <p class="text-xs text-ink-3 mt-0.5">
                                {{ $jogo->data_hora?->format('d/m/Y H:i') }} · {{ $jogo->modalidade->nome }}
                                @if($jogo->competicao) · {{ $jogo->competicao->nome }} @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <span class="font-extrabold text-ink">{{ $jogo->placar_casa ?? 0 }} x {{ $jogo->placar_fora ?? 0 }}</span>
                            @if($jogo->status === \App\Models\Placar\Jogo::STATUS_AO_VIVO)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-danger-soft text-grena-ink">
                                    <span class="w-1.5 h-1.5 rounded-full bg-danger animate-pulse"></span> ao vivo
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-subtle text-ink-2">{{ str_replace('_', ' ', $jogo->status) }}</span>
                            @endif
                        </div>
                    </li>
                    @endforeach
                </ul>
                <div class="p-4">{{ $jogos->links() }}</div>
            @endif
        </div>
    </x-page>
</x-app-layout>
