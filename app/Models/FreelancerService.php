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

    /** Limite recomendado de serviços por freelancer, por semana de calendário. */
    const WEEKLY_LIMIT = 2;

    /** Tamanho da semana de calendário, em dias. A semana sempre começa na segunda-feira. */
    const WEEKLY_WINDOW_DAYS = 7;

    /**
     * Quanto a assinatura do freelancer pode atrasar em relação ao início do
     * turno sem ser considerada fora do prazo. Um turno que começa 16:00 pode
     * ser assinado até 16:30.
     */
    const SIGNATURE_TOLERANCE_MINUTES = 30;

    /**
     * Com quanta antecedência o contrato libera a entrada do freelancer na
     * portaria. Turno às 08:00 abre a portaria às 07:30. Ver a seção
     * "Liberação na portaria".
     */
    const ACCESS_EARLY_MINUTES = 30;

    /** O valor da função é cobrado por bloco de 15 minutos. */
    const BLOCK_MINUTES = 15;

    /* ---------------------------------------------------------------------
     | Comissão de venda
     |---------------------------------------------------------------------*/

    /** Tipos de aditivo (coluna `amendment_type`). */
    const AMENDMENT_SCHEDULE = 'schedule';
    const AMENDMENT_COMMISSION = 'commission';

    /** A cada R$ 1.000,00 vendidos, paga-se R$ 50,00. */
    const COMMISSION_BLOCK_SALES = 1000.00;
    const COMMISSION_BLOCK_VALUE = 50.00;

    /** Ou 5% do valor total vendido. */
    const COMMISSION_PERCENT = 5.0;

    /**
     * Os dois critérios, com o texto que aparece na tela e no documento. O
     * rótulo mora aqui junto da conta para não haver uma tela dizendo uma coisa
     * e o cálculo fazendo outra.
     */
    const COMMISSION_METHODS = [
        'block' => 'R$ 50,00 a cada R$ 1.000,00 vendidos',
        'percent' => '5% do valor total vendido',
    ];

    /** Origem do valor de venda: digitado no tablet ou apurado no MultiVendas. */
    const SALES_SOURCE_MANUAL = 'manual';
    const SALES_SOURCE_SYSTEM = 'system';

    /** Teto do valor vendido aceito na entrada — trava contra zero a mais. */
    const MAX_SALES_AMOUNT = 1000000;

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

    /**
     * Redação vigente das cláusulas — a que um contrato novo assina.
     *
     * O texto do instrumento é revisado pelo jurídico de tempos em tempos, e
     * cada revisão é uma REDAÇÃO NOVA, nunca uma edição da anterior: contrato
     * assinado tem de continuar dizendo o que dizia. Ver `CONTRACT_VERSIONS`.
     */
    const CONTRACT_VERSION_CURRENT = 1;

    /**
     * As redações já existentes, com a data em que entraram em vigor e o que
     * mudou. Serve à varredura (o filtro da listagem lê daqui) e é o histórico
     * que o jurídico consulta sem precisar abrir o git.
     *
     * Para criar uma redação nova:
     *   1. copie `resources/views/freelancer/services/partials/contract/vN`
     *      para `vN+1` e edite o texto — NUNCA edite uma versão já em uso;
     *   2. acrescente a entrada aqui e suba `CONTRACT_VERSION_CURRENT`;
     *   3. registre o sha256 dos arquivos da vN em
     *      `tests/Unit/FreelancerContractVersionTest.php`, que é o lacre que
     *      impede a redação antiga de ser alterada depois.
     */
    const CONTRACT_VERSIONS = [
        1 => [
            'label' => 'Redação original',
            'from' => '2026-07-22',
            'summary' => 'Modelo do Clube dos Funcionários, com a cláusula da forma de pagamento por PIX e o texto por dia (sem horário no corpo do instrumento).',
        ],
    ];

    protected $table = 'freelancer_services';

    protected $fillable = [
        'freelancer_id',
        'function_freelancer_id',
        // Contrato base, quando esta linha é um aditivo (ver seção "Aditivo").
        'parent_service_id',
        'amendment_type',
        // Comissão de venda (ver seção "Comissão de venda").
        'sales_amount',
        'commission_method',
        'sales_source',
        'sales_login',
        'sales_period_start',
        'sales_period_end',
        'sales_report',
        // Por que o valor apurado no relatório foi alterado (ver migration).
        'sales_adjustment_reason',
        'location',
        // Esclarecimento livre do serviço — apenas informativo (ver migration).
        'description',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'price',
        // Chave PIX conferida pelo freelancer na assinatura (ver a seção
        // "Chave PIX do pagamento").
        'pix_key',
        'pix_key_confirmed_at',
        // Redação das cláusulas e dados das partes congelados na assinatura
        // (ver a seção "Congelamento do documento").
        'contract_version',
        'signed_snapshot',
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
        'pix_key_confirmed_at' => 'datetime',
        'contract_version' => 'integer',
        'signed_snapshot' => 'array',
        // Trâmite do lote. Ficaram fora do cast desde a criação e voltavam como
        // string: o resto do código só testa `!== null`, mas quem precisa da
        // data (a relação impressa do financeiro) não conseguia formatá-la.
        'manager_approved_at' => 'datetime',
        'manager_rejected_at' => 'datetime',
        'director_approved_at' => 'datetime',
        'director_rejected_at' => 'datetime',
        'paid' => 'boolean',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'weekly_limit_authorized_at' => 'datetime',
        'amended_at' => 'datetime',
        'sales_amount' => 'decimal:2',
        'sales_period_start' => 'datetime',
        'sales_period_end' => 'datetime',
        'sales_report' => 'array',
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

    /** Todas as tentativas de Pix deste contrato, da mais antiga à mais nova. */
    public function pixPayments()
    {
        return $this->hasMany(PixPayment::class);
    }

    /**
     * A tentativa de Pix mais recente — a que a tela do financeiro mostra.
     * As anteriores continuam no histórico; o que interessa na listagem é
     * onde o dinheiro está agora.
     */
    public function latestPixPayment()
    {
        return $this->hasOne(PixPayment::class)->latestOfMany();
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
        return !$this->isCancelled()
            && $this->coordinator_signed_at === null
            && $this->hasBeenReleased();
    }

    /* ---------------------------------------------------------------------
     | Liberação para a coordenação (08h do dia seguinte ao turno)
     |
     | O freelancer assina no COMEÇO do serviço, e o turno ainda muda depois
     | disso: estica, encurta, troca de local, ganha comissão de venda. Cada uma
     | dessas mudanças é um ADITIVO, e o aditivo só existe enquanto o dia corre.
     |
     | Por isso o contrato não segue para a contraparte no mesmo dia: ele espera
     | até as `RELEASE_HOUR` da manhã seguinte ao dia do turno. Só então o
     | coordenador assina e só então ele entra num lote — antes disso o dia
     | ainda não acabou, e assinar seria fechar um documento que pode mudar.
     |
     | O dia de referência é `start_date`, o dia do turno em todo o módulo: um
     | turno que vira a meia-noite pertence ao dia em que começou, e é na manhã
     | seguinte a ESSE dia que ele é liberado.
     |
     | A conta vive em dois lugares — `hasBeenReleased()` decide por registro,
     | `scopeReleased()` filtra no banco — e é de propósito que as duas sejam a
     | mesma. A do banco compara só DATAS (ver `lastReleasedDate()`), porque
     | somar horas em SQL muda de MySQL para SQLite, e uma tela que lista o que
     | o servidor depois recusa é pior que a trava não existir.
     |---------------------------------------------------------------------*/

    /** Hora da manhã seguinte em que o contrato do dia anterior é liberado. */
    const RELEASE_HOUR = 8;

    /** Instante a partir do qual este contrato pode ser assinado e loteado. */
    public function releasesAt(): ?Carbon
    {
        if ($this->start_date === null) {
            return null;
        }

        return Carbon::parse($this->start_date->toDateString())
            ->addDay()
            ->setTime(self::RELEASE_HOUR, 0);
    }

    public function hasBeenReleased(?Carbon $moment = null): bool
    {
        $releasesAt = $this->releasesAt();

        // Contrato sem data de turno não tem manhã seguinte a esperar; deixá-lo
        // travado para sempre seria pior que liberá-lo.
        return $releasesAt === null || ($moment ?? Carbon::now())->greaterThanOrEqualTo($releasesAt);
    }

    /**
     * Por que este contrato ainda não pode ir adiante — null quando já pode. A
     * mesma frase para o tablet, o painel e a exceção do serviço.
     */
    public function releaseBlockReason(?Carbon $moment = null): ?string
    {
        if ($this->hasBeenReleased($moment)) {
            return null;
        }

        return 'O turno de ' . Carbon::parse($this->start_date)->format('d/m/Y')
            . ' ainda pode receber aditivo. Este contrato é liberado para a coordenação em '
            . $this->releasesAt()->format('d/m/Y') . ' às '
            . $this->releasesAt()->format('H:i') . '.';
    }

    /**
     * A maior `start_date` já liberada no instante informado — o que transforma
     * a regra numa comparação de datas, sem aritmética de hora no SQL.
     *
     * Antes das 08h, a manhã de hoje ainda não chegou: o último dia liberado é
     * o de anteontem. A partir das 08h, é o de ontem.
     */
    public static function lastReleasedDate(?Carbon $moment = null): Carbon
    {
        $moment ??= Carbon::now();

        return $moment->copy()->startOfDay()
            ->subDays($moment->hour >= self::RELEASE_HOUR ? 1 : 2);
    }

    /** Contratos cujo dia de turno já passou da manhã seguinte. */
    public function scopeReleased($query, ?Carbon $moment = null)
    {
        return $query->whereDate('start_date', '<=', self::lastReleasedDate($moment));
    }

    /** O complemento: os que ainda estão maturando. */
    public function scopeNotReleased($query, ?Carbon $moment = null)
    {
        return $query->whereDate('start_date', '>', self::lastReleasedDate($moment));
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
     | Congelamento do documento
     |
     | O corpo do contrato é montado ao vivo: a redação vem dos templates e os
     | dados das partes, do cadastro. As duas coisas mudam depois da assinatura
     | — o jurídico revisa uma cláusula, o freelancer corrige o endereço —, e
     | sem congelá-las um contrato já firmado passaria a dizer outra coisa.
     |
     | Congela-se na PRIMEIRA assinatura, de qualquer das partes: é o ato que
     | fecha o documento. Enquanto ninguém assinou, o contrato acompanha a
     | redação vigente e o cadastro vivo — é o que ele vai assinar.
     |---------------------------------------------------------------------*/

    /**
     * Opções do filtro de redação na listagem — a varredura: "quais contratos
     * foram assinados sob o texto antigo?".
     *
     * `unfrozen` são os que ainda não têm redação congelada porque ninguém
     * assinou: eles acompanham a vigente e mudam de texto se o jurídico
     * publicar outra, e por isso não pertencem a nenhuma redação.
     *
     * @return array<string, string>
     */
    public static function contractVersionFilters(): array
    {
        $filters = [];

        foreach (self::CONTRACT_VERSIONS as $number => $info) {
            $filters[(string) $number] = 'Redação ' . $number . ' · ' . $info['label'];
        }

        $filters['unfrozen'] = 'Ainda não congelada (sem assinatura)';

        return $filters;
    }

    /** Aplica o filtro de redação da listagem. */
    public function scopeContractVersionFilter($query, ?string $filter)
    {
        if ($filter === null || !array_key_exists($filter, self::contractVersionFilters())) {
            return $query;
        }

        return $filter === 'unfrozen'
            ? $query->whereNull('contract_version')
            : $query->where('contract_version', (int) $filter);
    }

    /** A redação firmada, ou a vigente enquanto o contrato não foi assinado. */
    public function contractVersion(): int
    {
        return $this->contract_version ?? self::CONTRACT_VERSION_CURRENT;
    }

    /** O documento já está fechado: nem a redação nem os dados mudam mais. */
    public function contractIsFrozen(): bool
    {
        return $this->contract_version !== null;
    }

    /**
     * Prefixo das views da redação deste contrato. Não existindo a pasta, o
     * Laravel falha ao renderizar — e é o que se quer: melhor um erro visível
     * que imprimir, em silêncio, um texto diferente do que foi assinado.
     */
    public function contractViewNamespace(): string
    {
        return 'freelancer.services.partials.contract.v' . $this->contractVersion();
    }

    /** @return array{label: string, from: string, summary: string}|null */
    public function contractVersionInfo(): ?array
    {
        return self::CONTRACT_VERSIONS[$this->contractVersion()] ?? null;
    }

    /** "Redação 1 · Redação original" — o rótulo das telas e da varredura. */
    public function contractVersionLabel(): string
    {
        $info = $this->contractVersionInfo();

        return 'Redação ' . $this->contractVersion() . ($info ? ' · ' . $info['label'] : '');
    }

    /**
     * O que gravar no ato da assinatura. Já congelado, devolve o que está lá:
     * a segunda assinatura não reescreve o que a primeira firmou.
     */
    public function contractFreezeAttributes(): array
    {
        if ($this->contractIsFrozen()) {
            return [];
        }

        return [
            'contract_version' => self::CONTRACT_VERSION_CURRENT,
            'signed_snapshot' => $this->buildContractSnapshot(),
        ];
    }

    /** Os dados que o texto do instrumento cita, como estão agora. */
    public function buildContractSnapshot(): array
    {
        $f = $this->freelancer;

        return [
            'freelancer' => [
                'name' => $f?->name,
                'cpf' => $f?->cpf,
                'rg' => $f?->rg,
                'nacionality' => $f?->nacionality,
                'civil_status' => $f?->civil_status,
                'address' => $f?->address,
            ],
            'function' => [
                'name' => $this->functionFreelancer?->name,
            ],
        ];
    }

    /**
     * A qualificação do FREELANCER como o documento a cita: a congelada quando
     * há, o cadastro enquanto não há.
     *
     * O bloco é tomado inteiro, e não campo a campo: misturar um RG congelado
     * com um endereço vivo produziria uma qualificação que nunca existiu.
     * Contratos anteriores a esta cópia ficam sem snapshot e caem no cadastro —
     * que é o que eles citavam antes, a mesma decisão tomada para a `pix_key`.
     *
     * @return array{name: ?string, cpf: ?string, rg: ?string, nacionality: ?string, civil_status: ?string, address: ?string}
     */
    public function contractParty(): array
    {
        $snapshot = $this->signed_snapshot['freelancer'] ?? null;

        if (!is_array($snapshot)) {
            return $this->buildContractSnapshot()['freelancer'];
        }

        return [
            'name' => $snapshot['name'] ?? null,
            'cpf' => $snapshot['cpf'] ?? null,
            'rg' => $snapshot['rg'] ?? null,
            'nacionality' => $snapshot['nacionality'] ?? null,
            'civil_status' => $snapshot['civil_status'] ?? null,
            'address' => $snapshot['address'] ?? null,
        ];
    }

    /**
     * A função como o documento a nomeia. Congelada junto com o resto: nomes de
     * função são editáveis no cadastro, e renomear "Garçom" mudaria a cláusula
     * 1 de todo contrato de garçom já assinado.
     */
    public function contractFunctionName(): ?string
    {
        $snapshot = $this->signed_snapshot['function']['name'] ?? null;

        return $snapshot ?? $this->functionFreelancer?->name;
    }

    /** A qualificação exibida veio do congelamento, e não do cadastro vivo? */
    public function contractPartyIsFrozen(): bool
    {
        return is_array($this->signed_snapshot['freelancer'] ?? null);
    }

    /**
     * O cadastro do freelancer mudou depois da assinatura — o documento cita a
     * qualificação antiga, que é a correta, e a tela avisa para ninguém achar
     * que o contrato está com dado errado. Mesmo aviso que a chave PIX dá.
     */
    public function contractPartyDivergesFromFreelancer(): bool
    {
        return $this->contractPartyIsFrozen()
            && $this->contractParty() !== $this->buildContractSnapshot()['freelancer'];
    }

    /* ---------------------------------------------------------------------
     | Chave PIX do pagamento
     |
     | O documento diz para qual chave o valor será pago, e o freelancer confere
     | essa chave no tablet antes de assinar. O que ele confere fica COPIADO
     | aqui (`pix_key`): o cadastro pode mudar amanhã, e um contrato assinado
     | não pode passar a dizer outra coisa.
     |
     | Contratos anteriores a esta cópia — e os assinados pela API, que não têm
     | a tela de conferência — caem no cadastro do freelancer, que é o que o
     | documento citava antes.
     |---------------------------------------------------------------------*/

    public function pixKey(): string
    {
        return (string) ($this->pix_key ?: $this->freelancer?->pixKey());
    }

    public function pixKeyFormatted(): string
    {
        return Freelancer::formatPixKey($this->pixKey());
    }

    public function pixKeyTypeLabel(): string
    {
        return Freelancer::pixKeyTypeLabelFor($this->pixKey());
    }

    /** A chave foi conferida com o freelancer no momento da assinatura? */
    public function pixKeyWasConfirmed(): bool
    {
        return $this->pix_key_confirmed_at !== null;
    }

    /**
     * A chave que o freelancer conferiu deixou de ser a do cadastro — o
     * cadastro foi alterado depois da assinatura. Não é erro: é o aviso de que
     * o Pix vai sair para uma chave diferente da que está no documento.
     */
    public function pixKeyDivergesFromFreelancer(): bool
    {
        return $this->pix_key !== null
            && $this->freelancer !== null
            && $this->pix_key !== $this->freelancer->pixKey();
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

        if ($this->isCommissionAmendment()) {
            return 'Termo Aditivo de Comissão sobre Vendas';
        }

        // A numeração é dos aditivos de horário: a comissão é única por turno e
        // não entra na contagem.
        $order = $this->amendmentOrder();

        return ($order > 1 ? $order . 'º ' : '')
            . 'Termo Aditivo ao Contrato Autônomo de Serviços de Freelancer';
    }

    /**
     * O que este documento é, quando não é o contrato do turno — para as telas
     * onde ele aparece como uma linha a pagar (lote, aprovação, financeiro,
     * e-mail da diretoria). Null no contrato comum: nada a acrescentar.
     *
     * Sem isto, quem aprova vê o mesmo freelancer duas vezes no mesmo dia, com
     * dois valores, e não tem como saber se é para pagar os dois ou se alguém
     * duplicou o lançamento.
     */
    public function kindLabel(): ?string
    {
        return match (true) {
            $this->isCommissionAmendment() => 'Comissão de venda',
            $this->isAmendment() => 'Aditivo de horário',
            $this->isAmended() => 'Aditivado',
            default => null,
        };
    }

    /** A frase que acompanha o rótulo e responde "então este valor soma ou substitui?". */
    public function kindNote(): ?string
    {
        return match (true) {
            $this->isCommissionAmendment() => trim(sprintf(
                '%s — acresce ao contrato #%s do mesmo turno, que continua sendo pago à parte.',
                $this->commissionExplanation() ?? 'Comissão sobre as vendas do turno',
                $this->parent_service_id,
            )),
            $this->isAmendment() => sprintf(
                'Substitui o contrato #%s, que foi assinado mas não é pago.',
                $this->parent_service_id,
            ),
            $this->isAmended() => sprintf(
                'O pagamento do turno é feito pelo aditivo #%s.',
                $this->amendment_service_id,
            ),
            default => null,
        };
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
            // A comissão remunera vendas, não um período: mudar horário nela não
            // significaria nada. O aditivo de horário se faz no contrato do turno.
            $this->isCommissionAmendment() => 'Comissão de venda não recebe aditivo de horário.',
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

    /* ---------------------------------------------------------------------
     | Comissão de venda
     |
     | O segundo tipo de aditivo, exclusivo de quem tem `allows_sales_commission`
     | na função (hoje, só o Garçom): remuneração variável sobre o que se vendeu
     | no turno, assinada ao FINAL do expediente.
     |
     | Ao contrário do aditivo de horário, a comissão NÃO substitui o contrato
     | base — ela ACRESCE. O turno continua sendo pago pelo contrato, e a
     | comissão é paga por cima, como documento próprio. Por isso ela não marca
     | `amended_at` em ninguém.
     |---------------------------------------------------------------------*/

    public function isScheduleAmendment(): bool
    {
        return $this->amendment_type === self::AMENDMENT_SCHEDULE;
    }

    public function isCommissionAmendment(): bool
    {
        return $this->amendment_type === self::AMENDMENT_COMMISSION;
    }

    /**
     * Quanto se paga de comissão sobre $sales.
     *
     * `block` conta **blocos fechados**: R$ 50,00 a cada R$ 1.000,00
     * integralmente vendidos, arredondando para baixo — a mesma lógica dos
     * blocos de 15 minutos, e o que diferencia este critério do percentual (com
     * proporcionalidade, R$ 50 por R$ 1.000 seriam os mesmos 5%, e escolher o
     * método não mudaria nada). Ex.: R$ 1.900 pagam R$ 50, não R$ 95.
     */
    public static function commissionFor(string $method, float $sales): float
    {
        if ($sales <= 0) {
            return 0.0;
        }

        return match ($method) {
            'percent' => round($sales * self::COMMISSION_PERCENT / 100, 2),
            'block' => floor($sales / self::COMMISSION_BLOCK_SALES) * self::COMMISSION_BLOCK_VALUE,
            default => 0.0,
        };
    }

    /**
     * A conta demonstrada, para o freelancer conferir antes de assinar:
     * "12 blocos de R$ 1.000,00 × R$ 50,00" ou "5% de R$ 12.400,00".
     */
    public static function commissionExplanationFor(string $method, float $sales): string
    {
        if ($method === 'percent') {
            return self::COMMISSION_PERCENT . '% de R$ ' . number_format($sales, 2, ',', '.');
        }

        $blocks = (int) floor($sales / self::COMMISSION_BLOCK_SALES);

        return $blocks . ' bloco' . ($blocks === 1 ? '' : 's') . ' de R$ '
            . number_format(self::COMMISSION_BLOCK_SALES, 2, ',', '.')
            . ' × R$ ' . number_format(self::COMMISSION_BLOCK_VALUE, 2, ',', '.');
    }

    /** A mesma conta, já com os dados gravados neste documento. */
    public function commissionExplanation(): ?string
    {
        if (!$this->isCommissionAmendment() || $this->sales_amount === null) {
            return null;
        }

        return self::commissionExplanationFor($this->commission_method, (float) $this->sales_amount);
    }

    /** Rótulo do critério usado, como aparece na tela e no documento. */
    public function commissionMethodLabel(): ?string
    {
        return self::COMMISSION_METHODS[$this->commission_method] ?? null;
    }

    /** Tem relatório de vendas do MultiVendas anexado? */
    public function hasSalesReport(): bool
    {
        return is_array($this->sales_report) && ($this->sales_report['sections'] ?? []) !== [];
    }

    /**
     * Seções do relatório anexo, prontas para exibir.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function salesReportSections(): array
    {
        return $this->sales_report['sections'] ?? [];
    }

    /**
     * O valor apurado pelo sistema difere do que foi considerado na comissão?
     * Acontece quando o operador corrige o número à mão — e o documento tem de
     * dizer isso, já que o anexo mostra o outro valor.
     */
    public function salesAmountWasAdjusted(): bool
    {
        if (!$this->hasSalesReport() || $this->sales_amount === null) {
            return false;
        }

        return abs((float) ($this->sales_report['base'] ?? 0) - (float) $this->sales_amount) >= 0.01;
    }

    /**
     * O valor apurado só pode ser alterado com justificativa do operador.
     *
     * A pergunta é sobre o relatório: sem ele não existe valor de origem a
     * alterar, e o documento já diz que o número foi informado pelo CONTRATANTE.
     * Com relatório e número diferente, a justificativa é parte do termo — quem
     * assina lê a diferença e o motivo dela, lado a lado com o Anexo I.
     *
     * O tamanho mínimo é proposital: "ajuste" ou "ok" não explicam nada, e uma
     * justificativa que não explica é pior que nenhuma, porque dá a aparência de
     * controle.
     */
    public const SALES_ADJUSTMENT_REASON_MIN = 10;

    public static function salesAdjustmentIsRequired(?array $report, float $salesAmount): bool
    {
        return $report !== null
            && ($report['sections'] ?? []) !== []
            && abs((float) ($report['base'] ?? 0) - $salesAmount) >= 0.01;
    }

    /** Período apurado, formatado: "04/08/2026 14:00 → 04/08/2026 19:00". */
    public function salesPeriodLabel(): ?string
    {
        if ($this->sales_period_start === null || $this->sales_period_end === null) {
            return null;
        }

        return $this->sales_period_start->format('d/m/Y H:i') . ' → ' . $this->sales_period_end->format('d/m/Y H:i');
    }

    public function canReceiveCommission(): bool
    {
        return $this->commissionBlockReason() === null;
    }

    /**
     * Por que este contrato não recebe comissão — null quando recebe.
     *
     * Repare no que NÃO bloqueia: lote enviado, aprovação da gerência ou da
     * diretoria, e até o pagamento. É a diferença de natureza entre os dois
     * aditivos — o de horário mexe no contrato que a gerência está analisando,
     * enquanto a comissão nasce como documento novo, que segue sozinho para o
     * lote seguinte. Isso importa: o valor de venda pode chegar depois, e com a
     * captura automática vai chegar mesmo.
     */
    public function commissionBlockReason(): ?string
    {
        return match (true) {
            $this->isCommissionAmendment() => 'Este documento já é uma comissão de venda.',
            !$this->functionAllowsCommission() => 'A função ' . ($this->functionFreelancer?->name ?? 'deste contrato')
                . ' não recebe comissão de venda.',
            $this->isCancelled() => 'Contrato cancelado não recebe comissão.',
            // A comissão acompanha um turno que aconteceu, e o que prova isso é
            // a assinatura do freelancer no contrato.
            $this->freelancer_signed_at === null => 'O freelancer ainda não assinou este contrato.',
            $this->isAmended() => 'Este contrato foi substituído por um aditivo — a comissão se faz sobre o aditivo vigente.',
            $this->hasCommissionChild() => 'Este turno já tem uma comissão de venda.',
            default => null,
        };
    }

    /**
     * Já pendurei uma comissão NESTE contrato? Pergunta barata — olha só os
     * filhos diretos, e usa a relação já carregada quando houver, porque isto é
     * chamado uma vez por linha nas listagens.
     *
     * A checagem completa, que varre a cadeia inteira do turno, é
     * `shiftHasCommission()` e roda uma vez só, na gravação: é lá que ela
     * precisa ser exata.
     */
    public function hasCommissionChild(): bool
    {
        // Registro que ainda não existe no banco não tem filhos.
        if (!$this->exists) {
            return false;
        }

        if ($this->relationLoaded('amendments')) {
            return $this->amendments
                ->contains(fn(self $a) => $a->isCommissionAmendment() && !$a->isCancelled());
        }

        return $this->amendments()
            ->where('amendment_type', self::AMENDMENT_COMMISSION)
            ->where('status_id', '!=', self::STATUS_CANCELLED)
            ->exists();
    }

    public function functionAllowsCommission(): bool
    {
        return (bool) ($this->functionFreelancer?->allows_sales_commission);
    }

    /**
     * Já existe comissão para este TURNO — não só para esta linha. Com um
     * aditivo de horário no meio, a comissão pode estar pendurada no contrato
     * antigo, e sem varrer a cadeia o mesmo turno receberia duas. Canceladas não
     * contam.
     *
     * Custa algumas consultas, então é a checagem da GRAVAÇÃO (uma vez por
     * comissão criada). As telas usam `hasCommissionChild()`, que é barata; no
     * caso raro em que as duas discordam, o botão aparece e o servidor recusa
     * com o motivo.
     */
    public function shiftHasCommission(): bool
    {
        return static::whereIn('id', $this->shiftServiceIds())
            ->where('amendment_type', self::AMENDMENT_COMMISSION)
            ->where('status_id', '!=', self::STATUS_CANCELLED)
            ->exists();
    }

    /**
     * Ids de todos os documentos do mesmo turno: o contrato original e tudo o
     * que desceu dele (aditivos de horário e a comissão).
     *
     * @return array<int, int>
     */
    public function shiftServiceIds(): array
    {
        // Sobe até o contrato original...
        $root = $this;
        $guard = 0;

        while ($root->parent_service_id !== null && $guard++ < 20) {
            $parent = $root->baseService;

            if ($parent === null) {
                break;
            }

            $root = $parent;
        }

        // ...e desce recolhendo os filhos, nível a nível. As cadeias reais têm
        // um ou dois níveis; o teto é trava contra dado corrompido.
        $ids = [$root->id];
        $frontier = [$root->id];
        $depth = 0;

        while ($frontier && $depth++ < 20) {
            $frontier = static::whereIn('parent_service_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return array_values(array_unique(array_filter($ids)));
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
        // Aditivo é assinado DEPOIS de o turno começar, por definição: o de
        // horário nasce quando o turno muda, e a comissão é assinada ao final do
        // expediente. Cobrar deles o prazo do contrato marcaria todos como
        // atrasados e encheria a lista de falhas de ruído.
        if ($this->isAmendment()) {
            return null;
        }

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
        // Mesma razão de minutesFromStartToSignature(): o prazo do contrato não
        // se aplica a aditivo.
        if ($this->isAmendment() || $this->isCancelled() || $this->freelancer_signed_at !== null) {
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
     | Liberação na portaria
     |
     | O contrato é o que autoriza o freelancer a entrar no clube: ele tem
     | serviço registrado, então tem entrada liberada. A janela começa 30 min
     | ANTES do início do turno — quem trabalha às 08:00 entra a partir das
     | 07:30 — e vai até o horário de término do contrato.
     |
     | A assinatura NÃO entra na conta: ela é colhida no tablet, dentro do
     | clube, depois de o freelancer já ter passado pela portaria. Exigi-la
     | aqui deixaria todo freelancer do lado de fora.
     |---------------------------------------------------------------------*/

    /** Instante a partir do qual este contrato libera a portaria. */
    public function accessOpensAt(): Carbon
    {
        return $this->startsAt()->subMinutes(self::ACCESS_EARLY_MINUTES);
    }

    /**
     * Este contrato libera a entrada no instante informado (padrão: agora)?
     * Contrato cancelado não libera nada, e contrato sem período gravado
     * também não — não há janela a calcular.
     */
    public function allowsAccessAt(?Carbon $moment = null): bool
    {
        if ($this->isCancelled()) {
            return false;
        }

        if ($this->start_date === null || $this->end_date === null
            || blank($this->start_time) || blank($this->end_time)) {
            return false;
        }

        $moment ??= Carbon::now();

        return $moment->greaterThanOrEqualTo($this->accessOpensAt())
            && $moment->lessThanOrEqualTo($this->endsAt());
    }

    /** Ex.: "07:30 → 12:00" — a janela de entrada, para exibição na portaria. */
    public function formattedAccessWindow(): ?string
    {
        if ($this->start_date === null || $this->end_date === null
            || blank($this->start_time) || blank($this->end_time)) {
            return null;
        }

        return $this->accessOpensAt()->format('H:i') . ' → ' . $this->endsAt()->format('H:i');
    }

    /**
     * Peneira grossa dos contratos que PODEM estar liberando a portaria no
     * instante informado: não cancelados, não aditivados e com o turno
     * começando na véspera, no dia ou no dia seguinte — o bastante para pegar
     * turnos que viram a meia-noite e a antecedência de 30 min. A janela exata
     * é conferida em PHP por `allowsAccessAt()`, porque data e hora moram em
     * colunas separadas e concatená-las em SQL muda de MySQL para SQLite.
     *
     * Aditivado fica de fora: quem responde pelo período corrigido é o aditivo,
     * e o base pode ter horário que não vale mais.
     */
    public function scopeAroundAccessWindow($query, ?Carbon $moment = null)
    {
        $moment ??= Carbon::now();

        return $query->where('status_id', '!=', self::STATUS_CANCELLED)
            ->whereNull('amended_at')
            ->whereBetween('start_date', [
                $moment->copy()->subDay()->startOfDay(),
                $moment->copy()->addDay()->endOfDay(),
            ]);
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

        // O dia do turno ainda não fechou — ver "Liberação para a coordenação".
        // Na prática esta linha raramente decide algo, porque um contrato só
        // fica com as duas assinaturas depois de liberado; ela cobre o contrato
        // assinado antes de a regra existir.
        if (!$this->hasBeenReleased()) {
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
            // O turno só entra em lote depois da manhã seguinte, quando não
            // cabe mais aditivo — ver "Liberação para a coordenação".
            ->released()
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
     * Existe um Pix deste contrato em andamento, já finalizado ou com desfecho
     * desconhecido? Enquanto houver, a tela não oferece o botão de baixa — é a
     * trava visual contra o segundo clique, complementar à trava do serviço.
     *
     * Depende de `latestPixPayment` estar carregada; sem ela, responde `false`
     * e a decisão fica com a trava do servidor, que é a que vale.
     */
    public function hasPixInProgress(): bool
    {
        $pix = $this->relationLoaded('latestPixPayment') ? $this->latestPixPayment : null;

        return $pix !== null && in_array($pix->status, PixPayment::BLOCKING_STATUSES, true);
    }

    /**
     * O contrato pode receber uma ordem de Pix agora? Só a leitura da tela —
     * quem realmente decide é `FreelancerService::pixBlockReason()`, no
     * servidor, onde a corrida entre duas abas é resolvida com lock.
     */
    public function canRequestPix(): bool
    {
        return $this->canBePaid() && !$this->hasPixInProgress();
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
            ->where('status_id', '!=', self::STATUS_CANCELLED)
            // O contrato do turno de hoje não aparece na fila: até as 08h de
            // amanhã ele ainda pode receber aditivo, e assinar agora fecharia
            // um documento que vai mudar.
            ->released();
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
     | Acompanhamento (a visão do Comercial)
     |
     | O contrato atravessa quatro etapas — assinaturas, gerência, diretoria e
     | pagamento — e quem registrou o serviço só conseguia saber onde ele parou
     | abrindo tela por tela. `trackingStage()` responde isso numa palavra.
     |
     | É a MESMA leitura que `signatureLabel()` e `approvalLabel()` fazem, mas
     | inteira e num eixo só: aqueles dois contam metade da história cada um, e
     | nenhum deles distingue "aprovado, esperando o dinheiro" de "pago" — que é
     | justamente a pergunta do fim da fila. Os contadores da tela usam os
     | escopos abaixo, escritos para casar com estes estados: contador e rótulo
     | discordando é o jeito de a tela mentir sem ninguém perceber.
     |---------------------------------------------------------------------*/

    /** @var array<string, string> */
    public const TRACKING_STAGES = [
        'awaiting_signatures' => 'Aguardando assinaturas',
        'awaiting_release' => 'Aguardando o fim do dia',
        'awaiting_batch' => 'Aguardando entrar em lote',
        'in_draft' => 'Em lote (rascunho)',
        'awaiting_manager' => 'Aguardando gerência',
        'awaiting_director' => 'Aguardando diretoria',
        'awaiting_payment' => 'Aguardando pagamento',
        'paying' => 'Pagamento em processamento',
        'paid' => 'Pago',
        'manager_rejected' => 'Recusado pela gerência',
        'director_rejected' => 'Recusado pela diretoria',
        'amended' => 'Substituído por aditivo',
        'cancelled' => 'Cancelado',
    ];

    /**
     * Onde este contrato está, agora. A ordem do `match` é a precedência: o
     * desfecho vem antes da etapa (um contrato pago não está "aguardando
     * pagamento"), e o que saiu do fluxo — cancelado, aditivado — vem antes de
     * tudo, porque nele as etapas seguintes não vão acontecer.
     */
    public function trackingStage(): string
    {
        return match (true) {
            $this->isCancelled() => 'cancelled',
            $this->isAmended() => 'amended',
            // Antes de "aguardando assinaturas": a assinatura que falta é a do
            // coordenador, e ela não falta por esquecimento — o dia do turno
            // ainda não fechou. Dizer só "aguardando assinaturas" faria o
            // Comercial ir cobrar uma assinatura que a regra está segurando.
            $this->awaitsRelease() => 'awaiting_release',
            !$this->isFullySigned() => 'awaiting_signatures',
            $this->isPaid() => 'paid',
            // Depende de `latestPixPayment` carregada; sem ela o contrato
            // aparece como "aguardando pagamento", que é o estado anterior e
            // não uma informação errada.
            $this->hasPixInProgress() => 'paying',
            $this->isDirectorApproved() => 'awaiting_payment',
            $this->isDirectorRejected() => 'director_rejected',
            $this->isManagerRejected() => 'manager_rejected',
            $this->isManagerApproved() => 'awaiting_director',
            $this->isInOpenBatch() && $this->batch->isSent() => 'awaiting_manager',
            $this->isInOpenBatch() => 'in_draft',
            default => 'awaiting_batch',
        };
    }

    public function trackingStageLabel(): string
    {
        return self::TRACKING_STAGES[$this->trackingStage()] ?? $this->trackingStage();
    }

    /**
     * Contratos que o fluxo ainda não descartou: nem cancelados, nem
     * substituídos por aditivo. Base de todos os escopos de acompanhamento —
     * contar um contrato aditivado seria contar o mesmo turno duas vezes.
     */
    public function scopeInTrackingFlow($query)
    {
        return $query->where('status_id', '!=', self::STATUS_CANCELLED)->whereNull('amended_at');
    }

    /**
     * O freelancer assinou e o contrato espera a manhã seguinte para ir à
     * coordenação. Fila própria: quem está aqui não depende de ninguém, só do
     * relógio.
     */
    public function awaitsRelease(?Carbon $moment = null): bool
    {
        return $this->freelancer_signed_at !== null
            && $this->coordinator_signed_at === null
            && !$this->hasBeenReleased($moment);
    }

    public function scopeAwaitingRelease($query, ?Carbon $moment = null)
    {
        return $query->inTrackingFlow()
            ->whereNotNull('freelancer_signed_at')
            ->whereNull('coordinator_signed_at')
            ->notReleased($moment);
    }

    /**
     * Falta a assinatura de uma das partes (ou das duas) — **menos** os que
     * estão só esperando o relógio, que têm fila própria. A exclusão é escrita
     * como a negação literal de `awaitingRelease`, para as duas filas não
     * poderem se sobrepor quando uma delas mudar.
     */
    public function scopeAwaitingSignature($query)
    {
        return $query->inTrackingFlow()
            ->where(fn($q) => $q
                ->whereNull('freelancer_signed_at')
                ->orWhereNull('coordinator_signed_at'))
            ->whereNot(fn($q) => $q
                ->whereNotNull('freelancer_signed_at')
                ->whereNull('coordinator_signed_at')
                ->notReleased());
    }

    /** Assinado pelas duas partes e parado num lote que a gerência ainda não analisou. */
    public function scopeAwaitingManagerReview($query)
    {
        return $query->inTrackingFlow()
            ->whereNotNull('freelancer_signed_at')
            ->whereNotNull('coordinator_signed_at')
            ->whereHas('batch', fn($b) => $b->where('status', FreelancerServiceBatch::STATUS_SENT));
    }

    /** Aprovado pela gerência, esperando o código que o diretor dita. */
    public function scopeAwaitingDirectorReview($query)
    {
        return $query->inTrackingFlow()
            ->whereNotNull('manager_approved_at')
            ->whereNull('director_approved_at')
            ->whereNull('director_rejected_at')
            ->whereHas('batch', fn($b) => $b->where('status', FreelancerServiceBatch::STATUS_AWAITING_DIRECTOR));
    }

    /** Aprovado nos dois níveis e ainda não pago — a fila do financeiro. */
    public function scopeAwaitingPayment($query)
    {
        return $query->awaitingFinance()->where('paid', false);
    }

    public function scopePaidServices($query)
    {
        return $query->awaitingFinance()->where('paid', true);
    }

    /* ---------------------------------------------------------------------
     | Regra de limite semanal
     |---------------------------------------------------------------------*/

    /**
     * Verifica se este serviço faz parte de uma semana de calendário (segunda a
     * domingo) em que o freelancer acumula mais serviços que o limite
     * recomendado. Contratos cancelados não entram na conta.
     */
    public function exceedsWeeklyLimit(): bool
    {
        return static::countInWeeklyWindow($this->freelancer_id, $this->start_date) > self::WEEKLY_LIMIT;
    }

    /**
     * Início e fim (segunda 00:00 a domingo 23:59:59) da semana de calendário
     * que contém $date. A semana é um bloco fixo: contratos de sábado/domingo
     * ficam na semana que já começou na segunda anterior, nunca na seguinte —
     * por isso a segunda-feira sempre "zera" a contagem.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function weekBounds(Carbon $date): array
    {
        $start = $date->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $end = $start->copy()->addDays(self::WEEKLY_WINDOW_DAYS - 1)->endOfDay();

        return [$start, $end];
    }

    /**
     * Serviços já registrados para o freelancer na semana de calendário (segunda
     * a domingo) que contém $startDate. Por ser um bloco fixo, não importa a
     * ordem de lançamento: qualquer contrato com start_date na mesma semana
     * conta junto. Contratos cancelados não entram na conta.
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
        [$weekStart, $weekEnd] = self::weekBounds($date);

        $dates = static::weeklyWindowDates($freelancerId, $weekStart, $weekEnd)
            ->concat(collect($extraDates)->map(fn($value) => Carbon::parse($value)->startOfDay()));

        return $dates->filter(fn(Carbon $other) => $other->between($weekStart, $weekEnd))->count();
    }

    /**
     * Datas já gravadas que podem cair na semana [$weekStart, $weekEnd].
     * Isolado do resto do cálculo para que a regra possa ser exercitada nos
     * testes sem banco.
     *
     * Aditivos ficam de fora: eles não acrescentam um dia de trabalho, apenas
     * remendam um turno já contado pelo contrato base. Contá-los faria o
     * segundo documento do mesmo dia estourar o limite sozinho.
     *
     * @return Collection<int, Carbon>
     */
    protected static function weeklyWindowDates(int $freelancerId, Carbon $weekStart, Carbon $weekEnd): Collection
    {
        return static::where('freelancer_id', $freelancerId)
            ->where('status_id', '!=', self::STATUS_CANCELLED)
            ->whereNull('parent_service_id')
            ->whereBetween('start_date', [$weekStart, $weekEnd])
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
     * regra de semana de calendário (segunda a domingo) por freelancer.
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

            [$weekStart, $weekEnd] = self::weekBounds(Carbon::parse($service->start_date)->startOfDay());

            // Mesma regra de countInWeeklyWindow (bloco fixo da semana), só que
            // sobre o que já está em memória.
            $count = $considered
                ->filter(fn($other) => $other->freelancer_id === $service->freelancer_id)
                ->map(fn($other) => Carbon::parse($other->start_date)->startOfDay())
                ->filter(fn(Carbon $other) => $other->between($weekStart, $weekEnd))
                ->count();

            return [$service->id => $count > self::WEEKLY_LIMIT];
        });
    }

    /* ---------------------------------------------------------------------
     | Funções já exercidas
     |---------------------------------------------------------------------*/

    /**
     * Em que funções cada freelancer já atuou e quantas vezes — o que a
     * listagem mostra em forma de tag ("Garçom - 4").
     *
     * As exclusões são as mesmas de weeklyWindowDates(): contrato cancelado
     * não foi trabalhado, e aditivo apenas remenda um turno que o contrato
     * base já conta. Sem elas a tag diria que o freelancer atuou mais vezes
     * do que de fato pegou serviço.
     *
     * Uma consulta agregada para a listagem inteira, e não uma por card.
     *
     * @param  array<int, int>  $freelancerIds
     * @return Collection<int, array<string, int>>  id do freelancer => [função => total], do mais atuado ao menos
     */
    public static function functionCountsFor(array $freelancerIds): Collection
    {
        if ($freelancerIds === []) {
            return collect();
        }

        return static::query()
            ->join('function_freelancers', 'function_freelancers.id', '=', 'freelancer_services.function_freelancer_id')
            ->whereIn('freelancer_services.freelancer_id', $freelancerIds)
            ->where('freelancer_services.status_id', '!=', self::STATUS_CANCELLED)
            ->whereNull('freelancer_services.parent_service_id')
            ->groupBy('freelancer_services.freelancer_id', 'function_freelancers.name')
            ->select('freelancer_services.freelancer_id', 'function_freelancers.name as function_name')
            ->selectRaw('COUNT(*) as total')
            ->get()
            ->groupBy('freelancer_id')
            ->map(fn(Collection $rows) => $rows
                ->sortBy([['total', 'desc'], ['function_name', 'asc']])
                ->mapWithKeys(fn($row) => [$row->function_name => (int) $row->total])
                ->all());
    }
}
