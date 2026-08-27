<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Partidas do Jogador') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-8">

        <div class="flex items-center gap-4">
            @if($perfil['jogador']['foto_url'])
                <img src="{{ $perfil['jogador']['foto_url'] }}" class="w-16 h-16 rounded-full object-cover border border-gray-200 dark:border-gray-600">
            @else
                <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700"></div>
            @endif
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">{{ $perfil['jogador']['nome_exibicao'] }}</h1>
                <a href="{{ route('placar.jogadores.show', $jogador) }}" class="text-sm font-bold text-emerald-600 dark:text-emerald-400 hover:underline">Ver cadastro →</a>
            </div>
        </div>

        <form method="GET" class="flex items-center gap-3">
            <input type="number" name="temporada" value="{{ $temporada }}" placeholder="Temporada (todas se vazio)"
                class="w-56 px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
            <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-bold hover:bg-gray-900 transition">Filtrar</button>
        </form>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="p-6 border-b border-gray-50 dark:border-gray-700">
                <h2 class="text-lg font-bold text-gray-800 dark:text-white">Partidas em que atuou</h2>
                <p class="text-xs text-gray-400 mt-1">Clique numa partida para ver a ficha com os lances minutados.</p>
            </div>

            @if(empty($perfil['partidas']))
                <div class="p-12 text-center">
                    <p class="text-gray-500 dark:text-gray-400">Nenhuma partida com lance registrado para este jogador.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-6 py-3">Data</th>
                                <th class="px-6 py-3">Confronto</th>
                                <th class="px-6 py-3">Placar</th>
                                <th class="px-6 py-3 text-right">Pontos</th>
                                <th class="px-6 py-3 text-right">Faltas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($perfil['partidas'] as $partida)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <td class="px-6 py-4 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ $partida['data_hora'] ? \Illuminate\Support\Carbon::parse($partida['data_hora'])->format('d/m/Y') : '—' }}
                                </td>
                                <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">
                                    <a href="{{ route('placar.scout.atuacao', [$partida['jogo_id'], $jogador]) }}" class="hover:underline">
                                        {{ $partida['confronto'] }}
                                    </a>
                                    @if($partida['competicao'])
                                        <span class="block text-xs font-normal text-gray-400">{{ $partida['competicao'] }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $partida['placar'] }}</td>
                                <td class="px-6 py-4 text-right font-bold text-gray-900 dark:text-white">{{ $partida['pontos'] }}</td>
                                <td class="px-6 py-4 text-right text-gray-500 dark:text-gray-400">{{ $partida['faltas'] }}</td>
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
