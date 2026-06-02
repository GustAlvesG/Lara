# 12. Banco de Dados

Visão geral do esquema: migrações, seeders e factories. Os detalhes de cada entidade
(campos, casts, relações) estão em [Models](models.md).

## 12.1. Conexões

| Conexão | Uso |
|---------|-----|
| `mysql` (default) | Banco principal da aplicação. |
| `mc_sqlsrv` / `mc_sqlsrv_image` | Base externa SQL Server (MultiClubes), somente leitura. |

## 12.2. Principais tabelas (MySQL)

| Grupo | Tabelas |
|-------|---------|
| **Usuários/Auth** | `users`, `password_reset_tokens`, `sessions`, `permissions`, `roles`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` (Spatie) |
| **Sócios** | `members` |
| **Espaços** | `place_groups`, `places`, `weekdays`, `week_days_place_group` (pivô) |
| **Agendamento** | `schedule_rules`, `place_schedule_rule` (pivô), `week_days_schedule_rule` (pivô), `schedules`, `schedule_payments`, `status`, `status_config` |
| **Freelancers** | `freelancers`, `function_freelancers`, `freelancer_services` |
| **Banco de horas** | `employees`, `time_entries`, `time_adjustments`, `banco_de_horas` |
| **Empresas** | `companies`, `company_workers`, `company_access_rules`, `week_days_company_access_rule` (pivô), `access`, `access_rules`, `outers` |
| **Torneios** | `tournaments`, `categories`, `tournaments_categories` (pivô), `teams`, `team_member` (pivô), `tournament_subscription`, `tournament_subscription_payment` |
| **WhatsApp** | `contacts`, `conversations`, `messages`, `media_attachments`, `templates`, `webhook_logs` |
| **Telegram** | `contact_telegram` |
| **Conteúdo** | `information`, `data_infos`, `general_settings` |
| **Outros** | `parkings`, `visitors`, `cache`, `jobs` |

> As tabelas `access`, `visitors` e dados de sócios também existem na base externa SQL
> Server (lidas pelos models `Access` e `Visitor`).

## 12.3. Migrações (`database/migrations/`)

São cerca de **80 migrações**, datadas de 2024-05 a 2026-05, refletindo a evolução
incremental do esquema (criação de tabelas + diversos `add_column`/`fix`). Destaques:

- `0001_01_01_000000_create_users_table` — usuários, tokens e sessões.
- `2025_02_17_*` — núcleo de espaços e agendamento (`place_groups`, `places`,
  `schedule`, `schedule_rules`).
- `2025_08_28_*` / `2025_11_10_*` — sistema de `status` e `status_config`.
- `2025_12_23_*` — tabelas de permissões (Spatie).
- `2026_01_13_*` — tabelas do WhatsApp.
- `2026_01_14_*` — empresas, trabalhadores e regras de acesso.
- `2026_02_23_*` — torneios.
- `2026_05_19_*` — freelancers, funções e serviços.

Para o estado completo, rode `php artisan migrate:status`.

## 12.4. Seeders (`database/seeders/`)

`DatabaseSeeder` orquestra os demais. Seeders disponíveis (24): `AccessRuleSeeder`,
`AccessSeeder`, `CompanyAccessRulesSeeder`, `CompanySeeder`, `CompanyWorkerSeeder`,
`DataInfoSeeder`, `EmployeeSeeder`, `FreelancerSeeder`, `FreelancerServiceSeeder`,
`FunctionFreelancerSeeder`, `InformationSeeder`, `MemberSeeder`, `OuterSeeder`,
`ParkingSeeder`, `PlaceGroupSeeder`, `PlaceSeeder`, `RolesAndPermissionsSeeder`,
`SchedulePaymentSeeder`, `ScheduleRulesSeeder`, `ScheduleSeeder`, `SetUserAsRoleUser`,
`TimeAdjustmentSeeder`, `TimeEntrySeeder`, `VisitorSeeder`.

> `RolesAndPermissionsSeeder` cria papéis/permissões; `SetUserAsRoleUser` associa
> usuários a papéis.

## 12.5. Factories (`database/factories/`)

Factories para geração de dados de teste (23): Access, AccessRule, CompanyAccessRules,
Company, CompanyWorker, DataInfo, Employee, Freelancer, FreelancerService,
FunctionFreelancer, Information, Member, Outer, Parking, Place, PlaceGroup, Schedule,
SchedulePayment, ScheduleRules, TimeAdjustment, TimeEntry, User, Visitor.

## 12.6. Comandos úteis

```bash
php artisan migrate              # aplica migrações pendentes
php artisan migrate:status       # estado das migrações
php artisan db:seed              # roda DatabaseSeeder
php artisan migrate:fresh --seed # recria o banco e popula (cuidado: apaga dados)
```
