<?php

namespace App\Services\Cotacao\Export;

use App\Models\CotacaoMapa;
use App\Models\CotacaoMapaItem;
use App\Services\Cotacao\MapaCalculoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * O XLSX de análise — o irmão do {@see LayoutClassico}.
 *
 * O clássico existe para ser impresso: ele reproduz a planilha que a compra já
 * assina e arquiva, e por isso não pode ganhar coluna nova. Este aqui é o
 * arquivo que se abre no computador para DECIDIR, e carrega o que não cabe no
 * papel:
 *
 *   Mapa      — a grade, com última compra ao lado, menor preço por linha,
 *               economia por item e cobertura por fornecedor.
 *   Resumo    — as duas estratégias lado a lado (comprar dividido × comprar de
 *               um só) e o quadro de cobertura. É a página que responde "o que
 *               eu compro?".
 *   Histórico — item a item contra a última compra.
 *   Decisão   — o que ficou escolhido, por fornecedor, com o total.
 *
 * COMO O CLÁSSICO, TUDO O QUE É TOTAL É FÓRMULA. Corrigir um preço na planilha
 * recalcula subtotal, total, menor da linha, economia, cobertura e as duas
 * estratégias do Resumo. Uma exportação "com valores" quebraria isso em
 * silêncio — quem editasse veria o total não mudar e decidiria pelo número
 * velho.
 *
 * A regra do módulo atravessa o arquivo: **célula vazia nunca vira zero**.
 * `SUMPRODUCT`, `MIN` e `COUNT` ignoram texto e vazio, e é por isso que "NT"
 * entra como texto e "sem resposta" fica em branco.
 */
class LayoutCompleto implements LayoutExportacao
{
    private const FONTE = 'Calibri';

    /** Primeira coluna de fornecedor (F): A..E são as fixas. */
    private const COLUNA_FORNECEDORES = 6;

    /** Linha do cabeçalho da grade. */
    private const LINHA_CABECALHO = 10;

    private const MOEDA = '#,##0.00;[Red]-#,##0.00;"—"';

    private const MOEDA_SIMPLES = 'R$ #,##0.00;[Red]-R$ #,##0.00;"—"';

    private const QTD = '#,##0.###';

    private const PERCENTUAL = '0.0%;[Red]-0.0%;"—"';

    // Paleta: o vermelho da Lara no topo, cinzas neutros na grade e verde só
    // onde ele significa alguma coisa (o menor preço, a economia).
    private const COR_FAIXA = 'FF7F1D1D';

    private const COR_CABECALHO = 'FF991B1B';

    private const COR_ROTULO = 'FFF3F4F6';

    private const COR_ZEBRA = 'FFFAFAFA';

    private const COR_BORDA = 'FFD1D5DB';

    private const COR_MENOR = 'FFD1FAE5';

    private const COR_MENOR_TEXTO = 'FF065F46';

    private const COR_NT = 'FFEFEFEF';

    public function __construct(private readonly MapaCalculoService $calculo)
    {
    }

    public function nome(): string
    {
        return 'Completo (análise e decisão)';
    }

    public function descricao(): string
    {
        return 'Quatro abas para decidir na tela: a grade com última compra e economia por item, '
            . 'o resumo comparando comprar dividido × de um só, o histórico e a decisão registrada.';
    }

    public function montar(CotacaoMapa $mapa): Spreadsheet
    {
        $mapa->loadMissing(['itens.precos', 'fornecedores']);

        $itens = $mapa->itens;
        $fornecedores = $mapa->fornecedores;
        $precos = $itens->flatMap(fn (CotacaoMapaItem $i) => $i->precos);

        $calculado = $this->calculo->calcular($itens, $fornecedores, $precos);

        $planilha = new Spreadsheet;
        $planilha->getDefaultStyle()->getFont()->setName(self::FONTE)->setSize(10);

        $planilha->getProperties()
            ->setTitle($mapa->titulo)
            ->setSubject('Mapa de cotação — SC ' . $mapa->questor_solicitacao)
            ->setCompany('Clube dos Funcionários')
            ->setDescription(
                'Gerado pela Lara a partir da solicitação de compra ' . $mapa->questor_solicitacao
                . '. Os totais são fórmula: editar um preço recalcula o arquivo.'
            );

        $referencias = $this->abaMapa($planilha->getActiveSheet(), $mapa, $itens, $fornecedores, $precos);

        $this->abaResumo($planilha->createSheet(), $mapa, $fornecedores, $calculado, $referencias);
        $this->abaHistorico($planilha->createSheet(), $itens, $calculado);
        $this->abaDecisao($planilha->createSheet(), $itens, $fornecedores, $calculado, $referencias);

        $planilha->setActiveSheetIndex(0);

        return $planilha;
    }

    /**
     * Aba 1 — a grade.
     *
     * Devolve as referências que o Resumo usa para apontar de volta para cá em
     * vez de recalcular: assim os dois nunca discordam.
     *
     * @param  Collection<int, CotacaoMapaItem>  $itens
     * @param  Collection<int, \App\Models\CotacaoMapaFornecedor>  $fornecedores
     * @param  Collection<int, \App\Models\CotacaoPreco>  $precos
     * @return array<string, mixed>
     */
    private function abaMapa(
        Worksheet $aba,
        CotacaoMapa $mapa,
        Collection $itens,
        Collection $fornecedores,
        Collection $precos
    ): array {
        $aba->setTitle('Mapa');

        $qtdItens = $itens->count();
        $qtdFornecedores = $fornecedores->count();

        $primeiraLinha = self::LINHA_CABECALHO + 1;
        $ultimaLinha = $qtdItens > 0 ? $primeiraLinha + $qtdItens - 1 : $primeiraLinha;

        $primeiraColuna = self::COLUNA_FORNECEDORES;
        $ultimaColuna = $primeiraColuna + max($qtdFornecedores, 1) - 1;

        $colIni = Coordinate::stringFromColumnIndex($primeiraColuna);
        $colFim = Coordinate::stringFromColumnIndex($ultimaColuna);

        // Duas colunas de análise DEPOIS dos fornecedores — visíveis, ao
        // contrário da auxiliar escondida do layout clássico: aqui elas são
        // conteúdo, não gambiarra de fórmula.
        $colMenor = Coordinate::stringFromColumnIndex($ultimaColuna + 1);
        $colEconomia = Coordinate::stringFromColumnIndex($ultimaColuna + 2);
        $colFinal = $colEconomia;

        // ---------- Faixa de título ----------

        $aba->setCellValue('A1', 'MAPA DE COTAÇÃO');
        $aba->setCellValue('A2', $mapa->titulo);
        $aba->mergeCells("A1:{$colFinal}1");
        $aba->mergeCells("A2:{$colFinal}2");

        $aba->getStyle('A1')->getFont()->setBold(true)->setSize(16)
            ->getColor()->setARGB('FFFFFFFF');
        $aba->getStyle("A1:{$colFinal}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COR_FAIXA);
        $aba->getStyle('A1')->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);
        $aba->getRowDimension(1)->setRowHeight(30);

        $aba->getStyle('A2')->getFont()->setSize(11)->getColor()->setARGB('FF6B7280');
        $aba->getStyle('A2')->getAlignment()->setIndent(1);
        $aba->getRowDimension(2)->setRowHeight(18);

        // ---------- Identificação ----------

        $identificacao = [
            4 => [['SC', (string) $mapa->questor_solicitacao], ['SOLICITANTE', $mapa->solicitante ?: '—'], ['DEPARTAMENTO', $mapa->departamento ?: '—']],
            5 => [['DATA', ($mapa->data_mapa ?? Carbon::today())->format('d/m/Y')], ['COMPRADOR', $mapa->comprador ?: '—'], ['SITUAÇÃO', $mapa->statusLabel()]],
        ];

        foreach ($identificacao as $linha => $pares) {
            $coluna = 1;

            foreach ($pares as [$rotulo, $valor]) {
                $celRotulo = Coordinate::stringFromColumnIndex($coluna) . $linha;
                $celValor = Coordinate::stringFromColumnIndex($coluna + 1) . $linha;

                $aba->setCellValue($celRotulo, $rotulo);
                $aba->setCellValueExplicit($celValor, $valor, DataType::TYPE_STRING);

                $aba->getStyle($celRotulo)->getFont()->setBold(true)->setSize(9)
                    ->getColor()->setARGB('FF6B7280');
                $aba->getStyle($celRotulo)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COR_ROTULO);
                $aba->getStyle($celRotulo)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $coluna += 2;
            }
        }

        // ---------- Condições por fornecedor (linhas 7 a 9) ----------

        $condicoes = [7 => ['Frete', 'frete'], 8 => ['Prazo de entrega', 'prazo_entrega'], 9 => ['Condição de pagamento', 'condicao_pagamento']];

        foreach ($condicoes as $linha => [$rotulo, $campo]) {
            $aba->setCellValue('D' . $linha, $rotulo);
            $aba->getStyle('D' . $linha)->getFont()->setBold(true)->setSize(9)
                ->getColor()->setARGB('FF6B7280');
            $aba->getStyle('D' . $linha)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $aba->mergeCells("D{$linha}:E{$linha}");

            $coluna = $primeiraColuna;

            foreach ($fornecedores as $fornecedor) {
                $celula = Coordinate::stringFromColumnIndex($coluna) . $linha;
                $aba->setCellValueExplicit($celula, (string) ($fornecedor->{$campo} ?: '—'), DataType::TYPE_STRING);
                $aba->getStyle($celula)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $aba->getStyle($celula)->getFont()->setSize(9);
                $coluna++;
            }
        }

        if ($qtdFornecedores > 0) {
            $aba->getStyle("{$colIni}7:{$colFim}9")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COR_ROTULO);
        }

        // ---------- Cabeçalho da grade ----------

        $fixas = ['A' => 'ITEM', 'B' => 'UND', 'C' => 'DESCRIÇÃO', 'D' => 'QNT.', 'E' => 'ÚLT. COMPRA'];

        foreach ($fixas as $letra => $titulo) {
            $aba->setCellValue($letra . self::LINHA_CABECALHO, $titulo);
        }

        $coluna = $primeiraColuna;

        foreach ($fornecedores as $fornecedor) {
            $aba->setCellValue(
                Coordinate::stringFromColumnIndex($coluna) . self::LINHA_CABECALHO,
                mb_strtoupper($fornecedor->nome)
            );
            $coluna++;
        }

        $aba->setCellValue($colMenor . self::LINHA_CABECALHO, 'MENOR');
        $aba->setCellValue($colEconomia . self::LINHA_CABECALHO, 'ECONOMIA');

        $cabecalho = $aba->getStyle("A" . self::LINHA_CABECALHO . ":{$colFinal}" . self::LINHA_CABECALHO);
        $cabecalho->getFont()->setBold(true)->setSize(9)->getColor()->setARGB('FFFFFFFF');
        $cabecalho->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COR_CABECALHO);
        $cabecalho->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $aba->getRowDimension(self::LINHA_CABECALHO)->setRowHeight(32);

        // ---------- Itens ----------

        $matriz = [];

        foreach ($precos as $preco) {
            $matriz[(int) $preco->cotacao_mapa_item_id][(int) $preco->cotacao_mapa_fornecedor_id] = $preco;
        }

        $linha = $primeiraLinha;

        foreach ($itens as $item) {
            $aba->setCellValue('A' . $linha, $item->questor_cd_item ?? '—');
            $aba->setCellValue('B' . $linha, $item->unidade);
            $aba->setCellValue('C' . $linha, $item->descricao);
            $aba->setCellValue('D' . $linha, (float) $item->quantidade);

            // Última compra: zero quando não há, para as fórmulas de economia
            // não terem de tratar texto. O formato mostra "—".
            $aba->setCellValue('E' . $linha, $item->temUltimaCompra() ? (float) $item->ult_compra_valor : 0);

            if ($item->temUltimaCompra()) {
                $aba->getComment('E' . $linha)->getText()->createTextRun(sprintf(
                    "%s\n%s\nNF %s",
                    $item->ult_compra_data?->format('d/m/Y') ?? '—',
                    $item->ult_compra_fornecedor_nome ?? '—',
                    $item->ult_compra_nf ?: '—'
                ));
            }

            $coluna = $primeiraColuna;

            foreach ($fornecedores as $fornecedor) {
                $celula = Coordinate::stringFromColumnIndex($coluna) . $linha;
                $preco = $matriz[(int) $item->id][(int) $fornecedor->id] ?? null;

                if ($preco !== null && $preco->temPreco()) {
                    $aba->setCellValue($celula, (float) $preco->valor_unitario);

                    if ($preco->marca) {
                        $aba->getComment($celula)->getText()->createTextRun('Marca: ' . $preco->marca);
                    }
                } elseif ($preco !== null && $preco->naoTrabalha()) {
                    // Texto explícito: SUMPRODUCT, MIN e COUNT o ignoram, que é
                    // exatamente o que "não trabalha o item" tem de fazer.
                    $aba->setCellValueExplicit($celula, 'NT', DataType::TYPE_STRING);
                }
                // `sem_resposta` não escreve nada — vazio também sai das contas.

                $coluna++;
            }

            // Menor da linha. Zero quando ninguém cotou (COUNT ignora texto e
            // vazio), e não MIN() de nada, que valeria zero por acidente.
            $aba->setCellValue(
                $colMenor . $linha,
                "=IF(COUNT({$colIni}{$linha}:{$colFim}{$linha})=0,0,MIN({$colIni}{$linha}:{$colFim}{$linha}))"
            );

            // Economia do item só existe com os dois lados: última compra E
            // cotação. Sem um deles, zero — e o formato mostra "—".
            $aba->setCellValue(
                $colEconomia . $linha,
                "=IF(OR(\$E{$linha}=0,{$colMenor}{$linha}=0),0,(\$E{$linha}-{$colMenor}{$linha})*\$D{$linha})"
            );

            $linha++;
        }

        // ---------- Formatos e destaques da grade ----------

        if ($qtdItens > 0) {
            $aba->getStyle("D{$primeiraLinha}:D{$ultimaLinha}")->getNumberFormat()->setFormatCode(self::QTD);
            $aba->getStyle("E{$primeiraLinha}:E{$ultimaLinha}")->getNumberFormat()->setFormatCode(self::MOEDA);
            $aba->getStyle("{$colIni}{$primeiraLinha}:{$colMenor}{$ultimaLinha}")
                ->getNumberFormat()->setFormatCode(self::MOEDA);
            $aba->getStyle("{$colEconomia}{$primeiraLinha}:{$colEconomia}{$ultimaLinha}")
                ->getNumberFormat()->setFormatCode(self::MOEDA);

            $aba->getStyle("C{$primeiraLinha}:C{$ultimaLinha}")->getAlignment()->setWrapText(true);
            $aba->getStyle("A{$primeiraLinha}:A{$ultimaLinha}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $aba->getStyle("B{$primeiraLinha}:B{$ultimaLinha}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $aba->getStyle("{$colMenor}{$primeiraLinha}:{$colMenor}{$ultimaLinha}")
                ->getFont()->setBold(true);

            $this->formatarGrade($aba, $colIni, $colFim, $colMenor, $colEconomia, $primeiraLinha, $ultimaLinha, $colFinal);

            $aba->setAutoFilter('A' . self::LINHA_CABECALHO . ":{$colFinal}{$ultimaLinha}");
        }

        // ---------- Rodapé ----------

        $lFrete = $ultimaLinha + 1;
        $lDesconto = $lFrete + 1;
        $lSubtotal = $lDesconto + 1;
        $lTotal = $lSubtotal + 1;
        $lCobertura = $lTotal + 1;

        $rodape = [
            $lFrete => 'FRETE',
            $lDesconto => 'DESCONTO',
            $lSubtotal => 'SUBTOTAL',
            $lTotal => 'TOTAL',
            $lCobertura => 'ITENS COTADOS',
        ];

        foreach ($rodape as $l => $rotulo) {
            $aba->setCellValue('E' . $l, $rotulo);
            $aba->getStyle("A{$l}:E{$l}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COR_ROTULO);
            $aba->getStyle('E' . $l)->getFont()->setBold(true)->setSize(9);
            $aba->getStyle('E' . $l)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $coluna = $primeiraColuna;

        foreach ($fornecedores as $fornecedor) {
            $letra = Coordinate::stringFromColumnIndex($coluna);

            $aba->setCellValue($letra . $lFrete, (float) $fornecedor->valor_frete);
            $aba->setCellValue($letra . $lDesconto, (float) $fornecedor->desconto);

            if ($qtdItens > 0) {
                $aba->setCellValue(
                    $letra . $lSubtotal,
                    "=SUMPRODUCT(\$D\${$primeiraLinha}:\$D\${$ultimaLinha},{$letra}{$primeiraLinha}:{$letra}{$ultimaLinha})"
                );
                // Cobertura: COUNT conta só número, então "NT" e vazio ficam de
                // fora — que é a distinção do módulo inteira em uma fórmula.
                $aba->setCellValue(
                    $letra . $lCobertura,
                    "=COUNT({$letra}{$primeiraLinha}:{$letra}{$ultimaLinha})"
                );
            } else {
                $aba->setCellValue($letra . $lSubtotal, 0);
                $aba->setCellValue($letra . $lCobertura, 0);
            }

            // Frete e desconto entram no TOTAL, nunca no SUBTOTAL — e só quando
            // houve alguma cotação: quem não respondeu nada não deve frete.
            $aba->setCellValue(
                $letra . $lTotal,
                "=IF({$letra}{$lCobertura}=0,0,{$letra}{$lSubtotal}+{$letra}{$lFrete}-{$letra}{$lDesconto})"
            );

            $coluna++;
        }

        if ($qtdItens > 0 && $qtdFornecedores > 0) {
            $aba->setCellValue(
                $colMenor . $lSubtotal,
                "=SUMPRODUCT(\$D\${$primeiraLinha}:\$D\${$ultimaLinha},{$colMenor}{$primeiraLinha}:{$colMenor}{$ultimaLinha})"
            );
            $aba->setCellValue($colEconomia . $lSubtotal, "=SUM({$colEconomia}{$primeiraLinha}:{$colEconomia}{$ultimaLinha})");
            $aba->getStyle("{$colMenor}{$lSubtotal}:{$colEconomia}{$lSubtotal}")->getFont()->setBold(true);
        }

        $aba->getStyle("{$colIni}{$lFrete}:{$colFinal}{$lTotal}")
            ->getNumberFormat()->setFormatCode(self::MOEDA);
        $aba->getStyle("{$colIni}{$lCobertura}:{$colFim}{$lCobertura}")
            ->getNumberFormat()->setFormatCode('0');
        $aba->getStyle("{$colIni}{$lCobertura}:{$colFim}{$lCobertura}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $totalStyle = $aba->getStyle("A{$lTotal}:{$colFinal}{$lTotal}");
        $totalStyle->getFont()->setBold(true)->setSize(11);
        $totalStyle->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);

        $aba->setCellValue('A' . $lCobertura, "de {$qtdItens} item(ns)");
        $aba->getStyle('A' . $lCobertura)->getFont()->setSize(8)->setItalic(true)
            ->getColor()->setARGB('FF6B7280');
        $aba->mergeCells("A{$lCobertura}:C{$lCobertura}");

        // ---------- Acabamento ----------

        $aba->getColumnDimension('A')->setWidth(8);
        $aba->getColumnDimension('B')->setWidth(7);
        $aba->getColumnDimension('C')->setWidth(52);
        $aba->getColumnDimension('D')->setWidth(8);
        $aba->getColumnDimension('E')->setWidth(13);

        for ($c = $primeiraColuna; $c <= $ultimaColuna + 2; $c++) {
            $aba->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(14);
        }

        // Congela as colunas fixas E o cabeçalho: rolar para a direita continua
        // mostrando qual item é, que é o problema da grade larga.
        $aba->freezePane($colIni . $primeiraLinha);

        $aba->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $aba->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(self::LINHA_CABECALHO, self::LINHA_CABECALHO);
        $aba->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.3)->setRight(0.3);
        $aba->getHeaderFooter()->setOddFooter('&L&9SC ' . $mapa->questor_solicitacao . ' — ' . $mapa->titulo . '&R&9Página &P de &N');

        return [
            'primeiraLinha' => $primeiraLinha,
            'ultimaLinha' => $ultimaLinha,
            'colIni' => $colIni,
            'colFim' => $colFim,
            'colMenor' => $colMenor,
            'colEconomia' => $colEconomia,
            'lSubtotal' => $lSubtotal,
            'lTotal' => $lTotal,
            'lCobertura' => $lCobertura,
            'lFrete' => $lFrete,
            'temGrade' => $qtdItens > 0 && $qtdFornecedores > 0,
        ];
    }

    /**
     * Zebra, bordas e os três destaques condicionais da grade.
     *
     * Tudo por FORMATAÇÃO CONDICIONAL, não cor fixa: editado o preço na
     * planilha, o destaque acompanha. Cor fixa viraria mentira no primeiro
     * ajuste.
     */
    private function formatarGrade(
        Worksheet $aba,
        string $colIni,
        string $colFim,
        string $colMenor,
        string $colEconomia,
        int $primeiraLinha,
        int $ultimaLinha,
        string $colFinal
    ): void {
        $grade = "A{$primeiraLinha}:{$colFinal}{$ultimaLinha}";

        $bordas = $aba->getStyle("A" . self::LINHA_CABECALHO . ":{$colFinal}{$ultimaLinha}")->getBorders();
        $bordas->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setARGB(self::COR_BORDA);

        $condicoes = [];

        // 1. Zebra — legibilidade em grade larga, sem depender de linha fixa.
        $zebra = new Conditional;
        $zebra->setConditionType(Conditional::CONDITION_EXPRESSION);
        $zebra->setConditions(['MOD(ROW(),2)=0']);
        $zebra->getStyle()->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COR_ZEBRA);
        $condicoes[] = $zebra;

        $aba->getStyle($grade)->setConditionalStyles($condicoes);

        // 2. Menor preço da linha, só entre as colunas de fornecedor.
        //    O `<>""` impede que a linha inteira fique verde quando ninguém
        //    cotou: MIN de intervalo vazio dá zero, e vazio "empataria".
        $menor = new Conditional;
        $menor->setConditionType(Conditional::CONDITION_EXPRESSION);
        $menor->setConditions([
            "AND({$colIni}{$primeiraLinha}<>\"\",ISNUMBER({$colIni}{$primeiraLinha}),"
            . "{$colIni}{$primeiraLinha}=MIN(\${$colIni}{$primeiraLinha}:\${$colFim}{$primeiraLinha}))",
        ]);
        $menor->getStyle()->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COR_MENOR);
        $menor->getStyle()->getFont()->setBold(true)->getColor()->setARGB(self::COR_MENOR_TEXTO);

        // 3. "NT" — presente, mas apagado: ele não é uma proposta cara, é uma
        //    não-proposta.
        $nt = new Conditional;
        $nt->setConditionType(Conditional::CONDITION_EXPRESSION);
        $nt->setConditions(["{$colIni}{$primeiraLinha}=\"NT\""]);
        $nt->getStyle()->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COR_NT);
        $nt->getStyle()->getFont()->setItalic(true)->getColor()->setARGB('FF9CA3AF');

        $aba->getStyle("{$colIni}{$primeiraLinha}:{$colFim}{$ultimaLinha}")
            ->setConditionalStyles([$menor, $nt, $zebra]);

        // 4. Economia negativa: a cotação saiu MAIS CARA que a última compra.
        //    É resultado legítimo e precisa saltar aos olhos.
        $pior = new Conditional;
        $pior->setConditionType(Conditional::CONDITION_EXPRESSION);
        $pior->setConditions(["{$colEconomia}{$primeiraLinha}<0"]);
        $pior->getStyle()->getFont()->setBold(true)->getColor()->setARGB('FFB91C1C');

        $melhor = new Conditional;
        $melhor->setConditionType(Conditional::CONDITION_EXPRESSION);
        $melhor->setConditions(["{$colEconomia}{$primeiraLinha}>0"]);
        $melhor->getStyle()->getFont()->getColor()->setARGB(self::COR_MENOR_TEXTO);

        $aba->getStyle("{$colEconomia}{$primeiraLinha}:{$colEconomia}{$ultimaLinha}")
            ->setConditionalStyles([$pior, $melhor, $zebra]);

        // Coluna do menor: sempre destacada, é a resposta da linha.
        $aba->getStyle("{$colMenor}{$primeiraLinha}:{$colMenor}{$ultimaLinha}")
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0FDF4');
    }

    /**
     * Aba 2 — Resumo. A página que responde "o que eu compro?".
     *
     * Os números apontam para a aba Mapa por fórmula, e não são recalculados
     * aqui: se as duas abas fizessem a própria conta, uma edição na grade faria
     * as duas discordarem — e a discordância apareceria tarde.
     *
     * @param  Collection<int, \App\Models\CotacaoMapaFornecedor>  $fornecedores
     * @param  array<string, mixed>  $calculado
     * @param  array<string, mixed>  $ref
     */
    private function abaResumo(
        Worksheet $aba,
        CotacaoMapa $mapa,
        Collection $fornecedores,
        array $calculado,
        array $ref
    ): void {
        $aba->setTitle('Resumo');

        $totais = $calculado['totais'];

        $aba->setCellValue('A1', 'RESUMO DA COTAÇÃO');
        $aba->mergeCells('A1:F1');
        $aba->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
        $aba->getStyle('A1:F1')->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COR_FAIXA);
        $aba->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);
        $aba->getRowDimension(1)->setRowHeight(30);

        $aba->setCellValue('A2', $mapa->titulo . ' · SC ' . $mapa->questor_solicitacao);
        $aba->mergeCells('A2:F2');
        $aba->getStyle('A2')->getFont()->setSize(10)->getColor()->setARGB('FF6B7280');
        $aba->getStyle('A2')->getAlignment()->setIndent(1);

        // ---------- As duas estratégias ----------

        $aba->setCellValue('A4', 'AS DUAS FORMAS DE COMPRAR');
        $this->tituloSecao($aba, 'A4:F4');

        $linhas = [
            5 => [
                'Comprar DIVIDIDO — cada item pelo menor preço',
                $ref['temGrade'] ? "=Mapa!{$ref['colMenor']}{$ref['lSubtotal']}" : 0,
                'Soma o menor preço de cada linha. Costuma perder parte da vantagem no frete, '
                . 'porque envolve mais de um fornecedor.',
            ],
            6 => [
                'Comprar de UM SÓ fornecedor — o melhor total',
                $totais['melhor_fornecedor_unico_total'] ?? 0,
                $totais['melhor_fornecedor_unico_id'] !== null
                    ? 'Fornecedor: ' . ($fornecedores->firstWhere('id', $totais['melhor_fornecedor_unico_id'])?->nome ?? '—')
                        . '. Só entram na disputa os que cotaram o mapa inteiro.'
                    : 'Nenhum fornecedor cotou todos os itens — não há compra concentrada possível hoje.',
            ],
            7 => [
                'Diferença entre as duas',
                ($totais['melhor_fornecedor_unico_total'] !== null && $totais['melhor_combinacao'] !== null)
                    ? $totais['melhor_fornecedor_unico_total'] - $totais['melhor_combinacao']
                    : 0,
                'Quanto se paga a mais concentrando a compra. Compare com o trabalho de '
                . 'lidar com vários fornecedores e com o frete de cada um.',
            ],
        ];

        foreach ($linhas as $l => [$rotulo, $valor, $nota]) {
            $aba->setCellValue('A' . $l, $rotulo);
            $aba->setCellValue('C' . $l, $valor);
            $aba->setCellValue('D' . $l, $nota);
            $aba->mergeCells("A{$l}:B{$l}");
            $aba->mergeCells("D{$l}:F{$l}");
            $aba->getStyle('A' . $l)->getFont()->setBold(true);
            $aba->getStyle('C' . $l)->getFont()->setBold(true)->setSize(12);
            $aba->getStyle('C' . $l)->getNumberFormat()->setFormatCode(self::MOEDA_SIMPLES);
            $aba->getStyle('D' . $l)->getFont()->setSize(9)->getColor()->setARGB('FF6B7280');
            $aba->getStyle('D' . $l)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
            $aba->getRowDimension($l)->setRowHeight(30);
        }

        // ---------- Economia ----------

        $aba->setCellValue('A9', 'CONTRA A ÚLTIMA COMPRA');
        $this->tituloSecao($aba, 'A9:F9');

        $economia = [
            10 => ['Economia projetada', $ref['temGrade'] ? "=Mapa!{$ref['colEconomia']}{$ref['lSubtotal']}" : 0, self::MOEDA_SIMPLES],
            11 => ['Base (o que se pagou da última vez)', $totais['base_ultima_compra'] ?? 0, self::MOEDA_SIMPLES],
            12 => ['Itens comparáveis (com última compra e cotação)', $totais['itens_comparaveis'], '0'],
            13 => ['Itens sem nenhuma cotação', $totais['itens_total'] - $totais['itens_com_preco'], '0'],
        ];

        foreach ($economia as $l => [$rotulo, $valor, $formato]) {
            $aba->setCellValue('A' . $l, $rotulo);
            $aba->setCellValue('C' . $l, $valor);
            $aba->mergeCells("A{$l}:B{$l}");
            $aba->getStyle('C' . $l)->getNumberFormat()->setFormatCode($formato);
            $aba->getStyle('C' . $l)->getFont()->setBold(true);
        }

        $aba->getStyle('C10')->getFont()->setSize(12);

        // Negativo é resultado legítimo: a cotação saiu mais cara.
        $pior = new Conditional;
        $pior->setConditionType(Conditional::CONDITION_EXPRESSION);
        $pior->setConditions(['C10<0']);
        $pior->getStyle()->getFont()->getColor()->setARGB('FFB91C1C');

        $bom = new Conditional;
        $bom->setConditionType(Conditional::CONDITION_EXPRESSION);
        $bom->setConditions(['C10>0']);
        $bom->getStyle()->getFont()->getColor()->setARGB(self::COR_MENOR_TEXTO);

        $aba->getStyle('C10')->setConditionalStyles([$pior, $bom]);

        if ($totais['itens_total'] > $totais['itens_com_preco']) {
            $aba->setCellValue('D13', 'Enquanto houver item sem cotação, os totais acima são PARCIAIS.');
            $aba->mergeCells('D13:F13');
            $aba->getStyle('D13')->getFont()->setBold(true)->setSize(9)->getColor()->setARGB('FFB45309');
        }

        // ---------- Cobertura por fornecedor ----------

        $aba->setCellValue('A15', 'COBERTURA POR FORNECEDOR');
        $this->tituloSecao($aba, 'A15:F15');

        $colunas = ['A' => 'FORNECEDOR', 'B' => 'COTOU', 'C' => 'NT', 'D' => 'SEM RESPOSTA', 'E' => 'TOTAL', 'F' => 'COMPARÁVEL?'];

        foreach ($colunas as $letra => $titulo) {
            $aba->setCellValue($letra . '16', $titulo);
        }

        $cab = $aba->getStyle('A16:F16');
        $cab->getFont()->setBold(true)->setSize(9)->getColor()->setARGB('FFFFFFFF');
        $cab->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COR_CABECALHO);
        $cab->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);

        $linha = 17;
        $coluna = self::COLUNA_FORNECEDORES;

        foreach ($fornecedores as $fornecedor) {
            $coluna_mapa = Coordinate::stringFromColumnIndex($coluna);
            $dados = $calculado['fornecedores'][(int) $fornecedor->id] ?? null;

            $aba->setCellValue('A' . $linha, $fornecedor->nome);
            $aba->setCellValue('B' . $linha, $dados['itens_cotados'] ?? 0);
            $aba->setCellValue('C' . $linha, $dados['itens_nao_trabalha'] ?? 0);
            $aba->setCellValue('D' . $linha, $dados['itens_sem_resposta'] ?? 0);
            $aba->setCellValue('E' . $linha, $ref['temGrade'] ? "=Mapa!{$coluna_mapa}{$ref['lTotal']}" : 0);

            // A distinção que a planilha em Excel não faz: quem não cotou tudo
            // NÃO é comparável no total, por mais barato que pareça.
            $aba->setCellValueExplicit(
                'F' . $linha,
                ($dados['cobertura_completa'] ?? false) ? 'sim' : 'não — cotou só parte',
                DataType::TYPE_STRING
            );

            if (! ($dados['cobertura_completa'] ?? false)) {
                $aba->getStyle('E' . $linha)->getFont()->getColor()->setARGB('FF9CA3AF');
                $aba->getStyle('F' . $linha)->getFont()->setItalic(true)->getColor()->setARGB('FFB45309');
            }

            $linha++;
            $coluna++;
        }

        $ultima = max($linha - 1, 17);

        $aba->getStyle("B17:D{$ultima}")->getNumberFormat()->setFormatCode('0');
        $aba->getStyle("B17:D{$ultima}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $aba->getStyle("E17:E{$ultima}")->getNumberFormat()->setFormatCode(self::MOEDA_SIMPLES);
        $aba->getStyle("A16:F{$ultima}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::COR_BORDA);

        $aba->getColumnDimension('A')->setWidth(38);
        $aba->getColumnDimension('B')->setWidth(9);
        $aba->getColumnDimension('C')->setWidth(7);
        $aba->getColumnDimension('D')->setWidth(15);
        $aba->getColumnDimension('E')->setWidth(17);
        $aba->getColumnDimension('F')->setWidth(22);

        $aba->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT)->setFitToWidth(1)->setFitToHeight(0);
    }

    /**
     * Aba 3 — Histórico. Item a item contra a última compra.
     *
     * Aqui os valores vão calculados, e não como fórmula: a última compra veio
     * do Questor e não está na planilha para o Excel recalcular. É um retrato
     * do momento da exportação, e o cabeçalho diz isso.
     *
     * @param  Collection<int, CotacaoMapaItem>  $itens
     * @param  array<string, mixed>  $calculado
     */
    private function abaHistorico(Worksheet $aba, Collection $itens, array $calculado): void
    {
        $aba->setTitle('Histórico');

        $aba->setCellValue('A1', 'HISTÓRICO — CADA ITEM CONTRA A ÚLTIMA COMPRA');
        $aba->mergeCells('A1:I1');
        $aba->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
        $aba->getStyle('A1:I1')->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COR_FAIXA);
        $aba->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);
        $aba->getRowDimension(1)->setRowHeight(26);

        $aba->setCellValue('A2', 'Retrato do momento da exportação: a última compra vem do Questor e não recalcula nesta planilha.');
        $aba->mergeCells('A2:I2');
        $aba->getStyle('A2')->getFont()->setSize(9)->setItalic(true)->getColor()->setARGB('FF6B7280');
        $aba->getStyle('A2')->getAlignment()->setIndent(1);

        $colunas = [
            'A' => 'ITEM', 'B' => 'DESCRIÇÃO', 'C' => 'QNT.',
            'D' => 'ÚLT. COMPRA', 'E' => 'FORNECEDOR', 'F' => 'NF',
            'G' => 'VL. UNIT.', 'H' => 'MELHOR COTAÇÃO', 'I' => 'VARIAÇÃO %',
        ];

        foreach ($colunas as $letra => $titulo) {
            $aba->setCellValue($letra . '4', $titulo);
        }

        $cab = $aba->getStyle('A4:I4');
        $cab->getFont()->setBold(true)->setSize(9)->getColor()->setARGB('FFFFFFFF');
        $cab->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COR_CABECALHO);
        $cab->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        $aba->getRowDimension(4)->setRowHeight(28);

        $linha = 5;

        foreach ($itens as $item) {
            $calc = $calculado['itens'][(int) $item->id] ?? null;
            $ultima = $calc['ult_compra_valor'] ?? null;
            $melhor = $calc['menor_preco'] ?? null;

            $aba->setCellValue('A' . $linha, $item->questor_cd_item ?? '—');
            $aba->setCellValue('B' . $linha, $item->descricao);
            $aba->setCellValue('C' . $linha, (float) $item->quantidade);

            if (! $item->temCadastroNoQuestor()) {
                // O item de texto livre não tem histórico por código, e dizer
                // isso é melhor que deixar a linha em branco e parecer falha.
                $aba->setCellValue('D' . $linha, 'item sem cadastro — sem histórico');
                $aba->mergeCells("D{$linha}:F{$linha}");
                $aba->getStyle('D' . $linha)->getFont()->setItalic(true)->getColor()->setARGB('FFB45309');
            } elseif ($ultima !== null) {
                $aba->setCellValue('D' . $linha, $item->ult_compra_data?->format('d/m/Y'));
                $aba->setCellValue('E' . $linha, $item->ult_compra_fornecedor_nome);
                $aba->setCellValue('F' . $linha, $item->ult_compra_nf);
                $aba->setCellValue('G' . $linha, $ultima);
            } else {
                $aba->setCellValue('D' . $linha, 'sem compra anterior');
                $aba->mergeCells("D{$linha}:F{$linha}");
                $aba->getStyle('D' . $linha)->getFont()->setItalic(true)->getColor()->setARGB('FF9CA3AF');
            }

            if ($melhor !== null) {
                $aba->setCellValue('H' . $linha, $melhor);
            }

            if ($ultima !== null && $melhor !== null && $ultima > 0) {
                $aba->setCellValue('I' . $linha, ($melhor - $ultima) / $ultima);
            }

            $linha++;
        }

        $ultima = max($linha - 1, 5);

        $aba->getStyle("C5:C{$ultima}")->getNumberFormat()->setFormatCode(self::QTD);
        $aba->getStyle("G5:H{$ultima}")->getNumberFormat()->setFormatCode(self::MOEDA);
        $aba->getStyle("I5:I{$ultima}")->getNumberFormat()->setFormatCode(self::PERCENTUAL);
        $aba->getStyle("B5:B{$ultima}")->getAlignment()->setWrapText(true);
        $aba->getStyle("A4:I{$ultima}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::COR_BORDA);

        // Verde abaixo da última compra, vermelho acima — a leitura que o
        // comprador faz primeiro.
        $melhorou = new Conditional;
        $melhorou->setConditionType(Conditional::CONDITION_EXPRESSION);
        $melhorou->setConditions(['AND(I5<>"",I5<0)']);
        $melhorou->getStyle()->getFont()->setBold(true)->getColor()->setARGB(self::COR_MENOR_TEXTO);

        $piorou = new Conditional;
        $piorou->setConditionType(Conditional::CONDITION_EXPRESSION);
        $piorou->setConditions(['AND(I5<>"",I5>0)']);
        $piorou->getStyle()->getFont()->setBold(true)->getColor()->setARGB('FFB91C1C');

        $aba->getStyle("I5:I{$ultima}")->setConditionalStyles([$melhorou, $piorou]);

        $aba->getColumnDimension('A')->setWidth(8);
        $aba->getColumnDimension('B')->setWidth(50);
        $aba->getColumnDimension('C')->setWidth(8);
        foreach (['D', 'E', 'F', 'G', 'H', 'I'] as $letra) {
            $aba->getColumnDimension($letra)->setWidth(17);
        }

        $aba->freezePane('A5');
        $aba->setAutoFilter("A4:I{$ultima}");
        $aba->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);
    }

    /**
     * Aba 4 — Decisão. O que o comprador escolheu, item a item.
     *
     * Existe porque a compra pode ser DIVIDIDA: o mapa não termina num
     * fornecedor, termina numa lista de "este item, deste fornecedor". Sem esta
     * aba, essa lista só existiria na cabeça de quem decidiu.
     *
     * O total de cada linha é FÓRMULA apontando para a aba Mapa — preço do
     * fornecedor escolhido × quantidade. Assim corrigir o preço na grade
     * corrige a decisão também; um valor estático aqui faria as duas abas
     * discordarem no primeiro ajuste.
     *
     * @param  Collection<int, CotacaoMapaItem>  $itens
     * @param  Collection<int, \App\Models\CotacaoMapaFornecedor>  $fornecedores
     * @param  array<string, mixed>  $calculado
     * @param  array<string, mixed>  $ref
     */
    private function abaDecisao(
        Worksheet $aba,
        Collection $itens,
        Collection $fornecedores,
        array $calculado,
        array $ref
    ): void {
        $aba->setTitle('Decisão');

        $aba->setCellValue('A1', 'DECISÃO DE COMPRA');
        $aba->mergeCells('A1:F1');
        $aba->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
        $aba->getStyle('A1:F1')->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COR_FAIXA);
        $aba->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);
        $aba->getRowDimension(1)->setRowHeight(26);

        $decididos = $itens->filter(fn (CotacaoMapaItem $i) => $i->vencedor_id !== null);

        if ($decididos->isEmpty()) {
            $aba->setCellValue('A3', 'Nenhum item teve o fornecedor escolhido ainda.');
            $aba->setCellValue('A4', 'A escolha é feita na grade do mapa, item a item — a compra pode ser dividida entre fornecedores.');
            $aba->getStyle('A3')->getFont()->setBold(true)->setSize(11);
            $aba->getStyle('A4')->getFont()->setSize(9)->getColor()->setARGB('FF6B7280');
            $aba->getColumnDimension('A')->setWidth(90);

            return;
        }

        $colunas = ['A' => 'ITEM', 'B' => 'DESCRIÇÃO', 'C' => 'UND', 'D' => 'QNT.', 'E' => 'FORNECEDOR ESCOLHIDO', 'F' => 'TOTAL'];

        foreach ($colunas as $letra => $titulo) {
            $aba->setCellValue($letra . '3', $titulo);
        }

        $cab = $aba->getStyle('A3:F3');
        $cab->getFont()->setBold(true)->setSize(9)->getColor()->setARGB('FFFFFFFF');
        $cab->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COR_CABECALHO);
        $cab->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);

        $porNome = $fornecedores->keyBy('id');

        // De onde cada número vem na aba Mapa: a ordem das coleções aqui é a
        // mesma com que a grade foi escrita, então índice vira linha e coluna.
        $linhaNoMapa = [];
        $l = self::LINHA_CABECALHO + 1;

        foreach ($itens as $item) {
            $linhaNoMapa[(int) $item->id] = $l++;
        }

        $colunaNoMapa = [];
        $c = self::COLUNA_FORNECEDORES;

        foreach ($fornecedores as $fornecedor) {
            $colunaNoMapa[(int) $fornecedor->id] = Coordinate::stringFromColumnIndex($c++);
        }

        $linha = 4;

        foreach ($decididos as $item) {
            $calc = $calculado['itens'][(int) $item->id] ?? null;

            $aba->setCellValue('A' . $linha, $item->questor_cd_item ?? '—');
            $aba->setCellValue('B' . $linha, $item->descricao);
            $aba->setCellValue('C' . $linha, $item->unidade);
            $aba->setCellValue('D' . $linha, (float) $item->quantidade);
            $aba->setCellValue('E' . $linha, $porNome->get($item->vencedor_id)?->nome ?? '—');

            $colVencedor = $colunaNoMapa[(int) $item->vencedor_id] ?? null;
            $linVencedor = $linhaNoMapa[(int) $item->id] ?? null;

            if ($colVencedor !== null && $linVencedor !== null) {
                $aba->setCellValue(
                    'F' . $linha,
                    "=Mapa!{$colVencedor}{$linVencedor}*Mapa!\$D\${$linVencedor}"
                );
            } else {
                // Só cai aqui se a coluna do vencedor tiver saído do mapa — o
                // valor calculado é melhor que célula vazia.
                $aba->setCellValue('F' . $linha, $calc['vencedor_total'] ?? 0);
            }

            $linha++;
        }

        $ultima = $linha - 1;
        $lTotal = $linha + 1;

        $aba->setCellValue('E' . $lTotal, 'TOTAL DECIDIDO');
        $aba->setCellValue('F' . $lTotal, "=SUM(F4:F{$ultima})");
        $aba->getStyle("E{$lTotal}:F{$lTotal}")->getFont()->setBold(true)->setSize(12);
        $aba->getStyle("E{$lTotal}:F{$lTotal}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
        $aba->getStyle('E' . $lTotal)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $pendentes = $itens->count() - $decididos->count();

        if ($pendentes > 0) {
            $aba->setCellValue('A' . ($lTotal + 2), "Atenção: {$pendentes} item(ns) ainda sem fornecedor escolhido — este total é parcial.");
            $aba->mergeCells('A' . ($lTotal + 2) . ':F' . ($lTotal + 2));
            $aba->getStyle('A' . ($lTotal + 2))->getFont()->setBold(true)->setSize(9)->getColor()->setARGB('FFB45309');
        }

        $aba->getStyle("D4:D{$ultima}")->getNumberFormat()->setFormatCode(self::QTD);
        $aba->getStyle("F4:F{$lTotal}")->getNumberFormat()->setFormatCode(self::MOEDA_SIMPLES);
        $aba->getStyle("B4:B{$ultima}")->getAlignment()->setWrapText(true);
        $aba->getStyle("A3:F{$ultima}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::COR_BORDA);

        $aba->getColumnDimension('A')->setWidth(8);
        $aba->getColumnDimension('B')->setWidth(46);
        $aba->getColumnDimension('C')->setWidth(7);
        $aba->getColumnDimension('D')->setWidth(9);
        $aba->getColumnDimension('E')->setWidth(32);
        $aba->getColumnDimension('F')->setWidth(17);

        $aba->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT)->setFitToWidth(1)->setFitToHeight(0);
    }

    /**
     * Barra de seção — o mesmo tratamento nas três abas que têm seções.
     */
    private function tituloSecao(Worksheet $aba, string $intervalo): void
    {
        $aba->mergeCells($intervalo);

        $estilo = $aba->getStyle($intervalo);
        $estilo->getFont()->setBold(true)->setSize(10)->getColor()->setARGB('FF374151');
        $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COR_ROTULO);
        $estilo->getAlignment()->setIndent(1)->setVertical(Alignment::VERTICAL_CENTER);
        $estilo->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setARGB(self::COR_CABECALHO);
    }
}
