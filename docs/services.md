# 6. Services

A camada de **Services** (`app/Services/`) concentra as regras de negócio, transações de
banco e integrações externas. Os controllers delegam a essas classes.

---

## SchedulesService

`app/Services/SchedulesService.php` — núcleo de agendamentos. Integra `ScheduleRulesService`,
`MemberService`, `EmailService`, `RedeItauService`, geração de PDF e envio de e-mail.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `getShedulesByPlace` | `getShedulesByPlace($place_id, $date = null)` | Agendamentos de um espaço (opcionalmente por data). |
| `getScheduleByMember` | `getScheduleByMember($member_id)` | Todos os agendamentos de um sócio. |
| `getSchedules` | `getSchedules($date = null)` | Agendamentos disponíveis agrupados por grupo de espaços. |
| `createSchedule` | `createSchedule(Request $request)` | Cria agendamento com detecção de colisão e notificação por e-mail. |
| `store` | `store(Request $request)` | Persiste um registro de agendamento. |
| `updateSchedulesStatus` | `updateSchedulesStatus($data)` | Atualiza o status e, se aplicável, estorna o pagamento. |
| `checkColide` | `checkColide($slotStartTime, $slotEndTime, $place_id, $date, $member_id = null)` | Detecta sobreposição de horários. |
| `countMemberSchedulesInPlaceGroupOnDate` | `countMemberSchedulesInPlaceGroupOnDate($group, $member_id, $date)` | Conta agendamentos do sócio no grupo/dia e o limite restante. |
| `homeAssistantAutomation` | `homeAssistantAutomation()` | Retorna o estado dos contatores (iluminação) para automação. |

---

## ScheduleRulesService

`app/Services/ScheduleRulesService.php` — regras de disponibilidade e cálculo de horários.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `store` | `store(Request $request)` | Cria regra com associação a espaços e dias da semana. |
| `getFilteredRulesByPlaceGroup` | `getFilteredRulesByPlaceGroup($placeGroup)` | Regras aplicáveis a um grupo de espaços. |
| `getTimeOptions` | `getTimeOptions($place_id, $date, $member_id = null)` | Calcula horários disponíveis (colisão + exclusões de regras). |
| `getLimit` | `getLimit($place_id, $member_id, $date)` | Limite restante de agendamentos do sócio para a data. |
| `isBetweenOrGreaterDates` | `isBetweenOrGreaterDates($currentDate, $startDate, $endDate = null)` | Valida intervalo de datas. |

---

## PlaceService

`app/Services/PlaceService.php` — depende de `SchedulesService` e `ScheduleRulesService`.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `getPlacesByGroup` | `getPlacesByGroup($group_id, $member_id, $date)` | Espaços disponíveis do grupo, com horários, contagem de disponibilidade e limite diário. |

---

## PlaceGroupService

`app/Services/PlaceGroupService.php` — grupos de espaços com upload de imagens.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `store` | `store(Request $request)` | Cria grupo com anexação de dias da semana. |
| `show` | `show($placeGroup)` | Retorna os dados do grupo. |
| `update` | `update(Request $request, $placeGroup)` | Atualiza grupo (imagens + sincronização de dias da semana, em transação). |

---

## SchedulePaymentService

`app/Services/SchedulePaymentService.php`

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `createSchedulePayment` | `createSchedulePayment(StoreSchedulePaymentRequest $request)` | Cria o registro de pagamento (em transação). |

---

## MemberService

`app/Services/MemberService.php` — sócios, com integração ao SQL Server (MultiClubes).

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `memberByCpf` | `memberByCpf(Request $request)` | Busca ou cria o sócio pelo CPF. |
| `getMemberById` | `getMemberById($member_id)` | Recupera o registro do sócio. |
| `store` | `store($cpf, $title, $birthDate)` | Cria sócio a partir da consulta externa e recupera a foto. |
| `queryMember` | `static queryMember($title, $document, $birthdate)` | Consulta o SQL Server por dados do sócio. |
| `getPhotoBlob` | `static getPhotoBlob($photoID)` | Recupera o blob binário da foto no SQL Server. |

**Conexões:** `mc_sqlsrv` (dados) e `mc_sqlsrv_image` (fotos). Converte binário em base64.

---

## FreelancerService

`app/Services/FreelancerService.php`

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `create` | `create($data)` | Cria um freelancer. |
| `get` | `get($cpf)` | Recupera o freelancer pelo CPF. |
| `getFunctions` | `getFunctions()` | Lista as funções disponíveis. |
| `createService` | `createService($data)` | Cria um registro de serviço de freelancer. |

---

## CompTimeService

`app/Services/CompTimeService.php` — banco de horas; faz parsing de HTML (Symfony DomCrawler).

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `importFile` | `importFile(string $filePath)` | Faz parsing do HTML de ponto e importa os dados (em transação). |
| `getStructures` | `getStructures()` | Retorna as estruturas/departamentos distintos. |
| `filterEmployees` | `filterEmployees(array $filters)` | Filtra funcionários por estrutura, nome ou código. |
| `getTimeEntriesForEmployees` | `getTimeEntriesForEmployees($employees, $filters)` | Marcações de vários funcionários (período/status). |
| `getTimeEntryDetails` | `getTimeEntryDetails($employeeId, $periodStart = null, $periodEnd = null)` | Detalhes das marcações + ajustes + métricas. |
| `showDayDetails` | `showDayDetails($employeeId, $day)` | Marcações e ajustes de um dia específico. |
| `recalculateAllBalances` | `recalculateAllBalances()` | Recalcula os saldos de todos os funcionários. |

---

## CompanyService

`app/Services/CompanyService.php` — empresas, trabalhadores e regras de acesso.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `getAllCompanies` | `getAllCompanies()` | Lista todas as empresas. |
| `createCompany` | `createCompany($request)` | Cria empresa com upload de imagem. |
| `getCompanyDetails` | `getCompanyDetails($company)` | Carrega empresa com trabalhadores e regras. |
| `storeWorker` | `storeWorker($data)` | Cria trabalhador (imagem em base64). |
| `storeAccessRule` | `storeAccessRule($data)` | Cria regra de acesso com sincronização de dias da semana. |
| `validateTryToAccess` | `validateTryToAccess($data)` | Valida a tentativa de acesso por CPF ou nome da empresa. |

---

## RuleValidatorService

`app/Services/RuleValidatorService.php` — validação de regras por data/hora/dia da semana.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `validate` | `validate($rule, $data)` | Valida a regra contra a data/hora atual. |
| `isBetweenOrGreaterDates` | `isBetweenOrGreaterDates($currentDate, $startDate = null, $endDate = null)` | Verifica o intervalo de datas. |

---

## TournamentService

`app/Services/TournamentService.php` — CRUD de torneios, times e categorias (com transações).

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `getAllTournaments` | `getAllTournaments()` | Lista paginada de torneios (status + categorias). |
| `getTournamentById` | `getTournamentById($id)` | Torneio com relacionamentos. |
| `createTournament` | `createTournament(array $data)` | Cria torneio (transação). |
| `updateTournament` | `updateTournament($id, array $data)` | Atualiza torneio (transação). |
| `deleteTournament` | `deleteTournament($id)` | Remove torneio (transação). |
| `getAllTeams` | `getAllTeams()` | Lista paginada de times (com membros). |
| `createTeam` | `createTeam(array $data)` | Cria time. |
| `updateTeam` | `updateTeam($id, array $data)` | Atualiza time. |
| `deleteTeam` | `deleteTeam($id)` | Remove time. |
| `getAllCategories` | `getAllCategories()` | Lista categorias. |
| `createCategory` | `createCategory(array $data)` | Cria categoria. |
| `updateCategory` | `updateCategory($id, array $data)` | Atualiza categoria. |
| `deleteCategory` | `deleteCategory($id)` | Remove categoria. |

---

## TelegramService

`app/Services/TelegramService.php` — contatos do Telegram.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `findContacts` | `findContacts($param)` | Busca contatos por nome, chat_id ou telefone. |
| `createContact` | `createContact($data)` | Cria um contato. |
| `updateContact` | `updateContact($id, $data)` | Atualiza um contato. |

---

## WhatsAppService

`app/Services/WhatsAppService.php` — integração com o WhatsApp Cloud API (Meta Graph v18).

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `sendText` | `sendText($to, $message)` | Envia mensagem de texto via API do WhatsApp. |
| `downloadMedia` | `downloadMedia($mediaId)` | Baixa a mídia do WhatsApp e salva no storage. |

---

## RedeItauService

`app/Services/RedeItauService.php` — gateway de pagamento (Itaú/Rede).

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `authenticate` | `authenticate(): string` | Autentica na API (OAuth, client credentials) e retorna o token. |
| `getTransaction` | `getTransaction(string $tid): array` | Consulta os detalhes de uma transação. |
| `beginRefund` | `beginRefund(array $payments_ids)` | Inicia o estorno de pagamentos. |

---

## EmailService

`app/Services/EmailService.php`

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `processContactForm` | `processContactForm(array $data): void` | Envia o e-mail do formulário de contato (Mailable `ContactMail`), com tratamento de exceções. |

---

## LoginTokenService

`app/Services/LoginTokenService.php`. Ver [Autenticação e Permissões](04-autenticacao-e-permissoes.md) (seção *LoginTokenService*).

## JwtService

`app/Providers/Services/JwtService.php`. Ver [Autenticação e Permissões](04-autenticacao-e-permissoes.md) (seção *JwtService*).
