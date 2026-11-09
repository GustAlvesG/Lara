<?php

namespace Tests\Unit\MultiClubes;

use App\Services\EmailCampaign\MultiClubesRecipientSource;
use App\Services\MultiClubes\MemberMailingFilter;
use App\Services\MultiClubes\TitleMemberLookup;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * O filtro da lista de sócios do MultiClubes vira SQL só com `?` e a idade
 * vira faixa de nascimento — sem banco.
 */
class MemberMailingFilterTest extends TestCase
{
    private function ref(): Carbon
    {
        return Carbon::parse('2026-11-09');
    }

    public function test_padrao_tira_os_tipos_especiais_e_pega_ativos(): void
    {
        [$where, $bindings] = (new MemberMailingFilter(referenceDate: $this->ref()))->whereSql();

        $excluded = TitleMemberLookup::EXCLUDED_TITLE_TYPES;
        $this->assertStringContainsString('t.TitleType NOT IN (' . implode(',', array_fill(0, count($excluded), '?')) . ')', $where);
        $this->assertStringContainsString('t.Status IN (?)', $where);
        $this->assertStringContainsString('m.Status IN (?)', $where);
        $this->assertStringNotContainsString('m.Titular', $where);
        $this->assertStringNotContainsString('m.Sex', $where);
        $this->assertStringNotContainsString('BirthDate', $where);
        $this->assertSame(array_merge($excluded, [0, 0]), $bindings);
    }

    public function test_tipos_escolhidos_substituem_a_exclusao(): void
    {
        [$where, $bindings] = (new MemberMailingFilter(titleTypes: [365, 375], titleStatuses: [], memberStatuses: []))->whereSql();

        $this->assertSame('t.TitleType IN (?,?)', $where);
        $this->assertSame([365, 375], $bindings);
    }

    public function test_titular_dependente_ou_todos(): void
    {
        $where = fn (string $link) => (new MemberMailingFilter(link: $link))->whereSql()[0];

        $this->assertStringContainsString('m.Titular = 1', $where(MemberMailingFilter::LINK_TITULAR));
        $this->assertStringContainsString('m.Titular = 0', $where(MemberMailingFilter::LINK_DEPENDENT));
        $this->assertStringNotContainsString('m.Titular', $where(MemberMailingFilter::LINK_ALL));
    }

    public function test_sexo(): void
    {
        [$where, $bindings] = (new MemberMailingFilter(titleStatuses: [], memberStatuses: [], sex: 1, titleTypes: [365]))->whereSql();

        $this->assertStringContainsString('m.Sex = ?', $where);
        $this->assertSame([365, 1], $bindings);
    }

    public function test_idade_vira_faixa_de_nascimento_em_anos_completos(): void
    {
        // 18 anos ou mais em 09/11/2026: nasceu até 09/11/2008.
        [$where, $bindings] = (new MemberMailingFilter(titleTypes: [1], titleStatuses: [], memberStatuses: [], ageMin: 18, referenceDate: $this->ref()))->whereSql();
        $this->assertStringContainsString('m.BirthDate < ?', $where);
        $this->assertSame('2008-11-10', end($bindings));

        // Até 17: ainda não fez 18 → nasceu depois de 09/11/2008.
        [$where, $bindings] = (new MemberMailingFilter(titleTypes: [1], titleStatuses: [], memberStatuses: [], ageMax: 17, referenceDate: $this->ref()))->whereSql();
        $this->assertStringContainsString('m.BirthDate >= ?', $where);
        $this->assertSame('2008-11-10', end($bindings));

        // De 0 a 11.
        [, $bindings] = (new MemberMailingFilter(titleTypes: [1], titleStatuses: [], memberStatuses: [], ageMin: 0, ageMax: 11, referenceDate: $this->ref()))->whereSql();
        $this->assertSame(['2026-11-10', '2014-11-10'], array_slice($bindings, 1));
    }

    public function test_aniversario_no_proprio_dia_ja_conta(): void
    {
        // Em 28/02/2027, quem nasceu em 28/02/2026 já tem 1 ano; 01/03/2026 ainda não.
        [, $bindings] = (new MemberMailingFilter(titleTypes: [1], titleStatuses: [], memberStatuses: [], ageMin: 1, referenceDate: Carbon::parse('2027-02-28')))->whereSql();
        $this->assertSame('2026-03-01', end($bindings));
    }

    public function test_from_input_e_descricao(): void
    {
        $filter = MemberMailingFilter::fromInput([
            'tipos' => ['365', '365', '367'],
            'situacao_titulo' => ['0', '1'],
            'situacao_socio' => ['0'],
            'idade_min' => '18',
            'idade_max' => '',
            'sexo' => '1',
            'vinculo' => 'titular',
        ]);

        $this->assertSame([365, 367], $filter->titleTypes);
        $this->assertSame([0, 1], $filter->titleStatuses);
        $this->assertSame(18, $filter->ageMin);
        $this->assertNull($filter->ageMax);
        $this->assertSame(1, $filter->sex);
        $this->assertSame(
            'MultiClubes: EFETIVO, PROPRIETARIO; título ativo/bloqueado; sócio ativo; só titulares; feminino; 18 anos ou mais',
            $filter->describe([365 => 'EFETIVO', 367 => 'PROPRIETARIO'])
        );

        $this->assertNull(MemberMailingFilter::fromInput(['sexo' => '', 'vinculo' => 'todos'])->sex);
    }

    public function test_nome_em_maiusculas_vira_nome_proprio(): void
    {
        $this->assertSame('João da Silva e Souza', MultiClubesRecipientSource::properName('JOÃO  DA SILVA E SOUZA'));
        // Já misto: alguém digitou assim, fica como está.
        $this->assertSame('maria dos Anjos', MultiClubesRecipientSource::properName('maria dos Anjos'));
        $this->assertSame('', MultiClubesRecipientSource::properName('  '));
    }
}
