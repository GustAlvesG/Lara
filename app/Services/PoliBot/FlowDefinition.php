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
        }

        return $erros;
    }
}
