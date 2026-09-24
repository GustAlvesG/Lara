<?php

namespace Tests\Feature;

use App\Exceptions\PoliListMessageNotIndexedException;
use App\Jobs\CloseUberCaptureSession;
use App\Jobs\ProcessUberAccessRequestMessage;
use App\Models\Employee;
use App\Models\UberAccessRequest;
use App\Models\UberAccessRequestMessage;
use App\Services\MultiClubes\TitleMemberLookup;
use App\Services\Poli\ParsedPoliMessage;
use App\Services\UberAccessRequestFlow;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Sem RefreshDatabase pelo mesmo motivo registrado em LaraMessageHistoryTest:
 * a cadeia completa de migrations falha hoje em `add_columns_member` x
 * `tourments` (as duas criam `members.title`). Aqui só as migrations desta
 * feature são aplicadas, no SQLite :memory: que o phpunit.xml configura, e
 * cada teste recebe um banco novo.
 */
class UberAccessRequestWebhookTest extends TestCase
{
    private const CONTACT_UUID = 'd5e8a972-6360-11f1-9d75-06799772b1cd';
    private const CONTACT_PHONE = '5524992542363';
    private const TRIGGER = 'Carro de Aplicativo';

    private int $messageCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        (require base_path('database/migrations/2026_07_20_150000_create_uber_access_requests_tables.php'))->up();
        (require base_path('database/migrations/2026_07_21_120000_add_matricula_to_uber_access_requests.php'))->up();
        (require base_path('database/migrations/2026_08_27_170000_add_member_validation_to_uber_access_requests.php'))->up();
        (require base_path('database/migrations/2026_09_10_100000_add_member_validation_type_to_uber_access_requests.php'))->up();

        // Índice dos menus enviados: sem ele nenhum gatilho é aceito, porque a
        // origem do toque deixou de ser deduzida do texto.
        (require base_path('database/migrations/2026_09_23_190000_create_poli_list_messages_table.php'))->up();

        // Sequência e marca de processamento, que ordenam o que o webhook
        // entrega fora de ordem.
        (require base_path('database/migrations/2026_09_24_120000_add_ordering_to_uber_access_request_messages.php'))->up();

        // Funcionários também podem pedir: a conferência consulta a tabela
        // employees de verdade (é banco local), só o MultiClubes é falso.
        (require base_path('database/migrations/2026_01_05_141304_banco_de_horas.php'))->up();

        // Sem SQL Server nos testes: por padrão o título não devolve ninguém.
        // Cada teste que precisa sobrescreve com fakeTitleMembers().
        $this->fakeTitleMembers([]);
    }

    /**
     * Substitui a consulta ao MultiClubes. Passar um Throwable simula o
     * SQL Server fora do ar.
     *
     * @param  string[]|\Throwable  $names
     */
    private function fakeTitleMembers(array|\Throwable $names): void
    {
        $this->app->instance(TitleMemberLookup::class, new class($names) extends TitleMemberLookup {
            public function __construct(private array|\Throwable $names) {}

            public function namesForTitle(string $matricula): array
            {
                if ($this->names instanceof \Throwable) {
                    throw $this->names;
                }

                return $this->names;
            }
        });
    }

    private function endpoint(): string
    {
        return '/api/webhooks/whatsapp';
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . config('services.api.token')];
    }

    private function payload(
        ?string $text = null,
        ?string $mediaUrl = null,
        string $direction = 'IN',
        ?string $messageId = null,
        string $contactUuid = self::CONTACT_UUID,
        ?string $contextMessageUuid = null,
        ?string $attendanceUuid = null,
        ?int $sequence = null
    ): array {
        $this->messageCounter++;

        $isImage = $mediaUrl !== null;
        $components = $isImage
            ? ['attachments' => [['type' => 'image', 'media' => ['url' => $mediaUrl], 'mime_type' => 'image/jpeg']]]
            : ['body' => ['text' => $text ?? '']];

        return [
            'object' => 'message',
            'event' => 'received',
            'value' => [
                'event' => 'MESSAGE',
                'type' => $isImage ? 'IMAGE' : 'CHAT',
                'direction' => $direction,
                'contact' => [
                    'uuid' => $contactUuid,
                    'attributes' => ['name' => 'Gustavo', 'phone' => self::CONTACT_PHONE],
                ],
                // Nulo quando o associado digita; preenchido quando ele toca
                // numa opção. É a diferença que o payload real carrega.
                'context' => $contextMessageUuid === null
                    ? null
                    : ['type' => 'message', 'message' => ['uuid' => $contextMessageUuid]],
                'components' => $components,
                'attendance' => ['uuid' => $attendanceUuid ?? $this->attendanceFor($contactUuid)],
                'metadata' => array_filter([
                    'external_message_id' => $messageId ?? 'wamid-' . $this->messageCounter,
                    // A sequência da Poli. Sem ela a mensagem não disputa
                    // ordem — é o caso dos testes que não se importam com isso.
                    'deprecated_message_id' => $sequence,
                ], fn ($v) => $v !== null),
            ],
        ];
    }

    /** Um atendimento por contato, como a Poli faz. */
    private function attendanceFor(string $contactUuid): string
    {
        return 'attendance-' . $contactUuid;
    }

    /**
     * O menu de departamentos saindo — `event: "sent"`, com as opções. É este
     * payload que o webhook indexa e que a resposta depois cita.
     */
    private function menuPayload(string $menuUuid, string $contactUuid, ?string $attendanceUuid = null): array
    {
        return [
            'object' => 'message',
            'event' => 'sent',
            'value' => [
                'uuid' => $menuUuid,
                'event' => 'MESSAGE',
                'type' => 'CHAT',
                'direction' => 'OUT',
                'contact' => ['uuid' => $contactUuid],
                'components' => [
                    'body' => ['text' => 'Selecione o Departamento que deseja conversar:'],
                    'button' => 'Ver opções',
                    'section' => [[
                        'id' => 'secao-departamentos',
                        'text' => 'Departamentos',
                        'rows' => [
                            ['id' => 'linha-financeiro', 'messageOption' => [
                                'title' => 'Financeiro',
                                'description' => 'Consulte seus débitos ou outras pendências.',
                            ]],
                            ['id' => 'linha-carro', 'messageOption' => [
                                'title' => self::TRIGGER,
                                'description' => 'Carro, moto ou táxi',
                            ]],
                        ],
                    ]],
                    'name' => 'polichat_list_message_556542',
                ],
                'attendance' => ['uuid' => $attendanceUuid ?? $this->attendanceFor($contactUuid)],
                'metadata' => ['external_message_id' => 'wamid-out-' . $menuUuid],
            ],
        ];
    }

    /**
     * A despedida do bot, que é onde `attendance.closed_reason` aparece —
     * as mensagens anteriores do mesmo atendimento trazem null.
     */
    private function closingPayload(string $contactUuid = self::CONTACT_UUID, ?string $attendanceUuid = null): array
    {
        $this->messageCounter++;

        return [
            'object' => 'message',
            'event' => 'sent',
            'value' => [
                'uuid' => 'bot-close-' . $this->messageCounter,
                'event' => 'MESSAGE',
                'type' => 'CHAT',
                'direction' => 'OUT',
                'contact' => ['uuid' => $contactUuid],
                'components' => ['body' => ['text' => 'Prezado(a), Estamos finalizando esse chat.']],
                'attendance' => [
                    'uuid' => $attendanceUuid ?? $this->attendanceFor($contactUuid),
                    'closed_reason' => 'FINISHED_BY_SYSTEM',
                ],
                'metadata' => ['external_message_id' => 'wamid-close-' . $this->messageCounter],
            ],
        ];
    }

    /** O texto que o toque numa opção produz: título + quebra + descrição. */
    private function toqueEm(string $titulo, string $descricao): string
    {
        return $titulo . "\n" . $descricao;
    }

    private function send(array $payload): void
    {
        $this->postJson($this->endpoint(), $payload, $this->authHeaders())->assertOk();
    }

    /**
     * O caminho legítimo: o menu sai, o associado toca na opção.
     *
     * Virou helper porque o gatilho deixou de ser uma mensagem só — mandar o
     * texto sem o menu por trás é, agora, exatamente o que a trava recusa.
     */
    private function sendTrigger(string $contactUuid = self::CONTACT_UUID, ?string $messageId = null): string
    {
        $menuUuid = 'menu-' . $contactUuid . '-' . (++$this->messageCounter);

        $this->send($this->menuPayload($menuUuid, $contactUuid));
        $this->send($this->payload(
            text: $this->toqueEm(self::TRIGGER, 'Carro, moto ou táxi'),
            messageId: $messageId,
            contactUuid: $contactUuid,
            contextMessageUuid: $menuUuid,
        ));

        return $menuUuid;
    }

    public function test_outbound_message_is_ignored(): void
    {
        $this->postJson($this->endpoint(), $this->payload(text: self::TRIGGER, direction: 'OUT'), $this->authHeaders())
            ->assertOk();

        $this->assertDatabaseCount('uber_access_requests', 0);
    }

    public function test_message_without_trigger_in_idle_is_ignored(): void
    {
        $this->postJson($this->endpoint(), $this->payload(text: 'Oi, tudo bem?'), $this->authHeaders())
            ->assertOk();

        $this->assertDatabaseCount('uber_access_requests', 0);
    }

    public function test_trigger_creates_session_awaiting_matricula(): void
    {
        $this->sendTrigger();

        $this->assertDatabaseHas('uber_access_requests', [
            'contact_uuid' => self::CONTACT_UUID,
            'contact_phone' => self::CONTACT_PHONE,
            'status' => UberAccessRequest::STATUS_AGUARDANDO_MATRICULA,
        ]);
    }

    /**
     * O texto sozinho não abre mais pedido — nem o exato, nem um que comece
     * com ele. Começar com o texto do gatilho é só o filtro barato que evita
     * ir ao banco a cada mensagem; quem autoriza é a procedência do toque.
     */
    public function test_texto_digitado_nao_abre_pedido(): void
    {
        foreach ([self::TRIGGER, self::TRIGGER . ' - Uber', 'carro de aplicativo'] as $texto) {
            $this->send($this->payload(text: $texto));
        }

        $this->assertDatabaseCount('uber_access_requests', 0);
    }

    /**
     * Mesmo citando o menu certo: quem digitou mandou só o título, e a
     * resposta de um toque traz título E descrição. É o que separa o toque no
     * botão de uma mensagem digitada em resposta ao menu.
     */
    public function test_texto_digitado_citando_o_menu_nao_abre_pedido(): void
    {
        $this->send($this->menuPayload('menu-citado', self::CONTACT_UUID));

        $this->send($this->payload(text: self::TRIGGER, contextMessageUuid: 'menu-citado'));

        $this->assertDatabaseCount('uber_access_requests', 0);
    }

    /** Menu de outro atendimento: é o menu de dias atrás, rolando a conversa. */
    public function test_toque_em_menu_de_outro_atendimento_nao_abre_pedido(): void
    {
        $this->send($this->menuPayload('menu-antigo', self::CONTACT_UUID, attendanceUuid: 'atendimento-de-ontem'));

        $this->send($this->payload(
            text: $this->toqueEm(self::TRIGGER, 'Carro, moto ou táxi'),
            contextMessageUuid: 'menu-antigo',
        ));

        $this->assertDatabaseCount('uber_access_requests', 0);
    }

    /** Menu desconhecido: nunca vimos sair, então não é nosso. */
    public function test_toque_em_menu_nao_indexado_nao_abre_pedido(): void
    {
        $this->send($this->payload(
            text: $this->toqueEm(self::TRIGGER, 'Carro, moto ou táxi'),
            contextMessageUuid: 'menu-que-nunca-existiu',
        ));

        $this->assertDatabaseCount('uber_access_requests', 0);
    }

    /**
     * Menu não indexado não é recusa, é indecisão: pode ser a corrida entre o
     * webhook de saída e o de entrada. O fluxo levanta a exceção e quem decide
     * entre reprocessar e desistir é o job — este teste fixa esse contrato,
     * porque na suíte a fila é `sync` e o reprocessamento não chega a rodar.
     */
    public function test_menu_nao_indexado_levanta_excecao_para_o_job_decidir(): void
    {
        $this->expectException(PoliListMessageNotIndexedException::class);

        app(UberAccessRequestFlow::class)->handle(new ParsedPoliMessage(
            messageId: 'wamid-corrida',
            contactUuid: self::CONTACT_UUID,
            contactPhone: self::CONTACT_PHONE,
            contactName: 'Gustavo',
            attendanceUuid: $this->attendanceFor(self::CONTACT_UUID),
            type: ParsedPoliMessage::TYPE_TEXT,
            text: self::TRIGGER . ' Carro, moto ou táxi',
            contextMessageUuid: 'menu-ainda-nao-chegou',
        ));
    }

    /** Outra opção do mesmo menu não abre pedido de carro. */
    public function test_toque_em_outra_opcao_do_menu_nao_abre_pedido(): void
    {
        $this->send($this->menuPayload('menu-financeiro', self::CONTACT_UUID));

        $this->send($this->payload(
            text: $this->toqueEm('Financeiro', 'Consulte seus débitos ou outras pendências.'),
            contextMessageUuid: 'menu-financeiro',
        ));

        $this->assertDatabaseCount('uber_access_requests', 0);
    }

    /**
     * O toque repetido no menu durante uma sessão aberta era o que deslocava o
     * pedido inteiro: ele entrava como matrícula, a matrícula virava nome e o
     * nome virava local. Agora é ignorado, e a resposta seguinte cai no campo
     * certo.
     */
    public function test_toque_repetido_no_gatilho_nao_e_consumido_como_resposta(): void
    {
        $this->sendTrigger();

        // O associado toca no menu de novo antes de responder.
        $this->sendTrigger();

        $this->send($this->payload(text: '987654'));
        $this->send($this->payload(text: 'Gustavo Alves'));

        $this->assertDatabaseCount('uber_access_requests', 1);

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();

        $this->assertSame('987654', $request->matricula);
        $this->assertSame('Gustavo Alves', $request->requester_name);
        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_LOCAL, $request->status);
    }

    /**
     * Resposta de menu entra pelo título. O texto que chega é
     * "Campo\nBar do Campo"; gravar isso inteiro faz o aviso de chegada dizer
     * "a caminho de Campo Bar do Campo".
     */
    public function test_resposta_de_menu_grava_so_o_titulo_da_opcao(): void
    {
        $this->sendTrigger();
        $this->send($this->payload(text: '987654'));
        $this->send($this->payload(text: 'Gustavo Alves'));

        $this->send($this->menuPayload('menu-locais', self::CONTACT_UUID));
        $this->send($this->payload(
            text: $this->toqueEm('Financeiro', 'Consulte seus débitos ou outras pendências.'),
            contextMessageUuid: 'menu-locais',
        ));

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();

        $this->assertSame('Financeiro', $request->club_location);
    }

    /**
     * O atendimento fechou no meio da coleta: o associado desistiu, ou o bot
     * encerrou. O que ficou pela metade não recebe mais resposta, então não
     * tem por que continuar aberto.
     */
    public function test_fecho_do_atendimento_encerra_a_coleta_incompleta(): void
    {
        $this->sendTrigger();
        $this->send($this->payload(text: '987654'));

        $this->send($this->closingPayload());

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();

        $this->assertSame(UberAccessRequest::STATUS_EXPIRADO, $request->status);
    }

    /**
     * O caso que não pode dar errado: no fluxo normal o atendimento fecha
     * LOGO DEPOIS do print, com o pedido já pronto. Encerrar aqui mataria todo
     * pedido legítimo no instante em que ele ficou utilizável na portaria.
     */
    public function test_fecho_do_atendimento_nao_toca_em_pedido_ja_completo(): void
    {
        $request = $this->completeFlow();

        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_ACESSO, $request->status);

        $this->send($this->closingPayload());

        $request->refresh();

        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_ACESSO, $request->status);
        $this->assertNotNull($request->expires_at);
    }

    /**
     * Encerrada a coleta, a mensagem seguinte não é continuação: não acha
     * pedido aberto, e volta a passar pela validação do gatilho. Só um toque
     * válido no menu recomeça.
     */
    public function test_mensagem_depois_do_fecho_e_uma_validacao_nova(): void
    {
        $this->sendTrigger();
        $this->send($this->payload(text: '987654'));
        $this->send($this->closingPayload());

        // Texto solto não continua o pedido encerrado nem abre outro.
        $this->send($this->payload(text: 'Gustavo Alves'));

        $this->assertDatabaseCount('uber_access_requests', 1);
        $this->assertNull(
            UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail()->requester_name
        );

        $this->sendTrigger();

        $this->assertDatabaseCount('uber_access_requests', 2);
        $this->assertSame(
            UberAccessRequest::STATUS_AGUARDANDO_MATRICULA,
            UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->latest('id')->firstOrFail()->status
        );
    }

    /** O fecho é de um atendimento, não de todo mundo que está pedindo. */
    public function test_fecho_de_um_atendimento_nao_encerra_o_de_outro_contato(): void
    {
        $this->sendTrigger('contato-a');
        $this->sendTrigger('contato-b');

        $this->send($this->closingPayload('contato-a'));

        $this->assertSame(
            UberAccessRequest::STATUS_EXPIRADO,
            UberAccessRequest::where('contact_uuid', 'contato-a')->firstOrFail()->status
        );
        $this->assertSame(
            UberAccessRequest::STATUS_AGUARDANDO_MATRICULA,
            UberAccessRequest::where('contact_uuid', 'contato-b')->firstOrFail()->status
        );
    }

    /**
     * O fecho vai para a fila COM ATRASO. A despedida do bot sai no mesmo
     * segundo em que o print chega, e sem a carência um segundo worker poderia
     * encerrar a coleta antes de o print ser processado.
     */
    public function test_fecho_do_atendimento_vai_adiado_para_a_fila(): void
    {
        Queue::fake([CloseUberCaptureSession::class]);

        $this->send($this->closingPayload());

        Queue::assertPushed(
            CloseUberCaptureSession::class,
            fn (CloseUberCaptureSession $job) => $job->attendanceUuid === $this->attendanceFor(self::CONTACT_UUID)
                && $job->delay !== null
        );
    }

    /**
     * Emula o worker: percorre as pendentes na ordem de CHEGADA e, quando uma
     * tem irmã mais antiga pendente, adia — que é o que o `release()` faz na
     * fila de verdade. Precisa ser simulado porque a suíte roda com `sync`,
     * onde adiar é um no-op.
     */
    private function drenarFila(): void
    {
        for ($volta = 0; $volta < 20; $volta++) {
            $pendentes = UberAccessRequestMessage::whereNull('processed_at')->orderBy('id')->get();

            if ($pendentes->isEmpty()) {
                return;
            }

            $andou = false;

            foreach ($pendentes as $row) {
                if ($row->hasPendingPredecessor(120)) {
                    continue;
                }

                app()->call([new ProcessUberAccessRequestMessage($row->id), 'handle']);
                $andou = true;
            }

            if (!$andou) {
                return;
            }
        }
    }

    /**
     * O caso que aconteceu em produção: a Poli entregou o toque no menu DEPOIS
     * da matrícula, embora o tenha enviado 12 segundos antes. Na ordem de
     * chegada o pedido sai deslocado; na ordem da sequência, sai certo.
     */
    public function test_mensagens_fora_de_ordem_sao_processadas_pela_sequencia(): void
    {
        Queue::fake([ProcessUberAccessRequestMessage::class]);

        $this->send($this->menuPayload('menu-ordem', self::CONTACT_UUID));

        // Chegada: matrícula primeiro. Envio: o toque veio antes dela.
        $this->send($this->payload(text: '99988', sequence: 200));
        $this->send($this->payload(
            text: $this->toqueEm(self::TRIGGER, 'Carro, moto ou táxi'),
            contextMessageUuid: 'menu-ordem',
            sequence: 100,
        ));
        $this->send($this->payload(text: 'Gustavo Alves', sequence: 300));

        $this->drenarFila();

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();

        $this->assertSame('99988', $request->matricula);
        $this->assertSame('Gustavo Alves', $request->requester_name);
        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_LOCAL, $request->status);
    }

    /** A mesma sequência em ordem continua funcionando, sem nada a ceder. */
    public function test_mensagens_em_ordem_seguem_direto(): void
    {
        Queue::fake([ProcessUberAccessRequestMessage::class]);

        $this->send($this->menuPayload('menu-direto', self::CONTACT_UUID));
        $this->send($this->payload(
            text: $this->toqueEm(self::TRIGGER, 'Carro, moto ou táxi'),
            contextMessageUuid: 'menu-direto',
            sequence: 100,
        ));
        $this->send($this->payload(text: '99988', sequence: 200));
        $this->send($this->payload(text: 'Gustavo Alves', sequence: 300));

        $this->drenarFila();

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();

        $this->assertSame('99988', $request->matricula);
        $this->assertSame('Gustavo Alves', $request->requester_name);
    }

    public function test_mensagem_cede_a_vez_apenas_para_irma_mais_antiga_do_mesmo_contato(): void
    {
        $anterior = UberAccessRequestMessage::create([
            'poli_message_id' => 'm-100', 'poli_sequence' => 100,
            'contact_uuid' => 'contato-x', 'raw_payload' => [],
        ]);
        $posterior = UberAccessRequestMessage::create([
            'poli_message_id' => 'm-200', 'poli_sequence' => 200,
            'contact_uuid' => 'contato-x', 'raw_payload' => [],
        ]);
        $outroContato = UberAccessRequestMessage::create([
            'poli_message_id' => 'm-050', 'poli_sequence' => 50,
            'contact_uuid' => 'contato-y', 'raw_payload' => [],
        ]);

        $this->assertTrue($posterior->hasPendingPredecessor(120));
        $this->assertFalse($anterior->hasPendingPredecessor(120));
        // Contato diferente não disputa: o estado do fluxo é por contato.
        $this->assertFalse($outroContato->hasPendingPredecessor(120));

        $anterior->markProcessed();

        $this->assertFalse($posterior->fresh()->hasPendingPredecessor(120));
    }

    /**
     * O freio de mão: uma mensagem que nunca conclui não pode segurar a
     * conversa daquele contato para sempre.
     */
    public function test_irma_antiga_fora_da_janela_deixa_de_segurar(): void
    {
        $presa = UberAccessRequestMessage::create([
            'poli_message_id' => 'm-presa', 'poli_sequence' => 100,
            'contact_uuid' => 'contato-z', 'raw_payload' => [],
        ]);
        $presa->forceFill(['created_at' => now()->subMinutes(10)])->save();

        $seguinte = UberAccessRequestMessage::create([
            'poli_message_id' => 'm-seguinte', 'poli_sequence' => 200,
            'contact_uuid' => 'contato-z', 'raw_payload' => [],
        ]);

        $this->assertTrue($seguinte->hasPendingPredecessor(3600));
        $this->assertFalse($seguinte->hasPendingPredecessor(120));
    }

    /** Mensagem sem sequência (anterior à migration) não segura ninguém. */
    public function test_mensagem_sem_sequencia_nao_segura_a_vez(): void
    {
        UberAccessRequestMessage::create([
            'poli_message_id' => 'm-velha', 'poli_sequence' => null,
            'contact_uuid' => 'contato-w', 'raw_payload' => [],
        ]);
        $nova = UberAccessRequestMessage::create([
            'poli_message_id' => 'm-nova', 'poli_sequence' => 500,
            'contact_uuid' => 'contato-w', 'raw_payload' => [],
        ]);

        $this->assertFalse($nova->hasPendingPredecessor(120));
    }

    /** As de saída nascem resolvidas: nunca disputam vez com uma resposta. */
    public function test_mensagem_de_saida_nasce_resolvida(): void
    {
        $this->send($this->menuPayload('menu-saida', self::CONTACT_UUID));

        $menu = UberAccessRequestMessage::where('poli_message_id', 'wamid-out-menu-saida')->firstOrFail();

        $this->assertNotNull($menu->processed_at);
        $this->assertSame(self::CONTACT_UUID, $menu->contact_uuid);
    }

    /** Resposta digitada continua entrando como veio. */
    public function test_resposta_digitada_continua_sendo_gravada_literalmente(): void
    {
        $this->sendTrigger();
        $this->send($this->payload(text: '987654'));
        $this->send($this->payload(text: 'Gustavo Alves'));
        $this->send($this->payload(text: 'Portaria 2'));

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();

        $this->assertSame('Portaria 2', $request->club_location);
    }

    public function test_plate_capture_strips_special_characters(): void
    {
        $this->sendTrigger();
        $this->postJson($this->endpoint(), $this->payload(text: '12345'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'Gustavo Alves'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'Portaria 2'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'abc-1d23.'), $this->authHeaders())->assertOk();

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();

        $this->assertSame('ABC1D23', $request->vehicle_plate);
        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_PRINT, $request->status);
    }

    public function test_matricula_capture_advances_to_awaiting_name(): void
    {
        $this->sendTrigger();
        $this->postJson($this->endpoint(), $this->payload(text: '987654'), $this->authHeaders())->assertOk();

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();

        $this->assertSame('987654', $request->matricula);
        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_NOME, $request->status);
    }

    public function test_full_sequence_completes_and_sets_expiration(): void
    {
        $this->sendTrigger();
        $this->postJson($this->endpoint(), $this->payload(text: '987654'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'Gustavo Alves'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'Portaria 2'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'ABC1D23'), $this->authHeaders())->assertOk();
        $this->postJson(
            $this->endpoint(),
            $this->payload(mediaUrl: 'https://poli.example/media/print.jpg'),
            $this->authHeaders()
        )->assertOk();

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();

        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_ACESSO, $request->status);
        $this->assertSame('Gustavo Alves', $request->requester_name);
        $this->assertSame('987654', $request->matricula);
        $this->assertSame('Portaria 2', $request->club_location);
        $this->assertSame('ABC1D23', $request->vehicle_plate);
        $this->assertSame('https://poli.example/media/print.jpg', $request->screenshot_url);
        $this->assertNotNull($request->completed_at);
        $this->assertNotNull($request->expires_at);
        $this->assertEqualsWithDelta(
            $request->completed_at->addMinutes(30)->timestamp,
            $request->expires_at->timestamp,
            1
        );
    }

    /** Roda o fluxo inteiro até a imagem, deixando o pedido completo. */
    private function completeFlow(string $name = 'Gustavo Alves', string $matricula = '987654'): UberAccessRequest
    {
        $this->sendTrigger();
        $this->postJson($this->endpoint(), $this->payload(text: $matricula), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: $name), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'Portaria 2'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'ABC1D23'), $this->authHeaders())->assertOk();
        $this->postJson(
            $this->endpoint(),
            $this->payload(mediaUrl: 'https://poli.example/media/print.jpg'),
            $this->authHeaders()
        )->assertOk();

        return UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();
    }

    public function test_member_is_validated_when_name_belongs_to_the_title(): void
    {
        $this->fakeTitleMembers(['Maria Souza', 'Gustavo Alves']);

        $request = $this->completeFlow(name: 'Gustavo Alves');

        $this->assertSame(UberAccessRequest::MEMBER_VALIDATION_VALIDADO, $request->member_validation);
        $this->assertSame('Gustavo Alves', $request->member_validation_name);
        $this->assertSame(UberAccessRequest::MEMBER_TYPE_SOCIO, $request->member_validation_type);
        $this->assertNotNull($request->member_validated_at);
    }

    public function test_member_validation_fails_when_name_is_not_on_the_title(): void
    {
        $this->fakeTitleMembers(['Maria Souza', 'Joana Lima']);

        $request = $this->completeFlow(name: 'Gustavo Alves');

        $this->assertSame(UberAccessRequest::MEMBER_VALIDATION_NAO_ENCONTRADO, $request->member_validation);
        $this->assertNull($request->member_validation_name);
    }

    public function test_member_validation_fails_when_title_has_nobody(): void
    {
        $this->fakeTitleMembers([]);

        $request = $this->completeFlow();

        $this->assertSame(UberAccessRequest::MEMBER_VALIDATION_NAO_ENCONTRADO, $request->member_validation);
    }

    public function test_multiclubes_outage_marks_validation_unavailable_without_blocking(): void
    {
        $this->fakeTitleMembers(new \RuntimeException('SQLSTATE[08001] sql server unreachable'));

        $request = $this->completeFlow();

        // O pedido tem de continuar utilizável na portaria mesmo sem conferência.
        $this->assertSame(UberAccessRequest::MEMBER_VALIDATION_INDISPONIVEL, $request->member_validation);
        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_ACESSO, $request->status);
        $this->assertNotNull($request->expires_at);
    }

    private function makeEmployee(string $code, string $cpf, string $name): Employee
    {
        return Employee::create([
            'employee_code'  => $code,
            'name'           => $name,
            'cpf'            => $cpf,
            'admission_date' => '2024-01-01',
            'position'       => 'Recepcionista',
            'department'     => 'Portaria',
        ]);
    }

    public function test_employee_is_validated_by_code(): void
    {
        $this->makeEmployee('12152', '121.983.027-56', 'DANIELLE TRINDADE BERION');

        $request = $this->completeFlow(name: 'Danielle', matricula: '12152');

        $this->assertSame(UberAccessRequest::MEMBER_VALIDATION_VALIDADO, $request->member_validation);
        $this->assertSame(UberAccessRequest::MEMBER_TYPE_FUNCIONARIO, $request->member_validation_type);
        $this->assertSame('DANIELLE TRINDADE BERION', $request->member_validation_name);
        $this->assertSame('Funcionário confere', $request->memberValidationLabel());
    }

    public function test_employee_is_validated_by_cpf_regardless_of_how_either_side_is_formatted(): void
    {
        // O banco tem CPF gravado com e sem máscara; o funcionário digita dos
        // dois jeitos também.
        $this->makeEmployee('12152', '121.983.027-56', 'DANIELLE TRINDADE BERION');
        $this->makeEmployee('12206', '20613169727', 'NATALIA MARLENE IVA RODRIGUES DA SILVA');

        $cases = [
            ['12198302756', 'Danielle', 'DANIELLE TRINDADE BERION'],
            ['206.131.697-27', 'Natália', 'NATALIA MARLENE IVA RODRIGUES DA SILVA'],
        ];

        foreach ($cases as $i => [$cpf, $name, $official]) {
            $contact = 'employee-cpf-' . $i;

            $this->sendTrigger($contact);

            foreach ([$cpf, $name, 'Portaria 2', 'ABC1D23'] as $text) {
                $this->send($this->payload(text: $text, contactUuid: $contact));
            }
            $this->send($this->payload(mediaUrl: 'https://poli.example/media/print.jpg', contactUuid: $contact));

            $request = UberAccessRequest::where('contact_uuid', $contact)->firstOrFail();

            $this->assertSame(UberAccessRequest::MEMBER_TYPE_FUNCIONARIO, $request->member_validation_type, $cpf);
            $this->assertSame($official, $request->member_validation_name, $cpf);
        }
    }

    public function test_dismissed_employee_is_not_validated(): void
    {
        $this->makeEmployee('12152', '121.983.027-56', 'DANIELLE TRINDADE BERION')->delete();

        $request = $this->completeFlow(name: 'Danielle', matricula: '12152');

        $this->assertSame(UberAccessRequest::MEMBER_VALIDATION_NAO_ENCONTRADO, $request->member_validation);
        $this->assertNull($request->member_validation_type);
    }

    public function test_media_out_of_order_does_not_advance_state(): void
    {
        $this->sendTrigger();
        $this->postJson(
            $this->endpoint(),
            $this->payload(mediaUrl: 'https://poli.example/media/print.jpg'),
            $this->authHeaders()
        )->assertOk();

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();

        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_MATRICULA, $request->status);
        $this->assertNull($request->screenshot_url);
    }

    public function test_duplicate_message_id_is_not_reprocessed(): void
    {
        $this->send($this->menuPayload('menu-dup', self::CONTACT_UUID));

        $payload = $this->payload(
            text: $this->toqueEm(self::TRIGGER, 'Carro, moto ou táxi'),
            messageId: 'dup-1',
            contextMessageUuid: 'menu-dup',
        );

        $this->postJson($this->endpoint(), $payload, $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $payload, $this->authHeaders())->assertOk();

        $this->assertSame(1, UberAccessRequestMessage::where('poli_message_id', 'dup-1')->count());
        $this->assertDatabaseCount('uber_access_requests', 1);
    }

    public function test_expired_session_does_not_accept_stale_answers_but_new_trigger_opens_fresh_session(): void
    {
        $this->sendTrigger();

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();
        $request->update([
            'last_message_at' => now()->subSeconds(UberAccessRequestFlow::SESSION_TIMEOUT_SECONDS + 1),
        ]);

        $this->postJson($this->endpoint(), $this->payload(text: 'Gustavo Alves'), $this->authHeaders())->assertOk();

        $request->refresh();
        $this->assertSame(UberAccessRequest::STATUS_EXPIRADO, $request->status);
        $this->assertNull($request->requester_name);
        $this->assertDatabaseCount('uber_access_requests', 1);

        $this->sendTrigger();

        $this->assertDatabaseCount('uber_access_requests', 2);
        $this->assertDatabaseHas('uber_access_requests', [
            'contact_uuid' => self::CONTACT_UUID,
            'status' => UberAccessRequest::STATUS_AGUARDANDO_MATRICULA,
        ]);
    }

    public function test_answer_just_within_the_timeout_is_still_accepted(): void
    {
        $this->sendTrigger();

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();
        $request->update([
            'last_message_at' => now()->subSeconds(UberAccessRequestFlow::SESSION_TIMEOUT_SECONDS - 20),
        ]);

        $this->postJson($this->endpoint(), $this->payload(text: '987654'), $this->authHeaders())->assertOk();

        $request->refresh();
        $this->assertSame('987654', $request->matricula);
        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_NOME, $request->status);
    }

    public function test_completed_request_is_not_killed_by_the_response_timeout(): void
    {
        $this->sendTrigger();
        $this->postJson($this->endpoint(), $this->payload(text: '987654'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'Gustavo Alves'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'Portaria 2'), $this->authHeaders())->assertOk();
        $this->postJson($this->endpoint(), $this->payload(text: 'ABC1D23'), $this->authHeaders())->assertOk();
        $this->postJson(
            $this->endpoint(),
            $this->payload(mediaUrl: 'https://poli.example/media/print.jpg'),
            $this->authHeaders()
        )->assertOk();

        // O motorista tem até `expires_at` (30 min) para chegar: ficar mais de
        // 200s sem mensagem nova não pode cancelar um pedido já completo.
        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();
        $request->update([
            'last_message_at' => now()->subSeconds(UberAccessRequestFlow::SESSION_TIMEOUT_SECONDS + 60),
        ]);

        $this->artisan('app:expire-uber-access-requests')->assertSuccessful();

        $request->refresh();
        $this->assertSame(UberAccessRequest::STATUS_AGUARDANDO_ACESSO, $request->status);
    }

    public function test_scheduled_command_cancels_abandoned_capture(): void
    {
        $this->sendTrigger();
        $this->postJson($this->endpoint(), $this->payload(text: '987654'), $this->authHeaders())->assertOk();

        $request = UberAccessRequest::where('contact_uuid', self::CONTACT_UUID)->firstOrFail();
        $request->update([
            'last_message_at' => now()->subSeconds(UberAccessRequestFlow::SESSION_TIMEOUT_SECONDS + 1),
        ]);

        $this->artisan('app:expire-uber-access-requests')->assertSuccessful();

        $request->refresh();
        $this->assertSame(UberAccessRequest::STATUS_EXPIRADO, $request->status);

        // Cancelado de forma proativa, o gatilho seguinte abre um pedido novo.
        $this->sendTrigger();

        $this->assertDatabaseCount('uber_access_requests', 2);
        $this->assertDatabaseHas('uber_access_requests', [
            'contact_uuid' => self::CONTACT_UUID,
            'status' => UberAccessRequest::STATUS_AGUARDANDO_MATRICULA,
        ]);
    }

    public function test_malformed_payload_returns_422(): void
    {
        $this->postJson($this->endpoint(), ['foo' => 'bar'], $this->authHeaders())
            ->assertStatus(422);
    }

    public function test_valid_payload_outside_flow_returns_200_without_side_effects(): void
    {
        $payload = $this->payload(text: self::TRIGGER);
        $payload['value']['event'] = 'STATUS';

        $this->postJson($this->endpoint(), $payload, $this->authHeaders())->assertOk();

        $this->assertDatabaseCount('uber_access_requests', 0);
        $this->assertDatabaseCount('uber_access_request_messages', 1);
    }

    public function test_request_without_valid_bearer_token_is_rejected(): void
    {
        $this->postJson($this->endpoint(), $this->payload(text: self::TRIGGER), ['Authorization' => 'Bearer invalid'])
            ->assertStatus(401);
    }
}
