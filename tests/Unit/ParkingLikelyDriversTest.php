<?php

namespace Tests\Unit;

use App\Services\Parking\LikelyDrivers;
use PHPUnit\Framework\TestCase;

/**
 * SIV — "Condutores mais prováveis". Código puro, sem framework.
 *
 * O erro que motivou a classe (16/11/2026): o controller dividia as aparições
 * pelo número de pessoas diferentes, e 8 aparições ÷ 2 pessoas viravam 400%.
 */
class ParkingLikelyDriversTest extends TestCase
{
    private function socio(string $name, string $tel = '(21) 99999-0000'): array
    {
        return ['name' => $name, 'telephone' => $tel];
    }

    public function test_percentual_e_a_fatia_no_total_de_aparicoes_e_soma_100(): void
    {
        $joao = $this->socio('João');
        $maria = $this->socio('Maria', '(21) 98888-0000');
        $readings = array_merge(array_fill(0, 8, [$joao]), array_fill(0, 2, [$maria]));

        $result = LikelyDrivers::rank($readings);

        $this->assertSame(10, $result['total_readings']);
        $this->assertSame(10, $result['total_appearances']);
        $this->assertSame(['João', 'Maria'], array_column($result['people'], 'name'));
        $this->assertSame([80.0, 20.0], array_column($result['people'], 'share'));
        $this->assertSame([8, 2], array_column($result['people'], 'readings'));
    }

    public function test_devolve_todas_as_pessoas_inclusive_as_de_baixa_frequencia(): void
    {
        $readings = [];
        foreach (range(1, 6) as $i) {
            $readings[] = [$this->socio('Fixo'), $this->socio("Passante {$i}", "tel {$i}")];
        }

        $result = LikelyDrivers::rank($readings);

        $this->assertCount(7, $result['people']);
        $this->assertSame('Fixo', $result['people'][0]['name']);
        $this->assertSame(50.0, $result['people'][0]['share']);
        $this->assertSame(8.3, $result['people'][6]['share']);
        $this->assertEqualsWithDelta(100.0, array_sum(array_column($result['people'], 'share')), 0.5);
    }

    public function test_mesma_pessoa_duas_vezes_na_mesma_leitura_conta_uma(): void
    {
        $result = LikelyDrivers::rank([[$this->socio('Ana'), $this->socio('Ana')], [$this->socio('Bia', 'x')]]);

        $this->assertSame(2, $result['total_appearances']);
        $this->assertSame([1, 1], array_column($result['people'], 'readings'));
        $this->assertSame([50.0, 50.0], array_column($result['people'], 'share'));
    }

    public function test_externo_guarda_o_tipo_e_e_outra_pessoa_que_o_socio_homonimo(): void
    {
        $result = LikelyDrivers::rank([
            [['name' => 'Bruno', 'telephone' => '1', 'label' => 'Terceirizado']],
            [['name' => 'Bruno', 'telephone' => '1', 'label' => 'Terceirizado'], ['name' => 'Bruno', 'telephone' => '1']],
        ]);

        $this->assertCount(2, $result['people']);
        $this->assertSame('Terceirizado', $result['people'][0]['label']);
        $this->assertSame(2, $result['people'][0]['readings']);
        $this->assertSame('', $result['people'][1]['label']);
    }

    public function test_leitura_sem_ninguem_e_nome_vazio_nao_quebram(): void
    {
        $this->assertSame(['total_readings' => 0, 'total_appearances' => 0, 'people' => []], LikelyDrivers::rank([]));

        $result = LikelyDrivers::rank([[], [['name' => null, 'telephone' => null]], [$this->socio('Caio')]]);
        $this->assertSame(3, $result['total_readings']);
        $this->assertSame(100.0, $result['people'][0]['share']);
        $this->assertSame(1, $result['people'][0]['readings']);
    }
}
