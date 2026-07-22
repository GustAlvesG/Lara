<?php

namespace App\Http\Controllers\Freelancer;

use App\Exceptions\FreelancerServiceLockedException;
use App\Exceptions\SpreadsheetImportException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportSpreadsheetRequest;
use App\Http\Requests\StoreFreelancerServiceRequest;
use App\Http\Requests\UpdateFreelancerServiceRequest;
use App\Imports\FreelancerServiceImport;
use App\Models\Freelancer;
use App\Models\FreelancerService;
use App\Models\FunctionFreelancer;
use App\Models\Status;
use App\Services\FreelancerService as FreelancerServiceManager;

class ServiceController extends Controller
{
    public function __construct(private FreelancerServiceManager $freelancerService)
    {
    }

    public function index()
    {
        $services = FreelancerService::with(['freelancer', 'functionFreelancer', 'status'])
            ->orderByDesc('start_date')
            ->get();

        $excessFlags = FreelancerService::flagExcessWithinCollection($services);

        return view('freelancer.services.index', compact('services', 'excessFlags'));
    }

    public function create(FreelancerServiceImport $import)
    {
        return view('freelancer.services.create', array_merge($this->formOptions(), [
            'importColumns' => $import->columns(),
        ]));
    }

    public function store(StoreFreelancerServiceRequest $request)
    {
        $data = $request->validated();

        // O aviso de limite semanal vem antes de gravar: o formulário volta
        // preenchido, pedindo uma confirmação explícita.
        if (!$request->boolean('confirm_weekly_limit')
            && FreelancerService::wouldExceedWeeklyLimit($data['freelancer_id'], $data['start_date'])) {
            return back()
                ->withInput()
                ->with('confirm_weekly_limit', $this->weeklyLimitConfirmMessage($data));
        }

        $service = $this->freelancerService->createService($data);

        return redirect()->route('freelancer-services.show', $service)
            ->with('success', 'Serviço registrado com sucesso.');
    }

    /** Arquivo .xlsx em branco, no formato aceito pela importação. */
    public function importTemplate(FreelancerServiceImport $import)
    {
        return $import->downloadTemplate();
    }

    public function import(ImportSpreadsheetRequest $request, FreelancerServiceImport $import)
    {
        try {
            $result = $import->import($request->file('spreadsheet')->getRealPath());
        } catch (SpreadsheetImportException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($result['errors']) {
            return back()
                ->with('error', 'Nenhum serviço foi importado. Corrija a planilha e envie novamente.')
                ->with('import_errors', $result['errors']);
        }

        $redirect = redirect()->route('freelancer-services.index')
            ->with('success', $result['imported'] . ' serviço(s) importado(s) com sucesso.');

        // O mesmo alerta do cadastro individual, resumido para o lote.
        $exceeding = $result['records']
            ->filter(fn(FreelancerService $service) => $service->exceedsWeeklyLimit())
            ->map(fn(FreelancerService $service) => $service->freelancer->name)
            ->unique()
            ->values();

        if ($exceeding->isNotEmpty()) {
            $redirect->with('warning', 'Acima do limite recomendado de '
                . FreelancerService::WEEKLY_LIMIT . ' serviços em 7 dias: ' . $exceeding->implode(', ') . '.');
        }

        return $redirect;
    }

    public function show(FreelancerService $freelancerService)
    {
        $freelancerService->load([
            'freelancer',
            'functionFreelancer',
            'status',
            'freelancerSignedBy',
            'coordinatorSignedBy',
            'paidBy',
            'cancelledBy',
            'createdBy',
            'updatedBy',
        ]);

        return view('freelancer.services.show', array_merge($this->formOptions(), [
            'service' => $freelancerService,
            'exceedsWeeklyLimit' => $freelancerService->exceedsWeeklyLimit(),
            'canSignAsCoordinator' => $this->isCoordinator() && $freelancerService->canBeSignedByCoordinator(),
            'canCancel' => $this->isCoordinator() && $freelancerService->canBeCancelled(),
        ]));
    }

    public function update(UpdateFreelancerServiceRequest $request, FreelancerService $freelancerService)
    {
        try {
            $this->freelancerService->updateService($freelancerService, $request->validated());
        } catch (FreelancerServiceLockedException $e) {
            return redirect()->route('freelancer-services.show', $freelancerService)
                ->with('error', $e->getMessage());
        }

        $redirect = redirect()->route('freelancer-services.show', $freelancerService)
            ->with('success', 'Serviço atualizado com sucesso.');

        if ($freelancerService->exceedsWeeklyLimit()) {
            $redirect->with('warning', $this->weeklyLimitMessage($freelancerService));
        }

        return $redirect;
    }

    /**
     * Assinatura do coordenador. Só quem é coordenador de algum setor assina.
     */
    public function sign(FreelancerService $freelancerService)
    {
        abort_unless($this->isCoordinator(), 403);

        try {
            $this->freelancerService->signAsCoordinator($freelancerService, auth()->user());
        } catch (FreelancerServiceLockedException $e) {
            return redirect()->route('freelancer-services.show', $freelancerService)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('freelancer-services.show', $freelancerService)
            ->with('success', 'Contrato assinado como coordenador. Ele não pode mais ser alterado.');
    }

    /**
     * Cancela o contrato — permitido apenas enquanto não houver assinaturas.
     */
    public function cancel(FreelancerService $freelancerService)
    {
        abort_unless($this->isCoordinator(), 403);

        try {
            $this->freelancerService->cancelService($freelancerService, auth()->user());
        } catch (FreelancerServiceLockedException $e) {
            return redirect()->route('freelancer-services.show', $freelancerService)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('freelancer-services.show', $freelancerService)
            ->with('success', 'Contrato cancelado.');
    }

    public function destroy(FreelancerService $freelancerService)
    {
        if (!$freelancerService->canBeDeleted()) {
            return redirect()->route('freelancer-services.show', $freelancerService)
                ->with('error', 'Contrato assinado não pode ser excluído.');
        }

        $freelancerService->delete();

        return redirect()->route('freelancer-services.index')
            ->with('success', 'Serviço excluído com sucesso.');
    }

    private function formOptions(): array
    {
        return [
            'freelancers' => Freelancer::orderBy('name')->get(['id', 'name']),
            'functions' => FunctionFreelancer::orderBy('name')->get(['id', 'name', 'price']),
            'statuses' => Status::orderBy('status')->get(),
        ];
    }

    private function isCoordinator(): bool
    {
        return auth()->user()?->isCoordinator() ?? false;
    }

    /**
     * Mensagem do aviso exibido antes da gravação, contando o serviço que está
     * prestes a ser criado.
     */
    private function weeklyLimitConfirmMessage(array $data): string
    {
        $freelancer = Freelancer::find($data['freelancer_id']);
        $total = FreelancerService::countInWeeklyWindow($data['freelancer_id'], $data['start_date']) + 1;

        return 'Com este registro, ' . ($freelancer?->name ?? 'o freelancer') . ' passa a ter ' . $total
            . ' serviços numa janela de 7 dias (limite recomendado: '
            . FreelancerService::WEEKLY_LIMIT . '). Confirme para prosseguir.';
    }

    private function weeklyLimitMessage(FreelancerService $service): string
    {
        return 'Atenção: ' . $service->freelancer->name . ' já possui mais de '
            . FreelancerService::WEEKLY_LIMIT . ' serviços registrados numa janela de 7 dias '
            . '(limite recomendado: ' . FreelancerService::WEEKLY_LIMIT . ').';
    }
}
