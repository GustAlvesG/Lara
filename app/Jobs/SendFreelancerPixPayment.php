<?php

namespace App\Jobs;

use App\Exceptions\Sicoob\SicoobException;
use App\Exceptions\Sicoob\SicoobPaymentOutcomeUnknownException;
use App\Models\PixPayment;
use App\Services\FreelancerService;
use App\Services\Sicoob\SicoobPixPagamentoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envia o Pix de um contrato de freelancer.
 *
 * SEM RETRY AUTOMÁTICO, e isso é a decisão central deste arquivo.
 *
 * O retry padrão de fila existe para operações que, repetidas, dão no mesmo.
 * Confirmar um Pix não é uma delas: a segunda tentativa transfere de novo. E o
 * caso em que o retry é mais tentador — timeout, "não sei se chegou" — é
 * exatamente aquele em que ele é mais perigoso, porque o silêncio da rede não
 * distingue "não processou" de "processou e a resposta se perdeu".
 *
 * Então: `tries = 1`. Quem decide se cabe uma nova tentativa é o estado
 * gravado em `pix_payments`, depois de o BANCO ter dito o que aconteceu:
 *
 *   failed / rejected → a conta não foi debitada. Reenviar é seguro.
 *   unknown           → pode ter sido debitada. Só a consulta resolve, e é a
 *                       reconciliação (`sicoob:pix-reconciliar`) que a faz.
 *   sent / finalized  → já está com o banco. Não se reenvia.
 *
 * A guarda no topo do `handle()` sustenta isso mesmo contra um
 * `queue:retry` feito à mão: um pagamento fora de `pending`/`initiated` sai
 * pela porta sem tocar na API.
 */
class SendFreelancerPixPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Uma tentativa. Ver o bloco acima — não aumente este número. */
    public int $tries = 1;

    /**
     * Folga sobre o timeout HTTP da confirmação (config `sicoob.http.timeout`,
     * 60s). O worker não pode matar o processo no meio de uma confirmação: o
     * pagamento ficaria sem nem chegar a `unknown`.
     */
    public int $timeout = 180;

    public function __construct(public int $pixPaymentId)
    {
    }

    public function handle(SicoobPixPagamentoService $pix, FreelancerService $freelancers): void
    {
        $payment = PixPayment::with(['freelancer', 'freelancerService'])->find($this->pixPaymentId);

        if (!$payment) {
            return;
        }

        // Porta de entrada única. Vale para o fluxo normal, para um
        // `queue:retry` manual e para uma fila reprocessada por engano.
        if (!in_array($payment->status, [PixPayment::STATUS_PENDING, PixPayment::STATUS_INITIATED], true)) {
            Log::channel('sicoob')->warning('Sicoob: envio ignorado — pagamento não está mais aguardando envio', [
                'pix_payment_id' => $payment->id,
                'status' => $payment->status,
                'end_to_end_id' => $payment->end_to_end_id,
            ]);

            return;
        }

        try {
            $payment = $pix->enviar($payment);
        } catch (SicoobPaymentOutcomeUnknownException $e) {
            // Deixa o job falhar de propósito: uma linha em `failed_jobs` é o
            // sinal de que alguém precisa olhar. A reconciliação vai resolver o
            // registro sozinha, mas o alarme fica.
            throw $e;
        } catch (SicoobException $e) {
            // Recusa, saldo, titular divergente, autenticação: desfechos
            // esperados, já gravados na linha e visíveis na tela do financeiro.
            // Não viram falha de job — só ruído.
            Log::channel('sicoob')->error('Sicoob: envio de Pix não concluído', [
                'pix_payment_id' => $payment->id,
                'freelancer_service_id' => $payment->freelancer_service_id,
                'erro' => $e->getMessage(),
                'contexto' => $e->context(),
            ]);

            return;
        }

        // Só o FINALIZADO dá baixa. `sent` (EM_PROCESSAMENTO) ainda não é
        // dinheiro na conta do freelancer — quem fecha esse caso é a
        // reconciliação, quando o banco confirmar.
        if ($payment->isFinalized()) {
            $freelancers->markAsPaidFromPix($payment);
        }
    }

    /**
     * Chamado quando o job falha de vez. Não tenta consertar nada: o estado do
     * dinheiro está em `pix_payments`, e mexer nele daqui às cegas é como se
     * cria inconsistência.
     */
    public function failed(?Throwable $e): void
    {
        Log::channel('sicoob')->critical('Sicoob: job de envio de Pix falhou', [
            'pix_payment_id' => $this->pixPaymentId,
            'erro' => $e?->getMessage(),
            'acao' => 'Confira o estado em pix_payments antes de qualquer novo envio.',
        ]);
    }
}
