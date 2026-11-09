@php
    /**
     * A área de assinatura do documento, um bloco por signatário.
     *
     * No modo `original` (o que o tablet exibe e o que tem o hash) os campos
     * vão EM BRANCO: é o documento que a pessoa lê antes de assinar. No modo
     * `final`, cada campo recebe a imagem do traço e a data do servidor. No
     * modo `progress` (09/11/2026), o que o próximo signatário lê: traço e
     * data só de quem já assinou; o pendente segue em branco.
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
            @if(SignatureDocumentRenderer::showsSignatures($mode) && isset($signatureImages[$signer->id]))
                @php $imagem = $signatureImages[$signer->id]; @endphp
                <img src="{{ $imagem['src'] }}" alt="Assinatura" class="sig-img"
                     style="width: {{ $imagem['width'] }}px; height: {{ $imagem['height'] }}px;">
            @endif

            <div class="sig-line">
                @if(isset($contractCaptions))
                    {{-- Contrato (05/11/2026): nome em maiúsculas e só o cargo, sem o papel (Contratante, Testemunha…). --}}
                    <div class="sig-name">{{ mb_strtoupper($signer->name) }}</div>
                    <div class="sig-meta">
                        {{ ($contractCaptions[$signer->id] ?? '') !== '' ? $contractCaptions[$signer->id] . ' — ' : '' }}CPF {{ $signer->maskedCpf() }}
                    </div>
                @else
                    <div class="sig-name">{{ $signer->name }}</div>
                    <div class="sig-meta">
                        {{ $signer->documentCapacityLabel() }} — CPF {{ $signer->maskedCpf() }}
                    </div>
                @endif

                @if(SignatureDocumentRenderer::showsSignatures($mode) && $signer->signed_at)
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
