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
- **`$fillable`:** `Id`, `title`, `cpf`, `birth_date`, `Barcode`, `Name`, `Titular`, `telephone`, `Email`, `image`, `Password`
- **`$hidden`:** `image`, `password`, `created_at`, `updated_at`, `deleted_at`
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
- **`$fillable`:** `name`, `cpf`, `rg`, `email`, `nacionality`, `civil_status`, `address`, `telephone`
- **Relacionamentos:** `freelancerServices()` hasMany FreelancerService

### FunctionFreelancer
- **Tabela:** `function_freelancers`
- **`$fillable`:** `name`, `description`, `price`
- **Relacionamentos:** `freelancerServices()` hasMany FreelancerService

### FreelancerService
- **Tabela:** `freelancer_services`
- **`$fillable`:** `freelancer_id`, `function_freelancer_id`, `start_date`, `end_date`, `price`, `total_hours`, `status_id`
- **Relacionamentos:** `freelancer()` belongsTo Freelancer · `functionFreelancer()` belongsTo FunctionFreelancer · `status()` belongsTo Status

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
- **Relacionamentos:** `user()` belongsTo User (`created_by`) · `data_info()` hasMany DataInfo

### DataInfo
- **Tabela:** `data_infos` · **SoftDeletes** · `UPDATED_AT = null`
- **`$fillable`:** `name`, `description`, `fee`, `image`, `category`, `responsible`, `responsible_contact`, `name_price`, `price_associated`, `price_not_associated`, `slots`, `day_hour`, `location`, `status`, `information_id`, `created_by`, `before_data`
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
