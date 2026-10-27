<?php

namespace Tests\Feature\Tickets;

use App\Exceptions\ConfirmationRequired;
use App\Exceptions\TicketException;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketLot;
use App\Models\Ticket\TicketOrder;
use App\Models\Ticket\TicketPayment;
use App\Services\Ticket\TicketBuyer;
use App\Services\Ticket\TicketInventory;
use App\Services\Ticket\TicketOrderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTickets;
use Tests\Concerns\CreatesTicketSchema;
use Tests\TestCase;

/**
 * Ingressos, regras do pedido (TicketOrderService e TicketInventory): lote
 * vigente por quantidade e por data, reserva temporária, sócio só para si,
 * não sócio sem conta, um por CPF, pagamento, expiração, cancelamento.
 */
class TicketOrderFlowTest extends TestCase
{
    use CreatesTicketSchema;
    use BuildsTickets;

    private const CPF_ANA = '39053344705';

    private TicketOrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->createTicketSchema();
        $this->fakeIntegrations();
        $this->orders = app(TicketOrderService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function guest(): TicketBuyer
    {
        return TicketBuyer::guest(self::GUEST_CPFS[0], 'Carla Visitante', 'carla@visita.local', '61999990000');
    }

    private function refused(callable $call, string $reason): TicketException
    {
        try {
            $call();
        } catch (TicketException $e) {
            $this->assertSame($reason, $e->reason, $e->getMessage());

            return $e;
        }

        $this->fail("Esperava a recusa {$reason}.");
    }

    public function test_socio_compra_para_si_com_preco_de_socio(): void
    {
        $event = $this->ticketEvent();
        $type = $this->ticketType($event);
        $this->ticketLot($type);
        $ana = $this->member(self::CPF_ANA, 'Ana Souza');

        $order = $this->orders->create($event, $this->orders->buyerFor($ana), [['type_id' => $type->id]]);

        $this->assertSame(TicketOrder::STATUS_PENDING_PAYMENT, $order->status);
        $this->assertSame(TicketOrder::BUYER_MEMBER, $order->buyer_kind);
        $this->assertNull($order->access_token);
        $ticket = $order->tickets->sole();
        // Sem CPF no item, o ingresso é do próprio sócio.
        $this->assertSame(self::CPF_ANA, $ticket->holder_cpf);
        $this->assertSame('Ana Souza', $ticket->holder_name);
        $this->assertSame(TicketLot::PRICE_MEMBER, $ticket->price_kind);
        $this->assertSame(50.0, (float) $ticket->amount);
        $this->assertMatchesRegularExpression(Ticket::CODE_PATTERN, $ticket->code);
    }

    public function test_socio_compra_para_todos_do_titulo_com_o_nome_do_cadastro(): void
    {
        $event = $this->ticketEvent(['max_per_order' => 1]);
        $type = $this->ticketType($event);
        $this->ticketLot($type);
        // Quem compra é o dependente: qualquer um do título compra para todos.
        $this->dependent(self::GUEST_CPFS[1], 'Bia Souza');
        $this->dependent(self::GUEST_CPFS[2], 'Caio Souza');
        $this->member(self::CPF_ANA, 'Ana Souza');
        // Conta do site do dependente; o nome que vale é o do MultiClubes.
        $caio = $this->member('86288366757', 'Caio Conta');
        $this->dependent('86288366757', 'Caio Souza Lima');

        $order = $this->orders->create($event, $this->orders->buyerFor($caio), [
            ['type_id' => $type->id],
            ['type_id' => $type->id, 'holder_cpf' => self::CPF_ANA, 'holder_name' => 'Nome Digitado'],
            ['type_id' => $type->id, 'holder_cpf' => self::GUEST_CPFS[1]],
        ]);

        // max_per_order é do não sócio: o título compra para todos.
        $this->assertSame(['Caio Souza Lima', 'Ana Souza', 'Bia Souza'], $order->tickets->pluck('holder_name')->all());
        $this->assertSame(150.0, (float) $order->total);
        $this->assertSame([TicketLot::PRICE_MEMBER], $order->tickets->pluck('price_kind')->unique()->values()->all());
    }

    public function test_socio_nao_compra_para_quem_e_de_fora_do_titulo(): void
    {
        $event = $this->ticketEvent();
        $type = $this->ticketType($event);
        $this->ticketLot($type);
        $buyer = $this->orders->buyerFor($this->member(self::CPF_ANA, 'Ana Souza'));
        $this->dependent(self::GUEST_CPFS[3], 'Outro Título', '2002');

        $this->refused(fn () => $this->orders->create($event, $buyer, [['type_id' => $type->id, 'holder_cpf' => self::GUEST_CPFS[3]]]), TicketException::HOLDER_NOT_IN_TITLE);
        $this->refused(fn () => $this->orders->create($event, $buyer, [['type_id' => $type->id], ['type_id' => $type->id]]), TicketException::INVALID_HOLDER);
    }

    public function test_socio_sem_cadastro_ativo_no_multiclubes_e_recusado(): void
    {
        $ana = $this->member(self::CPF_ANA, 'Ana Souza');
        $this->mcProfiles = [];

        $this->refused(fn () => $this->orders->buyerFor($ana), TicketException::MEMBER_NOT_FOUND);
    }

    public function test_nao_socio_compra_para_varias_pessoas_com_preco_de_nao_socio(): void
    {
        $event = $this->ticketEvent();
        $type = $this->ticketType($event);
        $this->ticketLot($type);

        $order = $this->orders->create($event, $this->guest(), [$this->holder($type->id, 0), $this->holder($type->id, 1)]);

        $this->assertSame(TicketOrder::BUYER_GUEST, $order->buyer_kind);
        $this->assertSame(160.0, (float) $order->total);
        $this->assertSame(40, strlen($order->access_token));
        $this->assertTrue($order->tokenMatches($order->access_token));
        $this->assertFalse($order->tokenMatches('outro'));
        $this->assertSame([TicketLot::PRICE_NON_MEMBER], $order->tickets->pluck('price_kind')->unique()->values()->all());
    }

    public function test_cpf_de_socio_nao_entra_no_pedido_sem_login(): void
    {
        $event = $this->ticketEvent();
        $type = $this->ticketType($event);
        $this->ticketLot($type);
        $this->member(self::CPF_ANA, 'Ana Souza');

        $this->refused(fn () => $this->orders->create($event, $this->guest(), [
            $this->holder($type->id, 0),
            ['type_id' => $type->id, 'holder_name' => 'Ana Souza', 'holder_cpf' => self::CPF_ANA],
        ]), TicketException::HOLDER_IS_MEMBER);
    }

    public function test_titular_invalido_ou_repetido_e_recusado(): void
    {
        $event = $this->ticketEvent();
        $type = $this->ticketType($event);
        $this->ticketLot($type);

        $this->refused(fn () => $this->orders->create($event, $this->guest(), [['type_id' => $type->id, 'holder_name' => 'Fulano', 'holder_cpf' => '12345678900']]), TicketException::INVALID_HOLDER);
        $this->refused(fn () => $this->orders->create($event, $this->guest(), [$this->holder($type->id, 0), $this->holder($type->id, 0, 'De novo')]), TicketException::INVALID_HOLDER);
    }

    public function test_um_ingresso_ativo_por_cpf_no_evento(): void
    {
        $event = $this->ticketEvent();
        $type = $this->ticketType($event);
        $this->ticketLot($type);
        $this->orders->create($event, $this->guest(), [$this->holder($type->id, 0)]);

        $this->refused(fn () => $this->orders->create($event, $this->guest(), [$this->holder($type->id, 0)]), TicketException::ALREADY_HAS_TICKET);
    }

    public function test_limite_por_pedido_do_nao_socio(): void
    {
        $event = $this->ticketEvent(['max_per_order' => 2]);
        $type = $this->ticketType($event);
        $this->ticketLot($type);

        $this->refused(fn () => $this->orders->create($event, $this->guest(), [$this->holder($type->id, 0), $this->holder($type->id, 1), $this->holder($type->id, 2)]), TicketException::LIMIT_PER_ORDER);
    }

    public function test_lote_so_para_socios_recusa_nao_socio(): void
    {
        $event = $this->ticketEvent();
        $type = $this->ticketType($event);
        $this->ticketLot($type, ['price_non_member' => null]);

        $this->refused(fn () => $this->orders->create($event, $this->guest(), [$this->holder($type->id, 0)]), TicketException::MEMBERS_ONLY);
    }

    public function test_fora_da_janela_de_vendas_o_site_e_recusado(): void
    {
        $event = $this->ticketEvent(['sales_starts_at' => now()->addDay(), 'sales_ends_at' => now()->addDays(5)]);
        $type = $this->ticketType($event);
        $this->ticketLot($type);

        $this->refused(fn () => $this->orders->create($event, $this->guest(), [$this->holder($type->id, 0)]), TicketException::SALES_CLOSED);
    }

    public function test_lote_vira_quando_esgota_e_o_pedido_atravessa_a_virada(): void
    {
        $event = $this->ticketEvent();
        $type = $this->ticketType($event);
        $first = $this->ticketLot($type, ['quantity' => 1, 'price_non_member' => 80]);
        $second = $this->ticketLot($type, ['quantity' => 5, 'price_non_member' => 100]);

        $order = $this->orders->create($event, $this->guest(), [$this->holder($type->id, 0), $this->holder($type->id, 1)]);

        $this->assertSame([$first->id, $second->id], $order->tickets->pluck('ticket_lot_id')->all());
        $this->assertSame(180.0, (float) $order->total);

        $inventory = app(TicketInventory::class);
        $occupied = $inventory->occupiedByLot([$first->id, $second->id]);
        $this->assertSame($second->id, $inventory->currentLot($type->fresh('lots'), $occupied)->id);
    }

    public function test_lote_vira_pela_data_e_o_proximo_espera_a_janela(): void
    {
        $event = $this->ticketEvent();
        $type = $this->ticketType($event);
        $this->ticketLot($type, ['ends_at' => now()->subMinute()]);
        $future = $this->ticketLot($type, ['starts_at' => now()->addDays(2)]);

        $this->refused(fn () => $this->orders->create($event, $this->guest(), [$this->holder($type->id, 0)]), TicketException::SOLD_OUT);

        $inventory = app(TicketInventory::class);
        $this->assertSame($future->id, $inventory->nextLot($type->fresh('lots'), [])->id);

        Carbon::setTestNow(now()->addDays(3));
        $order = $this->orders->create($event->fresh(), $this->guest(), [$this->holder($type->id, 0)]);
        $this->assertSame($future->id, $order->tickets->sole()->ticket_lot_id);
    }

    public function test_pendente_segura_o_estoque_so_dentro_do_prazo(): void
    {
        config(['tickets.hold_minutes' => 10]);
        $event = $this->ticketEvent();
        $type = $this->ticketType($event);
        $this->ticketLot($type, ['quantity' => 1]);
        $this->orders->create($event, $this->guest(), [$this->holder($type->id, 0)]);

        $this->refused(fn () => $this->orders->create($event, $this->guest(), [$this->holder($type->id, 1)]), TicketException::SOLD_OUT);

        // Venceu o prazo: o lugar volta à venda mesmo antes do comando de expiração.
        Carbon::setTestNow(now()->addMinutes(11));
        $other = $this->orders->create($event->fresh(), $this->guest(), [$this->holder($type->id, 1)]);
        $this->assertSame(TicketOrder::STATUS_PENDING_PAYMENT, $other->status);
    }

    public function test_lotacao_do_evento_soma_os_tipos(): void
    {
        $event = $this->ticketEvent(['capacity' => 1]);
        $pista = $this->ticketType($event, 'Pista');
        $mesa = $this->ticketType($event, 'Mesa');
        $this->ticketLot($pista);
        $this->ticketLot($mesa);
        $this->orders->create($event, $this->guest(), [$this->holder($pista->id, 0)]);

        $this->refused(fn () => $this->orders->create($event, $this->guest(), [$this->holder($mesa->id, 1)]), TicketException::CAPACITY_FULL);
    }

    public function test_pagamento_confirma_e_o_mesmo_tid_devolve_o_ja_gravado(): void
    {
        $event = $this->ticketEvent();
        $type = $this->ticketType($event);
        $this->ticketLot($type);
        $order = $this->orders->create($event, $this->guest(), [$this->holder($type->id, 0)]);
        $data = ['payment_method' => 'credit', 'paid_amount' => 80, 'payment_integration_id' => 'TID-1'];

        $first = $this->orders->pay($order, $data);
        $again = $this->orders->pay($order, $data);

        $this->assertTrue($first['created']);
        $this->assertFalse($again['created']);
        $this->assertSame($first['payment']->id, $again['payment']->id);
        $this->assertSame(TicketOrder::STATUS_PAID, $order->fresh()->status);
        $this->assertSame(Ticket::STATUS_VALID, $order->tickets()->first()->status);
        $this->assertSame(1, TicketPayment::count());

        $other = $this->orders->create($event, $this->guest(), [$this->holder($type->id, 1)]);
        $this->refused(fn () => $this->orders->pay($other, $data + ['paid_amount' => 80]), TicketException::INVALID_STATUS);
    }

    public function test_valor_diferente_do_total_e_recusado(): void
    {
        $event = $this->ticketEvent();
        $type = $this->ticketType($event);
        $this->ticketLot($type);
        $order = $this->orders->create($event, $this->guest(), [$this->holder($type->id, 0)]);

        $e = $this->refused(fn () => $this->orders->pay($order, ['payment_method' => 'credit', 'paid_amount' => 50, 'payment_integration_id' => 'TID-X']), TicketException::PAYMENT_MISMATCH);
        $this->assertSame(80.0, $e->context['expected']);
    }

    public function test_pagamento_depois_do_prazo_vale_se_ainda_houver_estoque(): void
    {
        $event = $this->ticketEvent();
        $type = $this->ticketType($event);
        $this->ticketLot($type, ['quantity' => 5]);
        $order = $this->orders->create($event, $this->guest(), [$this->holder($type->id, 0)]);

        Carbon::setTestNow(now()->addMinutes(30));
        $this->assertSame(1, $this->orders->expireOverdue());
        $this->assertSame(Ticket::STATUS_EXPIRED, $order->tickets()->first()->status);

        $this->orders->pay($order->fresh(), ['payment_method' => 'credit', 'paid_amount' => 80, 'payment_integration_id' => 'TID-LATE']);

        $this->assertSame(TicketOrder::STATUS_PAID, $order->fresh()->status);
        $this->assertSame(Ticket::STATUS_VALID, $order->tickets()->first()->status);
    }

    public function test_pagamento_depois_do_prazo_sem_estoque_e_hold_expired(): void
    {
        $event = $this->ticketEvent();
        $type = $this->ticketType($event);
        $this->ticketLot($type, ['quantity' => 1]);
        $late = $this->orders->create($event, $this->guest(), [$this->holder($type->id, 0)]);

        Carbon::setTestNow(now()->addMinutes(30));
        $this->orders->create($event->fresh(), $this->guest(), [$this->holder($type->id, 1)]);

        $this->refused(fn () => $this->orders->pay($late, ['payment_method' => 'credit', 'paid_amount' => 80, 'payment_integration_id' => 'TID-LATE']), TicketException::HOLD_EXPIRED);
        $this->assertSame(0, TicketPayment::count());
    }

    public function test_desistencia_so_antes_de_pagar(): void
    {
        $event = $this->ticketEvent();
        $type = $this->ticketType($event);
        $this->ticketLot($type);
        $order = $this->orders->create($event, $this->guest(), [$this->holder($type->id, 0)]);

        $this->orders->withdraw($order);
        $this->assertSame(TicketOrder::STATUS_WITHDRAWN, $order->fresh()->status);
        $this->assertSame(Ticket::STATUS_EXPIRED, $order->tickets()->first()->status);

        $paid = $this->orders->create($event, $this->guest(), [$this->holder($type->id, 0)]);
        $this->orders->pay($paid, ['payment_method' => 'credit', 'paid_amount' => 80, 'payment_integration_id' => 'TID-2']);
        $this->refused(fn () => $this->orders->withdraw($paid->fresh()), TicketException::PAID_CONTACT_ORGANIZER);
    }

    public function test_preco_zero_ja_nasce_pago(): void
    {
        $event = $this->ticketEvent();
        $type = $this->ticketType($event, 'Infantil');
        $this->ticketLot($type, ['price_member' => 0, 'price_non_member' => 0]);

        $order = $this->orders->create($event, $this->guest(), [$this->holder($type->id, 0)]);

        $this->assertSame(TicketOrder::STATUS_PAID, $order->status);
        $this->assertSame(Ticket::STATUS_VALID, $order->tickets->sole()->status);
    }

    public function test_balcao_vende_fora_da_janela_com_aviso_e_cortesia_sai_paga(): void
    {
        $event = $this->ticketEvent(['sales_ends_at' => now()->subMinute()]);
        $type = $this->ticketType($event);
        $this->ticketLot($type, ['quantity' => 0]);
        $user = $this->makeUser('Secretaria');

        try {
            $this->orders->create($event, $this->guest(), [$this->holder($type->id, 0)], $user);
            $this->fail('Esperava aviso.');
        } catch (ConfirmationRequired $e) {
            $this->assertCount(2, $e->warnings);
        }

        $order = $this->orders->create($event, $this->guest(), [$this->holder($type->id, 0)], $user, force: true, courtesy: true);

        $this->assertSame(TicketOrder::STATUS_PAID, $order->status);
        $this->assertTrue($order->courtesy);
        $this->assertSame(TicketOrder::ORIGIN_COUNTER, $order->origin);
        $this->assertSame(0.0, (float) $order->total);
    }

    public function test_baixa_manual_e_cancelamento_com_estorno(): void
    {
        $event = $this->ticketEvent();
        $type = $this->ticketType($event);
        $this->ticketLot($type);
        $user = $this->makeUser('Secretaria');

        $manual = $this->orders->create($event, $this->guest(), [$this->holder($type->id, 0)]);
        $payment = $this->orders->recordManual($manual, ['payment_method' => 'manual_pix'], $user);
        $this->assertTrue($payment->is_manual);
        $this->assertSame(TicketOrder::STATUS_PAID, $manual->fresh()->status);

        $online = $this->orders->create($event, $this->guest(), [$this->holder($type->id, 1), $this->holder($type->id, 2)]);
        $this->orders->pay($online, ['payment_method' => 'credit', 'paid_amount' => 160, 'payment_integration_id' => 'TID-9']);
        [$one, $two] = $online->tickets()->get()->all();

        $this->assertSame(80.0, $this->orders->cancelTicket($one, $user, 'Pediu para cancelar', refund: true));
        $this->assertSame([['tid' => 'TID-9', 'amount' => 80.0]], $this->redeRefunds);
        $this->assertSame(TicketOrder::STATUS_PAID, $online->fresh()->status);
        $this->assertSame(TicketPayment::STATUS_PARTIALLY_REFUNDED, $online->payment()->first()->status);

        $this->orders->cancelTicket($two, $user, 'Evento lotado');
        $this->assertSame(TicketOrder::STATUS_CANCELLED, $online->fresh()->status);
        $this->assertSame(Ticket::STATUS_CANCELLED, $two->fresh()->status);

        // Baixa manual não tem estorno pelo Lara.
        $this->expectException(\InvalidArgumentException::class);
        $this->orders->cancelTicket($manual->tickets()->first(), $user, 'x', refund: true);
    }

    public function test_comando_expira_os_pendentes_vencidos(): void
    {
        $event = $this->ticketEvent();
        $type = $this->ticketType($event);
        $this->ticketLot($type);
        $order = $this->orders->create($event, $this->guest(), [$this->holder($type->id, 0)]);

        Carbon::setTestNow(now()->addMinutes(30));
        $this->artisan('ingressos:expirar-pedidos')->assertSuccessful();

        $this->assertSame(TicketOrder::STATUS_EXPIRED, $order->fresh()->status);
    }

    public function test_codigo_digitado_e_normalizado(): void
    {
        $this->assertSame('ING-AB23CD45', Ticket::normalizeCode('ing ab23-cd45'));
        $this->assertSame('ING-AB23CD45', Ticket::normalizeCode('AB23CD45'));
    }
}
