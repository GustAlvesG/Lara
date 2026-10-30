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
- [Banco de horas](#comptimecontroller) · [Torneios](#tournamentcategorytemplatecontroller) · [Ingressos](#ticketticketeventcontroller-tickettypecontroller-ticketlotcontroller) · [Contratos](#signaturecontractcontroller-signaturecontractlibrarycontroller)
- [Empresas](#company---apphttpcontrollerscompany)
- [WhatsApp](#whatsappcontroller) · [Telegram](#telegramcontactcontroller)
- [Acessos](#accesscontroller) · [Estacionamento](#parkingcontroller)
- [Informações](#informationcontroller) · [DataInfo](#datainfocontroller)
- [Usuários](#usercontroller) · [Permissões](#permissioncontroller) · [Perfil](#profilecontroller)
- [Auxiliares](#controllers-auxiliares) (Energy, Ftp, Email, Test, Visitor, DataInfo)
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
| `homeAssistantAutomation` | `homeAssistantAutomation()` | Retorna o estado dos contatores (iluminação) para automação. → JSON | `api_token` (sem `login_token`) |

---

## SchedulePaymentController

`app/Http/Controllers/SchedulePaymentController.php`

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `store` | `store(StoreSchedulePaymentRequest $request)` | Registra um pagamento de agendamento (via `SchedulePaymentService`, em transação). Se o schedule é de reserva social, confere estágio/prazo/valor (`SocialBookingService::assertPayable`, recusa com `reason`) e confirma a reserva (`markPaid`). | `POST /api/schedule/payment` |
| `storeManual` | `storeManual(Request, ManualPaymentService)` | Baixa manual (pago fora da plataforma): `schedule_ids[]`, `payment_method`, `amount`, `paid_at`, `manual_notes`. Volta para a reserva social ou para o agendamento. | `POST /payments/manual` (`can:reservas.pagamentos.baixa-manual`) |
| `index` / `show` | — | Lista (filtro de forma inclui "Baixa manual (todas)") e detalhe; baixa manual não mostra consulta nem estorno na Rede. | `GET /payments`, `GET /payments/{id}` |

---

## Social\SocialBookingController

`app/Http/Controllers/Social/SocialBookingController.php` — painel dos espaços sociais. Regras em `SocialBookingService`.

| Método | Descrição | Rota |
|--------|-----------|------|
| `index` | Lista com filtros (situação, espaço, período, sócio) e contadores da fila. | `GET /social-bookings` |
| `create` / `store` | Pré-reserva no balcão (sócio pelo MultiClubes via `MemberService::memberByCpf`). Com `complete` (pede `reservas.sociais.aprovar`) fecha a reserva de uma vez — contrato (anexo opcional), aprovação com desconto — via `SocialCounterBookingService`; com `paid_now` (pede também `reservas.pagamentos.baixa-manual`) registra o pagamento recebido. | `GET /social-bookings/create`, `POST /social-bookings` |
| `show` | Detalhe com as ações do estágio. | `GET /social-bookings/{booking}` |
| `updateDetails` | Edita a pré-reserva: evento, pessoas, extras, observações e bebidas (em análise, antes do contrato ok). | `PUT …/details` |
| `contract` / `downloadContract` | Contrato ok + anexo opcional no disco `local` (`social-contracts/`). | `POST`/`GET …/contract` |
| `approve` / `refuse` / `cancel` | Decisão; o cancelamento estorna pela Rede se pedido (exige `reservas.pagamentos.estornar`). | `POST …/approve`, `…/refuse`, `…/cancel` |
| `contact` | Registra o contato com o sócio (encerra o prazo de 2 dias úteis). | `POST …/contact` |
| `updateGuests` / `guestsPdf` | Lista de convidados (equipe, sem prazo) e PDF para a portaria. | `PUT …/guests`, `GET …/guests.pdf` |
| `availability` | Calendário do mês (JSON) para a tela de pré-reserva. | `GET /social-bookings/availability` |

## Social\SocialBeverageController

`app/Http/Controllers/Social/SocialBeverageController.php` — catálogo de bebidas (nome, unidade, Código MV, preço, ativo). `index`/`store`/`update`/`destroy` em `/social-bookings/bebidas`.

## Api\SocialBookingApiController

`app/Http/Controllers/Api/SocialBookingApiController.php` — `/api/social/*` para o app de locação (T + L). O sócio sai do JWT; reserva alheia = 404; recusas como `{message, reason}`. Contrato em [reservas-sociais-prompt-site-locacao.md](reservas-sociais-prompt-site-locacao.md).

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
| `update` | `update(UpdatePlaceGroupRequest $request, PlaceGroup $placeGroup)` | Atualiza grupo; `image_vertical`/`image_horizontal` por arquivo ou por link (`*_url`), resolvidas antes da transação (`PlaceGroupService::resolveImages`). → Redirect |
| `scheduleRules` | `scheduleRules($id)` | Retorna as regras de agendamento do grupo. → JSON |
| `createScheduleRule` | `createScheduleRule($group_id)` | Formulário de criação de regra. → View |
| `storeScheduleRule` | `storeScheduleRule(Request $request)` | Cria regra com dias da semana e espaços. → Redirect |
| `editScheduleRule` | `editScheduleRule($id)` | Formulário de edição de regra. → View |
| `updateScheduleRule` | `updateScheduleRule(Request $request, $id)` | Atualiza regra (em transação). → Redirect |
| `destroyScheduleRule` | `destroyScheduleRule($id)` | Remove regra. → Redirect |
| `createPlace` | `createPlace($group_id)` | Formulário de criação de espaço no grupo. → View |
| `storePlace` | `storePlace(Request $request)` | Cria espaço; imagem por arquivo (`image`) ou link (`image_url`), via `ImageSource::resolve`. → Redirect |
| `editPlace` | `editPlace($place_id)` | Formulário de edição de espaço. → View |
| `updatePlace` | `updatePlace(Request $request, $place_id)` | Atualiza espaço; arquivo ou link novo trocam a imagem, sem nenhum fica a atual. → Redirect |
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

## EmployeeController

`app/Http/Controllers/EmployeeController.php` — módulo [Funcionários](funcionalidades/funcionarios.md),
rotas `employees.*` sob `/funcionarios`, todas com `can:funcionarios.gerenciar`. Depende de
`EmployeeSectorSync`; valida com `EmployeeRequest`; importa com `EmployeeImport`.

| Método | Descrição | Rota |
|--------|-----------|------|
| `index` | Lista paginada com busca (nome, matrícula, CPF) e filtros de setor e situação. | `GET /funcionarios` |
| `create` | Formulário + bloco de importação. | `GET /funcionarios/novo` |
| `store` | Cadastra (`source = manual`) e aplica a regra de setor do usuário. | `POST /funcionarios` |
| `show` | Ficha (abre também o excluído). | `GET /funcionarios/{employee}` |
| `update` | Edita cadastro e rescisão; reaplica a regra de setor se setor/matrícula/rescisão mudaram. | `PUT /funcionarios/{employee}` |
| `destroy` / `restore` | Soft delete / restauração. | `DELETE /funcionarios/{employee}`, `POST /funcionarios/{employee}/restaurar` |
| `importTemplate` / `import` | Modelo .xlsx e importação tudo-ou-nada (cria ou atualiza pela matrícula). | `GET /funcionarios/import/template`, `POST /funcionarios/import` |
| `storeAbsence` / `destroyAbsence` | Férias e afastamentos. | `POST /funcionarios/{employee}/ausencias`, `DELETE …/ausencias/{absence}` |

---

## Tournament\CategoryTemplateController

`app/Http/Controllers/Tournament/CategoryTemplateController.php` — catálogo de categorias de torneio de cada grupo de espaços esportivo (`?group=`). `index`/`store`/`update`/`destroy` (soft delete) em `/torneios/categorias`, permissão `torneios.gerenciar`. O grupo não muda na edição; nome único por grupo entre as não removidas.

---

## Tournament\TournamentController

`app/Http/Controllers/Tournament/TournamentController.php` — torneios no painel (`torneios.gerenciar`): `index` (busca `q`, filtro `status`), `create`/`store` (nasce em rascunho), `show` (categorias ligadas, contagens, catálogo disponível), `edit`/`update` (grupo só muda sem categoria; imagem por `ImageSource`), `status` (passagens em `TournamentSetupService::TRANSITIONS`).

## Tournament\TournamentCategoryController

`store`/`update`/`destroy` das categorias ligadas a um torneio (`/torneios/{id}/categorias`, `torneios.gerenciar`), pelo `TournamentSetupService` (cópia do catálogo, campos do vínculo em `linkRules()`).

## Tournament\TournamentRegistrationController

Inscrições de um torneio (`torneios.inscricoes`): `index` (busca nome/CPF/matrícula, filtros de categoria e situação), `store` (balcão: MultiClubes + cadastro do site, liberação com `override_reason`), `cancel` (motivo; `refund` pede `torneios.pagamentos.estornar`), `manual` (baixa manual, `torneios.pagamentos.baixa-manual`).

## Tournament\TournamentTeamController

Times de uma categoria (`/torneios/{id}/categorias/{categoria}/times`, `torneios.inscricoes`): `index` (sem time e times), `store` (monta com os marcados), `draw` (sorteio entre pagos sem time), `update` (nome, cabeça de chave, capitão), `addMember`, `removeMember`, `destroy` (desfaz). Regras em `TournamentTeamService`.

## Tournament\TournamentBracketController

Chaveamento de uma categoria (`/torneios/{id}/categorias/{categoria}/chaveamento`, `torneios.gerenciar`): `show` (times, aviso de incompletos e de mudança desde a geração, grupos com classificação, chave por rodada; valores iniciais do "montar à mão"), `generate` (sorteio ou à mão), `destroy` (desfaz), `publish` (divulga ou recolhe do site), `schedule` (data, hora e local do jogo). Regras em `TournamentBracketService`.

## Tournament\TournamentResultController

Resultados no painel (`torneios.resultados`): `record` (placar ou W.O.; trava para o site), `clear`, `closeGroup` (ordem final → classificados no mata-mata), `reopenGroup`, `generateCode` (mostra o código uma vez, na sessão `results_code`), `revokeCode`. Recusas do `TournamentResultService` viram erro de formulário (`result`).

## Tournament\TournamentFinanceController

Relatório financeiro (`torneios.financeiro`): `index` (torneios com totais, filtro `?ano=`), `show` (por categoria e pagamentos), `csv`.

## Api\TournamentStaffApiController

Equipe do clube lançando resultado pelo site (`/api/tournaments/{id}/staff/*`): `check`, `matches`, `record`. Autoriza pelo código de lançamento (header `Tournament-Code`), não por login; o nome de quem lança (`operator_name`) vai para o histórico.

## SiteAccountController

Reservas › Contas do site (`reservas.contas-site`): `index`, `store` (cadastro manual), `show` (diagnóstico), `update`, `pull` (MultiClubes), `password` (senha provisória, na sessão `temporary_password`), `destroy` (desativa duplicada), `restore`. Regras em `SiteAccountService`; ver [contas-do-site.md](funcionalidades/contas-do-site.md).

## Api\TournamentApiController

`/api/tournaments/*` do site de locação: `index`, `show` (só `api_token`); `eligibility`, `register`, `mine`, `pay`, `withdraw` (com `login_token`; sócio sempre do JWT); times e convites (F4); `bracket` (F5, só `api_token`: chaveamento divulgado). Recusas como `TournamentException::toArray()`. Contrato em [torneios.md](funcionalidades/torneios.md#api-do-site-apitournamentapicontroller).

## Signature\ContractController, Signature\ContractLibraryController

Contratos (Assinaturas → Contratos). `ContractController`: `index` (visíveis ao usuário — os do setor; a Gerência vê todos e tem a aba "Para validar"; busca `q` por `SignatureContract::scopeSearch`), `create`/`store` (setor do usuário, modelo opcional), `show` (prévia, validação, o que mudou desde a última devolução, anexos, versões, histórico), `edit`/`update` (editor; `next=submit` salva e envia; `next=ai-review` salva e pede a revisão automática), `preview` (JSON: HTML numerado + problemas + outline + variáveis, sem gravar), `submit`, `decide` (`approve`/`return` com `comments[uid]`), `aiReview` (revisão automática pelo Claude; quem redige e a Gerência), `reopen`, `cancel`, `addendum`, `generateDocument`, `docx`, `saveModel`, `resolveComment`, anexos (`storeAttachment`/`showAttachment`/`destroyAttachment`) e `parties` (busca no cadastro de contratadas). `ContractLibraryController`: biblioteca de cláusulas e modelos por setor (`index`, `store` — também JSON pelo editor —, `update`, `destroy`, `destroyModel`) e `json` (cláusulas do setor do contrato, com uid novo). Quem pode: `SignatureContractPolicy`. `ContractKioskController`: a validação pela Gerência no tablet do balcão (rotas públicas `quiosque.gerencia.*`, matrícula + PIN, cookie `lara_management`): `index` (página), `login`, `pending`, `show`, `decide` (pelo `ContractService`), `attachment`, `logout`. Ver [contratos.md](funcionalidades/contratos.md).

## Ticket\TicketEventController, TicketDayController, TicketTypeController, TicketLotController

Ingressos no painel: eventos (`index` com busca `q` e filtro `status` e `show` com tipos, lote vigente, ocupados e pagos por lote — os dois abrem com o Gate `acessar-ingressos`; `create`/`store` em rascunho, `edit`/`update`, `status` por `TicketSetupService::TRANSITIONS` — `ingressos.gerenciar`; editar o evento não deixa um dia de fora do período), dias do evento de vários dias (`TicketDayController`: `store`/`update`/`destroy` por `TicketSetupService::saveDay`/`deleteDay` — dentro do evento, sem cruzar, dia com diária não apaga), tipos (`store`/`update` por `saveType` com `ticket_event_day_id` — diária ou, vazio, passaporte; o dia não muda depois de vendido —, `destroy`; com ingresso não apaga) e lotes (`store`/`update` por `TicketSetupService::saveLot`, com aviso, quantidade vazia = sem limite; `destroy`; `turn` = Virar lote agora, por `turnLots`, com confirmação). Ver [ingressos.md](funcionalidades/ingressos.md#telas).

## Ticket\TicketCouponController

Cupons do evento (`ingressos.cupons`): `index` (com usos), `store`, `update` (código normalizado e único no evento; porcentagem até 100; tipos do evento), `destroy` (recusa cupom já usado em pedido).

## Ticket\TicketOrderController

Pedidos (`ingressos.vendas`): `index` (busca por nome, CPF, código `ING-` ou número; filtro de situação), `show`, `pdf`, `resend`, `changeHolder`, `cancel` (marcados ou todos; `refund` pede `ingressos.pagamentos.estornar`), `manual` (baixa, `ingressos.pagamentos.baixa-manual`); emissão `create`/`store` (`external` pede baixa manual; `courtesy` pede `ingressos.cortesias`; sócio pelo CPF no MultiClubes, com matrícula se o CPF estiver em mais de um título, e um ingresso por pessoa do título). Recusas do serviço viram erro de formulário (`order`).

## Ticket\TicketCheckinController

Check-in (`ingressos.checkin`): `index` (eventos com a entrada aberta e os próximos, contador), `store` (código ou CPF; `only_check` só consulta; `force` registra fora da janela; código de outro evento não registra). Resultado na sessão `checkin`.

## Ticket\TicketFinanceController

Financeiro (`ingressos.financeiro`): `index` (eventos do ano, `?ano=`), `show` (totais, por lote, por forma, por cupom), `csv` (um ingresso por linha, `;`, BOM).

## Api\TicketApiController

`/api/tickets/*` do site de locação: vitrine (`index`, `show`, `checkCoupon`, só `api_token`); sócio (`myTitle` — pessoas do título com `ref` cifrada —, `storeMember` — um ingresso por pessoa do título, pela `ref` —, `mine`, `showMember`, `payMember`, `withdrawMember`, `pdfMember`, com `login_token`; sócio sempre do JWT); não sócio sem conta (`storeGuest` devolve o `access_token`; `showGuest`, `payGuest`, `withdrawGuest`, `pdfGuest` com o header `Ticket-Order-Token`; `resendGuestLink` com resposta sempre igual). Recusas como `TicketException::toArray()`. Contrato em [ingressos.md](funcionalidades/ingressos.md#api-do-site-apiticketapicontroller).

## Api\TicketGuestAccessController

"Meus ingressos" do não sócio (`/api/tickets/guest-access/*`, só `api_token`): `requestCode` (CPF + e-mail → código por e-mail, resposta sempre igual), `verify` (código → `access_token` de 2 h), `orders` (header `Ticket-Access-Token` → pedidos de não sócio daquele CPF + e-mail, cada um com o próprio `access_token`). Regras em `TicketGuestAccessService`.

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
| `validateCompanyAccess` | `validateCompanyAccess(Request $request)` | Valida tentativa de acesso (por CPF — terceirizado e/ou freelancer — ou nome da empresa). → JSON | `POST /api/company-access/validate-access` |
| `registerWorkerAccess` | `registerWorkerAccess(Request $request)` | Grava o acesso de um terceirizado (`worker_id`). → JSON | `POST /api/company-access/register-worker-access` |
| `registerFreelancerAccess` | `registerFreelancerAccess(Request $request)` | Grava o acesso de um freelancer (`freelancer_id`), validado pelo contrato. → JSON | `POST /api/company-access/register-freelancer-access` |

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
| `search` | `search()` | Tela de busca com a contagem do dia. → View `parking.search` | `GET /parking/search` (perm. `siv.busca`) |
| `show` | `show(Request $request)` | Busca por placa/dia e exibe as leituras com quem estava no carro: associados (catracas), externos e pedidos de carro de aplicativo (`ParkingAccessCorrelationService`). → View `parking.show` | `POST /parking/find` (perm. `siv.busca`) |
| `create`/`store`/`edit`/`update`/`destroy` | — | _stub_. | — |

---

## InformationController

`app/Http/Controllers/InformationController.php` — conteúdo informativo. CRUD completo, com
versionamento (toda alteração cria uma nova linha em `data_infos`) e permissões Spatie
(`view information`, `create information`, `edit information`, `delete information`).

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `index` | `index()` | Lista informações. → View | `GET /information` |
| `create` | `create()` | Formulário. → View | `GET /information/create` |
| `store` | `store(StoreInformationRequest $request)` | Cria (sem `information_id`) ou grava uma nova versão (com `information_id` — é o que o formulário de edição usa). → Redirect | `POST /information` |
| `show` | `show(DataInfo $information)` | Detalhes da versão atual. → View | `GET /information/{information}` |
| `edit` | `edit(DataInfo $information)` | Formulário de edição, submete para `store`. → View | `GET /information/{information}/edit` |
| `update` | `update(UpdateInformationRequest $request, DataInfo $information)` | Restaura uma versão do histórico como a atual (botão "Tornar versão atual"). → Redirect | `PUT /information/{information}` |
| `destroy` | `destroy(Information $information)` | Soft delete. → Redirect | `DELETE /information/{information}` |
| `history` | `history($information)` | Histórico de alterações. → View | `GET /information/{id}/history` |

> Métodos auxiliares: `concatenateArrayValues($array, $delimiter = ';')` e
> `explode_fields($info)` (privado) tratam a serialização dos campos compostos.
> `show`/`edit`/`history` retornam 404 se a `Information` pai estiver soft-deleted.

---

## UserController

`app/Http/Controllers/UserController.php` — usuários do painel. Protegido por `can:usuarios.gerenciar`. Mudanças de acesso passam pelo `App\Authorization\AccessManager` (auditoria e travas) — ver [Usuários, setores e permissões](funcionalidades/usuarios-e-permissoes.md).

| Método | Descrição | Rota |
|--------|-----------|------|
| `index` | Lista usuários com setores e permissões individuais. | `GET /users` |
| `create` / `store` | Cadastro, já com os setores. | `GET /users/create`, `POST /users` |
| `edit` | Dados, setores, permissões individuais, acesso efetivo e histórico. | `GET /users/{id}/edit` |
| `update` | Dados da conta (senha e PIN em branco mantêm). | `PUT /users/{id}` |
| `updateSectors` | Setores e papel em cada um. | `PUT /users/{id}/sectors` |
| `updatePermissions` | Permissões individuais. | `PUT /users/{id}/permissions` |
| `destroy` | Tira dos setores (com as travas) e exclui (soft delete). | `DELETE /users/{id}` |

---

## SectorController

`app/Http/Controllers/SectorController.php` — setores. Protegido por `can:setores.gerenciar`: dados, acesso total, o que o setor alcança (`updatePermissions`), membros e o histórico de acesso (`audit`).

## MySectorController

`app/Http/Controllers/MySectorController.php` — "Meu setor": o coordenador coloca e tira colaboradores e cadastra gente nova nos setores que coordena (conferido em cada ação).

---

## ProfileController

`app/Http/Controllers/ProfileController.php` — perfil do usuário autenticado (Breeze).

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `edit` | `edit(Request $request): View` | Formulário de perfil. → View | `GET /profile` |
| `update` | `update(ProfileUpdateRequest $request): RedirectResponse` | Atualiza o perfil. → Redirect | `PATCH /profile` |
| `destroy` | `destroy(Request $request): RedirectResponse` | Exclui a conta. → Redirect | `DELETE /profile` |

---

## Disparo de E-mails (`app/Http/Controllers/EmailCampaign/`)

Permissão `disparo-emails` no corte da rota. Regras em [disparo-emails.md](funcionalidades/disparo-emails.md).

### EmailCampaignController
| Método | Descrição |
|--------|-----------|
| `index` | Lista paginada com busca (nome/assunto) e filtro de situação; enviados/falhas por `withCount`. → View |
| `create` / `store` | Nova campanha (`EmailCampaignRequest`: remetente da lista `email_campaigns.from_addresses`); nasce rascunho, no editor visual. No modo visual, grava `body` limpo e `html` composto. |
| `show` | Andamento, Enviar/Agendar, teste, lista (busca + situação, paginada), prévia. → View |
| `edit` / `update` | Só rascunho ou pausada; o remetente atual continua aceito mesmo fora da configuração. |
| `destroy` | Só rascunho, concluída ou cancelada. |
| `compose` | O e-mail que o editor visual monta (`EmailComposer::compose`) → JSON, para a prévia e o "Passar para HTML". |
| `preview` | O HTML com os dados do primeiro destinatário, com `Content-Security-Policy` sem script. |
| `progress` | Situação e contagens → JSON (a tela consulta a cada 5 s enquanto ativa). |
| `sendTest` | E-mail de teste na hora (`CampaignSender::sendTest`); a falha volta como erro na tela. |
| `start` · `pause` · `resume` · `cancel` · `unschedule` · `retryFailed` · `duplicate` | Transições do `EmailCampaignService`; `EmailCampaignException` vira mensagem de erro. |
| `importRecipients` | `.csv`/`.xlsx` até 20 MB, validado pela extensão (`RecipientImporter`); linhas recusadas em `import_rejected` na sessão. |
| `clearRecipients` · `exportRecipients` | Apaga a lista (rascunho) · CSV com a situação de cada destinatário. |
| `listTemplate` | Lista de exemplo em .csv ou .xlsx. |
| `uploadImage` · `showImage` | Imagem do editor → JSON `{url}` · serve a imagem (só logado). |

### EmailSuppressionController
| Método | Descrição |
|--------|-----------|
| `index` | "Não enviar para", com busca. → View |
| `store` | Vários e-mails de uma vez (linha, vírgula ou ponto e vírgula), normalizados em minúsculas; os inválidos são listados. |
| `destroy` | Volta a enviar para o endereço. |

## Controllers auxiliares

### EnergyController (`EnergyController.php`)
Classe vazia (sem métodos implementados) — reservada para um futuro dashboard de energia.

### FtpController (`FtpController.php`)
| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `getImage` | `static getImage($imageName)` | Garante a foto do acesso (SIV) no disco local `img_car` e devolve o caminho relativo, ou `false` quando não há foto. Só vai ao disco `ftp` se a cópia local não existir. |
| `imageUrl` | `static imageUrl($path)` | URL pública da foto (`/storage/img_car/…`), com cada trecho do caminho codificado. |

Detalhes em [Placas de Carro — Foto do acesso](funcionalidades/estacionamento-placas.md#foto-do-acesso-ftp-das-câmeras).

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

---

## Replay (`app/Http/Controllers/Replay/`)

Vídeos das quadras. Telas em `Web/` (permissão `replay` do catálogo), integração em `Api/`.
Contrato completo em [replay-api.md](replay-api.md).

### Web\SettingController
| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `index` | `index()` | Esportes com as quadras aninhadas, mostrando a configuração **efetiva** e sua origem (`própria`/`herdado do esporte`/`padrão`). Usa o `ReplayResolver` — a tela nunca reimplementa a herança. |
| `store` | `store(Request): RedirectResponse` | Grava a configuração do esporte ou da quadra (`updateOrCreate` pela chave do dono). Valida orientação e duração de 5 a 60s. |
| `destroy` | `destroy(Setting): RedirectResponse` | Remove a configuração **própria** da quadra — ela volta a herdar do esporte. Não desliga o Replay. |

### Web\LayoutController
| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `index` | `index()` | Lista os layouts e avisa se falta ffmpeg (GIF animado sairia parado). |
| `create` / `store` | | Cria o layout (dono + orientação + nome). Layout já existente para o mesmo par abre para edição em vez de estourar o UNIQUE. |
| `edit` | `edit(Layout)` | Editor visual: arrastar, redimensionar, opacidade e ordem. |
| `update` | `update(Request, Layout)` | Nome e situação. **Orientação não se edita** — é metade da chave única e define a tela em que tudo foi posicionado. |
| `storeLogo` | `storeLogo(Request, Layout)` | Sobe PNG/GIF e cria a peça no canto superior esquerdo, preservando a proporção original. |
| `updateItems` | `updateItems(Request, Layout): JsonResponse` | Recebe as posições do editor (JSON) e re-renderiza. Só aceita ids que pertencem ao layout — eles vêm do navegador. |
| `destroyItem` / `destroy` / `rerender` | | Remove peça, remove layout (com arquivos) e força nova composição. |

Toda alteração que muda o pixel final termina em `OverlayRenderer::render()` — o arquivo
publicado nunca fica atrasado em relação à tela.

### Web\CameraController
| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `index` | `index()` | Câmeras com quadra, último contato e **como cada uma está gravando** (orientação, duração e layout em vigor). |
| `store` / `update` / `destroy` | | CRUD. `external_id` é único em todo o clube; máximo de 2 câmeras por quadra (`Camera::MAX_PER_PLACE`). Excluir a câmera **não** apaga os vídeos: o clipe pertence à quadra, não ao equipamento. |

### Web\VideoController
| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `index` | `index(Request)` | Galeria interna com filtros (esporte, quadra, data, com/sem reserva) e dias restantes. |
| `destroy` | `destroy(Video)` | Exclusão manual — apaga do FTP, a cópia local e o registro. FTP fora → nada é apagado e a tela avisa. |

### VideoFileController
| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `show` | `show(string $uuid, RemoteVideoStore)` | Rota **pública** `replay.videos.file`. Refaz a cópia local do clipe a partir do FTP e redireciona para o arquivo estático. Vencido ou ausente no FTP → 404; FTP fora → 503. |

### Api\CameraController
| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `index` | `index(): JsonResponse` | Todas as câmeras ativas com a configuração **já resolvida**, mais o `config_hash`. O sistema de captura só reprocessa quando o hash muda. |
| `show` | `show(string $externalId)` | Idem, para uma câmera. 404 se inativa ou inexistente. |
| `heartbeat` | `heartbeat(string $externalId)` | Sinal de vida. Grava com `timestamps = false`: o `updated_at` da câmera entra no `config_hash`, e um heartbeat por minuto faria o parque inteiro reprocessar. |

### Api\VideoController
| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `store` | `store(StoreVideoRequest, string $externalId)` | Recebe o **aviso** do clipe já gravado no FTP (`path`, sem arquivo). `201` para clipe novo, `200` com `duplicated: true` no reenvio do mesmo `external_id` ou `path`, `422` para arquivo ausente/formato não aceito, `503` com o FTP fora. |

### Api\PortalController
| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `places` | `places()` | Quadras que têm vídeo disponível agora. |
| `placeVideos` | `placeVideos(Request, Place)` | Galeria pública da quadra, paginada, filtro por data. Marca `has_member` **sem revelar quem**. |
| `myVideos` | `myVideos(Request)` | Vídeos do sócio logado. O sócio sai do **próprio JWT** (o `username` é o CPF), nunca de um id na URL. |
