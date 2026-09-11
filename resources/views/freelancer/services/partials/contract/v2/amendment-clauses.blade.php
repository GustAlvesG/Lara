@php
    /**
     * Corpo do TERMO ADITIVO. Recebe $service (o aditivo), $party (a
     * qualificação do freelancer como o documento a cita) e $cpf já formatado, e
     * cita o contrato que ele altera ($base).
     *
     * O texto parte de uma premissa do desenho: o aditivo SUBSTITUI o contrato
     * base, não se soma a ele — por isso a cláusula do valor diz, com todas as
     * letras, que o valor aqui ajustado é o único devido.
     *
     * Como o contrato original, o termo ajusta DIA e VALOR: horário e duração não
     * entram no texto (seguem no registro interno).
     *
     * REDAÇÃO 2 — mesmo texto da redação 1; o que mudou foi quem assina pelo
     * CONTRATANTE (o diretor, na aprovação do lote). Assim que entrar em uso,
     * fica congelada como a 1: para revisar, copie a pasta para `v3` e edite
     * lá; alterar este arquivo mudaria termos já assinados.
     */
    use Illuminate\Support\Carbon;

    $base = $service->baseService;

    $valor = number_format((float) $service->price, 2, ',', '.');
    $valorBase = $base ? number_format((float) $base->price, 2, ',', '.') : '—';

    $inicioBr = $service->start_date ? Carbon::parse($service->start_date)->format('d/m/Y') : '—';
    $fimBr = $service->end_date ? Carbon::parse($service->end_date)->format('d/m/Y') : '—';

    $inicioBaseBr = $base && $base->start_date ? Carbon::parse($base->start_date)->format('d/m/Y') : '—';
    $fimBaseBr = $base && $base->end_date ? Carbon::parse($base->end_date)->format('d/m/Y') : '—';

    // "celebrado em 22/07/2026" — a data em que o freelancer assinou o base é o
    // ato que o fechou. Sem assinatura registrada, cita-se só a data do turno.
    $celebradoEm = $base?->freelancer_signed_at?->format('d/m/Y');

    $ordem = $service->amendmentOrder();
    $referencia = $ordem > 1
        ? 'CONTRATO ORIGINAL, já alterado por termo(s) aditivo(s) anterior(es),'
        : 'CONTRATO ORIGINAL';

    // Sem horário e sem duração no texto: como o contrato original, o termo
    // ajusta DIA e VALOR. O que mudou no turno continua registrado internamente
    // (start_time/end_time/total_hours) e aparece nas telas de operação — o que
    // este documento precisa dizer é que o valor mudou e passa a ser o único
    // devido. A comparação de valores é que conta a história: R$ X em
    // substituição a R$ Y.
    $valorMudou = $base && abs((float) $base->price - (float) $service->price) >= 0.01;

    // Sem os horários no texto, as datas só merecem ser repetidas quando de fato
    // mudaram — o que acontece no turno que passa (ou deixa de passar) da
    // meia-noite. Fora disso as duas metades da frase sairiam idênticas.
    $datasMudaram = $inicioBaseBr !== $inicioBr || $fimBaseBr !== $fimBr;

    $mudouLocal = $base && $base->location !== $service->location;
@endphp

<p>Por este particular instrumento, firmado entre as partes, de um lado,
    <b>CLUBE DOS FUNCIONARIOS DA COMPANHIA SIDERURGICA NACIONAL</b>, empresa estabelecida na Rua - General Oswaldo
    Pinto da Veiga, 231, Volta Redonda – RJ, a seguir denominada simplesmente CONTRATANTE, e, de outro lado
    <b>{{ $party['name'] }}</b>, {{ $party['nacionality'] ?: '—' }}, {{ $party['civil_status'] ?: '—' }}, titular do
    CPF: {{ $cpf }} e do RG nº {{ $party['rg'] ?: '—' }}, residente e domiciliado {{ $party['address'] ?: '—' }}, a
    seguir denominado simplesmente FREELANCER, fica justo e acordado o presente <b>TERMO ADITIVO</b> ao Contrato
    Autônomo de Serviços de Freelancer celebrado entre as partes{{ $celebradoEm ? ' em ' . $celebradoEm : '' }},
    para a prestação de serviços na função de <b>{{ $service->contractFunctionName() ?: '—' }}</b> no dia
    {{ $inicioBaseBr }}, a seguir denominado simplesmente CONTRATO ORIGINAL, nos seguintes termos:</p>

<p><b>1- DO OBJETO DO ADITAMENTO:</b> O presente termo tem por objeto, exclusivamente, alterar o período e o local
    da prestação dos serviços ajustados no {{ $referencia }} em razão de alteração superveniente na necessidade do
    CONTRATANTE, permanecendo a prestação vinculada à mesma função e ao mesmo dia ali previstos.</p>

<p><b>2- DA ALTERAÇÃO DO PERÍODO:</b>
    @if($datasMudaram)
        O período de prestação dos serviços ajustado no {{ $referencia }}, com início em {{ $inicioBaseBr }} e
        término em {{ $fimBaseBr }}, passa a ter início em <b>{{ $inicioBr }}</b> e término em <b>{{ $fimBr }}</b>.
    @else
        O período de prestação dos serviços ajustado no {{ $referencia }} para o dia <b>{{ $inicioBr }}</b> foi
        alterado, mantido o mesmo dia de trabalho.
    @endif
    A alteração fica registrada nos controles do CONTRATANTE e repercute exclusivamente no valor ajustado na
    cláusula 4.</p>

<p><b>3- DO LOCAL DA PRESTAÇÃO:</b>
    @if($mudouLocal)
        O local da prestação dos serviços, originalmente {{ $base->location }}, passa a ser
        <b>{{ $service->location }}</b>.
    @else
        Permanece inalterado o local da prestação dos serviços, <b>{{ $service->location }}</b>.
    @endif
</p>

<p><b>4- DO VALOR:</b>
    @if($valorMudou)
        Em razão da alteração do período, o valor devido pelos serviços passa a ser de <b>R$ {{ $valor }}</b>,
        apurado na forma da cláusula 2 do CONTRATO ORIGINAL, em substituição integral ao valor de
        R$ {{ $valorBase }} ali previsto.
    @else
        O valor devido pelos serviços permanece o de <b>R$ {{ $valor }}</b>, apurado na forma da cláusula 2 do
        CONTRATO ORIGINAL, sem acréscimo nem redução em razão da alteração ora ajustada.
    @endif
    O valor ora ajustado <b>não se soma</b> ao do CONTRATO ORIGINAL, sendo o único devido pela prestação de
    serviços aqui tratada, e a assinatura do presente termo serve como recibo do pagamento.</p>

@include('freelancer.services.partials.contract.v2.pix-clause', ['service' => $service, 'numero' => '4.1'])

<p><b>5- DA RATIFICAÇÃO:</b> Permanecem inalteradas e em pleno vigor todas as demais cláusulas e condições do
    CONTRATO ORIGINAL que não conflitem com o presente termo, em especial a natureza autônoma da prestação e a
    ausência de vínculo empregatício, nos termos dos artigos 442-B e 3º da CLT, as disposições sobre descontos, os
    deveres de conduta do FREELANCER, o fornecimento de refeição previsto na cláusula 7 e o foro de eleição de
    Volta Redonda.</p>

<p><b>6- DA VIGÊNCIA:</b> O presente termo aditivo integra o CONTRATO ORIGINAL para todos os fins de direito e
    produz efeitos a partir da sua assinatura, mantida a validade de 1 (um) dia do contrato aditado, ao final do
    qual o serviço do FREELANCER já deverá ter se concluído.</p>

<p>E assim por estarem de pleno acordo com o contido neste instrumento, CONTRATANTE e FREELANCER o firmam consoante
    os ditames legais.</p>
