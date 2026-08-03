<?php

namespace Tests\Unit;

use App\Services\Lara\LaraAnswer;
use App\Services\Lara\LaraClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Testes do cliente da IA. Nenhum toca o banco (o histórico é problema do
 * controller) e nenhum toca a rede — a VM da Lara nunca é chamada de verdade
 * aqui, tudo passa por Http::fake().
 */
class LaraClientTest extends TestCase
{
    private const BASE_URL = 'http://ia.teste:3000';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.lara.enabled' => true,
            'services.lara.base_url' => self::BASE_URL,
            'services.lara.timeout' => 30,
            'services.lara.reset_timeout' => 10,
            'services.lara.max_input_chars' => 1000,
            'services.lara.fallback_message' => 'Vou te transferir para o setor responsável, só um momento!',
        ]);

        Log::spy();
    }

    private function client(): LaraClient
    {
        return new LaraClient();
    }

    public function test_devolve_a_resposta_da_ia(): void
    {
        Http::fake([
            '*/perguntar' => Http::response(['resposta' => 'A academia funciona das 06h às 22h!']),
        ]);

        $answer = $this->client()->ask('portal_7', 'qual o horário da academia?');

        $this->assertTrue($answer->ok());
        $this->assertSame('A academia funciona das 06h às 22h!', $answer->texto);
    }

    public function test_envia_usuario_id_e_mensagem_no_endpoint_perguntar(): void
    {
        Http::fake(['*' => Http::response(['resposta' => 'ok'])]);

        $this->client()->ask('portal_7', 'qual o horário da academia?');

        Http::assertSent(function (Request $request) {
            return $request->url() === self::BASE_URL . '/perguntar'
                && $request['usuario_id'] === 'portal_7'
                && $request['mensagem'] === 'qual o horário da academia?';
        });
    }

    public function test_base_url_com_barra_no_final_nao_duplica_a_barra(): void
    {
        config(['services.lara.base_url' => self::BASE_URL . '/']);
        Http::fake(['*' => Http::response(['resposta' => 'ok'])]);

        $this->client()->ask('portal_7', 'oi');

        Http::assertSent(fn (Request $request) => $request->url() === self::BASE_URL . '/perguntar');
    }

    public function test_queda_de_conexao_vira_fallback_sem_lancar_excecao(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $answer = $this->client()->ask('portal_7', 'qual o horário da academia?');

        $this->assertSame(LaraAnswer::STATUS_ERRO, $answer->status);
        $this->assertSame(config('services.lara.fallback_message'), $answer->texto);
        $this->assertStringContainsString('conexão', (string) $answer->erro);
    }

    public function test_erro_http_vira_fallback(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $answer = $this->client()->ask('portal_7', 'qual o horário da academia?');

        $this->assertSame(LaraAnswer::STATUS_ERRO, $answer->status);
        $this->assertSame('HTTP 500', $answer->erro);
    }

    public function test_corpo_sem_o_campo_resposta_vira_fallback(): void
    {
        Http::fake(['*' => Http::response(['erro' => "campos 'usuario_id' e 'mensagem' são obrigatórios"], 200)]);

        $answer = $this->client()->ask('portal_7', 'qual o horário da academia?');

        $this->assertSame(LaraAnswer::STATUS_ERRO, $answer->status);
        $this->assertSame(config('services.lara.fallback_message'), $answer->texto);
    }

    /**
     * O modelo roda em CPU: repetir a chamada depois de um timeout dobraria a
     * carga justamente quando a VM já está saturada.
     */
    public function test_nao_repete_a_chamada_apos_falha(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        $this->client()->ask('portal_7', 'qual o horário da academia?');

        Http::assertSentCount(1);
    }

    public function test_desativado_nao_chama_a_ia(): void
    {
        config(['services.lara.enabled' => false]);
        Http::fake();

        $answer = $this->client()->ask('portal_7', 'qual o horário da academia?');

        $this->assertSame(LaraAnswer::STATUS_DESATIVADO, $answer->status);
        Http::assertNothingSent();
    }

    public function test_sem_base_url_a_integracao_se_desliga_sozinha(): void
    {
        config(['services.lara.base_url' => null]);
        Http::fake();

        $this->assertFalse($this->client()->enabled());
        $this->assertSame(LaraAnswer::STATUS_DESATIVADO, $this->client()->ask('portal_7', 'oi')->status);
        Http::assertNothingSent();
    }

    public function test_trunca_a_mensagem_no_limite_configurado(): void
    {
        config(['services.lara.max_input_chars' => 10]);
        Http::fake(['*' => Http::response(['resposta' => 'ok'])]);

        $this->client()->ask('portal_7', str_repeat('a', 50));

        Http::assertSent(fn (Request $request) => $request['mensagem'] === str_repeat('a', 10));
    }

    public function test_reiniciar_chama_o_endpoint_reiniciar(): void
    {
        Http::fake(['*/reiniciar' => Http::response(['status' => 'ok'])]);

        $this->assertTrue($this->client()->reset('portal_7'));

        Http::assertSent(function (Request $request) {
            return $request->url() === self::BASE_URL . '/reiniciar'
                && $request['usuario_id'] === 'portal_7';
        });
    }

    public function test_reiniciar_devolve_false_quando_a_ia_esta_fora_do_ar(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->assertFalse($this->client()->reset('portal_7'));
    }
}
