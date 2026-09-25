<?php

namespace Tests\Feature\Poli;

use App\Services\Poli\PoliClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Testes de envio para a API Poli v3 — sem tráfego real (Http::fake).
 * Caminho no projeto: tests/Feature/Poli/PoliEnvioTest.php
 */
class PoliEnvioTest extends TestCase
{
    private const BASE = 'https://foundation-api.poli.digital/v3';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'poli.base_url'     => self::BASE,
            'poli.token'        => 'token-teste',
            'poli.account_uuid' => 'acc-uuid',
            'poli.channel_uuid' => 'chan-uuid',
        ]);
    }

    private function respostaCriada(string $uuid = 'msg-123'): array
    {
        return ['uuid' => $uuid, 'event' => 'MESSAGE', 'type' => 'TEXT', 'ack' => 'CREATED', 'direction' => 'OUT'];
    }

    public function test_envia_texto_por_contact_uuid_com_payload_v3(): void
    {
        Http::fake([self::BASE.'/contacts/*' => Http::response($this->respostaCriada(), 201)]);

        $resp = app(PoliClient::class)->texto('contact-uuid', 'Olá, teste');

        $this->assertSame('msg-123', $resp['uuid']);
        $this->assertSame('CREATED', $resp['ack']);

        Http::assertSent(function (Request $r) {
            $d = $r->data();

            return $r->method() === 'POST'
                && $r->url() === self::BASE.'/contacts/contact-uuid/messages'
                && $r->hasHeader('Authorization', 'Bearer token-teste')
                && ! $r->hasHeader('Authentication')
                && ($d['provider'] ?? null) === 'WHATSAPP'
                && ($d['account_channel_uuid'] ?? null) === 'chan-uuid'
                && ($d['type'] ?? null) === 'TEXT'
                && ($d['version'] ?? null) === 'v3'
                && data_get($d, 'components.body.text') === 'Olá, teste'
                && ! array_key_exists('context', $d);
        });
    }

    public function test_resposta_citando_mensagem_envia_context(): void
    {
        Http::fake([self::BASE.'/contacts/*' => Http::response($this->respostaCriada(), 201)]);

        app(PoliClient::class)->texto('contact-uuid', 'Respondendo', 'msg-original');

        Http::assertSent(fn (Request $r) => data_get($r->data(), 'context.type') === 'message'
            && data_get($r->data(), 'context.message.uuid') === 'msg-original');
    }

    public function test_envia_por_telefone_normalizando_numero(): void
    {
        Http::fake([self::BASE.'/accounts/*' => Http::response($this->respostaCriada('msg-tel'), 201)]);

        $resp = app(PoliClient::class)->textoPorTelefone('(24) 99999-8888', 'Teste por telefone');

        $this->assertSame('msg-tel', $resp['uuid']);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && str_starts_with($r->url(), self::BASE.'/accounts/acc-uuid/contacts/5524999998888/messages')
            && data_get($r->data(), 'components.body.text') === 'Teste por telefone');
    }

    public function test_telefone_ja_com_ddi_nao_duplica_55(): void
    {
        Http::fake([self::BASE.'/accounts/*' => Http::response($this->respostaCriada(), 201)]);

        app(PoliClient::class)->textoPorTelefone('+55 24 99999-8888', 'x');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/contacts/5524999998888/messages'));
    }

    public function test_erro_422_lanca_excecao_e_nao_faz_retry(): void
    {
        Http::fake([self::BASE.'/contacts/*' => Http::response([
            'message' => 'O campo account_channel_uuid é obrigatório.',
            'errors'  => ['account_channel_uuid' => ['O campo account_channel_uuid é obrigatório.']],
        ], 422)]);

        try {
            app(PoliClient::class)->texto('contact-uuid', 'x');
            $this->fail('Deveria ter lançado RequestException');
        } catch (RequestException $e) {
            $this->assertSame(422, $e->response->status());
        }

        Http::assertSentCount(1);
    }

    public function test_erro_500_faz_retry_e_depois_sucesso(): void
    {
        Http::fake([
            self::BASE.'/contacts/*' => Http::sequence()
                ->push(['message' => 'Server Error'], 500)
                ->push($this->respostaCriada('msg-retry'), 201),
        ]);

        $resp = app(PoliClient::class)->texto('contact-uuid', 'x');

        $this->assertSame('msg-retry', $resp['uuid']);
        Http::assertSentCount(2);
    }

    public function test_consulta_status_da_mensagem(): void
    {
        Http::fake([self::BASE.'/messages/*' => Http::response([
            'uuid' => 'msg-123', 'ack' => 'READ_BY_CLIENT', 'direction' => 'OUT',
        ], 200)]);

        $msg = app(PoliClient::class)->mensagem('msg-123');

        $this->assertSame('READ_BY_CLIENT', $msg['ack']);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET'
            && str_starts_with($r->url(), self::BASE.'/messages/msg-123')
            && str_contains(urldecode($r->url()), 'ack'));
    }

    public function test_distribuir_para_time(): void
    {
        Http::fake([self::BASE.'/contacts/*' => Http::response([], 201)]);

        app(PoliClient::class)->distribuir('contact-uuid', 'team-uuid');

        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/contacts/contact-uuid/distribute'
            && ($r->data()['team'] ?? null) === 'team-uuid');
    }

    public function test_envio_por_telefone_devolve_contact_uuid(): void
    {
        Http::fake([self::BASE.'/accounts/*' => Http::response(
            $this->respostaCriada() + ['contact' => ['uuid' => 'contact-xyz']], 201)]);

        $resp = app(PoliClient::class)->textoPorTelefone('24999998888', 'x');

        $this->assertSame('contact-xyz', data_get($resp, 'contact.uuid'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'include=contact'));
    }

    public function test_telefone_invalido_nao_chama_api(): void
    {
        Http::fake();

        $this->expectException(\InvalidArgumentException::class);

        try {
            app(PoliClient::class)->textoPorTelefone('12345', 'x');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_envia_template_de_botoes_sem_parametros(): void
    {
        Http::fake([self::BASE.'/contacts/*' => Http::response($this->respostaCriada(), 201)]);

        app(PoliClient::class)->template('contact-uuid', 'tpl-botoes');

        Http::assertSent(function (Request $r) {
            $d = $r->data();

            return $r->url() === self::BASE.'/contacts/contact-uuid/messages'
                && ($d['provider'] ?? null) === 'WHATSAPP'
                && ($d['account_channel_uuid'] ?? null) === 'chan-uuid'
                && ($d['type'] ?? null) === 'TEMPLATE'
                && ($d['template_uuid'] ?? null) === 'tpl-botoes'
                && ($d['version'] ?? null) === 'v3'
                && ! array_key_exists('components', $d);   // igual ao script validado
        });
    }

    public function test_envia_template_por_telefone_com_parametros(): void
    {
        Http::fake([self::BASE.'/accounts/*' => Http::response($this->respostaCriada(), 201)]);

        app(PoliClient::class)->templatePorTelefone('24999998888', 'tpl-lista', ['Gustavo', '25/09']);

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), self::BASE.'/accounts/acc-uuid/contacts/5524999998888/messages')
            && ($r->data()['type'] ?? null) === 'TEMPLATE'
            && data_get($r->data(), 'components.body.parameters') === [
                ['type' => 'text', 'text' => 'Gustavo'],
                ['type' => 'text', 'text' => '25/09'],
            ]);
    }

    public function test_lista_templates_com_filtro_de_tipo(): void
    {
        Http::fake([self::BASE.'/accounts/*' => Http::response(['data' => [
            ['uuid' => 'tpl-1', 'type' => 'BUTTON', 'key' => '#menu',
             'message' => ['body' => 'Escolha:', 'buttons' => [['text' => 'Sou sócio']]]],
        ]], 200)]);

        $lista = app(PoliClient::class)->templates('BUTTON');

        $this->assertSame('tpl-1', data_get($lista, '0.uuid') ?? data_get($lista, 'data.0.uuid'));
        Http::assertSent(fn (Request $r) => $r->method() === 'GET'
            && str_starts_with($r->url(), self::BASE.'/accounts/acc-uuid/templates')
            && str_contains($r->url(), 'type=BUTTON'));
    }

    public function test_encerrar_sem_corpo_aceita_204(): void
    {
        Http::fake([self::BASE.'/contacts/*' => Http::response(null, 204)]);

        app(PoliClient::class)->encerrar('contact-uuid');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::BASE.'/contacts/contact-uuid/close'
            && in_array($r->body(), ['', '[]', '{}'], true));
    }

    public function test_encerrar_com_despedida_envia_texto_antes(): void
    {
        Http::fake([
            self::BASE.'/contacts/*/messages' => Http::response($this->respostaCriada(), 201),
            self::BASE.'/contacts/*/close'    => Http::response(null, 204),
        ]);

        app(PoliClient::class)->encerrar('contact-uuid', 'Atendimento encerrado. Obrigado!');

        $urls = Http::recorded()->map(fn ($par) => $par[0]->url())->values()->all();
        $this->assertSame([
            self::BASE.'/contacts/contact-uuid/messages',
            self::BASE.'/contacts/contact-uuid/close',
        ], $urls);
    }

    public function test_consulta_status_desembrulha_data(): void
    {
        Http::fake([self::BASE.'/messages/*' => Http::response(['data' => ['uuid' => 'm', 'ack' => 'RECEIVED_BY_CLIENT']], 200)]);

        $this->assertSame('RECEIVED_BY_CLIENT', app(PoliClient::class)->mensagem('m')['ack']);
    }

    public function test_nota_interna_usa_provider_annotation(): void
    {
        Http::fake([self::BASE.'/contacts/*' => Http::response($this->respostaCriada(), 201)]);

        app(PoliClient::class)->nota('contact-uuid', 'Sócio validado');

        Http::assertSent(fn (Request $r) => ($r->data()['provider'] ?? null) === 'ANNOTATION'
            && ! array_key_exists('account_channel_uuid', $r->data()));
    }
}
