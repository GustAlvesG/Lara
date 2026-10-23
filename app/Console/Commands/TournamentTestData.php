<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentCategoryTemplate as Template;
use App\Models\Tournament\TournamentGroup;
use App\Models\Tournament\TournamentGroupTeam;
use App\Models\Tournament\TournamentMatch as Game;
use App\Models\Tournament\TournamentPayment as Payment;
use App\Models\Tournament\TournamentRegistration as Registration;
use App\Models\Tournament\TournamentStage as Stage;
use App\Models\Tournament\TournamentTeam as Team;
use App\Models\Tournament\TournamentTeamInvite as Invite;
use App\Models\Tournament\TournamentTeamMember;
use App\Models\User;
use App\Services\Members\SiteAccountService;
use App\Services\Tournament\TournamentBracketService;
use App\Services\Tournament\TournamentResultCodeService;
use App\Services\Tournament\TournamentResultService;
use App\Services\Tournament\TournamentTeamService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dados de teste dos Torneios (e das Contas do site) para homologação: um
 * torneio em cada situação e todos os cenários de inscrição, pagamento, time,
 * chaveamento e resultado, para testar o painel do Lara e o site de locação.
 *
 * O que ele NÃO faz, de propósito:
 *
 *  - **Não roda em produção** (ambiente `production` ou banco `lara`).
 *  - **Não manda e-mail, não chama a eRede nem o MultiClubes**: inscrições,
 *    pagamentos, estornos e convites são gravados direto nas tabelas. Por isso
 *    as pessoas de teste entram no site (login = CPF, senha `teste123`), mas a
 *    elegibilidade e a inscrição *pelo site* delas dão `member_not_found` —
 *    para testar o fluxo do site, use o seu CPF de sócio com `--socio=`.
 *  - **Não mexe em nada que não criou**: tudo leva o prefixo `[TESTE]` (torneio,
 *    categoria do catálogo, conta do site), e é por ele que `--limpar` apaga.
 *    Rodar de novo limpa o anterior e recria.
 *
 *     php artisan torneios:dados-teste
 *     php artisan torneios:dados-teste --socio=12345678909 --grupo=1
 *     php artisan torneios:dados-teste --so-limpar
 */
class TournamentTestData extends Command
{
    protected $signature = 'torneios:dados-teste
                            {--grupo= : Grupo de espaços esportivo (id); padrão: o primeiro esportivo}
                            {--socio= : CPF de uma conta do site real, para testar inscrição, pagamento e convite pelo site}
                            {--usuario= : Usuário do Lara que aparece como organizador (id); padrão: o primeiro}
                            {--so-limpar : Só apaga os dados de teste anteriores}';

    protected $description = 'Gera (ou limpa) dados de teste de Torneios e Contas do site em homologação, com o prefixo [TESTE].';

    public const PREFIX = '[TESTE]';
    public const PASSWORD = 'teste123';

    private User $organizer;
    private int $groupId;
    private int $tid = 0;

    /** @var array<int, Member> */
    private array $people = [];

    /** @var array<int, string> sexo de cada pessoa (member_id => male|female) */
    private array $genders = [];

    /** @var list<array{0: string, 1: string}> */
    private array $report = [];

    public function __construct(
        private TournamentBracketService $brackets,
        private TournamentResultService $results,
        private TournamentResultCodeService $codes,
        private TournamentTeamService $teams,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (app()->environment('production') || config('database.connections.mysql.database') === 'lara') {
            $this->error('Isto cria torneios e contas falsas. Não roda em produção (ambiente production ou banco "lara").');

            return self::FAILURE;
        }

        $removed = $this->clean();
        $this->info("Dados de teste anteriores apagados: {$removed['tournaments']} torneio(s), {$removed['members']} conta(s).");

        if ($this->option('so-limpar')) {
            return self::SUCCESS;
        }

        $organizer = $this->option('usuario') ? User::find($this->option('usuario')) : User::orderBy('id')->first();
        $groupId = $this->option('grupo')
            ?: DB::table('place_groups')->whereNull('deleted_at')->where('category', 'esportiva')->orderBy('id')->value('id');

        if (! $organizer || ! $groupId) {
            $this->error('Preciso de um usuário do Lara e de um grupo de espaços esportivo (--usuario, --grupo).');

            return self::FAILURE;
        }

        $socio = null;
        if ($this->option('socio')) {
            $socio = Member::where('cpf', preg_replace('/\D/', '', $this->option('socio')))->first();
            if (! $socio) {
                $this->error('Não há conta do site com esse CPF.');

                return self::FAILURE;
            }
        }

        $this->organizer = $organizer;
        $this->groupId = (int) $groupId;

        DB::transaction(function () use ($socio) {
            $this->makePeople();
            $templates = $this->makeTemplates();

            $this->openTournament($templates, $socio);
            $code = $this->runningTournament($templates);
            $this->draftTournament($templates);
            $this->finishedTournament($templates);
            $this->cancelledTournament($templates);
            $this->siteAccounts();

            $this->report[] = ['Código de lançamento (Em andamento)', $code];
        });

        $this->newLine();
        $this->table(['Cenário', 'Onde'], $this->report);
        $this->line('Pessoas de teste: login = CPF, senha "' . self::PASSWORD . '". Elas não existem no MultiClubes (pelo site, a inscrição delas dá member_not_found).');

        return self::SUCCESS;
    }

    /** @return array{tournaments: int, members: int} */
    public function clean(): array
    {
        return DB::transaction(function () {
            $tournaments = Tournament::withTrashed()->where('title', 'like', self::PREFIX . '%')->get();
            $categoryIds = TournamentCategory::whereIn('tournament_id', $tournaments->pluck('id'))->pluck('id');
            $stageIds = Stage::whereIn('tournament_category_id', $categoryIds)->pluck('id');
            $teamIds = Team::whereIn('tournament_category_id', $categoryIds)->pluck('id');
            $registrations = Registration::whereIn('tournament_category_id', $categoryIds)->get(['id', 'tournament_payment_id']);

            Game::whereIn('tournament_stage_id', $stageIds)->update(['next_match_id' => null]);
            Game::whereIn('tournament_stage_id', $stageIds)->delete();
            TournamentGroupTeam::whereIn('tournament_group_id', TournamentGroup::whereIn('tournament_stage_id', $stageIds)->pluck('id'))->delete();
            TournamentGroup::whereIn('tournament_stage_id', $stageIds)->delete();
            Stage::whereIn('id', $stageIds)->delete();
            Invite::whereIn('tournament_team_id', $teamIds)->delete();
            TournamentTeamMember::whereIn('tournament_team_id', $teamIds)->delete();
            Team::whereIn('id', $teamIds)->delete();
            Registration::whereIn('id', $registrations->pluck('id'))->delete();
            Payment::whereIn('id', $registrations->pluck('tournament_payment_id')->filter()->unique())->delete();
            TournamentCategory::whereIn('id', $categoryIds)->delete();
            Tournament::withTrashed()->whereIn('id', $tournaments->pluck('id'))->forceDelete();
            Template::withTrashed()->where('name', 'like', self::PREFIX . '%')->forceDelete();

            $members = Member::withTrashed()->where('name', 'like', self::PREFIX . '%')->get();
            Payment::whereIn('member_id', $members->pluck('id'))->delete();
            foreach ($members as $member) {
                $member->forceDelete();
            }

            return ['tournaments' => $tournaments->count(), 'members' => $members->count()];
        });
    }

    // ---------------------------------------------------------------- pessoas

    private function makePeople(): void
    {
        $names = [
            1 => ['Ana Souza', 'female', '1990-05-10'], 2 => ['Bruno Lima', 'male', '1988-02-01'],
            3 => ['Carla Dias', 'female', '1995-07-20'], 4 => ['Davi Rocha', 'male', '1992-11-30'],
            5 => ['Eva Nunes', 'female', '1985-03-15'], 6 => ['Fábio Reis', 'male', '1979-09-09'],
            7 => ['Gabi Torres', 'female', '1993-01-25'], 8 => ['Helena Prado', 'female', '1991-06-06'],
            9 => ['Iara Melo', 'female', '1998-12-12'], 10 => ['Júlia Castro', 'female', '2000-04-04'],
            11 => ['Kauê Alves', 'male', '1996-08-18'], 12 => ['Leo Martins', 'male', '1987-10-10'],
            13 => ['Marcos Pinto', 'male', '1983-05-05'], 14 => ['Nina Duarte', 'female', '2014-02-02'],
            15 => ['Otávio Ramos', 'male', '2015-03-03'], 16 => ['Paula Freitas', 'female', '1989-07-07'],
            17 => ['Rafa Gomes', 'male', '1994-09-19'], 18 => ['Sara Lopes', 'female', '1997-11-11'],
            19 => ['Tiago Costa', 'male', '1986-01-01'], 20 => ['Úrsula Vaz', 'female', '1990-02-20'],
        ];

        foreach ($names as $i => [$name, $gender, $birth]) {
            $member = new Member();
            $member->forceFill([
                'name' => self::PREFIX . ' ' . $name,
                'cpf' => $this->cpf($i),
                'title' => sprintf('T%04d', $i),
                'birth_date' => $birth,
                'email' => 'teste' . $i . '@exemplo.invalid',
                'telephone' => '2199990' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'password' => SiteAccountService::siteHash(self::PASSWORD),
            ])->save();
            $this->people[$i] = $member;
            $this->genders[$member->id] = $gender;
        }
    }

    /** CPF válido e falso: 999 + número, com os dígitos verificadores certos. */
    private function cpf(int $n): string
    {
        $base = '999' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);

        for ($len = 9; $len < 11; $len++) {
            $sum = 0;
            for ($i = 0; $i < $len; $i++) {
                $sum += (int) $base[$i] * (($len + 1) - $i);
            }
            $digit = (($sum * 10) % 11) % 10;
            $base .= $digit;
        }

        return $base;
    }

    // ----------------------------------------------------------- construtores

    /** @return array<string, Template> */
    private function makeTemplates(): array
    {
        $rows = [
            'duplas' => ['Duplas Livre', 2, Template::GENDER_ANY, null, null],
            'duplasB' => ['Duplas Livre B', 2, Template::GENDER_ANY, null, null],
            'femininas' => ['Duplas Femininas 18+', 2, Template::GENDER_FEMALE, 18, null],
            'simples' => ['Simples Masculino', 1, Template::GENDER_MALE, null, null],
            'kids' => ['Kids até 14', 1, Template::GENDER_ANY, null, 14],
        ];

        $templates = [];
        foreach ($rows as $key => [$name, $size, $gender, $min, $max]) {
            $templates[$key] = Template::create([
                'place_group_id' => $this->groupId,
                'name' => self::PREFIX . ' ' . $name,
                'team_size' => $size,
                'gender' => $gender,
                'min_age' => $min,
                'max_age' => $max,
                'active' => true,
            ]);
        }

        return $templates;
    }

    private function tournament(string $title, string $status, Carbon $regStart, Carbon $regEnd, Carbon $start, Carbon $end): Tournament
    {
        return Tournament::create([
            'place_group_id' => $this->groupId,
            'title' => self::PREFIX . ' ' . $title,
            'description' => 'Torneio de teste gerado por `php artisan torneios:dados-teste`.',
            'rules' => "1. Regulamento de teste.\n2. Partidas em sets.\n3. Empate é resolvido pela organização.",
            'registration_starts_at' => $regStart,
            'registration_ends_at' => $regEnd,
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
            'status' => $status,
            'created_by' => $this->organizer->id,
        ]);
    }

    private function category(Tournament $tournament, Template $template, array $link): TournamentCategory
    {
        return $tournament->categories()->create(array_merge([
            'category_template_id' => $template->id,
            'name' => $template->name,
            'team_size' => $template->team_size,
            'gender' => $template->gender,
            'min_age' => $template->min_age,
            'max_age' => $template->max_age,
            'price' => 80,
            'team_formation' => TournamentCategory::FORMATION_ORGANIZER,
            'format' => TournamentCategory::FORMAT_SINGLE_ELIMINATION,
            'sets_to_win' => 2,
            'sort_order' => (int) $tournament->categories()->max('sort_order') + 1,
        ], $link));
    }

    private function registration(TournamentCategory $category, Member $member, string $status = Registration::STATUS_PENDING_PAYMENT, array $extra = []): Registration
    {
        return Registration::create(array_merge([
            'tournament_category_id' => $category->id,
            'member_id' => $member->id,
            'cpf' => $member->cpf,
            'title' => $member->title,
            'name' => $member->name,
            'email' => $member->email,
            'birth_date' => $member->birth_date,
            'gender' => $this->genders[$member->id] ?? null,
            'amount' => $category->price,
            'status' => $status,
        ], $extra));
    }

    /** Pagamento (eRede ou manual) cobrindo as inscrições, já marcadas como pagas. */
    private function pay(array $registrations, bool $manual = false, string $method = 'credit'): Payment
    {
        $payment = new Payment([
            'member_id' => $registrations[0]->member_id,
            'payment_method' => $manual ? 'manual_cash' : $method,
            'paid_amount' => round(collect($registrations)->sum('amount'), 2),
            'paid_at' => now()->subDays(2),
            'payment_integration_id' => $manual ? null : 'TID-TESTE-' . (++$this->tid),
            'status' => Payment::STATUS_PAID,
        ]);
        $payment->is_manual = $manual;
        $payment->recorded_by = $manual ? $this->organizer->id : null;
        $payment->manual_notes = $manual ? 'Pago em dinheiro na secretaria (teste).' : null;
        $payment->save();

        foreach ($registrations as $registration) {
            $registration->update(['status' => Registration::STATUS_PAID, 'tournament_payment_id' => $payment->id, 'paid_at' => $payment->paid_at]);
        }

        return $payment;
    }

    /** Time com as inscrições; a situação (completo / em formação) sai do TeamService. */
    private function team(TournamentCategory $category, string $name, array $registrations, string $origin = Team::ORIGIN_ORGANIZER, ?int $seed = null, ?Registration $captain = null): Team
    {
        $team = Team::create([
            'tournament_category_id' => $category->id,
            'name' => $name,
            'origin' => $origin,
            'seed' => $seed,
            'captain_registration_id' => $captain?->id,
        ]);

        foreach ($registrations as $registration) {
            TournamentTeamMember::create([
                'tournament_team_id' => $team->id,
                'tournament_registration_id' => $registration->id,
                'added_by_user_id' => $this->organizer->id,
            ]);
        }

        return $this->teams->refresh($team);
    }

    /** Duplas pagas e completas, com as pessoas indicadas (pares). */
    private function paidPairs(TournamentCategory $category, array $pairs, bool $seeded = false): void
    {
        foreach (array_values($pairs) as $i => [$a, $b]) {
            $ra = $this->registration($category, $this->people[$a]);
            $rb = $this->registration($category, $this->people[$b]);
            $this->pay([$ra]);
            $this->pay([$rb], manual: $i % 3 === 2);
            $this->team($category, $this->short($a) . ' / ' . $this->short($b), [$ra->fresh(), $rb->fresh()], seed: $seeded && $i < 2 ? $i + 1 : null);
        }
    }

    private function short(int $i): string
    {
        return strtok(str_replace(self::PREFIX . ' ', '', $this->people[$i]->name), ' ');
    }

    private function win(int $home, int $away, int $sets = 2): array
    {
        return array_fill(0, $sets, ['home_games' => $home, 'away_games' => $away]);
    }

    // -------------------------------------------------------------- torneios

    /** Inscrições abertas: todos os cenários de inscrição, pagamento e time. */
    private function openTournament(array $t, ?Member $socio): void
    {
        $tournament = $this->tournament('Inscrições abertas', Tournament::STATUS_PUBLISHED, now()->subDays(3), now()->addDays(10), now()->addDays(20), now()->addDays(21));
        $p = $this->people;

        // Duplas Livre (organizador monta, eliminatória).
        $duplas = $this->category($tournament, $t['duplas'], ['price' => 80, 'max_registrations' => 16, 'third_place_match' => true]);
        $simples = $this->category($tournament, $t['simples'], ['price' => 50, 'max_registrations' => 4, 'team_formation' => TournamentCategory::FORMATION_DRAW]);

        $ana = $this->registration($duplas, $p[1]);
        $bruno = $this->registration($duplas, $p[2]);
        $brunoSimples = $this->registration($simples, $p[2]);
        $this->pay([$bruno, $brunoSimples]); // um pagamento eRede cobrindo duas categorias
        $this->pay([$ana], manual: true);   // baixa manual
        $this->team($duplas, 'Ana / Bruno', [$ana->fresh(), $bruno->fresh()]);

        $carla = $this->registration($duplas, $p[3]); // pendente, em time em formação
        $davi = $this->registration($duplas, $p[4]);
        $this->pay([$davi]);
        $this->team($duplas, 'Carla / Davi', [$carla, $davi->fresh()]);

        $this->registration($duplas, $p[5], Registration::STATUS_WITHDRAWN, ['withdrawn_at' => now()->subDay()]);

        $fabio = $this->registration($duplas, $p[6]);
        $payment = $this->pay([$fabio]);
        $fabio->update(['status' => Registration::STATUS_CANCELLED, 'cancelled_at' => now()->subDay(), 'cancelled_by' => $this->organizer->id, 'cancel_reason' => 'Lesão (teste)', 'refunded_amount' => 80]);
        $payment->update(['status' => Payment::STATUS_REFUNDED, 'refunded_amount' => 80, 'refunded_by' => $this->organizer->id, 'refunded_at' => now()->subDay()]);

        $this->registration($duplas, $p[19]); // pendente, sem time

        // Simples Masculino (sorteio, 4 vagas): cheia — Bruno, Kauê, Leo, Marcos pagos; Rafa pendente fica sem vaga.
        foreach ([11, 12, 13] as $i) {
            $this->pay([$this->registration($simples, $p[$i])]);
        }
        $this->registration($simples, $p[17]);
        $this->teams->afterPaid(Registration::where('tournament_category_id', $simples->id)->where('status', Registration::STATUS_PAID)->pluck('id'));

        // Duplas Femininas 18+ (capitão convida, grupos).
        $femininas = $this->category($tournament, $t['femininas'], [
            'price' => 90, 'team_formation' => TournamentCategory::FORMATION_CAPTAIN,
            'format' => TournamentCategory::FORMAT_GROUPS_KNOCKOUT, 'groups_count' => 2, 'qualifiers_per_group' => 2,
        ]);
        $gabi = $this->registration($femininas, $p[7]);
        $this->pay([$gabi]);
        $helena = $this->registration($femininas, $p[8]);
        $team = $this->team($femininas, 'As Feras', [$gabi->fresh(), $helena], Team::ORIGIN_CAPTAIN, captain: $gabi);
        $this->registration($femininas, $p[10]); // inscrita, sem time

        // Menor de 18 numa categoria 18+: entrou com liberação do organizador.
        $nina = $this->registration($femininas, $p[14], extra: ['eligibility_override_by' => $this->organizer->id, 'eligibility_override_reason' => 'Menor liberada pela coordenação (teste)']);
        $this->pay([$nina]);

        $sara = $this->registration($femininas, $p[18]);
        $this->pay([$sara]);
        $team2 = $this->team($femininas, 'Sara sozinha', [$sara->fresh()], Team::ORIGIN_CAPTAIN, captain: $sara);
        Invite::create(['tournament_team_id' => $team2->id, 'invited_by_member_id' => $p[18]->id, 'cpf' => $p[9]->cpf, 'title' => $p[9]->title, 'name' => $p[9]->name, 'email' => $p[9]->email]);
        Invite::create(['tournament_team_id' => $team2->id, 'invited_by_member_id' => $p[18]->id, 'cpf' => $p[20]->cpf, 'title' => $p[20]->title, 'name' => $p[20]->name, 'email' => $p[20]->email, 'status' => Invite::STATUS_DECLINED, 'responded_at' => now()->subDay()]);

        // Kids (grátis, individual).
        $kids = $this->category($tournament, $t['kids'], ['price' => 0, 'sets_to_win' => 1]);
        $otavio = $this->registration($kids, $p[15], Registration::STATUS_PAID, ['amount' => 0, 'paid_at' => now()]);
        $this->teams->afterPaid([$otavio->id]);

        $this->report[] = ['Inscrições abertas › Duplas Livre', 'paga eRede, baixa manual, pendente, desistência, cancelada com estorno, time completo e em formação, inscrito sem time'];
        $this->report[] = ['Inscrições abertas › Simples Masculino', '4 vagas cheias (um pagamento cobre duas categorias), pendente sem vaga, times individuais'];
        $this->report[] = ['Inscrições abertas › Duplas Femininas 18+', 'capitão: time em formação, convite pendente e recusado, inscrita sem time, menor liberada pelo organizador'];
        $this->report[] = ['Inscrições abertas › Kids até 14', 'categoria grátis (já nasce paga)'];

        if ($socio) {
            $this->registration($duplas, $socio, extra: ['name' => $socio->name, 'gender' => null]);
            Invite::create(['tournament_team_id' => $team->id, 'invited_by_member_id' => $p[7]->id, 'cpf' => $socio->cpf, 'title' => $socio->title, 'name' => $socio->name, 'email' => $socio->email]);
            $this->report[] = ["Seu CPF ({$socio->cpf})", 'inscrição pendente na Duplas Livre (para pagar pelo site) e convite pendente para o time "As Feras"'];
        }
    }

    /** Em andamento: chaveamentos e resultados em todos os estados. */
    private function runningTournament(array $t): string
    {
        $tournament = $this->tournament('Em andamento', Tournament::STATUS_IN_PROGRESS, now()->subDays(20), now()->subDays(2), now()->subDay(), now()->addDay());

        // Eliminatória com 6 duplas (cabeças 1 e 2 passam direto), 3º lugar, resultados do Lara e do site.
        $elim = $this->category($tournament, $t['duplas'], ['price' => 80, 'third_place_match' => true]);
        $this->paidPairs($elim, [[2, 1], [4, 3], [6, 5], [11, 7], [12, 8], [13, 9]], seeded: true);
        $this->brackets->generate($elim, Stage::GENERATION_DRAW, $this->organizer);
        $this->brackets->publish($elim, true);
        $roundOne = $elim->stages()->first()->matches()->where('round', 1)->where('status', Game::STATUS_PENDING)->orderBy('position')->get();
        $this->results->record($roundOne[0], [['home_games' => 6, 'away_games' => 4], ['home_games' => 3, 'away_games' => 6], ['home_games' => 7, 'away_games' => 6, 'home_tiebreak' => 7, 'away_tiebreak' => 5]], TournamentResultService::ORIGIN_LARA, $this->organizer);
        $this->results->record($roundOne[1], $this->win(6, 2), TournamentResultService::ORIGIN_SITE, operator: 'Equipe de teste (quadra 1)');
        $semi = $elim->stages()->first()->matches()->where('round', 2)->orderBy('position')->first();
        $semi->update(['scheduled_at' => now()->setTime(9, 0), 'location_note' => 'Quadra 1']);

        // Grupos + mata-mata com 8 duplas: Grupo A fechado (classificados no mata-mata), Grupo B a meio.
        $groups = $this->category($tournament, $t['duplasB'], [
            'price' => 90, 'team_formation' => TournamentCategory::FORMATION_DRAW,
            'format' => TournamentCategory::FORMAT_GROUPS_KNOCKOUT, 'groups_count' => 2, 'qualifiers_per_group' => 2, 'sets_to_win' => 1,
        ]);
        $this->paidPairs($groups, [[1, 2], [3, 4], [5, 6], [7, 11], [8, 12], [9, 13], [10, 17], [16, 19]]);
        $this->brackets->generate($groups, Stage::GENERATION_DRAW, $this->organizer);
        $this->brackets->publish($groups, true);
        [$a, $b] = $groups->stages()->where('type', Stage::TYPE_GROUP)->first()->groups()->orderBy('order')->get()->all();
        foreach ($a->matches as $i => $match) {
            $this->results->record($match, [['home_games' => 6, 'away_games' => $i % 4]], TournamentResultService::ORIGIN_SITE, operator: 'Equipe de teste');
        }
        $positions = $this->brackets->standings($a->fresh())->values()->mapWithKeys(fn ($row, $i) => [$row['team']->id => $i + 1])->all();
        $this->results->closeGroup($a, $positions, $this->organizer);
        $this->results->record($b->matches()->first(), [], TournamentResultService::ORIGIN_LARA, $this->organizer, walkoverWinnerId: $b->matches()->first()->home_team_id);

        // Chaveamento gerado e não divulgado; e categoria ainda sem chaveamento (com time em formação).
        $hidden = $this->category($tournament, $t['simples'], ['price' => 50, 'team_formation' => TournamentCategory::FORMATION_DRAW, 'sets_to_win' => 1]);
        foreach ([2, 4, 6, 11] as $i) {
            $this->pay([$this->registration($hidden, $this->people[$i])]);
        }
        $this->teams->afterPaid(Registration::where('tournament_category_id', $hidden->id)->pluck('id'));
        $this->brackets->generate($hidden, Stage::GENERATION_DRAW, $this->organizer);

        $none = $this->category($tournament, $t['kids'], ['price' => 0, 'sets_to_win' => 1]);
        foreach ([14, 15] as $i) {
            $this->teams->afterPaid([$this->registration($none, $this->people[$i], Registration::STATUS_PAID, ['amount' => 0, 'paid_at' => now()])->id]);
        }

        $code = $this->codes->generate($tournament, $this->organizer);

        $this->report[] = ['Em andamento › Duplas Livre', 'eliminatória de 6 (passa direto), 3º lugar, resultado do Lara (travado) e do site, data e local'];
        $this->report[] = ['Em andamento › Duplas Livre B', 'grupos: Grupo A fechado com classificados no mata-mata, Grupo B com um W.O.'];
        $this->report[] = ['Em andamento › Simples Masculino', 'chaveamento gerado e NÃO divulgado (site recebe bracket_not_published)'];
        $this->report[] = ['Em andamento › Kids até 14', 'sem chaveamento'];

        return $code;
    }

    private function draftTournament(array $t): void
    {
        $tournament = $this->tournament('Rascunho', Tournament::STATUS_DRAFT, now()->addDays(5), now()->addDays(15), now()->addDays(30), now()->addDays(30));
        $this->category($tournament, $t['duplas'], ['price' => 70]);
        $this->report[] = ['Rascunho', 'não aparece no site (404 tournament_not_found)'];
    }

    /** Encerrado: eliminatória de 4 completa, com final e 3º lugar. */
    private function finishedTournament(array $t): void
    {
        $tournament = $this->tournament('Encerrado', Tournament::STATUS_IN_PROGRESS, now()->subDays(60), now()->subDays(40), now()->subDays(30), now()->subDays(29));
        $category = $this->category($tournament, $t['duplas'], ['price' => 60, 'third_place_match' => true]);
        $this->paidPairs($category, [[2, 1], [4, 3], [6, 5], [12, 7]], seeded: true);
        $this->brackets->generate($category, Stage::GENERATION_DRAW, $this->organizer);
        $this->brackets->publish($category, true);

        $stage = $category->stages()->first();
        foreach ($stage->matches()->where('round', 1)->get() as $match) {
            $this->results->record($match, $this->win(6, 3), TournamentResultService::ORIGIN_LARA, $this->organizer);
        }
        foreach ($stage->matches()->where('round', 2)->get() as $match) {
            $this->results->record($match->fresh(), $this->win(6, 4), TournamentResultService::ORIGIN_LARA, $this->organizer);
        }

        $tournament->update(['status' => Tournament::STATUS_FINISHED]);
        $this->report[] = ['Encerrado', 'chave completa com campeão e 3º lugar; o site não lança mais (results_closed)'];
    }

    private function cancelledTournament(array $t): void
    {
        $tournament = $this->tournament('Cancelado', Tournament::STATUS_CANCELLED, now()->subDays(10), now()->addDays(2), now()->addDays(8), now()->addDays(8));
        $category = $this->category($tournament, $t['duplas'], ['price' => 80]);
        $this->registration($category, $this->people[16], Registration::STATUS_CANCELLED, ['cancelled_at' => now(), 'cancelled_by' => $this->organizer->id, 'cancel_reason' => 'Torneio cancelado (teste)']);
        $this->report[] = ['Cancelado', 'não aparece no site; inscrição cancelada sem estorno'];
    }

    /** Contas do site: duplicada, sem e-mail, senha antiga. */
    private function siteAccounts(): void
    {
        $base = $this->people[16];

        $copy = new Member();
        $copy->forceFill([
            'name' => self::PREFIX . ' Paula Freitas (duplicada)',
            'cpf' => $base->cpf,
            'title' => $base->title,
            'birth_date' => $base->birth_date,
            'email' => 'paula.outra@exemplo.invalid',
            'password' => SiteAccountService::siteHash('outra123'),
        ])->save();

        $this->people[19]->forceFill(['email' => null])->save();
        // Senha no formato antigo: SHA256 puro (é convertida no primeiro login certo).
        $this->people[20]->forceFill(['password' => hash('sha256', self::PASSWORD)])->save();

        $this->report[] = ['Contas do site › ' . $base->cpf, 'CPF com 2 contas (o login usa a mais antiga; a cópia tem senha "outra123")'];
        $this->report[] = ['Contas do site › ' . $this->people[19]->cpf, 'conta sem e-mail'];
        $this->report[] = ['Contas do site › ' . $this->people[20]->cpf, 'senha no formato antigo (SHA256 puro)'];
    }
}
