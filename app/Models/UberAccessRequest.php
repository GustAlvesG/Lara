<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UberAccessRequest extends Model
{
    use HasFactory;

    public const STATUS_AGUARDANDO_MATRICULA = 'aguardando_matricula';
    public const STATUS_AGUARDANDO_NOME = 'aguardando_nome';
    public const STATUS_AGUARDANDO_LOCAL = 'aguardando_local';
    public const STATUS_AGUARDANDO_PLACA = 'aguardando_placa';
    public const STATUS_AGUARDANDO_PRINT = 'aguardando_print';
    public const STATUS_AGUARDANDO_ACESSO = 'aguardando_acesso';
    public const STATUS_CONCLUIDO = 'concluido';
    public const STATUS_EXPIRADO = 'expirado';

    /**
     * Status finais (não recebem mais mensagens do fluxo do WhatsApp):
     *   - concluido: o motorista efetivamente acessou;
     *   - expirado : a validade venceu sem acesso.
     * "aguardando_acesso" é intencionalmente não-terminal: é o pedido pronto,
     * aguardando o motorista chegar na portaria.
     */
    public const TERMINAL_STATUSES = [
        self::STATUS_CONCLUIDO,
        self::STATUS_EXPIRADO,
    ];

    /**
     * Conferência do par (nome, matrícula) contra o MultiClubes, feita quando
     * o pedido se completa. "indisponivel" é diferente de "nao_encontrado": o
     * primeiro é o MultiClubes fora do ar, o segundo é divergência real.
     */
    public const MEMBER_VALIDATION_VALIDADO = 'validado';
    public const MEMBER_VALIDATION_NAO_ENCONTRADO = 'nao_encontrado';
    public const MEMBER_VALIDATION_INDISPONIVEL = 'indisponivel';

    public const MEMBER_VALIDATION_LABELS = [
        self::MEMBER_VALIDATION_VALIDADO        => 'Sócio confere',
        self::MEMBER_VALIDATION_NAO_ENCONTRADO  => 'Nome/matrícula não confere',
        self::MEMBER_VALIDATION_INDISPONIVEL    => 'Não foi possível conferir',
    ];

    public function memberValidationLabel(): ?string
    {
        if ($this->member_validation === null) {
            return null;
        }

        return self::MEMBER_VALIDATION_LABELS[$this->member_validation] ?? $this->member_validation;
    }

    /**
     * Etapas em que o fluxo ainda está coletando respostas no WhatsApp. São as
     * únicas sujeitas ao timeout de inatividade: depois de "aguardando_acesso"
     * o associado já respondeu tudo e quem manda é a validade (expires_at).
     */
    public const CAPTURE_STATUSES = [
        self::STATUS_AGUARDANDO_MATRICULA,
        self::STATUS_AGUARDANDO_NOME,
        self::STATUS_AGUARDANDO_LOCAL,
        self::STATUS_AGUARDANDO_PLACA,
        self::STATUS_AGUARDANDO_PRINT,
    ];

    public const STATUS_LABELS = [
        self::STATUS_AGUARDANDO_MATRICULA => 'Aguardando matrícula',
        self::STATUS_AGUARDANDO_NOME      => 'Aguardando nome',
        self::STATUS_AGUARDANDO_LOCAL     => 'Aguardando local',
        self::STATUS_AGUARDANDO_PLACA     => 'Aguardando placa',
        self::STATUS_AGUARDANDO_PRINT     => 'Aguardando print',
        self::STATUS_AGUARDANDO_ACESSO    => 'Aguardando acesso do motorista',
        self::STATUS_CONCLUIDO            => 'Concluído',
        self::STATUS_EXPIRADO             => 'Expirado',
    ];

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    protected $fillable = [
        'contact_uuid',
        'contact_phone',
        'contact_name_whatsapp',
        'poli_attendance_uuid',
        'status',
        'matricula',
        'requester_name',
        'club_location',
        'vehicle_plate',
        'screenshot_url',
        'member_validation',
        'member_validation_name',
        'member_validated_at',
        'completed_at',
        'expires_at',
        'accessed_at',
        'last_message_at',
    ];

    protected $casts = [
        'member_validated_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'accessed_at' => 'datetime',
        'last_message_at' => 'datetime',
    ];

    public function messages()
    {
        return $this->hasMany(UberAccessRequestMessage::class);
    }

    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', self::TERMINAL_STATUSES);
    }
}
