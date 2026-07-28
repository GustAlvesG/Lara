<?php

namespace Tests\Unit;

use App\Models\Freelancer;
use Tests\TestCase;

/**
 * Regra que libera (ou barra) a geração de contrato conforme a completude do
 * cadastro do freelancer. É a fonte única usada pelo painel web e pelo tablet,
 * então merece cobertura direta — sem banco.
 */
class FreelancerContractCompletenessTest extends TestCase
{
    private function completeFreelancer(): Freelancer
    {
        return new Freelancer([
            'name' => 'Maria da Silva',
            'cpf' => '12345678901',
            'rg' => 'MG1234567',
            'nacionality' => 'Brasileira',
            'civil_status' => 'Solteira',
            'address' => 'Rua das Flores, 100 - Belo Horizonte/MG',
            'telephone' => '31999998888',
        ]);
    }

    public function test_cadastro_com_todos_os_campos_esta_completo(): void
    {
        $freelancer = $this->completeFreelancer();

        $this->assertTrue($freelancer->hasCompleteContractData());
        $this->assertSame([], $freelancer->missingContractFields());
        $this->assertSame([], $freelancer->missingContractFieldLabels());
    }

    public function test_email_nao_e_exigido_para_o_contrato(): void
    {
        $freelancer = $this->completeFreelancer();
        $freelancer->email = null;

        $this->assertTrue($freelancer->hasCompleteContractData());
    }

    public function test_campo_faltante_deixa_o_cadastro_incompleto(): void
    {
        $freelancer = $this->completeFreelancer();
        $freelancer->address = null;

        $this->assertFalse($freelancer->hasCompleteContractData());
        $this->assertContains('address', $freelancer->missingContractFields());
        $this->assertContains('endereço', $freelancer->missingContractFieldLabels());
    }

    public function test_string_vazia_conta_como_faltante(): void
    {
        $freelancer = $this->completeFreelancer();
        $freelancer->telephone = '';

        $this->assertFalse($freelancer->hasCompleteContractData());
        $this->assertContains('telephone', $freelancer->missingContractFields());
    }

    public function test_lista_todos_os_campos_pendentes(): void
    {
        $freelancer = new Freelancer([
            'name' => 'João',
            'cpf' => '98765432100',
        ]);

        $this->assertFalse($freelancer->hasCompleteContractData());
        $this->assertEqualsCanonicalizing(
            ['rg', 'nacionality', 'civil_status', 'address', 'telephone'],
            $freelancer->missingContractFields()
        );
    }
}
