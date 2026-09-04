<?php

namespace Tests\Unit;

use App\Models\PurchaseOrderApproval;
use App\Models\PurchaseOrderApprovalStep;
use App\Models\User;
use Tests\TestCase;

/**
 * A leitura do processo de aprovação: de quem é a vez, o que ainda falta e o
 * texto que vai para o Questor.
 *
 * A regra que mais importa aqui é a que o cliente pediu em voz alta: **cada
 * nível é uma decisão própria**. A mesma pessoa pode ocupar os três cargos, e
 * ainda assim aprovar um nível não pode dar a ela o passo do seguinte nem
 * deixá-la aprovar duas vezes o mesmo.
 *
 * Sem banco: os passos são montados na memória e pendurados na relação, como no
 * FreelancerTrackingStageTest. O model User está preso à conexão `mysql`, então
 * tocar no banco escreveria em homologação.
 */
class PurchaseOrderApprovalFlowTest extends TestCase
{
    private function user(int $id, string $nome = 'Fulano'): User
    {
        $user = new User(['name' => $nome]);
        $user->id = $id;

        return $user;
    }

    /**
     * @param  array<int, array<string, mixed>>  $passos
     */
    private function approval(int $nivel, array $passos, string $status = PurchaseOrderApproval::STATUS_OPEN): PurchaseOrderApproval
    {
        $approval = (new PurchaseOrderApproval)->forceFill([
            'cd_ordem_compra' => 40975,
            'status' => $status,
            'current_level' => $nivel,
        ]);
        $approval->id = 7;

        $approval->setRelation('steps', collect($passos)->map(
            fn(array $atributos, int $i) => tap(
                (new PurchaseOrderApprovalStep)->forceFill($atributos),
                fn($s) => $s->id = $i + 1
            )
        ));

        return $approval;
    }

    public function test_a_vez_e_de_quem_tem_passo_pendente_no_nivel_atual(): void
    {
        $approval = $this->approval(PurchaseOrderApproval::LEVEL_DIRECTORS, [
            ['level' => 1, 'role' => PurchaseOrderApprovalStep::ROLE_ACCOUNTING, 'decision' => 'approved'],
            ['level' => 2, 'role' => PurchaseOrderApprovalStep::ROLE_MANAGEMENT, 'decision' => 'approved'],
            ['level' => 3, 'user_id' => 10, 'decision' => 'pending'],
            ['level' => 3, 'user_id' => 11, 'decision' => 'pending'],
        ]);

        $this->assertNotNull($approval->stepFor($this->user(10)));
        $this->assertNotNull($approval->stepFor($this->user(11)));
        // Quem não foi escolhido não decide, mesmo sendo diretor.
        $this->assertNull($approval->stepFor($this->user(12)));
    }

    public function test_quem_ja_decidiu_o_nivel_nao_decide_de_novo(): void
    {
        $approval = $this->approval(PurchaseOrderApproval::LEVEL_DIRECTORS, [
            ['level' => 3, 'user_id' => 10, 'decision' => 'approved'],
            ['level' => 3, 'user_id' => 11, 'decision' => 'pending'],
        ]);

        $this->assertNull($approval->stepFor($this->user(10)));
        $this->assertNotNull($approval->stepFor($this->user(11)));
        $this->assertCount(1, $approval->pendingSteps());
    }

    public function test_aprovar_um_nivel_nao_da_o_passo_do_seguinte(): void
    {
        // A mesma pessoa (id 1) é dona dos três cargos — o caso real de
        // homologação. Com o processo parado no nível 3, o passo dela é o de
        // diretora; os níveis 1 e 2 já foram decididos e não voltam.
        $approval = $this->approval(PurchaseOrderApproval::LEVEL_DIRECTORS, [
            ['level' => 1, 'role' => PurchaseOrderApprovalStep::ROLE_ACCOUNTING, 'decision' => 'approved', 'decided_by' => 1],
            ['level' => 2, 'role' => PurchaseOrderApprovalStep::ROLE_MANAGEMENT, 'decision' => 'approved', 'decided_by' => 1],
            ['level' => 3, 'user_id' => 1, 'decision' => 'pending'],
        ]);

        $passo = $approval->stepFor($this->user(1));

        $this->assertNotNull($passo);
        $this->assertSame(3, $passo->level);
        // E é um passo só: aprovar o nível 1 não adiantou nenhum outro.
        $this->assertCount(1, $approval->pendingSteps());
    }

    public function test_passos_de_outros_niveis_nao_contam_como_pendentes(): void
    {
        $approval = $this->approval(PurchaseOrderApproval::LEVEL_MANAGEMENT, [
            ['level' => 1, 'role' => PurchaseOrderApprovalStep::ROLE_ACCOUNTING, 'decision' => 'approved'],
            ['level' => 2, 'role' => PurchaseOrderApprovalStep::ROLE_MANAGEMENT, 'decision' => 'pending'],
        ]);

        $this->assertCount(1, $approval->pendingSteps());
        $this->assertSame(2, $approval->pendingSteps()->first()->level);
    }

    public function test_resumo_dos_aprovadores_agrupa_por_nivel(): void
    {
        $approval = $this->approval(PurchaseOrderApproval::LEVEL_DIRECTORS, [
            ['level' => 1, 'decision' => 'approved', 'decided_by_name' => 'Ana', 'decided_at' => '2026-08-13 10:00:00'],
            ['level' => 2, 'decision' => 'approved', 'decided_by_name' => 'Bruno', 'decided_at' => '2026-08-13 11:30:00'],
            ['level' => 3, 'decision' => 'approved', 'decided_by_name' => 'Carla', 'decided_at' => '2026-08-13 14:05:00'],
            ['level' => 3, 'decision' => 'approved', 'decided_by_name' => 'Diego', 'decided_at' => '2026-08-13 14:20:00'],
        ], PurchaseOrderApproval::STATUS_APPROVED);

        $resumo = $approval->approversSummary();

        // É o texto que vai para DS_OBS: precisa dizer quem, em que nível e
        // quando, porque no Questor só vai aparecer o usuário técnico.
        $this->assertStringContainsString('Contabilidade: Ana (13/08 10:00)', $resumo);
        $this->assertStringContainsString('Gerência: Bruno (13/08 11:30)', $resumo);
        $this->assertStringContainsString('Diretoria: Carla (13/08 14:05), Diego (13/08 14:20)', $resumo);
    }

    public function test_resumo_ignora_quem_nao_aprovou(): void
    {
        $approval = $this->approval(PurchaseOrderApproval::LEVEL_DIRECTORS, [
            ['level' => 1, 'decision' => 'approved', 'decided_by_name' => 'Ana', 'decided_at' => '2026-08-13 10:00:00'],
            ['level' => 3, 'decision' => 'rejected', 'decided_by_name' => 'Carla', 'decided_at' => '2026-08-13 14:05:00'],
            ['level' => 3, 'decision' => 'skipped', 'decided_by_name' => null],
        ], PurchaseOrderApproval::STATUS_REJECTED);

        $resumo = $approval->approversSummary();

        $this->assertStringContainsString('Ana', $resumo);
        $this->assertStringNotContainsString('Carla', $resumo);
    }
}
