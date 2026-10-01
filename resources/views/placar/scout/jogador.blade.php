<x-app-layout>
<div class="py-6">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-8">

        <div class="flex items-center gap-4">
            @if($perfil['jogador']['foto_url'])
                <img src="{{ $perfil['jogador']['foto_url'] }}" class="w-16 h-16 rounded-full object-cover border border-line">
            @else
                <div class="w-16 h-16 rounded-full bg-subtle"></div>
            @endif
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">{{ $perfil['jogador']['nome_exibicao'] }}</h1>
                <a href="{{ route('placar.jogadores.show', $jogador) }}" class="text-sm font-bold text-grena-ink hover:underline">Ver cadastro →</a>
            </div>
        </div>

        <form method="GET" class="flex items-center gap-3">
            <input type="number" name="temporada" value="{{ $temporada }}" placeholder="Temporada (todas se vazio)"
                class="w-56 px-4 py-2 border border-line rounded-lg bg-surface text-ink">
            <button type="submit" class="px-4 py-2 bg-ink text-canvas rounded-lg text-sm font-bold hover:bg-ink-2 transition">Filtrar</button>
        </form>

        <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
            <div class="p-6 border-b border-line">
                <h2 class="text-lg font-bold text-ink">Partidas em que atuou</h2>
                <p class="text-xs text-ink-3 mt-1">Clique numa partida para ver a ficha com os lances minutados.</p>
            </div>

            @if(empty($perfil['partidas']))
                <div class="p-12 text-center">
                    <p class="text-ink-2">Nenhuma partida com lance registrado para este jogador.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-ink-3 uppercase tracking-wider bg-subtle">
                            <tr>
                                <th class="px-6 py-3">Data</th>
                                <th class="px-6 py-3">Confronto</th>
                                <th class="px-6 py-3">Placar</th>
                                <th class="px-6 py-3 text-right">Pontos</th>
                                <th class="px-6 py-3 text-right">Faltas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($perfil['partidas'] as $partida)
                            <tr class="hover:bg-subtle transition">
                                <td class="px-6 py-4 text-ink-2 whitespace-nowrap">
                                    {{ $partida['data_hora'] ? \Illuminate\Support\Carbon::parse($partida['data_hora'])->format('d/m/Y') : '—' }}
                                </td>
                                <td class="px-6 py-4 font-semibold text-ink">
                                    <a href="{{ route('placar.scout.atuacao', [$partida['jogo_id'], $jogador]) }}" class="hover:underline">
                                        {{ $partida['confronto'] }}
                                    </a>
                                    @if($partida['competicao'])
                                        <span class="block text-xs font-normal text-ink-3">{{ $partida['competicao'] }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-ink whitespace-nowrap">{{ $partida['placar'] }}</td>
                                <td class="px-6 py-4 text-right font-bold text-ink">{{ $partida['pontos'] }}</td>
                                <td class="px-6 py-4 text-right text-ink-2">{{ $partida['faltas'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
</x-app-layout>
