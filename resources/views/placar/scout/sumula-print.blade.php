@php
    $j = $sumula['jogo'];
    $recorte = $sumula['recorte'];
    $periodoAtivo = $sumula['periodo'];
    // Cada esporte chama as coisas do seu jeito — ver Vocabulario.
    $vocab = $sumula['vocabulario'];
    $nomeDoPeriodo = ucfirst($vocab['periodo']);
    $confronto = $j['time_casa']['nome_exibicao'] . ' x ' . $j['time_fora']['nome_exibicao'];
    // Sem isto, a linha do tempo da súmula completa não diz de quem foi
    // cada lance — o documento impresso fica ambíguo.
    $nomeDoTime = [
        $j['time_casa']['id'] => $j['time_casa']['nome_exibicao'],
        $j['time_fora']['id'] => $j['time_fora']['nome_exibicao'],
    ];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    {{-- O título vira o nome do arquivo quando se imprime em PDF: com o
         recorte no nome, duas súmulas do mesmo jogo não se confundem. --}}
    <title>Súmula{{ $recorte ? ' ' . $recorte['nome_exibicao'] : '' }}{{ $periodoAtivo ? ' ' . $nomeDoPeriodo . ' ' . $periodoAtivo : '' }} — {{ $confronto }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; font-size: 11px; color: #1f1819; margin: 0; padding: 16px; background: #fff; }

        .head { border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 16px; }
        .head h1 { font-size: 16px; margin: 0 0 4px; }
        .head p { margin: 0; font-size: 10px; color: #555; }

        .placar { text-align: center; margin-bottom: 20px; }
        .placar .times { font-size: 14px; font-weight: bold; }
        .placar .resultado { font-size: 28px; font-weight: bold; margin-top: 4px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; font-size: 10px; }
        th { background: #f2f2f2; }

        h2 { font-size: 12px; margin: 16px 0 6px; }
        .cols { display: flex; gap: 16px; }
        .cols > div { flex: 1; }

        .estornado { text-decoration: line-through; color: #999; }

        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <button class="no-print" onclick="window.print()" style="margin-bottom:12px;">Imprimir</button>

    <div class="head">
        <h1>Súmula do Jogo</h1>
        @if($recorte || $periodoAtivo)
            <p>
                <b>Recorte:
                    {{ $recorte['nome_exibicao'] ?? 'os dois times' }}@if($periodoAtivo), {{ $nomeDoPeriodo }} {{ $periodoAtivo }}@endif</b>
                — lances e totais só deste recorte; o placar é o da partida inteira.
            </p>
        @endif
        <p>
            {{ ucfirst($j['esporte']) }} · {{ \Illuminate\Support\Carbon::parse($j['data_hora'])->format('d/m/Y H:i') }}
            @if($j['competicao']) · {{ $j['competicao']['nome'] }} @endif
            @if($j['local']) · {{ $j['local'] }} @endif
            · Status: {{ $j['status'] }}
        </p>
    </div>

    <div class="placar">
        <div class="times">{{ $confronto }}</div>
        <div class="resultado">{{ $j['placar_casa'] ?? 0 }} x {{ $j['placar_fora'] ?? 0 }}</div>
    </div>

    @if(!empty($sumula['placar_por_periodo']))
    <table>
        <thead><tr><th>{{ $nomeDoPeriodo }}</th><th>{{ $j['time_casa']['nome_exibicao'] }}</th><th>{{ $j['time_fora']['nome_exibicao'] }}</th></tr></thead>
        <tbody>
            {{-- As parciais são sempre do jogo inteiro, mesmo com filtro:
                 são a referência de onde a parcial escolhida se encaixa. --}}
            @foreach($sumula['placar_por_periodo'] as $parcial)
            <tr @if($periodoAtivo === $parcial['periodo']) style="font-weight:bold" @endif>
                <td>{{ $nomeDoPeriodo }} {{ $parcial['periodo'] }}</td>
                <td>{{ $parcial['placar_casa'] }}</td>
                <td>{{ $parcial['placar_fora'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="cols">
        @foreach($lados as $lado => $nome)
        <div>
            <h2>{{ $nome }} <small>({{ $periodoAtivo ? $nomeDoPeriodo . ' ' . $periodoAtivo : 'jogo todo' }})</small></h2>
            <table>
                <thead>
                    <tr>
                        <th>Nº</th><th>Jogador</th><th>{{ ucfirst($vocab['pontos']) }}</th>
                        {{-- Vôlei não tem falta: a coluna sairia sempre zerada. --}}
                        @if($vocab['tem_falta'])<th>Faltas</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($sumula['totais_por_jogador'][$lado] as $totais)
                        <tr>
                            <td>{{ $totais['numero'] ?? '—' }}</td>
                            <td>{{ $totais['nome_exibicao'] }}</td>
                            <td>{{ $totais['pontos'] }}</td>
                            @if($vocab['tem_falta'])<td>{{ $totais['faltas'] }}</td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $vocab['tem_falta'] ? 4 : 3 }}">Sem {{ $vocab['pontos'] }} neste recorte.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endforeach
    </div>

    @if(!empty($sumula['eventos']))
    <h2>Linha do tempo</h2>
    <table>
        <thead><tr><th>Minuto</th><th>Lance</th><th>Time</th><th>Nº</th><th>Jogador</th><th>{{ $nomeDoPeriodo }}</th></tr></thead>
        <tbody>
            @foreach($sumula['eventos'] as $evento)
            <tr class="@if($evento['estornado']) estornado @endif">
                <td>{{ $evento['minuto'] ?? '—' }}</td>
                {{-- O nome do lance no esporte: gol, cesta de 3, ponto. O
                     valor já está dentro dele, então não tem coluna própria. --}}
                <td>{{ $evento['rotulo'] }}</td>
                <td>{{ $nomeDoTime[$evento['time_id']] ?? '—' }}</td>
                <td>{{ $evento['jogador']['numero'] ?? '—' }}</td>
                <td>
                    @if(isset($evento['substituicao']))
                        sai {{ $evento['substituicao']['sai']['nome_exibicao'] ?? '—' }},
                        entra {{ $evento['substituicao']['entra']['nome_exibicao'] ?? '—' }}
                    @else
                        {{ $evento['jogador']['nome_exibicao'] ?? '—' }}
                    @endif
                </td>
                <td>{{ $evento['periodo'] ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</body>
</html>
