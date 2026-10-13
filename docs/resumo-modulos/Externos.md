# Externos — resumo para quem vai mexer

Documentação completa: [docs/company-access-control.md](../company-access-control.md) (telas,
regras, liberação pontual, API de registro) e
[docs/funcionalidades/empresas.md](../funcionalidades/empresas.md) (cadastro).
O pedido de carro de aplicativo pelo WhatsApp está em
[bot-whatsapp.md](../funcionalidades/bot-whatsapp.md) e no [resumo do Bot WhatsApp](Bot%20WhatsApp.md).

## Em uma frase

Quem **não é sócio** e entra no clube: terceirizados de empresas parceiras (regras de dia e
horário), freelancers (o contrato libera), liberações pontuais (uma entrada no dia) e carros de
aplicativo pedidos pelo sócio no WhatsApp. A portaria consulta e registra no **Monitor de
Acesso** (tela web ou a aplicação Python da guarita, pela API); tudo cai no mesmo histórico,
`company_access_logs`.

## Telas e permissões

| Aba | Rota | Permissão |
|---|---|---|
| Empresas (+ prestadores e regras) | `company.index`, `company.show`, `company.worker.*`, `company.rules.*` | nenhuma (todo logado) |
| Monitor de Acesso | `company.access.monitor` | nenhuma (todo logado) |
| Liberação Pontual | `company.one-off.*` | `externos.liberacao-pontual` |
| Histórico | `company.access.logs` | `externos.historico` |
| Carros de Aplicativo | `company.uber.requests`, `company.uber.accesses`, `company.uber.waiting` (fila da portaria, fora do menu) | `externos.carros-aplicativo` |

Rotas fixas (`/one-off-accesses`, `/uber-*`, `/workers/search`) ficam **antes** de `/{company}`
em `routes/web.php`, senão o segmento é lido como empresa.

## Como a consulta decide (`CompanyService`)

O `target` digitado decide o tipo (`registerAccess` / `validateTryToAccess`):

| `target` | Tipo |
|---|---|
| CPF | terceirizado **e/ou** freelancer **e/ou** liberação pontual — as três buscas somam linhas |
| `PLACA.Nome.Obs` | motorista de aplicativo avulso (`app_drivers`, cadastrado na hora) |
| placa de pedido de Uber | `validateUberAccess` (pedido `aguardando_acesso` e dentro de `expires_at`) |
| texto | nome de empresa → todos os prestadores, um botão de registro por pessoa |

- **Terceirizado:** regras do prestador, se houver, **substituem** as da empresa
  (`validateRulesForAccess`). Precisa de alguma `include` ativa e nenhuma `exclude` ativa.
- **Freelancer:** sem regra; vale contrato com janela de **30 min antes do início até o fim**
  (vira a meia-noite). Ignora cancelados e aditivados (vale o aditivo). Assinatura **não** é
  exigida na portaria.
- **Liberação pontual** (`OneOffAccessService`, `one_off_accesses`): uma por CPF aberta, só no
  dia de criação, gasta no primeiro **registro** (consultar não gasta), consumo atômico
  (`UPDATE … WHERE used_at IS NULL`). Vence sozinha na virada do dia, sem job.
- Registro por botão usa endpoint próprio por tipo (`register-worker-access`,
  `register-freelancer-access`, `register-one-off-access`) — os ids são de tabelas diferentes.

## Carros de aplicativo (Uber)

- O sócio pede pelo WhatsApp (Poli). A máquina de estados `UberAccessRequestFlow`, por
  `contact_uuid`: matrícula → nome → local → placa → print → `aguardando_acesso`. Sem resposta
  por `SESSION_TIMEOUT_SECONDS` (200 s) o pedido expira; um novo gatilho recomeça do zero. No
  bot do Lara, o mesmo pedido nasce pela ação `uber_request` (`registrarPedidoDoBot`).
- O sócio é conferido no MultiClubes (`MemberValidator`): `validado`, `nao_encontrado` ou
  `indisponivel` — é informativo, não bloqueia.
- A portaria libera **pelo id** do pedido (`GET /api/company-access/uber-waiting` +
  `POST register-uber-request-access`), não pela placa — a placa é o campo que mais chega
  errado. `POST uber-request-plate` corrige a placa.
- Liberado, sai o aviso de chegada ao sócio (`SendPoliTextMessage`, assíncrono; Poli fora do ar
  não segura a liberação). Encerra a conversa conforme `poli.messages.uber_arrival.close_after`.
- Agendados: `app:expire-uber-access-requests` (a cada minuto) e
  `app:prune-uber-access-request-messages` (03:00).
- O webhook é `POST /api/webhooks/whatsapp` (`UberAccessRequestWebhookController`), o mesmo do bot.

## API da portaria (`/api/company-access/*`)

`validate-access`, `register-access`, `register-worker-access`, `register-freelancer-access`,
`register-one-off-access`, `uber-waiting`, `register-uber-request-access`, `uber-request-plate`.
**Sem middleware de token** no grupo (ver `routes/api.php`) — mudar isso quebra o Monitor Python.

## Peças

| Arquivo | Papel |
|---|---|
| `app/Services/CompanyService.php` | Toda a decisão e o registro de acesso. |
| `app/Services/OneOffAccessService.php` | Liberação pontual. |
| `app/Services/UberAccessRequestFlow.php`, `app/Services/MemberValidation/*` | Captura do pedido e conferência do sócio. |
| `app/Http/Controllers/Company/*` | Empresas, prestadores, regras, monitor, histórico, Uber, liberação. |
| `app/Models/Company/*`, `UberAccessRequest.php`, `UberAccessRequestMessage.php`, `AppDriver.php` | Dados. |
| `resources/views/companies/*` | Telas (`uber/` para Carros de Aplicativo). |

O SIV usa `company_access_logs` e `uber_access_requests` para dizer quem estava no carro (ver
[SIV](SIV.md)); o Dashboard conta permitidos/negados do dia.

## Testes

`tests/Feature/OneOffAccessTest.php`, `UberAccessValidationTest.php`, `UberRequestsSearchTest.php`,
`UberAccessRequestWebhookTest.php`, `PruneUberAccessRequestMessagesTest.php`,
`tests/Unit/FreelancerAccessWindowTest.php`, `tests/Feature/Rebrand/ExternosScreensTest.php`.
Confira o isolamento SQLite do `phpunit.xml` antes de rodar.
