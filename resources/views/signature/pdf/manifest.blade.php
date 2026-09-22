@php
    /**
     * Página de manifesto — o anexo que sustenta a assinatura eletrônica
     * avançada (Lei 14.063/2020).
     *
     * Ela existe para responder, sozinha, a três perguntas: QUEM assinou, O
     * QUE foi assinado e COMO isso foi registrado.
     *
     * O `original_sha256` é o que amarra a segunda pergunta: é o hash do PDF
     * que a pessoa leu no tablet. Quem receber este arquivo pode conferir na
     * página pública de validação.
     *
     * O CPF sai MASCARADO. O manifesto viaja junto com o documento, fica em
     * pasta e às vezes é fotografado — não há por que imprimir o número
     * inteiro nele.
     */
    use App\Models\SignatureAuditEvent;
@endphp

<h2 style="border-bottom:2px solid #A00001;padding-bottom:6px;">Manifesto de assinatura eletrônica</h2>

<p style="font-size:10px;color:#6d6062;margin-bottom:14px;">
    Documento assinado eletronicamente, nos termos da Lei 14.063/2020 (assinatura eletrônica avançada),
    com base nas evidências registradas abaixo. Os horários são do servidor do Clube dos Funcionários da CSN.
</p>

<table style="width:100%;border-collapse:collapse;margin-bottom:14px;">
    <tr>
        <td style="width:62%;border:1px solid #d8cbc9;padding:8px;vertical-align:top;">
            <b style="font-size:10px;">Documento</b><br>
            <span style="font-size:10px;">{{ $document->title }}</span><br>
            <span style="font-size:9px;color:#6d6062;">
                Modelo: {{ $document->template?->name }} (versão {{ $document->template_version }})<br>
                Congelado em: {{ $document->frozen_at?->format('d/m/Y H:i:s') }}<br>
                Finalizado em: {{ $document->finalized_at?->format('d/m/Y H:i:s') ?? now()->format('d/m/Y H:i:s') }}<br>
                Atendente: {{ $document->created_by_name ?? 'não registrado' }}<br>
                Local: {{ $document->location ?? 'não informado' }}
            </span>
        </td>
        <td style="width:38%;border:1px solid #d8cbc9;padding:8px;text-align:center;vertical-align:top;">
            <b style="font-size:10px;">Validação</b><br>
            <img src="{{ $qr }}" alt="QR de validação" style="width:108px;height:108px;margin:4px 0;"><br>
            <span style="font-size:8.5px;color:#6d6062;word-break:break-all;">{{ $validationUrl }}</span><br>
            <b style="font-size:11px;letter-spacing:1px;">{{ $document->validation_code }}</b>
        </td>
    </tr>
</table>

<div style="border:1px solid #d8cbc9;padding:8px;margin-bottom:14px;">
    <b style="font-size:10px;">Impressão digital do documento original (SHA-256)</b><br>
    <span style="font-size:8.5px;font-family:DejaVu Sans Mono, monospace;word-break:break-all;">
        {{ $document->original_sha256 }}
    </span>
    <div style="font-size:8.5px;color:#6d6062;margin-top:4px;">
        É o hash do arquivo exibido no tablet, calculado no momento do congelamento — antes de qualquer
        assinatura. Confira-o na página de validação.
    </div>
</div>

<h3 style="font-size:11.5px;margin:0 0 6px;">Signatários e evidências</h3>

@foreach($signers as $signer)
    @php $evidencia = $signer->evidence; @endphp
    <table style="width:100%;border-collapse:collapse;margin-bottom:10px;">
        <tr>
            <td style="border:1px solid #d8cbc9;padding:8px;vertical-align:top;">
                <b style="font-size:10.5px;">{{ $signer->name }}</b>
                <span style="font-size:9px;color:#6d6062;">— {{ $signer->roleLabel() }}</span><br>
                <span style="font-size:9.5px;">CPF {{ $signer->maskedCpf() }}</span><br>

                <span style="font-size:9px;color:#6d6062;">
                    @if($signer->signed_at)
                        Assinado em {{ $signer->signed_at->format('d/m/Y H:i:s') }}<br>
                    @else
                        {{ $signer->statusLabel() }}<br>
                    @endif

                    @if($evidencia)
                        IP: {{ $evidencia->ip ?? 'não registrado' }}<br>
                        Dispositivo: {{ \Illuminate\Support\Str::limit($evidencia->user_agent ?? 'não registrado', 90) }}<br>
                        Tempo de leitura: {{ $evidencia->read_seconds !== null ? $evidencia->read_seconds . ' segundo(s)' : 'não registrado' }}<br>
                        Rolou o documento até o fim: {{ $evidencia->scrolled_to_end ? 'sim' : 'não' }}
                        (informado pelo navegador do tablet)<br>
                        Aceite explícito dos termos: {{ $evidencia->accepted ? 'sim' : 'não' }}<br>
                        Pontos capturados no traço: {{ $evidencia->strokePoints() }}
                    @endif
                </span>
            </td>

            @if(isset($photos[$signer->id]))
                <td style="width:96px;border:1px solid #d8cbc9;padding:6px;text-align:center;vertical-align:top;">
                    <img src="{{ $photos[$signer->id] }}" alt="Foto do signatário" style="width:82px;height:82px;">
                    <div style="font-size:7.5px;color:#6d6062;">registro no ato</div>
                </td>
            @endif
        </tr>
    </table>
@endforeach

<h3 style="font-size:11.5px;margin:14px 0 6px;">Trilha de auditoria</h3>

<table style="width:100%;border-collapse:collapse;">
    <thead>
        <tr>
            <th style="border:1px solid #d8cbc9;background:#f2ecea;padding:4px 6px;font-size:8.5px;text-align:left;width:108px;">Data e hora</th>
            <th style="border:1px solid #d8cbc9;background:#f2ecea;padding:4px 6px;font-size:8.5px;text-align:left;">Evento</th>
            <th style="border:1px solid #d8cbc9;background:#f2ecea;padding:4px 6px;font-size:8.5px;text-align:left;width:92px;">Origem</th>
        </tr>
    </thead>
    <tbody>
        @foreach($events as $event)
            <tr>
                <td style="border:1px solid #eee4e3;padding:3px 6px;font-size:8.5px;white-space:nowrap;">
                    {{ $event->occurred_at?->format('d/m/Y H:i:s') }}
                </td>
                <td style="border:1px solid #eee4e3;padding:3px 6px;font-size:8.5px;">
                    {{ $event->label() }}
                </td>
                <td style="border:1px solid #eee4e3;padding:3px 6px;font-size:8.5px;">
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

<p style="font-size:8px;color:#777;margin-top:12px;line-height:1.5;">
    A trilha acima é gravada em tabela somente inserção, com cada evento encadeado ao anterior por hash —
    uma alteração feita por fora do sistema quebra a conferência e fica detectável. A rolagem até o fim do
    documento é informada pelo navegador do tablet: o servidor registra o que recebeu, com o horário dele.
</p>
