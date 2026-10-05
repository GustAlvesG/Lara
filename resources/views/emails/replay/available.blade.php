@php
    /*
     | Aviso de vídeos do Replay prontos.
     |
     | Não leva o arquivo nem link direto para o vídeo: leva ao login do
     | portal de locação. Foi a decisão de quem opera — o vídeo de quem
     | alugou se coleta autenticado, no mesmo lugar onde a reserva foi feita.
     |
     | Layout em tabela e CSS embutido pelo mesmo motivo dos outros e-mails do
     | sistema: Outlook e boa parte dos webmails ignoram flexbox e folha
     | externa.
     */
    $count = (int) ($data['videos_count'] ?? 0);
    $portal = $data['portal_url'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $data['subject'] ?? 'Seus vídeos estão disponíveis' }}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f9; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; }
        .header { background-color: #059669; padding: 36px 24px; text-align: center; color: #ffffff; }
        .content { padding: 32px 40px 40px; color: #1e293b; font-size: 15px; line-height: 1.6; }
        .card { background-color: #f8fafc; border-radius: 12px; padding: 20px 22px; margin-top: 18px; border: 1px solid #e2e8f0; }
        table.details { width: 100%; border-collapse: collapse; }
        table.details td { padding: 5px 0; font-size: 14px; vertical-align: top; }
        td.label { color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; font-weight: bold; width: 40%; padding-right: 12px; }
        td.value { color: #065f46; font-weight: 700; }
        .cta { display: inline-block; margin-top: 24px; background-color: #059669; color: #ffffff !important; text-decoration: none; padding: 14px 28px; border-radius: 12px; font-weight: bold; font-size: 15px; }
        .note { margin-top: 26px; font-size: 13px; color: #475569; background-color: #ecfdf5; border-left: 4px solid #059669; border-radius: 8px; padding: 14px 16px; }
        .footer { background-color: #f1f5f9; padding: 20px; text-align: center; font-size: 12px; color: #64748b; line-height: 1.5; }
        h1 { margin: 0; font-size: 24px; font-weight: 800; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Seus vídeos estão prontos</h1>
            <p style="margin: 10px 0 0; opacity: 0.9;">
                {{ $count > 1 ? $count . ' momentos gravados na sua reserva.' : 'Um momento gravado na sua reserva.' }}
            </p>
        </div>

        <div class="content">
            <p style="margin-top: 0;">Olá, <strong>{{ $data['member_name'] ?? 'Sócio' }}</strong>,</p>

            <p>
                Os replays gravados durante a sua reserva já estão disponíveis para assistir e baixar
                no site de locação de espaços.
            </p>

            <div class="card">
                <table class="details">
                    <tr>
                        <td class="label">Local</td>
                        <td class="value">{{ $data['place_name'] ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Data</td>
                        <td class="value">{{ $data['recorded_at'] ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Vídeos</td>
                        <td class="value">{{ $count }}</td>
                    </tr>
                    <tr>
                        <td class="label">Disponíveis até</td>
                        <td class="value">{{ $data['expires_at'] ?? '—' }}</td>
                    </tr>
                </table>
            </div>

            @if($portal)
                <p style="text-align: center;">
                    <a href="{{ $portal }}" class="cta">Acessar meus vídeos</a>
                </p>
            @endif

            <div class="note">
                Entre com o seu login do site de locação para ver os vídeos.
                Eles ficam disponíveis por <strong>7 dias a contar da gravação</strong> e depois
                são apagados automaticamente — baixe o que quiser guardar.
            </div>
        </div>

        <div class="footer">
            Este é um e-mail automático do Replay — não é necessário responder.
        </div>
    </div>
</body>
</html>
