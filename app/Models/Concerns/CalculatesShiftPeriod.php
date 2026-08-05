<?php

namespace App\Models\Concerns;

use Illuminate\Support\Carbon;

/**
 * Período de um turno informado por horário de início e término.
 *
 * Quando o término é anterior (ou igual) ao início, entende-se que o turno
 * atravessou a meia-noite e terminou no dia seguinte — 22:00 → 02:00 são 4h,
 * não 20h negativas. Turnos são sempre de um dia: a virada nunca avança mais
 * de 24 horas.
 *
 * A regra vive aqui, e não em cada model, porque contrato de freelancer e
 * cachê de funcionário leem o relógio do mesmo jeito. O que difere entre os
 * dois é o PREÇO (bloco de 15 min lá, faixa de horas aqui), não o tempo.
 */
trait CalculatesShiftPeriod
{
    /** Uniformiza "19:00" e "19:00:00" para o formato H:i:s. */
    public static function normalizeTime(string $time): string
    {
        return Carbon::createFromFormat('Y-m-d', '2000-01-01')
            ->setTimeFromTimeString($time)
            ->format('H:i:s');
    }

    /** O turno atravessa a meia-noite? */
    public static function crossesMidnight(string $startTime, string $endTime): bool
    {
        return self::normalizeTime($endTime) <= self::normalizeTime($startTime);
    }

    /** Duração real do turno em minutos, já considerando a virada de dia. */
    public static function minutesBetween(string $startTime, string $endTime): int
    {
        // Data-base fixa para o cálculo não depender do dia em que roda.
        $base = Carbon::createFromFormat('Y-m-d', '2000-01-01')->startOfDay();

        $start = $base->copy()->setTimeFromTimeString($startTime);
        $end = $base->copy()->setTimeFromTimeString($endTime);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return $start->diffInMinutes($end);
    }
}
