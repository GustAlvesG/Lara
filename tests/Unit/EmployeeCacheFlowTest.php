<?php

namespace Tests\Unit;

use App\Models\EmployeeCache;
use Tests\TestCase;

/**
 * Trâmite do cachê lido a partir dos carimbos — sem banco.
 *
 * O que se exercita aqui é a regra que decide o dinheiro: um cachê só é pagável
 * depois de aprovado, assinado e — quando o horário informado pelo funcionário
 * ficou diferente do previsto — reconferido pelo coordenador E pela gerência.
 */
class EmployeeCacheFlowTest extends TestCase
{
    /** Cachê não persistido, com o previsto já preenchido. */
    private function cache(array $attributes = []): EmployeeCache
    {
        $cache = new EmployeeCache(array_merge([
            'event_date' => '2026-08-10',
            'expected_start_time' => '18:00:00',
            'expected_end_time' => '22:00:00',
            'expected_hours' => 4,
            'expected_price' => '120.00',
        ], $attributes));

        return $cache;
    }

    /** Assina com o horário informado, direto nos atributos. */
    private function signed(string $start, string $end, array $attributes = []): EmployeeCache
    {
        return $this->cache(array_merge([
            'actual_start_time' => $start,
            'actual_end_time' => $end,
            'hours' => 4,
            'price' => '120.00',
        ], $attributes))->forceFill([
            'manager_approved_at' => now(),
            'employee_signed_at' => now(),
        ]);
    }

    /* ---------------------------------------------------------------------
     | Divergência
     |---------------------------------------------------------------------*/

    public function test_horario_igual_ao_previsto_nao_diverge(): void
    {
        $cache = $this->signed('18:00:00', '22:00:00');

        $this->assertFalse($cache->hasDivergence());
    }

    /** Divergência é QUALQUER mudança de início ou término — não a de faixa. */
    public function test_qualquer_mudanca_de_horario_diverge(): void
    {
        $this->assertTrue($this->signed('18:00:00', '22:10:00')->hasDivergence());
        $this->assertTrue($this->signed('17:50:00', '22:00:00')->hasDivergence());
    }

    /** "18:00" e "18:00:00" são o mesmo horário e não podem virar divergência. */
    public function test_formato_do_horario_nao_cria_divergencia(): void
    {
        $cache = $this->signed('18:00', '22:00');

        $this->assertFalse($cache->hasDivergence());
    }

    public function test_sem_assinatura_nao_ha_divergencia(): void
    {
        $cache = $this->cache()->forceFill(['manager_approved_at' => now()]);

        $this->assertFalse($cache->hasDivergence());
    }

    /* ---------------------------------------------------------------------
     | Quando o cachê fica pagável
     |---------------------------------------------------------------------*/

    public function test_sem_divergencia_vai_direto_ao_financeiro(): void
    {
        $cache = $this->signed('18:00:00', '22:00:00');

        $this->assertTrue($cache->isPayable());
        $this->assertFalse($cache->awaitsCoordinatorRecheck());
    }

    public function test_divergencia_exige_as_duas_reconferencias(): void
    {
        $cache = $this->signed('18:00:00', '23:30:00');

        // Assinado e divergente: espera o coordenador.
        $this->assertTrue($cache->awaitsCoordinatorRecheck());
        $this->assertFalse($cache->awaitsManagerRecheck());
        $this->assertFalse($cache->isPayable());

        // Coordenador reconferiu: agora é a vez da gerência.
        $cache->forceFill(['recheck_coordinator_at' => now()]);
        $this->assertFalse($cache->awaitsCoordinatorRecheck());
        $this->assertTrue($cache->awaitsManagerRecheck());
        $this->assertFalse($cache->isPayable());

        // Gerência reconferiu: liberado.
        $cache->forceFill(['recheck_manager_at' => now()]);
        $this->assertFalse($cache->awaitsManagerRecheck());
        $this->assertTrue($cache->isPayable());
    }

    public function test_recusa_na_reconferencia_encerra_o_cache(): void
    {
        $cache = $this->signed('18:00:00', '23:30:00')
            ->forceFill(['recheck_rejected_at' => now()]);

        $this->assertFalse($cache->isPayable());
        $this->assertFalse($cache->awaitsCoordinatorRecheck());
        $this->assertSame('Recusado na reconferência', $cache->statusLabel());
    }

    public function test_sem_aprovacao_da_gerencia_nao_se_assina(): void
    {
        $cache = $this->cache();

        $this->assertFalse($cache->canBeSignedByEmployee());

        $cache->forceFill(['manager_approved_at' => now()]);
        $this->assertTrue($cache->canBeSignedByEmployee());

        // Assinado uma vez, não se assina de novo.
        $cache->forceFill(['employee_signed_at' => now()]);
        $this->assertFalse($cache->canBeSignedByEmployee());
    }

    public function test_cancelado_e_recusado_nao_sao_pagaveis(): void
    {
        $cancelado = $this->signed('18:00:00', '22:00:00')->forceFill(['cancelled_at' => now()]);
        $this->assertFalse($cancelado->isPayable());
        $this->assertFalse($cancelado->canBeSignedByEmployee());

        $recusado = $this->cache()->forceFill(['manager_rejected_at' => now()]);
        $this->assertTrue($recusado->isManagerRejected());
        $this->assertFalse($recusado->isPayable());
    }

    /* ---------------------------------------------------------------------
     | Período
     |---------------------------------------------------------------------*/

    public function test_turno_que_vira_a_meia_noite(): void
    {
        $cache = $this->cache([
            'expected_start_time' => '22:00:00',
            'expected_end_time' => '02:00:00',
        ]);

        $this->assertSame(240, $cache->expectedMinutes());
        $this->assertStringContainsString('(+1)', $cache->formattedExpectedPeriod());
        $this->assertSame('2026-08-11', EmployeeCache::endDateFor('2026-08-10', '22:00', '02:00')->toDateString());
        $this->assertSame('2026-08-10', EmployeeCache::endDateFor('2026-08-10', '18:00', '22:00')->toDateString());
    }

    public function test_diferenca_de_valor_apos_a_assinatura(): void
    {
        $cache = $this->signed('18:00:00', '23:30:00', ['hours' => 6, 'price' => '180.00']);

        $this->assertEqualsWithDelta(60.0, $cache->priceDifference(), 0.001);
    }
}
