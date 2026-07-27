<?php

namespace App\Http\Controllers\Freelancer;

use App\Exceptions\FreelancerBatchException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewFreelancerBatchRequest;
use App\Models\FreelancerService;
use App\Models\FreelancerServiceBatch;
use App\Services\FreelancerBatchService;
use Illuminate\Http\Request;

/**
 * Lotes de contratos para aprovação da gerência.
 *
 * Duas frentes na mesma tela-base: o **coordenador** monta um rascunho com os
 * contratos já assinados pelas duas partes e o envia; o **gerente** (role
 * `admin`) analisa o lote enviado, aprovando ou recusando contrato a contrato.
 *
 * Montar e enviar também acontece pelo kiosk (ver KioskController); a análise,
 * por decisão de processo, é só aqui na web.
 */
class BatchController extends Controller
{
    public function __construct(private FreelancerBatchService $batches)
    {
    }

    /* ---------------------------------------------------------------------
     | Coordenador — montagem do lote
     |---------------------------------------------------------------------*/

    public function index()
    {
        $this->coordinatorOrFail();

        $draft = $this->batches->existingDraftFor(request()->user());
        $draft?->load(['services.freelancer:id,name,cpf', 'services.functionFreelancer']);

        return view('freelancer.batches.index', [
            'draft' => $draft,
            'available' => FreelancerService::availableForBatch()
                ->with(['freelancer:id,name,cpf', 'functionFreelancer', 'managerRejectedBy'])
                ->orderByDesc('start_date')
                ->get(),
            // Histórico: tudo o que já saiu do rascunho, em qualquer etapa do
            // trâmite (gerência, diretoria ou encerrado).
            'history' => FreelancerServiceBatch::where('created_by', request()->user()->id)
                ->where('status', '!=', FreelancerServiceBatch::STATUS_DRAFT)
                ->withCount('services')
                ->with('reviewedBy')
                ->orderByDesc('id')
                ->limit(30)
                ->get(),
        ]);
    }

    public function addItems(Request $request)
    {
        $this->coordinatorOrFail();

        $request->validate([
            'services' => ['required', 'array'],
            'services.*' => ['integer', 'exists:freelancer_services,id'],
        ]);

        $batch = $this->batches->draftFor($request->user());
        $ids = array_map('intval', $request->input('services', []));

        try {
            $added = $this->batches->addServices($batch, $ids);
        } catch (FreelancerBatchException $e) {
            return $this->backToIndex()->with('error', $e->getMessage());
        }

        if ($added === 0) {
            return $this->backToIndex()->with('error', 'Nenhum contrato foi incluído — verifique se já não estão em outro lote.');
        }

        $redirect = $this->backToIndex()->with('success', $added === 1
            ? 'Contrato incluído no lote.'
            : "{$added} contratos incluídos no lote.");

        if ($added < count($ids)) {
            $redirect->with('warning', (count($ids) - $added) . ' contrato(s) foram ignorados por já não estarem disponíveis.');
        }

        return $redirect;
    }

    public function removeItem(FreelancerService $freelancerService)
    {
        $this->coordinatorOrFail();

        $draft = $this->batches->existingDraftFor(request()->user());

        if (!$draft) {
            return $this->backToIndex()->with('error', 'Não há lote em rascunho.');
        }

        try {
            $this->batches->removeService($draft, $freelancerService);
        } catch (FreelancerBatchException $e) {
            return $this->backToIndex()->with('error', $e->getMessage());
        }

        return $this->backToIndex()->with('success', 'Contrato retirado do lote.');
    }

    public function send(Request $request)
    {
        $this->coordinatorOrFail();

        $draft = $this->batches->existingDraftFor($request->user());

        if (!$draft) {
            return $this->backToIndex()->with('error', 'Não há lote em rascunho para enviar.');
        }

        try {
            $this->batches->send($draft, $request->user());
        } catch (FreelancerBatchException $e) {
            return $this->backToIndex()->with('error', $e->getMessage());
        }

        return redirect()->route('freelancer-batches.show', $draft)
            ->with('success', 'Lote enviado para a aprovação da gerência.');
    }

    public function discard(Request $request)
    {
        $this->coordinatorOrFail();

        $draft = $this->batches->existingDraftFor($request->user());

        if (!$draft) {
            return $this->backToIndex()->with('error', 'Não há lote em rascunho.');
        }

        try {
            $this->batches->discard($draft);
        } catch (FreelancerBatchException $e) {
            return $this->backToIndex()->with('error', $e->getMessage());
        }

        return $this->backToIndex()->with('success', 'Rascunho descartado; os contratos voltaram para a fila.');
    }

    /* ---------------------------------------------------------------------
     | Gerente — análise
     |---------------------------------------------------------------------*/

    /** Fila da gerência: o que aguarda análise e o que aguarda a diretoria. */
    public function approvalQueue()
    {
        abort_unless($this->isManager(), 403);

        return view('freelancer.batches.queue', [
            'batches' => FreelancerServiceBatch::awaitingManager()
                ->withCount('services')
                ->withSum('services', 'price')
                ->with('createdBy')
                ->orderBy('sent_at')
                ->get(),
            // Já aprovados pela gerência: falta o PIN ditado pela diretoria.
            'awaitingDirector' => FreelancerServiceBatch::awaitingDirector()
                ->withCount('services')
                ->withSum('services', 'price')
                ->with(['createdBy', 'reviewedBy'])
                ->orderBy('reviewed_at')
                ->get(),
        ]);
    }

    public function show(FreelancerServiceBatch $batch)
    {
        $isManager = $this->isManager();

        // O coordenador acompanha os lotes que montou; o gerente vê todos.
        abort_unless($isManager || $batch->created_by === request()->user()->id, 403);

        $batch->load([
            'services.freelancer:id,name,cpf,pix_key',
            'services.functionFreelancer',
            'services.managerRejectedBy',
            'services.managerApprovedBy',
            'createdBy',
            'reviewedBy',
        ]);

        return view('freelancer.batches.show', [
            'batch' => $batch,
            'canReview' => $isManager && $batch->canBeReviewed(),
            // A gerência é quem digita o PIN que o diretor ditou.
            'canRecordDirector' => $isManager && $batch->isAwaitingDirector(),
            'directorEmail' => config('freelancers.director.email'),
        ]);
    }

    public function review(ReviewFreelancerBatchRequest $request, FreelancerServiceBatch $batch)
    {
        try {
            $result = $this->batches->reviewAndNotify($batch, $request->user(), $request->decisions());
        } catch (FreelancerBatchException $e) {
            return redirect()->route('freelancer-batches.show', $batch)->with('error', $e->getMessage());
        }

        $redirect = redirect()->route('freelancer-batches.show', $batch);

        $message = "Lote analisado: {$result['approved']} aprovado(s)";

        if ($result['rejected'] > 0) {
            $message .= ", {$result['rejected']} recusado(s) — os recusados voltaram para o coordenador";
        }

        if ($result['approved'] === 0) {
            return $redirect->with('success', $message . '. Nada seguiu para a diretoria.');
        }

        if ($result['notified']) {
            $message .= '. E-mail enviado à diretoria — peça o código ao diretor e informe abaixo.';

            return $redirect->with('success', $message);
        }

        // A aprovação foi gravada; só o e-mail falhou. O lote fica em
        // "aguardando envio" e o botão de reenviar aparece na tela.
        return $redirect
            ->with('success', $message . '.')
            ->with('warning', 'O e-mail para a diretoria NÃO foi enviado: ' . $result['error']
                . ' Corrija o problema e use "Reenviar à diretoria".');
    }

    /* ---------------------------------------------------------------------
     | Diretoria — envio do e-mail e registro do PIN ditado
     |---------------------------------------------------------------------*/

    public function notifyDirector(FreelancerServiceBatch $batch)
    {
        abort_unless($this->isManager(), 403);

        $redirect = redirect()->route('freelancer-batches.show', $batch);

        try {
            $this->batches->notifyDirector($batch);
        } catch (FreelancerBatchException $e) {
            return $redirect->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return $redirect->with('error', 'Falha ao enviar o e-mail: ' . $e->getMessage());
        }

        return $redirect->with('success', 'E-mail enviado à diretoria (' . $batch->fresh()->director_email . ').');
    }

    /**
     * A gerência digita o PIN que o diretor ditou. Errar o PIN não diz qual dos
     * dois estava errado — a mensagem é sempre a mesma, e as tentativas são
     * limitadas por rota.
     */
    public function directorDecision(Request $request, FreelancerServiceBatch $batch)
    {
        abort_unless($this->isManager(), 403);

        $request->validate([
            'pin' => ['required', 'digits:' . FreelancerServiceBatch::PIN_LENGTH],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['pin' => 'código da diretoria']);

        $redirect = redirect()->route('freelancer-batches.show', $batch);

        try {
            $decision = $this->batches->applyDirectorPin(
                $batch,
                $request->user(),
                $request->input('pin'),
                $request->input('note'),
            );
        } catch (FreelancerBatchException $e) {
            return $redirect->with('error', $e->getMessage());
        }

        if ($decision === null) {
            return $redirect->with('error', 'Código não confere. Confirme com o diretor o código do lote #' . $batch->id . '.');
        }

        if ($decision === FreelancerServiceBatch::DECISION_APPROVED) {
            return $redirect->with('success', 'Diretoria APROVOU o lote. Os contratos já podem ser pagos pelo financeiro.');
        }

        return $redirect->with('success', 'Diretoria RECUSOU o lote. Os contratos voltaram para a fila do coordenador.');
    }

    /* ---------------------------------------------------------------------
     | Auxiliares
     |---------------------------------------------------------------------*/

    private function isManager(): bool
    {
        return request()->user()?->hasRole('admin') ?? false;
    }

    /** Montar lote é atribuição de coordenador — de qualquer setor, ao contrário
     *  da assinatura, que é só do Comercial e só no tablet. */
    private function coordinatorOrFail(): void
    {
        abort_unless(request()->user()?->isCoordinator() ?? false, 403);
    }

    private function backToIndex()
    {
        return redirect()->route('freelancer-batches.index');
    }
}
