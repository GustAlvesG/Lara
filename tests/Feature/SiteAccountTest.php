<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MemberAccountLog;
use App\Models\User;
use App\Services\Members\SiteAccountService;
use App\Services\MultiClubes\MemberProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsTournaments;
use Tests\Concerns\CreatesTournamentSchema;
use Tests\TestCase;

/**
 * Reservas › Contas do site: senha provisória (que entra de verdade no
 * login do site, que manda a senha em SHA256), correção de e-mail e
 * telefone, "trazer do MultiClubes", cadastro manual e conta duplicada.
 */
class SiteAccountTest extends TestCase
{
    use CreatesTournamentSchema;
    use BuildsTournaments;

    private const CPF = '39053344705';

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.api.token' => 'token-de-teste', 'services.jwt.secret' => 'segredo-de-teste']);

        // O schema dos torneios já traz `members` (com deleted_at) e o MultiClubes falso.
        $this->createTournamentSchema();
        Schema::table('members', function (Blueprint $table) {
            $table->date('birth_date')->nullable();
            $table->string('barcode')->nullable();
            $table->string('telephone')->nullable();
            $table->string('password')->nullable();
            $table->string('login_token', 80)->nullable();
        });
        (require database_path('migrations/2026_10_23_120000_create_member_account_logs_table.php'))->up();
        $this->fakeIntegrations();

        $this->staff = $this->makeUser('Pessoa da Recepção');
        $this->joinSector($this->staff, 'TI');
    }

    private function account(array $attributes = []): Member
    {
        $id = DB::table('members')->insertGetId(array_merge([
            'cpf' => self::CPF, 'name' => 'Ana Souza', 'title' => '1001', 'email' => 'errado@x.com',
            'password' => hash('sha256', 'antiga'), 'created_at' => now(),
        ], $attributes));

        return Member::withTrashed()->findOrFail($id);
    }

    private function profile(string $email = 'ana@mc.local', ?string $phone = '21999990000'): void
    {
        $this->mcProfiles[self::CPF . '|1001'] = new MemberProfile('Ana Souza Lima', self::CPF, '1001', Carbon::parse('1990-05-10'), 'female', $email, true, $phone, '000123');
    }

    private function login(string $plain)
    {
        // O site manda o SHA256 da senha digitada. Os headers não podem
        // vazar para as requisições do painel que vêm depois.
        $response = $this->withHeaders(['Authorization' => 'Bearer token-de-teste', 'Accept' => 'application/json'])
            ->postJson('/api/login', ['login' => self::CPF, 'password' => hash('sha256', $plain)]);
        $this->flushHeaders();

        return $response;
    }

    public function test_senha_provisoria_entra_no_login_do_site(): void
    {
        $member = $this->account();
        $this->login('antiga')->assertOk();

        $this->actingAs($this->staff)->post(route('site-accounts.password', $member->id))
            ->assertRedirect(route('site-accounts.show', $member->id))
            ->assertSessionHas('temporary_password');
        $password = session('temporary_password');

        $this->assertMatchesRegularExpression('/^[a-z2-9]{8}$/', $password);
        $this->assertTrue(Hash::check(hash('sha256', $password), $member->fresh()->password));
        $this->login($password)->assertOk()->assertJsonPath('user.cpf', self::CPF);
        $this->login('antiga')->assertUnauthorized();

        $log = MemberAccountLog::sole();
        $this->assertSame(MemberAccountLog::ACTION_PASSWORD, $log->action);
        $this->assertNull($log->details);
    }

    public function test_corrige_email_e_traz_do_multiclubes(): void
    {
        $member = $this->account();
        $this->profile();

        $this->actingAs($this->staff)->get(route('site-accounts.show', $member->id))
            ->assertOk()
            ->assertSee('errado@x.com')
            ->assertSee('ana@mc.local')
            ->assertSee('Sócio ativo no MultiClubes');

        $this->actingAs($this->staff)->put(route('site-accounts.update', $member->id), ['email' => 'ana@certo.com', 'telephone' => '2133334444'])->assertRedirect();
        $this->assertSame('ana@certo.com', $member->fresh()->email);

        $this->actingAs($this->staff)->post(route('site-accounts.pull', $member->id))->assertRedirect();
        $member->refresh();
        $this->assertSame(['Ana Souza Lima', 'ana@mc.local', '21999990000'], [$member->name, $member->email, $member->telephone]);

        $this->assertSame(
            [MemberAccountLog::ACTION_PULLED, MemberAccountLog::ACTION_UPDATED],
            $member->accountLogs()->pluck('action')->all(),
        );
        $this->assertSame('errado@x.com', $member->accountLogs()->where('action', MemberAccountLog::ACTION_UPDATED)->first()->details['before']['email']);
    }

    public function test_cadastro_manual(): void
    {
        $this->profile();

        $this->actingAs($this->staff)->post(route('site-accounts.store'), ['cpf' => '390.533.447-05', 'title' => '1001'])
            ->assertSessionHas('temporary_password');
        $member = Member::where('cpf', self::CPF)->sole();
        $this->assertSame(['Ana Souza Lima', '1001', '1990-05-10', '000123'], [$member->name, $member->title, (string) $member->birth_date, $member->barcode]);
        $this->login(session('temporary_password'))->assertOk();

        // Já tem conta.
        $this->actingAs($this->staff)->post(route('site-accounts.store'), ['cpf' => self::CPF, 'title' => '1001'])->assertSessionHasErrors('cpf');
        // Não é sócio ativo nessa matrícula.
        $this->actingAs($this->staff)->post(route('site-accounts.store'), ['cpf' => '86288366757', 'title' => '9999'])->assertSessionHasErrors('title');
        $this->assertSame(1, Member::count());
    }

    public function test_duplicada_desativa_e_o_login_vai_para_a_outra(): void
    {
        $first = $this->account(['password' => hash('sha256', 'da-primeira')]);
        $second = $this->account(['password' => hash('sha256', 'da-segunda'), 'email' => 'ana@certo.com']);

        // O login pega a mais antiga: a senha da segunda não entra.
        $this->login('da-segunda')->assertUnauthorized();

        $this->actingAs($this->staff)->get(route('site-accounts.index'))->assertOk()->assertSee('CPF duplicado');
        $this->actingAs($this->staff)->get(route('site-accounts.show', $second->id))->assertOk()->assertSee('2 contas ativas', false);

        $this->actingAs($this->staff)->delete(route('site-accounts.destroy', $first->id), ['reason' => 'Cadastro repetido'])->assertRedirect();
        $this->assertSoftDeleted('members', ['id' => $first->id]);
        $this->login('da-segunda')->assertOk();

        // A única conta ativa não pode ser desativada; a desativada não volta enquanto há outra.
        $this->actingAs($this->staff)->delete(route('site-accounts.destroy', $second->id), ['reason' => 'x'])->assertSessionHasErrors('member');
        $this->actingAs($this->staff)->post(route('site-accounts.restore', $first->id))->assertSessionHasErrors('member');

        // A desativada continua aparecendo na busca, marcada.
        $this->actingAs($this->staff)->get(route('site-accounts.index', ['q' => self::CPF]))->assertOk()->assertSee('desativada');
    }

    public function test_sem_permissao_nao_abre(): void
    {
        $member = $this->account();
        $other = $this->makeUser('Sem Acesso');

        $this->actingAs($other)->get(route('site-accounts.index'))->assertForbidden();
        $this->actingAs($other)->post(route('site-accounts.password', $member->id))->assertForbidden();
    }

    public function test_senha_gerada_e_a_do_site(): void
    {
        $this->assertTrue(Hash::check(hash('sha256', 'abc123'), SiteAccountService::siteHash('abc123')));
    }
}
