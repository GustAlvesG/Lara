<?php

namespace Tests\Feature\Tickets;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketOrder;
use App\Models\User;
use App\Services\Ticket\TicketBuyer;
use App\Services\Ticket\TicketFinanceService;
use App\Services\Ticket\TicketOrderService;
use App\Support\Cpf;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTickets;
use Tests\Concerns\CreatesTicketSchema;
use Tests\TestCase;

/**
 * Ingressos, F7: relatório financeiro (vendidos, cortesias, descontos,
 * recebido por forma de pagamento, cupons), CSV e o comando de dados de teste.
 */
class TicketFinanceTest extends TestCase
{
    use CreatesTicketSchema;
    use BuildsTickets;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->createTicketSchema();
        $this->fakeIntegrations();
        $this->staff = $this->makeUser('Financeiro');
        $this->joinSector($this->staff, 'TI');
    }

    public function test_totais_por_lote_forma_e_cupom(): void
    {
        $event = $this->ticketEvent();
        $type = $this->ticketType($event);
        $this->ticketLot($type, ['price_non_member' => 100]);
        $event->coupons()->create(['code' => 'DEZ', 'kind' => 'percent', 'value' => 10]);
        $orders = app(TicketOrderService::class);
        $guest = fn (int $i) => TicketBuyer::guest(self::GUEST_CPFS[$i], 'Comprador ' . $i, null, null);

        $online = $orders->create($event, $guest(0), [$this->holder($type->id, 0), $this->holder($type->id, 1)], couponCode: 'DEZ');
        $orders->pay($online, ['payment_method' => 'credit', 'paid_amount' => 180, 'payment_integration_id' => 'TID-F']);
        $orders->issueAtCounter($event, $guest(2), [$this->holder($type->id, 2)], $this->staff, TicketOrderService::ISSUE_EXTERNAL, ['payment_method' => 'manual_cash']);
        $orders->issueAtCounter($event, $guest(3), [$this->holder($type->id, 3)], $this->staff, TicketOrderService::ISSUE_COURTESY);
        $orders->cancelTicket($online->tickets()->first(), $this->staff, 'Desistiu', refund: true);

        $finance = app(TicketFinanceService::class);
        $totals = $finance->totals($event);

        $this->assertSame(3, $totals['sold']);
        $this->assertSame(1, $totals['courtesies']);
        $this->assertSame(1, $totals['cancelled']);
        $this->assertSame(10.0, $totals['discount']);
        $this->assertSame(280.0, $totals['paid']);
        $this->assertSame(90.0, $totals['refunded']);
        $this->assertSame(190.0, $totals['received']);

        $methods = $finance->byMethod($event)->keyBy('method');
        $this->assertSame(180.0, $methods['eRede (site)']['paid']);
        $this->assertSame(100.0, $methods->first(fn ($row) => $row['method'] !== 'eRede (site)')['paid']);
        $this->assertSame([['code' => 'DEZ', 'count' => 1, 'discount' => 10.0]], $finance->byCoupon($event)->all());

        $this->actingAs($this->staff)->get(route('tickets.finance.index'))->assertOk()->assertSee($event->title);
        $this->actingAs($this->staff)->get(route('tickets.finance.show', $event))->assertOk()->assertSee('DEZ');

        $csv = $this->actingAs($this->staff)->get(route('tickets.finance.csv', $event))->assertOk()->streamedContent();
        $this->assertStringContainsString('Cortesia', $csv);
        $this->assertStringNotContainsString(self::GUEST_CPFS[0], $csv);
        $this->assertSame(5, substr_count(trim($csv), "\n") + 1);
    }

    public function test_dados_de_teste_criam_e_limpam_respeitando_as_regras(): void
    {
        $this->artisan('ingressos:dados-teste')->assertSuccessful();

        $events = TicketEvent::where('title', 'like', '[TESTE]%')->get();
        $this->assertCount(7, $events);
        // Réveillon com lugar marcado: três mapas, mesas presas aos ingressos.
        $this->assertSame(3, \App\Models\Ticket\TicketMap::count());
        $this->assertSame(4, \App\Models\Ticket\TicketSeatClaim::count());

        // Um ingresso ativo por CPF em cada evento (a cortesia sem CPF fica de fora).
        $duplicated = Ticket::whereIn('status', Ticket::SOLD)
            ->whereNotNull('holder_cpf')
            ->selectRaw('ticket_event_id, holder_cpf, count(*) as total')
            ->groupBy('ticket_event_id', 'holder_cpf')
            ->having('total', '>', 1)
            ->count();
        $this->assertSame(0, $duplicated);
        $this->assertTrue(Ticket::whereNotNull('holder_cpf')->get()->every(fn (Ticket $t) => Cpf::isValid($t->holder_cpf)));
        $this->assertGreaterThan(0, TicketOrder::where('courtesy', true)->count());
        // 20 cortesias sem CPF, duas mesas cativas e o histórico das emissões.
        $this->assertSame(20, Ticket::whereNull('holder_cpf')->count());
        $this->assertSame(2, \App\Models\Ticket\TicketSeat::whereNotNull('captive_title')->count());
        $this->assertGreaterThan(0, \App\Models\Ticket\TicketOrderLog::count());

        // Rodar de novo recria; --so-limpar apaga tudo.
        $this->artisan('ingressos:dados-teste')->assertSuccessful();
        $this->assertCount(7, TicketEvent::where('title', 'like', '[TESTE]%')->get());

        $this->artisan('ingressos:dados-teste', ['--so-limpar' => true])->assertSuccessful();
        $this->assertSame(0, TicketEvent::withTrashed()->count());
        $this->assertSame(0, Ticket::count());
        $this->assertSame(0, \App\Models\Ticket\TicketEventDay::count());
        $this->assertSame(0, \App\Models\Ticket\TicketSeat::count());
        $this->assertSame(0, \App\Models\Ticket\TicketOrderLog::count());
    }
}
