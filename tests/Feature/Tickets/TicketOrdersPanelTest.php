<?php

namespace Tests\Feature\Tickets;

use App\Authorization\Permissions as P;
use App\Models\Ticket\TicketCoupon;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketOrder;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Services\Ticket\TicketBuyer;
use App\Services\Ticket\TicketOrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTickets;
use Tests\Concerns\CreatesTicketSchema;
use Tests\TestCase;

/**
 * Ingressos, F5, no painel: cupons, pedidos, emissão pelo Lara (pagamento
 * por fora e cortesia), baixa manual, cancelamento e as permissões de cada um.
 */
class TicketOrdersPanelTest extends TestCase
{
    use CreatesTicketSchema;
    use BuildsTickets;

    private const CPF_ANA = '39053344705';

    private User $staff;
    private TicketEvent $event;
    private TicketType $pista;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->createTicketSchema();
        $this->fakeIntegrations();

        $this->staff = $this->makeUser('Secretaria');
        $this->joinSector($this->staff, 'TI');

        $this->event = $this->ticketEvent();
        $this->pista = $this->ticketType($this->event, 'Pista');
        $this->ticketLot($this->pista);
    }

    /** Usuário com só estas permissões, dadas individualmente. */
    private function userWith(array $permissions): User
    {
        $user = $this->makeUser('Pessoa ' . implode(' ', $permissions));
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

    public function test_quem_so_vende_abre_o_evento_e_os_pedidos_mas_nao_edita(): void
    {
        $vendas = $this->userWith([P::INGRESSOS_VENDAS]);

        $this->actingAs($vendas)->get(route('tickets.index'))->assertOk()->assertDontSee('Novo evento');
        $this->actingAs($vendas)->get(route('tickets.show', $this->event))->assertOk()->assertSee('Pedidos')->assertDontSee('Criar tipo');
        $this->actingAs($vendas)->get(route('tickets.orders.index', $this->event))->assertOk();
        $this->actingAs($vendas)->get(route('tickets.edit', $this->event))->assertForbidden();
        $this->actingAs($vendas)->get(route('tickets.coupons.index', $this->event))->assertForbidden();
    }

    public function test_cria_edita_e_nao_apaga_cupom_usado(): void
    {
        $this->actingAs($this->staff)->post(route('tickets.coupons.store', $this->event), [
            'code' => 'verão!', 'kind' => TicketCoupon::KIND_PERCENT, 'value' => 20, 'audience' => 'all', 'ticket_type_ids' => [$this->pista->id],
        ])->assertSessionHasErrors('code');

        $this->actingAs($this->staff)->post(route('tickets.coupons.store', $this->event), [
            'code' => 'verao20', 'kind' => TicketCoupon::KIND_PERCENT, 'value' => 120, 'audience' => 'all',
        ])->assertSessionHasErrors('value');

        $this->actingAs($this->staff)->post(route('tickets.coupons.store', $this->event), [
            'code' => 'verao20', 'kind' => TicketCoupon::KIND_PERCENT, 'value' => 20, 'audience' => 'all', 'ticket_type_ids' => [$this->pista->id], 'max_uses' => 50,
        ])->assertRedirect();

        $coupon = TicketCoupon::sole();
        $this->assertSame('VERAO20', $coupon->code);
        $this->assertSame([$this->pista->id], $coupon->ticket_type_ids);

        $this->actingAs($this->staff)->get(route('tickets.coupons.index', $this->event))->assertOk()->assertSee('VERAO20')->assertSee('20%');

        app(TicketOrderService::class)->create($this->event, TicketBuyer::guest(self::GUEST_CPFS[0], 'Carla', 'c@x.local', null), [$this->holder($this->pista->id, 0)], couponCode: 'VERAO20');

        $this->actingAs($this->staff)->delete(route('tickets.coupons.destroy', [$this->event, $coupon]))->assertSessionHasErrors('coupon');
        $this->actingAs($this->staff)->put(route('tickets.coupons.update', [$this->event, $coupon]), [
            'code' => 'VERAO20', 'kind' => TicketCoupon::KIND_FIXED, 'value' => 15, 'audience' => 'all',
        ])->assertRedirect();
        $this->assertFalse($coupon->fresh()->active);
        $this->assertNull($coupon->fresh()->ticket_type_ids);
    }

    public function test_emite_para_nao_socio_com_pagamento_por_fora(): void
    {
        $this->actingAs($this->staff)->post(route('tickets.issue.store', $this->event), [
            'mode' => TicketOrderService::ISSUE_EXTERNAL,
            'buyer_kind' => TicketOrder::BUYER_GUEST,
            'buyer_name' => 'Carla Visitante',
            'buyer_cpf' => '529.982.247-25',
            'buyer_email' => 'carla@visita.local',
            'holders' => [$this->holder($this->pista->id, 0), $this->holder($this->pista->id, 1)],
            'payment_method' => 'manual_pix',
            'paid_at' => now()->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $order = TicketOrder::sole();
        $this->assertSame(TicketOrder::STATUS_PAID, $order->status);
        $this->assertSame(160.0, (float) $order->total);
        $this->assertTrue($order->payment->is_manual);

        $this->actingAs($this->staff)->get(route('tickets.orders.show', $order))->assertOk()->assertSee('Baixa manual')->assertSee('Convidado 1');
        $this->actingAs($this->staff)->get(route('tickets.orders.index', ['event' => $this->event, 'q' => $order->tickets->first()->code]))->assertOk()->assertSee('#' . $order->id);
        $this->actingAs($this->staff)->get(route('tickets.issue.create', $this->event))->assertOk()->assertSee('Cortesia');
    }

    public function test_emite_cortesia_para_socio_e_dependente_do_titulo(): void
    {
        $this->member(self::CPF_ANA, 'Ana Souza');
        $this->dependent(self::GUEST_CPFS[1], 'Bia Souza');

        $this->actingAs($this->staff)->post(route('tickets.issue.store', $this->event), [
            'mode' => TicketOrderService::ISSUE_COURTESY,
            'buyer_kind' => TicketOrder::BUYER_MEMBER,
            'member_cpf' => self::CPF_ANA,
            'holders' => [
                ['type_id' => $this->pista->id, 'holder_cpf' => ''],
                ['type_id' => $this->pista->id, 'holder_cpf' => self::GUEST_CPFS[1]],
            ],
        ])->assertRedirect();

        $order = TicketOrder::sole();
        $this->assertTrue($order->courtesy);
        $this->assertSame(TicketOrder::BUYER_MEMBER, $order->buyer_kind);
        $this->assertSame(['Ana Souza', 'Bia Souza'], $order->tickets->pluck('holder_name')->all());
    }

    public function test_pessoa_de_fora_do_titulo_no_balcao_pede_confirmacao(): void
    {
        $this->member(self::CPF_ANA, 'Ana Souza');
        $data = [
            'mode' => TicketOrderService::ISSUE_COURTESY,
            'buyer_kind' => TicketOrder::BUYER_MEMBER,
            'member_cpf' => self::CPF_ANA,
            'holders' => [['type_id' => $this->pista->id, 'holder_cpf' => self::GUEST_CPFS[2], 'holder_name' => 'Amiga da Ana']],
        ];

        $this->actingAs($this->staff)->post(route('tickets.issue.store', $this->event), $data)->assertSessionHas('confirmation');
        $this->assertSame(0, TicketOrder::count());

        $this->actingAs($this->staff)->post(route('tickets.issue.store', $this->event), $data + ['force' => 1])->assertRedirect();
        $this->assertSame('Amiga da Ana', TicketOrder::sole()->tickets->sole()->holder_name);
    }

    public function test_cpf_que_nao_e_de_socio_nao_emite_como_socio(): void
    {
        $this->actingAs($this->staff)->post(route('tickets.issue.store', $this->event), [
            'mode' => TicketOrderService::ISSUE_COURTESY,
            'buyer_kind' => TicketOrder::BUYER_MEMBER,
            'member_cpf' => self::GUEST_CPFS[0],
            'holders' => [['type_id' => $this->pista->id]],
        ])->assertSessionHasErrors('member_cpf');
    }

    public function test_cortesia_pede_permissao_propria(): void
    {
        $vendasBaixa = $this->userWith([P::INGRESSOS_VENDAS, P::INGRESSOS_PAGAMENTOS_BAIXA_MANUAL]);

        $this->actingAs($vendasBaixa)->post(route('tickets.issue.store', $this->event), [
            'mode' => TicketOrderService::ISSUE_COURTESY,
            'buyer_kind' => TicketOrder::BUYER_GUEST,
            'buyer_name' => 'Carla', 'buyer_cpf' => self::GUEST_CPFS[0],
            'holders' => [$this->holder($this->pista->id, 0)],
        ])->assertForbidden();
        $this->assertSame(0, TicketOrder::count());
    }

    public function test_baixa_manual_de_pendente_e_cancelamento_sem_permissao_de_estorno(): void
    {
        $order = app(TicketOrderService::class)->create($this->event, TicketBuyer::guest(self::GUEST_CPFS[0], 'Carla', 'c@x.local', null), [$this->holder($this->pista->id, 0)]);

        $this->actingAs($this->staff)->post(route('tickets.orders.manual', $order), ['payment_method' => 'manual_card'])->assertRedirect();
        $this->assertSame(TicketOrder::STATUS_PAID, $order->fresh()->status);

        $vendas = $this->userWith([P::INGRESSOS_VENDAS]);
        $this->actingAs($vendas)->post(route('tickets.orders.cancel', $order), ['reason' => 'x', 'refund' => 1])->assertForbidden();

        $this->actingAs($vendas)->post(route('tickets.orders.cancel', $order), ['reason' => 'Desistiu na secretaria'])->assertRedirect();
        $this->assertSame(TicketOrder::STATUS_CANCELLED, $order->fresh()->status);
    }
}
