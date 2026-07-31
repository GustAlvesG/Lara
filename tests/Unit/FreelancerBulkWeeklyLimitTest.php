<?php

namespace Tests\Unit;

use App\Models\FreelancerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Registro em massa contra o limite de 7 dias.
 *
 * O ponto é as linhas do MESMO envio contarem umas com as outras: sem isso,
 * três linhas do mesmo freelancer na mesma semana entrariam cada uma se achando
 * a primeira, e a tela em massa viraria o caminho para furar a regra.
 *
 * Sem banco: a subclasse abaixo troca só a consulta das datas já gravadas —
 * a matemática da janela e a soma do lote são o código de produção.
 */
class FreelancerBulkWeeklyLimitTest extends TestCase
{
    protected function tearDown(): void
    {
        BulkTestService::$existing = [];

        parent::tearDown();
    }

    /** @param  array<int, array<int, string>>  $existing  freelancer_id => datas já gravadas */
    private function withExisting(array $existing): void
    {
        BulkTestService::$existing = $existing;
    }

    /** @param  array<int, array{0: int, 1: string}>  $rows  [freelancer_id, data] */
    private function rows(array $rows): array
    {
        return array_map(fn(array $row) => [
            'freelancer_id' => $row[0],
            'start_date' => $row[1],
        ], $rows);
    }

    public function test_lote_dentro_do_limite_nao_exige_liberacao(): void
    {
        $exceeding = BulkTestService::rowsExceedingWeeklyLimit($this->rows([
            [1, '2026-08-03'],
            [1, '2026-08-05'],
        ]));

        $this->assertSame([], $exceeding);
    }

    /** A terceira linha do mesmo freelancer na mesma semana é a que estoura. */
    public function test_linhas_do_mesmo_lote_contam_umas_com_as_outras(): void
    {
        $exceeding = BulkTestService::rowsExceedingWeeklyLimit($this->rows([
            [1, '2026-08-03'],
            [1, '2026-08-05'],
            [1, '2026-08-07'],
        ]));

        $this->assertSame([2], $exceeding);
    }

    public function test_da_terceira_em_diante_todas_estouram(): void
    {
        $exceeding = BulkTestService::rowsExceedingWeeklyLimit($this->rows([
            [1, '2026-08-03'],
            [1, '2026-08-04'],
            [1, '2026-08-05'],
            [1, '2026-08-06'],
        ]));

        $this->assertSame([2, 3], $exceeding);
    }

    public function test_freelancers_diferentes_nao_se_somam(): void
    {
        $exceeding = BulkTestService::rowsExceedingWeeklyLimit($this->rows([
            [1, '2026-08-03'],
            [2, '2026-08-03'],
            [1, '2026-08-04'],
            [2, '2026-08-04'],
        ]));

        $this->assertSame([], $exceeding);
    }

    /** Linhas espalhadas além da janela não se encontram. */
    public function test_linhas_fora_da_janela_de_sete_dias_nao_estouram(): void
    {
        $exceeding = BulkTestService::rowsExceedingWeeklyLimit($this->rows([
            [1, '2026-08-03'],
            [1, '2026-08-04'],
            [1, '2026-08-10'],
        ]));

        $this->assertSame([], $exceeding);
    }

    /** O que já está no banco entra na conta do lote. */
    public function test_servicos_ja_gravados_contam_contra_o_lote(): void
    {
        $this->withExisting([1 => ['2026-08-03', '2026-08-04']]);

        $exceeding = BulkTestService::rowsExceedingWeeklyLimit($this->rows([
            [1, '2026-08-05'],
        ]));

        $this->assertSame([0], $exceeding);
    }

    public function test_uma_unica_linha_ja_pode_estourar_pelo_que_esta_no_banco(): void
    {
        $this->withExisting([1 => ['2026-08-03', '2026-08-09']]);

        // 03 e 09 estão a 7 dias de distância, mas 05 fecha a janela com os dois.
        $exceeding = BulkTestService::rowsExceedingWeeklyLimit($this->rows([
            [1, '2026-08-05'],
        ]));

        $this->assertSame([0], $exceeding);
    }

    /** Datas gravadas depois da linha também apertam a semana dela. */
    public function test_janela_olha_para_frente_tambem_no_lote(): void
    {
        $this->withExisting([1 => ['2026-08-10', '2026-08-12']]);

        $exceeding = BulkTestService::rowsExceedingWeeklyLimit($this->rows([
            [1, '2026-08-08'],
        ]));

        $this->assertSame([0], $exceeding);
    }

    public function test_lote_vazio_nao_estoura(): void
    {
        $this->assertSame([], BulkTestService::rowsExceedingWeeklyLimit([]));
    }
}

/**
 * Só a consulta ao banco é trocada; janela, soma do lote e limite continuam
 * sendo o código real de FreelancerService.
 */
class BulkTestService extends FreelancerService
{
    /** @var array<int, array<int, string>> freelancer_id => datas já gravadas */
    public static array $existing = [];

    protected static function weeklyWindowDates(int $freelancerId, Carbon $date): Collection
    {
        return collect(static::$existing[$freelancerId] ?? [])
            ->map(fn(string $value) => Carbon::parse($value)->startOfDay());
    }
}
