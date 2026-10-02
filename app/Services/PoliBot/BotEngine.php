<?php

namespace App\Services\PoliBot;

use App\Jobs\ConfirmPoliBotHandoff;
use App\Models\BotFlow;
use App\Models\BotSession;
use App\Models\PoliMessage;
use App\Services\Poli\ParsedPoliMessage;
use App\Services\Poli\PoliClient;
use App\Services\UberAccessRequestFlow;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Conduz a conversa do WhatsApp nas conversas atribuídas ao usuário O Lara.
 *
 * A regra de dono é explícita (Implementação 2, medida em 29/09/2026):
 *
 *   a conversa é da Lara  ⇔  attendance.attendant.uuid == poli.bot.user_uuid
 *                            e attendance.status != CLOSED
 *
 * O bot da Poli continua sendo a porta de entrada e transfere para O Lara;
 * a Lara conduz o fluxo e, no fim, distribui para um time ou encerra. As
 * conversas que continuam no bot da Poli (atendimento sem atendente) passam
 * por aqui só em sombra, para comparação.
 *
 * Portas de entrada:
 *
 *   - handleInbound(): uma mensagem do contato, já na ordem certa.
 *   - handleRedirect(): o atendimento foi transferido para O Lara — a Lara
 *     abre o fluxo sem esperar a próxima mensagem do contato.
 *   - observe(): qualquer evento do webhook. Só mantém o estado: humano
 *     assumiu, atendimento encerrado, ACK das mensagens do bot.
 *   - expireSessions() / confirmHandoff() / reconcile(): os agendamentos.
 *
 * Nenhuma lança: o webhook e a escuta do Uber não podem cair por causa do
 * bot. Erro vira log. A trava por contato (exclusive()) é de quem chama —
 * a do Laravel não é reentrante.
 */
class BotEngine
{
    public const MODE_OFF = 'off';
    public const MODE_SHADOW = 'shadow';
    public const MODE_ON = 'on';

    /** Passos sem pergunta encadeados de uma vez — trava contra laço na definição. */
    private const MAX_CHAIN = 20;

    /** Até quanto tempo antes da transferência a mensagem do contato vale como gatilho. */
    private const REDIRECT_TRIGGER_MINUTES = 30;

    /** Conversa sem sessão ativa, parada há menos que isto, não é reconciliada. */
    private const RECONCILE_GRACE_MINUTES = 10;

    private const DONO_LARA = 'lara';
    private const DONO_RESGATE = 'resgate';
    private const DONO_BOT_DA_POLI = 'bot_da_poli';
    private const DONO_HUMANO = 'outro_atendente';
    private const DONO_ENCERRADO = 'atendimento_encerrado';
    private const DONO_EMPRESA = 'iniciado_pela_empresa';

    private BotOutbox $out;
    private bool $incluiInativos = false;
    private bool $simulacao = false;
    private bool $desviouPorHorario = false;
    private ParsedPoliMessage $message;

    public function __construct(
        private readonly AnswerValidator $validator,
        private readonly BotOutbox $outbox,
        private readonly UberAccessRequestFlow $uber,
        private readonly PoliClient $poli,
    ) {}

    public function mode(): string
    {
        $mode = config('poli.bot.mode');

        return in_array($mode, [self::MODE_SHADOW, self::MODE_ON], true) ? $mode : self::MODE_OFF;
    }

    public function botUserUuid(): ?string
    {
        $uuid = config('poli.bot.user_uuid');

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }

    public function isLaraAttendant(?string $attendantUuid): bool
    {
        return $attendantUuid !== null && $attendantUuid === $this->botUserUuid();
    }

    /** A regra de dono. */
    public function ownsConversation(ParsedPoliMessage $message): bool
    {
        return $this->isLaraAttendant($message->attendanceAttendantUuid) && !$message->isClosed();
    }

    /**
     * O Lara é a própria Lara: conversa dele é sempre respondida de verdade,
     * em qualquer modo ligado — não há outro atendente para responder. A
     * sombra fica para a comparação das conversas do bot da Poli, e o
     * simulador nunca envia.
     */
    private function liveFor(bool $daLara): bool
    {
        return $daLara && !$this->simulacao && $this->mode() !== self::MODE_OFF;
    }

    /**
     * A escuta do fluxo do Uber acompanha as perguntas do bot da POLI. Nas
     * conversas do O Lara as mensagens da Lara têm a mesma assinatura das do
     * bot da Poli e a escuta duplicaria o pedido que o próprio fluxo da Lara
     * cria — ali ela fica de fora. No resto, segue como sempre foi.
     */
    public function listensToPoliUberFlow(ParsedPoliMessage $message): bool
    {
        if ($this->isLaraAttendant($message->attendanceAttendantUuid)) {
            return false;
        }

        if ($this->mode() === self::MODE_OFF || $message->contactUuid === null) {
            return true;
        }

        // O toque que motivou a transferência pode ser processado depois
        // dela (ProcessPoliBotRedirect não o espera): a conversa já é do O
        // Lara, e é o fluxo da Lara que cuida do pedido. A escuta do Uber não
        // pode cair por causa do bot: sem conseguir ler a sessão, ela segue.
        try {
            $session = BotSession::find($message->contactUuid);
        } catch (Throwable $e) {
            Log::warning('PoliBot: sessão ilegível, a escuta do Uber segue', ['erro' => $e->getMessage()]);

            return true;
        }

        return !($session !== null && $this->takenByLara($session, $message));
    }

    /**
     * Mensagem da fase do bot da Poli (sem atendente) que chega depois de a
     * Lara já ter assumido este atendimento: é anterior à transferência.
     */
    private function takenByLara(BotSession $session, ParsedPoliMessage $message): bool
    {
        return $message->attendanceAttendantUuid === null
            && $session->lara_owned
            && ($session->inFlow() || $session->isEnding() || $session->isHuman())
            && (blank($session->attendance_uuid) || $session->attendance_uuid === $message->attendanceUuid);
    }

    /**
     * Uma conversa por vez por contato: a transferência e a primeira mensagem
     * do contato chegam quase juntas, e cada uma lê e grava a mesma sessão.
     *
     * @throws \Illuminate\Contracts\Cache\LockTimeoutException
     */
    public function exclusive(string $contactUuid, callable $callback, int $waitSeconds = 15): mixed
    {
        return Cache::lock('poli-bot:contato:' . $contactUuid, 60)->block($waitSeconds, $callback);
    }

    /* ---------------------------------------------------------------------
     | Mensagem do contato
     |---------------------------------------------------------------------*/

    public function handleInbound(ParsedPoliMessage $message): void
    {
        if ($this->mode() === self::MODE_OFF || $message->contactUuid === null) {
            return;
        }

        try {
            $this->desviouPorHorario = false;
            $this->process($message);
        } catch (Throwable $e) {
            Log::error('PoliBot: falha ao processar mensagem', [
                'contact_uuid' => $message->contactUuid,
                'message_id' => $message->messageId,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    private function process(ParsedPoliMessage $message): void
    {
        // Idempotência: o job pode rodar de novo (retentativa, reenvio da
        // Poli). A mensagem de entrada só é tratada uma vez.
        if (PoliMessage::where('uuid', $message->messageId)->exists()) {
            return;
        }

        $message = $this->confirmAttendance($message);

        $session = BotSession::find($message->contactUuid)
            ?? new BotSession(['contact_uuid' => $message->contactUuid]);

        $session->fill(array_filter([
            'contact_phone' => $message->contactPhone,
            'contact_name' => $message->contactName,
        ], 'filled'));

        $dono = $this->ownerOf($message, $session);
        $daLara = in_array($dono, [self::DONO_LARA, self::DONO_RESGATE], true);

        $this->message = $message;
        $this->out = $this->outbox->live($this->liveFor($daLara));

        $this->recordInbound($session, $message);

        if (!$daLara && $dono !== self::DONO_BOT_DA_POLI) {
            $this->silence($session, $message, $dono);

            return;
        }

        if ($dono === self::DONO_RESGATE) {
            $this->rescue($session, $message);
            $this->save($session);

            return;
        }

        // O toque no menu da Poli processado depois da transferência: a
        // conversa já é da Lara. Fica no histórico e não mexe em nada.
        if ($dono === self::DONO_BOT_DA_POLI && $this->takenByLara($session, $message)) {
            return;
        }

        // Atendimento novo: o anterior acabou, mesmo que o fim não tenha
        // chegado pelo webhook.
        if (filled($session->attendance_uuid) && filled($message->attendanceUuid)
            && $session->attendance_uuid !== $message->attendanceUuid) {
            $session->endConversation();
        }
        $session->attendance_uuid = $message->attendanceUuid ?? $session->attendance_uuid;

        if ($daLara) {
            $this->continueLaraConversation($session, $message);

            return;
        }

        // Comparação em sombra de uma conversa do bot da Poli.
        if ($session->lara_owned) {
            $session->endConversation();
        }

        if ($session->isHuman()) {
            if (!$this->humanExpired($session)) {
                $this->save($session);

                return;
            }

            $session->reset();
        }

        $this->converse($session, $message);
        $this->save($session);
    }

    private function continueLaraConversation(BotSession $session, ParsedPoliMessage $message): void
    {
        // A sessão diz humano, o evento diz O Lara: ou o transbordo não pegou,
        // ou o evento chegou com o atendimento de antes da troca. Quem decide
        // é a API. Continuando com O Lara, a Lara responde — conversa do O
        // Lara nunca fica sem resposta.
        if ($session->isHuman()) {
            if (!$this->stillWithLara($session->contact_uuid)) {
                $this->save($session);

                return;
            }

            $session->reset();
        }

        $session->lara_owned = true;

        if ($this->sentOutOfPilot($session)) {
            $this->save($session);

            return;
        }

        // Fluxo concluído e o contato escreveu antes do encerramento: a
        // conversa ainda é da Lara, que recomeça pelo menu.
        if ($session->isEnding()) {
            $session->reset();
            $session->lara_owned = true;
            $this->startTriggered($session, $message);
            $this->save($session);

            return;
        }

        $this->converse($session, $message);
        $this->save($session);
    }

    /**
     * Piloto (modo shadow): a opção "Funcionalidade Teste" aparece para todo
     * mundo no menu da Poli, mas só os números de teste têm a conversa
     * conduzida pela Lara. Os demais vão direto para a Secretaria — sem
     * fluxo e sem desvio de horário, que os prenderia no bot novo.
     *
     * @return bool true se a conversa foi encaminhada (e não deve seguir)
     */
    private function sentOutOfPilot(BotSession $session): bool
    {
        if ($this->mode() !== self::MODE_SHADOW || $this->simulacao || $this->isTestContact($session)) {
            return false;
        }

        Log::info('PoliBot: piloto — número fora da lista de teste encaminhado', ['contact_uuid' => $session->contact_uuid]);

        $this->say($session, (string) config('poli.bot.messages.test_others'));
        $feito = $this->out->handoff($session, config('poli.bot.test_others_team_uuid') ?: DefaultFlows::TEAM_SECRETARIA);
        $session->toHuman();

        if ($feito) {
            ConfirmPoliBotHandoff::dispatch($session->contact_uuid)
                ->delay(now()->addSeconds((int) config('poli.bot.handoff_confirm_seconds', 10)));
        }

        return true;
    }

    private function isTestContact(BotSession $session): bool
    {
        $telefone = preg_replace('/\D/', '', (string) $session->contact_phone);

        return $telefone !== '' && in_array($telefone, (array) config('poli.bot.test_contacts', []), true);
    }

    /** O atendimento em curso do contato, pela API, está com O Lara? Sem resposta dela: não. */
    private function stillWithLara(string $contactUuid): bool
    {
        try {
            $atual = $this->poli->atendimentoAtual($contactUuid);
        } catch (Throwable $e) {
            Log::warning('PoliBot: não deu para conferir o atendente na API', ['contact_uuid' => $contactUuid, 'erro' => $e->getMessage()]);

            return false;
        }

        return $atual !== null
            && ($atual['status'] ?? null) !== 'CLOSED'
            && $this->isLaraAttendant(is_string($atual['attendant']['uuid'] ?? null) ? $atual['attendant']['uuid'] : null);
    }

    /**
     * Escape, resposta à pergunta em aberto (com o prazo do fluxo) ou início.
     */
    private function converse(BotSession $session, ParsedPoliMessage $message): void
    {
        if ($this->escape($session, $message)) {
            return;
        }

        if ($session->inFlow()) {
            $flow = $this->findFlow($session->flow_slug);

            if ($flow === null) {
                $session->reset();
            } elseif ($this->timedOut($session, $flow)) {
                // O aviso só faz sentido para quem estava no meio de algo.
                // Quem parou no menu inicial recebe o menu, sem sermão.
                // Parado numa pergunta opcional, o fluxo já tinha terminado.
                $estavaNoMeio = ($session->step_key !== $flow->start() || !empty($session->data))
                    && empty($flow->step($session->step_key)['optional']);

                $session->reset();

                if ($estavaNoMeio) {
                    $this->say($session, (string) config('poli.bot.messages.expired'));
                }
                $this->startTriggered($session, $message, onlyAnyMessage: true);

                return;
            } else {
                $this->answer($session, $flow, $message);

                return;
            }
        }

        $this->startTriggered($session, $message);
    }

    /**
     * De quem é a conversa desta mensagem.
     */
    private function ownerOf(ParsedPoliMessage $message, BotSession $session): string
    {
        if ($this->ownsConversation($message)) {
            return self::DONO_LARA;
        }

        if ($message->isClosed()) {
            return $this->isSwallowedAfterLaraClose($message, $session) ? self::DONO_RESGATE : self::DONO_ENCERRADO;
        }

        if ($message->attendanceAttendantUuid !== null) {
            return self::DONO_HUMANO;
        }

        // Regra do bot da Poli (medida em 25/09/2026): ele não entra em
        // atendimento aberto pela empresa — a resposta a uma cobrança é para
        // quem escreveu, não para um menu.
        if ($message->attendanceType === 'INITIATED_BY_BUSINESS') {
            return self::DONO_EMPRESA;
        }

        return self::DONO_BOT_DA_POLI;
    }

    /**
     * A primeira mensagem do contato até ~1 minuto depois de um close fica
     * presa no atendimento fechado, sem menu (medido em 29/09/2026). Se foi
     * a Lara quem fechou, ela resgata.
     */
    private function isSwallowedAfterLaraClose(ParsedPoliMessage $message, BotSession $session): bool
    {
        return $this->botUserUuid() !== null
            && filled($message->attendanceUuid)
            && $message->attendanceUuid === $session->closed_attendance_uuid
            && $session->closed_at !== null
            && $session->closed_at->copy()->addMinutes((int) config('poli.bot.rescue_minutes', 30))->isFuture();
    }

    private function silence(BotSession $session, ParsedPoliMessage $message, string $motivo): void
    {
        if ($motivo === self::DONO_HUMANO && !$session->isHuman()) {
            $session->toHuman();
            $session->attendance_uuid = $message->attendanceUuid ?? $session->attendance_uuid;
        }

        // O atendente vai no log: é o que se compara com poli.bot.user_uuid
        // quando uma conversa que devia ser do O Lara fica em silêncio.
        Log::info('PoliBot: silêncio — o atendimento não é do bot', [
            'motivo' => $motivo,
            'contact_uuid' => $session->contact_uuid,
            'attendance_uuid' => $message->attendanceUuid,
            'attendant_uuid' => $message->attendanceAttendantUuid,
            'attendance_status' => $message->attendanceStatus,
            'bot_user_uuid' => $this->botUserUuid(),
        ]);

        $this->save($session);
    }

    /**
     * Mensagem presa no atendimento que a Lara fechou: traz o contato de
     * volta para O Lara (atendimento novo) e abre o menu com o que ele disse.
     */
    private function rescue(BotSession $session, ParsedPoliMessage $message): void
    {
        Log::info('PoliBot: resgate de mensagem presa em atendimento encerrado pela Lara', [
            'contact_uuid' => $session->contact_uuid,
            'attendance_uuid' => $message->attendanceUuid,
        ]);

        $session->reset();
        $session->closed_attendance_uuid = null;
        $session->closed_at = null;

        if ($this->out->isLive() && !$this->out->forward($session, (string) $this->botUserUuid())) {
            Log::warning('PoliBot: resgate sem forward — a mensagem fica no atendimento encerrado', [
                'contact_uuid' => $session->contact_uuid,
            ]);

            return;
        }

        if (!$this->out->isLive()) {
            $this->out->action($session, 'forward usuario=' . $this->botUserUuid() . ' (simulado)');
        }

        // O atendimento novo só se conhece pelo evento da transferência, que
        // chega depois: sem uuid, a sessão adota o próximo que aparecer.
        $session->attendance_uuid = null;
        $session->lara_owned = true;
        $this->startTriggered($session, $message);
    }

    /**
     * Resposta à pergunta em aberto.
     */
    private function answer(BotSession $session, FlowDefinition $flow, ParsedPoliMessage $message): void
    {
        $step = $flow->step($session->step_key);

        if ($step === null) {
            $session->reset();

            return;
        }

        // Toque num menu nosso que NÃO é a pergunta em aberto: é um menu
        // antigo, rolado para cima. Não vale como resposta desta etapa.
        $contexto = $message->contextMessageUuid;
        if ($contexto !== null && $contexto !== $session->prompt_message_uuid
            && PoliMessage::where('uuid', $contexto)
                ->where('contact_uuid', $session->contact_uuid)
                ->where('direction', PoliMessage::OUT)
                ->exists()) {
            $this->say($session, (string) config('poli.bot.messages.stale_menu'));
            $this->prompt($session, $step);

            return;
        }

        $answer = $this->validator->validate($step, $message);

        if ($answer->valid) {
            if (filled($step['save_as'] ?? null)) {
                $session->put($step['save_as'], $answer->value);
            }

            $session->tentativas = 0;
            $next = $answer->option['next'] ?? $step['next'] ?? null;

            $next !== null ? $this->enter($session, $flow, $next) : $this->finish($session);

            return;
        }

        // Pergunta opcional ("o motorista trocou?"): quem responde outra coisa
        // já terminou — um "obrigado" não é erro. O fluxo acaba e a mensagem
        // vale como começo de conversa, como no encerramento diferido.
        if (!empty($step['optional'])) {
            $session->reset();
            $this->startTriggered($session, $message);

            return;
        }

        $session->tentativas = $session->tentativas + 1;

        if ($session->tentativas >= $flow->maxAttempts($step)) {
            $this->say($session, (string) config('poli.bot.messages.too_many_attempts'));
            $this->runAction($session, $flow, $flow->onMaxAttempts());

            return;
        }

        // Áudio/figurinha onde se esperava texto pede outra frase que a
        // correção do passo: o problema não é o conteúdo, é o formato.
        $correcao = $answer->reason === 'media'
            ? config('poli.bot.messages.invalid.media')
            : ($step['invalid'] ?? config('poli.bot.messages.invalid.' . $answer->reason) ?? config('poli.bot.messages.invalid.text'));

        $this->say($session, (string) $correcao);

        // Menu: a correção sozinha não basta, o contato precisa ver as opções.
        if (in_array($step['say']['type'] ?? null, ['menu', 'template'], true)) {
            $this->prompt($session, $step);
        }
    }

    /**
     * Entra num passo e segue pelos que não esperam resposta, até parar numa
     * pergunta, numa ação que encerra, ou no fim do fluxo.
     */
    private function enter(BotSession $session, FlowDefinition $flow, string $key): void
    {
        for ($i = 0; $i < self::MAX_CHAIN; $i++) {
            $step = $flow->step($key);

            if ($step === null) {
                Log::warning('PoliBot: passo inexistente', ['flow' => $flow->slug, 'step' => $key]);
                $session->reset();

                return;
            }

            // Transbordo fora do horário: antes do "vou te encaminhar", não
            // depois — o passo de fora do horário fala por ele.
            if (($step['action']['type'] ?? null) === 'handoff' && ($fora = $this->outOfHoursStep($flow)) !== null) {
                [$flow, $key] = $fora;
                $session->data = [];
                $session->tentativas = 0;
                continue;
            }

            $session->state = BotSession::STATE_FLOW;
            $session->flow_slug = $flow->slug;
            $session->step_key = $key;
            $session->prompt_message_uuid = null;

            if (isset($step['say'])) {
                $this->prompt($session, $step);
            }

            if (isset($step['action'])) {
                $seguinte = $this->runAction($session, $flow, $step['action']);

                if ($seguinte === false) {
                    return;                      // handoff / close: a conversa saiu do bot
                }

                if ($seguinte instanceof FlowDefinition) {
                    $flow = $seguinte;           // goto_flow
                    $key = (string) $flow->start();
                    continue;
                }
            }

            if (isset($step['expect'])) {
                return;                          // aguardando a resposta
            }

            if (!isset($step['next'])) {
                $this->finish($session);

                return;
            }

            $key = $step['next'];
        }

        Log::warning('PoliBot: fluxo encadeou passos demais sem perguntar nada', ['flow' => $flow->slug]);
        $session->reset();
    }

    /**
     * @param array<string, mixed> $action
     * @return FlowDefinition|bool false quando a conversa sai do bot; o fluxo
     *                             de destino num goto; true para seguir.
     */
    private function runAction(BotSession $session, FlowDefinition $flow, array $action): FlowDefinition|bool
    {
        switch ($action['type'] ?? null) {
            case 'handoff':
                $this->handoff($session, $flow, $action['team_uuid'] ?? config('poli.bot.fallback_team_uuid'));

                return false;

            case 'close':
                $this->close($session);

                return false;

            case 'goto_flow':
                $destino = $this->findFlow((string) ($action['flow'] ?? ''));

                if ($destino === null) {
                    Log::warning('PoliBot: goto para fluxo inexistente ou inativo', ['flow' => $action['flow'] ?? null]);
                    $session->reset();

                    return false;
                }

                $session->data = [];
                $session->tentativas = 0;

                return $destino;

            case 'uber_request':
                $this->registrarUber($session);

                return true;
        }

        Log::warning('PoliBot: ação desconhecida', ['flow' => $flow->slug, 'action' => $action]);

        return true;
    }

    /**
     * Transbordo para um time. O horário é conferido AGORA, e não quando a
     * conversa começou: fora dele a conversa vai para o passo de fora do
     * horário. Depois do distribute, um job confere se o atendimento saiu
     * mesmo do O Lara.
     */
    private function handoff(BotSession $session, ?FlowDefinition $flow, ?string $teamUuid, ?string $aviso = null): void
    {
        if (($fora = $this->outOfHoursStep($flow)) !== null) {
            [$destino, $passo] = $fora;
            $session->data = [];
            $session->tentativas = 0;
            $this->enter($session, $destino, $passo);

            return;
        }

        if ($aviso !== null) {
            $this->say($session, $aviso);
        }

        $feito = $this->out->handoff($session, $teamUuid);
        $session->toHuman();

        if ($feito && $session->lara_owned) {
            ConfirmPoliBotHandoff::dispatch($session->contact_uuid)
                ->delay(now()->addSeconds((int) config('poli.bot.handoff_confirm_seconds', 10)));
        }
    }

    /**
     * O passo de fora do horário, se agora estiver fora dele — do fluxo
     * atual, ou do fluxo de boas-vindas quando o atual não tem horário (o
     * fluxo do carro não tem, e o transbordo dele à noite cairia no vazio).
     * Desvia uma vez só por mensagem: o próprio passo de fora do horário pode
     * ter um transbordo.
     *
     * @return array{0: FlowDefinition, 1: string}|null
     */
    private function outOfHoursStep(?FlowDefinition $flow): ?array
    {
        if ($this->desviouPorHorario) {
            return null;
        }

        foreach ([$flow, $this->defaultFlow()] as $candidato) {
            if ($candidato === null || !$candidato->hasHours()) {
                continue;
            }

            $passo = $candidato->entryStep();

            if ($passo === null || $passo === $candidato->start()) {
                return null;
            }

            $this->desviouPorHorario = true;

            return [$candidato, $passo];
        }

        return null;
    }

    private function close(BotSession $session): void
    {
        $this->out->close($session) ? $session->markClosedByLara() : $session->reset();
    }

    /**
     * Cria o pedido de acesso do carro de aplicativo. De verdade em toda
     * conversa do O Lara. Na comparação em sombra de uma conversa do bot da
     * Poli, a escuta do Uber já está criando o pedido dela, e criar outro
     * aqui duplicaria o carro na portaria; no simulador, nunca.
     */
    private function registrarUber(BotSession $session): void
    {
        if (!$this->out->isLive()) {
            $this->out->action($session, 'uber_request (simulado: conversa fora do O Lara ou simulador)');

            return;
        }

        // Registrar de novo na mesma conversa (o fluxo voltou a uma pergunta)
        // atualiza o pedido que ela criou, em vez de abrir outro.
        $anterior = is_numeric($session->get('uber_access_request_id')) ? (int) $session->get('uber_access_request_id') : null;

        try {
            $pedido = $this->uber->registrarPedidoDoBot([
                'matricula' => (string) $session->get('matricula'),
                'nome' => (string) $session->get('nome'),
                'local' => $session->get('local'),
                'placa' => (string) $session->get('placa'),
                'print' => (string) $session->get('print'),
            ], $this->message, $anterior);

            $session->put('uber_access_request_id', $pedido->id);
            $this->out->action($session, "uber_request pedido={$pedido->id}" . ($pedido->id === $anterior ? ' atualizado' : ''));
        } catch (Throwable $e) {
            Log::error('PoliBot: falha ao registrar pedido do Uber', [
                'contact_uuid' => $session->contact_uuid,
                'erro' => $e->getMessage(),
            ]);

            $this->out->live(false)->action($session, 'uber_request FALHOU: ' . mb_strimwidth($e->getMessage(), 0, 200));
        }
    }

    /**
     * Fim do fluxo. Na conversa do O Lara o atendimento não fecha na hora:
     * fecha depois de alguns minutos de silêncio (expireSessions), e quem
     * escrever antes disso recebe o menu.
     */
    private function finish(BotSession $session): void
    {
        $session->lara_owned
            ? $session->toEnding((int) config('poli.bot.close_after_minutes', 10))
            : $session->reset();
    }

    /* ---------------------------------------------------------------------
     | Transferência para O Lara
     |---------------------------------------------------------------------*/

    /**
     * O atendimento foi transferido para O Lara (ATTENDANCE_REDIRECTED). A
     * Lara abre o fluxo pelo gatilho da última mensagem do contato antes da
     * transferência — o toque no menu do bot da Poli —, sem esperar a próxima.
     */
    /**
     * @param ParsedPoliMessage|null $ultima A última mensagem do contato antes
     *        da transferência, lida do webhook gravado (ProcessPoliBotRedirect).
     *        Sem ela, vale a última registrada em poli_messages.
     */
    public function handleRedirect(ParsedPoliMessage $redirect, ?ParsedPoliMessage $ultima = null): void
    {
        if ($this->mode() === self::MODE_OFF || $redirect->contactUuid === null) {
            return;
        }

        try {
            $this->desviouPorHorario = false;
            $this->startFromRedirect($redirect, $ultima);
        } catch (Throwable $e) {
            Log::error('PoliBot: falha ao abrir a conversa transferida', [
                'contact_uuid' => $redirect->contactUuid,
                'message_id' => $redirect->messageId,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    private function startFromRedirect(ParsedPoliMessage $redirect, ?ParsedPoliMessage $ultima): void
    {
        if (PoliMessage::where('uuid', $redirect->messageId)->exists()) {
            return;
        }

        $redirect = $this->confirmAttendance($redirect);

        if (!$this->ownsConversation($redirect)) {
            return;
        }

        $session = BotSession::find($redirect->contactUuid)
            ?? new BotSession(['contact_uuid' => $redirect->contactUuid]);

        $session->fill(array_filter([
            'contact_phone' => $redirect->contactPhone,
            'contact_name' => $redirect->contactName,
        ], 'filled'));

        $this->out = $this->outbox->live($this->liveFor(true));
        $gatilho = $this->lastInboundBeforeRedirect($session, $redirect, $ultima);

        $this->out->recordInboundRow($session, $redirect->messageId, 'REDIRECT', '[transferido para O Lara]');

        // A conversa já andou neste atendimento: a mensagem do contato foi
        // processada antes, ou foi a própria Lara quem encaminhou (resgate,
        // aviso do Uber) e a sessão está à espera do uuid do atendimento.
        if ($session->lara_owned && ($session->inFlow() || $session->isEnding())
            && (blank($session->attendance_uuid) || $session->attendance_uuid === $redirect->attendanceUuid)) {
            $session->attendance_uuid = $redirect->attendanceUuid;
            $session->save();

            return;
        }

        $session->reset();
        $session->lara_owned = true;
        $session->attendance_uuid = $redirect->attendanceUuid;

        if ($this->sentOutOfPilot($session)) {
            $this->save($session);

            return;
        }

        $this->message = $gatilho ?? $redirect;
        $gatilho !== null
            ? $this->startTriggered($session, $gatilho)
            : $this->startTriggered($session, $redirect, onlyAnyMessage: true);

        $this->save($session);
    }

    /**
     * A última mensagem de texto do contato antes da transferência, como
     * mensagem nova do atendimento transferido — para passar pelos gatilhos
     * dos fluxos e pelo atalho do menu inicial.
     */
    private function lastInboundBeforeRedirect(BotSession $session, ParsedPoliMessage $redirect, ?ParsedPoliMessage $doWebhook): ?ParsedPoliMessage
    {
        if ($doWebhook !== null) {
            $texto = $doWebhook->type === ParsedPoliMessage::TYPE_TEXT ? $doWebhook->text : null;
        } else {
            $linha = PoliMessage::where('contact_uuid', $session->contact_uuid)
                ->where('direction', PoliMessage::IN)
                ->where('type', '!=', 'REDIRECT')
                ->where('created_at', '>=', now()->subMinutes(self::REDIRECT_TRIGGER_MINUTES))
                ->orderByDesc('id')
                ->first();

            $texto = $linha?->type === 'TEXT' ? $linha->texto : null;
        }

        if (blank($texto)) {
            return null;
        }

        return new ParsedPoliMessage(
            messageId: $redirect->messageId,
            contactUuid: $redirect->contactUuid,
            contactPhone: $redirect->contactPhone ?? $session->contact_phone,
            contactName: $redirect->contactName ?? $session->contact_name,
            attendanceUuid: $redirect->attendanceUuid,
            type: ParsedPoliMessage::TYPE_TEXT,
            text: $texto,
            attendanceType: $redirect->attendanceType,
            attendanceStatus: $redirect->attendanceStatus,
            attendanceAttendantUuid: $redirect->attendanceAttendantUuid,
        );
    }

    /**
     * Evento reenviado pela Poli (X-Webhook-Attempt > 1) pode trazer o
     * atendimento como estava na entrega original. Antes de agir, confere na
     * API. Sem resposta dela, segue com o que veio no evento.
     */
    private function confirmAttendance(ParsedPoliMessage $message): ParsedPoliMessage
    {
        if (!$message->isRetry() || $message->contactUuid === null) {
            return $message;
        }

        try {
            $atual = $this->poli->atendimentoAtual($message->contactUuid);
        } catch (Throwable $e) {
            Log::warning('PoliBot: evento reenviado sem conferência do atendimento', [
                'contact_uuid' => $message->contactUuid,
                'erro' => $e->getMessage(),
            ]);

            return $message;
        }

        if ($atual === null) {
            return $message->withAttendance(null, 'CLOSED');
        }

        return $message->withAttendance(
            is_string($atual['attendant']['uuid'] ?? null) ? $atual['attendant']['uuid'] : null,
            is_string($atual['status'] ?? null) ? $atual['status'] : null,
        );
    }

    /* ---------------------------------------------------------------------
     | Início e palavras de escape
     |---------------------------------------------------------------------*/

    private function startTriggered(BotSession $session, ParsedPoliMessage $message, bool $onlyAnyMessage = false): void
    {
        $flow = $onlyAnyMessage ? $this->defaultFlow() : $this->triggeredFlow($message);

        // Fora do horário de atendimento o fluxo começa pelo passo próprio.
        $entrada = $flow?->entryStep();

        if ($flow === null || $entrada === null) {
            return;
        }

        $session->data = [];
        $session->tentativas = 0;

        // Atalho: se a primeira mensagem já é uma das opções do menu inicial
        // ("financeiro" digitado, ou o toque num menu antigo), ela vale como
        // resposta — mandar o menu para quem já escolheu seria só atrito.
        // Pelo NOME da opção, nunca pelo número: quem manda "1" sem ter visto
        // menu nenhum não escolheu nada.
        $inicio = $flow->step($entrada);
        if (!$onlyAnyMessage && ($inicio['expect']['type'] ?? null) === 'option'
            && $message->type === ParsedPoliMessage::TYPE_TEXT
            && !ctype_digit(AnswerValidator::normalize($message->text))
            && $this->validator->option($inicio['options'] ?? [], (string) $message->text)->valid) {
            $session->state = BotSession::STATE_FLOW;
            $session->flow_slug = $flow->slug;
            $session->step_key = $entrada;
            $this->answer($session, $flow, $message);

            return;
        }

        $this->enter($session, $flow, $entrada);
    }

    /* ---------------------------------------------------------------------
     | Simulação
     |---------------------------------------------------------------------*/

    /**
     * Rascunhos (fluxos inativos) passam a valer como se estivessem ativos.
     * Só para o simulador — a conversa de verdade nunca vê um fluxo inativo.
     */
    public function incluindoInativos(bool $incluir = true): static
    {
        $this->incluiInativos = $incluir;

        return $this;
    }

    /**
     * Simulação: nada sai para a Poli e nenhum pedido é criado, mesmo sendo
     * conversa do O Lara (que de verdade é sempre respondida).
     */
    public function simulando(bool $simular = true): static
    {
        $this->simulacao = $simular;

        return $this;
    }

    /**
     * Põe a conversa no começo de um fluxo específico, sem esperar gatilho.
     * É o "testar este fluxo" do simulador.
     */
    public function beginFlow(ParsedPoliMessage $message, string $slug): bool
    {
        $flow = $this->findFlow($slug);

        if ($flow === null || $message->contactUuid === null) {
            return false;
        }

        $session = BotSession::find($message->contactUuid)
            ?? new BotSession(['contact_uuid' => $message->contactUuid]);
        $session->fill(array_filter(['contact_name' => $message->contactName], 'filled'));

        $this->desviouPorHorario = false;
        $this->message = $message;
        $this->out = $this->outbox->live($this->liveFor($this->ownsConversation($message)));

        $session->reset();
        $session->lara_owned = $this->ownsConversation($message);
        $session->attendance_uuid = $message->attendanceUuid;
        $this->enter($session, $flow, (string) $flow->entryStep());
        $this->save($session);

        return true;
    }

    private function findFlow(?string $slug): ?FlowDefinition
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        $query = BotFlow::where('slug', $slug);

        if (!$this->incluiInativos) {
            $query->where('active', true);
        }

        return $query->first()?->flow();
    }

    /**
     * Primeiro o fluxo cujo gatilho de texto casa; senão o que abre em
     * qualquer mensagem.
     */
    private function triggeredFlow(ParsedPoliMessage $message): ?FlowDefinition
    {
        $texto = AnswerValidator::normalize($message->text);

        if ($texto !== '') {
            foreach ($this->activeFlows() as $flow) {
                foreach ($flow->triggerTexts() as $gatilho) {
                    $g = AnswerValidator::normalize($gatilho);

                    if ($texto === $g || str_starts_with($texto, $g . ' ')) {
                        return $flow;
                    }
                }
            }
        }

        return $this->defaultFlow();
    }

    private function defaultFlow(): ?FlowDefinition
    {
        foreach ($this->activeFlows() as $flow) {
            if ($flow->opensOnAnyMessage()) {
                return $flow;
            }
        }

        return null;
    }

    /** @return FlowDefinition[] */
    private function activeFlows(): array
    {
        return BotFlow::where('active', true)->orderBy('id')->get()->map->flow()->all();
    }

    /**
     * menu / sair / atendente, em qualquer ponto da conversa.
     *
     * @return bool true se a mensagem foi consumida pela palavra de escape
     */
    private function escape(BotSession $session, ParsedPoliMessage $message): bool
    {
        if ($message->type !== ParsedPoliMessage::TYPE_TEXT) {
            return false;
        }

        $texto = AnswerValidator::normalize($message->text);
        $palavras = (array) config('poli.bot.escape', []);

        $fluxoAtual = $session->inFlow() ? $this->findFlow($session->flow_slug) : null;
        $esperaNumero = ($fluxoAtual?->step($session->step_key)['expect']['type'] ?? null) === 'number';

        $casa = function (string $grupo) use ($texto, $palavras, $esperaNumero): bool {
            foreach ((array) ($palavras[$grupo] ?? []) as $p) {
                if ($p === '0' && $esperaNumero) {
                    continue;
                }
                if ($texto === AnswerValidator::normalize((string) $p)) {
                    return true;
                }
            }

            return false;
        };

        if ($casa('atendente')) {
            $this->handoff($session, $fluxoAtual, config('poli.bot.fallback_team_uuid'), (string) config('poli.bot.messages.handoff'));

            return true;
        }

        if ($casa('sair')) {
            $this->say($session, (string) config('poli.bot.messages.goodbye'));
            $this->close($session);

            return true;
        }

        if ($casa('menu')) {
            $session->reset();
            $this->startTriggered($session, $message, onlyAnyMessage: true);

            return true;
        }

        return false;
    }

    /* ---------------------------------------------------------------------
     | Fala
     |---------------------------------------------------------------------*/

    /**
     * Envia a mensagem do passo. Se o passo espera resposta, a mensagem vira
     * a pergunta em aberto — é contra ela que o toque em menu é conferido.
     *
     * @param array<string, mixed> $step
     */
    private function prompt(BotSession $session, array $step): void
    {
        $say = $step['say'] ?? null;

        if (!is_array($say)) {
            return;
        }

        $opcoes = isset($step['options']) && is_array($step['options']) ? array_values($step['options']) : null;

        $mensagem = match ($say['type'] ?? 'text') {
            'template' => $this->out->template(
                $session,
                (string) $say['template_uuid'],
                array_map(fn ($p) => $this->render($session, (string) $p), (array) ($say['params'] ?? [])),
                $opcoes,
            ),
            'menu' => $this->out->text($session, $this->menuText($session, (string) $say['text'], $opcoes ?? []), $opcoes),
            default => $this->out->text($session, $this->render($session, (string) ($say['text'] ?? '')), $opcoes),
        };

        if (isset($step['expect'])) {
            $session->prompt_message_uuid = $mensagem->uuid;
        }
    }

    private function say(BotSession $session, string $texto): void
    {
        if (trim($texto) !== '') {
            $this->out->text($session, $this->render($session, $texto));
        }
    }

    /** @param array<int, array<string, mixed>> $opcoes */
    private function menuText(BotSession $session, string $texto, array $opcoes): string
    {
        $linhas = [];
        foreach ($opcoes as $i => $opcao) {
            $linhas[] = ($i + 1) . ' - ' . ($opcao['label'] ?? '');
        }

        return $this->render($session, $texto) . "\n\n" . implode("\n", $linhas);
    }

    /**
     * {variavel} vira a resposta guardada; {contato} é o primeiro nome do
     * WhatsApp. Variável que não existe some, em vez de sair "{nome}" na
     * mensagem.
     */
    private function render(BotSession $session, string $texto): string
    {
        return preg_replace_callback('/\{([a-z0-9_]+)\}/i', function ($m) use ($session) {
            if ($m[1] === 'contato') {
                return $this->firstName($session->contact_name);
            }

            $valor = $session->get($m[1]);

            return is_scalar($valor) ? (string) $valor : '';
        }, $texto) ?? $texto;
    }

    /**
     * O nome do WhatsApp chega com sufixos ("Gustavo Coordenador de TI|Gustavo");
     * o último pedaço depois da barra é o perfil, e dele só o primeiro nome.
     */
    private function firstName(?string $nome): string
    {
        $perfil = trim((string) Str::afterLast((string) $nome, '|'));

        return Str::of($perfil)->before(' ')->title()->value();
    }

    /* ---------------------------------------------------------------------
     | Eventos do webhook (estado, sem resposta)
     |---------------------------------------------------------------------*/

    public function observe(array $payload): void
    {
        if ($this->mode() === self::MODE_OFF) {
            return;
        }

        try {
            $this->observeEvent($payload);
        } catch (Throwable $e) {
            Log::error('PoliBot: falha ao observar evento', ['erro' => $e->getMessage()]);
        }
    }

    private function observeEvent(array $payload): void
    {
        // ACK no formato da documentação: sem `value`.
        if (($payload['event'] ?? null) === 'ack' && is_string($payload['uuid'] ?? null)) {
            $this->updateAck($payload['uuid'], $payload['status'] ?? null);

            return;
        }

        $value = $payload['value'] ?? null;
        if (!is_array($value)) {
            return;
        }

        // Eco de uma mensagem que o próprio bot mandou: só atualiza o ACK.
        $uuid = $value['uuid'] ?? null;
        if (is_string($uuid) && PoliMessage::where('uuid', $uuid)->where('direction', PoliMessage::OUT)->exists()) {
            $this->updateAck($uuid, $value['ack'] ?? null);

            return;
        }

        $contato = $value['contact']['uuid'] ?? null;
        if (!is_string($contato) || $contato === '') {
            return;
        }

        $session = BotSession::find($contato);
        $tipo = $value['type'] ?? null;
        $atendimento = is_array($value['attendance'] ?? null) ? $value['attendance'] : [];

        // Atendimento encerrado — por atendente, pelo sistema ou pelo bot:
        // a próxima mensagem do contato é conversa nova. O registro do que a
        // Lara encerrou fica na sessão (é dele que o resgate precisa).
        if ($tipo === 'ATTENDANCE_CLOSED' || filled($atendimento['closed_reason'] ?? null)) {
            if ($session) {
                $session->endConversation();
                $session->save();
            }

            return;
        }

        if ($this->humanTookOver($payload, $value)) {
            $session ??= new BotSession(['contact_uuid' => $contato]);

            if (!$session->isHuman()) {
                $session->toHuman();
                $session->attendance_uuid = $atendimento['uuid'] ?? $session->attendance_uuid;
                $session->save();
            }
        }
    }

    /**
     * Um humano assumiu a conversa:
     *   - a Poli redirecionou o contato para um atendente que não é O Lara
     *     (a transferência PARA O Lara é o gatilho do handleRedirect);
     *   - o atendimento do evento tem um atendente que não é O Lara — vale
     *     para quem assume pelo painel e para o distribute da própria Lara;
     *   - saiu uma mensagem de atendente de verdade (autor USER com uuid).
     *     O bot nativo da Poli e a API saem como USER, mas SEM uuid.
     */
    private function humanTookOver(array $payload, array $value): bool
    {
        $atendente = $value['attendance']['attendant']['uuid'] ?? null;

        if (($value['type'] ?? null) === 'ATTENDANCE_REDIRECTED') {
            return filled($atendente) && !$this->isLaraAttendant($atendente);
        }

        if (is_string($atendente) && $atendente !== '') {
            return !$this->isLaraAttendant($atendente);
        }

        $autor = $value['author'] ?? [];

        // O eco das mensagens do próprio bot é reconhecido pelo uuid (ver
        // observeEvent). Esta lista cobre a corrida em que o eco chega antes
        // de o uuid ter sido gravado: o autor com que a API publica em nome
        // do token não é atendente.
        $proprios = array_filter([...(array) config('poli.bot.own_author_uuids', []), $this->botUserUuid()]);
        if (in_array($autor['uuid'] ?? null, $proprios, true)) {
            return false;
        }

        return ($payload['event'] ?? null) === 'sent'
            && ($value['direction'] ?? null) === 'OUT'
            && ($autor['type'] ?? null) === 'USER'
            && filled($autor['uuid'] ?? null);
    }

    private function updateAck(string $uuid, mixed $ack): void
    {
        if (!is_string($ack) || $ack === '') {
            return;
        }

        $atualizadas = PoliMessage::where('uuid', $uuid)->update(['ack' => $ack]);

        if ($atualizadas > 0 && $ack === 'ERROR') {
            Log::warning('PoliBot: a Poli reportou ERROR numa mensagem do bot', ['uuid' => $uuid]);
        }
    }

    /* ---------------------------------------------------------------------
     | Agendamentos
     |---------------------------------------------------------------------*/

    /**
     * Encerramento diferido e abandono. A Poli não encerra por inatividade
     * as conversas do O Lara: sem isto, elas ficariam abertas para sempre.
     *
     * @return array{encerradas: int, abandonadas: int}
     */
    public function expireSessions(): array
    {
        $contagem = ['encerradas' => 0, 'abandonadas' => 0];

        if ($this->mode() === self::MODE_OFF) {
            return $contagem;
        }

        $candidatas = BotSession::where('lara_owned', true)
            ->whereIn('state', [BotSession::STATE_ENDING, BotSession::STATE_FLOW])
            ->where(fn ($q) => $q->whereNull('attendance_uuid')->orWhere('attendance_uuid', '!=', BotSimulator::ATTENDANCE))
            ->pluck('contact_uuid');

        foreach ($candidatas as $contato) {
            try {
                $resultado = Cache::lock('poli-bot:contato:' . $contato, 60)->get(fn () => $this->expireOne($contato));
            } catch (Throwable $e) {
                Log::error('PoliBot: falha ao expirar sessão', ['contact_uuid' => $contato, 'erro' => $e->getMessage()]);
                $resultado = null;
            }

            if (is_string($resultado)) {
                $contagem[$resultado]++;
            }
        }

        return $contagem;
    }

    /** @return string|null 'encerradas', 'abandonadas' ou null (nada a fazer) */
    private function expireOne(string $contato): ?string
    {
        $session = BotSession::find($contato);

        if ($session === null || !$session->lara_owned) {
            return null;
        }

        $this->out = $this->outbox->live($this->liveFor(true));

        if ($session->isEnding()) {
            if ($session->ending_at === null || $session->ending_at->isFuture()) {
                return null;
            }

            $this->close($session);
            $session->save();

            return 'encerradas';
        }

        if (!$session->inFlow()) {
            return null;
        }

        $flow = $this->findFlow($session->flow_slug);
        $prazo = $flow?->timeoutMinutes() ?? (int) config('poli.bot.default_timeout_minutes', 15);

        if ($session->last_interaction_at === null || $session->last_interaction_at->copy()->addMinutes($prazo)->isFuture()) {
            return null;
        }

        // Sem resposta a uma pergunta opcional: o fluxo tinha terminado, e
        // fecha como no encerramento diferido — sem "não tivemos resposta".
        if (!empty($flow?->step($session->step_key)['optional'])) {
            $this->close($session);
            $session->save();

            return 'encerradas';
        }

        $this->say($session, (string) config('poli.bot.messages.abandoned'));
        $this->close($session);
        $session->save();

        return 'abandonadas';
    }

    /**
     * Confere, depois do distribute, se o atendimento saiu mesmo do O Lara.
     * Se não saiu, o contato fica sabendo e a conversa volta para a Lara.
     */
    public function confirmHandoff(string $contactUuid): void
    {
        if ($this->mode() === self::MODE_OFF) {
            return;
        }

        $session = BotSession::find($contactUuid);

        if ($session === null || !$session->isHuman() || !$session->lara_owned) {
            return;
        }

        try {
            $atual = $this->poli->atendimentoAtual($contactUuid);
        } catch (Throwable $e) {
            Log::warning('PoliBot: não deu para conferir o transbordo', ['contact_uuid' => $contactUuid, 'erro' => $e->getMessage()]);

            return;
        }

        if ($atual === null || ($atual['status'] ?? null) === 'CLOSED'
            || !$this->isLaraAttendant(is_string($atual['attendant']['uuid'] ?? null) ? $atual['attendant']['uuid'] : null)) {
            return;
        }

        Log::error('PoliBot: transbordo aceito pela Poli, mas o atendimento continua com O Lara', [
            'contact_uuid' => $contactUuid,
            'attendance_uuid' => $atual['uuid'] ?? $session->attendance_uuid,
        ]);

        $this->out = $this->outbox->live(true);
        $this->say($session, (string) config('poli.bot.messages.handoff_failed'));
        $session->toEnding((int) config('poli.bot.close_after_minutes', 10));
        $session->save();
    }

    /**
     * Conversas abertas com O Lara que a Lara não está conduzindo — queda,
     * fila parada, sessão perdida. Encerra (ou, em `$devolverPara`, distribui
     * para esse time: é o rollback). Cada contato é conferido na API antes:
     * a lista vem de um endpoint ainda não exercitado.
     *
     * @return array<int, array{contact_uuid: string, acao: string}>
     */
    public function reconcile(bool $simular = false, ?string $devolverPara = null): array
    {
        $usuario = $this->botUserUuid();

        if ($usuario === null || ($devolverPara === null && $this->mode() === self::MODE_OFF)) {
            return [];
        }

        $chats = $this->poli->chatsAtribuidos($usuario);
        $teto = (int) config('poli.bot.reconcile_max_chats', 50);

        if (count($chats) > $teto) {
            Log::warning('PoliBot: reconciliação abortada — a Poli devolveu conversas demais para um usuário só', [
                'conversas' => count($chats),
                'teto' => $teto,
            ]);

            return [];
        }

        $relatorio = [];

        foreach ($chats as $chat) {
            // Medido em 29/09/2026: o item da lista é o contato —
            // {id, uuid, contact_origin, attendance_origin, from_campaign}.
            // As outras chaves ficam para o caso de a Poli embrulhar diferente.
            $contato = $chat['contact']['uuid'] ?? $chat['contact_uuid'] ?? $chat['uuid'] ?? null;

            if (!is_string($contato) || $contato === '') {
                Log::warning('PoliBot: conversa da reconciliação sem contato reconhecível', ['chaves' => array_keys($chat)]);
                continue;
            }

            // Um item com problema (uuid que não é de contato, API fora) não
            // derruba a rodada: é pulado, e nada é encerrado por ele.
            try {
                $acao = Cache::lock('poli-bot:contato:' . $contato, 60)
                    ->get(fn () => $this->reconcileOne($contato, $simular, $devolverPara));
            } catch (Throwable $e) {
                Log::warning('PoliBot: reconciliação pulou um contato', ['contact_uuid' => $contato, 'erro' => $e->getMessage()]);
                $acao = null;
            }

            if (is_string($acao)) {
                $relatorio[] = ['contact_uuid' => $contato, 'acao' => $acao];
            }
        }

        return $relatorio;
    }

    private function reconcileOne(string $contato, bool $simular, ?string $devolverPara): ?string
    {
        $session = BotSession::find($contato);

        if ($devolverPara === null && $session !== null) {
            $ativa = $session->lara_owned && ($session->inFlow() || $session->isEnding());
            $recente = $session->last_interaction_at?->copy()->addMinutes(self::RECONCILE_GRACE_MINUTES)->isFuture();

            if ($ativa || $recente) {
                return null;
            }
        }

        $atual = $this->poli->atendimentoAtual($contato);
        $atendente = is_string($atual['attendant']['uuid'] ?? null) ? $atual['attendant']['uuid'] : null;

        if ($atual === null || ($atual['status'] ?? null) === 'CLOSED' || !$this->isLaraAttendant($atendente)) {
            return null;
        }

        $acao = $devolverPara !== null ? "devolver time={$devolverPara}" : 'encerrar';

        if ($simular) {
            return $acao . ' (simulado)';
        }

        $session ??= new BotSession(['contact_uuid' => $contato]);
        $session->attendance_uuid = is_string($atual['uuid'] ?? null) ? $atual['uuid'] : $session->attendance_uuid;
        $this->out = $this->outbox->live(true);

        if ($devolverPara !== null) {
            $this->out->handoff($session, $devolverPara);
            $session->toHuman();
        } else {
            $this->close($session);
        }

        $session->save();

        return $acao;
    }

    /**
     * Depois do aviso de chegada do Uber: a conversa é do O Lara e fecha
     * sozinha se o contato não responder. Sem `$attendanceUuid` (o forward
     * abre um atendimento novo), o uuid chega depois, com a transferência.
     */
    public function awaitReplyAfterNotice(string $contactUuid, ?string $attendanceUuid = null): void
    {
        $session = BotSession::find($contactUuid) ?? new BotSession(['contact_uuid' => $contactUuid]);
        $session->toEnding((int) config('poli.bot.close_after_minutes', 10));
        $session->attendance_uuid = $attendanceUuid;
        $session->last_interaction_at = now();
        $session->save();
    }

    /* ---------------------------------------------------------------------
     | Apoio
     |---------------------------------------------------------------------*/

    private function humanExpired(BotSession $session): bool
    {
        return $session->human_since !== null
            && $session->human_since->copy()->addHours((int) config('poli.bot.human_timeout_hours', 12))->isPast();
    }

    private function timedOut(BotSession $session, FlowDefinition $flow): bool
    {
        return $session->last_interaction_at !== null
            && $session->last_interaction_at->copy()->addMinutes($flow->timeoutMinutes())->isPast();
    }

    private function recordInbound(BotSession $session, ParsedPoliMessage $message): void
    {
        $this->out->recordInboundRow(
            $session,
            $message->messageId,
            match ($message->type) {
                ParsedPoliMessage::TYPE_TEXT => 'TEXT',
                ParsedPoliMessage::TYPE_IMAGE => 'IMAGE',
                default => 'MEDIA',
            },
            $message->text,
        );
    }

    private function save(BotSession $session): void
    {
        $session->last_interaction_at = now();
        $session->save();
    }
}
