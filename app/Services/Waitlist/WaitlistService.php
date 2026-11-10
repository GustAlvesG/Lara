<?php

namespace App\Services\Waitlist;

use App\Models\Waitlist\WaitlistClass;
use App\Models\Waitlist\WaitlistContact;
use App\Models\Waitlist\WaitlistEntry;
use App\Models\Waitlist\WaitlistPerson;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Regras da fila de espera das modalidades (não sócios).
 *
 * - Uma pessoa pode estar em várias filas, mas só uma vez ativa em cada turma.
 * - A ordem é a data de entrada; ligação nenhuma muda a posição.
 * - Sair da fila é decisão da secretaria: matriculado, desistiu ou removido
 *   (removido pede motivo). Reativar devolve a pessoa à posição original.
 * - Turma inativa não recebe gente nova.
 *
 * Não fala com o MultiClubes: a fila dos sócios é de lá, e a prioridade do
 * sócio é conferida pela secretaria.
 */
class WaitlistService
{
    /**
     * Coloca a pessoa na fila das turmas escolhidas. Turma em que ela já
     * está aguardando é ignorada (volta em `skipped`), não duplicada.
     *
     * @param  list<int>  $classIds
     * @return array{added: Collection<int, WaitlistEntry>, skipped: Collection<int, WaitlistClass>}
     */
    public function enroll(WaitlistPerson $person, array $classIds, ?int $userId): array
    {
        $classes = WaitlistClass::whereIn('id', $classIds)->ordered()->get();

        $inactive = $classes->reject(fn (WaitlistClass $c) => $c->active);
        if ($inactive->isNotEmpty()) {
            throw ValidationException::withMessages([
                'classes' => 'Turma inativa não recebe gente nova: ' . $inactive->map->label()->implode(', ') . '.',
            ]);
        }

        return DB::transaction(function () use ($person, $classes, $userId) {
            // Trava a pessoa: dois cliques em "Incluir" não viram duas inscrições.
            WaitlistPerson::whereKey($person->id)->lockForUpdate()->first();

            $already = WaitlistEntry::waiting()
                ->where('waitlist_person_id', $person->id)
                ->pluck('waitlist_class_id')
                ->all();

            $added = collect();
            $skipped = collect();
            $now = now();

            foreach ($classes as $class) {
                if (in_array($class->id, $already, true)) {
                    $skipped->push($class);
                    continue;
                }

                $added->push(WaitlistEntry::create([
                    'waitlist_class_id' => $class->id,
                    'waitlist_person_id' => $person->id,
                    'joined_at' => $now,
                    'status' => WaitlistEntry::STATUS_WAITING,
                    'created_by' => $userId,
                ]));
            }

            return ['added' => $added, 'skipped' => $skipped];
        });
    }

    public function registerContact(WaitlistEntry $entry, string $result, ?string $notes, ?int $userId): WaitlistContact
    {
        if (!$entry->isWaiting()) {
            throw ValidationException::withMessages([
                'result' => 'Esta pessoa não está mais na fila desta turma. Reative a inscrição antes de registrar a ligação.',
            ]);
        }

        return $entry->contacts()->create([
            'result' => $result,
            'notes' => $notes,
            'user_id' => $userId,
        ]);
    }

    /**
     * Tira a pessoa da fila. Devolve as outras filas em que ela continua
     * aguardando, para a tela lembrar a secretaria (matriculou na natação,
     * talvez não precise mais da vaga no tênis).
     *
     * @return Collection<int, WaitlistEntry>
     */
    public function close(WaitlistEntry $entry, string $status, ?string $reason, ?int $userId): Collection
    {
        if (!in_array($status, WaitlistEntry::CLOSING_STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Situação inválida.']);
        }

        if ($status === WaitlistEntry::STATUS_REMOVED && blank($reason)) {
            throw ValidationException::withMessages(['close_reason' => 'Diga por que a pessoa foi removida da fila.']);
        }

        if (!$entry->isWaiting()) {
            throw ValidationException::withMessages(['status' => 'Esta pessoa já saiu da fila desta turma.']);
        }

        $entry->update([
            'status' => $status,
            'closed_at' => now(),
            'closed_by' => $userId,
            'close_reason' => filled($reason) ? trim($reason) : null,
        ]);

        return WaitlistEntry::waiting()
            ->with('waitlistClass')
            ->where('waitlist_person_id', $entry->waitlist_person_id)
            ->get();
    }

    /**
     * Devolve a pessoa à fila, na posição original (a data de entrada não
     * muda). Recusa se ela já voltou a esta turma por outra inscrição.
     */
    public function reopen(WaitlistEntry $entry): void
    {
        if ($entry->isWaiting()) {
            return;
        }

        $duplicate = WaitlistEntry::waiting()
            ->where('waitlist_person_id', $entry->waitlist_person_id)
            ->where('waitlist_class_id', $entry->waitlist_class_id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'status' => 'A pessoa já está aguardando nesta turma por outra inscrição.',
            ]);
        }

        $entry->update([
            'status' => WaitlistEntry::STATUS_WAITING,
            'closed_at' => null,
            'closed_by' => null,
            'close_reason' => null,
        ]);
    }
}
