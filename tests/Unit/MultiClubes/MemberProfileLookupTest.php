<?php

namespace Tests\Unit\MultiClubes;

use App\Models\Tournament\TournamentCategoryTemplate as Template;
use App\Services\MultiClubes\MemberProfile;
use App\Services\MultiClubes\MemberProfileLookup;
use Tests\TestCase;

/**
 * Busca de sócio no MultiClubes para os torneios. Sem SQL Server: o que vai ao
 * banco fica fora; aqui, a regra do sexo, o CPF inválido que nem consulta e a
 * escolha do registro pela matrícula.
 */
class MemberProfileLookupTest extends TestCase
{
    public function test_sexo_zero_e_masculino_e_um_e_feminino(): void
    {
        $this->assertSame(Template::GENDER_MALE, MemberProfileLookup::genderFromSex(0));
        $this->assertSame(Template::GENDER_MALE, MemberProfileLookup::genderFromSex('0'));
        $this->assertSame(Template::GENDER_FEMALE, MemberProfileLookup::genderFromSex(1));
        $this->assertNull(MemberProfileLookup::genderFromSex(null));
        $this->assertNull(MemberProfileLookup::genderFromSex(2));
    }

    public function test_cpf_invalido_nao_chega_ao_multiclubes(): void
    {
        // Se consultasse, a conexão mc_sqlsrv (sem SQL Server nos testes) estouraria.
        $lookup = new MemberProfileLookup();

        $this->assertSame([], $lookup->byCpf('000.000.000-00'));
        $this->assertSame([], $lookup->byCpf('123'));
        $this->assertSame([], $lookup->byTitle('   '));
    }

    public function test_find_escolhe_o_registro_da_matricula(): void
    {
        $lookup = new class extends MemberProfileLookup {
            public function byCpf(string $cpf): array
            {
                return [
                    new MemberProfile('Ana Titular', '52998224725', '1001', null, Template::GENDER_FEMALE, null, true),
                    new MemberProfile('Ana Dependente', '52998224725', '2002', null, Template::GENDER_FEMALE, null, false),
                ];
            }
        };

        $this->assertSame('Ana Dependente', $lookup->find('529.982.247-25', ' 2002 ')?->name);
        $this->assertNull($lookup->find('529.982.247-25', '3003'));
    }
}
