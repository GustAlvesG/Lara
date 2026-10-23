<?php

namespace Tests\Feature\Tournament;

use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentCategoryTemplate as Template;
use App\Models\Tournament\TournamentPayment;
use App\Models\Tournament\TournamentRegistration as Registration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTournaments;
use Tests\Concerns\CreatesTournamentSchema;
use Tests\TestCase;

/**
 * Torneios, F3, no painel: cadastro do torneio, categorias do catálogo,
 * situação, e a tela de inscrições (balcão, baixa manual, cancelamento).
 */
class TournamentPanelTest extends TestCase
{
    use CreatesTournamentSchema;
    use BuildsTournaments;

    private User $esporte;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->createTournamentSchema();
        $this->fakeIntegrations();

        // TI tem acesso total: alcança todas as `torneios.*`.
        $this->esporte = $this->makeUser('Pessoa do Esporte');
        $this->joinSector($this->esporte, 'TI');
        $this->groupId = DB::table('place_groups')->insertGetId(['name' => 'Beach Tennis', 'category' => 'esportiva']);
    }

    private function template(array $attributes = []): Template
    {
        return Template::create(array_merge(['place_group_id' => $this->groupId, 'name' => 'Feminina B', 'team_size' => 2, 'gender' => Template::GENDER_FEMALE], $attributes));
    }

    private function link(array $overrides = []): array
    {
        return array_merge([
            'price' => 90,
            'team_formation' => TournamentCategory::FORMATION_DRAW,
            'format' => TournamentCategory::FORMAT_GROUPS_KNOCKOUT,
            'groups_count' => 4,
            'qualifiers_per_group' => 2,
            'sets_to_win' => 2,
            'third_place_match' => 1,
        ], $overrides);
    }

    public function test_sem_permissao_nao_abre_torneios(): void
    {
        $semAcesso = $this->makeUser('Sem Acesso');

        $this->actingAs($semAcesso)->get(route('tournaments.index'))->assertForbidden();
        $tournament = $this->tournament();
        $this->actingAs($semAcesso)->get(route('tournaments.registrations.index', $tournament))->assertForbidden();
    }

    public function test_cria_em_rascunho_liga_categoria_e_publica(): void
    {
        $this->actingAs($this->esporte)->post(route('tournaments.store'), [
            'place_group_id' => $this->groupId,
            'title' => 'Copa de Primavera',
            'registration_starts_at' => '2026-11-01T08:00',
            'registration_ends_at' => '2026-11-20T23:59',
            'starts_on' => '2026-11-28',
            'ends_on' => '2026-11-29',
        ])->assertRedirect();

        $tournament = Tournament::where('title', 'Copa de Primavera')->firstOrFail();
        $this->assertSame(Tournament::STATUS_DRAFT, $tournament->status);
        $this->assertSame($this->esporte->id, $tournament->created_by);

        // Publicar sem categoria é recusado.
        $this->actingAs($this->esporte)->put(route('tournaments.status', $tournament), ['status' => Tournament::STATUS_PUBLISHED])
            ->assertSessionHasErrors('status');

        $template = $this->template(['min_age' => 30]);
        $this->actingAs($this->esporte)->post(route('tournaments.event-categories.store', $tournament), $this->link(['category_template_id' => $template->id]))
            ->assertSessionHasNoErrors();

        $category = $tournament->categories()->firstOrFail();
        $this->assertSame('Feminina B', $category->name);
        $this->assertSame(Template::GENDER_FEMALE, $category->gender);
        $this->assertSame(30, $category->min_age);
        $this->assertSame('90.00', $category->price);
        $this->assertSame(4, $category->groups_count);
        $this->assertTrue($category->third_place_match);
        $this->assertNull($category->max_registrations);

        $this->actingAs($this->esporte)->put(route('tournaments.status', $tournament), ['status' => Tournament::STATUS_PUBLISHED])
            ->assertSessionHasNoErrors();
        $this->assertSame(Tournament::STATUS_PUBLISHED, $tournament->fresh()->status);

        $this->actingAs($this->esporte)->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee('Copa de Primavera')
            ->assertSee('Feminina B')
            ->assertSee('Grupos + mata-mata');
    }

    public function test_eliminatoria_nao_guarda_grupos_e_categoria_de_outro_grupo_e_recusada(): void
    {
        $tournament = $this->tournament(['status' => Tournament::STATUS_DRAFT]);
        $template = $this->template();

        $this->actingAs($this->esporte)->post(route('tournaments.event-categories.store', $tournament), $this->link([
            'category_template_id' => $template->id,
            'format' => TournamentCategory::FORMAT_SINGLE_ELIMINATION,
        ]))->assertSessionHasNoErrors();
        $this->assertNull($tournament->categories()->first()->groups_count);

        $outroGrupo = DB::table('place_groups')->insertGetId(['name' => 'Tênis']);
        $alheia = Template::create(['place_group_id' => $outroGrupo, 'name' => 'Simples', 'team_size' => 1, 'gender' => Template::GENDER_ANY]);
        $this->actingAs($this->esporte)->post(route('tournaments.event-categories.store', $tournament), $this->link(['category_template_id' => $alheia->id]))
            ->assertSessionHasErrors('category_template_id');
    }

    public function test_com_inscricao_nao_volta_a_rascunho_nem_tira_a_categoria(): void
    {
        $tournament = $this->tournament();
        $category = $this->category($tournament);
        $ana = $this->member('39053344705', 'Ana Souza', '1001');
        Registration::create(['tournament_category_id' => $category->id, 'member_id' => $ana->id, 'cpf' => $ana->cpf, 'name' => 'Ana Souza', 'amount' => 80]);

        $this->actingAs($this->esporte)->put(route('tournaments.status', $tournament), ['status' => Tournament::STATUS_DRAFT])
            ->assertSessionHasErrors('status');
        $this->actingAs($this->esporte)->delete(route('tournaments.event-categories.destroy', [$tournament, $category]))
            ->assertSessionHasErrors('category');
        $this->assertTrue($category->exists());
    }

    public function test_balcao_inscreve_baixa_manual_e_cancela_com_estorno(): void
    {
        $tournament = $this->tournament();
        $category = $this->category($tournament);
        $this->member('39053344705', 'Ana Souza', '1001');

        $this->actingAs($this->esporte)->post(route('tournaments.registrations.store', $tournament), [
            'cpf' => '390.533.447-05', 'title' => '1001', 'category_ids' => [$category->id],
        ])->assertSessionHas('success');

        $registration = Registration::firstOrFail();
        $this->assertSame(Registration::STATUS_PENDING_PAYMENT, $registration->status);

        $this->actingAs($this->esporte)->get(route('tournaments.registrations.index', $tournament))
            ->assertOk()
            ->assertSee('Ana Souza')
            ->assertSee('Aguardando pagamento');

        $this->actingAs($this->esporte)->post(route('tournaments.registrations.manual', $tournament), [
            'registration_ids' => [$registration->id], 'payment_method' => 'manual_cash', 'manual_notes' => 'Recibo 12',
        ])->assertSessionHas('success');
        $this->assertSame(Registration::STATUS_PAID, $registration->fresh()->status);
        $this->assertTrue(TournamentPayment::firstOrFail()->is_manual);

        // Baixa manual não tem estorno pelo Lara: a inscrição continua paga.
        $this->actingAs($this->esporte)->post(route('tournaments.registrations.cancel', $registration), ['cancel_reason' => 'Desistiu', 'refund' => 1])
            ->assertSessionHas('error');
        $this->assertSame(Registration::STATUS_PAID, $registration->fresh()->status);

        $this->actingAs($this->esporte)->post(route('tournaments.registrations.cancel', $registration), ['cancel_reason' => 'Desistiu'])
            ->assertSessionHas('success');
        $this->assertSame(Registration::STATUS_CANCELLED, $registration->fresh()->status);
        $this->assertSame([], $this->redeRefunds);
    }

    public function test_balcao_recusa_quem_nao_tem_cadastro_no_site_ou_nao_se_encaixa(): void
    {
        $tournament = $this->tournament();
        $masculina = $this->category($tournament, ['name' => 'Masculina', 'gender' => Template::GENDER_MALE]);
        $this->member('39053344705', 'Ana Souza', '1001');

        // Sócia no MultiClubes, mas sem cadastro no site.
        $this->mcProfiles['52998224725|2002'] = new \App\Services\MultiClubes\MemberProfile('Carla Dias', '52998224725', '2002', null, Template::GENDER_FEMALE, null, true);
        $this->actingAs($this->esporte)->post(route('tournaments.registrations.store', $tournament), [
            'cpf' => '52998224725', 'title' => '2002', 'category_ids' => [$masculina->id],
        ])->assertSessionHas('error', fn ($m) => str_contains($m, 'não tem cadastro no site'));

        $data = ['cpf' => '39053344705', 'title' => '1001', 'category_ids' => [$masculina->id]];
        $this->actingAs($this->esporte)->post(route('tournaments.registrations.store', $tournament), $data)
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'justificativa'));

        $this->actingAs($this->esporte)->post(route('tournaments.registrations.store', $tournament), $data + ['override_reason' => 'Cadastro com sexo errado'])
            ->assertSessionHas('success');
        $this->assertSame('Cadastro com sexo errado', Registration::firstOrFail()->eligibility_override_reason);
    }
}
