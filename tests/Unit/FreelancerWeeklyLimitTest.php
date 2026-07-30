<?php

namespace Tests\Unit;

use App\Models\FreelancerService;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Regra do limite de 2 serviços por freelancer a cada 7 dias.
 *
 * A contagem é a mesma no painel web, no tablet e nos selos das listagens, e a
 * janela vale para QUALQUER intervalo de 7 dias que contenha a data do serviço
 * — não só para os 6 dias anteriores a ela. Aqui isso é exercitado pela versão
 * em memória (`flagExcessWithinCollection`), que não toca o banco.
 */
class FreelancerWeeklyLimitTest extends TestCase
{
    private int $nextId = 1;

    /** Serviço não persistido, só com o que a regra olha. */
    private function service(string $startDate, int $freelancerId = 1, bool $cancelled = false): FreelancerService
    {
        $service = new FreelancerService([
            'freelancer_id' => $freelancerId,
            'start_date' => $startDate,
            'status_id' => $cancelled ? FreelancerService::STATUS_CANCELLED : FreelancerService::STATUS_ACTIVE,
        ]);

        $service->id = $this->nextId++;

        return $service;
    }

    /** @param  array<int, FreelancerService>  $services */
    private function flags(array $services): Collection
    {
        return FreelancerService::flagExcessWithinCollection(collect($services));
    }

    public function test_o_limite_e_de_dois_servicos_em_sete_dias(): void
    {
        $this->assertSame(2, FreelancerService::WEEKLY_LIMIT);
        $this->assertSame(7, FreelancerService::WEEKLY_WINDOW_DAYS);
    }

    public function test_dois_servicos_na_mesma_semana_nao_estouram(): void
    {
        $flags = $this->flags([
            $this->service('2026-07-06'),
            $this->service('2026-07-08'),
        ]);

        $this->assertFalse($flags->contains(true));
    }

    public function test_tres_servicos_em_sete_dias_estouram_para_todos(): void
    {
        $flags = $this->flags([
            $this->service('2026-07-06'),
            $this->service('2026-07-08'),
            $this->service('2026-07-12'),
        ]);

        $this->assertCount(3, $flags);
        $this->assertNotContains(false, $flags->values()->all());
    }

    /**
     * 06 a 12 são 7 dias corridos; 13 já é o oitavo. Com o terceiro serviço
     * fora da janela, ninguém estoura.
     */
    public function test_terceiro_servico_fora_da_janela_nao_estoura(): void
    {
        $flags = $this->flags([
            $this->service('2026-07-06'),
            $this->service('2026-07-08'),
            $this->service('2026-07-13'),
        ]);

        $this->assertFalse($flags->contains(true));
    }

    /**
     * Regressão: a janela olhava só para trás. Um serviço lançado numa data
     * ANTERIOR a outros dois já registrados aperta a mesma semana e passava
     * despercebido — o dia 08 não era marcado, embora 08-12 tenha três.
     */
    public function test_janela_tambem_olha_para_frente(): void
    {
        $primeiro = $this->service('2026-07-08');
        $segundo = $this->service('2026-07-10');
        $terceiro = $this->service('2026-07-12');

        $flags = $this->flags([$primeiro, $segundo, $terceiro]);

        $this->assertTrue($flags[$primeiro->id]);
        $this->assertTrue($flags[$segundo->id]);
        $this->assertTrue($flags[$terceiro->id]);
    }

    public function test_servicos_de_freelancers_diferentes_nao_se_somam(): void
    {
        $flags = $this->flags([
            $this->service('2026-07-06', freelancerId: 1),
            $this->service('2026-07-07', freelancerId: 1),
            $this->service('2026-07-08', freelancerId: 2),
            $this->service('2026-07-09', freelancerId: 2),
        ]);

        $this->assertFalse($flags->contains(true));
    }

    public function test_contrato_cancelado_nao_entra_na_conta(): void
    {
        $cancelado = $this->service('2026-07-07', cancelled: true);
        $ativo = $this->service('2026-07-08');
        $outro = $this->service('2026-07-09');

        $flags = $this->flags([$cancelado, $ativo, $outro]);

        $this->assertFalse($flags[$cancelado->id]);
        $this->assertFalse($flags[$ativo->id]);
        $this->assertFalse($flags[$outro->id]);
    }

    public function test_quarto_servico_na_semana_continua_estourando(): void
    {
        $flags = $this->flags([
            $this->service('2026-07-06'),
            $this->service('2026-07-07'),
            $this->service('2026-07-08'),
            $this->service('2026-07-09'),
        ]);

        $this->assertNotContains(false, $flags->values()->all());
    }
}
