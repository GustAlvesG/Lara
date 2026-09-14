<?php

namespace App\Services\HomeAssistant;

use App\Models\Contactor;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Única fonte da decisão "este contator deve estar ligado agora?".
 *
 * Usada pelo endpoint que o Home Assistant consulta, pelo painel e pelo
 * interruptor do dashboard — antes cada um calculava por conta própria e o
 * dashboard mostrava "desligado" para quadras acesas por reserva.
 *
 * Ordem de precedência, da mais forte para a mais fraca:
 *   1. ação rápida (prioridade 1000);
 *   2. agendamentos, por prioridade — só os que se pronunciam no instante;
 *   3. reserva confirmada em algum espaço do contator.
 *
 * resolve() e timeline() não consultam o banco: recebem as reservas já
 * carregadas por schedulesBetween(), o que permite simular o dia inteiro.
 */
class ContactorStateResolver
{
    /** A luz acende antes do início e apaga depois do fim da reserva. */
    public const MARGIN_MINUTES = 5;

    /** Contatores com o que resolve() precisa já carregado. */
    public function contactors(): Collection
    {
        return Contactor::with([
            'places',
            'overrides' => fn ($q) => $q->with(['weekdays', 'windows']),
        ])->orderBy('name')->get();
    }

    /**
     * Reservas confirmadas que tocam o intervalo (com a margem), agrupadas por place_id.
     *
     * Filtra por sobreposição de horário, não pela data de início: uma reserva
     * das 23:00 às 00:30 continua valendo depois da meia-noite.
     */
    public function schedulesBetween(Carbon $from, Carbon $to): Collection
    {
        return Schedule::where('status_id', 1)
            ->where('start_schedule', '<=', $to->copy()->addMinutes(self::MARGIN_MINUTES))
            ->where('end_schedule', '>=', $from->copy()->subMinutes(self::MARGIN_MINUTES))
            ->orderBy('start_schedule')
            ->get()
            ->groupBy('place_id');
    }

    /** Estado de cada contator agora, indexado pelo id do contator. */
    public function resolveAll(Collection $contactors, ?Carbon $moment = null): Collection
    {
        $moment = $moment ?: Carbon::now();
        $schedules = $this->schedulesBetween($moment, $moment);

        return $contactors->mapWithKeys(
            fn (Contactor $contactor) => [$contactor->id => $this->resolve($contactor, $moment, $schedules)]
        );
    }

    public function resolve(Contactor $contactor, Carbon $moment, Collection $schedulesByPlace): ContactorState
    {
        $override = $contactor->effectiveOverride($moment);

        if ($override) {
            return new ContactorState(
                on: $override->stateAt($moment),
                source: $override->is_quick ? ContactorState::SOURCE_QUICK : ContactorState::SOURCE_OVERRIDE,
                override: $override,
            );
        }

        foreach ($contactor->places as $place) {
            foreach ($schedulesByPlace->get($place->id, []) as $schedule) {
                $lightsOn  = $schedule->start_schedule->copy()->subMinutes(self::MARGIN_MINUTES);
                $lightsOff = $schedule->end_schedule->copy()->addMinutes(self::MARGIN_MINUTES);

                // Meio-aberto, como as faixas dos agendamentos: às 20:05 em ponto já apagou
                if ($moment->greaterThanOrEqualTo($lightsOn) && $moment->lessThan($lightsOff)) {
                    return new ContactorState(
                        on: true,
                        source: ContactorState::SOURCE_RESERVATION,
                        schedule: $schedule,
                        placeName: $place->name,
                    );
                }
            }
        }

        return new ContactorState(on: false, source: ContactorState::SOURCE_IDLE);
    }

    /**
     * Simulação do dia com as regras atuais, em trechos contínuos de mesmo estado e motivo.
     *
     * @return list<array{start: int, end: int, on: bool, source: string, label: string}>
     *         start/end em minutos desde 00:00
     */
    public function timeline(Contactor $contactor, Carbon $day, Collection $schedulesByPlace, int $stepMinutes = 5): array
    {
        $midnight = $day->copy()->startOfDay();
        $segments = [];

        for ($minute = 0; $minute < 1440; $minute += $stepMinutes) {
            $state = $this->resolve($contactor, $midnight->copy()->addMinutes($minute), $schedulesByPlace);
            $label = $state->source === ContactorState::SOURCE_RESERVATION
                ? 'Reserva' . ($state->placeName ? ' · ' . $state->placeName : '')
                : $state->reason();

            $last = array_key_last($segments);

            if ($last !== null
                && $segments[$last]['on'] === $state->on
                && $segments[$last]['source'] === $state->source
                && $segments[$last]['label'] === $label) {
                $segments[$last]['end'] = $minute + $stepMinutes;
                continue;
            }

            $segments[] = [
                'start'  => $minute,
                'end'    => $minute + $stepMinutes,
                'on'     => $state->on,
                'source' => $state->source,
                'label'  => $label,
            ];
        }

        return $segments;
    }
}
