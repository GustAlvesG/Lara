<?php

namespace App\Models;

use App\Services\PoliBot\FlowDefinition;
use Illuminate\Database\Eloquent\Model;

/**
 * Um fluxo de conversa do bot do WhatsApp. O conteúdo é o JSON de
 * `definition`, lido sempre pelo FlowDefinition.
 */
class BotFlow extends Model
{
    protected $fillable = ['slug', 'name', 'active', 'definition'];

    protected $casts = [
        'active' => 'boolean',
        'definition' => 'array',
    ];

    public function flow(): FlowDefinition
    {
        return new FlowDefinition($this->slug, $this->definition ?? []);
    }

    public static function findActive(string $slug): ?self
    {
        return static::where('slug', $slug)->where('active', true)->first();
    }
}
