<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Placar Clube — Artilharia') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Artilharia</h1>
            <p class="text-gray-500 dark:text-gray-400 font-medium">Ranking de pontos por jogador, calculado a partir do log de eventos.</p>
        </div>

        @include('partials.alerts')

        <form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
            <select name="modalidade" class="px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                <option value="">Todas as modalidades</option>
                @foreach($modalidades as $modalidade)
                    <option value="{{ $modalidade->slug }}" @selected($filtros['modalidade'] === $modalidade->slug)>{{ $modalidade->nome }}</option>
                @endforeach
            </select>
            <select name="competicao_id" class="px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                <option value="">Todas as competições</option>
                @foreach($competicoes as $competicao)
                    <option value="{{ $competicao->id }}" @selected((string) $filtros['competicao_id'] === (string) $competicao->id)>{{ $competicao->nome }}</option>
                @endforeach
            </select>
            <select name="time_id" class="px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                <option value="">Todos os times</option>
                @foreach($times as $time)
                    <option value="{{ $time->id }}" @selected((string) $filtros['time_id'] === (string) $time->id)>{{ $time->nomeExibicaoResolvido() }}</option>
                @endforeach
            </select>
            <input type="number" name="temporada" value="{{ $filtros['temporada'] }}" placeholder="Temporada"
                class="w-32 px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
            <input type="hidden" name="ordenar" value="{{ $ordenar }}">
            <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-bold hover:bg-gray-900 transition">Filtrar</button>
        </form>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            @if(empty($artilharia))
                <div class="p-12 text-center">
                    <p class="text-gray-500 dark:text-gray-400">Nenhum ponto registrado com estes filtros.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-6 py-3">#</th>
                                <th class="px-6 py-3"></th>
                                <th class="px-6 py-3">Nº</th>
                                <th class="px-6 py-3">Jogador</th>
                                @foreach(['pontos' => 'Pontos', 'jogos' => 'Jogos', 'media' => 'Média'] as $campo => $label)
                                <th class="px-6 py-3">
                                    <a href="{{ request()->fullUrlWithQuery(['ordenar' => $campo]) }}" class="hover:underline @if($ordenar === $campo) text-indigo-600 dark:text-indigo-400 @endif">
                                        {{ $label }} @if($ordenar === $campo) ▼ @endif
                                    </a>
                                </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($artilharia as $i => $linha)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <td class="px-6 py-4 text-gray-400">{{ $i + 1 }}</td>
                                <td class="px-6 py-4">
                                    @if($linha['foto_url'])
                                        <img src="{{ $linha['foto_url'] }}" class="w-8 h-8 rounded-full object-cover">
                                    @else
                                        <div class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700"></div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-gray-400 dark:text-gray-500 font-mono">{{ $linha['numero'] ?? '—' }}</td>
                                <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">
                                    <a href="{{ route('placar.scout.jogador', $linha['jogador_id']) }}" class="hover:underline">{{ $linha['nome_exibicao'] ?? '—' }}</a>
                                </td>
                                <td class="px-6 py-4 font-bold text-gray-900 dark:text-white">{{ $linha['pontos'] }}</td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $linha['jogos'] }}</td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $linha['media'] }}</td>
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
