<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Perfil do Jogador') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-8">

        <div class="flex items-center gap-4">
            @if($perfil['foto_url'])
                <img src="{{ $perfil['foto_url'] }}" class="w-16 h-16 rounded-full object-cover border border-gray-200 dark:border-gray-600">
            @else
                <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700"></div>
            @endif
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">{{ $perfil['nome_exibicao'] }}</h1>
                <a href="{{ route('placar.jogadores.show', $jogador) }}" class="text-sm font-bold text-emerald-600 dark:text-emerald-400 hover:underline">Ver cadastro →</a>
            </div>
        </div>

        <form method="GET" class="flex items-center gap-3">
            <input type="number" name="temporada" value="{{ $temporada }}" placeholder="Temporada (todas se vazio)"
                class="w-56 px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
            <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-bold hover:bg-gray-900 transition">Filtrar</button>
        </form>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach(['jogos' => 'Jogos', 'pontos' => 'Pontos', 'media' => 'Média/jogo', 'faltas' => 'Faltas'] as $campo => $label)
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6 text-center">
                <p class="text-3xl font-extrabold text-gray-900 dark:text-white">{{ $perfil[$campo] }}</p>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mt-1">{{ $label }}</p>
            </div>
            @endforeach
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="p-6 border-b border-gray-50 dark:border-gray-700">
                <h2 class="text-lg font-bold text-gray-800 dark:text-white">Pontos por período</h2>
            </div>
            @if(empty($perfil['distribuicao_por_periodo']))
                <div class="p-6 text-sm text-gray-500 dark:text-gray-400">Nenhum ponto registrado.</div>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($perfil['distribuicao_por_periodo'] as $item)
                    <li class="p-4 flex items-center justify-between text-sm">
                        <span class="text-gray-700 dark:text-gray-300">{{ $item['periodo'] }}º período</span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ $item['pontos'] }} pts</span>
                    </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
</x-app-layout>
