<?php

namespace App\Services\PoliBot;

/**
 * Leitura e validação da definição de um fluxo (bot_flows.definition).
 *
 * Formato:
 *
 *   {
 *     "start": "menu",                    // passo inicial
 *     "triggers": {"any": true}           // abre em qualquer 1ª mensagem
 *              | {"texts": ["carro"]},    // ou só quando o texto casa
 *     "timeout_minutes": 15,              // inatividade que encerra a conversa
 *     "max_attempts": 3,                  // respostas inválidas até o transbordo
 *     "on_max_attempts": {"type": "handoff", "team_uuid": "…"},
 *     "steps": {
 *       "<chave>": {
 *         "say":     {"type": "text", "text": "Olá, {nome}!"}
 *                  | {"type": "menu", "text": "Escolha:"}         // texto + opções numeradas
 *                  | {"type": "template", "template_uuid": "…", "params": ["{nome}"]},
 *         "expect":  {"type": "text", "min": 2, "max": 80, "pattern": "…"}
 *                  | {"type": "option"} | {"type": "plate"} | {"type": "date"}
 *                  | {"type": "number", "min": 1, "max": 10} | {"type": "yes_no"}
 *                  | {"type": "image"} | {"type": "any"},
 *         "options": [{"label": "Financeiro", "description": "…", "aliases": ["fin"], "next": "fin", "value": "…"}],
 *         "save_as": "placa",
 *         "invalid": "Mensagem de correção",
 *         "action":  {"type": "handoff", "team_uuid": "…"} | {"type": "close"}
 *                  | {"type": "uber_request"} | {"type": "goto_flow", "flow": "slug"},
 *         "next": "<chave>"
 *       }
 *     }
 *   }
 *
 * Passo sem `expect` não espera resposta: diz o que tem a dizer, executa a
 * ação e segue para `next` (ou termina a conversa, se não houver).
 *
 * Horário de atendimento (opcional): fora dele, a conversa começa por
 * `out_of_hours` em vez de `start`. Dias pela ISO (1 = segunda … 7 =
 * domingo); dia sem intervalo é fechado o dia todo. Feriados usam o
 * intervalo `holiday`.
 *
 *   "hours": {
 *     "enabled": true,
 *     "days": {"1": ["07:00", "19:50"], …, "6": ["07:00", "18:00"], "7": ["07:00", "18:00"]},
 *     "holidays": ["2026-12-25"],
 *     "holiday": ["07:00", "18:00"],
 *     "out_of_hours": "fora_do_horario"
 *   }
 */
class FlowDefinition
{
    public const EXPECT_TYPES = ['text', 'option', 'plate', 'date', 'number', 'yes_no', 'image', 'any'];
    public const SAY_TYPES = ['text', 'menu', 'template'];
    public const ACTION_TYPES = ['handoff', 'close', 'uber_request', 'goto_flow'];

    public function __construct(
        public readonly string $slug,
        private readonly array $definition,
    ) {}

    public function start(): ?string
    {
        return $this->definition['start'] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function step(?string $key): ?array
    {
        if ($key === null) {
            return null;
        }

        $step = $this->definition['steps'][$key] ?? null;

        return is_array($step) ? $step : null;
    }

    public function opensOnAnyMessage(): bool
    {
        return (bool) ($this->definition['triggers']['any'] ?? false);
    }

    /** @return string[] */
    public function triggerTexts(): array
    {
        return array_values(array_filter(
            (array) ($this->definition['triggers']['texts'] ?? []),
            fn ($t) => is_string($t) && trim($t) !== '',
        ));
    }

    public function timeoutMinutes(): int
    {
        return (int) ($this->definition['timeout_minutes'] ?? config('poli.bot.default_timeout_minutes', 15));
    }

    public function maxAttempts(array $step): int
    {
        return (int) ($step['max_attempts'] ?? $this->definition['max_attempts'] ?? config('poli.bot.default_max_attempts', 3));
    }

    /** @return array<string, mixed> */
    public function onMaxAttempts(): array
    {
        $acao = $this->definition['on_max_attempts'] ?? null;

        return is_array($acao) ? $acao : ['type' => 'handoff', 'team_uuid' => config('poli.bot.fallback_team_uuid')];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->definition;
    }

    /* ---------------------------------------------------------------------
     | Horário de atendimento
     |---------------------------------------------------------------------*/

    public function hasHours(): bool
    {
        return (bool) ($this->definition['hours']['enabled'] ?? false);
    }

    /**
     * O passo por onde a conversa começa agora: `start`, ou `out_of_hours`
     * se o fluxo tem horário e está fechado.
     */
    public function entryStep(?\DateTimeInterface $agora = null): ?string
    {
        if (!$this->hasHours() || $this->isOpenAt($agora ?? now())) {
            return $this->start();
        }

        $fora = $this->definition['hours']['out_of_hours'] ?? null;

        return is_string($fora) && isset($this->definition['steps'][$fora]) ? $fora : $this->start();
    }

    public function isOpenAt(\DateTimeInterface $momento): bool
    {
        if (!$this->hasHours()) {
            return true;
        }

        $horas = $this->definition['hours'];
        $quando = \Illuminate\Support\Carbon::instance($momento)->setTimezone(config('app.timezone'));

        $feriado = in_array($quando->format('Y-m-d'), (array) ($horas['holidays'] ?? []), true);
        $intervalo = $feriado
            ? ($horas['holiday'] ?? null)
            : ($horas['days'][(string) $quando->isoWeekday()] ?? $horas['days'][$quando->isoWeekday()] ?? null);

        if (!is_array($intervalo) || count($intervalo) !== 2) {
            return false;
        }

        $agora = $quando->format('H:i');

        return $agora >= $intervalo[0] && $agora < $intervalo[1];
    }

    /**
     * Tira da definição o que a tela manda vazio (textos em branco, listas
     * sem item), para o JSON gravado conter só o que tem significado.
     *
     * @param array<string, mixed> $definicao
     * @return array<string, mixed>
     */
    public static function clean(array $definicao): array
    {
        $limpar = function ($valor) use (&$limpar) {
            if (is_array($valor)) {
                $lista = array_is_list($valor);
                $saida = [];

                foreach ($valor as $k => $v) {
                    $v = $limpar($v);

                    if ($v === null || $v === '' || $v === []) {
                        continue;
                    }

                    $saida[$k] = $v;
                }

                return $lista ? array_values($saida) : $saida;
            }

            return is_string($valor) ? trim($valor) : $valor;
        };

        $limpa = $limpar($definicao);

        // Estes precisam sobreviver mesmo "vazios": `any: false` é escolha,
        // e o intervalo de um dia fechado é justamente a ausência dele.
        $limpa['triggers'] = [
            'any' => (bool) ($definicao['triggers']['any'] ?? false),
            'texts' => array_values(array_filter(array_map('trim', (array) ($definicao['triggers']['texts'] ?? [])))),
            'only_goto' => (bool) ($definicao['triggers']['only_goto'] ?? false),
        ];

        return $limpa;
    }

    /**
     * Problemas que impedem o fluxo de rodar. Vazio = válido.
     *
     * @return string[]
     */
    public function errors(): array
    {
        $erros = [];
        $steps = $this->definition['steps'] ?? null;

        if (!is_array($steps) || $steps === []) {
            return ['O fluxo não tem passos.'];
        }

        $start = $this->start();
        if ($start === null || !isset($steps[$start])) {
            $erros[] = "Passo inicial \"{$start}\" não existe.";
        }

        foreach ($steps as $key => $step) {
            $onde = "Passo \"{$key}\"";

            if (!is_array($step)) {
                $erros[] = "{$onde}: definição inválida.";
                continue;
            }

            $say = $step['say'] ?? null;
            if ($say !== null) {
                $tipo = $say['type'] ?? null;

                if (!in_array($tipo, self::SAY_TYPES, true)) {
                    $erros[] = "{$onde}: tipo de mensagem \"{$tipo}\" desconhecido.";
                } elseif ($tipo === 'template' && blank($say['template_uuid'] ?? null)) {
                    $erros[] = "{$onde}: template sem template_uuid.";
                } elseif ($tipo !== 'template' && blank($say['text'] ?? null)) {
                    $erros[] = "{$onde}: mensagem sem texto.";
                }
            }

            $expect = $step['expect']['type'] ?? null;
            if (isset($step['expect']) && !in_array($expect, self::EXPECT_TYPES, true)) {
                $erros[] = "{$onde}: tipo de resposta \"{$expect}\" desconhecido.";
            }

            $opcoes = $step['options'] ?? [];
            $pedeOpcao = $expect === 'option' || in_array($say['type'] ?? null, ['menu'], true);
            if ($pedeOpcao && (!is_array($opcoes) || $opcoes === [])) {
                $erros[] = "{$onde}: menu sem opções.";
            }

            foreach (is_array($opcoes) ? $opcoes : [] as $i => $opcao) {
                if (blank($opcao['label'] ?? null)) {
                    $erros[] = "{$onde}: opção " . ($i + 1) . ' sem rótulo.';
                }
                if (isset($opcao['next']) && !isset($steps[$opcao['next']])) {
                    $erros[] = "{$onde}: opção \"" . ($opcao['label'] ?? $i) . "\" leva ao passo inexistente \"{$opcao['next']}\".";
                }
            }

            if (isset($step['next']) && !isset($steps[$step['next']])) {
                $erros[] = "{$onde}: próximo passo \"{$step['next']}\" não existe.";
            }

            $acao = $step['action']['type'] ?? null;
            if (isset($step['action']) && !in_array($acao, self::ACTION_TYPES, true)) {
                $erros[] = "{$onde}: ação \"{$acao}\" desconhecida.";
            }
            if ($acao === 'goto_flow' && blank($step['action']['flow'] ?? null)) {
                $erros[] = "{$onde}: goto_flow sem o fluxo de destino.";
            }

            if ($say === null && !isset($step['expect']) && !isset($step['action']) && !isset($step['next'])) {
                $erros[] = "{$onde}: passo vazio.";
            }

            $padrao = $step['expect']['pattern'] ?? null;
            if (is_string($padrao) && $padrao !== '' && @preg_match('~' . str_replace('~', '\~', $padrao) . '~u', '') === false) {
                $erros[] = "{$onde}: o padrão de resposta não é uma expressão regular válida.";
            }

            if (isset($step['save_as']) && !preg_match('/^[a-z0-9_]+$/', (string) $step['save_as'])) {
                $erros[] = "{$onde}: \"guardar como\" aceita só letras minúsculas, números e _.";
            }
        }

        $gatilhoTexto = $this->triggerTexts() !== [];
        if (!$this->opensOnAnyMessage() && !$gatilhoTexto && !$this->reachedOnlyByGoto()) {
            $erros[] = 'O fluxo não tem gatilho: marque "qualquer primeira mensagem" ou informe palavras-chave.';
        }

        if ($this->hasHours()) {
            $horas = $this->definition['hours'];
            $fora = $horas['out_of_hours'] ?? null;

            if (!is_string($fora) || !isset($steps[$fora])) {
                $erros[] = 'Horário de atendimento ligado, mas o passo "fora do horário" não existe.';
            }

            $intervalos = (array) ($horas['days'] ?? []);
            if (isset($horas['holiday'])) {
                $intervalos['feriado'] = $horas['holiday'];
            }

            foreach ($intervalos as $dia => $intervalo) {
                if ($intervalo === null) {
                    continue;
                }

                $ok = is_array($intervalo) && count($intervalo) === 2
                    && preg_match('/^\d{2}:\d{2}$/', (string) $intervalo[0])
                    && preg_match('/^\d{2}:\d{2}$/', (string) $intervalo[1])
                    && $intervalo[0] < $intervalo[1];

                if (!$ok) {
                    $erros[] = "Horário de atendimento: intervalo inválido em \"{$dia}\" (use HH:MM, início antes do fim).";
                }
            }
        }

        return $erros;
    }

    /**
     * Fluxo sem gatilho próprio, feito para ser chamado por outro (goto_flow).
     * Marcado explicitamente para não confundir com esquecimento.
     */
    public function reachedOnlyByGoto(): bool
    {
        return (bool) ($this->definition['triggers']['only_goto'] ?? false);
    }

    /** @return string[] slugs dos fluxos para onde este manda (goto_flow) */
    public function gotoTargets(): array
    {
        $alvos = [];

        foreach ((array) ($this->definition['steps'] ?? []) as $step) {
            if (($step['action']['type'] ?? null) === 'goto_flow' && filled($step['action']['flow'] ?? null)) {
                $alvos[] = $step['action']['flow'];
            }
        }

        $maximo = $this->definition['on_max_attempts'] ?? [];
        if (($maximo['type'] ?? null) === 'goto_flow' && filled($maximo['flow'] ?? null)) {
            $alvos[] = $maximo['flow'];
        }

        return array_values(array_unique($alvos));
    }
}
