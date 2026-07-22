<?php

namespace App\Http\Controllers;

use App\Exceptions\FreelancerServiceLockedException;
use App\Exceptions\PasswordConfirmationException;
use App\Http\Controllers\Concerns\ConfirmsUserPassword;
use App\Http\Requests\SignFreelancerServiceRequest;
use App\Http\Requests\StoreFreelancerServiceRequest;
use App\Http\Requests\UpdateFreelancerServiceRequest;
use App\Models\Freelancer;
use App\Models\FreelancerService;
use App\Services\FreelancerService as FreelancerServiceManager;

/**
 * Endpoints de serviços/contratos consumidos pela integração externa (bot do
 * Telegram). O CRUD do painel interno fica em App\Http\Controllers\Freelancer\*.
 */
class FreelancerServiceController extends Controller
{
    use ConfirmsUserPassword;

    public function __construct(private FreelancerServiceManager $freelancerService)
    {
    }

    /**
     * Lista os serviços de um freelancer a partir do CPF.
     */
    public function indexByCpf(string $cpf)
    {
        $services = $this->freelancerService->getServicesByCpf($cpf);

        if ($services === null) {
            return response()->json(['error' => 'Freelancer não encontrado'], 404);
        }

        return response()->json($services->map(fn($service) => $this->present($service)), 200);
    }

    /**
     * Registra um serviço. O preço é calculado no servidor (por bloco de 15min).
     *
     * `created_by` é obrigatório aqui: é o login que auxiliou o preenchimento.
     * Passando do limite semanal recomendado, o cadastro só é gravado com
     * `confirm_weekly_limit` e a senha desse usuário — o bot devolve 409 na
     * primeira tentativa justamente para pedir essa confirmação.
     */
    public function store(StoreFreelancerServiceRequest $request)
    {
        $data = $request->validated();
        $userId = $data['created_by'] ?? null;

        if (!$userId) {
            return response()->json([
                'error' => 'Informe em created_by o usuário logado que está preenchendo o contrato.',
            ], 422);
        }

        if (FreelancerService::wouldExceedWeeklyLimit($data['freelancer_id'], $data['start_date'])) {
            if (!$request->boolean('confirm_weekly_limit')) {
                return response()->json($this->weeklyLimitPayload($data), 409);
            }

            try {
                $this->confirmUserPassword($userId, $request->input('password'));
            } catch (PasswordConfirmationException $e) {
                return response()->json(['error' => $e->getMessage()], 401);
            }
        }

        try {
            $service = $this->freelancerService->createService($data);

            return response()->json($this->present($service), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Atualiza um serviço. Bloqueado se o contrato já foi assinado ou cancelado.
     */
    public function update(UpdateFreelancerServiceRequest $request, FreelancerService $freelancerService)
    {
        try {
            $this->freelancerService->updateService($freelancerService, $request->validated());
        } catch (FreelancerServiceLockedException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json($this->present($freelancerService->fresh()), 200);
    }

    /**
     * Registra a assinatura do freelancer no contrato. Exige a reconfirmação
     * da senha do usuário logado no bot, que fica gravado como quem auxiliou
     * a assinatura.
     */
    public function sign(SignFreelancerServiceRequest $request, FreelancerService $freelancerService)
    {
        try {
            $user = $this->confirmUserPassword(
                $request->validated('user_id'),
                $request->validated('password')
            );
        } catch (PasswordConfirmationException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }

        try {
            $this->freelancerService->signAsFreelancer($freelancerService, $user);
        } catch (FreelancerServiceLockedException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json($this->present($freelancerService->fresh()), 200);
    }

    /**
     * Resposta que pede a confirmação por senha antes de gravar um serviço
     * acima do limite recomendado de 7 dias.
     */
    private function weeklyLimitPayload(array $data): array
    {
        $count = FreelancerService::countInWeeklyWindow($data['freelancer_id'], $data['start_date']);
        $freelancer = Freelancer::find($data['freelancer_id']);

        return [
            'error' => 'Limite semanal recomendado excedido',
            'requires_confirmation' => true,
            'confirmation_field' => 'confirm_weekly_limit',
            'requires_password' => true,
            'weekly_limit' => FreelancerService::WEEKLY_LIMIT,
            'services_in_window' => $count,
            'services_after_save' => $count + 1,
            'message' => 'Com este registro, ' . ($freelancer?->name ?? 'o freelancer') . ' passa a ter '
                . ($count + 1) . ' serviços numa janela de 7 dias (limite recomendado: '
                . FreelancerService::WEEKLY_LIMIT . '). Reenvie com confirm_weekly_limit=true e a senha '
                . 'do usuário logado para confirmar.',
        ];
    }

    /**
     * Formato de saída do serviço para a integração, incluindo o estado das
     * assinaturas e o que ainda pode ser feito com o contrato.
     */
    private function present(FreelancerService $service): array
    {
        $service->loadMissing(['functionFreelancer', 'status', 'freelancerSignedBy', 'createdBy']);

        return [
            'id' => $service->id,
            'freelancer_id' => $service->freelancer_id,
            'function_freelancer_id' => $service->function_freelancer_id,
            'function' => $service->functionFreelancer?->name,
            'location' => $service->location,
            'start_date' => $service->start_date?->toDateString(),
            'start_time' => substr($service->start_time, 0, 5),
            // end_date é derivado: igual a start_date, ou +1 dia quando o turno
            // vira a meia-noite (end_time <= start_time).
            'end_date' => $service->end_date?->toDateString(),
            'end_time' => substr($service->end_time, 0, 5),
            'crosses_midnight' => $service->start_date?->ne($service->end_date) ?? false,
            'duration_minutes' => $service->durationInMinutes(),
            'total_hours' => $service->total_hours,
            'price' => $service->price,
            'status_id' => $service->status_id,
            'status' => $service->status?->status,
            'freelancer_signed_at' => $service->freelancer_signed_at?->toDateTimeString(),
            // Logins que auxiliaram o freelancer no preenchimento e na assinatura.
            'created_by' => $service->created_by,
            'created_by_name' => $service->createdBy?->name,
            'freelancer_signed_by' => $service->freelancer_signed_by,
            'freelancer_signed_by_name' => $service->freelancerSignedBy?->name,
            'coordinator_signed_at' => $service->coordinator_signed_at?->toDateTimeString(),
            'cancelled_at' => $service->cancelled_at?->toDateTimeString(),
            'signature_status' => $service->signatureLabel(),
            'can_be_updated' => $service->canBeUpdated(),
            'can_be_signed_by_freelancer' => $service->canBeSignedByFreelancer(),
        ];
    }
}
