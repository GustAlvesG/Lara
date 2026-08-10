<?php

namespace Tests\Unit;

use App\Models\FreelancerService;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Regra do limite de 2 serviços por freelancer por semana de calendário.
 *
 * A contagem é a mesma no painel web, no tablet e nos selos das listagens. A
 * semana é um bloco fixo de segunda a domingo: a segunda-feira zera a
 * contagem, mesmo que o freelancer tenha trabalhado sábado/domingo anteriores.
 * Aqui isso é exercitado pela versão em memória (`flagExcessWithinCollection`),
 * que não toca o banco.
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
     * Como a semana é um bloco fixo (segunda a domingo), a ordem de lançamento
     * não importa: um serviço lançado numa data anterior a outros dois já
     * registrados na mesma semana aperta igual.
     */
    public function test_lancamento_fora_de_ordem_na_mesma_semana_aperta_igual(): void
    {
        $primeiro = $this->service('2026-07-08');
        $segundo = $this->service('2026-07-10');
        $terceiro = $this->service('2026-07-12');

        $flags = $this->flags([$primeiro, $segundo, $terceiro]);

        $this->assertTrue($flags[$primeiro->id]);
        $this->assertTrue($flags[$segundo->id]);
        $this->assertTrue($flags[$terceiro->id]);
    }

    /**
     * Regra pedida: a segunda-feira zera a contagem. Sábado (07-11) e domingo
     * (07-12) fecham a semana anterior; a segunda-feira seguinte (07-13) não
     * deve contar com eles.
     */
    public function test_segunda_feira_nao_conta_sabado_e_domingo_anteriores(): void
    {
        $flags = $this->flags([
            $this->service('2026-07-11'),
            $this->service('2026-07-12'),
            $this->service('2026-07-13'),
        ]);

        $this->assertFalse($flags->contains(true));
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
