<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FreelancerService extends Model
{
    /** @use HasFactory<\Database\Factories\FreelancerServiceFactory> */
    use HasFactory;

    /** Limite recomendado de serviços por freelancer numa janela de 7 dias. */
    const WEEKLY_LIMIT = 2;

    /** Tamanho da janela do limite, em dias. */
    const WEEKLY_WINDOW_DAYS = 7;

    /** O valor da função é cobrado por bloco de 15 minutos. */
    const BLOCK_MINUTES = 15;

    /** Ids da tabela `status` usados por este módulo. */
    const STATUS_CANCELLED = 0;
    const STATUS_ACTIVE = 1;

    protected $table = 'freelancer_services';

    protected $fillable = [
        'freelancer_id',
        'function_freelancer_id',
        'location',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'price',
        'total_hours',
        'status_id',
        'freelancer_signed_at',
        'freelancer_signed_by',
        'coordinator_signed_at',
        'coordinator_signed_by',
        'paid',
        'paid_at',
        'paid_by',
        'cancelled_at',
        'cancelled_by',
        'created_by',
        'updated_by',
        'weekly_limit_authorized_at',
        'weekly_limit_authorized_by',
    ];

    /**
     * Espelha o default da coluna, para que um serviço recém-criado já venha
     * com o status preenchido (e não null) sem precisar reler do banco.
     */
    protected $attributes = [
        'status_id' => self::STATUS_ACTIVE,
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_hours' => 'decimal:2',
        'price' => 'decimal:2',
        'freelancer_signed_at' => 'datetime',
        'coordinator_signed_at' => 'datetime',
        'paid' => 'boolean',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'weekly_limit_authorized_at' => 'datetime',
    ];

    public function freelancer()
    {
        return $this->belongsTo(Freelancer::class);
    }

    public function functionFreelancer()
    {
        return $this->belongsTo(FunctionFreelancer::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    /** Usuário do sistema que conduziu a assinatura do freelancer pelo bot. */
    public function freelancerSignedBy()
    {
        return $this->belongsTo(User::class, 'freelancer_signed_by');
    }

    public function coordinatorSignedBy()
    {
        return $this->belongsTo(User::class, 'coordinator_signed_by');
    }

    /** Coordenador do Comercial que liberou o registro acima do limite de 7 dias. */
    public function weeklyLimitAuthorizedBy()
    {
        return $this->belongsTo(User::class, 'weekly_limit_authorized_by');
    }

    /** Último lote em que o contrato entrou (rascunho, enviado ou já analisado). */
    public function batch()
    {
        return $this->belongsTo(FreelancerServiceBatch::class, 'batch_id');
    }

    public function managerApprovedBy()
    {
        return $this->belongsTo(User::class, 'manager_approved_by');
    }

    public function managerRejectedBy()
    {
        return $this->belongsTo(User::class, 'manager_rejected_by');
    }

    /** Usuário do financeiro que deu baixa no pagamento. */
    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /* ---------------------------------------------------------------------
     | Período trabalhado
     |
     | O turno é informado por horário de início e fim. Quando o horário de
     | término é anterior ao de início, entende-se que o turno virou a
     | meia-noite e termina no dia seguinte (ex.: 22:00 -> 02:00).
     |---------------------------------------------------------------------*/

    /** Uniformiza "19:00" e "19:00:00" para o formato H:i:s. */
    public static function normalizeTime(string $time): string
    {
        return Carbon::createFromFormat('Y-m-d', '2000-01-01')
            ->setTimeFromTimeString($time)
            ->format('H:i:s');
    }

    /** O turno atravessa a meia-noite? */
    public static function crossesMidnight(string $startTime, string $endTime): bool
    {
        return self::normalizeTime($endTime) <= self::normalizeTime($startTime);
    }

    /** Duração real do turno em minutos, já considerando a virada de dia. */
    public static function minutesBetween(string $startTime, string $endTime): int
    {
        // Data-base fixa para o cálculo não depender do dia em que roda.
        $base = Carbon::createFromFormat('Y-m-d', '2000-01-01')->startOfDay();

        $start = $base->copy()->setTimeFromTimeString($startTime);
        $end = $base->copy()->setTimeFromTimeString($endTime);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return $start->diffInMinutes($end);
    }

    /**
     * Blocos de 15 minutos a pagar. Arredonda para baixo: só bloco
     * integralmente cumprido é remunerado.
     */
    public static function billedBlocks(string $startTime, string $endTime): int
    {
        return intdiv(self::minutesBetween($startTime, $endTime), self::BLOCK_MINUTES);
    }

    /**
     * Valida o par início/término e devolve a mensagem do problema, ou null
     * quando o período é aceitável. Compartilhado pelo formulário e pela
     * importação por planilha.
     */
    public static function scheduleError(string $startTime, string $endTime): ?string
    {
        if (self::normalizeTime($startTime) === self::normalizeTime($endTime)) {
            return 'O horário de término deve ser diferente do horário de início.';
        }

        // Com arredondamento para baixo, menos de um bloco resultaria em um
        // contrato de 0 hora e valor zero.
        if (self::minutesBetween($startTime, $endTime) < self::BLOCK_MINUTES) {
            return 'O período deve ter no mínimo ' . self::BLOCK_MINUTES . ' minutos.';
        }

        return null;
    }

    public function durationInMinutes(): int
    {
        return self::minutesBetween($this->start_time, $this->end_time);
    }

    public function startsAt(): Carbon
    {
        return Carbon::parse($this->start_date->toDateString())
            ->setTimeFromTimeString($this->start_time);
    }

    public function endsAt(): Carbon
    {
        return Carbon::parse($this->end_date->toDateString())
            ->setTimeFromTimeString($this->end_time);
    }

    /** Ex.: "22/07/2026 22:00 → 23/07/2026 02:00". */
    public function formattedPeriod(): string
    {
        return $this->startsAt()->format('d/m/Y H:i') . ' → ' . $this->endsAt()->format('d/m/Y H:i');
    }

    /** Ex.: "4h30" ou "3h". */
    public function formattedDuration(): string
    {
        $minutes = $this->durationInMinutes();
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest === 0 ? "{$hours}h" : sprintf('%dh%02d', $hours, $rest);
    }

    /* ---------------------------------------------------------------------
     | Estado do contrato
     |---------------------------------------------------------------------*/

    /** Assinado por pelo menos uma das partes. */
    public function isSigned(): bool
    {
        return $this->freelancer_signed_at !== null || $this->coordinator_signed_at !== null;
    }

    /** Assinado pelas duas partes. */
    public function isFullySigned(): bool
    {
        return $this->freelancer_signed_at !== null && $this->coordinator_signed_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->status_id === self::STATUS_CANCELLED;
    }

    /**
     * Um contrato assinado (por qualquer uma das partes) ou cancelado não pode
     * mais ter seus dados alterados.
     */
    public function canBeUpdated(): bool
    {
        return !$this->isSigned() && !$this->isCancelled();
    }

    /** Só é possível cancelar enquanto não houver nenhuma assinatura. */
    public function canBeCancelled(): bool
    {
        return !$this->isSigned() && !$this->isCancelled();
    }

    public function canBeSignedByFreelancer(): bool
    {
        return !$this->isCancelled() && $this->freelancer_signed_at === null;
    }

    public function canBeSignedByCoordinator(): bool
    {
        return !$this->isCancelled() && $this->coordinator_signed_at === null;
    }

    /**
     * Contrato assinado vira histórico: não se apaga, cancela-se (e cancelar
     * também deixa de ser possível depois da primeira assinatura).
     */
    public function canBeDeleted(): bool
    {
        return !$this->isSigned();
    }

    /** Rótulo curto do estado do contrato, para exibição. */
    public function signatureLabel(): string
    {
        return match (true) {
            $this->isCancelled() => 'Cancelado',
            $this->isFullySigned() => 'Assinado',
            $this->freelancer_signed_at !== null => 'Aguardando coordenador',
            $this->coordinator_signed_at !== null => 'Aguardando freelancer',
            default => 'Não assinado',
        };
    }

    /* ---------------------------------------------------------------------
     | Aprovação da gerência (lote)
     |---------------------------------------------------------------------*/

    public function isManagerApproved(): bool
    {
        return $this->manager_approved_at !== null;
    }

    public function isManagerRejected(): bool
    {
        return $this->manager_rejected_at !== null && !$this->isManagerApproved();
    }

    public function isDirectorApproved(): bool
    {
        return $this->director_approved_at !== null;
    }

    public function isDirectorRejected(): bool
    {
        return $this->director_rejected_at !== null && !$this->isDirectorApproved();
    }

    /** Está num lote que ainda está tramitando (rascunho, gerência ou diretoria). */
    public function isInOpenBatch(): bool
    {
        return $this->batch_id !== null
            && $this->batch !== null
            && !$this->batch->isClosed();
    }

    /**
     * Pode entrar num lote: assinado pelas duas partes, não cancelado, ainda
     * não aprovado pela diretoria e fora de qualquer lote em tramitação. O que
     * a gerência ou a diretoria recusou volta para cá.
     */
    public function canBeBatched(): bool
    {
        if (!$this->isFullySigned() || $this->isCancelled() || $this->isDirectorApproved()) {
            return false;
        }

        if ($this->batch_id === null || $this->batch === null) {
            return true;
        }

        // Lote encerrado sem aprovar, ou item que a gerência recusou depois de
        // já ter dado seu parecer no lote.
        return $this->batch->isClosed() && !$this->isDirectorApproved()
            || ($this->batch->isReviewed() && $this->isManagerRejected());
    }

    /** Rótulo do trâmite de aprovação, para exibição. */
    public function approvalLabel(): string
    {
        return match (true) {
            $this->isCancelled() => 'Cancelado',
            !$this->isFullySigned() => 'Aguardando assinaturas',
            $this->isDirectorApproved() => 'Aprovado pela diretoria',
            $this->isDirectorRejected() => 'Recusado pela diretoria',
            $this->isManagerRejected() => 'Recusado pela gerência',
            $this->isManagerApproved() => 'Aguardando diretoria',
            $this->isInOpenBatch() && $this->batch->isSent() => 'Aguardando gerência',
            $this->isInOpenBatch() => 'Em lote (rascunho)',
            default => 'Aguardando envio para a gerência',
        };
    }

    /** Contratos que o coordenador pode incluir num lote. */
    public function scopeAvailableForBatch($query)
    {
        $encerrados = [
            FreelancerServiceBatch::STATUS_DIRECTOR_REJECTED,
            FreelancerServiceBatch::STATUS_CLOSED,
        ];

        $comParecerDaGerencia = [
            FreelancerServiceBatch::STATUS_AWAITING_DIRECTOR,
            FreelancerServiceBatch::STATUS_DIRECTOR_APPROVED,
            FreelancerServiceBatch::STATUS_DIRECTOR_REJECTED,
            FreelancerServiceBatch::STATUS_CLOSED,
        ];

        return $query->whereNotNull('freelancer_signed_at')
            ->whereNotNull('coordinator_signed_at')
            ->where('status_id', '!=', self::STATUS_CANCELLED)
            ->whereNull('director_approved_at')
            ->where(function ($q) use ($encerrados, $comParecerDaGerencia) {
                $q->whereNull('batch_id')
                    // Lote encerrado sem aprovação: tudo volta para a fila.
                    ->orWhereHas('batch', fn($b) => $b->whereIn('status', $encerrados))
                    // Item recusado pela gerência dentro de um lote que já
                    // seguiu adiante. A checagem do status do lote é o que
                    // impede o contrato de reaparecer depois de entrar num
                    // rascunho novo, quando a recusa antiga ainda está gravada.
                    ->orWhere(fn($sub) => $sub->whereNotNull('manager_rejected_at')
                        ->whereHas('batch', fn($b) => $b->whereIn('status', $comParecerDaGerencia)));
            });
    }

    /* ---------------------------------------------------------------------
     | Financeiro
     |---------------------------------------------------------------------*/

    public function isPaid(): bool
    {
        return (bool) $this->paid;
    }

    /**
     * Só entra no financeiro o contrato assinado pelas duas partes e aprovado
     * pelos DOIS níveis: a assinatura do coordenador confirma o serviço
     * prestado, a gerência confere contrato a contrato e a diretoria dá o aval
     * final que libera o pagamento.
     */
    public function isPayable(): bool
    {
        return $this->isFullySigned()
            && $this->isManagerApproved()
            && $this->isDirectorApproved()
            && !$this->isCancelled();
    }

    public function canBePaid(): bool
    {
        return $this->isPayable() && !$this->isPaid();
    }

    /**
     * Contratos que o coordenador enxerga no Kiosk: o freelancer já assinou e
     * só falta a contraparte. Cancelados ficam de fora.
     */
    public function scopeAwaitingCoordinator($query)
    {
        return $query->whereNotNull('freelancer_signed_at')
            ->whereNull('coordinator_signed_at')
            ->where('status_id', '!=', self::STATUS_CANCELLED);
    }

    /**
     * Contratos que o financeiro enxerga: assinados pelas duas partes e
     * aprovados pela gerência e pela diretoria.
     */
    public function scopeAwaitingFinance($query)
    {
        return $query->whereNotNull('freelancer_signed_at')
            ->whereNotNull('coordinator_signed_at')
            ->whereNotNull('manager_approved_at')
            ->whereNotNull('director_approved_at')
            ->where('status_id', '!=', self::STATUS_CANCELLED);
    }

    /* ---------------------------------------------------------------------
     | Regra de limite semanal
     |---------------------------------------------------------------------*/

    /**
     * Verifica se este serviço faz parte de uma janela de 7 dias (baseada em
     * start_date) em que o freelancer acumula mais serviços que o limite
     * recomendado. Contratos cancelados não entram na conta.
     */
    public function exceedsWeeklyLimit(): bool
    {
        return static::countInWeeklyWindow($this->freelancer_id, $this->start_date) > self::WEEKLY_LIMIT;
    }

    /**
     * Serviços já registrados para o freelancer na janela de 7 dias MAIS CHEIA
     * que contenha $startDate. A janela não é só "os 6 dias anteriores": lançar
     * um contrato numa data ANTERIOR a outros já registrados também aperta a
     * mesma semana, e olhar só para trás deixava esse caso passar sem aviso.
     * Contratos cancelados não entram na conta.
     */
    public static function countInWeeklyWindow(int $freelancerId, $startDate): int
    {
        $date = Carbon::parse($startDate)->startOfDay();
        $reach = self::WEEKLY_WINDOW_DAYS - 1;

        $dates = static::where('freelancer_id', $freelancerId)
            ->where('status_id', '!=', self::STATUS_CANCELLED)
            ->whereBetween('start_date', [
                $date->copy()->subDays($reach)->startOfDay(),
                $date->copy()->addDays($reach)->endOfDay(),
            ])
            ->pluck('start_date')
            ->map(fn($value) => Carbon::parse($value)->startOfDay());

        return self::fullestWindowCount($date, $dates);
    }

    /**
     * Maior número de datas que cabem numa janela de 7 dias que também contenha
     * $date — testa as 7 posições possíveis dessa janela.
     *
     * @param  Collection<int, Carbon>  $dates
     */
    private static function fullestWindowCount(Carbon $date, Collection $dates): int
    {
        $counts = [];

        for ($back = 0; $back < self::WEEKLY_WINDOW_DAYS; $back++) {
            $start = $date->copy()->subDays($back);
            $end = $start->copy()->addDays(self::WEEKLY_WINDOW_DAYS - 1);

            $counts[] = $dates->filter(fn(Carbon $other) => $other->between($start, $end))->count();
        }

        return max($counts);
    }

    /**
     * Um novo serviço nessa data ultrapassaria o limite recomendado? Usado para
     * avisar antes de gravar, e não depois.
     */
    public static function wouldExceedWeeklyLimit(int $freelancerId, $startDate): bool
    {
        return static::countInWeeklyWindow($freelancerId, $startDate) + 1 > self::WEEKLY_LIMIT;
    }

    /**
     * Dado um conjunto de serviços já carregado em memória (sem novas queries),
     * retorna um mapa [service_id => excede_limite_semanal], usando a mesma
     * regra de janela de 7 dias por freelancer.
     */
    public static function flagExcessWithinCollection(Collection $services): Collection
    {
        $considered = $services->reject(fn($service) => $service->isCancelled());

        return $services->mapWithKeys(function ($service) use ($considered) {
            if ($service->isCancelled()) {
                return [$service->id => false];
            }

            // Mesma regra de countInWeeklyWindow (janela mais cheia), só que
            // sobre o que já está em memória.
            $dates = $considered
                ->filter(fn($other) => $other->freelancer_id === $service->freelancer_id)
                ->map(fn($other) => Carbon::parse($other->start_date)->startOfDay());

            $count = self::fullestWindowCount(
                Carbon::parse($service->start_date)->startOfDay(),
                $dates->values()
            );

            return [$service->id => $count > self::WEEKLY_LIMIT];
        });
    }
}
