@php
    /**
     * Documento base do contrato (Clube dos Funcionários da CSN) preenchido com
     * os dados do serviço. As variáveis ((...)) do modelo original viram os
     * campos abaixo. Mantido em sincronia com o buildDocument() do Kiosk
     * (resources/views/kiosk/index.blade.php).
     */
    use Illuminate\Support\Carbon;

    $f = $service->freelancer;
    $meses = [1=>'janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];

    $cpf = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', str_pad((string) $f->cpf, 11, '0', STR_PAD_LEFT));
    $valor = number_format((float) $service->price, 2, ',', '.');
    $inicioBr = $service->start_date ? Carbon::parse($service->start_date)->format('d/m/Y') : '—';
    $fimBr = $service->end_date ? Carbon::parse($service->end_date)->format('d/m/Y') : '—';
    $horaInicio = substr((string) $service->start_time, 0, 5);
    $horaFim = substr((string) $service->end_time, 0, 5);

    // Data do contrato: a assinatura do freelancer, ou hoje se ainda não assinado.
    $dataRef = $service->freelancer_signed_at ?? now();
    $dataExtenso = $dataRef->day . ' de ' . $meses[(int) $dataRef->month] . ' de ' . $dataRef->year;

    // As imagens vêm por rota autenticada, não pelo disco público.
    $signatureUrl = $service->freelancer_signature_path
        ? route('freelancer-services.signature', ['freelancerService' => $service->id, 'party' => 'freelancer'])
        : null;

    // O coordenador assina desenhando no tablet, então há traço. A marca
    // eletrônica abaixo só aparece em contratos antigos, assinados pelo painel
    // antes de essa opção ser retirada.
    $coordinatorSignatureUrl = $service->coordinator_signature_path
        ? route('freelancer-services.signature', ['freelancerService' => $service->id, 'party' => 'coordinator'])
        : null;
@endphp

<div class="doc">
    {{-- Cabeçalho no thead e rodapé no tfoot: o navegador repete os dois em
         todas as páginas na impressão. O conteúdo fica no tbody. --}}
    <table class="doc-table">
    <thead><tr><td>
        <div class="doc-header-img">
            <img src="{{ asset('images/freelancer/cabecalho.png') }}" alt="Clube dos Funcionários">
        </div>
    </td></tr></thead>
    <tfoot><tr><td>
        <div class="doc-footer-img">
            <img src="{{ asset('images/freelancer/rodape.png') }}" alt="Endereços e contatos do Clube dos Funcionários">
        </div>
    </td></tr></tfoot>
    <tbody><tr><td>

    <div class="doc-title">Contrato Autônomo de Serviços de Freelancer</div>

    <div class="doc-body">
        <p>Por este particular instrumento contratual de serviço autônomo de freelancer, firmado entre as partes, de um lado,
            <b>CLUBE DOS FUNCIONARIOS DA COMPANHIA SIDERURGICA NACIONAL</b>, empresa estabelecida na Rua - General Oswaldo
            Pinto da Veiga, 231, Volta Redonda – RJ, a seguir denominada simplesmente CONTRATANTE, e, de outro lado
            <b>{{ $f->name }}</b>, {{ $f->nacionality ?: '—' }}, {{ $f->civil_status ?: '—' }}, titular do CPF: {{ $cpf }}
            e do RG nº {{ $f->rg ?: '—' }}, residente e domiciliado {{ $f->address ?: '—' }} a seguir denominado
            simplesmente FREELANCER, fica justo e acordado o contrato de serviço autônomo freelancer nos seguintes termos:</p>

        <p><b>1- DO OBJETO:</b> O objeto do presente contrato trata-se da prestação de serviços, na modalidade de trabalho
            autônomo, sem vínculo de emprego, pelo FREELANCER, ao CONTRATANTE, conforme artigo 442-B, da CLT. O (a)
            FREELANCER (a) <b>{{ $service->functionFreelancer->name ?? '—' }}</b> com todas as atribuições que lhe são
            peculiares, bem como as que vierem a ser designadas por meio de instruções do CONTRATANTE.</p>

        <p><b>2- DO VALOR:</b> O CONTRATANTE paga, neste ato, ao FREELANCER, pelos serviços ora prestados, o valor de
            <b>R$ {{ $valor }}</b>, por dia, previamente acordado, no horário de <b>{{ $horaInicio }}</b> ás
            <b>{{ $horaFim }}</b> servindo a assinatura no presente termo, como recibo do pagamento.</p>

        <p><b>3- DO PRAZO DE VIGÊNCIA:</b> O presente contrato de serviços de freelancer tem a validade de 1 (Um) dia, no
            qual, ao final, o serviço do FREELANCER já deverá ter se concluído, ficando as partes compromissadas até o
            termino do contrato. O prazo terá início na data de <b>{{ $inicioBr }}</b> sendo regido por tempo determinado,
            finalizando na data de <b>{{ $fimBr }}</b>.</p>

        <p><b>4- Da Ausência de Vínculo Empregatício:</b> A prestação de serviços estabelecida no presente contrato tem
            natureza autônoma (cível), de forma que não implica em qualquer vínculo empregatício do FREELANCER pelos
            serviços prestados ao CONTRATANTE, uma vez que eventuais e sem a subordinação, exigidos para caracterização do
            vínculo de emprego (artigo 3º da CLT).</p>

        <p><b>5- DOS DESCONTOS:</b> O CONTRATANTE poderá descontar dos haveres do FREELANCER, além dos descontos legais ou
            expressamente autorizados, os prejuízos por ele causados, por dolo ou culpa, sem prejuízo da penalidade que a
            ação ou omissão comportar.</p>

        <p><b>6-</b> O FREELANCER deve se portar de forma adequada quando da prestação dos serviços, respeitando as
            orientações quanto ao uso do celular no horário de prestação dos serviços, atrasos, indisciplinas, devendo
            respeitar o contido nos seus regimentos internos e ao senso comum de educação e urbanidade.</p>

        <p><b>7-</b> Em caso de o FREELANCER exercer o serviço contratado por período superior a 6 (Seis) horas diárias, o
            CONTRATANTE, por livre e espontânea vontade, fornecerá ao FREELANCER uma refeição diária, sem que haja desconto
            do valor previsto na cláusula 2.</p>

        <p><b>8- DO FORO DE ELEIÇÃO:</b> As partes elegem o foro de Volta Redonda, como único competente para dirimir
            quaisquer litígios oriundos do presente contrato.</p>

        <p>E assim por estarem de pleno acordo com o contido neste instrumento, CONTRATANTE e FREELANCER o firmam consoante
            os ditames legais.</p>

        <p class="doc-place"><b>Volta Redonda-RJ, {{ $dataExtenso }}</b></p>

        <div class="doc-signatures">
            <div class="doc-sign-block">
                @if($coordinatorSignatureUrl)
                    <div class="doc-sign-img"><img src="{{ $coordinatorSignatureUrl }}" alt="Assinatura do coordenador"></div>
                @elseif($service->coordinator_signed_at)
                    {{-- Legado: contratos assinados pelo painel antes de a assinatura passar a ser só no tablet. --}}
                    <div class="doc-sign-mark">✓ Assinado eletronicamente{{ $service->coordinatorSignedBy ? ' por ' . $service->coordinatorSignedBy->name : '' }} em {{ $service->coordinator_signed_at->format('d/m/Y H:i') }}</div>
                @else
                    <div class="doc-sign-empty"></div>
                @endif
                <div class="doc-sign-line"></div>
                <div class="doc-sign-name">CLUBE DOS FUNCIONARIOS DA CSN</div>
                <div class="doc-sign-role">CONTRATANTE</div>
                @if($coordinatorSignatureUrl && $service->coordinator_signed_at)
                    <div class="doc-sign-note">Assinado em {{ $service->coordinator_signed_at->format('d/m/Y H:i') }}{{ $service->coordinatorSignedBy ? ' · ' . $service->coordinatorSignedBy->name : '' }}</div>
                @endif
            </div>

            <div class="doc-sign-block">
                @if($signatureUrl)
                    <div class="doc-sign-img"><img src="{{ $signatureUrl }}" alt="Assinatura do freelancer"></div>
                @else
                    <div class="doc-sign-empty"></div>
                @endif
                <div class="doc-sign-line"></div>
                <div class="doc-sign-name">{{ $f->name }}</div>
                <div class="doc-sign-role">FREELANCER · CPF {{ $cpf }}</div>
                @if($service->freelancer_signed_at)
                    <div class="doc-sign-note">Assinado em {{ $service->freelancer_signed_at->format('d/m/Y H:i') }}{{ $service->freelancerSignedBy ? ' · atendimento por ' . $service->freelancerSignedBy->name : '' }}</div>
                @endif
            </div>
        </div>
    </div>

    </td></tr></tbody>
    </table>
</div>
