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
            'last_login_at' => 'datetime', // Isso permite usar Carbon no campo
        ];
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
}
