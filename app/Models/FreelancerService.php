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

    /**
     * Quanto a assinatura do freelancer pode atrasar em relação ao início do
     * turno sem ser considerada fora do prazo. Um turno que começa 16:00 pode
     * ser assinado até 16:30.
     */
    const SIGNATURE_TOLERANCE_MINUTES = 30;

    /** O valor da função é cobrado por bloco de 15 minutos. */
    const BLOCK_MINUTES = 15;

    /** Ids da tabela `status` usados por este módulo. */
    const STATUS_CANCELLED = 0;
    const STATUS_ACTIVE = 1;

    /**
     * Estados de assinatura pelos quais a listagem pode ser filtrada, com o
     * rótulo que aparece na tela. São os mesmos de `signatureLabel()`: um lugar
     * só, para o filtro não passar a oferecer um estado que a coluna não mostra.
     */
    const SIGNATURE_FILTERS = [
        'unsigned' => 'Não assinado',
        'awaiting_coordinator' => 'Aguardando coordenador',
        'awaiting_freelancer' => 'Aguardando freelancer',
        'signed' => 'Assinado',
        'cancelled' => 'Cancelado',
    ];

    /**
     * Registros com falha — os dois desvios que o sistema já marca na listagem:
     * o freelancer acima do limite de 7 dias e o contrato assinado depois de o
     * turno ter começado. Nenhum dos dois bloqueia nada na hora; o filtro é o
     * que permite ir atrás deles depois.
     */
    const ISSUE_FILTERS = [
        'any' => 'Qualquer falha',
        'none' => 'Sem falhas',
        'weekly' => 'Excesso em 7 dias',
        'late' => 'Assinatura em atraso',
        'unsigned_late' => 'Sem assinatura, turno já começou',
    ];

    protected $table = 'freelancer_services';

    protected $fillable = [
        'freelancer_id',
        'function_freelancer_id',
        // Contrato base, quando esta linha é um aditivo (ver seção "Aditivo").
        'parent_service_id',
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
        'amended_at' => 'datetime',
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

    /** Contrato que este aditivo altera. Null nos contratos originais. */
    public function baseService()
    {
        return $this->belongsTo(self::class, 'parent_service_id');
    }

    /** Aditivos feitos sobre este contrato (na prática, no máximo um vigente). */
    public function amendments()
    {
        return $this->hasMany(self::class, 'parent_service_id');
    }

    /** O aditivo que passou a responder pelo pagamento deste contrato. */
    public function activeAmendment()
    {
        return $this->belongsTo(self::class, 'amendment_service_id');
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

    /**
     * Ter recebido aditivo NÃO tira a assinatura de cena: o contrato base é um
     * documento firmado entre as partes e é assinado até o fim, como qualquer
     * outro. O aditivo muda o caminho do dinheiro, não o do papel.
     */
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

    /* ---------------------------------------------------------------------
     | Aditivo
     |
     | O turno muda depois de o contrato estar assinado: o serviço é esticado,
     | encurtado ou muda de local. Contrato assinado não se altera — o caminho
     | é um ADITIVO: um contrato novo que referencia o base e repete tudo dele,
     | exceto horário de início, horário de término e local.
     |
     | O contrato base continua vivo: é assinado pelas duas partes até o fim e
     | fica no histórico como o documento que elas firmaram. O que o aditivo
     | tira dele é o PAGAMENTO — o base sai do lote e do financeiro, e quem paga
     | o turno é o aditivo, com o período já corrigido. Somar os dois pagaria o
     | mesmo turno duas vezes.
     |---------------------------------------------------------------------*/

    public function isAmendment(): bool
    {
        return $this->parent_service_id !== null;
    }

    /** Recebeu aditivo: continua sendo assinado, mas não é mais ele que paga. */
    public function isAmended(): bool
    {
        return $this->amended_at !== null;
    }

    /** 1 = 1º termo aditivo, 2 = aditivo do aditivo, e assim por diante. */
    public function amendmentOrder(): int
    {
        $order = 0;
        $service = $this;

        // O pai é sempre um registro anterior, então não há ciclo; o teto é só
        // uma trava contra dados corrompidos.
        while ($service?->parent_service_id !== null && $order < 20) {
            $order++;
            $service = $service->baseService;
        }

        return $order;
    }

    /** Título do documento: contrato original ou termo aditivo numerado. */
    public function documentTitle(): string
    {
        if (!$this->isAmendment()) {
            return 'Contrato Autônomo de Serviços de Freelancer';
        }

        $order = $this->amendmentOrder();

        return ($order > 1 ? $order . 'º ' : '')
            . 'Termo Aditivo ao Contrato Autônomo de Serviços de Freelancer';
    }

    public function canBeAmended(): bool
    {
        return $this->amendmentBlockReason() === null;
    }

    /**
     * Por que este contrato não aceita aditivo — null quando aceita. A mesma
     * frase serve ao tablet, ao painel e à exceção do serviço, para que o
     * motivo da recusa seja sempre o mesmo texto.
     */
    public function amendmentBlockReason(): ?string
    {
        return match (true) {
            $this->isCancelled() => 'Contrato cancelado não recebe aditivo.',
            // Sem assinatura o contrato ainda é editável: aditivar seria criar
            // um segundo documento onde bastava corrigir o primeiro.
            !$this->isSigned() => 'Contrato ainda não assinado: altere os dados do próprio contrato, sem aditivo.',
            $this->isAmended() => 'Este contrato já tem um aditivo — o novo aditivo se faz sobre ele.',
            $this->isPaid() => 'Contrato já pago não recebe aditivo.',
            $this->isManagerApproved() || $this->isDirectorApproved() => 'Contrato já aprovado não recebe aditivo.',
            // Preso a um lote em tramitação: substituí-lo trocaria o conteúdo de
            // um lote que a gerência já está analisando.
            $this->batch_id !== null && !$this->canBeBatched() =>
                'Contrato em lote de aprovação: retire-o do lote antes de fazer o aditivo.',
            default => null,
        };
    }

    /** Ex.: "de 4h para 6h30" — o que o aditivo mudou no período. */
    public function amendmentDurationChange(): ?string
    {
        if (!$this->isAmendment() || $this->baseService === null) {
            return null;
        }

        return 'de ' . $this->baseService->formattedDuration() . ' para ' . $this->formattedDuration();
    }

    /* ---------------------------------------------------------------------
     | Prazo da assinatura
     |
     | O contrato existe para ser assinado ANTES de o turno começar. A conta é
     | feita sobre a assinatura DO FREELANCER: é ela que acontece no momento do
     | serviço. A do coordenador é sempre posterior — ele assina em fila, pelo
     | tablet —, e cobrá-la pelo mesmo prazo acusaria praticamente todo contrato.
     |---------------------------------------------------------------------*/

    /**
     * Minutos entre o início do turno e a assinatura do freelancer. Negativo
     * quando assinado antes (o esperado); null enquanto não houver assinatura.
     */
    public function minutesFromStartToSignature(): ?int
    {
        if ($this->freelancer_signed_at === null || $this->start_date === null || blank($this->start_time)) {
            return null;
        }

        return (int) $this->startsAt()->diffInMinutes($this->freelancer_signed_at, false);
    }

    /** Assinado depois do início do turno, já descontada a tolerância. */
    public function isSignedAfterStart(): bool
    {
        $minutes = $this->minutesFromStartToSignature();

        return $minutes !== null && $minutes > self::SIGNATURE_TOLERANCE_MINUTES;
    }

    /**
     * O atraso em relação ao INÍCIO do turno (não à tolerância), formatado:
     * "45min", "2h", "2h15". Null quando não há atraso.
     */
    public function formattedSignatureDelay(): ?string
    {
        $minutes = $this->minutesFromStartToSignature();

        return $minutes === null || $minutes <= 0 ? null : self::formatMinutes($minutes);
    }

    /**
     * O turno já começou e o freelancer NUNCA assinou — o caso vizinho ao da
     * assinatura em atraso, e mais grave: lá o contrato ao menos existe
     * assinado, aqui o serviço está sendo prestado sem contrato firmado.
     *
     * Mesma tolerância de 30 min da assinatura em atraso, para as duas marcas
     * aparecerem a partir do mesmo instante — e a mesma conta sobre a assinatura
     * DO FREELANCER: a do coordenador é sempre posterior, por desenho.
     * Cancelado não conta: saiu do fluxo antes de qualquer assinatura.
     */
    public function isUnsignedAfterStart(): bool
    {
        if ($this->isCancelled() || $this->freelancer_signed_at !== null) {
            return false;
        }

        if ($this->start_date === null || blank($this->start_time)) {
            return false;
        }

        return $this->startsAt()->addMinutes(self::SIGNATURE_TOLERANCE_MINUTES)->isPast();
    }

    /** Há quanto tempo o turno começou, para o contrato que segue sem assinatura. */
    public function formattedTimeSinceStart(): ?string
    {
        if (!$this->isUnsignedAfterStart()) {
            return null;
        }

        return self::formatMinutes((int) $this->startsAt()->diffInMinutes(now(), false));
    }

    /** "45min", "2h", "2h15" — formato comum das marcas de prazo. */
    private static function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return match (true) {
            $hours === 0 => "{$rest}min",
            $rest === 0 => "{$hours}h",
            default => sprintf('%dh%02d', $hours, $rest),
        };
    }

    /** Rótulo curto do estado do contrato, para exibição. */
    public function signatureLabel(): string
    {
        // Aditivado ou não, a leitura aqui é sobre as assinaturas: o contrato
        // base é assinado até o fim. Quem conta a história do aditivo é o
        // approvalLabel(), porque o que muda é o pagamento.
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
        // Aditivado: quem vai a lote é o aditivo, não ele.
        if (!$this->isFullySigned() || $this->isCancelled() || $this->isAmended() || $this->isDirectorApproved()) {
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
            $this->isAmended() => 'Pago pelo aditivo',
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
            // Aditivado: quem entra no lote é o aditivo.
            ->whereNull('amended_at')
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
     | Busca e ordenação da listagem
     |---------------------------------------------------------------------*/

    /**
     * Busca livre da listagem: nome ou CPF do freelancer e evento/local. São os
     * três jeitos de procurar um contrato quando não se sabe a data.
     */
    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($term)) . '%';
        // Só os dígitos: o CPF é gravado sem pontuação, mas se digita com.
        $digits = preg_replace('/\D/', '', $term);

        return $query->where(function ($q) use ($like, $digits) {
            $q->where('location', 'like', $like)
                ->orWhereHas('freelancer', function ($f) use ($like, $digits) {
                    $f->where('name', 'like', $like);

                    if ($digits !== '') {
                        $f->orWhere('cpf', 'like', '%' . $digits . '%');
                    }
                });
        });
    }

    /**
     * Filtra pelo estado das assinaturas — a mesma leitura de `signatureLabel()`,
     * escrita em SQL. Valor desconhecido (ou vazio) não filtra nada.
     */
    public function scopeSignatureStatus($query, ?string $status)
    {
        if (!array_key_exists((string) $status, self::SIGNATURE_FILTERS)) {
            return $query;
        }

        // Cancelado tem precedência sobre as assinaturas na hora de rotular, e
        // por isso também sai dos demais filtros.
        $active = fn($q) => $q->where('status_id', '!=', self::STATUS_CANCELLED);

        return match ($status) {
            'cancelled' => $query->where('status_id', self::STATUS_CANCELLED),
            'unsigned' => $active($query)->whereNull('freelancer_signed_at')->whereNull('coordinator_signed_at'),
            'awaiting_coordinator' => $active($query)->whereNotNull('freelancer_signed_at')->whereNull('coordinator_signed_at'),
            'awaiting_freelancer' => $active($query)->whereNotNull('coordinator_signed_at')->whereNull('freelancer_signed_at'),
            'signed' => $active($query)->whereNotNull('freelancer_signed_at')->whereNotNull('coordinator_signed_at'),
        };
    }

    /**
     * Ordenação da listagem: por data do turno (padrão) ou por nome do
     * freelancer. O desempate é sempre a data, para a ordem ser estável entre
     * contratos do mesmo freelancer.
     */
    public function scopeSortedBy($query, string $sort = 'date', string $direction = 'desc')
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        if ($sort === 'name') {
            return $query->select('freelancer_services.*')
                ->join('freelancers', 'freelancers.id', '=', 'freelancer_services.freelancer_id')
                ->orderBy('freelancers.name', $direction)
                ->orderByDesc('freelancer_services.start_date')
                ->orderByDesc('freelancer_services.start_time');
        }

        return $query->orderBy('start_date', $direction)
            ->orderBy('start_time', $direction);
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
            && !$this->isCancelled()
            // Turno aditivado é pago pelo aditivo, uma vez só.
            && !$this->isAmended();
    }

    public function canBePaid(): bool
    {
        return $this->isPayable() && !$this->isPaid();
    }

    /**
     * Contratos que o coordenador enxerga no Kiosk: o freelancer já assinou e
     * só falta a contraparte. Cancelados ficam de fora.
     *
     * Contrato que recebeu aditivo CONTINUA aqui: ele é um documento firmado e
     * precisa da assinatura das duas partes. O aditivo aparece ao lado, e é ele
     * que seguirá para o lote.
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
            ->where('status_id', '!=', self::STATUS_CANCELLED)
            ->whereNull('amended_at');
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
     *
     * `$extraDates` soma datas que ainda não estão no banco — é assim que o
     * registro em massa faz as linhas do próprio lote contarem umas com as
     * outras, em vez de cada uma se achar a primeira da semana.
     *
     * @param  array<int, mixed>  $extraDates
     */
    public static function countInWeeklyWindow(int $freelancerId, $startDate, array $extraDates = []): int
    {
        $date = Carbon::parse($startDate)->startOfDay();

        $dates = static::weeklyWindowDates($freelancerId, $date)
            ->concat(collect($extraDates)->map(fn($value) => Carbon::parse($value)->startOfDay()));

        return self::fullestWindowCount($date, $dates);
    }

    /**
     * Datas já gravadas que podem cair numa janela de 7 dias contendo $date.
     * Isolado do resto do cálculo para que a regra possa ser exercitada nos
     * testes sem banco.
     *
     * Aditivos ficam de fora: eles não acrescentam um dia de trabalho, apenas
     * remendam um turno já contado pelo contrato base. Contá-los faria o
     * segundo documento do mesmo dia estourar o limite sozinho.
     *
     * @return Collection<int, Carbon>
     */
    protected static function weeklyWindowDates(int $freelancerId, Carbon $date): Collection
    {
        $reach = self::WEEKLY_WINDOW_DAYS - 1;

        return static::where('freelancer_id', $freelancerId)
            ->where('status_id', '!=', self::STATUS_CANCELLED)
            ->whereNull('parent_service_id')
            ->whereBetween('start_date', [
                $date->copy()->subDays($reach)->startOfDay(),
                $date->copy()->addDays($reach)->endOfDay(),
            ])
            ->pluck('start_date')
            ->map(fn($value) => Carbon::parse($value)->startOfDay());
    }

    /**
     * Índices das linhas de um lote que passam do limite, contando o que já está
     * no banco MAIS as linhas anteriores do próprio lote. Sem essa soma, três
     * linhas do mesmo freelancer na mesma semana entrariam cada uma se achando
     * a primeira, e o registro em massa viraria o caminho para furar a regra.
     *
     * @param  array<int, array>  $rows  linhas com `freelancer_id` e `start_date`
     * @return array<int, int>
     */
    public static function rowsExceedingWeeklyLimit(array $rows): array
    {
        $exceeding = [];
        $pending = [];

        foreach (array_values($rows) as $index => $row) {
            $freelancerId = (int) $row['freelancer_id'];

            if (static::wouldExceedWeeklyLimit(
                $freelancerId,
                $row['start_date'],
                $pending[$freelancerId] ?? []
            )) {
                $exceeding[] = $index;
            }

            $pending[$freelancerId][] = $row['start_date'];
        }

        return $exceeding;
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
     * Um novo serviço nessa data ultrapassaria o limite? Usado para avisar antes
     * de gravar, e não depois.
     *
     * @param  array<int, mixed>  $extraDates  outras datas do mesmo lote, ainda não gravadas
     */
    public static function wouldExceedWeeklyLimit(int $freelancerId, $startDate, array $extraDates = []): bool
    {
        return static::countInWeeklyWindow($freelancerId, $startDate, $extraDates) + 1 > self::WEEKLY_LIMIT;
    }

    /**
     * Dado um conjunto de serviços já carregado em memória (sem novas queries),
     * retorna um mapa [service_id => excede_limite_semanal], usando a mesma
     * regra de janela de 7 dias por freelancer.
     */
    public static function flagExcessWithinCollection(Collection $services): Collection
    {
        // Mesmas exclusões de weeklyWindowDates(): cancelado não conta, e
        // aditivo não é um dia novo de trabalho.
        $considered = $services->reject(fn($service) => $service->isCancelled() || $service->isAmendment());

        return $services->mapWithKeys(function ($service) use ($considered) {
            if ($service->isCancelled() || $service->isAmendment()) {
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
