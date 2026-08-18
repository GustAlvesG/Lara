@php
    $j = $sumula['jogo'];
    $recorte = $sumula['recorte'];
    $periodoAtivo = $sumula['periodo'];
    // Cada esporte chama as coisas do seu jeito (gol/cesta/ponto,
    // período/quarter/set) — ver Vocabulario.
    $vocab = $sumula['vocabulario'];
    $nomeDoPeriodo = ucfirst($vocab['periodo']);

    // Os filtros se combinam, e a impressão sai exatamente do que está na
    // tela: os dois links carregam o recorte de time E o de período.
    $filtros = array_filter([
        'time_id' => $recorte['time_id'] ?? null,
        'periodo' => $periodoAtivo,
    ]);
    $paramsImpressao = [$jogo, ...$filtros];

    $nomeDoTime = [
        $j['time_casa']['id'] => $j['time_casa']['nome_exibicao'],
        $j['time_fora']['id'] => $j['time_fora']['nome_exibicao'],
    ];

    $comTime = fn ($timeId) => [$jogo, ...array_filter(['time_id' => $timeId, 'periodo' => $periodoAtivo])];
    $comPeriodo = fn ($periodo) => [$jogo, ...array_filter(['time_id' => $recorte['time_id'] ?? null, 'periodo' => $periodo])];
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
                    <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
                        Súmula
                        @if($recorte)
                            <span class="text-base font-bold text-indigo-600 dark:text-indigo-400">· {{ $recorte['nome_exibicao'] }}</span>
                        @endif
                        @if($periodoAtivo)
                            <span class="text-base font-bold text-amber-600 dark:text-amber-400">· {{ $nomeDoPeriodo }} {{ $periodoAtivo }}</span>
                        @endif
                    </h1>
                    <p class="text-gray-500 dark:text-gray-400 font-medium text-sm">
                        {{ $j['esporte'] }} · {{ \Illuminate\Support\Carbon::parse($j['data_hora'])->format('d/m/Y H:i') }}
                        @if($j['competicao']) · {{ $j['competicao']['nome'] }} @endif
                        @if($j['local']) · {{ $j['local'] }} @endif
                    </p>
                </div>
            </div>

            {{-- Recorte: a súmula sai completa ou de um time só — cada
                 equipe costuma querer só a sua para arquivar. --}}
            <div class="flex flex-col items-stretch sm:items-end gap-2">
                <div class="inline-flex rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 text-xs font-bold">
                    @php
                        $opcoes = [
                            ['rotulo' => 'Completa', 'params' => $comTime(null), 'ativo' => !$recorte],
                            ['rotulo' => $j['time_casa']['nome_exibicao'], 'params' => $comTime($j['time_casa']['id']), 'ativo' => $recorte && $recorte['lado'] === 'casa'],
                            ['rotulo' => $j['time_fora']['nome_exibicao'], 'params' => $comTime($j['time_fora']['id']), 'ativo' => $recorte && $recorte['lado'] === 'fora'],
                        ];
                    @endphp
                    @foreach($opcoes as $opcao)
                        <a href="{{ route('placar.scout.sumula', $opcao['params']) }}"
                           class="px-3 py-2 transition {{ $opcao['ativo'] ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                            {{ $opcao['rotulo'] }}
                        </a>
                    @endforeach
                </div>

                {{-- Filtro por parcial: os lances e os totais passam a ser
                     só daquele set/quarter/período. --}}
                @if(count($sumula['periodos_disponiveis']) > 1)
                <div class="inline-flex rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 text-xs font-bold">
                    <a href="{{ route('placar.scout.sumula', $comPeriodo(null)) }}"
                       class="px-3 py-2 transition {{ !$periodoAtivo ? 'bg-amber-500 text-white' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                        Jogo todo
                    </a>
                    @foreach($sumula['periodos_disponiveis'] as $numero)
                        <a href="{{ route('placar.scout.sumula', $comPeriodo($numero)) }}"
                           class="px-3 py-2 transition {{ $periodoAtivo === $numero ? 'bg-amber-500 text-white' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                            {{ $nomeDoPeriodo }} {{ $numero }}
                        </a>
                    @endforeach
                </div>
                @endif

                <a href="{{ route('placar.scout.sumula.print', $paramsImpressao) }}" target="_blank" class="inline-flex items-center justify-center px-4 py-2 bg-gray-800 text-white rounded-xl font-bold shadow hover:bg-gray-900 transition text-sm">
                    Imprimir / PDF
                </a>
                <p class="text-xs text-gray-400 dark:text-gray-500">Imprime o recorte selecionado.</p>
            </div>
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
            {{-- As parciais são do jogo inteiro mesmo quando há filtro: são
                 a referência de onde a parcial escolhida se encaixa. Clicar
                 filtra por ela. --}}
            <div class="mt-6 flex flex-wrap justify-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                @foreach($sumula['placar_por_periodo'] as $parcial)
                    <a href="{{ route('placar.scout.sumula', $comPeriodo($parcial['periodo'])) }}"
                       class="px-3 py-1 rounded-full transition {{ $periodoAtivo === $parcial['periodo'] ? 'bg-amber-500 text-white font-bold' : 'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                        {{ $nomeDoPeriodo }} {{ $parcial['periodo'] }}: {{ $parcial['placar_casa'] }}-{{ $parcial['placar_fora'] }}
                    </a>
                @endforeach
            </div>
            @endif
        </div>

        @if(empty($sumula['eventos']))
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-12 text-center">
                <p class="text-gray-500 dark:text-gray-400">
                    @if($periodoAtivo && $recorte)
                        Nada registrado para {{ $recorte['nome_exibicao'] }} no {{ $nomeDoPeriodo }} {{ $periodoAtivo }}.
                    @elseif($periodoAtivo)
                        Nada registrado no {{ $nomeDoPeriodo }} {{ $periodoAtivo }}.
                    @elseif($recorte)
                        Nenhum evento registrado para {{ $recorte['nome_exibicao'] }} neste jogo.
                    @else
                        Nenhum evento registrado para este jogo ainda.
                    @endif
                </p>
            </div>
        @else
        <div class="grid grid-cols-1 {{ count($lados) > 1 ? 'md:grid-cols-2' : '' }} gap-6">
            @foreach($lados as $lado => $nome)
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50 flex items-baseline justify-between gap-2">
                    <h2 class="text-sm font-bold text-gray-800 dark:text-white">{{ $nome }}</h2>
                    <span class="text-xs text-gray-400">
                        {{ $periodoAtivo ? $nomeDoPeriodo . ' ' . $periodoAtivo : 'jogo todo' }}
                    </span>
                </div>
                @if(empty($sumula['totais_por_jogador'][$lado]))
                    <div class="p-4 text-xs text-gray-400">Sem {{ $vocab['pontos'] }} {{ $periodoAtivo ? 'neste ' . $vocab['periodo'] : 'nesta partida' }}.</div>
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
                                <td class="px-4 py-2 text-right font-bold text-gray-900 dark:text-white">{{ $totais['pontos'] }} {{ $vocab['pontos'] }}</td>
                                @if($vocab['tem_falta'])
                                    {{-- Vôlei não tem falta: a coluna sairia sempre zerada. --}}
                                    <td class="px-4 py-2 text-right text-gray-400">{{ $totais['faltas'] }} faltas</td>
                                @endif
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
                    {{-- O nome que o esporte dá ao lance (gol, cesta de 3,
                         ponto), resolvido no servidor. --}}
                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400 w-28 shrink-0">{{ $evento['rotulo'] }}</span>
                    <span class="flex-1 text-gray-800 dark:text-gray-200">
                        @if(isset($evento['substituicao']))
                            {{-- Uma substituição são duas pessoas: dizer só
                                 quem entrou deixa a linha pela metade. --}}
                            @php $troca = $evento['substituicao']; @endphp
                            <span class="text-red-600 dark:text-red-400">↓ {{ $troca['sai']['nome_exibicao'] ?? '—' }}</span>
                            <span class="text-emerald-600 dark:text-emerald-400">↑ {{ $troca['entra']['nome_exibicao'] ?? '—' }}</span>
                        @elseif($evento['jogador'])
                            @if($evento['jogador']['numero']) <span class="text-gray-400 font-mono">#{{ $evento['jogador']['numero'] }}</span> @endif
                            {{ $evento['jogador']['nome_exibicao'] }}
                        @endif
                        {{-- Sem repetir o valor: o rótulo já diz "cesta de 3". --}}
                        @if($evento['estornado']) <span class="text-red-500 font-bold">estornado</span> @endif
                    </span>
                    {{-- De quem foi o lance: na súmula completa a linha do
                         tempo mistura os dois times. --}}
                    <span class="text-xs text-gray-400 truncate max-w-[10rem] hidden sm:inline">{{ $nomeDoTime[$evento['time_id']] ?? '' }}</span>
                    <span class="text-xs text-gray-400 w-8 text-right shrink-0">{{ $evento['periodo'] ? mb_strtoupper(mb_substr($vocab['periodo'], 0, 1)) . $evento['periodo'] : '' }}</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</div>
</x-app-layout>
