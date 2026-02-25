<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Comprovante de Transação</title>

    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 720px;
            margin: auto;
            background: #ffffff;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,.08);
        }

        h1 {
            text-align: center;
            margin-bottom: 24px;
        }

        .section {
            margin-bottom: 24px;
        }

        .section h2 {
            font-size: 16px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 6px;
            margin-bottom: 12px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
        }

        .label {
            font-weight: bold;
            color: #555;
        }

        .value {
            text-align: right;
        }

        .status {
            font-weight: bold;
            color: #c0392b;
        }

        .success {
            color: #27ae60;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #999;
            margin-top: 32px;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Comprovante de Transação</h1>

    {{-- Dados Gerais --}}
    <div class="section">
        <h2>Informações Gerais</h2>

        <div class="row">
            <span class="label">Data da Consulta</span>
            <span class="value">{{ \Carbon\Carbon::parse($transaction['requestDateTime'])->format('d/m/Y H:i:s') }}</span>
        </div>

        <div class="row">
            <span class="label">Referência</span>
            <span class="value">{{ $transaction['authorization']['reference'] }}</span>
        </div>

        <div class="row">
            <span class="label">TID</span>
            <span class="value">{{ $transaction['authorization']['tid'] }}</span>
        </div>
    </div>

    {{-- Autorização --}}
    <div class="section">
        <h2>Autorização</h2>

        <div class="row">
            <span class="label">Status</span>
            <span class="value status">{{ $transaction['authorization']['status'] }}</span>
        </div>

        <div class="row">
            <span class="label">Mensagem</span>
            <span class="value">{{ $transaction['authorization']['returnMessage'] }}</span>
        </div>

        <div class="row">
            <span class="label">Data</span>
            <span class="value">
                {{ \Carbon\Carbon::parse($transaction['authorization']['dateTime'])->format('d/m/Y H:i:s') }}
            </span>
        </div>

        <div class="row">
            <span class="label">Valor</span>
            <span class="value">
                R$ {{ number_format($transaction['authorization']['amount'] / 100, 2, ',', '.') }}
            </span>
        </div>

        <div class="row">
            <span class="label">Parcelas</span>
            <span class="value">{{ $transaction['authorization']['installments'] }}</span>
        </div>
    </div>

    {{-- Cartão --}}
    <div class="section">
        <h2>Dados do Cartão</h2>

        <div class="row">
            <span class="label">Titular</span>
            <span class="value">{{ $transaction['authorization']['cardHolderName'] }}</span>
        </div>

        <div class="row">
            <span class="label">Cartão</span>
            <span class="value">
                **** **** **** {{ $transaction['authorization']['last4'] }}
            </span>
        </div>

        <div class="row">
            <span class="label">Bandeira / Tipo</span>
            <span class="value">{{ $transaction['authorization']['kind'] }}</span>
        </div>
    </div>

    {{-- Captura --}}
    @if(isset($transaction['capture']))
        <div class="section">
            <h2>Captura</h2>

            <div class="row">
                <span class="label">NSU</span>
                <span class="value">{{ $transaction['capture']['nsu'] }}</span>
            </div>

            <div class="row">
                <span class="label">Valor Capturado</span>
                <span class="value">
                    R$ {{ number_format($transaction['capture']['amount'] / 100, 2, ',', '.') }}
                </span>
            </div>
        </div>
    @endif

    {{-- Reembolso --}}
    @if(!empty($transaction['refunds']))
        <div class="section">
            <h2>Reembolso</h2>

            @foreach($transaction['refunds'] as $refund)
                <div class="row">
                    <span class="label">Status</span>
                    <span class="value success">{{ $refund['status'] }}</span>
                </div>

                <div class="row">
                    <span class="label">Data</span>
                    <span class="value">
                        {{ \Carbon\Carbon::parse($refund['refundDateTime'])->format('d/m/Y') }}
                    </span>
                </div>

                <div class="row">
                    <span class="label">Valor</span>
                    <span class="value">
                        R$ {{ number_format($refund['amount'] / 100, 2, ',', '.') }}
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="footer">
        Este comprovante é apenas informativo e não possui valor fiscal.
    </div>
</div>

</body>
</html>