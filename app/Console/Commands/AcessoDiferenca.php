<?php

namespace App\Console\Commands;

use App\Authorization\LegacyPermissionMap;
use App\Authorization\Permissions;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compara, pessoa a pessoa, o acesso de ANTES da reforma (roles do Spatie,
 * permissões antigas e Gates de setor) com o de AGORA (setores, permissões
 * individuais e acesso total), já traduzido para os nomes do catálogo.
 *
 * Só lê. Serve para duas coisas:
 *
 *   - antes de ligar em produção: vincular as pessoas aos setores até a coluna
 *     "perde" mostrar só o que se quer que ela perca;
 *   - depois: responder "fulano perdeu o quê?" sem abrir o banco.
 *
 * Precisa das tabelas antigas (roles, model_has_roles). Depois do
 * `acesso:limpar-legado` não há mais o que comparar.
 *
 *     php artisan acesso:diferenca
 *     php artisan acesso:diferenca --so-perdas --csv=storage/app/acesso.csv
 *     php artisan acesso:diferenca --sem-abertas   # ignora as telas que eram abertas
 */
class AcessoDiferenca extends Command
{
    protected $signature = 'acesso:diferenca
                            {--so-perdas : Lista só quem perde alguma permissão}
                            {--sem-abertas : Não conta como perda as telas que antes eram abertas a qualquer login}
                            {--todos : Inclui usuários inativos}
                            {--csv= : Grava o resultado neste arquivo CSV}';

    protected $description = 'Compara o acesso de antes da reforma (roles) com o de agora (setores), por usuário.';

    public function handle(): int
    {
        if (! Schema::hasTable('model_has_roles')) {
            $this->error('As tabelas de roles não existem mais — não há acesso antigo para comparar.');

            return self::FAILURE;
        }

        $users = User::query()
            ->when(! $this->option('todos'), fn ($q) => $q->where('status_id', 1))
            ->with('sectors')
            ->orderBy('name')
            ->get();

        $rows = [];
        $totals = ['perde' => 0, 'ganha' => 0, 'igual' => 0];

        foreach ($users as $user) {
            $before = $this->legacyAccess($user);
            $access = $user->access();
            $after = $access->hasFullAccess() ? Permissions::all() : $access->permissions;

            $lost = array_values(array_diff($before, $after));
            $gained = array_values(array_diff($after, $before));

            $totals[$lost ? 'perde' : ($gained ? 'ganha' : 'igual')]++;

            if ($this->option('so-perdas') && ! $lost) {
                continue;
            }

            if (! $lost && ! $gained) {
                continue;
            }

            $rows[] = [
                'nome' => $user->name,
                'email' => $user->email,
                'setores' => $user->sectors->map(fn ($s) => $s->name . ($s->pivot->role === 'coordinator' ? ' ★' : ''))->implode(', ') ?: '—',
                'perde' => $this->describe($lost),
                'ganha' => $access->hasFullAccess() && $gained ? 'acesso total' : $this->describe($gained),
            ];
        }

        $this->table(['Usuário', 'E-mail', 'Setores', 'Perde', 'Ganha'], array_map('array_values', $rows));

        $this->newLine();
        $this->line(sprintf(
            '%d usuário(s): <fg=red>%d perdem algo</>, <fg=green>%d só ganham</>, %d sem mudança.',
            $users->count(), $totals['perde'], $totals['ganha'], $totals['igual']
        ));

        if (! $this->option('sem-abertas')) {
            $this->comment('Marcadas com (aberta): telas que antes só pediam login. Use --sem-abertas para ignorá-las.');
        }

        if ($path = $this->option('csv')) {
            $this->writeCsv($path, $rows);
            $this->info("CSV gravado em {$path}.");
        }

        return self::SUCCESS;
    }

    /**
     * O acesso antigo do usuário, já nos nomes do catálogo.
     *
     * @return list<string>
     */
    private function legacyAccess(User $user): array
    {
        $viaRoles = DB::table('model_has_roles')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->where('model_has_roles.model_id', $user->id)
            ->pluck('permissions.name');

        $direct = DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_type', $user->getMorphClass())
            ->where('model_has_permissions.model_id', $user->id)
            ->pluck('permissions.name');

        $access = LegacyPermissionMap::translate($viaRoles->merge($direct));

        $sectorNames = $user->sectors->map(fn ($s) => mb_strtolower($s->name))->all();
        foreach (LegacyPermissionMap::SECTOR_GATES as $gate) {
            $sectors = array_map('mb_strtolower', $gate['sectors']);
            if (array_intersect($sectors, $sectorNames)) {
                $access = array_merge($access, $gate['grants']);
            }
        }

        $roles = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->where('model_has_roles.model_id', $user->id)
            ->pluck('roles.name');

        if ($roles->contains(LegacyPermissionMap::TELEGRAM_ROLE)) {
            $access[] = Permissions::TELEGRAM_LOGIN;
        }

        if (! $this->option('sem-abertas')) {
            $access = array_merge($access, LegacyPermissionMap::PREVIOUSLY_OPEN);
        }

        return array_values(array_unique($access));
    }

    /** @param list<string> $permissions */
    private function describe(array $permissions): string
    {
        sort($permissions);

        return implode(', ', array_map(
            fn ($p) => $p . (in_array($p, LegacyPermissionMap::PREVIOUSLY_OPEN, true) && ! $this->option('sem-abertas') ? ' (aberta)' : ''),
            $permissions
        ));
    }

    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');
        // BOM: o Excel só lê o acento certo com ele.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['nome', 'email', 'setores', 'perde', 'ganha'], ';');
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row), ';');
        }
        fclose($handle);
    }
}
