<?php

namespace App\Models\Replay;

use App\Models\Member;
use App\Models\Place;
use App\Models\PlaceGroup;
use App\Models\Schedule;
use App\Services\Replay\MediaService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Um clipe gravado. Vive 7 dias contados da GRAVAÇÃO (não do envio) e some —
 * sem exceção, por decisão de quem opera. Ver o comando `replay:prune`.
 */
class Video extends Model
{
    use HasFactory;

    protected $table = 'replay_videos';

    /** Retenção fixa, em dias. Muda aqui e em lugar nenhum mais. */
    const RETENTION_DAYS = 7;

    protected $fillable = [
        'uuid',
        'place_id',
        'place_group_id',
        'replay_camera_id',
        'schedule_id',
        'member_id',
        'external_id',
        'recorded_at',
        'duration_seconds',
        'orientation',
        // Caminho do clipe no FTP (disco `replay_ftp`), como o sistema de
        // captura informou.
        'file_path',
        // Cópia servida pelo servidor web (disco `replay`).
        'local_path',
        'size_bytes',
        'expires_at',
        'download_count',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'expires_at' => 'datetime',
            'duration_seconds' => 'integer',
            'size_bytes' => 'integer',
            'download_count' => 'integer',
        ];
    }

    public function place()
    {
        return $this->belongsTo(Place::class, 'place_id');
    }

    public function group()
    {
        return $this->belongsTo(PlaceGroup::class, 'place_group_id');
    }

    public function camera()
    {
        return $this->belongsTo(Camera::class, 'replay_camera_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * O agendamento que cobria o instante da gravação, quando havia um pago.
     *
     * `withoutGlobalScopes()` porque Schedule esconde por padrão os
     * expirados: um vídeo gravado durante uma reserva que depois expirou
     * ainda precisa saber a qual reserva pertenceu.
     */
    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id')->withoutGlobalScopes();
    }

    /**
     * URL do clipe para o player e o download.
     *
     * Com a cópia local presente, é o arquivo estático (range request, sem
     * PHP no caminho). Sem ela, a rota que refaz a cópia a partir do FTP e
     * redireciona para o estático — o FTP não fala HTTP.
     */
    public function url(): ?string
    {
        if ($this->local_path && MediaService::disk()->exists($this->local_path)) {
            return MediaService::url($this->local_path);
        }

        return route('replay.videos.file', ['uuid' => $this->uuid]);
    }

    /** Dias inteiros que faltam para o expurgo — o que a tela mostra. */
    public function daysLeft(): int
    {
        return max(0, (int) ceil(now()->diffInDays($this->expires_at, false)));
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeAvailable($query)
    {
        return $query->where('expires_at', '>', now());
    }
}
