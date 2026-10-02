<?php

namespace Tests\Feature\Poli;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Paridade do `poli:teste-envio` com o poli_teste_envio.py, que foi validado
 * contra a API real: cada caso abaixo é uma das chamadas de referência, e o
 * que se confere é método, caminho, query string e corpo — sem tráfego real.
 */
class PoliTesteEnvioCommandTest extends TestCase
{
    private const BASE = 'https://foundation-api.poli.digital/v3';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'poli.base_url' => self::BASE,
            'poli.token' => 'token-teste',
            'poli.account_uuid' => 'acc-uuid',
            'poli.channel_uuid' => 'chan-uuid',
            'poli.http.retry_sleep_ms' => 0,
        ]);
    }

    /** python poli_teste_envio.py 24999998888 -m "teste" */
    public function test_texto_por_telefone(): void
    {
        Http::fake(['*' => Http::response(['uuid' => 'msg-1', 'ack' => 'CREATED', 'contact' => ['uuid' => 'c-1']], 201)]);

        $this->artisan('poli:teste-envio', ['destino' => '24999998888', '-m' => 'teste', '--force' => true, '--aguardar' => 0])
            ->expectsOutputToContain('contact_uuid: c-1')
            ->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::BASE . '/accounts/acc-uuid/contacts/5524999998888/messages?include=contact'
            && $r->data() === [
                'provider' => 'WHATSAPP',
                'account_channel_uuid' => 'chan-uuid',
                'type' => 'TEXT',
                'version' => 'v3',
                'components' => ['body' => ['text' => 'teste']],
            ]);
    }

    /** python poli_teste_envio.py 24999998888 --template <uuid> --param "X" */
    public function test_template_por_telefone_com_parametro(): void
    {
        Http::fake(['*' => Http::response(['uuid' => 'msg-1', 'ack' => 'CREATED'], 201)]);

        $this->artisan('poli:teste-envio', [
            'destino' => '24999998888', '--template' => 'tpl-1', '--param' => ['X'], '--force' => true, '--aguardar' => 0,
        ])->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r->url() === self::BASE . '/accounts/acc-uuid/contacts/5524999998888/messages?include=contact'
            && $r->data() === [
                'provider' => 'WHATSAPP',
                'account_channel_uuid' => 'chan-uuid',
                'type' => 'TEMPLATE',
                'template_uuid' => 'tpl-1',
                'version' => 'v3',
                'components' => ['body' => ['parameters' => [['type' => 'text', 'text' => 'X']]]],
            ]);
    }

    /** python poli_teste_envio.py --templates --tipo BUTTON */
    public function test_lista_templates_por_tipo(): void
    {
        Http::fake(['*' => Http::response(['data' => [
            ['uuid' => 'tpl-1', 'type' => 'LIST', 'key' => 'Lista Departamentos', 'status' => 'NOT_VERIFIED',
             'message' => ['body' => 'Selecione:', 'button' => 'Ver opções',
                 'section' => [['rows' => [['messageOption' => ['title' => 'Financeiro']]]]]]],
        ]], 200)]);

        $this->artisan('poli:teste-envio', ['--templates' => true, '--tipo' => 'BUTTON'])
            ->expectsOutputToContain('item  : Financeiro')
            ->assertSuccessful();

        Http::assertSent(function (Request $r) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $query);

            return $r->method() === 'GET'
                && str_starts_with($r->url(), self::BASE . '/accounts/acc-uuid/templates?')
                && $query === [
                    'include' => 'key,status,message,interactive,account_channel',
                    'per_page' => '100',
                    'type' => 'BUTTON',
                ];
        });
    }

    /** python poli_teste_envio.py <contact_uuid> --encerrar -m "Tchau" */
    public function test_encerrar_com_despedida(): void
    {
        Http::fake([
            self::BASE . '/contacts/*/messages' => Http::response(['uuid' => 'msg-1'], 201),
            self::BASE . '/contacts/*/close' => Http::response(null, 204),
        ]);

        $this->artisan('poli:teste-envio', ['destino' => 'contact-uuid', '--encerrar' => true, '-m' => 'Tchau', '--force' => true])
            ->assertSuccessful();

        $enviadas = Http::recorded()->map(fn ($par) => [$par[0]->method(), $par[0]->url(), $par[0]->body()])->values()->all();

        $this->assertSame('POST', $enviadas[0][0]);
        $this->assertSame(self::BASE . '/contacts/contact-uuid/messages', $enviadas[0][1]);
        $this->assertSame('Tchau', data_get(json_decode($enviadas[0][2], true), 'components.body.text'));
        $this->assertSame(['POST', self::BASE . '/contacts/contact-uuid/close', ''], $enviadas[1]);
    }

    public function test_encerrar_recusa_telefone(): void
    {
        Http::fake();

        $this->artisan('poli:teste-envio', ['destino' => '24999998888', '--encerrar' => true, '--force' => true])
            ->expectsOutputToContain('precisa do contact_uuid')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_sem_confirmacao_nao_envia(): void
    {
        Http::fake();

        $this->artisan('poli:teste-envio', ['destino' => '24999998888'])
            ->expectsConfirmation('Isto envia uma mensagem REAL no WhatsApp. Continuar?', 'no')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_config_incompleta_para_antes_de_chamar_a_api(): void
    {
        config(['poli.channel_uuid' => null]);
        Http::fake();

        $this->artisan('poli:teste-envio', ['destino' => '24999998888', '--force' => true])
            ->expectsOutputToContain('poli.channel_uuid')
            ->assertFailed();

        Http::assertNothingSent();
    }
}
