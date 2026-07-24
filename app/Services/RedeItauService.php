<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\SchedulePayment;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class RedeItauService
{

    public function authenticate(): string
    {
        return Cache::remember('rede_access_token', 55, function () {
            $credentials = base64_encode(
                config('services.rede.client_id') . ':' .
                config('services.rede.client_secret')
            );

            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Basic ' . $credentials,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ])->asForm()->post(config('services.rede.auth_url'), [
                'grant_type' => 'client_credentials',
            ]);

            if ($response->failed()) {
                throw new RequestException($response);
            }

            return $response->json('access_token');
        });
    }

    /**
     * Realiza o reembolso (parcial ou total) de uma transação na Rede.
     */
    private function refund(string $tid, float $amount): array
    {
        $amountCents = (int) round($amount * 100); // Convert to cents
        $accessToken = $this->authenticate();
        $response = Http::withoutVerifying()->withToken($accessToken)
            ->post(config('services.rede.base_url') . "/transactions/{$tid}/refunds", [
                'amount' => $amountCents,
            ]);

        if ($response->failed()) {
            // Token pode ter expirado entre a chamada e o cache; força reautenticação numa próxima tentativa.
            Cache::forget('rede_access_token');
            throw new RequestException($response);
        }

        return $response->json();
    }

    /**
     * Consulta os dados atuais de uma transação diretamente na Rede
     * (usado para exibir informações "ao vivo" na tela de detalhe do pagamento).
     */
    public function getTransaction(string $tid): array
    {
        $accessToken = $this->authenticate();
        $response = Http::withoutVerifying()->withToken($accessToken)
            ->get(config('services.rede.base_url') . "/transactions/{$tid}");

        if ($response->failed()) {
            Cache::forget('rede_access_token');
            throw new RequestException($response);
        }

        return $response->json();
    }

    /**
     * Estorna um pagamento específico (total ou parcial) e atualiza a auditoria local.
     * $amount omitido = estorna o valor restante (paid_amount - refunded_amount já feito).
     */
    public function refundPayment(SchedulePayment $payment, ?float $amount, ?int $actorUserId): array
    {
        if (empty($payment->payment_integration_id)) {
            throw new InvalidArgumentException('Pagamento sem identificador de transação na Rede (payment_integration_id).');
        }

        $alreadyRefunded = (float) ($payment->refunded_amount ?? 0);
        $remaining = round((float) $payment->paid_amount - $alreadyRefunded, 2);

        $amount = $amount !== null ? round($amount, 2) : $remaining;

        if ($amount <= 0 || $amount > $remaining) {
            throw new InvalidArgumentException("Valor de estorno inválido. Disponível para estorno: R$ " . number_format($remaining, 2, ',', '.'));
        }

        try {
            $response = $this->refund($payment->payment_integration_id, $amount);
        } catch (RequestException $e) {
            Log::error('Falha ao estornar pagamento na Rede', [
                'schedule_payment_id' => $payment->id,
                'tid' => $payment->payment_integration_id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $payment->refunded_amount = round($alreadyRefunded + $amount, 2);
        $payment->refunded_by = $actorUserId;
        $payment->refunded_at = Carbon::now();

        // Só marca como cancelado (0) quando o valor total já foi estornado; estorno parcial mantém o status atual.
        if ($payment->refunded_amount >= (float) $payment->paid_amount) {
            $payment->status_id = 0;
        }

        $payment->save();

        return $response;
    }

    /**
     * Estorna integralmente vários pagamentos de uma vez (fluxo de cancelamento em massa de agendamentos).
     */
    public function beginRefund(array $payments_ids, ?int $actorUserId = null): array
    {
        $refundResponses = [];

        foreach ($payments_ids as $payment_id) {
            $payment = SchedulePayment::find($payment_id);
            if (!$payment) {
                continue;
            }

            try {
                $refundResponses[] = $this->refundPayment($payment, null, $actorUserId);
            } catch (\Throwable $e) {
                Log::error('Estorno em massa falhou para um dos pagamentos selecionados', [
                    'schedule_payment_id' => $payment_id,
                    'error' => $e->getMessage(),
                ]);
                throw new \RuntimeException(
                    'O estorno não funcionou para o pagamento #' . $payment_id . '. Tire um print desta mensagem e envie para a TI. Detalhe: ' . $e->getMessage(),
                    previous: $e
                );
            }
        }

        return $refundResponses;
    }
}
