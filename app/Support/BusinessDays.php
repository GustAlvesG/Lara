<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Contagem de dias úteis: segunda a sexta. Feriados não entram — o Lara não
 * tem calendário de feriados; num feriado, o prazo corre como dia útil.
 */
class BusinessDays
{
    /**
     * Fim do N-ésimo dia útil depois do dia de `$from`. Pedido na sexta com
     * N = 2 vence na terça às 23:59:59; pedido no sábado também (segunda é o 1º).
     */
    public static function deadline(Carbon $from, int $days): Carbon
    {
        $day = $from->copy()->startOfDay();
        $counted = 0;

        while ($counted < max(0, $days)) {
            $day->addDay();
            if (! $day->isWeekend()) {
                $counted++;
            }
        }

        return $day->endOfDay();
    }
}
