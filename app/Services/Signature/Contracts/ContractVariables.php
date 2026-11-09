<?php

namespace App\Services\Signature\Contracts;

use App\Services\Signature\SignatureFieldTypes;
use App\Support\NumeroPorExtenso;

/**
 * Os tipos de variável de um contrato e como cada uma sai ESCRITA.
 *
 * A conferência e a forma canônica de CPF, CNPJ, valor e data são as de
 * SignatureFieldTypes — um lugar só sabe o que é um CNPJ válido. O que é
 * próprio do contrato é o extenso: "R$ 8.900,00 (oito mil e novecentos
 * reais)", "15 (quinze)", "10% (dez por cento)".
 *
 * O valor é guardado como a pessoa digitou e conferido a cada renderização:
 * reabrir o contrato mostra o que ela escreveu, e um valor inválido vira
 * problema na lista (e trava o envio), em vez de sumir.
 */
class ContractVariables
{
    public const TEXT = 'text';
    public const MONEY = 'money';
    public const NUMBER = 'number';
    public const PERCENT = 'percent';
    public const DATE = 'date';
    public const DATE_LONG = 'date_long';
    public const CPF = 'cpf';
    public const CNPJ = 'cnpj';

    public const LABELS = [
        self::TEXT => 'Texto',
        self::MONEY => 'Valor em reais, com extenso — R$ 8.900,00 (oito mil e novecentos reais)',
        self::NUMBER => 'Número, com extenso — 15 (quinze)',
        self::PERCENT => 'Percentual, com extenso — 10% (dez por cento)',
        self::DATE => 'Data — 28/09/2026',
        self::DATE_LONG => 'Data por extenso — 28 de setembro de 2026',
        self::CPF => 'CPF',
        self::CNPJ => 'CNPJ',
    ];

    /** O tipo do módulo que confere cada tipo daqui. */
    private const FIELD_TYPES = [
        self::TEXT => SignatureFieldTypes::TEXT,
        self::MONEY => SignatureFieldTypes::MONEY,
        self::NUMBER => SignatureFieldTypes::NUMBER,
        self::PERCENT => SignatureFieldTypes::NUMBER,
        self::DATE => SignatureFieldTypes::DATE,
        self::DATE_LONG => SignatureFieldTypes::DATE_LONG,
        self::CPF => SignatureFieldTypes::CPF,
        self::CNPJ => SignatureFieldTypes::CNPJ,
    ];

    /**
     * O palpite do tipo pelo nome da variável (05/11/2026): "Valor global" é
     * dinheiro, "Data de início" é data por extenso, "Prazo em meses" é
     * número. A primeira regra que casa vale; nada casa, é texto. As mesmas
     * expressões vão para o editor (JavaScript), sobre o nome sem acento e em
     * minúsculas — por isso só `\b`, classes simples e nenhum modificador.
     *
     * @var array<string, string>
     */
    public const GUESS = [
        self::CPF => '\bcpf\b',
        self::CNPJ => '\bcnpj\b',
        self::PERCENT => 'percent|porcent|%|\bjuros\b|\bindice de\b',
        self::DATE_LONG => '\bdata\b|\binicio\b|\btermino\b|\bassinatura em\b',
        self::MONEY => '\bvalor\b|\bvalores\b|\bpreco\b|\baluguel\b|\bmensalidade\b|\btaxa\b|\bmulta\b|\bcusto\b|\bsinal\b|\bpagamento\b|\breais\b|\bcaucao\b|\bdiaria\b',
        self::NUMBER => '\bprazo\b|\bquantidade\b|\bnumero\b|\bdias\b|\bmeses\b|\bparcelas\b|\bqtd\b|\bdia\b|\bhoras\b|\banos\b|\bvagas\b|\bpessoas\b',
    ];

    /**
     * Variáveis comuns para escolher pronto, com o tipo certo (05/11/2026).
     *
     * @var array<int, array{label: string, type: string}>
     */
    public const COMMON = [
        ['label' => 'Valor global', 'type' => self::MONEY],
        ['label' => 'Valor mensal', 'type' => self::MONEY],
        ['label' => 'Valor da parcela', 'type' => self::MONEY],
        ['label' => 'Número de parcelas', 'type' => self::NUMBER],
        ['label' => 'Dia do vencimento', 'type' => self::NUMBER],
        ['label' => 'Prazo de vigência em meses', 'type' => self::NUMBER],
        ['label' => 'Prazo de execução em dias', 'type' => self::NUMBER],
        ['label' => 'Data de início', 'type' => self::DATE_LONG],
        ['label' => 'Data de término', 'type' => self::DATE_LONG],
        ['label' => 'Multa por atraso em percentual', 'type' => self::PERCENT],
        ['label' => 'Índice de reajuste', 'type' => self::TEXT],
        ['label' => 'Forma de pagamento', 'type' => self::TEXT],
        ['label' => 'Local da prestação do serviço', 'type' => self::TEXT],
    ];

    /**
     * O campo de preenchimento de cada tipo (decisão do usuário, 09/11/2026):
     * a pessoa vê um campo só, o do preenchimento, e ele muda com o tipo —
     * calendário para data, teclado numérico com "R$" ou "%" ao lado para
     * quantia e percentual. O editor (JavaScript) recebe este mesmo mapa e
     * troca o campo quando o tipo muda. Quem confere continua sendo format().
     *
     * `input`: o type do <input>; `inputmode`: o teclado do celular.
     *
     * @var array<string, array{input: string, inputmode: string, placeholder: string, prefix: string, suffix: string, hint: string}>
     */
    public const INPUTS = [
        self::TEXT => ['input' => 'text', 'inputmode' => 'text', 'placeholder' => 'Escreva como deve sair no contrato', 'prefix' => '', 'suffix' => '', 'hint' => 'Sai no contrato do jeito que você escrever.'],
        self::MONEY => ['input' => 'text', 'inputmode' => 'decimal', 'placeholder' => '8.900,00', 'prefix' => 'R$', 'suffix' => '', 'hint' => 'Só o número. Sai com o extenso: R$ 8.900,00 (oito mil e novecentos reais).'],
        self::NUMBER => ['input' => 'text', 'inputmode' => 'decimal', 'placeholder' => '12', 'prefix' => '', 'suffix' => '', 'hint' => 'Só o número. Sai com o extenso: 12 (doze).'],
        self::PERCENT => ['input' => 'text', 'inputmode' => 'decimal', 'placeholder' => '10', 'prefix' => '', 'suffix' => '%', 'hint' => 'Só o número. Sai com o extenso: 10% (dez por cento).'],
        self::DATE => ['input' => 'date', 'inputmode' => 'numeric', 'placeholder' => '', 'prefix' => '', 'suffix' => '', 'hint' => 'Sai assim: 28/09/2026.'],
        self::DATE_LONG => ['input' => 'date', 'inputmode' => 'numeric', 'placeholder' => '', 'prefix' => '', 'suffix' => '', 'hint' => 'Sai por extenso: 28 de setembro de 2026.'],
        self::CPF => ['input' => 'text', 'inputmode' => 'numeric', 'placeholder' => '000.000.000-00', 'prefix' => '', 'suffix' => '', 'hint' => 'O CPF é conferido e sai com pontos e traço.'],
        self::CNPJ => ['input' => 'text', 'inputmode' => 'text', 'placeholder' => '00.000.000/0000-00', 'prefix' => '', 'suffix' => '', 'hint' => 'O CNPJ é conferido e sai com pontos, barra e traço.'],
    ];

    /** O campo de preenchimento do tipo — ver INPUTS. */
    public static function input(?string $type): array
    {
        return self::INPUTS[self::exists($type) ? $type : self::TEXT];
    }

    /**
     * O preenchimento como vai para o campo da tela: a data guardada como
     * 28/09/2026 vira 2026-09-28, o formato do calendário (<input type=date>).
     * O resto vai como está.
     */
    public static function inputValue(?string $type, mixed $value): string
    {
        $texto = trim((string) $value);

        if (in_array($type, [self::DATE, self::DATE_LONG], true)
            && preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $texto, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return $texto;
    }

    /**
     * O preenchimento como fica guardado: o calendário manda 2026-09-28, e a
     * data fica 28/09/2026, como sempre foi (é o que aparece no histórico de
     * revisões e no "o que mudou").
     */
    public static function storedValue(?string $type, string $value): string
    {
        if (in_array($type, [self::DATE, self::DATE_LONG], true)
            && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return $m[3] . '/' . $m[2] . '/' . $m[1];
        }

        return $value;
    }

    public static function exists(?string $type): bool
    {
        return $type !== null && isset(self::LABELS[$type]);
    }

    /** O tipo pelo nome — ver GUESS. */
    public static function guessType(string $label): string
    {
        $nome = mb_strtolower(\Illuminate\Support\Str::ascii($label));

        foreach (self::GUESS as $tipo => $regra) {
            if (preg_match('/' . $regra . '/', $nome)) {
                return $tipo;
            }
        }

        return self::TEXT;
    }

    /**
     * O valor escrito no contrato. Devolve `[texto, erro]`: texto vazio com
     * erro nulo é "sem valor".
     *
     * @return array{0: string, 1: ?string}
     */
    public static function format(string $type, mixed $raw): array
    {
        $type = self::exists($type) ? $type : self::TEXT;
        $texto = trim((string) $raw);

        if ($texto === '') {
            return ['', null];
        }

        if ($type === self::PERCENT) {
            $texto = rtrim($texto, "% \t");
        }

        $campo = ['type' => self::FIELD_TYPES[$type]];

        [$valor, $erro] = SignatureFieldTypes::parse($campo, $texto);

        if ($erro !== null) {
            return ['', $erro];
        }

        return match ($type) {
            self::MONEY => [
                SignatureFieldTypes::format($campo, $valor) . ' (' . NumeroPorExtenso::reais($valor) . ')',
                null,
            ],
            // O número sai como foi digitado ("01 (um)"), e o extenso, do valor.
            self::NUMBER => [$texto . ' (' . NumeroPorExtenso::decimal($valor) . ')', null],
            self::PERCENT => [
                SignatureFieldTypes::format($campo, $valor) . '% (' . NumeroPorExtenso::decimal($valor) . ' por cento)',
                null,
            ],
            default => [SignatureFieldTypes::format($campo, $valor), null],
        };
    }
}
