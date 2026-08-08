<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Painel do Time') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-8">

        <div class="flex items-center gap-4">
            @if($painel['time']['logo_url'])
                <img src="{{ $painel['time']['logo_url'] }}" class="w-16 h-16 rounded-full object-cover border border-gray-200 dark:border-gray-600">
            @else
                <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700"></div>
            @endif
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">{{ $painel['time']['nome_exibicao'] }}</h1>
                <a href="{{ route('placar.times.show', $time) }}" class="text-sm font-bold text-emerald-600 dark:text-emerald-400 hover:underline">Ver cadastro →</a>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6 text-center">
                <p class="text-3xl font-extrabold text-green-600">{{ $painel['retrospecto']['vitorias'] }}</p>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mt-1">Vitórias</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6 text-center">
                <p class="text-3xl font-extrabold text-gray-500">{{ $painel['retrospecto']['empates'] }}</p>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mt-1">Empates</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6 text-center">
                <p class="text-3xl font-extrabold text-red-600">{{ $painel['retrospecto']['derrotas'] }}</p>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mt-1">Derrotas</p>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="p-6 border-b border-gray-50 dark:border-gray-700">
                <h2 class="text-lg font-bold text-gray-800 dark:text-white">Artilheiros do time</h2>
            </div>
            @if(empty($painel['artilheiros']))
                <div class="p-6 text-sm text-gray-500 dark:text-gray-400">Nenhum ponto registrado ainda.</div>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($painel['artilheiros'] as $linha)
                    <li class="p-4 flex items-center justify-between text-sm">
                        <a href="{{ route('placar.scout.jogador', $linha['jogador_id']) }}" class="text-gray-800 dark:text-gray-200 hover:underline">
                            @if($linha['numero']) <span class="text-gray-400 font-mono">#{{ $linha['numero'] }}</span> @endif
                            {{ $linha['nome_exibicao'] }}
                        </a>
                        <span class="font-bold text-gray-900 dark:text-white">{{ $linha['pontos'] }} pts · {{ $linha['jogos'] }} jogos</span>
                    </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="p-6 border-b border-gray-50 dark:border-gray-700">
                <h2 class="text-lg font-bold text-gray-800 dark:text-white">Jogos</h2>
            </div>
            @if(empty($painel['jogos']))
                <div class="p-6 text-sm text-gray-500 dark:text-gray-400">Nenhum jogo registrado.</div>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($painel['jogos'] as $jogo)
                    <li class="p-4 flex items-center justify-between text-sm">
                        <a href="{{ route('placar.jogos.show', $jogo['jogo_id']) }}" class="text-gray-800 dark:text-gray-200 hover:underline">
                            {{ $jogo['mandante'] ? 'vs' : '@' }} {{ $jogo['adversario']['nome_exibicao'] }}
                        </a>
                        <span class="flex items-center gap-2">
                            @if($jogo['resultado'])
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold
                                    @class([
                                        'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' => $jogo['resultado'] === 'V',
                                        'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' => $jogo['resultado'] === 'D',
                                        'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' => $jogo['resultado'] === 'E',
                                    ])">{{ $jogo['resultado'] }}</span>
                                <span class="text-xs text-gray-400">{{ $jogo['placar_time'] }}-{{ $jogo['placar_adversario'] }}</span>
                            @else
                                <span class="text-xs text-gray-400">{{ $jogo['status'] }}</span>
                            @endif
                        </span>
                    </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
</x-app-layout>
