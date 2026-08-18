<?php

namespace Tests\Feature;

use App\Models\Freelancer;
use App\Models\FreelancerService;
use App\Models\FunctionFreelancer;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesFreelancerPixSchema;
use Tests\TestCase;

/**
 * As tags "Garçom - 4" da listagem de freelancers.
 *
 * O que a tag promete é *serviço trabalhado*, e é isso que estes testes
 * seguram: contrato cancelado e aditivo não podem entrar na conta, senão o
 * número da tela passa a ser maior do que o número de vezes que a pessoa
 * efetivamente pegou serviço — que é a informação usada para escalar.
 *
 * Como nos demais testes do módulo, nada autentica ninguém nem passa pela
 * rota: o model User fixa a conexão `mysql` e tocá-lo levaria a suíte para
 * fora do SQLite.
 */
class FreelancerFunctionTagsTest extends TestCase
{
    use CreatesFreelancerPixSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFreelancerPixSchema();
    }

    public function test_conta_os_servicos_por_funcao_do_mais_atuado_ao_menos(): void
    {
        $freelancer = $this->freelancer();
        $garcom = $this->funcao('Garçom');
        $cozinha = $this->funcao('Auxiliar de Cozinha');

        $this->servico($freelancer, $garcom);
        $this->servico($freelancer, $garcom);
        $this->servico($freelancer, $cozinha);

        $counts = FreelancerService::functionCountsFor([$freelancer->id]);

        $this->assertSame(
            ['Garçom' => 2, 'Auxiliar de Cozinha' => 1],
            $counts[$freelancer->id]
        );
    }

    public function test_ignora_cancelados_e_aditivos(): void
    {
        $freelancer = $this->freelancer();
        $garcom = $this->funcao('Garçom');

        $base = $this->servico($freelancer, $garcom);
        $this->servico($freelancer, $garcom, ['status_id' => FreelancerService::STATUS_CANCELLED]);
        $this->servico($freelancer, $garcom, ['parent_service_id' => $base->id]);

        $counts = FreelancerService::functionCountsFor([$freelancer->id]);

        $this->assertSame(['Garçom' => 1], $counts[$freelancer->id]);
    }

    public function test_separa_a_contagem_por_freelancer_e_omite_quem_nao_atuou(): void
    {
        $comServico = $this->freelancer();
        $semServico = $this->freelancer();
        $garcom = $this->funcao('Garçom');

        $this->servico($comServico, $garcom);

        $counts = FreelancerService::functionCountsFor([$comServico->id, $semServico->id]);

        $this->assertSame(['Garçom' => 1], $counts[$comServico->id]);
        $this->assertArrayNotHasKey($semServico->id, $counts);
    }

    public function test_sem_freelancers_nao_consulta_nada(): void
    {
        $this->assertTrue(FreelancerService::functionCountsFor([])->isEmpty());
    }

    /* ---------------------------------------------------------------------
     | Apoio
     |---------------------------------------------------------------------*/

    private function freelancer(): Freelancer
    {
        return Freelancer::create([
            'name' => 'Freelancer ' . Str::random(6),
            'cpf' => (string) random_int(10000000000, 99999999999),
        ]);
    }

    private function funcao(string $nome): FunctionFreelancer
    {
        return FunctionFreelancer::create(['name' => $nome, 'price' => 10.00]);
    }

    private function servico(Freelancer $freelancer, FunctionFreelancer $funcao, array $sobrescreve = []): FreelancerService
    {
        $service = FreelancerService::create([
            'freelancer_id' => $freelancer->id,
            'function_freelancer_id' => $funcao->id,
            'location' => 'Salão',
            'start_date' => now()->subDays(4)->toDateString(),
            'start_time' => '18:00',
            'end_date' => now()->subDays(4)->toDateString(),
            'end_time' => '22:00',
            'price' => 40.00,
            'total_hours' => 4,
        ]);

        if ($sobrescreve !== []) {
            $service->forceFill($sobrescreve)->save();
        }

        return $service->refresh();
    }
}
