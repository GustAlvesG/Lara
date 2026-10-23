<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O que a equipe fez numa conta do site (Reservas › Contas do site). Só
 * acrescenta. `details` guarda o antes e o depois dos dados corrigidos; a
 * senha nunca entra.
 */
class MemberAccountLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_UPDATED = 'updated';
    public const ACTION_PULLED = 'pulled_from_mc';
    public const ACTION_PASSWORD = 'temporary_password';
    public const ACTION_CREATED = 'created';
    public const ACTION_DEACTIVATED = 'deactivated';
    public const ACTION_RESTORED = 'restored';

    /** @var array<string, string> */
    public const ACTION_LABELS = [
        self::ACTION_UPDATED => 'corrigiu dados',
        self::ACTION_PULLED => 'trouxe dados do MultiClubes',
        self::ACTION_PASSWORD => 'gerou senha provisória',
        self::ACTION_CREATED => 'cadastrou a conta',
        self::ACTION_DEACTIVATED => 'desativou a conta',
        self::ACTION_RESTORED => 'reativou a conta',
    ];

    protected $connection = 'mysql';

    protected $fillable = [
        'member_id',
        'user_id',
        'action',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
