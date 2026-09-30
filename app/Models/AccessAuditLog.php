<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Uma mudança de acesso: quem fez (actor), em quem (user), em qual setor e
 * qual permissão. Ver a migration `create_access_audit_logs_table`.
 *
 * Só se escreve por AccessAuditLog::record(); nunca se edita.
 */
class AccessAuditLog extends Model
{
    public const UPDATED_AT = null;

    public const SECTOR_MEMBER_ADDED = 'sector.member_added';
    public const SECTOR_MEMBER_REMOVED = 'sector.member_removed';
    public const SECTOR_MEMBER_ROLE_CHANGED = 'sector.member_role_changed';
    public const SECTOR_PERMISSIONS_CHANGED = 'sector.permissions_changed';
    public const SECTOR_FULL_ACCESS_CHANGED = 'sector.full_access_changed';
    public const SECTOR_CREATED = 'sector.created';
    public const SECTOR_DELETED = 'sector.deleted';
    public const USER_CREATED = 'user.created';
    public const USER_PERMISSIONS_CHANGED = 'user.permissions_changed';

    protected $fillable = ['actor_id', 'action', 'user_id', 'sector_id', 'permission', 'details'];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public static function record(string $action, ?int $userId = null, ?int $sectorId = null, ?string $permission = null, array $details = []): self
    {
        return self::create([
            'actor_id' => auth()->id(),
            'action' => $action,
            'user_id' => $userId,
            'sector_id' => $sectorId,
            'permission' => $permission,
            'details' => $details ?: null,
        ]);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sector()
    {
        return $this->belongsTo(Sector::class, 'sector_id');
    }
}
