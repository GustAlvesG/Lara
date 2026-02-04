<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f9; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; }
        .header { background-color: #4f46e5; padding: 40px 20px; text-align: center; color: #ffffff; }
        .content { padding: 40px; color: #1e293b; }
        .details-card { background-color: #f8fafc; border-radius: 12px; padding: 25px; margin-top: 20px; border: 1px solid #eef2ff; }
        .detail-row { display: flex; margin-bottom: 10px; font-size: 14px; }
        .detail-label { font-weight: bold; width: 100px; color: #64748b; text-transform: uppercase; font-size: 10px; }
        .detail-value { font-weight: 800; color: #1e3a8a; }
        .status-pill { background-color: #dcfce7; color: #166534; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .footer { background-color: #f1f5f9; padding: 20px; text-align: center; font-size: 12px; color: #64748b; }
        h1 { margin: 0; font-size: 24px; font-weight: 800; }
        .button { display: inline-block; background-color: #4f46e5; color: #ffffff; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-weight: bold; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Sua Reserva está Confirmada!</h1>
            <p style="margin-top: 10px; opacity: 0.9;">Tudo pronto para o seu jogo.</p>
        </div>
        <div class="content">


            <p>Olá, <strong>{{ $data['name'] }}</strong>,</p>
            <p>Seu agendamento foi processado com sucesso. Confira os detalhes abaixo:</p>
            
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
                    <span class="status-pill">Confirmado</span>
                </div>
            </div>

            <p style="margin-top: 30px; font-size: 13px;">Caso necessário, entre em contato com a secretaria.</p>
            
            {{-- <div style="text-align: center;">
                <a href="#" class="button">Gerenciar Meus Agendamentos</a>
            </div> --}}
        </div>
        <div class="footer">
            © 2026 Clube dos Funcionários - Lara<br>
            Este é um e-mail automático, por favor não responda.
        </div>
    </div>
</body>
</html>