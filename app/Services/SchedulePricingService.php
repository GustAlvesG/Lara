<?php

namespace App\Services;

use App\Console\Commands\ExpirePendingSchedules;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Preço e janela de venda de um horário que já começou.
 *
 * Um horário continua à venda depois de iniciado: quem entra na quadra às
 * 20:30 de um horário de 20:00 às 21:00 paga metade. O valor é proporcional
 * ao tempo que ainda resta, minuto a minuto, sobre o preço cheio do espaço.
 *
 * A venda não vai até o último minuto: fecha em `fim - HOLD_MINUTES` porque
 * a reserva nasce pendente e só é liberada de novo quando o hold expira —
 * vender às 20:55 criaria um pendente que só expiraria às 21:05, depois do
 * horário já ter acabado. Por isso o corte é derivado da constante do hold,
 * e não de um horário fixo: se o hold mudar, a janela de venda acompanha.
 */
class SchedulePricingService
{
    /**
     * Fração do preço cheio que o horário vale no instante informado.
     *
     * 1.0 enquanto não começou, 0.0 depois de terminado e, no meio,
     * minutos restantes / duração total.
     */
    public function factor($start, $end, $now = null): float
    {
        [$start, $end, $now] = $this->normalize($start, $end, $now);

        $total = $this->minutesBetween($start, $end);

        // Horário sem duração (dados inconsistentes) não vira desconto: cobra cheio.
        if ($total <= 0) {
            return 1.0;
        }

        if ($now->lte($start)) {
            return 1.0;
        }

        if ($now->gte($end)) {
            return 0.0;
        }

        return $this->minutesBetween($now, $end) / $total;
    }

    /**
     * Preço a cobrar pelo horário no instante informado, em reais.
     */
    public function price($basePrice, $start, $end, $now = null): float
    {
        return round((float) $basePrice * $this->factor($start, $end, $now), 2);
    }

    /**
     * Minutos que ainda restam do horário (0 se já terminou, duração cheia se
     * ainda não começou).
     */
    public function remainingMinutes($start, $end, $now = null): int
    {
        [$start, $end, $now] = $this->normalize($start, $end, $now);

        if ($now->lte($start)) {
            return $this->minutesBetween($start, $end);
        }

        if ($now->gte($end)) {
            return 0;
        }

        return $this->minutesBetween($now, $end);
    }

    /**
     * Último instante em que o horário ainda pode ser vendido.
     *
     * É o fim menos o hold do pagamento pendente, para que a reserva pendente
     * nunca sobreviva ao próprio horário.
     */
    public function bookingDeadline($end): Carbon
    {
        return $this->toCarbon($end)->subMinutes(ExpirePendingSchedules::HOLD_MINUTES);
    }

    /**
     * O horário ainda pode ser reservado neste instante?
     *
     * Antes de começar, sempre pode — a janela do hold só restringe o horário
     * em andamento (e um horário mais curto que o hold nunca chega a ser
     * vendido depois de iniciado).
     */
    public function isBookable($start, $end, $now = null): bool
    {
        [$start, $end, $now] = $this->normalize($start, $end, $now);

        if ($now->lte($start)) {
            return true;
        }

        return $now->lt($this->bookingDeadline($end));
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: Carbon}
     */
    private function normalize($start, $end, $now): array
    {
        return [
            $this->toCarbon($start),
            $this->toCarbon($end),
            $this->toCarbon($now ?? Carbon::now()),
        ];
    }

    /**
     * Segundos são ruído aqui: às 20:30:47 o sócio contratou meia hora, não
     * 49,98% dela. Truncar ao minuto é o que faz o exemplo do balcão fechar.
     */
    private function toCarbon($value): Carbon
    {
        $date = $value instanceof CarbonInterface
            ? $value->copy()
            : Carbon::parse($value);

        return Carbon::instance($date)->startOfMinute();
    }

    private function minutesBetween(Carbon $from, Carbon $to): int
    {
        return (int) round(abs($from->diffInMinutes($to)));
    }
}
