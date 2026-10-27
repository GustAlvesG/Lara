<?php

namespace Tests\Feature\Tickets;

use App\Exceptions\ConfirmationRequired;
use App\Exceptions\TicketException;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketCoupon;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketOrder;
use App\Models\Ticket\TicketPayment;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Services\Ticket\TicketBuyer;
use App\Services\Ticket\TicketOrderService;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTickets;
use Tests\Concerns\CreatesTicketSchema;
use Tests\TestCase;

/**
 * Ingressos, F5: cupom de desconto (porcentagem e valor fixo), emissão pelo
 * Lara com pagamento por fora e cortesia, cancelamento em lote com um estorno
 * só e troca de titular.
 */
class TicketCouponAndCounterTest extends TestCase
{
    use CreatesTicketSchema;
    use BuildsTickets;

    private const CPF_ANA = '39053344705';

    private TicketOrderService $orders;
    private TicketEvent $event;
    private TicketType $pista;
    private TicketType $mesa;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->createTicketSchema();
        $this->fakeIntegrations();
        $this->orders = app(TicketOrderService::class);

        $this->event = $this->ticketEvent();
        $this->pista = $this->ticketType($this->event, 'Pista');
        $this->mesa = $this->ticketType($this->event, 'Mesa');
        $this->ticketLot($this->pista, ['price_member' => 50, 'price_non_member' => 80]);
        $this->ticketLot($this->mesa, ['price_member' => 150, 'price_non_member' => 200]);
        $this->staff = $this->makeUser('Secretaria');
    }

    private function guest(): TicketBuyer
    {
        return TicketBuyer::guest(self::GUEST_CPFS[0], 'Carla Visitante', 'carla@visita.local', null);
    }

    private function coupon(array $attributes = []): TicketCoupon
    {
        return $this->event->coupons()->create(array_merge(['code' => 'festa10', 'kind' => TicketCoupon::KIND_PERCENT, 'value' => 10], $attributes));
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

    public function test_cupom_percentual_abate_de_cada_ingresso_e_codigo_ignora_caixa(): void
    {
        $this->coupon();

        $order = $this->orders->create($this->event, $this->guest(), [$this->holder($this->pista->id, 0), $this->holder($this->mesa->id, 1)], couponCode: ' FESTA10 ');

        $this->assertSame('FESTA10', $order->coupon_code);
        $this->assertSame(28.0, (float) $order->discount_total);
        $this->assertSame(252.0, (float) $order->total);
        $pista = $order->tickets->firstWhere('ticket_type_id', $this->pista->id);
        $this->assertSame(80.0, (float) $pista->list_price);
        $this->assertSame(8.0, (float) $pista->discount_amount);
        $this->assertSame(72.0, (float) $pista->amount);
    }

    public function test_cupom_de_valor_fixo_so_no_tipo_escolhido_e_nunca_abaixo_de_zero(): void
    {
        $this->coupon(['code' => 'MESA300', 'kind' => TicketCoupon::KIND_FIXED, 'value' => 300, 'ticket_type_ids' => [$this->mesa->id]]);

        $order = $this->orders->create($this->event, $this->guest(), [$this->holder($this->pista->id, 0), $this->holder($this->mesa->id, 1)], couponCode: 'MESA300');

        $this->assertSame(200.0, (float) $order->discount_total);
        $this->assertSame(80.0, (float) $order->total);
        $this->assertSame(0.0, (float) $order->tickets->firstWhere('ticket_type_id', $this->mesa->id)->amount);
    }

    public function test_cupom_cem_por_cento_deixa_o_pedido_pago(): void
    {
        $this->coupon(['code' => 'CONVITE', 'value' => 100]);

        $order = $this->orders->create($this->event, $this->guest(), [$this->holder($this->pista->id, 0)], couponCode: 'CONVITE');

        $this->assertSame(TicketOrder::STATUS_PAID, $order->status);
        $this->assertSame(Ticket::STATUS_VALID, $order->tickets->sole()->status);
    }

    public function test_cupom_invalido_inativo_fora_do_prazo_ou_de_outro_publico_e_recusado(): void
    {
        $this->refused(fn () => $this->orders->create($this->event, $this->guest(), [$this->holder($this->pista->id, 0)], couponCode: 'NAOEXISTE'), TicketException::COUPON_INVALID);

        $this->coupon(['code' => 'OFF', 'active' => false]);
        $this->refused(fn () => $this->orders->create($this->event, $this->guest(), [$this->holder($this->pista->id, 0)], couponCode: 'OFF'), TicketException::COUPON_INVALID);

        $this->coupon(['code' => 'VELHO', 'ends_at' => now()->subDay()]);
        $this->refused(fn () => $this->orders->create($this->event, $this->guest(), [$this->holder($this->pista->id, 0)], couponCode: 'VELHO'), TicketException::COUPON_INVALID);

        $this->coupon(['code' => 'SOCIO', 'audience' => 'member']);
        $this->refused(fn () => $this->orders->create($this->event, $this->guest(), [$this->holder($this->pista->id, 0)], couponCode: 'SOCIO'), TicketException::COUPON_NOT_APPLICABLE);

        // E o sócio usa o cupom de sócio.
        $ana = $this->member(self::CPF_ANA, 'Ana Souza');
        $order = $this->orders->create($this->event, $this->orders->buyerFor($ana), [['type_id' => $this->pista->id]], couponCode: 'socio');
        $this->assertSame(45.0, (float) $order->total);
    }

    public function test_limite_de_usos_conta_ingressos_e_o_pendente_vencido_devolve_o_uso(): void
    {
        $this->coupon(['code' => 'DOIS', 'max_uses' => 2]);
        $this->orders->create($this->event, $this->guest(), [$this->holder($this->pista->id, 0)], couponCode: 'DOIS');

        $e = $this->refused(fn () => $this->orders->create($this->event, $this->guest(), [$this->holder($this->pista->id, 1), $this->holder($this->pista->id, 2)], couponCode: 'DOIS'), TicketException::COUPON_EXHAUSTED);
        $this->assertSame(1, $e->context['remaining']);

        $this->travel(30)->minutes();
        $order = $this->orders->create($this->event->fresh(), $this->guest(), [$this->holder($this->pista->id, 1), $this->holder($this->pista->id, 2)], couponCode: 'DOIS');
        $this->assertSame(16.0, (float) $order->discount_total);
    }

    public function test_conferencia_do_cupom_pelo_site(): void
    {
        $this->coupon(['code' => 'SOCIO', 'audience' => 'member']);

        $this->assertSame('SOCIO', $this->orders->checkCoupon($this->event, 'socio', 'member')->code);
        $this->refused(fn () => $this->orders->checkCoupon($this->event, 'socio', 'non_member'), TicketException::COUPON_NOT_APPLICABLE);
        $this->refused(fn () => $this->orders->checkCoupon($this->event, 'xyz', 'member'), TicketException::COUPON_INVALID);
    }

    public function test_emissao_pelo_lara_com_pagamento_por_fora_e_cupom(): void
    {
        $this->coupon();

        $order = $this->orders->issueAtCounter($this->event, $this->guest(), [$this->holder($this->pista->id, 0)], $this->staff, TicketOrderService::ISSUE_EXTERNAL, [
            'payment_method' => 'manual_cash', 'manual_notes' => 'Pago na secretaria',
        ], 'FESTA10');

        $this->assertSame(TicketOrder::STATUS_PAID, $order->status);
        $this->assertSame(TicketOrder::ORIGIN_COUNTER, $order->origin);
        $this->assertSame(72.0, (float) $order->total);
        $payment = TicketPayment::sole();
        $this->assertTrue($payment->is_manual);
        $this->assertSame(72.0, (float) $payment->paid_amount);
        $this->assertSame($this->staff->id, $payment->recorded_by);
    }

    public function test_emissao_pelo_lara_pede_forma_de_pagamento(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->orders->issueAtCounter($this->event, $this->guest(), [$this->holder($this->pista->id, 0)], $this->staff, TicketOrderService::ISSUE_EXTERNAL, []);
    }

    public function test_cortesia_sai_zerada_paga_e_sem_pagamento_gravado(): void
    {
        $order = $this->orders->issueAtCounter($this->event, $this->guest(), [$this->holder($this->mesa->id, 0)], $this->staff, TicketOrderService::ISSUE_COURTESY, [], 'FESTA10');

        $this->assertSame(TicketOrder::STATUS_PAID, $order->status);
        $this->assertTrue($order->courtesy);
        $this->assertNull($order->coupon_code);
        $this->assertSame(0.0, (float) $order->total);
        $this->assertSame(200.0, (float) $order->tickets->sole()->list_price);
        $this->assertSame(0, TicketPayment::count());
    }

    public function test_emissao_pelo_lara_fora_do_periodo_pede_confirmacao(): void
    {
        $this->event->update(['sales_ends_at' => now()->subMinute()]);

        try {
            $this->orders->issueAtCounter($this->event->fresh(), $this->guest(), [$this->holder($this->pista->id, 0)], $this->staff, TicketOrderService::ISSUE_COURTESY);
            $this->fail('Esperava aviso.');
        } catch (ConfirmationRequired) {
            $this->assertSame(0, TicketOrder::count());
        }

        $this->orders->issueAtCounter($this->event->fresh(), $this->guest(), [$this->holder($this->pista->id, 0)], $this->staff, TicketOrderService::ISSUE_COURTESY, force: true);
        $this->assertSame(1, TicketOrder::count());
    }

    public function test_cancelamento_em_lote_faz_um_estorno_so_dividido_pelos_ingressos(): void
    {
        $this->coupon();
        $order = $this->orders->create($this->event, $this->guest(), [$this->holder($this->pista->id, 0), $this->holder($this->mesa->id, 1)], couponCode: 'FESTA10');
        $this->orders->pay($order, ['payment_method' => 'credit', 'paid_amount' => 252, 'payment_integration_id' => 'TID-L']);

        $refunded = $this->orders->cancelTickets($order->fresh(), null, $this->staff, 'Evento adiado', refund: true);

        $this->assertSame(252.0, $refunded);
        $this->assertSame([['tid' => 'TID-L', 'amount' => 252.0]], $this->redeRefunds);
        $this->assertSame(252.0, (float) $order->tickets()->sum('refunded_amount'));
        $this->assertSame(TicketOrder::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(TicketPayment::STATUS_REFUNDED, $order->payment()->first()->status);
    }

    public function test_troca_de_titular_respeita_um_por_cpf_e_avisa_no_ingresso_de_socio(): void
    {
        $order = $this->orders->create($this->event, $this->guest(), [$this->holder($this->pista->id, 0), $this->holder($this->pista->id, 1)]);
        [$first, $second] = $order->tickets->all();

        $this->refused(fn () => $this->orders->changeHolder($first, 'Outro Nome', self::GUEST_CPFS[1], $this->staff), TicketException::ALREADY_HAS_TICKET);

        $changed = $this->orders->changeHolder($first, 'Dora Nova', self::GUEST_CPFS[3], $this->staff);
        $this->assertSame(self::GUEST_CPFS[3], $changed->holder_cpf);
        $this->assertSame($first->code, $changed->code);

        $ana = $this->member(self::CPF_ANA, 'Ana Souza');
        $member = $this->orders->create($this->event, $this->orders->buyerFor($ana), [['type_id' => $this->pista->id]]);
        $this->expectException(ConfirmationRequired::class);
        $this->orders->changeHolder($member->tickets->sole(), 'Outra Pessoa', self::GUEST_CPFS[2], $this->staff);
    }
}
