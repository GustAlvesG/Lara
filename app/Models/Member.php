<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Conta do sócio no site de locação (login = CPF). Os dados vêm do
 * MultiClubes no cadastro.
 *
 * SoftDeletes (desde 23/10/2026): a coluna `deleted_at` existe desde 2025,
 * mas nada a usava. As Contas do site desativam a conta duplicada (mesmo CPF)
 * por ela — conta desativada não entra no login nem aparece nas buscas por
 * CPF. Nunca apague a linha: reservas e inscrições apontam para ela.
 *
 * Perfil no site (`site_role`, desde 12/11/2026): `usuario` ou
 * `administrador`. Só o administrador abre os dashboards do site (/api/dashboard/*,
 * middleware `member_admin`). O perfil é lido do banco a cada requisição,
 * nunca do token: rebaixar corta o acesso na hora.
 */
class Member extends Model
{

    use HasFactory;
    use SoftDeletes;

    public const ROLE_USER = 'usuario';
    public const ROLE_ADMIN = 'administrador';

    /** @var array<string, string> */
    public const ROLE_LABELS = [
        self::ROLE_USER => 'Usuário',
        self::ROLE_ADMIN => 'Administrador',
    ];

    /** Administrador dos dashboards do site (conta ativa). */
    public function isSiteAdmin(): bool
    {
        return $this->site_role === self::ROLE_ADMIN && ! $this->trashed();
    }

    /** A conta que o login do site usa para o CPF: a ativa mais antiga. */
    public static function loginAccount(?string $cpf): ?self
    {
        if (blank($cpf)) {
            return null;
        }

        return static::where('cpf', $cpf)->orderBy('id')->first();
    }
    //Choose connection
    protected $connection = 'mysql';

    //Choose table
    protected $table = 'members';

    //Choose primary key
    protected $fillable = [
        'Id',
        'title',
        'cpf',
        'birth_date',
        'Barcode',
        'Name',
        'Titular',
        'telephone',
        'Email',
        'Password',
    ];
    //By default, ignore status_id = 4, Expired

    public function accountLogs(): HasMany
    {
        return $this->hasMany(MemberAccountLog::class)->orderByDesc('id');
    }

    protected $hidden = [
        'password',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
}
