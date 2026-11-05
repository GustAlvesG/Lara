<?php

namespace App\Services\Signature\Contracts;

use App\Models\SignatureContract;
use App\Models\User;
use App\Notifications\ContractMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Notificações dos contratos no sino e no navegador (05/11/2026, decisões do
 * usuário), como as dos avisos:
 *
 *  - Gerência (TODOS os membros, não só o coordenador do e-mail): enviado
 *    para validação; recusado na assinatura, assinado e cancelado.
 *  - Quem redigiu: devolvido, aprovado, ajuste da Gerência no texto,
 *    recusado, assinado e cancelado.
 *  - Quem redigiu e o setor do contrato: término da vigência chegando
 *    (60, 30 e 15 dias).
 *
 * Quem fez a movimentação não recebe aviso dela. Falha aqui nunca derruba o
 * fluxo do contrato: registra no log e segue.
 *
 * Isolado (como o ContractRecipients) porque consulta `users`, na conexão
 * `mysql`: os testes trocam esta classe no container.
 */
class ContractNotifier
{
    public const TO_AUTHOR = 'author';
    public const TO_MANAGEMENT = 'management';
    public const TO_SECTOR = 'sector';

    public const TITLES = [
        'submitted' => 'Contrato para validar',
        'returned' => 'Contrato devolvido com observações',
        'approved' => 'Contrato aprovado',
        'adjusted' => 'A Gerência ajustou o contrato',
        'refused' => 'Contrato recusado na assinatura',
        'signed' => 'Contrato assinado',
        'canceled' => 'Contrato cancelado',
        'expiring' => 'Contrato perto do fim',
    ];

    /**
     * @param  array<int, string>  $to  TO_AUTHOR, TO_MANAGEMENT, TO_SECTOR
     */
    public function notify(SignatureContract $contract, string $kind, array $to, string $message, ?int $actorId = null): void
    {
        try {
            $usuarios = $this->recipients($contract, $to)
                ->reject(fn(User $u) => $actorId !== null && (int) $u->id === $actorId)
                ->unique('id')
                ->values();

            if ($usuarios->isEmpty()) {
                return;
            }

            Notification::send($usuarios, new ContractMovement(
                $contract,
                $kind,
                self::TITLES[$kind] ?? 'Contrato',
                $message,
            ));
        } catch (\Throwable $e) {
            Log::warning('Contrato: notificação não enviada.', ['contrato' => $contract->id, 'tipo' => $kind, 'erro' => $e->getMessage()]);
        }
    }

    /**
     * Ajuste da Gerência no texto (salvar em validação): um aviso só enquanto
     * quem redigiu não leu o anterior — o salvar automático grava a cada
     * pausa da digitação.
     */
    public function adjusted(SignatureContract $contract, ?int $actorId, ?string $actorName): void
    {
        try {
            $autor = $this->author($contract);

            if (!$autor || (int) $autor->id === $actorId) {
                return;
            }

            $jaAvisado = $autor->unreadNotifications()
                ->where('data->type', 'contract_adjusted')
                ->where('data->contract_id', $contract->id)
                ->exists();

            if (!$jaAvisado) {
                $this->notify($contract, 'adjusted', [self::TO_AUTHOR],
                    ($actorName ?: 'A Gerência') . ' alterou o texto de ' . $this->name($contract) . ' durante a validação.', $actorId);
            }
        } catch (\Throwable $e) {
            Log::warning('Contrato: notificação de ajuste não enviada.', ['contrato' => $contract->id, 'erro' => $e->getMessage()]);
        }
    }

    /** "CTL - 3/26 — Título" ou só o título. */
    public function name(SignatureContract $contract): string
    {
        return ($contract->number ? $contract->number . ' — ' : '') . $contract->title;
    }

    /**
     * @param  array<int, string>  $to
     * @return Collection<int, User>
     */
    protected function recipients(SignatureContract $contract, array $to): Collection
    {
        $usuarios = collect();

        if (in_array(self::TO_AUTHOR, $to, true) && ($autor = $this->author($contract))) {
            $usuarios->push($autor);
        }

        if (in_array(self::TO_MANAGEMENT, $to, true)) {
            $usuarios = $usuarios->merge($this->management());
        }

        if (in_array(self::TO_SECTOR, $to, true) && $contract->sector_id) {
            $usuarios = $usuarios->merge(
                User::query()->whereHas('sectors', fn($q) => $q->where('sectors.id', $contract->sector_id))->get()
            );
        }

        return $usuarios;
    }

    protected function author(SignatureContract $contract): ?User
    {
        return $contract->created_by ? User::find($contract->created_by) : null;
    }

    /** @return Collection<int, User> todos os membros da Gerência */
    protected function management(): Collection
    {
        return User::query()
            ->whereHas('sectors', fn($q) => $q->whereRaw('LOWER(sectors.name) = ?', [mb_strtolower(User::MANAGEMENT_SECTOR)]))
            ->get();
    }
}
