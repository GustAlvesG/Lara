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

    /**
     * O menu de departamentos saindo, como a Poli entrega depois de o webhook
     * de saída ser ligado. Reparar no `event: "sent"` e no `value.uuid` —
     * é ele, e não o wamid, que a resposta devolve no contexto.
     */
    private function realOutgoingListPayload(): array
    {
        return [
            'object' => 'message',
            'event' => 'sent',
            'account_uuid' => 'a9c0bc53-430e-11f1-9d75-06799772b1cd',
            'uuid' => 'bot-0-99923-68849486-msg-17901883491664-',
            'value' => [
                'uuid' => 'bot-0-99923-68849486-msg-17901883491664-',
                'event' => 'MESSAGE',
                'type' => 'CHAT',
                'direction' => 'OUT',
                'timestamp' => 1790188349,
                'contact' => ['uuid' => '59bd92c9-8467-11f1-9d75-06799772b1cd'],
                'components' => [
                    'body' => ['text' => 'Olá! Seja bem vindo(a) ao Clube dos Funcionários da CSN.'],
                    'button' => 'Ver opções',
                    'section' => [[
                        'id' => '1781528942331',
                        'text' => 'Departamentos',
                        'rows' => [
                            ['id' => '1781529293152', 'messageOption' => [
                                'title' => 'Financeiro',
                                'description' => 'Consulte seus débitos ou outras pendências.',
                            ]],
                            ['id' => '1784570154383', 'messageOption' => [
                                'title' => 'Carro de Aplicativo',
                                'description' => 'Carro, moto ou táxi',
                            ]],
                        ],
                    ]],
                    'name' => 'polichat_list_message_556542',
                ],
                'attendance' => ['uuid' => '21ab48ee-b77d-11f1-9d75-06799772b1cd'],
                'metadata' => ['external_message_id' => 'wamid.HBgNNTUyNDk4MTE5NzI2ORUCABEYEjA2NzIxMzg3RDU1RUNBMjg1MAA='],
            ],
        ];
    }

    public function test_reconhece_e_extrai_o_menu_enviado(): void
    {
        $parser = new PoliMessageParser();
        $payload = $this->realOutgoingListPayload();

        $this->assertTrue($parser->isOutgoingListMessage($payload));

        // Continua fora do fluxo do WhatsApp: o evento é "sent", não "received".
        $this->assertFalse($parser->isRelevantEvent($payload));

        $list = $parser->parseOutgoingList($payload);

        // A chave é o value.uuid — o wamid não é citado pelo contexto da resposta.
        $this->assertSame('bot-0-99923-68849486-msg-17901883491664-', $list['poli_message_uuid']);
        $this->assertSame('21ab48ee-b77d-11f1-9d75-06799772b1cd', $list['attendance_uuid']);
        $this->assertSame('59bd92c9-8467-11f1-9d75-06799772b1cd', $list['contact_uuid']);
        $this->assertSame('polichat_list_message_556542', $list['template_name']);
        $this->assertSame(1790188349, $list['sent_at']->timestamp);

        $this->assertSame([
            ['title' => 'Financeiro', 'description' => 'Consulte seus débitos ou outras pendências.'],
            ['title' => 'Carro de Aplicativo', 'description' => 'Carro, moto ou táxi'],
        ], $list['rows']);
    }

    /** Mensagem de saída sem lista não vira menu. */
    public function test_saida_sem_lista_nao_e_menu(): void
    {
        $payload = $this->realOutgoingListPayload();
        $payload['value']['components'] = ['body' => ['text' => 'Informe seu nome completo, por favor.']];

        $parser = new PoliMessageParser();

        $this->assertFalse($parser->isOutgoingListMessage($payload));
        $this->assertNull($parser->parseOutgoingList($payload));
    }

    /**
     * O toque devolve o uuid do menu; o texto digitado vem com `context: null`.
     * É a diferença que sustenta a trava inteira.
     */
    public function test_extrai_o_menu_citado_pelo_toque(): void
    {
        $parser = new PoliMessageParser();

        $payload = $this->realTextPayload();
        $payload['value']['components'] = ['body' => ['text' => "Carro de Aplicativo\nCarro, moto ou táxi"]];
        $payload['value']['context'] = [
            'type' => 'message',
            'message' => ['uuid' => 'bot-0-99923-68849486-msg-17901883491664-'],
        ];

        $this->assertSame(
            'bot-0-99923-68849486-msg-17901883491664-',
            $parser->parse($payload)->contextMessageUuid
        );

        // A quebra entre título e descrição vira espaço na sanitização.
        $this->assertSame('Carro de Aplicativo Carro, moto ou táxi', $parser->parse($payload)->text);

        $digitado = $this->realTextPayload();
        $digitado['value']['context'] = null;

        $this->assertNull($parser->parse($digitado)->contextMessageUuid);
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

    public function test_parses_confirmed_real_image_payload(): void
    {
        $url = 'https://cdn.polichat.io/company/52129/media/received/wamid.ABC123.jpeg';

        $payload = $this->realTextPayload();
        $payload['value']['type'] = 'IMAGE';
        $payload['value']['components'] = [
            'attachments' => [
                ['type' => 'image', 'media' => ['url' => $url], 'mime_type' => 'image/jpeg'],
            ],
        ];

        $parser = new PoliMessageParser();

        $this->assertTrue($parser->isRelevantEvent($payload));

        $parsed = $parser->parse($payload);
        $this->assertSame(ParsedPoliMessage::TYPE_IMAGE, $parsed->type);
        $this->assertSame($url, $parsed->mediaUrl);
    }

    public function test_normalizes_escaped_slashes_in_media_url(): void
    {
        $payload = $this->realTextPayload();
        $payload['value']['type'] = 'IMAGE';
        $payload['value']['components'] = [
            'attachments' => [
                ['type' => 'image', 'media' => ['url' => 'https:\\/\\/cdn.polichat.io\\/media\\/x.jpeg'], 'mime_type' => 'image/jpeg'],
            ],
        ];

        $parsed = (new PoliMessageParser())->parse($payload);

        $this->assertSame('https://cdn.polichat.io/media/x.jpeg', $parsed->mediaUrl);
    }

    public function test_detects_best_effort_image_component_fallback(): void
    {
        $payload = $this->realTextPayload();
        $payload['value']['components'] = ['image' => ['url' => 'https://poli.example/media/print.jpg']];

        $parsed = (new PoliMessageParser())->parse($payload);

        $this->assertSame(ParsedPoliMessage::TYPE_IMAGE, $parsed->type);
        $this->assertSame('https://poli.example/media/print.jpg', $parsed->mediaUrl);
    }

    public function test_sanitizes_escaped_slashes_and_trailing_json_noise(): void
    {
        $payload = $this->realTextPayload();
        $payload['value']['components'] = ['body' => ['text' => 'Pedi um Uber\\/99\\/Taxi\\n}']];

        $parsed = (new PoliMessageParser())->parse($payload);

        $this->assertSame(ParsedPoliMessage::TYPE_TEXT, $parsed->type);
        $this->assertSame('Pedi um Uber/99/Taxi', $parsed->text);
    }

    public function test_sanitizes_real_control_characters_and_stray_braces(): void
    {
        $payload = $this->realTextPayload();
        $payload['value']['components'] = ['body' => ['text' => "{Gustavo Alves}\n"]];

        $parsed = (new PoliMessageParser())->parse($payload);

        $this->assertSame('Gustavo Alves', $parsed->text);
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
