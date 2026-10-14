<?php

namespace App\Services\Social;

use App\Exceptions\SocialBookingException;
use App\Models\Place;
use App\Models\PlaceGroup;
use App\Models\Schedule;
use App\Models\ScheduleRules;
use App\Models\Social\SocialBooking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Que dias um espaço social está livre, e quanto o sócio ainda pode reservar.
 *
 * A unidade é o dia inteiro. Um dia está:
 *   - `out_of_window` — passado, fora da antecedência do grupo ou num dia da
 *     semana em que o grupo não abre;
 *   - `blocked` — uma regra de exclusão do espaço vale para o dia (qualquer
 *     faixa de horário: o evento é o dia todo);
 *   - `reserved` — há ocupação ativa (status fora de 0/4) no espaço ou num
 *     espaço que conflita com ele (Place::conflictingPlaceIds);
 *   - `available` — nenhum dos anteriores.
 *
 * Antecedência do espaço social: a data precisa estar entre hoje +
 * `minimum_antecedence` e hoje + `maximum_antecedence` dias; máximo 0 é "sem
 * limite". (A quadra lê os mesmos campos de outro jeito — ver
 * ScheduleRulesService::calcAntecedenceDate — porque lá o 0 vende só o dia.)
 */
class SocialAvailabilityService
{
    public const AVAILABLE = 'available';
    public const RESERVED = 'reserved';
    public const BLOCKED = 'blocked';
    public const OUT_OF_WINDOW = 'out_of_window';

    /**
     * Calendário do mês.
     *
     * @return array<int, array{date: string, status: string}>
     */
    public function month(Place $place, string $month, ?Carbon $now = null): array
    {
        $first = Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
        $last = $first->copy()->endOfMonth()->startOfDay();

        $occupied = $this->occupiedDates($place, $first, $last);
        $rules = $this->excludeRules($place);

        $days = [];
        for ($day = $first->copy(); $day->lte($last); $day->addDay()) {
            $days[] = [
                'date' => $day->toDateString(),
                'status' => $this->resolve($place, $day, $occupied, $rules, $now),
            ];
        }

        return $days;
    }

    /**
     * Situação de um dia só. `$ignoreScheduleId` tira a própria ocupação da
     * conta; `$checkWindow = false` olha só ocupação e regra (a aprovação de
     * uma pré-reserva antiga não reprova pela antecedência de hoje).
     */
    public function dayStatus(Place $place, $date, ?Carbon $now = null, ?int $ignoreScheduleId = null, bool $checkWindow = true): string
    {
        $day = Carbon::parse($date)->startOfDay();

        return $this->resolve(
            $place,
            $day,
            $this->occupiedDates($place, $day, $day, $ignoreScheduleId),
            $this->excludeRules($place),
            $now,
            $checkWindow
        );
    }

    /**
     * Lança a recusa correspondente se o dia não estiver livre.
     *
     * @throws SocialBookingException
     */
    public function assertAvailable(Place $place, $date, ?Carbon $now = null, ?int $ignoreScheduleId = null, bool $checkWindow = true): void
    {
        $status = $this->dayStatus($place, $date, $now, $ignoreScheduleId, $checkWindow);

        if ($status === self::AVAILABLE) {
            return;
        }

        [$reason, $message] = match ($status) {
            self::RESERVED => [SocialBookingException::DATE_UNAVAILABLE, 'Esta data já está reservada.'],
            self::BLOCKED => [SocialBookingException::DATE_BLOCKED, 'O espaço está bloqueado nesta data.'],
            default => [SocialBookingException::OUT_OF_WINDOW, 'Esta data não está aberta para reserva.'],
        };

        throw new SocialBookingException($reason, $message);
    }

    /**
     * O que o sócio ainda pode reservar no grupo. Cada limite é opcional; o que
     * o grupo não define volta como null.
     *
     * @return array{active: ?array{limit: int, used: int, remaining: int}, window: ?array{limit: int, days: int}}
     */
    public function limits(PlaceGroup $group, int $memberId, ?Carbon $now = null): array
    {
        $today = ($now ?? Carbon::now())->copy()->startOfDay();

        $active = null;
        if ($group->social_active_limit) {
            $used = $this->memberBookings($group, $memberId)
                ->where('event_date', '>=', $today->toDateString())
                ->count();

            $active = [
                'limit' => (int) $group->social_active_limit,
                'used' => $used,
                'remaining' => max(0, (int) $group->social_active_limit - $used),
            ];
        }

        $window = null;
        if ($group->social_limit_count && $group->social_limit_days) {
            $window = [
                'limit' => (int) $group->social_limit_count,
                'days' => (int) $group->social_limit_days,
            ];
        }

        return ['active' => $active, 'window' => $window];
    }

    /**
     * Confere os dois limites do grupo para uma nova reserva na data.
     *
     * "N a cada X dias": em nenhuma janela de X dias seguidos que contenha a
     * data nova o sócio pode passar de N reservas. Reserva já realizada conta
     * (é o que impede emendar uma festa na outra); recusada, cancelada e
     * expirada não.
     *
     * @throws SocialBookingException
     */
    public function assertWithinLimits(PlaceGroup $group, int $memberId, $date, ?Carbon $now = null): void
    {
        $day = Carbon::parse($date)->startOfDay();
        $limits = $this->limits($group, $memberId, $now);

        if ($limits['active'] && $limits['active']['remaining'] <= 0) {
            throw new SocialBookingException(
                SocialBookingException::LIMIT_REACHED,
                "Você já tem {$limits['active']['used']} reserva(s) ativa(s) neste grupo; o limite é {$limits['active']['limit']}.",
                422,
                ['limit' => 'active', 'max' => $limits['active']['limit']]
            );
        }

        if ($limits['window']) {
            $n = $limits['window']['limit'];
            $x = $limits['window']['days'];

            $dates = $this->memberBookings($group, $memberId)
                ->whereBetween('event_date', [
                    $day->copy()->subDays($x - 1)->toDateString(),
                    $day->copy()->addDays($x - 1)->toDateString(),
                ])
                ->pluck('event_date')
                ->map(fn ($d) => Carbon::parse($d)->startOfDay());

            for ($start = $day->copy()->subDays($x - 1); $start->lte($day); $start->addDay()) {
                $end = $start->copy()->addDays($x - 1);
                $inWindow = $dates->filter(fn (Carbon $d) => $d->betweenIncluded($start, $end))->count();

                if ($inWindow + 1 > $n) {
                    throw new SocialBookingException(
                        SocialBookingException::LIMIT_REACHED,
                        "O limite deste grupo é de {$n} reserva(s) a cada {$x} dias.",
                        422,
                        ['limit' => 'window', 'max' => $n, 'days' => $x]
                    );
                }
            }
        }
    }

    private function memberBookings(PlaceGroup $group, int $memberId)
    {
        return SocialBooking::query()
            ->active()
            ->where('member_id', $memberId)
            ->whereIn('place_id', $group->places()->withoutGlobalScopes()->pluck('id'));
    }

    /**
     * @param  Collection<int, string>  $occupied  datas Y-m-d ocupadas
     * @param  Collection<int, ScheduleRules>  $rules
     */
    private function resolve(Place $place, Carbon $day, Collection $occupied, Collection $rules, ?Carbon $now, bool $checkWindow = true): string
    {
        if ($checkWindow && ! $this->inWindow($place->group, $day, $now)) {
            return self::OUT_OF_WINDOW;
        }

        if ($this->blockedByRule($rules, $day)) {
            return self::BLOCKED;
        }

        if ($occupied->contains($day->toDateString())) {
            return self::RESERVED;
        }

        return self::AVAILABLE;
    }

    private function inWindow(PlaceGroup $group, Carbon $day, ?Carbon $now): bool
    {
        $today = ($now ?? Carbon::now())->copy()->startOfDay();

        if ($day->lt($today->copy()->addDays((int) $group->minimum_antecedence))) {
            return false;
        }

        $max = (int) $group->maximum_antecedence;
        if ($max > 0 && $day->gt($today->copy()->addDays($max))) {
            return false;
        }

        // weekday_id 1 = domingo … 7 = sábado (mesma conta da grade das quadras).
        $weekdays = $group->weekdays;
        if ($weekdays && $weekdays->isNotEmpty() && ! $weekdays->contains('id', $day->dayOfWeek + 1)) {
            return false;
        }

        return true;
    }

    /** @param  Collection<int, ScheduleRules>  $rules */
    private function blockedByRule(Collection $rules, Carbon $day): bool
    {
        $date = $day->toDateString();
        $weekday = $day->dayOfWeek + 1;

        return $rules->contains(function (ScheduleRules $rule) use ($date, $weekday) {
            if ($rule->start_date && $date < Carbon::parse($rule->start_date)->toDateString()) {
                return false;
            }
            if ($rule->end_date && $date > Carbon::parse($rule->end_date)->toDateString()) {
                return false;
            }

            return ! $rule->weekdays || $rule->weekdays->isEmpty() || $rule->weekdays->contains('id', $weekday);
        });
    }

    /** @return Collection<int, ScheduleRules> */
    private function excludeRules(Place $place): Collection
    {
        return ScheduleRules::where('type', 'exclude')
            ->whereHas('places', fn ($q) => $q->where('place_id', $place->id))
            ->get();
    }

    /**
     * Datas (Y-m-d) do intervalo com ocupação ativa no espaço ou em quem
     * conflita com ele.
     *
     * @return Collection<int, string>
     */
    private function occupiedDates(Place $place, Carbon $from, Carbon $to, ?int $ignoreScheduleId = null): Collection
    {
        $placeIds = array_merge([$place->id], Place::conflictingPlaceIds($place->id));

        return Schedule::withoutGlobalScopes()
            ->whereIn('place_id', $placeIds)
            ->whereNotIn('status_id', [0, 4])
            ->where('start_schedule', '<=', $to->copy()->endOfDay())
            ->where('end_schedule', '>=', $from->copy()->startOfDay())
            ->when($ignoreScheduleId, fn ($q) => $q->where('id', '!=', $ignoreScheduleId))
            ->get(['start_schedule', 'end_schedule'])
            ->flatMap(function (Schedule $schedule) {
                $dates = [];
                $day = $schedule->start_schedule->copy()->startOfDay();
                // Fim exclusivo: o horário das 23:00 às 00:00 não ocupa o dia seguinte.
                $end = $schedule->end_schedule->copy()->subSecond();
                for (; $day->lte($end); $day->addDay()) {
                    $dates[] = $day->toDateString();
                }

                return $dates;
            })
            ->unique()
            ->values();
    }
}
