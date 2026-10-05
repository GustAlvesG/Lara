<?php

namespace App\Models\Replay;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de que a reserva já teve seu e-mail de "vídeos disponíveis"
 * enviado. É a trava que garante um aviso por reserva, mesmo com clipe
 * atrasado ou Job repetido — ver a migration.
 */
class MemberNotification extends Model
{
    use HasFactory;

    protected $table = 'replay_member_notifications';

    protected $fillable = [
        'schedule_id',
        'member_id',
        'videos_count',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'videos_count' => 'integer',
        ];
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}
