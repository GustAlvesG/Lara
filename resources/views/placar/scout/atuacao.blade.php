@php
    $j = $atuacao['jogo'];
    $p = $atuacao['jogador'];
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Atuação na Partida') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        <div class="flex items-center gap-4">
            <a href="{{ route('placar.scout.sumula', $jogo) }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-100 dark:border-gray-700 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            @if($p['foto_url'])
                <img src="{{ $p['foto_url'] }}" class="w-16 h-16 rounded-full object-cover border border-gray-200 dark:border-gray-600">
            @else
                <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700"></div>
            @endif
            <div>
                <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
                    @if($p['numero'])<span class="text-gray-400 dark:text-gray-500 font-mono">#{{ $p['numero'] }}</span>@endif
                    {{ $p['nome_exibicao'] }}
                </h1>
                <p class="text-gray-500 dark:text-gray-400 font-medium text-sm">
                    {{ $j['time_casa']['nome_exibicao'] }} {{ $j['placar_casa'] ?? 0 }} x {{ $j['placar_fora'] ?? 0 }} {{ $j['time_fora']['nome_exibicao'] }}
                    · {{ \Illuminate\Support\Carbon::parse($j['data_hora'])->format('d/m/Y H:i') }}
                    @if($j['competicao']) · {{ $j['competicao']['nome'] }} @endif
                </p>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            @foreach(['pontos' => 'Pontos', 'faltas' => 'Faltas', 'lances' => 'Lances'] as $campo => $label)
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6 text-center">
                <p class="text-3xl font-extrabold text-gray-900 dark:text-white">{{ $atuacao['totais'][$campo] }}</p>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mt-1">{{ $label }}</p>
            </div>
            @endforeach
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="p-6 border-b border-gray-50 dark:border-gray-700">
                <h2 class="text-lg font-bold text-gray-800 dark:text-white">Lances minutados</h2>
                <p class="text-xs text-gray-400 mt-1">Minuto do cronômetro da partida, não do relógio.</p>
            </div>

            @if(empty($atuacao['lances']))
                <div class="p-12 text-center">
                    <p class="text-gray-500 dark:text-gray-400">Este jogador não teve nenhum lance registrado nesta partida.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-6 py-3">Minuto</th>
                                <th class="px-6 py-3">Período</th>
                                <th class="px-6 py-3">Lance</th>
                                <th class="px-6 py-3 text-right">Valor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($atuacao['lances'] as $lance)
                            <tr class="@if($lance['estornado']) opacity-40 @endif">
                                <td class="px-6 py-3 font-mono font-bold text-gray-900 dark:text-white">{{ $lance['minuto'] ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ $lance['periodo'] ? $lance['periodo'] . 'º' : '—' }}</td>
                                <td class="px-6 py-3">
                                    <span class="text-xs font-bold uppercase text-gray-600 dark:text-gray-300">{{ $lance['tipo'] }}</span>
                                    @if($lance['estornado'])
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">estornado</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right text-gray-700 dark:text-gray-300">{{ $lance['valor'] ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="text-center">
            <a href="{{ route('placar.scout.jogador', $jogador) }}" class="text-sm font-bold text-emerald-600 dark:text-emerald-400 hover:underline">
                Ver todas as partidas deste jogador →
            </a>
        </div>
    </div>
</div>
</x-app-layout>
