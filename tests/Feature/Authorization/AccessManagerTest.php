<?php

namespace Tests\Feature\Authorization;

use App\Authorization\AccessChangeRejected;
use App\Authorization\AccessManager;
use App\Authorization\Permissions as P;
use App\Models\AccessAuditLog;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MigratesAccessSchema;
use Tests\Concerns\SharesSqliteWithUserConnection;
use Tests\TestCase;

/**
 * As duas travas do AccessManager — setor de acesso total nunca fica vazio,
 * e ninguém tira de si o acesso à tela que está usando — e a auditoria.
 */
class AccessManagerTest extends TestCase
{
    use MigratesAccessSchema;
    use SharesSqliteWithUserConnection;

    private AccessManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateAccessSchema();
        $this->shareSqliteWithUserConnection();
        $this->manager = app(AccessManager::class);
    }

    private function sector(string $name): Sector
    {
        return Sector::findOrFail($this->sectorId($name));
    }

    public function test_colocar_no_setor_da_o_acesso_e_fica_registrado(): void
    {
        $admin = $this->makeUser('Admin');
        $this->joinSector($admin, 'TI');
        $pessoa = $this->makeUser('Porteiro Novo');

        $this->actingAs($admin);
        $this->manager->setMembership($admin, $this->sector('Portaria'), $pessoa, Sector::ROLE_COLLABORATOR);

        $this->assertTrue($pessoa->can(P::SIV_FROTA));
        $this->assertDatabaseHas('access_audit_logs', [
            'action' => AccessAuditLog::SECTOR_MEMBER_ADDED,
            'actor_id' => $admin->id,
            'user_id' => $pessoa->id,
            'sector_id' => $this->sectorId('Portaria'),
        ]);
    }

    public function test_setor_de_acesso_total_nao_fica_sem_membros(): void
    {
        $unico = $this->makeUser('Único da Diretoria');
        $this->joinSector($unico, 'Diretoria');

        $admin = $this->makeUser('Admin');
        $this->joinSector($admin, 'TI');

        try {
            $this->manager->setMembership($admin, $this->sector('Diretoria'), $unico, null);
            $this->fail('Deveria ter recusado esvaziar a Diretoria.');
        } catch (AccessChangeRejected $e) {
            $this->assertStringContainsString('Diretoria', $e->getMessage());
        }

        // Nada gravado: a transação voltou.
        $this->assertDatabaseHas('user_sector', ['user_id' => $unico->id, 'sector_id' => $this->sectorId('Diretoria')]);
        $this->assertDatabaseMissing('access_audit_logs', ['action' => AccessAuditLog::SECTOR_MEMBER_REMOVED]);
    }

    public function test_desligar_o_acesso_total_de_um_setor_vazio_e_permitido(): void
    {
        $admin = $this->makeUser('Admin');
        $this->joinSector($admin, 'TI');

        // A Gerência não tem membros no banco de teste; tirar a marca dela não
        // esvazia nada nem tira o acesso de quem está mexendo.
        $this->manager->setFullAccess($admin, $this->sector('Gerência'), false);

        $this->assertFalse($this->sector('Gerência')->full_access);
    }

    public function test_ninguem_tira_de_si_o_acesso_a_gestao(): void
    {
        $admin = $this->makeUser('Admin Sozinho');
        $this->joinSector($admin, 'TI');
        $outro = $this->makeUser('Outro da TI');
        $this->joinSector($outro, 'TI');

        // Sair da TI tiraria dele o acesso total — e com ele Usuários e Setores.
        $this->expectException(AccessChangeRejected::class);

        try {
            $this->manager->setMembership($admin, $this->sector('TI'), $admin, null);
        } finally {
            $this->assertDatabaseHas('user_sector', ['user_id' => $admin->id, 'sector_id' => $this->sectorId('TI')]);
        }
    }

    public function test_tirar_a_propria_permissao_individual_de_gestao_e_recusado(): void
    {
        $admin = $this->makeUser('Admin da Ponte');
        $this->manager->syncUserPermissions($this->superuser(), $admin, [P::USUARIOS_GERENCIAR, P::SETORES_GERENCIAR]);

        $this->expectException(AccessChangeRejected::class);
        $this->manager->syncUserPermissions($admin, $admin, [P::USUARIOS_GERENCIAR]);
    }

    public function test_permissoes_do_setor_so_para_coordenadores(): void
    {
        $admin = $this->superuser();
        $portaria = $this->sector('Portaria');

        $colaborador = $this->makeUser('Colab');
        $this->joinSector($colaborador, 'Portaria', 'collaborator');
        $coordenador = $this->makeUser('Coord');
        $this->joinSector($coordenador, 'Portaria', 'coordinator');

        $this->manager->syncSectorPermissions($admin, $portaria, [P::SIV_BUSCA => false, P::LARA => true]);

        $colaborador->forgetAccess();
        $coordenador->forgetAccess();

        $this->assertTrue($colaborador->can(P::SIV_BUSCA));
        $this->assertFalse($colaborador->can(P::LARA));
        $this->assertTrue($coordenador->can(P::LARA));
        // O que saiu da lista saiu do setor.
        $this->assertFalse($colaborador->can(P::SIV_FROTA));

        $log = AccessAuditLog::where('action', AccessAuditLog::SECTOR_PERMISSIONS_CHANGED)->latest('id')->first();
        $this->assertSame(['de' => null, 'para' => 'coordenadores'], $log->details[P::LARA]);
        $this->assertSame(['de' => 'todos', 'para' => null], $log->details[P::SIV_FROTA]);
    }

    public function test_permissao_fora_do_catalogo_nao_entra(): void
    {
        $pessoa = $this->makeUser('Alguém');

        $this->manager->syncUserPermissions($this->superuser(), $pessoa, ['manage users', P::LARA]);

        $this->assertSame([P::LARA], $pessoa->directPermissions()->pluck('name')->all());
    }

    public function test_trocar_o_papel_no_setor_fica_registrado(): void
    {
        $admin = $this->superuser();
        $pessoa = $this->makeUser('Promovida');
        $this->joinSector($pessoa, 'Atendimento', 'collaborator');

        $this->manager->setMembership($admin, $this->sector('Atendimento'), $pessoa, Sector::ROLE_COORDINATOR);

        $this->assertSame('coordinator', DB::table('user_sector')->where('user_id', $pessoa->id)->value('role'));
        $this->assertTrue($pessoa->can(P::BOT_WHATSAPP));
        $this->assertDatabaseHas('access_audit_logs', ['action' => AccessAuditLog::SECTOR_MEMBER_ROLE_CHANGED, 'user_id' => $pessoa->id]);
    }

    private function superuser(): User
    {
        $user = $this->makeUser('Super ' . uniqid());
        $this->joinSector($user, 'TI');

        return $user;
    }
}
