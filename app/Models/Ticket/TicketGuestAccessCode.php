<?php

namespace App\Models\Ticket;

use Illuminate\Database\Eloquent\Model;

/**
 * Código de acesso do não sócio a "meus ingressos" no site: 6 dígitos por
 * e-mail, guardado só como hash. Vale `TicketGuestAccessService::CODE_MINUTES`
 * e aceita `MAX_ATTEMPTS` tentativas; usado, não vale de novo.
 */
class TicketGuestAccessCode extends Model
{
    protected $fillable = [
        'cpf',
        'email',
        'code_hash',
        'attempts',
        'expires_at',
        'used_at',
        'ip',
    ];

    protected $hidden = ['code_hash'];

    protected $casts = [
        'attempts' => 'integer',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];
}
