<?php

namespace Tests\Feature\Finance;

use App\Authorization\Permissions as P;
use App\Models\SchedulePayment;
use App\Models\Ticket\TicketPayment;
use App\Models\Tournament\TournamentPayment;
use App\Models\User;
use App\Services\Finance\FinanceLedger;
use App\Services\Finance\PaymentRefunder;
use App\Services\RedeItauService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Concerns\BuildsTickets;
use Tests\Concerns\CreatesFinanceSchema;
use Tests\Concerns\CreatesTicketSchema;
use Tests\TestCase;

/**
 * Módulo Financeiro (13/11/2026, docs/funcionalidades/financeiro.md):
 * o estorno único (PaymentRefunder), a consulta que junta Reservas, Torneios
 * e Ingressos (FinanceLedger), as telas, o menu e os endereços antigos.
 */
class FinanceModuleTest extends TestCase
{
    use CreatesTicketSchema;
    use BuildsTickets;
    use CreatesFinanceSchema;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTicketSchema();
        $this->createFinanceSchema();
        $this->fakeIntegrations();

        $this->staff = $this->makeUser('Secretaria');
        $this->seedPayments();
    }

    /* ------------------------------------------------------------ estorno */

    public function test_estorno_unico_vale_para_as_tres_origens(): void
    {
        $refunder = app(PaymentRefunder::class);

        $schedule = SchedulePayment::where('payment_integration_id', 'TID-R')->sole();
        $this->assertSame(40.0, $refunder->refund($schedule, 40, $this->staff->id));
        $this->assertSame(1, (int) $schedule->fresh()->status_id, 'estorno parcial mantém o status');
        $refunder->refund($schedule->fresh(), null, $this->staff->id);
        $this->assertSame(0, (int) $schedule->fresh()->status_id, 'estorno total cancela');

        $tournament = TournamentPayment::where('payment_integration_id', 'TID-T')->sole();
        // clamp: pede mais do que tem, estorna o disponível.
        $this->assertSame(80.0, $refunder->refund($tournament, 500, $this->staff->id, clamp: true));
        $this->assertSame(TournamentPayment::STATUS_REFUNDED, $tournament->fresh()->status);

        $this->assertSame([['tid' => 'TID-R', 'amount' => 40.0], ['tid' => 'TID-R', 'amount' => 60.0], ['tid' => 'TID-T', 'amount' => 80.0]], $this->redeRefunds);
    }

    public function test_estorno_recusa_baixa_manual_e_valor_acima_do_disponivel(): void
    {
        $refunder = app(PaymentRefunder::class);

        try {
            $refunder->refund(TicketPayment::where('is_manual', true)->sole(), 10, $this->staff->id);
            $this->fail('baixa manual não estorna');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('baixa manual', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Disponível para estorno: R$ 100,00');
        $refunder->refund(SchedulePayment::where('payment_integration_id', 'TID-R')->sole(), 150, $this->staff->id);
    }

    public function test_reserva_continua_estornando_pelo_rede_itau_service(): void
    {
        $payment = SchedulePayment::where('payment_integration_id', 'TID-R')->sole();
        app(RedeItauService::class)->refundPayment($payment, 25, $this->staff->id);

        $this->assertSame('25.00', $payment->fresh()->refunded_amount);
        $this->assertSame($this->staff->id, (int) $payment->fresh()->refunded_by);
    }

    /* ------------------------------------------------------------- ledger */

    public function test_ledger_junta_as_tres_origens_e_filtra(): void
    {
        $ledger = app(FinanceLedger::class);
        $rows = $ledger->query()->get();

        $this->assertCount(4, $rows);
        $this->assertEqualsCanonicalizing(['reservas', 'reservas', 'torneios', 'ingressos'], $rows->pluck('origin')->all());

        $court = $rows->firstWhere('tid', 'TID-R');
        $this->assertSame('Ana Reserva', $court->payer_name);
        $this->assertSame('Quadras de Tênis', $court->reference);
        $this->assertSame('Copa do Clube', $rows->firstWhere('tid', 'TID-T')->reference);
        $this->assertSame('Baile de Carnaval', $rows->firstWhere('origin', 'ingressos')->reference);

        $this->assertCount(2, $ledger->query(['method' => FinanceLedger::METHOD_MANUAL])->get());
        $this->assertCount(1, $ledger->query(['origin' => 'torneios'])->get());
        // Os dois pagamentos de reserva são da Ana.
        $this->assertCount(2, $ledger->query(['search' => 'ana res'])->get());
        $this->assertCount(2, $ledger->query(['search' => '390.533.447-05'])->get());
        $this->assertCount(1, $ledger->query(['search' => 'TID-T'])->get());
        $this->assertCount(0, $ledger->query(['from' => Carbon::tomorrow()])->get());

        app(PaymentRefunder::class)->refund(SchedulePayment::where('payment_integration_id', 'TID-R')->sole(), 30, $this->staff->id);
        $this->assertCount(1, $ledger->query(['status' => FinanceLedger::STATUS_PARTIAL])->get());
        $this->assertCount(3, $ledger->query(['status' => FinanceLedger::STATUS_PAID])->get());
    }

    public function test_resumo_soma_por_origem_e_forma(): void
    {
        $summary = app(FinanceLedger::class)->summary();

        $this->assertSame(4, $summary['total']['count']);
        $this->assertSame(270.0, $summary['total']['paid']);
        $this->assertSame(180.0, $summary['total']['online']);
        $this->assertSame(90.0, $summary['total']['manual']);
        $this->assertSame(150.0, $summary['origins']['reservas']['paid']);
        $this->assertSame(80.0, $summary['origins']['torneios']['paid']);
        $this->assertSame(40.0, $summary['origins']['ingressos']['paid']);
        $this->assertSame(90.0, $summary['methods']['manual_cash']['paid']);
    }

    /* -------------------------------------------------------------- telas */

    public function test_telas_do_financeiro_e_permissoes(): void
    {
        $finance = $this->userWith([P::FINANCEIRO_RECEBIMENTOS], 'Contabilidade');

        $this->actingAs($finance)->get(route('finance.index'))->assertOk()
            ->assertSee('Financeiro')->assertSee('R$ 270,00', false)->assertSee('Freelancers');
        $this->actingAs($finance)->get(route('finance.receipts.index'))->assertOk()
            ->assertSee('Ana Reserva')->assertSee('Copa do Clube')->assertSee('Baile de Carnaval');
        $this->actingAs($finance)->get(route('finance.receipts.index', ['origem' => 'torneios']))->assertOk()
            ->assertSee('Copa do Clube')->assertDontSee('Ana Reserva');
        $csv = $this->actingAs($finance)->get(route('finance.receipts.csv'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Torneios;', $csv);
        $this->assertStringContainsString('100,00', $csv);

        $other = $this->userWith([P::INGRESSOS_CHECKIN], 'Portaria');
        $this->actingAs($other)->get(route('finance.index'))->assertForbidden();
        $this->actingAs($other)->get(route('finance.receipts.index'))->assertForbidden();
    }

    public function test_caixa_junta_as_baixas_das_tres_origens(): void
    {
        $finance = $this->userWith([P::INGRESSOS_CAIXA_TODOS], 'Fechamento');

        $this->actingAs($finance)->get(route('finance.cash.index'))->assertOk()
            ->assertSee('Secretaria')->assertSee('R$ 90,00', false)->assertSee('Reservas')->assertSee('Ingressos');

        // Quem só dá baixa em reserva vê o próprio caixa.
        $desk = $this->userWith([P::RESERVAS_PAGAMENTOS_BAIXA_MANUAL], 'Balcão');
        $this->actingAs($desk)->get(route('finance.cash.index'))->assertOk()->assertSee('Você vê só o seu caixa');
    }

    public function test_menu_tem_o_modulo_financeiro_e_os_modulos_perderam_as_abas(): void
    {
        $user = $this->userWith([P::FINANCEIRO_RECEBIMENTOS, P::RESERVAS_PAGAMENTOS, P::RESERVAS_AGENDAMENTOS, P::INGRESSOS_FINANCEIRO], 'Gestor');
        $this->actingAs($user);

        $nav = \App\View\Navigation::build($user, request());
        $groups = collect($nav['links'])->keyBy('label');

        $this->assertSame(['finance.index', 'finance.receipts.index', 'payment.index', 'tickets.finance.index'],
            array_column($groups['Financeiro']['children'], 'route'));
        $this->assertNotContains('payment.index', array_column($groups['Reservas']['children'], 'route'));
    }

    public function test_enderecos_antigos_levam_ao_financeiro(): void
    {
        $user = $this->userWith([P::RESERVAS_PAGAMENTOS], 'Gestor');
        $this->actingAs($user);

        $this->get('/payments?member=ana')->assertRedirect(url('/financeiro/reservas?member=ana'))->assertStatus(301);
        $this->get('/payments/7')->assertRedirect(url('/financeiro/reservas/7'));
        $this->get('/ingressos/caixa/pdf?de=2026-11-01')->assertRedirect(url('/financeiro/caixa/pdf?de=2026-11-01'));
        $this->get('/ingressos/5/financeiro/csv')->assertRedirect(url('/financeiro/ingressos/5/csv'));
        $this->get('/torneios/financeiro')->assertRedirect(url('/financeiro/torneios'));
        $this->get('/freelancer-services/financeiro/lote/3/impressao')->assertRedirect(url('/financeiro/freelancers/lote/3/impressao'));
    }

    /* ------------------------------------------------------------- apoio */

    /**
     * Reservas: R$ 100 eRede (Ana, Quadras de Tênis) e R$ 50 em dinheiro;
     * Torneios: R$ 80 eRede (Copa do Clube); Ingressos: R$ 40 em dinheiro.
     */
    private function seedPayments(): void
    {
        $now = Carbon::now()->subHour();
        $ana = DB::table('members')->insertGetId(['name' => 'Ana Reserva', 'cpf' => '39053344705']);
        $bia = DB::table('members')->insertGetId(['name' => 'Bia Torneio', 'cpf' => '16899535009']);
        $group = DB::table('place_groups')->insertGetId(['name' => 'Quadras de Tênis']);
        $place = DB::table('places')->insertGetId(['name' => 'Quadra 1', 'place_group_id' => $group]);

        $online = DB::table('schedule_payments')->insertGetId(['payment_method' => 'credit', 'paid_amount' => 100, 'paid_at' => $now, 'payment_integration_id' => 'TID-R', 'status_id' => 1, 'is_manual' => false]);
        $manual = DB::table('schedule_payments')->insertGetId(['payment_method' => 'manual_cash', 'paid_amount' => 50, 'paid_at' => $now, 'status_id' => 1, 'is_manual' => true, 'recorded_by' => $this->staff->id]);
        DB::table('schedules')->insert([
            ['place_id' => $place, 'member_id' => $ana, 'schedule_payment_id' => $online, 'status_id' => 1, 'price' => 100],
            ['place_id' => $place, 'member_id' => $ana, 'schedule_payment_id' => $manual, 'status_id' => 1, 'price' => 50],
        ]);

        $tournament = DB::table('tournaments')->insertGetId(['title' => 'Copa do Clube']);
        $category = DB::table('tournament_categories')->insertGetId(['tournament_id' => $tournament, 'name' => 'Simples A']);
        $tpay = DB::table('tournament_payments')->insertGetId(['member_id' => $bia, 'payment_method' => 'credit', 'paid_amount' => 80, 'paid_at' => $now, 'payment_integration_id' => 'TID-T', 'is_manual' => false]);
        DB::table('tournament_registrations')->insert(['tournament_category_id' => $category, 'tournament_payment_id' => $tpay, 'member_id' => $bia, 'name' => 'Bia Torneio', 'amount' => 80]);

        $event = $this->ticketEvent();
        $order = DB::table('ticket_orders')->insertGetId(['ticket_event_id' => $event->id, 'buyer_kind' => 'guest', 'buyer_cpf' => '11144477735', 'buyer_name' => 'Carla Ingresso', 'total' => 40, 'status' => 'paid', 'origin' => 'counter', 'paid_at' => $now]);
        DB::table('ticket_payments')->insert(['ticket_order_id' => $order, 'payment_method' => 'manual_cash', 'paid_amount' => 40, 'paid_at' => $now, 'is_manual' => true, 'recorded_by' => $this->staff->id]);
    }

    private function userWith(array $permissions, string $name): User
    {
        $user = $this->makeUser($name);
        foreach ($permissions as $permission) {
            DB::table('model_has_permissions')->insert([
                'permission_id' => DB::table('permissions')->where('name', $permission)->value('id'),
                'model_type' => 'App\\Models\\User',
                'model_id' => $user->id,
            ]);
        }
        $user->forgetAccess();

        return $user;
    }
}
