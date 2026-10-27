# Integração com a API do Claude (Anthropic)

Desde 27/10/2026, O Lara chama a API do Claude, a IA da Anthropic, pelo SDK oficial em PHP
(`anthropic-ai/sdk`, no `composer.json`). O primeiro uso é a **revisão automática de contratos**
pela Gerência (ver [contratos.md](contratos.md#revisão-automática-pelo-claude)).

Não tem relação com a "Lara" do chat interno ([lara-ia.md](lara-ia.md)), que roda numa VM própria com Ollama.

## Peças

| Peça | Papel |
|------|-------|
| `config/claude.php` | Chave, tempo limite, tentativas e, por uso, o modelo, o limite de tokens e o esforço (`effort`). |
| `App\Services\Claude\ClaudeClient` | A única porta para a API. `isConfigured()` diz se há chave. `structured($model, $system, $prompt, $schema, $maxTokens, $effort)` faz uma pergunta e devolve a resposta em JSON já no formato do esquema (structured outputs, `output_config.format`), mais o modelo e os tokens usados. |
| `App\Exceptions\ClaudeException` | Erro já traduzido para a tela. O detalhe técnico vai para o log (`Falha na API do Claude.`). |

### Erros

| Situação | Mensagem na tela |
|----------|------------------|
| Sem chave | "A integração com o Claude não está configurada…" (as telas nem mostram o botão) |
| 401/403 | "A chave da API do Claude foi recusada. Avise a Informática." |
| 402 | "A conta da API do Claude está sem crédito…" |
| 429 | "O limite de uso da API do Claude foi atingido…" |
| 400 | "A API do Claude recusou o pedido…" |
| Rede ou tempo esgotado | "Não foi possível falar com o Claude agora…" |
| 5xx/529 | "O Claude está indisponível no momento…" |
| `stop_reason = refusal` | "O Claude não quis analisar este texto…" |
| `stop_reason = max_tokens` | "A resposta do Claude ficou longa demais e veio cortada…" |

O SDK repete sozinho os erros passageiros (429, 5xx, rede) até `CLAUDE_MAX_RETRIES` vezes.

## Configuração (`.env`)

| Variável | Padrão | Para que serve |
|----------|--------|----------------|
| `ANTHROPIC_API_KEY` | — | Chave da API (console.anthropic.com). **Vazia = recurso desligado.** Só no `.env` do servidor. |
| `CLAUDE_TIMEOUT` | `90` | Segundos por tentativa. |
| `CLAUDE_MAX_RETRIES` | `1` | Novas tentativas em erro passageiro. |
| `CLAUDE_CONTRACT_REVIEW_MODEL` | `claude-haiku-5-5` | Modelo da revisão de contratos (Claude Haiku 5.5, escolha do usuário). |
| `CLAUDE_CONTRACT_REVIEW_MAX_TOKENS` | `16000` | Limite da resposta (inclui o raciocínio do modelo). |
| `CLAUDE_CONTRACT_REVIEW_EFFORT` | `medium` | `low` / `medium` / `high`: quanto o modelo pensa antes de responder. Mais alto é mais lento e mais caro. |

O `anthropic-version` é o que o SDK manda (`2026-10-21` na versão 1.3). Depois de mudar o `.env` em
produção, rode `php artisan config:cache`; o deploy já faz isso.

## Custo

O Claude Haiku 5.5 custa US$ 0,10 por milhão de tokens de entrada e US$ 0,50 por milhão de saída, para
pedidos de até 100 mil tokens. Um contrato de 10 páginas fica na casa de 10 mil tokens de entrada, ou
seja, frações de centavo por revisão. Cada revisão guarda os tokens usados (`input_tokens`,
`output_tokens` em `signature_contract_ai_reviews`) para acompanhar o gasto.

## Privacidade

O texto enviado (no caso dos contratos: partes, CPF/CNPJ, endereços, valores) sai do Lara e vai para a
Anthropic. A tela avisa isso ao lado do botão. Não mande dados que não precisem ir: cada uso monta o próprio
texto, só com o necessário.

## Novo uso

1. Acrescente um bloco em `config/claude.php` (modelo, tokens, esforço) e as variáveis no `.env.example`.
2. Num serviço do módulo, injete `ClaudeClient`, monte o `system` (fixo) e o `prompt` (variável) e chame
   `structured()` com um JSON Schema (`additionalProperties: false`, todos os campos em `required`).
3. Trate `ClaudeException` mostrando a mensagem. Confira e limite o que voltou antes de gravar: o esquema
   garante o formato, não o conteúdo.
4. Esconda o recurso quando `isConfigured()` for falso.
5. Testes: nunca chamar a API de verdade. Use `new ClaudeClient($guzzleComMockHandler)`, que grava o pedido
   (ver `SignatureContractAiReviewTest`), ou troque `ClaudeClient` por um mock no container.
