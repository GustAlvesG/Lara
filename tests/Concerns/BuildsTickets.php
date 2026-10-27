<?php

namespace Tests\Concerns;

use App\Models\Member;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketLot;
use App\Models\Ticket\TicketType;
use App\Services\MultiClubes\MemberProfile;
use App\Services\MultiClubes\MemberProfileLookup;
use App\Services\RedeItauService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Evento, tipos, lotes e sócios para os testes dos ingressos, com o
 * MultiClubes e a eRede trocados por versões em memória (`$this->mcProfiles`,
 * `$this->redeRefunds`). Use depois de createTicketSchema().
 */
trait BuildsTickets
{
    /** @var array<string, MemberProfile> "cpf|matrícula" => perfil */
    public array $mcProfiles = [];

    /** @var list<array{tid: string, amount: float}> */
    public array $redeRefunds = [];

    /** CPFs válidos de não sócios (não estão no MultiClubes falso). */
    protected const GUEST_CPFS = ['52998224725', '11144477735', '93541134780', '45317828791'];

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
    protected function member(string $cpf, string $name, string $title = '1001'): Member
    {
        $id = DB::table('members')->insertGetId(['cpf' => $cpf, 'name' => $name, 'email' => strtolower(strtok($name, ' ')) . '@teste.local', 'title' => $title]);
        $this->mcProfiles["{$cpf}|{$title}"] = new MemberProfile($name, $cpf, $title, Carbon::parse('1990-05-10'), 'female', strtolower(strtok($name, ' ')) . '@mc.local', true);

        return Member::findOrFail($id);
    }

    /** Dependente do título no MultiClubes (sem conta no site). */
    protected function dependent(string $cpf, string $name, string $title = '1001'): void
    {
        $this->mcProfiles["{$cpf}|{$title}"] = new MemberProfile($name, $cpf, $title, Carbon::parse('2012-03-01'), 'male', null, false);
    }

    protected function ticketEvent(array $attributes = []): TicketEvent
    {
        return TicketEvent::create(array_merge([
            'title' => 'Baile de Carnaval',
            'venue' => 'Salão Nobre',
            'starts_at' => now()->addDays(20)->setTime(22, 0),
            'ends_at' => now()->addDays(21)->setTime(4, 0),
            'sales_starts_at' => now()->subDay(),
            'sales_ends_at' => now()->addDays(19),
            'max_per_order' => 4,
            'status' => TicketEvent::STATUS_PUBLISHED,
        ], $attributes));
    }

    protected function ticketType(TicketEvent $event, string $name = 'Pista', array $attributes = []): TicketType
    {
        return $event->types()->create(array_merge(['name' => $name], $attributes));
    }

    protected function ticketLot(TicketType $type, array $attributes = []): TicketLot
    {
        $next = ($type->lots()->max('sequence') ?? 0) + 1;

        return $type->lots()->create(array_merge([
            'name' => "{$next}º lote",
            'sequence' => $next,
            'quantity' => 100,
            'price_member' => 50,
            'price_non_member' => 80,
        ], $attributes));
    }

    /** Um titular para o pedido do não sócio. */
    protected function holder(int $typeId, int $index = 0, ?string $name = null): array
    {
        return ['type_id' => $typeId, 'holder_name' => $name ?? 'Convidado ' . ($index + 1), 'holder_cpf' => self::GUEST_CPFS[$index]];
    }
}
