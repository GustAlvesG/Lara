<?php

namespace App\Authorization;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Calcula o acesso efetivo de um usuário a partir de três fontes:
 *
 *   1. setores com **acesso total** (sectors.full_access) — tudo do catálogo;
 *   2. permissões dos setores (sector_permission), respeitando
 *      `coordinators_only`: a permissão marcada assim só chega a quem
 *      coordena o setor;
 *   3. permissões individuais (model_has_permissions do Spatie).
 *
 * Três consultas por requisição, feitas uma vez só: User::access() guarda o
 * resultado na instância. Não há cache entre requisições de propósito — tirar
 * alguém de um setor corta o acesso no clique seguinte, sem esperar o cache
 * de 24h que o Spatie usaria.
 *
 * Usa a conexão do próprio User (presa a `mysql`, ver o model), e não a
 * padrão: as tabelas de setor e de permissão moram no mesmo banco que ele.
 */
class AccessResolver
{
    public function resolve(User $user): UserAccess
    {
        if (! $user->getKey()) {
            return UserAccess::none();
        }

        $db = DB::connection($user->getConnectionName());

        $fullAccess = $db->table('user_sector')
            ->join('sectors', 'sectors.id', '=', 'user_sector.sector_id')
            ->where('user_sector.user_id', $user->getKey())
            ->where('sectors.full_access', true)
            ->orderBy('sectors.name')
            ->pluck('sectors.name')
            ->all();

        $sources = [];

        $viaSector = $db->table('sector_permission')
            ->join('permissions', 'permissions.id', '=', 'sector_permission.permission_id')
            ->join('user_sector', 'user_sector.sector_id', '=', 'sector_permission.sector_id')
            ->join('sectors', 'sectors.id', '=', 'sector_permission.sector_id')
            ->where('user_sector.user_id', $user->getKey())
            ->where(function ($q) {
                $q->where('sector_permission.coordinators_only', false)
                    ->orWhere('user_sector.role', 'coordinator');
            })
            ->get(['permissions.name as permission', 'sectors.name as sector']);

        foreach ($viaSector as $row) {
            $sources[$row->permission][] = 'setor ' . $row->sector;
        }

        $direct = $db->table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_type', $user->getMorphClass())
            ->where('model_has_permissions.model_id', $user->getKey())
            ->pluck('permissions.name');

        foreach ($direct as $permission) {
            $sources[$permission][] = 'individual';
        }

        // Só o que está no catálogo conta. Uma permissão antiga que sobrou na
        // tabela (de antes da reforma) não pode virar acesso por engano.
        $sources = array_intersect_key($sources, array_flip(Permissions::all()));

        return new UserAccess(array_keys($sources), $fullAccess, $sources);
    }
}
