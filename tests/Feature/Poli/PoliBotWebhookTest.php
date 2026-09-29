<?php

namespace Tests\Feature\Poli;

use App\Models\BotFlow;
use App\Models\BotSession;
use App\Models\PoliListMessage;
use App\Models\PoliMessage;
use App\Models\UberAccessRequest;
use App\Models\UberAccessRequestMessage;
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
    private const LARA = 'o-lara';

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // Horário comercial fixo: o fluxo padrão tem horário de atendimento.
        \Illuminate\Support\Carbon::setTestNow('2026-09-23 10:00:00');   // quarta-feira

        foreach ([
            '2026_07_20_150000_create_uber_access_requests_tables.php',
            '2026_07_21_120000_add_matricula_to_uber_access_requests.php',
            '2026_08_27_170000_add_member_validation_to_uber_access_requests.php',
            '2026_09_10_100000_add_member_validation_type_to_uber_access_requests.php',
            '2026_09_23_190000_create_poli_list_messages_table.php',
            '2026_09_24_120000_add_ordering_to_uber_access_request_messages.php',
            '2026_01_05_141304_banco_de_horas.php',
            '2026_09_25_160000_create_poli_bot_tables.php',
            '2026_09_29_120000_add_lara_owner_to_bot_sessions.php',
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
            'poli.bot.user_uuid' => self::LARA,
        ]);

        foreach (DefaultFlows::all() as $slug => $fluxo) {
            BotFlow::create(['slug' => $slug, 'name' => $fluxo['name'], 'active' => true, 'definition' => $fluxo['definition']]);
        }

        \Illuminate\Support\Facades\Queue::fake([\App\Jobs\ConfirmPoliBotHandoff::class]);
        Http::preventStrayRequests();
        Http::fake(fn (Request $r) => $r->method() === 'GET'
            ? Http::response(['current_attendance' => $this->atendimentoNaApi], 200)
            : Http::response(['uuid' => 'out-' . (++$this->n), 'ack' => 'CREATED'], 201));
    }

    /** O que o GET /contacts/{uuid}?include=current_attendance devolve. */
    private ?array $atendimentoNaApi = null;

    private function webhook(array $payload, array $headers = [])
    {
        return $this->postJson('/api/webhooks/whatsapp', $payload, ['Authorization' => 'Bearer token-da-api'] + $headers);
    }

    private function recebida(string $tipo, array $components, ?string $contexto = null, ?string $atendente = self::LARA): array
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
                'attendance' => [
                    'uuid' => 'att-1',
                    'status' => 'IN_PROGRESS',
                    'attendant' => $atendente ? ['uuid' => $atendente] : null,
                ],
                'metadata' => ['external_message_id' => 'wamid-' . $this->n],
            ],
        ];
    }

    /**
     * O toque no menu do bot da Poli é uma conversa sem atendente: a escuta
     * do Uber trabalha nela, e a Lara só compara em sombra — mesmo no modo on.
     */
    public function test_conversa_do_bot_da_poli_a_escuta_do_uber_abre_o_pedido_e_a_lara_nao_fala(): void
    {
        PoliListMessage::create([
            'poli_message_uuid' => 'menu-poli', 'attendance_uuid' => 'att-1', 'contact_uuid' => self::CONTACT,
            'rows' => [['title' => 'Carro de Aplicativo', 'description' => 'Carro, moto ou táxi']],
        ]);

        $this->webhook($this->recebida('CHAT', ['body' => ['text' => "Carro de Aplicativo\nCarro, moto ou táxi"]], 'menu-poli', atendente: null))
            ->assertOk();

        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_MATRICULA, UberAccessRequest::sole()->status);
        Http::assertNothingSent();
    }

    /**
     * Na conversa do O Lara as mensagens da Lara têm a mesma assinatura das
     * do bot da Poli: a escuta ficaria duplicando o pedido.
     */
    public function test_conversa_do_o_lara_a_lara_responde_e_a_escuta_do_uber_nao_abre_pedido(): void
    {
        PoliListMessage::create([
            'poli_message_uuid' => 'menu-poli', 'attendance_uuid' => 'att-1', 'contact_uuid' => self::CONTACT,
            'rows' => [['title' => 'Carro de Aplicativo', 'description' => 'Carro, moto ou táxi']],
        ]);

        $this->webhook($this->recebida('CHAT', ['body' => ['text' => "Carro de Aplicativo\nCarro, moto ou táxi"]], 'menu-poli'))
            ->assertOk();

        $this->assertSame(0, UberAccessRequest::count());
        $this->assertSame('carro-de-aplicativo', BotSession::find(self::CONTACT)->flow_slug);
        Http::assertSent(fn (Request $r) => str_contains((string) data_get($r->data(), 'components.body.text'), '*matrícula*'));
    }

    /**
     * O caminho inteiro da entrada: o toque no menu do bot da Poli, a
     * transferência para O Lara (mensagem de sistema, direction EMPTY) e a
     * Lara abrindo o fluxo pelo toque, sem esperar outra mensagem.
     */
    public function test_transferencia_para_o_lara_abre_o_fluxo_pelo_toque_no_menu_da_poli(): void
    {
        $this->webhook($this->recebida('CHAT', ['body' => ['text' => "Carro de Aplicativo\nCarro, moto ou táxi"]], atendente: null))
            ->assertOk();
        Http::assertNothingSent();

        $this->webhook([
            'object' => 'message', 'event' => 'received', 'account_uuid' => 'acc-uuid', 'uuid' => 'sys-1',
            'value' => [
                'uuid' => 'sys-1', 'event' => 'SYSTEM', 'type' => 'ATTENDANCE_REDIRECTED', 'direction' => 'EMPTY',
                'contact' => ['uuid' => self::CONTACT, 'attributes' => ['name' => 'Gustavo', 'phone' => '5524992542363']],
                'attendance' => ['uuid' => 'att-lara', 'type' => 'INITIATED_BY_FORWARDING', 'status' => 'IN_PROGRESS', 'attendant' => ['uuid' => self::LARA]],
            ],
        ])->assertOk();

        $sessao = BotSession::find(self::CONTACT);
        $this->assertSame('carro-de-aplicativo', $sessao->flow_slug);
        $this->assertSame('att-lara', $sessao->attendance_uuid);
        $this->assertFalse($sessao->isHuman(), 'a transferência para O Lara não é humano assumindo');
        Http::assertSent(fn (Request $r) => str_contains((string) data_get($r->data(), 'components.body.text'), '*matrícula*'));
    }

    /**
     * O `attendant` na raiz do contato fica desatualizado depois de um
     * distribute (medido em 29/09/2026): vale o do atendimento.
     */
    public function test_vale_o_atendente_do_atendimento_e_nao_o_da_raiz_do_contato(): void
    {
        $payload = $this->recebida('CHAT', ['body' => ['text' => 'oi']]);
        $payload['value']['contact']['attendant'] = ['uuid' => 'atendente-antigo'];

        $this->webhook($payload)->assertOk();

        Http::assertSent(fn (Request $r) => ($r->data()['type'] ?? null) === 'TEMPLATE');
    }

    /**
     * Reenvio da Poli: o atendimento é conferido na API antes de a Lara agir.
     */
    public function test_evento_reenviado_guarda_os_headers_e_confere_o_dono_na_api(): void
    {
        $this->atendimentoNaApi = ['uuid' => 'att-1', 'status' => 'IN_PROGRESS', 'attendant' => ['uuid' => 'atendente-ana']];

        $this->webhook($this->recebida('CHAT', ['body' => ['text' => 'oi']]), [
            'X-Webhook-Attempt' => '2',
            'X-Webhook-Delivery-Id' => 'entrega-9',
        ])->assertOk();

        $linha = UberAccessRequestMessage::sole();
        $this->assertSame(['attempt' => '2', 'delivery_id' => 'entrega-9'], $linha->raw_payload['_webhook']);
        $this->assertTrue(BotSession::find(self::CONTACT)->isHuman());
        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST');
    }

    public function test_modo_sombra_a_escuta_do_uber_continua_abrindo_o_pedido(): void
    {
        config(['poli.bot.mode' => BotEngine::MODE_SHADOW]);

        PoliListMessage::create([
            'poli_message_uuid' => 'menu-poli', 'attendance_uuid' => 'att-1', 'contact_uuid' => self::CONTACT,
            'rows' => [['title' => 'Carro de Aplicativo', 'description' => 'Carro, moto ou táxi']],
        ]);

        $this->webhook($this->recebida('CHAT', ['body' => ['text' => "Carro de Aplicativo\nCarro, moto ou táxi"]], 'menu-poli', atendente: null))
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

    public function test_resposta_em_atendimento_aberto_pela_empresa_fica_com_quem_escreveu(): void
    {
        $payload = $this->recebida('CHAT', ['body' => ['text' => 'Olá']], atendente: null);
        $payload['value']['attendance'] = ['uuid' => 'att-cobranca', 'type' => 'INITIATED_BY_BUSINESS', 'status' => 'IN_PROGRESS'];

        $this->webhook($payload)->assertOk();

        $this->assertSame(1, PoliMessage::where('direction', 'IN')->count());
        $this->assertSame(0, PoliMessage::where('direction', 'OUT')->count());
        Http::assertNothingSent();
    }

    public function test_so_a_conversa_do_o_lara_no_modo_on_usa_a_espera_do_bot(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        config(['poli.bot.inbound_max_lag_seconds' => 6, 'poli.inbound.max_delivery_lag_seconds' => 20]);

        $agora = now()->toIso8601String();
        $comCriacao = function (?string $atendente = self::LARA) use ($agora) {
            $p = $this->recebida('CHAT', ['body' => ['text' => 'oi']], atendente: $atendente);
            $p['value']['metadata']['created_at'] = $agora;

            return $p;
        };

        $this->webhook($comCriacao())->assertOk();
        $this->webhook($comCriacao(null))->assertOk();
        config(['poli.bot.mode' => BotEngine::MODE_SHADOW]);
        $this->webhook($comCriacao())->assertOk();

        $esperas = [];
        \Illuminate\Support\Facades\Queue::assertPushed(
            \App\Jobs\ProcessUberAccessRequestMessage::class,
            function ($job) use (&$esperas) {
                $esperas[] = (int) round(now()->diffInSeconds($job->delay, false));

                return true;
            },
        );

        $this->assertEqualsWithDelta(6, $esperas[0], 1, 'on, conversa do O Lara: teto do bot');
        $this->assertEqualsWithDelta(20, $esperas[1], 1, 'on, conversa do bot da Poli: teto da escuta do Uber');
        $this->assertEqualsWithDelta(6, $esperas[2], 1, 'shadow, conversa do O Lara: também respondida, teto do bot');
    }

    public function test_modo_off_nao_toca_nas_tabelas_do_bot(): void
    {
        config(['poli.bot.mode' => BotEngine::MODE_OFF]);

        $this->webhook($this->recebida('CHAT', ['body' => ['text' => 'oi']]))->assertOk();

        $this->assertSame(0, PoliMessage::count());
        $this->assertSame(0, BotSession::count());
    }
}
