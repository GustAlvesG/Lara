<?php

namespace App\Http\Controllers\Freelancer;

use App\Exceptions\SpreadsheetImportException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportSpreadsheetRequest;
use App\Http\Requests\StoreFreelancerRequest;
use App\Http\Requests\UpdateFreelancerRequest;
use App\Imports\FreelancerImport;
use App\Models\Freelancer;
use App\Models\FreelancerService;
use App\Services\FreelancerService as FreelancerServiceManager;
use Illuminate\Http\Request;

class FreelancerController extends Controller
{
    public function __construct(private FreelancerServiceManager $freelancerService)
    {
    }

    public function index(Request $request)
    {
        $search = $request->query('q');

        $freelancers = Freelancer::withCount('freelancerServices')
            ->with('freelancerServices:id,freelancer_id,start_date,status_id')
            ->when($search, fn($query) => $query->where(fn($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('cpf', 'like', "%{$search}%")))
            ->orderBy('name')
            ->get();

        $excessFlags = FreelancerService::flagExcessWithinCollection(
            $freelancers->flatMap->freelancerServices
        );

        foreach ($freelancers as $freelancer) {
            $freelancer->exceeds_weekly_limit = $freelancer->freelancerServices
                ->contains(fn($service) => $excessFlags[$service->id] ?? false);
        }

        return view('freelancer.freelancers.index', compact('freelancers', 'search'));
    }

    public function create(FreelancerImport $import)
    {
        return view('freelancer.freelancers.create', ['importColumns' => $import->columns()]);
    }

    public function store(StoreFreelancerRequest $request)
    {
        $freelancer = $this->freelancerService->create($request->validated());

        return redirect()->route('freelancers.show', $freelancer)
            ->with('success', 'Freelancer "' . $freelancer->name . '" cadastrado com sucesso.');
    }

    /** Arquivo .xlsx em branco, no formato aceito pela importação. */
    public function importTemplate(FreelancerImport $import)
    {
        return $import->downloadTemplate();
    }

    public function import(ImportSpreadsheetRequest $request, FreelancerImport $import)
    {
        try {
            $result = $import->import($request->file('spreadsheet')->getRealPath());
        } catch (SpreadsheetImportException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($result['errors']) {
            return back()
                ->with('error', 'Nenhum freelancer foi importado. Corrija a planilha e envie novamente.')
                ->with('import_errors', $result['errors']);
        }

        return redirect()->route('freelancers.index')
            ->with('success', $result['imported'] . ' freelancer(s) importado(s) com sucesso.');
    }

    public function show(Freelancer $freelancer)
    {
        $freelancer->load([
            'freelancerServices.functionFreelancer',
            'freelancerServices.status',
            'createdBy',
            'updatedBy',
        ]);

        $excessFlags = FreelancerService::flagExcessWithinCollection($freelancer->freelancerServices);

        return view('freelancer.freelancers.show', compact('freelancer', 'excessFlags'));
    }

    public function update(UpdateFreelancerRequest $request, Freelancer $freelancer)
    {
        $this->freelancerService->updateFreelancer($freelancer, $request->validated());

        return redirect()->route('freelancers.show', $freelancer)
            ->with('success', 'Freelancer atualizado com sucesso.');
    }

    public function destroy(Freelancer $freelancer)
    {
        if ($freelancer->freelancerServices()->exists()) {
            return redirect()->route('freelancers.index')
                ->with('error', 'Não é possível excluir "' . $freelancer->name . '" pois possui serviços vinculados.');
        }

        $name = $freelancer->name;
        $freelancer->delete();

        return redirect()->route('freelancers.index')
            ->with('success', 'Freelancer "' . $name . '" excluído com sucesso.');
    }
}
