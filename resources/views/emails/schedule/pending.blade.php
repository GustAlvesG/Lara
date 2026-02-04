<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f9; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; }
        .header { background-color: #f59e0b; padding: 40px 20px; text-align: center; color: #ffffff; }
        .content { padding: 40px; color: #1e293b; }
        .alert-box { background-color: #fffbeb; border: 1px solid #fef3c7; border-radius: 12px; padding: 20px; color: #92400e; font-size: 14px; margin-bottom: 25px; }
        .details-card { background-color: #f8fafc; border-radius: 12px; padding: 25px; border: 1px solid #eef2ff; }
        .detail-row { display: flex; margin-bottom: 10px; font-size: 14px; }
        .detail-label { font-weight: bold; width: 100px; color: #64748b; text-transform: uppercase; font-size: 10px; }
        .detail-value { font-weight: 800; color: #1e3a8a; }
        .status-pill { background-color: #fef9c3; color: #854d0e; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .footer { background-color: #f1f5f9; padding: 20px; text-align: center; font-size: 12px; color: #64748b; }
        h1 { margin: 0; font-size: 24px; font-weight: 800; }
        .button { display: inline-block; background-color: #f59e0b; color: #ffffff; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-weight: bold; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header" style="background-color: #eab308;">
            <h1>Falta pouco!</h1>
            <p style="margin-top: 10px; opacity: 0.9;">Conclua o pagamento para garantir sua reserva.</p>
        </div>
        <div class="content">
            <p>Olá, <strong>{{ $data['name'] }}</strong>,</p>
            <div class="alert-box">
                Sua reserva está <strong>aguardando pagamento</strong>. O horário será liberado para outros sócios se o pagamento não for confirmado em até 10 minutos.
            </div>
            
            <div class="details-card">
                <div class="detail-row">
                    <span class="detail-label">Local</span>
                    <span class="detail-value">{{ $data['place_name'] }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Data</span>
                    <span class="detail-value">{{ $data['date'] }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Horário</span>
                    <span class="detail-value">{{ $data['time'] }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Valor</span>
                    <span class="detail-value">R$ {{ $data['price'] }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="status-pill">Pendente</span>
                </div>
            </div>

        </div>
        <div class="footer">
            © 2026 Clube dos Funcionários - Lara<br>
            Este é um e-mail automático, por favor não responda.
        </div>
    </div>
</body>
</html>