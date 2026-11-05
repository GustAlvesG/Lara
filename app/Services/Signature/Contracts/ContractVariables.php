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
