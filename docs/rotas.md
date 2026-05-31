# 8. Rotas

As rotas estão divididas em quatro arquivos, registrados em `bootstrap/app.php`:

- `routes/web.php` — painel administrativo (views Blade, middleware `auth`).
- `routes/api.php` — API (prefixo `/api`), com `api_token` e `login_token`.
- `routes/auth.php` — autenticação Breeze (incluída ao final de `web.php` via `require`).
- `routes/console.php` — comandos de console agendados.

> Nota: as rotas de freelancer ficam em `api.php` (grupo `/api/telegram/freelancer/*`).

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
| GET | `/information` | InformationController@index | information.index | auth |
| GET | `/information/create` | InformationController@create | information.create | auth |
| POST | `/information` | InformationController@store | information.store | auth |
| GET | `/information/{information}` | InformationController@show | information.show | auth |
| GET | `/information/{information}/edit` | InformationController@edit | information.edit | auth |
| PUT | `/information/{information}` | InformationController@update | information.update | auth |
| DELETE | `/information/{information}` | InformationController@destroy | information.destroy | auth |
| GET | `/information/{id}/history` | InformationController@history | information.history | auth |
| GET | `/data-info/create` | DataInfoController@create | data-info.create | auth |
| POST | `/data-info` | DataInfoController@store | data-info.store | auth |
| GET | `/data-info/{dataInfo}/edit` | DataInfoController@edit | data-info.edit | auth |
| PUT | `/data-info/{dataInfo}` | DataInfoController@update | data-info.update | auth |
| DELETE | `/data-info/{dataInfo}` | DataInfoController@destroy | data-info.destroy | auth |
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
| GET | `/videowall` | VideoWallController@index | videowall.index | auth |
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
| GET | `/users` | UserController@index | users.index | auth, permission:manage users |
| GET | `/users/{id}/edit` | UserController@edit | users.edit | auth, permission:manage users |
| PUT | `/users/{id}` | UserController@update | users.update | auth, permission:manage users |
| GET | `/roles-permission` | PermissionController@index | roles-permission.index | auth, permission:manage users |
| GET | `/comp-time/upload` | CompTimeController@index | comp-time.index | auth |
| POST | `/comp-time/upload` | CompTimeController@store | comp-time.store | auth |
| POST | `/comp-time/filter` | CompTimeController@indexFilter | comp-time.index.filter | auth |
| POST | `/comp-time/details` | CompTimeController@showDetails | comp-time.show.details | auth |
| POST | `/comp-time/details/day` | CompTimeController@showDayDetails | comp-time.show.day.details | auth |
| GET | `/comp-time/recalculate` | CompTimeController@recalculateBalances | comp-time.recalculate | auth |
| GET | `/tournaments` | TournamentController@index | tournaments.index | auth |
| GET | `/tournaments/create` | TournamentController@create | tournaments.create | auth |
| POST | `/tournaments` | TournamentController@store | tournaments.store | auth |
| GET | `/tournaments/{id}` | TournamentController@show | tournaments.show | auth |
| GET | `/tournaments/{id}/edit` | TournamentController@edit | tournaments.edit | auth |
| PUT | `/tournaments/{id}` | TournamentController@update | tournaments.update | auth |
| DELETE | `/tournaments/{id}` | TournamentController@destroy | tournaments.destroy | auth |
| GET | `/tournaments/categories` | TournamentController@indexCategories | categories.index | auth |
| GET | `/tournaments/categories/create` | TournamentController@createCategory | categories.create | auth |
| POST | `/tournaments/categories` | TournamentController@storeCategory | categories.store | auth |
| GET | `/tournaments/categories/{id}/edit` | TournamentController@editCategory | categories.edit | auth |
| PUT | `/tournaments/categories/{id}` | TournamentController@updateCategory | categories.update | auth |
| DELETE | `/tournaments/categories/{id}` | TournamentController@destroyCategory | categories.destroy | auth |

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
| GET | `/api/schedule/home-assistant/automation` | ScheduleController@homeAssistantAutomation |

### Com `api_token` (T)
| Método | URI | Ação |
|--------|-----|------|
| POST | `/api/telegram/get-contacts` | TelegramContactController@find |
| POST | `/api/telegram/contacts` | TelegramContactController@store |
| PUT | `/api/telegram/contacts/{id}` | TelegramContactController@update |
| GET | `/api/telegram/freelancer/freelancer/{cpf}` | FreelancerController@show |
| POST | `/api/telegram/freelancer/freelancer` | FreelancerController@store |
| GET | `/api/telegram/freelancer/functions` | FunctionFreelancerController@index |
| POST | `/api/telegram/freelancer/function` | FunctionFreelancerController@store |
| POST | `/api/telegram/freelancer/service` | FreelancerServiceController@store |
| GET | `/api/image/{member_id}` | MemberAuthController@getImage |
| POST | `/api/login` | MemberAuthController@login |
| POST | `/api/register` | MemberAuthController@register |
| POST | `/api/check-member` | MemberAuthController@checkMember |
| PUT | `/api/change-password` | MemberAuthController@changePassword |
| POST | `/api/send-email` | EmailController@submit |
| GET | `/api/debug-mail` | closure (dump de config de e-mail) |

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

> Não há comandos de console customizados (`app/Console/Commands/` não contém classes próprias).
