@php
    /**
     * Corpo do TERMO ADITIVO DE COMISSÃO SOBRE VENDAS. Recebe $service (a
     * comissão), $f (freelancer) e $cpf já formatado.
     *
     * O oposto do aditivo de horário na cláusula do valor: aqui a comissão
     * ACRESCE ao contrato do turno, e o texto diz isso com todas as letras. Os
     * dois documentos existem lado a lado, e confundi-los é confundir o
     * pagamento. Mantido em sincronia com o commissionClauses() do Kiosk
     * (resources/views/kiosk/index.blade.php).
     */
    use Illuminate\Support\Carbon;
    use App\Models\FreelancerService;

    $base = $service->baseService;

    $valor = number_format((float) $service->price, 2, ',', '.');
    $vendas = number_format((float) $service->sales_amount, 2, ',', '.');

    $horaInicio = substr((string) $service->start_time, 0, 5);
    $horaFim = substr((string) $service->end_time, 0, 5);
    $dia = $base && $base->start_date
        ? Carbon::parse($base->start_date)->format('d/m/Y')
        : ($service->start_date ? Carbon::parse($service->start_date)->format('d/m/Y') : '—');

    // "celebrado em 22/07/2026": a assinatura do freelancer é o ato que fechou
    // o contrato do turno.
    $celebradoEm = $base?->freelancer_signed_at?->format('d/m/Y');

    $criterio = $service->commission_method === 'percent'
        ? $service->commissionMethodLabel() . ', aplicado sobre o total apurado'
        : $service->commissionMethodLabel()
            . ', considerados apenas os blocos de R$ '
            . number_format(FreelancerService::COMMISSION_BLOCK_SALES, 2, ',', '.')
            . ' integralmente atingidos e desprezada a fração inferior';
@endphp

<p>Por este particular instrumento, firmado entre as partes, de um lado,
    <b>CLUBE DOS FUNCIONARIOS DA COMPANHIA SIDERURGICA NACIONAL</b>, empresa estabelecida na Rua - General Oswaldo
    Pinto da Veiga, 231, Volta Redonda – RJ, a seguir denominada simplesmente CONTRATANTE, e, de outro lado
    <b>{{ $f->name }}</b>, {{ $f->nacionality ?: '—' }}, {{ $f->civil_status ?: '—' }}, titular do CPF: {{ $cpf }}
    e do RG nº {{ $f->rg ?: '—' }}, residente e domiciliado {{ $f->address ?: '—' }}, a seguir denominado
    simplesmente FREELANCER, fica justo e acordado o presente <b>TERMO ADITIVO DE COMISSÃO SOBRE VENDAS</b> ao
    Contrato Autônomo de Serviços de Freelancer celebrado entre as partes{{ $celebradoEm ? ' em ' . $celebradoEm : '' }},
    para a prestação de serviços na função de <b>{{ $service->functionFreelancer->name ?? '—' }}</b> no dia
    {{ $dia }}, a seguir denominado simplesmente CONTRATO ORIGINAL, nos seguintes termos:</p>

<p><b>1- DO OBJETO:</b> O presente termo tem por objeto a remuneração variável, a título de comissão, devida ao
    FREELANCER em razão das vendas por ele realizadas durante a prestação de serviços objeto do CONTRATO ORIGINAL,
    sem alteração de qualquer outra condição ali ajustada — em especial a função, o local, o período e o valor da
    prestação.</p>

<p><b>2- DA APURAÇÃO DAS VENDAS:</b> As partes reconhecem como base de cálculo o valor de <b>R$ {{ $vendas }}</b>,
    correspondente ao total das vendas realizadas pelo FREELANCER no período de prestação de serviços do dia
    {{ $dia }}, das <b>{{ $horaInicio }}</b> às <b>{{ $horaFim }}</b>,
    @if($service->hasSalesReport())
        apurado no sistema de vendas do CONTRATANTE sob o login <b>{{ $service->sales_login }}</b> no período de
        {{ $service->salesPeriodLabel() }}, conforme relatório que integra este termo como <b>Anexo I</b>.
        @if($service->salesAmountWasAdjusted())
            O valor acima foi ajustado pelo CONTRATANTE em relação ao total constante do Anexo I
            (R$ {{ number_format((float) ($service->sales_report['base'] ?? 0), 2, ',', '.') }}).
        @endif
    @else
        apurado e informado pelo CONTRATANTE no encerramento do expediente.
    @endif
</p>

<p><b>3- DO CRITÉRIO:</b> A comissão é calculada segundo o critério de {{ $criterio }}, do que resulta a apuração
    de {{ $service->commissionExplanation() }}.</p>

<p><b>4- DO VALOR DA COMISSÃO:</b> Em razão do disposto nas cláusulas anteriores, o CONTRATANTE paga ao FREELANCER,
    a título de comissão sobre vendas, o valor de <b>R$ {{ $valor }}</b>. Este valor <b>acresce</b> ao previsto na
    cláusula 2 do CONTRATO ORIGINAL, não o substituindo, servindo a assinatura do presente termo como recibo do
    pagamento.</p>

<p><b>5- DA NATUREZA DA COMISSÃO:</b> O pagamento ora ajustado decorre exclusivamente do resultado das vendas
    realizadas no período e não descaracteriza a natureza autônoma da prestação de serviços, não implicando vínculo
    empregatício, subordinação ou habitualidade, nos termos dos artigos 442-B e 3º da CLT.</p>

<p><b>6- DA RATIFICAÇÃO:</b> Permanecem inalteradas e em pleno vigor todas as demais cláusulas e condições do
    CONTRATO ORIGINAL que não conflitem com o presente termo, inclusive o foro de eleição de Volta Redonda.</p>

<p>E assim por estarem de pleno acordo com o contido neste instrumento, CONTRATANTE e FREELANCER o firmam consoante
    os ditames legais.</p>

@include('freelancer.services.partials.sales-report-annex', ['service' => $service])
