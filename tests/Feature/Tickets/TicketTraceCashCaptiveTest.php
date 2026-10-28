<?php

namespace Tests\Feature\Tickets;

use App\Authorization\Permissions as P;
use App\Exceptions\TicketException;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketMap;
use App\Models\Ticket\TicketOrder;
use App\Models\Ticket\TicketOrderLog;
use App\Models\Ticket\TicketSeat;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Providers\Services\JwtService;
use App\Services\Ticket\TicketBuyer;
use App\Services\Ticket\TicketCashService;
use App\Services\Ticket\TicketOrderService;
use App\Services\Ticket\TicketSeatService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTickets;
use Tests\Concerns\CreatesTicketSchema;
use Tests\TestCase;

/**
 * Rastreio, caixa, impressão térmica, cortesia sem CPF e mesa cativa (F14–F17):
 * toda ação do pedido vai para o histórico com quem fez; o caixa soma por
 * pessoa, dia e forma de pagamento (cada um vê o próprio, o financeiro vê
 * todos); a impressão conta a via; a cortesia de não sócio sai sem CPF, até
 * em quantidade; a mesa cativa só vende para a matrícula até a liberação.
 */
class TicketTraceCashCaptiveTest extends TestCase
{
    use CreatesTicketSchema;
    use BuildsTickets;

    private const CPF_ANA = '39053344705';
    private const CPF_BIA = '16899535009';

    private string $token = 'token-de-teste';
    private User $staff;
    private TicketEvent $event;
    private TicketType $pista;
    private TicketType $mesa;
    private TicketMap $map;
    /** @var array<string, TicketSeat> */
    private array $seats = [];
    private TicketOrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.api.token' => $this->token, 'services.jwt.secret' => 'segredo-de-teste']);

        $this->createTicketSchema();
        $this->fakeIntegrations();

        $this->staff = $this->makeUser('Secretaria');
        $this->joinSector($this->staff, 'TI');

        $this->event = $this->ticketEvent(['capacity' => 100]);
        $this->pista = $this->ticketType($this->event, 'Pista');
        $this->ticketLot($this->pista);

        $this->map = $this->event->maps()->create(['name' => 'Mesas']);
        foreach (['M1', 'M2', 'M3'] as $i => $label) {
            $this->seats[$label] = $this->map->seats()->create(['label' => $label, 'x' => 100 + $i * 60, 'y' => 200, 'places' => 4]);
        }
        $this->mesa = $this->ticketType($this->event, 'Mesa', ['ticket_map_id' => $this->map->id]);
        $this->ticketLot($this->mesa, ['price_member' => 200, 'price_non_member' => 300]);

        $this->orders = app(TicketOrderService::class);
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

    private function guest(int $index = 0): TicketBuyer
    {
        return TicketBuyer::guest(self::GUEST_CPFS[$index], 'Comprador ' . $index, "c{$index}@x.local", null);
    }

    private function sell(User $user, string $method, int $holder = 0): TicketOrder
    {
        return $this->orders->issueAtCounter($this->event, $this->guest($holder), [$this->holder($this->pista->id, $holder)], $user, TicketOrderService::ISSUE_EXTERNAL, ['payment_method' => $method]);
    }

    private function memberHeaders(string $cpf): array
    {
        return ['Authorization' => 'Bearer ' . $this->token, 'Accept' => 'application/json',
            'Session' => (new JwtService())->generateToken(['username' => $cpf, 'exp' => time() + 3600])];
    }

    /* ------------------------------------------------------------ rastreio */

    public function test_historico_grava_quem_fez_cada_acao(): void
    {
        $order = $this->sell($this->staff, 'manual_cash');
        $ticket = $order->tickets->sole();

        $this->orders->changeHolder($ticket, 'Outra Pessoa', self::GUEST_CPFS[2], $this->staff);
        $this->orders->cancelTicket($ticket->fresh(), $this->staff, 'desistiu');

        $actions = TicketOrderLog::where('ticket_order_id', $order->id)->orderBy('id')->pluck('action')->all();
        $this->assertSame([TicketOrderLog::CREATED, TicketOrderLog::PAID_MANUAL, TicketOrderLog::HOLDER_CHANGED, TicketOrderLog::CANCELLED], $actions);
        $this->assertTrue(TicketOrderLog::where('ticket_order_id', $order->id)->get()->every(fn ($log) => $log->user_id === $this->staff->id));

        $paid = TicketOrderLog::where('action', TicketOrderLog::PAID_MANUAL)->sole();
        $this->assertSame('Dinheiro', $paid->details['method_label']);
        $this->assertStringContainsString('Outra Pessoa', TicketOrderLog::where('action', TicketOrderLog::HOLDER_CHANGED)->sole()->summary());

        // Pedido do site: sem usuário ("Site"); troca de lugar também vai para o histórico.
        $site = $this->orders->create($this->event, $this->guest(1), [$this->holder($this->mesa->id, 1) + ['seat_id' => $this->seats['M1']->id]]);
        $this->assertNull(TicketOrderLog::where('ticket_order_id', $site->id)->sole()->user_id);
        app(TicketSeatService::class)->changeSeat($site->tickets->sole(), $this->seats['M2'], $this->staff);
        $this->assertSame(['from' => 'M1', 'to' => 'M2'], TicketOrderLog::where('action', TicketOrderLog::SEAT_CHANGED)->sole()->details);

        $this->actingAs($this->staff)->get(route('tickets.orders.show', $order))->assertOk()
            ->assertSee('Histórico')->assertSee('Pagamento recebido (baixa manual)')->assertSee('Titular trocado');
    }

    public function test_lista_de_pedidos_filtra_por_quem_vendeu(): void
    {
        $other = $this->userWith([P::INGRESSOS_VENDAS, P::INGRESSOS_PAGAMENTOS_BAIXA_MANUAL], 'Caixa Dois');
        $mine = $this->sell($this->staff, 'manual_cash', 0);
        $theirs = $this->sell($other, 'manual_pix', 1);

        $this->actingAs($this->staff)->get(route('tickets.orders.index', [$this->event, 'vendedor' => $other->id]))->assertOk()
            ->assertSee('#' . $theirs->id)->assertDontSee('#' . $mine->id . '<', false);
        $this->actingAs($this->staff)->get(route('tickets.orders.index', [$this->event, 'vendedor' => 'site']))->assertOk()
            ->assertDontSee('#' . $theirs->id . '<', false);
    }

    /* --------------------------------------------------------------- caixa */

    public function test_caixa_soma_por_pessoa_dia_e_forma(): void
    {
        $other = $this->userWith([P::INGRESSOS_VENDAS, P::INGRESSOS_PAGAMENTOS_BAIXA_MANUAL], 'Caixa Dois');
        $this->sell($this->staff, 'manual_cash', 0);
        $this->sell($this->staff, 'manual_pix', 1);
        $cancelled = $this->sell($other, 'manual_cash', 2);
        $this->orders->cancelTicket($cancelled->tickets->sole(), $other, 'erro');
        $this->orders->issueAtCounter($this->event, $this->guest(3), [$this->holder($this->pista->id, 3)], $this->staff, TicketOrderService::ISSUE_COURTESY);

        $report = app(TicketCashService::class)->report(Carbon::today(), Carbon::today());
        $mine = $report['sellers'][$this->staff->id];
        $this->assertSame(160.0, $mine['total']);
        $this->assertSame(80.0, $mine['methods']['manual_cash']);
        $this->assertSame(80.0, $mine['methods']['manual_pix']);
        $this->assertSame(1, $mine['courtesies']);
        $theirs = $report['sellers'][$other->id];
        $this->assertSame(80.0, $theirs['total']);
        $this->assertSame(1, $theirs['cancelled']);
        $this->assertSame(240.0, $report['totals']['total']);
        $this->assertNotNull($report['site']);

        // Ontem: nada.
        $this->assertSame([], app(TicketCashService::class)->report(Carbon::yesterday(), Carbon::yesterday())['days']);

        // Quem só vende vê o próprio caixa, mesmo pedindo o de outra pessoa; o financeiro vê todos.
        $this->actingAs($other)->get(route('tickets.cash.index', ['pessoa' => $this->staff->id]))->assertOk()
            ->assertSee('Caixa Dois')->assertDontSee('Secretaria</td>', false)->assertDontSee('Site (eRede)');
        $finance = $this->userWith([P::INGRESSOS_CAIXA_TODOS], 'Financeiro');
        $this->actingAs($finance)->get(route('tickets.cash.index'))->assertOk()->assertSee('Secretaria')->assertSee('Caixa Dois')->assertSee('Site (eRede)');
        $this->actingAs($finance)->get(route('tickets.cash.pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');
        $csv = $this->actingAs($finance)->get(route('tickets.cash.csv'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Dinheiro', $csv);
        $this->assertStringContainsString('Cancelamento', $csv);

        $nobody = $this->userWith([P::INGRESSOS_CHECKIN], 'Portaria');
        $this->actingAs($nobody)->get(route('tickets.cash.index'))->assertForbidden();
    }

    /* ----------------------------------------------------------- impressão */

    public function test_impressao_termica_conta_a_via(): void
    {
        $order = $this->sell($this->staff, 'manual_cash');

        $this->actingAs($this->staff)->post(route('tickets.orders.print', $order))->assertOk()
            ->assertSee($order->tickets->sole()->code)->assertSee('72mm', false)->assertDontSee('2ª VIA');
        $this->actingAs($this->staff)->post(route('tickets.orders.print', $order))->assertOk()->assertSee('2ª VIA');

        $this->assertSame(2, $order->tickets->sole()->fresh()->print_count);
        $this->assertSame(2, TicketOrderLog::where('action', TicketOrderLog::PRINTED)->count());

        // Pendente não imprime.
        $pending = $this->orders->create($this->event, $this->guest(1), [$this->holder($this->pista->id, 1)]);
        $this->actingAs($this->staff)->post(route('tickets.orders.print', $pending))->assertSessionHasErrors('order');
    }

    /* ------------------------------------------------- cortesia sem CPF */

    public function test_cortesias_em_quantidade_sem_cpf(): void
    {
        // 30 de uma vez, sem CPF de ninguém: o limite por pedido (4) não vale para a cortesia em quantidade.
        $this->actingAs($this->staff)->post(route('tickets.issue.store', $this->event), [
            'mode' => TicketOrderService::ISSUE_COURTESY,
            'buyer_kind' => 'guest',
            'buyer_name' => 'Patrocinador X',
            'courtesy_format' => 'batch',
            'batch_type_id' => $this->pista->id,
            'batch_quantity' => 30,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $order = TicketOrder::where('buyer_name', 'Patrocinador X')->sole();

        $this->assertNull($order->buyer_cpf);
        $this->assertTrue($order->courtesy);
        $this->assertSame(30, $order->tickets()->count());
        $this->assertSame(30, $order->tickets()->whereNull('holder_cpf')->where('holder_name', 'Cortesia')->where('status', Ticket::STATUS_VALID)->count());

        // PDF para baixar e impressão térmica, sem a linha do CPF.
        $this->actingAs($this->staff)->get(route('tickets.orders.pdf', $order))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->staff)->post(route('tickets.orders.print', $order))->assertOk()->assertDontSee('CPF</div>', false);

        // Entra pelo código, uma vez.
        $code = $order->tickets()->first()->code;
        $this->assertCount(1, app(\App\Services\Ticket\TicketCheckinService::class)->find($code));

        // Mesa não sai em quantidade; não sócio pago continua exigindo CPF.
        $this->expectException(TicketException::class);
        $this->orders->batchItems($this->event, $this->mesa->id, 2);
    }

    public function test_cortesia_nominal_de_nao_socio_aceita_sem_cpf_e_venda_nao(): void
    {
        $order = $this->orders->issueAtCounter($this->event, TicketBuyer::guest(null, 'Imprensa', null, null), [
            ['type_id' => $this->pista->id, 'holder_name' => 'Repórter Um', 'holder_cpf' => ''],
            ['type_id' => $this->pista->id, 'holder_name' => '', 'holder_cpf' => ''],
            $this->holder($this->pista->id, 0),
        ], $this->staff, TicketOrderService::ISSUE_COURTESY);

        $this->assertSame(['Repórter Um', 'Cortesia', 'Convidado 1'], $order->tickets->pluck('holder_name')->all());
        $this->assertSame([null, null, self::GUEST_CPFS[0]], $order->tickets->pluck('holder_cpf')->all());

        try {
            $this->orders->issueAtCounter($this->event, TicketBuyer::guest(null, 'Sem CPF', null, null), [$this->holder($this->pista->id, 1)], $this->staff, TicketOrderService::ISSUE_EXTERNAL, ['payment_method' => 'manual_cash']);
            $this->fail('Venda sem CPF de quem compra devia recusar.');
        } catch (TicketException $e) {
            $this->assertSame(TicketException::INVALID_HOLDER, $e->reason);
        }

        $this->expectException(TicketException::class);
        $this->orders->issueAtCounter($this->event, $this->guest(1), [['type_id' => $this->pista->id, 'holder_name' => 'Fulano', 'holder_cpf' => '']], $this->staff, TicketOrderService::ISSUE_EXTERNAL, ['payment_method' => 'manual_cash']);
    }

    /* --------------------------------------------------------- mesa cativa */

    public function test_mesa_cativa_so_vende_para_a_matricula_ate_liberar(): void
    {
        $this->member(self::CPF_ANA, 'Ana Souza', '1001');
        $this->member(self::CPF_BIA, 'Bia Lima', '2002');
        $this->event->update(['captive_release_days' => 5]);

        // Cadastro pela tela: a matrícula vem com o nome do titular do MultiClubes.
        $this->actingAs($this->staff)->post(route('tickets.maps.captives.store', [$this->event, $this->map]), ['seat_id' => $this->seats['M1']->id, 'captive_title' => '9999'])
            ->assertSessionHasErrors('captive_title');
        $this->actingAs($this->staff)->post(route('tickets.maps.captives.store', [$this->event, $this->map]), ['seat_id' => $this->seats['M1']->id, 'captive_title' => '1001'])
            ->assertRedirect();
        $seat = $this->seats['M1']->fresh();
        $this->assertSame('1001', $seat->captive_title);
        $this->assertSame('Ana Souza', $seat->captive_name);

        // No mapa: cativa para os outros, livre para a matrícula.
        $public = $this->getJson("/api/tickets/events/{$this->event->id}/maps/{$this->map->id}", ['Authorization' => 'Bearer ' . $this->token])->assertOk();
        $this->assertSame('captive', collect($public->json('seats'))->firstWhere('label', 'M1')['status']);
        $mine = $this->withHeaders($this->memberHeaders(self::CPF_ANA))->getJson("/api/tickets/events/{$this->event->id}/maps/{$this->map->id}/member")->assertOk();
        $m1 = collect($mine->json('seats'))->firstWhere('label', 'M1');
        $this->assertSame('free', $m1['status']);
        $this->assertTrue($m1['captive_mine']);

        // Outra matrícula e não sócio: recusa (site e balcão).
        $this->withHeaders($this->memberHeaders(self::CPF_BIA))->postJson("/api/tickets/events/{$this->event->id}/orders", ['tickets' => [['type_id' => $this->mesa->id, 'seat_id' => $seat->id]]])
            ->assertStatus(422)->assertJsonPath('reason', TicketException::SEAT_CAPTIVE);
        try {
            $this->orders->issueAtCounter($this->event, $this->guest(0), [$this->holder($this->mesa->id, 0) + ['seat_id' => $seat->id]], $this->staff, TicketOrderService::ISSUE_COURTESY, force: true);
            $this->fail('Esperava seat_captive no balcão.');
        } catch (TicketException $e) {
            $this->assertSame(TicketException::SEAT_CAPTIVE, $e->reason);
            $this->assertStringContainsString('1001', $e->getMessage());
        }

        // A matrícula compra.
        $this->withHeaders($this->memberHeaders(self::CPF_ANA))->postJson("/api/tickets/events/{$this->event->id}/orders", ['tickets' => [['type_id' => $this->mesa->id, 'seat_id' => $seat->id]]])
            ->assertCreated()->assertJsonPath('tickets.0.seat.label', 'M1');

        $this->actingAs($this->staff)->get(route('tickets.maps.captives.index', [$this->event, $this->map]))->assertOk()
            ->assertSee('Ana Souza')->assertSee('Comprada pela matrícula');
    }

    public function test_cativa_libera_n_dias_antes_e_pode_ser_tirada(): void
    {
        $this->seats['M2']->update(['captive_title' => '1001', 'captive_name' => 'Ana Souza']);
        $this->seats['M3']->update(['captive_title' => '1001', 'captive_name' => 'Ana Souza']);

        // Sem prazo: segue cativa.
        $this->assertSame('1001', $this->seats['M2']->captiveFor($this->event));

        // Prazo de 30 dias antes de um evento daqui a 20: já liberou.
        $this->actingAs($this->staff)->put(route('tickets.maps.captives.release', [$this->event, $this->map]), ['captive_release_days' => 30])->assertRedirect();
        $this->event->refresh();
        $this->assertTrue($this->event->captivesReleased());
        $order = $this->orders->create($this->event, $this->guest(0), [$this->holder($this->mesa->id, 0) + ['seat_id' => $this->seats['M2']->id]]);
        $this->assertSame($this->seats['M2']->id, $order->tickets->sole()->ticket_seat_id);

        // Tirar a cativa (uma e todas).
        $this->event->update(['captive_release_days' => 1]);
        $this->actingAs($this->staff)->delete(route('tickets.maps.captives.destroy', [$this->event, $this->map, $this->seats['M3']]))->assertRedirect();
        $this->assertNull($this->seats['M3']->fresh()->captive_title);
        $this->seats['M1']->update(['captive_title' => '5']);
        $this->actingAs($this->staff)->delete(route('tickets.maps.captives.clear', [$this->event, $this->map]))->assertRedirect();
        $this->assertSame(0, TicketSeat::whereNotNull('captive_title')->count());
    }

    public function test_copiar_o_mapa_leva_as_cativas_com_aviso(): void
    {
        $this->seats['M1']->update(['captive_title' => '1001', 'captive_name' => 'Ana Souza']);
        $other = $this->ticketEvent(['title' => 'Réveillon 2027']);

        $this->actingAs($this->staff)->post(route('tickets.maps.store', $other), ['name' => 'Mesas', 'copy_from' => $this->map->id])
            ->assertRedirect()->assertSessionHas('warning');

        $copy = TicketMap::where('ticket_event_id', $other->id)->sole();
        $this->assertSame('1001', $copy->seats()->where('label', 'M1')->value('captive_title'));
    }
}
