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
<div class="py-6">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('placar.jogos.show', $jogo) }}" class="p-2 bg-surface rounded-xl shadow-card text-ink-3 hover:text-grena-ink border border-line transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">
                        Súmula
                        @if($recorte)
                            <span class="text-base font-bold text-grena-ink">· {{ $recorte['nome_exibicao'] }}</span>
                        @endif
                        @if($periodoAtivo)
                            <span class="text-base font-bold text-warn">· {{ $nomeDoPeriodo }} {{ $periodoAtivo }}</span>
                        @endif
                    </h1>
                    <p class="text-ink-2 font-medium text-sm">
                        {{ $j['esporte'] }} · {{ \Illuminate\Support\Carbon::parse($j['data_hora'])->format('d/m/Y H:i') }}
                        @if($j['competicao']) · {{ $j['competicao']['nome'] }} @endif
                        @if($j['local']) · {{ $j['local'] }} @endif
                    </p>
                </div>
            </div>

            {{-- Recorte: a súmula sai completa ou de um time só — cada
                 equipe costuma querer só a sua para arquivar. --}}
            <div class="flex flex-col items-stretch sm:items-end gap-2">
                <div class="inline-flex rounded-xl overflow-hidden border border-line text-xs font-bold">
                    @php
                        $opcoes = [
                            ['rotulo' => 'Completa', 'params' => $comTime(null), 'ativo' => !$recorte],
                            ['rotulo' => $j['time_casa']['nome_exibicao'], 'params' => $comTime($j['time_casa']['id']), 'ativo' => $recorte && $recorte['lado'] === 'casa'],
                            ['rotulo' => $j['time_fora']['nome_exibicao'], 'params' => $comTime($j['time_fora']['id']), 'ativo' => $recorte && $recorte['lado'] === 'fora'],
                        ];
                    @endphp
                    @foreach($opcoes as $opcao)
                        <a href="{{ route('placar.scout.sumula', $opcao['params']) }}"
                           class="px-3 py-2 transition {{ $opcao['ativo'] ? 'bg-grena text-white' : 'bg-surface text-ink-2 hover:bg-subtle' }}">
                            {{ $opcao['rotulo'] }}
                        </a>
                    @endforeach
                </div>

                {{-- Filtro por parcial: os lances e os totais passam a ser
                     só daquele set/quarter/período. --}}
                @if(count($sumula['periodos_disponiveis']) > 1)
                <div class="inline-flex rounded-xl overflow-hidden border border-line text-xs font-bold">
                    <a href="{{ route('placar.scout.sumula', $comPeriodo(null)) }}"
                       class="px-3 py-2 transition {{ !$periodoAtivo ? 'bg-warn text-white dark:text-canvas' : 'bg-surface text-ink-2 hover:bg-subtle' }}">
                        Jogo todo
                    </a>
                    @foreach($sumula['periodos_disponiveis'] as $numero)
                        <a href="{{ route('placar.scout.sumula', $comPeriodo($numero)) }}"
                           class="px-3 py-2 transition {{ $periodoAtivo === $numero ? 'bg-warn text-white dark:text-canvas' : 'bg-surface text-ink-2 hover:bg-subtle' }}">
                            {{ $nomeDoPeriodo }} {{ $numero }}
                        </a>
                    @endforeach
                </div>
                @endif

                <a href="{{ route('placar.scout.sumula.print', $paramsImpressao) }}" target="_blank" class="inline-flex items-center justify-center px-4 py-2 bg-ink text-canvas rounded-xl font-bold shadow-card hover:bg-ink-2 transition text-sm">
                    Imprimir / PDF
                </a>
                <p class="text-xs text-ink-3">Imprime o recorte selecionado.</p>
            </div>
        </div>

        {{-- Placar --}}
        <div class="bg-surface rounded-2xl shadow-pop border border-line p-8">
            @if($j['status'] === 'ao_vivo')
                <div class="mb-4 flex items-center justify-center gap-2 text-danger font-bold text-sm">
                    <span class="w-2 h-2 rounded-full bg-danger animate-pulse"></span> AO VIVO — placar parcial
                </div>
            @elseif($j['status'] === 'agendado')
                <div class="mb-4 text-center text-ink-3 text-sm font-bold uppercase">Agendado — ainda não começou</div>
            @endif

            <div class="flex items-center justify-between">
                <div class="flex-1 text-center">
                    @if($j['time_casa']['logo_url'])<img src="{{ $j['time_casa']['logo_url'] }}" class="w-16 h-16 mx-auto rounded-full object-cover mb-2">@endif
                    <p class="font-bold text-ink">{{ $j['time_casa']['nome_exibicao'] }}</p>
                </div>
                <div class="px-8 text-4xl font-extrabold text-ink">
                    {{ $j['placar_casa'] ?? 0 }} <span class="text-ink-3">x</span> {{ $j['placar_fora'] ?? 0 }}
                </div>
                <div class="flex-1 text-center">
                    @if($j['time_fora']['logo_url'])<img src="{{ $j['time_fora']['logo_url'] }}" class="w-16 h-16 mx-auto rounded-full object-cover mb-2">@endif
                    <p class="font-bold text-ink">{{ $j['time_fora']['nome_exibicao'] }}</p>
                </div>
            </div>

            @if(!empty($sumula['placar_por_periodo']))
            {{-- As parciais são do jogo inteiro mesmo quando há filtro: são
                 a referência de onde a parcial escolhida se encaixa. Clicar
                 filtra por ela. --}}
            <div class="mt-6 flex flex-wrap justify-center gap-2 text-xs text-ink-2">
                @foreach($sumula['placar_por_periodo'] as $parcial)
                    <a href="{{ route('placar.scout.sumula', $comPeriodo($parcial['periodo'])) }}"
                       class="px-3 py-1 rounded-full transition {{ $periodoAtivo === $parcial['periodo'] ? 'bg-warn text-white dark:text-canvas font-bold' : 'bg-subtle hover:bg-line' }}">
                        {{ $nomeDoPeriodo }} {{ $parcial['periodo'] }}: {{ $parcial['placar_casa'] }}-{{ $parcial['placar_fora'] }}
                    </a>
                @endforeach
            </div>
            @endif
        </div>

        @if(empty($sumula['eventos']))
            <div class="bg-surface rounded-2xl shadow-pop border border-line p-12 text-center">
                <p class="text-ink-2">
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
            <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
                <div class="p-4 border-b border-line bg-subtle flex items-baseline justify-between gap-2">
                    <h2 class="text-sm font-bold text-ink">{{ $nome }}</h2>
                    <span class="text-xs text-ink-3">
                        {{ $periodoAtivo ? $nomeDoPeriodo . ' ' . $periodoAtivo : 'jogo todo' }}
                    </span>
                </div>
                @if(empty($sumula['totais_por_jogador'][$lado]))
                    <div class="p-4 text-xs text-ink-3">Sem {{ $vocab['pontos'] }} {{ $periodoAtivo ? 'neste ' . $vocab['periodo'] : 'nesta partida' }}.</div>
                @else
                    <table class="w-full text-xs">
                        <tbody class="divide-y divide-line">
                            @foreach($sumula['totais_por_jogador'][$lado] as $totais)
                            <tr>
                                <td class="px-4 py-2 text-ink">
                                    <a href="{{ route('placar.scout.atuacao', [$jogo, $totais['jogador_id']]) }}" class="hover:underline">
                                        @if($totais['numero']) <span class="text-ink-3 font-mono">#{{ $totais['numero'] }}</span> @endif
                                        {{ $totais['nome_exibicao'] }}
                                    </a>
                                </td>
                                <td class="px-4 py-2 text-right font-bold text-ink">{{ $totais['pontos'] }} {{ $vocab['pontos'] }}</td>
                                @if($vocab['tem_falta'])
                                    {{-- Vôlei não tem falta: a coluna sairia sempre zerada. --}}
                                    <td class="px-4 py-2 text-right text-ink-3">{{ $totais['faltas'] }} faltas</td>
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
        <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
            <div class="p-4 border-b border-line">
                <h2 class="text-sm font-bold text-ink">Linha do tempo</h2>
            </div>
            <ul class="divide-y divide-line max-h-[32rem] overflow-y-auto">
                @foreach($sumula['eventos'] as $evento)
                <li class="p-3 flex items-center gap-3 text-sm @if($evento['estornado']) opacity-40 @endif">
                    <span class="text-xs font-mono font-bold text-ink-2 w-12 shrink-0">{{ $evento['minuto'] ?? '—' }}</span>
                    {{-- O nome que o esporte dá ao lance (gol, cesta de 3,
                         ponto), resolvido no servidor. --}}
                    <span class="text-xs font-bold text-ink-2 w-28 shrink-0">{{ $evento['rotulo'] }}</span>
                    <span class="flex-1 text-ink">
                        @if(isset($evento['substituicao']))
                            {{-- Uma substituição são duas pessoas: dizer só
                                 quem entrou deixa a linha pela metade. --}}
                            @php $troca = $evento['substituicao']; @endphp
                            <span class="text-danger">↓ {{ $troca['sai']['nome_exibicao'] ?? '—' }}</span>
                            <span class="text-ok">↑ {{ $troca['entra']['nome_exibicao'] ?? '—' }}</span>
                        @elseif($evento['jogador'])
                            @if($evento['jogador']['numero']) <span class="text-ink-3 font-mono">#{{ $evento['jogador']['numero'] }}</span> @endif
                            {{ $evento['jogador']['nome_exibicao'] }}
                        @endif
                        {{-- Sem repetir o valor: o rótulo já diz "cesta de 3". --}}
                        @if($evento['estornado']) <span class="text-danger font-bold">estornado</span> @endif
                    </span>
                    {{-- De quem foi o lance: na súmula completa a linha do
                         tempo mistura os dois times. --}}
                    <span class="text-xs text-ink-3 truncate max-w-[10rem] hidden sm:inline">{{ $nomeDoTime[$evento['time_id']] ?? '' }}</span>
                    <span class="text-xs text-ink-3 w-8 text-right shrink-0">{{ $evento['periodo'] ? mb_strtoupper(mb_substr($vocab['periodo'], 0, 1)) . $evento['periodo'] : '' }}</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</div>
</x-app-layout>
