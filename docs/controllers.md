# 5. Controllers

Documentação de **todos** os controllers e seus métodos públicos. Métodos vazios/stub
(scaffold do `php artisan make:controller --resource` ainda não implementados) são
indicados como _stub_.

> Convenção: `→ JSON` resposta JSON · `→ View` view Blade · `→ Redirect` redirecionamento.

---

## Índice

- [Agendamentos](#schedulecontroller) · [SchedulePayment](#schedulepaymentcontroller)
- [Espaços](#placegroupcontroller) · [Place](#placecontroller) · [Regras](#schedulerulescontroller)
- [Sócios](#membercontroller) · [Auth de Sócio](#memberauthcontroller--apphttpcontrollersauth) · [Login Token](#logintokencontroller)
- [Freelancers](#freelancercontroller) · [Funções](#functionfreelancercontroller) · [Serviços de Freelancer](#freelancerservicecontroller)
- [Banco de horas](#comptimecontroller) · [Torneios](#tournamentcontroller--apphttpcontrollerstournament)
- [Empresas](#company---apphttpcontrollerscompany)
- [WhatsApp](#whatsappcontroller) · [Telegram](#telegramcontactcontroller)
- [Acessos](#accesscontroller) · [Estacionamento](#parkingcontroller)
- [Informações](#informationcontroller) · [DataInfo](#datainfocontroller)
- [Usuários](#usercontroller) · [Permissões](#permissioncontroller) · [Perfil](#profilecontroller)
- [Auxiliares](#controllers-auxiliares) (Energy, VideoWall, Ftp, Email, Test, Visitor, DataInfo)
- [Autenticação Breeze](#controllers-de-autenticação-breeze)

---

## ScheduleController

`app/Http/Controllers/ScheduleController.php` — gerencia agendamentos. Depende de `SchedulesService`.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `__construct` | `__construct()` | Instancia o `SchedulesService`. |
| `index` | `index(Request $request)` | Lista agendamentos de uma data para o painel. → View `location.index` |
| `indexFilter` | `indexFilter(Request $request)` | Filtra agendamentos (painel). |
| `indexByPlace` | `indexByPlace(Request $request)` | Retorna agendamentos de um espaço. → JSON (rota POST que funciona como GET) |
| `indexByMember` | `indexByMember($member_id)` | Retorna todos os agendamentos de um sócio. → JSON |
| `show` | `show($id)` | Detalhes de um agendamento e agendamentos relacionados. → View `location.schedule.show` |
| `create` | `create()` | Tela de criação de agendamento. → View |
| `store` | `store(Request $request)` | Cria agendamento (detecção de colisão, pagamento e e-mail). → JSON (API) ou Redirect (web). |
| `update` | `update(Request $request)` | Atualiza um agendamento (status, dados). → Redirect/JSON |
| `updateStatus` | `updateStatus(Request $request)` | Atualiza apenas o status, podendo estornar pagamento. → JSON |
| `destroyPending` | `destroyPending(Request $request)` | Remove um agendamento pendente. → JSON (`200`/`404`) |
| `generateDailySchedulePDF` | `generateDailySchedulePDF()` | Gera o PDF da agenda do dia (DomPDF). | Rota `GET /api/schedule/generate-pdf` |
| `homeAssistantAutomation` | `homeAssistantAutomation()` | Retorna o estado dos contatores (iluminação) para automação. → JSON | Rota pública (sem tokens) |

---

## SchedulePaymentController

`app/Http/Controllers/SchedulePaymentController.php`

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `store` | `store(StoreSchedulePaymentRequest $request)` | Registra um pagamento de agendamento (via `SchedulePaymentService`, em transação). | `POST /api/schedule/payment` |

---

## ScheduleRulesController

`app/Http/Controllers/ScheduleRulesController.php` — regras de disponibilidade. Depende de `ScheduleRulesService` e `SchedulesService`.

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `store` | `store(StoreScheduleRulesRequest $request)` | Cria uma regra de agendamento. → Redirect | `POST /place-group/schedule/rule` |
| `getScheduledDates` | `getScheduledDates($place_id)` | Retorna as datas disponíveis para agendamento de um espaço. → JSON | `GET /schedule/getDates/{place_id?}` |
| `getTimeOptions` | `getTimeOptions(Request $request)` | Calcula horários disponíveis (aplica regras, exclusões, limite e colisão). → JSON | `POST /api/schedule/time-options` |
| `index`/`create`/`show`/`edit`/`update`/`destroy` | — | _stub_ (scaffold de resource). | — |

---

## PlaceGroupController

`app/Http/Controllers/PlaceGroupController.php` — grupos de espaços (categorias). Depende de `PlaceGroupService` e `ScheduleRulesService`.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `index` | `index()` | Lista grupos no painel. → View `location.placeGroup.index` |
| `index_api` | `index_api($none = null)` | Lista todos os grupos com imagens. → JSON (API) |
| `indexByCategory` | `indexByCategory($category)` | Lista grupos por categoria, com imagens. → JSON |
| `create` | `create()` | Formulário de criação. → View |
| `store` | `store(Request $request)` | Cria grupo (via service). → Redirect |
| `show` | `show(PlaceGroup $placeGroup)` | Exibe grupo com suas regras. → View |
| `edit` | `edit(PlaceGroup $placeGroup)` | Formulário de edição. → View |
| `update` | `update(UpdatePlaceGroupRequest $request, PlaceGroup $placeGroup)` | Atualiza grupo. → Redirect |
| `scheduleRules` | `scheduleRules($id)` | Retorna as regras de agendamento do grupo. → JSON |
| `createScheduleRule` | `createScheduleRule($group_id)` | Formulário de criação de regra. → View |
| `storeScheduleRule` | `storeScheduleRule(Request $request)` | Cria regra com dias da semana e espaços. → Redirect |
| `editScheduleRule` | `editScheduleRule($id)` | Formulário de edição de regra. → View |
| `updateScheduleRule` | `updateScheduleRule(Request $request, $id)` | Atualiza regra (em transação). → Redirect |
| `destroyScheduleRule` | `destroyScheduleRule($id)` | Remove regra. → Redirect |
| `createPlace` | `createPlace($group_id)` | Formulário de criação de espaço no grupo. → View |
| `storePlace` | `storePlace(Request $request)` | Cria espaço com upload de imagem. → Redirect |
| `editPlace` | `editPlace($place_id)` | Formulário de edição de espaço. → View |
| `updatePlace` | `updatePlace(Request $request, $place_id)` | Atualiza espaço (imagem opcional). → Redirect |
| `destroyPlace` | `destroyPlace($place_id)` | Remove espaço. → Redirect |

---

## PlaceController

`app/Http/Controllers/PlaceController.php` — depende de `PlaceService` (injeção via construtor).

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `indexByGroup` | `indexByGroup(Request $request)` | Lista espaços de um grupo com disponibilidade (group_id, member_id, date). → JSON | `POST /api/places` |
| `scheduleRules` | `scheduleRules($id)` | Regras de agendamento de um espaço. → JSON (`404` se não houver) | `GET /api/places/group/rules/{id}` |
| `show` | `show($id)` | Espaço com suas regras de agendamento. → JSON | `GET /api/place/{id}` |
| `create`/`store`/`edit`/`update`/`destroy` | — | _stub_. | — |

---

## MemberController

`app/Http/Controllers/MemberController.php` — sócios.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `index` | `index()` | Lista sócios cujo título começa com 'A'. → JSON |
| `findMemberByTitle` | `static findMemberByTitle($title)` | Busca sócios por título. → JSON |
| `findMemberByCode` | `static findMemberByCode($code)` | Busca sócio por código no banco e retorna view com a foto. → View `member.index` |
| `getByTitle` | `getByTitle(Request $request)` | Busca sócios pelo título informado no corpo. → JSON | Rota `POST /api/member/by-title` |
| `store` | `static store($cpf, $title, $birthDate)` | Cria registro de sócio (delega ao `MemberAuthController`). |
| `queryMemberByCpf` | `private queryMemberByCpf($document, $title, $birthDate)` | Consulta SQL de sócio por CPF (uso interno). |

---

## MemberAuthController  (`app/Http/Controllers/Auth/`)

Autenticação e dados de sócios; integra com a base externa (SQL Server) e gera JWT.

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `store` | `static store($cpf, $title, $birthDate, $password = null)` | Cria sócio a partir da consulta externa e retorna usuário + token. → JSON (`201`/`409`/`404`) | — |
| `register` | `register(StoreMemberRequest $request)` | Registra sócio via Form Request. | `POST /api/register` |
| `login` | `login(Request $request)` | Autentica por login/senha e retorna token JWT. → JSON (`401` se inválido) | `POST /api/login` |
| `checkMember` | `checkMember(Request $request)` | Verifica existência de sócio. → JSON | `POST /api/check-member` |
| `changePassword` | `changePassword(Request $request)` | Altera a senha do sócio. → JSON | `PUT /api/change-password` |
| `update` | `update(Request $request)` | Atualiza dados do sócio autenticado. → JSON | `PUT /api/member/update` |
| `getImage` | `getImage($member_id)` | Retorna a imagem (foto) do sócio em base64/arquivo. → imagem (`404` se ausente) | `GET /api/image/{member_id}` |

---

## LoginTokenController

`app/Http/Controllers/Auth/LoginTokenController.php`. Ver [Autenticação e Permissões](04-autenticacao-e-permissoes.md) (seção *LoginTokenController*).

---

## FreelancerController

`app/Http/Controllers/FreelancerController.php` — depende de `FreelancerService`.

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `store` | `store(StoreFreelancerRequest $request)` | Cria freelancer. → JSON (`201`/`400`) | `POST /api/telegram/freelancer/freelancer` |
| `show` | `show($cpf)` | Busca freelancer por CPF. → JSON (`200`/`400`) | `GET /api/telegram/freelancer/freelancer/{cpf}` |
| `index`/`create`/`edit`/`update`/`destroy` | — | _stub_. | — |

---

## FunctionFreelancerController

`app/Http/Controllers/FunctionFreelancerController.php` — depende de `FreelancerService`.

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `index` | `index()` | Lista as funções de freelancer disponíveis. → JSON (`200`/`500`) | `GET /api/telegram/freelancer/functions` |
| `store` | `store(StoreFunctionFreelancerRequest $request)` | Cria função. | `POST /api/telegram/freelancer/function` |
| demais | — | _stub_. | — |

---

## FreelancerServiceController

`app/Http/Controllers/FreelancerServiceController.php`

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `store` | `store(StoreFreelancerServiceRequest $request)` | Registra um serviço prestado por freelancer. | `POST /api/telegram/freelancer/service` |

---

## CompTimeController

`app/Http/Controllers/CompTimeController.php` — banco de horas. Depende de `CompTimeService`.

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `index` | `index()` | Tela de upload do arquivo de ponto. → View | `GET /comp-time/upload` |
| `store` | `store(Request $request)` | Importa o arquivo HTML de ponto. → Redirect | `POST /comp-time/upload` |
| `indexFilter` | `indexFilter(Request $request)` | Filtra funcionários por estrutura/nome/código. | `POST /comp-time/filter` |
| `showDetails` | `showDetails(Request $request)` | Detalhes do banco de horas de um funcionário. | `POST /comp-time/details` |
| `showDayDetails` | `showDayDetails(Request $request)` | Detalhes de um dia específico. | `POST /comp-time/details/day` |
| `recalculateBalances` | `recalculateBalances()` | Recalcula os saldos de todos os funcionários. | `GET /comp-time/recalculate` |

---

## TournamentController  (`app/Http/Controllers/Tournament/`)

Depende de `TournamentService`. Gerencia torneios e categorias.

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `index` | `index()` | Lista torneios. → View | `GET /tournaments` |
| `create` | `create()` | Formulário de torneio. → View | `GET /tournaments/create` |
| `store` | `store(Request $request)` | Cria torneio. → Redirect | `POST /tournaments` |
| `show` | `show($id)` | Detalhes do torneio. → View | `GET /tournaments/{id}` |
| `edit` | `edit($id)` | Formulário de edição. → View | `GET /tournaments/{id}/edit` |
| `update` | `update(Request $request, $id)` | Atualiza torneio. → Redirect | `PUT /tournaments/{id}` |
| `destroy` | `destroy($id)` | Remove torneio. → Redirect | `DELETE /tournaments/{id}` |
| `indexCategories` | `indexCategories()` | Lista categorias. → View | `GET /tournaments/categories` |
| `createCategory` | `createCategory()` | Formulário de categoria. → View | `GET /tournaments/categories/create` |
| `storeCategory` | `storeCategory(Request $request)` | Cria categoria. → Redirect | `POST /tournaments/categories` |
| `editCategory` | `editCategory($id)` | Formulário de edição de categoria. → View | `GET /tournaments/categories/{id}/edit` |
| `updateCategory` | `updateCategory(Request $request, $id)` | Atualiza categoria. → Redirect | `PUT /tournaments/categories/{id}` |
| `destroyCategory` | `destroyCategory($id)` | Remove categoria. → Redirect | `DELETE /tournaments/categories/{id}` |

---

## Company  (`app/Http/Controllers/Company/`)

### CompanyController
Depende de `CompanyService`.

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `index` | `index()` | Lista empresas. → View `companies.index` | `GET /company` |
| `create` | `create()` | Formulário de criação. → View | `GET /company/create` |
| `store` | `store(StoreCompanyRequest $request)` | Cria empresa (upload de imagem). → Redirect | `POST /company` |
| `show` | `show(Company $company)` | Detalhes da empresa (com trabalhadores e regras). → View | `GET /company/{company}` |
| `edit`/`update`/`destroy` | — | _stub_. | — |

### CompanyWorkerController

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `create` | `create(...)` | Formulário de trabalhador. → View | `GET /company/{company}/worker/create` |
| `store` | `store(StoreCompanyWorkerRequest $request, ...)` | Cria trabalhador (imagem base64). → Redirect | `POST /company/{company}/worker` |
| `destroy` | `destroy(...)` | Remove trabalhador. → Redirect | `DELETE /company/{company}/worker/{worker}` |

### CompanyAccessRulesController

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `create` | `create(...)` | Formulário de regra de acesso. → View | `GET /company/{company}/rules/create` |
| `store` | `store(StoreCompanyAccessRulesRequest $request, ...)` | Cria regra de acesso (dias da semana). → Redirect | `POST /company/{company}/rules` |
| `validateCompanyAccess` | `validateCompanyAccess(Request $request)` | Valida tentativa de acesso de empresa (por CPF ou nome). → JSON | `POST /api/company-access/validate-access` |

---

## WhatsAppController

`app/Http/Controllers/WhatsAppController.php` — integração com o WhatsApp Cloud API (Meta).

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `verifyWebhook` | `verifyWebhook(Request $request)` | Verificação do webhook (Meta challenge). → challenge ou `403` | `GET /api/whatsapp/webhook` |
| `handleWebhook` | `handleWebhook(Request $request)` | Recebe eventos e despacha o Job `ProcessWhatsAppWebhook`. → `200 OK` | `POST /api/whatsapp/webhook` |
| `sendMessage` | `sendMessage(Request $request, WhatsAppService $service)` | Envia mensagem de texto via WhatsApp. → JSON | `POST /api/whatsapp/send-message` |

---

## TelegramContactController

`app/Http/Controllers/TelegramContactController.php` — depende de `TelegramService`.

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `find` | `find(Request $request)` | Busca contatos (nome/chat_id/telefone). → JSON | `POST /api/telegram/get-contacts` |
| `store` | `store(Request $request)` | Cria contato do Telegram. → JSON (`201`) | `POST /api/telegram/contacts` |
| `update` | `update(Request $request, $id)` | Atualiza contato (com validação). → JSON | `PUT /api/telegram/contacts/{id}` |

---

## AccessController

`app/Http/Controllers/AccessController.php` — consulta de acessos físicos (catracas) na base externa SQL Server.

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `findAccessByTime` | `static findAccessByTime($time, $gate)` | Busca acessos numa janela de ~15s em torno do horário, para a catraca/portão informado. | `GET /accesses/{time}` |
| `queryAccess` | `static queryAccess($startTime, $endTime, $ratchet = null)` | Executa SQL bruto contra a base MultiClubes (`mc_sqlsrv`). | — (uso interno) |

> A rota `GET /accesses` referencia `AccessController@index`, porém esse método não está
> implementado na classe atual.

---

## ParkingController

`app/Http/Controllers/ParkingController.php` — estacionamento.

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `search` | `search()` | Tela de busca com a contagem do dia. → View `parking.search` | `GET /parking/search` (perm. `search parking`) |
| `show` | `show(Request $request)` | Busca por placa/horário e exibe acessos relacionados. → View `parking.show` | `POST /parking/find` (perm. `search parking`) |
| `create`/`store`/`edit`/`update`/`destroy` | — | _stub_. | — |

---

## InformationController

`app/Http/Controllers/InformationController.php` — conteúdo informativo. CRUD completo.

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `index` | `index()` | Lista informações. → View | `GET /information` |
| `create` | `create()` | Formulário. → View | `GET /information/create` |
| `store` | `store(StoreInformationRequest $request)` | Cria (com imagem). → Redirect | `POST /information` |
| `show` | `show(DataInfo $information)` | Detalhes. → View | `GET /information/{information}` |
| `edit` | `edit(DataInfo $information)` | Formulário de edição. → View | `GET /information/{information}/edit` |
| `update` | `update($information)` | Atualiza. → Redirect | `PUT /information/{information}` |
| `destroy` | `destroy(Information $information)` | Remove. → Redirect | `DELETE /information/{information}` |
| `history` | `history($information)` | Histórico de alterações. → View | `GET /information/{id}/history` |

> Métodos auxiliares: `concatenateArrayValues($array, $delimiter = ';')` e
> `explode_fields($info)` (privado) tratam a serialização dos campos compostos.

---

## DataInfoController

`app/Http/Controllers/DataInfoController.php` — dados estruturados ligados a `Information`. CRUD (resource).

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `create` | `create()` | Formulário. → View | `GET /data-info/create` |
| `store` | `store(StoreDataInfoRequest $request)` | Cria registro de dado. → Redirect | `POST /data-info` |
| `edit` | `edit(DataInfo $dataInfo)` | Formulário de edição. → View | `GET /data-info/{dataInfo}/edit` |
| `update` | `update(UpdateDataInfoRequest $request, DataInfo $dataInfo)` | Atualiza. → Redirect | `PUT /data-info/{dataInfo}` |
| `destroy` | `destroy(DataInfo $dataInfo)` | Remove. → Redirect | `DELETE /data-info/{dataInfo}` |
| `index`/`show` | — | _stub_. | — |

---

## UserController

`app/Http/Controllers/UserController.php` — usuários administrativos. Protegido por `permission:manage users`.

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `index` | `index()` | Lista usuários com papéis/permissões. → View `user.index` | `GET /users` |
| `edit` | `edit(User $id)` | Edita usuário e carrega papéis. → View `user.edit` | `GET /users/{id}/edit` |
| `update` | `update(Request $request, User $id)` | Atualiza dados/senha/e-mail/status e sincroniza papéis. → Redirect | `PUT /users/{id}` |
| `create`/`store`/`show`/`destroy` | — | _stub_. | — |

---

## PermissionController

`app/Http/Controllers/PermissionController.php`

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `index` | `index()` | Tela de papéis e permissões. → View | `GET /roles-permission` (perm. `manage users`) |

---

## ProfileController

`app/Http/Controllers/ProfileController.php` — perfil do usuário autenticado (Breeze).

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `edit` | `edit(Request $request): View` | Formulário de perfil. → View | `GET /profile` |
| `update` | `update(ProfileUpdateRequest $request): RedirectResponse` | Atualiza o perfil. → Redirect | `PATCH /profile` |
| `destroy` | `destroy(Request $request): RedirectResponse` | Exclui a conta. → Redirect | `DELETE /profile` |

---

## Controllers auxiliares

### EnergyController (`EnergyController.php`)
Classe vazia (sem métodos implementados) — reservada para um futuro dashboard de energia.

### VideoWallController (`VideoWallController.php`)
Controller de recurso; apenas `index` está em uso por rota.

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `index` | `index()` | Mural de vídeos. → View | `GET /videowall` |
| `store`/`show`/`update`/`destroy`/`test` | — | Métodos de recurso (sem rota associada no momento). | — |

### FtpController (`FtpController.php`)
| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `getImage` | `static getImage($imageName)` | Recupera uma imagem via disco FTP (Flysystem FTP). |

### EmailController (`EmailController.php`)
| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `__construct` | `__construct(EmailService $emailService)` | Injeta o `EmailService`. | — |
| `submit` | `submit(Request $request)` | Processa o formulário de contato e envia e-mail (via `EmailService`). | `POST /api/send-email` |

### TestController (`TestController.php`)
| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `index` | `index()` | Endpoint de teste/diagnóstico. → JSON | `GET /api/test` |

### VisitorController (`VisitorController.php`)
CRUD de visitantes (resource) — todos os métodos são _stub_ no momento.

---

## Controllers de autenticação (Breeze)

Em `app/Http/Controllers/Auth/`, gerados pelo Laravel Breeze (sessão web). Rotas em `routes/auth.php`.

| Controller | Métodos | Função |
|------------|---------|--------|
| `RegisteredUserController` | `create(): View`, `store(Request): RedirectResponse` | Registro de usuário. |
| `AuthenticatedSessionController` | `create(): View`, `store(LoginRequest): RedirectResponse`, `destroy(Request): RedirectResponse` | Login e logout. |
| `PasswordResetLinkController` | `create(): View`, `store(Request): RedirectResponse` | Solicitação de link de redefinição. |
| `NewPasswordController` | `create(Request): View`, `store(Request): RedirectResponse` | Definição de nova senha. |
| `PasswordController` | `update(Request): RedirectResponse` | Alteração de senha (logado). |
| `ConfirmablePasswordController` | `show(): View`, `store(Request): RedirectResponse` | Confirmação de senha. |
| `EmailVerificationPromptController` | `__invoke(Request): RedirectResponse\|View` | Aviso de verificação de e-mail. |
| `EmailVerificationNotificationController` | `store(Request): RedirectResponse` | Reenvio do e-mail de verificação. |
| `VerifyEmailController` | `__invoke(EmailVerificationRequest): RedirectResponse` | Confirma o e-mail. |
