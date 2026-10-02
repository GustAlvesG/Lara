<?php

namespace App\Console\Commands;

use App\Models\AccessAuditLog;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Leva as pessoas das roles antigas para os setores, seguindo o arquivo de
 * de-para (`database/data/acesso-de-para.php`, gerado pelo antigo
 * `acesso:inventario` e preenchido à mão).
 *
 * Do arquivo, só vale a parte de PESSOAS: `papeis.<role>.setor` (quem tinha a
 * role entra no setor como colaborador), `papeis.<role>.coordenadores` (esses
 * entram como coordenador) e `pessoas` (exceções, somadas depois). A chave
 * `permissoes` ("copiar"/"nenhuma") é ignorada de propósito: o que cada setor
 * alcança agora é a matriz da migration `sync_access_catalog`, editável na
 * tela de Setores — copiar as permissões da role traria de volta o desenho
 * antigo.
 *
 * Nunca tira ninguém de setor e nunca rebaixa coordenador: só acrescenta.
 * Roda como simulação por padrão; `--aplicar` grava.
 *
 *     php artisan acesso:aplicar-de-para
 *     php artisan acesso:aplicar-de-para --aplicar
 */
class AcessoAplicarDePara extends Command
{
    protected $signature = 'acesso:aplicar-de-para
                            {--arquivo= : Caminho do de-para (padrão: database/data/acesso-de-para.php)}
                            {--aplicar : Grava. Sem isto, só mostra o que faria}';

    protected $description = 'Coloca nos setores quem tinha as roles antigas, conforme o arquivo de de-para.';

    public function handle(): int
    {
        $path = $this->option('arquivo') ?: database_path('data/acesso-de-para.php');

        if (! File::exists($path)) {
            $this->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        if (! Schema::hasTable('model_has_roles')) {
            $this->error('As tabelas de roles não existem mais — não há de quem ler as pessoas.');

            return self::FAILURE;
        }

        $map = require $path;
        $apply = (bool) $this->option('aplicar');
        $plan = [];

        foreach ($map['papeis'] ?? [] as $role => $config) {
            if (blank($config['setor'] ?? null)) {
                continue;
            }

            $sector = $this->sector($config['setor']);
            if (! $sector) {
                $this->warn("Role {$role}: setor \"{$config['setor']}\" não existe — crie na tela de Setores e rode de novo.");
                continue;
            }

            $coordinators = array_map('mb_strtolower', $config['coordenadores'] ?? []);

            foreach ($this->usersWithRole($role) as $user) {
                $role_ = in_array(mb_strtolower($user->email), $coordinators, true) ? Sector::ROLE_COORDINATOR : Sector::ROLE_COLLABORATOR;
                $plan[] = [$user, $sector, $role_, "role {$role}"];
            }
        }

        foreach ($map['pessoas'] ?? [] as $email => $entries) {
            $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();
            if (! $user) {
                $this->warn("Pessoa {$email}: usuário não encontrado.");
                continue;
            }

            foreach ($entries as $entry) {
                $sector = $this->sector($entry['setor'] ?? '');
                if (! $sector) {
                    $this->warn("Pessoa {$email}: setor \"" . ($entry['setor'] ?? '') . '" não existe.');
                    continue;
                }
                $role_ = ($entry['papel'] ?? '') === Sector::ROLE_COORDINATOR ? Sector::ROLE_COORDINATOR : Sector::ROLE_COLLABORATOR;
                $plan[] = [$user, $sector, $role_, 'exceção'];
            }
        }

        $rows = [];
        $changes = 0;

        foreach ($plan as [$user, $sector, $role, $origin]) {
            $current = DB::table('user_sector')
                ->where('user_id', $user->id)
                ->where('sector_id', $sector->id)
                ->value('role');

            // Só acrescenta: não mexe em quem já está, a não ser para promover.
            $action = match (true) {
                $current === null => 'entra',
                $current === Sector::ROLE_COLLABORATOR && $role === Sector::ROLE_COORDINATOR => 'promovido',
                default => null,
            };

            if ($action === null) {
                continue;
            }

            $rows[] = [$user->name, $user->email, $sector->name, $role === Sector::ROLE_COORDINATOR ? 'coordenador' : 'colaborador', $action, $origin];
            $changes++;

            if ($apply) {
                DB::transaction(function () use ($user, $sector, $role, $current, $origin) {
                    if ($current === null) {
                        $sector->users()->attach($user->id, ['role' => $role]);
                        AccessAuditLog::record(AccessAuditLog::SECTOR_MEMBER_ADDED, $user->id, $sector->id, null, ['role' => $role, 'via' => 'de-para: ' . $origin]);
                    } else {
                        $sector->users()->updateExistingPivot($user->id, ['role' => $role]);
                        AccessAuditLog::record(AccessAuditLog::SECTOR_MEMBER_ROLE_CHANGED, $user->id, $sector->id, null, ['de' => $current, 'para' => $role, 'via' => 'de-para: ' . $origin]);
                    }
                });
            }
        }

        $this->table(['Usuário', 'E-mail', 'Setor', 'Papel', 'Ação', 'Origem'], $rows);
        $this->newLine();

        if ($changes === 0) {
            $this->info('Nada a fazer: todo mundo do de-para já está no setor certo.');
        } elseif ($apply) {
            $this->info("{$changes} vínculo(s) gravado(s). Confira com: php artisan acesso:diferenca");
        } else {
            $this->comment("Simulação: {$changes} vínculo(s) seriam gravados. Rode com --aplicar para gravar.");
        }

        return self::SUCCESS;
    }

    private function sector(string $name): ?Sector
    {
        return Sector::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->orderBy('id')->first();
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function usersWithRole(string $role)
    {
        $ids = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', $role)
            ->where('model_has_roles.model_type', (new User())->getMorphClass())
            ->pluck('model_has_roles.model_id');

        return User::whereIn('id', $ids)->orderBy('name')->get();
    }
}
