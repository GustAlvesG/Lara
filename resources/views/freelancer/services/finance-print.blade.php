{{--
    Relação do lote para impressão, usada pelo financeiro na conferência.

    Página independente do layout do painel (sem menu, sem tema escuro): abre em
    aba nova, chama a impressão sozinha e é montada em paisagem, porque são 13
    colunas. Cada linha é autossuficiente — inclusive a aprovação da diretoria,
    que é a mesma do lote inteiro —, para que uma folha solta continue provando
    o que aprovou aquele pagamento.
--}}
@php
    $cpf = fn($valor) => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', str_pad((string) $valor, 11, '0', STR_PAD_LEFT));
    $diretoriaEm = $batch->director_decided_at?->format('d/m/Y H:i');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Lote #{{ $batch->id }} — relação para pagamento</title>
    <style>
        @page { size: A4 landscape; margin: 12mm 8mm; }

        * { box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; font-size: 9px; color: #1f1819;
               margin: 0; padding: 16px; background: #fff; }

        .head { border-bottom: 2px solid #A00001; padding-bottom: 8px; margin-bottom: 12px;
                display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; }
        .head .club { font-size: 15px; font-weight: bold; color: #A00001; }
        .head .doc { font-size: 11px; margin-top: 2px; }
        .head .lote { font-size: 20px; font-weight: bold; text-align: right; white-space: nowrap; }

        /* Resumo geral do lote */
        .resumo { display: flex; flex-wrap: wrap; gap: 0; margin-bottom: 12px;
                  border: 1px solid #d8cbc9; border-radius: 4px; overflow: hidden; }
        .resumo div { flex: 1 1 0; min-width: 120px; padding: 7px 10px; border-right: 1px solid #e8dedd; }
        .resumo div:last-child { border-right: none; }
        .resumo .rot { font-size: 7.5px; text-transform: uppercase; letter-spacing: .5px; color: #6d6062; }
        .resumo .val { font-size: 12px; font-weight: bold; margin-top: 2px; }
        .resumo .val.money { color: #A00001; }
        .resumo .val.pago { color: #1d7a4c; }

        .tramite { font-size: 8.5px; color: #555; line-height: 1.6; margin-bottom: 10px; }
        .tramite b { color: #1f1819; }

        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }   /* repete o cabeçalho a cada folha */
        tr { page-break-inside: avoid; }
        th { background: #f2ecea; border: 1px solid #d8cbc9; padding: 4px 5px; text-align: left;
             font-size: 7.5px; text-transform: uppercase; letter-spacing: .3px; color: #6d6062; }
        td { border: 1px solid #eee4e3; padding: 4px 5px; vertical-align: top; font-size: 8px; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        td.nowrap { white-space: nowrap; }
        td .sub { display: block; color: #777; font-size: 7.5px; margin-top: 1px; }
        tr.total td { border: none; border-top: 2px solid #A00001; padding-top: 7px;
                      font-weight: bold; font-size: 11px; }
        tr.total td.num { color: #A00001; font-size: 13px; }
        .pago-tag { color: #1d7a4c; font-weight: bold; }

        .foot { margin-top: 18px; border-top: 1px solid #d8cbc9; padding-top: 8px;
                font-size: 7.5px; color: #777; line-height: 1.5; }
        .sign { margin-top: 26px; display: flex; gap: 60px; }
        .sign .line { border-top: 1px solid #1f1819; width: 240px; padding-top: 4px;
                      font-size: 8.5px; text-align: center; }

        /* Barra de ação: existe na tela, some no papel. */
        .toolbar { margin-bottom: 14px; }
        .toolbar button, .toolbar a { font: inherit; font-size: 11px; font-weight: bold; cursor: pointer;
                                      padding: 8px 16px; border-radius: 6px; text-decoration: none; }
        .toolbar button { background: #A00001; color: #fff; border: none; }
        .toolbar a { background: #fff; color: #1f1819; border: 1px solid #cfc4c3; margin-left: 8px; }
        @media print { .toolbar { display: none; } body { padding: 0; } }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">Imprimir</button>
    <a href="{{ route('freelancer-services.finance.batch', $batch) }}">Voltar ao lote</a>
</div>

<div class="head">
    <div>
        <div class="club">Clube dos Funcionários da CSN</div>
        <div class="doc">Relação de contratos de freelancer para pagamento</div>
    </div>
    <div class="lote">Lote #{{ $batch->id }}</div>
</div>

{{-- ============ RESUMO GERAL DO LOTE ============ --}}
<div class="resumo">
    <div>
        <div class="rot">Contratos</div>
        <div class="val">{{ $services->count() }}</div>
    </div>
    <div>
        <div class="rot">Freelancers</div>
        <div class="val">{{ $services->pluck('freelancer_id')->unique()->count() }}</div>
    </div>
    <div>
        <div class="rot">Total do lote</div>
        <div class="val money">R$ {{ number_format($total, 2, ',', '.') }}</div>
    </div>
    <div>
        <div class="rot">A pagar</div>
        <div class="val">R$ {{ number_format($pendingTotal, 2, ',', '.') }}</div>
    </div>
    <div>
        <div class="rot">Já pago</div>
        <div class="val pago">R$ {{ number_format($paidTotal, 2, ',', '.') }}</div>
    </div>
    <div>
        <div class="rot">Situação</div>
        <div class="val">{{ $pendingTotal > 0 ? ($paidTotal > 0 ? 'Parcial' : 'A pagar') : 'Quitado' }}</div>
    </div>
    <div>
        <div class="rot">Emitida em</div>
        <div class="val">{{ now()->format('d/m/Y H:i') }}</div>
    </div>
</div>

<div class="tramite">
    <b>Montagem:</b> {{ $batch->createdBy->name ?? '—' }}
    @if($batch->sent_at) · enviado em {{ $batch->sent_at->format('d/m/Y H:i') }} @endif
    &nbsp;|&nbsp;
    <b>Gerência:</b> {{ $batch->reviewedBy->name ?? '—' }}
    @if($batch->reviewed_at) · {{ $batch->reviewed_at->format('d/m/Y H:i') }} @endif
    &nbsp;|&nbsp;
    <b>Diretoria:</b> {{ $batch->director_email ?? '—' }}
    @if($diretoriaEm) · {{ $diretoriaEm }} @endif
    @if($batch->directorDecidedBy) · código registrado por {{ $batch->directorDecidedBy->name }} @endif
    @if(filled($batch->director_note)) · obs.: {{ $batch->director_note }} @endif
</div>

{{-- ============ CONTRATOS ============ --}}
<table>
    <thead>
        <tr>
            <th>Nº</th>
            <th>Freelancer</th>
            <th>Função</th>
            <th>Evento/Local</th>
            <th>CPF</th>
            <th>RG</th>
            <th>Estado civil</th>
            <th>Início e fim</th>
            <th class="num">Valor</th>
            <th>Chave PIX</th>
            <th>Coordenação aprovou</th>
            <th>Gerência aprovou</th>
            <th>Diretoria aprovou</th>
        </tr>
    </thead>
    <tbody>
        @foreach($services as $service)
            @php $f = $service->freelancer; @endphp
            <tr>
                <td class="nowrap">#{{ $service->id }}</td>
                <td>
                    {{ $f->name ?? '—' }}
                    {{-- A comissão repete nome e data do contrato do turno: sem o
                         rótulo, a conferência lê duas linhas como uma duplicidade. --}}
                    @if($service->kindLabel())
                        <span class="sub"><b>{{ $service->kindLabel() }}</b> — {{ $service->kindNote() }}</span>
                    @endif
                    @if($service->isPaid())
                        <span class="sub pago-tag">PAGO {{ $service->paid_at?->format('d/m/Y H:i') }}</span>
                    @endif
                </td>
                <td>{{ $service->functionFreelancer->name ?? '—' }}</td>
                <td>
                    {{ $service->location ?? '—' }}
                    @if(filled($service->description))
                        <span class="sub">{{ $service->description }}</span>
                    @endif
                </td>
                <td class="nowrap">{{ $f?->cpf ? $cpf($f->cpf) : '—' }}</td>
                <td class="nowrap">{{ $f?->rg ?: '—' }}</td>
                <td>{{ $f?->civil_status ?: '—' }}</td>
                <td class="nowrap">
                    {{ $service->startsAt()->format('d/m/Y H:i') }}
                    {{-- Na comissão a duração é do turno, não do que se paga nesta
                         linha: some, para não sugerir cálculo por hora. --}}
                    <span class="sub">até {{ $service->endsAt()->format('d/m/Y H:i') }}@unless($service->isCommissionAmendment()) · {{ $service->formattedDuration() }}@endunless</span>
                </td>
                <td class="num">R$ {{ number_format($service->price, 2, ',', '.') }}</td>
                <td>{{ $f?->pix_key ?: '—' }}</td>
                <td>
                    {{ $service->coordinatorSignedBy->name ?? '—' }}
                    <span class="sub">{{ $service->coordinator_signed_at?->format('d/m/Y H:i') ?? '—' }}</span>
                </td>
                <td>
                    {{ $service->managerApprovedBy->name ?? '—' }}
                    <span class="sub">{{ $service->manager_approved_at?->format('d/m/Y H:i') ?? '—' }}</span>
                </td>
                <td>
                    {{ $batch->director_email ?? '—' }}
                    <span class="sub">{{ $service->director_approved_at?->format('d/m/Y H:i') ?? $diretoriaEm ?? '—' }}</span>
                </td>
            </tr>
        @endforeach

        <tr class="total">
            <td colspan="8">Total do lote #{{ $batch->id }} — {{ $services->count() }} contrato(s)</td>
            <td class="num">R$ {{ number_format($total, 2, ',', '.') }}</td>
            <td colspan="4"></td>
        </tr>
    </tbody>
</table>

<div class="sign">
    <div class="line">Conferido pelo financeiro</div>
    <div class="line">Visto</div>
</div>

<div class="foot">
    Documento gerado pelo sistema em {{ now()->format('d/m/Y \à\s H:i') }} por {{ auth()->user()->name ?? '—' }}.
    Os horários de aprovação são os registrados no sistema no momento de cada decisão.
    A aprovação da diretoria vale para o lote inteiro e foi registrada por código informado pelo diretor.
</div>

<script>
    // Impressão automática ao abrir; a barra continua na tela para reimprimir.
    window.addEventListener('load', () => window.print());
</script>
</body>
</html>
