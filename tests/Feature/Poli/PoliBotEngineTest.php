<?php

namespace Tests\Feature\Poli;

use App\Models\BotFlow;
use App\Models\BotSession;
use App\Models\PoliMessage;
use App\Models\UberAccessRequest;
use App\Services\MultiClubes\TitleMemberLookup;
use App\Services\Poli\ParsedPoliMessage;
use App\Services\PoliBot\BotEngine;
use App\Services\PoliBot\DefaultFlows;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O bot conduzindo a conversa, com os fluxos padrão (DefaultFlows) — os
 * mesmos que vão para produção.
 *
 * Sem RefreshDatabase (a cadeia completa de migrations quebra, ver
 * UberAccessRequestWebhookTest): só as tabelas que o bot e o pedido de Uber
 * usam, no SQLite :memory: do phpunit.xml.
 */
class PoliBotEngineTest extends TestCase
{
    private const BASE = 'https://foundation-api.poli.digital/v3';
    private const CONTACT = 'contato-1';
    private const PHONE = '5524992542363';

    private int $seq = 0;

    private bool $poliForaDoAr = false;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            '2026_07_20_150000_create_uber_access_requests_tables.php',
            '2026_07_21_120000_add_matricula_to_uber_access_requests.php',
            '2026_08_27_170000_add_member_validation_to_uber_access_requests.php',
            '2026_09_10_100000_add_member_validation_type_to_uber_access_requests.php',
            '2026_01_05_141304_banco_de_horas.php',
            '2026_09_25_160000_create_poli_bot_tables.php',
        ] as $migration) {
            (require base_path('database/migrations/' . $migration))->up();
        }

        // Sem SQL Server: o título não devolve ninguém.
        $this->app->instance(TitleMemberLookup::class, new class extends TitleMemberLookup {
            public function __construct() {}

            public function namesForTitle(string $matricula): array
            {
                return [];
            }
        });

        config([
            'poli.base_url' => self::BASE,
            'poli.token' => 'token-teste',
            'poli.account_uuid' => 'acc-uuid',
            'poli.channel_uuid' => 'chan-uuid',
            'poli.http.retry_sleep_ms' => 0,
            'poli.bot.mode' => BotEngine::MODE_ON,
            'poli.bot.live_contacts' => [],
            'poli.bot.fallback_team_uuid' => 'time-geral',
        ]);

        foreach (DefaultFlows::all() as $slug => $fluxo) {
            BotFlow::create(['slug' => $slug, 'name' => $fluxo['name'], 'active' => true, 'definition' => $fluxo['definition']]);
        }

        $n = 0;
        Http::fake(function (Request $r) use (&$n) {
            $n++;

            if ($this->poliForaDoAr) {
                return Http::response(['message' => 'fora do ar'], 500);
            }

            return str_ends_with($r->url(), '/close') || str_ends_with($r->url(), '/distribute')
                ? Http::response(null, 204)
                : Http::response(['uuid' => "out-{$n}", 'ack' => 'CREATED'], 201);
        });
    }

    private function bot(): BotEngine
    {
        return app(BotEngine::class);
    }

    private function texto(string $texto, ?string $contexto = null, string $contato = self::CONTACT): void
    {
        $this->bot()->handleInbound($this->msg(ParsedPoliMessage::TYPE_TEXT, $texto, null, $contexto, $contato));
    }

    private function imagem(string $url = 'https://cdn/print.jpg'): void
    {
        $this->bot()->handleInbound($this->msg(ParsedPoliMessage::TYPE_IMAGE, null, $url));
    }

    private function msg(string $tipo, ?string $texto, ?string $url = null, ?string $contexto = null, string $contato = self::CONTACT): ParsedPoliMessage
    {
        return new ParsedPoliMessage(
            messageId: 'in-' . (++$this->seq),
            contactUuid: $contato,
            contactPhone: $contato === self::CONTACT ? self::PHONE : '55249' . str_pad((string) crc32($contato) % 100000000, 8, '0'),
            contactName: 'Gustavo Coordenador de TI|Gustavo',
            attendanceUuid: 'att-1',
            type: $tipo,
            text: $texto,
            mediaUrl: $url,
            contextMessageUuid: $contexto,
        );
    }

    private function sessao(): BotSession
    {
        return BotSession::findOrFail(self::CONTACT);
    }

    /** @return string[] textos enviados, na ordem */
    private function enviados(): array
    {
        return PoliMessage::where('direction', 'OUT')->where('type', '!=', 'ACTION')->orderBy('id')->pluck('texto')->all();
    }

    private function ultimoEnviado(): ?string
    {
        return PoliMessage::where('direction', 'OUT')->where('type', '!=', 'ACTION')->orderByDesc('id')->value('texto');
    }

    /* ------------------------------------------------------------------ */

    public function test_modo_off_nao_faz_nada(): void
    {
        config(['poli.bot.mode' => BotEngine::MODE_OFF]);

        $this->texto('oi');

        $this->assertSame(0, PoliMessage::count());
        $this->assertNull(BotSession::find(self::CONTACT));
        Http::assertNothingSent();
    }

    public function test_primeira_mensagem_abre_o_menu_de_departamentos_pelo_template(): void
    {
        $this->texto('Boa tarde');

        Http::assertSent(fn (Request $r) => $r->url() === self::BASE . '/contacts/' . self::CONTACT . '/messages'
            && ($r->data()['type'] ?? null) === 'TEMPLATE'
            && ($r->data()['template_uuid'] ?? null) === DefaultFlows::TPL_DEPARTAMENTOS);

        $s = $this->sessao();
        $this->assertSame('flow', $s->state);
        $this->assertSame('atendimento', $s->flow_slug);
        $this->assertSame('menu', $s->step_key);
        $this->assertSame('out-1', $s->prompt_message_uuid);

        $menu = PoliMessage::where('uuid', 'out-1')->first();
        $this->assertSame('Financeiro', $menu->options[1]['label']);
        $this->assertFalse($menu->shadow);
    }

    public function test_modo_sombra_registra_tudo_e_nao_envia_nada(): void
    {
        config(['poli.bot.mode' => BotEngine::MODE_SHADOW]);

        $this->texto('oi');
        $this->texto("Financeiro\nConsulte seus débitos ou outras pendências.");

        Http::assertNothingSent();
        $this->assertTrue(PoliMessage::where('direction', 'OUT')->get()->every->shadow);
        $this->assertTrue($this->sessao()->isHuman());
        $this->assertTrue(PoliMessage::where('type', 'ACTION')->where('texto', 'like', 'handoff time=' . DefaultFlows::TEAM_FINANCEIRO . '%')->exists());
    }

    public function test_piloto_em_sombra_recebe_de_verdade_e_os_outros_nao(): void
    {
        config(['poli.bot.mode' => BotEngine::MODE_SHADOW, 'poli.bot.live_contacts' => [self::PHONE]]);

        $this->texto('oi');
        $this->texto('oi', contato: 'outro-contato');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/contacts/' . self::CONTACT . '/'));
    }

    public function test_toque_na_lista_e_numero_e_apelido_escolhem_a_opcao(): void
    {
        $this->texto('oi');
        $this->texto("Financeiro\nConsulte seus débitos ou outras pendências.", 'out-1');
        $this->assertTrue($this->sessao()->isHuman());

        $this->texto('oi', contato: 'c2');
        $this->texto('3', contato: 'c2');   // 3ª opção = Secretaria
        $this->assertTrue(BotSession::find('c2')->isHuman());
        Http::assertSent(fn (Request $r) => $r->url() === self::BASE . '/contacts/c2/distribute'
            && ($r->data()['team'] ?? null) === DefaultFlows::TEAM_SECRETARIA);

        $this->texto('oi', contato: 'c3');
        $this->texto('uber', contato: 'c3');
        $this->assertSame('carro-de-aplicativo', BotSession::find('c3')->flow_slug);
    }

    public function test_departamento_passa_para_o_time_certo_e_silencia(): void
    {
        $this->texto('oi');
        $this->texto('Financeiro');

        Http::assertSent(fn (Request $r) => $r->url() === self::BASE . '/contacts/' . self::CONTACT . '/distribute'
            && ($r->data()['team'] ?? null) === DefaultFlows::TEAM_FINANCEIRO);
        $this->assertStringContainsString('*Financeiro*', $this->ultimoEnviado());

        $antes = PoliMessage::where('direction', 'OUT')->count();
        $this->texto('alô? tem alguém?');

        $this->assertSame($antes, PoliMessage::where('direction', 'OUT')->count(), 'Com humano no atendimento o bot não fala');
    }

    public function test_fluxo_do_uber_completo_cria_o_pedido_e_encerra(): void
    {
        $this->texto('oi');
        $this->texto("Carro de Aplicativo\nCarro, moto ou táxi", 'out-1');
        $this->assertStringContainsString('*matrícula*', $this->ultimoEnviado());

        $this->texto('12345');
        $this->texto('gustavo');
        $this->texto('Campo Bar do Campo');     // toque na lista local-uber
        $this->texto('abc-1d23');
        $this->imagem();

        $pedido = UberAccessRequest::sole();
        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_ACESSO, $pedido->status);
        $this->assertSame('12345', $pedido->matricula);
        $this->assertSame('gustavo', $pedido->requester_name);
        $this->assertSame('Campo', $pedido->club_location);
        $this->assertSame('ABC1D23', $pedido->vehicle_plate);
        $this->assertSame('https://cdn/print.jpg', $pedido->screenshot_url);
        $this->assertSame(self::CONTACT, $pedido->contact_uuid);
        $this->assertNotNull($pedido->expires_at);

        $this->assertStringContainsString('placa *ABC1D23*', $this->ultimoEnviado());
        Http::assertSent(fn (Request $r) => $r->url() === self::BASE . '/contacts/' . self::CONTACT . '/close');
        $this->assertSame('idle', $this->sessao()->state);
    }

    public function test_placa_invalida_recebe_correcao_e_tres_erros_passam_para_humano(): void
    {
        $this->irAtePlaca();

        $this->texto('não sei');
        $this->assertStringContainsString('placa não parece válida', $this->ultimoEnviado());
        $this->assertSame('placa', $this->sessao()->step_key);
        $this->assertSame(1, $this->sessao()->tentativas);

        $this->texto('123');
        $this->texto('xxxx');

        $this->assertTrue($this->sessao()->isHuman());
        $this->assertStringContainsString('Vou te passar para um atendente', implode(' ', $this->enviados()));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/distribute')
            && ($r->data()['team'] ?? null) === DefaultFlows::TEAM_SECRETARIA);
    }

    public function test_resposta_certa_zera_as_tentativas(): void
    {
        $this->irAtePlaca();
        $this->texto('errada');
        $this->texto('ABC1234');

        $this->assertSame('print', $this->sessao()->step_key);
        $this->assertSame(0, $this->sessao()->tentativas);
    }

    public function test_audio_onde_se_espera_texto_pede_para_escrever(): void
    {
        $this->irAtePlaca();
        $this->bot()->handleInbound($this->msg(ParsedPoliMessage::TYPE_UNKNOWN, null));

        $this->assertStringContainsString('só consigo ler mensagens de texto', $this->ultimoEnviado());
    }

    public function test_texto_onde_se_espera_imagem_pede_o_print(): void
    {
        $this->irAtePlaca();
        $this->texto('ABC1D23');
        $this->texto('já mandei');

        $this->assertStringContainsString('Preciso do print', $this->ultimoEnviado());
        $this->assertSame('print', $this->sessao()->step_key);
    }

    public function test_primeira_mensagem_que_ja_e_uma_opcao_pula_o_menu(): void
    {
        $this->texto('financeiro');

        Http::assertNotSent(fn (Request $r) => ($r->data()['type'] ?? null) === 'TEMPLATE');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/distribute')
            && ($r->data()['team'] ?? null) === DefaultFlows::TEAM_FINANCEIRO);
    }

    public function test_primeira_mensagem_qualquer_nao_e_tomada_por_opcao(): void
    {
        // Quem manda "1" sem ter visto menu nenhum não escolheu Achados e Perdidos.
        $this->texto('1');
        $this->assertSame('menu', $this->sessao()->step_key);
        $this->assertFalse($this->sessao()->isHuman());

        $this->texto('Bom dia, tudo bem?', contato: 'c9');
        $this->assertSame('menu', BotSession::find('c9')->step_key);
    }

    public function test_menu_errado_reenvia_as_opcoes(): void
    {
        $this->texto('oi');
        $this->texto('quero reclamar');

        $ultimas = PoliMessage::where('direction', 'OUT')->orderByDesc('id')->take(2)->get();
        $this->assertSame('TEMPLATE', $ultimas[0]->type);
        $this->assertStringContainsString('Ver opções', $ultimas[1]->texto);
        $this->assertSame($ultimas[0]->uuid, $this->sessao()->prompt_message_uuid);
    }

    public function test_toque_em_menu_antigo_nao_vale_como_resposta(): void
    {
        $this->texto('oi');                          // menu = out-1
        $this->texto('Carro de Aplicativo', 'out-1');  // agora pergunta a matrícula
        $this->texto('Financeiro', 'out-1');           // toque no menu de cima

        $this->assertStringContainsString('etapa anterior', implode(' ', $this->enviados()));
        $this->assertSame('matricula', $this->sessao()->step_key);
        $this->assertFalse($this->sessao()->isHuman());
    }

    public function test_palavra_atendente_passa_para_humano(): void
    {
        $this->irAtePlaca();
        $this->texto('Atendente');

        $this->assertTrue($this->sessao()->isHuman());
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/distribute') && ($r->data()['team'] ?? null) === 'time-geral');
    }

    public function test_palavra_sair_encerra(): void
    {
        $this->irAtePlaca();
        $this->texto('sair');

        $this->assertSame('idle', $this->sessao()->state);
        $this->assertStringContainsString('Atendimento encerrado', $this->ultimoEnviado());
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/close'));
    }

    public function test_palavra_menu_recomeca(): void
    {
        $this->irAtePlaca();
        $this->texto('MENU');

        $this->assertSame('atendimento', $this->sessao()->flow_slug);
        $this->assertSame('menu', $this->sessao()->step_key);
        $this->assertSame([], $this->sessao()->data);
    }

    public function test_conversa_parada_expira_e_recomeca(): void
    {
        $this->irAtePlaca();
        BotSession::where('contact_uuid', self::CONTACT)->update(['last_interaction_at' => now()->subMinutes(11)]);

        $this->texto('ABC1D23');

        $this->assertStringContainsString('ficou parada', implode(' ', $this->enviados()));
        $this->assertSame('menu', $this->sessao()->step_key);
        $this->assertSame(0, UberAccessRequest::count());
    }

    public function test_mesma_mensagem_duas_vezes_e_tratada_uma_vez(): void
    {
        $m = $this->msg(ParsedPoliMessage::TYPE_TEXT, 'oi');
        $this->bot()->handleInbound($m);
        $this->bot()->handleInbound($m);

        Http::assertSentCount(1);
    }

    public function test_cpf_e_data_ficam_mascarados_no_historico(): void
    {
        $this->irAtePlaca();
        $this->texto('meu cpf é 123.456.789-09 e nasci em 25/09/1980');

        $linha = PoliMessage::where('direction', 'IN')->orderByDesc('id')->first();
        $this->assertStringNotContainsString('123.456.789-09', $linha->texto);
        $this->assertStringNotContainsString('25/09/1980', $linha->texto);
    }

    public function test_em_sombra_o_pedido_do_uber_e_so_simulado(): void
    {
        config(['poli.bot.mode' => BotEngine::MODE_SHADOW]);

        $this->irAtePlaca();
        $this->texto('ABC1D23');
        $this->imagem();

        $this->assertSame(0, UberAccessRequest::count());
        $this->assertTrue(PoliMessage::where('texto', 'like', 'uber_request (simulado%')->exists());
    }

    public function test_falha_no_envio_fica_registrada_e_a_conversa_segue(): void
    {
        $this->poliForaDoAr = true;

        $this->texto('oi');

        $menu = PoliMessage::where('direction', 'OUT')->first();
        $this->assertSame('FAILED', $menu->ack);
        $this->assertStringContainsString('HTTP 500', $menu->error);
        $this->assertSame('menu', $this->sessao()->step_key);
    }

    /* ---------------- comandos ---------------- */

    public function test_simulador_conversa_sem_enviar_nada_mesmo_com_modo_on(): void
    {
        $this->artisan('poli:bot-simular', ['texto' => 'oi', '--contato' => 'sim'])
            ->expectsOutputToContain('[template ' . DefaultFlows::TPL_DEPARTAMENTOS)
            ->expectsOutputToContain('2) Financeiro')
            ->assertSuccessful();

        $this->artisan('poli:bot-simular', ['texto' => 'Carro de Aplicativo', '--contato' => 'sim'])
            ->expectsOutputToContain('*matrícula*')
            ->expectsOutputToContain('passo=matricula')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertTrue(PoliMessage::where('contact_uuid', 'sim')->where('direction', 'OUT')->get()->every->shadow);

        $this->artisan('poli:bot-simular', ['--contato' => 'sim', '--reset' => true])->assertSuccessful();
        $this->assertNull(BotSession::find('sim'));
    }

    public function test_comando_de_fluxos_lista_e_valida(): void
    {
        BotFlow::create(['slug' => 'quebrado', 'name' => 'Quebrado', 'active' => true, 'definition' => ['start' => 'x', 'steps' => []]]);

        $this->artisan('poli:bot-fluxos')
            ->expectsOutputToContain('atendimento')
            ->expectsOutputToContain('O fluxo não tem passos.')
            ->assertFailed();
    }

    public function test_instalar_nao_sobrescreve_edicao_sem_pedir(): void
    {
        BotFlow::where('slug', 'atendimento')->update(['name' => 'Editado à mão']);

        $this->artisan('poli:bot-fluxos', ['--instalar' => true])->assertSuccessful();
        $this->assertSame('Editado à mão', BotFlow::where('slug', 'atendimento')->value('name'));

        $this->artisan('poli:bot-fluxos', ['--instalar' => true, '--sobrescrever' => true])->assertSuccessful();
        $this->assertSame('Atendimento inicial', BotFlow::where('slug', 'atendimento')->value('name'));
    }

    /* ---------------- eventos observados ---------------- */

    public function test_atendimento_encerrado_devolve_o_contato_ao_bot(): void
    {
        $this->texto('oi');
        $this->texto('Financeiro');
        $this->assertTrue($this->sessao()->isHuman());

        $this->bot()->observe(['object' => 'message', 'event' => 'received', 'value' => [
            'event' => 'SYSTEM', 'type' => 'ATTENDANCE_CLOSED', 'direction' => 'SYSTEM',
            'contact' => ['uuid' => self::CONTACT], 'attendance' => null,
        ]]);

        $this->assertSame('idle', $this->sessao()->state);

        $this->texto('oi de novo');
        $this->assertSame('menu', $this->sessao()->step_key);
    }

    public function test_mensagem_de_atendente_silencia_o_bot(): void
    {
        $this->texto('oi');

        $this->bot()->observe($this->sent(['type' => 'USER', 'uuid' => 'atendente-1']));

        $this->assertTrue($this->sessao()->isHuman());
    }

    public function test_mensagem_do_bot_da_poli_nao_e_atendente(): void
    {
        $this->texto('oi');

        $this->bot()->observe($this->sent(['type' => 'USER']));

        $this->assertFalse($this->sessao()->isHuman());
    }

    public function test_eco_da_propria_mensagem_atualiza_o_ack_e_nao_silencia(): void
    {
        $this->texto('oi');   // out-1

        $this->bot()->observe($this->sent(['type' => 'USER', 'uuid' => 'dono-do-token'], 'out-1', 'READ_BY_CLIENT'));

        $this->assertFalse($this->sessao()->isHuman());
        $this->assertSame('READ_BY_CLIENT', PoliMessage::where('uuid', 'out-1')->value('ack'));
    }

    public function test_redirecionado_para_atendente_silencia(): void
    {
        $this->texto('oi');

        $this->bot()->observe(['object' => 'message', 'event' => 'received', 'value' => [
            'event' => 'SYSTEM', 'type' => 'ATTENDANCE_REDIRECTED', 'direction' => 'EMPTY',
            'contact' => ['uuid' => self::CONTACT],
            'attendance' => ['uuid' => 'att-1', 'attendant' => ['uuid' => 'atendente-1']],
        ]]);

        $this->assertTrue($this->sessao()->isHuman());
    }

    public function test_silencio_humano_tem_prazo(): void
    {
        $this->texto('oi');
        $this->texto('Financeiro');
        BotSession::where('contact_uuid', self::CONTACT)->update(['human_since' => now()->subHours(13)]);

        $this->texto('oi, voltei');

        $this->assertSame('menu', $this->sessao()->step_key);
    }

    /* ------------------------------------------------------------------ */

    private function irAtePlaca(): void
    {
        $this->texto('oi');
        $this->texto('Carro de Aplicativo');
        $this->texto('12345');
        $this->texto('Gustavo');
        $this->texto('Ginásio');

        $this->assertSame('placa', $this->sessao()->step_key);
    }

    private function sent(array $autor, string $uuid = 'msg-de-fora', string $ack = 'RECEIVED_BY_PROVIDER'): array
    {
        return ['object' => 'message', 'event' => 'sent', 'value' => [
            'uuid' => $uuid, 'event' => 'MESSAGE', 'type' => 'CHAT', 'direction' => 'OUT', 'ack' => $ack,
            'author' => $autor, 'contact' => ['uuid' => self::CONTACT],
            'attendance' => ['uuid' => 'att-1', 'closed_reason' => null],
            'components' => ['body' => ['text' => 'Olá, aqui é o atendente.']],
        ]];
    }
}
