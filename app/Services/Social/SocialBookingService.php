<?php

namespace App\Services\Social;

use App\Exceptions\SocialBookingException;
use App\Models\Company\OneOffAccess;
use App\Models\Place;
use App\Models\Schedule;
use App\Models\SchedulePayment;
use App\Models\Social\SocialBeverage;
use App\Models\Social\SocialBooking;
use App\Models\Social\SocialBookingGuest;
use App\Services\RedeItauService;
use App\Support\BusinessDays;
use App\Support\Cpf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fluxo da reserva de espaço social, do pedido do sócio ao fim.
 *
 *   pré-reserva (requested) ──contrato ok + aprovação──► aguardando pagamento
 *        │                                                   │        │
 *        └─► recusada / cancelada            prazo vencido ◄─┘        └─► confirmada (pago)
 *                                              (expired)
 *
 * - A data fica presa desde a pré-reserva: a ocupação é um Schedule do dia
 *   inteiro, criado já na pré-reserva com status 3.
 * - O valor é o preço do espaço + convidados extras + bebidas − desconto.
 *   Preço do espaço, do convidado extra e das bebidas ficam congelados na
 *   pré-reserva; o desconto só é dado na aprovação, em reais e com motivo.
 * - Convidados: o espaço inclui `max_guests` não sócios no preço e vende até
 *   `extra_guests` a mais, a `extra_guest_price` cada. O sócio escolhe os
 *   extras na pré-reserva; a lista nominal (nome + CPF) vai até a soma.
 *   `expected_guests` (sócios + não sócios) não passa da `capacity` do espaço.
 * - O pagamento é o mesmo endpoint das quadras (`POST /api/schedule/payment`),
 *   que pergunta aqui se pode (assertPayable) e avisa quando pagou (markPaid).
 *   Pago fora da plataforma, a equipe dá baixa manual no painel
 *   (ManualPaymentService), que também chama markPaid.
 * - No balcão, a reserva pode ser fechada de uma vez — pré-reserva, contrato,
 *   aprovação e, se já pago, baixa (SocialCounterBookingService).
 * - Convidados não sócios viram liberações pontuais na data do evento quando a
 *   reserva é confirmada (syncGuestAccesses).
 * - Mesas e cadeiras: a base do espaço é copiada na pré-reserva; mobiliário do
 *   catálogo (mesa, cadeira, jogo completo) soma a ela.
 * - Horário: espaço com `event_duration_hours` pede o início; o fim é início +
 *   duração. A ocupação continua sendo o dia inteiro.
 * - Contato: a equipe tem N dias úteis (`contact_due_at`) para falar com o
 *   sócio; pré-reserva do app avisa a equipe no sino (SocialStaffNotifier).
 *
 * Toda recusa sai como SocialBookingException, com `reason` estável para o app.
 */
class SocialBookingService
{
    public function __construct(
        private SocialAvailabilityService $availability,
        private SocialBookingNotifier $notifier,
        private RedeItauService $rede,
        private SocialStaffNotifier $staff,
    ) {
    }

    /**
     * Cria a pré-reserva.
     *
     * Lançada no balcão (`$userId`), o contato já aconteceu — o sócio está ali.
     * Vinda do app, avisa a equipe e começa a contar o prazo de contato.
     *
     * `$notify = false` não manda o e-mail ao sócio: é a reserva fechada de uma
     * vez no balcão (SocialCounterBookingService), que manda um só no fim.
     *
     * @param  array{date: string, start_time?: ?string, event_type: string, expected_guests?: ?int, extra_guests?: ?int, notes?: ?string, items?: array, guests?: array}  $data
     *
     * @throws SocialBookingException
     */
    public function request(Place $place, int $memberId, array $data, ?int $userId = null, ?Carbon $now = null, bool $notify = true): SocialBooking
    {
        $now ??= Carbon::now();
        $date = Carbon::parse($data['date'])->startOfDay();

        $booking = DB::transaction(function () use ($place, $memberId, $data, $userId, $now, $date) {
            // Trava a linha do espaço: duas pré-reservas da mesma data chegando
            // juntas passam uma de cada vez pela checagem de disponibilidade.
            $place = Place::whereKey($place->id)->lockForUpdate()->first();
            $this->assertSocialPlace($place);

            $this->availability->assertAvailable($place, $date, $now);
            $this->availability->assertWithinLimits($place->group, $memberId, $date, $now);

            $extraGuests = $this->extraGuests($place, $data['extra_guests'] ?? 0);
            $this->assertCapacity($place, $data['expected_guests'] ?? null);
            $times = $this->resolveTimes($place, $data['start_time'] ?? null);
            $items = $this->buildItems($data['items'] ?? []);
            $guests = $this->normalizeGuests($data['guests'] ?? [], (int) $place->max_guests + $extraGuests);

            $basePrice = round((float) $place->price, 2);
            $extraGuestPrice = round((float) $place->extra_guest_price, 2);
            $extrasTotal = round($extraGuests * $extraGuestPrice, 2);
            $itemsTotal = round($items->sum('subtotal'), 2);
            $total = round($basePrice + $extrasTotal + $itemsTotal, 2);

            $schedule = Schedule::create([
                'place_id' => $place->id,
                'member_id' => $memberId,
                'start_schedule' => $date->copy()->startOfDay(),
                'end_schedule' => $date->copy()->endOfDay(),
                'status_id' => SocialBooking::SCHEDULE_STATUS[SocialBooking::STAGE_REQUESTED],
                'price' => $total,
                'description' => 'Reserva de espaço social',
                'created_by_user' => $userId,
            ]);

            $booking = SocialBooking::create([
                'schedule_id' => $schedule->id,
                'place_id' => $place->id,
                'member_id' => $memberId,
                'event_date' => $date->toDateString(),
                'start_time' => $times['start_time'],
                'end_time' => $times['end_time'],
                'duration_hours' => $times['duration_hours'],
                'stage' => SocialBooking::STAGE_REQUESTED,
                'contact_due_at' => BusinessDays::deadline($now, (int) config('social_bookings.contact_business_days', 2)),
                'contacted_at' => $userId ? $now : null,
                'contacted_by' => $userId,
                'base_tables' => $place->base_tables,
                'base_chairs' => $place->base_chairs,
                'event_type' => trim($data['event_type']),
                'expected_guests' => $data['expected_guests'] ?? null,
                'notes' => $data['notes'] ?? null,
                'base_price' => $basePrice,
                'extra_guests' => $extraGuests,
                'extra_guest_price' => $extraGuestPrice,
                'extras_total' => $extrasTotal,
                'items_total' => $itemsTotal,
                'discount' => 0,
                'total' => $total,
                'created_by_user' => $userId,
            ]);

            $booking->items()->createMany($items->all());
            $booking->guests()->createMany($guests->all());

            return $booking;
        });

        if ($notify) {
            $this->notifier->notify($booking, SocialBookingNotifier::REQUESTED);
        }

        if (! $userId) {
            $this->staff->requested($booking);
        }

        return $booking->fresh(['items', 'guests', 'place.group']);
    }

    /**
     * A equipe registra que falou com o sócio — encerra o prazo de contato.
     * Contrato ok, aprovação e recusa também contam como contato.
     *
     * @throws SocialBookingException
     */
    public function registerContact(SocialBooking $booking, int $userId, ?string $notes = null): SocialBooking
    {
        $this->assertStage($booking, SocialBooking::ACTIVE_STAGES, 'Esta reserva já está encerrada.');

        $booking->forceFill([
            'contacted_at' => $booking->contacted_at ?? Carbon::now(),
            'contacted_by' => $booking->contacted_by ?? $userId,
            'contact_notes' => trim((string) $notes) !== '' ? trim($notes) : $booking->contact_notes,
        ])->save();

        return $booking;
    }

    /** Primeiro contato, se ainda não houve: qualquer decisão da equipe passa por falar com o sócio. */
    private function touchContact(SocialBooking $booking, int $userId): void
    {
        if ($booking->contacted_at === null) {
            $booking->contacted_at = Carbon::now();
            $booking->contacted_by = $userId;
        }
    }

    /**
     * Edita a pré-reserva — tudo, menos data e espaço (para isso, desistir e
     * pedir de novo: a disponibilidade é de outro dia/espaço). Vale pela
     * equipe (painel) e pelo sócio (app), enquanto ela está em análise e
     * antes do "contrato ok": o contrato fecha o combinado, e daí em diante
     * não há mais trocas (`contract_closed`).
     *
     * Campo ausente fica como está. `items` e `guests`, quando vêm, são a
     * lista inteira (substituem a anterior): bebida que some sai, bebida nova
     * entra pelo preço do catálogo, bebida que já estava mantém o preço
     * contratado.
     *
     * @param  array{start_time?: ?string, event_type?: string, expected_guests?: ?int, extra_guests?: ?int, notes?: ?string, items?: array, guests?: array}  $data
     *
     * @throws SocialBookingException
     */
    public function updateDetails(SocialBooking $booking, array $data, ?int $userId = null): SocialBooking
    {
        $this->assertStage($booking, [SocialBooking::STAGE_REQUESTED], 'Só a pré-reserva em análise pode ser editada.');

        if ($booking->contractOk()) {
            throw new SocialBookingException(
                SocialBookingException::CONTRACT_CLOSED,
                'O contrato já foi fechado: a pré-reserva não aceita mais alterações.',
                409
            );
        }

        return DB::transaction(function () use ($booking, $data, $userId) {
            $place = $booking->place;
            $extraGuests = array_key_exists('extra_guests', $data)
                ? $this->extraGuests($place, $data['extra_guests'] ?? 0)
                : (int) $booking->extra_guests;
            $expected = array_key_exists('expected_guests', $data) ? $data['expected_guests'] : $booking->expected_guests;
            $this->assertCapacity($place, $expected);

            $limit = (int) $place->max_guests + $extraGuests;

            if (array_key_exists('guests', $data)) {
                $this->applyGuests($booking, $this->normalizeGuests($data['guests'] ?? [], $limit), $userId);
            } elseif ($booking->guests()->count() > $limit) {
                throw new SocialBookingException(
                    SocialBookingException::OVER_CAPACITY,
                    'A lista de convidados já passa desse número. Tire convidados antes de reduzir os extras.',
                    422,
                    ['guest_limit' => $limit]
                );
            }

            if (array_key_exists('items', $data)) {
                $items = $this->buildItems($data['items'] ?? [], $booking);
                $booking->items()->delete();
                $booking->items()->createMany($items->all());
                $booking->items_total = round($items->sum('subtotal'), 2);
            }

            if (array_key_exists('start_time', $data) || ($place->event_duration_hours && ! $booking->start_time)) {
                $times = $this->resolveTimes($place, $data['start_time'] ?? $booking->start_time);
                $booking->start_time = $times['start_time'];
                $booking->end_time = $times['end_time'];
                $booking->duration_hours = $times['duration_hours'];
            }

            if (array_key_exists('event_type', $data) && trim((string) $data['event_type']) !== '') {
                $booking->event_type = trim($data['event_type']);
            }
            if (array_key_exists('notes', $data)) {
                $booking->notes = $data['notes'];
            }

            $booking->expected_guests = $expected;
            $booking->extra_guests = $extraGuests;
            $booking->extras_total = round($extraGuests * (float) $booking->extra_guest_price, 2);
            $booking->total = $this->totalOf($booking);
            $booking->save();

            $booking->schedule->update(['price' => $booking->total]);

            return $booking->fresh(['items', 'guests', 'place.group']);
        });
    }

    /** Contrato feito e assinado fora do Lara; aqui só o registro (e o anexo, se houver). */
    public function markContract(SocialBooking $booking, int $userId, ?string $file = null): SocialBooking
    {
        $this->assertStage($booking, [SocialBooking::STAGE_REQUESTED, SocialBooking::STAGE_AWAITING_PAYMENT, SocialBooking::STAGE_CONFIRMED], 'Reserva encerrada.');

        $this->touchContact($booking, $userId);
        $booking->forceFill([
            'contract_ok_at' => Carbon::now(),
            'contract_ok_by' => $userId,
            'contract_file' => $file ?? $booking->contract_file,
        ])->save();

        return $booking;
    }

    /**
     * Aprova a pré-reserva: fixa o valor (com desconto) e libera o pagamento
     * com prazo. Valor zero (desconto integral) confirma na hora — não há o
     * que cobrar na Rede.
     *
     * Aprovada, a reserva aparece para pagar no perfil do sócio no site (o
     * dono é o `member_id`, venha ela do app ou do balcão).
     *
     * @throws SocialBookingException
     */
    public function approve(SocialBooking $booking, float $discount, ?string $discountReason, int $userId, ?Carbon $now = null, bool $notify = true): SocialBooking
    {
        $now ??= Carbon::now();
        $this->assertStage($booking, [SocialBooking::STAGE_REQUESTED], 'Só a pré-reserva em análise pode ser aprovada.');

        if (! $booking->contractOk()) {
            throw new SocialBookingException(SocialBookingException::CONTRACT_PENDING, 'Marque o contrato como ok antes de aprovar.');
        }

        $discount = round($discount, 2);
        $gross = $booking->grossTotal();

        if ($discount < 0 || $discount > $gross) {
            throw new SocialBookingException(SocialBookingException::INVALID_DISCOUNT, 'O desconto precisa ficar entre zero e o valor da reserva.');
        }
        if ($discount > 0 && trim((string) $discountReason) === '') {
            throw new SocialBookingException(SocialBookingException::INVALID_DISCOUNT, 'Informe o motivo do desconto.');
        }

        $confirmed = DB::transaction(function () use ($booking, $discount, $discountReason, $userId, $now) {
            // A data pode ter sido ocupada por fora (agendamento no balcão de
            // uma quadra que conflita): aprovar não pode passar por cima disso.
            $this->availability->assertAvailable($booking->place, $booking->event_date, $now, $booking->schedule_id, checkWindow: false);

            $booking->discount = $discount;
            $booking->discount_reason = $discount > 0 ? trim((string) $discountReason) : null;
            $booking->total = $this->totalOf($booking);
            $booking->approved_at = $now;
            $booking->approved_by = $userId;
            $this->touchContact($booking, $userId);

            if ((float) $booking->total <= 0) {
                $booking->stage = SocialBooking::STAGE_CONFIRMED;
                $booking->payment_due_at = null;
            } else {
                $booking->stage = SocialBooking::STAGE_AWAITING_PAYMENT;
                $booking->payment_due_at = $this->paymentDueAt($booking, $now);
            }

            $booking->save();
            $this->syncSchedule($booking);

            if ($booking->stage === SocialBooking::STAGE_CONFIRMED) {
                $this->syncGuestAccesses($booking, $userId);
            }

            return $booking->stage === SocialBooking::STAGE_CONFIRMED;
        });

        if ($notify) {
            $this->notifier->notify($booking, $confirmed ? SocialBookingNotifier::CONFIRMED : SocialBookingNotifier::APPROVED);
        }

        return $booking;
    }

    /** @throws SocialBookingException */
    public function refuse(SocialBooking $booking, string $reason, int $userId): SocialBooking
    {
        $this->assertStage($booking, [SocialBooking::STAGE_REQUESTED], 'Só a pré-reserva em análise pode ser recusada.');

        DB::transaction(function () use ($booking, $reason, $userId) {
            $this->touchContact($booking, $userId);
            $booking->forceFill([
                'stage' => SocialBooking::STAGE_REFUSED,
                'refused_at' => Carbon::now(),
                'refused_by' => $userId,
                'refusal_reason' => trim($reason),
            ])->save();

            $this->syncSchedule($booking);
        });

        $this->notifier->notify($booking, SocialBookingNotifier::REFUSED);

        return $booking;
    }

    /**
     * O sócio desiste pelo app. Só antes de pagar: reserva paga é cancelada
     * pela equipe, que decide o estorno.
     *
     * @throws SocialBookingException
     */
    public function cancelByMember(SocialBooking $booking): SocialBooking
    {
        if (! $booking->isUnpaid()) {
            throw new SocialBookingException(
                SocialBookingException::INVALID_STAGE,
                'Reserva paga só pode ser cancelada pela secretaria.',
                409
            );
        }

        DB::transaction(function () use ($booking) {
            $booking->forceFill([
                'stage' => SocialBooking::STAGE_CANCELLED,
                'cancelled_at' => Carbon::now(),
                'cancelled_by' => null,
                'cancel_reason' => 'Desistência do sócio pelo app.',
            ])->save();

            $this->syncSchedule($booking);
        });

        $this->notifier->notify($booking, SocialBookingNotifier::CANCELLED);

        return $booking;
    }

    /**
     * Cancelamento pela equipe, em qualquer estágio ativo. O estorno é decidido
     * caso a caso: `$refundAmount` é o valor a devolver pela Rede (null ou 0,
     * nada). O cancelamento fica gravado mesmo se a Rede recusar o estorno — o
     * erro sobe para a tela e o e-mail não promete devolução.
     *
     * @return array{booking: SocialBooking, refunded: float}
     *
     * @throws SocialBookingException
     */
    public function cancel(SocialBooking $booking, string $reason, int $userId, ?float $refundAmount = null): array
    {
        $this->assertStage($booking, SocialBooking::ACTIVE_STAGES, 'Esta reserva já está encerrada.');

        DB::transaction(function () use ($booking, $reason, $userId) {
            $booking->forceFill([
                'stage' => SocialBooking::STAGE_CANCELLED,
                'cancelled_at' => Carbon::now(),
                'cancelled_by' => $userId,
                'cancel_reason' => trim($reason),
            ])->save();

            $schedule = $booking->schedule;
            $schedule->forceFill([
                'cancel_reason' => trim($reason),
                'cancelled_by' => $userId,
                'cancelled_at' => Carbon::now(),
                'updated_by_user' => $userId,
            ]);
            $this->syncSchedule($booking);
            $this->cancelGuestAccesses($booking, $userId);
        });

        $refunded = 0.0;

        try {
            if ($refundAmount !== null && $refundAmount > 0) {
                $payment = $booking->schedule->schedulePayment;

                if (! $payment) {
                    throw new SocialBookingException(SocialBookingException::INVALID_STAGE, 'Reserva cancelada, mas não há pagamento para estornar.');
                }

                $before = (float) $payment->refunded_amount;
                $this->rede->refundPayment($payment, $refundAmount, $userId);
                $refunded = round((float) $payment->fresh()->refunded_amount - $before, 2);
            }
        } finally {
            $this->notifier->notify($booking, SocialBookingNotifier::CANCELLED, ['refunded' => $refunded]);
        }

        return ['booking' => $booking, 'refunded' => $refunded];
    }

    /**
     * O pagamento que chega por `POST /api/schedule/payment` pode ser desta
     * reserva? Uma reserva social por pagamento, no estágio certo, dentro do
     * prazo e pelo valor exato.
     *
     * @param  Collection<int, SocialBooking>  $bookings  as reservas sociais entre os schedules pagos
     *
     * @throws SocialBookingException
     */
    public function assertPayable(Collection $bookings, array $scheduleIds, float $paidAmount, ?Carbon $now = null): SocialBooking
    {
        if ($bookings->count() !== 1 || count(array_unique($scheduleIds)) !== 1) {
            throw new SocialBookingException(
                SocialBookingException::PAYMENT_MISMATCH,
                'Reserva de espaço social é paga sozinha, sem outros agendamentos no mesmo pagamento.'
            );
        }

        $booking = $bookings->first();

        if (! $booking->canPay($now)) {
            throw new SocialBookingException(
                SocialBookingException::PAYMENT_MISMATCH,
                $booking->stage === SocialBooking::STAGE_AWAITING_PAYMENT
                    ? 'O prazo para pagar esta reserva venceu.'
                    : 'Esta reserva não está liberada para pagamento.',
                409,
                ['stage' => $booking->stage]
            );
        }

        if (abs(round($paidAmount, 2) - (float) $booking->total) >= 0.01) {
            throw new SocialBookingException(
                SocialBookingException::PAYMENT_MISMATCH,
                'O valor pago não confere com o total da reserva.',
                422,
                ['total' => (float) $booking->total]
            );
        }

        return $booking;
    }

    /**
     * Chamado dentro da transação do pagamento, depois de o schedule receber o
     * status do pagamento. Pagamento aprovado confirma a reserva e libera os
     * convidados na portaria.
     */
    public function markPaid(SocialBooking $booking, SchedulePayment $payment): void
    {
        if ((int) $payment->status_id !== 1) {
            return;
        }

        $booking->forceFill(['stage' => SocialBooking::STAGE_CONFIRMED])->save();
        $this->syncSchedule($booking);
        $this->syncGuestAccesses($booking);
    }

    /**
     * Expira o que passou do prazo de pagamento. Roda pelo agendador
     * (`social:expire-bookings`).
     */
    public function expireOverdue(?Carbon $now = null): int
    {
        $now ??= Carbon::now();

        $overdue = SocialBooking::where('stage', SocialBooking::STAGE_AWAITING_PAYMENT)
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '<', $now)
            ->get();

        foreach ($overdue as $booking) {
            DB::transaction(function () use ($booking) {
                $booking->forceFill(['stage' => SocialBooking::STAGE_EXPIRED])->save();
                $this->syncSchedule($booking);
            });

            $this->notifier->notify($booking, SocialBookingNotifier::EXPIRED);
        }

        return $overdue->count();
    }

    /**
     * Troca a lista de convidados inteira.
     *
     * Pelo app (`$byMember`), só até a véspera e só reserva ativa; pela equipe,
     * enquanto a reserva estiver ativa. Com a reserva confirmada, a portaria é
     * sincronizada na hora.
     *
     * @param  array<int, array{name?: string, cpf?: string}>  $guests
     *
     * @throws SocialBookingException
     */
    public function replaceGuests(SocialBooking $booking, array $guests, bool $byMember, ?int $userId = null, ?Carbon $now = null): SocialBooking
    {
        $this->assertStage($booking, SocialBooking::ACTIVE_STAGES, 'Esta reserva já está encerrada.');

        if ($byMember && ! $booking->canMemberEditGuests($now)) {
            throw new SocialBookingException(
                SocialBookingException::GUESTS_LOCKED,
                'A lista de convidados pelo app fecha na véspera do evento. Fale com a secretaria.',
                409
            );
        }

        $normalized = $this->normalizeGuests($guests, $booking->guestLimit());

        DB::transaction(fn () => $this->applyGuests($booking, $normalized, $userId));

        return $booking->fresh(['guests']);
    }

    /**
     * Troca a lista pelo que chegou, por CPF: quem saiu perde a liberação da
     * portaria (se não usada), quem ficou mantém, quem entrou é criado — e,
     * com a reserva confirmada, ganha a liberação na hora.
     *
     * @param  Collection<int, array{name: string, cpf: string}>  $normalized
     */
    private function applyGuests(SocialBooking $booking, Collection $normalized, ?int $userId): void
    {
        $current = $booking->guests()->with('oneOffAccess')->get()->keyBy('cpf');
        $incoming = $normalized->keyBy('cpf');

        foreach ($current as $cpf => $guest) {
            if (! $incoming->has($cpf)) {
                $this->cancelGuestAccess($guest, $userId);
                $guest->delete();
            }
        }

        foreach ($incoming as $cpf => $data) {
            $guest = $current->get($cpf);

            if ($guest) {
                if ($guest->name !== $data['name']) {
                    $guest->update(['name' => $data['name']]);
                    $guest->oneOffAccess?->update(['name' => $data['name']]);
                }
            } else {
                $booking->guests()->create($data);
            }
        }

        if ($booking->stage === SocialBooking::STAGE_CONFIRMED) {
            $this->syncGuestAccesses($booking, $userId);
        }
    }

    /**
     * Cada convidado de reserva confirmada tem uma liberação pontual aberta na
     * data do evento. Quem já tem fica como está; quem não tem ganha uma.
     */
    public function syncGuestAccesses(SocialBooking $booking, ?int $userId = null): void
    {
        $booking->loadMissing(['place', 'member']);
        $reason = $this->guestAccessReason($booking);

        foreach ($booking->guests()->with('oneOffAccess')->get() as $guest) {
            $access = $guest->oneOffAccess;

            if ($access && $access->canceled_at === null) {
                continue;
            }

            $access = OneOffAccess::create([
                'cpf' => $guest->cpf,
                'name' => $guest->name,
                'reason' => $reason,
                'access_date' => Carbon::parse($booking->event_date)->toDateString(),
                'created_by_user' => $userId,
            ]);

            $guest->update(['one_off_access_id' => $access->id]);
        }
    }

    private function cancelGuestAccesses(SocialBooking $booking, ?int $userId): void
    {
        foreach ($booking->guests()->with('oneOffAccess')->get() as $guest) {
            $this->cancelGuestAccess($guest, $userId);
        }
    }

    /** A liberação já usada fica: é o registro de uma entrada que aconteceu. */
    private function cancelGuestAccess(SocialBookingGuest $guest, ?int $userId): void
    {
        $access = $guest->oneOffAccess;

        if ($access && $access->used_at === null && $access->canceled_at === null) {
            $access->forceFill(['canceled_at' => Carbon::now(), 'canceled_by_user' => $userId])->save();
        }
    }

    private function guestAccessReason(SocialBooking $booking): string
    {
        $member = $booking->member?->name ?? 'sócio';
        $place = $booking->place?->name ?? 'espaço social';

        return "Convidado de {$member} — {$place} (reserva social #{$booking->id})";
    }

    /** Status e preço do schedule acompanham o estágio da reserva. */
    private function syncSchedule(SocialBooking $booking): void
    {
        $schedule = $booking->schedule;
        $schedule->status_id = SocialBooking::SCHEDULE_STATUS[$booking->stage];
        $schedule->price = $booking->total;
        $schedule->save();
    }

    /**
     * Prazo para pagar: N dias (do grupo) a partir da aprovação, até o fim do
     * dia — mas nunca depois da véspera do evento. Aprovado em cima da hora,
     * o prazo é o fim do próprio dia.
     */
    private function paymentDueAt(SocialBooking $booking, Carbon $now): Carbon
    {
        $days = $booking->place->group->socialPaymentDays();
        $due = $now->copy()->addDays($days)->endOfDay();
        $eve = Carbon::parse($booking->event_date)->subDay()->endOfDay();

        if ($due->gt($eve)) {
            $due = $eve;
        }

        return $due->lt($now) ? $now->copy()->endOfDay() : $due;
    }

    private function totalOf(SocialBooking $booking): float
    {
        return max(0, round($booking->grossTotal() - (float) $booking->discount, 2));
    }

    /**
     * Convidados extras pedidos, dentro da cota do espaço.
     *
     * @throws SocialBookingException
     */
    private function extraGuests(Place $place, $requested): int
    {
        $requested = (int) $requested;
        $max = (int) $place->extra_guests;

        if ($requested < 0 || $requested > $max) {
            throw new SocialBookingException(
                SocialBookingException::OVER_CAPACITY,
                "Este espaço permite até {$max} convidado(s) extra(s).",
                422,
                ['extra_guests' => $max]
            );
        }

        return $requested;
    }

    /**
     * Total de pessoas (sócios + não sócios) dentro da capacidade do espaço.
     * Criança abaixo de 4 anos não conta — é regra do clube, explicada ao
     * sócio no texto de regras do grupo, não um campo a mais.
     *
     * @throws SocialBookingException
     */
    private function assertCapacity(Place $place, $expectedGuests): void
    {
        if ($expectedGuests !== null && $place->capacity && (int) $expectedGuests > (int) $place->capacity) {
            throw new SocialBookingException(
                SocialBookingException::OVER_CAPACITY,
                "A capacidade deste espaço é de {$place->capacity} pessoas.",
                422,
                ['capacity' => (int) $place->capacity]
            );
        }
    }

    /**
     * Linhas do catálogo (bebidas e mobiliário) a partir de
     * [{item_id|beverage_id, quantity}] — `beverage_id` é o nome de quando o
     * catálogo era só de bebidas, e continua aceito.
     *
     * Item novo precisa estar ativo e respeita o máximo por reserva. Ao editar,
     * o que já estava na reserva mantém o preço e o que trazia de mesas/cadeiras,
     * mesmo que tenha saído do catálogo ou mudado.
     *
     * @return Collection<int, array<string, mixed>>
     *
     * @throws SocialBookingException
     */
    private function buildItems(array $items, ?SocialBooking $booking = null): Collection
    {
        $wanted = collect($items)
            ->map(fn ($item) => [
                'id' => (int) ($item['item_id'] ?? $item['beverage_id'] ?? 0),
                'quantity' => (int) ($item['quantity'] ?? 0),
            ])
            ->filter(fn ($item) => $item['quantity'] > 0 && $item['id'] > 0)
            ->groupBy('id')
            ->map(fn ($rows, $id) => ['id' => (int) $id, 'quantity' => $rows->sum('quantity')]);

        if ($wanted->isEmpty()) {
            return collect();
        }

        $previous = $booking ? $booking->items()->get()->keyBy('social_beverage_id') : collect();
        $catalog = SocialBeverage::withTrashed()->whereIn('id', $wanted->keys())->get()->keyBy('id');

        return $wanted->values()->map(function ($item) use ($catalog, $previous) {
            $source = $previous->get($item['id']);
            $entry = $catalog->get($item['id']);

            if (! $source && (! $entry || ! $entry->active || $entry->trashed())) {
                throw new SocialBookingException(
                    SocialBookingException::BEVERAGE_UNAVAILABLE,
                    'Um dos itens escolhidos não está mais disponível.',
                    422,
                    ['item_id' => $item['id']]
                );
            }

            $max = $entry?->max_quantity ?: 999;
            if ($item['quantity'] > $max) {
                throw new SocialBookingException(
                    SocialBookingException::ITEM_LIMIT,
                    'Quantidade acima do permitido para ' . ($source->name ?? $entry->name) . " (máximo {$max}).",
                    422,
                    ['item_id' => $item['id'], 'max_quantity' => $max]
                );
            }

            $unitPrice = round((float) ($source ? $source->unit_price : $entry->price), 2);

            return [
                'social_beverage_id' => $item['id'],
                'category' => $source ? $source->category : $entry->category,
                'name' => $source->name ?? $entry->name,
                'unit' => $source ? $source->unit : $entry->unit,
                'mv_code' => $source ? $source->mv_code : $entry->mv_code,
                'unit_price' => $unitPrice,
                'quantity' => $item['quantity'],
                'subtotal' => round($unitPrice * $item['quantity'], 2),
                'tables_per_unit' => (int) ($source ? $source->tables_per_unit : $entry->tables_per_unit),
                'chairs_per_unit' => (int) ($source ? $source->chairs_per_unit : $entry->chairs_per_unit),
            ];
        });
    }

    /**
     * Início e fim do evento. Espaço com duração fixa exige o início (HH:MM),
     * dentro da faixa `event_earliest_start`–`event_latest_start` se houver; o
     * fim é início + duração e pode passar da meia-noite. Espaço sem duração
     * configurada não tem horário.
     *
     * @return array{start_time: ?string, end_time: ?string, duration_hours: ?int}
     *
     * @throws SocialBookingException
     */
    private function resolveTimes(Place $place, ?string $start): array
    {
        $hours = (int) $place->event_duration_hours;

        if ($hours <= 0) {
            return ['start_time' => null, 'end_time' => null, 'duration_hours' => null];
        }

        $start = $start !== null ? substr(trim($start), 0, 5) : null;

        if (! $start || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start)) {
            throw new SocialBookingException(
                SocialBookingException::INVALID_TIME,
                'Informe o horário de início do evento (HH:MM).',
                422,
                ['duration_hours' => $hours]
            );
        }

        $earliest = $place->event_earliest_start ? substr($place->event_earliest_start, 0, 5) : null;
        $latest = $place->event_latest_start ? substr($place->event_latest_start, 0, 5) : null;

        if (($earliest && $start < $earliest) || ($latest && $start > $latest)) {
            throw new SocialBookingException(
                SocialBookingException::INVALID_TIME,
                'O evento neste espaço pode começar ' . ($earliest && $latest ? "entre {$earliest} e {$latest}" : ($earliest ? "a partir das {$earliest}" : "até as {$latest}")) . '.',
                422,
                ['earliest_start' => $earliest, 'latest_start' => $latest]
            );
        }

        $end = Carbon::createFromFormat('H:i', $start)->addHours($hours)->format('H:i');

        return ['start_time' => $start . ':00', 'end_time' => $end . ':00', 'duration_hours' => $hours];
    }

    /**
     * Nome e CPF obrigatórios, CPF válido e sem repetir, e no máximo `$limit`
     * convidados não sócios (incluídos no preço + extras contratados).
     *
     * @return Collection<int, array{name: string, cpf: string}>
     *
     * @throws SocialBookingException
     */
    private function normalizeGuests(array $guests, int $limit): Collection
    {
        $normalized = collect($guests)->map(function ($guest, $i) {
            $name = trim((string) ($guest['name'] ?? ''));
            $cpf = Cpf::digits($guest['cpf'] ?? '');

            if ($name === '' || mb_strlen($name) > 255) {
                throw new SocialBookingException(SocialBookingException::INVALID_GUEST, 'Informe o nome de cada convidado.', 422, ['index' => $i]);
            }
            if (! Cpf::isValid($cpf)) {
                throw new SocialBookingException(SocialBookingException::INVALID_GUEST, "CPF inválido para o convidado {$name}.", 422, ['index' => $i]);
            }

            return ['name' => $name, 'cpf' => $cpf];
        });

        if ($normalized->pluck('cpf')->duplicates()->isNotEmpty()) {
            throw new SocialBookingException(SocialBookingException::INVALID_GUEST, 'Há CPF repetido na lista de convidados.');
        }

        if ($normalized->count() > $limit) {
            throw new SocialBookingException(
                SocialBookingException::OVER_CAPACITY,
                "A lista aceita até {$limit} convidado(s) não sócio(s) nesta reserva.",
                422,
                ['guest_limit' => $limit]
            );
        }

        return $normalized->values();
    }

    /** @throws SocialBookingException */
    private function assertSocialPlace(?Place $place): void
    {
        if (! $place || (int) $place->status_id !== 1 || ! $place->isSocial()) {
            throw new SocialBookingException(SocialBookingException::PLACE_NOT_SOCIAL, 'Espaço social não encontrado.', 404);
        }
    }

    /** @throws SocialBookingException */
    private function assertStage(SocialBooking $booking, array $stages, string $message): void
    {
        if (! in_array($booking->stage, $stages, true)) {
            throw new SocialBookingException(SocialBookingException::INVALID_STAGE, $message, 409, ['stage' => $booking->stage]);
        }
    }
}
