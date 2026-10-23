<?php

namespace Tests\Unit\Tournament;

use App\Models\Tournament\TournamentCategory as Category;
use App\Models\Tournament\TournamentCategoryTemplate as Template;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Quem pode jogar a categoria: sexo do cadastro do MultiClubes e idade
 * completada na data de início do torneio. Sem banco.
 */
class CategoryEligibilityTest extends TestCase
{
    private function category(array $attributes): Category
    {
        return new Category(array_merge(['gender' => Template::GENDER_ANY, 'min_age' => null, 'max_age' => null], $attributes));
    }

    private function date(string $value): Carbon
    {
        return Carbon::parse($value);
    }

    public function test_idade_e_a_completada_na_data_de_inicio(): void
    {
        $birth = $this->date('1986-11-10');

        $this->assertSame(39, Category::ageOn($birth, $this->date('2026-11-09')));
        $this->assertSame(40, Category::ageOn($birth, $this->date('2026-11-10')));
        $this->assertSame(40, Category::ageOn($birth, $this->date('2026-12-01')));
    }

    public function test_categoria_livre_aceita_qualquer_pessoa(): void
    {
        $this->assertNull($this->category([])->ineligibilityReason(null, null, $this->date('2026-11-10')));
    }

    public function test_sexo_da_categoria(): void
    {
        $feminina = $this->category(['gender' => Template::GENDER_FEMALE]);
        $on = $this->date('2026-11-10');

        $this->assertNull($feminina->ineligibilityReason(Template::GENDER_FEMALE, null, $on));
        $this->assertSame(Category::INELIGIBLE_GENDER, $feminina->ineligibilityReason(Template::GENDER_MALE, null, $on));
        $this->assertSame(Category::INELIGIBLE_GENDER_UNKNOWN, $feminina->ineligibilityReason(null, null, $on));
    }

    public function test_faixa_de_idade_conta_os_extremos(): void
    {
        $quarenta = $this->category(['min_age' => 40, 'max_age' => 49]);
        $on = $this->date('2026-11-10');

        $this->assertNull($quarenta->ineligibilityReason(null, $this->date('1986-11-10'), $on));       // 40 no dia
        $this->assertSame(Category::INELIGIBLE_TOO_YOUNG, $quarenta->ineligibilityReason(null, $this->date('1986-11-11'), $on));
        $this->assertNull($quarenta->ineligibilityReason(null, $this->date('1976-11-11'), $on));       // 49
        $this->assertSame(Category::INELIGIBLE_TOO_OLD, $quarenta->ineligibilityReason(null, $this->date('1976-11-10'), $on));
        $this->assertSame(Category::INELIGIBLE_BIRTH_DATE_UNKNOWN, $quarenta->ineligibilityReason(null, null, $on));
    }

    public function test_resumo_da_categoria(): void
    {
        $this->assertSame('Duplas · Feminino · 40+ anos', Category::describe(2, Template::GENDER_FEMALE, 40, null));
        $this->assertSame('Individual · Livre · até 17 anos', Category::describe(1, Template::GENDER_ANY, null, 17));
        $this->assertSame('Equipes de 4 · Masculino · 30 a 39 anos', Category::describe(4, Template::GENDER_MALE, 30, 39));
    }
}
