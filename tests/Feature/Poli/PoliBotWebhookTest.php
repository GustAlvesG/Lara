<?php

namespace Tests\Feature\Poli;

use App\Models\BotFlow;
use App\Models\BotSession;
use App\Models\PoliListMessage;
use App\Models\PoliMessage;
use App\Models\UberAccessRequest;
use App\Services\PoliBot\BotEngine;
use App\Services\PoliBot\DefaultFlows;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O bot pelo caminho de produção: POST no webhook → controller → job (fila
 * síncrona no phpunit.xml) → motor. Aqui entra o que só aparece com as peças
 * juntas: a escuta do Uber desligando no modo on, a mídia que o fluxo do Uber
 * não lê, o ACK sem `value`, a conta errada.
 */
class PoliBotWebhookTest extends TestCase
{
    private const CONTACT = 'contato-webhook';

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            '2026_07_20_150000_create_uber_access_requests_tables.php',
            '2026_07_21_120000_add_matricula_to_uber_access_requests.php',
            '2026_08_27_170000_add_member_validation_to_uber_access_requests.php',
            '2026_09_10_100000_add_member_validation_type_to_uber_access_requests.php',
            '2026_09_23_190000_create_poli_list_messages_table.php',
            '2026_09_24_120000_add_ordering_to_uber_access_request_messages.php',
            '2026_01_05_141304_banco_de_horas.php',
            '2026_09_25_160000_create_poli_bot_tables.php',
        ] as $migration) {
            (require base_path('database/migrations/' . $migration))->up();
        }

        config([
            'services.api.token' => 'token-da-api',
            'poli.base_url' => 'https://foundation-api.poli.digital/v3',
            'poli.token' => 'token-teste',
            'poli.account_uuid' => 'acc-uuid',
            'poli.channel_uuid' => 'chan-uuid',
            'poli.http.retry_sleep_ms' => 0,
            'poli.bot.mode' => BotEngine::MODE_ON,
            'poli.bot.live_contacts' => [],
        ]);

        foreach (DefaultFlows::all() as $slug => $fluxo) {
            BotFlow::create(['slug' => $slug, 'name' => $fluxo['name'], 'active' => true, 'definition' => $fluxo['definition']]);
        }

        Http::fake(fn () => Http::response(['uuid' => 'out-' . (++$this->n), 'ack' => 'CREATED'], 201));
    }

    private function webhook(array $payload)
    {
        return $this->postJson('/api/webhooks/whatsapp', $payload, ['Authorization' => 'Bearer token-da-api']);
    }

    private function recebida(string $tipo, array $components, ?string $contexto = null): array
    {
        $this->n++;

        return [
            'object' => 'message',
            'event' => 'received',
            'account_uuid' => 'acc-uuid',
            'uuid' => 'rec-' . $this->n,
            'value' => [
                'uuid' => 'rec-' . $this->n,
                'event' => 'MESSAGE',
                'type' => $tipo,
                'direction' => 'IN',
                'author' => ['type' => 'CONTACT', 'uuid' => self::CONTACT],
                'contact' => ['uuid' => self::CONTACT, 'attributes' => ['name' => 'Gustavo', 'phone' => '5524992542363']],
                'context' => $contexto ? ['type' => 'message', 'message' => ['uuid' => $contexto]] : null,
                'components' => $components,
                'attendance' => ['uuid' => 'att-1'],
                'metadata' => ['external_message_id' => 'wamid-' . $this->n],
            ],
        ];
    }

    public function test_modo_on_o_bot_responde_e_a_escuta_do_uber_nao_abre_pedido(): void
    {
        // Um menu da POLI indexado, com o toque nele: é exatamente o que faria
        // a escuta abrir um pedido "aguardando_matricula".
        PoliListMessage::create([
            'poli_message_uuid' => 'menu-poli', 'attendance_uuid' => 'att-1', 'contact_uuid' => self::CONTACT,
            'rows' => [['title' => 'Carro de Aplicativo', 'description' => 'Carro, moto ou táxi']],
        ]);

        $this->webhook($this->recebida('CHAT', ['body' => ['text' => "Carro de Aplicativo\nCarro, moto ou táxi"]], 'menu-poli'))
            ->assertOk();

        $this->assertSame(0, UberAccessRequest::count(), 'No modo on quem cria o pedido é o fluxo do bot');
        $this->assertSame('carro-de-aplicativo', BotSession::find(self::CONTACT)->flow_slug);
        Http::assertSent(fn (Request $r) => str_contains((string) data_get($r->data(), 'components.body.text'), '*matrícula*'));
    }

    public function test_modo_sombra_a_escuta_do_uber_continua_abrindo_o_pedido(): void
    {
        config(['poli.bot.mode' => BotEngine::MODE_SHADOW]);

        PoliListMessage::create([
            'poli_message_uuid' => 'menu-poli', 'attendance_uuid' => 'att-1', 'contact_uuid' => self::CONTACT,
            'rows' => [['title' => 'Carro de Aplicativo', 'description' => 'Carro, moto ou táxi']],
        ]);

        $this->webhook($this->recebida('CHAT', ['body' => ['text' => "Carro de Aplicativo\nCarro, moto ou táxi"]], 'menu-poli'))
            ->assertOk();

        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_MATRICULA, UberAccessRequest::sole()->status);
        Http::assertNothingSent();
    }

    public function test_audio_recebe_pedido_de_texto(): void
    {
        $this->webhook($this->recebida('CHAT', ['body' => ['text' => 'oi']]))->assertOk();
        $this->webhook($this->recebida('AUDIO', ['attachments' => [['type' => 'audio', 'media' => ['url' => 'https://cdn/a.ogg']]]]))->assertOk();

        // A correção, e logo atrás o menu de novo (o passo é uma lista).
        $ultimas = PoliMessage::where('direction', 'OUT')->orderByDesc('id')->take(2)->pluck('texto');
        $this->assertStringContainsString('só consigo ler mensagens de texto', $ultimas[1]);
        $this->assertStringContainsString('[template', $ultimas[0]);
    }

    public function test_ack_sem_value_e_aceito_e_atualiza_a_mensagem(): void
    {
        $this->webhook($this->recebida('CHAT', ['body' => ['text' => 'oi']]))->assertOk();
        $uuid = PoliMessage::where('direction', 'OUT')->value('uuid');

        $this->webhook(['object' => 'message', 'event' => 'ack', 'account_uuid' => 'acc-uuid', 'uuid' => $uuid, 'status' => 'READ_BY_CLIENT'])
            ->assertOk();

        $this->assertSame('READ_BY_CLIENT', PoliMessage::where('uuid', $uuid)->value('ack'));
    }

    public function test_evento_de_outra_conta_e_ignorado(): void
    {
        $payload = $this->recebida('CHAT', ['body' => ['text' => 'oi']]);
        $payload['account_uuid'] = 'outra-conta';

        $this->webhook($payload)->assertOk()->assertJson(['status' => 'ignored']);

        $this->assertSame(0, PoliMessage::count());
        Http::assertNothingSent();
    }

    public function test_atendente_escrevendo_silencia_o_bot(): void
    {
        $this->webhook($this->recebida('CHAT', ['body' => ['text' => 'oi']]))->assertOk();

        $this->webhook(['object' => 'message', 'event' => 'sent', 'account_uuid' => 'acc-uuid', 'uuid' => 'x', 'value' => [
            'uuid' => 'msg-atendente', 'event' => 'MESSAGE', 'type' => 'CHAT', 'direction' => 'OUT',
            'author' => ['type' => 'USER', 'uuid' => 'atendente-1'],
            'contact' => ['uuid' => self::CONTACT],
            'components' => ['body' => ['text' => 'Oi, sou a Ana.']],
            'attendance' => ['uuid' => 'att-1', 'closed_reason' => null],
            'metadata' => ['external_message_id' => 'wamid-atendente'],
        ]])->assertOk();

        $this->assertTrue(BotSession::find(self::CONTACT)->isHuman());

        $antes = PoliMessage::where('direction', 'OUT')->count();
        $this->webhook($this->recebida('CHAT', ['body' => ['text' => 'obrigado']]))->assertOk();
        $this->assertSame($antes, PoliMessage::where('direction', 'OUT')->count());
    }

    public function test_modo_off_nao_toca_nas_tabelas_do_bot(): void
    {
        config(['poli.bot.mode' => BotEngine::MODE_OFF]);

        $this->webhook($this->recebida('CHAT', ['body' => ['text' => 'oi']]))->assertOk();

        $this->assertSame(0, PoliMessage::count());
        $this->assertSame(0, BotSession::count());
    }
}
