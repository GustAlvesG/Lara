<?php

namespace Tests\Feature;

use App\Exceptions\FreelancerServiceLockedException;
use App\Models\Freelancer;
use App\Models\FreelancerService;
use App\Models\FunctionFreelancer;
use App\Services\FreelancerService as FreelancerServiceManager;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesFreelancerPixSchema;
use Tests\TestCase;

/**
 * O caso que a falta existe para resolver.
 *
 * O freelancer tem contrato na quarta e na sexta — as duas vagas da semana. Ele
 * falta na quarta e aparece no sábado. Sem a baixa da quarta, o contrato de
 * sábado é o terceiro da semana e só entra com a liberação de um coordenador do
 * Comercial: gasta-se a exceção com um problema de cadastro.
 *
 * Marcada a falta, a vaga volta e o sábado entra sozinho.
 *
 * Sem passar pelas rotas do tablet: a sessão do kiosk exige o `User`, preso à
 * conexão mysql. A regra inteira mora em `markNoShow()` e na contagem da semana,
 * que é o que o endpoint chama.
 */
class FreelancerNoShowFlowTest extends TestCase
{
    use CreatesFreelancerPixSchema;

    /** Sábado. A semana vai de segunda 07/09 a domingo 13/09/2026. */
    private const HOJE = '2026-09-12 17:00:00';

    private const QUARTA = '2026-09-09';
    private const SEXTA = '2026-09-11';
    private const SABADO = '2026-09-12';

    private FunctionFreelancer $garcom;

    private Freelancer $maria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFreelancerPixSchema();

        Carbon::setTestNow(self::HOJE);

        $this->garcom = FunctionFreelancer::create(['name' => 'Garçom', 'price' => 10.00]);
        $this->maria = Freelancer::create(['name' => 'Maria', 'cpf' => '12345678901', 'telephone' => '24999990000']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_falta_devolve_a_vaga_da_semana_e_libera_o_novo_contrato(): void
    {
        $quarta = $this->servico(self::QUARTA);
        $this->servico(self::SEXTA);

        // Antes: as duas vagas ocupadas, e o sábado precisaria de liberação.
        $this->assertSame(2, FreelancerService::countInWeeklyWindow($this->maria->id, self::SABADO));
        $this->assertTrue(FreelancerService::wouldExceedWeeklyLimit($this->maria->id, self::SABADO));

        $this->manager()->markNoShow($quarta);

        $this->assertSame(1, FreelancerService::countInWeeklyWindow($this->maria->id, self::SABADO));
        $this->assertFalse(
            FreelancerService::wouldExceedWeeklyLimit($this->maria->id, self::SABADO),
            'Com a quarta baixada como falta, o sábado tem de entrar sem liberação.'
        );
    }

    /** A baixa fica registrada como falta, e não como cancelamento comum. */
    public function test_a_falta_grava_o_motivo_e_nao_se_confunde_com_cancelamento(): void
    {
        $quarta = $this->servico(self::QUARTA);

        $this->manager()->markNoShow($quarta);

        $quarta->refresh();

        $this->assertTrue($quarta->isCancelled());
        $this->assertTrue($quarta->isNoShow());
        $this->assertSame(FreelancerService::CANCEL_REASON_NO_SHOW, $quarta->cancel_reason);
        $this->assertNotNull($quarta->cancelled_at);
        $this->assertSame('Falta', $quarta->signatureLabel());
    }

    /** O cancelamento comum continua sendo cancelamento comum. */
    public function test_cancelamento_pela_web_nao_vira_falta(): void
    {
        $quarta = $this->servico(self::QUARTA);

        $this->manager()->cancelService($quarta);

        $quarta->refresh();

        $this->assertTrue($quarta->isCancelled());
        $this->assertFalse($quarta->isNoShow());
        $this->assertSame(FreelancerService::CANCEL_REASON_ADMIN, $quarta->cancel_reason);
    }

    /**
     * O bloqueio da busca por função acompanha a baixa: o freelancer volta a
     * aparecer como disponível assim que a vaga é devolvida.
     */
    public function test_a_busca_por_funcao_desbloqueia_depois_da_falta(): void
    {
        $quarta = $this->servico(self::QUARTA);
        $this->servico(self::SEXTA);

        $antes = $this->buscar();
        $this->assertSame(['Maria'], array_column($antes['blocked'], 'name'));

        $this->manager()->markNoShow($quarta);

        $depois = $this->buscar();
        $this->assertSame([], array_column($depois['blocked'], 'name'));
        $this->assertSame(['Maria'], array_column($depois['available'], 'name'));
        $this->assertSame(1, $depois['available'][0]['services_in_week']);
    }

    /** Quem assinou compareceu: a falta não alcança contrato assinado. */
    public function test_contrato_assinado_recusa_a_falta(): void
    {
        $quarta = $this->servico(self::QUARTA, ['freelancer_signed_at' => '2026-09-09 18:05:00']);

        $this->expectException(FreelancerServiceLockedException::class);

        $this->manager()->markNoShow($quarta);
    }

    /** Turno que ainda não chegou não tem falta a registrar. */
    public function test_turno_futuro_recusa_a_falta(): void
    {
        $domingo = $this->servico('2026-09-13');

        $this->expectException(FreelancerServiceLockedException::class);

        $this->manager()->markNoShow($domingo);
    }

    public function test_marcar_falta_duas_vezes_recusa_a_segunda(): void
    {
        $quarta = $this->servico(self::QUARTA);

        $this->manager()->markNoShow($quarta);

        $this->expectException(FreelancerServiceLockedException::class);

        $this->manager()->markNoShow($quarta->refresh());
    }

    /**
     * A lista que o tablet mostra quando o limite bate: os contratos que estão
     * ocupando as vagas daquela semana, e não tudo o que o freelancer tem.
     */
    public function test_a_lista_da_semana_traz_so_o_que_ocupa_vaga(): void
    {
        $quarta = $this->servico(self::QUARTA);
        $this->servico(self::SEXTA);
        // Fora da semana, baixado e aditivo: nenhum dos três ocupa vaga aqui.
        $this->servico('2026-09-05');
        $this->servico('2026-09-08', ['status_id' => FreelancerService::STATUS_CANCELLED]);
        $this->servico(self::QUARTA, ['parent_service_id' => $quarta->id]);

        $semana = FreelancerService::weeklyWindowServices($this->maria->id, self::SABADO);

        $this->assertSame(
            [self::QUARTA, self::SEXTA],
            $semana->map(fn(FreelancerService $s) => $s->start_date->toDateString())->all()
        );
    }

    /* ---------------------------------------------------------------------
     | Auxiliares
     |---------------------------------------------------------------------*/

    private function manager(): FreelancerServiceManager
    {
        return app(FreelancerServiceManager::class);
    }

    private function buscar(string $dia = self::SABADO): array
    {
        return $this->manager()->searchByFunction($this->garcom, Carbon::parse($dia));
    }

    private function servico(string $dia, array $sobrescreve = []): FreelancerService
    {
        $service = FreelancerService::create([
            'freelancer_id' => $this->maria->id,
            'function_freelancer_id' => $this->garcom->id,
            'location' => 'Salão',
            'start_date' => $dia,
            'start_time' => '18:00',
            'end_date' => $dia,
            'end_time' => '22:00',
            'price' => 160.00,
            'total_hours' => 4,
        ]);

        if ($sobrescreve !== []) {
            $service->forceFill($sobrescreve)->save();
        }

        return $service;
    }
}
