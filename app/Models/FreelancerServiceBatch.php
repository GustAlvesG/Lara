<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Lote de contratos, do coordenador até a diretoria.
 *
 * Trâmite completo:
 *
 *   draft              o coordenador monta
 *   sent               aguardando a gerência
 *   awaiting_director  a gerência aprovou (ao menos um contrato) e a diretoria
 *                      recebeu o e-mail com os PINs
 *   director_approved  o diretor ditou o PIN de aprovação  ── terminais
 *   director_rejected  o diretor ditou o PIN de recusa
 *   closed             a gerência recusou tudo; não há o que mandar à diretoria
 *
 * A gerência decide **por contrato**; a diretoria decide o **lote inteiro**,
 * porque o diretor não opera o sistema: ele recebe um e-mail com dois PINs
 * (um aprova, outro recusa) e dita o escolhido para a gerência digitar.
 */
class FreelancerServiceBatch extends Model
{
    use HasFactory;

    const STATUS_DRAFT = 'draft';
    const STATUS_SENT = 'sent';
    const STATUS_AWAITING_DIRECTOR = 'awaiting_director';
    const STATUS_DIRECTOR_APPROVED = 'director_approved';
    const STATUS_DIRECTOR_REJECTED = 'director_rejected';
    const STATUS_CLOSED = 'closed';

    const DECISION_APPROVED = 'approved';
    const DECISION_REJECTED = 'rejected';

    /** Dígitos do PIN ditado pelo diretor. */
    const PIN_LENGTH = 6;

    protected $table = 'freelancer_service_batches';

    protected $fillable = ['status', 'created_by', 'sent_at', 'reviewed_by', 'reviewed_at'];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'reviewed_at' => 'datetime',
        // Cifrados, não hash: o reenvio do e-mail precisa repetir os mesmos
        // números. Nunca são exibidos no painel — só no corpo do e-mail.
        'director_approve_pin' => 'encrypted',
        'director_reject_pin' => 'encrypted',
        'director_notified_at' => 'datetime',
        'director_decided_at' => 'datetime',
    ];

    /** Nunca serializar os PINs, nem por acidente. */
    protected $hidden = ['director_approve_pin', 'director_reject_pin'];

    public function services()
    {
        return $this->hasMany(FreelancerService::class, 'batch_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Gerente que digitou o PIN ditado pelo diretor. */
    public function directorDecidedBy()
    {
        return $this->belongsTo(User::class, 'director_decided_by');
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

    public function isAwaitingDirector(): bool
    {
        return $this->status === self::STATUS_AWAITING_DIRECTOR;
    }

    public function isDirectorApproved(): bool
    {
        return $this->status === self::STATUS_DIRECTOR_APPROVED;
    }

    public function isDirectorRejected(): bool
    {
        return $this->status === self::STATUS_DIRECTOR_REJECTED;
    }

    /** A gerência já deu seu parecer — não importa qual foi o desfecho depois. */
    public function isReviewed(): bool
    {
        return in_array($this->status, [
            self::STATUS_AWAITING_DIRECTOR,
            self::STATUS_DIRECTOR_APPROVED,
            self::STATUS_DIRECTOR_REJECTED,
            self::STATUS_CLOSED,
        ], true);
    }

    /** Nada mais acontece com este lote. */
    public function isClosed(): bool
    {
        return in_array($this->status, [
            self::STATUS_DIRECTOR_APPROVED,
            self::STATUS_DIRECTOR_REJECTED,
            self::STATUS_CLOSED,
        ], true);
    }

    /** Enquanto é rascunho, o coordenador pode incluir e retirar contratos. */
    public function canBeEdited(): bool
    {
        return $this->isDraft();
    }

    /** Lote vazio não vai para a gerência. */
    public function canBeSent(): bool
    {
        return $this->isDraft() && $this->services()->exists();
    }

    public function canBeReviewed(): bool
    {
        return $this->isSent();
    }

    /** Rascunho se descarta; lote enviado é histórico. */
    public function canBeDiscarded(): bool
    {
        return $this->isDraft();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Rascunho',
            self::STATUS_SENT => 'Aguardando gerência',
            self::STATUS_AWAITING_DIRECTOR => $this->director_notified_at
                ? 'Aguardando diretoria'
                : 'Aguardando envio à diretoria',
            self::STATUS_DIRECTOR_APPROVED => 'Aprovado pela diretoria',
            self::STATUS_DIRECTOR_REJECTED => 'Recusado pela diretoria',
            self::STATUS_CLOSED => 'Encerrado sem aprovação',
            default => $this->status,
        };
    }

    /* ---------------------------------------------------------------------
     | PINs da diretoria
     |---------------------------------------------------------------------*/

    /** Já existe um par de PINs gerado para este lote? */
    public function hasDirectorPins(): bool
    {
        return filled($this->director_approve_pin) && filled($this->director_reject_pin);
    }

    /**
     * Gera o par de PINs, uma única vez por lote. Reenviar o e-mail repete os
     * mesmos números — trocar invalidaria o e-mail que o diretor já tem.
     */
    public function ensureDirectorPins(): void
    {
        if ($this->hasDirectorPins()) {
            return;
        }

        $approve = $this->randomPin();

        do {
            $reject = $this->randomPin();
        } while ($reject === $approve);

        $this->forceFill([
            'director_approve_pin' => $approve,
            'director_reject_pin' => $reject,
        ])->save();
    }

    /**
     * Confere o PIN ditado e devolve o que ele significa: `approved`,
     * `rejected`, ou null quando não bate com nenhum dos dois.
     *
     * A comparação é em tempo constante para não vazar o PIN por medida de
     * tempo — são só 6 dígitos.
     */
    public function decisionForPin(string $pin): ?string
    {
        if (!$this->hasDirectorPins()) {
            return null;
        }

        $pin = trim($pin);

        if (hash_equals((string) $this->director_approve_pin, $pin)) {
            return self::DECISION_APPROVED;
        }

        if (hash_equals((string) $this->director_reject_pin, $pin)) {
            return self::DECISION_REJECTED;
        }

        return null;
    }

    private function randomPin(): string
    {
        return str_pad((string) random_int(0, (10 ** self::PIN_LENGTH) - 1), self::PIN_LENGTH, '0', STR_PAD_LEFT);
    }

    /* ---------------------------------------------------------------------
     | Escopos
     |---------------------------------------------------------------------*/

    /** Rascunho aberto do coordenador — só existe um por vez. */
    public function scopeDraftOf($query, int $userId)
    {
        return $query->where('status', self::STATUS_DRAFT)->where('created_by', $userId);
    }

    public function scopeAwaitingManager($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeAwaitingDirector($query)
    {
        return $query->where('status', self::STATUS_AWAITING_DIRECTOR);
    }

    /* ---------------------------------------------------------------------
     | Financeiro
     |
     | O lote é a unidade de pagamento: a diretoria aprovou um bloco, e é esse
     | bloco que o financeiro quita. O estado de pagamento é SEMPRE derivado
     | dos contratos — nada é gravado aqui. A baixa do Pix é escrita de forma
     | assíncrona (job e reconciliação); um campo denormalizado no lote teria
     | duas fontes de verdade e derivaria em silêncio.
     |---------------------------------------------------------------------*/

    /** Contratos do lote que o financeiro enxerga — os que a gerência recusou ficam de fora. */
    public function payableServices()
    {
        return $this->services()->awaitingFinance();
    }

    /** Lotes com algo a mostrar no financeiro: aprovados pela diretoria. */
    public function scopeApprovedForFinance($query)
    {
        return $query->where('status', self::STATUS_DIRECTOR_APPROVED)
            ->whereHas('services', fn($q) => $q->awaitingFinance());
    }

    /**
     * Depende dos agregados `payable_count` e `paid_count` carregados pela
     * consulta do financeiro; sem eles, conta no banco.
     */
    public function financePaidCount(): int
    {
        return (int) ($this->paid_count ?? $this->payableServices()->where('paid', true)->count());
    }

    public function financePayableCount(): int
    {
        return (int) ($this->payable_count ?? $this->payableServices()->count());
    }

    public function isFullyPaid(): bool
    {
        $total = $this->financePayableCount();

        return $total > 0 && $this->financePaidCount() === $total;
    }

    public function isPartiallyPaid(): bool
    {
        $pagos = $this->financePaidCount();

        return $pagos > 0 && $pagos < $this->financePayableCount();
    }

    public function financeStatusLabel(): string
    {
        return match (true) {
            $this->isFullyPaid() => 'Quitado',
            $this->isPartiallyPaid() => 'Parcialmente pago',
            default => 'A pagar',
        };
    }
}
