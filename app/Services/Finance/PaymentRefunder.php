<?php

namespace App\Services\Finance;

use App\Services\RedeItauService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * O estorno pela eRede, um só para Reservas, Torneios e Ingressos (antes eram
 * três cópias). Confere, chama a Rede e só então grava no pagamento: se a
 * Rede recusar, nada muda.
 *
 * Baixa manual não se estorna pelo Lara: o dinheiro não passou pela Rede, e
 * a devolução, se houver, é feita fora do sistema.
 *
 * O pagamento é qualquer model com o trait App\Models\Concerns\FinancePayment.
 */
class PaymentRefunder
{
    public const MANUAL_MESSAGE = 'Pagamento registrado por baixa manual (pago fora da eRede): não há estorno pelo Lara. Se houver devolução, ela é feita fora do sistema.';

    public function __construct(private RedeItauService $rede)
    {
    }

    /**
     * @param  float|null  $amount  null = o que falta estornar
     * @param  bool  $clamp  true = estorna até o disponível (o cancelamento de
     *                       uma inscrição ou de ingressos pede o valor deles, e
     *                       o pagamento pode já ter devolvido parte); false =
     *                       valor acima do disponível é recusado (a tela de
     *                       Pagamentos, onde a pessoa digita o valor)
     * @return float o valor estornado
     *
     * @throws InvalidArgumentException|RequestException
     */
    public function refund(Model $payment, ?float $amount, ?int $userId, bool $clamp = false): float
    {
        if ($payment->is_manual) {
            throw new InvalidArgumentException(self::MANUAL_MESSAGE);
        }

        if (empty($payment->payment_integration_id)) {
            throw new InvalidArgumentException('Pagamento sem identificador de transação na Rede (payment_integration_id).');
        }

        $remaining = $payment->refundableAmount();
        $amount = $amount === null ? $remaining : round($amount, 2);

        if ($clamp) {
            $amount = round(min($amount, $remaining), 2);
        }

        if ($remaining <= 0) {
            throw new InvalidArgumentException('Não há valor disponível para estorno neste pagamento.');
        }

        if ($amount <= 0 || $amount > $remaining) {
            throw new InvalidArgumentException('Valor de estorno inválido. Disponível para estorno: R$ ' . number_format($remaining, 2, ',', '.'));
        }

        try {
            $this->rede->refund($payment->payment_integration_id, $amount);
        } catch (RequestException $e) {
            Log::error('Falha ao estornar pagamento na Rede', [
                'payment' => $payment::class . '#' . $payment->getKey(),
                'tid' => $payment->payment_integration_id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $payment->recordRefund($amount, $userId);

        return $amount;
    }
}
