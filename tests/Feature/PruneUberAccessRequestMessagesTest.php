<?php

namespace Tests\Feature;

use App\Models\UberAccessRequest;
use App\Models\UberAccessRequestMessage;
use Tests\TestCase;

/**
 * Sem RefreshDatabase pelo mesmo motivo registrado em LaraMessageHistoryTest:
 * a cadeia completa de migrations falha hoje em `add_columns_member` x
 * `tourments`. Aqui só as migrations desta feature são aplicadas.
 */
class PruneUberAccessRequestMessagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (require base_path('database/migrations/2026_07_20_150000_create_uber_access_requests_tables.php'))->up();
        (require base_path('database/migrations/2026_07_21_120000_add_matricula_to_uber_access_requests.php'))->up();
    }

    private function makeMessage(string $poliMessageId, ?int $requestId, $createdAt): UberAccessRequestMessage
    {
        $message = UberAccessRequestMessage::create([
            'uber_access_request_id' => $requestId,
            'poli_message_id' => $poliMessageId,
            'raw_payload' => ['value' => ['type' => 'CHAT']],
        ]);

        $message->created_at = $createdAt;
        $message->save();

        return $message;
    }

    public function test_prunes_old_general_messages_and_keeps_recent_and_uber_linked(): void
    {
        $request = UberAccessRequest::create([
            'contact_uuid' => 'prune-test-uuid',
            'contact_phone' => '5524900000000',
            'status' => UberAccessRequest::STATUS_AGUARDANDO_NOME,
        ]);

        $oldGeneral = $this->makeMessage('prune-old-general', null, now()->subDays(2));
        $recentGeneral = $this->makeMessage('prune-recent-general', null, now()->subHours(2));
        $oldUberLinked = $this->makeMessage('prune-old-uber', $request->id, now()->subDays(2));

        $this->artisan('app:prune-uber-access-request-messages')->assertSuccessful();

        $this->assertDatabaseMissing('uber_access_request_messages', ['id' => $oldGeneral->id]);
        $this->assertDatabaseHas('uber_access_request_messages', ['id' => $recentGeneral->id]);
        $this->assertDatabaseHas('uber_access_request_messages', ['id' => $oldUberLinked->id]);
    }

    public function test_respects_custom_days_option(): void
    {
        $threeDaysOld = $this->makeMessage('prune-3d', null, now()->subDays(3));
        $oneDayOld = $this->makeMessage('prune-1d', null, now()->subDays(1)->subHour());

        $this->artisan('app:prune-uber-access-request-messages', ['--days' => 2])->assertSuccessful();

        $this->assertDatabaseMissing('uber_access_request_messages', ['id' => $threeDaysOld->id]);
        $this->assertDatabaseHas('uber_access_request_messages', ['id' => $oneDayOld->id]);
    }
}
