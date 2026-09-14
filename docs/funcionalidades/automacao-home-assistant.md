# Automação Home Assistant (Iluminação)

## O que é

O **Home Assistant** consulta periodicamente um endpoint do sistema para saber se cada
contator (relé de iluminação) deve estar **ligado ou desligado**. O sistema não envia comandos:
ele só responde, e o Home Assistant aplica o estado nos relés.

A decisão combina três fontes: controle manual, agendamentos cadastrados no painel e
reservas confirmadas dos espaços.

## Para quem

- **Home Assistant**: consome o endpoint com o token da API.
- **Equipe com a permissão `manage home assistant`** (por padrão, o papel `admin`): usa o
  painel `/home-assistant` e o card do dashboard.

## Endpoint

`GET /api/schedule/home-assistant/automation` → `ScheduleController@homeAssistantAutomation`

Exige o header `Authorization: Bearer <API_TOKEN>` (middleware `api_token`). Não exige `login_token`.

```json
{ "contactors": { "switch.quadra_1": true, "switch.quadra_2": false } }
```

Em caso de erro, responde `500` com `{"error": "Falha ao calcular o estado dos contatores."}`.
O detalhe da exceção vai só para o log.

## Como o estado é decidido

Toda a decisão fica em `App\Services\HomeAssistant\ContactorStateResolver`, o mesmo cálculo
usado pelo endpoint, pelo painel e pelo dashboard. A primeira regra que se aplica decide:

1. **Controle manual (ação rápida)**: "Ligado" ou "Desligado" no cartão do contator ou no
   interruptor do dashboard. É gravado como agendamento `is_quick` de prioridade 1000, válido
   só no dia. "Automático" remove a ação rápida.
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
- Testes: `tests/Unit/HomeAssistant/`, `tests/Feature/HomeAssistantAutomationEndpointTest.php`
- Integração: [Integrações](../integracoes.md#116-home-assistant-automação-de-iluminação)
