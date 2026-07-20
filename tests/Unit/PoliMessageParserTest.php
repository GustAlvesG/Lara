<?php

namespace Tests\Unit;

use App\Services\Poli\ParsedPoliMessage;
use App\Services\Poli\PoliMessageParser;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PoliMessageParserTest extends TestCase
{
    private function realTextPayload(): array
    {
        return [
            'object' => 'message',
            'event' => 'received',
            'account_uuid' => 'a9c0bc53-430e-11f1-9d75-06799772b1cd',
            'uuid' => 'rec-52129-msg-1784555627.8982-1638',
            'value' => [
                'uuid' => 'rec-52129-msg-1784555627.8982-1638',
                'event' => 'MESSAGE',
                'type' => 'CHAT',
                'account_channel' => [
                    'uid' => '5524992510959@c.us',
                    'name' => 'Clube dos Funcionários da CSN',
                    'provider' => 'WABA',
                ],
                'direction' => 'IN',
                'timestamp' => '1784555626',
                'author' => [
                    'type' => 'CONTACT',
                    'uuid' => 'd5e8a972-6360-11f1-9d75-06799772b1cd',
                    'attributes' => ['name' => 'Gustavo Coordenador de TI|Gustavo', 'phone' => '5524992542363'],
                ],
                'contact' => [
                    'uuid' => 'd5e8a972-6360-11f1-9d75-06799772b1cd',
                    'attributes' => ['name' => 'Gustavo Coordenador de TI|Gustavo', 'phone' => '5524992542363'],
                ],
                'components' => ['body' => ['text' => 'Teste']],
                'attendance' => ['uuid' => '6e592720-8442-11f1-9d75-06799772b1cd', 'type' => 'INITIATED_BY_CONTACT'],
                'metadata' => ['external_message_id' => 'wamid.HBgNNTUyNDk5MjU0MjM2MxUCABIYFjNFQjAzRjM1QjFCOEE1QzUxQzA2Q0QA'],
            ],
        ];
    }

    public function test_parses_confirmed_real_text_payload(): void
    {
        $parsed = (new PoliMessageParser())->parse($this->realTextPayload());

        $this->assertNotNull($parsed);
        $this->assertSame(ParsedPoliMessage::TYPE_TEXT, $parsed->type);
        $this->assertSame('Teste', $parsed->text);
        $this->assertSame('d5e8a972-6360-11f1-9d75-06799772b1cd', $parsed->contactUuid);
        $this->assertSame('5524992542363', $parsed->contactPhone);
        $this->assertSame('6e592720-8442-11f1-9d75-06799772b1cd', $parsed->attendanceUuid);
        $this->assertSame('wamid.HBgNNTUyNDk5MjU0MjM2MxUCABIYFjNFQjAzRjM1QjFCOEE1QzUxQzA2Q0QA', $parsed->messageId);
    }

    public function test_is_relevant_event_rejects_outbound_messages(): void
    {
        $payload = $this->realTextPayload();
        $payload['value']['direction'] = 'OUT';

        $this->assertFalse((new PoliMessageParser())->isRelevantEvent($payload));
    }

    public function test_is_relevant_event_accepts_confirmed_inbound_shape(): void
    {
        $this->assertTrue((new PoliMessageParser())->isRelevantEvent($this->realTextPayload()));
    }

    public function test_detects_best_effort_image_component(): void
    {
        $payload = $this->realTextPayload();
        $payload['value']['components'] = ['image' => ['url' => 'https://poli.example/media/print.jpg']];

        $parsed = (new PoliMessageParser())->parse($payload);

        $this->assertSame(ParsedPoliMessage::TYPE_IMAGE, $parsed->type);
        $this->assertSame('https://poli.example/media/print.jpg', $parsed->mediaUrl);
    }

    public function test_logs_raw_payload_when_content_shape_is_unrecognized(): void
    {
        Log::shouldReceive('warning')->once();

        $payload = $this->realTextPayload();
        $payload['value']['components'] = ['some_unknown_component' => ['foo' => 'bar']];

        $parsed = (new PoliMessageParser())->parse($payload);

        $this->assertSame(ParsedPoliMessage::TYPE_UNKNOWN, $parsed->type);
    }
}
