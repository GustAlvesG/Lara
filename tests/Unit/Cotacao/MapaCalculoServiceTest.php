<?php

namespace Tests\Unit\Cotacao;

use App\Models\CotacaoMapaFornecedor;
use App\Models\CotacaoMapaItem;
use App\Models\CotacaoPreco;
use App\Services\Cotacao\MapaCalculoService;
use PHPUnit\Framework\TestCase;

/**
 * As contas do mapa.
 *
 * `PHPUnit\Framework\TestCase` e não `Tests\TestCase`: o serviço é puro e não
 * toca em banco, cache ou relógio. Os models são montados em memória com
 * `forceFill` — nada é gravado, e o teste roda sem aplicação de pé.
 *
 * É justamente por isso que a classe é pura: estas contas decidem para onde vai
 * dinheiro, e uma conta que só dá para exercitar com banco montado é uma conta
 * que ninguém testa.
 */
class MapaCalculoServiceTest extends TestCase
{
    private MapaCalculoService $servico;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servico = new MapaCalculoService;
    }

    public function test_menor_preco_marca_a_celula_mais_barata_e_a_segunda(): void
    {
        $item = $this->item(1, quantidade: 2);
        $a = $this->fornecedor(10);
        $b = $this->fornecedor(20);
        $c = $this->fornecedor(30);

        $resultado = $this->servico->calcular(
            collect([$item]),
            collect([$a, $b, $c]),
            collect([
                $this->preco($item, $a, 100.0),
                $this->preco($item, $b, 90.0),
                $this->preco($item, $c, 120.0),
            ])
        );

        $linha = $resultado['itens'][1];

        $this->assertSame(90.0, $linha['menor_preco']);
        $this->assertSame([20], $linha['menor_preco_fornecedores']);
        $this->assertFalse($linha['empate']);
        $this->assertSame(100.0, $linha['segundo_menor']);
        $this->assertSame([10], $linha['segundo_menor_fornecedores']);

        $this->assertTrue($linha['celulas'][20]['menor']);
        $this->assertTrue($linha['celulas'][10]['segundo']);
        $this->assertFalse($linha['celulas'][30]['menor']);

        // Quantidade 2: o total da linha é 90 × 2.
        $this->assertSame(180.0, $linha['menor_preco_total']);
    }

    /**
     * Empate não pode ser resolvido por sorteio: as duas colunas ficam
     * marcadas, e o comprador vê que há dois preços iguais.
     */
    public function test_empate_marca_as_duas_colunas_e_o_segundo_e_o_proximo_valor_distinto(): void
    {
        $item = $this->item(1);
        $a = $this->fornecedor(10);
        $b = $this->fornecedor(20);
        $c = $this->fornecedor(30);

        $resultado = $this->servico->calcular(
            collect([$item]),
            collect([$a, $b, $c]),
            collect([
                $this->preco($item, $a, 100.0),
                $this->preco($item, $b, 100.0),
                $this->preco($item, $c, 120.0),
            ])
        );

        $linha = $resultado['itens'][1];

        $this->assertTrue($linha['empate']);
        $this->assertEqualsCanonicalizing([10, 20], $linha['menor_preco_fornecedores']);
        $this->assertTrue($linha['celulas'][10]['menor']);
        $this->assertTrue($linha['celulas'][20]['menor']);

        // O segundo menor é o próximo VALOR distinto (120), não a segunda
        // célula de 100 — que não é pior que a primeira.
        $this->assertSame(120.0, $linha['segundo_menor']);
        $this->assertSame([30], $linha['segundo_menor_fornecedores']);
        $this->assertFalse($linha['celulas'][20]['segundo']);
    }

    /**
     * A regra que a planilha em Excel erra: coluna sem nenhum preço não é
     * coluna com total zero.
     */
    public function test_coluna_sem_nenhum_preco_nao_vira_zero_nem_ganha_o_menor_total(): void
    {
        $item = $this->item(1, quantidade: 3);
        $comPreco = $this->fornecedor(10);
        $vazio = $this->fornecedor(20, frete: 50.0);

        $resultado = $this->servico->calcular(
            collect([$item]),
            collect([$comPreco, $vazio]),
            collect([$this->preco($item, $comPreco, 80.0)])
        );

        $coluna = $resultado['fornecedores'][20];

        $this->assertSame(0.0, $coluna['subtotal']);
        // Frete NÃO entra: um fornecedor que não respondeu nada não pode
        // aparecer no rodapé devendo frete.
        $this->assertSame(0.0, $coluna['total']);
        $this->assertSame(0, $coluna['itens_cotados']);
        $this->assertFalse($coluna['cobertura_completa']);
        $this->assertNull($coluna['total_cheio']);

        // E ele não pode ser eleito "melhor fornecedor único" com total zero.
        $this->assertSame(10, $resultado['totais']['melhor_fornecedor_unico_id']);
        $this->assertSame(240.0, $resultado['totais']['melhor_fornecedor_unico_total']);

        // A célula vazia tem total nulo, não zero.
        $this->assertNull($resultado['itens'][1]['celulas'][20]['total']);
    }

    /**
     * "Não trabalha" e "sem resposta" saem das duas somas, mas contam
     * diferente na cobertura: quem não vende o item não pode ser cobrado por
     * não ter cotado.
     */
    public function test_nao_trabalha_e_sem_resposta_saem_das_somas_e_contam_separado_na_cobertura(): void
    {
        $i1 = $this->item(1);
        $i2 = $this->item(2);
        $i3 = $this->item(3);
        $f = $this->fornecedor(10);

        $resultado = $this->servico->calcular(
            collect([$i1, $i2, $i3]),
            collect([$f]),
            collect([
                $this->preco($i1, $f, 50.0),
                $this->precoNaoTrabalha($i2, $f),
                $this->precoSemResposta($i3, $f),
            ])
        );

        $coluna = $resultado['fornecedores'][10];

        $this->assertSame(50.0, $coluna['subtotal']);
        $this->assertSame(1, $coluna['itens_cotados']);
        $this->assertSame(1, $coluna['itens_nao_trabalha']);
        $this->assertSame(1, $coluna['itens_sem_resposta']);
        $this->assertSame(3, $coluna['itens_total']);
        $this->assertFalse($coluna['cobertura_completa']);

        $this->assertNull($resultado['itens'][2]['celulas'][10]['total']);
        $this->assertNull($resultado['itens'][3]['celulas'][10]['total']);
        $this->assertSame(CotacaoPreco::SITUACAO_NAO_TRABALHA, $resultado['itens'][2]['celulas'][10]['situacao']);
        $this->assertSame(CotacaoPreco::SITUACAO_SEM_RESPOSTA, $resultado['itens'][3]['celulas'][10]['situacao']);
    }

    public function test_frete_zerado_nao_muda_o_total_e_frete_com_valor_entra_so_no_total(): void
    {
        $item = $this->item(1, quantidade: 2);
        $semFrete = $this->fornecedor(10, frete: 0.0);
        $comFrete = $this->fornecedor(20, frete: 35.0);

        $resultado = $this->servico->calcular(
            collect([$item]),
            collect([$semFrete, $comFrete]),
            collect([
                $this->preco($item, $semFrete, 100.0),
                $this->preco($item, $comFrete, 90.0),
            ])
        );

        $this->assertSame(200.0, $resultado['fornecedores'][10]['subtotal']);
        $this->assertSame(200.0, $resultado['fornecedores'][10]['total']);

        // Frete entra no TOTAL, nunca no SUBTOTAL — como no mapa impresso.
        $this->assertSame(180.0, $resultado['fornecedores'][20]['subtotal']);
        $this->assertSame(215.0, $resultado['fornecedores'][20]['total']);

        // Mais barato no item, mais caro no total: é a comparação que o
        // comprador precisa enxergar.
        $this->assertSame(10, $resultado['totais']['melhor_fornecedor_unico_id']);

        // A melhor combinação soma o item pelo menor preço e o frete de quem
        // vai fornecê-lo.
        $this->assertSame(180.0, $resultado['totais']['melhor_combinacao']);
        $this->assertSame(35.0, $resultado['totais']['melhor_combinacao_frete']);
        $this->assertSame(215.0, $resultado['totais']['melhor_combinacao_com_frete']);
    }

    public function test_quantidade_fracionada_multiplica_certo(): void
    {
        $item = $this->item(1, quantidade: 2.5);
        $f = $this->fornecedor(10);

        $resultado = $this->servico->calcular(
            collect([$item]),
            collect([$f]),
            collect([$this->preco($item, $f, 12.4)])
        );

        $this->assertEqualsWithDelta(31.0, $resultado['itens'][1]['celulas'][10]['total'], 0.0001);
        $this->assertEqualsWithDelta(31.0, $resultado['fornecedores'][10]['subtotal'], 0.0001);
    }

    /**
     * Cotação mais cara que a última compra dá economia NEGATIVA — e o número
     * tem de aparecer, não ser escondido nem zerado.
     */
    public function test_economia_negativa_quando_a_cotacao_sai_mais_cara_que_a_ultima_compra(): void
    {
        $item = $this->item(1, quantidade: 4, ultimaCompra: 100.0);
        $f = $this->fornecedor(10);

        $resultado = $this->servico->calcular(
            collect([$item]),
            collect([$f]),
            collect([$this->preco($item, $f, 130.0)])
        );

        $this->assertSame(-120.0, $resultado['totais']['economia']);
        $this->assertSame(-120.0, $resultado['itens'][1]['economia_vs_ultima']);
        $this->assertEqualsWithDelta(0.30, $resultado['itens'][1]['celulas'][10]['variacao_vs_ultima'], 0.0001);
    }

    /**
     * Item sem última compra fica FORA da conta de economia — não entra como
     * zero, que inventaria economia igual ao valor cotado.
     */
    public function test_item_sem_ultima_compra_nao_entra_na_economia(): void
    {
        $comHistorico = $this->item(1, quantidade: 1, ultimaCompra: 100.0);
        $semHistorico = $this->item(2, quantidade: 1);
        $f = $this->fornecedor(10);

        $resultado = $this->servico->calcular(
            collect([$comHistorico, $semHistorico]),
            collect([$f]),
            collect([
                $this->preco($comHistorico, $f, 80.0),
                $this->preco($semHistorico, $f, 500.0),
            ])
        );

        $this->assertSame(20.0, $resultado['totais']['economia']);
        $this->assertSame(1, $resultado['totais']['itens_comparaveis']);
        $this->assertNull($resultado['itens'][2]['economia_vs_ultima']);
        $this->assertNull($resultado['itens'][2]['celulas'][10]['variacao_vs_ultima']);
    }

    public function test_mapa_sem_nenhum_preco_devolve_totais_nulos_e_nao_zeros(): void
    {
        $item = $this->item(1);
        $f = $this->fornecedor(10);

        $resultado = $this->servico->calcular(collect([$item]), collect([$f]), collect());

        $this->assertNull($resultado['itens'][1]['menor_preco']);
        $this->assertSame([], $resultado['itens'][1]['menor_preco_fornecedores']);
        $this->assertNull($resultado['totais']['melhor_combinacao']);
        $this->assertNull($resultado['totais']['economia']);
        $this->assertNull($resultado['totais']['total_decidido']);
        $this->assertFalse($resultado['totais']['cotacao_completa']);
    }

    public function test_total_decidido_soma_apenas_os_itens_com_vencedor_escolhido(): void
    {
        $i1 = $this->item(1, quantidade: 2);
        $i2 = $this->item(2, quantidade: 1);
        $a = $this->fornecedor(10);
        $b = $this->fornecedor(20);

        // O comprador escolheu o mais CARO no item 1 (frete, prazo, relação —
        // o mapa registra a decisão dele, não o mínimo automático).
        $i1->forceFill(['vencedor_id' => 10]);

        $resultado = $this->servico->calcular(
            collect([$i1, $i2]),
            collect([$a, $b]),
            collect([
                $this->preco($i1, $a, 100.0),
                $this->preco($i1, $b, 90.0),
                $this->preco($i2, $a, 40.0),
            ])
        );

        $this->assertSame(200.0, $resultado['totais']['total_decidido']);
        $this->assertSame(1, $resultado['totais']['itens_decididos']);
        $this->assertSame(200.0, $resultado['itens'][1]['vencedor_total']);
        $this->assertNull($resultado['itens'][2]['vencedor_total']);
        $this->assertSame(1, $resultado['fornecedores'][10]['itens_vencidos']);
    }

    /**
     * Cobertura parcial não é comparável com cobertura total — e o campo que a
     * tela usa para comparar (`total_cheio`) fica nulo justamente para impedir
     * a comparação errada.
     */
    public function test_fornecedor_com_cobertura_parcial_nao_disputa_o_melhor_total(): void
    {
        $i1 = $this->item(1);
        $i2 = $this->item(2);
        $completo = $this->fornecedor(10);
        $parcial = $this->fornecedor(20);

        $resultado = $this->servico->calcular(
            collect([$i1, $i2]),
            collect([$completo, $parcial]),
            collect([
                $this->preco($i1, $completo, 100.0),
                $this->preco($i2, $completo, 100.0),
                // Muito mais barato, mas só num dos dois itens.
                $this->preco($i1, $parcial, 10.0),
            ])
        );

        $this->assertSame(10.0, $resultado['fornecedores'][20]['total']);
        $this->assertNull($resultado['fornecedores'][20]['total_cheio']);
        $this->assertSame(200.0, $resultado['fornecedores'][10]['total_cheio']);

        // O "melhor único" é o de 200, não o de 10.
        $this->assertSame(10, $resultado['totais']['melhor_fornecedor_unico_id']);
        $this->assertSame(1, $resultado['totais']['fornecedores_completos']);
    }

    // ------------------------------------------------------------------
    // Fábricas em memória — nada aqui toca em banco.
    // ------------------------------------------------------------------

    private function item(int $id, float $quantidade = 1, ?float $ultimaCompra = null): CotacaoMapaItem
    {
        return (new CotacaoMapaItem)->forceFill([
            'id' => $id,
            'cotacao_mapa_id' => 1,
            'descricao' => "ITEM {$id}",
            'quantidade' => $quantidade,
            'ult_compra_valor' => $ultimaCompra,
            'vencedor_id' => null,
        ]);
    }

    private function fornecedor(int $id, float $frete = 0.0, float $desconto = 0.0): CotacaoMapaFornecedor
    {
        return (new CotacaoMapaFornecedor)->forceFill([
            'id' => $id,
            'cotacao_mapa_id' => 1,
            'nome' => "FORNECEDOR {$id}",
            'valor_frete' => $frete,
            'desconto' => $desconto,
        ]);
    }

    private function preco(CotacaoMapaItem $item, CotacaoMapaFornecedor $fornecedor, float $valor): CotacaoPreco
    {
        return (new CotacaoPreco)->forceFill([
            'cotacao_mapa_item_id' => $item->id,
            'cotacao_mapa_fornecedor_id' => $fornecedor->id,
            'valor_unitario' => $valor,
            'situacao' => CotacaoPreco::SITUACAO_COTADO,
        ]);
    }

    private function precoNaoTrabalha(CotacaoMapaItem $item, CotacaoMapaFornecedor $fornecedor): CotacaoPreco
    {
        return (new CotacaoPreco)->forceFill([
            'cotacao_mapa_item_id' => $item->id,
            'cotacao_mapa_fornecedor_id' => $fornecedor->id,
            'valor_unitario' => null,
            'situacao' => CotacaoPreco::SITUACAO_NAO_TRABALHA,
        ]);
    }

    private function precoSemResposta(CotacaoMapaItem $item, CotacaoMapaFornecedor $fornecedor): CotacaoPreco
    {
        return (new CotacaoPreco)->forceFill([
            'cotacao_mapa_item_id' => $item->id,
            'cotacao_mapa_fornecedor_id' => $fornecedor->id,
            'valor_unitario' => null,
            'situacao' => CotacaoPreco::SITUACAO_SEM_RESPOSTA,
        ]);
    }
}
