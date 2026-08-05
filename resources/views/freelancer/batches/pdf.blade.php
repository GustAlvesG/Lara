@php
    /**
     * Relação do lote, anexada ao e-mail da diretoria. Sem cores de fundo
     * pesadas e sem fontes externas — dompdf renderiza melhor assim.
     */
    use Illuminate\Support\Carbon;

    $comissoes = $services->filter->isCommissionAmendment();
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Lote #{{ $batch->id }}</title>
    <style>
        @page { margin: 26px 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1f1819; }
        .head { border-bottom: 2px solid #A00001; padding-bottom: 10px; margin-bottom: 16px; }
        .head .club { font-size: 16px; font-weight: bold; color: #A00001; }
        .head .doc { font-size: 12px; margin-top: 3px; }
        .meta { margin-bottom: 14px; font-size: 10px; color: #555; line-height: 1.6; }
        .meta b { color: #1f1819; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th { background: #f2ecea; border-bottom: 1px solid #d8cbc9; padding: 6px 7px;
                         text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: .4px; color: #6d6062; }
        table.items td { border-bottom: 1px solid #eee4e3; padding: 6px 7px; vertical-align: top; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tr.total td { border-top: 2px solid #A00001; border-bottom: none; padding-top: 9px;
                      font-weight: bold; font-size: 12px; }
        tr.total td.num { color: #A00001; font-size: 14px; }
        .foot { margin-top: 26px; border-top: 1px solid #d8cbc9; padding-top: 10px;
                font-size: 8.5px; color: #777; line-height: 1.5; }
        /* Comissão de venda: mesma pessoa, mesmo dia, outro valor — a relação
           precisa dizer que soma ao contrato do turno, não que o repete. */
        .tag { display: inline-block; padding: 0 4px; border-radius: 6px; font-size: 8px;
               font-weight: bold; color: #fff; }
        .tag-com { background: #157a58; }
        .tag-adit { background: #4f46e5; }
        .kind-note { display: block; margin-top: 2px; font-size: 8.5px; line-height: 1.35; }
        .kind-com { color: #157a58; }
        .kind-adit { color: #4f46e5; }
        .callout { border-left: 3px solid #157a58; background: #eef8f3; padding: 8px 10px;
                   margin-bottom: 14px; font-size: 9.5px; line-height: 1.5; color: #14503c; }
        .sign { margin-top: 34px; }
        .sign .line { border-top: 1px solid #1f1819; width: 260px; margin-top: 44px; padding-top: 5px;
                      font-size: 9.5px; text-align: center; }
    </style>
</head>
<body>

<div class="head">
    <div class="club">Clube dos Funcionários da CSN</div>
    <div class="doc">Relação de contratos de freelancer para aprovação da diretoria</div>
</div>

<div class="meta">
    <b>Lote:</b> #{{ $batch->id }} &nbsp;·&nbsp;
    <b>Contratos:</b> {{ $services->count() }} &nbsp;·&nbsp;
    <b>Total:</b> R$ {{ number_format($total, 2, ',', '.') }}<br>
    <b>Montado por:</b> {{ $batch->createdBy->name ?? '—' }}
    @if($batch->sent_at) em {{ $batch->sent_at->format('d/m/Y H:i') }} @endif<br>
    <b>Conferido pela gerência:</b> {{ $batch->reviewedBy->name ?? '—' }}
    @if($batch->reviewed_at) em {{ $batch->reviewed_at->format('d/m/Y H:i') }} @endif<br>
    <b>Emitido em:</b> {{ now()->format('d/m/Y H:i') }}
</div>

@if($comissoes->isNotEmpty())
    <div class="callout">
        <b>{{ $comissoes->count() }} {{ $comissoes->count() === 1 ? 'documento desta relação é um termo' : 'documentos desta relação são termos' }}
        de comissão de venda</b>, somando R$ {{ number_format($comissoes->sum('price'), 2, ',', '.') }}.
        A comissão remunera as vendas do garçom no turno e é paga <b>além</b> do contrato do mesmo dia,
        por isso o mesmo freelancer aparece em duas linhas com valores diferentes. Não são lançamentos repetidos.
    </div>
@endif

<table class="items">
    <thead>
        <tr>
            <th style="width:6%">Nº</th>
            <th style="width:21%">Freelancer</th>
            <th style="width:10%">CPF</th>
            <th style="width:13%">Função</th>
            <th style="width:24%">Local / evento</th>
            <th style="width:17%">Período</th>
            <th class="num" style="width:9%">Valor</th>
        </tr>
    </thead>
    <tbody>
        @foreach($services as $service)
            @php $nota = $service->kindNote(); @endphp
            <tr>
                <td>#{{ $service->id }}</td>
                <td>
                    {{ $service->freelancer->name ?? '—' }}
                    @if($service->kindLabel())
                        <br><span class="tag {{ $service->isCommissionAmendment() ? 'tag-com' : 'tag-adit' }}">{{ $service->kindLabel() }}</span>
                        <span class="kind-note {{ $service->isCommissionAmendment() ? 'kind-com' : 'kind-adit' }}">{{ $nota }}</span>
                    @endif
                </td>
                <td>{{ preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', str_pad((string) ($service->freelancer->cpf ?? ''), 11, '0', STR_PAD_LEFT)) }}</td>
                <td>{{ $service->functionFreelancer->name ?? '—' }}</td>
                <td>{{ $service->location }}@if(filled($service->description))<br><span style="color:#777;font-size:9px;">{{ $service->description }}</span>@endif</td>
                {{-- Na comissão a duração é do turno, não do que se paga aqui. --}}
                <td>{{ $service->formattedPeriod() }}@unless($service->isCommissionAmendment())<br><span style="color:#777">{{ $service->formattedDuration() }}</span>@endunless</td>
                <td class="num">R$ {{ number_format($service->price, 2, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td colspan="6">Total do lote</td>
            <td class="num">R$ {{ number_format($total, 2, ',', '.') }}</td>
        </tr>
    </tbody>
</table>

<div class="sign">
    <div class="line">{{ config('freelancers.director.name') }} — Diretoria</div>
</div>

<div class="foot">
    Todos os contratos desta relação estão assinados pelo freelancer e pelo coordenador, e foram
    conferidos individualmente pela gerência. O número da coluna "Nº" é o do documento no sistema.
    A aprovação da diretoria é registrada no sistema pelo
    código informado no e-mail que acompanha esta relação.<br>
    Clube dos Funcionários da CSN · Rua General Oswaldo Pinto da Veiga, 231 — Volta Redonda/RJ
</div>

</body>
</html>
