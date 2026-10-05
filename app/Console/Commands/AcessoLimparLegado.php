<?php

namespace App\Console\Commands;

use App\Authorization\Permissions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Última fase da reforma do acesso: apaga as roles do Spatie e as permissões
 * que não estão no catálogo (App\Authorization\Permissions).
 *
 * É comando, e não migration, de propósito. Uma migration apagaria as roles
 * no mesmo deploy que as aposenta, e com elas iria o único registro de quem
 * tinha o quê — justamente o que o `acesso:diferenca` e o
 * `acesso:aplicar-de-para` leem para vincular as pessoas aos setores. Aqui a
 * limpeza acontece quando alguém decide que acabou.
 *
 * Nada que o sistema usa depende do que é apagado: o acesso é decidido por
 * setor, permissão individual e acesso total. Mas não tem volta — por isso
 * pede --confirmar, e mostra antes o que vai sair.
 *
 *     php artisan acesso:limpar-legado              # só mostra
 *     php artisan acesso:limpar-legado --confirmar  # apaga
 */
class AcessoLimparLegado extends Command
{
    protected $signature = 'acesso:limpar-legado
                            {--confirmar : Apaga de verdade. Sem isto, só mostra o que sairia}';

    protected $description = 'Apaga as roles do Spatie e as permissões fora do catálogo novo.';

    public function handle(): int
    {
        if (! Schema::hasTable('roles')) {
            $this->info('Não há mais tabela de roles — nada a limpar.');

            return self::SUCCESS;
        }

        $roles = DB::table('roles')->orderBy('name')->get(['id', 'name']);
        $assignments = DB::table('model_has_roles')->count();
        $legacy = DB::table('permissions')->whereNotIn('name', Permissions::all())->orderBy('name')->get(['id', 'name']);
        $legacyGrants = DB::table('model_has_permissions')->whereIn('permission_id', $legacy->pluck('id'))->count();

        $this->line('<options=bold>Sairia:</>');
        $this->line("  · {$roles->count()} role(s): " . ($roles->pluck('name')->implode(', ') ?: '—'));
        $this->line("  · {$assignments} vínculo(s) de usuário com role");
        $this->line("  · {$legacy->count()} permissão(ões) fora do catálogo: " . ($legacy->pluck('name')->implode(', ') ?: '—'));
        $this->line("  · {$legacyGrants} concessão(ões) individual(is) dessas permissões");
        $this->newLine();

        if (! $this->option('confirmar')) {
            $this->comment('Simulação. Confira antes com `php artisan acesso:diferenca` e rode com --confirmar para apagar.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($legacy) {
            DB::table('role_has_permissions')->delete();
            DB::table('model_has_roles')->delete();
            DB::table('roles')->delete();

            DB::table('model_has_permissions')->whereIn('permission_id', $legacy->pluck('id'))->delete();
            DB::table('permissions')->whereIn('id', $legacy->pluck('id'))->delete();
        });

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info('Legado apagado. As tabelas continuam existindo (o pacote do Spatie as espera), só vazias de roles.');

        return self::SUCCESS;
    }
}
