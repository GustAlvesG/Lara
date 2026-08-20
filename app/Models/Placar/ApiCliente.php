<?php

namespace App\Models\Placar;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

/**
 * Dono dos tokens de acesso pessoal do Sanctum usados pela integração com o
 * Node do Placar Clube. Não é um usuário do sistema — não faz login, não tem
 * papel nem permissão — só existe para o token ter a quem pertencer e para
 * dar nome/identidade a cada token emitido por `php artisan placar:token`.
 *
 * Implementa AuthenticatableContract (via o trait Authenticatable do próprio
 * framework, não o de Breeze) porque o guard `sanctum` precisa disso para
 * aceitar o tokenable como usuário autenticado da requisição — sem isso,
 * `auth:sanctum` quebra com TypeError ao resolver o token. Não usa nenhum dos
 * métodos de senha/remember-token do trait; eles só existem para satisfazer
 * o contrato.
 */
class ApiCliente extends Model implements AuthenticatableContract
{
    use HasFactory, HasApiTokens, Authenticatable;

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
