@php
    /**
     * Cláusula da FORMA DE PAGAMENTO — a chave PIX para a qual o valor vai.
     *
     * Recebe $service e $numero (o número da cláusula, sempre um sub-item da
     * cláusula do valor que vem logo acima: "2.1" no contrato, "4.1" nos
     * aditivos). É sub-item de propósito: acrescentar um item na numeração
     * corrida deslocaria as cláusulas de todo o modelo, inclusive as que os
     * outros documentos citam pelo número.
     *
     * A chave é a do próprio contrato quando ele já foi assinado (cópia
     * congelada na assinatura) e a do cadastro enquanto não foi — ver
     * FreelancerService::pixKey(). Mantido em sincronia com o pixClause() do
     * Kiosk (resources/views/kiosk/index.blade.php).
     */
@endphp

<p><b>{{ $numero }}- DA FORMA DE PAGAMENTO:</b> O valor previsto na cláusula anterior é pago exclusivamente por
    transferência PIX para a chave <b>{{ $service->pixKeyTypeLabel() }}: {{ $service->pixKeyFormatted() }}</b>,
    indicada pelo FREELANCER e por ele conferida neste ato. O FREELANCER declara que a chave acima corresponde a
    conta de sua titularidade e responsabiliza-se pela exatidão dela, ficando o CONTRATANTE desobrigado de qualquer
    novo pagamento na hipótese de a transferência ser efetuada para chave informada de forma incorreta. Qualquer
    alteração da chave deve ser comunicada ao CONTRATANTE antes do pagamento.</p>
