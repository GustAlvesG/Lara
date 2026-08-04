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
| `LARA_TIMEOUT` | `60` | Segundos de espera pela resposta. |
| `LARA_RESET_TIMEOUT` | `10` | Segundos de espera do "Nova conversa". |
| `LARA_HEALTH_TIMEOUT` | `3` | Segundos de espera do health check. |
| `LARA_HEALTH_TTL` | `30` | Segundos de cache do health check. |
| `LARA_HISTORY_TTL_HOURS` | `24` | Espelha a expiração da memória da IA. |
| `LARA_MAX_INPUT_CHARS` | `1000` | Tamanho máximo da pergunta. |
| `LARA_FALLBACK_MESSAGE` | "Vou te transferir…" | Texto exibido quando a IA não responde. |

### A cadeia de timeouts

Os três limites são escalonados de propósito, do mais interno para o mais externo:

| Camada | Limite | O que faz ao estourar |
|--------|--------|-----------------------|
| Serviço da IA | 22s | Devolve o fallback dele com `transferir: true` |
| Portal (`LARA_TIMEOUT`) | 60s | Devolve o fallback com `status = erro` |
| Trava por usuário | `LARA_TIMEOUT + 10` = 70s | Libera a próxima pergunta |
| Navegador | `LARA_TIMEOUT + 10` = 70s | Mostra "A Lara demorou demais para responder" |
| PHP (`set_time_limit`) | `LARA_TIMEOUT + 30` = 90s | Erro 500 (não deve acontecer) |

**Só `LARA_TIMEOUT` se ajusta** — os outros quatro derivam dele no código.
Encurtá-lo para menos que o teto da IA faria o portal desistir de respostas que
estavam a caminho, e o modelo continuaria gerando texto que ninguém leria.

Latência medida contra a VM real (modelo aquecido, 2026-08-03): `/health` em
0,19s, perguntas em 4,2s e 8,9s. Os 60s são folga para o pior caso — se a média
observada em `lara_messages` subir para perto disso, o problema é capacidade da
VM, não configuração daqui.

Sobre o `set_time_limit`: em Linux o `max_execution_time` conta apenas tempo de
CPU, então a espera pela IA não entra na conta e ele não é o que segura a
requisição em produção. A chamada fica porque em Windows (ambiente de dev) o
tempo medido é real, e porque é barata.

> **Acima de ~50s, conferir o Apache.** O deploy roda em Apache
> (`deploy_hml.sh`). Se ele conversa com o PHP por proxy (php-fpm) ou fcgid, o
> timeout dele corta por fora e devolve **504 antes** de qualquer limite da
> aplicação — o funcionário veria erro de servidor em vez da frase de fallback.
>
> Descobrir qual é o caso, no servidor:
>
> ```bash
> apache2ctl -M | grep -E 'php|proxy_fcgi|fcgid'
> ```
>
> | Módulo | Diretiva | Padrão | Ação com `LARA_TIMEOUT=60` |
> |--------|----------|--------|-----------------------------|
> | `php_module` (mod_php) | — | — | Nada a fazer |
> | `proxy_fcgi_module` | `ProxyTimeout` (herda de `Timeout`) | 60s | Subir para 90s |
> | `fcgid_module` | `FcgidIOTimeout` | 40s | Subir para 90s |
>
> Conferir também `request_terminate_timeout` no pool do php-fpm (padrão `0`,
> desligado — mas algumas distribuições preenchem).

O endereço da VM **não vai para o repositório** — a segurança do endpoint é de rede (só o IP
do portal alcança a porta 3000) e ele não exige token.

## Fluxo passo a passo

### 1. Abrir o chat — `GET /lara`
Consulta `GET {LARA_BASE_URL}/health` (resultado em cache por 30s) e carrega a conversa vigente
do funcionário — guardada em `lara_messages`, identificada por um `conversation_uuid` na sessão.
O campo de envio fica bloqueado em dois casos, com mensagens diferentes: **desativada** (falta
`LARA_ENABLED`/`LARA_BASE_URL`, decisão nossa) e **fora do ar** (health check falhou, chamado
para a TI).

Se a última mensagem da conversa tiver mais de `LARA_HISTORY_TTL_HOURS`, a tela abre uma conversa
nova. É o espelho da expiração da memória do lado da IA: sem isso o funcionário veria o assunto
antigo na tela e a Lara não lembraria nada dele.

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
| LaraMessage | `user_id`, `conversation_uuid`, `role` (`user`/`assistant`), `conteudo`, `status`, `latencia_ms`, `erro`, `created_at` |

### Os quatro `status` (é por eles que se mede a qualidade)

| Status | Significa | De onde vem |
|--------|-----------|-------------|
| `ok` | Resposta de verdade — inclusive quando a Lara encaminha o assunto por decisão dela | `transferir: false` ou campo ausente |
| `fallback` | A IA quebrou: timeout dela, limite de 3 chamadas simultâneas ou erro do modelo | `transferir: true` |
| `erro` | Não chegamos a falar com a IA: rede, HTTP != 2xx, corpo inválido | Falha no portal |
| `desativado` | Nem tentamos: integração desligada | Configuração |

A distinção `ok` x `fallback` só existe porque a IA passou a mandar o campo `transferir`. Sem
ele, a frase "Vou te transferir para o setor responsável" seria idêntica nos dois casos e a
taxa de erro real ficaria invisível.

## Regras de negócio

- **A chamada é síncrona.** O modelo roda em CPU e uma resposta pode levar até ~30s. Por isso
  o controller chama `set_time_limit()`: o `max_execution_time` padrão do PHP-FPM (30s) mataria
  a requisição antes da hora e o funcionário veria erro 500 em vez do texto de fallback.
- **Uma pergunta por vez, por funcionário** (`Cache::lock`). Cada pergunta segura um worker do
  PHP-FPM por até 30s; sem a trava, alguém apertando "enviar" várias vezes derruba o portal.
- **A IA nunca "não responde".** Falha de rede, HTTP de erro ou corpo inesperado viram a frase
  de fallback com `status = erro`. O status existe porque a própria IA usa a mesma frase quando
  não sabe responder — sem ele, queda de rede e transferência legítima ficariam indistinguíveis.
- **Não há retry.** Repetir depois de um timeout dobraria a carga com a VM já saturada — que é,
  aliás, a causa mais provável da primeira falha.
- **O histórico tem dois donos.** A IA guarda contexto em memória (expira em 24h de inatividade,
  ou quando o processo dela reinicia); o portal guarda o texto no banco. A tela acompanha a
  expiração de 24h abrindo uma conversa nova, mas não tem como saber de um restart da IA — nesse
  caso a conversa continua na tela e a Lara não lembra dela.
- **A resposta é limitada na origem** (`num_predict=300` no modelo, 3–4 parágrafos em texto
  corrido). O portal não trunca nem reformata o que chega.
- **A resposta é exibida como texto puro** (`x-text`, nunca `x-html`): o conteúdo vem de um
  modelo de linguagem e não pode virar HTML executável na tela.
- Retenção: `php artisan app:prune-lara-messages --days=90`.

## Mensagens/erros

| Situação | O que o funcionário vê |
|----------|------------------------|
| IA fora do ar / erro interno | A frase de `LARA_FALLBACK_MESSAGE`, como se fosse uma resposta |
| Mais de 3 conversas simultâneas na VM | A mesma frase, na hora (a IA nem enfileira) |
| Pergunta anterior ainda em curso | "Sua pergunta anterior ainda está sendo respondida…" (HTTP 429) |
| Pergunta vazia, curta ou longa demais | Mensagem de validação (HTTP 422) |
| `LARA_ENABLED=false` ou sem `LARA_BASE_URL` | "A Lara está desativada no momento" |
| Health check falhou | "A Lara não está respondendo agora — o servidor dela pode estar reiniciando" |
| Demora acima de `LARA_TIMEOUT + 10s` | "A Lara demorou demais para responder…" |

## Como testar sem a tela

```bash
curl -s http://<ip-da-vm>:3000/health

curl -X POST http://<ip-da-vm>:3000/perguntar \
  -H "Content-Type: application/json" \
  -d '{"usuario_id": "func_teste", "mensagem": "qual o horário da academia?"}'
```

Rodar **do servidor web** (é ele que faz a chamada). No nosso caso o PHP-FPM roda na mesma VM
do banco (`192.168.10.10`), que é a liberada na porta 3000 — mas se um dia o portal sair dessa
máquina, a liberação de rede precisa acompanhar.

## Rollout

1. Deploy com `LARA_ENABLED=false`.
2. `php artisan migrate` e `php artisan db:seed --class=RolesAndPermissionsSeeder`
   (idempotente — só acrescenta a permissão nova).
3. Conceder `use lara chat` a 2–3 pessoas.
4. Preencher `LARA_BASE_URL`, ligar `LARA_ENABLED=true` e rodar
   **`php artisan config:cache`**.

> O `deploy_hml.sh` roda `config:cache`, então o `.env` fica congelado no cache:
> editar uma variável sem recachear não muda nada. Pelo mesmo motivo, o valor
> em `config/services.php` é só o padrão de quem **não** tem a variável no
> `.env` — quem tem, o `.env` manda.
5. Acompanhar `lara_messages` antes de abrir para todos:

```sql
SELECT status, COUNT(*), ROUND(AVG(latencia_ms)) AS media_ms, MAX(latencia_ms) AS pior_ms
FROM lara_messages
WHERE role = 'assistant' AND created_at >= NOW() - INTERVAL 1 DAY
GROUP BY status;
```

`fallback` subindo significa problema na VM da IA (timeout ou concorrência); `erro` subindo
significa problema de rede entre o portal e ela. São donos diferentes.

## Referência técnica

- Controller: `LaraChatController` · Request: `AskLaraRequest`
- Service: `App\Services\Lara\LaraClient` (+ DTO `LaraAnswer`)
- Model: `LaraMessage` · Command: `app:prune-lara-messages`
- View: `resources/views/lara/index.blade.php`
- Testes: `tests/Unit/LaraClientTest.php`, `tests/Feature/LaraMessageHistoryTest.php`
