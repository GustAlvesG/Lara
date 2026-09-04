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
        $mapa = $this->mapaCompleto();

        $aba = $this->servico()->montar($mapa)->getSheet(0);

        $this->assertSame('COTAÇÃO DE COMPRAS', $aba->getCell('A1')->getValue());
        $this->assertSame($mapa->titulo, $aba->getCell('C1')->getValue());
        $this->assertSame('SOLICITANTE', $aba->getCell('A3')->getValue());
        $this->assertStringStartsWith('DATA: ', $aba->getCell('C3')->getValue());
        $this->assertSame('MANUTENÇÃO', $aba->getCell('A4')->getValue());
        $this->assertSame('SC: 34334', $aba->getCell('C4')->getValue());

        // Linhas 5, 6 e 7: condições por fornecedor, a partir da coluna E.
        $this->assertSame('CIF', $aba->getCell('E5')->getValue());
        $this->assertSame('1 DU', $aba->getCell('E6')->getValue());
        $this->assertSame('14D', $aba->getCell('E7')->getValue());

        // Linha 8: cabeçalho da grade.
        $this->assertSame('ITEM SC', $aba->getCell('A8')->getValue());
        $this->assertSame('UND MED', $aba->getCell('B8')->getValue());
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
        $mapa = $this->mapaCompleto();

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
        $this->assertSame('=E13+E12', $total->getValue());

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
        $mapa = $this->mapaCompleto();

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
        $mapa = $this->mapaCompleto();

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
        $mapa = $this->mapaCompleto();

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

    private function servico(): MapaExportService
    {
        return new MapaExportService(new MapaCalculoService);
    }

    /**
     * Um mapa com a mesma forma do modelo real: dois fornecedores, três itens,
     * e as três situações de célula representadas.
     */
    private function mapaCompleto(): CotacaoMapa
    {
        $mapa = CotacaoMapa::factory()->emCotacao()->create([
            'questor_solicitacao' => 34334,
            'titulo' => 'MATERIAL PARA PINTURA DA CERCA DO PARQUINHO',
            'departamento' => 'MANUTENÇÃO',
            'solicitante' => 'MANUTENCAO JOAO',
        ]);

        $almeida = CotacaoMapaFornecedor::factory()->create([
            'cotacao_mapa_id' => $mapa->id,
            'nome' => 'D C DE ALMEIDA',
            'frete' => 'CIF',
            'prazo_entrega' => '1 DU',
            'condicao_pagamento' => '14D',
            'valor_frete' => 50.00,
            'ordem' => 1,
        ]);

        $barra = CotacaoMapaFornecedor::factory()->create([
            'cotacao_mapa_id' => $mapa->id,
            'nome' => 'BARRA COR',
            'frete' => 'CIF',
            'prazo_entrega' => '3DU',
            'condicao_pagamento' => 'Á VISTA',
            'valor_frete' => 0,
            'ordem' => 2,
        ]);

        $i1 = CotacaoMapaItem::factory()->create([
            'cotacao_mapa_id' => $mapa->id,
            'questor_cd_item' => 1,
            'questor_cd_material' => 5001,
            'descricao' => 'TINTA ESMALTE AZUL 3,6L',
            'unidade' => 'UN',
            'quantidade' => 2,
            'ordem' => 1,
            'ult_compra_valor' => 200.00,
            'ult_compra_data' => '2026-05-12',
            'ult_compra_fornecedor_nome' => 'BARRA COR',
            'ult_compra_nf' => '55123',
        ]);

        $i2 = CotacaoMapaItem::factory()->create([
            'cotacao_mapa_id' => $mapa->id,
            'questor_cd_item' => 2,
            'questor_cd_material' => 5002,
            'descricao' => 'VERNIZ BASE SOLVENTE IMBUIA 3,6L',
            'unidade' => 'UN',
            'quantidade' => 1,
            'ordem' => 2,
        ]);

        $i3 = CotacaoMapaItem::factory()->semCadastro()->create([
            'cotacao_mapa_id' => $mapa->id,
            'questor_cd_item' => 3,
            'descricao' => 'PARAFUSO ESPECIAL SOB MEDIDA',
            'unidade' => 'UN',
            'quantidade' => 4,
            'ordem' => 3,
        ]);

        CotacaoPreco::factory()->create([
            'cotacao_mapa_item_id' => $i1->id,
            'cotacao_mapa_fornecedor_id' => $almeida->id,
            'valor_unitario' => 180.00,
        ]);

        CotacaoPreco::factory()->create([
            'cotacao_mapa_item_id' => $i1->id,
            'cotacao_mapa_fornecedor_id' => $barra->id,
            'valor_unitario' => 189.43,
        ]);

        CotacaoPreco::factory()->create([
            'cotacao_mapa_item_id' => $i2->id,
            'cotacao_mapa_fornecedor_id' => $almeida->id,
            'valor_unitario' => 161.86,
        ]);

        // O "NT" da planilha: BARRA COR não vende o verniz.
        CotacaoPreco::factory()->naoTrabalha()->create([
            'cotacao_mapa_item_id' => $i2->id,
            'cotacao_mapa_fornecedor_id' => $barra->id,
        ]);

        // Item 3: consultado e sem resposta de um, sem linha nenhuma do outro.
        CotacaoPreco::factory()->semResposta()->create([
            'cotacao_mapa_item_id' => $i3->id,
            'cotacao_mapa_fornecedor_id' => $barra->id,
        ]);

        return $mapa->fresh();
    }
}
