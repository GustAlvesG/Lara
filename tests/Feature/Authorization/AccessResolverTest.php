<?php

namespace Tests\Feature\Authorization;

use App\Authorization\Permissions as P;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MigratesAccessSchema;
use Tests\Concerns\SharesSqliteWithUserConnection;
use Tests\TestCase;

/**
 * O acesso efetivo calculado de verdade, contra o banco que as migrations
 * montam — inclusive a matriz inicial da `sync_access_catalog`. É o teste
 * que diz "a Portaria alcança a frota e não alcança a liberação pontual".
 */
class AccessResolverTest extends TestCase
{
    use MigratesAccessSchema;
    use SharesSqliteWithUserConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateAccessSchema();
        $this->shareSqliteWithUserConnection();
    }

    public function test_sem_setor_nao_alcanca_nada(): void
    {
        $user = $this->makeUser('Sem Setor');

        $this->assertSame([], $user->access()->permissions);
        $this->assertFalse($user->hasFullAccess());
        $this->assertFalse($user->can(P::SIV_BUSCA));
    }

    public function test_atendimento_e_a_secretaria_da_matriz(): void
    {
        $user = $this->makeUser('Recepção');
        $this->joinSector($user, 'Atendimento');

        foreach ([P::INFOCLUBE_EDITAR, P::SIV_BUSCA, P::SIV_PLACAS_DIRETORIA, P::HOME_ASSISTANT,
                  P::RESERVAS_AGENDAMENTOS, P::RESERVAS_PAGAMENTOS, P::RESERVAS_PAGAMENTOS_ESTORNAR,
                  P::EXTERNOS_LIBERACAO_PONTUAL, P::FREELANCERS_CADASTRO, P::FREELANCERS_SERVICOS_LISTAR] as $permission) {
            $this->assertTrue($user->can($permission), "Atendimento deveria alcançar {$permission}");
        }

        // A Secretaria vê a lista de serviços, mas não os valores nem o resto.
        $this->assertFalse($user->can(P::FREELANCERS_SERVICOS_GERENCIAR));
        $this->assertFalse($user->can(P::FREELANCERS_FUNCOES));
        $this->assertFalse($user->can(P::LARA));
    }

    public function test_bot_whatsapp_e_so_do_coordenador_do_atendimento(): void
    {
        $colaborador = $this->makeUser('Colaboradora');
        $this->joinSector($colaborador, 'Atendimento', 'collaborator');

        $coordenador = $this->makeUser('Coordenadora');
        $this->joinSector($coordenador, 'Atendimento', 'coordinator');

        $this->assertFalse($colaborador->can(P::BOT_WHATSAPP));
        $this->assertTrue($coordenador->can(P::BOT_WHATSAPP));
    }

    public function test_portaria_alcanca_frota_e_externos_mas_nao_a_liberacao_pontual(): void
    {
        $user = $this->makeUser('Porteiro');
        $this->joinSector($user, 'Portaria');

        $this->assertTrue($user->can(P::SIV_BUSCA));
        $this->assertTrue($user->can(P::SIV_FROTA));
        $this->assertTrue($user->can(P::EXTERNOS_HISTORICO));
        $this->assertTrue($user->can(P::EXTERNOS_CARROS_APLICATIVO));
        $this->assertFalse($user->can(P::EXTERNOS_LIBERACAO_PONTUAL));
        $this->assertFalse($user->can(P::SIV_PLACAS_DIRETORIA));
    }

    public function test_contabilidade_e_o_financeiro(): void
    {
        $user = $this->makeUser('Contadora');
        $this->joinSector($user, 'Contabilidade');

        $this->assertTrue($user->can(P::COMPRAS));
        $this->assertTrue($user->can(P::FREELANCERS_FINANCEIRO));
        $this->assertTrue($user->can(P::RESERVAS_PAGAMENTOS));
        $this->assertFalse($user->can(P::RESERVAS_AGENDAMENTOS));
    }

    public function test_gerencia_diretoria_e_ti_tem_acesso_total(): void
    {
        foreach (['Gerência', 'Diretoria', 'TI'] as $sector) {
            $user = $this->makeUser('Pessoa ' . $sector);
            $this->joinSector($user, $sector);

            $this->assertTrue($user->hasFullAccess(), "{$sector} deveria ter acesso total");

            foreach (P::all() as $permission) {
                $this->assertTrue($user->can($permission), "{$sector} deveria alcançar {$permission}");
            }
        }
    }

    public function test_permissao_individual_soma_a_do_setor_e_diz_a_origem(): void
    {
        $user = $this->makeUser('Porteira');
        $this->joinSector($user, 'Portaria');
        $this->grantDirect($user->id, P::LARA);

        $user->forgetAccess();

        $this->assertTrue($user->can(P::LARA));
        $this->assertSame(['individual'], $user->access()->sourcesOf(P::LARA));
        $this->assertSame(['setor Portaria'], $user->access()->sourcesOf(P::SIV_FROTA));
    }

    /** Permissão antiga que sobrou na tabela não pode virar acesso. */
    public function test_permissao_fora_do_catalogo_e_ignorada(): void
    {
        $user = $this->makeUser('Antigo');
        $legacyId = DB::table('permissions')->insertGetId(['name' => 'manage users', 'guard_name' => 'web']);
        DB::table('model_has_permissions')->insert(['permission_id' => $legacyId, 'model_type' => $user->getMorphClass(), 'model_id' => $user->id]);

        $this->assertSame([], $user->access()->permissions);
    }

    public function test_o_acesso_e_calculado_uma_vez_por_instancia(): void
    {
        $user = $this->makeUser('Uma Vez');
        $this->joinSector($user, 'Portaria');

        $connection = DB::connection($user->getConnectionName());
        $connection->enableQueryLog();
        $user->can(P::SIV_BUSCA);
        $user->can(P::SIV_FROTA);
        $user->can(P::LARA);
        $queries = count($connection->getQueryLog());

        // Três consultas (acesso total, setores, individuais) — não três por can().
        $this->assertLessThanOrEqual(3, $queries);
    }

    private function grantDirect(int $userId, string $permission): void
    {
        DB::table('model_has_permissions')->insert([
            'permission_id' => DB::table('permissions')->where('name', $permission)->value('id'),
            'model_type' => 'App\\Models\\User',
            'model_id' => $userId,
        ]);
    }
}
