# Gestão de tokens da API (Administração › Integrações)

Desde 17/11/2026. Quem chama O Lara de fora (site Next.js, HAL/IA, portaria, Home Assistant, bot
do Telegram, Poli) usa um **token próprio por instalação**, gerado na tela **Integrações**, com
**escopos** (o que ele alcança), **validade** escolhida na geração e **registro do último uso**.
Antes disso havia um token só, o `API_TOKEN` do `.env`, compartilhado por todos: se vazasse de um
sistema, todos os outros ficavam abertos, e trocar exigia mexer em todos ao mesmo tempo.

## Decisões (17/11/2026)

- Vale **só para quem chama O Lara** (entrada). Os tokens que O Lara usa para chamar outros
  sistemas (`REPET_API_TOKEN`, Poli, VM da IA) continuam no `.env`.
- **Um token por instalação** (site produção ≠ site homologação): revogar um não derruba o outro.
- **Sem restrição por IP.**
- **Validade definida na geração**: 30, 90, 180 ou 365 dias, até uma data, ou sem validade (a tela
  avisa que é melhor ter data).
- O **último uso** de cada token (data/hora, IP, rota) fica gravado e aparece na tela.
- `/api/company-access/*` (Monitor de Acesso), que nunca pediu token, entra no escopo `portaria`.
  No começo só **registra** quem chama, para não parar a guarita (ver "Transição").
- A cozinha (`/api/freelancer/dinners`) continua aberta, por decisão de quem opera.

## Escopos e inventário (F0)

Catálogo em `App\Support\ApiTokens\ApiScopes`. A rota declara o escopo no middleware
(`api_token:ia`) e o token só passa se tiver um dos escopos dela.

| Escopo | Quem usa | Rotas |
|---|---|---|
| `site` | Site Next.js (locação) | `/api/login`, `/register`, `/check-member`, `/change-password`, `/send-email`, `/tournaments*`, `/social-showcase*`, `/tickets*`, `/replay/places*`, e todo o grupo `login_token` (`/member/*`, `/places*`, `/place/*`, `/schedule*`, `/social/*`, `/lighting/*`, `/dashboard/*`, `/replay/my-videos`, `/verify-token`) |
| `ia` | VM do HAL (IA) | `/api/information/search`, `/api/avisos/search`, `/api/guias/search` |
| `portaria` | Monitor de Acesso, câmeras, app da guarita | `/api/parking/*`, `/api/fleet/*` e `/api/company-access/*` (este com `api_token_portaria`, ver abaixo) |
| `home-assistant` | Home Assistant | `GET /api/schedule/home-assistant/automation`, `POST /api/schedule/home-assistant/contactors/{id}/manual` |
| `telegram` | Bot do Telegram | `/api/telegram/*` e o comando manual do Home Assistant (a rota aceita `home-assistant` ou `telegram`) |
| `webhook-whatsapp` | Poli | `POST /api/webhooks/whatsapp` |

Fora da gestão (cada um com a sua autenticação, sem mudança): `/api/placar/*` e `/api/replay/*`
da captura (Sanctum, `placar:token`/`replay:token`), `/api/assinatura/remota/*`
(`SIGNATURE_REMOTE_API_TOKEN`), `/api/aprovacao/*` (JWT próprio). Abertas, como antes:
`/api/ping`, `/api/test`, `/api/schedule/generate-pdf`, `/api/whatsapp/*` (webhook da Meta),
`/api/freelancer/dinners`, `/api/aprovacao/login`.

A automação do Home Assistant (`/schedule/home-assistant/automation`) saiu do grupo do site e foi
para o do Home Assistant: mesma URL, mesmo nome de rota (`api.schedule.homeAssistantAutomation`).

`tests/Feature/ApiTokens/ApiRouteScopesTest.php` confere o escopo de cada grupo e que nenhuma rota
usa `api_token` sem escopo.

## Como a chamada é validada

`App\Services\ApiTokens\ApiTokenGuard`, chamado pelo middleware `api_token:<escopos>`
(`App\Http\Middleware\APIToken`):

1. Sem `Authorization: Bearer …` → **401** `Invalid API Token`.
2. Token que começa com `lara_` → token gerenciado, procurado pelo SHA-256:
   - não existe → **401**;
   - revogado, vencido ou de integração desativada → **401**;
   - sem nenhum dos escopos da rota → **403** `Este token não tem acesso a esta API`;
   - senão passa, e o token fica em `$request->attributes->get('api_integration_token')`.
3. Qualquer outro valor → compara com o `API_TOKEN` do `.env` (`hash_equals`). Passa em **qualquer
   rota** enquanto `API_TOKEN_LEGACY_ENABLED=true`, registrado como **legado**.
4. O resto → **401**.

O header recebido nunca volta na resposta. `OPTIONS` passa direto.

### Último uso e contagem

A cada chamada o guard:

- grava no token `last_used_at`, `last_used_ip` e `last_used_route` (nome da rota, ou o caminho)
  — no máximo uma vez por `API_TOKEN_TOUCH_SECONDS` (60 s), mas na hora se o IP ou a rota mudar;
- soma a chamada em `api_token_usage_daily` (dia × token × tipo × escopo × IP), com o tipo
  `token`, `legado`, `sem-token` ou `recusado`.

Falha ao registrar nunca derruba a chamada (vira aviso no log). A chamada recusada não mexe no
último uso, só na contagem.

### `api_token_portaria` (company-access)

`App\Http\Middleware\ObserveApiToken`. Com `API_TOKEN_PORTARIA_ENFORCED=false` (padrão) **não
barra nada**: token válido fica registrado no token; sem token (ou recusado) passa e fica registrado
como `sem-token`/`recusado`. Com `true`, vira exatamente o `api_token:portaria`. A config é lida a
cada requisição: ligar pede só `config:cache`, não `route:cache`.

## O token

- Formato `lara_` + 48 caracteres aleatórios (53 no total).
- No banco só o SHA-256 (`token_hash`) e os 4 últimos caracteres (`token_hint`) para a tela
  identificar qual está instalado onde.
- O texto aparece **uma vez**, na tela seguinte à geração (sessão flash `new_api_token`), com botão
  de copiar. Não vai para log nenhum.
- Revogado não é apagado: fica na lista, com quem revogou, quando e o motivo.

### Rotacionar

Gera um token novo com a mesma instalação e os mesmos escopos. O antigo continua valendo pelos
**dias de convivência** escolhidos (0, 1, 7, 15 ou 30; padrão `API_TOKEN_ROTATION_GRACE_DAYS`=7) —
a validade dele vira "agora + N dias", nunca mais longa que a que já tinha. Com 0, é revogado na
hora. O antigo guarda `replaced_by_id`.

## Tela (Administração › Integrações, `/integracoes`)

No menu da conta (canto superior direito), ao lado de Usuários e Setores.

- **Lista** (`api-integrations.index`): cartões com sistema, tokens ativos, "vence em breve" e o
  último uso; busca por nome, instalação ou observação; cadastro de integração. Embaixo, **Chamadas
  sem token da tela (últimos 30 dias)**: quem ainda usa o `API_TOKEN` antigo ou chama o Monitor de
  Acesso sem token, por escopo e IP, com a última rota. É essa lista que precisa zerar.
- **Integração** (`api-integrations.show`): tabela dos tokens com instalação, final do token,
  escopos, **último uso** (data/hora, há quanto tempo, IP, rota), chamadas nos últimos 7 dias (e
  recusadas), validade e situação (ativo, vence em breve, vencido, revogado); rotacionar e revogar
  por token; gerar token (instalação, validade, escopos — já marcado o escopo do sistema); editar e
  desativar a integração (desativada, nenhum token dela passa, sem precisar revogar).

### Permissões (grupo Administração)

| Permissão | O que libera |
|---|---|
| `integracoes.visualizar` | Ver a lista, os tokens e o último uso |
| `integracoes.gerenciar` | Cadastrar integração, gerar, rotacionar e revogar token (também vê a tela) |

A tela abre com qualquer uma das duas (habilidade composta `acessar-integracoes` em
`Permissions::ANY_OF`). Nascem sem setor; os setores de acesso total já têm.

## Transição (F4 e F5)

1. Deploy: `API_TOKEN_LEGACY_ENABLED=true` e `API_TOKEN_PORTARIA_ENFORCED=false` (padrões). Nada
   muda para os sistemas externos.
2. Cadastrar as integrações e gerar um token por instalação. Passar cada token ao sistema (prompts
   em `docs/prompts/NextJS/`, `docs/prompts/IA/`, `docs/prompts/LaraExternos/`
   `gestao-tokens-*-prompt.md`), além do Home Assistant, do bot do Telegram e da Poli.
3. Acompanhar a lista "Chamadas sem token da tela" até zerar.
4. Monitor de Acesso já mandando o token: `API_TOKEN_PORTARIA_ENFORCED=true` + `config:cache`.
5. Lista zerada por alguns dias: `API_TOKEN_LEGACY_ENABLED=false` + `config:cache` (homologação
   antes). Depois, tirar o `API_TOKEN` do `.env`.

Para voltar atrás em qualquer passo: a flag de volta para `true`/`false` e `config:cache`.

## Configuração (`config/api_tokens.php`)

| Variável | Padrão | Efeito |
|---|---|---|
| `API_TOKEN` | — | O token antigo, aceito enquanto o legado está ligado |
| `API_TOKEN_LEGACY_ENABLED` | `true` | Aceita o `API_TOKEN` antigo em todas as rotas |
| `API_TOKEN_PORTARIA_ENFORCED` | `false` | Exige token em `/api/company-access/*` |
| `API_TOKEN_ROTATION_GRACE_DAYS` | `7` | Convivência padrão na rotação |
| `API_TOKEN_EXPIRING_DAYS` | `30` | Antecedência do "vence em breve" |
| `API_TOKEN_TOUCH_SECONDS` | `60` | Intervalo mínimo para regravar o último uso |

## Banco

Migration `2026_11_17_100000_create_api_integrations_tables` (idempotente, nomes curtos de índice):

- `api_integrations` — `name` (único), `system` (`ApiIntegration::SYSTEMS`), `notes`, `active`,
  `created_by`.
- `api_integration_tokens` — `api_integration_id`, `name` (instalação), `token_hash` (único),
  `token_hint`, `scopes` (JSON), `expires_at`, `last_used_at`, `last_used_ip`, `last_used_route`,
  `revoked_at`, `revoked_by`, `revoke_reason`, `replaced_by_id`, `created_by`.
- `api_token_usage_daily` — `day`, `api_integration_token_id` (0 = sem token gerenciado), `kind`,
  `scope`, `ip`, `hits`, `last_route`, `last_seen_at`; único por (dia, token, tipo, escopo, IP).

Sem chave estrangeira para `users` (o User está na conexão `mysql`; os testes rodam em SQLite).

## Peças

| Arquivo | Papel |
|---|---|
| `app/Support/ApiTokens/ApiScopes.php` | Catálogo de escopos |
| `app/Services/ApiTokens/ApiTokenManager.php` | Gerar, rotacionar, revogar |
| `app/Services/ApiTokens/ApiTokenGuard.php` | Validar a chamada e registrar o uso |
| `app/Http/Middleware/APIToken.php` | `api_token:<escopos>` |
| `app/Http/Middleware/ObserveApiToken.php` | `api_token_portaria:<escopo>` |
| `app/Models/ApiTokens/ApiIntegration.php`, `ApiIntegrationToken.php` | Models |
| `app/Http/Controllers/ApiIntegrationController.php` | Tela |
| `resources/views/api-integrations/*` | Views |
| `config/api_tokens.php` | Flags |

## Testes

`tests/Feature/ApiTokens/`: `ApiTokenMiddlewareTest` (escopo, revogado, vencido, desativado,
legado ligado/desligado, portaria registrando/exigindo, último uso e contagem, rotação),
`ApiRouteScopesTest` (escopo de cada grupo) e `ApiIntegrationScreenTest` (permissões, token visto
uma vez, validade obrigatória, último uso na tela, lista do token antigo, menu).

Nos testes dos módulos, o `config(['services.api.token' => …])` de sempre continua funcionando:
o token antigo segue aceito com o legado ligado (o padrão).

## Pendente

- Aviso automático (notificação) de token vencendo: hoje só a tela marca "vence em breve".
- F6 (opcional): trazer para a mesma tela o `SIGNATURE_REMOTE_API_TOKEN` (escopo próprio) e os
  clientes do Placar e do Replay, hoje gerados por artisan.
