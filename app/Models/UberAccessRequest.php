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
        'completed_at',
        'expires_at',
        'accessed_at',
        'last_message_at',
    ];

    protected $casts = [
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
