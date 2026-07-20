<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UberAccessRequest extends Model
{
    use HasFactory;

    public const STATUS_AGUARDANDO_NOME = 'aguardando_nome';
    public const STATUS_AGUARDANDO_LOCAL = 'aguardando_local';
    public const STATUS_AGUARDANDO_PLACA = 'aguardando_placa';
    public const STATUS_AGUARDANDO_PRINT = 'aguardando_print';
    public const STATUS_CONCLUIDO = 'concluido';
    public const STATUS_EXPIRADO = 'expirado';

    public const TERMINAL_STATUSES = [
        self::STATUS_CONCLUIDO,
        self::STATUS_EXPIRADO,
    ];

    protected $fillable = [
        'contact_uuid',
        'contact_phone',
        'contact_name_whatsapp',
        'poli_attendance_uuid',
        'status',
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
