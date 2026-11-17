<?php

namespace App\Authorization;

/**
 * O acesso efetivo de um usuário numa requisição: se ele tem acesso total e,
 * não tendo, quais permissões do catálogo alcança.
 *
 * `sources` diz de onde veio cada permissão ("setor Portaria", "individual") —
 * só a tela de Usuários lê isso, para responder "por que fulano leva 403".
 *
 * `denied` são as permissões NEGADAS à pessoa (desde 17/11/2026). A negação
 * vence tudo, inclusive o acesso total: `allows()` responde não para elas
 * mesmo em quem é da Gerência, da Diretoria ou da TI. `permissions` já vem
 * sem as negadas.
 */
final class UserAccess
{
    /**
     * @param list<string> $permissions
     * @param list<string> $fullAccessSectors
     * @param array<string, list<string>> $sources permissão => origens
     * @param list<string> $denied
     */
    public function __construct(
        public readonly array $permissions = [],
        public readonly array $fullAccessSectors = [],
        public readonly array $sources = [],
        public readonly array $denied = [],
    ) {
    }

    public static function none(): self
    {
        return new self();
    }

    public function hasFullAccess(): bool
    {
        return $this->fullAccessSectors !== [];
    }

    public function allows(string $permission): bool
    {
        if ($this->isDenied($permission)) {
            return false;
        }

        return $this->hasFullAccess() || in_array($permission, $this->permissions, true);
    }

    public function isDenied(string $permission): bool
    {
        return in_array($permission, $this->denied, true);
    }

    /**
     * Tudo o que a pessoa alcança de fato, já sem as negadas: o catálogo
     * inteiro para quem tem acesso total, as permissões dela para o resto.
     *
     * @return list<string>
     */
    public function effectivePermissions(): array
    {
        $all = $this->hasFullAccess() ? Permissions::all() : $this->permissions;

        return array_values(array_diff($all, $this->denied));
    }

    /** @return list<string> */
    public function sourcesOf(string $permission): array
    {
        return $this->sources[$permission] ?? [];
    }
}
