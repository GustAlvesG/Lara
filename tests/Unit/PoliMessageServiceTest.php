<?php

namespace Tests\Unit;

use App\Services\Poli\PoliMessageService;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * Envio de texto pela Poli. Sem banco de propósito: nada aqui toca Eloquent,
 * e teste que não precisa de tabela não deve arrastar RefreshDatabase junto.
 */
class PoliMessageServiceTest extends TestCase
{
    private const ACCOUNT = 'a9c0bc53-430e-11f1-9d75-06799772b1cd';
    private const CHANNEL = 'c2e53327-633f-11f1-9d75-06799772b1cd';
    private const CONTACT = 'd5e8a972-6360-11f1-9d75-06799772b1cd';
    private const PHONE = '5524992542363';
    private const ENDPOINT = 'https://foundation-api.poli.digital/v3/accounts/' . self::ACCOUNT . '/messages';

    protected function setUp(): void
    {
        parent::setUp();

        // O log real não interessa aqui, mas o mascaramento sim — ver o
        // teste que confere que o token não vaza.
        Log::spy();

        config()->set([
            'poli.enabled' => true,
            'poli.base_url' => 'https://foundation-api.poli.digital/v3',
            'poli.token' => 'token-secreto-de-teste',
            'poli.account_uuid' => self::ACCOUNT,
            'poli.default_channel_uuid' => self::CHANNEL,
        ]);
    }

    private function service(): PoliMessageService
    {
        return new PoliMessageService();
    }

    public function test_envia_texto_no_formato_do_contrato(): void
    {
        Http::fake([
            '*' => Http::response(['data' => ['uuid' => 'msg-123', 'status' => 'SENT']], 201),
        ]);

        $result = $this->service()->sendTextByPhone(
            self::PHONE,
            'Seu carro chegou.',
            null,
            self::CONTACT
        );

        $this->assertTrue($result->success);
        $this->assertSame('msg-123', $result->messageUuid);
        $this->assertSame('SENT', $result->status);
        $this->assertNull($result->error);

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->url() === self::ENDPOINT
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer token-secreto-de-teste')
                && $body['provider'] === 'WHATSAPP'
                && $body['account_channel_uuid'] === self::CHANNEL
                && $body['type'] === 'CHAT'
                && $body['version'] === 'v3'
                && $body['direction'] === 'OUT'
                && $body['contact']['type'] === 'PERSON'
                && $body['contact']['contact_uuid'] === self::CONTACT
                && $body['author']['type'] === 'APPLICATION'
                && $body['components']['body']['text'] === 'Seu carro chegou.'
                && !array_key_exists('attachments', $body['components']);
        });
    }

    /**
     * O `contact_channel_uid` é inferência nossa, não contrato confirmado —
     * e o envio real de 28/08 passou sem ele, com o uuid sozinho. Fica de
     * fora por padrão; ligado, sai no formato do canal.
     */
    public function test_contact_channel_uid_fica_de_fora_por_padrao(): void
    {
        Http::fake(['*' => Http::response(['data' => ['uuid' => 'msg-1']], 201)]);

        $this->service()->sendTextByPhone(self::PHONE, 'Oi', null, self::CONTACT);

        Http::assertSent(fn (Request $r) => !array_key_exists('contact_channel_uid', $r->data()['contact']));
    }

    public function test_contact_channel_uid_entra_quando_ligado(): void
    {
        config()->set('poli.send.include_contact_channel_uid', true);
        Http::fake(['*' => Http::response(['data' => ['uuid' => 'msg-1']], 201)]);

        $this->service()->sendTextByPhone(self::PHONE, 'Oi', null, self::CONTACT);

        Http::assertSent(
            fn (Request $r) => $r->data()['contact']['contact_channel_uid'] === self::PHONE . '@c.us'
        );
    }

    public function test_autor_vira_user_quando_ha_uuid_configurado(): void
    {
        config()->set('poli.send.author.user_uuid', 'user-uuid-1');
        config()->set('poli.send.author.name', 'Portaria');
        Http::fake(['*' => Http::response(['data' => ['uuid' => 'msg-1']], 201)]);

        $this->service()->sendTextByPhone(self::PHONE, 'Oi');

        Http::assertSent(function (Request $r) {
            $author = $r->data()['author'];

            return $author['type'] === 'USER'
                && $author['user_uuid'] === 'user-uuid-1'
                && $author['name'] === 'Portaria';
        });
    }

    public function test_429_devolve_rate_limited_com_retry_after(): void
    {
        Http::fake([
            '*' => Http::response(
                ['message' => 'Too Many Attempts.'],
                429,
                ['Retry-After' => '30']
            ),
        ]);

        $result = $this->service()->sendTextByPhone(self::PHONE, 'Seu carro chegou.');

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

        $result = $this->service()->sendTextByPhone(self::PHONE, 'Oi');

        $this->assertTrue($result->isRateLimited());
        $this->assertNull($result->retryAfter);
    }

    /**
     * Formato de erro confirmado da Poli: `message` + bag `errors`.
     */
    public function test_422_traz_a_mensagem_e_os_campos_recusados(): void
    {
        Http::fake([
            '*' => Http::response([
                'message' => 'O campo contact.contact_uuid é obrigatório.',
                'errors' => [
                    'contact.contact_uuid' => ['O campo contact.contact_uuid é obrigatório.'],
                ],
            ], 422),
        ]);

        $result = $this->service()->sendTextByPhone(self::PHONE, 'Oi');

        $this->assertFalse($result->success);
        $this->assertSame(422, $result->httpStatus);
        $this->assertStringContainsString('contact.contact_uuid', $result->error);
        // 422 é payload errado: repetir erra de novo igual.
        $this->assertFalse($result->retryable);
    }

    public function test_5xx_e_retentavel(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        $result = $this->service()->sendTextByPhone(self::PHONE, 'Oi');

        $this->assertFalse($result->success);
        $this->assertTrue($result->retryable);
        $this->assertSame('HTTP 503', $result->error);
    }

    /**
     * Um número brasileiro sem o 55 tem 11 dígitos e é indistinguível de um
     * E.164 curto de outro país. Completar por conta própria pode mandar a
     * mensagem para um estranho — então recusa, sem sair da nossa casa.
     */
    public function test_recusa_telefone_sem_ddi_sem_chamar_a_api(): void
    {
        Http::fake();

        $result = $this->service()->sendTextByPhone('24992542363', 'Oi');

        $this->assertFalse($result->success);
        $this->assertFalse($result->retryable);
        $this->assertStringContainsString('E.164', $result->error);
        Http::assertNothingSent();
    }

    public function test_aceita_telefone_com_mascara_e_normaliza(): void
    {
        // O uid do canal é o único lugar do corpo em que o telefone aparece,
        // então é por ele que se confere a normalização.
        config()->set('poli.send.include_contact_channel_uid', true);
        Http::fake(['*' => Http::response(['data' => ['uuid' => 'msg-1']], 201)]);

        $result = $this->service()->sendTextByPhone('+55 (24) 99254-2363', 'Oi');

        $this->assertTrue($result->success);
        Http::assertSent(
            fn (Request $r) => $r->data()['contact']['contact_channel_uid'] === '5524992542363@c.us'
        );
    }

    /**
     * O log da aplicação é lido por mais gente do que o .env: nem o token
     * inteiro nem o telefone do associado podem cair nele.
     */
    public function test_log_mascara_token_e_telefone(): void
    {
        Http::fake(['*' => Http::response(['data' => ['uuid' => 'msg-1']], 201)]);

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

        $result = $this->service()->sendTextByPhone(self::PHONE, '   ');

        $this->assertFalse($result->success);
        $this->assertSame('texto vazio', $result->error);
        Http::assertNothingSent();
    }

    public function test_integracao_desligada_nao_envia(): void
    {
        config()->set('poli.enabled', false);
        Http::fake();

        $result = $this->service()->sendTextByPhone(self::PHONE, 'Oi');

        $this->assertFalse($result->success);
        $this->assertFalse($result->retryable);
        Http::assertNothingSent();
    }

    public function test_falha_de_conexao_nao_lanca(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $result = $this->service()->sendTextByPhone(self::PHONE, 'Oi');

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
                new GuzzleRequest('POST', self::ENDPOINT)
            );
        });

        $result = $this->service()->sendTextByPhone(self::PHONE, 'Oi');

        $this->assertFalse($result->success);
        $this->assertTrue($result->retryable);
        $this->assertStringContainsString('cURL error 60', $result->error);
    }

    /**
     * Formato real, medido num envio de verdade: 200 com o corpo vazio. Não é
     * anomalia, então não deve gerar log de "formato não mapeado" a cada
     * mensagem enviada.
     */
    public function test_200_com_corpo_vazio_e_o_sucesso_normal(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $result = $this->service()->sendTextByPhone(self::PHONE, 'Oi');

        $this->assertTrue($result->success);
        $this->assertNull($result->messageUuid);
        $this->assertNull($result->error);

        Log::shouldNotHaveReceived('info', [
            'Poli: envio aceito em formato de resposta não mapeado',
            Mockery::any(),
        ]);
    }

    public function test_corpo_desconhecido_e_sucesso_mas_fica_registrado(): void
    {
        Http::fake(['*' => Http::response(['algo' => 'inesperado'], 200)]);

        $result = $this->service()->sendTextByPhone(self::PHONE, 'Oi');

        $this->assertTrue($result->success);
        $this->assertNull($result->messageUuid);

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($message) => $message === 'Poli: envio aceito em formato de resposta não mapeado')
            ->once();
    }
}
