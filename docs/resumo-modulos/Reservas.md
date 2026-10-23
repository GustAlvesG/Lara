# Reservas — resumo para quem vai mexer

Documentação completa: [agendamento.md](../funcionalidades/agendamento.md) (grade de horários,
colisão, preço proporcional, e-mails), [espacos-e-grupos.md](../funcionalidades/espacos-e-grupos.md)
(cadastro base) e [socios.md](../funcionalidades/socios.md) (login do sócio, JWT).

## Em uma frase

O sócio reserva quadras (por horário) e **espaços sociais** (o dia inteiro, com aprovação) pelo **app/site de locação** (Next.js, API com `api_token` +
JWT `login_token`); o painel do Lara mostra a agenda, cria reservas no balcão, cancela com
estorno na **Rede (Itaú)**, dá baixa manual do que foi pago fora da plataforma e configura
grupos, espaços e regras. A reserva confirmada também
acende a luz (Home Assistant) e é o que vincula os clipes do RePET.

## Telas e permissões

| Aba / tela | Rotas | Permissão |
|---|---|---|
| Novo Agendamento | `schedule.index` (agenda do dia), `schedule.create`, `schedule.store.web` | `reservas.agendamentos` |
| Todos os Agendamentos | `schedule.list`, `schedule.show`, `schedule.update` (cancelar) | `reservas.agendamentos` |
| Pagamentos | `payment.index`, `payment.show` (consulta ao vivo na Rede) | `reservas.pagamentos` |
| Estorno | `payment.refund` | `reservas.pagamentos.estornar` |
| Baixa manual (pago fora da plataforma; botões no detalhe do agendamento e da reserva social) | `payment.manual` | `reservas.pagamentos.baixa-manual` |
| Espaços Sociais | `social-booking.index/create/store/show/details/guests/guests.pdf/contract.download/availability` | `reservas.sociais` |
| Espaços Sociais — decidir | `social-booking.contract/approve/refuse/cancel` (estornar pede também `reservas.pagamentos.estornar`) | `reservas.sociais.aprovar` |
| Bebidas dos espaços sociais (botão na lista) | `social-booking.beverages.*` | `reservas.configurar` |
| Locais e Grupos (aba desde 14/10/2026; também pelo botão Configurações da agenda) | `place-group.*`, `schedule-rules.store` | `reservas.configurar` |
| Contas do site (desde 23/10/2026: senha provisória, e-mail, cadastro manual, duplicadas — ver [Contas do site](Contas%20do%20site.md)) | `site-accounts.*` | `reservas.contas-site` |

Pagamentos é sub-aba de Reservas, mas a URL continua `/payments`.

## Modelo

- `PlaceGroup` (grupo: duração do slot, horário, janela de venda do dia, antecedência mín./máx.,
  `daily_limit`, dias da semana via `Weekday`) → `Place` (espaço: preço, status, contator,
  `self_service_lighting`; scope global em ordem alfabética) → `ScheduleRules` (`include`/`exclude`).
- `Schedule` (reserva, um registro **por horário**), `SchedulePayment` (pagamento, N reservas),
  `Status`, `Member` (sócio local; dados vêm do MultiClubes).
- **Status:** 0 cancelado, 1 confirmado, 3 pendente, 4 expirado. O **4 é escondido por global
  scope** do `Schedule` — consultas que precisam dele usam `withoutGlobalScopes`.

## Espaços sociais (desde 14/10/2026)

Guia completo: [espacos-sociais.md](../funcionalidades/espacos-sociais.md). Pontos para quem mexe:

- `PlaceGroup.category` = `esportiva` | `social` (constantes `CATEGORY_*`, scopes `sport()`/`social()`).
  Tudo que é de quadra filtra `sport()`: agenda do painel, `index_api` (`/api/places/group`),
  `getTimeOptions` (devolve `[]` para social), `createSchedule` (recusa social).
- Fluxo em `App\Services\Social\SocialBookingService`: `requested` → (contrato ok + `approve`) →
  `awaiting_payment` → (`markPaid`, chamado por `SchedulePaymentController::store`) → `confirmed`;
  saídas `refused`, `cancelled`, `expired` (`social:expire-bookings`, 5 min).
- A ocupação é um `Schedule` 00:00–23:59:59 ligado 1:1 (`social_bookings.schedule_id`); o status
  dele segue `SocialBooking::SCHEDULE_STATUS`. `ExpirePendingSchedules` usa `withoutSocial()`;
  `updateSchedulesStatus` e `destroyPending` recusam schedule social.
- Edição da pré-reserva (`updateDetails`, painel e `PUT /api/social/bookings/{id}`): tudo menos
  data e espaço, só em `requested` **e antes do contrato ok** (`SocialBooking::isEditable()`;
  depois, `contract_closed`). Campo ausente fica; `items`/`guests` substituem a lista.
- Valor = `base_price` + `extras_total` + `items_total` − `discount` (desconto só na aprovação,
  com motivo). Preços congelados na pré-reserva (itens copiam nome, código MV e preço).
- Convidados: lista até `max_guests` + `extra_guests` contratados; confirmada → `OneOffAccess` na
  data (Monitor de Acesso). Sócio edita pelo app até a véspera.
- `place_conflicts` vale nos dois sentidos (`Place::conflictingPlaceIds`) e entra no `checkColide`
  e na disponibilidade social. É também como se monta espaço combinado ("Sauna + Tênis").
- Recusas são `SocialBookingException` com `reason` estável; a API devolve `{message, reason}`.
- E-mails `social.*` num template só (`emails.social.status`) via `SocialBookingNotifier`.
- Espaço social não tem contator (o controller zera): a reserva acenderia a luz 24 h.
- Mobiliário (16/10/2026): mesmo catálogo das bebidas (`social_beverages.category` =
  `bebida`|`mobiliario`, `tables_per_unit`, `chairs_per_unit`, `max_quantity`); o nome da classe
  `SocialBeverage` é histórico. Base do espaço (`places.base_tables/base_chairs`) copiada na
  reserva; totais em `SocialBooking::tablesTotal()/chairsTotal()`. API: `/api/social/furniture`;
  `items[].item_id` (alias de `beverage_id`). Recusa `item_limit`.
- Horário: `places.event_duration_hours` (+ faixa `event_earliest_start/latest_start`) exige
  `start_time`; `end_time` = início + duração (pode virar o dia; `endsNextDay()`). A ocupação
  segue o dia inteiro. Recusa `invalid_time`.
- Prazo de contato: `contact_due_at` = `BusinessDays::deadline(criação, config social_bookings.contact_business_days)`
  (seg–sex, sem feriados). Pré-reserva do app avisa no sino quem tem `reservas.sociais` por setor
  ou individual (`AccessResolver::holderIds`, sem acesso total) — `SocialStaffNotifier`;
  vencido, `social:contact-reminders` avisa uma vez (`contact_overdue_notified_at`).
  Balcão nasce contatado; `registerContact`, contrato ok, aprovação e recusa marcam
  `contacted_at`. Scope `SocialBooking::pendingContact()` (não confundir com o método de
  instância `awaitingContact()`).
- Reserva completa no balcão (19/10/2026): `SocialCounterBookingService::book` faz `request` +
  `markContract` + `approve` (+ `ManualPaymentService::record`) numa transação, com `notify:
  false` nos passos e um e-mail só no fim. Sem baixa, termina `awaiting_payment` e o site
  mostra para o sócio pagar (dono = `member_id`). Formulário: `complete` (pede
  `reservas.sociais.aprovar`) e `paid_now` (pede também `reservas.pagamentos.baixa-manual`).
- API: `origin` (`site` | `secretaria`, por `created_by_user`) e `payment` (null ou
  `{paid_amount, paid_at, method, method_label, manual, refunded_amount}`).

## Copiar a configuração social entre bancos

`social:copy-config --from=lara_hml --to=lara [--dry-run] [--force]`
(`app/Console/Commands/CopySocialConfig.php`): grupos sociais + `week_days_place_group`, espaços,
`place_conflicts` e `social_beverages`, casando por nome (upsert, nunca apaga, ignora excluídos e
reservas). Colunas = interseção dos dois esquemas, sem id/carimbos; espaço social sai sem
contator. `--dry-run` só lê (não abre transação no destino). Teste:
`tests/Feature/Social/CopySocialConfigTest.php` (duas conexões SQLite).

## Baixa manual de pagamento (desde 19/10/2026)

- `App\Services\ManualPaymentService::record(scheduleIds, data, userId, notify)`: cria um
  `SchedulePayment` status 1 com `is_manual`, `recorded_by`, `manual_notes`, sem
  `payment_integration_id`; liga os schedules e põe status 1. Linhas travadas com
  `lockForUpdate` (corrida com o pagamento do site).
- Aceita schedules do mesmo sócio, em status 1 ou 3, sem pagamento. Social: sozinho, em
  `awaiting_payment`, valor = `total`; chama `SocialBookingService::markPaid`.
- E-mail: social → `social.confirmed`; quadra → `schedule.confirm` só para os que estavam
  pendentes (3). Recusas são `ManualPaymentException` (mensagem de tela).
- Formas: `SchedulePayment::MANUAL_METHODS` (`manual_cash`, `manual_pix`, `manual_card`,
  `manual_transfer`, `manual_other`); rótulos em `METHOD_LABELS` / `methodLabel()`.
  `is_manual` está **fora do fillable**: o `POST /api/schedule/payment` não cria baixa manual.
- Sem Rede: `isOnline()` esconde consulta/estorno; `RedeItauService::refundPayment` recusa
  pagamento manual com `InvalidArgumentException`.

## Imagens de grupo e espaço (desde 19/10/2026)

`image_vertical`, `image_horizontal` e `places.image` guardam **nome de arquivo** (`public/images`)
**ou URL** `https://` (imgur, postimg…). Grave com `ImageSource::resolve($request, $campo, $atual)`
(arquivo em `$campo`, link em `{$campo}_url`; páginas do imgur/postimg viram link direto; erro
como `ValidationException` no `{$campo}_url`) e leia com `ImageSource::url()`. As leituras
antigas com `str_contains(..., 'http')` continuam certas. Na API da iluminação há `image_url`.
`PlaceGroupService::store` passou a gravar as imagens (antes gravava o objeto do upload).

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
| `/api/social/*` | Espaços sociais: grupos, espaços, calendário, bebidas, limites, pré-reserva, convidados, desistência (`Api\SocialBookingApiController`, exige `login_token`). |
| `POST /api/schedule/payment` | Pagamento (`SchedulePaymentController::store`) — confirma e manda o e-mail. |
| `GET /api/schedule/generate-pdf` | PDF da agenda do dia. |
| `/api/login`, `/api/register`, `/api/check-member`… | Sócio (`MemberAuthController`); JWT no header `Session`, vence no fim do dia. |

Rede: `RedeItauService` (token em cache 55 s, `getTransaction`, `refundPayment` total/parcial,
`beginRefund` em lote).

## Peças

| Arquivo | Papel |
|---|---|
| `app/Http/Controllers/ScheduleController.php`, `ScheduleRulesController.php`, `PlaceGroupController.php`, `PlaceController.php` | Painel e API. |
| `app/Http/Controllers/SchedulePaymentController.php` | Pagamento, baixa manual (`storeManual`), consulta na Rede, estorno. |
| `app/Services/SchedulesService.php`, `ScheduleRulesService.php`, `SchedulePricingService.php`, `PlaceGroupService.php`, `PlaceService.php` | Regras. |
| `app/Services/RedeItauService.php`, `SchedulePaymentService.php`, `ManualPaymentService.php` | Pagamento. |
| `resources/views/payments/partials/manual-fields.blade.php` | Campos da baixa manual (forma, data, comprovante). |
| `app/Console/Commands/ExpirePendingSchedules.php` | Expira o hold (agendado). |
| `app/Services/Social/*`, `app/Models/Social/*`, `app/Http/Controllers/Social/*`, `Api/SocialBookingApiController.php` | Espaços sociais. |
| `app/Console/Commands/ExpireSocialBookings.php` | Expira reserva social aprovada e não paga no prazo. |
| `resources/views/location/social/*`, `emails/social/status.blade.php` | Telas e e-mail dos espaços sociais. |
| `resources/views/location/*` (agenda, `schedule/`, `placeGroup/`, `place/`, `rule/`), `payments/*` | Telas. |

## Testes

`tests/Feature/ScheduleInProgressSlotTest.php`, `tests/Unit/ScheduleProportionalPriceTest.php`,
`tests/Unit/ScheduleNotificationTest.php`, `tests/Feature/Rebrand/ReservasScreensTest.php`,
`tests/Feature/Social/*` (fluxo e API, sobre as migrations reais via `Tests\Concerns\CreatesSocialBookingSchema`),
`tests/Feature/Rebrand/EspacosSociaisScreensTest.php`, `tests/Feature/Payments/ManualPaymentTest.php`,
`tests/Feature/Rebrand/BaixaManualScreensTest.php`.
Confira o isolamento SQLite do `phpunit.xml` antes de rodar.
