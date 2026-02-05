<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmailRequest;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailController extends Controller
{
    protected EmailService $emailService;

    // Injeção de Dependência do Serviço
    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    public function submit(Request $request)
    {
        // O $request->validated() retorna apenas os campos aprovados nas regras
        $data = $request->all();
        try {
            // Chama a lógica de negócio
            $this->emailService->processContactForm($data);

            return response()->json([
                'success' => true,
                'message' => 'Mensagem enviada com sucesso!'
            ], 200);

        } catch (\Exception $e) {
            // Log do erro real para o desenvolvedor ver no laravel.log
            // \Log::error("Erro no formulário de contato: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ocorreu um erro ao enviar sua mensagem. Tente novamente.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}