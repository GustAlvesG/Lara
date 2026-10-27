<?php

namespace Tests\Feature\Tickets;

use App\Exceptions\TicketException;
use App\Mail\ContactMail;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketGuestAccessCode;
use App\Models\Ticket\TicketType;
use App\Services\Ticket\TicketBuyer;
use App\Services\Ticket\TicketGuestAccessService;
use App\Services\Ticket\TicketOrderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTickets;
use Tests\Concerns\CreatesTicketSchema;
use Tests\TestCase;

/**
 * "Meus ingressos" do não sócio no site: CPF + e-mail → código por e-mail →
 * acesso temporário → pedidos (cada um com o token dele).
 */
class TicketGuestAccessTest extends TestCase
{
    use CreatesTicketSchema;
    use BuildsTickets;

    private TicketEvent $event;
    private TicketType $pista;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.api.token' => 'token-de-teste']);

        $this->createTicketSchema();
        $this->fakeIntegrations();

        $this->event = $this->ticketEvent();
        $this->pista = $this->ticketType($this->event);
        $this->ticketLot($this->pista);
    }

    private function appHeaders(): array
    {
        return ['Authorization' => 'Bearer token-de-teste', 'Accept' => 'application/json'];
    }

    private function order(int $buyer, string $email, int $holder)
    {
        return app(TicketOrderService::class)->create($this->event, TicketBuyer::guest(self::GUEST_CPFS[$buyer], 'Carla Visitante', $email, null), [$this->holder($this->pista->id, $holder)]);
    }

    /** Pede o código e devolve o que foi para o e-mail. */
    private function requestCode(string $cpf, string $email): ?string
    {
        $this->withHeaders($this->appHeaders())->postJson('/api/tickets/guest-access/code', ['cpf' => $cpf, 'email' => $email])
            ->assertOk()
            ->assertJsonPath('expires_in_minutes', TicketGuestAccessService::CODE_MINUTES);

        $code = null;
        Mail::assertQueued(ContactMail::class, function (ContactMail $mail) use (&$code) {
            if (($mail->data['type'] ?? null) === 'tickets.access_code') {
                $code = $mail->data['code'];
            }

            return true;
        });

        return $code;
    }

    public function test_codigo_por_email_da_acesso_aos_pedidos(): void
    {
        $first = $this->order(0, 'Carla@Visita.local', 0);
        $second = $this->order(0, 'carla@visita.local', 1);
        $this->order(1, 'outra@visita.local', 2);

        $code = $this->requestCode('529.982.247-25', 'carla@visita.local');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertNotSame($code, TicketGuestAccessCode::sole()->code_hash);

        $token = $this->withHeaders($this->appHeaders())->postJson('/api/tickets/guest-access/verify', ['cpf' => self::GUEST_CPFS[0], 'email' => 'CARLA@visita.local', 'code' => $code])
            ->assertOk()
            ->json('access_token');

        $list = $this->withHeaders($this->appHeaders() + ['Ticket-Access-Token' => $token])->getJson('/api/tickets/guest-access/orders')
            ->assertOk()
            ->assertJsonPath('buyer.name', 'Carla Visitante')
            ->assertJsonPath('buyer.cpf', '529.***.**7-25')
            ->assertJsonCount(2, 'orders')
            ->assertJsonPath('orders.0.id', $second->id)
            ->assertJsonPath('orders.1.id', $first->id);

        // Cada pedido vem com o token dele: as rotas /guest-orders/{id} seguem valendo.
        $orderToken = $list->json('orders.1.access_token');
        $this->withHeaders($this->appHeaders() + ['Ticket-Order-Token' => $orderToken])->getJson("/api/tickets/guest-orders/{$first->id}")
            ->assertOk()
            ->assertJsonPath('id', $first->id);

        // O código vale uma vez.
        $this->withHeaders($this->appHeaders())->postJson('/api/tickets/guest-access/verify', ['cpf' => self::GUEST_CPFS[0], 'email' => 'carla@visita.local', 'code' => $code])
            ->assertStatus(422)
            ->assertJsonPath('reason', TicketException::ACCESS_CODE_EXPIRED);
    }

    public function test_sem_pedido_responde_igual_e_nao_manda_email(): void
    {
        $this->order(0, 'carla@visita.local', 0);

        $this->withHeaders($this->appHeaders())->postJson('/api/tickets/guest-access/code', ['cpf' => self::GUEST_CPFS[0], 'email' => 'errado@visita.local'])
            ->assertOk()
            ->assertJsonPath('message', 'Se houver compra com esse CPF e e-mail, enviamos um código para o e-mail.');

        Mail::assertNotQueued(ContactMail::class, fn (ContactMail $mail) => ($mail->data['type'] ?? null) === 'tickets.access_code');
        $this->assertSame(0, TicketGuestAccessCode::count());

        $this->withHeaders($this->appHeaders())->postJson('/api/tickets/guest-access/code', ['cpf' => '111.111.111-11', 'email' => 'carla@visita.local'])
            ->assertStatus(422);
    }

    public function test_codigo_errado_conta_tentativas_e_vence(): void
    {
        $this->order(0, 'carla@visita.local', 0);
        $code = $this->requestCode(self::GUEST_CPFS[0], 'carla@visita.local');
        $wrong = $code === '000000' ? '111111' : '000000';
        $url = '/api/tickets/guest-access/verify';
        $body = ['cpf' => self::GUEST_CPFS[0], 'email' => 'carla@visita.local', 'code' => $wrong];

        $this->withHeaders($this->appHeaders())->postJson($url, $body)
            ->assertStatus(422)
            ->assertJsonPath('reason', TicketException::ACCESS_CODE_INVALID)
            ->assertJsonPath('context.attempts_left', 4);

        for ($i = 0; $i < 3; $i++) {
            $this->withHeaders($this->appHeaders())->postJson($url, $body)->assertJsonPath('reason', TicketException::ACCESS_CODE_INVALID);
        }
        $this->withHeaders($this->appHeaders())->postJson($url, $body)->assertJsonPath('reason', TicketException::ACCESS_CODE_EXPIRED);

        // Esgotou: nem o certo passa mais.
        $this->withHeaders($this->appHeaders())->postJson($url, ['code' => $code] + $body)->assertJsonPath('reason', TicketException::ACCESS_CODE_EXPIRED);

        // Código novo vale; e vence em 10 minutos.
        $fresh = $this->requestCode(self::GUEST_CPFS[0], 'carla@visita.local');
        Carbon::setTestNow(now()->addMinutes(11));
        $this->withHeaders($this->appHeaders())->postJson($url, ['code' => $fresh] + $body)->assertJsonPath('reason', TicketException::ACCESS_CODE_EXPIRED);
    }

    public function test_acesso_invalido_ou_vencido_e_401(): void
    {
        $this->order(0, 'carla@visita.local', 0);

        $this->withHeaders($this->appHeaders())->getJson('/api/tickets/guest-access/orders')
            ->assertStatus(401)
            ->assertJsonPath('reason', TicketException::ACCESS_EXPIRED);
        $this->withHeaders($this->appHeaders() + ['Ticket-Access-Token' => 'adulterado'])->getJson('/api/tickets/guest-access/orders')
            ->assertStatus(401);

        $access = app(TicketGuestAccessService::class);
        $code = $this->requestCode(self::GUEST_CPFS[0], 'carla@visita.local');
        $token = $access->verify(self::GUEST_CPFS[0], 'carla@visita.local', $code)['access_token'];
        $this->assertNotNull($access->resolve($token));

        Carbon::setTestNow(now()->addHours(TicketGuestAccessService::ACCESS_HOURS)->addMinute());
        $this->withHeaders($this->appHeaders() + ['Ticket-Access-Token' => $token])->getJson('/api/tickets/guest-access/orders')
            ->assertStatus(401);
    }

    public function test_limite_de_codigos_por_hora(): void
    {
        $this->order(0, 'carla@visita.local', 0);
        $access = app(TicketGuestAccessService::class);

        for ($i = 0; $i < TicketGuestAccessService::MAX_CODES_PER_HOUR + 2; $i++) {
            $access->requestCode(self::GUEST_CPFS[0], 'carla@visita.local');
        }

        $this->assertSame(TicketGuestAccessService::MAX_CODES_PER_HOUR, TicketGuestAccessCode::count());
        // Só o último continua valendo.
        $this->assertSame(1, TicketGuestAccessCode::whereNull('used_at')->count());
    }
}
