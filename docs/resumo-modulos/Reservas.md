# Reservas — resumo para quem vai mexer

Documentação completa: [agendamento.md](../funcionalidades/agendamento.md) (grade de horários,
colisão, preço proporcional, e-mails), [espacos-e-grupos.md](../funcionalidades/espacos-e-grupos.md)
(cadastro base) e [socios.md](../funcionalidades/socios.md) (login do sócio, JWT).


## Identidade visual (11/11/2026)

Reservas tem desenho próprio, na mesma pegada do SIV (pedido do usuário). A ideia é a quadra.
Peças em `resources/css/app.css` (bloco "Reservas", tokens `--res-court`/`--res-clay`, escuros
nos dois temas, texto sempre branco):

- `res-court`: faixa verde com as linhas da quadra (contorno, meio e círculo, em `::before`/`::after`
  com `z-index: -1` — o conteúdo não precisa de nada). Na agenda leva a foto da arena de areia do
  clube (`public/images/auth/arena-areia.jpg`, `res-court-photo`, só no lg+).
- Agenda (`location/partials/header`): o dia em letra grande, troca de dia, PDF do dia e
  Configurações dentro da faixa, a **ocupação do dia** somando todos os locais (mesma regra de
  estado do `time-card`: bloqueado, confirmado `status 1`, pendente, passado, livre; ocupação =
  reservados ÷ reserváveis, sem bloqueados e passados) e atalhos `#mod-<slug>` para as
  modalidades. Cada local ganha a `res-strip` (uma casa por horário, `data-s` = estado).
- Gestão da reserva (`location/schedule/show`, refeita no `x-page`): o "ingresso" (`res-ticket`,
  recortes laterais por máscara em `--cut`, picote `res-perforation`, carimbo `res-stamp`),
  auditoria em linha do tempo e os painéis de vinculados, cancelamento e baixa manual — campos,
  nomes e regras iguais aos de antes.
- Espaços Sociais: a fila (contato atrasado, aguardando contato, em análise, aguardando
  pagamento) virou números grandes na faixa, cada um filtrando a lista.
- Todos os Agendamentos, Pagamentos, Contas do site e Locais e Grupos ficam com o padrão do sistema.

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
| Pagamentos | `payment.index`, `payment.show` (consulta ao vivo na Rede), `payment.export` (CSV, desde 04/11/2026) | `reservas.pagamentos` |
| Estorno | `payment.refund` | `reservas.pagamentos.estornar` |
| Baixa manual (pago fora da plataforma; botões no detalhe do agendamento e da reserva social) | `payment.manual` | `reservas.pagamentos.baixa-manual` |
| Espaços Sociais | `social-booking.index/create/store/show/details/guests/guests.pdf/contract.download/availability` | `reservas.sociais` |
| Espaços Sociais — decidir | `social-booking.contract/approve/refuse/cancel` (estornar pede também `reservas.pagamentos.estornar`) | `reservas.sociais.aprovar` |
| Bebidas dos espaços sociais (botão na lista) | `social-booking.beverages.*` | `reservas.configurar` |
| Galeria de fotos do espaço social (na edição do espaço, desde 10/11/2026) | `place-group.photos.*` | `reservas.configurar` |
| Locais e Grupos (aba desde 14/10/2026; também pelo botão Configurações da agenda) | `place-group.*`, `schedule-rules.store` | `reservas.configurar` |
| Contas do site (desde 23/10/2026: senha provisória, e-mail, cadastro manual, duplicadas — ver [Contas do site](Contas%20do%20site.md)) | `site-accounts.*` | `reservas.contas-site` |
| Luz da quadra na agenda (desde 05/11/2026; faixa no cartão da quadra com contator) | `schedule.lights.store` (`ScheduleLightingController`) | `reservas.luz` (+ `reservas.agendamentos` do grupo) |

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

## Galeria de fotos dos espaços sociais (desde 10/11/2026)

Carrossel do site. Guia: [espacos-sociais.md › Galeria](../funcionalidades/espacos-sociais.md#galeria-de-fotos-carrossel-do-site).

- **O navegador de quem visita o site não alcança o Lara.** A foto é **arquivo estático** em
  `public/images/galeria/{place_id}/` (o Apache entrega, sem rota PHP); a API devolve `path`
  relativo e o **servidor do Next.js** busca e serve no domínio dele. Não crie rota do Lara que
  entregue imagem.
- `place_photos` (`file`, `thumb`, `width`, `height`, `caption`, `position`, `active`);
  `Place::photos()` já vem em ordem. Formato da API num lugar só: `PlacePhoto::toApi()`.
- `PlacePhotoService` (GD, sem dependência nova): gira pelo EXIF, reduz a 1920 px + miniatura de
  480 px, sempre JPEG, nome aleatório (nunca sobrescreve: cache eterno no site). Envio tudo ou
  nada; 20 fotos por espaço; só espaço social; só upload (link não entra na galeria). "Usar como
  capa" grava uma **cópia** em `public/images` e põe em `places.image`. Recusas são
  `ValidationException` em `photos`. Sobe `memory_limit` para 512M durante o processamento.
- Raiz configurável `social_bookings.gallery_root` (vazio = `public/images`) — os testes apontam
  para uma pasta temporária; não grave foto de teste no `public/`.
- API: `GET /api/social-showcase` e `/api/social-showcase/places/{id}` (`SocialShowcaseApiController`,
  **só `api_token`**, vitrine sem login); `photos` também em `GET /api/social/groups/{group}/places`.
- `social:copy-config` copia as linhas (casa por espaço + `file`) e avisa os arquivos que faltam.
- Testes: `tests/Feature/Social/PlacePhotoGalleryTest.php`, `CopySocialConfigTest`,
  `SocialBookingApiTest`, `EspacosSociaisScreensTest::test_galeria_de_fotos_na_edicao_do_espaco`.

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

## Pagamentos: período e extração (desde 04/11/2026)

- `App\Services\SchedulePaymentReport` é a **fonte única dos filtros** da tela `payment.index` e do CSV
  `payment.export` (`GET /payments/exportar`, mesma permissão `reservas.pagamentos`, rota declarada antes de
  `/{schedulePayment}`): `member`, `status_id`, `payment_method` (`manual` = toda baixa manual), `date_from`/`date_to`
  (Y-m-d; o "até" vale o dia inteiro) e `period_by` — `paid` (padrão, `paid_at`) ou `use`
  (`schedules.start_schedule`, via `whereHas`). Filtro novo entra no `query()` e vale para os dois.
- CSV (`;`, BOM, vírgula decimal sem milhar): **uma linha por horário** (`HEADER`), em ordem de horário, com grupo,
  local, data, início, fim (com data quando vira o dia), valor e status do horário. `Valor pago` e `Estornado` só
  na primeira linha do pagamento (a soma fecha). Pagamento sem horário sai numa linha com as colunas do horário
  vazias. Percorre com `lazy(500)`.
- A lista ganhou a coluna "Local e horário" (até 2 horários; "+ N horários" leva ao detalhe).
- Não mexe na API do site: nenhum prompt para o agent do Next.js.
- Testes: `tests/Feature/Payments/PaymentReportTest.php` e `ReservasScreensTest::test_pagamentos_com_busca_e_metodo_por_extenso`.

## Luz da quadra pela agenda (desde 05/11/2026)

Faixa **Luz da quadra** no cartão de cada quadra com `contactor_id` em `/schedule`: campo de
minutos + **Acender**. `ScheduleLightingController@store` grava o **comando manual do Home
Assistant** (`ManualCommandService::apply(..., 'on', $minutes, 'Agenda de reservas', user_id)`):
um comando substitui o anterior, então acender quadra acesa **renova** o prazo para a nova
minutagem a partir de agora (pode encurtar). Prazo de 1 a `home_assistant.manual_max_minutes`
(720), truncado na meia-noite; o campo vem com `manual_default_minutes` (120). Recusa volta como
`warning` de sessão (a agenda não mostra `$errors`). O estado mostrado vem de
`ContactorStateResolver::resolveAll` (`ScheduleController::lightsFor`), só para quem tem
`reservas.luz`; se o cálculo falhar, a agenda abre sem a faixa. Os `<form>` ficam fora de
`#agenda` (atributo `form=` nos campos — o cartão já é um `<form>`). O Lara não aciona o relé:
o HA aplica no polling. Ver [Home Assistant](Home%20Assistant.md).

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
| `/api/social-showcase*` | Vitrine dos espaços sociais com a galeria de fotos, sem login (`Api\SocialShowcaseApiController`, só `api_token`). |
| `POST /api/schedule/payment` | Pagamento (`SchedulePaymentController::store`) — confirma e manda o e-mail. |
| `GET /api/schedule/generate-pdf` | PDF da agenda do dia. |
| `/api/login`, `/api/register`, `/api/check-member`… | Sócio (`MemberAuthController`); JWT no header `Session`, vence no fim do dia. |

Rede: `RedeItauService` (token em cache 55 s, `getTransaction`, `refundPayment` total/parcial,
`beginRefund` em lote).

## Peças

| Arquivo | Papel |
|---|---|
| `app/Http/Controllers/ScheduleController.php`, `ScheduleRulesController.php`, `PlaceGroupController.php`, `PlaceController.php` | Painel e API. |
| `app/Http/Controllers/ScheduleLightingController.php` | Acender a luz da quadra pela agenda. |
| `app/Http/Controllers/SchedulePaymentController.php` | Pagamento, baixa manual (`storeManual`), consulta na Rede, estorno. |
| `app/Services/SchedulesService.php`, `ScheduleRulesService.php`, `SchedulePricingService.php`, `PlaceGroupService.php`, `PlaceService.php` | Regras. |
| `app/Services/RedeItauService.php`, `SchedulePaymentService.php`, `ManualPaymentService.php` | Pagamento. |
| `app/Services/SchedulePaymentReport.php` | Filtros da lista de pagamentos e extração CSV. |
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
`tests/Feature/Rebrand/BaixaManualScreensTest.php`, `tests/Feature/Payments/PaymentReportTest.php`,
`tests/Feature/ScheduleLightingTest.php`.
Confira o isolamento SQLite do `phpunit.xml` antes de rodar.
