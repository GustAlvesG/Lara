<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Uma tentativa de Pix para um contrato de freelancer.
 *
 * Cada tentativa é uma linha nova e definitiva (ver a migration). O que este
 * model acrescenta é a leitura dos estados — e a distinção que sustenta toda
 * a segurança do fluxo: um pagamento `failed` não saiu daqui e pode ser
 * reenviado; um `unknown` pode ter saído, e reenviar duplicaria a
 * transferência.
 */
class PixPayment extends Model
{
    /** @use HasFactory<\Database\Factories\PixPaymentFactory> */
    use HasFactory;

    /** Linha criada, nenhuma chamada feita ainda. */
    const STATUS_PENDING = 'pending';

    /** Iniciação DICT concluída: endToEndId reservado, dinheiro ainda parado. */
    const STATUS_INITIATED = 'initiated';

    /** Confirmação aceita pelo banco; liquidação em andamento. */
    const STATUS_SENT = 'sent';

    /** O banco confirmou. O dinheiro saiu. */
    const STATUS_FINALIZED = 'finalized';

    /** O banco recusou. Nada saiu. */
    const STATUS_REJECTED = 'rejected';

    /** Falhou antes de confirmar. Nada saiu — reenvio é seguro. */
    const STATUS_FAILED = 'failed';

    /**
     * A confirmação foi enviada e não sabemos o desfecho (timeout, queda de
     * rede, 5xx no meio). O dinheiro PODE ter saído. Só a consulta ao banco
     * resolve; reenviar às cegas é o jeito de pagar duas vezes.
     */
    const STATUS_UNKNOWN = 'unknown';

    /**
     * Estados em que a transação já está de posse do banco, ou pode estar.
     * Enquanto o pagamento estiver em um deles, o contrato não aceita uma
     * nova tentativa — é esta lista que impede o pagamento em dobro.
     */
    const BLOCKING_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_INITIATED,
        self::STATUS_SENT,
        self::STATUS_FINALIZED,
        self::STATUS_UNKNOWN,
    ];

    /** Estados que a reconciliação precisa resolver junto ao banco. */
    const OPEN_STATUSES = [
        self::STATUS_INITIATED,
        self::STATUS_SENT,
        self::STATUS_UNKNOWN,
    ];

    /** Rótulos para a tela do financeiro. */
    const STATUS_LABELS = [
        self::STATUS_PENDING => 'Na fila',
        self::STATUS_INITIATED => 'Iniciando',
        self::STATUS_SENT => 'Em processamento',
        self::STATUS_FINALIZED => 'Pago',
        self::STATUS_REJECTED => 'Rejeitado',
        self::STATUS_FAILED => 'Falhou',
        self::STATUS_UNKNOWN => 'Conferir no banco',
    ];

    protected $table = 'pix_payments';

    protected $fillable = [
        'freelancer_service_id',
        'freelancer_id',
        'idempotency_key',
        'end_to_end_id',
        'pix_key',
        'payee_document',
        'payee_name',
        'payee_key_type',
        'amount',
        'description',
        'status',
        'bank_state',
        'rejection_detail',
        'request_payload',
        'response_payload',
        'environment',
        'requested_by',
        'initiated_at',
        'confirmed_at',
        'finalized_at',
        'last_checked_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'initiated_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'finalized_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    public function freelancerService()
    {
        return $this->belongsTo(FreelancerService::class);
    }

    public function freelancer()
    {
        return $this->belongsTo(Freelancer::class);
    }

    /** Usuário do financeiro que clicou em "Dar baixa". */
    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /* ---------------------------------------------------------------------
     | Leitura do estado
     |---------------------------------------------------------------------*/

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /** Ainda vai mudar de estado sozinho (por job ou reconciliação). */
    public function isPending(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_INITIATED,
            self::STATUS_SENT,
        ], true);
    }

    /**
     * Pode-se tentar de novo? Só quando há CERTEZA de que a tentativa anterior
     * não moveu dinheiro. `failed` nunca chegou a confirmar e `rejected` foi
     * recusado pelo banco — nos dois casos a conta não foi debitada.
     *
     * `unknown` fica de fora de propósito: é exatamente o caso em que reenviar
     * pode transferir duas vezes.
     */
    public function canBeRetried(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_REJECTED], true);
    }

    /** Exige conferência manual no extrato antes de qualquer nova tentativa. */
    public function needsManualCheck(): bool
    {
        return $this->status === self::STATUS_UNKNOWN;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /* ---------------------------------------------------------------------
     | Escopos
     |---------------------------------------------------------------------*/

    /**
     * Tentativas que impedem um novo envio para o mesmo contrato: as que estão
     * em andamento e as que já deram certo.
     */
    public function scopeBlocking($query)
    {
        return $query->whereIn('status', self::BLOCKING_STATUSES);
    }

    /** Tentativas cujo desfecho ainda precisa ser buscado no banco. */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }
}
