<?php

namespace Tests\Feature\Cotacao;

use App\Models\CotacaoMapa;
use App\Models\CotacaoMapaFornecedor;
use App\Models\CotacaoMapaItem;
use App\Models\CotacaoPreco;
use App\Services\Cotacao\MapaCalculoService;
use App\Services\Cotacao\MapaExportService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\CreatesCotacaoSchema;
use Tests\Concerns\CriaMapaDeCotacao;
use Tests\TestCase;

/**
 * O XLSX exportado.
 *
 * O TESTE CENTRAL AQUI É O DAS FÓRMULAS: o arquivo é reaberto do disco com o
 * PhpSpreadsheet e se confere que SUBTOTAL e TOTAL voltaram como FÓRMULA, não
 * como número. Um número calculado no PHP passa despercebido na tela e quebra o
 * arquivo em silêncio — quem corrige um preço no Excel vê o total não mudar, e
 * decide a compra pelo valor errado.
 *
 * O layout conferido é o da planilha que a compra usa hoje (linhas 1–8 e o
 * rodapé FRETE/SUBTOTAL/TOTAL/TOTAL GERAL), porque ela é impressa, assinada e
 * arquivada — mudar a posição das linhas quebraria um hábito que funciona.
 */
class MapaExportServiceTest extends TestCase
{
    use CreatesCotacaoSchema;
    use CriaMapaDeCotacao;

    private string $arquivo = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCotacaoSchema();
    }

    protected function tearDown(): void
    {
        if ($this->arquivo !== '' && is_file($this->arquivo)) {
            unlink($this->arquivo);
        }

        parent::tearDown();
    }

    public function test_o_cabecalho_segue_o_layout_da_planilha_em_uso(): void
    {
        $mapa = $this->mapaDeExemplo();

        $aba = $this->servico()->montar($mapa)->getSheet(0);

        $this->assertSame('COTAÇÃO DE COMPRAS', $aba->getCell('A1')->getValue());
        $this->assertSame($mapa->titulo, $aba->getCell('C1')->getValue());
        // Rótulo em sigla: a coluna A tem 9 de largura por causa de "ITEM SC"
        // na grade, e o nome inteiro disputava a faixa de transbordo com o
        // departamento da linha 4.
        $this->assertSame('SOLIC.', $aba->getCell('A3')->getValue());
        $this->assertStringStartsWith('DATA: ', $aba->getCell('C3')->getValue());
        $this->assertSame('MANUTENÇÃO', $aba->getCell('A4')->getValue());
        $this->assertSame('SC: 34334', $aba->getCell('C4')->getValue());

        // Linhas 5, 6 e 7: condições por fornecedor, a partir da coluna E.
        $this->assertSame('CIF', $aba->getCell('E5')->getValue());
        $this->assertSame('1 DU', $aba->getCell('E6')->getValue());
        $this->assertSame('14D', $aba->getCell('E7')->getValue());

        // Linha 8: cabeçalho da grade.
        $this->assertSame('ITEM SC', $aba->getCell('A8')->getValue());
        $this->assertSame('UND', $aba->getCell('B8')->getValue());
        $this->assertSame('DESCRIÇÃO', $aba->getCell('C8')->getValue());
        $this->assertSame('QNT.', $aba->getCell('D8')->getValue());
        $this->assertSame('D C DE ALMEIDA', $aba->getCell('E8')->getValue());
        $this->assertSame('BARRA COR', $aba->getCell('F8')->getValue());

        // Itens a partir da linha 9.
        $this->assertSame(1, $aba->getCell('A9')->getValue());
        $this->assertSame('UN', $aba->getCell('B9')->getValue());
        $this->assertSame('TINTA ESMALTE AZUL 3,6L', $aba->getCell('C9')->getValue());
    }

    public function test_subtotal_e_total_sao_formulas_de_verdade_no_arquivo_gravado(): void
    {
        $mapa = $this->mapaDeExemplo();

        $this->arquivo = $this->servico()->gerar($mapa);

        $this->assertFileExists($this->arquivo);

        // Reabre do disco: é o arquivo que o comprador vai abrir, não o objeto
        // em memória.
        $planilha = IOFactory::createReader('Xlsx')->load($this->arquivo);
        $aba = $planilha->getSheet(0);

        // 3 itens => linhas 9 a 11; FRETE 12, SUBTOTAL 13, TOTAL 14, GERAL 15.
        $subtotal = $aba->getCell('E13');
        $total = $aba->getCell('E14');

        $this->assertSame(DataType::TYPE_FORMULA, $subtotal->getDataType());
        $this->assertSame('=SUMPRODUCT($D$9:$D$11,E9:E11)', $subtotal->getValue());

        $this->assertSame(DataType::TYPE_FORMULA, $total->getDataType());

        // Sem desconto no mapa, o rodapé fica com as quatro linhas de sempre e
        // o TOTAL é subtotal + frete. O `IF(COUNT(...)=0,0,...)` existe para o
        // fornecedor que não respondeu NADA não aparecer devendo o frete.
        $this->assertSame('=IF(COUNT(E9:E11)=0,0,E13+E12)', $total->getValue());

        // O TOTAL GERAL DO PEDIDO também recalcula: soma a coluna auxiliar de
        // "menor da linha × quantidade".
        $geral = $aba->getCell('E15');
        $this->assertSame(DataType::TYPE_FORMULA, $geral->getDataType());
        $this->assertStringStartsWith('=SUM(', $geral->getValue());

        $this->assertSame('FRETE', $aba->getCell('C12')->getValue());
        $this->assertSame('SUBTOTAL', $aba->getCell('C13')->getValue());
        $this->assertSame('TOTAL', $aba->getCell('C14')->getValue());
        $this->assertSame('TOTAL GERAL DO PEDIDO', $aba->getCell('C15')->getValue());

        $planilha->disconnectWorksheets();
    }

    /**
     * As duas situações sem preço saem diferentes no papel — e nenhuma delas
     * vira zero. `SUMPRODUCT` ignora texto e vazio, então o subtotal continua
     * certo nos dois casos.
     */
    public function test_nao_trabalha_sai_como_texto_nt_e_sem_resposta_sai_vazio(): void
    {
        $mapa = $this->mapaDeExemplo();

        $aba = $this->servico()->montar($mapa)->getSheet(0);

        // Item 1 / coluna F (BARRA COR): cotado.
        $this->assertEqualsWithDelta(189.43, $aba->getCell('F9')->getValue(), 0.0001);

        // Item 2 / coluna F: não trabalha.
        $this->assertSame('NT', $aba->getCell('F10')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $aba->getCell('F10')->getDataType());

        // Item 3 / coluna F: sem resposta — célula em branco, como na planilha.
        $this->assertNull($aba->getCell('F11')->getValue());

        // Item 3 / coluna E: também sem linha de preço nenhuma no banco.
        $this->assertNull($aba->getCell('E11')->getValue());
    }

    public function test_a_aba_de_historico_compara_a_melhor_cotacao_com_a_ultima_compra(): void
    {
        $mapa = $this->mapaDeExemplo();

        $planilha = $this->servico()->montar($mapa);
        $aba = $planilha->getSheet(1);

        $this->assertSame('Histórico', $aba->getTitle());
        $this->assertSame('ITEM SC', $aba->getCell('A3')->getValue());
        $this->assertSame('MELHOR COTAÇÃO', $aba->getCell('H3')->getValue());
        $this->assertSame('VARIAÇÃO %', $aba->getCell('I3')->getValue());

        // Item 1: última compra 200,00 e melhor cotação 180,00 (D C DE ALMEIDA).
        $this->assertEqualsWithDelta(200.0, $aba->getCell('G4')->getValue(), 0.0001);
        $this->assertEqualsWithDelta(180.0, $aba->getCell('H4')->getValue(), 0.0001);
        $this->assertEqualsWithDelta(-0.10, $aba->getCell('I4')->getValue(), 0.0001);

        // Item 3 não tem cadastro no Questor: a aba diz isso em vez de deixar
        // a linha em branco e parecer erro de exportação.
        $this->assertSame('item sem cadastro — sem histórico', $aba->getCell('D6')->getValue());

        $planilha->disconnectWorksheets();
    }

    public function test_nome_do_arquivo_carrega_titulo_sc_e_data(): void
    {
        $mapa = $this->mapaDeExemplo();

        $nome = $this->servico()->nomeArquivo($mapa);

        $this->assertStringStartsWith('COTACAO_', $nome);
        $this->assertStringContainsString('_34334_', $nome);
        $this->assertStringContainsString($mapa->data_mapa->format('d_m_Y'), $nome);
        $this->assertStringEndsWith('.xlsx', $nome);
    }

    public function test_mapa_sem_fornecedor_ainda_gera_arquivo_valido(): void
    {
        $mapa = CotacaoMapa::factory()->create(['questor_solicitacao' => 34334]);
        CotacaoMapaItem::factory()->create(['cotacao_mapa_id' => $mapa->id, 'ordem' => 1]);

        $this->arquivo = $this->servico()->gerar($mapa->fresh());

        $this->assertFileExists($this->arquivo);

        $planilha = IOFactory::createReader('Xlsx')->load($this->arquivo);
        $this->assertSame('COTAÇÃO DE COMPRAS', $planilha->getSheet(0)->getCell('A1')->getValue());
        $planilha->disconnectWorksheets();
    }

    // ------------------------------------------------------------------

    /**
     * O DESCONTO DO FORNECEDOR ABATE O TOTAL — e por muito tempo não abatia.
     *
     * Este layout somava só `SUBTOTAL + FRETE`, sem linha nem fórmula de
     * desconto, enquanto a tela e o layout completo subtraíam. O efeito era o
     * pior tipo de defeito num documento de compra: a planilha impressa e a
     * tela mostravam totais diferentes para o mesmo mapa, e nada na planilha
     * denunciava a diferença.
     */
    public function test_o_desconto_do_fornecedor_abate_o_total(): void
    {
        $mapa = $this->mapaDeExemplo();
        $mapa->fornecedores()->first()->update(['desconto' => 35.90]);

        $aba = $this->servico()->montar($mapa->fresh())->getSheet(0);

        // 3 itens (9 a 11) → FRETE 12, DESCONTO 13, SUBTOTAL 14, TOTAL 15.
        $this->assertSame('FRETE', $aba->getCell('C12')->getValue());
        $this->assertSame('DESCONTO', $aba->getCell('C13')->getValue());
        $this->assertSame('SUBTOTAL', $aba->getCell('C14')->getValue());
        $this->assertSame('TOTAL', $aba->getCell('C15')->getValue());

        $this->assertEqualsWithDelta(35.90, $aba->getCell('E13')->getValue(), 0.0001);

        // O valor tem de estar VISÍVEL e a fórmula tem de abatê-lo: um total
        // que não fecha com as linhas acima é pior que um total errado, porque
        // ninguém consegue conferir.
        $this->assertSame(
            '=IF(COUNT(E9:E11)=0,0,E14+E12-E13)',
            $aba->getCell('E15')->getValue()
        );
    }

    /**
     * A linha só aparece quando alguém deu desconto: a planilha é impressa e
     * assinada, e uma linha de zeros em toda cotação seria ruído permanente por
     * um caso ocasional.
     */
    public function test_sem_desconto_o_rodape_fica_com_as_linhas_de_sempre(): void
    {
        $aba = $this->servico()->montar($this->mapaDeExemplo())->getSheet(0);

        $this->assertSame('FRETE', $aba->getCell('C12')->getValue());
        $this->assertSame('SUBTOTAL', $aba->getCell('C13')->getValue());
        $this->assertSame('TOTAL', $aba->getCell('C14')->getValue());
        $this->assertSame('TOTAL GERAL DO PEDIDO', $aba->getCell('C15')->getValue());
    }

    /**
     * O número que sai no papel é o mesmo que o comprador viu na tela.
     *
     * Esta é a asserção que faltava: o Excel avalia a fórmula do arquivo e o
     * resultado é comparado com `MapaCalculoService`, que é quem manda no
     * rodapé da grade. Foi por não existir que a divergência do desconto passou.
     */
    public function test_o_total_da_planilha_bate_com_o_total_da_tela(): void
    {
        $mapa = $this->mapaDeExemplo();
        $mapa->fornecedores()->first()->update(['desconto' => 35.90]);
        $mapa = $mapa->fresh();

        $calculado = (new MapaCalculoService)->calcular(
            $mapa->itens,
            $mapa->fornecedores,
            $mapa->itens->flatMap(fn ($i) => $i->precos)
        );

        $this->arquivo = $this->servico()->gerar($mapa);

        $planilha = IOFactory::createReader('Xlsx')->load($this->arquivo);
        $aba = $planilha->getSheet(0);

        $primeiro = $mapa->fornecedores->first();

        $this->assertEqualsWithDelta(
            $calculado['fornecedores'][$primeiro->id]['total'],
            (float) $aba->getCell('E15')->getCalculatedValue(),
            0.0001,
            'O TOTAL do XLSX divergiu do TOTAL da tela — é o mesmo mapa.'
        );

        $planilha->disconnectWorksheets();
    }

    /**
     * O CABEÇALHO CABE SEM ALARGAR A GRADE.
     *
     * As colunas A e B são estreitas (9 e 10) porque abaixo delas moram "ITEM
     * SC" e "UND" — a planilha é impressa nessa proporção. Mas o cabeçalho
     * escreve nas MESMAS colunas, e no Excel um texto só transborda até achar
     * célula ocupada: a C do bloco da direita. Um departamento com nome
     * comprido aparecia pela metade.
     *
     * A correção mescla A:B e encaixa o texto na caixa. O que este teste
     * protege é a tentação de "resolver" alargando a coluna A, que consertaria
     * o cabeçalho e estragaria a grade inteira.
     */
    public function test_o_cabecalho_cabe_por_mesclagem_e_nao_por_largura_de_coluna(): void
    {
        $mapa = $this->mapaDeExemplo();
        $mapa->update(['departamento' => 'MANUTENCAO E CONSERVACAO PREDIAL DA SEDE']);

        $aba = $this->servico()->montar($mapa->fresh())->getSheet(0);

        $mesclagens = $aba->getMergeCells();

        foreach (['A1:B1', 'A3:B3', 'A4:B4'] as $caixa) {
            $this->assertArrayHasKey($caixa, $mesclagens, "O cabeçalho precisa da caixa {$caixa}.");
            $this->assertTrue(
                $aba->getStyle(explode(':', $caixa)[0])->getAlignment()->getShrinkToFit(),
                "O texto de {$caixa} tem de encaixar na caixa — mesclada não ganha altura automática."
            );
        }

        // As larguras da grade continuam exatamente as do papel.
        $this->assertEqualsWithDelta(9, $aba->getColumnDimension('A')->getWidth(), 0.01);
        $this->assertEqualsWithDelta(10, $aba->getColumnDimension('B')->getWidth(), 0.01);
    }

    /**
     * Condição de pagamento real é comprida ("50% ENTRADA + 50% EM 30 DIAS") e
     * a coluna do fornecedor tem 16.
     *
     * Aqui a quebra de linha é a escolha certa, e não o encaixe do cabeçalho:
     * estas células NÃO são mescladas, então o Excel cresce a altura sozinho e
     * o texto sai inteiro no tamanho normal. A altura da linha fica automática
     * de propósito — fixá-la reintroduziria o corte.
     */
    public function test_condicao_comprida_quebra_linha_em_vez_de_ser_cortada(): void
    {
        $mapa = $this->mapaDeExemplo();
        $mapa->fornecedores()->first()->update(['condicao_pagamento' => '50% + 50% EM 30 DIAS']);

        $aba = $this->servico()->montar($mapa->fresh())->getSheet(0);

        $this->assertTrue($aba->getStyle('E7')->getAlignment()->getWrapText());
        $this->assertFalse($aba->getStyle('E7')->getAlignment()->getShrinkToFit());

        // -1 = sem altura fixa: é o que deixa o Excel calcular.
        $this->assertSame(-1.0, (float) $aba->getRowDimension(7)->getRowHeight());
    }

    /* ---------------------------------------------------------------------
     | A fachada: qual layout sai, e com que nome
     |---------------------------------------------------------------------*/

    public function test_os_dois_layouts_aparecem_no_menu_de_exportacao(): void
    {
        $layouts = $this->servico()->layouts();

        $this->assertSame(
            [MapaExportService::LAYOUT_CLASSICO, MapaExportService::LAYOUT_COMPLETO],
            array_keys($layouts)
        );

        foreach ($layouts as $layout) {
            $this->assertNotEmpty($layout['nome']);
            $this->assertNotEmpty($layout['descricao']);
        }
    }

    /**
     * O clássico é o padrão de propósito: quem pede "exportar" sem pensar quer
     * o de sempre. O dia em que o completo virar padrão é decisão de quem usa.
     */
    public function test_o_classico_e_o_padrao_e_um_layout_desconhecido_cai_nele(): void
    {
        $servico = $this->servico();

        $this->assertSame(MapaExportService::LAYOUT_CLASSICO, MapaExportService::LAYOUT_PADRAO);
        $this->assertSame(MapaExportService::LAYOUT_CLASSICO, $servico->normalizar('inexistente'));
        $this->assertSame(MapaExportService::LAYOUT_CLASSICO, $servico->normalizar(''));
        $this->assertSame(MapaExportService::LAYOUT_COMPLETO, $servico->normalizar('completo'));
    }

    /**
     * Um link velho no meio de uma cotação tem de entregar a planilha de
     * sempre, não uma página de erro.
     */
    public function test_layout_invalido_gera_o_classico_sem_estourar(): void
    {
        $planilha = $this->servico()->montar($this->mapaDeExemplo(), 'nao-existe');

        // Assinatura do clássico: A1 é a faixa da planilha impressa.
        $this->assertSame('COTAÇÃO DE COMPRAS', $planilha->getSheet(0)->getCell('A1')->getValue());
        $this->assertCount(2, $planilha->getSheetNames());

        $planilha->disconnectWorksheets();
    }

    /**
     * Os dois arquivos convivem na pasta de downloads do comprador; sem o
     * sufixo, o segundo sobrescreveria o primeiro sem avisar.
     */
    public function test_o_nome_do_arquivo_distingue_os_dois_layouts(): void
    {
        $mapa = $this->mapaDeExemplo();
        $servico = $this->servico();

        $classico = $servico->nomeArquivo($mapa, MapaExportService::LAYOUT_CLASSICO);
        $completo = $servico->nomeArquivo($mapa, MapaExportService::LAYOUT_COMPLETO);

        $this->assertStringEndsWith('.xlsx', $classico);
        $this->assertStringNotContainsString('_COMPLETO', $classico);
        $this->assertStringContainsString('_COMPLETO.xlsx', $completo);
        $this->assertNotSame($classico, $completo);

        // O resto do nome é o mesmo: título, SC e data.
        $this->assertSame($classico, str_replace('_COMPLETO', '', $completo));
    }

    private function servico(): MapaExportService
    {
        return new MapaExportService(new MapaCalculoService);
    }

}
