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
 */
class Member extends Model
{

    use HasFactory;
    use SoftDeletes;
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
