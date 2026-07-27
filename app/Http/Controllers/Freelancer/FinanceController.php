<?php

namespace App\Http\Controllers\Freelancer;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayFreelancerServicesRequest;
use App\Models\FreelancerService;
use App\Services\FreelancerService as FreelancerServiceManager;

/**
 * Aba Financeiro de Serviços / Contratos: lista os contratos assinados pelas
 * duas partes e permite dar baixa de pagamento. Acesso restrito à permissão
 * "manage freelancer payments".
 */
class FinanceController extends Controller
{
    public function __construct(private FreelancerServiceManager $freelancerService)
    {
    }

    public function index()
    {
        $services = FreelancerService::awaitingFinance()
            ->with(['freelancer:id,name,cpf,pix_key', 'functionFreelancer', 'paidBy'])
            // Pendentes primeiro; dentro de cada grupo, os mais recentes no topo.
            ->orderBy('paid')
            ->orderByDesc('start_date')
            ->get();

        return view('freelancer.services.finance', [
            'services' => $services,
            'pendingTotal' => $services->where('paid', false)->sum('price'),
            'paidTotal' => $services->where('paid', true)->sum('price'),
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
