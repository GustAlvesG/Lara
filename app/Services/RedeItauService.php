<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\SchedulePayment;
use App\Services\Finance\PaymentRefunder;
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
     *
     * Só a chamada à Rede, sem auditoria: quem grava o estorno no pagamento
     * é o App\Services\Finance\PaymentRefunder (Reservas, Torneios e Ingressos).
     */
    public function refund(string $tid, float $amount): array
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
    public function refundPayment(SchedulePayment $payment, ?float $amount, ?int $actorUserId): float
    {
        // As regras (baixa manual não estorna, valor até o disponível) e a
        // gravação moram no estorno único do Financeiro.
        return (new PaymentRefunder($this))->refund($payment, $amount, $actorUserId);
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
