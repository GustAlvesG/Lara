<?php

namespace Tests\Concerns;

use App\Models\Member;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentCategoryTemplate as Template;
use App\Services\MultiClubes\MemberProfile;
use App\Services\MultiClubes\MemberProfileLookup;
use App\Services\RedeItauService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Torneio, categorias e sócios para os testes da F3, com o MultiClubes e a
 * eRede trocados por versões em memória (`$this->mcProfiles`, `$this->redeRefunds`).
 * Use depois de createTournamentSchema().
 */
trait BuildsTournaments
{
    /** @var array<string, MemberProfile> "cpf|matrícula" => perfil */
    public array $mcProfiles = [];

    /** @var list<array{tid: string, amount: float}> */
    public array $redeRefunds = [];

    protected int $groupId;

    protected function fakeIntegrations(): void
    {
        $test = $this;

        $this->app->instance(MemberProfileLookup::class, new class($test) extends MemberProfileLookup {
            public function __construct(private $test)
            {
            }

            public function byCpf(string $cpf): array
            {
                return array_values(array_filter($this->test->mcProfiles, fn (MemberProfile $p) => $p->cpf === $cpf));
            }

            // Os dois: nenhum teste pode chegar ao MultiClubes de verdade.
            public function byTitle(string $title): array
            {
                return array_values(array_filter($this->test->mcProfiles, fn (MemberProfile $p) => $p->title === trim($title)));
            }
        });

        $this->app->instance(RedeItauService::class, new class($test) extends RedeItauService {
            public function __construct(private $test)
            {
            }

            public function refund(string $tid, float $amount): array
            {
                $this->test->redeRefunds[] = ['tid' => $tid, 'amount' => $amount];

                return ['returnCode' => '359'];
            }
        });
    }

    /** Sócio local (cadastro do site) e o perfil dele no MultiClubes. */
    protected function member(string $cpf, string $name, string $title, string $gender = Template::GENDER_FEMALE, string $birth = '1990-05-10'): Member
    {
        $id = DB::table('members')->insertGetId(['cpf' => $cpf, 'name' => $name, 'email' => strtolower(strtok($name, ' ')) . '@teste.local', 'title' => $title]);
        $this->mcProfiles["{$cpf}|{$title}"] = new MemberProfile($name, $cpf, $title, Carbon::parse($birth), $gender, strtolower(strtok($name, ' ')) . '@mc.local', true);

        return Member::findOrFail($id);
    }

    protected function tournament(array $attributes = []): Tournament
    {
        $this->groupId ??= DB::table('place_groups')->insertGetId(['name' => 'Beach Tennis', 'category' => 'esportiva']);

        return Tournament::create(array_merge([
            'place_group_id' => $this->groupId,
            'title' => 'Copa de Verão',
            'registration_starts_at' => now()->subDay(),
            'registration_ends_at' => now()->addDays(10),
            'starts_on' => now()->addDays(20)->toDateString(),
            'ends_on' => now()->addDays(21)->toDateString(),
            'status' => Tournament::STATUS_PUBLISHED,
        ], $attributes));
    }

    protected function category(Tournament $tournament, array $attributes = []): TournamentCategory
    {
        return $tournament->categories()->create(array_merge([
            'name' => 'Duplas Livre',
            'team_size' => 2,
            'gender' => Template::GENDER_ANY,
            'price' => 80,
            'team_formation' => TournamentCategory::FORMATION_ORGANIZER,
            'format' => TournamentCategory::FORMAT_SINGLE_ELIMINATION,
            'sets_to_win' => 2,
        ], $attributes));
    }
}
