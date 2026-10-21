<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\MigratesAccessSchema;
use Tests\Concerns\SharesSqliteWithUserConnection;
use Tests\TestCase;

/**
 * CPF do usuário na tela de Usuários: é o que a Assinatura Diretoria imprime
 * no documento do diretor. Grava só dígitos, recusa CPF inválido ou repetido.
 */
class UserCpfTest extends TestCase
{
    use MigratesAccessSchema;
    use SharesSqliteWithUserConnection;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateAccessSchema();
        Artisan::call('migrate', ['--path' => [
            // O layout consulta notificações e avisos em toda tela.
            'database/migrations/2026_06_10_000001_create_notifications_table.php',
            'database/migrations/2026_06_10_000002_create_avisos_table.php',
            'database/migrations/2026_06_10_000003_avisos_privacy_and_lembretes.php',
            'database/migrations/2026_06_10_000004_create_aviso_views_table.php',
            'database/migrations/2026_06_10_000006_create_tags_tables.php',
            'database/migrations/2026_06_12_000002_create_aviso_user_table.php',
            'database/migrations/2026_07_31_120000_add_mandatory_reading_to_avisos.php',
        ]]);
        $this->shareSqliteWithUserConnection();

        // TI tem acesso total: alcança a tela de Usuários sem configurar setor.
        $this->admin = $this->makeUser('Pessoa da TI');
        $this->joinSector($this->admin, 'TI');
    }

    private function payload(User $user, array $extra = []): array
    {
        return array_merge(['name' => $user->name, 'email' => $user->email, 'status' => 1], $extra);
    }

    public function test_grava_o_cpf_so_com_digitos(): void
    {
        $user = $this->makeUser('Diretor Financeiro');

        $this->actingAs($this->admin)
            ->put(route('users.update', $user->id), $this->payload($user, ['cpf' => '529.982.247-25']))
            ->assertSessionHasNoErrors();

        $this->assertSame('52998224725', $user->fresh()->cpf);
    }

    public function test_recusa_cpf_invalido(): void
    {
        $user = $this->makeUser('Diretor Financeiro');

        $this->actingAs($this->admin)
            ->put(route('users.update', $user->id), $this->payload($user, ['cpf' => '111.111.111-11']))
            ->assertSessionHasErrors('cpf');

        $this->assertNull($user->fresh()->cpf);
    }

    public function test_recusa_cpf_de_outro_usuario(): void
    {
        $this->makeUser('Outro', ['cpf' => '52998224725']);
        $user = $this->makeUser('Diretor Financeiro');

        $this->actingAs($this->admin)
            ->put(route('users.update', $user->id), $this->payload($user, ['cpf' => '52998224725']))
            ->assertSessionHasErrors('cpf');
    }

    public function test_em_branco_limpa_o_cpf(): void
    {
        $user = $this->makeUser('Diretor Financeiro', ['cpf' => '52998224725']);

        $this->actingAs($this->admin)
            ->put(route('users.update', $user->id), $this->payload($user, ['cpf' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->cpf);
    }

    public function test_tela_mostra_o_cpf_formatado(): void
    {
        $user = $this->makeUser('Diretor Financeiro', ['cpf' => '52998224725']);

        $this->actingAs($this->admin)->get(route('users.edit', $user->id))
            ->assertOk()
            ->assertSee('529.982.247-25');
    }
}
