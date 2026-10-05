<?php

namespace App\Models\Replay;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

/**
 * Dono dos tokens Sanctum do sistema de captura.
 *
 * Mesmo arranjo do ApiCliente do Placar: não é usuário do sistema, não faz
 * login, não tem papel nem permissão. Implementa AuthenticatableContract
 * porque o guard `sanctum` precisa disso para aceitar o tokenable como
 * usuário autenticado da requisição — sem isso, `auth:sanctum` quebra com
 * TypeError ao resolver o token. Nenhum método de senha do trait é usado;
 * eles só existem para satisfazer o contrato.
 */
class ApiClient extends Model implements AuthenticatableContract
{
    use HasFactory, HasApiTokens, Authenticatable;

    protected $table = 'replay_api_clients';

    protected $fillable = [
        'name',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
