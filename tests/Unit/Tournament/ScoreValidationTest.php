<?php

namespace Tests\Unit\Tournament;

use App\Exceptions\TournamentException;
use App\Services\Tournament\TournamentResultService as Results;
use PHPUnit\Framework\TestCase;

/**
 * A conferência do placar, sem banco: todo set tem vencedor, o jogo fecha
 * em `sets_to_win`, não há set depois de decidido, tie-break combina.
 */
class ScoreValidationTest extends TestCase
{
    private function set(int $h, int $a, ?int $ht = null, ?int $at = null): array
    {
        return ['home_games' => $h, 'away_games' => $a, 'home_tiebreak' => $ht, 'away_tiebreak' => $at];
    }

    public function test_placar_valido_conta_os_sets(): void
    {
        [$sets, $home, $away] = Results::score([$this->set(6, 4), $this->set(3, 6), $this->set(7, 6, 7, 5)], 2);

        $this->assertSame([2, 1], [$home, $away]);
        $this->assertCount(3, $sets);
        $this->assertSame(7, $sets[2]['home_tiebreak']);

        [, $home, $away] = Results::score([$this->set(4, 6)], 1);
        $this->assertSame([0, 1], [$home, $away]);
    }

    public function test_linhas_vazias_sao_ignoradas(): void
    {
        [$sets] = Results::score([$this->set(6, 1), $this->set(6, 2), ['home_games' => '', 'away_games' => '']], 2);

        $this->assertCount(2, $sets);
    }

    public function test_recusas(): void
    {
        $cases = [
            'no_sets' => [[], 2],
            'tied_set' => [[$this->set(6, 6)], 1],
            'undecided' => [[$this->set(6, 4), $this->set(4, 6)], 2],
            'extra_set' => [[$this->set(6, 4), $this->set(6, 4), $this->set(6, 4)], 2],
            'tiebreak' => [[$this->set(7, 6, 5, 7)], 1],
        ];

        foreach ($cases as $detail => [$sets, $toWin]) {
            try {
                Results::score($sets, $toWin);
                $this->fail("Deveria recusar: {$detail}");
            } catch (TournamentException $e) {
                $this->assertSame(TournamentException::INVALID_SCORE, $e->reason);
                $this->assertSame($detail, $e->context['detail'], $detail);
            }
        }

        // Tie-break de um lado só.
        $this->expectException(TournamentException::class);
        Results::score([['home_games' => 7, 'away_games' => 6, 'home_tiebreak' => 7]], 1);
    }
}
