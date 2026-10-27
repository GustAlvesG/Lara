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

    public static function exists(?string $type): bool
    {
        return $type !== null && isset(self::LABELS[$type]);
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
