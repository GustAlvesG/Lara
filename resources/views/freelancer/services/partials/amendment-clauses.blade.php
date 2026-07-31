@php
    /**
     * Corpo do TERMO ADITIVO. Recebe $service (o aditivo), $f (freelancer) e
     * $cpf já formatado, e cita o contrato que ele altera ($base).
     *
     * O texto parte de uma premissa do desenho: o aditivo SUBSTITUI o contrato
     * base, não se soma a ele — por isso a cláusula do valor diz, com todas as
     * letras, que o valor aqui ajustado é o único devido. Mantido em sincronia
     * com o amendmentClauses() do Kiosk (resources/views/kiosk/index.blade.php).
     */
    use Illuminate\Support\Carbon;

    $base = $service->baseService;

    $valor = number_format((float) $service->price, 2, ',', '.');
    $valorBase = $base ? number_format((float) $base->price, 2, ',', '.') : '—';

    $inicioBr = $service->start_date ? Carbon::parse($service->start_date)->format('d/m/Y') : '—';
    $fimBr = $service->end_date ? Carbon::parse($service->end_date)->format('d/m/Y') : '—';
    $horaInicio = substr((string) $service->start_time, 0, 5);
    $horaFim = substr((string) $service->end_time, 0, 5);

    $inicioBaseBr = $base && $base->start_date ? Carbon::parse($base->start_date)->format('d/m/Y') : '—';
    $fimBaseBr = $base && $base->end_date ? Carbon::parse($base->end_date)->format('d/m/Y') : '—';
    $horaInicioBase = $base ? substr((string) $base->start_time, 0, 5) : '—';
    $horaFimBase = $base ? substr((string) $base->end_time, 0, 5) : '—';

    // "celebrado em 22/07/2026" — a data em que o freelancer assinou o base é o
    // ato que o fechou. Sem assinatura registrada, cita-se só a data do turno.
    $celebradoEm = $base?->freelancer_signed_at?->format('d/m/Y');

    $ordem = $service->amendmentOrder();
    $referencia = $ordem > 1
        ? 'CONTRATO ORIGINAL, já alterado por termo(s) aditivo(s) anterior(es),'
        : 'CONTRATO ORIGINAL';

    // Acréscimo, redução ou mesma duração — o que motivou o aditivo.
    $mudancaPeriodo = null;

    if ($base) {
        $delta = $service->durationInMinutes() - $base->durationInMinutes();
        $absoluto = abs($delta);
        $horas = intdiv($absoluto, 60);
        $minutos = $absoluto % 60;
        $extenso = $minutos === 0 ? $horas . 'h' : sprintf('%dh%02d', $horas, $minutos);

        $mudancaPeriodo = match (true) {
            $delta > 0 => 'com acréscimo de ' . $extenso . ' em relação ao originalmente ajustado',
            $delta < 0 => 'com redução de ' . $extenso . ' em relação ao originalmente ajustado',
            default => 'mantida a duração originalmente ajustada',
        };
    }

    $mudouLocal = $base && $base->location !== $service->location;
@endphp

<p>Por este particular instrumento, firmado entre as partes, de um lado,
    <b>CLUBE DOS FUNCIONARIOS DA COMPANHIA SIDERURGICA NACIONAL</b>, empresa estabelecida na Rua - General Oswaldo
    Pinto da Veiga, 231, Volta Redonda – RJ, a seguir denominada simplesmente CONTRATANTE, e, de outro lado
    <b>{{ $f->name }}</b>, {{ $f->nacionality ?: '—' }}, {{ $f->civil_status ?: '—' }}, titular do CPF: {{ $cpf }}
    e do RG nº {{ $f->rg ?: '—' }}, residente e domiciliado {{ $f->address ?: '—' }}, a seguir denominado
    simplesmente FREELANCER, fica justo e acordado o presente <b>TERMO ADITIVO</b> ao Contrato Autônomo de Serviços
    de Freelancer celebrado entre as partes{{ $celebradoEm ? ' em ' . $celebradoEm : '' }}, para a prestação de
    serviços na função de <b>{{ $service->functionFreelancer->name ?? '—' }}</b> no dia {{ $inicioBaseBr }}, a
    seguir denominado simplesmente CONTRATO ORIGINAL, nos seguintes termos:</p>

<p><b>1- DO OBJETO DO ADITAMENTO:</b> O presente termo tem por objeto, exclusivamente, alterar o horário e o local
    da prestação dos serviços ajustados no {{ $referencia }} em razão de alteração superveniente na necessidade do
    CONTRATANTE, permanecendo a prestação vinculada à mesma função e ao mesmo dia ali previstos.</p>

<p><b>2- DA ALTERAÇÃO DO PERÍODO:</b> O período de prestação dos serviços, originalmente ajustado no horário de
    <b>{{ $horaInicioBase }}</b> às <b>{{ $horaFimBase }}</b>, com início em {{ $inicioBaseBr }} e término em
    {{ $fimBaseBr }}, passa a vigorar no horário de <b>{{ $horaInicio }}</b> às <b>{{ $horaFim }}</b>, com início
    em <b>{{ $inicioBr }}</b> e término em <b>{{ $fimBr }}</b>, perfazendo
    <b>{{ $service->formattedDuration() }}</b> de prestação de serviços{{ $mudancaPeriodo ? ', ' . $mudancaPeriodo : '' }}.</p>

<p><b>3- DO LOCAL DA PRESTAÇÃO:</b>
    @if($mudouLocal)
        O local da prestação dos serviços, originalmente {{ $base->location }}, passa a ser
        <b>{{ $service->location }}</b>.
    @else
        Permanece inalterado o local da prestação dos serviços, <b>{{ $service->location }}</b>.
    @endif
</p>

<p><b>4- DO VALOR:</b> Em razão da alteração do período, o valor devido pelos serviços passa a ser de
    <b>R$ {{ $valor }}</b>, apurado na forma da cláusula 2 do CONTRATO ORIGINAL, em substituição integral ao valor
    de R$ {{ $valorBase }} ali previsto. O valor ora ajustado <b>não se soma</b> ao do CONTRATO ORIGINAL, sendo o
    único devido pela prestação de serviços aqui tratada, e a assinatura do presente termo serve como recibo do
    pagamento.</p>

<p><b>5- DA RATIFICAÇÃO:</b> Permanecem inalteradas e em pleno vigor todas as demais cláusulas e condições do
    CONTRATO ORIGINAL que não conflitem com o presente termo, em especial a natureza autônoma da prestação e a
    ausência de vínculo empregatício, nos termos dos artigos 442-B e 3º da CLT, as disposições sobre descontos, os
    deveres de conduta do FREELANCER, o fornecimento de refeição na prestação superior a 6 (seis) horas diárias e o
    foro de eleição de Volta Redonda.</p>

<p><b>6- DA VIGÊNCIA:</b> O presente termo aditivo integra o CONTRATO ORIGINAL para todos os fins de direito e
    produz efeitos a partir da sua assinatura, mantida a validade de 1 (um) dia do contrato aditado, ao final do
    qual o serviço do FREELANCER já deverá ter se concluído.</p>

<p>E assim por estarem de pleno acordo com o contido neste instrumento, CONTRATANTE e FREELANCER o firmam consoante
    os ditames legais.</p>
