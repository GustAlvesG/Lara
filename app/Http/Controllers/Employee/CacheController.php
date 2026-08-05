<?php

namespace App\Http\Controllers\Employee;

use App\Exceptions\EmployeeCacheException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewEmployeeCacheBatchRequest;
use App\Http\Requests\StoreEmployeeCacheBatchRequest;
use App\Models\Employee;
use App\Models\EmployeeCache;
use App\Models\EmployeeCacheBatch;
use App\Models\FunctionFreelancer;
use App\Models\Sector;
use App\Services\EmployeeCacheService;
use App\Support\EmployeeScope;
use Illuminate\Http\Request;

/**
 * Cachê de funcionários — as três frentes do painel.
 *
 *  - **Coordenador de setor:** solicita em lote (horário previsto), acompanha e
 *    reconfere o que divergiu depois da assinatura;
 *  - **Coordenador da Gerência:** aprova a solicitação item a item e reconfere
 *    a divergência em segunda instância;
 *  - **Financeiro:** tela própria (CacheFinanceController).
 *
 * A assinatura NÃO está aqui: ela acontece fora da sessão do painel, na tela do
 * funcionário (CacheSignatureController).
 */
class CacheController extends Controller
{
    /** Cachês por página na listagem. */
    private const PER_PAGE = 20;

    public function __construct(private EmployeeCacheService $caches)
    {
    }

    /* ---------------------------------------------------------------------
     | Listagem
     |---------------------------------------------------------------------*/

    public function index(Request $request)
    {
        $access = $this->accessOrFail();

        $caches = EmployeeScope::applyToRelation(
            EmployeeCache::with(['employee:id,name,employee_code,department', 'functionFreelancer:id,name', 'batch']),
            $access
        )
            ->search($request->query('q'))
            ->when($request->query('from'), fn($q, $from) => $q->whereDate('event_date', '>=', $from))
            ->when($request->query('to'), fn($q, $to) => $q->whereDate('event_date', '<=', $to))
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('employee.cache.index', [
            'caches' => $caches,
            'filters' => $request->only(['q', 'from', 'to']),
        ]);
    }

    /* ---------------------------------------------------------------------
     | Coordenador — solicitação
     |---------------------------------------------------------------------*/

    public function create()
    {
        $access = $this->coordinatorOrFail();

        return view('employee.cache.create', [
            'employees' => EmployeeScope::apply(Employee::query(), $access)
                ->orderBy('name')
                ->get(['id', 'name', 'employee_code', 'department']),
            // Só funções de cachê, e só as que têm as dez faixas preenchidas:
            // uma faixa faltando barraria a linha no momento de gravar.
            'functions' => FunctionFreelancer::ofType(FunctionFreelancer::TYPE_CACHE)
                ->with('cacheRates')
                ->orderBy('name')
                ->get()
                ->filter->hasCompleteCacheRates()
                ->values(),
            'sectors' => request()->user()->coordinatorSectors()->orderBy('name')->get(['sectors.id', 'sectors.name']),
        ]);
    }

    public function store(StoreEmployeeCacheBatchRequest $request)
    {
        try {
            $batch = $this->caches->createBatch(
                $request->user(),
                $request->rows(),
                $request->input('sector_id') ? (int) $request->input('sector_id') : null,
                $request->input('title'),
            );
        } catch (EmployeeCacheException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('employee-caches.batches.show', $batch)
            ->with('success', 'Solicitação criada com ' . $batch->caches()->count() . ' cachê(s). Confira e envie para a gerência.');
    }

    /* ---------------------------------------------------------------------
     | Coordenador — lotes
     |---------------------------------------------------------------------*/

    public function batches()
    {
        $this->coordinatorOrFail();

        return view('employee.cache.batches', [
            'batches' => EmployeeCacheBatch::where('created_by', request()->user()->id)
                ->withCount('caches')
                ->with(['sector', 'reviewedBy'])
                ->orderByDesc('id')
                ->limit(50)
                ->get(),
        ]);
    }

    public function show(EmployeeCacheBatch $batch)
    {
        $isManager = $this->isManager();

        // O coordenador acompanha o que solicitou; a gerência vê todos.
        abort_unless($isManager || $batch->created_by === request()->user()->id, 403);

        $batch->load([
            'caches.employee:id,name,employee_code,department,cpf',
            'caches.functionFreelancer:id,name',
            'caches.managerRejectedBy',
            'createdBy',
            'reviewedBy',
            'sector',
        ]);

        return view('employee.cache.show', [
            'batch' => $batch,
            'isManager' => $isManager,
            'canReview' => $isManager && $batch->canBeReviewed(),
            'isOwner' => $batch->created_by === request()->user()->id,
        ]);
    }

    public function send(EmployeeCacheBatch $batch)
    {
        $this->coordinatorOrFail();
        abort_unless($batch->created_by === request()->user()->id, 403);

        try {
            $this->caches->send($batch);
        } catch (EmployeeCacheException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('employee-caches.batches.show', $batch)
            ->with('success', 'Solicitação enviada para a aprovação da gerência.');
    }

    public function removeItem(EmployeeCacheBatch $batch, EmployeeCache $cache)
    {
        $this->coordinatorOrFail();
        abort_unless($batch->created_by === request()->user()->id, 403);

        try {
            $this->caches->removeRow($batch, $cache);
        } catch (EmployeeCacheException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Cachê retirado da solicitação.');
    }

    public function discard(EmployeeCacheBatch $batch)
    {
        $this->coordinatorOrFail();
        abort_unless($batch->created_by === request()->user()->id, 403);

        try {
            $this->caches->discard($batch);
        } catch (EmployeeCacheException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('employee-caches.batches')
            ->with('success', 'Rascunho descartado.');
    }

    /* ---------------------------------------------------------------------
     | Gerência — 1ª aprovação
     |---------------------------------------------------------------------*/

    public function queue()
    {
        abort_unless($this->isManager(), 403);

        return view('employee.cache.queue', [
            'batches' => EmployeeCacheBatch::awaitingManager()
                ->withCount('caches')
                ->withSum('caches', 'expected_price')
                ->with(['createdBy', 'sector'])
                ->orderBy('sent_at')
                ->get(),
        ]);
    }

    public function review(ReviewEmployeeCacheBatchRequest $request, EmployeeCacheBatch $batch)
    {
        try {
            $result = $this->caches->review($batch, $request->user(), $request->decisions());
        } catch (EmployeeCacheException $e) {
            return redirect()->route('employee-caches.batches.show', $batch)->with('error', $e->getMessage());
        }

        $message = "Solicitação analisada: {$result['approved']} aprovado(s)";

        if ($result['rejected'] > 0) {
            $message .= ", {$result['rejected']} recusado(s)";
        }

        $message .= $result['approved'] > 0
            ? '. Os aprovados já aparecem para os funcionários assinarem.'
            : '. Nada seguiu para assinatura.';

        return redirect()->route('employee-caches.batches.show', $batch)->with('success', $message);
    }

    /* ---------------------------------------------------------------------
     | Reconferência — 2ª aprovação, só quando o horário divergiu
     |
     | O coordenador reconfere primeiro (é ele quem sabe o que foi combinado no
     | setor), a gerência depois. Recusar em qualquer uma das duas para o cachê.
     |---------------------------------------------------------------------*/

    public function recheckQueue()
    {
        $access = $this->accessOrFail();
        $isManager = $this->isManager();

        $pending = EmployeeScope::applyToRelation(
            EmployeeCache::signedPendingRecheck()
                ->with(['employee:id,name,employee_code,department', 'functionFreelancer:id,name', 'batch.createdBy']),
            $access
        )
            ->orderBy('event_date')
            ->get()
            // A divergência é conferida em PHP: horário mora em coluna separada
            // da data, e comparar isso em SQL muda de MySQL para SQLite.
            ->filter->hasDivergence();

        return view('employee.cache.recheck', [
            'coordinatorQueue' => $pending->filter->awaitsCoordinatorRecheck()->values(),
            'managerQueue' => $isManager ? $pending->filter->awaitsManagerRecheck()->values() : collect(),
            'isManager' => $isManager,
        ]);
    }

    public function recheck(Request $request, EmployeeCache $cache)
    {
        $user = $request->user();
        $stage = $request->input('stage');

        $request->validate([
            'stage' => ['required', 'in:coordinator,manager'],
            'decision' => ['required', 'in:approve,reject'],
            'reason' => ['nullable', 'string', 'max:255', 'required_if:decision,reject'],
        ], ['reason.required_if' => 'Informe o motivo da recusa.']);

        if ($stage === 'manager') {
            abort_unless($this->isManager(), 403);
        } else {
            $this->assertResponsibleFor($cache);
        }

        try {
            if ($request->input('decision') === 'reject') {
                $this->caches->rejectRecheck($cache, $user, $request->input('reason'));

                return back()->with('success', 'Cachê recusado na reconferência.');
            }

            $stage === 'manager'
                ? $this->caches->recheckAsManager($cache, $user)
                : $this->caches->recheckAsCoordinator($cache, $user);
        } catch (EmployeeCacheException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $cache->fresh()->isPayable()
            ? 'Reconferência concluída — o cachê foi liberado para o financeiro.'
            : 'Reconferência do coordenador registrada. Segue para a gerência.');
    }

    /* ---------------------------------------------------------------------
     | Auxiliares
     |---------------------------------------------------------------------*/

    private function isManager(): bool
    {
        return request()->user()?->isManagementCoordinator() ?? false;
    }

    /** @return array<string, mixed> */
    private function accessOrFail(): array
    {
        $access = EmployeeScope::for(request()->user());

        abort_if($access['type'] === 'none', 403);

        return $access;
    }

    /** @return array<string, mixed> */
    private function coordinatorOrFail(): array
    {
        $access = EmployeeScope::for(request()->user());

        abort_unless(EmployeeScope::isCoordinator($access), 403);

        return $access;
    }

    /** O usuário responde pelo funcionário deste cachê? */
    private function assertResponsibleFor(EmployeeCache $cache): void
    {
        $access = $this->coordinatorOrFail();

        if ($access['type'] === 'all') {
            return;
        }

        abort_unless(
            in_array($cache->employee?->department, $access['values'] ?? [], true),
            403
        );
    }
}
