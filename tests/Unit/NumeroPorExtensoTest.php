<?php

namespace Tests\Unit;

use App\Support\NumeroPorExtenso;
use PHPUnit\Framework\TestCase;

class NumeroPorExtensoTest extends TestCase
{
    public function test_inteiros(): void
    {
        $this->assertSame('um', NumeroPorExtenso::inteiro(1));
        $this->assertSame('quinze', NumeroPorExtenso::inteiro(15));
        $this->assertSame('cem', NumeroPorExtenso::inteiro(100));
        $this->assertSame('cento e um', NumeroPorExtenso::inteiro(101));
        $this->assertSame('mil', NumeroPorExtenso::inteiro(1000));
        $this->assertSame('mil e quinhentos', NumeroPorExtenso::inteiro(1500));
        $this->assertSame('mil e vinte', NumeroPorExtenso::inteiro(1020));
        $this->assertSame('mil duzentos e trinta', NumeroPorExtenso::inteiro(1230));
        $this->assertSame('oito mil e novecentos', NumeroPorExtenso::inteiro(8900));
        $this->assertSame('dois milhões e quinhentos mil', NumeroPorExtenso::inteiro(2_500_000));
        $this->assertSame(
            'um milhão duzentos e trinta e quatro mil quinhentos e sessenta e sete',
            NumeroPorExtenso::inteiro(1_234_567),
        );
    }

    public function test_reais(): void
    {
        $this->assertSame('oito mil e novecentos reais', NumeroPorExtenso::reais('8900.00'));
        $this->assertSame('um real', NumeroPorExtenso::reais(1));
        $this->assertSame('mil e quinhentos reais e cinquenta centavos', NumeroPorExtenso::reais(1500.5));
        $this->assertSame('um milhão de reais', NumeroPorExtenso::reais(1_000_000));
        $this->assertSame('cinquenta centavos', NumeroPorExtenso::reais(0.5));
    }

    public function test_decimal_e_ordinal(): void
    {
        $this->assertSame('dois vírgula cinco', NumeroPorExtenso::decimal('2.5'));
        $this->assertSame('dez', NumeroPorExtenso::decimal('10'));
        $this->assertSame('primeira', NumeroPorExtenso::ordinalFeminino(1));
        $this->assertSame('nona', NumeroPorExtenso::ordinalFeminino(9));
        $this->assertSame('décima', NumeroPorExtenso::ordinalFeminino(10));
        $this->assertSame('décima segunda', NumeroPorExtenso::ordinalFeminino(12));
        $this->assertSame('vigésima primeira', NumeroPorExtenso::ordinalFeminino(21));
    }
}
