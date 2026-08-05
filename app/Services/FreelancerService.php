<?php

namespace App\Services;

use App\Exceptions\FreelancerServiceLockedException;
use App\Jobs\SendFreelancerPixPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Freelancer;
use App\Models\FunctionFreelancer;
use App\Models\FreelancerService as FreelancerServiceModel;
use App\Models\PixPayment;
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

    /** Só as funções de freelancer: as de cachê pertencem ao outro fluxo. */
    public function getFunctions()
    {
        return FunctionFreelancer::ofType(FunctionFreelancer::TYPE_FREELANCER)->orderBy('name')->get();
    }

    public function createFunction($data)
    {
        $data['created_by'] = $this->actorId($data);
        $data['updated_by'] = $data['created_by'];

        return DB::transaction(function () use ($data) {
            $rates = $this->pullCacheRates($data);
            $function = FunctionFreelancer::create($data);
            $this->syncCacheRates($function, $rates);

            return $function;
        });
    }

    public function updateFunction(FunctionFreelancer $function, $data)
    {
        $data['updated_by'] = $this->actorId($data, 'updated_by');

        return DB::transaction(function () use ($function, $data) {
            $rates = $this->pullCacheRates($data);

            // Trocar o tipo de uma função já usada mudaria a conta de
            // lançamentos existentes: o caminho é cadastrar outra função.
            if (isset($data['type']) && $data['type'] !== $function->type && !$function->canChangeType()) {
                unset($data['type']);
            }

            $function->update($data);
            $this->syncCacheRates($function->fresh(), $rates);

            return $function;
        });
    }

    /**
     * Separa as faixas de cachê do resto do payload — elas moram em outra
     * tabela e não são colunas de `function_freelancers`.
     *
     * @return array<int, mixed>|null
     */
    private function pullCacheRates(array &$data): ?array
    {
        $rates = $data['cache_rates'] ?? null;
        unset($data['cache_rates']);

        // Função de freelancer não carrega faixa; função de cachê não tem
        // preço por bloco. Zerar o campo do outro tipo evita que um valor
        // esquecido na tela seja lido depois como se valesse.
        if (($data['type'] ?? FunctionFreelancer::TYPE_FREELANCER) === FunctionFreelancer::TYPE_CACHE) {
            $data['price'] = null;
        } else {
            $rates = null;
        }

        return $rates;
    }

    /** Regrava as faixas de 2h a 11h da função de cachê. */
    private function syncCacheRates(FunctionFreelancer $function, ?array $rates): void
    {
        if (!$function->isCache()) {
            $function->cacheRates()->delete();

            return;
        }

        if ($rates === null) {
            return;
        }

        foreach (FunctionFreelancer::cacheHourRange() as $hours) {
            if (!isset($rates[$hours]) || $rates[$hours] === '') {
                continue;
            }

            $function->cacheRates()->updateOrCreate(
                ['hours' => $hours],
                ['price' => $rates[$hours]],
            );
        }
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

    /* ---------------------------------------------------------------------
     | Aditivo
     |---------------------------------------------------------------------*/

    /**
     * Cria o contrato aditivo de $base: mesma pessoa, mesma função, mesmo dia,
     * mudando só horário de início, horário de término e local. Preço, horas e
     * data de término são recalculados pelo mesmo caminho de um contrato comum
     * — o aditivo vale pelo turno INTEIRO, não pela diferença.
     *
     * Por isso o base é marcado como aditivado na mesma transação: os dois
     * documentos existem e os dois são assinados, mas só o aditivo é pago.
     *
     * @param  array{location: string, start_time: string, end_time: string}  $data
     * @throws FreelancerServiceLockedException quando o base não aceita aditivo
     */
    public function createAmendment(FreelancerServiceModel $base, array $data, ?User $actor = null)
    {
        if ($reason = $base->amendmentBlockReason()) {
            throw new FreelancerServiceLockedException($reason);
        }

        $actorId = $actor?->id ?? $this->actorId($data);

        return DB::transaction(function () use ($base, $data, $actorId) {
            $amendment = $this->createService([
                'freelancer_id' => $base->freelancer_id,
                'function_freelancer_id' => $base->function_freelancer_id,
                'parent_service_id' => $base->id,
                'start_date' => Carbon::parse($base->start_date)->toDateString(),
                'location' => $data['location'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'created_by' => $actorId,
            ]);

            $base->forceFill([
                'amended_at' => now(),
                'amendment_service_id' => $amendment->id,
                'updated_by' => $actorId ?? $base->updated_by,
            ])->save();

            return $amendment;
        });
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
     * Assinatura do coordenador — feita no kiosk, desenhada no tablet pelo
     * coordenador do setor Comercial. O painel não assina mais: quem grava o
     * traço e chama este método é o KioskController.
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
     * Cancelar um ADITIVO devolve o pagamento ao contrato base: ele deixa de
     * estar aditivado e volta ao lote e ao financeiro. Sem isso, um aditivo
     * criado por engano deixaria o turno sem nenhum contrato pagável.
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

        return DB::transaction(function () use ($service, $user) {
            $service->forceFill([
                'status_id' => FreelancerServiceModel::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $user?->id,
            ])->save();

            $base = $service->isAmendment() ? $service->baseService : null;

            if ($base && $base->amendment_service_id === $service->id) {
                $base->forceFill([
                    'amended_at' => null,
                    'amendment_service_id' => null,
                ])->save();
            }

            return $service;
        });
    }

    /**
     * Exclui um contrato ainda sem assinatura. Excluir um aditivo, como
     * cancelá-lo, devolve o contrato base à vida.
     *
     * @throws FreelancerServiceLockedException
     */
    public function deleteService(FreelancerServiceModel $service): void
    {
        if (!$service->canBeDeleted()) {
            throw new FreelancerServiceLockedException('Contrato assinado não pode ser excluído.');
        }

        DB::transaction(function () use ($service) {
            $base = $service->isAmendment() ? $service->baseService : null;

            if ($base && $base->amendment_service_id === $service->id) {
                $base->forceFill([
                    'amended_at' => null,
                    'amendment_service_id' => null,
                ])->save();
            }

            $service->delete();
        });
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
                'Só é possível dar baixa em contrato assinado pelas duas partes e aprovado pela gerência.'
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

    /* ---------------------------------------------------------------------
     | Pix automático (Sicoob)
     |
     | Com `sicoob.enabled`, o botão "Dar baixa" deixa de ser uma marcação e
     | passa a MOVER DINHEIRO. A diferença que organiza tudo aqui é: o clique
     | não paga nada — ele enfileira um pedido. A baixa (`paid`) só é gravada
     | quando o banco confirma que o Pix foi FINALIZADO, e é por isso que
     | `markAsPaidFromPix()` existe separado de `markAsPaid()`.
     |
     | Marcar na hora do clique deixaria a tela dizendo "pago" para um Pix que
     | o banco ainda pode recusar — e o financeiro não teria como saber.
     |---------------------------------------------------------------------*/

    /**
     * Enfileira o Pix dos contratos selecionados.
     *
     * Um `PixPayment` e um job por contrato: um pagamento que falha não derruba
     * os outros da mesma seleção, e cada linha da tela conta a própria história.
     *
     * @param  array<int>  $ids
     * @return array{queued: int, skipped: int, problems: array<int, string>}
     */
    public function requestPixForMany(array $ids, User $user): array
    {
        $resultado = ['queued' => 0, 'skipped' => 0, 'problems' => []];

        if (!$ids) {
            return $resultado;
        }

        // Os ids a despachar são coletados dentro da transação, mas os jobs só
        // são disparados DEPOIS do commit: um job que começa a rodar antes de a
        // linha existir no banco encontraria um `PixPayment` inexistente.
        $paraDespachar = DB::transaction(function () use ($ids, $user, &$resultado) {
            $services = FreelancerServiceModel::whereIn('id', $ids)
                ->awaitingFinance()
                ->where('paid', false)
                ->with('freelancer')
                ->lockForUpdate()
                ->get();

            $resultado['skipped'] = count($ids) - $services->count();

            $pagamentos = [];

            foreach ($services as $service) {
                $problema = $this->pixBlockReason($service);

                if ($problema !== null) {
                    $resultado['problems'][] = $problema;
                    $resultado['skipped']++;

                    continue;
                }

                $pagamentos[] = $this->createPixPayment($service, $user)->id;
            }

            return $pagamentos;
        });

        foreach ($paraDespachar as $pixPaymentId) {
            SendFreelancerPixPayment::dispatch($pixPaymentId);
            $resultado['queued']++;
        }

        return $resultado;
    }

    /**
     * Por que este contrato não pode ter Pix enviado agora — null quando pode.
     *
     * A trava que importa é a última: um contrato com pagamento em andamento,
     * já finalizado ou com desfecho desconhecido não aceita uma nova tentativa.
     * É ela que impede o duplo clique, a tela aberta em duas abas e o lote
     * reenviado por engano de virarem duas transferências.
     */
    public function pixBlockReason(FreelancerServiceModel $service): ?string
    {
        $freelancer = $service->freelancer;

        if (!$freelancer) {
            return "Contrato #{$service->id}: freelancer não encontrado.";
        }

        if (blank($freelancer->pix_key)) {
            return "{$freelancer->name}: sem chave PIX no cadastro.";
        }

        if ((float) $service->price <= 0) {
            return "{$freelancer->name}: contrato #{$service->id} sem valor.";
        }

        $max = (float) config('sicoob.pix.max_amount', 0);

        if ($max > 0 && (float) $service->price > $max) {
            return "{$freelancer->name}: valor de R$ " . number_format((float) $service->price, 2, ',', '.')
                . ' acima do teto por transferência (R$ ' . number_format($max, 2, ',', '.') . ').';
        }

        $emAndamento = PixPayment::where('freelancer_service_id', $service->id)->blocking()->first();

        if ($emAndamento) {
            return "{$freelancer->name}: já existe um Pix para o contrato #{$service->id} ("
                . $emAndamento->statusLabel() . '). Nenhum novo envio foi feito.';
        }

        return null;
    }

    /**
     * Cria a linha da tentativa. Ela nasce em `pending` e com todos os dados
     * congelados como estavam no clique — o cadastro do freelancer pode mudar
     * amanhã, a trilha de auditoria não.
     */
    protected function createPixPayment(FreelancerServiceModel $service, User $user): PixPayment
    {
        $freelancer = $service->freelancer;

        return PixPayment::create([
            'freelancer_service_id' => $service->id,
            'freelancer_id' => $freelancer->id,
            'idempotency_key' => (string) Str::uuid(),
            'pix_key' => $freelancer->pix_key,
            'amount' => $service->price,
            'description' => $this->pixDescription($service),
            'status' => PixPayment::STATUS_PENDING,
            'environment' => config('sicoob.environment'),
            'requested_by' => $user->id,
        ]);
    }

    /**
     * Texto que aparece no extrato das duas pontas (limite de 140 no schema da
     * API). Vale a pena ser específico: é por ele que o freelancer reconhece o
     * crédito e o contábil casa a saída com o contrato.
     */
    protected function pixDescription(FreelancerServiceModel $service): string
    {
        $data = $service->start_date ? Carbon::parse($service->start_date)->format('d/m/Y') : '';

        return mb_substr(trim("Serviço freelancer #{$service->id} {$data}"), 0, 140);
    }

    /**
     * Baixa do contrato a partir de um Pix que o banco deu como FINALIZADO.
     *
     * `paid_by` guarda quem CLICOU em "Dar baixa", não o processo que
     * confirmou: a responsabilidade pelo pagamento é de quem o autorizou.
     *
     * Idempotente de propósito — o job e a reconciliação podem chegar aqui
     * para o mesmo pagamento, e a segunda passagem não deve fazer nada.
     */
    public function markAsPaidFromPix(PixPayment $payment): ?FreelancerServiceModel
    {
        if (!$payment->isFinalized()) {
            return null;
        }

        return DB::transaction(function () use ($payment) {
            $service = FreelancerServiceModel::whereKey($payment->freelancer_service_id)
                ->lockForUpdate()
                ->first();

            if (!$service || $service->isPaid()) {
                return $service;
            }

            $service->forceFill([
                'paid' => true,
                'paid_at' => $payment->finalized_at ?? now(),
                'paid_by' => $payment->requested_by,
            ])->save();

            Log::channel('sicoob')->info('Sicoob: baixa registrada após Pix finalizado', [
                'freelancer_service_id' => $service->id,
                'pix_payment_id' => $payment->id,
                'end_to_end_id' => $payment->end_to_end_id,
                'valor' => (float) $payment->amount,
            ]);

            return $service;
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
