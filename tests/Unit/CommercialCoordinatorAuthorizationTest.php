<?php

namespace Tests\Unit;

use App\Exceptions\CoordinatorAuthorizationException;
use App\Http\Controllers\Concerns\AuthorizesCommercialCoordinator;
use App\Models\User;
use Tests\TestCase;

/**
 * Liberação do limite de 7 dias: quem autoriza é SEMPRE o coordenador do setor
 * Comercial, identificado pela própria matrícula e pelo próprio PIN. É a mesma
 * regra usada pelo painel web e pelo tablet, então é testada uma vez só, direto
 * no trait — sem banco, porque o model User está preso à conexão `mysql` e a
 * suíte roda em SQLite.
 */
class CommercialCoordinatorAuthorizationTest extends TestCase
{
    private function authorizer(?User $found): object
    {
        return new class($found) {
            use AuthorizesCommercialCoordinator;

            public function __construct(private ?User $found)
            {
            }

            protected function findUserByMatricula(string $matricula): ?User
            {
                return $this->found;
            }

            public function run(?string $matricula, ?string $pin): User
            {
                return $this->authorizeCommercialCoordinator($matricula, $pin);
            }
        };
    }

    /** @param  string|null  $sector  setor do qual o usuário é coordenador */
    private function user(?string $sector, ?string $pin = '123456', int $statusId = 1): User
    {
        $user = new class extends User {
            public ?string $coordinatorSector = null;

            public function isCoordinatorOfSectorNamed(string $name): bool
            {
                return $this->coordinatorSector !== null
                    && mb_strtolower($this->coordinatorSector) === mb_strtolower($name);
            }
        };

        $user->coordinatorSector = $sector;
        $user->name = 'Coordenador';
        $user->matricula = '1234';
        $user->status_id = $statusId;

        // O cast `hashed` do model já grava o PIN em hash.
        if ($pin !== null) {
            $user->pin = $pin;
        }

        return $user;
    }

    public function test_coordenador_do_comercial_com_pin_correto_libera(): void
    {
        $coordinator = $this->user('Comercial');

        $authorized = $this->authorizer($coordinator)->run('1234', '123456');

        $this->assertSame($coordinator, $authorized);
    }

    public function test_nome_do_setor_nao_diferencia_maiusculas(): void
    {
        $authorized = $this->authorizer($this->user('comercial'))->run('1234', '123456');

        $this->assertNotNull($authorized);
    }

    public function test_coordenador_de_outro_setor_nao_libera(): void
    {
        $this->expectException(CoordinatorAuthorizationException::class);
        $this->expectExceptionMessage('Apenas o coordenador do setor Comercial pode liberar este registro.');

        $this->authorizer($this->user('Operacional'))->run('1234', '123456');
    }

    public function test_usuario_que_nao_e_coordenador_nao_libera(): void
    {
        $this->expectException(CoordinatorAuthorizationException::class);

        $this->authorizer($this->user(null))->run('1234', '123456');
    }

    public function test_pin_errado_do_coordenador_certo_pede_o_pin_de_novo(): void
    {
        try {
            $this->authorizer($this->user('Comercial'))->run('1234', '999999');
            $this->fail('Deveria ter recusado o PIN errado.');
        } catch (CoordinatorAuthorizationException $e) {
            $this->assertSame('PIN inválido.', $e->getMessage());
            // A matrícula já foi aceita: a tela só repete o passo do PIN.
            $this->assertSame('pin', $e->step);
        }
    }

    public function test_matricula_desconhecida_e_recusada(): void
    {
        try {
            $this->authorizer(null)->run('9999', '123456');
            $this->fail('Deveria ter recusado a matrícula inexistente.');
        } catch (CoordinatorAuthorizationException $e) {
            $this->assertSame('Matrícula não encontrada ou usuário inativo.', $e->getMessage());
            $this->assertSame('matricula', $e->step);
        }
    }

    public function test_coordenador_inativo_nao_libera(): void
    {
        $this->expectException(CoordinatorAuthorizationException::class);
        $this->expectExceptionMessage('Matrícula não encontrada ou usuário inativo.');

        $this->authorizer($this->user('Comercial', '123456', statusId: 2))->run('1234', '123456');
    }

    public function test_coordenador_sem_pin_nao_libera(): void
    {
        $this->expectException(CoordinatorAuthorizationException::class);
        $this->expectExceptionMessage('Este coordenador ainda não tem PIN definido. Cadastre um PIN de 6 dígitos no painel (Usuários).');

        $this->authorizer($this->user('Comercial', pin: null))->run('1234', '123456');
    }

    public function test_matricula_em_branco_e_recusada_antes_de_qualquer_consulta(): void
    {
        $this->expectException(CoordinatorAuthorizationException::class);
        $this->expectExceptionMessage('Informe a matrícula do coordenador do setor Comercial.');

        $this->authorizer($this->user('Comercial'))->run(null, '123456');
    }

    public function test_pin_em_branco_e_recusado(): void
    {
        $this->expectException(CoordinatorAuthorizationException::class);
        $this->expectExceptionMessage('PIN inválido.');

        $this->authorizer($this->user('Comercial'))->run('1234', null);
    }
}
