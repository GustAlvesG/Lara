<?php

namespace Tests\Unit;

use App\Services\Signature\Govbr\Asn1;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * O leitor BER da validação gov.br.
 *
 * O caso que motivou o leitor é o primeiro: o gov.br grava o CMS com
 * comprimento INDEFINIDO, e logo depois vem o enchimento de zeros do
 * /Contents — um leitor DER estrito, ou um corte dos zeros do fim, erra.
 */
class GovbrAsn1Test extends TestCase
{
    public function test_comprimento_indefinido_termina_no_marcador_e_ignora_o_enchimento(): void
    {
        // SEQUENCE indefinida { INTEGER 5, SEQUENCE indefinida { NULL } } + EOC + enchimento.
        $bytes = "\x30\x80" . "\x02\x01\x05" . "\x30\x80" . "\x05\x00" . "\x00\x00" . "\x00\x00" . str_repeat("\x00", 20);

        $no = Asn1::node($bytes);

        $this->assertSame(13, $no['end']);
        $this->assertSame(substr($bytes, 0, 13), Asn1::raw($bytes, $no));
        $this->assertCount(2, Asn1::children($bytes, $no));
    }

    public function test_comprimento_longo(): void
    {
        $conteudo = str_repeat('A', 300);
        $bytes = "\x04\x82\x01\x2c" . $conteudo;

        $no = Asn1::node($bytes);

        $this->assertSame($conteudo, Asn1::content($bytes, $no));
    }

    public function test_oid(): void
    {
        // 2.16.76.1.3.1 — o CPF no certificado.
        $bytes = "\x06\x05\x60\x4c\x01\x03\x01";

        $this->assertSame('2.16.76.1.3.1', Asn1::oid($bytes, Asn1::node($bytes)));
    }

    public function test_hora_utc(): void
    {
        $bytes = "\x17\x0d" . '261007202158Z';

        $this->assertSame(gmmktime(20, 21, 58, 10, 7, 2026), Asn1::time($bytes, Asn1::node($bytes)));
    }

    public function test_estrutura_truncada_e_recusada(): void
    {
        $this->expectException(RuntimeException::class);

        Asn1::node("\x30\x80\x02\x01\x05");
    }
}
