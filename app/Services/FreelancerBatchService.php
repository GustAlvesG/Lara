<?php

namespace App\Services;

use App\Exceptions\FreelancerBatchException;
use App\Mail\DirectorBatchApprovalMail;
use App\Models\FreelancerService as FreelancerServiceModel;
use App\Models\FreelancerServiceBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Montagem, envio e análise dos lotes de contratos.
 *
 * O coordenador mantém **um** rascunho por vez: vai jogando nele os contratos
 * já assinados pelas duas partes e, quando fecha o dia, envia para a gerência.
 * O gerente decide contrato a contrato — o que ele recusa volta para a fila do
 * coordenador e pode entrar num lote seguinte.
 */
class FreelancerBatchService
{
    /** Rascunho aberto do coordenador, criado na primeira necessidade. */
    public function draftFor(User $coordinator): FreelancerServiceBatch
    {
        return FreelancerServiceBatch::draftOf($coordinator->id)->latest('id')->first()
            ?? FreelancerServiceBatch::create([
                'status' => FreelancerServiceBatch::STATUS_DRAFT,
                'created_by' => $coordinator->id,
            ]);
    }

    /** Rascunho já existente, sem criar um novo. */
    public function existingDraftFor(User $coordinator): ?FreelancerServiceBatch
    {
        return FreelancerServiceBatch::draftOf($coordinator->id)->latest('id')->first();
    }

    /**
     * Inclui contratos no rascunho. Ids inaptos são ignorados em silêncio — a
     * tela pode ter ficado aberta enquanto outra pessoa mexeu nos contratos —,
     * e o retorno diz quantos entraram de fato.
     *
     * @param  array<int>  $serviceIds
     * @throws FreelancerBatchException
     */
    public function addServices(FreelancerServiceBatch $batch, array $serviceIds): int
    {
        $this->assertEditable($batch);

        if (!$serviceIds) {
            return 0;
        }

        return DB::transaction(function () use ($batch, $serviceIds) {
            $services = FreelancerServiceModel::whereIn('id', $serviceIds)
                ->availableForBatch()
                ->lockForUpdate()
                ->get();

            foreach ($services as $service) {
                $service->forceFill(['batch_id' => $batch->id])->save();
            }

            return $services->count();
        });
    }

    /**
     * Retira um contrato do rascunho.
     *
     * @throws FreelancerBatchException
     */
    public function removeService(FreelancerServiceBatch $batch, FreelancerServiceModel $service): void
    {
        $this->assertEditable($batch);

        if ($service->batch_id !== $batch->id) {
            throw new FreelancerBatchException('Este contrato não está neste lote.');
        }

        $service->forceFill(['batch_id' => null])->save();
    }

    /**
     * Envia o lote para a gerência. A partir daqui o coordenador não mexe mais
     * nele, e os contratos ficam presos até a análise.
     *
     * @throws FreelancerBatchException
     */
    public function send(FreelancerServiceBatch $batch, User $coordinator): FreelancerServiceBatch
    {
        if (!$batch->isDraft()) {
            throw new FreelancerBatchException('Este lote já foi enviado.');
        }

        if (!$batch->canBeSent()) {
            throw new FreelancerBatchException('Inclua ao menos um contrato antes de enviar o lote.');
        }

        $batch->forceFill([
            'status' => FreelancerServiceBatch::STATUS_SENT,
            'sent_at' => now(),
            'created_by' => $batch->created_by ?? $coordinator->id,
        ])->save();

        return $batch;
    }

    /**
     * Descarta um rascunho, soltando os contratos de volta para a fila.
     *
     * @throws FreelancerBatchException
     */
    public function discard(FreelancerServiceBatch $batch): void
    {
        if (!$batch->canBeDiscarded()) {
            throw new FreelancerBatchException('Só é possível descartar um lote em rascunho.');
        }

        DB::transaction(function () use ($batch) {
            $batch->services()->update(['batch_id' => null]);
            $batch->delete();
        });
    }

    /**
     * Análise da gerência: aprova e recusa contrato a contrato, de uma vez.
     * Contratos do lote que não vierem em $decisions são tratados como
     * aprovados — a tela manda todos, e omissão por erro de formulário não
     * deve virar recusa silenciosa.
     *
     * @param  array<int, array{decision: string, reason?: string|null}>  $decisions
     * @return array{approved: int, rejected: int}
     * @throws FreelancerBatchException
     */
    public function review(FreelancerServiceBatch $batch, User $manager, array $decisions): array
    {
        if (!$batch->canBeReviewed()) {
            throw new FreelancerBatchException(
                $batch->isDraft()
                    ? 'Este lote ainda não foi enviado para a gerência.'
                    : 'Este lote já foi analisado.'
            );
        }

        return DB::transaction(function () use ($batch, $manager, $decisions) {
            $services = $batch->services()->lockForUpdate()->get();
            $approved = 0;
            $rejected = 0;

            foreach ($services as $service) {
                $entry = $decisions[$service->id] ?? [];
                $isRejection = ($entry['decision'] ?? 'approve') === 'reject';

                if ($isRejection) {
                    $service->forceFill([
                        'manager_approved_at' => null,
                        'manager_approved_by' => null,
                        'manager_rejected_at' => now(),
                        'manager_rejected_by' => $manager->id,
                        'manager_rejection_reason' => $entry['reason'] ?? null,
                    ])->save();
                    $rejected++;

                    continue;
                }

                $service->forceFill([
                    'manager_approved_at' => now(),
                    'manager_approved_by' => $manager->id,
                    // Aprovação limpa uma recusa anterior: vale a decisão atual.
                    'manager_rejected_at' => null,
                    'manager_rejected_by' => null,
                    'manager_rejection_reason' => null,
                ])->save();
                $approved++;
            }

            $batch->forceFill([
                // Aprovou algo? Segue para a diretoria. Recusou tudo? Encerra
                // aqui — não há o que submeter ao diretor.
                'status' => $approved > 0
                    ? FreelancerServiceBatch::STATUS_AWAITING_DIRECTOR
                    : FreelancerServiceBatch::STATUS_CLOSED,
                'reviewed_by' => $manager->id,
                'reviewed_at' => now(),
            ])->save();

            return ['approved' => $approved, 'rejected' => $rejected];
        });
    }

    /* ---------------------------------------------------------------------
     | Diretoria
     |
     | O diretor não acessa a plataforma. Ele recebe por e-mail os dois PINs do
     | lote — um aprova, outro recusa —, dita o escolhido para a gerência, e a
     | gerência digita. O PIN é o que prova que a decisão partiu de quem
     | recebeu o e-mail: ele não existe em lugar nenhum da interface.
     |---------------------------------------------------------------------*/

    /**
     * Envia (ou reenvia) o lote à diretoria. Os PINs são gerados na primeira
     * vez e repetidos nos reenvios — trocá-los invalidaria o e-mail que o
     * diretor já tem em mãos.
     *
     * O envio é síncrono de propósito: com fila, uma falha de SMTP ficaria
     * invisível e o lote travaria sem ninguém perceber. Aqui a exceção sobe e
     * a tela mostra o que aconteceu.
     *
     * @throws FreelancerBatchException  quando o lote não está no ponto ou não
     *                                   há destinatário configurado
     */
    public function notifyDirector(FreelancerServiceBatch $batch): void
    {
        if (!$batch->isAwaitingDirector()) {
            throw new FreelancerBatchException(
                'Só um lote aprovado pela gerência pode ser enviado à diretoria.'
            );
        }

        $email = config('freelancers.director.email');

        if (blank($email)) {
            throw new FreelancerBatchException(
                'Nenhum e-mail de diretoria configurado (FREELANCER_DIRECTOR_EMAIL no .env).'
            );
        }

        $batch->ensureDirectorPins();
        $batch->load(['services.freelancer', 'services.functionFreelancer', 'createdBy', 'reviewedBy']);

        $mail = new DirectorBatchApprovalMail(
            $batch,
            (string) $batch->director_approve_pin,
            (string) $batch->director_reject_pin,
        );

        $pending = Mail::to($email);

        if ($cc = config('freelancers.director.cc')) {
            $pending->cc($cc);
        }

        $pending->send($mail);

        $batch->forceFill([
            'director_email' => $email,
            'director_notified_at' => now(),
        ])->save();
    }

    /**
     * Registra a decisão da diretoria a partir do PIN ditado ao gerente.
     * Devolve `approved` ou `rejected`; PIN que não bate devolve null e nada
     * é gravado.
     *
     * @throws FreelancerBatchException
     */
    public function applyDirectorPin(
        FreelancerServiceBatch $batch,
        User $operator,
        string $pin,
        ?string $note = null,
    ): ?string {
        if (!$batch->isAwaitingDirector()) {
            throw new FreelancerBatchException(
                $batch->isClosed()
                    ? 'Este lote já foi decidido pela diretoria.'
                    : 'Este lote ainda não foi aprovado pela gerência.'
            );
        }

        if (!$batch->hasDirectorPins()) {
            throw new FreelancerBatchException(
                'Este lote ainda não foi enviado à diretoria — envie o e-mail antes.'
            );
        }

        $decision = $batch->decisionForPin($pin);

        if ($decision === null) {
            return null;
        }

        return DB::transaction(function () use ($batch, $operator, $decision, $note) {
            $approved = $decision === FreelancerServiceBatch::DECISION_APPROVED;

            // A diretoria decide o lote inteiro, mas só os contratos que a
            // gerência aprovou estão em jogo: os recusados por ela já saíram.
            $services = $batch->services()
                ->whereNotNull('manager_approved_at')
                ->lockForUpdate()
                ->get();

            foreach ($services as $service) {
                $service->forceFill($approved
                    ? ['director_approved_at' => now(), 'director_rejected_at' => null]
                    : ['director_rejected_at' => now(), 'director_approved_at' => null]
                )->save();
            }

            $batch->forceFill([
                'status' => $approved
                    ? FreelancerServiceBatch::STATUS_DIRECTOR_APPROVED
                    : FreelancerServiceBatch::STATUS_DIRECTOR_REJECTED,
                'director_decision' => $decision,
                'director_decided_at' => now(),
                'director_decided_by' => $operator->id,
                'director_note' => $note,
            ])->save();

            return $decision;
        });
    }

    /**
     * Aprovação da gerência seguida do aviso à diretoria. O aviso é o passo que
     * pode falhar (SMTP fora do ar), e falhar nele NÃO desfaz a aprovação: o
     * lote fica em "aguardando envio" e a tela oferece reenviar.
     *
     * @return array{approved: int, rejected: int, notified: bool, error: string|null}
     */
    public function reviewAndNotify(FreelancerServiceBatch $batch, User $manager, array $decisions): array
    {
        $result = $this->review($batch, $manager, $decisions);
        $result['notified'] = false;
        $result['error'] = null;

        if ($result['approved'] === 0) {
            return $result; // gerência recusou tudo: nada a submeter
        }

        try {
            $this->notifyDirector($batch->fresh());
            $result['notified'] = true;
        } catch (\Throwable $e) {
            Log::error('Falha ao enviar lote à diretoria', [
                'batch_id' => $batch->id,
                'erro' => $e->getMessage(),
            ]);
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * @throws FreelancerBatchException
     */
    private function assertEditable(FreelancerServiceBatch $batch): void
    {
        if (!$batch->canBeEdited()) {
            throw new FreelancerBatchException('Lote já enviado não pode mais ser alterado.');
        }
    }
}
