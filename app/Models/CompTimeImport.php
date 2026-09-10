<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Estado de uma importação de espelho de ponto.
 *
 * O ciclo tem dois jobs e uma parada no meio para decisão humana:
 *
 *   pending → processing → AWAITING_REVIEW → (usuário decide) → processing → completed
 *                       ↘ completed (quando não há duplicata para revisar)
 *                       ↘ failed
 *
 * `AWAITING_REVIEW` existe justamente para esse meio do caminho. Antes ele era
 * escrito como `completed` + `phase = detecting` — um "concluído" que não
 * significava concluído, e que obrigava cada acesso à tela de revisão a
 * repetir os dois `where()` para não confundir com uma importação de verdade.
 */
class CompTimeImport extends Model
{
    public const STATUS_PENDING         = 'pending';
    public const STATUS_PROCESSING      = 'processing';
    public const STATUS_AWAITING_REVIEW = 'awaiting_review';
    public const STATUS_COMPLETED       = 'completed';
    public const STATUS_FAILED          = 'failed';

    public const PHASE_DETECTING  = 'detecting';
    public const PHASE_IMPORTING  = 'importing';
    public const PHASE_CONFIRMING = 'confirming';

    /**
     * A partir de quantos segundos parado em `pending` a tela passa a
     * desconfiar da fila. A fila é `database` e depende de um `queue:work`
     * vivo; sem worker o registro nunca sai de `pending` e, antes disso, a
     * tela girava para sempre sem dizer por quê.
     */
    public const STALLED_AFTER_SECONDS = 90;

    protected $fillable = [
        'uuid',
        'user_id',
        'status',
        'phase',
        'temp_file_path',
        'result_data',
        'error_message',
        'dispatched_at',
    ];

    protected $casts = [
        'result_data'   => 'array',
        'dispatched_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isAwaitingReview(): bool
    {
        return $this->status === self::STATUS_AWAITING_REVIEW;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }

    /**
     * Despachado há tempo demais e ainda não encostado por worker nenhum.
     * Não é prova de fila parada — é o suficiente para a tela parar de fingir
     * que está tudo bem e sugerir onde olhar.
     */
    public function looksStalled(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->dispatched_at !== null
            && $this->dispatched_at->diffInSeconds(now()) > self::STALLED_AFTER_SECONDS;
    }

    /** Quantidade de lançamentos novos apurada na fase de detecção. */
    public function newEntriesCount(): int
    {
        return (int) ($this->result_data['new_entries_count'] ?? 0);
    }

    /** Duplicatas apuradas na fase de detecção (já serializadas para JSON). */
    public function duplicateEntries(): array
    {
        return $this->result_data['duplicate_entries'] ?? [];
    }
}
