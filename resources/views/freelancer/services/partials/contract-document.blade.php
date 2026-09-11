@php
    /**
     * Moldura do documento — cabeçalho, título, corpo, local/data e o bloco das
     * assinaturas. As CLÁUSULAS não estão aqui: vêm da pasta da redação que este
     * contrato firmou (`contract/v1`, `contract/v2`…), escolhida por
     * `contractViewNamespace()`.
     *
     * É a ÚNICA montagem do documento no sistema. O tablet não monta mais o seu
     * em JavaScript: ele busca este HTML por `kiosk.service.document`. Com o
     * texto em dois lugares, cada revisão do jurídico teria de ser escrita duas
     * vezes — e no dia em que as duas divergissem, o freelancer assinaria no
     * tablet um texto diferente do que o painel imprime.
     *
     * Parâmetros:
     *   $service      — contrato, termo aditivo ou termo de comissão
     *   $layout       — 'print' (painel e impressão, padrão) ou 'tablet' (kiosk)
     *   $signing      — null | 'freelancer' | 'coordinator': quem vai assinar
     *                   AGORA, e portanto qual dos dois campos recebe o canvas.
     *                   Da redação 2 em diante o campo do CONTRATANTE nunca
     *                   recebe canvas: quem assina ali é o diretor, com a
     *                   imagem cadastrada, na aprovação do lote
     *   $operatorName — quem assina como CONTRATANTE, no tablet
     */
    use Illuminate\Support\Carbon;

    $layout = $layout ?? 'print';
    $signing = $signing ?? null;
    $operatorName = $operatorName ?? null;
    $tablet = $layout === 'tablet';

    // A qualificação vem congelada quando o contrato já foi assinado: o cadastro
    // muda, o documento firmado não. Ver FreelancerService::contractParty().
    $party = $service->contractParty();

    $meses = [1=>'janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];

    $cpf = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', str_pad((string) $party['cpf'], 11, '0', STR_PAD_LEFT));

    // Data do contrato: a assinatura do freelancer, ou hoje se ainda não assinado.
    $dataRef = $service->freelancer_signed_at ?? now();
    $dataExtenso = $dataRef->day . ' de ' . $meses[(int) $dataRef->month] . ' de ' . $dataRef->year;

    // As imagens vêm por rota autenticada, não pelo disco público — cada frente
    // pela sua, porque a sessão do tablet não é a do painel.
    $signatureRoute = $tablet ? 'kiosk.service.signature' : 'freelancer-services.signature';

    $signatureUrl = fn(string $party) => route($signatureRoute, [
        'freelancerService' => $service->id,
        'party' => $party,
    ]);

    $freelancerSignatureUrl = $service->freelancer_signature_path ? $signatureUrl('freelancer') : null;
    $coordinatorSignatureUrl = $service->coordinator_signature_path ? $signatureUrl('coordinator') : null;

    // Quem assina pelo CONTRATANTE vem da redação do contrato: na 1, o
    // coordenador desenha no tablet; da 2 em diante, assina o diretor na
    // aprovação do lote. A imagem vai embutida (data URI) — o mesmo HTML é
    // impresso pelo painel, exibido no tablet e convertido em PDF, e a
    // assinatura do diretor não tem URL pública.
    $directorSigns = $service->usesDirectorSignature();
    $directorSigned = $directorSigns && $service->hasDirectorSignature();
    $directorSignatureUri = $directorSigned ? $service->director?->signatureDataUri() : null;

    // O campo que recebe o traço desenhado no tablet. O canvas é preso pelo JS
    // do kiosk por estes ids.
    $sigSlot = '<div class="sig-slot" id="sigSlot"><canvas id="sigCanvas"></canvas><div class="sig-ph" id="sigPh">Assine aqui com o dedo</div></div>';
@endphp

@php
    // O corpo é o mesmo nos dois layouts; só a moldura difere. Na impressão ele
    // vai dentro de uma tabela, cujo thead/tfoot o navegador repete em todas as
    // páginas; no tablet, que rola numa tela só, divs bastam.
    $cabecalho = '<div class="doc-header-img"><img src="' . e(asset('images/freelancer/cabecalho.png')) . '" alt="Clube dos Funcionários"></div>';
    $rodape = '<div class="doc-footer-img"><img src="' . e(asset('images/freelancer/rodape.png')) . '" alt="Endereços e contatos do Clube dos Funcionários"></div>';
@endphp

<div class="doc" id="docSheet">
    @if($tablet)
        {!! $cabecalho !!}
    @else
    <table class="doc-table">
    <thead><tr><td>{!! $cabecalho !!}</td></tr></thead>
    <tfoot><tr><td>{!! $rodape !!}</td></tr></tfoot>
    <tbody><tr><td>
    @endif

    <div class="doc-title">{{ $service->documentTitle() }}</div>

    <div class="doc-body">
        @if($service->isCommissionAmendment())
            {{-- Comissão de venda: remunera as vendas do turno e ACRESCE ao
                 contrato, sem alterar nenhuma outra condição dele. --}}
            @include($service->contractViewNamespace() . '.commission-clauses', ['service' => $service, 'party' => $party, 'cpf' => $cpf])
        @elseif($service->isAmendment())
            {{-- O aditivo não repete o contrato: cita o que estava valendo e diz
                 o que passa a valer, ratificando o resto. --}}
            @include($service->contractViewNamespace() . '.amendment-clauses', ['service' => $service, 'party' => $party, 'cpf' => $cpf])
        @else
            @include($service->contractViewNamespace() . '.original-clauses', ['service' => $service, 'party' => $party, 'cpf' => $cpf])
        @endif

        <p class="doc-place"><b>Volta Redonda-RJ, {{ $dataExtenso }}</b></p>

        <div class="doc-signatures">
            <div class="doc-sign-block">
                @if($directorSigns)
                    {{-- Redação 2: pelo CONTRATANTE assina o DIRETOR, com a imagem do
                         cadastro da diretoria, quando aprova o lote. A coordenação só
                         valida pela web e não aparece no documento. Nunca há canvas
                         aqui: ninguém desenha a assinatura do CONTRATANTE. --}}
                    @if($directorSignatureUri)
                        <div class="doc-sign-img"><img src="{{ $directorSignatureUri }}" alt="Assinatura da diretoria"></div>
                    @else
                        <div class="doc-sign-empty"></div>
                    @endif
                    <div class="doc-sign-line"></div>
                    <div class="doc-sign-name">CLUBE DOS FUNCIONARIOS DA CSN</div>
                    <div class="doc-sign-role">
                        CONTRATANTE{{ $directorSigned ? '' : ' — assinatura da diretoria (pendente)' }}
                    </div>
                    @if($directorSigned)
                        <div class="doc-sign-note">Assinado digitalmente por {{ $service->director?->name ?? 'Diretoria' }} em {{ $service->director_signed_at->format('d/m/Y \à\s H:i') }}</div>
                    @endif
                @elseif($signing === 'coordinator')
                    {!! $sigSlot !!}
                @elseif($coordinatorSignatureUrl)
                    <div class="doc-sign-img"><img src="{{ $coordinatorSignatureUrl }}" alt="Assinatura do coordenador"></div>
                @elseif($service->coordinator_signed_at)
                    {{-- Legado: contratos assinados pelo painel antes de a assinatura passar a ser só no tablet. --}}
                    <div class="doc-sign-mark">✓ Assinado eletronicamente{{ $service->coordinatorSignedBy ? ' por ' . $service->coordinatorSignedBy->name : '' }} em {{ $service->coordinator_signed_at->format('d/m/Y H:i') }}</div>
                @else
                    <div class="doc-sign-empty"></div>
                @endif
                @unless($directorSigns)
                    <div class="doc-sign-line"></div>
                    <div class="doc-sign-name">CLUBE DOS FUNCIONARIOS DA CSN</div>
                    <div class="doc-sign-role">
                        @if($signing === 'coordinator')
                            CONTRATANTE{{ $operatorName ? ' · ' . $operatorName : '' }}
                        @elseif($signing === 'freelancer')
                            CONTRATANTE — assinatura do coordenador (pendente)
                        @else
                            CONTRATANTE
                        @endif
                    </div>
                    @if($coordinatorSignatureUrl && $service->coordinator_signed_at && $signing !== 'coordinator')
                        <div class="doc-sign-note">Assinado em {{ $service->coordinator_signed_at->format('d/m/Y H:i') }}{{ $service->coordinatorSignedBy ? ' · ' . $service->coordinatorSignedBy->name : '' }}</div>
                    @endif
                @endunless
            </div>

            <div class="doc-sign-block">
                @if($signing === 'freelancer')
                    {!! $sigSlot !!}
                @elseif($freelancerSignatureUrl)
                    <div class="doc-sign-img"><img src="{{ $freelancerSignatureUrl }}" alt="Assinatura do freelancer"></div>
                @else
                    <div class="doc-sign-empty"></div>
                @endif
                <div class="doc-sign-line"></div>
                <div class="doc-sign-name">{{ $party['name'] }}</div>
                <div class="doc-sign-role">FREELANCER · CPF {{ $cpf }}</div>
                @if($service->freelancer_signed_at && $signing !== 'freelancer')
                    {{-- Contratos assinados pelo bot (antes do kiosk) não têm traço: o
                         aviso evita que o coordenador ache que a assinatura se perdeu. --}}
                    <div class="doc-sign-note">Assinado em {{ $service->freelancer_signed_at->format('d/m/Y H:i') }}{{ $service->freelancerSignedBy ? ' · atendimento por ' . $service->freelancerSignedBy->name : '' }}{{ $freelancerSignatureUrl ? '' : ' · registrado sem desenho' }}</div>
                @endif
            </div>
        </div>
    </div>

    @if($tablet)
        {!! $rodape !!}
    @else
    </td></tr></tbody>
    </table>
    @endif
</div>
