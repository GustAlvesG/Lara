<?php

namespace Tests\Unit;

use App\Services\Signature\MinorTerms\MinorTermRules;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Quem é responsável, quem é menor e o sobrenome em comum. */
class MinorTermRulesTest extends TestCase
{
    public function test_sobrenomes_ignoram_primeiro_nome_particulas_acento_e_caixa(): void
    {
        $this->assertSame(['carlos', 'silva', 'conceicao'], MinorTermRules::surnames('João CARLOS da Silva e Conceição'));
        $this->assertSame([], MinorTermRules::surnames('Maria'));
    }

    /** @return array<string, array{string, string, bool}> */
    public static function pares(): array
    {
        return [
            'mesmo sobrenome' => ['João da Silva', 'Pedro Silva', true],
            'acento e caixa' => ['Ana Conceição', 'Bia CONCEICAO', true],
            'só partícula em comum' => ['José dos Santos', 'Lia dos Reis', false],
            'primeiro nome igual não conta' => ['Silva Souza', 'Silva Pereira', false],
            'nome composto do menor' => ['Maria de Souza Silva', 'Pedro Henrique Silva', true],
            'nenhum' => ['João Pereira', 'Ana Lima', false],
        ];
    }

    #[DataProvider('pares')]
    public function test_sobrenome_em_comum(string $a, string $b, bool $esperado): void
    {
        $this->assertSame($esperado, MinorTermRules::shareSurname($a, $b));
    }

    public function test_maioridade_conta_no_dia(): void
    {
        $hoje = Carbon::parse('2026-10-08');

        $this->assertTrue(MinorTermRules::isAdult(Carbon::parse('2008-10-08'), $hoje));
        $this->assertFalse(MinorTermRules::isAdult(Carbon::parse('2008-10-09'), $hoje));
        $this->assertSame(12, MinorTermRules::ageOn(Carbon::parse('2014-01-31'), $hoje));
    }
}
