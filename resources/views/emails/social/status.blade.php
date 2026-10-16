@php
    // Um template para todos os estágios da reserva social; o tipo decide cor,
    // título e o que o sócio precisa fazer agora. Tabela, não flexbox: Outlook.
    $type = $data['type'] ?? 'social.requested';
    [$color, $soft, $lead] = match ($type) {
        'social.approved' => ['#eab308', '#fffbeb', 'Sua pré-reserva foi aprovada. Para garantir a data, pague pelo aplicativo dentro do prazo.'],
        'social.confirmed' => ['#16a34a', '#f0fdf4', 'Pagamento recebido: sua reserva está confirmada.'],
        'social.refused' => ['#dc2626', '#fef2f2', 'Sua pré-reserva não pôde ser aprovada.'],
        'social.cancelled' => ['#dc2626', '#fef2f2', 'Sua reserva foi cancelada.'],
        'social.expired' => ['#64748b', '#f8fafc', 'O prazo de pagamento venceu e a data voltou a ficar disponível.'],
        default => ['#7f1d1d', '#fdf2f2', 'Recebemos sua pré-reserva. A secretaria vai entrar em contato para o contrato; depois da aprovação, o pagamento é liberado no aplicativo.'],
    };
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $data['subject'] ?? 'Reserva de espaço social' }}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f9; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; }
        .header { padding: 36px 24px; text-align: center; color: #ffffff; }
        .content { padding: 32px 40px 40px; color: #1e293b; font-size: 15px; line-height: 1.6; }
        .lead { border-radius: 12px; padding: 18px 20px; font-size: 14px; margin-bottom: 6px; border: 1px solid #e2e8f0; }
        .deadline { display: block; margin-top: 10px; font-size: 16px; font-weight: 800; }
        .card { background-color: #f8fafc; border-radius: 12px; padding: 20px 22px; margin-top: 18px; border: 1px solid #e2e8f0; }
        .card-title { font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: #64748b; font-weight: bold; margin: 0 0 12px; }
        table.details { width: 100%; border-collapse: collapse; }
        table.details td { padding: 5px 0; font-size: 14px; vertical-align: top; }
        td.label { color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; font-weight: bold; width: 40%; padding-right: 12px; }
        td.value { color: #1e293b; font-weight: 700; }
        .total td { border-top: 2px solid #e2e8f0; padding-top: 12px; font-size: 16px; }
        .note { margin-top: 26px; font-size: 13px; color: #475569; background-color: #f8fafc; border-left: 4px solid #94a3b8; border-radius: 8px; padding: 14px 16px; white-space: pre-line; }
        .footer { background-color: #f1f5f9; padding: 20px; text-align: center; font-size: 12px; color: #64748b; line-height: 1.5; }
        h1 { margin: 0; font-size: 24px; font-weight: 800; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header" style="background-color: {{ $color }};">
            <h1>{{ $data['title'] ?? 'Reserva de espaço social' }}</h1>
            <p style="margin: 10px 0 0; opacity: 0.9;">{{ $data['place_name'] }} · {{ $data['date'] }}</p>
        </div>

        <div class="content">
            <p style="margin-top: 0;">Olá, <strong>{{ $data['name'] }}</strong>,</p>

            <div class="lead" style="background-color: {{ $soft }};">
                {{ $lead }}
                @if ($type === 'social.approved' && !empty($data['payment_due_at']))
                    <span class="deadline" style="color: {{ $color }};">Pague até {{ $data['payment_due_at'] }}</span>
                @endif
                @if ($type === 'social.refused' && !empty($data['refusal_reason']))
                    <span style="display: block; margin-top: 8px;"><strong>Motivo:</strong> {{ $data['refusal_reason'] }}</span>
                @endif
                @if ($type === 'social.cancelled')
                    @if (!empty($data['cancel_reason']))
                        <span style="display: block; margin-top: 8px;"><strong>Motivo:</strong> {{ $data['cancel_reason'] }}</span>
                    @endif
                    @if (!empty($data['refunded']))
                        <span style="display: block; margin-top: 8px;">Estorno de <strong>R$ {{ $data['refunded'] }}</strong> solicitado ao cartão usado no pagamento.</span>
                    @endif
                @endif
            </div>

            <div class="card">
                <p class="card-title">Reserva nº {{ $data['booking_id'] }}</p>
                <table class="details">
                    <tr><td class="label">Espaço</td><td class="value">{{ $data['place_name'] }}</td></tr>
                    <tr><td class="label">Data</td><td class="value">{{ $data['date'] }} <span style="font-weight: 400; color: #64748b;">({{ $data['weekday'] }})</span></td></tr>
                    @if (!empty($data['time']))
                        <tr><td class="label">Horário</td><td class="value">{{ $data['time'] }}</td></tr>
                    @endif
                    <tr><td class="label">Evento</td><td class="value">{{ $data['event_type'] }}</td></tr>
                    @if (isset($data['tables_total']))
                        <tr><td class="label">Mesas e cadeiras</td><td class="value">{{ $data['tables_total'] }} mesa(s) · {{ $data['chairs_total'] }} cadeira(s)</td></tr>
                    @endif
                    <tr><td class="label">Situação</td><td class="value">{{ $data['stage_label'] }}</td></tr>
                    <tr><td class="label">Locação do espaço</td><td class="value">R$ {{ $data['base_price'] }}</td></tr>
                    @if (($data['extra_guests'] ?? 0) > 0)
                        <tr><td class="label">{{ $data['extra_guests'] }} convidado(s) extra(s)</td><td class="value">R$ {{ $data['extras_total'] }}</td></tr>
                    @endif
                    @foreach ($data['items'] ?? [] as $item)
                        <tr><td class="label">{{ $item['quantity'] }}× {{ $item['name'] }}</td><td class="value">R$ {{ $item['subtotal'] }}</td></tr>
                    @endforeach
                    @if (!empty($data['discount']))
                        <tr><td class="label">Desconto</td><td class="value">− R$ {{ $data['discount'] }}</td></tr>
                    @endif
                    <tr class="total"><td class="label">Total</td><td class="value">R$ {{ $data['total'] }}</td></tr>
                </table>
            </div>

            @if (in_array($type, ['social.requested', 'social.approved', 'social.confirmed'], true))
                <div class="note">
                    <strong>Convidados não sócios:</strong> a lista (nome e CPF) aceita até {{ $data['guest_limit'] }} pessoa(s) nesta reserva e pode ser editada pelo aplicativo até a véspera do evento. Sócios entram normalmente.
                </div>
            @endif

            @if (!empty($data['rules']))
                <div class="note"><strong>Regras do espaço</strong>
{{ $data['rules'] }}</div>
            @endif
        </div>

        <div class="footer">
            © {{ date('Y') }} Clube dos Funcionários - Lara<br>
            E-mail gerado em {{ $data['issued_at'] ?? now()->format('d/m/Y H:i') }}. Mensagem automática, não responda.
        </div>
    </div>
</body>
</html>
