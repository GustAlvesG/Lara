<?php

namespace Tests\Feature;

use App\Models\UberAccessRequest;
use App\Models\UberAccessRequestMessage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UberAccessRequestWebhookTest extends TestCase
{
    use DatabaseTransactions;

    private const CONTACT_UUID = 'd5e8a972-6360-11f1-9d75-06799772b1cd';
    private const CONTACT_PHONE = '5524992542363';
    private const TRIGGER = 'Pedi um Uber/99/Taxi';

    private int $messageCounter = 0;

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

        $components = $mediaUrl !== null
            ? ['image' => ['url' => $mediaUrl]]
            : ['body' => ['text' => $text ?? '']];

        return [
            'object' => 'message',
            'event' => 'received',
            'value' => [
                'event' => 'MESSAGE',
                'type' => 'CHAT',
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

    public function test_trigger_creates_session_awaiting_name(): void
    {
        $this->postJson($this->endpoint(), $this->payload(text: self::TRIGGER), $this->authHeaders())
            ->assertOk();

        $this->assertDatabaseHas('uber_access_requests', [
            'contact_uuid' => self::CONTACT_UUID,
            'contact_phone' => self::CONTACT_PHONE,
            'status' => UberAccessRequest::STATUS_AGUARDANDO_NOME,
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
            'status' => UberAccessRequest::STATUS_AGUARDANDO_NOME,
        ]);
    }

    public function test_plate_capture_strips_special_characters(): void
    {
        $this->postJson($this->endpoint(), $this->payload(text: self::TRIGGER), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'Gustavo Alves'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'Portaria 2'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'abc-1d23.'), $this->authHeaders())->assertOk();

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();

        $this->assertSame('ABC1D23', $request->vehicle_plate);
        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_PRINT, $request->status);
    }

    public function test_full_sequence_completes_and_sets_expiration(): void
    {
        $this->postJson($this->endpoint(), $this->payload(text: self::TRIGGER), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'Gustavo Alves'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'Portaria 2'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'ABC1D23'), $this->authHeaders())->assertOk();
        $this->postJson(
            $this->endpoint(),
            $this->payload(mediaUrl: 'https://poli.example/media/print.jpg'),
            $this->authHeaders()
        )->assertOk();

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();

        $this->assertSame(UberAccessRequest::STATUS_CONCLUIDO, $request->status);
        $this->assertSame('Gustavo Alves', $request->requester_name);
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

    public function test_media_out_of_order_does_not_advance_state(): void
    {
        $this->postJson($this->endpoint(), $this->payload(text: self::TRIGGER), $this->authHeaders())->assertOk();
        $this->postJson(
            $this->endpoint(),
            $this->payload(mediaUrl: 'https://poli.example/media/print.jpg'),
            $this->authHeaders()
        )->assertOk();

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();

        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_NOME, $request->status);
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
        $request->update(['last_message_at' => now()->subMinutes(31)]);

        $this->postJson($this->endpoint(), $this->payload(text: 'Gustavo Alves'), $this->authHeaders())->assertOk();

        $request->refresh();
        $this->assertSame(UberAccessRequest::STATUS_EXPIRADO, $request->status);
        $this->assertNull($request->requester_name);
        $this->assertDatabaseCount('uber_access_requests', 1);

        $this->postJson($this->endpoint(), $this->payload(text: self::TRIGGER), $this->authHeaders())->assertOk();

        $this->assertDatabaseCount('uber_access_requests', 2);
        $this->assertDatabaseHas('uber_access_requests', [
            'contact_uuid' => self::CONTACT_UUID,
            'status' => UberAccessRequest::STATUS_AGUARDANDO_NOME,
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
