<?php

namespace Tests\Feature\EmailCampaigns;

use App\Authorization\Permissions as P;
use App\Models\EmailCampaign\EmailCampaign;
use App\Models\User;
use App\Services\MultiClubes\MemberMailingDirectory;
use App\Services\MultiClubes\MemberMailingFilter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\CreatesEmailCampaignSchema;
use Tests\TestCase;

/**
 * Lista da campanha a partir dos sócios do MultiClubes: permissão própria,
 * filtros que chegam ao diretório, colunas/variáveis, sem e-mail contado à
 * parte, e-mail repetido na família, MultiClubes fora do ar. O MultiClubes é
 * trocado por um diretório de mentira — a suíte nunca toca o SQL Server.
 */
class EmailCampaignMultiClubesTest extends TestCase
{
    use CreatesEmailCampaignSchema;

    private User $staff;

    /** O diretório de mentira guarda o último filtro recebido. */
    private object $directory;

    protected function setUp(): void
    {
        parent::setUp();

        config(['email_campaigns.from_addresses' => ['campanhas@clube.test']]);

        $this->createEmailCampaignSchema();

        $this->staff = $this->makeUser('Pessoa do Marketing');
        $this->joinSector($this->staff, 'TI');

        $this->directory = new class extends MemberMailingDirectory {
            public ?MemberMailingFilter $lastFilter = null;

            public bool $down = false;

            public array $members = [];

            public function titleTypes(): array
            {
                $this->fail();

                return [
                    ['id' => 365, 'name' => 'EFETIVO', 'active_titles' => 3051, 'special' => false],
                    ['id' => 375, 'name' => 'ESCOLINHA NAO SOCIO', 'active_titles' => 1061, 'special' => true],
                ];
            }

            public function count(MemberMailingFilter $filter): array
            {
                $this->fail();
                $this->lastFilter = $filter;

                return ['members' => 10, 'with_email' => 7, 'distinct_emails' => 6];
            }

            public function members(MemberMailingFilter $filter): array
            {
                $this->fail();
                $this->lastFilter = $filter;

                return $this->members;
            }

            private function fail(): void
            {
                if ($this->down) {
                    throw new RuntimeException('SQLSTATE[08001]: TCP Provider: timeout');
                }
            }
        };

        $this->directory->members = [
            $this->member('001234', true, null, 'JOÃO DA SILVA', 'Joao@Exemplo.com.br', 0, '1980-05-10'),
            // Mesmo e-mail do titular: entra uma vez só, com o nome do titular.
            $this->member('001234', false, 'FILHO', 'PEDRO DA SILVA', 'joao@exemplo.com.br', 0, '2010-01-01'),
            $this->member('001234', false, 'ESPOSA', 'ANA DA SILVA', null, 1, '1982-03-03'),
            $this->member('009876', true, null, 'Maria Souza', 'maria@exemplo', 1, null),
        ];

        $this->app->instance(MemberMailingDirectory::class, $this->directory);
    }

    private function member(string $title, bool $titular, ?string $parentage, string $name, ?string $email, int $sex, ?string $birth): array
    {
        return [
            'title' => $title,
            'title_type' => 'EFETIVO',
            'titular' => $titular,
            'parentage' => $parentage,
            'name' => $name,
            'email' => $email,
            'sex' => $sex,
            'birth_date' => $birth ? Carbon::parse($birth) : null,
        ];
    }

    private function campaign(): EmailCampaign
    {
        return EmailCampaign::create([
            'name' => 'Assembleia',
            'subject' => 'Olá, {{primeiro_nome}}',
            'from_address' => 'campanhas@clube.test',
            'html' => '<p>Título {{titulo}}</p>',
            'status' => EmailCampaign::STATUS_DRAFT,
        ]);
    }

    private function filters(array $overrides = []): array
    {
        return $overrides + [
            'tipos' => [365],
            'situacao_titulo' => [0],
            'situacao_socio' => [0],
            'idade_min' => '18',
            'idade_max' => '',
            'sexo' => '',
            'vinculo' => 'todos',
            'mode' => 'append',
        ];
    }

    private function onlyCampaignsUser(): User
    {
        $user = $this->makeUser('Só Campanhas');
        $sectorId = DB::table('sectors')->insertGetId(['name' => 'Marketing', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sector_permission')->insert([
            'sector_id' => $sectorId,
            'permission_id' => DB::table('permissions')->where('name', P::DISPARO_EMAILS)->value('id'),
            'coordinators_only' => false,
        ]);
        $this->joinSector($user, 'Marketing');

        return $user;
    }

    public function test_permissao_propria_para_o_multiclubes(): void
    {
        $campaign = $this->campaign();
        $user = $this->onlyCampaignsUser();

        $this->actingAs($user)->get(route('email-campaigns.show', $campaign))->assertOk()
            ->assertDontSee('Importar sócios do MultiClubes');
        $this->actingAs($user)->getJson(route('email-campaigns.multiclubes.types'))->assertForbidden();
        $this->actingAs($user)->postJson(route('email-campaigns.multiclubes.count'), $this->filters())->assertForbidden();
        $this->actingAs($user)->post(route('email-campaigns.multiclubes.import', $campaign), $this->filters())->assertForbidden();

        $this->assertSame(0, $campaign->recipients()->count());

        $this->actingAs($this->staff)->get(route('email-campaigns.show', $campaign))->assertOk()
            ->assertSee('Importar sócios do MultiClubes');
    }

    public function test_tipos_e_contagem(): void
    {
        $this->actingAs($this->staff)->getJson(route('email-campaigns.multiclubes.types'))
            ->assertOk()
            ->assertJsonPath('types.1.special', true);

        $this->actingAs($this->staff)->postJson(route('email-campaigns.multiclubes.count'), $this->filters([
            'vinculo' => 'dependente', 'sexo' => '1', 'idade_min' => '12', 'idade_max' => '17',
        ]))->assertOk()->assertJson(['members' => 10, 'with_email' => 7, 'distinct_emails' => 6]);

        $filter = $this->directory->lastFilter;
        $this->assertSame(MemberMailingFilter::LINK_DEPENDENT, $filter->link);
        $this->assertSame(1, $filter->sex);
        $this->assertSame(12, $filter->ageMin);
        $this->assertSame(17, $filter->ageMax);
        $this->assertSame([365], $filter->titleTypes);
    }

    public function test_filtro_invalido_e_recusado(): void
    {
        $this->actingAs($this->staff)->postJson(route('email-campaigns.multiclubes.count'), $this->filters(['idade_min' => '30', 'idade_max' => '20']))
            ->assertUnprocessable()->assertJsonValidationErrors('idade_max');

        $this->actingAs($this->staff)->postJson(route('email-campaigns.multiclubes.count'), $this->filters(['vinculo' => 'outro']))
            ->assertUnprocessable()->assertJsonValidationErrors('vinculo');

        $this->actingAs($this->staff)->postJson(route('email-campaigns.multiclubes.count'), $this->filters(['situacao_titulo' => [9]]))
            ->assertUnprocessable()->assertJsonValidationErrors('situacao_titulo.0');

        $this->actingAs($this->staff)->postJson(route('email-campaigns.multiclubes.count'), $this->filters(['sexo' => '2']))
            ->assertUnprocessable()->assertJsonValidationErrors('sexo');
    }

    public function test_importa_os_socios_com_as_colunas_do_multiclubes(): void
    {
        Carbon::setTestNow('2026-11-09 10:00');
        $campaign = $this->campaign();

        $this->actingAs($this->staff)->post(route('email-campaigns.multiclubes.import', $campaign), $this->filters())
            ->assertRedirect(route('email-campaigns.show', $campaign))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 destinatário(s) novo(s)')
                && str_contains($m, '1 e-mail(s) repetido(s)')
                && str_contains($m, '1 sócio(s) sem e-mail'))
            ->assertSessionHas('import_rejected', fn ($r) => $r['count'] === 1
                && str_contains($r['samples'][0], 'Título 009876 (Maria Souza)'));

        $recipients = $campaign->recipients()->get();
        $this->assertCount(1, $recipients);

        $joao = $recipients->first();
        $this->assertSame('joao@exemplo.com.br', $joao->email);
        $this->assertSame('João da Silva', $joao->name);
        $this->assertSame([
            'titulo' => '001234',
            'tipo_titulo' => 'EFETIVO',
            'vinculo' => 'Titular',
            'parentesco' => 'Titular',
            'idade' => '46',
            'sexo' => 'Masculino',
        ], $joao->data);

        $campaign->refresh();
        $this->assertArrayHasKey('titulo', $campaign->columns);
        $this->assertArrayHasKey('tipo_titulo', $campaign->columns);
        $this->assertStringStartsWith('MultiClubes: EFETIVO; título ativo; sócio ativo; 18 anos ou mais', $campaign->list_name);

        Carbon::setTestNow();
    }

    public function test_substituir_e_pausada_so_acrescenta(): void
    {
        $campaign = $this->campaign();
        $campaign->recipients()->create(['email' => 'antigo@exemplo.com.br', 'status' => 'pending']);

        $this->actingAs($this->staff)->post(route('email-campaigns.multiclubes.import', $campaign), $this->filters(['mode' => 'replace']))
            ->assertSessionHas('success');
        $this->assertSame(['joao@exemplo.com.br'], $campaign->recipients()->pluck('email')->all());

        $campaign->update(['status' => EmailCampaign::STATUS_PAUSED]);
        $this->actingAs($this->staff)->post(route('email-campaigns.multiclubes.import', $campaign), $this->filters(['mode' => 'replace']))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'só pode ser acrescentada'));
    }

    public function test_ninguem_com_email_ou_multiclubes_fora(): void
    {
        $campaign = $this->campaign();

        $this->directory->members = [$this->member('1', true, null, 'SEM EMAIL', null, 0, null)];
        $this->actingAs($this->staff)->post(route('email-campaigns.multiclubes.import', $campaign), $this->filters())
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'não têm e-mail'));

        $this->directory->members = [];
        $this->actingAs($this->staff)->post(route('email-campaigns.multiclubes.import', $campaign), $this->filters())
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Nenhum sócio'));

        $this->directory->down = true;
        $this->actingAs($this->staff)->post(route('email-campaigns.multiclubes.import', $campaign), $this->filters())
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Não foi possível consultar o MultiClubes'));
        $this->actingAs($this->staff)->getJson(route('email-campaigns.multiclubes.types'))->assertStatus(503);
        $this->actingAs($this->staff)->postJson(route('email-campaigns.multiclubes.count'), $this->filters())->assertStatus(503);

        $this->assertSame(0, $campaign->recipients()->count());
    }
}
