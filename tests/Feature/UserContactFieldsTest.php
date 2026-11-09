<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\MigratesAccessSchema;
use Tests\Concerns\SharesSqliteWithUserConnection;
use Tests\TestCase;

/**
 * CPF, e-mail e telefone do usuário (09/11/2026): obrigatórios no registro,
 * no perfil a pessoa cadastra o CPF só se não tem (depois fica só leitura) e
 * o telefone à vontade; na tela de Usuários, telefone ao lado do CPF.
 */
class UserContactFieldsTest extends TestCase
{
    use MigratesAccessSchema;
    use SharesSqliteWithUserConnection;

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
    }

    public function test_telefone_valida_e_formata(): void
    {
        $this->assertTrue(Phone::isValid('(11) 98765-4321'));
        $this->assertTrue(Phone::isValid('1132654321'));
        $this->assertTrue(Phone::isValid('+55 11 98765-4321'));
        $this->assertFalse(Phone::isValid('98765-4321'));
        $this->assertFalse(Phone::isValid('0011987654321'));
        $this->assertSame('(11) 98765-4321', Phone::format('11987654321'));
        $this->assertSame('(11) 3265-4321', Phone::format('1132654321'));
    }

    public function test_registro_exige_cpf_e_telefone(): void
    {
        $this->post('/register', [
            'name' => 'Nova Pessoa',
            'email' => 'nova@teste.local',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ])->assertSessionHasErrors(['cpf', 'phone']);

        $this->assertSame(0, User::where('email', 'nova@teste.local')->count());
    }

    public function test_registro_grava_cpf_e_telefone_so_com_digitos(): void
    {
        $this->post('/register', [
            'name' => 'Nova Pessoa',
            'email' => 'nova@teste.local',
            'cpf' => '529.982.247-25',
            'phone' => '(11) 98765-4321',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ])->assertSessionHasNoErrors();

        $user = User::where('email', 'nova@teste.local')->sole();

        $this->assertSame('52998224725', $user->cpf);
        $this->assertSame('11987654321', $user->phone);
    }

    public function test_perfil_cadastra_cpf_e_telefone_quando_nao_tem(): void
    {
        $user = $this->makeUser('Pessoa Sem Dados', ['status_id' => 1]);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Complete o seu cadastro: falta CPF e telefone');

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'cpf' => '529.982.247-25',
            'phone' => '(11) 98765-4321',
        ])->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('52998224725', $user->cpf);
        $this->assertSame('11987654321', $user->phone);
    }

    public function test_perfil_nao_troca_cpf_ja_cadastrado(): void
    {
        $user = $this->makeUser('Pessoa Com CPF', ['status_id' => 1, 'cpf' => '52998224725', 'phone' => '11987654321']);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('Complete o seu cadastro')
            ->assertDontSee('name="cpf"', false);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'cpf' => '111.444.777-35',
            'phone' => '(21) 3265-4321',
        ])->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('52998224725', $user->cpf);
        $this->assertSame('2132654321', $user->phone);
    }

    public function test_perfil_recusa_telefone_invalido(): void
    {
        $user = $this->makeUser('Pessoa', ['status_id' => 1]);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '1234',
        ])->assertSessionHasErrors('phone');
    }

    public function test_tela_de_usuarios_grava_e_mostra_o_telefone(): void
    {
        $admin = $this->makeUser('Pessoa da TI');
        $this->joinSector($admin, 'TI');
        $user = $this->makeUser('Diretor Financeiro');

        $this->actingAs($admin)
            ->put(route('users.update', $user->id), [
                'name' => $user->name, 'email' => $user->email, 'status' => 1, 'phone' => '(11) 98765-4321',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('11987654321', $user->fresh()->phone);

        $this->actingAs($admin)->get(route('users.edit', $user->id))
            ->assertOk()
            ->assertSee('(11) 98765-4321');
    }
}
