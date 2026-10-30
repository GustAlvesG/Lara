# 8. Rotas

As rotas estão divididas em quatro arquivos, registrados em `bootstrap/app.php`:

- `routes/web.php` — painel administrativo (views Blade, middleware `auth`).
- `routes/api.php` — API (prefixo `/api`), com `api_token` e `login_token`.
- `routes/auth.php` — autenticação Breeze (incluída ao final de `web.php` via `require`).
- `routes/console.php` — comandos de console agendados.

> Nota: as rotas de freelancer ficam em `api.php` (grupo `/api/telegram/freelancer/*`).

> **Limite de pedidos (`throttle:N,M`) é POR ROTA.** O alias `throttle` aponta para
> `App\Http\Middleware\ThrottleRequestsPerRoute` (registrado em `bootstrap/app.php`), que acrescenta
> a rota à chave. O do Laravel conta só por usuário/IP, e todas as rotas com `throttle:N,M` dividiam
> UM contador: o tablet de assinatura, que confere a sessão a cada 5 s, estourava o limite de
> "assinar" (10/min) e a pessoa via **429** ao salvar. Limitadores nomeados (`throttle:nome`, de
> `RateLimiter::for`) não mudam. Teste: `SignatureCaptureTest::test_conferir_a_sessao_nao_gasta_o_limite_de_assinar`.

> **Permissões:** a coluna de middleware desta página é anterior à reforma do acesso e não
> lista todas as permissões. A fonte da verdade é `php artisan route:list` e o `routes/web.php`,
> onde cada rota do painel tem `can:<permissão do catálogo>` — ver
> [Usuários, setores e permissões](funcionalidades/usuarios-e-permissoes.md).

---

## 8.1. Rotas Web (`routes/web.php`)

Todas, salvo `/`, `/members` e `/dashboard`, estão sob o middleware `auth`.

| Método | URI | Ação | Nome | Middleware |
|--------|-----|------|------|------------|
| GET | `/` | view `welcome` | — | — |
| GET | `/dashboard` | view `dashboard` | dashboard | auth, verified |
| GET | `/profile` | ProfileController@edit | profile.edit | auth |
| PATCH | `/profile` | ProfileController@update | profile.update | auth |
| DELETE | `/profile` | ProfileController@destroy | profile.destroy | auth |
| GET | `/members` | MemberController@index | members.index | auth |
| GET | `/accesses` | AccessController@index | accesses.index | auth |
| GET | `/accesses/{time}` | AccessController@findAccessByTime | accesses.findAccessByTime | auth |
| GET | `/members/{title}` | MemberController@findMemberByCode | information.findMemberByCode | auth |
| GET | `/information` | InformationController@index | information.index | auth, permission:view information |
| GET | `/information/create` | InformationController@create | information.create | auth, permission:create information |
| POST | `/information` | InformationController@store | information.store | auth, permission:create information\|edit information |
| GET | `/information/{information}` | InformationController@show | information.show | auth, permission:view information |
| GET | `/information/{information}/edit` | InformationController@edit | information.edit | auth, permission:edit information |
| PUT | `/information/{information}` | InformationController@update | information.update | auth, permission:edit information |
| DELETE | `/information/{information}` | InformationController@destroy | information.destroy | auth, permission:delete information |
| GET | `/information/{id}/history` | InformationController@history | information.history | auth, permission:view information |
| GET | `/parking/search` | ParkingController@search | parking.search | auth, permission:search parking |
| POST | `/parking/find` | ParkingController@show | parking.show | auth, permission:search parking |
| GET | `/company` | CompanyController@index | company.index | auth |
| GET | `/company/create` | CompanyController@create | company.create | auth |
| POST | `/company` | CompanyController@store | company.store | auth |
| GET | `/company/{company}` | CompanyController@show | company.show | auth |
| GET | `/company/{company}/worker/create` | CompanyWorkerController@create | company.worker.create | auth |
| POST | `/company/{company}/worker` | CompanyWorkerController@store | company.worker.store | auth |
| DELETE | `/company/{company}/worker/{worker}` | CompanyWorkerController@destroy | company.worker.destroy | auth |
| GET | `/company/{company}/rules/create` | CompanyAccessRulesController@create | company.rules.create | auth |
| POST | `/company/{company}/rules` | CompanyAccessRulesController@store | company.rules.store | auth |
| GET | `/place-group` | PlaceGroupController@index | place-group.index | auth |
| GET | `/place-group/create` | PlaceGroupController@create | place-group.create | auth |
| POST | `/place-group` | PlaceGroupController@store | place-group.store | auth |
| GET | `/place-group/{placeGroup}` | PlaceGroupController@show | place-group.show | auth |
| GET | `/place-group/{placeGroup}/edit` | PlaceGroupController@edit | place-group.edit | auth |
| PUT | `/place-group/{placeGroup}` | PlaceGroupController@update | place-group.update | auth |
| GET | `/place-group/{id}/schedule/rule/create` | PlaceGroupController@createScheduleRule | place-group.createScheduleRule | auth |
| POST | `/place-group/schedule/rule` | ScheduleRulesController@store | schedule-rules.store | auth |
| GET | `/place-group/schedule/rule/{id}/edit` | PlaceGroupController@editScheduleRule | place-group.editScheduleRule | auth |
| PUT | `/place-group/schedule/rule/{id}` | PlaceGroupController@updateScheduleRule | place-group.updateScheduleRule | auth |
| DELETE | `/place-group/schedule/rule/{id}` | PlaceGroupController@destroyScheduleRule | place-group.destroyScheduleRule | auth |
| GET | `/place-group/{id}/place/create` | PlaceGroupController@createPlace | place-group.createPlace | auth |
| POST | `/place-group/place` | PlaceGroupController@storePlace | place-group.storePlace | auth |
| GET | `/place-group/place/{place_id}/edit` | PlaceGroupController@editPlace | place-group.editPlace | auth |
| PUT | `/place-group/place/{place_id}` | PlaceGroupController@updatePlace | place-group.updatePlace | auth |
| DELETE | `/place-group/place/{place_id}` | PlaceGroupController@destroyPlace | place-group.destroyPlace | auth |
| GET | `/schedule` | ScheduleController@index | schedule.index | auth |
| POST | `/schedule/filter` | ScheduleController@indexFilter | schedule.index.filter | auth |
| GET | `/schedule/create` | ScheduleController@create | schedule.create | auth |
| GET | `/schedule/group/{category}` | PlaceGroupController@indexByCategory | api.placegroup.indexByCategory | auth |
| GET | `/schedule/getDates/{place_id?}` | ScheduleRulesController@getScheduledDates | schedule.getScheduledDates | auth |
| GET | `/schedule/{id}` | ScheduleController@show | schedule.show | auth |
| PUT | `/schedule/update` | ScheduleController@update | schedule.update | auth |
| POST | `/schedule/store/web` | ScheduleController@store | schedule.store.web | auth |
| GET | `/users` | UserController@index | users.index | auth, can:usuarios.gerenciar |
| GET | `/users/{id}/edit` | UserController@edit | users.edit | auth, can:usuarios.gerenciar |
| PUT | `/users/{id}` | UserController@update | users.update | auth, can:usuarios.gerenciar |
| PUT | `/users/{id}/sectors` | UserController@updateSectors | users.sectors.update | auth, can:usuarios.gerenciar |
| PUT | `/users/{id}/permissions` | UserController@updatePermissions | users.permissions.update | auth, can:usuarios.gerenciar |
| GET | `/sectors` | SectorController@index | sectors.index | auth, can:setores.gerenciar |
| PUT | `/sectors/{id}/permissions` | SectorController@updatePermissions | sectors.permissions.update | auth, can:setores.gerenciar |
| GET | `/sectors/audit` | SectorController@audit | sectors.audit | auth, can:setores.gerenciar |
| GET | `/meu-setor/{sector}` | MySectorController@show | my-sector.show | auth (coordenador do setor) |
| GET | `/comp-time/upload` | CompTimeController@index | comp-time.index | auth |
| POST | `/comp-time/upload` | CompTimeController@store | comp-time.store | auth |
| POST | `/comp-time/filter` | CompTimeController@indexFilter | comp-time.index.filter | auth |
| POST | `/comp-time/details` | CompTimeController@showDetails | comp-time.show.details | auth |
| POST | `/comp-time/details/day` | CompTimeController@showDayDetails | comp-time.show.day.details | auth |
| GET | `/comp-time/recalculate` | CompTimeController@recalculateBalances | comp-time.recalculate | auth |
| GET | `/fleet` | Fleet\FleetController@index | fleet.index | auth, permission:manage fleet |
| POST | `/fleet/departure` | Fleet\FleetController@storeDeparture | fleet.departure | auth, permission:manage fleet |
| POST | `/fleet/return` | Fleet\FleetController@storeReturn | fleet.return | auth, permission:manage fleet |
| GET | `/fleet/trips` | Fleet\FleetController@trips | fleet.trips | auth, permission:manage fleet |
| POST | `/fleet/trips/{trip}/cancel` | Fleet\FleetController@cancelTrip | fleet.trips.cancel | auth, permission:manage fleet |
| GET | `/fleet/vehicles` | Fleet\FleetController@vehicles | fleet.vehicles | auth, permission:manage fleet |
| GET | `/fleet/vehicles/create` | Fleet\FleetController@createVehicle | fleet.vehicles.create | auth, permission:manage fleet |
| POST | `/fleet/vehicles` | Fleet\FleetController@storeVehicle | fleet.vehicles.store | auth, permission:manage fleet |
| GET | `/fleet/vehicles/{vehicle}/edit` | Fleet\FleetController@editVehicle | fleet.vehicles.edit | auth, permission:manage fleet |
| PUT | `/fleet/vehicles/{vehicle}` | Fleet\FleetController@updateVehicle | fleet.vehicles.update | auth, permission:manage fleet |
| DELETE | `/fleet/vehicles/{vehicle}` | Fleet\FleetController@destroyVehicle | fleet.vehicles.destroy | auth, permission:manage fleet |

### Espaços sociais (`social-booking.*`)

Guia: [Reservas de Espaços Sociais](funcionalidades/espacos-sociais.md). Rotas fixas antes de `/{booking}`.

| Método | URI | Ação | Nome | Middleware |
|--------|-----|------|------|------------|
| GET | `/social-bookings/bebidas` | Social\SocialBeverageController@index | social-booking.beverages.index | auth, can:reservas.configurar |
| POST | `/social-bookings/bebidas` | Social\SocialBeverageController@store | social-booking.beverages.store | auth, can:reservas.configurar |
| PUT | `/social-bookings/bebidas/{beverage}` | Social\SocialBeverageController@update | social-booking.beverages.update | auth, can:reservas.configurar |
| DELETE | `/social-bookings/bebidas/{beverage}` | Social\SocialBeverageController@destroy | social-booking.beverages.destroy | auth, can:reservas.configurar |
| GET | `/social-bookings` | Social\SocialBookingController@index | social-booking.index | auth, can:reservas.sociais |
| GET | `/social-bookings/create` | Social\SocialBookingController@create | social-booking.create | auth, can:reservas.sociais |
| POST | `/social-bookings` | Social\SocialBookingController@store | social-booking.store | auth, can:reservas.sociais |
| GET | `/social-bookings/availability` | Social\SocialBookingController@availability | social-booking.availability | auth, can:reservas.sociais |
| GET | `/social-bookings/{booking}` | Social\SocialBookingController@show | social-booking.show | auth, can:reservas.sociais |
| PUT | `/social-bookings/{booking}/details` | Social\SocialBookingController@updateDetails | social-booking.details | auth, can:reservas.sociais |
| PUT | `/social-bookings/{booking}/guests` | Social\SocialBookingController@updateGuests | social-booking.guests | auth, can:reservas.sociais |
| GET | `/social-bookings/{booking}/guests.pdf` | Social\SocialBookingController@guestsPdf | social-booking.guests.pdf | auth, can:reservas.sociais |
| GET | `/social-bookings/{booking}/contract` | Social\SocialBookingController@downloadContract | social-booking.contract.download | auth, can:reservas.sociais |
| POST | `/social-bookings/{booking}/contact` | Social\SocialBookingController@contact | social-booking.contact | auth, can:reservas.sociais |
| POST | `/social-bookings/{booking}/contract` | Social\SocialBookingController@contract | social-booking.contract | auth, can:reservas.sociais.aprovar |
| POST | `/social-bookings/{booking}/approve` | Social\SocialBookingController@approve | social-booking.approve | auth, can:reservas.sociais.aprovar |
| POST | `/social-bookings/{booking}/refuse` | Social\SocialBookingController@refuse | social-booking.refuse | auth, can:reservas.sociais.aprovar |
| POST | `/social-bookings/{booking}/cancel` | Social\SocialBookingController@cancel | social-booking.cancel | auth, can:reservas.sociais.aprovar (estorno pede também reservas.pagamentos.estornar) |
| POST | `/payments/manual` | SchedulePaymentController@storeManual | payment.manual | auth, can:reservas.pagamentos.baixa-manual (baixa manual: pago fora da plataforma; quadras e espaço social aprovado) |

### Torneios (`tournaments.*`)

Guia: [Torneios](funcionalidades/torneios.md). Catálogo de categorias, torneios e inscrições (desde a F3).

| Método | URI | Ação | Nome | Middleware |
|--------|-----|------|------|------------|
| GET | `/torneios/categorias` | Tournament\CategoryTemplateController@index | tournaments.categories.index | auth, can:torneios.gerenciar |
| POST | `/torneios/categorias` | Tournament\CategoryTemplateController@store | tournaments.categories.store | auth, can:torneios.gerenciar |
| PUT | `/torneios/categorias/{template}` | Tournament\CategoryTemplateController@update | tournaments.categories.update | auth, can:torneios.gerenciar |
| DELETE | `/torneios/categorias/{template}` | Tournament\CategoryTemplateController@destroy | tournaments.categories.destroy | auth, can:torneios.gerenciar |
| GET | `/torneios` | Tournament\TournamentController@index | tournaments.index | auth, can:torneios.gerenciar |
| GET | `/torneios/novo` | Tournament\TournamentController@create | tournaments.create | auth, can:torneios.gerenciar |
| POST | `/torneios` | Tournament\TournamentController@store | tournaments.store | auth, can:torneios.gerenciar |
| GET | `/torneios/{tournament}` | Tournament\TournamentController@show | tournaments.show | auth, can:torneios.gerenciar |
| GET | `/torneios/{tournament}/editar` | Tournament\TournamentController@edit | tournaments.edit | auth, can:torneios.gerenciar |
| PUT | `/torneios/{tournament}` | Tournament\TournamentController@update | tournaments.update | auth, can:torneios.gerenciar |
| PUT | `/torneios/{tournament}/situacao` | Tournament\TournamentController@status | tournaments.status | auth, can:torneios.gerenciar |
| POST | `/torneios/{tournament}/categorias` | Tournament\TournamentCategoryController@store | tournaments.event-categories.store | auth, can:torneios.gerenciar |
| PUT | `/torneios/{tournament}/categorias/{category}` | Tournament\TournamentCategoryController@update | tournaments.event-categories.update | auth, can:torneios.gerenciar |
| DELETE | `/torneios/{tournament}/categorias/{category}` | Tournament\TournamentCategoryController@destroy | tournaments.event-categories.destroy | auth, can:torneios.gerenciar |
| GET | `/torneios/{tournament}/inscricoes` | Tournament\TournamentRegistrationController@index | tournaments.registrations.index | auth, can:torneios.inscricoes |
| POST | `/torneios/{tournament}/inscricoes` | Tournament\TournamentRegistrationController@store | tournaments.registrations.store | auth, can:torneios.inscricoes |
| POST | `/torneios/inscricoes/{registration}/cancelar` | Tournament\TournamentRegistrationController@cancel | tournaments.registrations.cancel | auth, can:torneios.inscricoes (estorno: torneios.pagamentos.estornar) |
| POST | `/torneios/{tournament}/inscricoes/baixa` | Tournament\TournamentRegistrationController@manual | tournaments.registrations.manual | auth, can:torneios.pagamentos.baixa-manual |
| GET | `/torneios/{tournament}/categorias/{category}/times` | Tournament\TournamentTeamController@index | tournaments.teams.index | auth, can:torneios.inscricoes |
| POST | `/torneios/{tournament}/categorias/{category}/times` | Tournament\TournamentTeamController@store | tournaments.teams.store | auth, can:torneios.inscricoes |
| POST | `/torneios/{tournament}/categorias/{category}/times/sortear` | Tournament\TournamentTeamController@draw | tournaments.teams.draw | auth, can:torneios.inscricoes |
| PUT | `/torneios/{tournament}/categorias/{category}/times/{team}` | Tournament\TournamentTeamController@update | tournaments.teams.update | auth, can:torneios.inscricoes |
| DELETE | `/torneios/{tournament}/categorias/{category}/times/{team}` | Tournament\TournamentTeamController@destroy | tournaments.teams.destroy | auth, can:torneios.inscricoes |
| POST | `/torneios/{tournament}/categorias/{category}/times/{team}/atletas` | Tournament\TournamentTeamController@addMember | tournaments.teams.members.store | auth, can:torneios.inscricoes |
| DELETE | `/torneios/{tournament}/categorias/{category}/times/{team}/atletas/{registration}` | Tournament\TournamentTeamController@removeMember | tournaments.teams.members.destroy | auth, can:torneios.inscricoes |
| GET | `/torneios/{tournament}/categorias/{category}/chaveamento` | Tournament\TournamentBracketController@show | tournaments.bracket.show | auth (gerenciar ou resultados, no controller) |
| POST | `/torneios/{tournament}/categorias/{category}/chaveamento` | Tournament\TournamentBracketController@generate | tournaments.bracket.generate | auth, can:torneios.gerenciar |
| DELETE | `/torneios/{tournament}/categorias/{category}/chaveamento` | Tournament\TournamentBracketController@destroy | tournaments.bracket.destroy | auth, can:torneios.gerenciar |
| PUT | `/torneios/{tournament}/categorias/{category}/chaveamento/divulgar` | Tournament\TournamentBracketController@publish | tournaments.bracket.publish | auth, can:torneios.gerenciar |
| PUT | `/torneios/{tournament}/categorias/{category}/chaveamento/jogos/{match}` | Tournament\TournamentBracketController@schedule | tournaments.bracket.schedule | auth, can:torneios.gerenciar |
| PUT | `/torneios/{tournament}/categorias/{category}/jogos/{match}/resultado` | Tournament\TournamentResultController@record | tournaments.results.record | auth, can:torneios.resultados |
| DELETE | `/torneios/{tournament}/categorias/{category}/jogos/{match}/resultado` | Tournament\TournamentResultController@clear | tournaments.results.clear | auth, can:torneios.resultados |
| PUT | `/torneios/{tournament}/categorias/{category}/grupos/{group}/fechar` | Tournament\TournamentResultController@closeGroup | tournaments.results.groups.close | auth, can:torneios.resultados |
| DELETE | `/torneios/{tournament}/categorias/{category}/grupos/{group}/fechar` | Tournament\TournamentResultController@reopenGroup | tournaments.results.groups.reopen | auth, can:torneios.resultados |
| POST | `/torneios/{tournament}/codigo-resultados` | Tournament\TournamentResultController@generateCode | tournaments.results.code.store | auth, can:torneios.resultados |
| DELETE | `/torneios/{tournament}/codigo-resultados` | Tournament\TournamentResultController@revokeCode | tournaments.results.code.destroy | auth, can:torneios.resultados |
| GET | `/torneios/financeiro` | Tournament\TournamentFinanceController@index | tournaments.finance.index | auth, can:torneios.financeiro |
| GET | `/torneios/{tournament}/financeiro` | Tournament\TournamentFinanceController@show | tournaments.finance.show | auth, can:torneios.financeiro |
| GET | `/torneios/{tournament}/financeiro/csv` | Tournament\TournamentFinanceController@csv | tournaments.finance.csv | auth, can:torneios.financeiro |

### Contratos (`signature-contracts.*`, `signature-contract-library.*`)

Guia: [Contratos](funcionalidades/contratos.md). Quem pode cada ação: `SignatureContractPolicy` (setor do contrato + `assinatura.contratos`; validar = setor Gerência). Menu: Assinaturas → Contratos (`assinatura.contratos`).

| Método | URI | Controller@método | Nome |
|--------|-----|-------------------|------|
| GET | `/assinatura/contratos` | Signature\ContractController@index | signature-contracts.index |
| GET | `/assinatura/contratos/novo` | Signature\ContractController@create | signature-contracts.create |
| POST | `/assinatura/contratos` | Signature\ContractController@store | signature-contracts.store |
| GET | `/assinatura/contratos/contratadas` | Signature\ContractController@parties (60/min) | signature-contracts.parties |
| GET | `/assinatura/contratos/{c}` | Signature\ContractController@show | signature-contracts.show |
| GET | `/assinatura/contratos/{c}/editar` | Signature\ContractController@edit | signature-contracts.edit |
| PUT | `/assinatura/contratos/{c}` | Signature\ContractController@update | signature-contracts.update |
| POST | `/assinatura/contratos/{c}/previa` | Signature\ContractController@preview (60/min) | signature-contracts.preview |
| POST | `/assinatura/contratos/{c}/enviar` | Signature\ContractController@submit (20/min) | signature-contracts.submit |
| POST | `/assinatura/contratos/{c}/decisao` | Signature\ContractController@decide (20/min) | signature-contracts.decide |
| POST | `/assinatura/contratos/{c}/revisao-ia` | Signature\ContractController@aiReview (6/min; quem redige, no rascunho ou devolvido, e a Gerência) | signature-contracts.ai-review |
| POST | `/assinatura/contratos/{c}/reabrir` | Signature\ContractController@reopen | signature-contracts.reopen |
| POST | `/assinatura/contratos/{c}/cancelar` | Signature\ContractController@cancel | signature-contracts.cancel |
| POST | `/assinatura/contratos/{c}/aditivo` | Signature\ContractController@addendum | signature-contracts.addendum |
| POST | `/assinatura/contratos/{c}/documento` | Signature\ContractController@generateDocument (10/min) | signature-contracts.document |
| GET | `/assinatura/contratos/{c}/word` | Signature\ContractController@docx | signature-contracts.docx |
| POST | `/assinatura/contratos/{c}/modelo` | Signature\ContractController@saveModel | signature-contracts.save-model |
| POST | `/assinatura/contratos/{c}/observacoes/{comment}` | Signature\ContractController@resolveComment | signature-contracts.comments.resolve |
| POST/GET/DELETE | `/assinatura/contratos/{c}/anexos[/{anexo}]` | Signature\ContractController@storeAttachment / showAttachment / destroyAttachment | signature-contracts.attachments.* |
| GET | `/assinatura/contratos/{c}/biblioteca` | Signature\ContractLibraryController@json | signature-contracts.library |
| GET/POST | `/assinatura/contratos/biblioteca` | Signature\ContractLibraryController@index / store | signature-contract-library.index / store |
| PUT/DELETE | `/assinatura/contratos/biblioteca/{clausula}` | Signature\ContractLibraryController@update / destroy | signature-contract-library.update / destroy |
| DELETE | `/assinatura/contratos/biblioteca/modelos/{modelo}` | Signature\ContractLibraryController@destroyModel | signature-contract-library.models.destroy |

**Tablet do balcão — validação pela Gerência** (públicas, entrada por matrícula + PIN, cookie `lara_management`; Signature\ContractKioskController):

| Método | URI | Método | Nome |
|--------|-----|--------|------|
| GET | `/assinatura/kiosk/gerencia` | index (página) | quiosque.gerencia.index |
| POST | `/assinatura/kiosk/gerencia/entrar` | login (20/min) | quiosque.gerencia.login |
| GET | `/assinatura/kiosk/gerencia/contratos` | pending (120/min, batimento) | quiosque.gerencia.pending |
| GET | `/assinatura/kiosk/gerencia/contratos/{contract}` | show (60/min) | quiosque.gerencia.show |
| POST | `/assinatura/kiosk/gerencia/contratos/{contract}/decisao` | decide (20/min) | quiosque.gerencia.decide |
| GET | `/assinatura/kiosk/gerencia/contratos/{contract}/anexos/{attachment}` | attachment (60/min) | quiosque.gerencia.attachment |
| POST | `/assinatura/kiosk/gerencia/sair` | logout | quiosque.gerencia.logout |

### Ingressos (`tickets.*`)

Guia: [Ingressos](funcionalidades/ingressos.md). Tipo, lote e cupom presos ao evento (`scopeBindings`).

| Método | URI | Controller@método | Nome | Permissão |
|--------|-----|-------------------|------|-----------|
| GET | `/ingressos` | Ticket\TicketEventController@index | tickets.index | `acessar-ingressos` (qualquer `ingressos.*`) |
| GET | `/ingressos/{event}` | Ticket\TicketEventController@show | tickets.show | `acessar-ingressos` |
| GET | `/ingressos/novo` | Ticket\TicketEventController@create | tickets.create | ingressos.gerenciar |
| POST | `/ingressos` | Ticket\TicketEventController@store | tickets.store | ingressos.gerenciar |
| GET | `/ingressos/{event}/editar` | Ticket\TicketEventController@edit | tickets.edit | ingressos.gerenciar |
| PUT | `/ingressos/{event}` | Ticket\TicketEventController@update | tickets.update | ingressos.gerenciar |
| PUT | `/ingressos/{event}/situacao` | Ticket\TicketEventController@status | tickets.status | ingressos.gerenciar |
| POST | `/ingressos/{event}/virar-lote` | Ticket\TicketLotController@turn | tickets.lots.turn | ingressos.gerenciar |
| POST | `/ingressos/{event}/dias` | Ticket\TicketDayController@store | tickets.days.store | ingressos.gerenciar |
| PUT | `/ingressos/{event}/dias/{day}` | Ticket\TicketDayController@update | tickets.days.update | ingressos.gerenciar |
| DELETE | `/ingressos/{event}/dias/{day}` | Ticket\TicketDayController@destroy | tickets.days.destroy | ingressos.gerenciar |
| POST | `/ingressos/{event}/tipos` | Ticket\TicketTypeController@store | tickets.types.store | ingressos.gerenciar |
| PUT | `/ingressos/{event}/tipos/{type}` | Ticket\TicketTypeController@update | tickets.types.update | ingressos.gerenciar |
| DELETE | `/ingressos/{event}/tipos/{type}` | Ticket\TicketTypeController@destroy | tickets.types.destroy | ingressos.gerenciar |
| POST | `/ingressos/{event}/tipos/{type}/lotes` | Ticket\TicketLotController@store | tickets.types.lots.store | ingressos.gerenciar |
| PUT | `/ingressos/{event}/tipos/{type}/lotes/{lot}` | Ticket\TicketLotController@update | tickets.types.lots.update | ingressos.gerenciar |
| DELETE | `/ingressos/{event}/tipos/{type}/lotes/{lot}` | Ticket\TicketLotController@destroy | tickets.types.lots.destroy | ingressos.gerenciar |
| GET | `/ingressos/{event}/cupons` | Ticket\TicketCouponController@index | tickets.coupons.index | ingressos.cupons |
| POST | `/ingressos/{event}/cupons` | Ticket\TicketCouponController@store | tickets.coupons.store | ingressos.cupons |
| PUT | `/ingressos/{event}/cupons/{coupon}` | Ticket\TicketCouponController@update | tickets.coupons.update | ingressos.cupons |
| DELETE | `/ingressos/{event}/cupons/{coupon}` | Ticket\TicketCouponController@destroy | tickets.coupons.destroy | ingressos.cupons |
| GET | `/ingressos/{event}/pedidos` | Ticket\TicketOrderController@index | tickets.orders.index | ingressos.vendas |
| GET | `/ingressos/{event}/emitir` | Ticket\TicketOrderController@create | tickets.issue.create | ingressos.vendas |
| POST | `/ingressos/{event}/emitir` | Ticket\TicketOrderController@store | tickets.issue.store | ingressos.vendas + baixa-manual (pago por fora) ou cortesias (no controller) |
| GET | `/ingressos/pedidos/{order}` | Ticket\TicketOrderController@show | tickets.orders.show | ingressos.vendas |
| GET | `/ingressos/pedidos/{order}/pdf` | Ticket\TicketOrderController@pdf | tickets.orders.pdf | ingressos.vendas |
| POST | `/ingressos/pedidos/{order}/reenviar` | Ticket\TicketOrderController@resend | tickets.orders.resend | ingressos.vendas (throttle 10/min) |
| POST | `/ingressos/pedidos/{order}/cancelar` | Ticket\TicketOrderController@cancel | tickets.orders.cancel | ingressos.vendas (estorno: ingressos.pagamentos.estornar) |
| PUT | `/ingressos/pedidos/ingresso/{ticket}/titular` | Ticket\TicketOrderController@changeHolder | tickets.orders.holder | ingressos.vendas |
| POST | `/ingressos/pedidos/{order}/baixa` | Ticket\TicketOrderController@manual | tickets.orders.manual | ingressos.vendas + ingressos.pagamentos.baixa-manual |
| GET | `/ingressos/checkin` | Ticket\TicketCheckinController@index | tickets.checkin.index | ingressos.checkin |
| POST | `/ingressos/checkin/{event}` | Ticket\TicketCheckinController@store | tickets.checkin.store | ingressos.checkin (throttle 120/min) |
| GET | `/ingressos/financeiro` | Ticket\TicketFinanceController@index | tickets.finance.index | ingressos.financeiro |
| GET | `/ingressos/{event}/financeiro` | Ticket\TicketFinanceController@show | tickets.finance.show | ingressos.financeiro |
| GET | `/ingressos/{event}/financeiro/csv` | Ticket\TicketFinanceController@csv | tickets.finance.csv | ingressos.financeiro |

### Contas do site (`site-accounts.*`, `can:reservas.contas-site`)

| Método | URI | Ação | Nome | Middleware |
|---|---|---|---|---|
| GET | `/contas-site` | SiteAccountController@index | site-accounts.index | auth, can:reservas.contas-site |
| POST | `/contas-site` | SiteAccountController@store | site-accounts.store | auth, can:reservas.contas-site, throttle 30/min |
| GET | `/contas-site/{member}` | SiteAccountController@show | site-accounts.show | auth, can:reservas.contas-site |
| PUT | `/contas-site/{member}` | SiteAccountController@update | site-accounts.update | auth, can:reservas.contas-site |
| POST | `/contas-site/{member}/multiclubes` | SiteAccountController@pull | site-accounts.pull | auth, can:reservas.contas-site |
| POST | `/contas-site/{member}/senha` | SiteAccountController@password | site-accounts.password | auth, can:reservas.contas-site, throttle 30/min |
| DELETE | `/contas-site/{member}` | SiteAccountController@destroy | site-accounts.destroy | auth, can:reservas.contas-site |
| POST | `/contas-site/{member}/reativar` | SiteAccountController@restore | site-accounts.restore | auth, can:reservas.contas-site |

### Disparo de E-mails (`email-campaigns.*`, `email-suppressions.*`, `can:disparo-emails`)

Guia: [Disparo de E-mails](funcionalidades/disparo-emails.md). A tela só muda a situação da campanha; quem envia é o `email-campaigns:send`. `{campaign}` só número.

| Método | URI | Ação | Nome |
|---|---|---|---|
| GET | `/disparo-emails` | EmailCampaign\EmailCampaignController@index | email-campaigns.index |
| GET | `/disparo-emails/nova` | …@create | email-campaigns.create |
| POST | `/disparo-emails` | …@store | email-campaigns.store |
| GET | `/disparo-emails/modelo-lista/{format}` | …@listTemplate (`csv`/`xlsx`) | email-campaigns.list-template |
| POST | `/disparo-emails/imagens` | …@uploadImage → JSON `{url}` | email-campaigns.images.store |
| POST | `/disparo-emails/compor` | …@compose → JSON `{html}` (o e-mail do editor visual, para a prévia) | email-campaigns.compose |
| GET | `/disparo-emails/imagens/{file}` | …@showImage (só para a prévia; no e-mail vai embutida) | email-campaigns.images.show |
| GET | `/disparo-emails/{campaign}` | …@show | email-campaigns.show |
| GET | `/disparo-emails/{campaign}/editar` | …@edit (rascunho ou pausada) | email-campaigns.edit |
| PUT | `/disparo-emails/{campaign}` | …@update | email-campaigns.update |
| DELETE | `/disparo-emails/{campaign}` | …@destroy (rascunho, concluída ou cancelada) | email-campaigns.destroy |
| GET | `/disparo-emails/{campaign}/previa` | …@preview (HTML, CSP sem script) | email-campaigns.preview |
| GET | `/disparo-emails/{campaign}/andamento` | …@progress → JSON | email-campaigns.progress |
| POST | `/disparo-emails/{campaign}/teste` | …@sendTest (na hora) | email-campaigns.test |
| POST | `/disparo-emails/{campaign}/enviar` | …@start (`when=now` ou `schedule`, `scheduled_at`) | email-campaigns.start |
| POST | `/disparo-emails/{campaign}/pausar` · `/retomar` · `/cancelar` · `/desagendar` | …@pause · @resume · @cancel · @unschedule | email-campaigns.pause · .resume · .cancel · .unschedule |
| POST | `/disparo-emails/{campaign}/reenviar-falhas` | …@retryFailed | email-campaigns.retry |
| POST | `/disparo-emails/{campaign}/duplicar` | …@duplicate | email-campaigns.duplicate |
| POST | `/disparo-emails/{campaign}/destinatarios` | …@importRecipients (`list`, `mode=append` ou `replace`) | email-campaigns.recipients.import |
| DELETE | `/disparo-emails/{campaign}/destinatarios` | …@clearRecipients (só rascunho) | email-campaigns.recipients.clear |
| GET | `/disparo-emails/{campaign}/destinatarios/exportar` | …@exportRecipients (CSV) | email-campaigns.recipients.export |
| GET | `/disparo-emails/nao-enviar` | EmailCampaign\EmailSuppressionController@index | email-suppressions.index |
| POST | `/disparo-emails/nao-enviar` | …@store (vários de uma vez) | email-suppressions.store |
| DELETE | `/disparo-emails/nao-enviar/{suppression}` | …@destroy | email-suppressions.destroy |

### Replay — vídeos das quadras (`can:replay`)

Quatro telas, quatro etapas do mesmo trabalho: formato, layout, câmera, vídeos gravados.

| Método | URI | Ação | Nome |
|--------|-----|------|------|
| GET | `/replay` | redirect | replay.index |
| GET | `/replay/configuracoes` | Replay\Web\SettingController@index | replay.settings.index |
| POST | `/replay/configuracoes` | Replay\Web\SettingController@store | replay.settings.store |
| DELETE | `/replay/configuracoes/{setting}` | Replay\Web\SettingController@destroy | replay.settings.destroy |
| GET | `/replay/layouts` | Replay\Web\LayoutController@index | replay.layouts.index |
| GET | `/replay/layouts/create` | Replay\Web\LayoutController@create | replay.layouts.create |
| POST | `/replay/layouts` | Replay\Web\LayoutController@store | replay.layouts.store |
| GET | `/replay/layouts/{layout}/edit` | Replay\Web\LayoutController@edit | replay.layouts.edit |
| PUT | `/replay/layouts/{layout}` | Replay\Web\LayoutController@update | replay.layouts.update |
| DELETE | `/replay/layouts/{layout}` | Replay\Web\LayoutController@destroy | replay.layouts.destroy |
| POST | `/replay/layouts/{layout}/logos` | Replay\Web\LayoutController@storeLogo | replay.layouts.logos.store |
| PUT | `/replay/layouts/{layout}/itens` | Replay\Web\LayoutController@updateItems | replay.layouts.items.update |
| DELETE | `/replay/layouts/{layout}/itens/{item}` | Replay\Web\LayoutController@destroyItem | replay.layouts.items.destroy |
| POST | `/replay/layouts/{layout}/render` | Replay\Web\LayoutController@rerender | replay.layouts.rerender |
| GET | `/replay/cameras` | Replay\Web\CameraController@index | replay.cameras.index |
| POST | `/replay/cameras` | Replay\Web\CameraController@store | replay.cameras.store |
| PUT | `/replay/cameras/{camera}` | Replay\Web\CameraController@update | replay.cameras.update |
| DELETE | `/replay/cameras/{camera}` | Replay\Web\CameraController@destroy | replay.cameras.destroy |
| GET | `/replay/videos` | Replay\Web\VideoController@index | replay.videos.index |
| DELETE | `/replay/videos/{video}` | Replay\Web\VideoController@destroy | replay.videos.destroy |
| GET | `/replay/arquivo/{uuid}` | Replay\VideoFileController@show — **pública**, 120/min; refaz a cópia do FTP e redireciona | replay.videos.file |

---

## 8.2. Rotas da API (`routes/api.php`)

Prefixo `/api`. Legenda de middleware: **T** = `api_token`, **L** = `login_token`.

### Públicas (sem token)
| Método | URI | Ação |
|--------|-----|------|
| GET | `/api/user` | closure (auth:sanctum) |
| GET | `/api/ping` | closure → `{"message":"pong"}` |
| GET | `/api/test` | TestController@index |
| GET | `/api/schedule/generate-pdf` | ScheduleController@generateDailySchedulePDF |
| GET | `/api/whatsapp/webhook` | WhatsAppController@verifyWebhook |
| POST | `/api/whatsapp/webhook` | WhatsAppController@handleWebhook |
| POST | `/api/whatsapp/send-message` | WhatsAppController@sendMessage |
| POST | `/api/company-access/validate-access` | CompanyAccessRulesController@validateCompanyAccess |
| POST | `/api/company-access/register-access` | CompanyAccessRulesController@registerAccess |
| POST | `/api/company-access/register-worker-access` | CompanyAccessRulesController@registerWorkerAccess |
| POST | `/api/company-access/register-freelancer-access` | CompanyAccessRulesController@registerFreelancerAccess |
| POST | `/api/company-access/register-one-off-access` | CompanyAccessRulesController@registerOneOffAccess |
| POST | `/api/company-access/register-ticket-access` | CompanyAccessRulesController@registerTicketAccess (ingresso: faz o check-in) |
| GET | `/api/schedule/home-assistant/automation` | ScheduleController@homeAssistantAutomation |
| POST | `/api/schedule/home-assistant/contactors/{entity_id}/manual` | HomeAssistantApiController@manual (comando manual, `throttle:30,1`) |
| GET | `/api/freelancer/dinners` | Freelancer\DinnerApiController@index (jantar dos freelancers, lida pela cozinha) |

### Replay — sistema de captura (Sanctum, ability `replay:operate`)

Integração por **pull**: o sistema de captura consulta; o Lara nunca chama o outro lado.
Token: `php artisan replay:token {nome}`. Contrato completo em
[docs/replay-api.md](replay-api.md).

| Método | URI | Ação | Limite |
|--------|-----|------|--------|
| GET | `/api/replay/ping` | closure — diagnóstico de token | 60/min |
| GET | `/api/replay/cameras` | Replay\Api\CameraController@index | 120/min |
| GET | `/api/replay/cameras/{external_id}` | Replay\Api\CameraController@show | 120/min |
| POST | `/api/replay/cameras/{external_id}/heartbeat` | Replay\Api\CameraController@heartbeat | 120/min |
| POST | `/api/replay/cameras/{external_id}/videos` | Replay\Api\VideoController@store | 300/min |

### Replay — site de locação

| Método | URI | Ação | Middleware |
|--------|-----|------|------------|
| GET | `/api/replay/places` | Replay\Api\PortalController@places | T |
| GET | `/api/replay/places/{place}/videos` | Replay\Api\PortalController@placeVideos | T |
| GET | `/api/replay/my-videos` | Replay\Api\PortalController@myVideos | T + L |

A galeria por quadra é **aberta a qualquer visitante do site** e nunca revela quem é o sócio da
reserva (só o booleano `has_member`). `my-videos` resolve o sócio pelo **próprio JWT** — não há
id na URL, para que trocar um número não dê acesso aos vídeos de outro.

### Assinatura remota — site externo (`signature_remote_api`)

Chamada pelo **servidor** do site, com token próprio (`Authorization: Bearer
<SIGNATURE_REMOTE_API_TOKEN>`, não o `API_TOKEN`) e, se configurada, só do IP do site
(`SIGNATURE_REMOTE_ALLOWED_IPS`). **S** = sessão no header `X-Signature-Session`
(`signature_remote_session`). Limites por IP de quem assina (`X-Signer-Ip`) + rota. Contrato em
[assinatura-remota-prompt-site.md](funcionalidades/assinatura-remota-prompt-site.md).

| Método | URI | Ação | Sessão | Limite |
|--------|-----|------|--------|--------|
| POST | `/api/assinatura/remota/link` | Api\RemoteSignatureApiController@link | — | 20/min |
| POST | `/api/assinatura/remota/abrir` | Api\RemoteSignatureApiController@open | — | 20/min |
| GET | `/api/assinatura/remota/sessao` | Api\RemoteSignatureApiController@session | S | 120/min |
| GET | `/api/assinatura/remota/pdf` | Api\RemoteSignatureApiController@pdf | S | 120/min |
| POST | `/api/assinatura/remota/respostas` | Api\RemoteSignatureApiController@answers | S | 120/min |
| POST | `/api/assinatura/remota/visualizado` | Api\RemoteSignatureApiController@viewed | S | 120/min |
| POST | `/api/assinatura/remota/identidade` | Api\RemoteSignatureApiController@identity | S | 120/min |
| GET | `/api/assinatura/remota/anexos` | Api\RemoteSignatureApiController@attachments | S | 120/min |
| POST | `/api/assinatura/remota/anexos` | Api\RemoteSignatureApiController@storeAttachment | S | 120/min |
| DELETE | `/api/assinatura/remota/anexos/{anexo}` | Api\RemoteSignatureApiController@destroyAttachment | S | 120/min |
| POST | `/api/assinatura/remota/assinar` | Api\RemoteSignatureApiController@sign | S | 120/min |
| POST | `/api/assinatura/remota/recusar` | Api\RemoteSignatureApiController@refuse | S | 120/min |
| POST | `/api/assinatura/remota/encerrar` | Api\RemoteSignatureApiController@leave | S | 120/min |

No painel (web, `auth`, policy `release`): `POST /assinatura/documentos/{doc}/signatarios/{s}/link-remoto`
(`signature-documents.remote-link.store`) e `DELETE /assinatura/documentos/{doc}/links-remotos/{link}`
(`signature-documents.remote-link.destroy`).

### Com `api_token` (T)
| Método | URI | Ação |
|--------|-----|------|
| GET | `/api/fleet/vehicles` | FleetApiController@vehicles |
| GET | `/api/fleet/trips/open` | FleetApiController@openTrips |
| GET | `/api/fleet/trips` | FleetApiController@trips |
| GET | `/api/fleet/drivers` | FleetApiController@drivers |
| POST | `/api/fleet/departure` | FleetApiController@departure |
| POST | `/api/fleet/return` | FleetApiController@returnTrip |
| POST | `/api/fleet/trips/{trip}/cancel` | FleetApiController@cancel |
| POST | `/api/telegram/get-contacts` | TelegramContactController@find |
| POST | `/api/telegram/contacts` | TelegramContactController@store |
| PUT | `/api/telegram/contacts/{id}` | TelegramContactController@update |
| GET | `/api/telegram/freelancer/freelancer/{cpf}` | FreelancerController@show |
| POST | `/api/telegram/freelancer/freelancer` | FreelancerController@store |
| GET | `/api/telegram/freelancer/functions` | FunctionFreelancerController@index |
| POST | `/api/telegram/freelancer/function` | FunctionFreelancerController@store |
| POST | `/api/telegram/freelancer/service` | FreelancerServiceController@store |
| POST | `/api/login` | MemberAuthController@login |
| POST | `/api/register` | MemberAuthController@register |
| POST | `/api/check-member` | MemberAuthController@checkMember |
| PUT | `/api/change-password` | MemberAuthController@changePassword |
| POST | `/api/send-email` | EmailController@submit |
| GET | `/api/debug-mail` | closure (dump de config de e-mail) |

### Torneios — site de locação (`api.tournaments.*`)

Contrato completo em [torneios.md](funcionalidades/torneios.md#api-do-site-apitournamentapicontroller). T = `api_token`, L = `login_token`.

| Método | URI | Ação | Auth |
|--------|-----|------|------|
| GET | `/api/tournaments` | Api\TournamentApiController@index | T |
| GET | `/api/tournaments/{id}` | Api\TournamentApiController@show | T |
| GET | `/api/tournaments/{id}/categories/{categoryId}/bracket` | Api\TournamentApiController@bracket | T |
| GET | `/api/tournaments/{id}/eligibility` | Api\TournamentApiController@eligibility | T + L |
| POST | `/api/tournaments/{id}/registrations` | Api\TournamentApiController@register | T + L (throttle 20/min) |
| GET | `/api/tournaments/my-registrations` | Api\TournamentApiController@mine | T + L |
| POST | `/api/tournaments/payment` | Api\TournamentApiController@pay | T + L (throttle 20/min) |
| DELETE | `/api/tournaments/registrations/{id}` | Api\TournamentApiController@withdraw | T + L |
| GET | `/api/tournaments/my-teams` | Api\TournamentApiController@myTeams | T + L |
| POST | `/api/tournaments/categories/{id}/teams` | Api\TournamentApiController@createTeam | T + L (throttle 20/min) |
| GET | `/api/tournaments/member-lookup` | Api\TournamentApiController@lookup | T + L (throttle 30/min) |
| POST | `/api/tournaments/teams/{id}/invites` | Api\TournamentApiController@invite | T + L (throttle 20/min) |
| GET | `/api/tournaments/my-invites` | Api\TournamentApiController@myInvites | T + L |
| POST | `/api/tournaments/invites/{id}/accept` | Api\TournamentApiController@acceptInvite | T + L |
| POST | `/api/tournaments/invites/{id}/decline` | Api\TournamentApiController@declineInvite | T + L |
| DELETE | `/api/tournaments/invites/{id}` | Api\TournamentApiController@cancelInvite | T + L |
| POST | `/api/tournaments/{id}/staff/check` | Api\TournamentStaffApiController@check | T + código no corpo (throttle 10/min) |
| GET | `/api/tournaments/{id}/staff/matches` | Api\TournamentStaffApiController@matches | T + header `Tournament-Code` (throttle 60/min) |
| PUT | `/api/tournaments/{id}/staff/matches/{matchId}/result` | Api\TournamentStaffApiController@record | T + header `Tournament-Code` (throttle 60/min) |

### Ingressos — site de locação (`api.tickets.*`)

Contrato completo em [ingressos.md](funcionalidades/ingressos.md#api-do-site-apiticketapicontroller). T = `api_token`, L = `login_token`, G = header `Ticket-Order-Token` (pedido do não sócio).

| Método | URI | Ação | Auth |
|--------|-----|------|------|
| GET | `/api/tickets/events` | Api\TicketApiController@index | T |
| GET | `/api/tickets/events/{id}` | Api\TicketApiController@show | T |
| POST | `/api/tickets/events/{id}/coupon-check` | Api\TicketApiController@checkCoupon | T (throttle 20/min) |
| POST | `/api/tickets/events/{id}/guest-orders` | Api\TicketApiController@storeGuest | T (throttle 10/min) |
| POST | `/api/tickets/guest-orders/resend-link` | Api\TicketApiController@resendGuestLink | T (throttle 5/min) |
| POST | `/api/tickets/guest-access/code` | Api\TicketGuestAccessController@requestCode | T (throttle 5/min) |
| POST | `/api/tickets/guest-access/verify` | Api\TicketGuestAccessController@verify | T (throttle 10/min) |
| GET | `/api/tickets/guest-access/orders` | Api\TicketGuestAccessController@orders | T + `Ticket-Access-Token` (throttle 60/min) |
| GET | `/api/tickets/guest-orders/{id}` | Api\TicketApiController@showGuest | T + G (throttle 60/min) |
| POST | `/api/tickets/guest-orders/{id}/payment` | Api\TicketApiController@payGuest | T + G (throttle 60/min) |
| DELETE | `/api/tickets/guest-orders/{id}` | Api\TicketApiController@withdrawGuest | T + G (throttle 60/min) |
| GET | `/api/tickets/guest-orders/{id}/pdf` | Api\TicketApiController@pdfGuest | T + G (throttle 60/min) |
| GET | `/api/tickets/my-title` | Api\TicketApiController@myTitle | T + L (throttle 30/min) |
| POST | `/api/tickets/events/{id}/orders` | Api\TicketApiController@storeMember | T + L (throttle 20/min) |
| GET | `/api/tickets/my-orders` | Api\TicketApiController@mine | T + L |
| GET | `/api/tickets/orders/{id}` | Api\TicketApiController@showMember | T + L |
| POST | `/api/tickets/orders/{id}/payment` | Api\TicketApiController@payMember | T + L (throttle 20/min) |
| DELETE | `/api/tickets/orders/{id}` | Api\TicketApiController@withdrawMember | T + L |
| GET | `/api/tickets/orders/{id}/pdf` | Api\TicketApiController@pdfMember | T + L |

### Com `api_token` + `login_token` (T + L)
| Método | URI | Ação | Obs. |
|--------|-----|------|------|
| GET | `/api/verify-token` | LoginTokenController@validate | |
| POST | `/api/member/by-title` | MemberController@getByTitle | sem L |
| PUT | `/api/member/update` | MemberAuthController@update | |
| GET | `/api/places/group` | PlaceGroupController@index_api | |
| GET | `/api/places/group/{category}` | PlaceGroupController@indexByCategory | |
| GET | `/api/places/group/rules/{id}` | PlaceGroupController@scheduleRules | |
| POST | `/api/places` | PlaceController@indexByGroup | |
| GET | `/api/place/{id}` | PlaceController@show | |
| GET | `/api/schedule` | PlaceGroupController@index_api | |
| POST | `/api/schedule` | ScheduleController@store | sem L |
| PUT | `/api/schedule/{id}/update` | ScheduleController@update | |
| POST | `/api/schedule/place` | ScheduleController@indexByPlace | POST que funciona como GET |
| GET | `/api/schedule/member/{member_id}` | ScheduleController@indexByMember | |
| PUT | `/api/schedule/update-status` | ScheduleController@updateStatus | |
| POST | `/api/schedule/payment` | SchedulePaymentController@store | |
| DELETE | `/api/schedule/delete-pending` | ScheduleController@destroyPending | |
| POST | `/api/schedule/time-options` | ScheduleRulesController@getTimeOptions | sem L |
| GET | `/api/lighting/availability` | Api\MemberLightingController@availability | janela de hoje + acionamento vigente; `throttle:120,1` |
| GET | `/api/lighting/groups` | Api\MemberLightingController@groups | grupos com quadra liberada; `throttle:120,1` |
| GET | `/api/lighting/groups/{group}/places` | Api\MemberLightingController@places | quadras do grupo; `throttle:120,1` |
| GET | `/api/lighting/activations` | Api\MemberLightingController@history | últimos 30 do sócio; `throttle:60,1` |
| POST | `/api/lighting/places/{place}/activate` | Api\MemberLightingController@activate | acende a luz; `throttle:20,1` |
| POST | `/api/lighting/release` | Api\MemberLightingController@release | devolve a quadra; `throttle:20,1` |
| GET | `/api/social/groups` | Api\SocialBookingApiController@groups | grupos de categoria `social` |
| GET | `/api/social/groups/{group}/places` | Api\SocialBookingApiController@places | espaços ativos do grupo social |
| GET | `/api/social/availability` | Api\SocialBookingApiController@availability | `?place_id&month=YYYY-MM` → status de cada dia |
| GET | `/api/social/beverages` | Api\SocialBookingApiController@beverages | bebidas ativas |
| GET | `/api/social/furniture` | Api\SocialBookingApiController@furniture | mobiliário ativo (mesas, cadeiras, jogos) |
| GET | `/api/social/limits` | Api\SocialBookingApiController@limits | `?place_group_id` → limites do sócio |
| GET | `/api/social/bookings` | Api\SocialBookingApiController@index | reservas sociais do sócio |
| POST | `/api/social/bookings` | Api\SocialBookingApiController@store | pré-reserva; `throttle:20,1` |
| GET | `/api/social/bookings/{id}` | Api\SocialBookingApiController@show | 404 se não for do sócio |
| PUT | `/api/social/bookings/{id}` | Api\SocialBookingApiController@update | edita a pré-reserva (menos data e espaço) até o contrato ok; `throttle:30,1` |
| PUT | `/api/social/bookings/{id}/guests` | Api\SocialBookingApiController@updateGuests | lista inteira; até a véspera; `throttle:30,1` |
| DELETE | `/api/social/bookings/{id}` | Api\SocialBookingApiController@destroy | desistência, só antes de pagar |

---

## 8.3. Rotas de Autenticação (`routes/auth.php`)

| Método | URI | Ação | Nome | Middleware |
|--------|-----|------|------|------------|
| GET | `/register` | RegisteredUserController@create | register | guest |
| POST | `/register` | RegisteredUserController@store | — | guest |
| GET | `/login` | AuthenticatedSessionController@create | login | guest |
| POST | `/login` | AuthenticatedSessionController@store | — | guest |
| GET | `/forgot-password` | PasswordResetLinkController@create | password.request | guest |
| POST | `/forgot-password` | PasswordResetLinkController@store | password.email | guest |
| GET | `/reset-password/{token}` | NewPasswordController@create | password.reset | guest |
| POST | `/reset-password` | NewPasswordController@store | password.store | guest |
| GET | `/verify-email` | EmailVerificationPromptController | verification.notice | auth |
| GET | `/verify-email/{id}/{hash}` | VerifyEmailController | verification.verify | auth, signed, throttle:6,1 |
| POST | `/email/verification-notification` | EmailVerificationNotificationController@store | verification.send | auth, throttle:6,1 |
| GET | `/confirm-password` | ConfirmablePasswordController@show | password.confirm | auth |
| POST | `/confirm-password` | ConfirmablePasswordController@store | — | auth |
| PUT | `/password` | PasswordController@update | password.update | auth |
| POST | `/logout` | AuthenticatedSessionController@destroy | logout | auth |

---

## 8.4. Console (`routes/console.php`)

| Comando | Descrição | Agendamento |
|---------|-----------|-------------|
| `inspire` | Exibe uma frase inspiradora (comando padrão do Laravel). | — |
| `social:contact-reminders` | Avisa no sino quem tem `reservas.sociais` das pré-reservas com prazo de contato vencido (uma vez cada). | a cada 15 minutos |
| `social:expire-bookings` | Expira reserva de espaço social aprovada e não paga até `payment_due_at` (libera a data e avisa o sócio). | a cada 5 minutos |
| `ingressos:expirar-pedidos` | Expira pedido de ingresso pendente com o prazo de pagamento (`TICKETS_HOLD_MINUTES`) vencido. | a cada minuto |
| `email-campaigns:send [--limit=] [--seconds=50]` | Disparo de E-mails: promove as agendadas cuja hora chegou e manda o próximo lote das campanhas em andamento (até `EMAIL_CAMPAIGNS_PER_MINUTE`). `withoutOverlapping` + `runInBackground`. | a cada minuto |
| `ingressos:dados-teste` | Gera ou limpa (`--so-limpar`) eventos `[TESTE]` de Ingressos em homologação; recusa produção. | manual |
| `social:copy-config --from=lara_hml --to=lara [--dry-run] [--force]` | Copia a configuração dos espaços sociais (grupos, dias, espaços, conflitos, catálogo) de um banco para outro, casando pelo nome; não apaga nem copia reservas. | manual |

> Não há comandos de console customizados (`app/Console/Commands/` não contém classes próprias).
