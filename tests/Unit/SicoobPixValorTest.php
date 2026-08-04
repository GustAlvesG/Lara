<?php

namespace Tests\Unit;

use App\Services\Sicoob\SicoobAuthService;
use App\Services\Sicoob\SicoobContaCorrenteService;
use App\Services\Sicoob\SicoobPixPagamentoService;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Formato do valor e o teto por transferência.
 *
 * Regras puras, exercitadas sem banco e sem rede — são as duas barreiras que
 * ficam entre um número digitado errado e o dinheiro saindo da conta, e por
 * isso merecem teste próprio em vez de virem de carona num teste de fluxo.
 *
 * O formato de `valor` vem da especificação oficial do Sicoob:
 * `^[0-9]{1,18}([,][0-9]{1,2})?$` — string, vírgula decimal, sem milhar.
 */
class SicoobPixValorTest extends TestCase
{
    private function service(): SicoobPixPagamentoService
    {
        return new SicoobPixPagamentoService(
            $this->createMock(SicoobAuthService::class),
            $this->createMock(SicoobContaCorrenteService::class),
        );
    }

    public function test_formata_valor_com_virgula_e_sem_separador_de_milhar(): void
    {
        $this->assertSame('1234,50', SicoobPixPagamentoService::formatarValor(1234.5));
        $this->assertSame('1,99', SicoobPixPagamentoService::formatarValor(1.99));
        $this->assertSame('100,00', SicoobPixPagamentoService::formatarValor(100.0));
        $this->assertSame('0,01', SicoobPixPagamentoService::formatarValor(0.01));
    }

    public function test_valor_formatado_casa_com_o_padrao_da_api(): void
    {
        // O mesmo regex publicado na especificação. Se um dia o formato mudar,
        // é aqui que a divergência aparece — e não num 400 em produção.
        $padrao = '/^[0-9]{1,18}([,][0-9]{1,2})?$/';

        foreach ([0.01, 1.0, 1.99, 250.0, 1234.5, 99999.99] as $valor) {
            $this->assertMatchesRegularExpression(
                $padrao,
                SicoobPixPagamentoService::formatarValor($valor),
                "Valor {$valor} formatado fora do padrão aceito pelo Sicoob."
            );
        }
    }

    public function test_recusa_valor_zero_ou_negativo(): void
    {
        $service = $this->service();

        $this->expectException(InvalidArgumentException::class);
        $service->validarValor(0);
    }

    public function test_recusa_valor_negativo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->validarValor(-10.0);
    }

    public function test_recusa_valor_acima_do_teto_configurado(): void
    {
        config(['sicoob.pix.max_amount' => 5000]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/teto configurado/');

        $this->service()->validarValor(5000.01);
    }

    public function test_aceita_valor_exatamente_no_teto(): void
    {
        config(['sicoob.pix.max_amount' => 5000]);

        $this->service()->validarValor(5000.00);

        $this->assertTrue(true, 'O teto é inclusivo: 5000,00 com teto de 5000 deve passar.');
    }

    public function test_recusa_valor_com_mais_de_duas_casas_decimais(): void
    {
        config(['sicoob.pix.max_amount' => 5000]);

        // A API arredondaria em silêncio; a diferença só apareceria na
        // conciliação contábil, muito depois.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/duas casas decimais/');

        $this->service()->validarValor(10.999);
    }
}
