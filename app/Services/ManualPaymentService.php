<?php

namespace App\Services;

use App\Exceptions\ManualPaymentException;
use App\Models\Schedule;
use App\Models\SchedulePayment;
use App\Models\Social\SocialBooking;
use App\Services\Social\SocialBookingNotifier;
use App\Services\Social\SocialBookingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Baixa manual de pagamento: o operador marca como pago, no painel, o que foi
 * recebido fora da plataforma (dinheiro, Pix direto ao clube, maquininha da
 * secretaria, transferência).
 *
 * Gera um SchedulePayment como o da Rede (status 1, ligado aos agendamentos),
 * só que com `is_manual`, quem deu a baixa e a observação — e sem
 * `payment_integration_id`, então não se consulta nem se estorna na Rede.
 * Daí em diante o agendamento está pago para todo o resto do Lara: o site
 * deixa de oferecer o pagamento e a tela de Pagamentos lista a baixa.
 *
 * - Quadras: um ou mais agendamentos do mesmo sócio, confirmados (balcão) ou
 *   pendentes (site, ainda no prazo); o pendente passa a confirmado.
 * - Espaço social: a reserva sozinha, já aprovada (aguardando pagamento) e
 *   pelo total exato; confirma a reserva e libera os convidados na portaria,
 *   como o pagamento online (SocialBookingService::markPaid).
 */
class ManualPaymentService
{
    /** Status de agendamento que ainda aceitam pagamento: confirmado e pendente. */
    private const PAYABLE_STATUSES = [1, 3];

    public function __construct(
        private SocialBookingService $socialBookings,
        private SocialBookingNotifier $socialNotifier,
        private SchedulesService $schedulesService,
    ) {
    }

    /**
     * @param  array<int, int|string>  $scheduleIds
     * @param  array{payment_method: string, amount?: float|string|null, paid_at?: string|null, notes?: string|null}  $data
     * @param  bool  $notify  false quando quem chama manda o e-mail (a reserva social fechada de uma vez no balcão)
     *
     * @throws ManualPaymentException
     */
    public function record(array $scheduleIds, array $data, int $userId, bool $notify = true): SchedulePayment
    {
        $ids = array_values(array_unique(array_map('intval', $scheduleIds)));

        if ($ids === []) {
            throw new ManualPaymentException('Escolha o agendamento a dar baixa.');
        }

        if (! array_key_exists($data['payment_method'] ?? '', SchedulePayment::MANUAL_METHODS)) {
            throw new ManualPaymentException('Escolha a forma de pagamento.');
        }

        $paidAt = ! empty($data['paid_at']) ? Carbon::parse($data['paid_at']) : Carbon::now();

        if ($paidAt->isFuture()) {
            throw new ManualPaymentException('A data do pagamento não pode estar no futuro.');
        }

        [$payment, $social, $wasPending] = DB::transaction(function () use ($ids, $data, $userId, $paidAt) {
            // Trava as linhas: o site pode estar pagando o mesmo agendamento
            // agora; um dos dois espera e encontra o outro já gravado.
            $schedules = Schedule::withoutGlobalScopes()
                ->with('socialBooking')
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            $this->assertPayable($schedules, $ids);

            $social = $schedules->first()->socialBooking;
            $total = round((float) $schedules->sum('price'), 2);
            $amount = isset($data['amount']) && $data['amount'] !== '' ? round((float) $data['amount'], 2) : $total;

            if ($amount < 0) {
                throw new ManualPaymentException('O valor pago não pode ser negativo.');
            }

            if ($social && abs($amount - (float) $social->total) >= 0.01) {
                throw new ManualPaymentException('Reserva de espaço social: o valor da baixa tem de ser o total aprovado (R$ ' . number_format((float) $social->total, 2, ',', '.') . ').');
            }

            $payment = new SchedulePayment([
                'payment_method' => $data['payment_method'],
                'paid_amount' => $amount,
                'paid_at' => $paidAt,
                'status_id' => 1,
            ]);
            $payment->forceFill([
                'is_manual' => true,
                'recorded_by' => $userId,
                'manual_notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            ])->save();

            $wasPending = $schedules->where('status_id', 3)->values();

            Schedule::withoutGlobalScopes()->whereIn('id', $ids)->update([
                'schedule_payment_id' => $payment->id,
                'status_id' => 1,
                'updated_by_user' => $userId,
            ]);

            if ($social) {
                $this->socialBookings->markPaid($social, $payment);
            }

            return [$payment, $social, $wasPending];
        });

        if ($notify) {
            $this->notify($payment, $social, $wasPending);
        }

        return $payment;
    }

    /**
     * Confirmação ao sócio, fora da transação e sem desfazer a baixa se o
     * e-mail falhar. Quadra já confirmada no balcão recebeu o e-mail de
     * confirmação ao ser criada; só a que estava pendente recebe agora.
     */
    private function notify(SchedulePayment $payment, ?SocialBooking $social, $wasPending): void
    {
        try {
            if ($social) {
                $this->socialNotifier->notify($social->fresh(), SocialBookingNotifier::CONFIRMED);
            } elseif ($wasPending->isNotEmpty()) {
                $this->schedulesService->notifyScheduleStatus(
                    Schedule::withoutGlobalScopes()->whereIn('id', $wasPending->pluck('id'))->get(),
                    $payment
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** @throws ManualPaymentException */
    private function assertPayable($schedules, array $ids): void
    {
        if ($schedules->count() !== count($ids)) {
            throw new ManualPaymentException('Agendamento não encontrado.');
        }

        if ($schedules->whereNotNull('schedule_payment_id')->isNotEmpty()) {
            throw new ManualPaymentException('Este agendamento já tem pagamento registrado.');
        }

        if ($schedules->pluck('member_id')->unique()->count() > 1) {
            throw new ManualPaymentException('Uma baixa é de um sócio só: os agendamentos escolhidos são de sócios diferentes.');
        }

        if ($schedules->whereNotIn('status_id', self::PAYABLE_STATUSES)->isNotEmpty()) {
            throw new ManualPaymentException('Agendamento cancelado ou expirado não recebe baixa de pagamento.');
        }

        $social = $schedules->filter(fn (Schedule $s) => $s->socialBooking !== null);

        if ($social->isEmpty()) {
            return;
        }

        if ($schedules->count() > 1) {
            throw new ManualPaymentException('Reserva de espaço social recebe baixa sozinha, sem outros agendamentos.');
        }

        if ($social->first()->socialBooking->stage !== SocialBooking::STAGE_AWAITING_PAYMENT) {
            throw new ManualPaymentException('Só a reserva de espaço social aprovada (aguardando pagamento) recebe baixa: aprove antes, para fixar o valor.');
        }
    }
}
