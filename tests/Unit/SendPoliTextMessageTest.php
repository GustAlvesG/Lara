<?php

namespace Tests\Unit;

use App\Jobs\SendPoliTextMessage;
use App\Services\Poli\PoliMessageService;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ReflectionProperty;
use Tests\TestCase;

/**
 * O que este Job precisa acertar é UMA coisa: 429 não é falha, é "espere".
 * Sem banco — nada aqui usa Eloquent.
 */
class SendPoliTextMessageTest extends TestCase
{
    private const PHONE = '5524992542363';

    protected function setUp(): void
    {
        parent::setUp();

        Log::spy();

        config()->set([
            'poli.enabled' => true,
            'poli.base_url' => 'https://foundation-api.poli.digital/v3',
            'poli.token' => 'token-de-teste',
            'poli.account_uuid' => 'conta-1',
            'poli.default_channel_uuid' => 'canal-1',
        ]);
    }

    private function job(): SendPoliTextMessage
    {
        return (new SendPoliTextMessage(self::PHONE, 'Seu carro chegou.', 'contato-1', null, 42))
            ->withFakeQueueInteractions();
    }

    public function test_429_reagenda_o_job_pelo_retry_after(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '30']),
        ]);

        $job = $this->job();
        $job->handle(app(PoliMessageService::class));

        $job->assertReleased(30);
        $job->assertNotFailed();
    }

    /**
     * Sem Retry-After a espera é nossa: primeiro degrau do backoff.
     */
    public function test_429_sem_retry_after_usa_o_backoff(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Too Many Attempts.'], 429)]);

        $job = $this->job();
        $job->handle(app(PoliMessageService::class));

        $job->assertReleased(60);
    }

    public function test_esgotadas_as_tentativas_o_429_para_de_reagendar(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Too Many Attempts.'], 429)]);

        $job = $this->job();
        $job->job->attempts = $job->tries;

        $job->handle(app(PoliMessageService::class));

        $job->assertNotReleased();
        $job->assertNotFailed();
    }

    /**
     * 422 é payload recusado: insistir só repete o erro e ocupa a cota.
     */
    public function test_erro_definitivo_nao_reagenda(): void
    {
        Http::fake([
            '*' => Http::response([
                'message' => 'O campo contact.contact_uuid é obrigatório.',
                'errors' => ['contact.contact_uuid' => ['O campo contact.contact_uuid é obrigatório.']],
            ], 422),
        ]);

        $job = $this->job();
        $job->handle(app(PoliMessageService::class));

        $job->assertNotReleased();
        $job->assertNotFailed();
    }

    public function test_sucesso_nao_reagenda_nem_falha(): void
    {
        Http::fake(['*' => Http::response(['data' => ['uuid' => 'msg-1', 'status' => 'SENT']], 201)]);

        $job = $this->job();
        $job->handle(app(PoliMessageService::class));

        $job->assertNotReleased();
        $job->assertNotFailed();
        Http::assertSentCount(1);
    }

    /**
     * O Job atravessa a fila serializado. Como as propriedades são readonly,
     * vale garantir que a volta funciona — um erro aqui só apareceria em
     * produção, com a fila já rodando.
     */
    public function test_sobrevive_a_serializacao_da_fila(): void
    {
        $original = new SendPoliTextMessage(self::PHONE, 'Seu carro chegou.', 'contato-1', 'canal-2', 42);

        $restored = unserialize(serialize($original));

        $this->assertSame(self::PHONE, $restored->phone);
        $this->assertSame('Seu carro chegou.', $restored->text);
        $this->assertSame('contato-1', $restored->contactUuid);
        $this->assertSame('canal-2', $restored->channelUuid);
        $this->assertSame(42, $restored->uberAccessRequestId);
    }

    /**
     * O balde é um só para a aplicação inteira — ver a nota em config/poli.php.
     */
    public function test_usa_o_limitador_unico_da_aplicacao(): void
    {
        $middleware = (new SendPoliTextMessage(self::PHONE, 'Oi'))->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(RateLimited::class, $middleware[0]);

        $limiterName = (new ReflectionProperty(RateLimited::class, 'limiterName'));
        $limiterName->setAccessible(true);

        $this->assertSame('poli-outbound', $limiterName->getValue($middleware[0]));
    }

    /**
     * O limitador precisa estar registrado, senão o middleware passa reto e o
     * teto de 60/min da conta deixa de existir sem ninguém perceber.
     */
    public function test_o_limitador_esta_registrado(): void
    {
        $this->assertNotNull(
            app(\Illuminate\Cache\RateLimiter::class)->limiter('poli-outbound')
        );
    }
}
