<?php

namespace Tests\Unit;

use App\Services\Poli\PoliMessageService;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Envio de texto pela Poli. Sem banco de propósito: nada aqui toca Eloquent,
 * e teste que não precisa de tabela não deve arrastar RefreshDatabase junto.
 *
 * O formato da requisição é o comprovado com entrega no aparelho em
 * 25/09/2026 — ver config/poli.php.
 */
class PoliMessageServiceTest extends TestCase
{
    private const BASE = 'https://foundation-api.poli.digital/v3';
    private const ACCOUNT = 'a9c0bc53-430e-11f1-9d75-06799772b1cd';
    private const CHANNEL = 'c2e53327-633f-11f1-9d75-06799772b1cd';
    private const CONTACT = 'd5e8a972-6360-11f1-9d75-06799772b1cd';
    private const PHONE = '5524992542363';

    protected function setUp(): void
    {
        parent::setUp();

        // O log real não interessa aqui, mas o mascaramento sim — ver o
        // teste que confere que o token não vaza.
        Log::spy();

        config()->set([
            'poli.enabled' => true,
            'poli.base_url' => self::BASE,
            'poli.token' => 'token-secreto-de-teste',
            'poli.account_uuid' => self::ACCOUNT,
            'poli.channel_uuid' => self::CHANNEL,
            'poli.http.retry_sleep_ms' => 0,
        ]);
    }

    private function service(): PoliMessageService
    {
        return app(PoliMessageService::class);
    }

    private function aceito(string $uuid = 'msg-123'): array
    {
        return ['uuid' => $uuid, 'event' => 'MESSAGE', 'type' => 'TEXT', 'ack' => 'CREATED', 'direction' => 'OUT'];
    }

    public function test_com_contact_uuid_envia_pelo_contato_no_formato_validado(): void
    {
        Http::fake(['*' => Http::response($this->aceito(), 201)]);

        $result = $this->service()->sendTextByPhone(self::PHONE, 'Seu carro chegou.', null, self::CONTACT);

        $this->assertTrue($result->success);
        $this->assertSame('msg-123', $result->messageUuid);
        $this->assertSame('CREATED', $result->status);
        $this->assertNull($result->error);

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->url() === self::BASE . '/contacts/' . self::CONTACT . '/messages'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer token-secreto-de-teste')
                && $body === [
                    'provider' => 'WHATSAPP',
                    'account_channel_uuid' => self::CHANNEL,
                    'type' => 'TEXT',
                    'version' => 'v3',
                    'components' => ['body' => ['text' => 'Seu carro chegou.']],
                ];
        });
    }

    /**
     * A regressão que este arquivo existe para impedir: o endpoint da conta
     * responde 200 a qualquer corpo e não entrega nada.
     */
    public function test_nunca_usa_o_endpoint_da_conta_que_nao_entrega(): void
    {
        Http::fake(['*' => Http::response($this->aceito(), 201)]);

        $this->service()->sendTextByPhone(self::PHONE, 'Oi', null, self::CONTACT);
        $this->service()->sendTextByPhone(self::PHONE, 'Oi');

        Http::assertNotSent(fn (Request $r) => $r->url() === self::BASE . '/accounts/' . self::ACCOUNT . '/messages');
    }

    public function test_sem_contact_uuid_envia_por_telefone_pedindo_o_contato(): void
    {
        Http::fake(['*' => Http::response($this->aceito() + ['contact' => ['uuid' => self::CONTACT]], 201)]);

        $result = $this->service()->sendTextByPhone('+55 (24) 99254-2363', 'Oi');

        $this->assertTrue($result->success);
        Http::assertSent(fn (Request $r) => $r->url()
            === self::BASE . '/accounts/' . self::ACCOUNT . '/contacts/' . self::PHONE . '/messages?include=contact');
    }

    public function test_canal_informado_substitui_o_do_env(): void
    {
        Http::fake(['*' => Http::response($this->aceito(), 201)]);

        $this->service()->sendTextByPhone(self::PHONE, 'Oi', 'outro-canal', self::CONTACT);

        Http::assertSent(fn (Request $r) => $r->data()['account_channel_uuid'] === 'outro-canal');
    }

    public function test_canal_pelo_nome_antigo_da_config_ainda_vale(): void
    {
        config()->set(['poli.channel_uuid' => null, 'poli.default_channel_uuid' => 'canal-antigo']);
        Http::fake(['*' => Http::response($this->aceito(), 201)]);

        $result = $this->service()->sendTextByPhone(self::PHONE, 'Oi', null, self::CONTACT);

        $this->assertTrue($result->success);
        Http::assertSent(fn (Request $r) => $r->data()['account_channel_uuid'] === 'canal-antigo');
    }

    public function test_sem_canal_configurado_nao_envia(): void
    {
        config()->set(['poli.channel_uuid' => null, 'poli.default_channel_uuid' => null]);
        Http::fake();

        $result = $this->service()->sendTextByPhone(self::PHONE, 'Oi', null, self::CONTACT);

        $this->assertFalse($result->success);
        $this->assertSame('canal de envio não configurado', $result->error);
        Http::assertNothingSent();
    }

    /**
     * 2xx sem uuid é exatamente a cara do endpoint que aceita tudo e não
     * entrega. Não é sucesso — e também não é retentável: se por acaso a
     * mensagem saiu, repetir mandaria duas.
     */
    public function test_2xx_sem_uuid_nao_conta_como_enviado(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $result = $this->service()->sendTextByPhone(self::PHONE, 'Oi', null, self::CONTACT);

        $this->assertFalse($result->success);
        $this->assertFalse($result->retryable);
        $this->assertStringContainsString('sem uuid', $result->error);
        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message) => $message === 'Poli: envio respondido sem uuid — não confirmado')
            ->once();
    }

    public function test_429_devolve_rate_limited_com_retry_after(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '30']),
        ]);

        $result = $this->service()->sendTextByPhone(self::PHONE, 'Seu carro chegou.', null, self::CONTACT);

        $this->assertFalse($result->success);
        $this->assertTrue($result->isRateLimited());
        $this->assertTrue($result->retryable);
        $this->assertSame(429, $result->httpStatus);
        $this->assertSame(30, $result->retryAfter);
        $this->assertSame('Too Many Attempts.', $result->error);
    }

    public function test_429_sem_retry_after_deixa_a_espera_a_cargo_do_job(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Too Many Attempts.'], 429)]);

        $result = $this->service()->sendTextByPhone(self::PHONE, 'Oi', null, self::CONTACT);

        $this->assertTrue($result->isRateLimited());
        $this->assertNull($result->retryAfter);
    }

    /**
     * Formato de erro confirmado da Poli: `message` + bag `errors`. E 422 é
     * payload errado: o client não repete, e o resultado não é retentável.
     */
    public function test_422_traz_a_mensagem_e_nao_e_repetido(): void
    {
        Http::fake([
            '*' => Http::response([
                'message' => 'O campo account_channel_uuid é obrigatório.',
                'errors' => ['account_channel_uuid' => ['O campo account_channel_uuid é obrigatório.']],
            ], 422),
        ]);

        $result = $this->service()->sendTextByPhone(self::PHONE, 'Oi', null, self::CONTACT);

        $this->assertFalse($result->success);
        $this->assertSame(422, $result->httpStatus);
        $this->assertStringContainsString('account_channel_uuid', $result->error);
        $this->assertFalse($result->retryable);
        Http::assertSentCount(1);
    }

    public function test_5xx_e_repetido_na_hora_e_continua_retentavel(): void
    {
        config()->set('poli.http.retries', 2);
        Http::fake(['*' => Http::response('', 503)]);

        $result = $this->service()->sendTextByPhone(self::PHONE, 'Oi', null, self::CONTACT);

        $this->assertFalse($result->success);
        $this->assertTrue($result->retryable);
        $this->assertSame('HTTP 503', $result->error);
        Http::assertSentCount(3);
    }

    /**
     * Um número brasileiro sem o 55 tem 11 dígitos e é indistinguível de um
     * E.164 curto de outro país. Completar por conta própria pode mandar a
     * mensagem para um estranho — então recusa, sem sair da nossa casa.
     */
    public function test_sem_contact_uuid_recusa_telefone_sem_ddi_sem_chamar_a_api(): void
    {
        Http::fake();

        $result = $this->service()->sendTextByPhone('24992542363', 'Oi');

        $this->assertFalse($result->success);
        $this->assertFalse($result->retryable);
        $this->assertStringContainsString('E.164', $result->error);
        Http::assertNothingSent();
    }

    /**
     * Com o contact_uuid o telefone nem é usado — um telefone estranho no
     * pedido não pode impedir o aviso.
     */
    public function test_com_contact_uuid_telefone_estranho_nao_impede_o_envio(): void
    {
        Http::fake(['*' => Http::response($this->aceito(), 201)]);

        $result = $this->service()->sendTextByPhone('24992542363', 'Oi', null, self::CONTACT);

        $this->assertTrue($result->success);
    }

    /**
     * O log da aplicação é lido por mais gente do que o .env: nem o token
     * inteiro nem o telefone do associado podem cair nele.
     */
    public function test_log_mascara_token_e_telefone(): void
    {
        Http::fake(['*' => Http::response($this->aceito(), 201)]);

        $this->service()->sendTextByPhone(self::PHONE, 'Oi');

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context = []) {
            return $message === 'Poli: enviando mensagem de texto'
                && $context['token'] === '***este'
                && $context['phone'] === '5524*******63';
        })->once();
    }

    public function test_recusa_texto_vazio_sem_chamar_a_api(): void
    {
        Http::fake();

        $result = $this->service()->sendTextByPhone(self::PHONE, '   ', null, self::CONTACT);

        $this->assertFalse($result->success);
        $this->assertSame('texto vazio', $result->error);
        Http::assertNothingSent();
    }

    public function test_integracao_desligada_nao_envia(): void
    {
        config()->set('poli.enabled', false);
        Http::fake();

        $result = $this->service()->sendTextByPhone(self::PHONE, 'Oi', null, self::CONTACT);

        $this->assertFalse($result->success);
        $this->assertFalse($result->retryable);
        Http::assertNothingSent();
    }

    public function test_falha_de_conexao_nao_lanca(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $result = $this->service()->sendTextByPhone(self::PHONE, 'Oi', null, self::CONTACT);

        $this->assertFalse($result->success);
        $this->assertTrue($result->retryable);
        $this->assertStringContainsString('Connection refused', $result->error);
    }

    /**
     * Flagrado num envio real: o Laravel converte em ConnectionException só o
     * que o Guzzle classifica como ConnectException (DNS, recusa, timeout).
     * Um certificado que não valida (cURL 60) sobe como RequestException e
     * escaparia de um catch estreito — derrubando o Job em vez de virar um
     * resultado. Este teste é o que impede a regressão.
     */
    public function test_erro_de_transporte_fora_do_connection_exception_nao_lanca(): void
    {
        Http::fake(function () {
            throw new RequestException(
                'cURL error 60: SSL certificate problem',
                new GuzzleRequest('POST', self::BASE . '/contacts/' . self::CONTACT . '/messages')
            );
        });

        $result = $this->service()->sendTextByPhone(self::PHONE, 'Oi', null, self::CONTACT);

        $this->assertFalse($result->success);
        $this->assertTrue($result->retryable);
        $this->assertStringContainsString('cURL error 60', $result->error);
    }
}
