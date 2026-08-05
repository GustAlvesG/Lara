<?php

namespace Tests\Feature;

use App\Models\Freelancer;
use App\Models\FreelancerService;
use App\Models\FreelancerServiceBatch;
use App\Models\FunctionFreelancer;
use Tests\Concerns\CreatesFreelancerPixSchema;
use Tests\TestCase;

/**
 * O Financeiro agrupado por lote.
 *
 * A regra que estes testes protegem: **o lote é a unidade de pagamento**. A
 * diretoria aprova um bloco e é esse bloco que o financeiro quita — mas só os
 * contratos realmente pagáveis dele, porque o que a gerência recusou saiu do
 * lote e voltou para a fila do coordenador. Um lote que mostrasse os recusados
 * pediria um pagamento maior do que a diretoria aprovou.
 *
 * O outro lado da regra: nada de pagável pode ficar invisível por não estar num
 * lote. Daí a consulta de avulsos.
 *
 * Como em FreelancerPixOnPaymentTest, nada aqui autentica ninguém nem passa
 * pela rota HTTP: o model User fixa a conexão `mysql` e tocá-lo levaria a suíte
 * para fora do SQLite.
 */
class FreelancerFinanceByBatchTest extends TestCase
{
    use CreatesFreelancerPixSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFreelancerPixSchema();

        config(['sicoob.enabled' => false]);
    }

    /* ---------------------------------------------------------------------
     | Quais lotes o financeiro enxerga
     |---------------------------------------------------------------------*/

    public function test_so_lote_aprovado_pela_diretoria_aparece_no_financeiro(): void
    {
        $aprovado = $this->loteAprovado();
        $this->contratoAprovado(100.00, $aprovado);

        // Enviado à gerência, ainda sem aval: não é do financeiro.
        $emTramite = $this->lote(FreelancerServiceBatch::STATUS_SENT);
        $this->contrato(100.00, $emTramite, ['director_approved_at' => null]);

        $ids = FreelancerServiceBatch::approvedForFinance()->pluck('id');

        $this->assertTrue($ids->contains($aprovado->id));
        $this->assertFalse($ids->contains($emTramite->id));
    }

    public function test_lote_aprovado_sem_contrato_pagavel_nao_aparece(): void
    {
        $lote = $this->loteAprovado();
        // Único contrato do lote foi recusado pela gerência.
        $this->contrato(100.00, $lote, [
            'manager_approved_at' => null,
            'manager_rejected_at' => now(),
            'director_approved_at' => null,
        ]);

        $this->assertFalse(
            FreelancerServiceBatch::approvedForFinance()->pluck('id')->contains($lote->id),
            'Lote sem nada a pagar não deve ocupar a lista do financeiro.'
        );
    }

    /* ---------------------------------------------------------------------
     | O que aparece dentro do lote
     |---------------------------------------------------------------------*/

    public function test_lote_mostra_so_os_pagaveis_e_o_total_reflete_isso(): void
    {
        $lote = $this->loteAprovado();
        $this->contratoAprovado(150.00, $lote);
        $this->contratoAprovado(250.00, $lote);
        // Recusado pela gerência: continua com o batch_id, mas não é pagável.
        $this->contrato(999.00, $lote, [
            'manager_approved_at' => null,
            'manager_rejected_at' => now(),
            'director_approved_at' => null,
        ]);

        $pagaveis = $lote->payableServices()->get();

        $this->assertCount(2, $pagaveis);
        $this->assertEqualsWithDelta(400.00, $pagaveis->sum('price'), 0.001,
            'O total do lote no financeiro é o dos pagáveis, não o dos aprovados pela gerência.');
    }

    public function test_contrato_de_outro_lote_nao_vaza_para_o_lote_errado(): void
    {
        $a = $this->loteAprovado();
        $b = $this->loteAprovado();

        $this->contratoAprovado(100.00, $a);
        $doB = $this->contratoAprovado(200.00, $b);

        $this->assertSame([$doB->id], $b->payableServices()->pluck('id')->all());
    }

    /* ---------------------------------------------------------------------
     | Estado de pagamento do lote — sempre derivado dos contratos
     |---------------------------------------------------------------------*/

    public function test_lote_sem_baixa_esta_a_pagar(): void
    {
        $lote = $this->loteAprovado();
        $this->contratoAprovado(100.00, $lote);
        $this->contratoAprovado(100.00, $lote);

        $this->assertSame('A pagar', $lote->financeStatusLabel());
        $this->assertFalse($lote->isFullyPaid());
        $this->assertFalse($lote->isPartiallyPaid());
    }

    public function test_lote_com_parte_paga_fica_parcial(): void
    {
        $lote = $this->loteAprovado();
        $this->contratoAprovado(100.00, $lote)->forceFill(['paid' => true, 'paid_at' => now()])->save();
        $this->contratoAprovado(100.00, $lote);

        $this->assertTrue($lote->isPartiallyPaid());
        $this->assertFalse($lote->isFullyPaid());
        $this->assertSame('Parcialmente pago', $lote->financeStatusLabel());
    }

    public function test_lote_com_tudo_pago_fica_quitado(): void
    {
        $lote = $this->loteAprovado();

        foreach ([100.00, 200.00] as $valor) {
            $this->contratoAprovado($valor, $lote)->forceFill(['paid' => true, 'paid_at' => now()])->save();
        }

        $this->assertTrue($lote->isFullyPaid());
        $this->assertSame('Quitado', $lote->financeStatusLabel());
    }

    /** Um contrato recusado, mesmo não pago, não impede o lote de ficar quitado. */
    public function test_recusado_pela_gerencia_nao_impede_o_lote_de_quitar(): void
    {
        $lote = $this->loteAprovado();
        $this->contratoAprovado(100.00, $lote)->forceFill(['paid' => true, 'paid_at' => now()])->save();
        $this->contrato(999.00, $lote, [
            'manager_approved_at' => null,
            'manager_rejected_at' => now(),
            'director_approved_at' => null,
        ]);

        $this->assertTrue($lote->isFullyPaid());
    }

    public function test_lote_vazio_nao_e_considerado_quitado(): void
    {
        $this->assertFalse($this->loteAprovado()->isFullyPaid(),
            'Sem contrato nenhum não há o que quitar — "quitado" seria mentira.');
    }

    /* ---------------------------------------------------------------------
     | Avulsos: nada de pagável pode sumir da tela
     |---------------------------------------------------------------------*/

    public function test_contrato_pagavel_sem_lote_aparece_nos_avulsos(): void
    {
        $semLote = $this->contratoAprovado(300.00, null);
        $comLote = $this->contratoAprovado(100.00, $this->loteAprovado());

        $avulsos = $this->consultaDeAvulsos()->pluck('id');

        $this->assertTrue($avulsos->contains($semLote->id));
        $this->assertFalse($avulsos->contains($comLote->id));
    }

    /** Lote apagado (`nullOnDelete`) ou não aprovado não pode engolir o contrato. */
    public function test_contrato_preso_a_lote_nao_aprovado_aparece_nos_avulsos(): void
    {
        $lote = $this->lote(FreelancerServiceBatch::STATUS_CLOSED);
        $orfao = $this->contratoAprovado(400.00, $lote);

        $this->assertTrue($this->consultaDeAvulsos()->pluck('id')->contains($orfao->id));
    }

    public function test_a_lista_plana_continua_mostrando_tudo(): void
    {
        $this->contratoAprovado(100.00, $this->loteAprovado());
        $this->contratoAprovado(200.00, null);

        $this->assertCount(2, FreelancerService::awaitingFinance()->get(),
            'A visão "todos os contratos" é a rede de segurança: nenhum pagável fica de fora dela.');
    }

    /* ---------------------------------------------------------------------
     | Auxiliares
     |---------------------------------------------------------------------*/

    /** Mesma condição do FinanceController::orphanQuery(). */
    private function consultaDeAvulsos()
    {
        return FreelancerService::awaitingFinance()
            ->where(fn($q) => $q->whereNull('batch_id')
                ->orWhereDoesntHave('batch', fn($b) => $b->where('status', FreelancerServiceBatch::STATUS_DIRECTOR_APPROVED)))
            ->get();
    }

    private function lote(string $status): FreelancerServiceBatch
    {
        return FreelancerServiceBatch::create([
            'status' => $status,
            'created_by' => 7,
        ]);
    }

    private function loteAprovado(): FreelancerServiceBatch
    {
        $lote = $this->lote(FreelancerServiceBatch::STATUS_DIRECTOR_APPROVED);

        $lote->forceFill([
            'reviewed_by' => 7,
            'reviewed_at' => now()->subDay(),
            'director_email' => 'diretoria@exemplo.test',
            'director_decision' => FreelancerServiceBatch::DECISION_APPROVED,
            'director_decided_at' => now()->subHours(2),
            'director_decided_by' => 7,
        ])->save();

        return $lote;
    }

    /** Contrato assinado pelas duas partes e aprovado nos dois níveis. */
    private function contratoAprovado(float $valor, ?FreelancerServiceBatch $lote): FreelancerService
    {
        return $this->contrato($valor, $lote);
    }

    private function contrato(float $valor, ?FreelancerServiceBatch $lote, array $sobrescreve = []): FreelancerService
    {
        $freelancer = Freelancer::create([
            'name' => 'Freelancer ' . Str()->random(6),
            'cpf' => (string) random_int(10000000000, 99999999999),
            'pix_key' => 'chave-' . Str()->random(6),
            'rg' => '12.345.678-9',
            'civil_status' => 'Solteiro(a)',
        ]);

        $funcao = FunctionFreelancer::create(['name' => 'Garçom', 'price' => 10.00]);

        $service = FreelancerService::create([
            'freelancer_id' => $freelancer->id,
            'function_freelancer_id' => $funcao->id,
            'location' => 'Salão',
            'start_date' => now()->subDays(4)->toDateString(),
            'start_time' => '18:00',
            'end_date' => now()->subDays(4)->toDateString(),
            'end_time' => '22:00',
            'price' => $valor,
            'total_hours' => 4,
        ]);

        $service->forceFill(array_merge([
            'batch_id' => $lote?->id,
            'freelancer_signed_at' => now()->subDays(3),
            'coordinator_signed_at' => now()->subDays(3),
            'coordinator_signed_by' => 7,
            'manager_approved_at' => now()->subDays(2),
            'manager_approved_by' => 7,
            'director_approved_at' => now()->subDay(),
        ], $sobrescreve))->save();

        return $service;
    }
}
