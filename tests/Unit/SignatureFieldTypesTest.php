<?php

namespace Tests\Unit;

use App\Services\Signature\SignatureFieldTypes as T;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Os tipos de campo decidem o que entra num documento que alguém assina. O
 * que se confere aqui é o par: o que a pessoa digita vira UMA forma guardada,
 * e essa forma vira o texto certo no documento.
 *
 * PHPUnit puro — o catálogo não toca em banco nem em container.
 */
class SignatureFieldTypesTest extends TestCase
{
    /**
     * @return array<string, array{0: array<string, mixed>, 1: mixed, 2: mixed, 3: string}>
     */
    public static function validos(): array
    {
        return [
            'texto' => [['type' => T::TEXT], '  Salão de festas ', 'Salão de festas', 'Salão de festas'],
            'número inteiro' => [['type' => T::NUMBER], '150', '150', '150'],
            'número com vírgula' => [['type' => T::NUMBER], '2,5', '2.5', '2,5'],
            // Ano não ganha ponto de milhar.
            'número de quatro dígitos' => [['type' => T::NUMBER], '2026', '2026', '2026'],
            'real à brasileira' => [['type' => T::MONEY], '1.500,5', '1500.50', 'R$ 1.500,50'],
            'real com cifrão' => [['type' => T::MONEY], 'R$ 80', '80.00', 'R$ 80,00'],
            // Sem vírgula, o ponto é milhar: ninguém escreve mil e quinhentos como 1,5.
            'real com ponto de milhar' => [['type' => T::MONEY], '1.500', '1500.00', 'R$ 1.500,00'],
            'cpf com máscara' => [['type' => T::CPF], '123.456.789-09', '12345678909', '123.456.789-09'],
            'cpf sem máscara' => [['type' => T::CPF], '12345678909', '12345678909', '123.456.789-09'],
            'cnpj' => [['type' => T::CNPJ], '11.222.333/0001-81', '11222333000181', '11.222.333/0001-81'],
            'cnpj alfanumérico' => [['type' => T::CNPJ], '12.abc.345/01de-35', '12ABC34501DE35', '12.ABC.345/01DE-35'],
            'e-mail' => [['type' => T::EMAIL], 'Maria@Exemplo.com', 'maria@exemplo.com', 'maria@exemplo.com'],
            'celular' => [['type' => T::PHONE], '(24) 99999-1234', '24999991234', '(24) 99999-1234'],
            'fixo' => [['type' => T::PHONE], '24 3333-4444', '2433334444', '(24) 3333-4444'],
            'telefone com +55' => [['type' => T::PHONE], '+55 24 99999-1234', '24999991234', '(24) 99999-1234'],
            'cep' => [['type' => T::CEP], '27255-125', '27255125', '27255-125'],
            'data do seletor' => [['type' => T::DATE], '2026-10-03', '2026-10-03', '03/10/2026'],
            'data digitada' => [['type' => T::DATE], '3/10/2026', '2026-10-03', '03/10/2026'],
            'data por extenso' => [['type' => T::DATE_LONG], '2026-10-03', '2026-10-03', '3 de outubro de 2026'],
            'primeiro do mês' => [['type' => T::DATE_LONG], '2026-03-01', '2026-03-01', '1º de março de 2026'],
            'hora' => [['type' => T::TIME], '9:05', '09:05', '09:05'],
            'opção única' => [['type' => T::RADIO, 'options' => ['Piscina', 'Quadra']], 'Quadra', 'Quadra', 'Quadra'],
            'sim ou não' => [['type' => T::YES_NO], 'nao', 'nao', 'Não'],
            // Na ordem do modelo, e não na do toque.
            'múltipla escolha' => [
                ['type' => T::CHECKBOX, 'options' => ['Piscina', 'Academia', 'Quadra']],
                ['Quadra', 'Piscina'],
                ['Piscina', 'Quadra'],
                'Piscina e Quadra',
            ],
            'três opções' => [
                ['type' => T::CHECKBOX, 'options' => ['A', 'B', 'C']],
                ['A', 'B', 'C'],
                ['A', 'B', 'C'],
                'A, B e C',
            ],
        ];
    }

    #[DataProvider('validos')]
    public function test_valor_valido_e_guardado_numa_forma_e_escrito_em_outra(
        array $campo,
        mixed $digitado,
        mixed $guardado,
        string $noDocumento,
    ): void {
        [$valor, $erro] = T::parse($campo, $digitado);

        $this->assertNull($erro);
        $this->assertSame($guardado, $valor);
        $this->assertSame($noDocumento, T::format($campo, $valor));

        // Reabrir o formulário e salvar sem mexer não pode mudar o valor.
        [$deNovo] = T::parse($campo, T::inputValue($campo, $valor));
        $this->assertSame($guardado, $deNovo);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: mixed}>
     */
    public static function invalidos(): array
    {
        return [
            'cpf com dígito errado' => [['type' => T::CPF], '123.456.789-00'],
            'cpf repetido' => [['type' => T::CPF], '111.111.111-11'],
            'cpf curto' => [['type' => T::CPF], '1234'],
            'cnpj com dígito errado' => [['type' => T::CNPJ], '11.222.333/0001-80'],
            'e-mail sem domínio' => [['type' => T::EMAIL], 'maria@'],
            'telefone sem ddd' => [['type' => T::PHONE], '99999-1234'],
            'cep curto' => [['type' => T::CEP], '2725'],
            'número com letra' => [['type' => T::NUMBER], 'cento e dez'],
            'valor negativo' => [['type' => T::MONEY], '-10,00'],
            'trinta de fevereiro' => [['type' => T::DATE], '30/02/2026'],
            'data solta' => [['type' => T::DATE_LONG], 'amanhã'],
            'hora que não existe' => [['type' => T::TIME], '25:00'],
            'opção fora da lista' => [['type' => T::RADIO, 'options' => ['Piscina']], 'Sauna'],
            'múltipla fora da lista' => [['type' => T::CHECKBOX, 'options' => ['Piscina']], ['Piscina', 'Sauna']],
            'sim ou não com outra coisa' => [['type' => T::YES_NO], 'talvez'],
            'texto que não é texto' => [['type' => T::TEXT], ['a']],
        ];
    }

    #[DataProvider('invalidos')]
    public function test_valor_invalido_e_recusado_com_mensagem(array $campo, mixed $digitado): void
    {
        [$valor, $erro] = T::parse($campo, $digitado);

        $this->assertNull($valor);
        $this->assertNotNull($erro);
    }

    public function test_em_branco_nao_e_erro(): void
    {
        // Quem decide se podia ficar em branco é quem chama: o rascunho do
        // atendente pode, a resposta obrigatória do tablet não.
        foreach (array_keys(T::LABELS) as $tipo) {
            $this->assertSame([null, null], T::parse(['type' => $tipo, 'options' => ['A']], '  '), $tipo);
        }

        $this->assertSame([null, null], T::parse(['type' => T::CHECKBOX, 'options' => ['A']], []));
        $this->assertSame('', T::format(['type' => T::MONEY], null));
    }

    public function test_campo_sem_tipo_vale_como_texto(): void
    {
        // Modelo gravado antes de os campos terem tipo.
        $this->assertSame(['Salão', null], T::parse([], 'Salão'));
        $this->assertSame('Salão', T::format([], 'Salão'));
    }

    public function test_palpite_do_tipo_pelo_nome_do_campo(): void
    {
        $this->assertSame(T::CPF, T::guess('CPF do responsável'));
        $this->assertSame(T::EMAIL, T::guess('E-mail'));
        $this->assertSame(T::PHONE, T::guess('Telefone de contato'));
        $this->assertSame(T::MONEY, T::guess('Valor total (R$)'));
        $this->assertSame(T::DATE, T::guess('Data do evento'));
        $this->assertSame(T::DATE_SIGNING, T::guess('Data da assinatura'));
        $this->assertSame(T::CEP, T::guess('CEP'));
        // Na dúvida é texto, que aceita tudo.
        $this->assertSame(T::TEXT, T::guess('Horário'));
        $this->assertSame(T::TEXT, T::guess('Espaço locado'));
        $this->assertSame(T::TEXT, T::guess('Validade'));
    }

    public function test_todo_tipo_automatico_e_de_escolha_existe_no_catalogo(): void
    {
        foreach ([...T::AUTOMATIC, ...T::WITH_OPTIONS] as $tipo) {
            $this->assertTrue(T::exists($tipo));
        }

        $this->assertFalse(T::exists('qualquer'));
        $this->assertFalse(T::exists(null));
    }
}
