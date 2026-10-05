<?php

namespace App\Authorization;

use App\Models\AccessAuditLog;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * O único caminho para mudar acesso: vínculo com setor, permissões do setor,
 * acesso total e permissões individuais. As telas (Usuários, Setores, Meu
 * setor) chamam daqui; ninguém mexe em `user_sector`, `sector_permission` ou
 * `model_has_permissions` por fora.
 *
 * Existe para que duas coisas valham em toda tela, sem depender de quem
 * escreveu cada uma:
 *
 *   1. **Toda mudança fica registrada** em `access_audit_logs`.
 *   2. **Ninguém tranca o sistema.** Duas travas, conferidas depois de a
 *      mudança ser aplicada e ANTES de ela ser gravada (tudo numa transação):
 *        - um setor de acesso total não pode ficar sem membros — senão some
 *          quem administra;
 *        - quem faz a mudança não pode perder, por ela, o acesso às telas de
 *          gestão que está usando (`usuarios.gerenciar`, `setores.gerenciar`).
 *      Uma das duas falhou: nada é gravado, e a tela mostra o motivo.
 */
class AccessManager
{
    private const MANAGEMENT = [Permissions::USUARIOS_GERENCIAR, Permissions::SETORES_GERENCIAR];

    /**
     * Coloca, troca o papel ou tira (`$role = null`) alguém de um setor.
     */
    public function setMembership(User $actor, Sector $sector, User $user, ?string $role): void
    {
        $this->guarded($actor, function () use ($actor, $sector, $user, $role) {
            $this->applyMembership($sector, $user, $role);
        }, [$sector->id]);
    }

    /**
     * Setores de um usuário de uma vez: sector_id => 'coordinator' |
     * 'collaborator' | null (fora). Setor que não aparece na lista não muda.
     *
     * @param array<int, string|null> $roles
     */
    public function syncUserSectors(User $actor, User $user, array $roles): void
    {
        $sectors = Sector::whereIn('id', array_keys($roles))->get()->keyBy('id');

        $this->guarded($actor, function () use ($sectors, $user, $roles) {
            foreach ($roles as $sectorId => $role) {
                if ($sector = $sectors->get($sectorId)) {
                    $this->applyMembership($sector, $user, $role ?: null);
                }
            }
        }, $sectors->keys()->all());
    }

    /**
     * Permissões individuais do usuário: a lista passada é a lista final.
     *
     * @param list<string> $names
     */
    public function syncUserPermissions(User $actor, User $user, array $names): void
    {
        $names = array_values(array_intersect($names, Permissions::all()));

        $this->guarded($actor, function () use ($user, $names) {
            $before = $user->directPermissions()->pluck('name')->all();
            $ids = $this->permissionIds($names);

            $user->directPermissions()->sync(array_values($ids));

            $added = array_values(array_diff($names, $before));
            $removed = array_values(array_intersect(array_diff($before, $names), Permissions::all()));

            if ($added || $removed) {
                AccessAuditLog::record(AccessAuditLog::USER_PERMISSIONS_CHANGED, $user->id, null, null, [
                    'added' => $added,
                    'removed' => $removed,
                ]);
            }
        });
    }

    /**
     * Permissões do setor: nome => só coordenadores? A lista passada é a
     * lista final.
     *
     * @param array<string, bool> $grants
     */
    public function syncSectorPermissions(User $actor, Sector $sector, array $grants): void
    {
        $grants = array_intersect_key($grants, array_flip(Permissions::all()));

        $this->guarded($actor, function () use ($sector, $grants) {
            $before = $sector->permissions()->get()
                ->mapWithKeys(fn ($p) => [$p->name => (bool) $p->pivot->coordinators_only])
                ->all();

            $ids = $this->permissionIds(array_keys($grants));
            $sync = [];
            foreach ($grants as $name => $coordinatorsOnly) {
                $sync[$ids[$name]] = ['coordinators_only' => (bool) $coordinatorsOnly];
            }

            $sector->permissions()->sync($sync);

            $changes = [];
            foreach ($grants + $before as $name => $_) {
                $old = array_key_exists($name, $before) ? ($before[$name] ? 'coordenadores' : 'todos') : null;
                $new = array_key_exists($name, $grants) ? ($grants[$name] ? 'coordenadores' : 'todos') : null;

                if ($old !== $new) {
                    $changes[$name] = ['de' => $old, 'para' => $new];
                }
            }

            if ($changes) {
                AccessAuditLog::record(AccessAuditLog::SECTOR_PERMISSIONS_CHANGED, null, $sector->id, null, $changes);
            }
        }, [$sector->id]);
    }

    public function setFullAccess(User $actor, Sector $sector, bool $fullAccess): void
    {
        if ($sector->full_access === $fullAccess) {
            return;
        }

        $this->guarded($actor, function () use ($sector, $fullAccess) {
            $sector->forceFill(['full_access' => $fullAccess])->save();

            AccessAuditLog::record(AccessAuditLog::SECTOR_FULL_ACCESS_CHANGED, null, $sector->id, null, [
                'full_access' => $fullAccess,
            ]);
        }, [$sector->id]);
    }

    /**
     * Aplica a mudança, confere as travas e só então grava. As travas usam o
     * estado de DENTRO da transação, por isso rodam depois da mudança.
     *
     * @param list<int> $touchedSectors setores cujo esvaziamento precisa ser conferido
     */
    private function guarded(User $actor, callable $change, array $touchedSectors = []): void
    {
        $hadManagement = array_filter(self::MANAGEMENT, fn ($p) => $actor->hasAccess($p));

        DB::connection($actor->getConnectionName())->transaction(function () use ($actor, $change, $touchedSectors, $hadManagement) {
            $change();

            foreach (Sector::whereIn('id', $touchedSectors)->where('full_access', true)->get() as $sector) {
                if (! $sector->users()->exists()) {
                    throw new AccessChangeRejected(
                        "O setor {$sector->name} tem acesso total e não pode ficar sem nenhum membro."
                    );
                }
            }

            $actor->forgetAccess();

            foreach ($hadManagement as $permission) {
                if (! $actor->hasAccess($permission)) {
                    throw new AccessChangeRejected(
                        'Essa mudança tiraria de você o acesso a "' . Permissions::label($permission)
                        . '". Peça a outra pessoa com acesso total para fazê-la.'
                    );
                }
            }
        });

        $actor->forgetAccess();
    }

    private function applyMembership(Sector $sector, User $user, ?string $role): void
    {
        if ($role !== null && ! in_array($role, [Sector::ROLE_COORDINATOR, Sector::ROLE_COLLABORATOR], true)) {
            throw new AccessChangeRejected('Papel inválido no setor.');
        }

        $current = $sector->users()->where('users.id', $user->id)->first()?->pivot->role;

        if ($current === $role) {
            return;
        }

        if ($role === null) {
            $sector->users()->detach($user->id);
            AccessAuditLog::record(AccessAuditLog::SECTOR_MEMBER_REMOVED, $user->id, $sector->id, null, ['role' => $current]);
        } elseif ($current === null) {
            $sector->users()->attach($user->id, ['role' => $role]);
            AccessAuditLog::record(AccessAuditLog::SECTOR_MEMBER_ADDED, $user->id, $sector->id, null, ['role' => $role]);
        } else {
            $sector->users()->updateExistingPivot($user->id, ['role' => $role]);
            AccessAuditLog::record(AccessAuditLog::SECTOR_MEMBER_ROLE_CHANGED, $user->id, $sector->id, null, ['de' => $current, 'para' => $role]);
        }

        $user->forgetAccess();
    }

    /**
     * @param list<string> $names
     * @return array<string, int>
     */
    private function permissionIds(array $names): array
    {
        if ($names === []) {
            return [];
        }

        $ids = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $names)
            ->pluck('id', 'name')
            ->all();

        // O catálogo em código tem nome que a tabela ainda não tem: a
        // migration de sincronia não rodou depois de uma permissão nova.
        foreach ($names as $name) {
            if (! isset($ids[$name])) {
                $ids[$name] = Permission::query()->create(['name' => $name, 'guard_name' => 'web'])->id;
            }
        }

        return $ids;
    }
}
