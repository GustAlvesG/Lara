{{-- Código de "meus ingressos" do não sócio (TicketNotifier::accessCode). Tabela, não flexbox: Outlook. --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $data['subject'] ?? 'Código de acesso' }}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f9; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; }
        .header { padding: 36px 24px; text-align: center; color: #ffffff; background-color: #2563eb; }
        .content { padding: 32px 40px 40px; color: #1e293b; font-size: 15px; line-height: 1.6; }
        .code { display: inline-block; margin: 18px 0; padding: 16px 28px; border-radius: 12px; background-color: #eff6ff; border: 1px solid #bfdbfe; font-family: Consolas, 'Courier New', monospace; font-size: 34px; font-weight: 800; letter-spacing: .3em; color: #1e3a8a; }
        .muted { color: #64748b; font-size: 13px; }
        .footer { background-color: #f1f5f9; padding: 20px; text-align: center; font-size: 12px; color: #64748b; line-height: 1.5; }
        h1 { margin: 0; font-size: 24px; font-weight: 800; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Código de acesso</h1>
            <p style="margin: 10px 0 0; opacity: 0.9;">Meus ingressos</p>
        </div>

        <div class="content">
            <p style="margin-top: 0;">Olá, <strong>{{ $data['name'] }}</strong>,</p>
            <p>Use este código no site para ver os seus ingressos:</p>
            <div style="text-align: center;"><span class="code">{{ $data['code'] }}</span></div>
            <p class="muted">Ele vale por {{ $data['minutes'] }} minutos e só pode ser usado uma vez. Se não foi você quem pediu, ignore este e-mail: sem o código, ninguém vê os seus ingressos.</p>
        </div>

        <div class="footer">Este e-mail foi enviado automaticamente. Em caso de dúvida, fale com a organização do evento.</div>
    </div>
</body>
</html>
