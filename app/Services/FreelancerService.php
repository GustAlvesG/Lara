<?php

namespace App\Services;

use App\Exceptions\FreelancerServiceLockedException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Freelancer;
use App\Models\FunctionFreelancer;
use App\Models\FreelancerService as FreelancerServiceModel;
use App\Models\User;

class FreelancerService
{
    /* ---------------------------------------------------------------------
     | Freelancers
     |---------------------------------------------------------------------*/

    public function create($data)
    {
        $data['created_by'] = $this->actorId($data);
        $data['updated_by'] = $data['created_by'];

        return Freelancer::create($data);
    }

    public function get($cpf)
    {
        return Freelancer::where('cpf', $cpf)->first();
    }

    public function updateFreelancer(Freelancer $freelancer, $data)
    {
        $data['updated_by'] = $this->actorId($data, 'updated_by');

        $freelancer->update($data);

        return $freelancer;
    }

    /* ---------------------------------------------------------------------
     | Funções
     |---------------------------------------------------------------------*/

    public function getFunctions()
    {
        return FunctionFreelancer::orderBy('name')->get();
    }

    public function createFunction($data)
    {
        $data['created_by'] = $this->actorId($data);
        $data['updated_by'] = $data['created_by'];

        return FunctionFreelancer::create($data);
    }

    public function updateFunction(FunctionFreelancer $function, $data)
    {
        $data['updated_by'] = $this->actorId($data, 'updated_by');

        $function->update($data);

        return $function;
    }

    /* ---------------------------------------------------------------------
     | Serviços
     |---------------------------------------------------------------------*/

    public function createService($data)
    {
        $data = $this->withSchedule($this->withoutEmptyStatus($data));
        $data['created_by'] = $this->actorId($data);
        $data['updated_by'] = $data['created_by'];

        return FreelancerServiceModel::create($data);
    }

    /**
     * @throws FreelancerServiceLockedException quando o contrato já foi
     *         assinado ou cancelado e, portanto, não aceita mais alterações.
     */
    public function updateService(FreelancerServiceModel $service, $data)
    {
        $this->assertUpdatable($service);

        $data = $this->withSchedule($this->withoutEmptyStatus($data));
        $data['updated_by'] = $this->actorId($data, 'updated_by');

        $service->update($data);

        return $service;
    }

    public function getServicesByCpf(string $cpf)
    {
        $freelancer = $this->get($cpf);

        if (!$freelancer) {
            return null;
        }

        return $freelancer->freelancerServices()
            ->with(['functionFreelancer', 'status'])
            ->orderByDesc('start_date')
            ->get();
    }

    /**
     * O valor de uma função é cobrado por bloco de 15 minutos, não por hora,
     * e só blocos integralmente cumpridos são pagos (arredondamento para baixo).
     */
    public function calculatePrice(int $functionFreelancerId, string $startTime, string $endTime): float
    {
        $function = FunctionFreelancer::findOrFail($functionFreelancerId);

        return $function->price * FreelancerServiceModel::billedBlocks($startTime, $endTime);
    }

    /**
     * Deriva os campos que não são digitados: total de horas pagas, data de
     * término (start_date, ou +1 dia quando o turno vira a meia-noite) e preço.
     */
    private function withSchedule(array $data): array
    {
        $blocks = FreelancerServiceModel::billedBlocks($data['start_time'], $data['end_time']);
        $crossesMidnight = FreelancerServiceModel::crossesMidnight($data['start_time'], $data['end_time']);

        $data['total_hours'] = $blocks * (FreelancerServiceModel::BLOCK_MINUTES / 60);
        $data['end_date'] = Carbon::parse($data['start_date'])
            ->addDays($crossesMidnight ? 1 : 0)
            ->toDateString();
        $data['price'] = $this->calculatePrice(
            $data['function_freelancer_id'],
            $data['start_time'],
            $data['end_time']
        );

        return $data;
    }

    /* ---------------------------------------------------------------------
     | Assinaturas e cancelamento
     |---------------------------------------------------------------------*/

    /**
     * Assinatura do freelancer — feita pela API (bot do Telegram), sempre com
     * um usuário do sistema acompanhando ($assistedBy).
     *
     * @throws FreelancerServiceLockedException
     */
    public function signAsFreelancer(FreelancerServiceModel $service, ?User $assistedBy = null)
    {
        if ($service->isCancelled()) {
            throw new FreelancerServiceLockedException('Contrato cancelado não pode ser assinado.');
        }

        if (!$service->canBeSignedByFreelancer()) {
            throw new FreelancerServiceLockedException('Contrato já assinado pelo freelancer.');
        }

        $service->forceFill([
            'freelancer_signed_at' => now(),
            // Quem assina é o freelancer; guardamos o login que conduziu o
            // atendimento e reconfirmou a senha no momento da assinatura.
            'freelancer_signed_by' => $assistedBy?->id,
        ])->save();

        return $service;
    }

    /**
     * Assinatura do coordenador — feita pelo painel, por um usuário que seja
     * coordenador de algum setor.
     *
     * @throws FreelancerServiceLockedException
     */
    public function signAsCoordinator(FreelancerServiceModel $service, User $user)
    {
        if ($service->isCancelled()) {
            throw new FreelancerServiceLockedException('Contrato cancelado não pode ser assinado.');
        }

        if (!$service->canBeSignedByCoordinator()) {
            throw new FreelancerServiceLockedException('Contrato já assinado pelo coordenador.');
        }

        $service->forceFill([
            'coordinator_signed_at' => now(),
            'coordinator_signed_by' => $user->id,
        ])->save();

        return $service;
    }

    /**
     * Cancela o contrato. Só é possível enquanto não houver nenhuma assinatura.
     *
     * @throws FreelancerServiceLockedException
     */
    public function cancelService(FreelancerServiceModel $service, ?User $user = null)
    {
        if ($service->isCancelled()) {
            throw new FreelancerServiceLockedException('Contrato já está cancelado.');
        }

        if (!$service->canBeCancelled()) {
            throw new FreelancerServiceLockedException('Contrato já assinado não pode ser cancelado.');
        }

        $service->forceFill([
            'status_id' => FreelancerServiceModel::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $user?->id,
        ])->save();

        return $service;
    }

    /* ---------------------------------------------------------------------
     | Financeiro
     |---------------------------------------------------------------------*/

    /**
     * Baixa de pagamento, dada pelo financeiro. Registra quem deu e quando.
     *
     * @throws FreelancerServiceLockedException
     */
    public function markAsPaid(FreelancerServiceModel $service, User $user)
    {
        if (!$service->isPayable()) {
            throw new FreelancerServiceLockedException(
                'Só é possível dar baixa em contrato assinado pelo freelancer e pelo coordenador.'
            );
        }

        if ($service->isPaid()) {
            throw new FreelancerServiceLockedException('Este contrato já teve a baixa de pagamento registrada.');
        }

        $service->forceFill([
            'paid' => true,
            'paid_at' => now(),
            'paid_by' => $user->id,
        ])->save();

        return $service;
    }

    /**
     * Baixa em lote. Contratos que não estão aptos (não assinados pelas duas
     * partes, cancelados ou já pagos) são simplesmente ignorados — a tela pode
     * ter ficado aberta enquanto outra pessoa dava a baixa.
     *
     * @param  array<int>  $ids
     * @return int quantidade de contratos efetivamente baixados
     */
    public function markManyAsPaid(array $ids, User $user): int
    {
        if (!$ids) {
            return 0;
        }

        return DB::transaction(function () use ($ids, $user) {
            $services = FreelancerServiceModel::whereIn('id', $ids)
                ->awaitingFinance()
                ->where('paid', false)
                ->lockForUpdate()
                ->get();

            $services->each(fn(FreelancerServiceModel $service) => $this->markAsPaid($service, $user));

            return $services->count();
        });
    }

    /**
     * @throws FreelancerServiceLockedException
     */
    public function assertUpdatable(FreelancerServiceModel $service): void
    {
        if ($service->isCancelled()) {
            throw new FreelancerServiceLockedException('Contrato cancelado não pode ser alterado.');
        }

        if ($service->isSigned()) {
            throw new FreelancerServiceLockedException('Contrato já assinado não pode ser alterado.');
        }
    }

    /* ---------------------------------------------------------------------
     | Auxiliares
     |---------------------------------------------------------------------*/

    /**
     * Quem está executando a ação: o usuário logado no painel ou, quando a
     * chamada vem da API (sem sessão), o id informado no próprio payload.
     */
    private function actorId(array $data, string $key = 'created_by'): ?int
    {
        return auth()->id() ?? ($data[$key] ?? null);
    }

    /**
     * status_id é NOT NULL com default no banco; se vier vazio, deixamos o
     * default do model/coluna assumir em vez de gravar null.
     */
    private function withoutEmptyStatus(array $data): array
    {
        if (array_key_exists('status_id', $data) && $data['status_id'] === null) {
            unset($data['status_id']);
        }

        return $data;
    }
}
