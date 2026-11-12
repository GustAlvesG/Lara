<?php

namespace Tests\Feature\Dashboards;

use App\Http\Controllers\Auth\LoginTokenController;
use App\Models\Member;
use App\Models\Ticket\TicketEvent;
use App\Models\User;
use App\Services\Ticket\TicketBuyer;
use App\Services\Ticket\TicketOrderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsTickets;
use Tests\Concerns\CreatesTicketSchema;
use Tests\TestCase;

/**
 * Dashboards do site (/api/dashboard/*): o login do sócio de sempre, mais o
 * perfil `administrador` relido do banco a cada requisição; e os números dos
 * ingressos, na régua do relatório financeiro, sem dado de comprador.
 */
class SiteDashboardApiTest extends TestCase
{
    use CreatesTicketSchema;
    use BuildsTickets;

    private const ADMIN_CPF = '39053344705';
    private const USER_CPF = '86288366757';

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.api.token' => 'token-de-teste', 'services.jwt.secret' => 'segredo-de-teste']);

        $this->createTicketSchema();
        Schema::table('members', fn (Blueprint $table) => $table->string('password')->nullable());
        (require database_path('migrations/2026_11_12_100000_add_site_role_to_members.php'))->up();
        (require database_path('migrations/2026_10_23_120000_create_member_account_logs_table.php'))->up();
        $this->fakeIntegrations();

        $this->staff = $this->makeUser('Financeiro');
        $this->joinSector($this->staff, 'TI');
    }

    private function account(string $cpf, string $role, array $attributes = []): Member
    {
        $id = DB::table('members')->insertGetId(array_merge([
            'cpf' => $cpf, 'name' => 'Sócio ' . $cpf, 'title' => '1001', 'site_role' => $role,
            'password' => Hash::make('segredo'), 'created_at' => now(),
        ], $attributes));

        return Member::withTrashed()->findOrFail($id);
    }

    /** Os headers do site: api_token + o token de sessão do sócio. */
    private function headers(string $cpf): array
    {
        return [
            'Authorization' => 'Bearer token-de-teste',
            'Accept' => 'application/json',
            'Session' => LoginTokenController::generate(['cpf' => $cpf]),
        ];
    }

    public function test_so_o_administrador_entra_e_o_perfil_vale_na_hora(): void
    {
        $admin = $this->account(self::ADMIN_CPF, Member::ROLE_ADMIN);
        $this->account(self::USER_CPF, Member::ROLE_USER);

        // O login de sempre já diz o perfil, para o site mostrar o link.
        $this->postJson('/api/login', ['login' => self::ADMIN_CPF, 'password' => 'segredo'], ['Authorization' => 'Bearer token-de-teste'])
            ->assertOk()->assertJsonPath('user.site_role', Member::ROLE_ADMIN);

        $adminHeaders = $this->headers(self::ADMIN_CPF);
        $this->getJson('/api/dashboard/access', $adminHeaders)
            ->assertOk()
            ->assertJsonPath('admin', true)
            ->assertJsonPath('panels.0.key', 'ingressos');

        // Sócio comum, sem token e sem o api_token.
        $this->getJson('/api/dashboard/access', $this->headers(self::USER_CPF))->assertForbidden();
        $this->getJson('/api/dashboard/tickets/events', $this->headers(self::USER_CPF))->assertForbidden();
        $this->getJson('/api/dashboard/access', ['Authorization' => 'Bearer token-de-teste', 'Accept' => 'application/json'])->assertStatus(400);
        $this->getJson('/api/dashboard/access', ['Session' => $adminHeaders['Session'], 'Accept' => 'application/json'])->assertUnauthorized();

        // Rebaixado em Contas do site: o MESMO token deixa de abrir.
        $this->actingAs($this->staff)->put(route('site-accounts.role', $admin->id), ['site_role' => Member::ROLE_USER])->assertRedirect();
        $this->getJson('/api/dashboard/access', $adminHeaders)->assertForbidden();
    }

    public function test_conta_desativada_nao_e_administradora(): void
    {
        $this->account(self::ADMIN_CPF, Member::ROLE_ADMIN, ['deleted_at' => now()]);

        $this->getJson('/api/dashboard/access', $this->headers(self::ADMIN_CPF))->assertForbidden();
    }

    public function test_numeros_dos_ingressos(): void
    {
        $this->account(self::ADMIN_CPF, Member::ROLE_ADMIN);
        $headers = $this->headers(self::ADMIN_CPF);

        $event = $this->ticketEvent(['capacity' => 10, 'sales_starts_at' => now()->subDays(10)]);
        $type = $this->ticketType($event);
        $this->ticketLot($type, ['price_non_member' => 100]);
        $emptyLot = $this->ticketLot($type, ['name' => 'Lote extra', 'quantity' => null]);
        $event->coupons()->create(['code' => 'DEZ', 'kind' => 'percent', 'value' => 10]);
        $draft = $this->ticketEvent(['title' => 'Rascunho', 'status' => TicketEvent::STATUS_DRAFT]);
        $finished = $this->ticketEvent(['title' => 'Já passou', 'status' => TicketEvent::STATUS_FINISHED, 'starts_at' => now()->subDays(30), 'ends_at' => now()->subDays(29)]);

        $orders = app(TicketOrderService::class);
        $guest = fn (int $i) => TicketBuyer::guest(self::GUEST_CPFS[$i], 'Comprador ' . $i, null, null);

        // Venda no site há dois dias (2 × 90 com cupom; um cancelado depois).
        Carbon::setTestNow(now()->subDays(2));
        $online = $orders->create($event, $guest(0), [$this->holder($type->id, 0), $this->holder($type->id, 1)], couponCode: 'DEZ');
        $orders->pay($online, ['payment_method' => 'credit', 'paid_amount' => 180, 'payment_integration_id' => 'TID-D']);
        Carbon::setTestNow();

        // Hoje: um no balcão (pago fora) e uma cortesia.
        $orders->issueAtCounter($event, $guest(2), [$this->holder($type->id, 2)], $this->staff, TicketOrderService::ISSUE_EXTERNAL, ['payment_method' => 'manual_cash']);
        $orders->issueAtCounter($event, $guest(3), [$this->holder($type->id, 3)], $this->staff, TicketOrderService::ISSUE_COURTESY);
        $orders->cancelTicket($online->tickets()->first(), $this->staff, 'Desistiu', refund: true);

        $bodies = [];

        // Seletor: sem rascunho; abre no publicado à frente.
        $list = $this->getJson('/api/dashboard/tickets/events', $headers)->assertOk();
        $this->assertSame($event->id, $list->json('default_event_id'));
        $this->assertEqualsCanonicalizing([$event->id, $finished->id], array_column($list->json('events'), 'id'));
        $row = collect($list->json('events'))->firstWhere('id', $event->id);
        $this->assertSame([3, 190], [$row['sold'], (int) $row['net']]);
        $bodies[] = $list->getContent();

        $summary = $this->getJson("/api/dashboard/tickets/events/{$event->id}/summary", $headers)->assertOk();
        $kpis = collect($summary->json('kpis'))->pluck('value', 'key');
        $this->assertSame(3, $kpis['sold']);
        $this->assertSame(1, $kpis['courtesies']);
        $this->assertSame(1, $kpis['cancelled']);
        $this->assertEquals(190, $kpis['net']);
        $this->assertEquals(190, $kpis['received']);
        $this->assertEquals(90, $kpis['refunded']);
        $this->assertEquals(30, $kpis['occupancy']);
        $bodies[] = $summary->getContent();

        // Três dias seguidos, o do meio zerado.
        $days = $this->getJson("/api/dashboard/tickets/events/{$event->id}/sales-by-day", $headers)->assertOk()->json('series');
        $this->assertSame([now()->subDays(2)->toDateString(), now()->subDay()->toDateString(), now()->toDateString()], array_column($days, 'date'));
        $this->assertSame([1, 0, 2], array_column($days, 'tickets'));
        $this->assertEquals([90, 0, 100], array_column($days, 'amount'));

        $lots = collect($this->getJson("/api/dashboard/tickets/events/{$event->id}/by-lot", $headers)->assertOk()->json('rows'));
        $this->assertSame(3, $lots->sum('total'));
        $extra = $lots->firstWhere('lot', 'Lote extra');
        $this->assertSame([0, null], [$extra['total'], $extra['capacity']]);

        $channel = $this->getJson("/api/dashboard/tickets/events/{$event->id}/by-channel", $headers)->assertOk();
        $origin = collect($channel->json('origin'))->keyBy('key');
        $this->assertSame([1, 1, 1], [$origin['site']['count'], $origin['counter']['count'], $origin['courtesy']['count']]);
        $this->assertSame(3, collect($channel->json('buyer'))->sum('count'));
        $bodies[] = $channel->getContent();

        $methods = collect($this->getJson("/api/dashboard/tickets/events/{$event->id}/by-method", $headers)->assertOk()->json('rows'));
        $this->assertEquals(190, $methods->sum('received'));

        // Nenhum dado de comprador sai daqui.
        foreach ($bodies as $body) {
            $this->assertStringNotContainsString(self::GUEST_CPFS[0], $body);
            $this->assertStringNotContainsString('Comprador', $body);
        }

        // Rascunho e inexistente: 404.
        $this->getJson("/api/dashboard/tickets/events/{$draft->id}/summary", $headers)->assertNotFound();
        $this->getJson('/api/dashboard/tickets/events/999999/summary', $headers)->assertNotFound();
    }
}
