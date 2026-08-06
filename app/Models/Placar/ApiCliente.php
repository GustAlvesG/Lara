<?php

namespace App\Models\Placar;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

/**
 * Dono dos tokens de acesso pessoal do Sanctum usados pela integração com o
 * Node do Placar Clube. Não é um usuário do sistema — não faz login, não tem
 * papel nem permissão — só existe para o token ter a quem pertencer e para
 * dar nome/identidade a cada token emitido por `php artisan placar:token`.
 */
class ApiCliente extends Model
{
    use HasFactory, HasApiTokens;

    protected $table = 'placar_api_clientes';

    protected $fillable = [
        'nome',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }
}
