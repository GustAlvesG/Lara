# Lara — Assistente de IA (chat interno)

## O que é

Um chat de pergunta e resposta dentro do portal, ligado a um agente de IA ("Lara") que roda
numa **VM separada** (Ollama + base de conhecimento do estatuto do clube). O funcionário
pergunta em linguagem natural e recebe a resposta na própria tela.

O portal não hospeda o modelo: ele só faz uma chamada HTTP para a VM da IA e mostra o que
voltou.

## Para quem

Funcionários com a permissão **`use lara chat`**. A permissão é própria (e não "todo mundo
logado") para permitir liberar o chat aos poucos.

## Pré-requisitos (`.env`)

| Variável | Padrão | Para que serve |
|----------|--------|----------------|
| `LARA_ENABLED` | `false` | Liga/desliga o chat sem deploy. |
| `LARA_BASE_URL` | — | `http://<ip-da-vm>:3000`. **Vazio = chat desativado.** |
| `LARA_TIMEOUT` | `30` | Segundos de espera pela resposta. |
| `LARA_RESET_TIMEOUT` | `10` | Segundos de espera do "Nova conversa". |
| `LARA_MAX_INPUT_CHARS` | `1000` | Tamanho máximo da pergunta. |
| `LARA_FALLBACK_MESSAGE` | "Vou te transferir…" | Texto exibido quando a IA não responde. |

O endereço da VM **não vai para o repositório** — a segurança do endpoint é de rede (só o IP
do portal alcança a porta 3000) e ele não exige token.

## Fluxo passo a passo

### 1. Abrir o chat — `GET /lara`
Carrega a conversa vigente do funcionário (guardada em `lara_messages`, identificada por um
`conversation_uuid` na sessão). Se a integração estiver desligada, a tela avisa e o campo de
envio fica bloqueado.

### 2. Perguntar — `POST /lara/perguntar`
Grava a pergunta, chama `POST {LARA_BASE_URL}/perguntar` com `{usuario_id, mensagem}`, grava a
resposta e devolve o texto para a tela. O `usuario_id` enviado é `portal_{id do usuário}` — é
a chave do histórico em memória do lado da IA.

### 3. Nova conversa — `POST /lara/reiniciar`
Chama `POST {LARA_BASE_URL}/reiniciar` (limpa o histórico do lado da IA) e sorteia um
`conversation_uuid` novo na sessão. O histórico antigo continua no banco, só sai da tela.

## Entidades

| Entidade | Campos principais |
|----------|-------------------|
| LaraMessage | `user_id`, `conversation_uuid`, `role` (`user`/`assistant`), `conteudo`, `status` (`ok`/`erro`/`desativado`), `latencia_ms`, `erro`, `created_at` |

## Regras de negócio

- **A chamada é síncrona.** O modelo roda em CPU e uma resposta pode levar até ~30s. Por isso
  o controller chama `set_time_limit()`: o `max_execution_time` padrão do PHP-FPM (30s) mataria
  a requisição antes da hora e o funcionário veria erro 500 em vez do texto de fallback.
- **Uma pergunta por vez, por funcionário** (`Cache::lock`). Cada pergunta segura um worker do
  PHP-FPM por até 30s; sem a trava, alguém apertando "enviar" várias vezes derruba o portal.
- **A IA nunca "não responde".** Falha de rede, HTTP de erro ou corpo inesperado viram a frase
  de fallback com `status = erro`. O status existe porque a própria IA usa a mesma frase quando
  não sabe responder — sem ele, queda de rede e transferência legítima ficariam indistinguíveis.
- **Não há retry.** Repetir depois de um timeout dobraria a carga com a VM já saturada.
- **O histórico tem dois donos.** A IA guarda contexto em memória (some se o processo dela
  reiniciar); o portal guarda o texto no banco (sobrevive ao F5). Depois de um restart da IA a
  tela ainda mostra a conversa antiga, mas a Lara não lembra mais dela.
- **A resposta é exibida como texto puro** (`x-text`, nunca `x-html`): o conteúdo vem de um
  modelo de linguagem e não pode virar HTML executável na tela.
- Retenção: `php artisan app:prune-lara-messages --days=90`.

## Mensagens/erros

| Situação | O que o funcionário vê |
|----------|------------------------|
| IA fora do ar / erro interno | A frase de `LARA_FALLBACK_MESSAGE`, como se fosse uma resposta |
| Pergunta anterior ainda em curso | "Sua pergunta anterior ainda está sendo respondida…" (HTTP 429) |
| Pergunta vazia, curta ou longa demais | Mensagem de validação (HTTP 422) |
| `LARA_ENABLED=false` ou sem `LARA_BASE_URL` | Aviso no topo e campo de envio bloqueado |
| Demora acima de 45s no navegador | "A Lara demorou demais para responder…" |

## Como testar sem a tela

```bash
curl -X POST http://<ip-da-vm>:3000/perguntar \
  -H "Content-Type: application/json" \
  -d '{"usuario_id": "func_teste", "mensagem": "qual o horário da academia?"}'
```

Rodar **do servidor web** (é ele que faz a chamada), não do servidor de banco.

## Rollout

1. Deploy com `LARA_ENABLED=false`.
2. `php artisan migrate` e `php artisan db:seed --class=RolesAndPermissionsSeeder`
   (idempotente — só acrescenta a permissão nova).
3. Conceder `use lara chat` a 2–3 pessoas.
4. Preencher `LARA_BASE_URL`, ligar `LARA_ENABLED=true`, `php artisan config:clear`.
5. Acompanhar `lara_messages` (`status` e `latencia_ms`) antes de abrir para todos.

## Referência técnica

- Controller: `LaraChatController` · Request: `AskLaraRequest`
- Service: `App\Services\Lara\LaraClient` (+ DTO `LaraAnswer`)
- Model: `LaraMessage` · Command: `app:prune-lara-messages`
- View: `resources/views/lara/index.blade.php`
- Testes: `tests/Unit/LaraClientTest.php`, `tests/Feature/LaraMessageHistoryTest.php`
