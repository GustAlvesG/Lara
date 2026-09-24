<?php

namespace App\Services\HomeAssistant;

use Carbon\Carbon;

/**
 * A faixa de um dia em que o sócio pode acender a luz sozinho.
 *
 * Existe como objeto, e não como par de Carbon solto, porque a tela precisa
 * saber *por que* a janela é aquela: "sábado" e "feriado de Natal" abrem o
 * mesmo horário e merecem frases diferentes.
 *
 * Sempre dentro de um único dia. A janela que virasse a meia-noite não teria
 * como ser honrada: o comando manual que a executa é truncado na virada do dia
 * por ManualCommandService::expiryFor().
 */
final class LightingWindow
{
    /** Regra semanal de config/home_assistant.php (sábado, domingo). */
    public const SOURCE_WEEKLY = 'weekly';

    /** Data liberada à mão no painel (feriado, ponto facultativo). */
    public const SOURCE_DATE = 'date';

    public function __construct(
        public readonly Carbon $start,
        public readonly Carbon $end,
        public readonly string $source,
        public readonly ?string $reason = null,
    ) {
    }

    public function contains(Carbon $moment): bool
    {
        // Meio-aberta [início, fim), como as faixas dos agendamentos: às 23:00
        // em ponto a janela de sábado já fechou.
        return $moment->greaterThanOrEqualTo($this->start) && $moment->lessThan($this->end);
    }

    /** Minutos que ainda restam da janela — zero depois do fim. */
    public function minutesLeft(Carbon $moment): int
    {
        if ($moment->greaterThanOrEqualTo($this->end)) {
            return 0;
        }

        $from = $moment->greaterThan($this->start) ? $moment : $this->start;

        return (int) floor($from->diffInSeconds($this->end) / 60);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'date'   => $this->start->toDateString(),
            'start'  => $this->start->format('H:i'),
            'end'    => $this->end->format('H:i'),
            'starts_at' => $this->start->toIso8601String(),
            'ends_at'   => $this->end->toIso8601String(),
            'source' => $this->source,
            'reason' => $this->reason,
        ];
    }
}
