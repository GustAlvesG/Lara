<?php

namespace App\Http\Controllers;

use App\Services\FreelancerService;

/**
 * Listagem de funções para a integração externa (bot do Telegram) — usada para
 * escolher a função ao registrar um serviço. O cadastro de funções é feito
 * apenas pelo painel interno.
 */
class FunctionFreelancerController extends Controller
{
    public function __construct(private FreelancerService $freelancerService)
    {
    }

    public function index()
    {
        try {
            $functions = $this->freelancerService->getFunctions();

            return response()->json($functions, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
