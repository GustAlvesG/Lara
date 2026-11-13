<?php

namespace App\Models\Concerns;

use App\Services\Finance\PaymentMethods;
use Illuminate\Support\Carbon;

/**
 * O que os três pagamentos do Lara têm em comum (SchedulePayment,
 * TournamentPayment, TicketPayment): as mesmas colunas de valor, eRede,
 * baixa manual e estorno. O estorno em si é do PaymentRefunder; aqui fica só
 * a conta e o registro no próprio pagamento.
 *
 * Cada model diz como marca o estorno no seu status
 * (`markRefundStatus`): Reservas usa `status_id` (0 = cancelado quando
 * estornado por inteiro); Torneios e Ingressos, a coluna `status`.
 */
trait FinancePayment
{
    abstract protected function markRefundStatus(bool $fully): void;

    /** Pagamento da Rede: só esses se consultam e se estornam por lá. */
    public function isOnline(): bool
    {
        return ! $this->is_manual && ! empty($this->payment_integration_id);
    }

    public function methodLabel(): string
    {
        return PaymentMethods::label($this->payment_method);
    }

    /** O que ainda pode voltar ao pagador: pago − já estornado. */
    public function refundableAmount(): float
    {
        return max(0.0, round((float) $this->paid_amount - (float) ($this->refunded_amount ?? 0), 2));
    }

    /** Grava um estorno feito na Rede: soma, quem, quando e o status. */
    public function recordRefund(float $amount, ?int $userId): void
    {
        $this->refunded_amount = round((float) ($this->refunded_amount ?? 0) + $amount, 2);
        $this->refunded_by = $userId;
        $this->refunded_at = Carbon::now();
        $this->markRefundStatus($this->refunded_amount >= (float) $this->paid_amount);
        $this->save();
    }
}
