<?php

namespace Tests\Feature;

use App\Models\FreelancerWeeklyLimitCode;
use App\Services\WeeklyLimitCodeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Código do registro em massa (WeeklyLimitCodeService::consumeAll): um número
 * só para vários contratos, que só é queimado quando serve para TODOS — o
 * lote grava tudo ou nada, então liberar metade não serve para nada.
 *
 * A emissão (issueForContracts) não é testada aqui porque procura os
 * destinatários no model User, preso à conexão `mysql`.
 */
class WeeklyLimitBatchCodeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Só as colunas que o serviço lê, sem as FKs de users/freelancers.
        Schema::create('freelancer_weekly_limit_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coordinator_id')->nullable();
            $table->unsignedBigInteger('freelancer_id');
            $table->date('start_date');
            $table->string('code_hash');
            $table->text('sent_to');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();
        });
    }

    /** @param  array<int, array{0: int, 1: string}>  $contracts */
    private function issue(array $contracts, string $code = '654321'): void
    {
        $hash = Hash::make($code);

        foreach ($contracts as [$freelancerId, $startDate]) {
            FreelancerWeeklyLimitCode::create([
                'freelancer_id' => $freelancerId,
                'start_date' => $startDate,
                'code_hash' => $hash,
                'sent_to' => 'coordenador@exemplo.com',
                'expires_at' => now()->addMinutes(15),
            ]);
        }
    }

    private function codes(): WeeklyLimitCodeService
    {
        return app(WeeklyLimitCodeService::class);
    }

    public function test_codigo_certo_queima_todos_os_contratos_do_lote(): void
    {
        $contracts = [[7, '2026-08-01'], [8, '2026-08-01']];
        $this->issue($contracts);

        $this->assertTrue($this->codes()->consumeAll($contracts, '654321'));
        $this->assertSame(2, FreelancerWeeklyLimitCode::whereNotNull('used_at')->count());

        // Uma vez só.
        $this->assertFalse($this->codes()->consumeAll($contracts, '654321'));
    }

    public function test_codigo_errado_conta_tentativa_e_nao_queima_nada(): void
    {
        $contracts = [[7, '2026-08-01'], [8, '2026-08-01']];
        $this->issue($contracts);

        $this->assertFalse($this->codes()->consumeAll($contracts, '111111'));
        $this->assertSame(0, FreelancerWeeklyLimitCode::whereNotNull('used_at')->count());
        $this->assertSame([1, 1], FreelancerWeeklyLimitCode::orderBy('id')->pluck('attempts')->all());
    }

    /** Linha nova no lote, sem código: o código antigo não cobre e nada é queimado. */
    public function test_contrato_sem_codigo_recusa_o_lote_inteiro(): void
    {
        $this->issue([[7, '2026-08-01']]);

        $this->assertFalse($this->codes()->consumeAll([[7, '2026-08-01'], [8, '2026-08-01']], '654321'));
        $this->assertSame(0, FreelancerWeeklyLimitCode::whereNotNull('used_at')->count());
    }

    /** Duas linhas do mesmo freelancer no mesmo dia são um contrato só. */
    public function test_linhas_repetidas_e_datas_em_formatos_diferentes_se_unificam(): void
    {
        $this->assertSame(
            [[7, '2026-08-01'], [8, '2026-08-01']],
            WeeklyLimitCodeService::distinctContracts([[7, '2026-08-01'], ['7', '2026-08-01 00:00:00'], [8, '2026-08-01']]),
        );
    }

    /** Lote de um contrato só é o caminho de sempre (consume). */
    public function test_um_contrato_so_usa_o_codigo_individual(): void
    {
        $this->issue([[7, '2026-08-01']]);

        $this->assertTrue($this->codes()->consumeAll([[7, '2026-08-01']], '654321'));
    }
}
