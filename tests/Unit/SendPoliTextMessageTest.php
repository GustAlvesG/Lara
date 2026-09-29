<?php

namespace Tests\Unit;

use App\Jobs\SendPoliTextMessage;
use App\Services\Poli\PoliMessageService;
use App\Services\PoliBot\UberArrivalHandover;
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
            // O client repete 429/5xx na hora; aqui ele repete sem dormir.
            'poli.http.retry_sleep_ms' => 0,
            // Fora do modo on o aviso não passa a conversa para O Lara (esse
            // caminho usa banco e mora em PoliBotEngineTest).
            'poli.bot.mode' => 'off',
        ]);

        Http::preventStrayRequests();
    }

    private function job(): SendPoliTextMessage
    {
        return (new SendPoliTextMessage(self::PHONE, 'Seu carro chegou.', 'contato-1', null, 42))
            ->withFakeQueueInteractions();
    }

    private function handle(SendPoliTextMessage $job): void
    {
        $job->handle(app(PoliMessageService::class), app(UberArrivalHandover::class));
    }

    /** GET /contacts/{uuid}?include=current_attendance */
    private function atendimento(string $contato, ?array $atual): array
    {
        return ["*/contacts/{$contato}?*" => Http::response(['current_attendance' => $atual], 200)];
    }

    public function test_429_reagenda_o_job_pelo_retry_after(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '30']),
        ]);

        $job = $this->job();
        $this->handle($job);

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
        $this->handle($job);

        $job->assertReleased(60);
    }

    public function test_esgotadas_as_tentativas_o_429_para_de_reagendar(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Too Many Attempts.'], 429)]);

        $job = $this->job();
        $job->job->attempts = $job->tries;

        $this->handle($job);

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
        $this->handle($job);

        $job->assertNotReleased();
        $job->assertNotFailed();
    }

    public function test_sucesso_nao_reagenda_nem_falha(): void
    {
        // Formato real da resposta de envio (medido em 25/09/2026).
        Http::fake(['*' => Http::response(['uuid' => 'msg-1', 'ack' => 'CREATED', 'direction' => 'OUT'], 201)]);

        $job = $this->job();
        $this->handle($job);

        $job->assertNotReleased();
        $job->assertNotFailed();
        Http::assertSentCount(1);
    }

    /**
     * Aviso aceito com closeAfter, fora do modo on e sem humano no
     * atendimento: confere o atendimento, avisa (com o rodapé do
     * encerramento) e fecha, sem corpo.
     */
    public function test_com_close_after_encerra_a_conversa_depois_do_aviso(): void
    {
        Http::fake($this->atendimento('contato-1', null) + [
            '*/contacts/contato-1/close' => Http::response(['message' => 'ok'], 200),
            '*/contacts/contato-1/messages' => Http::response(['uuid' => 'msg-1', 'ack' => 'CREATED'], 201),
        ]);

        $job = (new SendPoliTextMessage(self::PHONE, 'Seu carro chegou.', 'contato-1', null, 42, closeAfter: true))
            ->withFakeQueueInteractions();
        $this->handle($job);

        $job->assertNotReleased();
        Http::assertSentCount(3);
        Http::assertSentInOrder([
            fn ($r) => $r->method() === 'GET' && str_contains($r->url(), '/contacts/contato-1?include=current_attendance'),
            fn ($r) => str_ends_with($r->url(), '/contacts/contato-1/messages')
                && str_ends_with((string) data_get($r->data(), 'components.body.text'), 'é só mandar uma nova mensagem.'),
            fn ($r) => str_ends_with($r->url(), '/contacts/contato-1/close') && $r->method() === 'POST' && $r->body() === '',
        ]);
    }

    /**
     * O defeito do #43: o close fechava também o atendimento de um humano
     * que estivesse conversando com o sócio.
     */
    public function test_com_humano_no_atendimento_so_avisa(): void
    {
        Http::fake($this->atendimento('contato-1', ['uuid' => 'att', 'status' => 'IN_PROGRESS', 'attendant' => ['uuid' => 'ana']]) + [
            '*/contacts/contato-1/messages' => Http::response(['uuid' => 'msg-1', 'ack' => 'CREATED'], 201),
        ]);

        $job = (new SendPoliTextMessage(self::PHONE, 'Seu carro chegou.', 'contato-1', null, 42, closeAfter: true))
            ->withFakeQueueInteractions();
        $this->handle($job);

        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/close'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/messages')
            && data_get($r->data(), 'components.body.text') === 'Seu carro chegou.');
    }

    /** Sem saber de quem é o atendimento, não mexe nele. */
    public function test_sem_conseguir_conferir_o_atendimento_so_avisa(): void
    {
        Http::fake([
            '*/contacts/contato-1?*' => Http::response(['message' => 'erro'], 500),
            '*/contacts/contato-1/messages' => Http::response(['uuid' => 'msg-1', 'ack' => 'CREATED'], 201),
        ]);

        $job = (new SendPoliTextMessage(self::PHONE, 'Seu carro chegou.', 'contato-1', null, 42, closeAfter: true))
            ->withFakeQueueInteractions();
        $this->handle($job);

        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/close'));
    }

    /**
     * Sem contact_uuid o aviso sai pelo telefone; o contato para encerrar
     * vem na própria resposta do envio (?include=contact).
     */
    public function test_sem_contact_uuid_encerra_pelo_contato_da_resposta(): void
    {
        Http::fake($this->atendimento('contato-9', null) + [
            '*/contacts/contato-9/close' => Http::response(null, 204),
            '*' => Http::response(['uuid' => 'msg-1', 'ack' => 'CREATED', 'contact' => ['uuid' => 'contato-9']], 201),
        ]);

        $job = (new SendPoliTextMessage(self::PHONE, 'Seu carro chegou.', null, null, 42, closeAfter: true))
            ->withFakeQueueInteractions();
        $this->handle($job);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/contacts/contato-9/close'));
    }

    public function test_sem_close_after_nao_encerra(): void
    {
        Http::fake(['*' => Http::response(['uuid' => 'msg-1', 'ack' => 'CREATED'], 201)]);

        $this->handle($this->job());

        Http::assertSentCount(1);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/close'));
    }

    /**
     * Aviso que não saiu não encerra nada: o associado ficaria sem o aviso
     * e sem a conversa.
     */
    public function test_aviso_recusado_nao_encerra(): void
    {
        Http::fake(['*' => Http::response(['message' => 'inválido'], 422)]);

        $job = (new SendPoliTextMessage(self::PHONE, 'Seu carro chegou.', 'contato-1', null, 42, closeAfter: true))
            ->withFakeQueueInteractions();
        $this->handle($job);

        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/close'));
    }

    /**
     * Encerramento que falha fica no log, e o job NÃO é reagendado — senão o
     * aviso, que já saiu, sairia de novo.
     */
    public function test_falha_ao_encerrar_nao_reenvia_o_aviso(): void
    {
        Http::fake($this->atendimento('contato-1', null) + [
            '*/contacts/contato-1/close' => Http::response(['message' => 'erro'], 500),
            '*/contacts/contato-1/messages' => Http::response(['uuid' => 'msg-1', 'ack' => 'CREATED'], 201),
        ]);

        $job = (new SendPoliTextMessage(self::PHONE, 'Seu carro chegou.', 'contato-1', null, 42, closeAfter: true))
            ->withFakeQueueInteractions();
        $this->handle($job);

        $job->assertNotReleased();
        $job->assertNotFailed();
        Http::assertSentCount(1 + 1 + 1 + (int) config('poli.http.retries', 2));   // GET, aviso, close com retentativas
        Log::shouldHaveReceived('warning')->withArgs(fn ($msg) => $msg === 'Poli: conversa não encerrada')->once();
    }

    /**
     * Job enfileirado antes do deploy chega sem a propriedade nova: precisa
     * rodar como antes (enviar e não encerrar), não estourar.
     */
    public function test_job_antigo_da_fila_sem_close_after_ainda_roda(): void
    {
        Http::fake(['*' => Http::response(['uuid' => 'msg-1', 'ack' => 'CREATED'], 201)]);

        $serializado = serialize(new SendPoliTextMessage(self::PHONE, 'Seu carro chegou.', 'contato-1', null, 42));
        $serializado = preg_replace('/s:10:"closeAfter";b:0;/', '', $serializado);
        $serializado = preg_replace_callback(
            '/^(O:\d+:"[^"]+":)(\d+):/',
            fn ($m) => $m[1] . ((int) $m[2] - 1) . ':',
            $serializado
        );
        $this->assertStringNotContainsString('closeAfter', $serializado);

        $job = unserialize($serializado)->withFakeQueueInteractions();
        $this->handle($job);

        Http::assertSentCount(1);
    }

    /**
     * Job da versão anterior já traz o rodapé no texto: não sai duas vezes.
     */
    public function test_job_antigo_com_rodape_no_texto_nao_repete_o_rodape(): void
    {
        $rodape = (string) config('poli.messages.uber_arrival.rodape');
        Http::fake($this->atendimento('contato-1', null) + [
            '*/contacts/contato-1/close' => Http::response(null, 204),
            '*/contacts/contato-1/messages' => Http::response(['uuid' => 'msg-1', 'ack' => 'CREATED'], 201),
        ]);

        $job = (new SendPoliTextMessage(self::PHONE, "Seu carro chegou.\n\n" . $rodape, 'contato-1', null, 42, closeAfter: true))
            ->withFakeQueueInteractions();
        $this->handle($job);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/messages')
            && substr_count((string) data_get($r->data(), 'components.body.text'), $rodape) === 1);
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
