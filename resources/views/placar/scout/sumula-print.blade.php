@php
    $j = $sumula['jogo'];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Súmula — {{ $j['time_casa']['nome_exibicao'] }} x {{ $j['time_fora']['nome_exibicao'] }}</title>
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
        <p>
            {{ ucfirst($j['esporte']) }} · {{ \Illuminate\Support\Carbon::parse($j['data_hora'])->format('d/m/Y H:i') }}
            @if($j['competicao']) · {{ $j['competicao']['nome'] }} @endif
            @if($j['local']) · {{ $j['local'] }} @endif
            · Status: {{ $j['status'] }}
        </p>
    </div>

    <div class="placar">
        <div class="times">{{ $j['time_casa']['nome_exibicao'] }} x {{ $j['time_fora']['nome_exibicao'] }}</div>
        <div class="resultado">{{ $j['placar_casa'] ?? 0 }} x {{ $j['placar_fora'] ?? 0 }}</div>
    </div>

    @if(!empty($sumula['placar_por_periodo']))
    <table>
        <thead><tr><th>Período</th><th>{{ $j['time_casa']['nome_exibicao'] }}</th><th>{{ $j['time_fora']['nome_exibicao'] }}</th></tr></thead>
        <tbody>
            @foreach($sumula['placar_por_periodo'] as $periodo)
            <tr><td>{{ $periodo['periodo'] }}º</td><td>{{ $periodo['placar_casa'] }}</td><td>{{ $periodo['placar_fora'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="cols">
        @foreach(['time_casa' => $j['time_casa']['nome_exibicao'], 'time_fora' => $j['time_fora']['nome_exibicao']] as $lado => $nome)
        <div>
            <h2>{{ $nome }}</h2>
            <table>
                <thead><tr><th>Nº</th><th>Jogador</th><th>Pontos</th><th>Faltas</th></tr></thead>
                <tbody>
                    @forelse($sumula['totais_por_jogador'][$lado] as $totais)
                        <tr><td>{{ $totais['numero'] ?? '—' }}</td><td>{{ $totais['nome_exibicao'] }}</td><td>{{ $totais['pontos'] }}</td><td>{{ $totais['faltas'] }}</td></tr>
                    @empty
                        <tr><td colspan="4">Sem pontuação registrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endforeach
    </div>

    @if(!empty($sumula['eventos']))
    <h2>Linha do tempo</h2>
    <table>
        <thead><tr><th>Minuto</th><th>Tipo</th><th>Nº</th><th>Jogador</th><th>Valor</th><th>Período</th></tr></thead>
        <tbody>
            @foreach($sumula['eventos'] as $evento)
            <tr class="@if($evento['estornado']) estornado @endif">
                <td>{{ $evento['minuto'] ?? '—' }}</td>
                <td>{{ $evento['tipo'] }}</td>
                <td>{{ $evento['jogador']['numero'] ?? '—' }}</td>
                <td>{{ $evento['jogador']['nome_exibicao'] ?? '—' }}</td>
                <td>{{ $evento['valor'] ?? '—' }}</td>
                <td>{{ $evento['periodo'] ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</body>
</html>
