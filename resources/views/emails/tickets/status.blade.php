@php
    // Um template para os e-mails dos pedidos de ingresso (TicketNotifier); o
    // tipo decide cor, título e o que a pessoa precisa saber. Tabela, não
    // flexbox: Outlook.
    $type = $data['type'] ?? 'tickets.paid';
    [$color, $soft, $lead] = match ($type) {
        'tickets.cancelled' => ['#dc2626', '#fef2f2', 'Um ou mais ingressos do seu pedido foram cancelados pela organização do evento.'],
        'tickets.link' => ['#2563eb', '#eff6ff', 'Aqui está o acesso ao seu pedido de ingressos.'],
        default => ['#16a34a', '#f0fdf4', 'Pagamento recebido: seus ingressos estão confirmados. Na entrada, cada pessoa apresenta o QR Code do ingresso (no site) ou informa o código e o CPF.'],
    };
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $data['subject'] ?? 'Ingressos' }}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f9; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; }
        .header { padding: 36px 24px; text-align: center; color: #ffffff; }
        .content { padding: 32px 40px 40px; color: #1e293b; font-size: 15px; line-height: 1.6; }
        .lead { border-radius: 12px; padding: 18px 20px; font-size: 14px; margin-bottom: 6px; border: 1px solid #e2e8f0; }
        .card { background-color: #f8fafc; border-radius: 12px; padding: 20px 22px; margin-top: 18px; border: 1px solid #e2e8f0; }
        .card-title { font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: #64748b; font-weight: bold; margin: 0 0 12px; }
        table.details { width: 100%; border-collapse: collapse; }
        table.details td { padding: 6px 0; font-size: 14px; vertical-align: top; }
        td.label { color: #1e293b; font-weight: 700; width: 55%; padding-right: 12px; }
        td.value { color: #1e293b; text-align: right; }
        .muted { font-weight: 400; color: #64748b; font-size: 12px; }
        .code { font-family: Consolas, 'Courier New', monospace; font-size: 15px; font-weight: 800; letter-spacing: .06em; }
        .total td { border-top: 2px solid #e2e8f0; padding-top: 12px; font-size: 16px; font-weight: 800; }
        .button { display: inline-block; margin-top: 14px; padding: 12px 22px; border-radius: 999px; color: #ffffff !important; text-decoration: none; font-weight: 700; }
        .footer { background-color: #f1f5f9; padding: 20px; text-align: center; font-size: 12px; color: #64748b; line-height: 1.5; }
        h1 { margin: 0; font-size: 24px; font-weight: 800; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header" style="background-color: {{ $color }};">
            <h1>{{ $data['title'] ?? 'Ingressos' }}</h1>
            <p style="margin: 10px 0 0; opacity: 0.9;">{{ $data['event'] }} · {{ $data['dates'] }}</p>
        </div>

        <div class="content">
            <p style="margin-top: 0;">Olá, <strong>{{ $data['name'] }}</strong>,</p>

            <div class="lead" style="background-color: {{ $soft }};">
                {{ $lead }}
                @if ($type === 'tickets.cancelled')
                    @if (!empty($data['cancel_reason']))
                        <span style="display: block; margin-top: 8px;"><strong>Motivo:</strong> {{ $data['cancel_reason'] }}</span>
                    @endif
                    @if (!empty($data['refunded']))
                        <span style="display: block; margin-top: 8px;"><strong>Valor estornado:</strong> R$ {{ $data['refunded'] }}</span>
                    @endif
                @endif
                @if (!empty($data['link']))
                    <br><a href="{{ $data['link'] }}" class="button" style="background-color: {{ $color }};">Ver meus ingressos</a>
                @endif
            </div>

            <div class="card">
                <p class="card-title">Pedido {{ $data['order_id'] }}@if (!empty($data['venue'])) · {{ $data['venue'] }}@endif</p>
                <table class="details" role="presentation">
                    @foreach ($data['tickets'] as $ticket)
                        <tr>
                            <td class="label">
                                {{ $ticket['holder'] }}
                                <span class="muted" style="display: block;">{{ $ticket['type'] }}@if ($ticket['lot']) · {{ $ticket['lot'] }}@endif · {{ $ticket['status'] }}</span>
                            </td>
                            <td class="value">
                                @if ($ticket['code'])<span class="code">{{ $ticket['code'] }}</span><br>@endif
                                <span class="muted">R$ {{ $ticket['amount'] }}</span>
                            </td>
                        </tr>
                    @endforeach
                    @if (!empty($data['discount']))
                        <tr>
                            <td class="label">Desconto @if (!empty($data['coupon_code']))<span class="muted">(cupom {{ $data['coupon_code'] }})</span>@endif</td>
                            <td class="value">− R$ {{ $data['discount'] }}</td>
                        </tr>
                    @endif
                    <tr class="total">
                        <td class="label">Total</td>
                        <td class="value">@if (!empty($data['courtesy']))Cortesia @else R$ {{ $data['total'] }}@endif</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="footer">
            Este e-mail foi enviado automaticamente em {{ $data['issued_at'] }}. O ingresso é pessoal: vale para a pessoa e o CPF indicados. Em caso de dúvida, fale com a organização do evento.
        </div>
    </div>
</body>
</html>
