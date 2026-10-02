<?php

use App\Authorization\LegacyPermissionMap;
use App\Authorization\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leva a assinatura eletrônica para o catálogo de acesso.
 *
 * O módulo nasceu antes da reforma do acesso, com quatro permissões do Spatie
 * ('manage signature templates' e companhia) dadas ao papel `admin`. Papel e
 * nome antigo não decidem mais nada: o que vale são as permissões do catálogo
 * (`assinatura.*`), dadas ao setor na tela de Setores ou à pessoa na tela de
 * Usuários.
 *
 * 1. **Catálogo.** As quatro permissões novas viram linhas em `permissions`.
 *    Em ambiente novo a `sync_access_catalog` já as cria (ela lê o catálogo
 *    inteiro); esta migration existe para os ambientes onde aquela já rodou.
 *
 * 2. **Ponte das permissões individuais.** Quem tinha uma das antigas dada
 *    direto ao usuário recebe a equivalente nova — ver LegacyPermissionMap.
 *
 * Nenhum setor recebe nada aqui, como antes ("nascem só no admin; quem mais
 * precisar recebe pela tela"). Os setores de acesso total (Gerência,
 * Diretoria, TI) alcançam as quatro por definição.
 */
return new class extends Migration
{
    private const SIGNATURE = [
        Permissions::ASSINATURA_MODELOS,
        Permissions::ASSINATURA_DOCUMENTOS,
        Permissions::ASSINATURA_CONSULTAR,
        Permissions::ASSINATURA_EVIDENCIAS,
    ];

    /** As antigas que têm equivalente no catálogo. */
    private const LEGACY = [
        'manage signature templates',
        'manage signature documents',
        'view signed documents',
        'view signature evidences',
    ];

    public function up(): void
    {
        $now = now();
        $hasDescription = Schema::hasColumn('permissions', 'description');

        foreach (self::SIGNATURE as $name) {
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

        $ids = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', self::SIGNATURE)
            ->pluck('id', 'name');

        $legacy = DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_type', 'App\\Models\\User')
            ->whereIn('permissions.name', self::LEGACY)
            ->get(['model_has_permissions.model_id', 'permissions.name']);

        $rows = [];
        foreach ($legacy->groupBy('model_id') as $userId => $items) {
            foreach (LegacyPermissionMap::translate($items->pluck('name')) as $new) {
                $rows[] = [
                    'permission_id' => $ids[$new],
                    'model_type' => 'App\\Models\\User',
                    'model_id' => (int) $userId,
                ];
            }
        }

        DB::table('model_has_permissions')->insertOrIgnore($rows);

        // O Spatie guarda a lista de permissões em cache por 24h.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Leva junto, por cascade, o que os setores e as pessoas receberam.
        DB::table('permissions')->whereIn('name', self::SIGNATURE)->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
