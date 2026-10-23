<?php

namespace Tests\Unit\Tournament;

use App\Services\Tournament\TournamentBracketService as Bracket;
use PHPUnit\Framework\TestCase;

/**
 * As contas do chaveamento, sem banco: tamanho da chave, posições-padrão
 * (1 e 2 só se cruzam na final) e o cruzado padrão dos grupos.
 */
class BracketMathTest extends TestCase
{
    public function test_tamanho_da_chave_e_a_potencia_de_2_seguinte(): void
    {
        $this->assertSame(2, Bracket::bracketSize(2));
        $this->assertSame(4, Bracket::bracketSize(3));
        $this->assertSame(8, Bracket::bracketSize(5));
        $this->assertSame(16, Bracket::bracketSize(16));
        $this->assertSame(32, Bracket::bracketSize(17));
    }

    public function test_posicoes_padrao(): void
    {
        $this->assertSame([1, 2], Bracket::seedOrder(2));
        $this->assertSame([1, 4, 2, 3], Bracket::seedOrder(4));
        $this->assertSame([1, 8, 4, 5, 2, 7, 3, 6], Bracket::seedOrder(8));

        // 1 na metade de cima, 2 na de baixo, em qualquer tamanho.
        $order = Bracket::seedOrder(32);
        $this->assertContains(1, array_slice($order, 0, 16));
        $this->assertContains(2, array_slice($order, 16));
    }

    public function test_cruzado_padrao_com_dois_e_quatro_grupos(): void
    {
        $this->assertSame(['1A × 2B', '1B × 2A'], $this->pairs(2, 2));
        $this->assertSame(['1A × 2B', '1D × 2C', '1B × 2A', '1C × 2D'], $this->pairs(4, 2));
    }

    public function test_ninguem_reencontra_o_proprio_grupo_na_primeira_rodada(): void
    {
        foreach ([[3, 2], [4, 2], [5, 2], [6, 2], [8, 2], [4, 3], [2, 3], [4, 4]] as [$groups, $qualifiers]) {
            foreach ($this->pairs($groups, $qualifiers) as $pair) {
                if (! str_contains($pair, 'passa')) {
                    [$a, $b] = explode(' × ', $pair);
                    $this->assertNotSame(substr($a, -1), substr($b, -1), "{$groups} grupos, {$qualifiers} classificados: {$pair}");
                }
            }
        }
    }

    public function test_nomes_das_rodadas(): void
    {
        $this->assertSame('Final', Bracket::roundLabel(3, 3));
        $this->assertSame('Semifinal', Bracket::roundLabel(2, 3));
        $this->assertSame('Quartas de final', Bracket::roundLabel(1, 3));
        $this->assertSame('1ª rodada', Bracket::roundLabel(1, 5));
        $this->assertSame('Disputa de 3º lugar', Bracket::roundLabel(3, 3, true));
    }

    /** Jogos da 1ª rodada do mata-mata, como "1A × 2B". */
    private function pairs(int $groups, int $qualifiers): array
    {
        $ranks = Bracket::crossRanks($groups, $qualifiers);
        $label = fn (?array $r) => $r ? $r[1] . 'ABCDEFGHIJ'[$r[0]] : 'passa';
        $slots = array_map(fn (int $rank) => $label($ranks[$rank - 1] ?? null), Bracket::seedOrder(Bracket::bracketSize(count($ranks))));

        return array_map(fn ($pair) => implode(' × ', $pair), array_chunk($slots, 2));
    }
}
