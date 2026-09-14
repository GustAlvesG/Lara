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

## Painel `/home-assistant`

- **Resumo**: quantos contatores estão ligados, quantos estão em modo manual e quantos
  agendamentos estão ativos e valendo agora, mais o "Como funciona" com a ordem acima.
- **Contatores**: cartão com estado, motivo ("Reserva até 20:00 · Quadra 1"), linha do tempo
  do dia simulada com as regras atuais (o passado aparece esmaecido), espaços vinculados e o
  controle Automático / Ligado / Desligado.
- **Agendamentos**: lista de ativos e de pausados/expirados, com resumo em texto, dias,
  contatores, período, prioridade, interruptor de pausa, edição e remoção.
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
