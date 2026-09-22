@php
    /**
     * A área de assinatura do documento, um bloco por signatário.
     *
     * No modo `original` (o que o tablet exibe e o que tem o hash) os campos
     * vão EM BRANCO: é o documento que a pessoa lê antes de assinar. No modo
     * `final`, cada campo recebe a imagem do traço e a data do servidor.
     *
     * O CPF sai mascarado nos dois modos. O documento é entregue à pessoa, fica
     * em pasta e às vezes é fotografado — não há motivo para o número inteiro
     * estar impresso nele.
     */
    use App\Services\Signature\SignatureDocumentRenderer;
@endphp
<div class="sig-area">
    @foreach($signers as $signer)
        <div class="sig-block">
            @if($mode === SignatureDocumentRenderer::MODE_FINAL && isset($signatureImages[$signer->id]))
                <img src="{{ $signatureImages[$signer->id] }}" alt="Assinatura" class="sig-img">
            @endif

            <div class="sig-line">
                <div class="sig-name">{{ $signer->name }}</div>
                <div class="sig-meta">
                    {{ $signer->roleLabel() }} — CPF {{ $signer->maskedCpf() }}
                </div>

                @if($mode === SignatureDocumentRenderer::MODE_FINAL && $signer->signed_at)
                    <div class="sig-meta">
                        Assinado eletronicamente em {{ $signer->signed_at->format('d/m/Y \à\s H:i') }}
                        (horário do servidor).
                    </div>
                @elseif($mode === SignatureDocumentRenderer::MODE_FINAL)
                    <div class="sig-pending">{{ $signer->statusLabel() }}.</div>
                @endif
            </div>
        </div>
    @endforeach
</div>
