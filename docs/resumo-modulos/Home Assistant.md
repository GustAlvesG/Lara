# Home Assistant — resumo para quem vai mexer

Documentação completa (regras, endpoints, autoatendimento):
[docs/funcionalidades/automacao-home-assistant.md](../funcionalidades/automacao-home-assistant.md).
Onde ela fala em `manage home assistant`, leia a permissão do catálogo **`home-assistant`** — a
Spatie é legado.

## Em uma frase

O Home Assistant **pergunta** ao Lara, a cada ~30 s, se cada contator (relé de iluminação) deve
estar ligado; o Lara decide combinando comando manual, agendamentos do painel e reservas
confirmadas, e **nunca aciona o relé**. Tela `/home-assistant` (`home-assistant.*`, permissão
`home-assistant`) e interruptores no Dashboard.

## A decisão (uma só, em `ContactorStateResolver`)

Endpoint, painel e dashboard usam o mesmo cálculo. A primeira regra que se aplica decide:

1. **Manual** (`is_quick`, prioridade 1000, gravado por `ManualCommandService`): pelo painel vale
   até a meia-noite; pela API até `expires_at` (truncado na virada do dia). Um substitui o outro.
   "Automático" remove.
2. **Agendamentos** (`HomeAssistantOverride`), maior `priority` primeiro (empate: o mais recente).
   `manual_on`/`manual_off` valem o dia; `schedule_override` só **dentro** das faixas — fora
   delas não decide e passa adiante. Regra de horário em `HomeAssistantOverride::stateAt()`.
3. **Reservas** confirmadas (`status_id = 1`) dos espaços do contator (`places.contactor_id`),
   de 5 min antes a 5 min depois.

Intervalos meio-abertos `[início, fim)`; faixa que passa da meia-noite pertence ao dia em que
começou; reserva buscada por sobreposição.

## Integrações

| Rota | Quem chama | Auth |
|---|---|---|
| `GET /api/schedule/home-assistant/automation` (`ScheduleController@homeAssistantAutomation` → `SchedulesService`) | Home Assistant (polling) | `api_token` (sem `login_token`) |
| `POST /api/schedule/home-assistant/contactors/{entity_id}/manual` (`HomeAssistantApiController`) | HA/Telegram, comando manual `on`/`off`/`auto` | `api_token`, `throttle:30,1` |
| `/api/lighting/*` (`Api\MemberLightingController`) | App de reservas (Next.js) — autoatendimento do sócio | `api_token` **e** `login_token` |

O manual devolve o estado **recalculado** (um "liga" pode perder para agendamento maior).
`created_by` é nulo vindo da API; a procedência fica em `origin`.

## Autoatendimento do sócio (fim de semana)

- O acionamento vira **o mesmo comando manual**; a camada nova só diz de quem é a cota.
  Regras em `SelfServiceLightingService` + `LightingWindow`.
- Janela **por quadra**: exceção da quadra → padrão do clube (`place_id` nulo) →
  `config/home_assistant.php`. `weekday` 0–6 (Carbon) + **7 = feriado**. Datas especiais
  (`lighting_self_service_dates`, `block`/`allow`) vêm antes de tudo.
- Quadra precisa de `places.self_service_lighting` e contator. Uma quadra por sócio por vez,
  até `max_minutes`, aparado no fim da janela; quadra com reserva confirmada recusa.
- A luz não é de ninguém: acionar quadra acesa **prolonga**; a luz do contator é o **maior**
  prazo vigente (`syncLight`); devolver só apaga se ninguém mais depende e se o comando ainda é
  o do autoatendimento.
- Recusas com `reason` estável (`window_closed`, `member_limit`, `place_reserved`…) — a tela
  decide pelo código. O sócio sai **sempre do JWT**, nunca do corpo da requisição.

## Peças

| Arquivo | Papel |
|---|---|
| `app/Services/HomeAssistant/ContactorStateResolver.php`, `ContactorState.php` | A decisão. |
| `app/Services/HomeAssistant/ManualCommandService.php` | Grava/remove o comando manual. |
| `app/Services/HomeAssistant/SelfServiceLightingService.php`, `LightingWindow.php` | Autoatendimento. |
| `app/Http/Controllers/HomeAssistantController.php` | Painel: contatores, ação rápida, agendamentos, aba Autoatendimento. |
| `app/Models/Contactor.php`, `HomeAssistantOverride*.php`, `LightingSelfService*.php`, `MemberLightingActivation.php` | Dados. |
| `config/home_assistant.php` | Durações do manual e do autoatendimento, janela de reserva. |
| `resources/views/home-assistant/*`, `components/dashboard/ha-switch.blade.php` | Telas. |

O vínculo espaço → contator é editado em Reservas (espaços), e também exige `home-assistant`
(`PlaceGroupController::contactorIdFor`). O painel recarrega a cada minuto, salvo com modal
aberto ou alguém digitando.

## Testes

`tests/Unit/HomeAssistant/*` (resolver, acesso, janela), `tests/Feature/HomeAssistantAutomationEndpointTest.php`,
`HomeAssistantManualCommandTest.php`, `MemberLightingSelfServiceTest.php`. Confira o isolamento
SQLite do `phpunit.xml` antes de rodar. Teste com `login_token` que usa `Carbon::setTestNow`
nasce com JWT vencido (401) — ver memória do JWT.
