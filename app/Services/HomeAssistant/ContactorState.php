<?php

namespace App\Services\HomeAssistant;

use App\Models\HomeAssistantOverride;
use App\Models\Schedule;

/**
 * Estado de um contator num instante, com o motivo — o painel precisa explicar
 * por que a luz está como está, não só dizer ligado/desligado.
 */
final class ContactorState
{
    /** Ação rápida (Ligar/Desligar agora) do painel ou do dashboard. */
    public const SOURCE_QUICK = 'quick';

    /** Agendamento cadastrado no painel. */
    public const SOURCE_OVERRIDE = 'override';

    /** Reserva confirmada de um espaço ligado ao contator. */
    public const SOURCE_RESERVATION = 'reservation';

    /** Nada manda ligar: sem reserva e sem agendamento. */
    public const SOURCE_IDLE = 'idle';

    public function __construct(
        public readonly bool $on,
        public readonly string $source,
        public readonly ?HomeAssistantOverride $override = null,
        public readonly ?Schedule $schedule = null,
        public readonly ?string $placeName = null,
    ) {
    }

    public function isManual(): bool
    {
        return $this->source === self::SOURCE_QUICK;
    }

    /** Motivo em uma linha, para o cartão do contator e o interruptor do dashboard. */
    public function reason(): string
    {
        return match ($this->source) {
            self::SOURCE_QUICK       => ($this->on ? 'Ligado' : 'Desligado') . ' manualmente até o fim do dia',
            self::SOURCE_OVERRIDE    => 'Agendamento “' . ($this->override?->name ?: 'sem nome') . '”',
            self::SOURCE_RESERVATION => 'Reserva até ' . $this->schedule?->end_schedule?->format('H:i')
                . ($this->placeName ? ' · ' . $this->placeName : ''),
            default                  => 'Sem reserva no momento',
        };
    }
}
