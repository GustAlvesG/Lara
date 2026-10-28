{{-- Fechamento de caixa dos ingressos (dompdf: CSS simples, sem Tailwind).
     Por dia e pessoa, o recebido por forma de pagamento; lançamentos; linhas
     para a assinatura de quem entrega e de quem confere. --}}
@php
    use App\Services\Ticket\TicketCashService;
    use App\Models\SchedulePayment;

    $money = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $from = $report['from'];
    $to = $report['to'];
    $period = $from->isSameDay($to) ? $from->format('d/m/Y') : $from->format('d/m/Y') . ' a ' . $to->format('d/m/Y');
    $totals = $report['totals'];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Caixa dos ingressos — {{ $period }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; margin: 0; }
        h1 { font-size: 16px; margin: 0 0 2px; color: #7f1d1d; }
        h2 { font-size: 12px; margin: 16px 0 6px; }
        .meta { color: #475569; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 8px; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #94a3b8; padding: 4px; }
        td { padding: 4px; border-bottom: 1px solid #e2e8f0; }
        .num { text-align: right; font-family: DejaVu Sans Mono, monospace; }
        .total td { font-weight: bold; border-top: 1px solid #94a3b8; }
        .note { color: #64748b; font-size: 9px; }
        .sign { margin-top: 40px; width: 100%; }
        .sign td { border: 0; width: 50%; padding-top: 30px; text-align: center; }
        .line { border-top: 1px solid #1e293b; margin: 0 20px; padding-top: 4px; }
    </style>
</head>
<body>
    <h1>Caixa dos ingressos — {{ $period }}</h1>
    <div class="meta">{{ $person ? 'Pessoa: ' . $person->name : ($all ? 'Todas as pessoas' : '') }} · gerado pelo Lara em {{ now()->format('d/m/Y H:i') }} por {{ $by->name }}</div>

    <table>
        <thead><tr><th>Forma de pagamento</th><th class="num">Recebido</th></tr></thead>
        <tbody>
            @foreach ($report['methods'] as $method => $methodLabel)
                <tr><td>{{ $methodLabel }}</td><td class="num">{{ $money($totals['methods'][$method] ?? 0) }}</td></tr>
            @endforeach
            <tr class="total"><td>Total recebido no balcão ({{ $totals['sales'] }} venda(s), {{ $totals['tickets'] }} ingresso(s))</td><td class="num">{{ $money($totals['total']) }}</td></tr>
        </tbody>
    </table>
    <p class="note">Cortesias: {{ $totals['courtesies'] }} ingresso(s), sem valor. Cancelados: {{ $totals['cancelled'] }} ingresso(s) de baixa manual ({{ $money($totals['cancelled_amount']) }}); a devolução, se houve, foi feita por fora do Lara e não sai do total acima.</p>

    @foreach ($report['days'] as $day => $rows)
        <h2>{{ \Illuminate\Support\Carbon::parse($day)->format('d/m/Y') }}</h2>
        <table>
            <thead>
                <tr>
                    <th>Pessoa</th>
                    @foreach ($report['methods'] as $methodLabel)<th class="num">{{ $methodLabel }}</th>@endforeach
                    <th class="num">Total</th><th class="num">Vendas</th><th class="num">Ingressos</th><th class="num">Cortesias</th><th class="num">Cancelados</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['user']?->name ?? '—' }}</td>
                        @foreach (array_keys($report['methods']) as $method)<td class="num">{{ $money($row['methods'][$method] ?? 0) }}</td>@endforeach
                        <td class="num"><strong>{{ $money($row['total']) }}</strong></td>
                        <td class="num">{{ $row['sales'] }}</td>
                        <td class="num">{{ $row['tickets'] }}</td>
                        <td class="num">{{ $row['courtesies'] }}</td>
                        <td class="num">{{ $row['cancelled'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    @if ($report['site'])
        <h2>Site (eRede) — fora do caixa</h2>
        <p>{{ $report['site']['orders'] }} pedido(s) · {{ $report['site']['tickets'] }} ingresso(s) · {{ $money($report['site']['amount']) }} pagos @if ($report['site']['refunded'] > 0)· {{ $money($report['site']['refunded']) }} estornados @endif</p>
    @endif

    @if ($report['entries'] !== [])
        <h2>Lançamentos</h2>
        <table>
            <thead><tr><th>Quando</th><th>Pessoa</th><th>Lançamento</th><th>Evento</th><th>Pedido</th><th class="num">Ingr.</th><th class="num">Valor</th></tr></thead>
            <tbody>
                @foreach ($report['entries'] as $e)
                    <tr>
                        <td>{{ $e['at']->format('d/m H:i') }}</td>
                        <td>{{ $e['user']?->name ?? '—' }}</td>
                        <td>{{ TicketCashService::KIND_LABELS[$e['kind']] }}@if ($e['method']) · {{ SchedulePayment::MANUAL_METHODS[$e['method']] ?? $e['method'] }}@endif</td>
                        <td>{{ $e['event'] }}</td>
                        <td>#{{ $e['order_id'] }} {{ $e['buyer'] }}</td>
                        <td class="num">{{ $e['tickets'] }}</td>
                        <td class="num">{{ $e['kind'] === TicketCashService::KIND_COURTESY ? '—' : ($e['kind'] === TicketCashService::KIND_CANCEL ? '− ' : '') . $money($e['amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="sign">
        <tr>
            <td><div class="line">Quem entrega o caixa</div></td>
            <td><div class="line">Quem confere (financeiro)</div></td>
        </tr>
    </table>
</body>
</html>
