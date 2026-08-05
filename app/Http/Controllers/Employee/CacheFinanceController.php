<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\EmployeeCache;
use App\Services\EmployeeCacheService;
use Illuminate\Http\Request;

/**
 * Financeiro do cachê.
 *
 * A baixa é **manual**: o cachê não é pago pelo Sicoob como o freelancer, e sim
 * por fora (folha/caixa). Marcar aqui é o registro de que o pagamento saiu —
 * não há transferência a disparar, e por isso também não há estado
 * "em processamento" como no Pix.
 */
class CacheFinanceController extends Controller
{
    public function __construct(private EmployeeCacheService $caches)
    {
    }

    public function index(Request $request)
    {
        // Só o que já passou por tudo. `isPayable()` é quem decide de verdade:
        // o escopo é a peneira grossa, e a reconferência da divergência é
        // conferida em PHP.
        $caches = EmployeeCache::awaitingFinance()
            ->with(['employee:id,name,employee_code,department,cpf', 'functionFreelancer:id,name', 'batch.sector', 'paidBy:id,name'])
            ->orderBy('paid')
            ->orderByDesc('event_date')
            ->get()
            ->filter->isPayable();

        return view('employee.cache.finance', [
            'pending' => $caches->reject->isPaid()->values(),
            'paid' => $caches->filter->isPaid()->sortByDesc('paid_at')->values(),
        ]);
    }

    /**
     * Uma rota só: a baixa individual manda `only`, a em massa manda `caches[]`.
     * Ids que deixaram de estar aptos enquanto a tela estava aberta são
     * ignorados, e o aviso diz quantos ficaram de fora.
     */
    public function pay(Request $request)
    {
        $request->validate([
            'only' => ['nullable', 'integer'],
            'caches' => ['nullable', 'array'],
            'caches.*' => ['integer'],
        ]);

        $ids = $request->filled('only')
            ? [(int) $request->input('only')]
            : array_map('intval', (array) $request->input('caches', []));

        if (empty($ids)) {
            return back()->with('error', 'Selecione ao menos um cachê para dar baixa.');
        }

        $result = $this->caches->pay($ids, $request->user());

        if ($result['paid'] === 0) {
            return back()->with('error', 'Nenhum cachê foi baixado — verifique se ainda estão liberados para pagamento.');
        }

        $redirect = back()->with('success', $result['paid'] === 1
            ? 'Baixa registrada.'
            : "{$result['paid']} cachês baixados.");

        if ($result['skipped'] > 0) {
            $redirect->with('warning', $result['skipped'] . ' cachê(s) foram ignorados por já não estarem aptos.');
        }

        return $redirect;
    }
}
