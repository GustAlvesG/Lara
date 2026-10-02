<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Uma gravação de um fluxo do bot — ver a migration. */
class BotFlowVersion extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['bot_flow_id', 'name', 'active', 'definition', 'user_id', 'user_name'];

    protected $casts = [
        'active' => 'boolean',
        'definition' => 'array',
    ];
}
