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

class RedeItauService
{

    public function authenticate(): string
    {
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
    }

    /**
     * Realiza o reembolso
     */
    public function refund(string $tid, float $amount): array
    {
        $amount = (int) ($amount * 100); // Convert to cents
        $accessToken = $this->authenticate();
        $response = Http::withoutVerifying()->withToken($accessToken)
            ->post(config('services.rede.base_url') . "/transactions/{$tid}/refunds", [
                'amount' => $amount,
            ]);
        
        if ($response->failed()) {
            throw new RequestException($response);
        }

        return $response->json();
    }

    public function beginRefund(array $payments_ids){
        try{

       
            foreach($payments_ids as $payment_id){
                $payment = SchedulePayment::find($payment_id);
                if($payment){
                    $refundResponse[] = $this->refund($payment->payment_integration_id, $payment->paid_amount);
                    //Update payment status to refunded
                    $payment->status_id = 0;
                    $payment->save();
                }
            }
        } catch (RequestException $e) {
            // Handle the exception as needed
            return dd(['error' => 'Refund failed: ' . $e->getMessage(),
            'ATENCAO' => 'O Estorno não funcionou. Tire um print dessa mensagem e envie para a TI']);
        }
        
        return $refundResponse;
    }


}