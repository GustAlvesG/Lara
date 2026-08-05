<?php

namespace App\Services;

use App\Exceptions\EmployeeCacheException;
use App\Models\EmployeeCache;
use App\Models\EmployeeCacheBatch;
use App\Models\FunctionFreelancer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * O trâmite do cachê de funcionário, de ponta a ponta.
 *
 * Este é o único lugar que grava os carimbos do fluxo: solicitação, aprovação
 * da gerência, assinatura do funcionário, reconferência da divergência e
 * baixa. Os controllers só perguntam e mostram — assim a regra não se
 * multiplica entre o painel e a tela de assinatura, que não compartilham nem
 * sessão.
 */
class EmployeeCacheService
{
    /* ---------------------------------------------------------------------
     | Solicitação (coordenador)
     |---------------------------------------------------------------------*/

    /**
     * Cria o lote e suas linhas de uma vez, em transação: uma solicitação é
     * tudo-ou-nada, como o registro em massa dos contratos. Meia solicitação
     * gravada faria o coordenador reenviar e duplicar o resto.
     *
     * @param  array<int, array>  $rows
     * @throws EmployeeCacheException
     */
    public function createBatch(User $coordinator, array $rows, ?int $sectorId = null, ?string $title = null): EmployeeCacheBatch
    {
        if (empty($rows)) {
            throw new EmployeeCacheException('Inclua ao menos um funcionário na solicitação.');
        }

        return DB::transaction(function () use ($coordinator, $rows, $sectorId, $title) {
            $batch = EmployeeCacheBatch::create([
                'status' => EmployeeCacheBatch::STATUS_DRAFT,
                'sector_id' => $sectorId,
                'title' => $title,
                'created_by' => $coordinator->id,
            ]);

            foreach ($rows as $row) {
                $this->addRow($batch, $row, $coordinator);
            }

            return $batch;
        });
    }

    /**
     * Acrescenta uma linha ao rascunho, já com a faixa e o valor calculados.
     *
     * @throws EmployeeCacheException
     */
    public function addRow(EmployeeCacheBatch $batch, array $row, User $user): EmployeeCache
    {
        $this->assertEditable($batch);

        $function = FunctionFreelancer::with('cacheRates')->findOrFail($row['function_freelancer_id']);

        if (!$function->isCache()) {
            throw new EmployeeCacheException("A função \"{$function->name}\" não é de cachê.");
        }

        $minutes = EmployeeCache::minutesBetween($row['start_time'], $row['end_time']);
        $hours = FunctionFreelancer::cacheBilledHours($minutes);
        $price = $function->priceForHours($hours);

        if ($price === null) {
            throw new EmployeeCacheException(
                "A função \"{$function->name}\" não tem valor cadastrado para a faixa de {$hours}h."
            );
        }

        return EmployeeCache::create([
            'batch_id' => $batch->id,
            'employee_id' => $row['employee_id'],
            'function_freelancer_id' => $function->id,
            'location' => $row['location'],
            'description' => $row['description'] ?? null,
            'event_date' => $row['event_date'],
            'expected_start_time' => EmployeeCache::normalizeTime($row['start_time']),
            'expected_end_time' => EmployeeCache::normalizeTime($row['end_time']),
            'expected_end_date' => EmployeeCache::endDateFor($row['event_date'], $row['start_time'], $row['end_time']),
            'expected_hours' => $hours,
            'expected_price' => $price,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    /** @throws EmployeeCacheException */
    public function removeRow(EmployeeCacheBatch $batch, EmployeeCache $cache): void
    {
        $this->assertEditable($batch);

        if ($cache->batch_id !== $batch->id) {
            throw new EmployeeCacheException('Este cachê não pertence a este lote.');
        }

        $cache->delete();
    }

    /** @throws EmployeeCacheException */
    public function send(EmployeeCacheBatch $batch): EmployeeCacheBatch
    {
        if (!$batch->isDraft()) {
            throw new EmployeeCacheException('Esta solicitação já foi enviada.');
        }

        if (!$batch->canBeSent()) {
            throw new EmployeeCacheException('Inclua ao menos um funcionário antes de enviar.');
        }

        $batch->forceFill([
            'status' => EmployeeCacheBatch::STATUS_SENT,
            'sent_at' => now(),
        ])->save();

        return $batch;
    }

    /** @throws EmployeeCacheException */
    public function discard(EmployeeCacheBatch $batch): void
    {
        if (!$batch->canBeDiscarded()) {
            throw new EmployeeCacheException('Só uma solicitação em rascunho pode ser descartada.');
        }

        DB::transaction(function () use ($batch) {
            $batch->caches()->delete();
            $batch->delete();
        });
    }

    /* ---------------------------------------------------------------------
     | 1ª aprovação (gerência)
     |---------------------------------------------------------------------*/

    /**
     * A gerência decide item a item. Omissão é aprovação: a tela manda todas as
     * linhas, e uma linha que se perdeu no formulário não pode virar recusa
     * silenciosa.
     *
     * @param  array<int, array{decision: string, reason?: string|null}>  $decisions
     * @return array{approved: int, rejected: int}
     * @throws EmployeeCacheException
     */
    public function review(EmployeeCacheBatch $batch, User $manager, array $decisions): array
    {
        if (!$batch->canBeReviewed()) {
            throw new EmployeeCacheException(
                $batch->isDraft()
                    ? 'Esta solicitação ainda não foi enviada para a gerência.'
                    : 'Esta solicitação já foi analisada.'
            );
        }

        return DB::transaction(function () use ($batch, $manager, $decisions) {
            $caches = $batch->caches()->lockForUpdate()->get();
            $approved = 0;
            $rejected = 0;

            foreach ($caches as $cache) {
                $entry = $decisions[$cache->id] ?? [];

                if (($entry['decision'] ?? 'approve') === 'reject') {
                    $cache->forceFill([
                        'manager_approved_at' => null,
                        'manager_approved_by' => null,
                        'manager_rejected_at' => now(),
                        'manager_rejected_by' => $manager->id,
                        'manager_rejection_reason' => $entry['reason'] ?? null,
                    ])->save();
                    $rejected++;

                    continue;
                }

                $cache->forceFill([
                    'manager_approved_at' => now(),
                    'manager_approved_by' => $manager->id,
                    'manager_rejected_at' => null,
                    'manager_rejected_by' => null,
                    'manager_rejection_reason' => null,
                ])->save();
                $approved++;
            }

            $batch->forceFill([
                'status' => $approved > 0
                    ? EmployeeCacheBatch::STATUS_MANAGER_REVIEWED
                    : EmployeeCacheBatch::STATUS_CLOSED,
                'reviewed_by' => $manager->id,
                'reviewed_at' => now(),
            ])->save();

            return ['approved' => $approved, 'rejected' => $rejected];
        });
    }

    /* ---------------------------------------------------------------------
     | Assinatura (funcionário)
     |---------------------------------------------------------------------*/

    /**
     * O funcionário informa o horário que de fato cumpriu e assina.
     *
     * O valor é recalculado aqui pelo horário REAL — o previsto não é
     * reaproveitado, senão um turno que esticou continuaria sendo pago pelo que
     * se imaginou na véspera.
     *
     * O caminho da imagem entra no mesmo save que o carimbo da assinatura: em
     * dois saves, uma falha no segundo deixaria o cachê assinado sem o traço, e
     * assinatura não se repete.
     *
     * @throws EmployeeCacheException
     */
    public function signByEmployee(
        EmployeeCache $cache,
        string $startTime,
        string $endTime,
        ?string $signaturePath = null,
    ): EmployeeCache {
        if (!$cache->canBeSignedByEmployee()) {
            throw new EmployeeCacheException(match (true) {
                $cache->isCancelled() => 'Este cachê foi cancelado.',
                $cache->isSigned() => 'Este cachê já foi assinado.',
                default => 'Este cachê ainda não foi aprovado pela gerência.',
            });
        }

        $function = $cache->functionFreelancer()->with('cacheRates')->first();
        $minutes = EmployeeCache::minutesBetween($startTime, $endTime);
        $hours = FunctionFreelancer::cacheBilledHours($minutes);
        $price = $function?->priceForHours($hours);

        if ($price === null) {
            throw new EmployeeCacheException(
                "A função deste cachê não tem valor cadastrado para a faixa de {$hours}h. Procure o coordenador."
            );
        }

        $cache->forceFill([
            'actual_start_time' => EmployeeCache::normalizeTime($startTime),
            'actual_end_time' => EmployeeCache::normalizeTime($endTime),
            'actual_end_date' => EmployeeCache::endDateFor($cache->event_date, $startTime, $endTime),
            'hours' => $hours,
            'price' => $price,
            'employee_signature_path' => $signaturePath,
            'employee_signed_at' => now(),
        ])->save();

        return $cache;
    }

    /* ---------------------------------------------------------------------
     | 2ª aprovação — reconferência da divergência
     |
     | Só existe quando o horário real ficou diferente do previsto. Sem
     | divergência o cachê vai direto ao financeiro: o que a gerência aprovou
     | foi exatamente o que aconteceu.
     |---------------------------------------------------------------------*/

    /** @throws EmployeeCacheException */
    public function recheckAsCoordinator(EmployeeCache $cache, User $coordinator): EmployeeCache
    {
        if (!$cache->awaitsCoordinatorRecheck()) {
            throw new EmployeeCacheException($this->recheckBlockReason($cache));
        }

        $cache->forceFill([
            'recheck_coordinator_at' => now(),
            'recheck_coordinator_by' => $coordinator->id,
        ])->save();

        return $cache;
    }

    /** @throws EmployeeCacheException */
    public function recheckAsManager(EmployeeCache $cache, User $manager): EmployeeCache
    {
        if (!$cache->awaitsManagerRecheck()) {
            throw new EmployeeCacheException($this->recheckBlockReason($cache));
        }

        $cache->forceFill([
            'recheck_manager_at' => now(),
            'recheck_manager_by' => $manager->id,
        ])->save();

        return $cache;
    }

    /**
     * Recusa na reconferência — de qualquer um dos dois. O cachê para aqui: o
     * horário informado não confere com o que se aprovou, e resolver isso é
     * conversa fora do sistema.
     *
     * @throws EmployeeCacheException
     */
    public function rejectRecheck(EmployeeCache $cache, User $user, ?string $reason = null): EmployeeCache
    {
        if (!$cache->awaitsCoordinatorRecheck() && !$cache->awaitsManagerRecheck()) {
            throw new EmployeeCacheException($this->recheckBlockReason($cache));
        }

        $cache->forceFill([
            'recheck_rejected_at' => now(),
            'recheck_rejected_by' => $user->id,
            'recheck_rejection_reason' => $reason,
        ])->save();

        return $cache;
    }

    private function recheckBlockReason(EmployeeCache $cache): string
    {
        return match (true) {
            $cache->isCancelled() => 'Este cachê foi cancelado.',
            $cache->isRecheckRejected() => 'Este cachê já foi recusado na reconferência.',
            !$cache->isSigned() => 'Este cachê ainda não foi assinado pelo funcionário.',
            !$cache->hasDivergence() => 'Este cachê não teve divergência de horário — não precisa de reconferência.',
            default => 'Esta etapa da reconferência já foi cumprida.',
        };
    }

    /* ---------------------------------------------------------------------
     | Financeiro
     |---------------------------------------------------------------------*/

    /**
     * Baixa manual: o cachê não é pago pelo Sicoob, então marcar aqui é o
     * registro de que o pagamento saiu por fora (folha, caixa).
     *
     * O lock existe para o caso de duas abas abertas na mesma tela do
     * financeiro; ids que deixaram de ser pagáveis são ignorados e contados.
     *
     * @param  array<int>  $ids
     * @return array{paid: int, skipped: int}
     */
    public function pay(array $ids, User $user): array
    {
        if (empty($ids)) {
            return ['paid' => 0, 'skipped' => 0];
        }

        return DB::transaction(function () use ($ids, $user) {
            $caches = EmployeeCache::whereIn('id', $ids)
                ->with('batch')
                ->lockForUpdate()
                ->get();

            $paid = 0;

            foreach ($caches as $cache) {
                if (!$cache->canBePaid()) {
                    continue;
                }

                $cache->forceFill([
                    'paid' => true,
                    'paid_at' => now(),
                    'paid_by' => $user->id,
                ])->save();
                $paid++;
            }

            return ['paid' => $paid, 'skipped' => count($ids) - $paid];
        });
    }

    /** @throws EmployeeCacheException */
    public function cancel(EmployeeCache $cache, User $user): void
    {
        if ($cache->isPaid()) {
            throw new EmployeeCacheException('Cachê já pago não pode ser cancelado.');
        }

        if ($cache->isSigned()) {
            throw new EmployeeCacheException('Cachê assinado pelo funcionário não pode ser cancelado.');
        }

        $cache->forceFill([
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
        ])->save();
    }

    /** @throws EmployeeCacheException */
    private function assertEditable(EmployeeCacheBatch $batch): void
    {
        if (!$batch->canBeEdited()) {
            throw new EmployeeCacheException('Solicitação já enviada não pode mais ser alterada.');
        }
    }
}
