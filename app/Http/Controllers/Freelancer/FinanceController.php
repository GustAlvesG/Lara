<?php

namespace App\Http\Controllers\Freelancer;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayFreelancerServicesRequest;
use App\Models\FreelancerService;
use App\Services\FreelancerService as FreelancerServiceManager;

/**
 * Aba Financeiro de Serviços / Contratos: lista os contratos assinados pelas
 * duas partes e permite dar baixa de pagamento. Acesso restrito a quem está no
 * setor Contabilidade ou Gerência — Gate "manage-freelancer-payments".
 *
 * O botão "Dar baixa" tem dois comportamentos, decididos por `sicoob.enabled`:
 *
 *   desligado → marcação manual, como sempre foi. O dinheiro sai por fora.
 *   ligado    → enfileira um Pix de verdade. O contrato NÃO fica pago no
 *               clique: fica "em processamento", e só recebe a baixa quando o
 *               banco confirmar (ver FreelancerService::markAsPaidFromPix).
 */
class FinanceController extends Controller
{
    public function __construct(private FreelancerServiceManager $freelancerService)
    {
    }

    public function index()
    {
        $services = FreelancerService::awaitingFinance()
            ->with([
                'freelancer:id,name,cpf,pix_key',
                'functionFreelancer',
                'paidBy',
                // Estado do Pix de cada contrato, para a coluna de pagamento.
                'latestPixPayment',
            ])
            // Pendentes primeiro; dentro de cada grupo, os mais recentes no topo.
            ->orderBy('paid')
            ->orderByDesc('start_date')
            ->get();

        return view('freelancer.services.finance', [
            'services' => $services,
            'pendingTotal' => $services->where('paid', false)->sum('price'),
            'paidTotal' => $services->where('paid', true)->sum('price'),
            'pixEnabled' => (bool) config('sicoob.enabled'),
            'pixAmbiente' => config('sicoob.environment'),
        ]);
    }

    /**
     * Baixa individual (botão da linha) e em lote (checkboxes) — mesma rota,
     * porque a tabela não comporta um formulário por linha.
     */
    public function pay(PayFreelancerServicesRequest $request)
    {
        $ids = $request->serviceIds();
        $redirect = redirect()->route('freelancer-services.finance');

        if (!$ids) {
            return $redirect->with('error', 'Selecione ao menos um contrato para dar baixa.');
        }

        return config('sicoob.enabled')
            ? $this->payWithPix($ids, $request, $redirect)
            : $this->payManually($ids, $request, $redirect);
    }

    /**
     * Fluxo com Pix automático: o clique só enfileira. Nenhuma mensagem aqui
     * afirma que o pagamento foi feito — a tela não pode prometer o que ainda
     * depende do banco.
     */
    private function payWithPix(array $ids, PayFreelancerServicesRequest $request, $redirect)
    {
        $resultado = $this->freelancerService->requestPixForMany($ids, $request->user());

        if ($resultado['queued'] === 0) {
            return $redirect
                ->with('error', 'Nenhum Pix foi enviado.')
                ->with('warning', $resultado['problems']
                    ? implode(' · ', $resultado['problems'])
                    : 'Os contratos selecionados já foram pagos ou não estão aptos.');
        }

        $redirect->with('success', $resultado['queued'] === 1
            ? 'Pix enviado para processamento. A baixa é registrada quando o banco confirmar.'
            : "{$resultado['queued']} Pix enviados para processamento. A baixa de cada contrato é registrada quando o banco confirmar.");

        if ($resultado['problems']) {
            $redirect->with('warning', implode(' · ', $resultado['problems']));
        } elseif ($resultado['skipped'] > 0) {
            $redirect->with('warning', $resultado['skipped']
                . ' contrato(s) selecionado(s) foram ignorados por já estarem pagos ou não estarem aptos.');
        }

        return $redirect;
    }

    /** Comportamento original: baixa manual, sem tocar no banco. */
    private function payManually(array $ids, PayFreelancerServicesRequest $request, $redirect)
    {
        $paid = $this->freelancerService->markManyAsPaid($ids, $request->user());

        if ($paid === 0) {
            return $redirect->with('error', 'Nenhum contrato apto para baixa — verifique se já não foram pagos.');
        }

        $redirect->with('success', $paid === 1
            ? 'Baixa de pagamento registrada.'
            : "Baixa de pagamento registrada em {$paid} contratos.");

        // Um id selecionado que não recebeu baixa quase sempre significa que
        // outra pessoa já pagou aquele contrato com a tela aberta.
        if ($paid < count($ids)) {
            $redirect->with('warning', (count($ids) - $paid) . ' contrato(s) selecionado(s) foram ignorados por já estarem pagos ou não estarem aptos.');
        }

        return $redirect;
    }
}
