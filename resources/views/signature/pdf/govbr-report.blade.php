@php
    /**
     * Relatório de validação do documento assinado pelo gov.br — o equivalente
     * da página de manifesto do tablet, em PDF À PARTE.
     *
     * Não pode ser página do PDF assinado: acrescentar páginas ao arquivo
     * desfaria as assinaturas do gov.br. As assinaturas estão no próprio PDF;
     * este relatório registra o que o clube conferiu nelas, e a trilha.
     *
     * O CPF sai MASCARADO, como no manifesto.
     *
     * @var \App\Models\SignatureDocument $document
     * @var \App\Models\SignatureGovbrCheck $check  a conferência cujo arquivo é o PDF final
     */
    use App\Models\SignatureAuditEvent;

    $marca = fn(?bool $ok) => match ($ok) {
        true => 'Sim',
        false => 'Não',
        null => 'Não conferido',
    };
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de assinatura pelo gov.br — {{ $document->title }}</title>
    <style>
        @page { margin: 90px 50px 70px 50px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f1819; line-height: 1.55; }
        header { position: fixed; top: -66px; left: 0; right: 0; height: 52px; border-bottom: 2px solid #A00001; }
        header .club { font-size: 15px; font-weight: bold; color: #A00001; }
        header .doc { font-size: 10px; margin-top: 2px; color: #6d6062; }
        footer { position: fixed; bottom: -46px; left: 0; right: 0; height: 30px; border-top: 1px solid #d8cbc9;
                 padding-top: 6px; font-size: 8px; color: #777; }
        h2 { font-size: 13px; border-bottom: 2px solid #A00001; padding-bottom: 6px; margin: 0 0 8px; }
        h3 { font-size: 11.5px; margin: 14px 0 6px; }
        table { width: 100%; border-collapse: collapse; margin: 0 0 10px; }
        td, th { border: 1px solid #d8cbc9; padding: 5px 7px; font-size: 9px; vertical-align: top; text-align: left; }
        th { background: #f2ecea; }
        .muted { color: #6d6062; }
        .mono { font-family: DejaVu Sans Mono, monospace; font-size: 8.5px; word-break: break-all; }
        .box { border: 1px solid #d8cbc9; padding: 8px; margin-bottom: 12px; }
    </style>
</head>
<body>
    <header>
        <div class="club">Clube dos Funcionários da CSN</div>
        <div class="doc">{{ $document->title }}</div>
    </header>

    <footer>
        Relatório que acompanha o PDF assinado pelo gov.br — código {{ $document->validation_code }}.
        As assinaturas estão no próprio PDF; este relatório não faz parte dele.
    </footer>

    <h2>Relatório de assinatura pelo gov.br</h2>

    <p class="muted" style="font-size:9.5px;margin:0 0 12px;">
        O documento foi assinado digitalmente por cada signatário — pelo portal gov.br (assinatura eletrônica
        avançada, Lei 14.063/2020) ou com certificado ICP-Brasil (assinatura qualificada); o tipo de cada uma está
        abaixo. As assinaturas estão <b>no próprio PDF assinado</b> — por isso este relatório é um arquivo separado:
        acrescentar páginas ao PDF desfaria as assinaturas. Qualquer pessoa pode conferir o PDF assinado no
        validador oficial do governo, em <b>validar.iti.gov.br</b>, sem acesso ao sistema do clube.
        @if(config('signature.pades.enabled'))
            O PDF assinado e este relatório levam ainda o <b>lacre digital do clube</b> (certificado ICP-Brasil{{ config('signature.pades.tsa_url') ? ', com carimbo de tempo' : '' }}),
            acrescentado depois das assinaturas sem alterá-las.
        @endif
    </p>

    <table>
        <tr>
            <td style="width:62%;">
                <b style="font-size:10px;">Documento</b><br>
                {{ $document->title }}<br>
                <span class="muted">
                    @if($document->isUploaded())
                        Documento enviado pronto, em PDF<br>
                    @else
                        Modelo: {{ $document->template?->name }} (versão {{ $document->template_version }})<br>
                    @endif
                    Congelado em: {{ $document->frozen_at?->format('d/m/Y H:i:s') }}<br>
                    Preparado para o gov.br em: {{ $document->govbr_sent_at?->format('d/m/Y H:i:s') ?? 'não registrado' }}<br>
                    Finalizado em: {{ $document->finalized_at?->format('d/m/Y H:i:s') ?? now()->format('d/m/Y H:i:s') }}<br>
                    Documento gerado por: {{ $document->created_by_name ?? 'não registrado' }} (usuário do sistema)
                </span>
            </td>
            <td style="width:38%;text-align:center;">
                <b style="font-size:10px;">Validação</b><br>
                <img src="{{ $qr }}" alt="QR de validação" style="width:100px;height:100px;margin:4px 0;"><br>
                <span class="muted" style="font-size:8px;word-break:break-all;">{{ $validationUrl }}</span><br>
                <b style="font-size:11px;letter-spacing:1px;">{{ $document->validation_code }}</b>
                @if($validationIti ?? false)
                    <br><span style="font-size:8px;color:#6d6062;">Leia o QR e envie <b>este PDF</b> ao validador oficial do governo.</span>
                @endif
            </td>
        </tr>
    </table>

    <div class="box">
        <b>PDF original (SHA-256)</b> — o arquivo enviado para assinar, antes de qualquer assinatura<br>
        <span class="mono">{{ $document->original_sha256 }}</span>
        <br><br>
        <b>PDF assinado (SHA-256)</b> — @if(config('signature.pades.enabled')) o arquivo que voltou assinado, com o lacre do clube acrescentado ao fim (o conteúdo assinado não muda) @else o arquivo que voltou assinado, guardado sem nenhuma alteração @endif<br>
        <span class="mono">{{ $finalSha256 }}</span>
    </div>

    @php $anexos = $document->attachments()->get(); @endphp
    @if($anexos->isNotEmpty())
        {{-- Os anexos não fazem parte do PDF assinado: ficam guardados à parte, ligados pelo hash. --}}
        <h3>Anexos do documento</h3>
        <table>
            <thead><tr><th>Anexo</th><th>Enviado</th><th>SHA-256</th></tr></thead>
            <tbody>
                @foreach($anexos as $anexo)
                    <tr>
                        <td>{{ $anexo->label }}<br><span class="muted">{{ $anexo->sizeLabel() }}</span></td>
                        <td class="muted">{{ $anexo->created_at?->format('d/m/Y H:i:s') }}<br>por {{ $anexo->uploaded_by_name ?? 'não registrado' }}</td>
                        <td class="mono">{{ $anexo->sha256 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h3>Conferência do arquivo</h3>
    <p class="muted" style="margin:0 0 6px;">
        Feita pelo sistema em {{ $check->created_at?->format('d/m/Y H:i:s') }}, no envio de
        {{ $check->checked_by_name ?? 'não registrado' }}.
    </p>
    <table>
        <thead><tr><th>Conferência</th><th style="width:78px;">Resultado</th><th>Detalhe</th></tr></thead>
        <tbody>
            @foreach($check->checks() as $item)
                <tr>
                    <td>{{ $item['label'] }}</td>
                    <td>{{ $marca($item['ok']) }}</td>
                    <td class="muted">{{ $item['detail'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3>Assinaturas no PDF</h3>
    @foreach($check->signatures() as $assinatura)
        <table style="page-break-inside: avoid;">
            <tr>
                <td colspan="3">
                    <b style="font-size:10px;">Assinatura {{ $assinatura['order'] }}: {{ $assinatura['name'] ?? 'sem certificado' }}</b><br>
                    <span class="muted">
                        @if($tipo = \App\Services\Signature\Govbr\GovbrSignatureValidator::kindLabel($assinatura['kind'] ?? null)) <b>{{ $tipo }}</b><br> @endif
                        @if($assinatura['cpf']) CPF {{ $assinatura['cpf'] }}<br> @endif
                        @if($assinatura['signer_name']) Signatário do documento: {{ $assinatura['signer_name'] }}<br> @endif
                        @if($assinatura['signed_at'])
                            Assinado em {{ \Illuminate\Support\Carbon::parse($assinatura['signed_at'])->format('d/m/Y H:i:s') }}
                            (hora declarada na assinatura)<br>
                        @endif
                        @if($assinatura['issuer']) Certificado emitido por {{ $assinatura['issuer'] }}, série {{ $assinatura['serial'] }} @endif
                    </span>
                </td>
            </tr>
            @foreach($assinatura['checks'] as $item)
                <tr>
                    <td style="width:40%;">{{ $item['label'] }}</td>
                    <td style="width:78px;">{{ $marca($item['ok']) }}</td>
                    <td class="muted">{{ $item['detail'] }}</td>
                </tr>
            @endforeach
        </table>
    @endforeach

    <h3>Signatários</h3>
    <table>
        <thead><tr><th>Signatário</th><th>Assinatura registrada</th><th>Convites por e-mail</th></tr></thead>
        <tbody>
            @foreach($signers as $signer)
                <tr>
                    <td>
                        <b>{{ $signer->name }}</b> — {{ $signer->capacityLabel() }}<br>
                        <span class="muted">CPF {{ $signer->maskedCpf() }}</span>
                    </td>
                    <td>
                        @if($signer->signed_at)
                            {{ $signer->signed_at->format('d/m/Y H:i:s') }}<br>
                            <span class="muted">
                                pelo gov.br{{ $signer->govbrCheck ? ', conferido em ' . $signer->govbrCheck->created_at?->format('d/m/Y H:i') . ' por ' . ($signer->govbrCheck->checked_by_name ?? 'não registrado') : '' }}
                            </span>
                        @else
                            {{ $signer->statusLabel() }}
                        @endif
                    </td>
                    <td class="muted">
                        @forelse($signer->govbrInvites as $convite)
                            {{ $convite->maskedEmail() }}, {{ $convite->created_at?->format('d/m/Y H:i') }}
                            por {{ $convite->sent_by_name ?? 'não registrado' }}<br>
                        @empty
                            Nenhum
                        @endforelse
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3>Trilha de auditoria</h3>
    <table>
        <thead>
            <tr>
                <th style="width:100px;">Data e hora</th>
                <th>Evento</th>
                <th style="width:88px;">Origem</th>
            </tr>
        </thead>
        <tbody>
            @foreach($events as $event)
                <tr>
                    <td style="white-space:nowrap;">{{ $event->occurred_at?->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $event->label() }}</td>
                    <td>
                        {{ match($event->actor_type) {
                            SignatureAuditEvent::ACTOR_USER => 'Atendente',
                            SignatureAuditEvent::ACTOR_KIOSK => 'Tablet',
                            default => 'Sistema',
                        } }}{{ $event->ip ? ' · ' . $event->ip : '' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="font-size:8px;color:#777;margin-top:10px;line-height:1.5;">
        A trilha acima é gravada em tabela somente inserção, com cada evento encadeado ao anterior por hash —
        uma alteração feita por fora do sistema quebra a conferência e fica detectável. A revogação dos
        certificados foi consultada na conferência, na lista de certificados revogados de cada AC (resultado na
        linha "Certificado não revogado" de cada assinatura); o validador oficial (validar.iti.gov.br) a confere
        de novo a qualquer momento.
    </p>
</body>
</html>
