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
 *   3. permissões individuais (model_has_permissions do Spatie);
 *
 * menos as **negadas** à pessoa (user_permission_denials, desde 17/11/2026),
 * que vencem as três — inclusive o acesso total.
 *
 * Quatro consultas por requisição, feitas uma vez só: User::access() guarda o
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

        // Negadas vencem tudo: saem da lista aqui e o UserAccess as recusa
        // também para quem tem acesso total.
        $denied = array_values(array_intersect(
            $db->table('user_permission_denials')
                ->join('permissions', 'permissions.id', '=', 'user_permission_denials.permission_id')
                ->where('user_permission_denials.user_id', $user->getKey())
                ->pluck('permissions.name')
                ->all(),
            Permissions::all()
        ));

        $permissions = array_values(array_diff(array_keys($sources), $denied));

        return new UserAccess($permissions, $fullAccess, $sources, $denied);
    }

    /**
     * Ids de quem recebeu a permissão de fato: por setor (respeitando
     * `coordinators_only`) ou individualmente. Quem só a tem pelo **acesso
     * total** fica de fora de propósito — é para avisar a equipe que cuida do
     * assunto, não Gerência, Diretoria e TI inteiras a cada evento.
     *
     * @return array<int, int>
     */
    public function holderIds(string $permission): array
    {
        $db = DB::connection((new User())->getConnectionName());

        $viaSector = $db->table('sector_permission')
            ->join('permissions', 'permissions.id', '=', 'sector_permission.permission_id')
            ->join('user_sector', 'user_sector.sector_id', '=', 'sector_permission.sector_id')
            ->where('permissions.name', $permission)
            ->where(function ($q) {
                $q->where('sector_permission.coordinators_only', false)
                    ->orWhere('user_sector.role', 'coordinator');
            })
            ->pluck('user_sector.user_id');

        $direct = $db->table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('permissions.name', $permission)
            ->where('model_has_permissions.model_type', (new User())->getMorphClass())
            ->pluck('model_has_permissions.model_id');

        // Quem teve a permissão negada não recebe o aviso dela.
        $denied = $db->table('user_permission_denials')
            ->join('permissions', 'permissions.id', '=', 'user_permission_denials.permission_id')
            ->where('permissions.name', $permission)
            ->pluck('user_permission_denials.user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $viaSector->merge($direct)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn ($id) => in_array($id, $denied, true))
            ->values()
            ->all();
    }
}
