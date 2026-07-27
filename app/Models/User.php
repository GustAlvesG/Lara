<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\DataInfo;
use App\Models\Information;
use Spatie\Permission\Traits\HasRoles; // Importe o trait
use Illuminate\Database\Eloquent\SoftDeletes;


class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles; // Use o trait HasRoles
    use SoftDeletes;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

     protected $connection = 'mysql';

    protected $fillable = [
        'name',
        'email',
        'password',
        'pin',
        'cpf',
        'matricula',
        'last_login_at',
        'status_id',
    ];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'pin',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin' => 'hashed',
            'last_login_at' => 'datetime', // Isso permite usar Carbon no campo
        ];
    }

    /** O usuário definiu um PIN de assinatura (Kiosk)? */
    public function hasPin(): bool
    {
        return filled($this->pin);
    }

    /** Confere o PIN informado contra o hash guardado. */
    public function checkPin(?string $pin): bool
    {
        return $this->hasPin() && filled($pin) && \Illuminate\Support\Facades\Hash::check($pin, $this->pin);
    }

    //Relacionamento de um para muitos with data_info
    public function data_info()
    {
        return $this->hasMany(DataInfo::class, 'created_by');
    }

    //Relacionamento de um para muitos com information
    public function information()
    {
        return $this->hasMany(Information::class, 'created_by');
    }

    //Status Has ONE
    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id', 'id');
    }

    public function schedulesCreated()
    {
        return $this->hasMany(Schedule::class, 'created_by_user', 'id');
    }

    public function schedulesUpdated()
    {
        return $this->hasMany(Schedule::class, 'updated_by_user', 'id');
    }

    public function sectors()
    {
        return $this->belongsToMany(Sector::class, 'user_sector')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function isCoordinatorOf(Sector $sector): bool
    {
        return $this->sectors()
            ->wherePivot('sector_id', $sector->id)
            ->wherePivot('role', 'coordinator')
            ->exists();
    }

    /** Coordenador de ao menos um setor. */
    public function isCoordinator(): bool
    {
        return $this->sectors()->wherePivot('role', 'coordinator')->exists();
    }

    /**
     * Coordenador de um setor específico, identificado pelo nome (sem diferenciar
     * maiúsculas/acentuação de digitação). Usado pelo Kiosk, que só libera a
     * assinatura do coordenador para o setor Comercial.
     */
    public function isCoordinatorOfSectorNamed(string $name): bool
    {
        return $this->sectors()
            ->wherePivot('role', 'coordinator')
            ->whereRaw('LOWER(sectors.name) = ?', [mb_strtolower($name)])
            ->exists();
    }

    public function coordinatorSectors()
    {
        return $this->sectors()->wherePivot('role', 'coordinator');
    }

    public function collaboratorSectors()
    {
        return $this->sectors()->wherePivot('role', 'collaborator');
    }
}
