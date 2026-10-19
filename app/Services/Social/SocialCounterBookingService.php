<?php

namespace App\Services\Social;

use App\Exceptions\ManualPaymentException;
use App\Exceptions\SocialBookingException;
use App\Models\Place;
use App\Models\Social\SocialBooking;
use App\Services\ManualPaymentService;
use Illuminate\Support\Facades\DB;

/**
 * Reserva de espaço social feita por completo no balcão, em nome do sócio.
 *
 * Os mesmos passos do fluxo do app (SocialBookingService), numa transação só:
 *
 *   pré-reserva ─► contrato ok ─► aprovação (com desconto) ─► [baixa manual]
 *
 * Sem baixa, a reserva termina aguardando pagamento e aparece para pagar no
 * perfil do sócio no site (é dele pelo `member_id`). Com baixa, termina
 * confirmada, com os convidados liberados na portaria. Se qualquer passo
 * recusar, nada fica gravado — nem a data presa.
 *
 * O sócio recebe um e-mail só, do estado final (aprovada para pagar ou
 * confirmada), em vez de um por passo.
 */
class SocialCounterBookingService
{
    public function __construct(
        private SocialBookingService $bookings,
        private ManualPaymentService $payments,
        private SocialBookingNotifier $notifier,
    ) {
    }

    /**
     * @param  array  $data  os mesmos campos da pré-reserva (SocialBookingService::request)
     * @param  array{discount?: float|null, discount_reason?: string|null, contract_file?: string|null}  $close
     * @param  array{payment_method: string, paid_at?: string|null, notes?: string|null}|null  $payment  null = o sócio paga depois, pelo site
     *
     * @throws SocialBookingException
     * @throws ManualPaymentException
     */
    public function book(Place $place, int $memberId, array $data, int $userId, array $close, ?array $payment = null): SocialBooking
    {
        $booking = DB::transaction(function () use ($place, $memberId, $data, $userId, $close, $payment) {
            $booking = $this->bookings->request($place, $memberId, $data, $userId, notify: false);
            $this->bookings->markContract($booking, $userId, $close['contract_file'] ?? null);
            $booking = $this->bookings->approve(
                $booking->fresh(),
                (float) ($close['discount'] ?? 0),
                $close['discount_reason'] ?? null,
                $userId,
                notify: false
            );

            // Desconto integral já confirma na aprovação: não há o que baixar.
            if ($payment && $booking->stage === SocialBooking::STAGE_AWAITING_PAYMENT) {
                $this->payments->record([$booking->schedule_id], $payment + ['amount' => (float) $booking->total], $userId, notify: false);
            }

            return $booking->fresh();
        });

        $this->notifier->notify(
            $booking,
            $booking->stage === SocialBooking::STAGE_CONFIRMED ? SocialBookingNotifier::CONFIRMED : SocialBookingNotifier::APPROVED
        );

        return $booking->fresh(['items', 'guests', 'place.group']);
    }
}
