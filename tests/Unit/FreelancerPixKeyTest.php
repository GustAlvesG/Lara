<?php

namespace Tests\Unit;

use App\Models\Freelancer;
use App\Models\FreelancerService;
use Tests\TestCase;

/**
 * Leitura, formatação e normalização da chave PIX — a regra que decide para
 * onde o dinheiro do contrato vai, e que o tablet mostra ao freelancer antes de
 * ele assinar. Sem banco: são funções puras do model.
 */
class FreelancerPixKeyTest extends TestCase
{
    private function freelancer(?string $pixKey): Freelancer
    {
        return new Freelancer(['name' => 'Maria da Silva', 'cpf' => '12345678901', 'pix_key' => $pixKey]);
    }

    public function test_sem_chave_informada_a_chave_e_o_cpf(): void
    {
        $freelancer = $this->freelancer(null);

        $this->assertSame('12345678901', $freelancer->pixKey());
        $this->assertSame(Freelancer::PIX_KEY_CPF, $freelancer->pixKeyType());
        $this->assertSame('123.456.789-01', $freelancer->pixKeyFormatted());
        $this->assertSame('CPF', $freelancer->pixKeyTypeLabel());
    }

    public function test_reconhece_os_tipos_de_chave(): void
    {
        $this->assertSame(Freelancer::PIX_KEY_EMAIL, Freelancer::pixKeyTypeFor('maria@exemplo.com'));
        $this->assertSame(Freelancer::PIX_KEY_PHONE, Freelancer::pixKeyTypeFor('+5524999998888'));
        $this->assertSame(Freelancer::PIX_KEY_CPF, Freelancer::pixKeyTypeFor('12345678901'));
        $this->assertSame(Freelancer::PIX_KEY_CNPJ, Freelancer::pixKeyTypeFor('12345678000199'));
        $this->assertSame(
            Freelancer::PIX_KEY_RANDOM,
            Freelancer::pixKeyTypeFor('123e4567-e89b-12d3-a456-426614174000')
        );
        $this->assertNull(Freelancer::pixKeyTypeFor(''));
    }

    public function test_formata_numero_e_deixa_email_como_esta(): void
    {
        $this->assertSame('+55 (24) 99999-8888', Freelancer::formatPixKey('+5524999998888'));
        $this->assertSame('12.345.678/0001-99', Freelancer::formatPixKey('12345678000199'));
        $this->assertSame('maria@exemplo.com', Freelancer::formatPixKey('maria@exemplo.com'));
    }

    /**
     * O telefone é o caso que separa esta implementação de um palpite: o
     * comprimento decide se o `55` que veio na frente é o país ou o DDD.
     */
    public function test_normaliza_telefone_com_e_sem_o_codigo_do_pais(): void
    {
        $this->assertSame(
            '+5524999998888',
            Freelancer::normalizePixKey(Freelancer::PIX_KEY_PHONE, '(24) 99999-8888')
        );
        $this->assertSame(
            '+5524999998888',
            Freelancer::normalizePixKey(Freelancer::PIX_KEY_PHONE, '+55 24 99999-8888')
        );
        // DDD 55 sem país: os 11 dígitos são DDD + celular, e o país entra.
        $this->assertSame(
            '+5555999998888',
            Freelancer::normalizePixKey(Freelancer::PIX_KEY_PHONE, '55 99999-8888')
        );
    }

    public function test_normaliza_cpf_e_email(): void
    {
        $this->assertSame('12345678901', Freelancer::normalizePixKey(Freelancer::PIX_KEY_CPF, '123.456.789-01'));
        $this->assertSame('maria@exemplo.com', Freelancer::normalizePixKey(Freelancer::PIX_KEY_EMAIL, ' Maria@Exemplo.COM '));
    }

    public function test_recusa_chave_que_nao_bate_com_o_tipo_escolhido(): void
    {
        $this->assertNull(Freelancer::pixKeyError(Freelancer::PIX_KEY_CPF, '123.456.789-01'));
        $this->assertNotNull(Freelancer::pixKeyError(Freelancer::PIX_KEY_CPF, '12345'));

        $this->assertNull(Freelancer::pixKeyError(Freelancer::PIX_KEY_EMAIL, 'maria@exemplo.com'));
        $this->assertNotNull(Freelancer::pixKeyError(Freelancer::PIX_KEY_EMAIL, 'maria@'));

        $this->assertNull(Freelancer::pixKeyError(Freelancer::PIX_KEY_PHONE, '(24) 99999-8888'));
        $this->assertNotNull(Freelancer::pixKeyError(Freelancer::PIX_KEY_PHONE, '99998888'));

        $this->assertNull(Freelancer::pixKeyError(Freelancer::PIX_KEY_RANDOM, '123e4567-e89b-12d3-a456-426614174000'));
        $this->assertNotNull(Freelancer::pixKeyError(Freelancer::PIX_KEY_RANDOM, 'chave-qualquer'));
    }

    /**
     * O contrato assinado cita a chave que foi conferida, não a que estiver no
     * cadastro depois — é o que impede um documento firmado de mudar sozinho.
     */
    public function test_contrato_assinado_cita_a_chave_congelada(): void
    {
        $freelancer = $this->freelancer('maria@exemplo.com');

        $service = new FreelancerService(['pix_key' => '12345678901']);
        $service->setRelation('freelancer', $freelancer);

        $this->assertSame('12345678901', $service->pixKey());
        $this->assertSame('123.456.789-01', $service->pixKeyFormatted());
        $this->assertSame('CPF', $service->pixKeyTypeLabel());
        $this->assertTrue($service->pixKeyDivergesFromFreelancer());
    }

    public function test_contrato_sem_chave_congelada_cai_no_cadastro(): void
    {
        $service = new FreelancerService();
        $service->setRelation('freelancer', $this->freelancer('maria@exemplo.com'));

        $this->assertSame('maria@exemplo.com', $service->pixKey());
        $this->assertFalse($service->pixKeyDivergesFromFreelancer());
        $this->assertFalse($service->pixKeyWasConfirmed());
    }
}
