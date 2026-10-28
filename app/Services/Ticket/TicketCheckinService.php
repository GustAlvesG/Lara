<?php

namespace App\Services\Ticket;

use App\Exceptions\ConfirmationRequired;
use App\Exceptions\TicketException;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketCheckin;
use App\Models\Ticket\TicketEvent;
use App\Models\Ticket\TicketEventDay;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Support\Cpf;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Check-in dos ingressos: na tela do Lara (`ingressos.checkin`) e no Monitor
 * de Acesso da portaria (CompanyService), pelo código `ING-…` (o QR) ou pelo
 * CPF do titular.
 *
 * Uma entrada por ingresso por dia: o ingresso do evento sem dias e a diária
 * entram uma vez; o passaporte, uma vez em cada dia do evento. Cada entrada é
 * uma linha de `ticket_checkins` com índice único (ingresso, dia): dois
 * registros ao mesmo tempo não passam os dois.
 *
 * A entrada abre `tickets.checkin_hours_before` antes do início e fecha no fim
 * — do evento, ou do dia (diária: o dia dela; passaporte: o dia que estiver
 * aberto). Fora dela, o Lara pede confirmação e o monitor nega.
 *
 * Ingresso de lugar marcado (mesa, bistrô, camarote) não é entrada: lido pelo
 * código, aparece negado (`ticket_seat_only`) com o lugar, sem como forçar;
 * na busca por CPF ele nem aparece.
 */
class TicketCheckinService
{
    public const REASON_GRANTED = 'ticket_access_granted';
    public const REASON_USED = 'ticket_already_used';
    public const REASON_NOT_VALID = 'ticket_not_valid';
    public const REASON_OUTSIDE_WINDOW = 'ticket_outside_window';
    public const REASON_SEAT = 'ticket_seat_only';

    /** O texto de cada motivo, para a portaria e o histórico. */
    public const REASON_LABELS = [
        self::REASON_GRANTED => 'Ingresso válido',
        self::REASON_USED => 'Ingresso já utilizado',
        self::REASON_NOT_VALID => 'Ingresso não está válido (pendente, cancelado ou expirado)',
        self::REASON_OUTSIDE_WINDOW => 'Ingresso fora do horário de entrada do evento',
        self::REASON_SEAT => 'Ingresso de mesa/lugar: não é entrada',
    ];

    private const RELATIONS = ['event.days', 'type.day', 'lot', 'seat', 'checker', 'checkins'];

    /** O texto digitado (ou lido do QR) é um código de ingresso? */
    public static function looksLikeCode(?string $target): bool
    {
        return (bool) preg_match('/^\s*ING[\s-]?[A-Za-z2-9]{8}\s*$/i', (string) $target);
    }

    /**
     * Os ingressos que respondem ao alvo: pelo código, o ingresso daquele
     * código; pelo CPF, os pagos do titular em eventos com a entrada aberta
     * agora (ou só no `$event` escolhido). Pelo CPF, o que pode entrar agora
     * vem primeiro, e a diária de outro dia some quando há ingresso do dia.
     *
     * @return Collection<int, Ticket>
     */
    public function find(string $target, ?TicketEvent $event = null): Collection
    {
        $query = Ticket::with(self::RELATIONS)->whereIn('status', [...Ticket::SOLD, Ticket::STATUS_CANCELLED, Ticket::STATUS_PENDING, Ticket::STATUS_EXPIRED]);

        if (self::looksLikeCode($target)) {
            return $query->where('code', Ticket::normalizeCode($target))->get();
        }

        $cpf = Cpf::digits($target);
        if (! Cpf::isValid($cpf)) {
            return collect();
        }

        $tickets = $query->where('holder_cpf', $cpf)
            ->people()
            ->whereIn('status', Ticket::SOLD)
            ->when($event, fn ($q) => $q->where('ticket_event_id', $event->id))
            ->when(! $event, fn ($q) => $q->whereHas('event', fn ($e) => $e->checkinWindow()))
            ->orderByDesc('id')
            ->get();

        return $this->relevantFirst($tickets);
    }

    /**
     * Pode entrar agora? Não grava nada.
     *
     * @return array{allowed: bool, reason: string, message: string}
     */
    public function evaluate(Ticket $ticket, ?Carbon $at = null): array
    {
        $ticket->loadMissing(self::RELATIONS);

        if ($ticket->isSeat()) {
            return [
                'allowed' => false,
                'reason' => self::REASON_SEAT,
                'message' => 'Ingresso de lugar (' . $ticket->typeLabel() . ', responsável ' . $ticket->holder_name . '): não é entrada. Para entrar, cada pessoa usa o ingresso de entrada dela.',
            ];
        }

        [$day, $open] = $this->slot($ticket, $at);
        $passport = $ticket->type->validity() === TicketType::VALIDITY_PASSPORT;
        $entry = $passport && $day ? $ticket->checkins->firstWhere('day_key', $day->id) : null;

        $reason = match (true) {
            ! in_array($ticket->status, Ticket::SOLD, true) => self::REASON_NOT_VALID,
            $ticket->status === Ticket::STATUS_USED && ! $passport => self::REASON_USED,
            ! $open => self::REASON_OUTSIDE_WINDOW,
            $entry !== null => self::REASON_USED,
            default => self::REASON_GRANTED,
        };

        $message = match ($reason) {
            self::REASON_USED => $entry
                ? 'O passaporte já entrou hoje (' . $day->label() . ') às ' . $entry->checked_in_at->format('H:i') . '.'
                : 'Já entrou' . ($ticket->checked_in_at ? ' às ' . $ticket->checked_in_at->format('H:i') . ' de ' . $ticket->checked_in_at->format('d/m') : '') . '.',
            self::REASON_NOT_VALID => 'Ingresso ' . mb_strtolower(Ticket::STATUS_LABELS[$ticket->status] ?? $ticket->status) . '.',
            self::REASON_OUTSIDE_WINDOW => $this->windowMessage($ticket),
            default => $day && $ticket->event->hasDays() ? 'Pode entrar (' . $day->label() . ').' : 'Pode entrar.',
        };

        return ['allowed' => $reason === self::REASON_GRANTED, 'reason' => $reason, 'message' => $message];
    }

    /**
     * Registra a entrada. `$force` (só o Lara) deixa entrar fora da janela,
     * depois de confirmar; o monitor nunca passa `$force`.
     */
    public function checkIn(Ticket $ticket, ?User $user, string $source, bool $force = false): Ticket
    {
        $state = $this->evaluate($ticket);

        if ($state['reason'] === self::REASON_SEAT) {
            throw new TicketException(TicketException::SEAT_NOT_ENTRY, $state['message'], 409);
        }

        if ($state['reason'] === self::REASON_OUTSIDE_WINDOW) {
            if ($source !== Ticket::CHECKIN_LARA) {
                throw new TicketException(TicketException::OUTSIDE_WINDOW, $state['message'], 409);
            }
            ConfirmationRequired::unless($force, ['Fora do horário de entrada: ' . $state['message']]);
        } elseif (! $state['allowed']) {
            throw new TicketException(
                $state['reason'] === self::REASON_USED ? TicketException::ALREADY_USED : TicketException::TICKET_NOT_VALID,
                $state['message'],
                409,
            );
        }

        [$day] = $this->slot($ticket);
        $day ??= $this->nearestDay($ticket);
        $now = Carbon::now();

        try {
            DB::transaction(function () use ($ticket, $user, $source, $day, $now) {
                TicketCheckin::create([
                    'ticket_id' => $ticket->id,
                    'ticket_event_day_id' => $day?->id,
                    'day_key' => $day?->id ?? 0,
                    'checked_in_at' => $now,
                    'checked_in_by' => $user?->id,
                    'source' => $source,
                ]);

                $updated = Ticket::whereKey($ticket->id)
                    ->whereIn('status', Ticket::SOLD)
                    ->update([
                        'status' => Ticket::STATUS_USED,
                        'checked_in_at' => $now,
                        'checked_in_by' => $user?->id,
                        'checkin_source' => $source,
                    ]);

                if (! $updated) {
                    // Cancelado entre a consulta e a gravação.
                    throw new TicketException(TicketException::TICKET_NOT_VALID, $this->evaluate($ticket->fresh())['message'], 409);
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Outro registro passou na frente entre a consulta e a gravação.
            throw new TicketException(TicketException::ALREADY_USED, $this->evaluate($ticket->fresh())['message'], 409);
        }

        return $ticket->fresh(self::RELATIONS);
    }

    /**
     * A janela de entrada do ingresso, em texto: a do evento, a do dia da
     * diária ou os dias do passaporte.
     */
    public function windowLabel(Ticket $ticket): string
    {
        $ticket->loadMissing(self::RELATIONS);
        $range = fn ($opens, $ends) => $opens->format('d/m H:i') . ' a ' . $ends->format('d/m H:i');

        return match ($ticket->type->validity()) {
            TicketType::VALIDITY_DAY => $range($ticket->type->day->checkinOpensAt(), $ticket->type->day->ends_at),
            TicketType::VALIDITY_PASSPORT => $ticket->event->days->map(fn (TicketEventDay $d) => $range($d->checkinOpensAt(), $d->ends_at))->implode('; '),
            default => $range($ticket->event->checkinOpensAt(), $ticket->event->ends_at),
        };
    }

    /**
     * Quantos ingressos valem no dia (ou no evento sem dias) e quantos já
     * entraram nele.
     *
     * @return array{sold: int, used: int, day: ?TicketEventDay}
     */
    public function counts(TicketEvent $event, ?TicketEventDay $day = null): array
    {
        $sold = Ticket::where('tickets.ticket_event_id', $event->id)->people()->whereIn('tickets.status', Ticket::SOLD)
            ->when($day, fn ($q) => $q->whereHas('type', fn ($t) => $t->whereNull('ticket_event_day_id')->orWhere('ticket_event_day_id', $day->id)))
            ->count();

        $used = TicketCheckin::where('day_key', $day?->id ?? 0)
            ->whereHas('ticket', fn ($q) => $q->where('ticket_event_id', $event->id)->whereIn('status', Ticket::SOLD))
            ->count();

        return ['sold' => $sold, 'used' => $used, 'day' => $day];
    }

    /**
     * O dia que a tela de check-in mostra: o aberto agora, senão o próximo,
     * senão o último. Null no evento sem dias.
     */
    public function dayForCounts(TicketEvent $event): ?TicketEventDay
    {
        $event->loadMissing('days');

        return $event->dayAt()
            ?? $event->days->first(fn (TicketEventDay $d) => $d->ends_at->greaterThanOrEqualTo(now()))
            ?? $event->days->last();
    }

    /**
     * O dia em que o ingresso entraria em `$at` e se a entrada está aberta:
     * a diária só no dia dela; o passaporte no dia aberto agora; o ingresso
     * do evento sem dias, na janela do evento (dia null).
     *
     * @return array{0: ?TicketEventDay, 1: bool}
     */
    private function slot(Ticket $ticket, ?Carbon $at = null): array
    {
        $event = $ticket->event;
        $live = ! in_array($event->status, [TicketEvent::STATUS_CANCELLED, TicketEvent::STATUS_DRAFT], true);

        return match ($ticket->type->validity()) {
            TicketType::VALIDITY_DAY => [$ticket->type->day, $live && $ticket->type->day->checkinOpen($at)],
            TicketType::VALIDITY_PASSPORT => [$day = $event->dayAt($at), $live && $day !== null],
            default => [null, $event->checkinOpen($at)],
        };
    }

    /** Entrada forçada do passaporte fora de qualquer dia: conta no dia mais próximo. */
    private function nearestDay(Ticket $ticket): ?TicketEventDay
    {
        if ($ticket->type->validity() !== TicketType::VALIDITY_PASSPORT) {
            return null;
        }

        return $ticket->event->days->sortBy(fn (TicketEventDay $d) => abs($d->starts_at->diffInSeconds(now(), false)))->first();
    }

    private function windowMessage(Ticket $ticket): string
    {
        return match ($ticket->type->validity()) {
            TicketType::VALIDITY_DAY => 'Diária de ' . $ticket->type->day->label() . ': a entrada é de ' . $this->windowLabel($ticket) . '.',
            TicketType::VALIDITY_PASSPORT => 'Passaporte: a entrada é nos dias do evento (' . $this->windowLabel($ticket) . ').',
            default => 'A entrada do evento é de ' . $this->windowLabel($ticket) . '.',
        };
    }

    /**
     * Pela busca por CPF: quem pode entrar agora primeiro, depois o já usado,
     * por último o fora da janela — e, no mesmo evento, só fica o mais
     * relevante (a diária de ontem, já usada, não aparece ao lado da de hoje).
     *
     * @param  Collection<int, Ticket>  $tickets
     * @return Collection<int, Ticket>
     */
    private function relevantFirst(Collection $tickets): Collection
    {
        $ranked = $tickets->map(function (Ticket $ticket) {
            $reason = $this->evaluate($ticket)['reason'];

            return [$ticket, match ($reason) {
                self::REASON_GRANTED => 0,
                self::REASON_OUTSIDE_WINDOW => 2,
                default => 1,
            }];
        });

        return $ranked
            ->reject(fn (array $row) => $ranked->contains(fn (array $other) => $other[1] < $row[1] && $other[0]->ticket_event_id === $row[0]->ticket_event_id))
            ->sortBy(fn (array $row) => $row[1])
            ->map(fn (array $row) => $row[0])
            ->values();
    }
}
