<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Mensagem que passou pelo bot — recebida ou enviada por ele. O texto é
 * gravado mascarado (App\Services\PoliBot\PoliTextMask).
 */
class PoliMessage extends Model
{
    public const IN = 'IN';
    public const OUT = 'OUT';

    protected $fillable = [
        'uuid',
        'contact_uuid',
        'direction',
        'type',
        'texto',
        'template_uuid',
        'options',
        'flow_slug',
        'step_key',
        'ack',
        'error',
        'shadow',
    ];

    protected $casts = [
        'options' => 'array',
        'shadow' => 'boolean',
    ];
}
