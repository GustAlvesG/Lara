<?php

namespace App\Console\Commands;

use App\Models\PixPayment;
use App\Services\FreelancerService;
use App\Services\Sicoob\SicoobPixPagamentoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fecha os pagamentos cujo desfecho ainda não conhecemos, perguntando ao banco.
 *
 * É a contrapartida obrigatória de o Job não ter retry. Sem retry, um Pix que
 * ficou em `unknown` (a confirmação saiu e a resposta se perdeu) ou em `sent`
 * (o banco aceitou e ainda está liquidando) ficaria parado para sempre — e o
 * contrato nunca receberia a baixa, mesmo com o dinheiro já tendo saído.
 *
 * Este comando não envia nada. Ele só CONSULTA `GET /pagamentos/{endToEndId}`
 * e escreve o que o banco respondeu. É por isso que ele pode rodar de minuto
 * em minuto sem risco: consultar não move dinheiro.
 */
class ReconcilePixPayments extends Command
{
    protected $signature = 'sicoob:pix-reconciliar
                            {--limit=50 : Máximo de pagamentos conferidos por execução}';

    protected $description = 'Consulta no Sicoob o desfecho dos Pix em processamento ou com desfecho desconhecido e atualiza a baixa dos contratos. Não envia pagamentos.';

    public function handle(SicoobPixPagamentoService $pix, FreelancerService $freelancers): int
    {
        if (!config('sicoob.enabled')) {
            return self::SUCCESS;
        }

        $this->liberarPendentesOrfaos();

        $pendentes = PixPayment::query()
            ->open()
            ->whereNotNull('end_to_end_id')
            ->orderBy('confirmed_at')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($pendentes->isEmpty()) {
            return self::SUCCESS;
        }

        $timeout = (int) config('sicoob.pix.reconcile_timeout_minutes', 1440);

        foreach ($pendentes as $payment) {
            try {
                $atualizado = $pix->reconciliar($payment);

                if ($atualizado->isFinalized()) {
                    $freelancers->markAsPaidFromPix($atualizado);

                    $this->info("Pix #{$atualizado->id} finalizado — baixa registrada no contrato #{$atualizado->freelancer_service_id}.");
                }
            } catch (Throwable $e) {
                // Uma consulta que falha não muda nada: o pagamento continua no
                // mesmo estado e será conferido de novo na próxima execução. A
                // insistência é segura justamente porque consultar não move
                // dinheiro.
                Log::channel('sicoob')->warning('Sicoob: reconciliação não conseguiu conferir um pagamento', [
                    'pix_payment_id' => $payment->id,
                    'end_to_end_id' => $payment->end_to_end_id,
                    'erro' => $e->getMessage(),
                ]);
            }

            // Insistir para sempre esconderia um problema real. Passado o
            // prazo, o caso vira alarme para conferência humana no extrato.
            $atual = $payment->fresh();
            $desde = $atual?->confirmed_at ?? $atual?->created_at;

            if ($atual && in_array($atual->status, PixPayment::OPEN_STATUSES, true)
                && $desde && $desde->diffInMinutes(now()) > $timeout) {
                Log::channel('sicoob')->critical('Sicoob: pagamento sem desfecho há tempo demais — confira no extrato', [
                    'pix_payment_id' => $payment->id,
                    'end_to_end_id' => $payment->end_to_end_id,
                    'freelancer_service_id' => $payment->freelancer_service_id,
                    'valor' => (float) $payment->amount,
                    'aguardando_desde' => $desde->toDateTimeString(),
                ]);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Solta contratos presos por uma linha `pending` que nunca virou chamada.
     *
     * Acontece quando o job se perde entre o commit e a fila: worker parado,
     * `queue:flush`, deploy no meio. A linha fica em `pending` para sempre e,
     * como `pending` é um estado bloqueante, o contrato nunca mais aceita um
     * novo envio — o financeiro fica sem conseguir pagar aquele freelancer.
     *
     * Marcar `failed` aqui é seguro, e essa é a parte que importa: sem
     * `end_to_end_id`, a iniciação nunca concluiu, logo a confirmação nunca
     * pôde ter sido enviada, logo NADA saiu da conta. É o único estado sobre o
     * qual dá para afirmar isso sem perguntar ao banco.
     */
    private function liberarPendentesOrfaos(): void
    {
        // Folga grande de propósito: um pagamento recém-criado cuja fila está
        // só um pouco atrasada não pode ser confundido com um órfão.
        $limite = now()->subMinutes(30);

        $orfaos = PixPayment::query()
            ->where('status', PixPayment::STATUS_PENDING)
            ->whereNull('end_to_end_id')
            ->where('created_at', '<', $limite)
            ->get();

        foreach ($orfaos as $orfao) {
            $orfao->forceFill([
                'status' => PixPayment::STATUS_FAILED,
                'rejection_detail' => 'A tentativa nunca chegou a ser enviada ao banco (job não executado). Nada foi transferido.',
            ])->save();

            Log::channel('sicoob')->warning('Sicoob: tentativa órfã liberada — o contrato voltou a aceitar pagamento', [
                'pix_payment_id' => $orfao->id,
                'freelancer_service_id' => $orfao->freelancer_service_id,
                'valor' => (float) $orfao->amount,
                'criada_em' => $orfao->created_at?->toDateTimeString(),
            ]);
        }
    }
}
