<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MultiVendas\SalesDashboardService;
use App\Services\MultiVendas\SalesDashboardUnavailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dashboards do site › Vendas (MultiVendas): o Grafana "Vendas Hoje" com
 * período de/até e filtro de lojas. Mesmo grupo dos outros painéis
 * (`api_token` + `login_token` + `member_admin`). Regras no
 * SalesDashboardService; ver docs/funcionalidades/dashboards-site.md.
 *
 * MultiVendas fora do ar: 503 com mensagem curta (nunca a do driver).
 */
class SalesDashboardApiController extends Controller
{
    public function __construct(private SalesDashboardService $sales)
    {
    }

    /** GET /api/dashboard/sales/stores — as lojas do filtro. */
    public function stores(): JsonResponse
    {
        try {
            return response()->json(['stores' => $this->sales->stores()]);
        } catch (SalesDashboardUnavailable $e) {
            return $this->unavailable($e);
        }
    }

    /** GET /api/dashboard/sales?from=AAAA-MM-DD&to=AAAA-MM-DD&stores[]=… */
    public function index(Request $request): JsonResponse
    {
        try {
            $filter = $this->sales->filter($request->only(['from', 'to', 'stores']));

            return response()->json($this->sales->sales($filter));
        } catch (SalesDashboardUnavailable $e) {
            return $this->unavailable($e);
        }
    }

    private function unavailable(SalesDashboardUnavailable $e): JsonResponse
    {
        return response()->json(['error' => $e->getMessage()], 503);
    }
}
