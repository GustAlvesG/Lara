<?php

namespace Tests\Unit;

use App\Http\Controllers\Freelancer\BatchController;
use App\Http\Requests\ReviewFreelancerBatchRequest;
use App\Models\User;
use App\Services\FreelancerBatchService;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Quem aprova o lote de contratos antes da diretoria.
 *
 * A regra mudou: era a role `admin` do Spatie, passou a ser o **coordenador do
 * setor Gerência**. Responder pelo lote é um cargo, não um nível de acesso ao
 * sistema — administrador continua administrando, e não aprova nada.
 *
 * São duas portas para a mesma regra, e as duas são testadas: o
 * `ReviewFreelancerBatchRequest` (a análise em si) e o `BatchController`
 * (fila, tela do lote e registro do PIN da diretoria).
 *
 * Sem banco: o model User está preso à conexão `mysql` e a suíte roda em
 * SQLite. O dublê responde pelo vínculo de setor sem consultar `user_sector`.
 */
class BatchApprovalAuthorizationTest extends TestCase
{
    /**
     * @param  string|null  $sector  setor do qual o usuário é coordenador
     * @param  bool  $admin  se ele tem a role `admin` — que não deve mais pesar
     */
    private function user(?string $sector, bool $admin = false): User
    {
        $user = new class extends User {
            public ?string $coordinatorSector = null;
            public bool $isAdmin = false;

            public function isCoordinatorOfSectorNamed(string $name): bool
            {
                return $this->coordinatorSector !== null
                    && mb_strtolower($this->coordinatorSector) === mb_strtolower($name);
            }

            /** Evita a consulta do Spatie: aqui a role é só um dado do dublê. */
            public function hasRole($roles, ?string $guard = null): bool
            {
                return $this->isAdmin && $roles === 'admin';
            }
        };

        $user->coordinatorSector = $sector;
        $user->isAdmin = $admin;
        $user->name = 'Fulano';

        return $user;
    }

    /** A análise do lote (POST /lotes/{batch}/analise). */
    private function canReview(User $user): bool
    {
        $request = new ReviewFreelancerBatchRequest();
        $request->setUserResolver(fn () => $user);

        return $request->authorize();
    }

    /** Fila, tela do lote e decisão da diretoria — todas passam por isManager(). */
    private function isManager(User $user): bool
    {
        $request = Request::create('/freelancer-services/lotes/aprovacao');
        $this->app->instance('request', $request);
        // Depois do bind, e não antes: registrar a instância dispara o
        // rebinding do AuthServiceProvider, que troca o userResolver pelo do
        // guard e apagaria o dublê.
        $request->setUserResolver(fn () => $user);

        $method = new ReflectionMethod(BatchController::class, 'isManager');
        $method->setAccessible(true);

        return $method->invoke(new BatchController($this->app->make(FreelancerBatchService::class)));
    }

    public function test_coordenador_da_gerencia_aprova_o_lote(): void
    {
        $user = $this->user('Gerência');

        $this->assertTrue($user->isManagementCoordinator());
        $this->assertTrue($this->canReview($user));
        $this->assertTrue($this->isManager($user));
    }

    /** O que a mudança tirou: administrar o sistema não aprova mais lote. */
    public function test_admin_que_nao_e_coordenador_da_gerencia_nao_aprova(): void
    {
        $user = $this->user(null, admin: true);

        $this->assertTrue($user->hasRole('admin'), 'o dublê precisa mesmo ser admin');
        $this->assertFalse($user->isManagementCoordinator());
        $this->assertFalse($this->canReview($user));
        $this->assertFalse($this->isManager($user));
    }

    /** Coordenador é de um setor só para este fim — o Comercial monta, não aprova. */
    public function test_coordenador_de_outro_setor_nao_aprova(): void
    {
        $user = $this->user('Comercial');

        $this->assertFalse($user->isManagementCoordinator());
        $this->assertFalse($this->canReview($user));
        $this->assertFalse($this->isManager($user));
    }

    public function test_usuario_sem_setor_nenhum_nao_aprova(): void
    {
        $this->assertFalse($this->canReview($this->user(null)));
    }

    /** Nome do setor digitado em outra caixa continua valendo. */
    public function test_nome_do_setor_nao_diferencia_maiusculas(): void
    {
        $this->assertTrue($this->user('gerência')->isManagementCoordinator());
    }

    /** Visitante sem sessão (rota fora do `auth`, por descuido) não passa. */
    public function test_sem_usuario_autenticado_nao_aprova(): void
    {
        $request = new ReviewFreelancerBatchRequest();
        $request->setUserResolver(fn () => null);

        $this->assertFalse($request->authorize());
    }
}
