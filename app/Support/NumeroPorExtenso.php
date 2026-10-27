<?php

namespace App\Support;

/**
 * Números escritos por extenso, em português — o "R$ 8.900,00 (oito mil e
 * novecentos reais)" dos contratos.
 *
 * Existe porque o extenso era digitado à mão ao lado do número, e os dois
 * divergiam sem ninguém notar. Quem escreve o contrato informa só o número;
 * o texto sai daqui.
 *
 * Só o masculino nos cardinais ("dois dias", "um mês"): é o que os contratos
 * do clube usam. As cláusulas usam o ordinal FEMININO ("CLÁUSULA PRIMEIRA").
 */
class NumeroPorExtenso
{
    private const UNIDADES = [
        '', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove',
        'dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove',
    ];

    private const DEZENAS = [
        '', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa',
    ];

    private const CENTENAS = [
        '', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos',
        'seiscentos', 'setecentos', 'oitocentos', 'novecentos',
    ];

    /** [singular, plural], da classe mais alta para a mais baixa. */
    private const CLASSES = [
        1_000_000_000 => ['bilhão', 'bilhões'],
        1_000_000 => ['milhão', 'milhões'],
        1_000 => ['mil', 'mil'],
    ];

    private const ORDINAIS_UNIDADE = [
        '', 'primeira', 'segunda', 'terceira', 'quarta', 'quinta', 'sexta', 'sétima', 'oitava', 'nona',
    ];

    private const ORDINAIS_DEZENA = [
        '', 'décima', 'vigésima', 'trigésima', 'quadragésima', 'quinquagésima',
        'sexagésima', 'septuagésima', 'octogésima', 'nonagésima',
    ];

    /** Um inteiro por extenso: 1500 → "mil e quinhentos". */
    public static function inteiro(int $numero): string
    {
        if ($numero === 0) {
            return 'zero';
        }

        if ($numero < 0) {
            return 'menos ' . self::inteiro(-$numero);
        }

        // Cada grupo: [texto, quanto vale dentro da própria classe (1 a 999)].
        $grupos = [];
        $resto = $numero;

        foreach (self::CLASSES as $valor => [$singular, $plural]) {
            $quantos = intdiv($resto, $valor);

            if ($quantos === 0) {
                continue;
            }

            // "mil", e não "um mil".
            $grupos[] = [
                $valor === 1_000 && $quantos === 1
                    ? 'mil'
                    : self::ateMil($quantos) . ' ' . ($quantos === 1 ? $singular : $plural),
                $quantos,
            ];

            $resto %= $valor;
        }

        if ($resto > 0) {
            $grupos[] = [self::ateMil($resto), $resto];
        }

        /*
         | Entre um grupo e o seguinte vai "e" quando o seguinte é menor que cem
         | ou "redondo": "mil e quinhentos", "mil e vinte", "dois milhões e
         | quinhentos mil" — mas "mil duzentos e trinta".
         */
        $texto = array_shift($grupos)[0];

        foreach ($grupos as [$parte, $valorDoGrupo]) {
            $texto .= ($valorDoGrupo < 100 || $valorDoGrupo % 100 === 0 ? ' e ' : ' ') . $parte;
        }

        return $texto;
    }

    /**
     * Valor em reais por extenso: 8900 → "oito mil e novecentos reais";
     * 1500.5 → "mil e quinhentos reais e cinquenta centavos".
     */
    public static function reais(float|string $valor): string
    {
        $centavosTotais = (int) round(((float) $valor) * 100);
        $reais = intdiv($centavosTotais, 100);
        $centavos = $centavosTotais % 100;

        $texto = [];

        if ($reais > 0) {
            // "um milhão DE reais": classe redonda de milhão para cima pede o "de".
            $de = $reais >= 1_000_000 && $reais % 1_000_000 === 0 ? ' de' : '';
            $texto[] = self::inteiro($reais) . $de . ($reais === 1 ? ' real' : ' reais');
        }

        if ($centavos > 0) {
            $texto[] = self::inteiro($centavos) . ($centavos === 1 ? ' centavo' : ' centavos');
        }

        return $texto === [] ? 'zero real' : implode(' e ', $texto);
    }

    /**
     * Número com casas decimais por extenso ("dois vírgula cinco"). Inteiro
     * sai sem vírgula.
     */
    public static function decimal(float|string $valor): string
    {
        $texto = rtrim(rtrim(number_format((float) $valor, 4, '.', ''), '0'), '.');

        if (!str_contains($texto, '.')) {
            return self::inteiro((int) $texto);
        }

        [$inteiro, $fracao] = explode('.', $texto);

        // Zeros à esquerda da fração são lidos um a um: 2,05 → "dois vírgula zero cinco".
        $zeros = strlen($fracao) - strlen(ltrim($fracao, '0'));

        return self::inteiro((int) $inteiro) . ' vírgula '
            . str_repeat('zero ', $zeros) . self::inteiro((int) ltrim($fracao, '0'));
    }

    /** O ordinal feminino, em minúsculas: 1 → "primeira", 12 → "décima segunda". */
    public static function ordinalFeminino(int $numero): string
    {
        if ($numero < 1 || $numero > 99) {
            return (string) $numero . 'ª';
        }

        $dezena = intdiv($numero, 10);
        $unidade = $numero % 10;

        return trim(self::ORDINAIS_DEZENA[$dezena] . ' ' . self::ORDINAIS_UNIDADE[$unidade]);
    }

    /** De 1 a 999. */
    private static function ateMil(int $numero): string
    {
        if ($numero === 100) {
            return 'cem';
        }

        $centena = intdiv($numero, 100);
        $resto = $numero % 100;

        $partes = [];

        if ($centena > 0) {
            $partes[] = self::CENTENAS[$centena];
        }

        if ($resto > 0) {
            $partes[] = $resto < 20
                ? self::UNIDADES[$resto]
                : self::DEZENAS[intdiv($resto, 10)] . ($resto % 10 > 0 ? ' e ' . self::UNIDADES[$resto % 10] : '');
        }

        return implode(' e ', $partes);
    }
}
