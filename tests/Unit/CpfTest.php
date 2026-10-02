<?php

namespace Tests\Unit;

use App\Support\Cpf;
use PHPUnit\Framework\TestCase;

/**
 * As três operações de CPF que o módulo de assinatura usa o tempo todo.
 *
 * `matches()` é a que tem consequência de segurança: é a conferência de
 * identidade do tablet. O modo `partial` pede EXATAMENTE quatro dígitos —
 * aceitar um prefixo de tamanho livre deixaria "1" passar como confirmação.
 *
 * Herda de PHPUnit\Framework\TestCase, e não de Tests\TestCase: é código puro,
 * não precisa do framework no ar.
 */
class CpfTest extends TestCase
{
    public function test_digits_tira_a_mascara(): void
    {
        $this->assertSame('12345678909', Cpf::digits('123.456.789-09'));
        $this->assertSame('12345678909', Cpf::digits('12345678909'));
        $this->assertSame('', Cpf::digits(null));
    }

    public function test_format_devolve_o_cpf_pontuado(): void
    {
        $this->assertSame('123.456.789-09', Cpf::format('12345678909'));
    }

    /** O que pode aparecer em log, manifesto e página pública. */
    public function test_mask_esconde_o_miolo_e_mantem_as_pontas(): void
    {
        $this->assertSame('123.***.**9-09', Cpf::mask('123.456.789-09'));
        $this->assertSame('', Cpf::mask(null));
    }

    public function test_mask_de_documento_fora_do_padrao_nao_vaza(): void
    {
        $mascarado = Cpf::mask('1234567');

        $this->assertSame('12***67', $mascarado);
        $this->assertStringNotContainsString('345', $mascarado);
    }

    public function test_conferencia_parcial_exige_os_quatro_primeiros_digitos(): void
    {
        $this->assertTrue(Cpf::matches('1234', '123.456.789-09', 'partial'));
        $this->assertFalse(Cpf::matches('1235', '12345678909', 'partial'));
    }

    /**
     * Um dígito só não confirma identidade. O modo parcial é quatro dígitos,
     * não "começa com".
     */
    public function test_conferencia_parcial_recusa_prefixo_mais_curto(): void
    {
        $this->assertFalse(Cpf::matches('1', '12345678909', 'partial'));
        $this->assertFalse(Cpf::matches('123', '12345678909', 'partial'));
        $this->assertFalse(Cpf::matches('12345678909', '12345678909', 'partial'));
    }

    public function test_conferencia_completa_compara_o_cpf_inteiro(): void
    {
        $this->assertTrue(Cpf::matches('123.456.789-09', '12345678909', 'full'));
        $this->assertFalse(Cpf::matches('12345678900', '12345678909', 'full'));
    }

    public function test_sem_conferencia_sempre_passa(): void
    {
        $this->assertTrue(Cpf::matches(null, '12345678909', 'none'));
    }

    /** Cadastro sem CPF não vira conferência que passa por vacuidade. */
    public function test_cadastro_sem_cpf_nao_confirma_identidade(): void
    {
        $this->assertFalse(Cpf::matches('1234', null, 'partial'));
        $this->assertFalse(Cpf::matches('1234', '', 'full'));
    }
}
