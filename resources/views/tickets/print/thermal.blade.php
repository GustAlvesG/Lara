{{-- Ingresso físico na impressora térmica de 80 mm (TicketPrintService).
     Página solta, sem o layout do Lara: preto no branco, 72 mm de largura útil,
     um ingresso por página (o driver corta no fim de cada página). Abre a
     janela de impressão sozinha; com o Chrome em --kiosk-printing, imprime
     direto na impressora padrão. --}}
@php
    use App\Support\Cpf;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingressos — pedido {{ $order->id }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #fff; color: #000; }
        body { font-family: "Courier New", Consolas, monospace; font-size: 12px; line-height: 1.3; }
        .ticket { width: 72mm; margin: 0 auto; padding: 3mm 2mm 6mm; break-after: page; page-break-after: always; }
        .ticket:last-child { break-after: auto; page-break-after: auto; }
        .center { text-align: center; }
        .event { font-size: 16px; font-weight: bold; text-transform: uppercase; margin: 0 0 1mm; }
        .meta { font-size: 11px; }
        hr { border: 0; border-top: 1px dashed #000; margin: 2mm 0; }
        .label { font-size: 9px; text-transform: uppercase; letter-spacing: .5px; }
        .value { font-size: 13px; font-weight: bold; margin-bottom: 1.5mm; word-break: break-word; }
        .big { font-size: 15px; }
        .qr { display: block; width: 46mm; height: 46mm; margin: 2mm auto; image-rendering: pixelated; }
        .code { font-size: 17px; font-weight: bold; letter-spacing: 1px; }
        .copy { display: inline-block; border: 2px solid #000; padding: 0 2mm; font-weight: bold; margin-top: 1mm; }
        .warn { border: 2px solid #000; padding: 1.5mm; font-weight: bold; text-align: center; margin: 2mm 0; }
        .foot { font-size: 9px; }
        @media screen {
            body { background: #e5e5e5; padding: 16px 0; }
            .ticket { background: #fff; margin-bottom: 16px; box-shadow: 0 1px 4px rgba(0,0,0,.2); }
            .toolbar { width: 72mm; margin: 0 auto 12px; font-family: system-ui, sans-serif; font-size: 13px; text-align: center; }
            .toolbar button { font-size: 14px; padding: 6px 14px; cursor: pointer; }
        }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Imprimir de novo</button>
        <p>{{ $tickets->count() }} ingresso(s). Se a impressão não abriu, use o botão (a via já foi contada).</p>
    </div>

    @foreach ($tickets as $row)
        @php($ticket = $row['ticket'])
        <section class="ticket">
            <div class="center">
                <p class="event">{{ $order->event->title }}</p>
                <div class="meta">{{ $order->event->datesLabel() }}</div>
                @if ($order->event->venue)<div class="meta">{{ $order->event->venue }}</div>@endif
                @if ($row['copy'] > 1)<div class="copy">{{ $row['copy'] }}ª VIA</div>@endif
            </div>
            <hr>
            <div class="label">Ingresso</div>
            <div class="value big">{{ $ticket->type?->fullName() }}</div>
            @if ($ticket->seat)
                <div class="label">Lugar{{ $ticket->seat->map ? ' · ' . $ticket->seat->map->name : '' }}</div>
                <div class="value big">{{ $ticket->seat->fullLabel() }}@if ($ticket->seat->category) · {{ $ticket->seat->category }}@endif</div>
            @endif
            <div class="label">{{ $ticket->seat ? 'Responsável' : 'Nome' }}</div>
            <div class="value">{{ $ticket->holder_name }}</div>
            @if ($ticket->holder_cpf)
                <div class="label">CPF</div>
                <div class="value">{{ Cpf::format($ticket->holder_cpf) }}</div>
            @endif
            @if ($order->courtesy)<div class="label">Cortesia</div>@endif

            <img class="qr" src="{{ $row['qr'] }}" alt="">
            <div class="center code">{{ $ticket->code }}</div>

            @if ($ticket->seat)
                <div class="warn">LUGAR — NÃO É ENTRADA<br><span style="font-weight: normal;">cada pessoa entra com o ingresso dela</span></div>
            @elseif (! $ticket->holder_cpf)
                <p class="foot">Vale uma entrada para quem apresentar este ingresso. O código é lido uma vez só.</p>
            @else
                <p class="foot">Pessoal e intransferível: uma entrada da pessoa e do CPF acima. Leve um documento com foto.</p>
            @endif
            <hr>
            <p class="foot center">Pedido {{ $order->id }} · impresso em {{ now()->format('d/m/Y H:i') }} por {{ $by->name }}</p>
        </section>
    @endforeach

    <script>
        // A janela de impressão abre sozinha; fechada a impressão, a aba fecha (se foi aberta pelo Lara).
        window.addEventListener('load', () => setTimeout(() => window.print(), 300));
        window.addEventListener('afterprint', () => { if (window.opener) window.close(); });
    </script>
</body>
</html>
