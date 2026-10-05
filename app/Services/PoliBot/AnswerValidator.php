<?php

namespace App\Services\PoliBot;

use App\Services\Poli\ParsedPoliMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Confere a resposta do contato contra o que o passo espera.
 *
 * Toque em lista e resposta digitada passam pelo mesmo caminho: o toque chega
 * como texto ("Título Descrição", já sem a quebra de linha), então casar pelo
 * título normalizado cobre os dois — mais o número da opção, para os menus
 * numerados.
 */
class AnswerValidator
{
    private const PLATE = '/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/';

    private const SIM = ['sim', 's', 'yes', 'y', '1', 'claro', 'isso', 'ok', 'pode', 'confirmo'];
    private const NAO = ['nao', 'n', 'no', '2', 'negativo'];

    /**
     * @param array<string, mixed> $step
     */
    public function validate(array $step, ParsedPoliMessage $message): Answer
    {
        $expect = $step['expect'] ?? ['type' => 'any'];
        $tipo = $expect['type'] ?? 'any';

        if ($tipo === 'image') {
            return $message->type === ParsedPoliMessage::TYPE_IMAGE && filled($message->mediaUrl)
                ? Answer::ok($message->mediaUrl)
                : Answer::invalid('image');
        }

        if ($message->type !== ParsedPoliMessage::TYPE_TEXT || blank($message->text)) {
            return $tipo === 'any' && $message->type === ParsedPoliMessage::TYPE_IMAGE
                ? Answer::ok($message->mediaUrl)
                : Answer::invalid('media');
        }

        $texto = trim((string) $message->text);

        return match ($tipo) {
            'option' => $this->option($step['options'] ?? [], $texto),
            'plate' => $this->plate($texto),
            'date' => $this->date($texto, $expect),
            'number' => $this->number($texto, $expect),
            'yes_no' => $this->yesNo($texto),
            'text' => $this->text($texto, $expect),
            default => Answer::ok($texto),
        };
    }

    /**
     * Resolve a opção escolhida. Aceita, nesta ordem: o número da opção, o
     * rótulo (ou rótulo + descrição, que é como o toque em lista chega), um
     * apelido, e por fim um texto que COMEÇA pelo rótulo — o mais longo vence,
     * para "Campo Bar" não cair em "Campo".
     *
     * @param array<int, array<string, mixed>> $options
     */
    public function option(array $options, string $texto): Answer
    {
        $alvo = self::normalize($texto);

        if ($alvo === '') {
            return Answer::invalid('option');
        }

        if (ctype_digit($alvo)) {
            $opcao = $options[(int) $alvo - 1] ?? null;

            return $opcao ? Answer::ok($opcao['value'] ?? $opcao['label'], $opcao) : Answer::invalid('option');
        }

        foreach ($options as $opcao) {
            $rotulo = self::normalize($opcao['label'] ?? '');
            $comDescricao = self::normalize(($opcao['label'] ?? '') . ' ' . ($opcao['description'] ?? ''));
            $apelidos = array_map(fn ($a) => self::normalize((string) $a), (array) ($opcao['aliases'] ?? []));

            if ($rotulo !== '' && ($alvo === $rotulo || $alvo === $comDescricao || in_array($alvo, $apelidos, true))) {
                return Answer::ok($opcao['value'] ?? $opcao['label'], $opcao);
            }
        }

        $melhor = null;
        foreach ($options as $opcao) {
            $rotulo = self::normalize($opcao['label'] ?? '');

            if ($rotulo !== '' && str_starts_with($alvo, $rotulo . ' ')
                && ($melhor === null || strlen($rotulo) > strlen(self::normalize($melhor['label'])))) {
                $melhor = $opcao;
            }
        }

        return $melhor ? Answer::ok($melhor['value'] ?? $melhor['label'], $melhor) : Answer::invalid('option');
    }

    public function plate(string $texto): Answer
    {
        $placa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $texto) ?? '');

        return preg_match(self::PLATE, $placa) ? Answer::ok($placa) : Answer::invalid('plate');
    }

    /**
     * Dia/mês/ano, com barra, hífen, ponto ou só dígitos (25091980). Ano de
     * dois dígitos vira 19xx ou 20xx pelo que estiver mais perto de hoje no
     * passado. Devolve Y-m-d.
     *
     * @param array<string, mixed> $expect `past_only` / `future_only`
     */
    public function date(string $texto, array $expect = []): Answer
    {
        $limpo = trim($texto);

        if (preg_match('/^(\d{1,2})[\/.\-\s](\d{1,2})[\/.\-\s](\d{2}|\d{4})$/', $limpo, $m)) {
            [, $d, $mes, $a] = $m;
        } elseif (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $limpo, $m)) {
            [, $d, $mes, $a] = $m;
        } else {
            return Answer::invalid('date');
        }

        $ano = (int) $a;
        if (strlen($a) === 2) {
            $ano += ($ano > (int) now()->format('y')) ? 1900 : 2000;
        }

        if (!checkdate((int) $mes, (int) $d, $ano)) {
            return Answer::invalid('date');
        }

        $data = sprintf('%04d-%02d-%02d', $ano, (int) $mes, (int) $d);
        $hoje = now()->format('Y-m-d');

        if (($expect['past_only'] ?? false) && $data > $hoje) {
            return Answer::invalid('date');
        }
        if (($expect['future_only'] ?? false) && $data < $hoje) {
            return Answer::invalid('date');
        }

        return Answer::ok($data);
    }

    /** @param array<string, mixed> $expect `min` / `max` */
    public function number(string $texto, array $expect = []): Answer
    {
        $limpo = str_replace([' ', '.'], '', trim($texto));

        if (!preg_match('/^-?\d+$/', $limpo)) {
            return Answer::invalid('number');
        }

        $n = (int) $limpo;

        if ((isset($expect['min']) && $n < $expect['min']) || (isset($expect['max']) && $n > $expect['max'])) {
            return Answer::invalid('number');
        }

        return Answer::ok($n);
    }

    public function yesNo(string $texto): Answer
    {
        $alvo = self::normalize($texto);

        if (in_array($alvo, self::SIM, true)) {
            return Answer::ok(true);
        }

        return in_array($alvo, self::NAO, true) ? Answer::ok(false) : Answer::invalid('yes_no');
    }

    /** @param array<string, mixed> $expect `min` / `max` / `pattern` */
    public function text(string $texto, array $expect = []): Answer
    {
        $tamanho = mb_strlen($texto);

        if ($tamanho < (int) ($expect['min'] ?? 1) || $tamanho > (int) ($expect['max'] ?? 500)) {
            return Answer::invalid('text');
        }

        // O padrão vem do fluxo sem delimitadores. `~` é o delimitador porque
        // ninguém o usa num padrão de resposta — ao contrário da barra, que
        // aparece em matrícula e data. Padrão que não compila recusa, e o log
        // aponta o fluxo a corrigir.
        $padrao = $expect['pattern'] ?? null;
        if (is_string($padrao) && $padrao !== '') {
            $casou = @preg_match('~' . str_replace('~', '\~', $padrao) . '~u', $texto);

            if ($casou === false) {
                Log::warning('PoliBot: padrão de resposta inválido na definição do fluxo', ['pattern' => $padrao]);
            }

            if ($casou !== 1) {
                return Answer::invalid('text');
            }
        }

        return Answer::ok($texto);
    }

    /** Caixa baixa, sem acento, sem pontuação nas pontas, espaços colapsados. */
    public static function normalize(?string $valor): string
    {
        $valor = Str::ascii((string) $valor);
        $valor = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $valor) ?? '';
        $valor = preg_replace('/\s+/', ' ', $valor) ?? '';

        return trim(Str::lower($valor), " \t.,;:!?*_-");
    }
}
