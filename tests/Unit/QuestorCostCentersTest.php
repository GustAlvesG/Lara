<?php

namespace Tests\Unit;

use App\Services\Questor\QuestorCostCenters;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Os centros de custo de uma ordem.
 *
 * O que está sob teste é a distinção que decide quem aprova: `codigos` são os
 * centros de custo encontrados nos itens, e `sem_centro_custo` diz se **algum**
 * item veio sem nenhum. Quase um terço das ordens pendentes cai nesse segundo
 * caso, e para elas não há diretor sugerido — quem escolhe é a Gerência. Se
 * essa flag se perder no caminho, a tela do gerente mostra uma lista vazia sem
 * dizer por quê, e a ordem trava sem responsável.
 *
 * Sem banco: a conexão do Questor é um duble.
 */
class QuestorCostCentersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'questor.enabled' => true,
            'questor.connection' => 'questor_sqlsrv',
            'questor.database' => 'FUNCSIDERURG',
            'questor.schema' => 'dbo',
            'questor.status.pendente' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /** @param array<int, int|null> $centros valores de CD_CENTRO_CUSTO nos itens */
    private function comItens(array $centros): QuestorCostCenters
    {
        $conexao = Mockery::mock();
        $conexao->shouldReceive('select')->andReturn(
            array_map(fn($c) => (object) ['CD_CENTRO_CUSTO' => $c], $centros)
        );

        DB::shouldReceive('connection')->andReturn($conexao);

        return new QuestorCostCenters;
    }

    public function test_ordem_de_um_centro_de_custo(): void
    {
        $resultado = $this->comItens([75, 75, 75])->forOrder(3);

        // Três itens do mesmo centro de custo são um aprovador, não três.
        $this->assertSame([75], $resultado['codigos']);
        $this->assertFalse($resultado['sem_centro_custo']);
    }

    public function test_ordem_com_varios_centros_de_custo(): void
    {
        $resultado = $this->comItens([75, 88, 75, 70])->forOrder(3);

        $this->assertSame([75, 88, 70], $resultado['codigos']);
        $this->assertFalse($resultado['sem_centro_custo']);
    }

    public function test_item_sem_centro_de_custo_e_sinalizado(): void
    {
        $resultado = $this->comItens([75, null])->forOrder(3);

        // O centro de custo que existe continua valendo, E a ordem é marcada:
        // ela precisa do diretor do 75 e de uma escolha do gerente para a parte
        // que não tem centro nenhum.
        $this->assertSame([75], $resultado['codigos']);
        $this->assertTrue($resultado['sem_centro_custo']);
    }

    public function test_ordem_inteiramente_sem_centro_de_custo(): void
    {
        $resultado = $this->comItens([null, null])->forOrder(3);

        $this->assertSame([], $resultado['codigos']);
        $this->assertTrue($resultado['sem_centro_custo']);
    }

    public function test_ordem_sem_itens(): void
    {
        $resultado = $this->comItens([])->forOrder(3);

        // Nenhum item não é o mesmo que item sem centro de custo: não há o que
        // o gerente complete, e a ordem não deveria estar na fila.
        $this->assertSame([], $resultado['codigos']);
        $this->assertFalse($resultado['sem_centro_custo']);
    }
}
