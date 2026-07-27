<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFreelancerRequest;
use App\Services\FreelancerService;

/**
 * Endpoints de Freelancer consumidos pela integração externa (bot do Telegram).
 * O CRUD do painel interno fica em App\Http\Controllers\Freelancer\*.
 */
class FreelancerController extends Controller
{
    public function __construct(private FreelancerService $freelancerService)
    {
    }

    /**
     * Consulta um freelancer pelo CPF. É o primeiro passo do fluxo do bot:
     * se retornar 404, o cadastro deve ser feito via store().
     */
    public function show($cpf)
    {
        $freelancer = $this->freelancerService->get($cpf);

        if (!$freelancer) {
            return response()->json(['error' => 'Freelancer não encontrado'], 404);
        }

        return response()->json($freelancer, 200);
    }

    /**
     * Cadastra um freelancer (usado quando a consulta por CPF não encontra).
     */
    public function store(StoreFreelancerRequest $request)
    {
        try {
            $freelancer = $this->freelancerService->create($request->validated());

            return response()->json($freelancer, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
