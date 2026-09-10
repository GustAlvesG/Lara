<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\UberAccessRequest;
use App\Models\UberAccessRequestMessage;
use App\Services\MultiClubes\TitleMemberLookup;
use App\Services\UberAccessRequestFlow;
use Tests\TestCase;

/**
 * Sem RefreshDatabase pelo mesmo motivo registrado em LaraMessageHistoryTest:
 * a cadeia completa de migrations falha hoje em `add_columns_member` x
 * `tourments` (as duas criam `members.title`). Aqui só as migrations desta
 * feature são aplicadas, no SQLite :memory: que o phpunit.xml configura, e
 * cada teste recebe um banco novo.
 */
class UberAccessRequestWebhookTest extends TestCase
{
    private const CONTACT_UUID = 'd5e8a972-6360-11f1-9d75-06799772b1cd';
    private const CONTACT_PHONE = '5524992542363';
    private const TRIGGER = 'Carro de Aplicativo';

    private int $messageCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        (require base_path('database/migrations/2026_07_20_150000_create_uber_access_requests_tables.php'))->up();
        (require base_path('database/migrations/2026_07_21_120000_add_matricula_to_uber_access_requests.php'))->up();
        (require base_path('database/migrations/2026_08_27_170000_add_member_validation_to_uber_access_requests.php'))->up();
        (require base_path('database/migrations/2026_09_10_100000_add_member_validation_type_to_uber_access_requests.php'))->up();

        // Funcionários também podem pedir: a conferência consulta a tabela
        // employees de verdade (é banco local), só o MultiClubes é falso.
        (require base_path('database/migrations/2026_01_05_141304_banco_de_horas.php'))->up();

        // Sem SQL Server nos testes: por padrão o título não devolve ninguém.
        // Cada teste que precisa sobrescreve com fakeTitleMembers().
        $this->fakeTitleMembers([]);
    }

    /**
     * Substitui a consulta ao MultiClubes. Passar um Throwable simula o
     * SQL Server fora do ar.
     *
     * @param  string[]|\Throwable  $names
     */
    private function fakeTitleMembers(array|\Throwable $names): void
    {
        $this->app->instance(TitleMemberLookup::class, new class($names) extends TitleMemberLookup {
            public function __construct(private array|\Throwable $names) {}

            public function namesForTitle(string $matricula): array
            {
                if ($this->names instanceof \Throwable) {
                    throw $this->names;
                }

                return $this->names;
            }
        });
    }

    private function endpoint(): string
    {
        return '/api/webhooks/whatsapp';
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . config('services.api.token')];
    }

    private function payload(
        ?string $text = null,
        ?string $mediaUrl = null,
        string $direction = 'IN',
        ?string $messageId = null,
        string $contactUuid = self::CONTACT_UUID
    ): array {
        $this->messageCounter++;

        $isImage = $mediaUrl !== null;
        $components = $isImage
            ? ['attachments' => [['type' => 'image', 'media' => ['url' => $mediaUrl], 'mime_type' => 'image/jpeg']]]
            : ['body' => ['text' => $text ?? '']];

        return [
            'object' => 'message',
            'event' => 'received',
            'value' => [
                'event' => 'MESSAGE',
                'type' => $isImage ? 'IMAGE' : 'CHAT',
                'direction' => $direction,
                'contact' => [
                    'uuid' => $contactUuid,
                    'attributes' => ['name' => 'Gustavo', 'phone' => self::CONTACT_PHONE],
                ],
                'components' => $components,
                'attendance' => ['uuid' => 'attendance-uuid'],
                'metadata' => ['external_message_id' => $messageId ?? 'wamid-' . $this->messageCounter],
            ],
        ];
    }

    public function test_outbound_message_is_ignored(): void
    {
        $this->postJson($this->endpoint(), $this->payload(text: self::TRIGGER, direction: 'OUT'), $this->authHeaders())
            ->assertOk();

        $this->assertDatabaseCount('uber_access_requests', 0);
    }

    public function test_message_without_trigger_in_idle_is_ignored(): void
    {
        $this->postJson($this->endpoint(), $this->payload(text: 'Oi, tudo bem?'), $this->authHeaders())
            ->assertOk();

        $this->assertDatabaseCount('uber_access_requests', 0);
    }

    public function test_trigger_creates_session_awaiting_matricula(): void
    {
        $this->postJson($this->endpoint(), $this->payload(text: self::TRIGGER), $this->authHeaders())
            ->assertOk();

        $this->assertDatabaseHas('uber_access_requests', [
            'contact_uuid' => self::CONTACT_UUID,
            'contact_phone' => self::CONTACT_PHONE,
            'status' => UberAccessRequest::STATUS_AGUARDANDO_MATRICULA,
        ]);
    }

    public function test_trigger_matches_when_message_starts_with_trigger_text(): void
    {
        $this->postJson(
            $this->endpoint(),
            $this->payload(text: self::TRIGGER . ' - Uber'),
            $this->authHeaders()
        )->assertOk();

        $this->assertDatabaseHas('uber_access_requests', [
            'contact_uuid' => self::CONTACT_UUID,
            'status' => UberAccessRequest::STATUS_AGUARDANDO_MATRICULA,
        ]);
    }

    public function test_plate_capture_strips_special_characters(): void
    {
        $this->postJson($this->endpoint(), $this->payload(text: self::TRIGGER), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: '12345'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'Gustavo Alves'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'Portaria 2'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'abc-1d23.'), $this->authHeaders())->assertOk();

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();

        $this->assertSame('ABC1D23', $request->vehicle_plate);
        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_PRINT, $request->status);
    }

    public function test_matricula_capture_advances_to_awaiting_name(): void
    {
        $this->postJson($this->endpoint(), $this->payload(text: self::TRIGGER), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: '987654'), $this->authHeaders())->assertOk();

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();

        $this->assertSame('987654', $request->matricula);
        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_NOME, $request->status);
    }

    public function test_full_sequence_completes_and_sets_expiration(): void
    {
        $this->postJson($this->endpoint(), $this->payload(text: self::TRIGGER), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: '987654'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'Gustavo Alves'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'Portaria 2'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'ABC1D23'), $this->authHeaders())->assertOk();
        $this->postJson(
            $this->endpoint(),
            $this->payload(mediaUrl: 'https://poli.example/media/print.jpg'),
            $this->authHeaders()
        )->assertOk();

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();

        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_ACESSO, $request->status);
        $this->assertSame('Gustavo Alves', $request->requester_name);
        $this->assertSame('987654', $request->matricula);
        $this->assertSame('Portaria 2', $request->club_location);
        $this->assertSame('ABC1D23', $request->vehicle_plate);
        $this->assertSame('https://poli.example/media/print.jpg', $request->screenshot_url);
        $this->assertNotNull($request->completed_at);
        $this->assertNotNull($request->expires_at);
        $this->assertEqualsWithDelta(
            $request->completed_at->addMinutes(30)->timestamp,
            $request->expires_at->timestamp,
            1
        );
    }

    /** Roda o fluxo inteiro até a imagem, deixando o pedido completo. */
    private function completeFlow(string $name = 'Gustavo Alves', string $matricula = '987654'): UberAccessRequest
    {
        $this->postJson($this->endpoint(), $this->payload(text: self::TRIGGER), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: $matricula), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: $name), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'Portaria 2'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'ABC1D23'), $this->authHeaders())->assertOk();
        $this->postJson(
            $this->endpoint(),
            $this->payload(mediaUrl: 'https://poli.example/media/print.jpg'),
            $this->authHeaders()
        )->assertOk();

        return UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();
    }

    public function test_member_is_validated_when_name_belongs_to_the_title(): void
    {
        $this->fakeTitleMembers(['Maria Souza', 'Gustavo Alves']);

        $request = $this->completeFlow(name: 'Gustavo Alves');

        $this->assertSame(UberAccessRequest::MEMBER_VALIDATION_VALIDADO, $request->member_validation);
        $this->assertSame('Gustavo Alves', $request->member_validation_name);
        $this->assertSame(UberAccessRequest::MEMBER_TYPE_SOCIO, $request->member_validation_type);
        $this->assertNotNull($request->member_validated_at);
    }

    public function test_member_validation_fails_when_name_is_not_on_the_title(): void
    {
        $this->fakeTitleMembers(['Maria Souza', 'Joana Lima']);

        $request = $this->completeFlow(name: 'Gustavo Alves');

        $this->assertSame(UberAccessRequest::MEMBER_VALIDATION_NAO_ENCONTRADO, $request->member_validation);
        $this->assertNull($request->member_validation_name);
    }

    public function test_member_validation_fails_when_title_has_nobody(): void
    {
        $this->fakeTitleMembers([]);

        $request = $this->completeFlow();

        $this->assertSame(UberAccessRequest::MEMBER_VALIDATION_NAO_ENCONTRADO, $request->member_validation);
    }

    public function test_multiclubes_outage_marks_validation_unavailable_without_blocking(): void
    {
        $this->fakeTitleMembers(new \RuntimeException('SQLSTATE[08001] sql server unreachable'));

        $request = $this->completeFlow();

        // O pedido tem de continuar utilizável na portaria mesmo sem conferência.
        $this->assertSame(UberAccessRequest::MEMBER_VALIDATION_INDISPONIVEL, $request->member_validation);
        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_ACESSO, $request->status);
        $this->assertNotNull($request->expires_at);
    }

    private function makeEmployee(string $code, string $cpf, string $name): Employee
    {
        return Employee::create([
            'employee_code'  => $code,
            'name'           => $name,
            'cpf'            => $cpf,
            'admission_date' => '2024-01-01',
            'position'       => 'Recepcionista',
            'department'     => 'Portaria',
        ]);
    }

    public function test_employee_is_validated_by_code(): void
    {
        $this->makeEmployee('12152', '121.983.027-56', 'DANIELLE TRINDADE BERION');

        $request = $this->completeFlow(name: 'Danielle', matricula: '12152');

        $this->assertSame(UberAccessRequest::MEMBER_VALIDATION_VALIDADO, $request->member_validation);
        $this->assertSame(UberAccessRequest::MEMBER_TYPE_FUNCIONARIO, $request->member_validation_type);
        $this->assertSame('DANIELLE TRINDADE BERION', $request->member_validation_name);
        $this->assertSame('Funcionário confere', $request->memberValidationLabel());
    }

    public function test_employee_is_validated_by_cpf_regardless_of_how_either_side_is_formatted(): void
    {
        // O banco tem CPF gravado com e sem máscara; o funcionário digita dos
        // dois jeitos também.
        $this->makeEmployee('12152', '121.983.027-56', 'DANIELLE TRINDADE BERION');
        $this->makeEmployee('12206', '20613169727', 'NATALIA MARLENE IVA RODRIGUES DA SILVA');

        $cases = [
            ['12198302756', 'Danielle', 'DANIELLE TRINDADE BERION'],
            ['206.131.697-27', 'Natália', 'NATALIA MARLENE IVA RODRIGUES DA SILVA'],
        ];

        foreach ($cases as $i => [$cpf, $name, $official]) {
            $contact = 'employee-cpf-' . $i;
            $send = fn (array $payload) => $this->postJson($this->endpoint(), $payload, $this->authHeaders())->assertOk();

            foreach ([self::TRIGGER, $cpf, $name, 'Portaria 2', 'ABC1D23'] as $text) {
                $send($this->payload(text: $text, contactUuid: $contact));
            }
            $send($this->payload(mediaUrl: 'https://poli.example/media/print.jpg', contactUuid: $contact));

            $request = UberAccessRequest::where('contact_uuid', $contact)->firstOrFail();

            $this->assertSame(UberAccessRequest::MEMBER_TYPE_FUNCIONARIO, $request->member_validation_type, $cpf);
            $this->assertSame($official, $request->member_validation_name, $cpf);
        }
    }

    public function test_dismissed_employee_is_not_validated(): void
    {
        $this->makeEmployee('12152', '121.983.027-56', 'DANIELLE TRINDADE BERION')->delete();

        $request = $this->completeFlow(name: 'Danielle', matricula: '12152');

        $this->assertSame(UberAccessRequest::MEMBER_VALIDATION_NAO_ENCONTRADO, $request->member_validation);
        $this->assertNull($request->member_validation_type);
    }

    public function test_media_out_of_order_does_not_advance_state(): void
    {
        $this->postJson($this->endpoint(), $this->payload(text: self::TRIGGER), $this->authHeaders())->assertOk();
        $this->postJson(
            $this->endpoint(),
            $this->payload(mediaUrl: 'https://poli.example/media/print.jpg'),
            $this->authHeaders()
        )->assertOk();

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();

        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_MATRICULA, $request->status);
        $this->assertNull($request->screenshot_url);
    }

    public function test_duplicate_message_id_is_not_reprocessed(): void
    {
        $payload = $this->payload(text: self::TRIGGER, messageId: 'dup-1');

        $this->postJson($this->endpoint(), $payload, $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $payload, $this->authHeaders())->assertOk();

        $this->assertSame(1, UberAccessRequestMessage::where('poli_message_id', 'dup-1')->count());
        $this->assertDatabaseCount('uber_access_requests', 1);
    }

    public function test_expired_session_does_not_accept_stale_answers_but_new_trigger_opens_fresh_session(): void
    {
        $this->postJson($this->endpoint(), $this->payload(text: self::TRIGGER), $this->authHeaders())->assertOk();

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();
        $request->update([
            'last_message_at' => now()->subSeconds(UberAccessRequestFlow::SESSION_TIMEOUT_SECONDS + 1),
        ]);

        $this->postJson($this->endpoint(), $this->payload(text: 'Gustavo Alves'), $this->authHeaders())->assertOk();

        $request->refresh();
        $this->assertSame(UberAccessRequest::STATUS_EXPIRADO, $request->status);
        $this->assertNull($request->requester_name);
        $this->assertDatabaseCount('uber_access_requests', 1);

        $this->postJson($this->endpoint(), $this->payload(text: self::TRIGGER), $this->authHeaders())->assertOk();

        $this->assertDatabaseCount('uber_access_requests', 2);
        $this->assertDatabaseHas('uber_access_requests', [
            'contact_uuid' => self::CONTACT_UUID,
            'status' => UberAccessRequest::STATUS_AGUARDANDO_MATRICULA,
        ]);
    }

    public function test_answer_just_within_the_timeout_is_still_accepted(): void
    {
        $this->postJson($this->endpoint(), $this->payload(text: self::TRIGGER), $this->authHeaders())->assertOk();

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();
        $request->update([
            'last_message_at' => now()->subSeconds(UberAccessRequestFlow::SESSION_TIMEOUT_SECONDS - 20),
        ]);

        $this->postJson($this->endpoint(), $this->payload(text: '987654'), $this->authHeaders())->assertOk();

        $request->refresh();
        $this->assertSame('987654', $request->matricula);
        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_NOME, $request->status);
    }

    public function test_completed_request_is_not_killed_by_the_response_timeout(): void
    {
        $this->postJson($this->endpoint(), $this->payload(text: self::TRIGGER), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: '987654'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'Gustavo Alves'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'Portaria 2'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'ABC1D23'), $this->authHeaders())->assertOk();
        $this->postJson(
            $this->endpoint(),
            $this->payload(mediaUrl: 'https://poli.example/media/print.jpg'),
            $this->authHeaders()
        )->assertOk();

        // O motorista tem até `expires_at` (30 min) para chegar: ficar mais de
        // 200s sem mensagem nova não pode cancelar um pedido já completo.
        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();
        $request->update([
            'last_message_at' => now()->subSeconds(UberAccessRequestFlow::SESSION_TIMEOUT_SECONDS + 60),
        ]);

        $this->artisan('app:expire-uber-access-requests')->assertSuccessful();

        $request->refresh();
        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_ACESSO, $request->status);
    }

    public function test_scheduled_command_cancels_abandoned_capture(): void
    {
        $this->postJson($this->endpoint(), $this->payload(text: self::TRIGGER), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: '987654'), $this->authHeaders())->assertOk();

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();
        $request->update([
            'last_message_at' => now()->subSeconds(UberAccessRequestFlow::SESSION_TIMEOUT_SECONDS + 1),
        ]);

        $this->artisan('app:expire-uber-access-requests')->assertSuccessful();

        $request->refresh();
        $this->assertSame(UberAccessRequest::STATUS_EXPIRADO, $request->status);

        // Cancelado de forma proativa, o gatilho seguinte abre um pedido novo.
        $this->postJson($this->endpoint(), $this->payload(text: self::TRIGGER), $this->authHeaders())->assertOk();

        $this->assertDatabaseCount('uber_access_requests', 2);
        $this->assertDatabaseHas('uber_access_requests', [
            'contact_uuid' => self::CONTACT_UUID,
            'status' => UberAccessRequest::STATUS_AGUARDANDO_MATRICULA,
        ]);
    }

    public function test_malformed_payload_returns_422(): void
    {
        $this->postJson($this->endpoint(), ['foo' => 'bar'], $this->authHeaders())
            ->assertStatus(422);
    }

    public function test_valid_payload_outside_flow_returns_200_without_side_effects(): void
    {
        $payload = $this->payload(text: self::TRIGGER);
        $payload['value']['event'] = 'STATUS';

        $this->postJson($this->endpoint(), $payload, $this->authHeaders())->assertOk();

        $this->assertDatabaseCount('uber_access_requests', 0);
        $this->assertDatabaseCount('uber_access_request_messages', 1);
    }

    public function test_request_without_valid_bearer_token_is_rejected(): void
    {
        $this->postJson($this->endpoint(), $this->payload(text: self::TRIGGER), ['Authorization' => 'Bearer invalid'])
            ->assertStatus(401);
    }
}
