# Automação Home Assistant (Iluminação)

## O que é

O **Home Assistant** consulta periodicamente um endpoint do sistema para saber se cada
contator (relé de iluminação) deve estar **ligado ou desligado**. A Lara nunca aciona o relé:
ela só responde, e o Home Assistant aplica o estado.

O caminho de volta existe para comandos manuais: um "liga a quadra 1" disparado no Telegram
grava o comando na Lara (ver o POST abaixo), em vez de o HA mexer no switch por fora. Assim a
Lara continua sendo a única fonte da verdade e o polling seguinte não desfaz o comando.

A decisão combina três fontes: controle manual, agendamentos cadastrados no painel e
reservas confirmadas dos espaços.

## Para quem

- **Home Assistant**: consome o endpoint com o token da API.
- **Equipe com a permissão `manage home assistant`** (por padrão, o papel `admin`): usa o
  painel `/home-assistant` e o card do dashboard.

## Endpoints

### Leitura: estado de cada contator

`GET /api/schedule/home-assistant/automation` → `ScheduleController@homeAssistantAutomation`

Exige o header `Authorization: Bearer <API_TOKEN>` (middleware `api_token`). Não exige `login_token`.

```json
{ "contactors": { "switch.quadra_1": true, "switch.quadra_2": false } }
```

Em caso de erro, responde `500` com `{"error": "Falha ao calcular o estado dos contatores."}`.
O detalhe da exceção vai só para o log.

### Escrita: comando manual

`POST /api/schedule/home-assistant/contactors/{entity_id}/manual` →
`HomeAssistantApiController@manual` (mesmo token, mais `throttle:30,1`).

É por aqui que um comando disparado de fora (Telegram → Home Assistant) entra na Lara, em
vez de o HA acionar o switch por conta própria: assim a Lara continua sendo a única fonte da
verdade e o polling seguinte não desfaz o comando.

```json
{ "state": "on", "duration_minutes": 120, "origin": "Telegram (Gustavo)" }
```

- `state`: `on`, `off` ou `auto` (`auto` remove o comando manual e devolve o contator aos
  agendamentos e reservas).
- `duration_minutes`: opcional, de 1 a `manual_max_minutes`. Sem ele, vale
  `manual_default_minutes` — os dois em `config/home_assistant.php`.
- `origin`: texto livre de até 80 caracteres, gravado em `home_assistant_overrides.origin`.

Resposta `201` (`on`/`off`) ou `200` (`auto`):

```json
{
  "entity_id": "beach_quadra_1",
  "on": true,
  "manual_until": "2026-09-14T16:30:00-03:00",
  "override_id": 46
}
```

O `on` é o estado **recalculado depois da escrita**, não o que foi pedido: um "liga" perde
para um agendamento de prioridade maior, e o HA recebe a verdade.

Recusas: `401` sem token, `404` com `entity_id` desconhecido, `422` na validação.

## Como o estado é decidido

Toda a decisão fica em `App\Services\HomeAssistant\ContactorStateResolver`, o mesmo cálculo
usado pelo endpoint, pelo painel e pelo dashboard. A primeira regra que se aplica decide:

1. **Controle manual (ação rápida)**: "Ligado" ou "Desligado" no cartão do contator, no
   interruptor do dashboard ou pela API. É gravado como agendamento `is_quick` de prioridade
   1000 (acima do teto de 999 dos agendamentos) por `ManualCommandService`. "Automático"
   remove o comando.
   - Pelo painel vale até a meia-noite; pela API vale até `expires_at`, sempre truncada na
     virada do dia.
   - Um comando substitui o anterior do mesmo contator: dois manuais nunca coexistem.
2. **Agendamentos**, do de maior `priority` para o menor (no empate, vence o mais recente).
   Só contam os ativos, dentro do período (`start_date`/`end_date`) e do dia da semana
   (sem dias marcados, vale para todos):
   - `manual_on` / `manual_off` ("Manter ligado/desligado"): valem o dia inteiro.
   - `schedule_override` ("Por horário"): vale **dentro** das faixas de horário, cada uma com
     estado `on` ou `off`. **Fora das faixas, o agendamento não decide** e a decisão passa ao
     próximo agendamento ou às reservas.
3. **Reservas**: fica ligado se algum espaço do contator (`places.contactor_id`) tem reserva
   confirmada (`status_id = 1`) de **5 min antes do início até 5 min depois do fim**. Sem
   reserva, fica desligado.

## Regras de detalhe

- Faixas e reservas são intervalos meio-abertos `[início, fim)`: 18:00–20:00 e 20:00–22:00
  não se sobrepõem.
- Uma faixa que passa da meia-noite (22:00–02:00) pertence ao dia em que começou. Uma regra
  só de sexta cobre a madrugada de sábado; o período (`end_date`) segue a mesma lógica.
- A reserva é buscada por sobreposição de horário, não pela data de início. Uma reserva das
  23:00 às 00:30 continua acendendo depois da meia-noite.
- Contator sem espaço vinculado só liga por agendamento ou manualmente.
- `expires_at` só existe no comando manual. Vencida, o comando se cala e a decisão volta aos
  agendamentos e reservas, sem ninguém precisar desligar.
- `created_by` é nulo no comando que vem da API (autenticada por token, sem usuário); a
  procedência fica em `origin`.

## Autoatendimento do sócio (fim de semana)

No fim de semana **não há reserva de quadra**: o uso é livre. Até aqui isso deixava a luz sem
dono — quem quisesse jogar à noite precisava achar alguém do clube para acender. O
autoatendimento é a porta do sócio para isso, pelo aplicativo de reservas (o site em Next.js
que consome esta API).

Não é um mecanismo novo de iluminação: o acionamento vira **o mesmo comando manual** descrito
acima (`is_quick`, prioridade 1000, com `expires_at`), e o Home Assistant o aplica no polling
seguinte. O que a camada acrescenta é **de quem é a cota** e **quem acendeu**.

### Regras

1. **Só na janela**, e a janela é **por quadra**. Ver [Horários](#horários) abaixo.
2. **Só em quadra liberada.** `places.self_service_lighting`, marcada em *Espaços → editar →
   Autoatendimento do sócio*. Sem contator vinculado a quadra não aparece.
3. **O sócio escolhe o tempo**, até `max_minutes` (2 h) por acionamento, sempre aparado no
   fim da janela: às 22:30 de sábado o máximo são 30 minutos.
4. **Uma quadra por sócio ao mesmo tempo, sem teto diário.**
5. **Quadra com reserva confirmada não aciona.** Ela tem dono e a luz já acende sozinha.

### Horários

Estão na tabela `lighting_self_service_windows`, editável na aba **Autoatendimento** do
painel — mudar de 17h para 14h não é deploy. Duas camadas na mesma tabela:

- **Padrão do clube** (`place_id` nulo): uma linha por dia da semana.
- **Exceção da quadra** (`place_id` preenchido): o caso das **quadras cobertas**, que
  escurecem antes e precisam de luz mais cedo que a quadra aberta ao lado, no mesmo dia.

`weekday` segue o Carbon (0 = domingo … 6 = sábado), mais o **7 = feriado**. Numa linha de
quadra, horários nulos significam **fechada naquele dia**, mesmo com o clube aberto — é como
se cala uma quadra sem mexer no padrão.

A resolução de um dia, na ordem: **exceção da quadra → padrão do clube →
`config/home_assistant.php`**. A configuração ficou no fim da fila como rede de segurança:
banco sem as linhas semeadas não pode deixar o clube sem horário nenhum.

As **datas especiais** (`lighting_self_service_dates`) vêm antes de tudo isso:

- `block` fecha o dia em **todas** as quadras;
- `allow` **com** horário vale para todas as quadras — é decisão tomada para aquele dia;
- `allow` **sem** horário cai na linha `weekday = 7` de cada quadra, e por isso a coberta
  também abre cedo no feriado, sem ninguém precisar lembrar de cadastrar a hora.

Consequência para a API: **"está aberto?" deixou de ser uma pergunta sobre o relógio do
clube**. Às 15h de sábado a coberta já abriu e as outras não. O `open` de
`/api/lighting/availability` é o agregado ("há alguma quadra aberta?"), e o horário de cada
quadra vem em `/api/lighting/groups/{group}/places`.

### A luz não é de ninguém

Um acionamento **não reserva a quadra**. Acabando o tempo, qualquer sócio presente aciona de
novo e a luz continua acesa sem piscar — inclusive quem não acendeu da primeira vez. É o que
resolve o jogo que passa do previsto: às 19:30, com a luz da Ana valendo até 20:00, o Bruno
aciona e ela passa a valer até 21:30.

Daí três consequências que o código honra explicitamente:

- **Quadra acesa não é recusa.** Acionar a quadra em que o sócio já está **prolonga** o
  acionamento dele (mesma linha, `starts_at` preservado, `ends_at` empurrado); acionar a de
  outro sócio cria um acionamento novo e estende a luz.
- **A luz do contator é o *maior* prazo entre os acionamentos vigentes dele**
  (`SelfServiceLightingService::syncLight`). Um pedido de 20 minutos no meio de um de duas
  horas não encurta o que já valia. A conta é por *contator*, não por quadra, porque um
  contator pode alimentar mais de um espaço.
- **Devolver a quadra não apaga a luz de quem ficou.** O `release` marca a saída e recalcula;
  só apaga quando mais ninguém depende dela — e, mesmo aí, só se o comando no contator for o
  do autoatendimento (um "manter ligado" dado no painel por cima sobrevive).

### Endpoints (`api_token` + `login_token`)

| Método | URI | O que faz |
|--------|-----|-----------|
| GET | `/api/lighting/availability` | Se **alguma** quadra está aberta, a próxima a abrir, os limites de duração e o acionamento vigente do sócio. |
| GET | `/api/lighting/groups` | Grupos que têm ao menos uma quadra liberada. |
| GET | `/api/lighting/groups/{group}/places` | Quadras do grupo, cada uma com a própria janela, `available_minutes`, `lit` e `lit_until`. |
| POST | `/api/lighting/places/{place}/activate` | Acende pelos `minutes` pedidos. `201` novo, `200` prolongando. |
| POST | `/api/lighting/release` | Devolve a quadra antes da hora. |
| GET | `/api/lighting/activations` | Histórico do sócio (30 últimos). |

Toda recusa traz um `reason` estável além da mensagem — a tela decide o que mostrar pelo
código, não pelo texto em português:

| `reason` | HTTP | Quando |
|----------|------|--------|
| `window_closed` | 422 | Fora da janela **daquela quadra**, ou dia bloqueado. Vem com `next_window`, também da quadra. |
| `window_ending` | 422 | Falta menos que `min_minutes` para fechar. |
| `member_limit` | 409 | O sócio está em **outra** quadra. Vem com `active_activation`. |
| `place_not_eligible` | 422 | Quadra não liberada, ou sem contator. |
| `place_reserved` | 409 | Reserva confirmada no período. Vem com `reserved_from`/`reserved_until`. |
| `no_activation` | 404 | `release` sem nada aceso. |

`minutes` fora de `[min_minutes, max_minutes]` volta como erro de validação comum do Laravel
(`422` com `errors.minutes`), não com `reason`.

O sócio é sempre o dono do `Session` (JWT), resolvido pelo cpf do token — **nenhum endpoint
aceita sócio vindo do corpo da requisição**.

### Referência técnica do autoatendimento

- Regras: `app/Services/HomeAssistant/SelfServiceLightingService.php`,
  `LightingWindow.php`, `app/Exceptions/SelfServiceLightingException.php`
- Endpoints: `app/Http/Controllers/Api/MemberLightingController.php`,
  `app/Http/Requests/StoreMemberLightingActivationRequest.php`
- Modelos: `LightingSelfServiceWindow`, `LightingSelfServiceDate`, `MemberLightingActivation`
- Painel: aba **Autoatendimento** em `/home-assistant`
  (`resources/views/home-assistant/partials/self-service.blade.php` e
  `self-service-window-form.blade.php`, o modal de horário por quadra)
- Testes: `tests/Unit/HomeAssistant/SelfServiceLightingWindowTest.php`,
  `tests/Feature/MemberLightingSelfServiceTest.php`
- Front-end: [prompt de implementação](iluminacao-autoatendimento-prompt.md)

## Painel `/home-assistant`

- **Resumo**: quantos contatores estão ligados, quantos estão em modo manual e quantos
  agendamentos estão ativos e valendo agora, mais o "Como funciona" com a ordem acima.
- **Contatores**: cartão com estado, motivo ("Reserva até 20:00 · Quadra 1"), linha do tempo
  do dia simulada com as regras atuais (o passado aparece esmaecido), espaços vinculados e o
  controle Automático / Ligado / Desligado.
- **Agendamentos**: lista de ativos e de pausados/expirados, com resumo em texto, dias,
  contatores, período, prioridade, interruptor de pausa, edição e remoção.
- **Autoatendimento**: o **horário padrão** do clube (editável, um campo por dia da semana
  mais o feriado), as quadras liberadas com o horário efetivo de hoje e o modal de **horário
  próprio por quadra** (seguir o padrão / horário próprio / fechada), quem está com luz acesa
  agora e o cadastro de **datas especiais** (liberar um feriado, bloquear um sábado de
  torneio).
- O formulário de agendamento mostra, antes de salvar, um resumo em linguagem natural do que
  o agendamento vai fazer.
- A página recarrega a cada minuto, exceto quando há um modal aberto ou alguém digitando.

Todas as rotas do painel exigem a permissão `manage home assistant`. Trocar o contator de um
espaço também exige essa permissão (`PlaceGroupController::contactorIdFor`).

## Referência técnica

- Decisão: `app/Services/HomeAssistant/ContactorStateResolver.php`, `ContactorState.php`
- Regra de horário: `HomeAssistantOverride::stateAt()`
- Endpoint: [`ScheduleController@homeAssistantAutomation`](../controllers.md#schedulecontroller) →
  [`SchedulesService::homeAssistantAutomation`](../services.md#schedulesservice)
- Painel: `HomeAssistantController`, views em `resources/views/home-assistant/`
- Comando manual: `app/Services/HomeAssistant/ManualCommandService.php`,
  `app/Http/Controllers/HomeAssistantApiController.php`,
  `app/Http/Requests/StoreHomeAssistantManualCommandRequest.php`, `config/home_assistant.php`
- Testes: `tests/Unit/HomeAssistant/`, `tests/Feature/HomeAssistantAutomationEndpointTest.php`,
  `tests/Feature/HomeAssistantManualCommandTest.php`
- Integração: [Integrações](../integracoes.md#116-home-assistant-automação-de-iluminação)
