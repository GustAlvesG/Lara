@php
    // Um template para os e-mails das inscrições de torneio; o tipo decide cor,
    // título e o que a pessoa precisa fazer agora. Tabela, não flexbox: Outlook.
    $type = $data['type'] ?? 'tournament.registered';
    [$color, $soft, $lead] = match ($type) {
        'tournament.paid' => ['#16a34a', '#f0fdf4', 'Pagamento recebido: sua inscrição está confirmada.'],
        'tournament.cancelled' => ['#dc2626', '#fef2f2', 'Sua inscrição foi cancelada pela organização do torneio.'],
        'tournament.invite' => ['#2563eb', '#eff6ff', ($data['inviter'] ?? 'Um sócio') . ' convidou você para o time ' . ($data['team'] ?? '') . '. Entre no site de locação, em Torneios, para aceitar ou recusar. Se você ainda não estiver inscrito na categoria, a inscrição é feita ao aceitar e o pagamento é pelo site.'],
        default => ['#7f1d1d', '#fdf2f2', 'Recebemos sua inscrição. Ela só fica garantida depois do pagamento, feito pelo site dentro do período de inscrição.'],
    };
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $data['subject'] ?? 'Inscrição em torneio' }}</title>
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
        .footer { background-color: #f1f5f9; padding: 20px; text-align: center; font-size: 12px; color: #64748b; line-height: 1.5; }
        h1 { margin: 0; font-size: 24px; font-weight: 800; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header" style="background-color: {{ $color }};">
            <h1>{{ $data['title'] ?? 'Inscrição em torneio' }}</h1>
            <p style="margin: 10px 0 0; opacity: 0.9;">{{ $data['tournament'] }} · {{ $data['dates'] }}</p>
        </div>

        <div class="content">
            <p style="margin-top: 0;">Olá, <strong>{{ $data['name'] }}</strong>,</p>

            <div class="lead" style="background-color: {{ $soft }};">
                {{ $lead }}
                @if ($type === 'tournament.invite')
                    @if (!empty($data['team_members']))
                        <span style="display: block; margin-top: 8px;"><strong>No time:</strong> {{ implode(', ', $data['team_members']) }}</span>
                    @endif
                    <span class="deadline" style="color: {{ $color }};">Responda até {{ $data['pay_until'] }}</span>
                @endif
                @if ($type === 'tournament.registered' && !empty($data['pay_until']))
                    <span class="deadline" style="color: {{ $color }};">Pague até {{ $data['pay_until'] }}</span>
                @endif
                @if ($type === 'tournament.cancelled')
                    @if (!empty($data['cancel_reason']))
                        <span style="display: block; margin-top: 8px;"><strong>Motivo:</strong> {{ $data['cancel_reason'] }}</span>
                    @endif
                    @if (!empty($data['refunded']))
                        <span style="display: block; margin-top: 8px;"><strong>Valor estornado:</strong> R$ {{ $data['refunded'] }}</span>
                    @endif
                @endif
            </div>

            <div class="card">
                <p class="card-title">{{ $data['tournament'] }}@if (!empty($data['place_group'])) · {{ $data['place_group'] }}@endif</p>
                <table class="details" role="presentation">
                    @foreach ($data['categories'] as $category)
                        <tr>
                            <td class="label">{{ $category['name'] }}</td>
                            <td class="value">R$ {{ $category['amount'] }} <span style="font-weight: 400; color: #64748b;">· {{ $category['status'] }}</span></td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td class="label">Total</td>
                        <td class="value">R$ {{ $data['total'] }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="footer">
            Este e-mail foi enviado automaticamente em {{ $data['issued_at'] }}. Em caso de dúvida, fale com a organização do torneio.
        </div>
    </div>
</body>
</html>
