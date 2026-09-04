<?php

namespace App\Services\Cotacao\Export;

use App\Models\CotacaoMapa;
use App\Services\Cotacao\MapaCalculoService;
use App\Models\CotacaoMapaFornecedor;
use App\Models\CotacaoMapaItem;
use App\Models\CotacaoPreco;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Gera o XLSX do mapa no MESMO layout da planilha que a compra usa hoje.
 *
 * O layout não é uma escolha estética: o comprador imprime, leva para a
 * reunião, marca a caneta e arquiva. Mudar a posição das linhas obrigaria a
 * refazer um hábito que funciona.
 *
 *   A1  COTAÇÃO DE COMPRAS            C1  <título>
 *   A3  SOLIC.                        C3  DATA: dd/mm/aaaa
 *   A4  <departamento>                C4  SC: <número>
 *   L5  E..N  frete (CIF/FOB)
 *   L6  E..N  prazo de entrega
 *   L7  E..N  condição de pagamento
 *   L8  A ITEM SC | B UND | C DESCRIÇÃO | D QNT. | E..N <fornecedores>
 *
 * Os rótulos são SIGLAS e as células de cabeçalho são MESCLADAS (A:B) com o
 * texto encaixado na caixa. As colunas A e B são estreitas porque a grade
 * abaixo é impressa nessa proporção — e texto em A só transborda até achar
 * célula ocupada, que é a C do bloco da direita. Alargar A resolveria o corte
 * e estragaria a grade; mesclar resolve sem tocar em largura nenhuma.
 *   L9+ itens
 *   ... FRETE | [DESCONTO] | SUBTOTAL | TOTAL | TOTAL GERAL DO PEDIDO
 *
 * A linha DESCONTO só sai quando algum fornecedor deu desconto: o arquivo é
 * impresso e assinado, e uma linha de zeros em toda cotação seria ruído
 * permanente por um caso ocasional. Quando ela existe, o TOTAL a abate — e é
 * ela que permite conferir o total à mão.
 *
 * DUAS COISAS QUE O ARQUIVO GERADO FAZ E A PLANILHA ATUAL NÃO:
 *
 * 1. Os totais são FÓRMULA, não número calculado no PHP. Quem abrir o arquivo e
 *    corrigir um preço vê o total mudar — que é o que todo mundo espera de uma
 *    planilha, e o que a exportação "com valores" quebra em silêncio.
 *
 * 2. O menor preço é FORMATAÇÃO CONDICIONAL, não cor fixa na célula. Mesmo
 *    motivo: editado o preço, o destaque acompanha.
 *
 * A aba 2 ("Histórico") é a melhoria de verdade sobre o arquivo atual — ela
 * responde "este preço é bom?" comparando cada item com a última compra.
 */
class LayoutClassico implements LayoutExportacao
{
    /** Primeira linha de item na grade. */
    private const LINHA_ITENS = 9;

    /** Primeira coluna de fornecedor (E). */
    private const COLUNA_FORNECEDORES = 5;

    private const FONTE = 'Arial';

    private const FORMATO_MOEDA = '#,##0.00';

    private const FORMATO_QTD = '#,##0';

    /** Cinza do cabeçalho — o mesmo tom em que a planilha atual é impressa. */
    private const COR_CABECALHO = 'FFD9D9D9';

    /** Verde discreto do menor preço da linha. */
    private const COR_MENOR = 'FFC6EFCE';

    public function __construct(private readonly MapaCalculoService $calculo)
    {
    }

    public function nome(): string
    {
        return 'Clássico (igual à planilha impressa)';
    }

    public function descricao(): string
    {
        return 'O layout que a compra imprime, assina e arquiva. Mesmas linhas, '
            . 'mesmas colunas — com os totais em fórmula e uma aba de histórico a mais.';
    }

    /**
     * O documento completo, com as duas abas. Público para o teste conseguir
     * inspecionar as fórmulas sem passar pelo disco.
     */
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
            ->setDescription('Gerado pela Lara a partir da solicitação de compra ' . $mapa->questor_solicitacao);

        $this->abaMapa($planilha->getActiveSheet(), $mapa, $itens, $fornecedores, $precos);
        $this->abaHistorico($planilha->createSheet(), $itens, $calculado);

        $planilha->setActiveSheetIndex(0);

        return $planilha;
    }

    /**
     * Aba 1 — a grade, igual ao modelo.
     *
     * @param  \Illuminate\Support\Collection<int, CotacaoMapaItem>  $itens
     * @param  \Illuminate\Support\Collection<int, CotacaoMapaFornecedor>  $fornecedores
     * @param  \Illuminate\Support\Collection<int, CotacaoPreco>  $precos
     */
    private function abaMapa(
        Worksheet $aba,
        CotacaoMapa $mapa,
        $itens,
        $fornecedores,
        $precos
    ): void {
        $aba->setTitle(Str::limit($mapa->data_mapa?->format('d.m.Y') ?? 'Mapa', 28, ''));

        $qtdItens = $itens->count();
        $qtdFornecedores = $fornecedores->count();

        $primeiraLinha = self::LINHA_ITENS;
        $ultimaLinha = $qtdItens > 0 ? $primeiraLinha + $qtdItens - 1 : $primeiraLinha;

        $primeiraColuna = self::COLUNA_FORNECEDORES;
        $ultimaColuna = $primeiraColuna + max($qtdFornecedores, 1) - 1;

        $colIni = Coordinate::stringFromColumnIndex($primeiraColuna);
        $colFim = Coordinate::stringFromColumnIndex($ultimaColuna);

        // --- Cabeçalho (linhas 1 a 4) ---------------------------------------

        $aba->setCellValue('A1', 'COTAÇÃO DE COMPRAS');
        $aba->setCellValue('C1', $mapa->titulo);
        $aba->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $aba->getStyle('C1')->getFont()->setBold(true);

        // Sigla, como no resto do arquivo: a coluna A tem 9 de largura porque
        // abaixo dela mora "ITEM SC", e o rótulo inteiro disputava com o
        // departamento a mesma faixa de transbordo.
        $aba->setCellValue('A3', 'SOLIC.');
        $aba->setCellValue('C3', 'DATA: ' . ($mapa->data_mapa ?? Carbon::today())->format('d/m/Y'));
        $aba->setCellValue('A4', $mapa->departamento ?: $mapa->solicitante);
        $aba->setCellValue('C4', 'SC: ' . $mapa->questor_solicitacao);
        $aba->getStyle('A3')->getFont()->setBold(true);

        /*
         | O TEXTO DO CABEÇALHO CABE AQUI, E NÃO NA LARGURA DAS COLUNAS.
         |
         | A coluna A tem 9 de largura porque abaixo dela mora "ITEM SC"; a B
         | tem 10 por causa de "UND MED". O cabeçalho, porém, escreve nas mesmas
         | colunas — e um texto em A só transborda até encontrar célula ocupada,
         | que é justamente a C do bloco da direita. Resultado: "COTAÇÃO DE
         | COMPRAS" saía cortada, e um departamento com nome comprido também.
         |
         | A saída é mesclar A:B no cabeçalho e encaixar o texto na caixa
         | (`shrinkToFit`), NUNCA alargar A ou B: alargá-las estragaria a grade
         | inteira abaixo, que é impressa e assinada nessa proporção.
         |
         | `shrinkToFit` em vez de `wrapText` de propósito: célula MESCLADA não
         | ganha altura automática no Excel, então quebrar linha ali esconderia
         | a segunda metade do texto — trocaria um corte por outro.
         */
        foreach (['A1', 'A3', 'A4'] as $celula) {
            $aba->mergeCells($celula . ':B' . substr($celula, 1));
            $aba->getStyle($celula)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setShrinkToFit(true);
        }

        // A coluna C tem 58 e a D está vazia nestas linhas: o texto da direita
        // transborda pelas colunas de fornecedor e aparece inteiro sem mesclar.
        // Mesclar aqui só limitaria o espaço de um título comprido.
        $aba->getStyle('C1:C4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // --- Linhas 5, 6 e 7: condições por fornecedor ----------------------

        $coluna = $primeiraColuna;

        foreach ($fornecedores as $fornecedor) {
            $letra = Coordinate::stringFromColumnIndex($coluna);

            $aba->setCellValue($letra . '5', $fornecedor->frete);
            $aba->setCellValue($letra . '6', $fornecedor->prazo_entrega);
            $aba->setCellValue($letra . '7', $fornecedor->condicao_pagamento);

            $coluna++;
        }

        // Condição de pagamento vira texto comprido na vida real ("50% ENTRADA
        // + 50% EM 30 DIAS") e a coluna do fornecedor tem 16 de largura.
        //
        // Aqui a quebra de linha é melhor que o `shrinkToFit` do cabeçalho:
        // estas células NÃO são mescladas, e o Excel cresce a altura da linha
        // sozinho — o texto aparece inteiro e no tamanho normal, em vez de
        // encolhido a ponto de não se ler.
        $aba->getStyle("{$colIni}5:{$colFim}7")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // --- Linha 8: cabeçalho da grade ------------------------------------

        $aba->setCellValue('A8', 'ITEM SC');
        $aba->setCellValue('B8', 'UND');
        $aba->setCellValue('C8', 'DESCRIÇÃO');
        $aba->setCellValue('D8', 'QNT.');

        $coluna = $primeiraColuna;

        foreach ($fornecedores as $fornecedor) {
            $aba->setCellValue(Coordinate::stringFromColumnIndex($coluna) . '8', mb_strtoupper($fornecedor->nome));
            $coluna++;
        }

        $cabecalho = $aba->getStyle("A8:{$colFim}8");
        $cabecalho->getFont()->setBold(true);
        $cabecalho->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COR_CABECALHO);
        $cabecalho->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);

        // --- Itens ----------------------------------------------------------

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

            $coluna = $primeiraColuna;

            foreach ($fornecedores as $fornecedor) {
                $celula = Coordinate::stringFromColumnIndex($coluna) . $linha;
                $preco = $matriz[(int) $item->id][(int) $fornecedor->id] ?? null;

                if ($preco !== null && $preco->temPreco()) {
                    $aba->setCellValue($celula, (float) $preco->valor_unitario);
                } elseif ($preco !== null && $preco->naoTrabalha()) {
                    // "NT" tem de ser TEXTO explícito: escrito como valor comum,
                    // o Excel poderia interpretá-lo, e as fórmulas de soma
                    // precisam continuar ignorando a célula.
                    $aba->setCellValueExplicit($celula, 'NT', DataType::TYPE_STRING);
                }
                // `sem_resposta` não escreve nada. Célula vazia é a convenção da
                // planilha, e vazio não entra em SUMPRODUCT nem em MIN.

                $coluna++;
            }

            $linha++;
        }

        $aba->getStyle("D{$primeiraLinha}:D{$ultimaLinha}")
            ->getNumberFormat()->setFormatCode(self::FORMATO_QTD);
        $aba->getStyle("{$colIni}{$primeiraLinha}:{$colFim}{$ultimaLinha}")
            ->getNumberFormat()->setFormatCode(self::FORMATO_MOEDA);

        // --- Rodapé: FRETE / [DESCONTO] / SUBTOTAL / TOTAL / TOTAL GERAL ----

        /*
         | O DESCONTO ENTRA NO TOTAL — e por muito tempo não entrava aqui.
         |
         | Este layout somava só `SUBTOTAL + FRETE`, sem nenhuma linha de
         | desconto. O resultado era o pior tipo de defeito num documento de
         | compra: a planilha IMPRESSA e a TELA mostravam totais diferentes para
         | o mesmo mapa, e nada na planilha denunciava a diferença. O layout
         | completo sempre subtraiu; era só este que divergia.
         |
         | A LINHA SÓ APARECE QUANDO ALGUÉM DEU DESCONTO. Este arquivo é
         | impresso, assinado e arquivado, e uma linha de zeros em toda cotação
         | seria ruído permanente por um caso ocasional. Quando não há desconto,
         | o rodapé sai exatamente como sempre saiu.
         |
         | Mostrar a linha não é enfeite: um TOTAL que não fecha com as linhas
         | acima dele é pior que um total errado, porque ninguém consegue
         | conferir. Se o desconto abate, ele tem de estar visível.
         */
        $temDesconto = $fornecedores->contains(fn (CotacaoMapaFornecedor $f) => (float) $f->desconto > 0);

        $lFrete = $ultimaLinha + 1;
        $lDesconto = $temDesconto ? $lFrete + 1 : null;
        $lSubtotal = ($lDesconto ?? $lFrete) + 1;
        $lTotal = $lSubtotal + 1;
        $lGeral = $lTotal + 1;

        $aba->setCellValue('C' . $lFrete, 'FRETE');
        $aba->setCellValue('C' . $lSubtotal, 'SUBTOTAL');
        $aba->setCellValue('C' . $lTotal, 'TOTAL');
        $aba->setCellValue('C' . $lGeral, 'TOTAL GERAL DO PEDIDO');

        if ($temDesconto) {
            $aba->setCellValue('C' . $lDesconto, 'DESCONTO');
        }

        $aba->getStyle("C{$lFrete}:C{$lGeral}")->getFont()->setBold(true);

        $coluna = $primeiraColuna;

        foreach ($fornecedores as $fornecedor) {
            $letra = Coordinate::stringFromColumnIndex($coluna);

            $aba->setCellValue($letra . $lFrete, (float) $fornecedor->valor_frete);

            if ($temDesconto) {
                $aba->setCellValue($letra . $lDesconto, (float) $fornecedor->desconto);
            }

            if ($qtdItens > 0) {
                // SUMPRODUCT ignora texto ("NT") e vazio, então quem não cotou
                // não entra no subtotal — que é exatamente a regra do módulo.
                $aba->setCellValue(
                    $letra . $lSubtotal,
                    "=SUMPRODUCT(\$D\${$primeiraLinha}:\$D\${$ultimaLinha},{$letra}{$primeiraLinha}:{$letra}{$ultimaLinha})"
                );
            } else {
                $aba->setCellValue($letra . $lSubtotal, 0);
            }

            $abate = $temDesconto ? "-{$letra}{$lDesconto}" : '';

            // O `IF(COUNT(...)=0,0,...)` é a segunda correção da mesma fórmula:
            // sem ele, um fornecedor que não respondeu NADA aparecia no rodapé
            // devendo o frete, como se tivesse cotado. É a mesma regra do
            // cálculo da tela — frete e desconto só entram se houve cotação.
            $aba->setCellValue(
                $letra . $lTotal,
                "=IF(COUNT({$letra}{$primeiraLinha}:{$letra}{$ultimaLinha})=0,0,"
                    . "{$letra}{$lSubtotal}+{$letra}{$lFrete}{$abate})"
            );

            $coluna++;
        }

        $aba->getStyle("{$colIni}{$lFrete}:{$colFim}{$lTotal}")
            ->getNumberFormat()->setFormatCode(self::FORMATO_MOEDA);
        $aba->getStyle("{$colIni}{$lTotal}:{$colFim}{$lTotal}")->getFont()->setBold(true);

        // TOTAL GERAL DO PEDIDO — a compra DIVIDIDA: cada item pelo menor preço
        // da linha. Precisa de uma coluna auxiliar porque o Excel não soma
        // "mínimo de cada linha" numa fórmula só sem matricial; a coluna fica
        // logo depois dos fornecedores e é ocultada.
        if ($qtdItens > 0 && $qtdFornecedores > 0) {
            $colAux = Coordinate::stringFromColumnIndex($ultimaColuna + 1);

            $aba->setCellValue($colAux . '8', 'MENOR × QNT (auxiliar)');

            for ($l = $primeiraLinha; $l <= $ultimaLinha; $l++) {
                // COUNT conta só números: linha sem nenhum preço dá zero em vez
                // de MIN() de nada, que valeria zero e mentiria no total.
                $aba->setCellValue(
                    $colAux . $l,
                    "=IF(COUNT({$colIni}{$l}:{$colFim}{$l})=0,0,MIN({$colIni}{$l}:{$colFim}{$l})*\$D\${$l})"
                );
            }

            $aba->setCellValue($colIni . $lGeral, "=SUM({$colAux}{$primeiraLinha}:{$colAux}{$ultimaLinha})");
            $aba->getStyle($colIni . $lGeral)->getNumberFormat()->setFormatCode(self::FORMATO_MOEDA);
            $aba->getStyle($colIni . $lGeral)->getFont()->setBold(true);

            $aba->getColumnDimension($colAux)->setVisible(false);
            $aba->getStyle("{$colAux}{$primeiraLinha}:{$colAux}{$ultimaLinha}")
                ->getNumberFormat()->setFormatCode(self::FORMATO_MOEDA);

            $this->destacarMenorPreco($aba, $colIni, $colFim, $primeiraLinha, $ultimaLinha);
        }

        // --- Acabamento -----------------------------------------------------

        $aba->getColumnDimension('A')->setWidth(9);
        $aba->getColumnDimension('B')->setWidth(10);
        $aba->getColumnDimension('C')->setWidth(58);
        $aba->getColumnDimension('D')->setWidth(7);

        for ($c = $primeiraColuna; $c <= $ultimaColuna; $c++) {
            $aba->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(16);
        }

        if ($qtdItens > 0) {
            $aba->getStyle("A8:{$colFim}{$lTotal}")
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        $aba->getStyle("C{$primeiraLinha}:C{$ultimaLinha}")->getAlignment()->setWrapText(true);
        $aba->freezePane('E' . self::LINHA_ITENS);
        $aba->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $aba->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
    }

    /**
     * Destaque do menor preço de cada linha, por formatação condicional.
     *
     * Fórmula relativa à primeira célula do intervalo: o Excel a reescreve por
     * célula, e `$E9:$N9` prende as colunas e solta a linha — cada célula
     * compara-se com o mínimo da SUA linha.
     *
     * O `<>""` é o que impede a linha inteira de ficar verde quando ninguém
     * cotou: sem ele, `MIN()` de um intervalo vazio dá zero e toda célula vazia
     * "empata" com o mínimo.
     */
    private function destacarMenorPreco(
        Worksheet $aba,
        string $colIni,
        string $colFim,
        int $primeiraLinha,
        int $ultimaLinha
    ): void {
        $condicao = new Conditional;
        $condicao->setConditionType(Conditional::CONDITION_EXPRESSION);
        $condicao->setConditions([
            "AND({$colIni}{$primeiraLinha}<>\"\","
            . "{$colIni}{$primeiraLinha}=MIN(\${$colIni}{$primeiraLinha}:\${$colFim}{$primeiraLinha}))",
        ]);
        $condicao->getStyle()->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(self::COR_MENOR);
        $condicao->getStyle()->getFont()->setBold(true);

        $aba->getStyle("{$colIni}{$primeiraLinha}:{$colFim}{$ultimaLinha}")
            ->setConditionalStyles([$condicao]);
    }

    /**
     * Aba 2 — Histórico. A melhoria sobre o arquivo em uso.
     *
     * Responde, item a item, "este preço é bom?": última compra (data,
     * fornecedor, valor) contra a melhor cotação do mapa, com a variação em
     * porcentagem.
     *
     * Aqui os valores vão calculados, e não como fórmula: são um retrato do
     * momento da exportação, incluindo a última compra, que veio do Questor e
     * não está na planilha para o Excel recalcular.
     *
     * @param  \Illuminate\Support\Collection<int, CotacaoMapaItem>  $itens
     * @param  array<string, mixed>  $calculado
     */
    private function abaHistorico(Worksheet $aba, $itens, array $calculado): void
    {
        $aba->setTitle('Histórico');

        $aba->setCellValue('A1', 'HISTÓRICO E COMPARAÇÃO COM A ÚLTIMA COMPRA');
        $aba->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        $colunas = [
            'A' => 'ITEM SC',
            'B' => 'DESCRIÇÃO',
            'C' => 'QNT.',
            'D' => 'ÚLT. COMPRA — DATA',
            'E' => 'ÚLT. COMPRA — FORNECEDOR',
            'F' => 'ÚLT. COMPRA — NF',
            'G' => 'ÚLT. COMPRA — VL. UNIT.',
            'H' => 'MELHOR COTAÇÃO',
            'I' => 'VARIAÇÃO %',
            'J' => 'ECONOMIA (R$)',
        ];

        foreach ($colunas as $letra => $titulo) {
            $aba->setCellValue($letra . '3', $titulo);
        }

        $cabecalho = $aba->getStyle('A3:J3');
        $cabecalho->getFont()->setBold(true);
        $cabecalho->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COR_CABECALHO);
        $cabecalho->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $linha = 4;

        foreach ($itens as $item) {
            $calc = $calculado['itens'][(int) $item->id] ?? null;

            $ultima = $calc['ult_compra_valor'] ?? null;
            $melhor = $calc['menor_preco'] ?? null;

            $aba->setCellValue('A' . $linha, $item->questor_cd_item ?? '—');
            $aba->setCellValue('B' . $linha, $item->descricao);
            $aba->setCellValue('C' . $linha, (float) $item->quantidade);

            if (! $item->temCadastroNoQuestor()) {
                // O item digitado como texto livre não tem histórico por código
                // — e dizer isso é melhor do que deixar a linha em branco e
                // parecer erro de exportação.
                $aba->setCellValue('D' . $linha, 'item sem cadastro — sem histórico');
            } elseif ($ultima !== null) {
                $aba->setCellValue('D' . $linha, $item->ult_compra_data?->format('d/m/Y'));
                $aba->setCellValue('E' . $linha, $item->ult_compra_fornecedor_nome);
                $aba->setCellValue('F' . $linha, $item->ult_compra_nf);
                $aba->setCellValue('G' . $linha, $ultima);
            } else {
                $aba->setCellValue('D' . $linha, 'sem compra anterior');
            }

            if ($melhor !== null) {
                $aba->setCellValue('H' . $linha, $melhor);
            }

            if ($ultima !== null && $melhor !== null && $ultima > 0) {
                $aba->setCellValue('I' . $linha, ($melhor - $ultima) / $ultima);
                $aba->setCellValue('J' . $linha, $calc['economia_vs_ultima']);
            }

            $linha++;
        }

        $ultimaLinha = max($linha - 1, 4);

        $aba->getStyle("C4:C{$ultimaLinha}")->getNumberFormat()->setFormatCode(self::FORMATO_QTD);
        $aba->getStyle("G4:H{$ultimaLinha}")->getNumberFormat()->setFormatCode(self::FORMATO_MOEDA);
        $aba->getStyle("J4:J{$ultimaLinha}")->getNumberFormat()->setFormatCode(self::FORMATO_MOEDA);
        $aba->getStyle("I4:I{$ultimaLinha}")->getNumberFormat()->setFormatCode('0.0%');

        // Resumo: a pergunta que o comprador leva para a chefia.
        $resumo = $ultimaLinha + 2;
        $totais = $calculado['totais'];

        $aba->setCellValue('B' . $resumo, 'ECONOMIA PROJETADA (vs. última compra)');
        $aba->setCellValue('J' . $resumo, $totais['economia']);
        $aba->setCellValue('B' . ($resumo + 1), 'TOTAL PELA MELHOR COMBINAÇÃO (compra dividida)');
        $aba->setCellValue('J' . ($resumo + 1), $totais['melhor_combinacao']);
        $aba->setCellValue('B' . ($resumo + 2), 'TOTAL PELO MELHOR FORNECEDOR ÚNICO');
        $aba->setCellValue('J' . ($resumo + 2), $totais['melhor_fornecedor_unico_total']);
        $aba->setCellValue('B' . ($resumo + 3), 'ITENS COMPARÁVEIS (com última compra e cotação)');
        $aba->setCellValue('J' . ($resumo + 3), $totais['itens_comparaveis']);

        $aba->getStyle("B{$resumo}:B" . ($resumo + 3))->getFont()->setBold(true);
        $aba->getStyle("J{$resumo}:J" . ($resumo + 2))->getNumberFormat()->setFormatCode(self::FORMATO_MOEDA);

        $aba->getColumnDimension('A')->setWidth(9);
        $aba->getColumnDimension('B')->setWidth(52);
        $aba->getColumnDimension('C')->setWidth(7);
        foreach (['D', 'E', 'F', 'G', 'H', 'I', 'J'] as $letra) {
            $aba->getColumnDimension($letra)->setWidth(18);
        }
    }
}
