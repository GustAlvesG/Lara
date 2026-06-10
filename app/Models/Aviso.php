<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Aviso extends Model
{
    use HasFactory, SoftDeletes;

    const PRIVACY_PESSOA  = 'pessoa';
    const PRIVACY_SETOR   = 'setor';
    const PRIVACY_PUBLICO = 'publico';

    const PRIVACY_LABELS = [
        self::PRIVACY_PESSOA  => 'Pessoal',
        self::PRIVACY_SETOR   => 'Setor',
        self::PRIVACY_PUBLICO => 'Público',
    ];

    protected $fillable = [
        'title',
        'content',
        'image',
        'privacy',
        'expires_at',
        'expiry_notified',
        'created_by',
    ];

    protected $casts = [
        'expires_at'      => 'date',
        'expiry_notified' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lembretes()
    {
        return $this->hasMany(Lembrete::class)->orderBy('remind_at');
    }

    public function views()
    {
        return $this->hasMany(AvisoView::class)->orderByDesc('viewed_at');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'aviso_tag')->orderBy('name');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function expiresSoon(): bool
    {
        return $this->expires_at
            && !$this->isExpired()
            && $this->expires_at->lte(Carbon::today()->addDays(3));
    }

    public function privacyLabel(): string
    {
        return self::PRIVACY_LABELS[$this->privacy] ?? $this->privacy;
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>=', today());
        });
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<', today());
    }

    /**
     * Busca por título ou por nome de tag (case-insensitive).
     */
    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
              ->orWhereHas('tags', fn($t) => $t->where('name', 'like', '%' . Tag::normalize($term) . '%'));
        });
    }

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasRole('admin')) {
            return $query;
        }

        $roleNames = $user->roles->pluck('name');

        return $query->where(function ($q) use ($user, $roleNames) {
            // Público: todos veem
            $q->where('privacy', self::PRIVACY_PUBLICO);

            // Setor: criador compartilha ao menos um role com o usuário atual
            $q->orWhere(function ($sq) use ($user, $roleNames) {
                $sq->where('privacy', self::PRIVACY_SETOR)
                   ->where(function ($cq) use ($user, $roleNames) {
                       $cq->where('created_by', $user->id)
                          ->orWhereHas('creator', function ($rq) use ($roleNames) {
                              $rq->whereHas('roles', fn($r) => $r->whereIn('name', $roleNames));
                          });
                   });
            });

            // Pessoal: só o criador
            $q->orWhere(fn($sq) => $sq->where('privacy', self::PRIVACY_PESSOA)
                                      ->where('created_by', $user->id));
        });
    }
}
