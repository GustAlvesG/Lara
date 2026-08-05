<?php

namespace Tests\Unit;

use App\Models\FunctionFreelancer;
use Tests\TestCase;

/**
 * Faixa de horas do cachê — a conta que decide quanto o funcionário recebe.
 *
 * O cachê não é proporcional: a duração do turno serve só para achar a FAIXA,
 * e cada faixa tem seu valor de tabela. O arredondamento soma 15 minutos e
 * toma a hora cheia do resultado; depois vêm o piso de 2h e o teto de 11h.
 *
 * Nada aqui toca o banco: são regras puras sobre minutos.
 */
class EmployeeCacheRateTest extends TestCase
{
    /** 3h45 vira 4h; 3h44 continua em 3h — a virada é exatamente nos 45 min. */
    public function test_arredonda_somando_quinze_minutos(): void
    {
        $this->assertSame(3, FunctionFreelancer::cacheBilledHours(3 * 60));
        $this->assertSame(3, FunctionFreelancer::cacheBilledHours(3 * 60 + 44));
        $this->assertSame(4, FunctionFreelancer::cacheBilledHours(3 * 60 + 45));
        $this->assertSame(4, FunctionFreelancer::cacheBilledHours(4 * 60));
    }

    /** Menos de 2h paga a faixa de 2h: é o mínimo acordado. */
    public function test_piso_de_duas_horas(): void
    {
        $this->assertSame(2, FunctionFreelancer::cacheBilledHours(30));
        $this->assertSame(2, FunctionFreelancer::cacheBilledHours(60));
        $this->assertSame(2, FunctionFreelancer::cacheBilledHours(2 * 60));
        $this->assertSame(2, FunctionFreelancer::cacheBilledHours(2 * 60 + 44));
    }

    /** De 11h em diante o valor é o mesmo: o turno estica, o cachê não. */
    public function test_teto_de_onze_horas(): void
    {
        $this->assertSame(11, FunctionFreelancer::cacheBilledHours(10 * 60 + 45));
        $this->assertSame(11, FunctionFreelancer::cacheBilledHours(11 * 60));
        $this->assertSame(11, FunctionFreelancer::cacheBilledHours(14 * 60));
        $this->assertSame(11, FunctionFreelancer::cacheBilledHours(23 * 60));
    }

    /** Toda faixa entre o piso e o teto responde por si. */
    public function test_faixas_intermediarias(): void
    {
        $this->assertSame(5, FunctionFreelancer::cacheBilledHours(4 * 60 + 45));
        $this->assertSame(6, FunctionFreelancer::cacheBilledHours(6 * 60 + 10));
        $this->assertSame(8, FunctionFreelancer::cacheBilledHours(7 * 60 + 50));
    }

    /** Duração negativa ou zero não existe no fluxo, mas não pode virar faixa 0. */
    public function test_duracao_invalida_cai_no_piso(): void
    {
        $this->assertSame(2, FunctionFreelancer::cacheBilledHours(0));
        $this->assertSame(2, FunctionFreelancer::cacheBilledHours(-90));
    }

    /** A modalidade decide qual conta vale — e é exclusiva. */
    public function test_tipo_da_funcao(): void
    {
        $freelancer = new FunctionFreelancer(['name' => 'Garçom Freelancer']);
        $cache = new FunctionFreelancer(['name' => 'Garçom Cachê', 'type' => FunctionFreelancer::TYPE_CACHE]);

        $this->assertTrue($freelancer->isFreelancer());
        $this->assertFalse($freelancer->isCache());
        $this->assertTrue($cache->isCache());
        $this->assertFalse($cache->isFreelancer());
    }

    /** As dez faixas que uma função de cachê precisa ter. */
    public function test_faixas_esperadas(): void
    {
        $this->assertSame(
            [2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
            FunctionFreelancer::cacheHourRange()
        );
    }
}
