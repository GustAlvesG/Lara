<?php

use App\Authorization\LegacyPermissionMap;
use App\Authorization\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Popula a reforma do acesso: catálogo, setores, o que cada setor recebe, e
 * duas pontes para o deploy não trancar ninguém do lado de fora.
 *
 * 1. **Catálogo.** Cada nome de App\Authorization\Permissions vira uma linha
 *    em `permissions` (guard `web`), com o rótulo como descrição.
 *
 * 2. **Setores.** Os que a matriz cita são procurados pelo nome sem
 *    diferenciar maiúsculas ("comercial" já existe assim em homologação) e
 *    criados se faltarem. "Secretaria" na definição do acesso é o setor
 *    **Atendimento**; "Financeiro" é a **Contabilidade** — e o setor
 *    **Finanças**, se existir, recebe o mesmo que ela (mas não é criado).
 *
 * 3. **Acesso total** em Gerência, Diretoria e TI.
 *
 * 4. **Matriz inicial** de setor → permissão. Daqui em diante ela é editada
 *    na tela de Setores. A inserção é insertOrIgnore: rodar de novo não
 *    duplica nem sobrescreve o "só coordenadores" escolhido na tela, mas
 *    DEVOLVE uma concessão que a tela tenha tirado — como toda migration, ela
 *    roda uma vez por ambiente, e não deve ser re-executada à mão.
 *
 * 5. **Ponte das permissões individuais.** Quem tinha uma permissão antiga
 *    dada direto ao usuário (não por role) recebe as equivalentes novas —
 *    ver LegacyPermissionMap.
 *
 * 6. **Ponte dos administradores.** Quem tem a role `admin` recebe, como
 *    permissão individual, `usuarios.gerenciar` e `setores.gerenciar`. Sem
 *    isso, no dia do deploy ninguém alcançaria a tela onde se vinculam as
 *    pessoas aos setores. NÃO dá acesso total: o admin enxerga as telas de
 *    gestão e, dali, se coloca no setor que de fato ocupa.
 *
 * Nada aqui apaga role ou permissão antiga — isso é do `acesso:limpar-legado`,
 * depois que o `acesso:diferenca` estiver limpo.
 */
return new class extends Migration
{
    private const SECRETARIA = 'Atendimento';
    private const FINANCEIRO = 'Contabilidade';
    private const FINANCEIRO_ALIAS = 'Finanças';

    /** Setores que a matriz cita e que são criados se não existirem. */
    private const SECTORS = [
        self::SECRETARIA => 'Secretaria e recepção.',
        'Portaria' => 'Portaria: placas, frota e acesso de externos.',
        'Manutenção' => 'Manutenção.',
        'Comercial' => 'Registra os contratos de freelancer e as reservas comerciais.',
        self::FINANCEIRO => 'Financeiro e contabilidade.',
        'RH' => 'Recursos humanos.',
        'Esporte' => 'Placar Clube.',
        'TI' => 'Tecnologia da informação.',
        'Gerência' => 'Gerência.',
        'Diretoria' => 'Diretoria.',
    ];

    private const FULL_ACCESS = ['Gerência', 'Diretoria', 'TI'];

    /**
     * Matriz inicial. `true` = só coordenadores do setor.
     *
     * @var array<string, array<string, bool>>
     */
    private const GRANTS = [
        self::SECRETARIA => [
            Permissions::INFOCLUBE_EDITAR => false,
            Permissions::SIV_BUSCA => false,
            Permissions::SIV_PLACAS_DIRETORIA => false,
            Permissions::SIV_FROTA => false,
            Permissions::SIV_VIAGENS => false,
            Permissions::SIV_VEICULOS => false,
            Permissions::HOME_ASSISTANT => false,
            Permissions::RESERVAS_AGENDAMENTOS => false,
            Permissions::RESERVAS_PAGAMENTOS => false,
            Permissions::RESERVAS_PAGAMENTOS_ESTORNAR => false,
            Permissions::EXTERNOS_LIBERACAO_PONTUAL => false,
            Permissions::EXTERNOS_HISTORICO => false,
            Permissions::EXTERNOS_CARROS_APLICATIVO => false,
            Permissions::BOT_WHATSAPP => true,
            Permissions::FREELANCERS_CADASTRO => false,
            Permissions::FREELANCERS_SERVICOS_LISTAR => false,
        ],
        'Portaria' => [
            Permissions::SIV_BUSCA => false,
            Permissions::SIV_FROTA => false,
            Permissions::SIV_VIAGENS => false,
            Permissions::SIV_VEICULOS => false,
            Permissions::EXTERNOS_HISTORICO => false,
            Permissions::EXTERNOS_CARROS_APLICATIVO => false,
        ],
        'Manutenção' => [
            Permissions::SIV_FROTA => false,
            Permissions::SIV_VIAGENS => false,
            Permissions::SIV_VEICULOS => false,
        ],
        'Comercial' => [
            Permissions::RESERVAS_AGENDAMENTOS => false,
            Permissions::FREELANCERS_CADASTRO => false,
            Permissions::FREELANCERS_FUNCOES => false,
            Permissions::FREELANCERS_SERVICOS_LISTAR => false,
            Permissions::FREELANCERS_SERVICOS_GERENCIAR => false,
            Permissions::FREELANCERS_ASSINATURA => false,
            Permissions::FREELANCERS_ACOMPANHAMENTO => false,
            Permissions::TELEGRAM_LOGIN => false,
        ],
        self::FINANCEIRO => [
            Permissions::RESERVAS_PAGAMENTOS => false,
            Permissions::RESERVAS_PAGAMENTOS_ESTORNAR => false,
            Permissions::COMPRAS => false,
            Permissions::FREELANCERS_FINANCEIRO => false,
        ],
        'RH' => [
            Permissions::CARTEIRINHAS => false,
            Permissions::BANCO_HORAS_ADMIN => false,
        ],
        'Esporte' => [
            Permissions::PLACAR_CADASTRO => false,
            Permissions::PLACAR_SCOUT => false,
        ],
    ];

    public function up(): void
    {
        $now = now();

        $permissionIds = $this->syncCatalog($now);
        $sectorIds = $this->ensureSectors($now);

        DB::table('sectors')
            ->whereIn('id', array_map(fn ($name) => $sectorIds[$name], self::FULL_ACCESS))
            ->update(['full_access' => true]);

        $grants = self::GRANTS;

        // Finanças recebe o que a Contabilidade recebe — só se já existir.
        if ($financas = $this->findSector(self::FINANCEIRO_ALIAS)) {
            $sectorIds[self::FINANCEIRO_ALIAS] = $financas;
            $grants[self::FINANCEIRO_ALIAS] = self::GRANTS[self::FINANCEIRO];
        }

        $rows = [];
        foreach ($grants as $sector => $permissions) {
            foreach ($permissions as $permission => $coordinatorsOnly) {
                $rows[] = [
                    'sector_id' => $sectorIds[$sector],
                    'permission_id' => $permissionIds[$permission],
                    'coordinators_only' => $coordinatorsOnly,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        DB::table('sector_permission')->insertOrIgnore($rows);

        $this->bridgeDirectPermissions($permissionIds);
        $this->bridgeAdmins($permissionIds);
    }

    public function down(): void
    {
        // Apagar as linhas do catálogo leva junto, por cascade, a matriz dos
        // setores e as permissões individuais novas. Os setores ficam: podem
        // já ter gente vinculada.
        DB::table('permissions')->whereIn('name', Permissions::all())->delete();
        DB::table('sectors')->update(['full_access' => false]);
    }

    /** @return array<string, int> nome => id */
    private function syncCatalog($now): array
    {
        $hasDescription = Schema::hasColumn('permissions', 'description');

        foreach (Permissions::all() as $name) {
            $exists = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists();

            if (! $exists) {
                DB::table('permissions')->insert(array_filter([
                    'name' => $name,
                    'guard_name' => 'web',
                    'description' => $hasDescription ? Permissions::group($name) . ' — ' . Permissions::label($name) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], fn ($v) => $v !== null));
            }
        }

        // O Spatie guarda a lista de permissões em cache por 24h.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', Permissions::all())
            ->pluck('id', 'name')
            ->all();
    }

    /** @return array<string, int> nome canônico => id */
    private function ensureSectors($now): array
    {
        $ids = [];

        foreach (self::SECTORS as $name => $description) {
            $id = $this->findSector($name);

            if (! $id) {
                $id = DB::table('sectors')->insertGetId([
                    'name' => $name,
                    'description' => $description,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $ids[$name] = $id;
        }

        return $ids;
    }

    private function findSector(string $name): ?int
    {
        $id = DB::table('sectors')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->orderBy('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    /** @param array<string, int> $permissionIds */
    private function bridgeDirectPermissions(array $permissionIds): void
    {
        $legacy = DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_type', 'App\\Models\\User')
            ->whereIn('permissions.name', array_keys(LegacyPermissionMap::PERMISSIONS))
            ->get(['model_has_permissions.model_id', 'permissions.name']);

        $rows = [];
        foreach ($legacy->groupBy('model_id') as $userId => $items) {
            foreach (LegacyPermissionMap::translate($items->pluck('name')) as $new) {
                $rows[] = $this->directRow($permissionIds[$new], (int) $userId);
            }
        }

        DB::table('model_has_permissions')->insertOrIgnore($rows);
    }

    /** @param array<string, int> $permissionIds */
    private function bridgeAdmins(array $permissionIds): void
    {
        if (! Schema::hasTable('model_has_roles')) {
            return;
        }

        $admins = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'admin')
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->pluck('model_has_roles.model_id');

        $rows = [];
        foreach ($admins as $userId) {
            foreach ([Permissions::USUARIOS_GERENCIAR, Permissions::SETORES_GERENCIAR] as $permission) {
                $rows[] = $this->directRow($permissionIds[$permission], (int) $userId);
            }
        }

        DB::table('model_has_permissions')->insertOrIgnore($rows);
    }

    private function directRow(int $permissionId, int $userId): array
    {
        return [
            'permission_id' => $permissionId,
            'model_type' => 'App\\Models\\User',
            'model_id' => $userId,
        ];
    }
};
