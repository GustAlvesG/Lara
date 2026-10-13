# Reservas — resumo para quem vai mexer

Documentação completa: [agendamento.md](../funcionalidades/agendamento.md) (grade de horários,
colisão, preço proporcional, e-mails), [espacos-e-grupos.md](../funcionalidades/espacos-e-grupos.md)
(cadastro base) e [socios.md](../funcionalidades/socios.md) (login do sócio, JWT).

## Em uma frase

O sócio reserva quadras e salões pelo **app/site de locação** (Next.js, API com `api_token` +
JWT `login_token`); o painel do Lara mostra a agenda, cria reservas no balcão, cancela com
estorno na **Rede (Itaú)** e configura grupos, espaços e regras. A reserva confirmada também
acende a luz (Home Assistant) e é o que vincula os clipes do RePET.

## Telas e permissões

| Aba / tela | Rotas | Permissão |
|---|---|---|
| Novo Agendamento | `schedule.index` (agenda do dia), `schedule.create`, `schedule.store.web` | `reservas.agendamentos` |
| Todos os Agendamentos | `schedule.list`, `schedule.show`, `schedule.update` (cancelar) | `reservas.agendamentos` |
| Pagamentos | `payment.index`, `payment.show` (consulta ao vivo na Rede) | `reservas.pagamentos` |
| Estorno | `payment.refund` | `reservas.pagamentos.estornar` |
| Locais, grupos e regras (fora do menu) | `place-group.*`, `schedule-rules.store` | `reservas.configurar` |

Pagamentos é sub-aba de Reservas, mas a URL continua `/payments`.

## Modelo

- `PlaceGroup` (grupo: duração do slot, horário, janela de venda do dia, antecedência mín./máx.,
  `daily_limit`, dias da semana via `Weekday`) → `Place` (espaço: preço, status, contator,
  `self_service_lighting`; scope global em ordem alfabética) → `ScheduleRules` (`include`/`exclude`).
- `Schedule` (reserva, um registro **por horário**), `SchedulePayment` (pagamento, N reservas),
  `Status`, `Member` (sócio local; dados vêm do MultiClubes).
- **Status:** 0 cancelado, 1 confirmado, 3 pendente, 4 expirado. O **4 é escondido por global
  scope** do `Schedule` — consultas que precisam dele usam `withoutGlobalScopes`.

## Regras que costumam pegar

- **Grade** em `ScheduleRulesService::getTimeOptions()`: slots → dia da semana → janela de venda
  → antecedência → include/exclude → colisão → encerrados → preço.
- **Colisão** (`checkColide`) compara só as horas no espaço; cancelado e expirado não ocupam.
- **Preço proporcional**: horário já começado continua à venda, cobrado minuto a minuto pelo
  tempo restante (`SchedulePricingService`). A venda fecha em `fim − HOLD_MINUTES`.
  **O preço é sempre recalculado no servidor** na gravação; o que o cliente manda não vale.
- **Hold:** a reserva nasce pendente (3); `app:expire-pending-schedules` (a cada minuto) passa a
  4 o que passou de `ExpirePendingSchedules::HOLD_MINUTES` (10 min). Mudar o hold muda a janela
  de venda junto.
- **E-mail** sai pelo status gravado, num ponto só (`SchedulesService::notifyScheduleStatus`):
  `schedule.pending`, `schedule.confirm` (disparado no pagamento, fora da transação) e
  `schedule.cancel` (depois da tentativa de estorno, anunciando só o que foi devolvido).
- **Limite diário** por sócio no grupo (`daily_limit`).
- Endpoints do app que **não** pedem `login_token` (`withoutMiddleware`): `POST /api/schedule`,
  `/api/member/by-title`, `/api/schedule/time-options`, `/api/schedule/home-assistant/automation`.
- O `POST /api/schedule/place` é um GET disfarçado (comentário no código).

## Integrações

| Rota | Papel |
|---|---|
| `/api/places*`, `/api/place/{id}` | Grupos e espaços para o app. |
| `/api/schedule*` | Criar, listar por espaço/sócio, mudar status, excluir pendente. |
| `POST /api/schedule/payment` | Pagamento (`SchedulePaymentController::store`) — confirma e manda o e-mail. |
| `GET /api/schedule/generate-pdf` | PDF da agenda do dia. |
| `/api/login`, `/api/register`, `/api/check-member`… | Sócio (`MemberAuthController`); JWT no header `Session`, vence no fim do dia. |

Rede: `RedeItauService` (token em cache 55 s, `getTransaction`, `refundPayment` total/parcial,
`beginRefund` em lote).

## Peças

| Arquivo | Papel |
|---|---|
| `app/Http/Controllers/ScheduleController.php`, `ScheduleRulesController.php`, `PlaceGroupController.php`, `PlaceController.php` | Painel e API. |
| `app/Http/Controllers/SchedulePaymentController.php` | Pagamento, consulta na Rede, estorno. |
| `app/Services/SchedulesService.php`, `ScheduleRulesService.php`, `SchedulePricingService.php`, `PlaceGroupService.php`, `PlaceService.php` | Regras. |
| `app/Services/RedeItauService.php`, `SchedulePaymentService.php` | Pagamento. |
| `app/Console/Commands/ExpirePendingSchedules.php` | Expira o hold (agendado). |
| `resources/views/location/*` (agenda, `schedule/`, `placeGroup/`, `place/`, `rule/`), `payments/*` | Telas. |

## Testes

`tests/Feature/ScheduleInProgressSlotTest.php`, `tests/Unit/ScheduleProportionalPriceTest.php`,
`tests/Unit/ScheduleNotificationTest.php`, `tests/Feature/Rebrand/ReservasScreensTest.php`.
Confira o isolamento SQLite do `phpunit.xml` antes de rodar.
