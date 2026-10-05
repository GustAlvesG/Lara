<?php

namespace App\Services\PoliBot;

use App\Services\Poli\PoliClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * O que acontece com o atendimento depois do aviso de chegada do Uber.
 *
 * Substitui o close imediato do PR #43, que fechava também o atendimento de
 * um humano que estivesse conversando com o sócio:
 *
 *   - atendimento aberto com um humano: só o aviso (HUMANO);
 *   - modo on: a conversa passa para O Lara ANTES do aviso, e fecha depois
 *     de alguns minutos se o sócio não responder — a resposta dele chega à
 *     Lara, e o close tardio não prende a mensagem seguinte (LARA);
 *   - shadow (piloto), off ou O Lara não configurado: o close logo depois do
 *     aviso, como no #43 (ENCERRAR) — o processo do Uber igual ao de hoje.
 *
 * Sem conseguir saber de quem é o atendimento, não mexe nele (HUMANO).
 */
class UberArrivalHandover
{
    public const HUMANO = 'humano';
    public const LARA = 'lara';
    public const ENCERRAR = 'encerrar';

    public function __construct(
        private readonly PoliClient $poli,
        private readonly BotEngine $bot,
    ) {}

    /**
     * Decide, e no caso da Lara já transfere. Pode rodar de novo na
     * retentativa do job: com o atendimento já no O Lara, não transfere outra
     * vez (cada forward abre um atendimento novo).
     */
    public function prepare(string $contactUuid): string
    {
        try {
            $atual = $this->poli->atendimentoAtual($contactUuid);
        } catch (Throwable $e) {
            Log::warning('Poli: aviso do Uber sem conferir o atendimento — só o aviso sai', [
                'contact_uuid' => $contactUuid,
                'erro' => $e->getMessage(),
            ]);

            return self::HUMANO;
        }

        $aberto = $atual !== null && ($atual['status'] ?? null) !== 'CLOSED';
        $atendente = $aberto && is_string($atual['attendant']['uuid'] ?? null) ? $atual['attendant']['uuid'] : null;
        $comOLara = $this->bot->isLaraAttendant($atendente);

        if ($atendente !== null && !$comOLara) {
            return self::HUMANO;
        }

        // Em shadow (piloto) o processo do Uber fica como sempre foi: só o
        // modo on passa a conversa para O Lara depois do aviso.
        if ($this->bot->mode() !== BotEngine::MODE_ON || $this->bot->botUserUuid() === null) {
            return self::ENCERRAR;
        }

        try {
            // A sessão antes do forward: a transferência que ele gera encontra
            // a conversa já adotada e não abre o menu por cima do aviso.
            $this->bot->exclusive($contactUuid, fn () => $this->bot->awaitReplyAfterNotice(
                $contactUuid,
                $comOLara && is_string($atual['uuid'] ?? null) ? $atual['uuid'] : null,
            ));

            if (!$comOLara) {
                $this->poli->encaminhar($contactUuid, userUuid: $this->bot->botUserUuid());
            }
        } catch (Throwable $e) {
            Log::warning('Poli: aviso do Uber não passou a conversa para O Lara — encerra depois do aviso', [
                'contact_uuid' => $contactUuid,
                'erro' => $e->getMessage(),
            ]);

            return self::ENCERRAR;
        }

        return self::LARA;
    }

    /** O rodapé do aviso para cada destino: ele diz ao sócio o que vem depois. */
    public static function footer(?string $plano): ?string
    {
        $rodape = match ($plano) {
            self::ENCERRAR => config('poli.messages.uber_arrival.rodape'),
            self::LARA => config('poli.messages.uber_arrival.rodape_continua'),
            default => null,
        };

        return filled($rodape) ? (string) $rodape : null;
    }
}
