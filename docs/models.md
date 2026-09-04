# 7. Models

Entidades Eloquent (`app/Models/`). Para cada model: **conexão/tabela** (quando difere do
padrão), **`$fillable`**, **`$casts`**, **relacionamentos** e particularidades (soft
deletes, scopes globais, etc.).

> Padrão: conexão `mysql`, nome de tabela inferido pelo plural do model, `timestamps` ativos.

---

## Núcleo de agendamento

### Schedule
- **Tabela:** `schedules`
- **`$fillable`:** `place_id`, `member_id`, `start_schedule`, `end_schedule`, `status_id`, `price`, `description`, `schedule_payment_id`, `created_by_user`, `updated_by_user`
- **`$casts`:** `start_schedule`→`datetime`, `end_schedule`→`datetime`, `price`→`decimal:2`
- **Relacionamentos:** `place()` belongsTo Place · `member()` belongsTo Member · `status()` belongsTo Status · `schedulePayment()` belongsTo SchedulePayment · `creator()` belongsTo User (`created_by_user`) · `editor()` belongsTo User (`updated_by_user`)
- **Scope global:** exclui registros com `status_id = 4` (expirados).

### ScheduleRules
- **Tabela:** `schedule_rules` · **SoftDeletes** · eager load de `weekdays` por padrão
- **`$fillable`:** `name`, `status_id`, `type`, `start_time`, `end_time`, `start_date`, `end_date`
- **Relacionamentos:** `places()` belongsToMany Place (pivô `place_schedule_rule`) · `weekdays()` belongsToMany Weekday (pivô `week_days_schedule_rule`)

### SchedulePayment
- **Tabela:** `schedule_payments`
- **`$fillable`:** `payment_method`, `paid_amount`, `paid_at`, `payment_integration_id`, `status_id`
- **Relacionamentos:** `schedule()` hasMany Schedule (`schedule_payment_id`)

---

## Espaços

### Place
- **Tabela:** `places` · **SoftDeletes** · **scope global:** ordena por `name` asc
- **`$fillable`:** `name`, `image`, `place_group_id`, `price`, `status_id`
- **Relacionamentos:** `group()` belongsTo PlaceGroup (`place_group_id`) · `schedule()` hasMany Schedule · `scheduleRules()` belongsToMany ScheduleRules (pivô `place_schedule_rule`)

### PlaceGroup
- **Tabela:** `place_groups` · **SoftDeletes**
- **`$fillable`:** `name`, `category`, `image_vertical`, `image_horizontal`, `status`, `vertices`, `icon`, `minimum_antecedence`, `maximum_antecedence`, `duration`, `interval`, `daily_limit`, `start_time`, `end_time`, `start_time_sales`, `end_time_sales`
- **Relacionamentos:** `places()` hasMany Place · `tournaments()` hasMany Tournament (`group_id`) · `weekdays()` belongsToMany Weekday (pivô `week_days_place_group`)

### Weekday
- **Tabela:** `weekdays` · `timestamps = false`
- **`$fillable`:** `name`, `short_name`, `name_pt`, `short_name_pt`
- **Relacionamentos:** `scheduleRules()` belongsToMany ScheduleRules · `companyAccessRules()` belongsToMany CompanyAccessRule · `placeGroups()` belongsToMany PlaceGroup

---

## Sócios e usuários

### Member
- **Tabela:** `members`
- **`$fillable`:** `Id`, `title`, `cpf`, `birth_date`, `Barcode`, `Name`, `Titular`, `telephone`, `Email`, `Password`
- **`$hidden`:** `password`, `created_at`, `updated_at`, `deleted_at`
- **Relacionamentos:** nenhum definido (dados de sócio vêm também da base externa).

### User
- **Tabela:** `users` · **SoftDeletes** · `Authenticatable`, `Notifiable`, `HasRoles` (Spatie)
- **`$fillable`:** `name`, `email`, `password`, `cpf`, `matricula`, `last_login_at`, `status_id`
- **`$casts`:** `email_verified_at`→`datetime`, `password`→`hashed`, `last_login_at`→`datetime`
- **Relacionamentos:** `data_info()` hasMany DataInfo (`created_by`) · `information()` hasMany Information (`created_by`) · `status()` belongsTo Status · `schedulesCreated()` hasMany Schedule (`created_by_user`) · `schedulesUpdated()` hasMany Schedule (`updated_by_user`)

---

## Freelancers

### Freelancer
- **Tabela:** `freelancers`
- **`$fillable`:** `name`, `cpf`, `pix_key`, `rg`, `email`, `nacionality`, `civil_status`, `address`, `telephone`
- **Relacionamentos:** `freelancerServices()` hasMany FreelancerService
- **Chave PIX:** `pixKey()` (sem chave informada, é o CPF), `pixKeyTypeFor()` / `pixKeyTypeLabel()`,
  `formatPixKey()`, `normalizePixKey()` e `pixKeyError()`. O **tipo é lido da chave já
  normalizada** — o `+55` do telefone é o que o separa de um CPF de 11 dígitos —, e é escolhido pelo
  operador na hora de gravar, nunca adivinhado. Ver
  [Freelancers → Conferência da chave PIX](funcionalidades/freelancers.md#conferência-da-chave-pix-etapa-que-antecede-a-assinatura).

### FunctionFreelancer
- **Tabela:** `function_freelancers`
- **`$fillable`:** `name`, `description`, `price`
- **Relacionamentos:** `freelancerServices()` hasMany FreelancerService

### FreelancerService
- **Tabela:** `freelancer_services`
- **`$fillable`:** `freelancer_id`, `function_freelancer_id`, `start_date`, `end_date`, `price`, `pix_key`, `pix_key_confirmed_at`, `total_hours`, `status_id`
- **Chave PIX do contrato:** `pix_key` é a **cópia congelada** na assinatura do freelancer — o
  documento assinado não pode passar a citar outra chave porque o cadastro mudou. `pixKey()` cai no
  cadastro quando a cópia não existe (contratos antigos e assinaturas pela API);
  `pixKeyDivergesFromFreelancer()` é o aviso de que o Pix sairá para chave diferente da do documento.
- **Relacionamentos:** `freelancer()` belongsTo Freelancer · `functionFreelancer()` belongsTo FunctionFreelancer · `status()` belongsTo Status · `pixPayments()` hasMany PixPayment · `latestPixPayment()` hasOne PixPayment (`latestOfMany`)
- **Estado do Pix:** `hasPixInProgress()` e `canRequestPix()` — leitura da tela. Quem decide de
  fato é `FreelancerService::pixBlockReason()` no servidor, com lock.
- **Liberação para a coordenação:** `releasesAt()` / `hasBeenReleased()` / `releaseBlockReason()` e
  o escopo `released()` (com `lastReleasedDate()`) — o contrato só é assinado pelo coordenador e
  entra em lote às **08h do dia seguinte ao turno** (`RELEASE_HOUR`), porque até lá ainda cabe
  aditivo. Ver [Freelancers](funcionalidades/freelancers.md).
- **Etapa do trâmite:** `trackingStage()` / `trackingStageLabel()` sobre `TRACKING_STAGES` — a
  leitura única das quatro etapas (assinaturas, gerência, diretoria, pagamento) que a tela de
  Acompanhamento usa. Os escopos `awaitingSignature`, `awaitingManagerReview`,
  `awaitingDirectorReview`, `awaitingPayment` e `paidServices` são a mesma regra em SQL.
- **Jantar do turno noturno:** `isDinnerEligible()` (6h ou mais, estar em serviço em algum momento
  da janela de 17:30 às 18:30 — meia janta é janta — e o dia ser igual ou posterior a
  `DINNER_STARTS_ON`, **31/08/2026**, quando a cozinha passa a servir), `dinnerDate()` (o dia em que
  ele janta — que no turno virando a
  meia-noite **não** é `start_date`), `needsDinnerAnswer()` e o escopo `dinnerConfirmedOn($date)`,
  que é a consulta da cozinha. As colunas são `dinner_wanted`, `dinner_date`, `dinner_answered_at` e
  `dinner_answered_by`. Ver [Freelancers](funcionalidades/freelancers.md).
- **Chave PIX do documento:** `pix_key` + `pix_key_confirmed_at` guardam a chave que o freelancer
  conferiu no tablet ao assinar — é ela que o contrato cita (`pixKey()`, com queda para o cadastro
  nos contratos antigos). `pixKeyDivergesFromFreelancer()` acusa o cadastro alterado depois da
  assinatura. Ver [Freelancers](funcionalidades/freelancers.md).

### PixPayment
- **Tabela:** `pix_payments` — **trilha de auditoria de dinheiro real.** Uma linha por
  tentativa, nunca sobrescrita nem apagada. Ver [Pix automático (Sicoob)](funcionalidades/pix-sicoob.md).
- **`$fillable`:** `freelancer_service_id`, `freelancer_id`, `idempotency_key`, `end_to_end_id`, `pix_key`, `payee_document`, `payee_name`, `payee_key_type`, `amount`, `description`, `status`, `bank_state`, `rejection_detail`, `request_payload`, `response_payload`, `environment`, `requested_by`, `initiated_at`, `confirmed_at`, `finalized_at`, `last_checked_at`
- **`$casts`:** `amount` → `decimal:2` · `request_payload`/`response_payload` → `array` · os quatro timestamps → `datetime`
- **Relacionamentos:** `freelancerService()` belongsTo FreelancerService · `freelancer()` belongsTo Freelancer · `requestedBy()` belongsTo User
- **`status`** — responde "o dinheiro saiu?":

  | Valor | O dinheiro saiu? | Pode reenviar? |
  |-------|------------------|----------------|
  | `pending` | Não | — |
  | `initiated` | Não (só reservou o `endToEndId`) | — |
  | `sent` | Está saindo | Não |
  | `finalized` | **Sim** | Não |
  | `rejected` | Não — o banco recusou | **Sim** |
  | `failed` | Não — parou antes de confirmar | **Sim** |
  | `unknown` | **Não sabemos** | **NUNCA** |

- **Métodos:** `canBeRetried()` (só `failed`/`rejected`) · `needsManualCheck()` (`unknown`) ·
  `isPending()` · `isFinalized()` · `statusLabel()`
- **Escopos:** `blocking()` (impedem novo envio para o contrato) · `open()` (a reconciliação precisa resolver)
- **Índices:** `end_to_end_id` e `idempotency_key` são **únicos** — é o índice único do
  `end_to_end_id` que impede duas linhas reivindicarem a mesma transação no SPI.
- **Sem FK para `users`** em `requested_by`: o model User fixa a conexão `mysql`.

---

## Banco de horas

### Employee
- **Tabela:** `employees` · **SoftDeletes**
- **`$fillable`:** `employee_code`, `name`, `cpf`, `admission_date`, `position`, `department`
- **Relacionamentos:** `timeEntries()` hasMany TimeEntry

### TimeEntry
- **Tabela:** `time_entries`
- **`$fillable`:** `employee_id`, `entry_date`, `reference_time`, `entry_times`, `type`, `amount_minutes`, `balance_minutes`, `due_date`, `status_id`
- **Relacionamentos:** `employee()` belongsTo Employee · `adjustmentsToAdjust()` hasMany TimeAdjustment (`entry_time_to_adjust_id`) · `adjustmentsAdjusted()` hasMany TimeAdjustment (`entry_time_adjusted_id`) · `status()` belongsTo Status

### TimeAdjustment
- **Tabela:** `time_adjustments`
- **`$fillable`:** `entry_time_to_adjust_id`, `entry_time_adjusted_id`, `amount_minutes`, `before_adjustment_minutes`, `after_adjustment_minutes`, `reason`

---

## Conteúdo informativo

### Information
- **Tabela:** `information` · **SoftDeletes**
- **`$fillable`:** `created_by`, `privacy`
- **Relacionamentos:** `user()` belongsTo User (`created_by`) · `data_info()` hasMany DataInfo (`information_id`)

### DataInfo
- **Tabela:** `data_infos` · **SoftDeletes** · `UPDATED_AT = null`
- **`$fillable`:** `name`, `description`, `fee`, `image`, `category`, `responsible`, `responsible_contact`, `name_price`, `price_associated`, `price_not_associated`, `slots`, `day_hour`, `location`, `status`, `information_id`, `created_by`
- **Relacionamentos:** `user()` belongsTo User (`created_by`) · `information()` belongsTo Information

---

## Status, acesso e estacionamento

### Status
- **Tabela:** `status` · `timestamps = false`
- **`$fillable`:** `name`, `portuguese`
- **Relacionamentos:** `schedule()` hasMany Schedule · `users()` hasMany User · `timeEntries()` hasMany TimeEntry

### Access  (base externa)
- **Conexão:** `mc_sqlsrv` · **Tabela:** `Analytics.RealizedAccesses` (somente leitura) · sem `$fillable`/relacionamentos.

### Visitor  (base externa)
- **Conexão:** `mc_sqlsrv` · **Tabela:** `dbo.Visitors`
- **`$fillable`:** `Id`, `Name`, `MobilePhone`

### Parking
- **Tabela:** `parkings`
- **`$fillable`:** `plate`, `color`, `entry_date`, `file`

### TelegramContact
- **Tabela:** `contact_telegram`
- **`$fillable`:** `name`, `chat_id`, `phone`

---

## Empresas (`app/Models/Company/`)

### Company
- **`$fillable`:** `name`, `address`, `email`, `telephone`, `description`, `image`
- **Relacionamentos:** `workers()` hasMany CompanyWorker · `rules()` hasMany CompanyAccessRule

### CompanyWorker
- **`$fillable`:** `company_id`, `name`, `email`, `position`, `document`, `telephone`, `image`
- **Relacionamentos:** `company()` belongsTo Company · `rules()` (regras de acesso do trabalhador)

### CompanyAccessRule
- **Tabela:** `company_access_rules`
- **`$fillable`:** `company_id`, `company_worker_id`, `type`, `start_date`, `end_date`, `start_time`, `end_time`, `description`
- **Relacionamentos:** `company()` belongsTo Company · `worker()` belongsTo CompanyWorker · `weekdays()` belongsToMany Weekday (pivô `week_days_company_access_rule`)

---

## Torneios (`app/Models/Tournament/`)

### Tournament
- **`$fillable`:** `title`, `description`, `start_date`, `end_date`, `start_date_subscription`, `end_date_subscription`, `max_teams`, `status_id`, `group_id`
- **`$casts`:** `start_date`, `end_date`, `start_date_subscription`, `end_date_subscription` → `datetime`
- **Relacionamentos:** `categories()` belongsToMany Category (pivô `tournaments_categories`) · `status()` belongsTo Status

### Category
- **`$fillable`:** `name`, `member_by_team`
- **Relacionamentos:** `tournaments()` belongsToMany Tournament

### TournamentCategory
- **Tabela:** `tournaments_categories`
- **`$fillable`:** `tournament_id`, `category_id`, `entry_price`
- **Relacionamentos:** `tournament()` belongsTo Tournament · `category()` belongsTo Category · `subscriptions()` hasMany TournamentSubscription

### Team
- **`$fillable`:** `name`, `member_id`
- **Relacionamentos:** `owner()` belongsTo (responsável) · `members()` belongsToMany · `subscriptions()` hasMany TournamentSubscription

### TournamentSubscription
- **Tabela:** `tournament_subscription`
- **`$fillable`:** `team_id`, `tournament_category_id`, `status_id`
- **Relacionamentos:** `team()` belongsTo Team · `tournamentCategory()` belongsTo TournamentCategory · `payments()` hasMany TournamentSubscriptionPayment

### TournamentSubscriptionPayment
- **Tabela:** `tournament_subscription_payment`
- **`$fillable`:** `tournament_subscription_id`, `payment_method`, `paid_amount`, `payment_integration_id`, `paid_at`, `status_id`
- **`$casts`:** `paid_at` → `datetime`
- **Relacionamentos:** `subscription()` belongsTo TournamentSubscription

---

## WhatsApp (`app/Models/WhatsApp/`)

### Contact
- **`$fillable`:** `wa_id`, `name`, `profile_pic_url`
- **Relacionamentos:** `conversations()` hasMany Conversation · `activeConversation()` hasOne Conversation (status `open`, mais recente)

### Conversation
- **`$fillable`:** `contact_id`, `user_id`, `status`, `last_message_at`
- **Relacionamentos:** `messages()` hasMany Message
- **Nota:** `status` (`open`/`closed`) e `last_message_at` controlam o ciclo da conversa (janela de 24h do WhatsApp).

### Message
- **`$fillable`:** `conversation_id`, `wam_id`, `type`, `direction`, `body`, `status`
- **Relacionamentos:** `media()` hasOne MediaAttachment
- **`direction`:** `inbound`/`outbound`.

### MediaAttachment
- **`$fillable`:** `message_id`, `whatsapp_media_id`, `file_type`, `mime_type`, `file_path`, `file_name`

---

## Placar Clube (`app/Models/Placar/`)

API + telas para o placar eletrônico do Node (futsal, basquete, vôlei). O Node não tem banco
próprio de cadastro — consome esta API. Ver `docs/placar-clube-api.md`.

### Modalidade
- **Tabela:** `modalidades` — referência fixa (3 linhas, seed em `ModalidadeSeeder`)
- **`$fillable`:** `nome`, `slug`, `ativo` · **`$casts`:** `ativo` → `boolean`
- **Relacionamentos:** `times()` hasMany Time · `competicoes()` hasMany Competicao · `jogos()` hasMany Jogo
- `slug` (`futsal`/`basquete`/`volei`) é o que o Node usa no campo `esporte` do gameState.

### Equipe
- **Tabela:** `equipes` · **SoftDeletes**
- **`$fillable`:** `nome`, `nome_curto`, `logo_path`, `cidade`, `criado_em_campo`, `ativo`
- **Relacionamentos:** `times()` hasMany Time
- **`logoUrl()`** resolve a URL absoluta da logo própria (ou `null`) — é o fallback de `Time::logoUrl()`.

### Time
- **Tabela:** `times` · **SoftDeletes** · `UNIQUE (equipe_id, modalidade_id, categoria)`
- **`$fillable`:** `equipe_id`, `modalidade_id`, `categoria`, `nome_exibicao`, `logo_path`, `criado_em_campo`, `ativo`
- **Relacionamentos:** `equipe()` belongsTo Equipe · `modalidade()` belongsTo Modalidade ·
  `elencos()` hasMany Elenco · `escalacoes()` hasMany Escalacao · `jogosEmCasa()`/`jogosFora()`
  hasMany Jogo (`time_casa_id`/`time_fora_id`)
- Recorte de uma equipe por modalidade + categoria — não confundir com Equipe. Um mesmo
  jogador pode estar em times diferentes da mesma equipe (ex.: Sub-17 → Adulto), com número de
  camisa próprio em cada um (ver Elenco).
- **`categoria` passa por `CategoriaService::resolver()`** nas duas portas de entrada (cadastro
  web e criação em campo da API): "Sub 15"/"sub15"/"SUB-15" caem na grafia já cadastrada em vez
  de furar o UNIQUE e duplicar o time. Um jogo só pode ser criado entre times da **mesma**
  categoria, comparada por `CategoriaService::chave()` (sem caixa/acento/separador, para não
  barrar grafias legadas divergentes).
- **`nomeExibicaoResolvido()`**: se `nome_exibicao` for nulo, monta a partir de
  `equipe.nome_curto ?: equipe.nome` + `categoria`. **`logoUrl()`**: própria, senão herda da equipe.

### Jogador
- **Tabela:** `jogadores` · **SoftDeletes** · índice `(equipe_id, modalidade_id)`
- **`$fillable`:** `equipe_id`, `modalidade_id`, `nome`, `nome_exibicao`, `foto_path`, `video_path`, `data_nascimento`, `criado_em_campo`, `ativo`
- **`$casts`:** `data_nascimento` → `date`
- **Relacionamentos:** `equipe()` belongsTo Equipe · `modalidade()` belongsTo Modalidade ·
  `elencos()` hasMany Elenco · `escalacoes()` hasMany Escalacao ·
  `eventos()` hasMany JogoEvento · `times()` belongsToMany Time (pivô `elencos`, com `temporada`/`numero`/`posicao`/`ativo`)
- **O jogador pertence a UMA equipe e UMA modalidade.** Pode estar em vários times daquela
  equipe (Sub-15 e Adulto, por exemplo), nunca em time de outra equipe ou modalidade.
  `podeJogarPor(Time)` é o ponto único da regra; o scope `elegiveisPara(Time)` filtra os
  candidatos ao elenco. Trocar equipe/modalidade só é possível enquanto o jogador não
  estiver em elenco nenhum — os vínculos existentes ficariam inválidos.
- As colunas são nullable no schema (para a migration não abortar em base que já tinha
  jogadores) mas obrigatórias na aplicação. `precisaDeRevisao()` marca quem ficou sem, e a
  listagem tem filtro "só pendentes de revisão".
- **`nomeExibicaoResolvido()`**: nome curto do telão, ou `nome`. **`fotoUrl()`**/**`videoUrl()`**: URL absoluta ou `null`.
- **Como dado pessoal, o cadastro guarda só nome e data de nascimento** — `documento` foi
  removido (não era usado em lugar nenhum do módulo). **`idade()`** devolve os anos
  completos, ou `null` sem data de nascimento: é o que a montagem do elenco mostra para
  conferir a categoria do time, e o que a API entrega em `idade`.
- Foto e vídeo são independentes e usados em momentos diferentes pelo telão (foto na
  escalação/súmula, vídeo na entrada em quadra). Ambos vivem em
  `public/storage/placar/jogadores/{id}/` — arquivo estático, ver `ImagemService`/`VideoService`.

### Elenco
- **Tabela:** `elencos` — vínculo jogador ↔ time por temporada · `UNIQUE (time_id, jogador_id, temporada)`
- **`$fillable`:** `time_id`, `jogador_id`, `temporada`, `numero`, `posicao`, `ativo`
- **Relacionamentos:** `time()` belongsTo Time · `jogador()` belongsTo Jogador

### Competicao
- **Tabela:** `competicoes`
- **`$fillable`:** `nome`, `modalidade_id`, `temporada`, `ativo`
- **Relacionamentos:** `modalidade()` belongsTo Modalidade · `jogos()` hasMany Jogo

### Jogo
- **Tabela:** `jogos` · **SoftDeletes**
- **`$fillable`:** `competicao_id`, `modalidade_id`, `time_casa_id`, `time_fora_id`, `data_hora`,
  `local`, `status`, `placar_casa`, `placar_fora`, `sets_casa`, `sets_fora`, `periodos_jogados`,
  `criado_em_campo`, `observacoes`
- **`$casts`:** `data_hora` → `datetime`
- **Relacionamentos:** `competicao()` belongsTo Competicao (nullable) · `modalidade()` belongsTo
  Modalidade · `timeCasa()`/`timeFora()` belongsTo Time · `escalacoes()` hasMany Escalacao ·
  `eventos()` hasMany JogoEvento (ordenado por `sequencia`)
- **`status`**: `agendado` | `ao_vivo` | `encerrado` | `cancelado` (const `STATUSES`).
  `placar_casa`/`placar_fora`/`sets_*` são **cache** derivado de `jogo_eventos` — a verdade é
  sempre o log de eventos.

### Escalacao
- **Tabela:** `escalacoes` — quem foi relacionado NESTE jogo (distinto do elenco da temporada) ·
  `UNIQUE (jogo_id, jogador_id)`
- **`$fillable`:** `jogo_id`, `time_id`, `jogador_id`, `numero`, `titular`, `capitao`
- **Relacionamentos:** `jogo()` belongsTo Jogo · `time()` belongsTo Time · `jogador()` belongsTo Jogador

### JogoEvento
- **Tabela:** `jogo_eventos` — **log append-only, o coração do scout.** `UPDATED_AT = null`.
  `UNIQUE (jogo_id, sequencia)` · índices em `jogador_id` e `tipo`
- **`$fillable`:** `uuid`, `jogo_id`, `sequencia`, `tipo`, `time_id`, `jogador_id`, `valor`,
  `periodo`, `cronometro_ms`, `ocorrido_em`, `payload`
- **`$casts`:** `ocorrido_em` → `datetime:Y-m-d H:i:s.v` (precisão de ms) · `payload` → `array`
- **Relacionamentos:** `jogo()` belongsTo Jogo · `time()`/`jogador()` belongsTo (nullable)
- **Nunca sofre UPDATE nem DELETE pela API.** `uuid` é gerado pelo Node (idempotência contra
  reenvio de fila offline); `sequencia` é o contador incremental do jogo, também gerado pelo
  Node. Correção de um evento é um evento novo `estorno` referenciando o `uuid` original no
  `payload`. `tipo` ∈ const `TIPOS` (`inicio_jogo`, `fim_jogo`, `ponto`, `falta`, `set`,
  `periodo`, `crono_play`, `crono_pause`, `crono_set`, `substituicao`, `timeout`, `cartao`,
  `estorno`).
- **`TIPOS_COM_MINUTAGEM`** (`ponto`, `falta`) exigem `cronometro_ms`: o scout mede atuação
  numa partida, e sem o instante do lance a ficha não serve. Evento sem minutagem é rejeitado
  individualmente, sem derrubar o lote. `formatarMinuto()`/`minuto()` convertem para "MM:SS"
  num ponto só, para súmula, ficha de atuação e Node exibirem igual.
