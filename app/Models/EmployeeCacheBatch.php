<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Lote de cachês solicitado por um coordenador de setor.
 *
 *   draft            o coordenador monta a solicitação
 *   sent             aguardando a gerência
 *   manager_reviewed a gerência decidiu item a item; os aprovados seguem para
 *                    a assinatura dos funcionários
 *   closed           a gerência recusou tudo — não há o que assinar
 *
 * Depois da análise o lote não muda mais de estado: o que acontece dali em
 * diante (assinatura, reconferência, pagamento) é de cada cachê, porque cada
 * funcionário assina o seu. O lote continua sendo a lente pela qual as telas
 * agrupam o trabalho.
 */
class EmployeeCacheBatch extends Model
{
    use HasFactory;

    const STATUS_DRAFT = 'draft';
    const STATUS_SENT = 'sent';
    const STATUS_MANAGER_REVIEWED = 'manager_reviewed';
    const STATUS_CLOSED = 'closed';

    protected $table = 'employee_cache_batches';

    protected $fillable = ['status', 'sector_id', 'title', 'created_by', 'sent_at', 'reviewed_by', 'reviewed_at'];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function caches()
    {
        return $this->hasMany(EmployeeCache::class, 'batch_id');
    }

    public function sector()
    {
        return $this->belongsTo(Sector::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /* ---------------------------------------------------------------------
     | Estado
     |---------------------------------------------------------------------*/

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isReviewed(): bool
    {
        return in_array($this->status, [self::STATUS_MANAGER_REVIEWED, self::STATUS_CLOSED], true);
    }

    public function canBeEdited(): bool
    {
        return $this->isDraft();
    }

    /** Lote vazio não vai para a gerência. */
    public function canBeSent(): bool
    {
        return $this->isDraft() && $this->caches()->exists();
    }

    public function canBeReviewed(): bool
    {
        return $this->isSent();
    }

    public function canBeDiscarded(): bool
    {
        return $this->isDraft();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Rascunho',
            self::STATUS_SENT => 'Aguardando gerência',
            self::STATUS_MANAGER_REVIEWED => 'Aprovado — em assinatura',
            self::STATUS_CLOSED => 'Encerrado sem aprovação',
            default => (string) $this->status,
        };
    }

    /* ---------------------------------------------------------------------
     | Escopos
     |---------------------------------------------------------------------*/

    public function scopeDraftOf($query, int $userId)
    {
        return $query->where('status', self::STATUS_DRAFT)->where('created_by', $userId);
    }

    public function scopeAwaitingManager($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }
}
