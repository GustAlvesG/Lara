@php
    use Illuminate\Support\Carbon;

    // Os termos de comissão de venda aparecem no lote com o mesmo nome e a mesma
    // data do contrato do turno, com outro valor. Sem dizer isso com todas as
    // letras, a relação parece ter linhas repetidas.
    $comissoes = $services->filter->isCommissionAmendment();
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
            @if($comissoes->isNotEmpty())
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                       style="border-left:4px solid #157a58;background:#eef8f3;border-radius:0 8px 8px 0;margin:0 0 14px;">
                    <tr>
                        <td style="padding:12px 16px;font-size:14px;line-height:1.55;">
                            <strong style="color:#0f5c42;">
                                {{ $comissoes->count() }} destes documentos {{ $comissoes->count() === 1 ? 'é um termo' : 'são termos' }}
                                de comissão de venda</strong>, totalizando
                            <strong>R$ {{ number_format($comissoes->sum('price'), 2, ',', '.') }}</strong>.
                            A comissão remunera as vendas do garçom no turno e é paga <strong>além</strong> do contrato
                            do mesmo dia — por isso o freelancer aparece duas vezes na relação, com valores diferentes.
                            Não são lançamentos repetidos.
                        </td>
                    </tr>
                </table>
            @endif
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
                    <th align="left" style="padding:9px 10px;border-bottom:1px solid #e8dedd;font-size:11.5px;text-transform:uppercase;letter-spacing:.5px;color:#6d6062;">Nº</th>
                    <th align="left" style="padding:9px 10px;border-bottom:1px solid #e8dedd;font-size:11.5px;text-transform:uppercase;letter-spacing:.5px;color:#6d6062;">Freelancer</th>
                    <th align="left" style="padding:9px 10px;border-bottom:1px solid #e8dedd;font-size:11.5px;text-transform:uppercase;letter-spacing:.5px;color:#6d6062;">Função</th>
                    <th align="left" style="padding:9px 10px;border-bottom:1px solid #e8dedd;font-size:11.5px;text-transform:uppercase;letter-spacing:.5px;color:#6d6062;">Data</th>
                    <th align="right" style="padding:9px 10px;border-bottom:1px solid #e8dedd;font-size:11.5px;text-transform:uppercase;letter-spacing:.5px;color:#6d6062;">Valor</th>
                </tr>
                @foreach($services as $service)
                    @php
                        $nota = $service->kindNote();
                        // A borda fecha a última linha do contrato, seja ela a
                        // principal, a do tipo ou a da descrição.
                        $borda = ($nota || filled($service->description)) ? '' : 'border-bottom:1px solid #f0eae9;';
                        $cor = $service->isCommissionAmendment() ? '#157a58' : '#4f46e5';
                    @endphp
                    <tr>
                        <td style="padding:9px 10px;{{ $borda }}color:#9a8e90;white-space:nowrap;font-family:'Courier New',Courier,monospace;">#{{ $service->id }}</td>
                        <td style="padding:9px 10px;{{ $borda }}">
                            {{ $service->freelancer->name ?? '—' }}
                            @if($service->kindLabel())
                                <span style="display:inline-block;margin-left:4px;padding:1px 7px;border-radius:9px;font-size:10.5px;font-weight:bold;color:#ffffff;background:{{ $cor }};white-space:nowrap;">{{ $service->kindLabel() }}</span>
                            @endif
                        </td>
                        <td style="padding:9px 10px;{{ $borda }}color:#6d6062;">{{ $service->functionFreelancer->name ?? '—' }}</td>
                        <td style="padding:9px 10px;{{ $borda }}color:#6d6062;white-space:nowrap;">{{ Carbon::parse($service->start_date)->format('d/m/Y') }}</td>
                        <td align="right" style="padding:9px 10px;{{ $borda }}white-space:nowrap;">R$ {{ number_format($service->price, 2, ',', '.') }}</td>
                    </tr>
                    @if($nota)
                        <tr>
                            <td colspan="5" style="padding:0 10px 9px;{{ filled($service->description) ? '' : 'border-bottom:1px solid #f0eae9;' }}color:{{ $cor }};font-size:12.5px;line-height:1.45;">
                                {{ $nota }}
                            </td>
                        </tr>
                    @endif
                    @if(filled($service->description))
                        <tr>
                            <td colspan="5" style="padding:0 10px 9px;border-bottom:1px solid #f0eae9;color:#6d6062;font-size:12.5px;line-height:1.45;">
                                <span style="color:#9a8e90;">Descrição / justificativa:</span> {{ $service->description }}
                            </td>
                        </tr>
                    @endif
                @endforeach
                <tr style="background:#f7f4f3;">
                    <td colspan="4" style="padding:11px 10px;font-weight:bold;">Total</td>
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
