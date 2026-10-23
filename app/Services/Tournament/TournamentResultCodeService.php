<?php

namespace App\Services\Tournament;

use App\Models\Tournament\Tournament;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Código de lançamento de resultados pelo site (F6), um por torneio.
 *
 * Quem tem `torneios.resultados` gera o código no Lara e passa para a equipe
 * que vai lançar no site. O código aparece **uma vez**; o banco guarda só o
 * sha256. Vale até o fim do último dia do torneio (ou até o fim de hoje, se
 * o torneio já acabou) e pode ser retirado a qualquer momento. Gerar outro
 * invalida o anterior.
 *
 * Formato: 8 caracteres sem os que se confundem (0/O, 1/I/L), mostrado como
 * "ABCD-EFGH"; a conferência ignora maiúsculas, espaços e hífen.
 */
class TournamentResultCodeService
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const LENGTH = 8;

    /** @return string o código, para mostrar uma vez */
    public function generate(Tournament $tournament, User $user): string
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        $endOfTournament = Carbon::parse($tournament->ends_on)->endOfDay();
        $today = now()->endOfDay();

        $tournament->forceFill([
            'results_code_hash' => self::hash($code),
            'results_code_expires_at' => $endOfTournament->greaterThan($today) ? $endOfTournament : $today,
            'results_code_created_at' => now(),
            'results_code_created_by' => $user->id,
        ])->save();

        return self::format($code);
    }

    public function revoke(Tournament $tournament): void
    {
        $tournament->forceFill([
            'results_code_hash' => null,
            'results_code_expires_at' => null,
        ])->save();
    }

    public function check(Tournament $tournament, ?string $code): bool
    {
        if (blank($code) || ! $tournament->resultsCodeActive()) {
            return false;
        }

        return hash_equals((string) $tournament->results_code_hash, self::hash($code));
    }

    public static function hash(string $code): string
    {
        return hash('sha256', self::normalize($code));
    }

    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[\s\-]+/', '', $code));
    }

    public static function format(string $code): string
    {
        $code = self::normalize($code);

        return substr($code, 0, 4) . '-' . substr($code, 4);
    }
}
