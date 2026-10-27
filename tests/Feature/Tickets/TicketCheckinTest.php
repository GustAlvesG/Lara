<?php

namespace Tests\Feature\Tickets;

use App\Exceptions\ConfirmationRequired;
use App\Exceptions\TicketException;
use App\Models\Company\CompanyAccessLog;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Services\CompanyService;
use App\Services\Ticket\TicketBuyer;
use App\Services\Ticket\TicketCheckinService;
use App\Services\Ticket\TicketOrderService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsTickets;
use Tests\Concerns\CreatesFreelancerPixSchema;
use Tests\Concerns\CreatesTicketSchema;
use Tests\TestCase;

/**
 * Ingressos, F6: check-in na tela do Lara e no Monitor de Acesso da
 * portaria (pelo CPF do titular e pelo código ING-), uma entrada por
 * ingresso, janela de entrada do evento, e o histórico de acessos.
 */
class TicketCheckinTest extends TestCase
{
    use CreatesTicketSchema;
    use CreatesFreelancerPixSchema;
    use BuildsTickets;

    private TicketEvent $event;
    private TicketType $pista;
    private User $staff;
    private TicketCheckinService $checkin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['tickets.checkin_hours_before' => 3]);

        $this->createTicketSchema();
        $this->fakeIntegrations();

        // O CPF no monitor também consulta freelancer e liberação pontual.
        $this->createFreelancerPixSchema();
        Schema::withoutForeignKeyConstraints(function () {
            foreach ([
                '2026_07_20_150000_create_uber_access_requests_tables.php',
                '2026_01_14_151220_create_companies_table.php',
                '2026_01_14_151348_create_company_workers_table.php',
                '2026_01_14_151811_create_company_access_rules_table.php',
                '2026_06_02_100000_create_company_access_logs_table.php',
                '2026_06_10_000000_create_app_drivers_table.php',
                '2026_06_10_000100_add_app_driver_to_company_access_logs.php',
                '2026_07_21_000000_add_uber_fields_to_company_access_logs.php',
                '2026_08_03_130000_add_freelancer_to_company_access_logs.php',
                '2026_09_11_140000_create_one_off_accesses_table.php',
                '2026_09_11_140100_add_one_off_access_to_company_access_logs.php',
                '2026_10_27_110000_add_ticket_id_to_company_access_logs.php',
            ] as $migration) {
                (require base_path('database/migrations/' . $migration))->up();
            }
        });

        // A entrada abre 3 h antes: o evento começa daqui a 1 h.
        $this->event = $this->ticketEvent(['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(6), 'sales_ends_at' => now()->addMinutes(50)]);
        $this->pista = $this->ticketType($this->event);
        $this->ticketLot($this->pista);
        $this->staff = $this->makeUser('Portaria Evento');
        $this->joinSector($this->staff, 'TI');
        $this->checkin = app(TicketCheckinService::class);
    }

    private function paidTicket(int $index = 0): Ticket
    {
        $order = app(TicketOrderService::class)->issueAtCounter($this->event, TicketBuyer::guest(self::GUEST_CPFS[$index], 'Comprador', null, null),
            [$this->holder($this->pista->id, $index)], $this->staff, TicketOrderService::ISSUE_COURTESY);

        return $order->tickets->sole();
    }

    public function test_uma_entrada_por_ingresso(): void
    {
        $ticket = $this->paidTicket();

        $used = $this->checkin->checkIn($ticket, $this->staff, Ticket::CHECKIN_LARA);
        $this->assertSame(Ticket::STATUS_USED, $used->status);
        $this->assertSame($this->staff->id, $used->checked_in_by);

        $this->expectException(TicketException::class);
        $this->checkin->checkIn($ticket, $this->staff, Ticket::CHECKIN_LARA);
    }

    public function test_fora_da_janela_o_lara_confirma_e_o_monitor_nega(): void
    {
        $this->event->update(['starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHours(5)]);
        $ticket = $this->paidTicket();

        try {
            $this->checkin->checkIn($ticket->fresh(), null, Ticket::CHECKIN_MONITOR);
            $this->fail('Esperava recusa.');
        } catch (TicketException $e) {
            $this->assertSame(TicketException::OUTSIDE_WINDOW, $e->reason);
        }

        try {
            $this->checkin->checkIn($ticket->fresh(), $this->staff, Ticket::CHECKIN_LARA);
            $this->fail('Esperava confirmação.');
        } catch (ConfirmationRequired) {
        }

        $this->assertSame(Ticket::STATUS_USED, $this->checkin->checkIn($ticket->fresh(), $this->staff, Ticket::CHECKIN_LARA, force: true)->status);
    }

    public function test_pendente_nao_entra(): void
    {
        $order = app(TicketOrderService::class)->create($this->event, TicketBuyer::guest(self::GUEST_CPFS[0], 'Carla', null, null), [$this->holder($this->pista->id, 0)]);

        $state = $this->checkin->evaluate($order->tickets->sole());
        $this->assertFalse($state['allowed']);
        $this->assertSame(TicketCheckinService::REASON_NOT_VALID, $state['reason']);
    }

    public function test_tela_do_lara_registra_pelo_codigo_e_pelo_cpf(): void
    {
        $byCode = $this->paidTicket(0);
        $byCpf = $this->paidTicket(1);

        $this->actingAs($this->staff)->get(route('tickets.checkin.index'))->assertOk()->assertSee($this->event->title)->assertSee('0</span> de 2', false);

        $this->actingAs($this->staff)->post(route('tickets.checkin.store', $this->event), ['target' => strtolower($byCode->code)])
            ->assertRedirect()->assertSessionHas('checkin.allowed', true);
        $this->assertSame(Ticket::STATUS_USED, $byCode->fresh()->status);

        $this->actingAs($this->staff)->post(route('tickets.checkin.store', $this->event), ['target' => $byCpf->holder_cpf, 'only_check' => 1])
            ->assertSessionHas('checkin.allowed', true);
        $this->assertSame(Ticket::STATUS_VALID, $byCpf->fresh()->status);

        $this->actingAs($this->staff)->post(route('tickets.checkin.store', $this->event), ['target' => $byCode->code])
            ->assertSessionHas('checkin.allowed', false);
    }

    public function test_monitor_acha_o_ingresso_pelo_cpf_e_registra_com_check_in(): void
    {
        $ticket = $this->paidTicket();
        $service = app(CompanyService::class);

        $found = $service->validateTryToAccess(['target' => $ticket->holder_cpf]);
        $this->assertTrue($found['found']);
        $this->assertSame('ticket', $found['workers'][0]['type']);
        $this->assertTrue($found['workers'][0]['allowed']);
        $this->assertSame(Ticket::STATUS_VALID, $ticket->fresh()->status);

        $registered = $service->registerAccess(['target' => $ticket->holder_cpf]);
        $this->assertTrue($registered['workers'][0]['allowed']);
        $this->assertSame(Ticket::STATUS_USED, $ticket->fresh()->status);
        $this->assertSame(Ticket::CHECKIN_MONITOR, $ticket->fresh()->checkin_source);

        // Segunda vez: aparece negado, com o motivo, e também vai para o histórico.
        $again = $service->registerAccess(['target' => $ticket->holder_cpf]);
        $this->assertFalse($again['workers'][0]['allowed']);
        $this->assertSame(TicketCheckinService::REASON_USED, $again['workers'][0]['reason']);

        $logs = CompanyAccessLog::where('ticket_id', $ticket->id)->orderBy('id')->get();
        $this->assertSame([true, false], $logs->pluck('allowed')->all());
    }

    public function test_monitor_pelo_codigo_e_pelo_botao(): void
    {
        $ticket = $this->paidTicket();
        $service = app(CompanyService::class);

        $byCode = $service->validateTryToAccess(['target' => $ticket->code]);
        $this->assertSame('ticket', $byCode['type']);

        $this->postJson('/api/company-access/register-ticket-access', ['ticket_id' => $ticket->id])
            ->assertOk()->assertJsonPath('workers.0.allowed', true);
        $this->assertSame(Ticket::STATUS_USED, $ticket->fresh()->status);

        $this->assertFalse($service->validateTryToAccess(['target' => 'ING-ZZZZZZZZ'])['found']);
    }
}
