@php
    $j = $sumula['jogo'];
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Súmula') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('placar.jogos.show', $jogo) }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-100 dark:border-gray-700 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">Súmula</h1>
                    <p class="text-gray-500 dark:text-gray-400 font-medium text-sm">
                        {{ $j['esporte'] }} · {{ \Illuminate\Support\Carbon::parse($j['data_hora'])->format('d/m/Y H:i') }}
                        @if($j['competicao']) · {{ $j['competicao']['nome'] }} @endif
                        @if($j['local']) · {{ $j['local'] }} @endif
                    </p>
                </div>
            </div>
            <a href="{{ route('placar.scout.sumula.print', $jogo) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-gray-800 text-white rounded-xl font-bold shadow hover:bg-gray-900 transition text-sm">
                Imprimir / PDF
            </a>
        </div>

        {{-- Placar --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-8">
            @if($j['status'] === 'ao_vivo')
                <div class="mb-4 flex items-center justify-center gap-2 text-red-600 dark:text-red-400 font-bold text-sm">
                    <span class="w-2 h-2 rounded-full bg-red-600 animate-pulse"></span> AO VIVO — placar parcial
                </div>
            @elseif($j['status'] === 'agendado')
                <div class="mb-4 text-center text-gray-400 text-sm font-bold uppercase">Agendado — ainda não começou</div>
            @endif

            <div class="flex items-center justify-between">
                <div class="flex-1 text-center">
                    @if($j['time_casa']['logo_url'])<img src="{{ $j['time_casa']['logo_url'] }}" class="w-16 h-16 mx-auto rounded-full object-cover mb-2">@endif
                    <p class="font-bold text-gray-800 dark:text-white">{{ $j['time_casa']['nome_exibicao'] }}</p>
                </div>
                <div class="px-8 text-4xl font-extrabold text-gray-900 dark:text-white">
                    {{ $j['placar_casa'] ?? 0 }} <span class="text-gray-300 dark:text-gray-600">x</span> {{ $j['placar_fora'] ?? 0 }}
                </div>
                <div class="flex-1 text-center">
                    @if($j['time_fora']['logo_url'])<img src="{{ $j['time_fora']['logo_url'] }}" class="w-16 h-16 mx-auto rounded-full object-cover mb-2">@endif
                    <p class="font-bold text-gray-800 dark:text-white">{{ $j['time_fora']['nome_exibicao'] }}</p>
                </div>
            </div>

            @if(!empty($sumula['placar_por_periodo']))
            <div class="mt-6 flex justify-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                @foreach($sumula['placar_por_periodo'] as $periodo)
                    <span class="px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-700">{{ $periodo['periodo'] }}º: {{ $periodo['placar_casa'] }}-{{ $periodo['placar_fora'] }}</span>
                @endforeach
            </div>
            @endif
        </div>

        @if(empty($sumula['eventos']))
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-12 text-center">
                <p class="text-gray-500 dark:text-gray-400">Nenhum evento registrado para este jogo ainda.</p>
            </div>
        @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach(['time_casa' => $j['time_casa']['nome_exibicao'], 'time_fora' => $j['time_fora']['nome_exibicao']] as $lado => $nome)
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
                    <h2 class="text-sm font-bold text-gray-800 dark:text-white">{{ $nome }}</h2>
                </div>
                @if(empty($sumula['totais_por_jogador'][$lado]))
                    <div class="p-4 text-xs text-gray-400">Sem pontuação registrada.</div>
                @else
                    <table class="w-full text-xs">
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($sumula['totais_por_jogador'][$lado] as $totais)
                            <tr>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                    <a href="{{ route('placar.scout.atuacao', [$jogo, $totais['jogador_id']]) }}" class="hover:underline">
                                        @if($totais['numero']) <span class="text-gray-400 font-mono">#{{ $totais['numero'] }}</span> @endif
                                        {{ $totais['nome_exibicao'] }}
                                    </a>
                                </td>
                                <td class="px-4 py-2 text-right font-bold text-gray-900 dark:text-white">{{ $totais['pontos'] }} pts</td>
                                <td class="px-4 py-2 text-right text-gray-400">{{ $totais['faltas'] }} faltas</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            @endforeach
        </div>

        {{-- Timeline --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="p-4 border-b border-gray-50 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-800 dark:text-white">Linha do tempo</h2>
            </div>
            <ul class="divide-y divide-gray-100 dark:divide-gray-700 max-h-[32rem] overflow-y-auto">
                @foreach($sumula['eventos'] as $evento)
                <li class="p-3 flex items-center gap-3 text-sm @if($evento['estornado']) opacity-40 @endif">
                    <span class="text-xs font-mono font-bold text-gray-500 dark:text-gray-400 w-12 shrink-0">{{ $evento['minuto'] ?? '—' }}</span>
                    <span class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400 w-24 shrink-0">{{ $evento['tipo'] }}</span>
                    <span class="flex-1 text-gray-800 dark:text-gray-200">
                        @if($evento['jogador'])
                            @if($evento['jogador']['numero']) <span class="text-gray-400 font-mono">#{{ $evento['jogador']['numero'] }}</span> @endif
                            {{ $evento['jogador']['nome_exibicao'] }}
                        @endif
                        @if($evento['valor']) ({{ $evento['valor'] }}) @endif
                        @if($evento['estornado']) <span class="text-red-500 font-bold">estornado</span> @endif
                    </span>
                    <span class="text-xs text-gray-400">{{ $evento['periodo'] ? "P{$evento['periodo']}" : '' }}</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</div>
</x-app-layout>
