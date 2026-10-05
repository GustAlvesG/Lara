<?php

namespace App\Services\Signature;

use App\Support\Cpf;
use Carbon\CarbonInterface;

/**
 * Os tipos de campo de um modelo de documento.
 *
 * Um campo é o `[[marcador]]` do texto. O tipo decide três coisas, e as três
 * moram aqui para que o formulário do atendente e o do tablet não tenham,
 * cada um, a sua ideia do que é um CPF válido:
 *
 *  - como o valor é CONFERIDO e guardado (`parse`) — sempre numa forma
 *    canônica, sem máscara: `1500.00`, `2026-10-03`, só os dígitos do CPF;
 *  - como ele é ESCRITO no documento (`format`): `R$ 1.500,00`,
 *    `3 de outubro de 2026`, `123.456.789-09`;
 *  - como ele volta para um campo de formulário (`inputValue`).
 *
 * Guardar a forma canônica, e não o texto já formatado, é o que permite
 * reabrir um rascunho e corrigir uma data sem redigitá-la.
 */
class SignatureFieldTypes
{
    public const TEXT = 'text';
    public const TEXTAREA = 'textarea';
    public const NUMBER = 'number';
    public const MONEY = 'money';
    public const CPF = 'cpf';
    public const CNPJ = 'cnpj';
    public const EMAIL = 'email';
    public const PHONE = 'phone';
    public const CEP = 'cep';
    public const DATE = 'date';
    public const DATE_LONG = 'date_long';
    public const TIME = 'time';
    public const RADIO = 'radio';
    public const CHECKBOX = 'checkbox';
    public const YES_NO = 'yes_no';
    public const DATE_SIGNING = 'date_signing';
    public const DATE_SIGNING_LONG = 'date_signing_long';

    /** Na ordem em que a tela do modelo oferece. */
    public const LABELS = [
        self::TEXT => 'Texto',
        self::TEXTAREA => 'Texto longo (várias linhas)',
        self::NUMBER => 'Número',
        self::MONEY => 'Valor em reais (R$)',
        self::CPF => 'CPF',
        self::CNPJ => 'CNPJ',
        self::EMAIL => 'E-mail',
        self::PHONE => 'Telefone',
        self::CEP => 'CEP',
        self::DATE => 'Data abreviada (03/10/2026)',
        self::DATE_LONG => 'Data por extenso (3 de outubro de 2026)',
        self::TIME => 'Hora (14:30)',
        self::RADIO => 'Opção única (escolhe uma)',
        self::CHECKBOX => 'Múltipla escolha (escolhe várias)',
        self::YES_NO => 'Sim ou não',
        self::DATE_SIGNING => 'Data da assinatura — automática, abreviada',
        self::DATE_SIGNING_LONG => 'Data da assinatura — automática, por extenso',
    ];

    /** Tipos em que quem escreve o modelo lista as opções. */
    public const WITH_OPTIONS = [self::RADIO, self::CHECKBOX];

    /**
     * Tipos que ninguém preenche: o servidor resolve no ato da assinatura.
     * Com a hora dele — o relógio do tablet não entra em nada neste módulo.
     */
    public const AUTOMATIC = [self::DATE_SIGNING, self::DATE_SIGNING_LONG];

    private const MONTHS = [
        1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
        'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
    ];

    public static function exists(?string $type): bool
    {
        return $type !== null && isset(self::LABELS[$type]);
    }

    public static function hasOptions(string $type): bool
    {
        return in_array($type, self::WITH_OPTIONS, true);
    }

    public static function isAutomatic(string $type): bool
    {
        return in_array($type, self::AUTOMATIC, true);
    }

    /**
     * O tipo provável de um campo pelo nome que a pessoa deu a ele no Word.
     *
     * É só um ponto de partida — a tela do modelo mostra o tipo e deixa
     * trocar. Conservador de propósito: na dúvida é texto, que aceita tudo.
     */
    public static function guess(string $label): string
    {
        $nome = mb_strtolower($label);

        return match (true) {
            (bool) preg_match('/\bdata d[ae] assinatura\b/u', $nome) => self::DATE_SIGNING,
            (bool) preg_match('/\bcpf\b/u', $nome) => self::CPF,
            (bool) preg_match('/\bcnpj\b/u', $nome) => self::CNPJ,
            (bool) preg_match('/\bcep\b/u', $nome) => self::CEP,
            (bool) preg_match('/\be-?mail\b/u', $nome) => self::EMAIL,
            (bool) preg_match('/\b(telefone|celular|whatsapp)\b/u', $nome) => self::PHONE,
            (bool) preg_match('/\b(valor|preço|preco)\b|r\$/u', $nome) => self::MONEY,
            (bool) preg_match('/^data\b/u', $nome) => self::DATE,
            default => self::TEXT,
        };
    }

    public static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === [] || (is_string($value) && trim($value) === '');
    }

    /**
     * Confere o que chegou do formulário e devolve a forma canônica.
     *
     * Devolve `[valor, erro]`. Vazio não é erro — é `[null, null]`: quem
     * decide se o campo podia ficar em branco é quem chama, porque o rascunho
     * do atendente pode e a resposta do tablet não.
     *
     * @param  array{type?: string, options?: array<int, string>}  $field
     * @return array{0: mixed, 1: ?string}
     */
    public static function parse(array $field, mixed $raw): array
    {
        $type = $field['type'] ?? self::TEXT;

        if ($type === self::CHECKBOX) {
            $marcadas = array_filter(
                array_map(fn($item) => is_scalar($item) ? trim((string) $item) : '', (array) $raw),
                fn(string $item) => $item !== '',
            );

            // Na ordem do MODELO, e não na ordem em que a pessoa tocou: o
            // documento lista as opções sempre do mesmo jeito.
            $validas = array_values(array_filter(
                $field['options'] ?? [],
                fn(string $opcao) => in_array($opcao, $marcadas, true),
            ));

            if (count($validas) !== count(array_unique($marcadas))) {
                return [null, 'Escolha entre as opções apresentadas.'];
            }

            return [$validas === [] ? null : $validas, null];
        }

        if (!is_scalar($raw) && $raw !== null) {
            return [null, 'Valor inválido.'];
        }

        $texto = trim((string) $raw);

        if ($texto === '') {
            return [null, null];
        }

        return match ($type) {
            self::TEXTAREA => mb_strlen($texto) > 5000
                ? [null, 'O texto passa de 5.000 caracteres.']
                : [$texto, null],

            self::NUMBER => self::parseNumber($texto),
            self::MONEY => self::parseMoney($texto),

            self::CPF => self::validCpf(Cpf::digits($texto))
                ? [Cpf::digits($texto), null]
                : [null, 'CPF inválido. Confira os números.'],

            self::CNPJ => self::validCnpj(self::cnpjChars($texto))
                ? [self::cnpjChars($texto), null]
                : [null, 'CNPJ inválido. Confira os números.'],

            self::EMAIL => filter_var($texto, FILTER_VALIDATE_EMAIL) && mb_strlen($texto) <= 150
                ? [mb_strtolower($texto), null]
                : [null, 'E-mail inválido.'],

            self::PHONE => self::parsePhone($texto),

            self::CEP => strlen(Cpf::digits($texto)) === 8
                ? [Cpf::digits($texto), null]
                : [null, 'CEP inválido: são 8 números.'],

            self::DATE, self::DATE_LONG, self::DATE_SIGNING, self::DATE_SIGNING_LONG => self::parseDate($texto),

            self::TIME => preg_match('/^([01]?\d|2[0-3])[:h]([0-5]\d)$/', $texto, $m)
                ? [str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2], null]
                : [null, 'Hora inválida. Use o formato 14:30.'],

            self::RADIO => in_array($texto, $field['options'] ?? [], true)
                ? [$texto, null]
                : [null, 'Escolha uma das opções apresentadas.'],

            self::YES_NO => in_array($texto, ['sim', 'nao'], true)
                ? [$texto, null]
                : [null, 'Responda sim ou não.'],

            default => mb_strlen($texto) > 500
                ? [null, 'O texto passa de 500 caracteres.']
                : [$texto, null],
        };
    }

    /**
     * O valor como sai ESCRITO no documento. Texto puro — quem escapa para
     * HTML é o SignatureDocumentRenderer.
     *
     * @param  array{type?: string}  $field
     */
    public static function format(array $field, mixed $value): string
    {
        if (self::isEmpty($value)) {
            return '';
        }

        $type = $field['type'] ?? self::TEXT;

        if (is_array($value)) {
            return self::joinList(array_map('strval', $value));
        }

        $valor = (string) $value;

        return match ($type) {
            self::NUMBER => str_replace('.', ',', $valor),
            self::MONEY => is_numeric($valor) ? 'R$ ' . number_format((float) $valor, 2, ',', '.') : $valor,
            self::CPF => Cpf::format($valor),
            self::CNPJ => strlen($valor) === 14
                ? substr($valor, 0, 2) . '.' . substr($valor, 2, 3) . '.' . substr($valor, 5, 3)
                    . '/' . substr($valor, 8, 4) . '-' . substr($valor, 12, 2)
                : $valor,
            self::PHONE => self::formatPhone($valor),
            self::CEP => strlen($valor) === 8 ? substr($valor, 0, 5) . '-' . substr($valor, 5) : $valor,
            self::DATE, self::DATE_SIGNING => self::formatDate($valor, false),
            self::DATE_LONG, self::DATE_SIGNING_LONG => self::formatDate($valor, true),
            self::YES_NO => $valor === 'sim' ? 'Sim' : ($valor === 'nao' ? 'Não' : $valor),
            default => $valor,
        };
    }

    /**
     * O valor como volta para um campo de formulário: o que a pessoa
     * digitaria. Data e hora ficam na forma canônica, que é a que
     * `<input type="date">` espera.
     *
     * @param  array{type?: string}  $field
     * @return string|array<int, string>
     */
    public static function inputValue(array $field, mixed $value): string|array
    {
        $type = $field['type'] ?? self::TEXT;

        if ($type === self::CHECKBOX) {
            return array_values(array_map('strval', (array) ($value ?? [])));
        }

        if (self::isEmpty($value) || is_array($value)) {
            return '';
        }

        return match ($type) {
            self::MONEY => is_numeric($value) ? number_format((float) $value, 2, ',', '.') : (string) $value,
            self::DATE, self::DATE_LONG, self::TIME, self::RADIO, self::YES_NO,
            self::TEXT, self::TEXTAREA, self::EMAIL => (string) $value,
            default => self::format($field, $value),
        };
    }

    /** O valor de um campo automático, com a data do servidor. */
    public static function automaticValue(string $type, CarbonInterface $now): ?string
    {
        return self::isAutomatic($type) ? $now->format('Y-m-d') : null;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private static function parseNumber(string $texto): array
    {
        $numero = self::decimal($texto);

        return $numero === null
            ? [null, 'Digite apenas números.']
            : [$numero, null];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private static function parseMoney(string $texto): array
    {
        $numero = self::decimal(trim(str_ireplace('R$', '', $texto)));

        if ($numero === null || (float) $numero < 0) {
            return [null, 'Valor inválido. Exemplo: 1.500,00'];
        }

        return [number_format((float) $numero, 2, '.', ''), null];
    }

    /**
     * Número digitado à brasileira → decimal com ponto.
     *
     * Com vírgula, o ponto é milhar (`1.500,50`). Sem vírgula, `1.500` também
     * é milhar — ninguém no balcão escreve mil e quinhentos como um e meio.
     */
    private static function decimal(string $texto): ?string
    {
        $texto = str_replace(' ', '', $texto);

        if (str_contains($texto, ',')) {
            $texto = str_replace(',', '.', str_replace('.', '', $texto));
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $texto)) {
            $texto = str_replace('.', '', $texto);
        }

        return preg_match('/^-?\d{1,15}(\.\d{1,6})?$/', $texto) ? $texto : null;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private static function parsePhone(string $texto): array
    {
        $digitos = Cpf::digits($texto);

        // +55 na frente: o número é o mesmo.
        if (in_array(strlen($digitos), [12, 13], true) && str_starts_with($digitos, '55')) {
            $digitos = substr($digitos, 2);
        }

        return in_array(strlen($digitos), [10, 11], true)
            ? [$digitos, null]
            : [null, 'Telefone inválido. Informe o DDD e o número.'];
    }

    private static function formatPhone(string $digitos): string
    {
        return match (strlen($digitos)) {
            11 => '(' . substr($digitos, 0, 2) . ') ' . substr($digitos, 2, 5) . '-' . substr($digitos, 7),
            10 => '(' . substr($digitos, 0, 2) . ') ' . substr($digitos, 2, 4) . '-' . substr($digitos, 6),
            default => $digitos,
        };
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private static function parseDate(string $texto): array
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $texto, $m)) {
            [$ano, $mes, $dia] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $texto, $m)) {
            [$dia, $mes, $ano] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return [null, 'Data inválida. Use o formato dia/mês/ano.'];
        }

        if (!checkdate($mes, $dia, $ano) || $ano < 1900 || $ano > 2200) {
            return [null, 'Data inválida. Confira o dia, o mês e o ano.'];
        }

        return [sprintf('%04d-%02d-%02d', $ano, $mes, $dia), null];
    }

    private static function formatDate(string $iso, bool $porExtenso): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m)) {
            // Valor antigo, de antes de o campo ter tipo: sai como foi escrito.
            return $iso;
        }

        if (!$porExtenso) {
            return $m[3] . '/' . $m[2] . '/' . $m[1];
        }

        $dia = (int) $m[3];

        return ($dia === 1 ? '1º' : (string) $dia) . ' de ' . self::MONTHS[(int) $m[2]] . ' de ' . $m[1];
    }

    /**
     * @param  array<int, string>  $itens
     */
    private static function joinList(array $itens): string
    {
        if (count($itens) <= 1) {
            return (string) ($itens[0] ?? '');
        }

        $ultimo = array_pop($itens);

        return implode(', ', $itens) . ' e ' . $ultimo;
    }

    private static function validCpf(string $digitos): bool
    {
        if (strlen($digitos) !== 11 || preg_match('/^(\d)\1{10}$/', $digitos)) {
            return false;
        }

        for ($posicao = 9; $posicao < 11; $posicao++) {
            $soma = 0;

            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $digitos[$i] * (($posicao + 1) - $i);
            }

            if ((int) $digitos[$posicao] !== ((10 * $soma) % 11) % 10) {
                return false;
            }
        }

        return true;
    }

    /** Só letras e números, em maiúsculas — o CNPJ alfanumérico tem letras na raiz. */
    private static function cnpjChars(string $texto): string
    {
        return strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $texto));
    }

    /**
     * Dígitos verificadores do CNPJ, pela regra que vale também para o
     * alfanumérico: cada caractere vale o código ASCII menos 48, o que dá o
     * próprio número para os dígitos.
     */
    private static function validCnpj(string $cnpj): bool
    {
        if (!preg_match('/^[0-9A-Z]{12}\d{2}$/', $cnpj) || preg_match('/^(.)\1{13}$/', $cnpj)) {
            return false;
        }

        foreach ([12, 13] as $posicao) {
            $soma = 0;
            $peso = 2;

            for ($i = $posicao - 1; $i >= 0; $i--) {
                $soma += (ord($cnpj[$i]) - 48) * $peso;
                $peso = $peso === 9 ? 2 : $peso + 1;
            }

            $resto = $soma % 11;

            if ((int) $cnpj[$posicao] !== ($resto < 2 ? 0 : 11 - $resto)) {
                return false;
            }
        }

        return true;
    }
}
