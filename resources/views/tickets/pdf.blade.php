{{-- Ingressos pagos de um pedido, um por página (dompdf: CSS simples, sem
     Tailwind). O QR leva só o código; a portaria confere nome e CPF. --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Ingressos — pedido {{ $order->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1e293b; margin: 0; }
        .ticket { border: 2px solid #7f1d1d; border-radius: 12px; padding: 24px; margin: 10px 0; }
        .page-break { page-break-after: always; }
        h1 { font-size: 20px; margin: 0 0 4px; color: #7f1d1d; }
        .meta { color: #475569; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 4px 0; }
        .label { font-size: 10px; text-transform: uppercase; color: #64748b; letter-spacing: 1px; }
        .value { font-size: 14px; font-weight: bold; }
        .code { font-family: DejaVu Sans Mono, monospace; font-size: 22px; font-weight: bold; letter-spacing: 2px; }
        .foot { margin-top: 16px; font-size: 10px; color: #64748b; }
    </style>
</head>
<body>
    @foreach ($tickets as $i => $row)
        @php($ticket = $row['ticket'])
        <div class="ticket">
            <h1>{{ $order->event->title }}</h1>
            <div class="meta">{{ $order->event->datesLabel() }}@if ($order->event->venue) · {{ $order->event->venue }}@endif</div>
            <table>
                <tr>
                    <td style="width: 60%;">
                        <div class="label">Nome</div>
                        <div class="value">{{ $ticket->holder_name }}</div>
                        <br>
                        <div class="label">CPF</div>
                        <div class="value">{{ \App\Support\Cpf::format($ticket->holder_cpf) }}</div>
                        <br>
                        <div class="label">Ingresso</div>
                        <div class="value">{{ $ticket->type?->fullName() }} · {{ $ticket->lot?->name }}</div>
                        <br>
                        <div class="label">Código</div>
                        <div class="code">{{ $ticket->code }}</div>
                    </td>
                    <td style="text-align: right;">
                        <img src="{{ $row['qr'] }}" alt="" width="220" height="220">
                    </td>
                </tr>
            </table>
            <p class="foot">Ingresso pessoal e intransferível, válido para uma entrada da pessoa e do CPF acima. Leve um documento com foto. Pedido {{ $order->id }} · gerado pelo Lara em {{ now()->format('d/m/Y H:i') }}.</p>
        </div>
        @unless ($loop->last)<div class="page-break"></div>@endunless
    @endforeach
</body>
</html>
