<?php

namespace App\Support;

use App\Models\User;

/**
 * Quais funcionários um usuário enxerga.
 *
 * A regra é antiga do Banco de Horas e passa a valer também para o cachê: o
 * vínculo entre um coordenador e "os funcionários dele" é o **nome do setor**
 * batendo com `employees.department`. RH e TI respondem por todo mundo.
 *
 * Fica aqui, e não no controller, porque agora são dois módulos lendo a mesma
 * coisa — e um coordenador que enxerga um funcionário no ponto e não o enxerga
 * no cachê seria um bug difícil de explicar.
 */
class EmployeeScope
{
    /** Setores cujos coordenadores enxergam todos os funcionários. */
    const FULL_ACCESS_SECTORS = ['RH', 'TI'];

    /**
     * @return array{type: string, values?: array<int, string>, value?: string}
     */
    public static function for(?User $user): array
    {
        if (!$user) {
            return ['type' => 'none'];
        }

        $hasFullAccess = $user->coordinatorSectors()
            ->whereIn('name', self::FULL_ACCESS_SECTORS)
            ->exists();

        if ($hasFullAccess) {
            return ['type' => 'all'];
        }

        // Coordenador de outros setores: só os departamentos que coordena.
        $departments = $user->coordinatorSectors()->pluck('name')->toArray();

        if (!empty($departments)) {
            return ['type' => 'departments', 'values' => $departments];
        }

        // Qualquer usuário com matrícula vê apenas os próprios registros.
        if ($user->matricula) {
            return ['type' => 'employee_code', 'value' => $user->matricula];
        }

        return ['type' => 'none'];
    }

    /** Aplica a restrição a uma consulta sobre `employees`. */
    public static function apply($query, array $access)
    {
        return match ($access['type']) {
            'departments' => $query->whereIn('department', $access['values']),
            'employee_code' => $query->where('employee_code', $access['value']),
            'none' => $query->whereRaw('1 = 0'),
            default => $query,
        };
    }

    /** Aplica a restrição a uma consulta que TEM a relação `employee`. */
    public static function applyToRelation($query, array $access, string $relation = 'employee')
    {
        if ($access['type'] === 'all') {
            return $query;
        }

        return $query->whereHas($relation, fn($q) => self::apply($q, $access));
    }

    /** Este usuário responde por algum funcionário além de si mesmo? */
    public static function isCoordinator(array $access): bool
    {
        return in_array($access['type'], ['all', 'departments'], true);
    }
}
