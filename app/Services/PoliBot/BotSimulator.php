<?php

namespace App\Services\PoliBot;

use App\Models\BotSession;
use App\Models\PoliMessage;
use App\Services\Poli\ParsedPoliMessage;
use Illuminate\Support\Str;

/**
 * Conversa com o bot sem WhatsApp — o simulador da tela e do terminal.
 *
 * Roda em simulação (BotEngine::simulando): NADA sai para a Poli e nenhum
 * pedido de Uber é criado, seja qual for o .env. A conversa simulada é uma conversa do
 * O Lara (é só nelas que a Lara fala), sob um atendimento próprio da
 * simulação — que o agendamento de encerramento ignora. As respostas ficam em
 * poli_messages (shadow = true) e o estado em bot_sessions, como numa
 * conversa de verdade. Fluxos inativos também valem: é assim que se testa um
 * rascunho antes de ativá-lo.
 */
class BotSimulator
{
    public const ATTENDANCE = 'sim-atendimento';

    private const USUARIO_SIMULADO = 'simulador-o-lara';

    public function __construct(private readonly BotEngine $engine) {}

    /**
     * @return array{replies: array<int, array<string, mixed>>, session: array<string, mixed>|null}
     */
    public function send(string $contato, ?string $texto, ?string $imagem = null): array
    {
        return $this->emSombra($contato, function () use ($contato, $texto, $imagem) {
            $this->engine->handleInbound($this->mensagem($contato, $texto, $imagem));
        });
    }

    /**
     * @return array{replies: array<int, array<string, mixed>>, session: array<string, mixed>|null}
     */
    public function start(string $contato, string $slug): array
    {
        return $this->emSombra($contato, function () use ($contato, $slug) {
            $this->engine->beginFlow($this->mensagem($contato, null), $slug);
        });
    }

    public function reset(string $contato): void
    {
        BotSession::where('contact_uuid', $contato)->delete();
        PoliMessage::where('contact_uuid', $contato)->delete();
    }

    private function emSombra(string $contato, callable $acao): array
    {
        $antes = [config('poli.bot.mode'), config('poli.bot.user_uuid')];
        config([
            'poli.bot.mode' => BotEngine::MODE_SHADOW,
            'poli.bot.user_uuid' => filled($antes[1]) ? $antes[1] : self::USUARIO_SIMULADO,
        ]);

        $ultimo = (int) PoliMessage::where('contact_uuid', $contato)->max('id');

        try {
            $this->engine->incluindoInativos()->simulando();
            $acao();
        } finally {
            $this->engine->incluindoInativos(false)->simulando(false);
            config(['poli.bot.mode' => $antes[0], 'poli.bot.user_uuid' => $antes[1]]);
        }

        $respostas = PoliMessage::where('contact_uuid', $contato)
            ->where('id', '>', $ultimo)
            ->where('direction', PoliMessage::OUT)
            ->orderBy('id')
            ->get()
            ->map(fn (PoliMessage $m) => [
                'type' => $m->type,
                'text' => $m->texto,
                'template_uuid' => $m->template_uuid,
                'options' => $m->options,
                'step' => $m->step_key,
            ])
            ->all();

        $sessao = BotSession::find($contato);

        return [
            'replies' => $respostas,
            'session' => $sessao ? [
                'state' => $sessao->state,
                'flow' => $sessao->flow_slug,
                'step' => $sessao->step_key,
                'tentativas' => $sessao->tentativas,
                'data' => $sessao->data ?? [],
            ] : null,
        ];
    }

    private function mensagem(string $contato, ?string $texto, ?string $imagem = null): ParsedPoliMessage
    {
        return new ParsedPoliMessage(
            messageId: 'sim-' . Str::ulid(),
            contactUuid: $contato,
            contactPhone: null,
            contactName: 'Simulador|Teste',
            attendanceUuid: self::ATTENDANCE,
            type: $imagem ? ParsedPoliMessage::TYPE_IMAGE : ParsedPoliMessage::TYPE_TEXT,
            text: $imagem ? null : $texto,
            mediaUrl: $imagem ?: null,
            attendanceAttendantUuid: (string) config('poli.bot.user_uuid'),
        );
    }
}
