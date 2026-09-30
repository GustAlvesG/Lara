<?php

namespace Tests\Feature\Poli;

use App\Jobs\ConfirmPoliBotHandoff;
use App\Models\BotFlow;
use App\Models\BotSession;
use App\Models\PoliMessage;
use App\Models\UberAccessRequest;
use App\Services\MultiClubes\TitleMemberLookup;
use App\Services\Poli\ParsedPoliMessage;
use App\Services\PoliBot\BotEngine;
use App\Services\PoliBot\BotSimulator;
use App\Services\PoliBot\DefaultFlows;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * O bot conduzindo a conversa, com os fluxos padrão (DefaultFlows) — os
 * mesmos que vão para produção.
 *
 * A conversa padrão destes testes é do usuário O Lara (atendimento atribuído
 * a ele e aberto): é só nela que a Lara fala. As outras — bot da Poli,
 * humano, encerrada — têm testes próprios.
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
    private const LARA = 'o-lara';

    private int $seq = 0;

    private bool $poliForaDoAr = false;

    private bool $envioSemUuid = false;

    /** Contato para o qual o GET responde 404. */
    private ?string $contatoInexistente = null;

    /** O que o GET /contacts/{uuid}?include=current_attendance devolve. */
    private ?array $atendimentoNaApi = null;

    /** O que o GET /accounts/{acc}/chats devolve. */
    private array $chatsNaApi = [];

    protected function setUp(): void
    {
        parent::setUp();

        // O fluxo padrão tem horário de atendimento: sem relógio fixo, a
        // suíte rodada à noite cairia toda no "fora do horário".
        Carbon::setTestNow('2026-09-23 10:00:00');   // quarta-feira

        foreach ([
            '2026_07_20_150000_create_uber_access_requests_tables.php',
            '2026_07_21_120000_add_matricula_to_uber_access_requests.php',
            '2026_08_27_170000_add_member_validation_to_uber_access_requests.php',
            '2026_09_10_100000_add_member_validation_type_to_uber_access_requests.php',
            '2026_01_05_141304_banco_de_horas.php',
            '2026_09_25_160000_create_poli_bot_tables.php',
            '2026_09_29_120000_add_lara_owner_to_bot_sessions.php',
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
            'poli.enabled' => true,
            'poli.base_url' => self::BASE,
            'poli.token' => 'token-teste',
            'poli.account_uuid' => 'acc-uuid',
            'poli.channel_uuid' => 'chan-uuid',
            'poli.http.retry_sleep_ms' => 0,
            'poli.bot.mode' => BotEngine::MODE_ON,
            'poli.bot.user_uuid' => self::LARA,
            'poli.bot.test_contacts' => [self::PHONE],
            'poli.bot.test_others_team_uuid' => null,
            'poli.bot.fallback_team_uuid' => 'time-geral',
            'poli.bot.close_after_minutes' => 10,
            'poli.bot.rescue_minutes' => 30,
        ]);

        foreach (DefaultFlows::all() as $slug => $fluxo) {
            BotFlow::create(['slug' => $slug, 'name' => $fluxo['name'], 'active' => true, 'definition' => $fluxo['definition']]);
        }

        // A conferência do transbordo roda depois, na fila. Aqui ela é
        // chamada à mão, nos testes dela.
        Queue::fake([ConfirmPoliBotHandoff::class]);

        Http::preventStrayRequests();

        $n = 0;
        Http::fake(function (Request $r) use (&$n) {
            $n++;

            if ($this->poliForaDoAr) {
                return Http::response(['message' => 'fora do ar'], 500);
            }

            if ($r->method() === 'GET' && str_contains($r->url(), '/chats')) {
                return Http::response(['data' => $this->chatsNaApi], 200);
            }

            if ($this->contatoInexistente !== null && str_contains($r->url(), '/contacts/' . $this->contatoInexistente . '?')) {
                return Http::response(['message' => 'Not found'], 404);
            }

            if ($r->method() === 'GET' && str_contains($r->url(), '/contacts/')) {
                return Http::response(['uuid' => 'x', 'current_attendance' => $this->atendimentoNaApi], 200);
            }

            // Medido em 29/09/2026: as ações de atendimento respondem 200
            // com {"message": …}, sem uuid.
            foreach (['/close', '/distribute', '/forward'] as $acao) {
                if (str_ends_with($r->url(), $acao)) {
                    return Http::response(['message' => 'ok'], 200);
                }
            }

            return $this->envioSemUuid
                ? Http::response(['message' => 'ok'], 200)
                : Http::response(['uuid' => "out-{$n}", 'ack' => 'CREATED'], 201);
        });
    }

    private function bot(): BotEngine
    {
        return app(BotEngine::class);
    }

    private function texto(string $texto, ?string $contexto = null, string $contato = self::CONTACT, ?string $atendente = self::LARA): void
    {
        $this->bot()->handleInbound($this->msg(ParsedPoliMessage::TYPE_TEXT, $texto, null, $contexto, $contato, atendente: $atendente));
    }

    private function imagem(string $url = 'https://cdn/print.jpg'): void
    {
        $this->bot()->handleInbound($this->msg(ParsedPoliMessage::TYPE_IMAGE, null, $url));
    }

    private function msg(
        string $tipo,
        ?string $texto,
        ?string $url = null,
        ?string $contexto = null,
        string $contato = self::CONTACT,
        ?string $attendanceType = null,
        ?string $attendanceStatus = 'IN_PROGRESS',
        ?string $atendente = self::LARA,
        string $atendimento = 'att-1',
        ?int $tentativa = null,
    ): ParsedPoliMessage {
        return new ParsedPoliMessage(
            messageId: 'in-' . (++$this->seq),
            contactUuid: $contato,
            contactPhone: $contato === self::CONTACT ? self::PHONE : '55249' . str_pad((string) crc32($contato) % 100000000, 8, '0'),
            contactName: 'Gustavo Coordenador de TI|Gustavo',
            attendanceUuid: $atendimento,
            type: $tipo,
            text: $texto,
            mediaUrl: $url,
            contextMessageUuid: $contexto,
            attendanceType: $attendanceType,
            attendanceStatus: $attendanceStatus,
            attendanceAttendantUuid: $atendente,
            webhookAttempt: $tentativa,
        );
    }

    private function redirect(string $atendimento = 'att-lara', string $atendente = self::LARA): ParsedPoliMessage
    {
        return new ParsedPoliMessage(
            messageId: 'sys-' . (++$this->seq),
            contactUuid: self::CONTACT,
            contactPhone: self::PHONE,
            contactName: 'Gustavo',
            attendanceUuid: $atendimento,
            type: ParsedPoliMessage::TYPE_UNKNOWN,
            attendanceType: 'INITIATED_BY_FORWARDING',
            attendanceStatus: 'IN_PROGRESS',
            attendanceAttendantUuid: $atendente,
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

    private function enviouPara(string $acao, ?callable $corpo = null): bool
    {
        $achou = false;
        Http::assertSent(function (Request $r) use ($acao, $corpo, &$achou) {
            $ok = $r->url() === self::BASE . '/contacts/' . self::CONTACT . '/' . $acao && ($corpo === null || $corpo($r->data()));
            $achou = $achou || $ok;

            return true;
        });

        return $achou;
    }

    /* ---------------- regra de dono ---------------- */

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
        $this->assertTrue($s->lara_owned);
        $this->assertSame('atendimento', $s->flow_slug);
        $this->assertSame('menu', $s->step_key);
        $this->assertSame('out-1', $s->prompt_message_uuid);

        $menu = PoliMessage::where('uuid', 'out-1')->first();
        $this->assertSame('Financeiro', $menu->options[1]['label']);
        $this->assertFalse($menu->shadow);
    }

    /**
     * O Lara é a própria Lara: a conversa dele não tem outro atendente, então
     * é respondida de verdade também em shadow. A sombra é só para a
     * comparação das conversas do bot da Poli.
     */
    public function test_em_sombra_a_conversa_do_o_lara_e_respondida_de_verdade(): void
    {
        config(['poli.bot.mode' => BotEngine::MODE_SHADOW]);

        $this->texto('oi');
        $this->texto("Financeiro\nConsulte seus débitos ou outras pendências.");

        Http::assertSent(fn (Request $r) => ($r->data()['template_uuid'] ?? null) === DefaultFlows::TPL_DEPARTAMENTOS);
        $this->assertTrue($this->enviouPara('distribute', fn ($d) => ($d['team'] ?? null) === DefaultFlows::TEAM_FINANCEIRO));
        $this->assertFalse(PoliMessage::where('direction', 'OUT')->get()->contains->shadow);
        $this->assertTrue($this->sessao()->isHuman());
        Queue::assertPushed(ConfirmPoliBotHandoff::class);
    }

    public function test_em_sombra_a_conversa_do_bot_da_poli_so_e_registrada(): void
    {
        config(['poli.bot.mode' => BotEngine::MODE_SHADOW]);

        $this->texto('oi', atendente: null);
        $this->texto('Financeiro', atendente: null);

        Http::assertNothingSent();
        $this->assertTrue(PoliMessage::where('direction', 'OUT')->get()->every->shadow);
        Queue::assertNotPushed(ConfirmPoliBotHandoff::class);
    }

    /**
     * Atendimento sem atendente é o bot da Poli conversando: no modo on a
     * Lara só compara, em sombra.
     */
    public function test_conversa_do_bot_da_poli_e_so_comparacao_em_sombra(): void
    {
        $this->texto('oi', atendente: null);

        Http::assertNothingSent();
        $this->assertTrue(PoliMessage::where('direction', 'OUT')->get()->every->shadow);
        $this->assertSame('menu', $this->sessao()->step_key);
        $this->assertFalse($this->sessao()->lara_owned);
    }

    public function test_outro_atendente_silencia_e_marca_humano(): void
    {
        $this->texto('oi');
        $this->texto('alô', atendente: 'atendente-ana');

        $this->assertTrue($this->sessao()->isHuman());
        $this->assertCount(1, $this->enviados(), 'só o menu da primeira mensagem');
    }

    public function test_atendimento_do_o_lara_encerrado_nao_responde(): void
    {
        $this->bot()->handleInbound($this->msg(ParsedPoliMessage::TYPE_TEXT, 'Olá', attendanceStatus: 'CLOSED'));

        Http::assertNothingSent();
        $this->assertSame(1, PoliMessage::where('direction', 'IN')->count(), 'o histórico registra mesmo assim');
    }

    /**
     * Medido em produção em 25/09/2026: o bot da Poli não responde em
     * atendimento aberto pela empresa. A comparação em sombra segue a regra.
     */
    public function test_atendimento_iniciado_pela_empresa_nao_e_do_bot(): void
    {
        $this->bot()->handleInbound($this->msg(ParsedPoliMessage::TYPE_TEXT, 'Olá', attendanceType: 'INITIATED_BY_BUSINESS', atendente: null));

        Http::assertNothingSent();
        $this->assertSame(0, PoliMessage::where('direction', 'OUT')->count());
    }

    /**
     * Evento reenviado (X-Webhook-Attempt > 1) pode trazer o atendimento da
     * primeira entrega. Vale o que a API diz agora.
     */
    public function test_evento_reenviado_confere_o_dono_na_api(): void
    {
        $this->atendimentoNaApi = ['uuid' => 'att-1', 'status' => 'IN_PROGRESS', 'attendant' => ['uuid' => 'atendente-ana']];

        $this->bot()->handleInbound($this->msg(ParsedPoliMessage::TYPE_TEXT, 'oi', tentativa: 2));

        $this->assertTrue($this->sessao()->isHuman());
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && str_contains($r->url(), 'include=current_attendance'));
    }

    public function test_primeira_entrega_nao_consulta_a_api(): void
    {
        $this->texto('oi');

        Http::assertNotSent(fn (Request $r) => $r->method() === 'GET');
    }

    /* ---------------- conversa ---------------- */

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

    public function test_departamento_passa_para_o_time_certo_silencia_e_agenda_a_conferencia(): void
    {
        $this->texto('oi');
        $this->texto('Financeiro');

        $this->assertTrue($this->enviouPara('distribute', fn ($d) => ($d['team'] ?? null) === DefaultFlows::TEAM_FINANCEIRO));
        $this->assertStringContainsString('*Financeiro*', $this->ultimoEnviado());
        $this->assertSame('DONE', PoliMessage::where('type', 'ACTION')->where('texto', 'like', 'handoff%')->value('ack'),
            'distribute 200 sem uuid é sucesso');
        Queue::assertPushed(ConfirmPoliBotHandoff::class, fn ($job) => $job->contactUuid === self::CONTACT);

        // A mensagem logo depois ainda pode vir com O Lara como atendente;
        // a API diz que o atendimento já saiu dele.
        $this->atendimentoNaApi = ['uuid' => 'att-1', 'status' => 'IN_PROGRESS', 'attendant' => ['uuid' => 'atendente-ana']];
        $antes = PoliMessage::where('direction', 'OUT')->count();
        $this->texto('alô? tem alguém?');

        $this->assertSame($antes, PoliMessage::where('direction', 'OUT')->count(), 'Com humano no atendimento o bot não fala');
    }

    /**
     * Toda mensagem do O Lara é respondida: se depois do transbordo o
     * atendimento continua com ele (confirmado na API), a Lara responde.
     */
    public function test_mensagem_com_o_lara_depois_de_transbordo_que_nao_pegou_e_respondida(): void
    {
        $this->texto('oi');
        $this->texto('Financeiro');
        $this->assertTrue($this->sessao()->isHuman());

        $this->atendimentoNaApi = ['uuid' => 'att-1', 'status' => 'IN_PROGRESS', 'attendant' => ['uuid' => self::LARA]];
        $this->texto('ninguém me respondeu');

        $this->assertFalse($this->sessao()->isHuman());
        $this->assertSame('menu', $this->sessao()->step_key);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && str_contains($r->url(), 'include=current_attendance'));
    }

    public function test_fluxo_do_uber_completo_cria_o_pedido_e_fica_em_encerramento_diferido(): void
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
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/close'));

        $s = $this->sessao();
        $this->assertSame(BotSession::STATE_ENDING, $s->state);
        $this->assertTrue($s->ending_at->equalTo(now()->addMinutes(10)));
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

    public function test_palavra_sair_encerra_e_registra_o_atendimento_fechado(): void
    {
        $this->irAtePlaca();
        $this->texto('sair');

        $s = $this->sessao();
        $this->assertSame('idle', $s->state);
        $this->assertSame('att-1', $s->closed_attendance_uuid);
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
        $this->assertTrue($this->sessao()->lara_owned);
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

    public function test_parada_no_menu_inicial_recebe_o_menu_sem_aviso_de_expiracao(): void
    {
        $this->texto('oi');
        BotSession::where('contact_uuid', self::CONTACT)->update(['last_interaction_at' => now()->subHours(2)]);

        $this->texto('oi de novo');

        $this->assertStringNotContainsString('ficou parada', implode(' ', $this->enviados()));
        $this->assertSame('menu', $this->sessao()->step_key);
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

    /** `uber_request` é real em toda conversa do O Lara, sem depender do modo. */
    public function test_em_sombra_a_conversa_do_o_lara_cria_o_pedido_de_verdade(): void
    {
        config(['poli.bot.mode' => BotEngine::MODE_SHADOW]);

        $this->irAtePlaca();
        $this->texto('ABC1D23');
        $this->imagem();

        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_ACESSO, UberAccessRequest::sole()->status);
    }

    /**
     * Fase 3, como está em produção (30/09/2026): o "fim" pergunta se o
     * motorista trocou, e "Trocar Veículo" volta para a placa. O segundo
     * registro sobrescreve o pedido da conversa — a placa antiga não pode
     * continuar liberada na portaria.
     */
    public function test_voltar_para_a_placa_sobrescreve_o_pedido_da_conversa(): void
    {
        $this->fimPerguntaSeTrocouOVeiculo();

        $this->irAtePlaca();
        $this->texto('ABC1234');
        $this->imagem('https://cdn/print-1.jpg');
        $primeiro = UberAccessRequest::sole();
        $this->assertSame('fim', $this->sessao()->step_key);

        $this->travel(5)->minutes();
        $this->texto('Trocar Veículo');
        $this->assertSame('placa', $this->sessao()->step_key);
        $this->texto('DEF4321');
        $this->imagem('https://cdn/print-2.jpg');

        $pedido = UberAccessRequest::sole();
        $this->assertSame($primeiro->id, $pedido->id);
        $this->assertSame('DEF4321', $pedido->vehicle_plate);
        $this->assertSame('https://cdn/print-2.jpg', $pedido->screenshot_url);
        $this->assertSame('12345', $pedido->matricula);
        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_ACESSO, $pedido->status);
        $this->assertTrue($pedido->expires_at->greaterThan($primeiro->expires_at), 'a validade recomeça com o carro novo');
        $this->assertTrue(PoliMessage::where('texto', "uber_request pedido={$pedido->id} atualizado")->exists());
    }

    /** O pedido que já saiu da espera (motorista entrou, validade venceu) não é reaberto. */
    public function test_voltar_depois_do_pedido_encerrado_abre_outro(): void
    {
        $this->fimPerguntaSeTrocouOVeiculo();

        $this->irAtePlaca();
        $this->texto('ABC1234');
        $this->imagem();
        UberAccessRequest::query()->update(['status' => UberAccessRequest::STATUS_CONCLUIDO]);

        $this->texto('Trocar Veículo');
        $this->texto('DEF4321');
        $this->imagem();

        $this->assertSame(2, UberAccessRequest::count());
        $this->assertSame('ABC1234', UberAccessRequest::orderBy('id')->first()->vehicle_plate);
    }

    /** Na comparação em sombra quem cria o pedido é a escuta do Uber: aqui seria duplicado. */
    public function test_na_comparacao_com_o_bot_da_poli_o_pedido_e_so_simulado(): void
    {
        foreach (['oi', 'Carro de Aplicativo', '12345', 'Gustavo', 'Ginásio', 'ABC1D23'] as $texto) {
            $this->texto($texto, atendente: null);
        }
        $this->bot()->handleInbound($this->msg(ParsedPoliMessage::TYPE_IMAGE, null, 'https://cdn/print.jpg', atendente: null));

        $this->assertSame(0, UberAccessRequest::count());
        $this->assertTrue(PoliMessage::where('texto', 'like', 'uber_request (simulado%')->exists());
        Http::assertNothingSent();
    }

    /**
     * Para a MENSAGEM, 2xx sem uuid continua sendo falha — é o sintoma do
     * endpoint que aceita tudo e não entrega nada. Para distribute/close, o
     * mesmo 200 sem uuid é sucesso (ver o teste do transbordo).
     */
    public function test_mensagem_aceita_sem_uuid_continua_sendo_falha(): void
    {
        $this->envioSemUuid = true;

        $this->texto('oi');

        $menu = PoliMessage::where('direction', 'OUT')->first();
        $this->assertSame('FAILED', $menu->ack);
        $this->assertStringContainsString('sem uuid', $menu->error);
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

    /* ---------------- horário de atendimento ---------------- */

    public function test_fora_do_horario_manda_o_menu_de_opcoes(): void
    {
        Carbon::setTestNow('2026-09-23 21:30:00');   // quarta, depois das 19:50

        $this->texto('oi');

        Http::assertSent(fn (Request $r) => ($r->data()['template_uuid'] ?? null) === DefaultFlows::TPL_FORA_DO_HORARIO);
        $this->assertSame('fora_do_horario', $this->sessao()->step_key);
    }

    public function test_fora_do_horario_o_carro_de_aplicativo_continua_funcionando(): void
    {
        Carbon::setTestNow('2026-09-27 23:00:00');   // domingo à noite

        $this->texto('oi');
        $this->texto("Carro de Aplicativo\nCarro, moto ou táxi");

        $this->assertSame('carro-de-aplicativo', $this->sessao()->flow_slug);
        $this->assertSame('matricula', $this->sessao()->step_key);
    }

    public function test_fora_do_horario_os_departamentos_nao_sao_oferecidos(): void
    {
        Carbon::setTestNow('2026-09-23 06:30:00');

        $this->texto('oi');
        $this->texto('Financeiro');

        $this->assertFalse($this->sessao()->isHuman(), 'Financeiro não é opção fora do horário');
        $this->assertStringContainsString('Fora do horário', implode(' ', $this->enviados()));
    }

    /**
     * O horário vale no momento do transbordo: a conversa começou às 19:45,
     * a escolha veio às 19:55.
     */
    public function test_transbordo_depois_do_fechamento_vai_para_o_passo_de_fora_do_horario(): void
    {
        Carbon::setTestNow('2026-09-23 19:45:00');
        $this->texto('oi');

        Carbon::setTestNow('2026-09-23 19:55:00');
        $this->texto('Financeiro');

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/distribute'));
        Http::assertSent(fn (Request $r) => ($r->data()['template_uuid'] ?? null) === DefaultFlows::TPL_FORA_DO_HORARIO);
        $this->assertStringNotContainsString('Vou te encaminhar', implode(' ', $this->enviados()));
        $this->assertSame('fora_do_horario', $this->sessao()->step_key);
        $this->assertFalse($this->sessao()->isHuman());
    }

    /** O fluxo do carro não tem horário: vale o do fluxo de boas-vindas. */
    public function test_atendente_a_noite_no_fluxo_do_carro_tambem_respeita_o_horario(): void
    {
        Carbon::setTestNow('2026-09-27 23:00:00');
        $this->texto('oi');
        $this->texto('Carro de Aplicativo');
        $this->texto('atendente');

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/distribute'));
        $this->assertSame('fora_do_horario', $this->sessao()->step_key);
    }

    public function test_sabado_e_feriado_seguem_o_horario_de_fim_de_semana(): void
    {
        $fluxo = BotFlow::where('slug', 'atendimento')->first()->flow();

        $this->assertTrue($fluxo->isOpenAt(Carbon::parse('2026-09-26 17:30')));   // sábado
        $this->assertFalse($fluxo->isOpenAt(Carbon::parse('2026-09-26 18:30')));
        $this->assertTrue($fluxo->isOpenAt(Carbon::parse('2026-09-23 19:45')));   // quarta
        $this->assertFalse($fluxo->isOpenAt(Carbon::parse('2026-09-23 19:50')));

        $def = BotFlow::where('slug', 'atendimento')->first()->definition;
        $def['hours']['holidays'] = ['2026-09-23'];
        $feriado = new \App\Services\PoliBot\FlowDefinition('x', $def);

        $this->assertFalse($feriado->isOpenAt(Carbon::parse('2026-09-23 19:00')), 'feriado fecha às 18:00');
    }

    /* ---------------- transferência para O Lara ---------------- */

    /**
     * O toque no menu do bot da Poli ("Carro de Aplicativo") é a última
     * mensagem antes da transferência: é o gatilho da Lara.
     */
    public function test_transferencia_abre_o_fluxo_pelo_gatilho_da_ultima_mensagem(): void
    {
        $this->texto("Carro de Aplicativo\nCarro, moto ou táxi", atendente: null);   // fase do bot da Poli
        Http::assertNothingSent();

        $this->bot()->handleRedirect($this->redirect());

        $s = $this->sessao();
        $this->assertTrue($s->lara_owned);
        $this->assertSame('att-lara', $s->attendance_uuid);
        $this->assertSame('carro-de-aplicativo', $s->flow_slug);
        $this->assertSame('matricula', $s->step_key);
        $this->assertStringContainsString('*matrícula*', $this->ultimoEnviado());
        Http::assertSentCount(1);
    }

    public function test_transferencia_sem_mensagem_que_combine_abre_o_menu(): void
    {
        $this->bot()->handleRedirect($this->redirect());

        Http::assertSent(fn (Request $r) => ($r->data()['template_uuid'] ?? null) === DefaultFlows::TPL_DEPARTAMENTOS);
        $this->assertSame('atendimento', $this->sessao()->flow_slug);
    }

    public function test_transferencia_repetida_e_tratada_uma_vez(): void
    {
        $r = $this->redirect();
        $this->bot()->handleRedirect($r);
        $this->bot()->handleRedirect($r);

        Http::assertSentCount(1);
    }

    /** A primeira mensagem do contato foi processada antes do evento da transferência. */
    public function test_transferencia_que_chega_depois_da_primeira_mensagem_nao_repete_o_menu(): void
    {
        $this->texto('oi', atendente: self::LARA);
        $this->bot()->handleRedirect($this->redirect('att-1'));

        Http::assertSentCount(1);
        $this->assertSame('menu', $this->sessao()->step_key);
    }

    public function test_transferencia_para_outro_atendente_nao_abre_nada(): void
    {
        $this->bot()->handleRedirect($this->redirect(atendente: 'atendente-ana'));

        Http::assertNothingSent();
        $this->assertNull(BotSession::find(self::CONTACT));
    }

    /* ---------------- transbordo: conferência ---------------- */

    public function test_transbordo_que_nao_trocou_o_atendente_avisa_o_contato_e_registra(): void
    {
        Log::spy();
        $this->texto('oi');
        $this->texto('Financeiro');

        $this->atendimentoNaApi = ['uuid' => 'att-1', 'status' => 'IN_PROGRESS', 'attendant' => ['uuid' => self::LARA]];
        (new ConfirmPoliBotHandoff(self::CONTACT))->handle($this->bot());

        $this->assertStringContainsString('Não consegui te transferir', $this->ultimoEnviado());
        $this->assertSame(BotSession::STATE_ENDING, $this->sessao()->state);
        Log::shouldHaveReceived('error')->withArgs(fn ($m) => str_contains($m, 'continua com O Lara'))->once();
    }

    public function test_transbordo_que_trocou_o_atendente_nao_faz_nada(): void
    {
        $this->texto('oi');
        $this->texto('Financeiro');
        $antes = count($this->enviados());

        $this->atendimentoNaApi = ['uuid' => 'att-1', 'status' => 'IN_PROGRESS', 'attendant' => ['uuid' => 'atendente-ana']];
        (new ConfirmPoliBotHandoff(self::CONTACT))->handle($this->bot());

        $this->assertCount($antes, $this->enviados());
        $this->assertTrue($this->sessao()->isHuman());
    }

    /* ---------------- fim do fluxo e agendamento ---------------- */

    public function test_encerramento_diferido_fecha_pelo_agendamento_e_registra_o_atendimento(): void
    {
        $this->irAteOFimDoUber();

        $this->artisan('poli:bot-expirar')->assertSuccessful();
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/close'));

        $this->travel(11)->minutes();
        $this->artisan('poli:bot-expirar')->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r->url() === self::BASE . '/contacts/' . self::CONTACT . '/close');
        $s = $this->sessao();
        $this->assertSame('idle', $s->state);
        $this->assertFalse($s->lara_owned);
        $this->assertSame('att-1', $s->closed_attendance_uuid);
        $this->assertNotNull($s->closed_at);
    }

    public function test_mensagem_dentro_do_prazo_abre_o_menu_sem_encerrar(): void
    {
        $this->irAteOFimDoUber();

        $this->travel(3)->minutes();
        $this->texto('obrigado!');

        $this->assertSame('atendimento', $this->sessao()->flow_slug);
        $this->assertSame('menu', $this->sessao()->step_key);
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/close'));

        $this->travel(11)->minutes();
        $this->artisan('poli:bot-expirar')->assertSuccessful();
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/close'));   // no menu, o prazo é o do fluxo (15)
    }

    public function test_conversa_abandonada_no_meio_e_encerrada_pelo_agendamento(): void
    {
        $this->irAtePlaca();

        $this->travel(11)->minutes();   // timeout do fluxo do carro: 10
        $this->artisan('poli:bot-expirar')->assertSuccessful();

        $this->assertStringContainsString('Como não tivemos resposta', $this->ultimoEnviado());
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/close'));
        $this->assertSame('idle', $this->sessao()->state);
    }

    /** Pedido feito e ninguém quis trocar o carro: nada de "não tivemos resposta". */
    public function test_pergunta_opcional_sem_resposta_fecha_em_silencio(): void
    {
        $this->fimPerguntaSeTrocouOVeiculo(opcional: true);
        $this->irAtePlaca();
        $this->texto('ABC1D23');
        $this->imagem();
        $enviadas = count($this->enviados());

        $this->travel(11)->minutes();
        $this->artisan('poli:bot-expirar')->assertSuccessful();

        $this->assertCount($enviadas, $this->enviados());
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/close'));
        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_ACESSO, UberAccessRequest::sole()->status);
    }

    /** "obrigado" no fim não é resposta errada: sem correção, sem transbordo — recomeça pelo menu. */
    public function test_pergunta_opcional_com_outra_resposta_encerra_o_fluxo(): void
    {
        $this->fimPerguntaSeTrocouOVeiculo(opcional: true);
        $this->irAtePlaca();
        $this->texto('ABC1D23');
        $this->imagem();

        $this->texto('obrigado');

        $this->assertStringNotContainsString('Não entendi', implode(' ', $this->enviados()));
        $this->assertSame('atendimento', $this->sessao()->flow_slug);
        $this->assertSame('menu', $this->sessao()->step_key);
        $this->assertSame(0, $this->sessao()->tentativas);
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/distribute'));
        $this->assertSame(1, UberAccessRequest::count());
    }

    public function test_pergunta_opcional_ainda_aceita_a_opcao(): void
    {
        $this->fimPerguntaSeTrocouOVeiculo(opcional: true);
        $this->irAtePlaca();
        $this->texto('ABC1D23');
        $this->imagem();

        $this->texto('Trocar Veículo');

        $this->assertSame('carro-de-aplicativo', $this->sessao()->flow_slug);
        $this->assertSame('placa', $this->sessao()->step_key);
    }

    public function test_pergunta_opcional_exige_resposta_esperada(): void
    {
        $fluxo = new \App\Services\PoliBot\FlowDefinition('x', [
            'start' => 'a',
            'triggers' => ['any' => true],
            'steps' => ['a' => ['say' => ['type' => 'text', 'text' => 'oi'], 'optional' => true]],
        ]);

        $this->assertContains('Passo "a": pergunta opcional sem resposta esperada.', $fluxo->errors());
    }

    public function test_agendamento_nao_toca_na_comparacao_em_sombra_nem_no_simulador(): void
    {
        $this->texto('oi', atendente: null);               // bot da Poli
        app(BotSimulator::class)->send('painel-1', 'oi');  // simulador

        $this->travel(2)->hours();
        $this->artisan('poli:bot-expirar')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_agendamento_no_modo_off_nao_faz_nada(): void
    {
        $this->irAteOFimDoUber();
        config(['poli.bot.mode' => BotEngine::MODE_OFF]);

        $this->travel(11)->minutes();
        $this->artisan('poli:bot-expirar')->assertSuccessful();

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/close'));
    }

    /* ---------------- resgate ---------------- */

    /**
     * Até ~1 minuto depois de um close, a primeira mensagem fica presa no
     * atendimento fechado (medido em 29/09/2026). Se foi a Lara quem fechou,
     * ela traz o contato de volta para O Lara e abre o menu.
     */
    public function test_mensagem_presa_no_atendimento_que_a_lara_fechou_e_resgatada(): void
    {
        $this->irAteOFimDoUber();
        $this->travel(11)->minutes();
        $this->artisan('poli:bot-expirar');

        $this->travel(30)->seconds();
        $this->bot()->handleInbound($this->msg(ParsedPoliMessage::TYPE_TEXT, 'oi', attendanceStatus: 'CLOSED'));

        $this->assertTrue($this->enviouPara('forward', fn ($d) => $d === ['user_uuid' => self::LARA]));
        Http::assertSent(fn (Request $r) => ($r->data()['template_uuid'] ?? null) === DefaultFlows::TPL_DEPARTAMENTOS
            && $r->url() === self::BASE . '/contacts/' . self::CONTACT . '/messages');

        $s = $this->sessao();
        $this->assertSame('menu', $s->step_key);
        $this->assertTrue($s->lara_owned);
        $this->assertNull($s->attendance_uuid, 'adota o atendimento novo quando ele aparecer');

        // A transferência que o forward gera não abre o menu de novo.
        $antes = count($this->enviados());
        $this->bot()->handleRedirect($this->redirect('att-novo'));
        $this->assertCount($antes, $this->enviados());
        $this->assertSame('att-novo', $this->sessao()->attendance_uuid);
    }

    public function test_mensagem_em_atendimento_que_outro_fechou_nao_e_resgatada(): void
    {
        $this->irAteOFimDoUber();
        $this->bot()->handleInbound($this->msg(ParsedPoliMessage::TYPE_TEXT, 'oi', attendanceStatus: 'CLOSED', atendimento: 'att-outro'));

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/forward'));
    }

    public function test_resgate_tem_prazo(): void
    {
        $this->irAteOFimDoUber();
        $this->travel(11)->minutes();
        $this->artisan('poli:bot-expirar');

        $this->travel(31)->minutes();
        $this->bot()->handleInbound($this->msg(ParsedPoliMessage::TYPE_TEXT, 'oi', attendanceStatus: 'CLOSED'));

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/forward'));
    }

    /* ---------------- reconciliação ---------------- */

    public function test_reconciliacao_encerra_conversa_do_o_lara_sem_sessao_na_lara(): void
    {
        // Formato real do item (29/09/2026): o contato vem em `uuid`.
        $this->chatsNaApi = [[
            'id' => 68849486, 'uuid' => self::CONTACT, 'contact_origin' => 'orgânico',
            'attendance_origin' => 'INITIATED_BY_FORWARDING', 'from_campaign' => false,
        ]];
        $this->atendimentoNaApi = ['uuid' => 'att-perdido', 'status' => 'IN_PROGRESS', 'attendant' => ['uuid' => self::LARA]];

        $this->artisan('poli:bot-reconciliar')->expectsOutputToContain('encerrar')->assertSuccessful();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/accounts/acc-uuid/chats')
            && str_contains($r->url(), 'assigned=' . self::LARA) && str_contains($r->url(), 'status=OPEN'));
        Http::assertSent(fn (Request $r) => $r->url() === self::BASE . '/contacts/' . self::CONTACT . '/close');
        $this->assertSame('att-perdido', $this->sessao()->closed_attendance_uuid);
    }

    public function test_reconciliacao_poupa_sessao_ativa_e_contato_que_a_api_nao_confirma(): void
    {
        $this->texto('oi');   // sessão ativa com O Lara
        $this->chatsNaApi = [['contact' => ['uuid' => self::CONTACT]], ['contact' => ['uuid' => 'c-humano']]];
        $this->atendimentoNaApi = ['uuid' => 'att-x', 'status' => 'IN_PROGRESS', 'attendant' => ['uuid' => 'atendente-ana']];

        $this->artisan('poli:bot-reconciliar')->assertSuccessful();

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/close'));
    }

    /** Item cujo uuid a API não reconhece como contato é pulado; os outros seguem. */
    public function test_reconciliacao_pula_item_com_erro_e_segue_com_os_outros(): void
    {
        $this->chatsNaApi = [['uuid' => 'nao-e-contato'], ['uuid' => self::CONTACT]];
        $this->atendimentoNaApi = ['uuid' => 'att-1', 'status' => 'IN_PROGRESS', 'attendant' => ['uuid' => self::LARA]];
        $this->contatoInexistente = 'nao-e-contato';

        $this->artisan('poli:bot-reconciliar')->assertSuccessful();

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/contacts/nao-e-contato/close'));
        Http::assertSent(fn (Request $r) => $r->url() === self::BASE . '/contacts/' . self::CONTACT . '/close');
    }

    public function test_reconciliacao_aborta_se_a_lista_vier_grande_demais(): void
    {
        config(['poli.bot.reconcile_max_chats' => 2]);
        $this->chatsNaApi = array_map(fn ($i) => ['contact' => ['uuid' => "c{$i}"]], range(1, 3));
        $this->atendimentoNaApi = ['uuid' => 'att', 'status' => 'IN_PROGRESS', 'attendant' => ['uuid' => self::LARA]];

        $this->artisan('poli:bot-reconciliar')->assertSuccessful();

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/close'));
    }

    public function test_devolver_distribui_tudo_do_o_lara_para_a_secretaria_mesmo_no_modo_off(): void
    {
        $this->texto('oi');
        config(['poli.bot.mode' => BotEngine::MODE_OFF]);
        $this->chatsNaApi = [['contact' => ['uuid' => self::CONTACT]]];
        $this->atendimentoNaApi = ['uuid' => 'att-1', 'status' => 'IN_PROGRESS', 'attendant' => ['uuid' => self::LARA]];

        $this->artisan('poli:bot-reconciliar', ['--devolver' => null])->assertSuccessful();   // `--devolver` sem valor

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/contacts/' . self::CONTACT . '/distribute')
            && ($r->data()['team'] ?? null) === DefaultFlows::TEAM_SECRETARIA);
    }

    public function test_simular_nao_age(): void
    {
        $this->chatsNaApi = [['contact' => ['uuid' => self::CONTACT]]];
        $this->atendimentoNaApi = ['uuid' => 'att-1', 'status' => 'IN_PROGRESS', 'attendant' => ['uuid' => self::LARA]];

        $this->artisan('poli:bot-reconciliar', ['--simular' => true])->expectsOutputToContain('(simulado)')->assertSuccessful();

        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST');
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
        $this->assertSame(self::LARA, config('poli.bot.user_uuid'), 'o simulador devolve a config');

        $this->artisan('poli:bot-simular', ['--contato' => 'sim', '--reset' => true])->assertSuccessful();
        $this->assertNull(BotSession::find('sim'));
    }

    public function test_simulador_funciona_sem_o_usuario_o_lara_configurado(): void
    {
        config(['poli.bot.user_uuid' => null]);

        $resultado = app(BotSimulator::class)->send('sim', 'oi');

        $this->assertNotEmpty($resultado['replies']);
        $this->assertNull(config('poli.bot.user_uuid'));
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
        $this->assertFalse($this->sessao()->lara_owned);

        $this->texto('oi de novo', atendente: self::LARA);
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

        $this->bot()->observe($this->redirecionado('atendente-1'));

        $this->assertTrue($this->sessao()->isHuman());
    }

    /** A transferência PARA O Lara não é humano assumindo: é o gatilho da Lara. */
    public function test_redirecionado_para_o_lara_nao_silencia(): void
    {
        $this->texto('oi');

        $this->bot()->observe($this->redirecionado(self::LARA));

        $this->assertFalse($this->sessao()->isHuman());
    }

    public function test_evento_com_outro_atendente_no_atendimento_silencia(): void
    {
        $this->texto('oi');

        $evento = $this->sent(['type' => 'USER']);
        $evento['value']['attendance']['attendant'] = ['uuid' => 'atendente-ana'];
        $this->bot()->observe($evento);

        $this->assertTrue($this->sessao()->isHuman());
    }

    public function test_silencio_humano_tem_prazo(): void
    {
        $this->texto('oi', atendente: null);
        $this->texto('Financeiro', atendente: null);
        BotSession::where('contact_uuid', self::CONTACT)->update(['human_since' => now()->subHours(13)]);

        $this->texto('oi, voltei', atendente: null);

        $this->assertSame('menu', $this->sessao()->step_key);
    }

    /* ---------------- aviso de chegada do Uber ---------------- */

    public function test_aviso_do_uber_com_humano_no_atendimento_so_avisa(): void
    {
        $this->atendimentoNaApi = ['uuid' => 'att-h', 'status' => 'IN_PROGRESS', 'attendant' => ['uuid' => 'atendente-ana']];

        $this->avisoDoUber();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/messages')
            && !str_contains((string) data_get($r->data(), 'components.body.text'), 'encerrado'));
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/close') || str_ends_with($r->url(), '/forward'));
    }

    public function test_aviso_do_uber_sem_atendimento_passa_para_o_lara_e_fica_em_encerramento_diferido(): void
    {
        $this->atendimentoNaApi = null;

        $this->avisoDoUber();

        Http::assertSentInOrder([
            fn (Request $r) => $r->method() === 'GET',
            fn (Request $r) => str_ends_with($r->url(), '/forward') && $r->data() === ['user_uuid' => self::LARA],
            fn (Request $r) => str_ends_with($r->url(), '/messages')
                && str_contains((string) data_get($r->data(), 'components.body.text'), 'responder por aqui'),
        ]);
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/close'));

        $s = $this->sessao();
        $this->assertSame(BotSession::STATE_ENDING, $s->state);
        $this->assertTrue($s->lara_owned);

        // A resposta do sócio chega à Lara.
        $this->bot()->handleInbound($this->msg(ParsedPoliMessage::TYPE_TEXT, 'obrigado', atendimento: 'att-novo'));
        $this->assertSame('menu', $this->sessao()->step_key);
        $this->assertSame('att-novo', $this->sessao()->attendance_uuid);
    }

    public function test_aviso_do_uber_com_o_lara_ja_no_atendimento_nao_encaminha_de_novo(): void
    {
        $this->irAteOFimDoUber();
        $this->atendimentoNaApi = ['uuid' => 'att-1', 'status' => 'IN_PROGRESS', 'attendant' => ['uuid' => self::LARA]];

        $this->avisoDoUber();

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/forward'));
        $this->assertSame('att-1', $this->sessao()->attendance_uuid);
        $this->assertSame(BotSession::STATE_ENDING, $this->sessao()->state);
    }

    /**
     * A opção "Funcionalidade Teste" aparece para todo mundo no menu da Poli.
     * Em shadow, quem não é número de teste vai direto para a Secretaria —
     * mesmo à noite, sem passar pelo fora do horário.
     */
    public function test_piloto_em_sombra_numero_fora_da_lista_vai_para_a_secretaria(): void
    {
        config(['poli.bot.mode' => BotEngine::MODE_SHADOW, 'poli.bot.test_contacts' => ['5524992689647']]);
        Carbon::setTestNow('2026-09-23 22:00:00');

        $this->texto("Funcionalidade Teste\n<em desenvolvimento>", atendente: null);
        $this->bot()->handleRedirect($this->redirect('att-1'));

        $this->assertTrue($this->enviouPara('distribute', fn ($d) => ($d['team'] ?? null) === DefaultFlows::TEAM_SECRETARIA));
        $this->assertStringContainsString('*Secretaria*', $this->ultimoEnviado());
        Http::assertNotSent(fn (Request $r) => ($r->data()['type'] ?? null) === 'TEMPLATE');
        $this->assertTrue($this->sessao()->isHuman());
        Queue::assertPushed(ConfirmPoliBotHandoff::class);
    }

    public function test_piloto_em_sombra_mensagem_de_numero_fora_da_lista_tambem_vai_para_a_secretaria(): void
    {
        config(['poli.bot.mode' => BotEngine::MODE_SHADOW, 'poli.bot.test_contacts' => ['5524992689647']]);

        $this->texto('oi');

        $this->assertTrue($this->enviouPara('distribute', fn ($d) => ($d['team'] ?? null) === DefaultFlows::TEAM_SECRETARIA));
        $this->assertNull($this->sessao()->flow_slug);
    }

    public function test_time_dos_demais_numeros_e_configuravel(): void
    {
        config(['poli.bot.mode' => BotEngine::MODE_SHADOW, 'poli.bot.test_contacts' => [], 'poli.bot.test_others_team_uuid' => 'time-x']);

        $this->texto('oi');

        $this->assertTrue($this->enviouPara('distribute', fn ($d) => ($d['team'] ?? null) === 'time-x'));
    }

    /** No on não há lista: toda conversa do O Lara é conduzida pela Lara. */
    public function test_no_modo_on_nao_ha_filtro_de_numero(): void
    {
        config(['poli.bot.test_contacts' => []]);

        $this->texto('oi');

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/distribute'));
        $this->assertSame('menu', $this->sessao()->step_key);
    }

    /** Em shadow (piloto) o processo do Uber é o de hoje: aviso e close, sem O Lara. */
    public function test_aviso_do_uber_em_sombra_segue_como_hoje(): void
    {
        config(['poli.bot.mode' => BotEngine::MODE_SHADOW]);
        $this->atendimentoNaApi = ['uuid' => 'att-bot', 'status' => 'IN_PROGRESS', 'attendant' => null];

        $this->avisoDoUber();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/close'));
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/forward'));
        Http::assertSent(fn (Request $r) => str_contains((string) data_get($r->data(), 'components.body.text'), 'foi encerrado'));
        $this->assertNull(BotSession::find(self::CONTACT));
    }

    /**
     * O piloto em shadow: o número de teste toca em "Funcionalidade Teste" no
     * menu da Poli, que transfere para O Lara. Dali em diante tudo roda de
     * verdade, como no on — menu da Lara, fluxo do carro e pedido na portaria.
     */
    public function test_piloto_em_sombra_pela_funcionalidade_teste_roda_tudo_de_verdade(): void
    {
        config(['poli.bot.mode' => BotEngine::MODE_SHADOW]);

        $this->texto("Funcionalidade Teste\n<em desenvolvimento>", atendente: null);   // menu do bot da Poli
        Http::assertNothingSent();
        $antesDaTransferencia = (int) PoliMessage::max('id');   // a comparação em sombra da fase da Poli

        $this->bot()->handleRedirect($this->redirect('att-1'));

        $this->assertStringContainsString('Modo de teste', implode(' ', $this->enviados()));
        Http::assertSent(fn (Request $r) => ($r->data()['template_uuid'] ?? null) === DefaultFlows::TPL_DEPARTAMENTOS);
        $this->assertSame('menu', $this->sessao()->step_key);

        foreach (['Carro de Aplicativo', '12345', 'Gustavo', 'Ginásio', 'ABC1D23'] as $texto) {
            $this->texto($texto);
        }
        $this->imagem();

        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_ACESSO, UberAccessRequest::sole()->status);
        $this->assertFalse(PoliMessage::where('direction', 'OUT')->where('id', '>', $antesDaTransferencia)->get()->contains->shadow);
        $this->assertSame(BotSession::STATE_ENDING, $this->sessao()->state);
    }

    public function test_aviso_do_uber_com_o_bot_desligado_encerra_depois_do_aviso(): void
    {
        config(['poli.bot.mode' => BotEngine::MODE_OFF]);
        $this->atendimentoNaApi = ['uuid' => 'att-bot', 'status' => 'IN_PROGRESS', 'attendant' => null];

        $this->avisoDoUber();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/close'));
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/forward'));
        Http::assertSent(fn (Request $r) => str_contains((string) data_get($r->data(), 'components.body.text'), 'foi encerrado'));
    }

    /* ------------------------------------------------------------------ */

    private function avisoDoUber(): void
    {
        (new \App\Jobs\SendPoliTextMessage(self::PHONE, 'Seu carro chegou.', self::CONTACT, null, 1, closeAfter: true))
            ->withFakeQueueInteractions()
            ->handle(app(\App\Services\Poli\PoliMessageService::class), app(\App\Services\PoliBot\UberArrivalHandover::class));
    }

    private function irAtePlaca(): void
    {
        $this->texto('oi');
        $this->texto('Carro de Aplicativo');
        $this->texto('12345');
        $this->texto('Gustavo');
        $this->texto('Ginásio');

        $this->assertSame('placa', $this->sessao()->step_key);
    }

    /** O passo "fim" do carro de aplicativo como foi configurado em produção na Fase 3. */
    private function fimPerguntaSeTrocouOVeiculo(bool $opcional = false): void
    {
        $fluxo = BotFlow::where('slug', 'carro-de-aplicativo')->sole();
        $definicao = $fluxo->definition;
        $definicao['steps']['fim'] = array_filter([
            'say' => ['type' => 'template', 'template_uuid' => 'tpl-uber-confirmacao', 'params' => ['{nome}', '{placa}']],
            'expect' => ['type' => 'option'],
            'options' => [['label' => 'Trocar Veículo', 'next' => 'placa']],
            'optional' => $opcional,
        ]);
        $fluxo->update(['definition' => $definicao]);
    }

    private function irAteOFimDoUber(): void
    {
        $this->irAtePlaca();
        $this->texto('ABC1D23');
        $this->imagem();

        $this->assertSame(BotSession::STATE_ENDING, $this->sessao()->state);
    }

    private function redirecionado(string $atendente): array
    {
        return ['object' => 'message', 'event' => 'received', 'value' => [
            'event' => 'SYSTEM', 'type' => 'ATTENDANCE_REDIRECTED', 'direction' => 'EMPTY',
            'contact' => ['uuid' => self::CONTACT],
            'attendance' => ['uuid' => 'att-1', 'attendant' => ['uuid' => $atendente]],
        ]];
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
