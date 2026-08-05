<?php

namespace App\Http\Controllers\Freelancer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFunctionFreelancerRequest;
use App\Http\Requests\UpdateFunctionFreelancerRequest;
use App\Models\FunctionFreelancer;
use App\Services\FreelancerService as FreelancerServiceManager;

class FunctionController extends Controller
{
    public function __construct(private FreelancerServiceManager $freelancerService)
    {
    }

    public function index()
    {
        $functions = FunctionFreelancer::withCount(['freelancerServices', 'employeeCaches'])
            ->with('cacheRates')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return view('freelancer.functions.index', compact('functions'));
    }

    public function create()
    {
        return view('freelancer.functions.create');
    }

    public function store(StoreFunctionFreelancerRequest $request)
    {
        $function = $this->freelancerService->createFunction($request->validated());

        return redirect()->route('freelancer-functions.show', $function)
            ->with('success', 'Função "' . $function->name . '" cadastrada com sucesso.');
    }

    public function show(FunctionFreelancer $freelancerFunction)
    {
        $freelancerFunction->load(['createdBy', 'updatedBy', 'cacheRates']);

        return view('freelancer.functions.show', ['function' => $freelancerFunction]);
    }

    public function update(UpdateFunctionFreelancerRequest $request, FunctionFreelancer $freelancerFunction)
    {
        $this->freelancerService->updateFunction($freelancerFunction, $request->validated());

        return redirect()->route('freelancer-functions.show', $freelancerFunction)
            ->with('success', 'Função atualizada com sucesso.');
    }

    public function destroy(FunctionFreelancer $freelancerFunction)
    {
        if ($freelancerFunction->freelancerServices()->exists() || $freelancerFunction->employeeCaches()->exists()) {
            return redirect()->route('freelancer-functions.index')
                ->with('error', 'Não é possível excluir "' . $freelancerFunction->name . '" pois possui lançamentos vinculados.');
        }

        $name = $freelancerFunction->name;
        $freelancerFunction->delete();

        return redirect()->route('freelancer-functions.index')
            ->with('success', 'Função "' . $name . '" excluída com sucesso.');
    }
}
