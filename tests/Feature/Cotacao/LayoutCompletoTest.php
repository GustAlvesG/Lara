<?php

namespace Tests\Feature\Cotacao;

use App\Models\CotacaoMapa;
use App\Models\CotacaoMapaItem;
use App\Services\Cotacao\MapaCalculoService;
use App\Services\Cotacao\MapaExportService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\CreatesCotacaoSchema;
use Tests\Concerns\CriaMapaDeCotacao;
use Tests\TestCase;

/**
 * O XLSX de análise — o layout `completo`.
 *
 * Ele existe ao lado do clássico, não no lugar dele: o clássico é o papel da
 * reunião e não pode ganhar coluna nova; este é o arquivo que se abre no
 * computador para decidir.
 *
 * O QUE ESTES TESTES PROTEGEM, em ordem de importância:
 *
 * 1. **Os totais continuam sendo fórmula.** Um arquivo bonito com números
 *    congelados é pior que um feio que recalcula: quem corrige um preço vê o
 *    total não mudar e decide pelo valor velho.
 *
 * 2. **Célula vazia não vira zero.** `COUNT` e `SUMPRODUCT` ignoram texto e
 *    vazio, e as fórmulas de menor preço, cobertura e total dependem disso.
 *
 * 3. **As abas não se contradizem.** O Resumo aponta para a aba Mapa por
 *    fórmula em vez de refazer a conta; se cada uma fizesse a sua, uma edição
 *    na grade faria as duas discordarem.
 */
class LayoutCompletoTest extends TestCase
{
    use CreatesCotacaoSchema;
    use CriaMapaDeCotacao;

    /** Onde a grade começa: cabeçalho na 10, itens da 11 em diante. */
    private const LINHA_CABECALHO = 10;

    private const PRIMEIRA_ITEM = 11;

    /** 3 itens => 11..13; 2 fornecedores => F e G; H = MENOR, I = ECONOMIA. */
    private const ULTIMA_ITEM = 13;

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

    public function test_o_arquivo_tem_as_quatro_abas_na_ordem_de_uso(): void
    {
        $planilha = $this->servico()->montar($this->mapaDeExemplo(), MapaExportService::LAYOUT_COMPLETO);

        $this->assertSame(
            ['Mapa', 'Resumo', 'Histórico', 'Decisão'],
            $planilha->getSheetNames()
        );

        // Abre na grade: é o documento principal.
        $this->assertSame(0, $planilha->getActiveSheetIndex());

        $planilha->disconnectWorksheets();
    }

    public function test_a_grade_tem_as_colunas_de_analise_que_o_classico_nao_tem(): void
    {
        $aba = $this->mapa();

        $this->assertSame('MAPA DE COTAÇÃO', $aba->getCell('A1')->getValue());
        $this->assertSame('MATERIAL PARA PINTURA DA CERCA DO PARQUINHO', $aba->getCell('A2')->getValue());

        $cabecalho = self::LINHA_CABECALHO;

        $this->assertSame('ITEM', $aba->getCell('A' . $cabecalho)->getValue());
        $this->assertSame('UND', $aba->getCell('B' . $cabecalho)->getValue());
        $this->assertSame('DESCRIÇÃO', $aba->getCell('C' . $cabecalho)->getValue());
        $this->assertSame('QNT.', $aba->getCell('D' . $cabecalho)->getValue());
        // A coluna que o clássico não pode ter, para não fugir do papel.
        $this->assertSame('ÚLT. COMPRA', $aba->getCell('E' . $cabecalho)->getValue());

        $this->assertSame('D C DE ALMEIDA', $aba->getCell('F' . $cabecalho)->getValue());
        $this->assertSame('BARRA COR', $aba->getCell('G' . $cabecalho)->getValue());

        // E as duas de análise, VISÍVEIS — ao contrário da auxiliar escondida
        // do clássico, aqui elas são conteúdo.
        $this->assertSame('MENOR', $aba->getCell('H' . $cabecalho)->getValue());
        $this->assertSame('ECONOMIA', $aba->getCell('I' . $cabecalho)->getValue());
    }

    public function test_identificacao_e_condicoes_por_fornecedor_saem_no_cabecalho(): void
    {
        $aba = $this->mapa();

        $this->assertSame('SC', $aba->getCell('A4')->getValue());
        $this->assertSame('34334', $aba->getCell('B4')->getValue());
        $this->assertSame('SOLICITANTE', $aba->getCell('C4')->getValue());
        $this->assertSame('MANUTENCAO JOAO', $aba->getCell('D4')->getValue());
        $this->assertSame('COMPRADOR', $aba->getCell('C5')->getValue());

        // Linhas 7, 8 e 9: as condições, por coluna de fornecedor.
        $this->assertSame('CIF', $aba->getCell('F7')->getValue());
        $this->assertSame('1 DU', $aba->getCell('F8')->getValue());
        $this->assertSame('14D', $aba->getCell('F9')->getValue());
        $this->assertSame('3DU', $aba->getCell('G8')->getValue());
        $this->assertSame('Á VISTA', $aba->getCell('G9')->getValue());
    }

    public function test_menor_e_economia_sao_formulas_que_ignoram_celula_vazia(): void
    {
        $aba = $this->mapa();
        $l = self::PRIMEIRA_ITEM;

        $menor = $aba->getCell('H' . $l);
        $this->assertSame(DataType::TYPE_FORMULA, $menor->getDataType());
        // COUNT antes de MIN: linha sem nenhum preço dá ZERO em vez de MIN() de
        // nada, que valeria zero por acidente e mentiria no total geral.
        $this->assertSame("=IF(COUNT(F{$l}:G{$l})=0,0,MIN(F{$l}:G{$l}))", $menor->getValue());

        $economia = $aba->getCell('I' . $l);
        $this->assertSame(DataType::TYPE_FORMULA, $economia->getDataType());
        // Só existe economia com os DOIS lados: última compra e cotação.
        $this->assertSame("=IF(OR(\$E{$l}=0,H{$l}=0),0,(\$E{$l}-H{$l})*\$D{$l})", $economia->getValue());
    }

    public function test_rodape_traz_subtotal_total_e_cobertura_como_formula(): void
    {
        $aba = $this->mapa();

        $lFrete = self::ULTIMA_ITEM + 1;      // 14
        $lDesconto = $lFrete + 1;              // 15
        $lSubtotal = $lDesconto + 1;           // 16
        $lTotal = $lSubtotal + 1;              // 17
        $lCobertura = $lTotal + 1;             // 18

        $this->assertSame('FRETE', $aba->getCell('E' . $lFrete)->getValue());
        $this->assertSame('DESCONTO', $aba->getCell('E' . $lDesconto)->getValue());
        $this->assertSame('SUBTOTAL', $aba->getCell('E' . $lSubtotal)->getValue());
        $this->assertSame('TOTAL', $aba->getCell('E' . $lTotal)->getValue());
        $this->assertSame('ITENS COTADOS', $aba->getCell('E' . $lCobertura)->getValue());

        $subtotal = $aba->getCell('F' . $lSubtotal);
        $this->assertSame(DataType::TYPE_FORMULA, $subtotal->getDataType());
        $this->assertSame('=SUMPRODUCT($D$11:$D$13,F11:F13)', $subtotal->getValue());

        // Frete e desconto entram no TOTAL, nunca no SUBTOTAL — e só quando
        // houve cotação: quem não respondeu nada não pode dever frete.
        $total = $aba->getCell('F' . $lTotal);
        $this->assertSame(DataType::TYPE_FORMULA, $total->getDataType());
        $this->assertSame(
            "=IF(F{$lCobertura}=0,0,F{$lSubtotal}+F{$lFrete}-F{$lDesconto})",
            $total->getValue()
        );

        // Cobertura por COUNT: "NT" e vazio ficam de fora, que é a distinção
        // inteira do módulo em uma fórmula.
        $cobertura = $aba->getCell('F' . $lCobertura);
        $this->assertSame(DataType::TYPE_FORMULA, $cobertura->getDataType());
        $this->assertSame('=COUNT(F11:F13)', $cobertura->getValue());

        // Frete e desconto continuam sendo NÚMERO editável, não fórmula.
        $this->assertEqualsWithDelta(50.0, $aba->getCell('F' . $lFrete)->getValue(), 0.0001);
        $this->assertEqualsWithDelta(0.0, $aba->getCell('G' . $lFrete)->getValue(), 0.0001);
    }

    public function test_nt_sai_como_texto_e_sem_resposta_sai_vazio(): void
    {
        $aba = $this->mapa();

        // Item 1 (linha 11): os dois cotaram.
        $this->assertEqualsWithDelta(180.0, $aba->getCell('F11')->getValue(), 0.0001);
        $this->assertEqualsWithDelta(189.43, $aba->getCell('G11')->getValue(), 0.0001);

        // Item 2 (linha 12): BARRA COR não trabalha o verniz.
        $this->assertSame('NT', $aba->getCell('G12')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $aba->getCell('G12')->getDataType());

        // Item 3 (linha 13): sem resposta de um, sem linha nenhuma do outro.
        $this->assertNull($aba->getCell('G13')->getValue());
        $this->assertNull($aba->getCell('F13')->getValue());
    }

    /**
     * A última compra vai como número (zero quando não há) para as fórmulas de
     * economia não terem de tratar texto — o formato é que mostra "—".
     */
    public function test_ultima_compra_entra_como_numero_e_zero_quando_nao_existe(): void
    {
        $aba = $this->mapa();

        $this->assertEqualsWithDelta(200.0, $aba->getCell('E11')->getValue(), 0.0001);
        $this->assertEqualsWithDelta(0.0, $aba->getCell('E12')->getValue(), 0.0001);
        $this->assertEqualsWithDelta(0.0, $aba->getCell('E13')->getValue(), 0.0001);

        // O "—" é formato, não texto: assim a célula continua somável.
        $formato = $aba->getStyle('E11')->getNumberFormat()->getFormatCode();
        $this->assertStringContainsString('"—"', $formato);
    }

    /**
     * O Resumo não refaz a conta: ele aponta para a aba Mapa. Se as duas
     * fizessem a própria, uma edição na grade faria as duas discordarem.
     */
    public function test_o_resumo_aponta_para_a_aba_mapa_em_vez_de_recalcular(): void
    {
        $planilha = $this->servico()->montar($this->mapaDeExemplo(), MapaExportService::LAYOUT_COMPLETO);
        $aba = $planilha->getSheetByName('Resumo');

        $this->assertNotNull($aba);
        $this->assertSame('RESUMO DA COTAÇÃO', $aba->getCell('A1')->getValue());
        $this->assertSame('AS DUAS FORMAS DE COMPRAR', $aba->getCell('A4')->getValue());

        // Comprar dividido: soma do menor de cada linha, lida da aba Mapa.
        $dividido = $aba->getCell('C5');
        $this->assertSame(DataType::TYPE_FORMULA, $dividido->getDataType());
        $this->assertSame('=Mapa!H16', $dividido->getValue());

        // Economia projetada: idem.
        $economia = $aba->getCell('C10');
        $this->assertSame(DataType::TYPE_FORMULA, $economia->getDataType());
        $this->assertSame('=Mapa!I16', $economia->getValue());

        $planilha->disconnectWorksheets();
    }

    /**
     * A distinção que a planilha em Excel não faz: quem cotou só parte NÃO é
     * comparável no total, por mais barato que pareça.
     */
    public function test_o_resumo_marca_quem_nao_cotou_tudo_como_nao_comparavel(): void
    {
        $planilha = $this->servico()->montar($this->mapaDeExemplo(), MapaExportService::LAYOUT_COMPLETO);
        $aba = $planilha->getSheetByName('Resumo');

        $this->assertSame('COBERTURA POR FORNECEDOR', $aba->getCell('A15')->getValue());
        $this->assertSame('FORNECEDOR', $aba->getCell('A16')->getValue());

        // Nenhum dos dois cotou os 3 itens.
        $this->assertSame('D C DE ALMEIDA', $aba->getCell('A17')->getValue());
        $this->assertSame(2, $aba->getCell('B17')->getValue());
        $this->assertStringStartsWith('não', $aba->getCell('F17')->getValue());

        $this->assertSame('BARRA COR', $aba->getCell('A18')->getValue());
        $this->assertSame(1, $aba->getCell('B18')->getValue());
        $this->assertSame(1, $aba->getCell('C18')->getValue());  // 1 NT
        $this->assertSame(1, $aba->getCell('D18')->getValue());  // 1 sem resposta
        $this->assertStringStartsWith('não', $aba->getCell('F18')->getValue());

        $planilha->disconnectWorksheets();
    }

    public function test_o_historico_diz_quando_o_item_nao_tem_cadastro(): void
    {
        $planilha = $this->servico()->montar($this->mapaDeExemplo(), MapaExportService::LAYOUT_COMPLETO);
        $aba = $planilha->getSheetByName('Histórico');

        $this->assertSame('ITEM', $aba->getCell('A4')->getValue());

        // Item 1: última compra 200,00 e melhor cotação 180,00 => -10%.
        $this->assertEqualsWithDelta(200.0, $aba->getCell('G5')->getValue(), 0.0001);
        $this->assertEqualsWithDelta(180.0, $aba->getCell('H5')->getValue(), 0.0001);
        $this->assertEqualsWithDelta(-0.10, $aba->getCell('I5')->getValue(), 0.0001);

        // Item 2 tem cadastro, mas nunca foi comprado.
        $this->assertSame('sem compra anterior', $aba->getCell('D6')->getValue());

        // Item 3 é texto livre na SC: não tem histórico POR CÓDIGO, e dizer
        // isso é melhor que deixar em branco e parecer falha de exportação.
        $this->assertSame('item sem cadastro — sem histórico', $aba->getCell('D7')->getValue());

        $planilha->disconnectWorksheets();
    }

    public function test_a_aba_de_decisao_avisa_quando_nada_foi_escolhido(): void
    {
        $planilha = $this->servico()->montar($this->mapaDeExemplo(), MapaExportService::LAYOUT_COMPLETO);
        $aba = $planilha->getSheetByName('Decisão');

        $this->assertSame('Nenhum item teve o fornecedor escolhido ainda.', $aba->getCell('A3')->getValue());

        $planilha->disconnectWorksheets();
    }

    public function test_a_aba_de_decisao_lista_o_escolhido_e_avisa_o_que_falta(): void
    {
        $mapa = $this->mapaDeExemplo();

        $item = $mapa->itens->firstWhere('questor_cd_item', 1);
        $vencedor = $mapa->fornecedores->firstWhere('nome', 'D C DE ALMEIDA');
        $item->update(['vencedor_id' => $vencedor->id]);

        $planilha = $this->servico()->montar($mapa->fresh(), MapaExportService::LAYOUT_COMPLETO);
        $aba = $planilha->getSheetByName('Decisão');

        $this->assertSame('ITEM', $aba->getCell('A3')->getValue());
        $this->assertSame('TINTA ESMALTE AZUL 3,6L', $aba->getCell('B4')->getValue());
        $this->assertSame('D C DE ALMEIDA', $aba->getCell('E4')->getValue());

        // O total da linha é FÓRMULA apontando para a grade: preço do
        // escolhido × quantidade. Corrigir o preço no Mapa corrige a decisão —
        // um valor estático aqui faria as duas abas discordarem no primeiro
        // ajuste. D C DE ALMEIDA é a coluna F, o item 1 é a linha 11.
        $total = $aba->getCell('F4');
        $this->assertSame(DataType::TYPE_FORMULA, $total->getDataType());
        $this->assertSame('=Mapa!F11*Mapa!$D$11', $total->getValue());
        // 180,00 x 2 unidades.
        $this->assertEqualsWithDelta(360.0, (float) $total->getCalculatedValue(), 0.0001);

        $this->assertSame('TOTAL DECIDIDO', $aba->getCell('E6')->getValue());
        $this->assertSame('=SUM(F4:F4)', $aba->getCell('F6')->getValue());

        // O total é PARCIAL enquanto houver item sem escolha, e o arquivo diz.
        $this->assertStringContainsString('2 item(ns) ainda sem fornecedor', $aba->getCell('A8')->getValue());

        $planilha->disconnectWorksheets();
    }

    /**
     * O teste que importa de verdade: reabrir do disco e conferir que as
     * fórmulas sobreviveram à gravação.
     */
    public function test_as_formulas_sobrevivem_a_gravacao_do_arquivo(): void
    {
        $mapa = $this->mapaDeExemplo();

        $this->arquivo = $this->servico()->gerar($mapa, MapaExportService::LAYOUT_COMPLETO);

        $this->assertFileExists($this->arquivo);
        // Sufixo no nome: os dois arquivos convivem na pasta de downloads, e
        // sem ele o segundo sobrescreveria o primeiro em silêncio.
        $this->assertStringContainsString('_COMPLETO.xlsx', $this->arquivo);

        $planilha = IOFactory::createReader('Xlsx')->load($this->arquivo);
        $aba = $planilha->getSheetByName('Mapa');

        $this->assertSame(DataType::TYPE_FORMULA, $aba->getCell('H11')->getDataType());
        $this->assertSame(DataType::TYPE_FORMULA, $aba->getCell('I11')->getDataType());
        $this->assertSame(DataType::TYPE_FORMULA, $aba->getCell('F16')->getDataType());
        $this->assertSame(DataType::TYPE_FORMULA, $aba->getCell('F17')->getDataType());
        $this->assertSame(DataType::TYPE_FORMULA, $aba->getCell('F18')->getDataType());

        $this->assertSame(DataType::TYPE_FORMULA, $planilha->getSheetByName('Resumo')->getCell('C5')->getDataType());

        $planilha->disconnectWorksheets();
    }

    /**
     * Mapa vazio não pode estourar: o comprador gera o mapa e exporta antes de
     * cotar qualquer coisa, para mandar a grade em branco ao fornecedor.
     */
    public function test_mapa_sem_fornecedor_e_sem_preco_ainda_gera_arquivo_valido(): void
    {
        $mapa = CotacaoMapa::factory()->create(['questor_solicitacao' => 34334]);
        CotacaoMapaItem::factory()->create(['cotacao_mapa_id' => $mapa->id, 'ordem' => 1]);

        $this->arquivo = $this->servico()->gerar($mapa->fresh(), MapaExportService::LAYOUT_COMPLETO);

        $this->assertFileExists($this->arquivo);

        $planilha = IOFactory::createReader('Xlsx')->load($this->arquivo);
        $this->assertSame('MAPA DE COTAÇÃO', $planilha->getSheetByName('Mapa')->getCell('A1')->getValue());
        $planilha->disconnectWorksheets();
    }

    /**
     * O teste mais importante do arquivo: as fórmulas do Excel têm de dar o
     * MESMO número que o {@see MapaCalculoService}.
     *
     * São duas implementações da mesma regra — uma em PHP, que manda na tela, e
     * outra em fórmula, que manda depois que o arquivo sai daqui. É o par que
     * costuma divergir em silêncio, e a divergência só apareceria numa reunião,
     * com o comprador defendendo um número que a tela não mostra.
     */
    public function test_as_formulas_do_excel_dao_o_mesmo_numero_que_o_servico_de_calculo(): void
    {
        $mapa = $this->mapaDeExemplo();

        $esperado = (new MapaCalculoService)->calcular(
            $mapa->itens,
            $mapa->fornecedores,
            $mapa->itens->flatMap(fn (CotacaoMapaItem $i) => $i->precos)
        );

        $this->arquivo = $this->servico()->gerar($mapa, MapaExportService::LAYOUT_COMPLETO);

        $planilha = IOFactory::createReader('Xlsx')->load($this->arquivo);
        $aba = $planilha->getSheetByName('Mapa');

        // Compra dividida: soma do menor de cada linha × quantidade.
        $this->assertEqualsWithDelta(
            $esperado['totais']['melhor_combinacao'],
            (float) $aba->getCell('H16')->getCalculatedValue(),
            0.005,
            'A soma do menor de cada linha divergiu entre a planilha e o serviço.'
        );

        // Economia contra a última compra — negativa é resultado legítimo.
        $this->assertEqualsWithDelta(
            $esperado['totais']['economia'],
            (float) $aba->getCell('I16')->getCalculatedValue(),
            0.005,
            'A economia projetada divergiu entre a planilha e o serviço.'
        );

        // E, coluna a coluna: subtotal, total e cobertura de cada fornecedor.
        $colunas = ['F', 'G'];

        foreach ($mapa->fornecedores as $i => $fornecedor) {
            $coluna = $colunas[$i];
            $dados = $esperado['fornecedores'][(int) $fornecedor->id];

            $this->assertEqualsWithDelta(
                $dados['subtotal'],
                (float) $aba->getCell($coluna . '16')->getCalculatedValue(),
                0.005,
                "Subtotal de {$fornecedor->nome} divergiu."
            );

            $this->assertEqualsWithDelta(
                $dados['total'],
                (float) $aba->getCell($coluna . '17')->getCalculatedValue(),
                0.005,
                "Total de {$fornecedor->nome} divergiu."
            );

            // A cobertura por COUNT: "NT" e vazio não contam como cotação.
            $this->assertSame(
                $dados['itens_cotados'],
                (int) $aba->getCell($coluna . '18')->getCalculatedValue(),
                "Cobertura de {$fornecedor->nome} divergiu."
            );
        }

        $planilha->disconnectWorksheets();
    }

    // ------------------------------------------------------------------

    private function mapa()
    {
        return $this->servico()
            ->montar($this->mapaDeExemplo(), MapaExportService::LAYOUT_COMPLETO)
            ->getSheetByName('Mapa');
    }

    private function servico(): MapaExportService
    {
        return new MapaExportService(new MapaCalculoService);
    }
}
