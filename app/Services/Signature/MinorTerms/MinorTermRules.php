<?php

namespace App\Services\Signature\MinorTerms;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * As regras de quem pode assinar por quem no Termo de Menores — sem banco,
 * para o teste Unit cobrir cada caso.
 *
 *  - Responsável: pessoa do título com `adult_age` anos ou mais na data.
 *  - Menor: pessoa do mesmo título com menos de `adult_age` anos.
 *  - O menor só aparece para o responsável se os dois têm ao menos um
 *    sobrenome em comum. Sobrenome = todo nome depois do primeiro, sem acento,
 *    em minúsculas, sem as partículas "de, da, do, das, dos, e".
 */
class MinorTermRules
{
    /** Partículas que não contam como sobrenome. */
    public const PARTICLES = ['de', 'da', 'do', 'das', 'dos', 'e'];

    public static function adultAge(): int
    {
        return (int) config('signature.minor_terms.adult_age', 18);
    }

    public static function ageOn(Carbon $birthDate, Carbon $day): int
    {
        return (int) $birthDate->copy()->startOfDay()->diffInYears($day->copy()->startOfDay());
    }

    public static function isAdult(Carbon $birthDate, Carbon $day): bool
    {
        return self::ageOn($birthDate, $day) >= self::adultAge();
    }

    /**
     * Os sobrenomes de um nome completo, normalizados.
     *
     * @return array<int, string>
     */
    public static function surnames(string $fullName): array
    {
        $partes = preg_split('/[^a-z]+/', strtolower(Str::ascii($fullName)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        array_shift($partes);

        return array_values(array_unique(array_filter(
            $partes,
            fn(string $parte) => !in_array($parte, self::PARTICLES, true),
        )));
    }

    public static function shareSurname(string $nameA, string $nameB): bool
    {
        return array_intersect(self::surnames($nameA), self::surnames($nameB)) !== [];
    }
}
