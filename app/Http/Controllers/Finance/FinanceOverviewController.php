<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FreelancerService;
use App\Services\Finance\FinanceLedger;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Financeiro › Visão geral (`financeiro.recebimentos`): o período em
 * números — recebido, estornado e líquido, por origem e por forma, eRede ×
 * baixa manual — e, ao lado, o que saiu para os freelancers e o que falta
 * pagar a eles. Os números vêm do FinanceLedger, a mesma consulta da lista
 * de Recebimentos.
 */
class FinanceOverviewController extends Controller
{
    use FinanceFilters;

    public function index(Request $request, FinanceLedger $ledger): View
    {
        $filters = $this->filters($request);
        // A visão geral é do período inteiro: só o período vale aqui.
        $period = ['from' => $filters['from'], 'to' => $filters['to']];

        return view('finance.index', [
            'filters' => $filters,
            'query' => $this->query($period),
            'summary' => $ledger->summary($period),
            'freelancers' => $this->freelancers($filters),
            'canFreelancers' => $request->user()->can(\App\Authorization\Permissions::FREELANCERS_FINANCEIRO),
        ]);
    }

    /**
     * Freelancers: pago no período (pela data da baixa) e o que está
     * liberado pela diretoria e ainda não foi pago (de qualquer data).
     *
     * @return array{paid: float, paid_count: int, pending: float, pending_count: int}
     */
    private function freelancers(array $filters): array
    {
        $paid = FreelancerService::query()->awaitingFinance()->where('paid', true)
            ->whereBetween('paid_at', [$filters['from'], $filters['to']]);
        $pending = FreelancerService::query()->awaitingFinance()->where('paid', false);

        return [
            'paid' => round((float) (clone $paid)->sum('price'), 2),
            'paid_count' => (clone $paid)->count(),
            'pending' => round((float) (clone $pending)->sum('price'), 2),
            'pending_count' => (clone $pending)->count(),
        ];
    }
}
