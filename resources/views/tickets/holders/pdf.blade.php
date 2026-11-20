{{-- Listagem dos compradores do evento (dompdf: CSS simples, sem Tailwind).
     Por dia e, dentro do dia, por tipo de ingresso: nome, CPF e ingresso. --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Listagem — {{ $event->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; margin: 0; }
        h1 { font-size: 16px; margin: 0 0 2px; color: #7f1d1d; }
        h2 { font-size: 13px; margin: 18px 0 4px; border-bottom: 2px solid #7f1d1d; padding-bottom: 2px; }
        h3 { font-size: 11px; margin: 10px 0 4px; }
        .meta { color: #475569; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 8px; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #94a3b8; padding: 4px; }
        td { padding: 4px; border-bottom: 1px solid #e2e8f0; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        .n { width: 24px; color: #64748b; }
        .count { color: #64748b; font-weight: normal; }
        .empty { color: #64748b; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>Listagem — {{ $event->title }}</h1>
    <div class="meta">{{ $event->datesLabel() }}@if ($event->venue) · {{ $event->venue }}@endif · {{ $groups->sum('count') }} ingresso(s) pago(s) · gerado pelo Lara em {{ now()->format('d/m/Y H:i') }} por {{ $by->name }}</div>

    @forelse ($groups as $day)
        <h2>{{ $day['label'] }} <span class="count">· {{ $day['count'] }} ingresso(s)</span></h2>
        @foreach ($day['types'] as $type)
            <h3>{{ $type['label'] }} <span class="count">· {{ $type['rows']->count() }}</span></h3>
            <table>
                <thead><tr><th class="n">#</th><th>Nome</th><th>CPF</th><th>Ingresso</th></tr></thead>
                <tbody>
                    @foreach ($type['rows'] as $i => $row)
                        <tr>
                            <td class="n">{{ $i + 1 }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td class="mono">{{ $row['cpf'] }}</td>
                            <td class="mono">{{ $row['ticket'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @empty
        <p class="empty">Nenhum ingresso pago neste evento.</p>
    @endforelse
</body>
</html>
