<?php

namespace App\Services\PoliBot;

use App\Models\BotSession;
use App\Models\PoliMessage;
use App\Services\Poli\PoliClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tudo que o bot diz ou faz na Poli passa por aqui.
 *
 * `live = false` é o modo sombra: nada sai, mas tudo é registrado em
 * poli_messages como se tivesse saído — é o que permite comparar o que a Lara
 * responderia com o que o bot da Poli respondeu, antes de trocar um pelo
 * outro.
 *
 * Os envios são síncronos e em ordem, dentro do job que processa a mensagem:
 * "placa inválida" tem de chegar antes de "qual é a placa?", e dois jobs na
 * fila não garantem isso. Falha de envio não interrompe a conversa — fica
 * registrada na linha (ack FAILED + erro) e no log.
 */
class BotOutbox
{
    private bool $live = false;

    public function __construct(private readonly PoliClient $poli) {}

    public function live(bool $live): static
    {
        $clone = clone $this;
        $clone->live = $live;

        return $clone;
    }

    public function isLive(): bool
    {
        return $this->live;
    }

    /**
     * @param array<int, array<string, mixed>>|null $options opções do menu, para casar o toque depois
     */
    public function text(BotSession $session, string $texto, ?array $options = null): PoliMessage
    {
        return $this->send($session, 'TEXT', $texto, null, $options,
            fn () => $this->poli->texto($session->contact_uuid, $texto));
    }

    /**
     * @param string[] $params
     * @param array<int, array<string, mixed>>|null $options
     */
    public function template(BotSession $session, string $templateUuid, array $params = [], ?array $options = null): PoliMessage
    {
        $descricao = '[template ' . $templateUuid . ']' . ($params ? ' ' . implode(' | ', $params) : '');

        return $this->send($session, 'TEMPLATE', $descricao, $templateUuid, $options,
            fn () => $this->poli->template($session->contact_uuid, $templateUuid, $params));
    }

    /**
     * Passa o contato para um time. Sem time, só registra: o bot silencia do
     * mesmo jeito, e a conversa fica na fila de quem atende a conta.
     */
    public function handoff(BotSession $session, ?string $teamUuid): void
    {
        $this->action($session, 'handoff' . ($teamUuid ? " time={$teamUuid}" : ' sem time'),
            $teamUuid ? fn () => $this->poli->distribuir($session->contact_uuid, $teamUuid) : null);
    }

    public function close(BotSession $session): void
    {
        $this->action($session, 'close', fn () => $this->poli->encerrar($session->contact_uuid));
    }

    /**
     * Registra uma ação que não é mensagem — e, fora do modo sombra, executa.
     */
    public function action(BotSession $session, string $descricao, ?callable $executar = null): PoliMessage
    {
        $linha = $this->record($session, 'ACTION', $descricao, null, null, 'shadow-' . Str::ulid());

        if (!$this->live || $executar === null) {
            return $linha;
        }

        try {
            $executar();
            $linha->update(['ack' => 'DONE']);
        } catch (Throwable $e) {
            $this->failed($linha, $e);
        }

        return $linha;
    }

    private function send(
        BotSession $session,
        string $tipo,
        string $texto,
        ?string $templateUuid,
        ?array $options,
        callable $enviar,
    ): PoliMessage {
        if (!$this->live) {
            return $this->record($session, $tipo, $texto, $templateUuid, $options, 'shadow-' . Str::ulid());
        }

        try {
            $resposta = $enviar();
            $uuid = $resposta['uuid'] ?? null;

            if (!is_string($uuid) || $uuid === '') {
                throw new \RuntimeException('Poli respondeu sem uuid — envio não confirmado');
            }

            $linha = $this->record($session, $tipo, $texto, $templateUuid, $options, $uuid);
            $linha->update(['ack' => $resposta['ack'] ?? 'CREATED']);

            return $linha;
        } catch (Throwable $e) {
            $linha = $this->record($session, $tipo, $texto, $templateUuid, $options, 'failed-' . Str::ulid());
            $this->failed($linha, $e);

            return $linha;
        }
    }

    private function record(
        BotSession $session,
        string $tipo,
        string $texto,
        ?string $templateUuid,
        ?array $options,
        string $uuid,
    ): PoliMessage {
        return PoliMessage::create([
            'uuid' => $uuid,
            'contact_uuid' => $session->contact_uuid,
            'direction' => PoliMessage::OUT,
            'type' => $tipo,
            'texto' => PoliTextMask::mask($texto),
            'template_uuid' => $templateUuid,
            'options' => $options,
            'flow_slug' => $session->flow_slug,
            'step_key' => $session->step_key,
            'shadow' => !$this->live,
        ]);
    }

    private function failed(PoliMessage $linha, Throwable $e): void
    {
        $erro = $e instanceof RequestException
            ? 'HTTP ' . $e->response->status() . ': ' . mb_strimwidth($e->response->body(), 0, 300)
            : class_basename($e) . ': ' . $e->getMessage();

        $linha->update(['ack' => 'FAILED', 'error' => mb_strimwidth($erro, 0, 500)]);

        Log::warning('PoliBot: falha ao falar com a Poli', [
            'poli_message_id' => $linha->id,
            'contact_uuid' => $linha->contact_uuid,
            'type' => $linha->type,
            'erro' => $erro,
        ]);
    }
}
