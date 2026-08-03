@php
    // Layout em tabela, e não em flexbox: Outlook e boa parte dos webmails
    // ignoram display:flex e empilhariam rótulo e valor de forma desalinhada.
    $items = $data['items'] ?? [[
        'place_name' => $data['place_name'] ?? '',
        'date' => $data['date'] ?? '',
        'weekday' => null,
        'time' => $data['time'] ?? '',
        'duration' => null,
        'price' => $data['price'] ?? null,
        'id' => null,
    ]];
    $total = $data['total'] ?? ($data['price'] ?? null);
    $payment = $data['payment'] ?? null;
    $refund = $data['refund'] ?? null;
    $plural = count($items) > 1;

    // Montada em PHP para a data ficar colada à frase, sem quebra antes do ponto.
    $aviso = ($plural ? 'Suas reservas foram canceladas' : 'Sua reserva foi cancelada')
        . ' pela administração do clube'
        . (!empty($data['cancelled_at']) ? ' em ' . $data['cancelled_at'] : '')
        . '.';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $data['subject'] ?? 'Agendamento cancelado' }}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f9; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; }
        .header { background-color: #ef4444; padding: 36px 24px; text-align: center; color: #ffffff; }
        .content { padding: 32px 40px 40px; color: #1e293b; font-size: 15px; line-height: 1.6; }
        .alert-box { background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 18px 20px; color: #991b1b; font-size: 14px; }
        .reason { display: block; margin-top: 10px; padding-top: 10px; border-top: 1px solid #fecaca; color: #7f1d1d; }
        .card { background-color: #f8fafc; border-radius: 12px; padding: 20px 22px; margin-top: 18px; border: 1px solid #e2e8f0; }
        .card-title { font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: #64748b; font-weight: bold; margin: 0 0 12px; }
        table.details { width: 100%; border-collapse: collapse; }
        table.details td { padding: 5px 0; font-size: 14px; vertical-align: top; }
        td.label { color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; font-weight: bold; width: 34%; padding-right: 12px; }
        td.value { color: #334155; font-weight: 700; }
        .slot { border-top: 1px dashed #cbd5e1; margin-top: 14px; padding-top: 14px; }
        .slot:first-of-type { border-top: 0; margin-top: 0; padding-top: 0; }
        .total { margin-top: 18px; padding-top: 14px; border-top: 2px solid #e2e8f0; font-size: 16px; color: #1e293b; }
        .status-pill { background-color: #fee2e2; color: #b91c1c; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .refund-ok { background-color: #ecfdf5; border-left: 4px solid #10b981; color: #065f46; }
        .refund-info { background-color: #f8fafc; border-left: 4px solid #94a3b8; color: #475569; }
        .note { margin-top: 18px; font-size: 13px; border-radius: 8px; padding: 14px 16px; }
        .footer { background-color: #f1f5f9; padding: 20px; text-align: center; font-size: 12px; color: #64748b; line-height: 1.5; }
        h1 { margin: 0; font-size: 24px; font-weight: 800; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $plural ? 'Agendamentos cancelados' : 'Agendamento cancelado' }}</h1>
            <p style="margin: 10px 0 0; opacity: 0.9;">
                {{ $plural ? 'Os horários abaixo foram liberados.' : 'O horário abaixo foi liberado.' }}
            </p>
        </div>

        <div class="content">
            <p style="margin-top: 0;">Olá, <strong>{{ $data['name'] }}</strong>,</p>

            <div class="alert-box">
                {{ $aviso }}
                @if (!empty($data['cancel_reason']))
                    <span class="reason"><strong>Motivo informado:</strong> {{ $data['cancel_reason'] }}</span>
                @endif
            </div>

            <div class="card">
                <p class="card-title">{{ $plural ? 'Reservas canceladas' : 'Reserva cancelada' }}</p>

                @foreach ($items as $item)
                    <div class="slot">
                        <table class="details">
                            <tr>
                                <td class="label">Local</td>
                                <td class="value">{{ $item['place_name'] }}</td>
                            </tr>
                            <tr>
                                <td class="label">Data</td>
                                <td class="value">
                                    {{ $item['date'] }}@if (!empty($item['weekday'])) <span style="font-weight: 400; color: #64748b;">({{ $item['weekday'] }})</span>@endif
                                </td>
                            </tr>
                            <tr>
                                <td class="label">Horário</td>
                                <td class="value">
                                    {{ $item['time'] }}@if (!empty($item['duration'])) <span style="font-weight: 400; color: #64748b;">— {{ $item['duration'] }}</span>@endif
                                </td>
                            </tr>
                            @if (!empty($item['price']))
                                <tr>
                                    <td class="label">Valor</td>
                                    <td class="value">R$ {{ $item['price'] }}</td>
                                </tr>
                            @endif
                            @if (!empty($item['id']))
                                <tr>
                                    <td class="label">Reserva nº</td>
                                    <td class="value">#{{ $item['id'] }}</td>
                                </tr>
                            @endif
                        </table>
                    </div>
                @endforeach

                <table class="details total">
                    <tr>
                        <td class="label" style="padding-top: 0;">Valor da reserva</td>
                        <td style="padding-top: 0;"><strong>R$ {{ $total }}</strong></td>
                    </tr>
                    <tr>
                        <td class="label">Situação</td>
                        <td><span class="status-pill">Cancelado</span></td>
                    </tr>
                </table>
            </div>

            @if ($payment)
                <div class="card">
                    <p class="card-title">Pagamento original</p>
                    <table class="details">
                        <tr>
                            <td class="label">Forma</td>
                            <td class="value">{{ $payment['method'] }}</td>
                        </tr>
                        <tr>
                            <td class="label">Valor pago</td>
                            <td class="value">R$ {{ $payment['amount'] }}</td>
                        </tr>
                        @if (!empty($payment['paid_at']))
                            <tr>
                                <td class="label">Pago em</td>
                                <td class="value">{{ $payment['paid_at'] }}</td>
                            </tr>
                        @endif
                        @if (!empty($payment['reference']))
                            <tr>
                                <td class="label">Transação</td>
                                <td class="value" style="font-family: monospace; font-weight: 600;">{{ $payment['reference'] }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            @endif

            @if (!empty($refund['amount']))
                <div class="note refund-ok">
                    <strong>Estorno solicitado: R$ {{ $refund['amount'] }}.</strong>
                    O pedido já foi enviado ao banco emissor. O prazo para o valor aparecer na sua fatura
                    ou conta é do próprio emissor — normalmente até duas faturas, conforme a bandeira.
                </div>
            @elseif (!empty($refund['paid']))
                <div class="note refund-info">
                    Nenhum estorno foi processado junto com este cancelamento. Se você pagou por esta reserva,
                    procure a secretaria para tratar da devolução — o atendimento confere o pagamento e
                    aplica a política de cancelamento do clube.
                </div>
            @endif

            <p style="margin-top: 26px; font-size: 14px; color: #475569;">
                {{ $plural ? 'Os horários já estão disponíveis' : 'O horário já está disponível' }} para novas reservas.
                Para agendar outra data, acesse o aplicativo. Em caso de dúvida sobre este cancelamento,
                fale com a secretaria informando
                {{ $plural ? 'os números das reservas' : 'o número da reserva' }} acima.
            </p>
        </div>

        <div class="footer">
            © {{ date('Y') }} Clube dos Funcionários - Lara<br>
            E-mail gerado em {{ $data['issued_at'] ?? now()->format('d/m/Y H:i') }}. Mensagem automática, não responda.
        </div>
    </div>
</body>
</html>
