<?php

namespace App\Authorization;

/**
 * O acesso efetivo de um usuário numa requisição: se ele tem acesso total e,
 * não tendo, quais permissões do catálogo alcança.
 *
 * `sources` diz de onde veio cada permissão ("setor Portaria", "individual") —
 * só a tela de Usuários lê isso, para responder "por que fulano leva 403".
 */
final class UserAccess
{
    /**
     * @param list<string> $permissions
     * @param array<string, list<string>> $sources permissão => origens
     * @param list<string> $fullAccessSectors
     */
    public function __construct(
        public readonly array $permissions = [],
        public readonly array $fullAccessSectors = [],
        public readonly array $sources = [],
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
        return $this->hasFullAccess() || in_array($permission, $this->permissions, true);
    }

    /** @return list<string> */
    public function sourcesOf(string $permission): array
    {
        return $this->sources[$permission] ?? [];
    }
}
