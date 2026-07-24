@php
    use Illuminate\Support\Carbon;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aprovação de contratos — Lote #{{ $batch->id }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f1f0;font-family:Arial,Helvetica,sans-serif;color:#1f1819;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f1f0;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);">

    <tr>
        <td style="background:#A00001;color:#ffffff;padding:22px 28px;">
            <div style="font-size:20px;font-weight:bold;line-height:1.2;">Clube dos Funcionários da CSN</div>
            <div style="font-size:14px;opacity:.9;margin-top:4px;">Aprovação de contratos de freelancer</div>
        </td>
    </tr>

    <tr>
        <td style="padding:26px 28px 8px;">
            <p style="margin:0 0 14px;font-size:15px;line-height:1.55;">
                Prezado(a) {{ config('freelancers.director.name') }},
            </p>
            <p style="margin:0 0 14px;font-size:15px;line-height:1.55;">
                O <strong>Lote #{{ $batch->id }}</strong> foi conferido e aprovado pela gerência
                @if($batch->reviewedBy) por <strong>{{ $batch->reviewedBy->name }}</strong> @endif
                e aguarda o aval da diretoria. São <strong>{{ $services->count() }} contrato(s)</strong>,
                totalizando <strong>R$ {{ number_format($total, 2, ',', '.') }}</strong>.
            </p>
            <p style="margin:0 0 4px;font-size:15px;line-height:1.55;">
                A relação completa segue em anexo, em PDF.
            </p>
        </td>
    </tr>

    {{-- ============ OS DOIS PINs ============ --}}
    <tr>
        <td style="padding:18px 28px 6px;">
            <p style="margin:0 0 12px;font-size:15px;line-height:1.55;">
                Para registrar sua decisão, <strong>informe à gerência o código correspondente</strong>.
                Apenas quem recebeu este e-mail conhece estes códigos — é o que garante que a decisão
                partiu da diretoria.
            </p>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="padding:0 0 12px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                               style="border:2px solid #157a58;border-radius:12px;background:#e8f6f0;">
                            <tr>
                                <td style="padding:16px 20px;">
                                    <div style="font-size:12px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#157a58;">
                                        Para APROVAR o lote
                                    </div>
                                    <div style="font-size:34px;font-weight:bold;letter-spacing:8px;color:#0f5c42;margin-top:6px;font-family:'Courier New',Courier,monospace;">
                                        {{ $approvePin }}
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                               style="border:2px solid #A00001;border-radius:12px;background:#fbe9e8;">
                            <tr>
                                <td style="padding:16px 20px;">
                                    <div style="font-size:12px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#A00001;">
                                        Para RECUSAR o lote
                                    </div>
                                    <div style="font-size:34px;font-weight:bold;letter-spacing:8px;color:#7c0001;margin-top:6px;font-family:'Courier New',Courier,monospace;">
                                        {{ $rejectPin }}
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <p style="margin:14px 0 0;font-size:13px;line-height:1.5;color:#6d6062;">
                A decisão vale para o lote inteiro e é definitiva. Se recusado, os contratos voltam
                para o coordenador refazer o trâmite.
            </p>
        </td>
    </tr>

    {{-- ============ RESUMO ============ --}}
    <tr>
        <td style="padding:22px 28px 6px;">
            <div style="font-size:13px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#6d6062;margin-bottom:10px;">
                Contratos do lote
            </div>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13.5px;">
                <tr style="background:#f7f4f3;">
                    <th align="left" style="padding:9px 10px;border-bottom:1px solid #e8dedd;font-size:11.5px;text-transform:uppercase;letter-spacing:.5px;color:#6d6062;">Freelancer</th>
                    <th align="left" style="padding:9px 10px;border-bottom:1px solid #e8dedd;font-size:11.5px;text-transform:uppercase;letter-spacing:.5px;color:#6d6062;">Função</th>
                    <th align="left" style="padding:9px 10px;border-bottom:1px solid #e8dedd;font-size:11.5px;text-transform:uppercase;letter-spacing:.5px;color:#6d6062;">Data</th>
                    <th align="right" style="padding:9px 10px;border-bottom:1px solid #e8dedd;font-size:11.5px;text-transform:uppercase;letter-spacing:.5px;color:#6d6062;">Valor</th>
                </tr>
                @foreach($services as $service)
                    <tr>
                        <td style="padding:9px 10px;border-bottom:1px solid #f0eae9;">{{ $service->freelancer->name ?? '—' }}</td>
                        <td style="padding:9px 10px;border-bottom:1px solid #f0eae9;color:#6d6062;">{{ $service->functionFreelancer->name ?? '—' }}</td>
                        <td style="padding:9px 10px;border-bottom:1px solid #f0eae9;color:#6d6062;white-space:nowrap;">{{ Carbon::parse($service->start_date)->format('d/m/Y') }}</td>
                        <td align="right" style="padding:9px 10px;border-bottom:1px solid #f0eae9;white-space:nowrap;">R$ {{ number_format($service->price, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr style="background:#f7f4f3;">
                    <td colspan="3" style="padding:11px 10px;font-weight:bold;">Total</td>
                    <td align="right" style="padding:11px 10px;font-weight:bold;font-size:16px;color:#A00001;white-space:nowrap;">
                        R$ {{ number_format($total, 2, ',', '.') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td style="padding:22px 28px 26px;">
            <p style="margin:0;font-size:12px;line-height:1.6;color:#9a8e90;border-top:1px solid #eee4e3;padding-top:14px;">
                Mensagem automática do sistema Lara — Clube dos Funcionários da CSN.<br>
                Enviada em {{ now()->format('d/m/Y \à\s H:i') }}. Não é necessário responder este e-mail:
                basta informar o código à gerência.
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>
