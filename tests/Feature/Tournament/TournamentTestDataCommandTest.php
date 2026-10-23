<?php

namespace Tests\Feature\Tournament;

use App\Models\Member;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentCategoryTemplate;
use App\Models\Tournament\TournamentMatch as Game;
use App\Models\Tournament\TournamentRegistration as Registration;
use App\Models\Tournament\TournamentTeamInvite as Invite;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsTournaments;
use Tests\Concerns\CreatesTournamentSchema;
use Tests\TestCase;

/**
 * `torneios:dados-teste`: gera os cenários, é idempotente, não manda e-mail,
 * e o `--so-limpar` só alcança o que tem o prefixo [TESTE].
 */
class TournamentTestDataCommandTest extends TestCase
{
    use CreatesTournamentSchema;
    use BuildsTournaments;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->createTournamentSchema();
        Schema::table('members', function (Blueprint $table) {
            $table->date('birth_date')->nullable();
            $table->string('barcode')->nullable();
            $table->string('telephone')->nullable();
            $table->string('password')->nullable();
        });
        $this->fakeIntegrations();
        $this->makeUser('Organizador');
        DB::table('place_groups')->insert(['name' => 'Beach Tennis', 'category' => 'esportiva']);
    }

    public function test_gera_todos_os_cenarios_e_limpa_so_o_de_teste(): void
    {
        // O que já existe e não é de teste.
        $real = $this->tournament(['title' => 'Copa de Verdade']);
        $socio = $this->member('39053344705', 'Ana Verdadeira', '1001');

        $this->artisan('torneios:dados-teste', ['--socio' => '390.533.447-05'])->assertSuccessful();

        $titles = Tournament::where('title', 'like', '[TESTE]%')->pluck('status', 'title');
        $this->assertSame([
            '[TESTE] Inscrições abertas' => Tournament::STATUS_PUBLISHED,
            '[TESTE] Em andamento' => Tournament::STATUS_IN_PROGRESS,
            '[TESTE] Rascunho' => Tournament::STATUS_DRAFT,
            '[TESTE] Encerrado' => Tournament::STATUS_FINISHED,
            '[TESTE] Cancelado' => Tournament::STATUS_CANCELLED,
        ], $titles->all());

        $open = Tournament::where('title', '[TESTE] Inscrições abertas')->first();
        $this->assertTrue($open->registrationOpen());
        $statuses = Registration::whereIn('tournament_category_id', $open->categories()->pluck('id'))->pluck('status')->unique()->sort()->values()->all();
        $this->assertSame(['cancelled', 'paid', 'pending_payment', 'withdrawn'], $statuses);
        $this->assertSame(1, Registration::where('member_id', $socio->id)->count());
        $this->assertSame(1, Invite::where('cpf', $socio->cpf)->where('status', Invite::STATUS_PENDING)->count());

        $running = Tournament::where('title', '[TESTE] Em andamento')->first();
        $this->assertTrue($running->resultsCodeActive());
        $this->assertSame(2, $running->categories()->whereNotNull('bracket_published_at')->count());
        $this->assertTrue(Game::where('locked_by_lara', true)->exists());
        $this->assertTrue(Game::where('result_origin', 'site')->exists());
        $this->assertTrue(Game::where('status', Game::STATUS_WALKOVER)->exists());

        $finished = Tournament::where('title', '[TESTE] Encerrado')->first();
        $finalIds = TournamentCategory::where('tournament_id', $finished->id)->first()->matches()->where('round', 2)->pluck('status')->unique()->all();
        $this->assertSame([Game::STATUS_FINISHED], $finalIds);

        $this->assertSame(2, Member::where('cpf', Member::where('name', '[TESTE] Paula Freitas')->value('cpf'))->count());
        Mail::assertNothingQueued();
        Mail::assertNothingSent();

        // Rodar de novo não duplica.
        $this->artisan('torneios:dados-teste')->assertSuccessful();
        $this->assertSame(5, Tournament::where('title', 'like', '[TESTE]%')->count());

        // Limpar só tira o que é de teste.
        $this->artisan('torneios:dados-teste', ['--so-limpar' => true])->assertSuccessful();
        $this->assertSame(0, Tournament::withTrashed()->where('title', 'like', '[TESTE]%')->count());
        $this->assertSame(0, Member::withTrashed()->where('name', 'like', '[TESTE]%')->count());
        $this->assertSame(0, TournamentCategoryTemplate::withTrashed()->where('name', 'like', '[TESTE]%')->count());
        $this->assertNotNull($real->fresh());
        $this->assertNotNull($socio->fresh());
        $this->assertSame(0, Registration::count());
    }

    public function test_recusa_o_banco_de_producao(): void
    {
        config(['database.connections.mysql.database' => 'lara']);

        $this->artisan('torneios:dados-teste')->assertFailed();
        $this->assertSame(0, Tournament::count());
    }
}
