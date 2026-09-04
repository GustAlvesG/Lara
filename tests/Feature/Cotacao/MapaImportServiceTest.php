<?php

namespace Tests\Feature\Cotacao;

use App\Exceptions\CotacaoException;
use App\Models\CotacaoMapa;
use App\Models\CotacaoMapaItem;
use App\Models\CotacaoMapaLog;
use App\Models\User;
use App\Services\Cotacao\MapaImportService;
use App\Services\Questor\DTO\FornecedorHistoricoDTO;
use App\Services\Questor\DTO\SolicitacaoDTO;
use App\Services\Questor\DTO\SolicitacaoItemDTO;
use App\Services\Questor\DTO\UltimaCompraDTO;
use App\Services\Questor\QuestorCompraRepository;
use App\Services\Questor\QuestorSolicitacaoRepository;
use Mockery;
use Tests\Concerns\CreatesCotacaoSchema;
use Tests\TestCase;

/**
 * A importação de uma Solicitação de Compra para dentro do mapa.
 *
 * O Questor é substituído por mock — e não é só conveniência de teste: é a
 * prova de que a importação depende apenas do contrato dos repositórios, que é
 * o que permite exercitar aqui os casos que ninguém consegue reproduzir na base
 * de homologação sob demanda (item sem cadastro, SC inexistente, ERP fora).
 *
 * O usuário é montado em memória com `forceFill`: `App\Models\User` fixa a
 * conexão `mysql`, e salvá-lo levaria a suíte para fora do SQLite. A
 * importação só lê `id` e `name` dele, então nada é consultado.
 */
class MapaImportServiceTest extends TestCase
{
    use CreatesCotacaoSchema;

    private QuestorSolicitacaoRepository $solicitacoes;

    private QuestorCompraRepository $compras;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCotacaoSchema();

        $this->solicitacoes = Mockery::mock(QuestorSolicitacaoRepository::class);
        $this->compras = Mockery::mock(QuestorCompraRepository::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_importar_solicitacao_inexistente_diz_qual_numero_nao_foi_achado(): void
    {
        $this->solicitacoes->shouldReceive('buscar')->with(34334)->once()->andReturn(null);
        $this->compras->shouldNotReceive('ultimaCompraPorItem');

        $this->expectException(CotacaoException::class);
        $this->expectExceptionMessage('Solicitação 34334 não encontrada no Questor.');

        $this->servico()->importar(34334, $this->comprador());
    }

    public function test_solicitacao_sem_itens_nao_vira_mapa(): void
    {
        $this->solicitacoes->shouldReceive('buscar')->andReturn($this->solicitacao());
        $this->solicitacoes->shouldReceive('itens')->andReturn(collect());

        $this->expectException(CotacaoException::class);
        $this->expectExceptionMessage('não tem itens');

        $this->servico()->importar(34334, $this->comprador());

        $this->assertDatabaseCount('cotacao_mapas', 0);
    }

    /**
     * A armadilha nº 1 do módulo: CD_MATERIAL é NULL-able na solicitação.
     * O item entra no mapa normalmente, só sem retrato de última compra.
     */
    public function test_item_sem_cadastro_no_questor_entra_no_mapa_sem_historico(): void
    {
        $this->solicitacoes->shouldReceive('buscar')->andReturn($this->solicitacao());
        $this->solicitacoes->shouldReceive('itens')->andReturn(collect([
            $this->item(1, material: 5001, descricao: 'TINTA ESMALTE AZUL 3,6L'),
            // Digitado como texto livre: sem CD_MATERIAL.
            $this->item(2, material: null, descricao: 'PARAFUSO ESPECIAL SOB MEDIDA'),
        ]));

        $this->compras->shouldReceive('ultimaCompraPorItem')->andReturn(collect([
            1 => $this->ultimaCompra(1, 5001, 189.43, 'BARRA COR'),
            // O OUTER APPLY devolve a linha do item mesmo sem compra: valores nulos.
            2 => $this->ultimaCompra(2, null, null, null),
        ]));

        $mapa = $this->servico()->importar(34334, $this->comprador());

        $this->assertCount(2, $mapa->itens);

        $comCadastro = $mapa->itens->firstWhere('questor_cd_item', 1);
        $this->assertSame(5001, $comCadastro->questor_cd_material);
        $this->assertTrue($comCadastro->temCadastroNoQuestor());
        $this->assertTrue($comCadastro->temUltimaCompra());
        $this->assertSame('BARRA COR', $comCadastro->ult_compra_fornecedor_nome);
        $this->assertEqualsWithDelta(189.43, (float) $comCadastro->ult_compra_valor, 0.0001);

        $semCadastro = $mapa->itens->firstWhere('questor_cd_item', 2);
        $this->assertNull($semCadastro->questor_cd_material);
        $this->assertFalse($semCadastro->temCadastroNoQuestor());
        $this->assertFalse($semCadastro->temUltimaCompra());
        $this->assertNull($semCadastro->ult_compra_valor);
        $this->assertNull($semCadastro->ult_compra_fornecedor_nome);

        // E a linha existe de verdade — não foi descartada por não ter código.
        $this->assertDatabaseCount('cotacao_mapa_itens', 2);
    }

    public function test_importacao_grava_retrato_do_cabecalho_e_deixa_registro_na_trilha(): void
    {
        $this->solicitacoes->shouldReceive('buscar')->andReturn($this->solicitacao());
        $this->solicitacoes->shouldReceive('itens')->andReturn(collect([$this->item(1, 5001, 'TINTA')]));
        $this->compras->shouldReceive('ultimaCompraPorItem')->andReturn(collect());

        $mapa = $this->servico()->importar(34334, $this->comprador());

        $this->assertSame(34334, $mapa->questor_solicitacao);
        $this->assertSame(1, $mapa->questor_empresa);
        $this->assertSame(2, $mapa->questor_filial);
        // Retratos de texto: a SC pode mudar no ERP, o mapa impresso não.
        $this->assertSame('MANUTENCAO JOAO', $mapa->solicitante);
        $this->assertSame('MANUTENÇÃO', $mapa->departamento);
        $this->assertSame(CotacaoMapa::STATUS_RASCUNHO, $mapa->status);
        // Sem título informado, o da observação da SC — que é onde o
        // solicitante escreve o "para quê" da compra.
        $this->assertStringContainsString('PINTURA DA CERCA', $mapa->titulo);

        $log = CotacaoMapaLog::where('cotacao_mapa_id', $mapa->id)
            ->where('acao', CotacaoMapaLog::ACAO_IMPORTACAO)
            ->firstOrFail();

        $this->assertSame(34334, $log->payload['solicitacao']);
        $this->assertSame(1, $log->payload['itens']);
        $this->assertSame('Comprador de Teste', $log->user_nome);
    }

    public function test_segunda_importacao_da_mesma_sc_aponta_para_o_mapa_que_ja_existe(): void
    {
        $existente = CotacaoMapa::factory()->emCotacao()->create(['questor_solicitacao' => 34334]);

        $this->solicitacoes->shouldNotReceive('buscar');

        try {
            $this->servico()->importar(34334, $this->comprador());
            $this->fail('A segunda importação deveria ter sido recusada.');
        } catch (CotacaoException $e) {
            // O mapa vem junto na exceção — é o que permite à tela oferecer
            // "abrir o existente" em vez de só recusar.
            $this->assertNotNull($e->mapa);
            $this->assertSame($existente->id, $e->mapa->id);
            $this->assertStringContainsString('Já existe um mapa', $e->getMessage());
        }

        $this->assertDatabaseCount('cotacao_mapas', 1);
    }

    /**
     * Cancelado não ocupa a solicitação: o comprador precisa poder recomeçar
     * depois de descartar uma cotação.
     */
    public function test_mapa_cancelado_nao_impede_nova_importacao_da_mesma_sc(): void
    {
        CotacaoMapa::factory()->create([
            'questor_solicitacao' => 34334,
            'status' => CotacaoMapa::STATUS_CANCELADO,
        ]);

        $this->solicitacoes->shouldReceive('buscar')->andReturn($this->solicitacao());
        $this->solicitacoes->shouldReceive('itens')->andReturn(collect([$this->item(1, 5001, 'TINTA')]));
        $this->compras->shouldReceive('ultimaCompraPorItem')->andReturn(collect());

        $novo = $this->servico()->importar(34334, $this->comprador());

        $this->assertSame(CotacaoMapa::STATUS_RASCUNHO, $novo->status);
        $this->assertDatabaseCount('cotacao_mapas', 2);
    }

    public function test_colunas_escolhidas_pelo_comprador_viram_fornecedores_na_ordem_marcada(): void
    {
        $this->solicitacoes->shouldReceive('buscar')->andReturn($this->solicitacao());
        $this->solicitacoes->shouldReceive('itens')->andReturn(collect([$this->item(1, 5001, 'TINTA')]));
        $this->compras->shouldReceive('ultimaCompraPorItem')->andReturn(collect());
        $this->compras->shouldReceive('fornecedoresDaSolicitacao')->andReturn(collect([
            $this->fornecedorHistorico(701, 'BARRA COR'),
            $this->fornecedorHistorico(702, 'NTN TINTAS'),
            $this->fornecedorHistorico(703, 'DARC TINTAS'),
        ]));

        $mapa = $this->servico()->importar(
            34334,
            $this->comprador(),
            fornecedoresSugeridos: [703, 701]
        );

        $this->assertCount(2, $mapa->fornecedores);
        $this->assertSame(['DARC TINTAS', 'BARRA COR'], $mapa->fornecedores->pluck('nome')->all());
        $this->assertSame([1, 2], $mapa->fornecedores->pluck('ordem')->all());
        $this->assertSame(703, $mapa->fornecedores->first()->questor_cd_entidade);
    }

    /**
     * Nenhum fornecedor é criado sozinho: o mapa nasceria com vinte colunas se
     * a sugestão virasse coluna automaticamente.
     */
    public function test_sem_escolha_do_comprador_o_mapa_nasce_sem_colunas(): void
    {
        $this->solicitacoes->shouldReceive('buscar')->andReturn($this->solicitacao());
        $this->solicitacoes->shouldReceive('itens')->andReturn(collect([$this->item(1, 5001, 'TINTA')]));
        $this->compras->shouldReceive('ultimaCompraPorItem')->andReturn(collect());
        $this->compras->shouldNotReceive('fornecedoresDaSolicitacao');

        $mapa = $this->servico()->importar(34334, $this->comprador());

        $this->assertCount(0, $mapa->fornecedores);
    }

    public function test_atualizar_historico_regrava_o_retrato_e_registra_a_acao(): void
    {
        $mapa = CotacaoMapa::factory()->create(['questor_solicitacao' => 34334]);

        $item = CotacaoMapaItem::factory()->create([
            'cotacao_mapa_id' => $mapa->id,
            'questor_cd_item' => 1,
            'questor_cd_material' => 5001,
            'ult_compra_valor' => 100.00,
            'ult_compra_fornecedor_nome' => 'FORNECEDOR ANTIGO',
        ]);

        $this->compras->shouldReceive('ultimaCompraPorItem')->with(34334)->once()->andReturn(collect([
            1 => $this->ultimaCompra(1, 5001, 145.90, 'FORNECEDOR NOVO'),
        ]));

        $alterados = $this->servico()->atualizarHistorico($mapa->fresh(), $this->comprador());

        $this->assertSame(1, $alterados);

        $item->refresh();
        $this->assertEqualsWithDelta(145.90, (float) $item->ult_compra_valor, 0.0001);
        $this->assertSame('FORNECEDOR NOVO', $item->ult_compra_fornecedor_nome);

        $this->assertDatabaseHas('cotacao_mapa_logs', [
            'cotacao_mapa_id' => $mapa->id,
            'acao' => CotacaoMapaLog::ACAO_ATUALIZOU_HISTORICO,
        ]);
    }

    /**
     * Um item que perdeu a referência não pode ficar com o retrato antigo: o
     * mapa passaria a comparar contra uma compra que o filtro já não considera.
     */
    public function test_atualizar_historico_limpa_o_retrato_quando_o_item_deixa_de_ter_compra(): void
    {
        $mapa = CotacaoMapa::factory()->create(['questor_solicitacao' => 34334]);

        $item = CotacaoMapaItem::factory()->create([
            'cotacao_mapa_id' => $mapa->id,
            'questor_cd_item' => 1,
            'ult_compra_valor' => 100.00,
            'ult_compra_fornecedor_nome' => 'FORNECEDOR ANTIGO',
        ]);

        $this->compras->shouldReceive('ultimaCompraPorItem')->andReturn(collect([
            1 => $this->ultimaCompra(1, 5001, null, null),
        ]));

        $this->servico()->atualizarHistorico($mapa->fresh(), $this->comprador());

        $item->refresh();
        $this->assertNull($item->ult_compra_valor);
        $this->assertNull($item->ult_compra_fornecedor_nome);
    }

    public function test_mapa_fechado_nao_aceita_atualizacao_de_historico(): void
    {
        $mapa = CotacaoMapa::factory()->fechado()->create();

        $this->compras->shouldNotReceive('ultimaCompraPorItem');

        $this->expectException(CotacaoException::class);
        $this->expectExceptionMessage('não aceita alterações');

        $this->servico()->atualizarHistorico($mapa, $this->comprador());
    }

    public function test_acrescentar_fornecedor_respeita_o_teto_de_colunas(): void
    {
        config()->set('questor.cotacao.max_fornecedores', 2);

        $mapa = CotacaoMapa::factory()->create();
        $servico = $this->servico();

        $servico->acrescentarFornecedor($mapa, ['nome' => 'UM'], $this->comprador());
        $servico->acrescentarFornecedor($mapa->fresh(), ['nome' => 'DOIS'], $this->comprador());

        $this->expectException(CotacaoException::class);
        $this->expectExceptionMessage('limite configurado');

        $servico->acrescentarFornecedor($mapa->fresh(), ['nome' => 'TRÊS'], $this->comprador());
    }

    /**
     * A alteração pedida sobre o desenho original: dentro da Lara dá para
     * acrescentar à cotação um fornecedor que não existe no cadastro do ERP.
     */
    public function test_fornecedor_fora_do_cadastro_do_questor_entra_na_cotacao(): void
    {
        $mapa = CotacaoMapa::factory()->create();

        $fornecedor = $this->servico()->acrescentarFornecedor($mapa, [
            'nome' => 'SERRALHERIA DO BAIRRO',
            'questor_cd_entidade' => null,
            'telefone' => '2199999-0000',
        ], $this->comprador());

        $this->assertNull($fornecedor->questor_cd_entidade);
        $this->assertSame('SERRALHERIA DO BAIRRO', $fornecedor->nome);
        $this->assertSame(1, $fornecedor->ordem);

        $this->assertDatabaseHas('cotacao_mapa_logs', [
            'cotacao_mapa_id' => $mapa->id,
            'acao' => CotacaoMapaLog::ACAO_FORNECEDOR,
        ]);
    }

    // ------------------------------------------------------------------

    private function servico(): MapaImportService
    {
        return new MapaImportService($this->solicitacoes, $this->compras);
    }

    private function comprador(): User
    {
        // `forceFill` e não `create`: o model User fixa a conexão `mysql`.
        // A importação só lê `id` e `name`, então nada é consultado.
        return (new User)->forceFill(['id' => 7, 'name' => 'Comprador de Teste']);
    }

    private function solicitacao(): SolicitacaoDTO
    {
        return SolicitacaoDTO::deLinha((object) [
            'CD_SOLICITACAO' => 34334,
            'CD_EMPRESA' => 1,
            'CD_FILIAL' => 2,
            'DS_FILIAL' => 'MATRIZ',
            'DS_SOLICITANTE' => 'MANUTENCAO JOAO',
            'CD_DEPARTAMENTO' => 9,
            'DS_DEPARTAMENTO' => 'MANUTENÇÃO',
            'DT_CADASTRO' => '2026-08-30 10:00:00',
            'DT_FINALIZACAO' => null,
            'DS_OBS' => 'Material para pintura da cerca do parquinho',
            'CD_STATUS' => 1,
            'DS_STATUS' => 'PENDENTE',
            'DS_OBRA' => null,
            'USUARIO_CADASTRO' => 'joao',
            'QTD_ITENS' => 2,
        ]);
    }

    private function item(int $cdItem, ?int $material, string $descricao): SolicitacaoItemDTO
    {
        return SolicitacaoItemDTO::deLinha((object) [
            'CD_SOLICITACAO' => 34334,
            'CD_ITEM' => $cdItem,
            'CD_MATERIAL' => $material,
            'DS_MATERIAL' => $descricao,
            'DS_UNIDADE' => 'UN',
            'NR_QUANTIDADE' => 1,
            'VL_UNITARIO' => 0,
            'DS_OBS' => null,
            'DS_ALMOXARIFADO' => null,
            'CD_CENTRO_CUSTO' => null,
            'NR_ESTOQUE_DISPONIVEL' => null,
            'VL_CUSTO_MEDIO' => null,
            'DT_ULTIMA_COMPRA_FILIAL' => null,
            'VL_ULTIMA_COMPRA_FILIAL' => null,
        ]);
    }

    private function ultimaCompra(int $cdItem, ?int $material, ?float $valor, ?string $fornecedor): UltimaCompraDTO
    {
        return UltimaCompraDTO::deLinha((object) [
            'CD_ITEM' => $cdItem,
            'CD_MATERIAL' => $material,
            'ULT_COMPRA_DATA' => $valor === null ? null : '2026-05-12',
            'ULT_COMPRA_NF' => $valor === null ? null : '55123',
            'ULT_COMPRA_CD_FORNECEDOR' => $valor === null ? null : 701,
            'ULT_COMPRA_FORNECEDOR' => $fornecedor,
            'ULT_COMPRA_FORNECEDOR_FANTASIA' => null,
            'ULT_COMPRA_CNPJ' => null,
            'ULT_COMPRA_QTD' => 1,
            'ULT_COMPRA_UN' => 'UN',
            'ULT_COMPRA_VL_UNITARIO' => $valor,
            'ULT_COMPRA_VL_CUSTO' => $valor,
            'ULT_COMPRA_OPERACAO' => 'COMPRA',
            'ULT_COMPRA_FILIAL' => 2,
            'VARIACAO_VS_CUSTO_MEDIO' => null,
        ]);
    }

    private function fornecedorHistorico(int $codigo, string $nome): FornecedorHistoricoDTO
    {
        return FornecedorHistoricoDTO::deLinha((object) [
            'CD_ITEM' => 1,
            'CD_MATERIAL' => 5001,
            'CD_FORNECEDOR' => $codigo,
            'DS_ENTIDADE' => $nome,
            'DS_FANTASIA' => null,
            'NR_CPFCNPJ' => '00.000.000/0001-00',
            'NR_TELEFONE' => '2130000000',
            'DS_EMAIL' => 'contato@exemplo.com',
            'DS_EMAIL_ORD_COMPRA' => null,
            'X_ATIVO' => 1,
            'QTD_COMPRAS' => 3,
            'DT_ULTIMA' => '2026-06-01',
            'VL_UNIT_ULTIMO' => 100.0,
            'VL_UNIT_MEDIO' => 105.0,
            'VL_UNIT_MIN' => 95.0,
        ]);
    }
}
