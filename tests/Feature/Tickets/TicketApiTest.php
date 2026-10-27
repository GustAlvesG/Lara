<?php

namespace Tests\Feature\Tickets;

use App\Exceptions\TicketException;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketOrder;
use App\Models\Ticket\TicketType;
use App\Providers\Services\JwtService;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTickets;
use Tests\Concerns\CreatesTicketSchema;
use Tests\TestCase;

/**
 * `/api/tickets/*` pelo site de locação: vitrine só com o `api_token`;
 * compra do sócio com o JWT (só para ele); compra do não sócio sem conta,
 * acessada pelo token do pedido no header `Ticket-Order-Token`.
 */
class TicketApiTest extends TestCase
{
    use CreatesTicketSchema;
    use BuildsTickets;

    private string $token = 'token-de-teste';
    private const CPF_ANA = '39053344705';

    private TicketEvent $event;
    private TicketType $pista;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.api.token' => $this->token, 'services.jwt.secret' => 'segredo-de-teste']);

        $this->createTicketSchema();
        $this->fakeIntegrations();

        $this->member(self::CPF_ANA, 'Ana Souza');
        $this->event = $this->ticketEvent();
        $this->pista = $this->ticketType($this->event);
        $this->ticketLot($this->pista, ['quantity' => 10]);
        $this->ticketEvent(['title' => 'Rascunho', 'status' => TicketEvent::STATUS_DRAFT]);
    }

    private function appHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token, 'Accept' => 'application/json'];
    }

    private function memberHeaders(string $cpf = self::CPF_ANA): array
    {
        // `exp` no relógio real: o JWT não enxerga o Carbon::setTestNow.
        return $this->appHeaders() + ['Session' => (new JwtService())->generateToken(['username' => $cpf, 'exp' => time() + 3600])];
    }

    private function guestOrder(array $tickets = null)
    {
        return $this->withHeaders($this->appHeaders())->postJson("/api/tickets/events/{$this->event->id}/guest-orders", [
            'buyer' => ['cpf' => self::GUEST_CPFS[0], 'name' => 'Carla Visitante', 'email' => 'carla@visita.local', 'phone' => '61999990000'],
            'tickets' => $tickets ?? [$this->holder($this->pista->id, 0), $this->holder($this->pista->id, 1)],
        ]);
    }

    public function test_vitrine_mostra_so_o_publicado_com_lote_vigente(): void
    {
        $this->withHeaders($this->appHeaders())->getJson('/api/tickets/events')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'Baile de Carnaval')
            ->assertJsonPath('0.sales_open', true)
            ->assertJsonPath('0.types.0.available', true)
            ->assertJsonPath('0.types.0.lot.price_member', 50)
            ->assertJsonPath('0.types.0.lot.price_non_member', 80)
            ->assertJsonPath('0.types.0.lot.remaining', 10);

        $draft = TicketEvent::where('title', 'Rascunho')->first();
        $this->withHeaders($this->appHeaders())->getJson("/api/tickets/events/{$draft->id}")
            ->assertNotFound()
            ->assertJsonPath('reason', TicketException::EVENT_NOT_FOUND);
    }

    public function test_sem_api_token_nao_responde(): void
    {
        $this->getJson('/api/tickets/events')->assertUnauthorized();
    }

    public function test_nao_socio_compra_paga_e_ve_o_pedido_pelo_token(): void
    {
        $created = $this->guestOrder()->assertCreated()
            ->assertJsonPath('buyer_kind', TicketOrder::BUYER_GUEST)
            ->assertJsonPath('total', 160)
            ->assertJsonPath('tickets.0.code', null);
        $id = $created->json('id');
        $token = $created->json('access_token');
        $this->assertNotEmpty($token);

        $this->withHeaders($this->appHeaders())->getJson("/api/tickets/guest-orders/{$id}")
            ->assertNotFound()->assertJsonPath('reason', TicketException::ORDER_NOT_FOUND);
        $this->withHeaders($this->appHeaders() + ['Ticket-Order-Token' => 'errado'])->getJson("/api/tickets/guest-orders/{$id}")
            ->assertNotFound();

        $auth = $this->appHeaders() + ['Ticket-Order-Token' => $token];
        $this->withHeaders($auth)->postJson("/api/tickets/guest-orders/{$id}/payment", [
            'payment_method' => 'credit', 'paid_amount' => 160, 'payment_integration_id' => 'TID-API',
        ])->assertCreated()->assertJsonPath('order.status', TicketOrder::STATUS_PAID);

        $shown = $this->withHeaders($auth)->getJson("/api/tickets/guest-orders/{$id}")->assertOk()
            ->assertJsonMissingPath('access_token');
        $this->assertMatchesRegularExpression('/^ING-/', $shown->json('tickets.0.code'));
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $shown->json('tickets.0.qr_code'));
        // O CPF do titular sai mascarado.
        $this->assertNotSame(self::GUEST_CPFS[0], $shown->json('tickets.0.holder_cpf'));
        $this->assertStringNotContainsString(self::GUEST_CPFS[0], (string) $shown->getContent());

        $this->withHeaders($auth)->get("/api/tickets/guest-orders/{$id}/pdf")
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_nao_socio_recusa_vem_com_reason(): void
    {
        $this->guestOrder([['type_id' => $this->pista->id, 'holder_name' => 'Ana Souza', 'holder_cpf' => self::CPF_ANA]])
            ->assertStatus(422)->assertJsonPath('reason', TicketException::HOLDER_IS_MEMBER);

        $this->guestOrder([$this->holder($this->pista->id, 0)])->assertCreated();
        $this->guestOrder([$this->holder($this->pista->id, 0)])
            ->assertStatus(409)->assertJsonPath('reason', TicketException::ALREADY_HAS_TICKET);
    }

    public function test_socio_compra_para_si_e_ve_em_meus_pedidos(): void
    {
        $url = "/api/tickets/events/{$this->event->id}/orders";

        // Sem o header `Session` o JwtMiddleware responde 400; com token inválido, 401.
        $this->withHeaders($this->appHeaders())->postJson($url, ['type_id' => $this->pista->id])->assertStatus(400);
        $this->withHeaders($this->appHeaders() + ['Session' => 'nao-e-um-jwt'])->postJson($url, ['type_id' => $this->pista->id])->assertUnauthorized();
        $this->assertSame(0, TicketOrder::count());

        $created = $this->withHeaders($this->memberHeaders())->postJson($url, ['type_id' => $this->pista->id, 'cpf' => self::GUEST_CPFS[3]])
            ->assertCreated()
            ->assertJsonPath('buyer_kind', TicketOrder::BUYER_MEMBER)
            ->assertJsonPath('total', 50)
            ->assertJsonPath('tickets.0.holder_name', 'Ana Souza')
            ->assertJsonMissingPath('access_token');
        $id = $created->json('id');

        $this->withHeaders($this->memberHeaders())->getJson('/api/tickets/my-orders')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $id);

        $this->withHeaders($this->memberHeaders())->postJson("/api/tickets/orders/{$id}/payment", [
            'payment_method' => 'credit', 'paid_amount' => 50, 'payment_integration_id' => 'TID-ANA',
        ])->assertCreated();
        $this->withHeaders($this->memberHeaders())->postJson("/api/tickets/orders/{$id}/payment", [
            'payment_method' => 'credit', 'paid_amount' => 50, 'payment_integration_id' => 'TID-ANA',
        ])->assertOk()->assertJsonPath('message', 'Pagamento já registrado.');

        $this->withHeaders($this->memberHeaders())->deleteJson("/api/tickets/orders/{$id}")
            ->assertStatus(422)->assertJsonPath('reason', TicketException::PAID_CONTACT_ORGANIZER);
    }

    public function test_socio_ve_o_titulo_e_compra_para_todos_pela_ref(): void
    {
        $this->dependent(self::GUEST_CPFS[1], 'Bia Souza');
        $this->dependent(self::GUEST_CPFS[3], 'De Outro Título', '2002');

        $title = $this->withHeaders($this->memberHeaders())->getJson("/api/tickets/my-title?event_id={$this->event->id}")
            ->assertOk()
            ->assertJsonPath('title', '1001')
            ->assertJsonCount(2, 'people')
            ->assertJsonPath('people.0.is_me', true)
            ->assertJsonPath('people.0.has_ticket', false);
        $this->assertStringNotContainsString(self::GUEST_CPFS[1], (string) $title->getContent());
        $bia = collect($title->json('people'))->firstWhere('name', 'Bia Souza');

        $this->withHeaders($this->memberHeaders())->postJson("/api/tickets/events/{$this->event->id}/orders", [
            'tickets' => [['type_id' => $this->pista->id], ['type_id' => $this->pista->id, 'holder_ref' => $bia['ref']]],
        ])->assertCreated()
            ->assertJsonPath('total', 100)
            ->assertJsonPath('tickets.1.holder_name', 'Bia Souza');

        $this->withHeaders($this->memberHeaders())->getJson("/api/tickets/my-title?event_id={$this->event->id}")
            ->assertJsonPath('people.0.has_ticket', true);

        // Ref adulterada ou de outro título: recusa.
        $this->withHeaders($this->memberHeaders())->postJson("/api/tickets/events/{$this->event->id}/orders", [
            'tickets' => [['type_id' => $this->pista->id, 'holder_ref' => 'nao-e-uma-ref']],
        ])->assertStatus(422)->assertJsonPath('reason', TicketException::INVALID_HOLDER);
        $foreign = \Illuminate\Support\Facades\Crypt::encryptString(self::GUEST_CPFS[3] . '|2002');
        $this->withHeaders($this->memberHeaders())->postJson("/api/tickets/events/{$this->event->id}/orders", [
            'tickets' => [['type_id' => $this->pista->id, 'holder_ref' => $foreign]],
        ])->assertStatus(422)->assertJsonPath('reason', TicketException::INVALID_HOLDER);
    }

    public function test_pedido_de_outro_socio_e_404(): void
    {
        $this->member('86288366757', 'Bruno Lima', '1002');
        $id = $this->withHeaders($this->memberHeaders())->postJson("/api/tickets/events/{$this->event->id}/orders", ['type_id' => $this->pista->id])->json('id');

        $this->withHeaders($this->memberHeaders('86288366757'))->getJson("/api/tickets/orders/{$id}")
            ->assertNotFound()->assertJsonPath('reason', TicketException::ORDER_NOT_FOUND);
    }

    public function test_reenvio_do_link_responde_igual_exista_ou_nao(): void
    {
        $this->guestOrder()->assertCreated();

        $this->withHeaders($this->appHeaders())->postJson('/api/tickets/guest-orders/resend-link', ['cpf' => self::GUEST_CPFS[0], 'email' => 'carla@visita.local'])
            ->assertOk();
        $this->withHeaders($this->appHeaders())->postJson('/api/tickets/guest-orders/resend-link', ['cpf' => self::GUEST_CPFS[2], 'email' => 'ninguem@x.local'])
            ->assertOk()->assertJsonPath('message', 'Se houver pedido com esse CPF e e-mail, o link foi enviado.');
    }
}
