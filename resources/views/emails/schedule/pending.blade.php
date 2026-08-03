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
    $holdMinutes = $data['hold_minutes'] ?? null;
    $holdDeadline = $data['hold_deadline'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $data['subject'] ?? 'Agendamento aguardando pagamento' }}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f9; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; }
        .header { background-color: #eab308; padding: 36px 24px; text-align: center; color: #ffffff; }
        .content { padding: 32px 40px 40px; color: #1e293b; font-size: 15px; line-height: 1.6; }
        .alert-box { background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 18px 20px; color: #92400e; font-size: 14px; margin-bottom: 6px; }
        .deadline { display: block; margin-top: 10px; font-size: 16px; font-weight: 800; color: #b45309; }
        .card { background-color: #f8fafc; border-radius: 12px; padding: 20px 22px; margin-top: 18px; border: 1px solid #e2e8f0; }
        .card-title { font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: #64748b; font-weight: bold; margin: 0 0 12px; }
        table.details { width: 100%; border-collapse: collapse; }
        table.details td { padding: 5px 0; font-size: 14px; vertical-align: top; }
        td.label { color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; font-weight: bold; width: 34%; padding-right: 12px; }
        td.value { color: #1e3a8a; font-weight: 700; }
        .slot { border-top: 1px dashed #cbd5e1; margin-top: 14px; padding-top: 14px; }
        .slot:first-of-type { border-top: 0; margin-top: 0; padding-top: 0; }
        .total { margin-top: 18px; padding-top: 14px; border-top: 2px solid #e2e8f0; font-size: 16px; color: #1e293b; }
        .total strong { color: #b45309; font-size: 18px; }
        .status-pill { background-color: #fef9c3; color: #854d0e; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .note { margin-top: 26px; font-size: 13px; color: #475569; background-color: #f8fafc; border-left: 4px solid #eab308; border-radius: 8px; padding: 14px 16px; }
        .note ul { margin: 8px 0 0; padding-left: 18px; }
        .footer { background-color: #f1f5f9; padding: 20px; text-align: center; font-size: 12px; color: #64748b; line-height: 1.5; }
        h1 { margin: 0; font-size: 24px; font-weight: 800; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Falta o pagamento</h1>
            <p style="margin: 10px 0 0; opacity: 0.9;">Seu horário está separado, mas ainda não confirmado.</p>
        </div>

        <div class="content">
            <p style="margin-top: 0;">Olá, <strong>{{ $data['name'] }}</strong>,</p>

            <div class="alert-box">
                {{ count($items) > 1 ? 'Seus horários estão' : 'Seu horário está' }} <strong>aguardando pagamento</strong>.
                @if ($holdMinutes)
                    Guardamos {{ count($items) > 1 ? 'as reservas' : 'a reserva' }} por {{ $holdMinutes }} minutos; depois disso {{ count($items) > 1 ? 'os horários voltam a ficar disponíveis' : 'o horário volta a ficar disponível' }} para outros sócios.
                @else
                    Conclua o pagamento para garantir a reserva; sem confirmação, o horário volta a ficar disponível para outros sócios.
                @endif
                @if ($holdDeadline)
                    <span class="deadline">Pague até {{ $holdDeadline }}</span>
                @endif
            </div>

            <div class="card">
                <p class="card-title">O que está reservado</p>

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
                        <td class="label" style="padding-top: 0;">Total a pagar</td>
                        <td style="padding-top: 0;"><strong>R$ {{ $total }}</strong></td>
                    </tr>
                    <tr>
                        <td class="label">Situação</td>
                        <td><span class="status-pill">Aguardando pagamento</span></td>
                    </tr>
                </table>
            </div>

            <div class="note">
                <strong>Como concluir:</strong>
                <ul>
                    <li>Volte ao aplicativo, abra <em>Meus agendamentos</em> e finalize o pagamento da reserva pendente.</li>
                    <li>Assim que o pagamento for aprovado, você recebe um novo e-mail com a confirmação.</li>
                    <li>Se você já pagou, ignore esta mensagem — a confirmação chega em seguida.</li>
                </ul>
            </div>
        </div>

        <div class="footer">
            © {{ date('Y') }} Clube dos Funcionários - Lara<br>
            E-mail gerado em {{ $data['issued_at'] ?? now()->format('d/m/Y H:i') }}. Mensagem automática, não responda.
        </div>
    </div>
</body>
</html>
