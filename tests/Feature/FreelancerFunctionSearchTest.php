<?php

namespace Tests\Feature;

use App\Models\Freelancer;
use App\Models\FreelancerService;
use App\Models\FunctionFreelancer;
use App\Services\FreelancerService as FreelancerServiceManager;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesFreelancerPixSchema;
use Tests\TestCase;

/**
 * Busca por função no tablet: quem já atuou numa função, para a operação achar
 * um freelancer às pressas no fim de semana.
 *
 * O que estes testes protegem:
 *
 * - a ordem (mais atuações primeiro) e o que conta como atuação — nem
 *   cancelado, nem aditivo, nem turno que ainda não aconteceu;
 * - o bloqueio pelo limite semanal, na semana FIXA do dia escolhido (segunda a
 *   domingo), com a mesma conta de `countInWeeklyWindow()`;
 * - que o bloqueado sai SEM telefone — o bloqueio é do servidor.
 *
 * Sem rota: a sessão do tablet exige o `User`, preso à conexão mysql. A regra
 * inteira mora em `FreelancerService::searchByFunction()`, que é o que o
 * endpoint devolve.
 */
class FreelancerFunctionSearchTest extends TestCase
{
    use CreatesFreelancerPixSchema;

    /** Sábado. A semana vai de segunda 07/09 a domingo 13/09/2026. */
    private const HOJE = '2026-09-12 10:00:00';

    private FunctionFreelancer $garcom;

    private FunctionFreelancer $seguranca;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFreelancerPixSchema();

        Carbon::setTestNow(self::HOJE);

        $this->garcom = FunctionFreelancer::create(['name' => 'Garçom', 'price' => 10.00]);
        $this->seguranca = FunctionFreelancer::create(['name' => 'Segurança', 'price' => 12.00]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_quem_mais_atuou_vem_primeiro(): void
    {
        $pouco = $this->freelancer('Pouco');
        $muito = $this->freelancer('Muito');

        $this->servico($pouco, $this->garcom, '2026-08-01');
        foreach (['2026-07-01', '2026-07-15', '2026-08-02'] as $dia) {
            $this->servico($muito, $this->garcom, $dia);
        }

        $nomes = array_column($this->buscar($this->garcom)['available'], 'name');

        $this->assertSame(['Muito', 'Pouco'], $nomes);
    }

    /** Empate na quantidade: quem trabalhou por último é quem se lembra primeiro. */
    public function test_no_empate_vem_quem_atuou_mais_recentemente(): void
    {
        $antigo = $this->freelancer('Antigo');
        $recente = $this->freelancer('Recente');

        $this->servico($antigo, $this->garcom, '2026-06-01');
        $this->servico($recente, $this->garcom, '2026-08-20');

        $this->assertSame(['Recente', 'Antigo'], array_column($this->buscar($this->garcom)['available'], 'name'));
    }

    /**
     * Atuação é turno que aconteceu, na função pedida, que valeu: cancelado não
     * foi trabalhado, aditivo remenda um turno já contado, e turno marcado para
     * depois de hoje ainda não é experiência de ninguém.
     */
    public function test_so_conta_o_que_foi_trabalhado_naquela_funcao(): void
    {
        $maria = $this->freelancer('Maria');

        $base = $this->servico($maria, $this->garcom, '2026-08-10');
        $this->servico($maria, $this->garcom, '2026-08-11', ['status_id' => FreelancerService::STATUS_CANCELLED]);
        $this->servico($maria, $this->garcom, '2026-08-10', ['parent_service_id' => $base->id]);
        $this->servico($maria, $this->garcom, '2026-09-20');
        $this->servico($maria, $this->seguranca, '2026-08-12');

        $linha = $this->buscar($this->garcom)['available'][0];

        $this->assertSame(1, $linha['services_in_function']);
        $this->assertSame('2026-08-10', $linha['last_service_date']);
        $this->assertSame('10/08/2026', $linha['last_service_label']);
    }

    public function test_quem_nunca_atuou_na_funcao_nao_aparece(): void
    {
        $this->servico($this->freelancer('Só segurança'), $this->seguranca, '2026-08-12');

        $resultado = $this->buscar($this->garcom);

        $this->assertSame([], $resultado['available']);
        $this->assertSame([], $resultado['blocked']);
    }

    /**
     * Dois serviços na semana do dia pedido: bloqueado. Continua na lista — em
     * outra seção —, mas sem telefone, para não ser chamado.
     */
    public function test_quem_esta_no_limite_da_semana_vem_bloqueado_e_sem_telefone(): void
    {
        $cheio = $this->freelancer('Cheio', '24999998888');
        $livre = $this->freelancer('Livre', '24988887777');

        $this->servico($cheio, $this->garcom, '2026-08-01');
        // Segunda e quinta desta semana — um deles em outra função: o limite é
        // do freelancer, não da função.
        $this->servico($cheio, $this->garcom, '2026-09-07');
        $this->servico($cheio, $this->seguranca, '2026-09-10');

        $this->servico($livre, $this->garcom, '2026-08-01');

        $resultado = $this->buscar($this->garcom);

        $this->assertSame(['Livre'], array_column($resultado['available'], 'name'));
        $this->assertSame(['Cheio'], array_column($resultado['blocked'], 'name'));

        $bloqueado = $resultado['blocked'][0];

        $this->assertTrue($bloqueado['blocked']);
        $this->assertSame(2, $bloqueado['services_in_week']);
        $this->assertNull($bloqueado['phone'], 'O bloqueado não pode sair com o telefone.');
        $this->assertNull($bloqueado['phone_dial']);

        $this->assertSame('(24) 98888-7777', $resultado['available'][0]['phone']);
    }

    /** Um serviço na semana não bloqueia: o limite é de 2, e ele pode pegar o segundo. */
    public function test_um_servico_na_semana_ainda_esta_disponivel(): void
    {
        $maria = $this->freelancer('Maria');
        $this->servico($maria, $this->garcom, '2026-09-08');

        $linha = $this->buscar($this->garcom)['available'][0];

        $this->assertFalse($linha['blocked']);
        $this->assertSame(1, $linha['services_in_week']);
    }

    /**
     * A semana é fixa, de segunda a domingo. Cheio nesta semana, o freelancer
     * está bloqueado para o domingo — e livre para a segunda, que já é a semana
     * seguinte. É para isso que o tablet pergunta "para quando".
     */
    public function test_a_semana_do_limite_e_a_do_dia_pedido(): void
    {
        $maria = $this->freelancer('Maria');
        $this->servico($maria, $this->garcom, '2026-09-07');
        $this->servico($maria, $this->garcom, '2026-09-08');

        $domingo = $this->buscar($this->garcom, '2026-09-13');
        $segunda = $this->buscar($this->garcom, '2026-09-14');

        $this->assertSame(['Maria'], array_column($domingo['blocked'], 'name'));
        $this->assertSame('07/09', $domingo['week_start']);
        $this->assertSame('13/09', $domingo['week_end']);

        $this->assertSame(['Maria'], array_column($segunda['available'], 'name'));
        $this->assertSame('14/09', $segunda['week_start']);
    }

    /** Cancelado e aditivo não ocupam a semana, como em `countInWeeklyWindow()`. */
    public function test_a_conta_da_semana_e_a_mesma_do_limite_semanal(): void
    {
        $maria = $this->freelancer('Maria');
        $base = $this->servico($maria, $this->garcom, '2026-09-07');
        $this->servico($maria, $this->garcom, '2026-09-08', ['status_id' => FreelancerService::STATUS_CANCELLED]);
        $this->servico($maria, $this->garcom, '2026-09-07', ['parent_service_id' => $base->id]);

        $semana = FreelancerService::weeklyCountsFor([$maria->id], '2026-09-12');

        $this->assertSame(FreelancerService::countInWeeklyWindow($maria->id, '2026-09-12'), $semana->get($maria->id));
        $this->assertSame(1, $semana->get($maria->id));
        $this->assertSame([], array_column($this->buscar($this->garcom)['blocked'], 'name'));
    }

    /** O telefone é texto livre no cadastro: formata o que dá, mostra o resto como veio. */
    public function test_o_telefone_e_formatado_para_exibir_e_discar(): void
    {
        $casos = [
            '24999998888' => ['(24) 99999-8888', '24999998888'],
            '(24) 3333-4444' => ['(24) 3333-4444', '2433334444'],
            '+55 24 99999-8888' => ['(24) 99999-8888', '24999998888'],
            'recado com a mãe' => ['recado com a mãe', null],
        ];

        foreach ($casos as $gravado => [$exibido, $discado]) {
            $freelancer = new Freelancer(['telephone' => $gravado]);

            $this->assertSame($exibido, $freelancer->phoneFormatted(), "Exibição de '{$gravado}'.");
            $this->assertSame($discado, $freelancer->phoneDigits(), "Discagem de '{$gravado}'.");
        }

        $this->assertNull((new Freelancer(['telephone' => null]))->phoneFormatted());
    }

    /* ---------------------------------------------------------------------
     | Auxiliares
     |---------------------------------------------------------------------*/

    private function buscar(FunctionFreelancer $funcao, string $dia = '2026-09-12'): array
    {
        return app(FreelancerServiceManager::class)->searchByFunction($funcao, Carbon::parse($dia));
    }

    private function freelancer(string $nome, string $telefone = '24999990000'): Freelancer
    {
        return Freelancer::create([
            'name' => $nome,
            'cpf' => (string) random_int(10000000000, 99999999999),
            'telephone' => $telefone,
        ]);
    }

    private function servico(Freelancer $freelancer, FunctionFreelancer $funcao, string $dia, array $sobrescreve = []): FreelancerService
    {
        $service = FreelancerService::create([
            'freelancer_id' => $freelancer->id,
            'function_freelancer_id' => $funcao->id,
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
