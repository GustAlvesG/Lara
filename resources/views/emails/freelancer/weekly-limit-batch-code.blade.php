<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Código de liberação — registro em massa</title>
</head>
<body style="margin:0;padding:0;background:#f4f1f0;font-family:Arial,Helvetica,sans-serif;color:#1f1819;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f1f0;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);">

    <tr>
        <td style="background:#A00001;color:#ffffff;padding:22px 28px;">
            <div style="font-size:20px;font-weight:bold;line-height:1.2;">Clube dos Funcionários da CSN</div>
            <div style="font-size:14px;opacity:.9;margin-top:4px;">Liberação de contratos acima do limite semanal</div>
        </td>
    </tr>

    <tr>
        <td style="padding:26px 28px 8px;">
            <p style="margin:0 0 14px;font-size:15px;line-height:1.55;">
                {{ $coordinator->name }},
            </p>
            <p style="margin:0 0 14px;font-size:15px;line-height:1.55;">
                @if($requestedBy)
                    <strong>{{ $requestedBy->name }}</strong> está fazendo um registro em massa
                @else
                    Está sendo feito um registro em massa
                @endif
                em que <strong>{{ count($contracts) }} contratos</strong> passam do limite de
                {{ \App\Models\FreelancerService::WEEKLY_LIMIT }} serviços numa janela de
                {{ \App\Models\FreelancerService::WEEKLY_WINDOW_DAYS }} dias:
            </p>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                   style="margin:0 0 14px;border:1px solid #e6e0e0;border-radius:10px;font-size:14px;">
                @foreach($contracts as $contract)
                    <tr>
                        <td style="padding:8px 12px;border-bottom:1px solid #f0ebeb;">{{ $contract['freelancer'] }}</td>
                        <td style="padding:8px 12px;border-bottom:1px solid #f0ebeb;text-align:right;white-space:nowrap;">{{ $contract['date']->format('d/m/Y') }}</td>
                    </tr>
                @endforeach
            </table>

            <p style="margin:0 0 14px;font-size:15px;line-height:1.55;">
                A liberação é do setor Comercial. Se concordar, <strong>dite o código abaixo</strong>
                para quem está registrando — ele libera todos os contratos da lista de uma vez.
            </p>
            <p style="margin:0 0 14px;font-size:14px;line-height:1.55;color:#5c5253;">
                Este mesmo código foi enviado a <strong>todos os coordenadores do Comercial</strong> —
                basta que um de vocês responda. Se outro já tiver liberado, o código deixa de valer.
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding:6px 28px 4px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                   style="border:2px solid #A00001;border-radius:12px;">
                <tr>
                    <td align="center" style="padding:20px 16px;">
                        <div style="font-size:13px;text-transform:uppercase;letter-spacing:1px;color:#7a6f70;">
                            Código de liberação
                        </div>
                        <div style="font-size:38px;font-weight:bold;letter-spacing:10px;color:#A00001;margin-top:8px;">
                            {{ $code }}
                        </div>
                        <div style="font-size:13px;color:#7a6f70;margin-top:10px;">
                            Válido até {{ $expiresAt->format('d/m/Y H:i') }}
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td style="padding:18px 28px 26px;">
            <p style="margin:0 0 10px;font-size:14px;line-height:1.55;color:#5c5253;">
                Este código vale <strong>uma única vez</strong> e <strong>só para os contratos listados acima</strong>.
                Se o lote mudar, um novo código precisa ser pedido.
            </p>
            <p style="margin:0;font-size:14px;line-height:1.55;color:#5c5253;">
                <strong>Se você não reconhece este pedido, não dite o código</strong> e avise o setor.
                Ele não aparece em nenhuma tela do sistema: só quem recebeu este e-mail o conhece.
            </p>
        </td>
    </tr>

    <tr>
        <td style="background:#faf8f8;padding:16px 28px;font-size:12px;color:#8a7f80;">
            Mensagem automática do sistema de contratos de freelancer. Não é necessário responder.
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>
