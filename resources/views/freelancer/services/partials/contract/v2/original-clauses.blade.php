@php
    /**
     * Cláusulas do CONTRATO AUTÔNOMO DE SERVIÇOS DE FREELANCER — modelo do Clube
     * dos Funcionários da CSN. Recebe $service, $party (a qualificação do
     * freelancer como o documento a cita) e $cpf já formatado.
     *
     * REDAÇÃO 2 — mesmo texto da redação 1. O que a redação 2 mudou foi o
     * bloco de assinaturas: pelo CONTRATANTE assina o diretor, digitalmente, na
     * aprovação do lote (ver `contract-document.blade.php`). Contratos
     * assinados apontam para esta pasta pelo `contract_version`: assim que ela
     * entrar em uso, revisar uma cláusula é copiar a pasta para `v3` e editar
     * lá — alterar este arquivo mudaria o texto de contratos já firmados.
     *
     * O texto ajusta DIA e VALOR — o horário do turno não entra no corpo do
     * instrumento (segue gravado e usado no cálculo, na portaria e nos controles
     * internos). A cláusula 2.1, da forma de pagamento, é sub-item do valor.
     */
    use Illuminate\Support\Carbon;

    $valor = number_format((float) $service->price, 2, ',', '.');
    $inicioBr = $service->start_date ? Carbon::parse($service->start_date)->format('d/m/Y') : '—';
    $fimBr = $service->end_date ? Carbon::parse($service->end_date)->format('d/m/Y') : '—';
@endphp

<p>Por este particular instrumento contratual de serviço autônomo de freelancer, firmado entre as partes, de um lado,
    <b>CLUBE DOS FUNCIONARIOS DA COMPANHIA SIDERURGICA NACIONAL</b>, empresa estabelecida na Rua - General Oswaldo
    Pinto da Veiga, 231, Volta Redonda – RJ, a seguir denominada simplesmente CONTRATANTE, e, de outro lado
    <b>{{ $party['name'] }}</b>, {{ $party['nacionality'] ?: '—' }}, {{ $party['civil_status'] ?: '—' }}, titular do
    CPF: {{ $cpf }} e do RG nº {{ $party['rg'] ?: '—' }}, residente e domiciliado {{ $party['address'] ?: '—' }} a
    seguir denominado simplesmente FREELANCER, fica justo e acordado o contrato de serviço autônomo freelancer nos
    seguintes termos:</p>

<p><b>1- DO OBJETO:</b> O objeto do presente contrato trata-se da prestação de serviços, na modalidade de trabalho
    autônomo, sem vínculo de emprego, pelo FREELANCER, ao CONTRATANTE, conforme artigo 442-B, da CLT. O (a)
    FREELANCER (a) <b>{{ $service->contractFunctionName() ?: '—' }}</b> com todas as atribuições que lhe são
    peculiares, bem como as que vierem a ser designadas por meio de instruções do CONTRATANTE.</p>

<p><b>2- DO VALOR:</b> O CONTRATANTE paga, neste ato, ao FREELANCER, pelos serviços ora prestados, o valor de
    <b>R$ {{ $valor }}</b>, por dia, previamente acordado, servindo a assinatura no presente termo, como
    recibo do pagamento.</p>

@include('freelancer.services.partials.contract.v2.pix-clause', ['service' => $service, 'numero' => '2.1'])

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
