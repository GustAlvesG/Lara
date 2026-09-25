<?php

namespace App\Services\PoliBot;

use App\Models\BotFlow;
use App\Models\BotSession;
use App\Models\PoliMessage;
use App\Services\Poli\ParsedPoliMessage;
use App\Services\UberAccessRequestFlow;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Conduz a conversa do WhatsApp no lugar do bot da Poli.
 *
 * Duas portas de entrada:
 *
 *   - handleInbound(): uma mensagem do contato, já na ordem certa (quem chama
 *     é o ProcessUberAccessRequestMessage, depois de ceder a vez às irmãs
 *     mais antigas). Aqui se valida a resposta, se corrige, se avança.
 *
 *   - observe(): qualquer evento do webhook, direto do controller. Não
 *     responde nada — só mantém o estado: atendente assumiu (bot quieto),
 *     atendimento encerrado (bot volta), ACK das mensagens que o bot mandou.
 *
 * Nenhuma das duas lança: o webhook e a escuta do Uber não podem cair por
 * causa do bot. Erro vira log.
 */
class BotEngine
{
    public const MODE_OFF = 'off';
    public const MODE_SHADOW = 'shadow';
    public const MODE_ON = 'on';

    /** Passos sem pergunta encadeados de uma vez — trava contra laço na definição. */
    private const MAX_CHAIN = 20;

    private BotOutbox $out;
    private ParsedPoliMessage $message;

    public function __construct(
        private readonly AnswerValidator $validator,
        private readonly BotOutbox $outbox,
        private readonly UberAccessRequestFlow $uber,
    ) {}

    public function mode(): string
    {
        $mode = config('poli.bot.mode');

        return in_array($mode, [self::MODE_SHADOW, self::MODE_ON], true) ? $mode : self::MODE_OFF;
    }

    /**
     * A escuta do fluxo do Uber (que acompanha as perguntas do bot da POLI)
     * só faz sentido enquanto o bot da Poli atende. Com o da Lara no ar, é o
     * fluxo do bot que cria o pedido.
     */
    public function listensToPoliUberFlow(): bool
    {
        return $this->mode() !== self::MODE_ON;
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

        $session = BotSession::find($message->contactUuid)
            ?? new BotSession(['contact_uuid' => $message->contactUuid]);

        $session->fill(array_filter([
            'contact_phone' => $message->contactPhone,
            'contact_name' => $message->contactName,
            'attendance_uuid' => $message->attendanceUuid,
        ], 'filled'));

        $this->message = $message;
        $this->out = $this->outbox->live($this->isLive($session));

        $this->recordInbound($session, $message);

        if ($session->isHuman()) {
            if (!$this->humanExpired($session)) {
                $this->save($session);

                return;
            }

            $session->reset();
        }

        if ($this->escape($session, $message)) {
            $this->save($session);

            return;
        }

        if ($session->inFlow()) {
            $flow = BotFlow::findActive($session->flow_slug)?->flow();

            if ($flow === null) {
                $session->reset();
            } elseif ($this->timedOut($session, $flow)) {
                $session->reset();
                $this->say($session, (string) config('poli.bot.messages.expired'));
                $this->startTriggered($session, $message, onlyAnyMessage: true);
                $this->save($session);

                return;
            } else {
                $this->answer($session, $flow, $message);
                $this->save($session);

                return;
            }
        }

        $this->startTriggered($session, $message);
        $this->save($session);
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
                $this->out->handoff($session, $action['team_uuid'] ?? config('poli.bot.fallback_team_uuid'));
                $session->toHuman();

                return false;

            case 'close':
                $this->out->close($session);
                $session->reset();

                return false;

            case 'goto_flow':
                $destino = BotFlow::findActive((string) ($action['flow'] ?? ''))?->flow();

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
     * Cria o pedido de acesso do carro de aplicativo. Só de verdade no modo
     * ON: em shadow a escuta do bot da Poli já está criando o pedido desta
     * mesma conversa — criar outro aqui duplicaria o carro na portaria. Vale
     * também para os contatos do piloto.
     */
    private function registrarUber(BotSession $session): void
    {
        if ($this->mode() !== self::MODE_ON) {
            $this->out->live(false)->action($session, 'uber_request (simulado: fora do modo on)');

            return;
        }

        try {
            $pedido = $this->uber->registrarPedidoDoBot([
                'matricula' => (string) $session->get('matricula'),
                'nome' => (string) $session->get('nome'),
                'local' => $session->get('local'),
                'placa' => (string) $session->get('placa'),
                'print' => (string) $session->get('print'),
            ], $this->message);

            $session->put('uber_access_request_id', $pedido->id);
            $this->out->action($session, "uber_request pedido={$pedido->id}");
        } catch (Throwable $e) {
            Log::error('PoliBot: falha ao registrar pedido do Uber', [
                'contact_uuid' => $session->contact_uuid,
                'erro' => $e->getMessage(),
            ]);

            $this->out->live(false)->action($session, 'uber_request FALHOU: ' . mb_strimwidth($e->getMessage(), 0, 200));
        }
    }

    private function finish(BotSession $session): void
    {
        $session->reset();
    }

    /* ---------------------------------------------------------------------
     | Início e palavras de escape
     |---------------------------------------------------------------------*/

    private function startTriggered(BotSession $session, ParsedPoliMessage $message, bool $onlyAnyMessage = false): void
    {
        $flow = $onlyAnyMessage ? $this->defaultFlow() : $this->triggeredFlow($message);

        if ($flow === null || $flow->start() === null) {
            return;
        }

        $session->data = [];
        $session->tentativas = 0;

        // Atalho: se a primeira mensagem já é uma das opções do menu inicial
        // ("financeiro" digitado, ou o toque num menu antigo), ela vale como
        // resposta — mandar o menu para quem já escolheu seria só atrito.
        // Pelo NOME da opção, nunca pelo número: quem manda "1" sem ter visto
        // menu nenhum não escolheu nada.
        $inicio = $flow->step($flow->start());
        if (!$onlyAnyMessage && ($inicio['expect']['type'] ?? null) === 'option'
            && $message->type === ParsedPoliMessage::TYPE_TEXT
            && !ctype_digit(AnswerValidator::normalize($message->text))
            && $this->validator->option($inicio['options'] ?? [], (string) $message->text)->valid) {
            $session->state = BotSession::STATE_FLOW;
            $session->flow_slug = $flow->slug;
            $session->step_key = $flow->start();
            $this->answer($session, $flow, $message);

            return;
        }

        $this->enter($session, $flow, $flow->start());
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

        $esperaNumero = false;
        if ($session->inFlow()) {
            $step = BotFlow::findActive($session->flow_slug)?->flow()->step($session->step_key);
            $esperaNumero = ($step['expect']['type'] ?? null) === 'number';
        }

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
            $this->say($session, (string) config('poli.bot.messages.handoff'));
            $this->out->handoff($session, config('poli.bot.fallback_team_uuid'));
            $session->toHuman();

            return true;
        }

        if ($casa('sair')) {
            $this->say($session, (string) config('poli.bot.messages.goodbye'));
            $this->out->close($session);
            $session->reset();

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
        $atendimento = $value['attendance'] ?? null;

        // Atendimento encerrado — por atendente, pelo sistema ou pelo bot:
        // a próxima mensagem do contato é conversa nova.
        if ($tipo === 'ATTENDANCE_CLOSED' || filled($atendimento['closed_reason'] ?? null)) {
            if ($session) {
                $session->reset();
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
     *   - a Poli redirecionou o contato para um atendente; ou
     *   - saiu uma mensagem de atendente de verdade (autor USER com uuid).
     *     O bot nativo da Poli também sai como USER, mas SEM uuid.
     */
    private function humanTookOver(array $payload, array $value): bool
    {
        if (($value['type'] ?? null) === 'ATTENDANCE_REDIRECTED') {
            return filled($value['attendance']['attendant']['uuid'] ?? null);
        }

        $autor = $value['author'] ?? [];

        // O eco das mensagens do próprio bot é reconhecido pelo uuid (ver
        // observeEvent). Esta lista cobre a corrida em que o eco chega antes
        // de o uuid ter sido gravado: o autor com que a API publica em nome
        // do token não é atendente.
        if (in_array($autor['uuid'] ?? null, (array) config('poli.bot.own_author_uuids', []), true)) {
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
     | Apoio
     |---------------------------------------------------------------------*/

    private function isLive(BotSession $session): bool
    {
        if ($this->mode() === self::MODE_ON) {
            return true;
        }

        $piloto = (array) config('poli.bot.live_contacts', []);
        $telefone = preg_replace('/\D/', '', (string) $session->contact_phone);

        return in_array($session->contact_uuid, $piloto, true)
            || ($telefone !== '' && in_array($telefone, $piloto, true));
    }

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
        PoliMessage::create([
            'uuid' => $message->messageId,
            'contact_uuid' => $session->contact_uuid,
            'direction' => PoliMessage::IN,
            'type' => match ($message->type) {
                ParsedPoliMessage::TYPE_TEXT => 'TEXT',
                ParsedPoliMessage::TYPE_IMAGE => 'IMAGE',
                default => 'MEDIA',
            },
            'texto' => PoliTextMask::mask($message->text),
            'flow_slug' => $session->flow_slug,
            'step_key' => $session->step_key,
            'shadow' => !$this->out->isLive(),
        ]);
    }

    private function save(BotSession $session): void
    {
        $session->last_interaction_at = now();
        $session->save();
    }
}
