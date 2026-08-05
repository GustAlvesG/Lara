@php
    /**
     * ANEXO I do termo de comissão — o relatório de fechamento que apurou as
     * vendas, impresso dentro do próprio documento.
     *
     * Vem do que foi GRAVADO na comissão, não de uma consulta nova: o
     * MultiVendas segue mudando, e o anexo tem de continuar mostrando o que as
     * partes assinaram. Mantido em sincronia com salesAnnex() do Kiosk.
     */
    $rep = $service->sales_report;
    $sections = $service->salesReportSections();

    $num = fn($v, $dec = 2) => $v === null ? '' : number_format((float) $v, $dec, ',', '.');
@endphp

@if($sections)
<div class="doc-annex">
    <div class="doc-annex-title">ANEXO I — Relatório de vendas do período</div>
    <p class="doc-annex-meta">
        Vendedor: <b>{{ $rep['login'] ?? '—' }}</b>
        · Período: {{ $rep['period']['start'] ?? '—' }} a {{ $rep['period']['end'] ?? '—' }}
        @if(!empty($rep['generated_at'])) · Apurado em {{ $rep['generated_at'] }} @endif
        no sistema MultiVendas.
    </p>

    <table class="annex">
        <thead>
            <tr>
                <th>Descrição</th>
                <th class="num">Qtde</th>
                <th class="num">Unit.</th>
                <th class="num">Valor</th>
            </tr>
        </thead>
        <tbody>
            @foreach(['CABEÇALHO', 'ITENS', 'RECEBIMENTOS', 'TOTAIS', 'CANCELAMENTOS'] as $secao)
                @if(!empty($sections[$secao]))
                    <tr><td colspan="4" class="annex-sec">{{ $secao }}</td></tr>
                    @foreach($sections[$secao] as $linha)
                        <tr>
                            <td>{{ $linha['descricao'] }}</td>
                            <td class="num">{{ $num($linha['qtde'] ?? null, 3) }}{{ !empty($linha['un']) ? ' ' . $linha['un'] : '' }}</td>
                            <td class="num">{{ $num($linha['valor_unit'] ?? null) }}</td>
                            <td class="num">{{ $num($linha['valor'] ?? null) }}</td>
                        </tr>
                    @endforeach
                @endif
            @endforeach
        </tbody>
    </table>
</div>
@endif
