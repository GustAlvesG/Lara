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
| **Espaços** | `place_groups`, `places`, `weekdays`, `week_days_place_group` (pivô), `place_conflicts` (pares de espaços que não podem ser usados no mesmo dia) |
| **Espaços sociais** | `social_bookings`, `social_booking_items`, `social_booking_guests`, `social_beverages` |
| **Agendamento** | `schedule_rules`, `place_schedule_rule` (pivô), `week_days_schedule_rule` (pivô), `schedules`, `schedule_payments`, `status`, `status_config` |
| **Freelancers** | `freelancers`, `function_freelancers`, `freelancer_services` |
| **Funcionários** | `employees` (cadastro do RH; CPF só dígitos, `sector_id` obrigatório, `source`), `employee_absences` |
| **Banco de horas** | `time_entries`, `time_adjustments`, `banco_de_horas` (e `employees`, acima) |
| **Empresas** | `companies`, `company_workers`, `company_access_rules`, `week_days_company_access_rule` (pivô), `access`, `access_rules`, `outers` |
| **Torneios** | `tournament_category_templates`, `tournaments`, `tournament_categories`, `tournament_registrations`, `tournament_payments`, `tournament_teams`, `tournament_team_members`, `tournament_team_invites`, `tournament_stages`, `tournament_groups`, `tournament_group_teams`, `tournament_matches`, `tournament_match_sets`, `tournament_match_result_logs` |
| **Ingressos** | `ticket_events`, `ticket_event_days` (dias do evento de vários dias), `ticket_types` (`ticket_event_day_id`: diária; nulo = passaporte), `ticket_lots`, `ticket_coupons`, `ticket_orders`, `ticket_payments`, `tickets`, `ticket_checkins` (uma entrada por ingresso por dia), `ticket_guest_access_codes` (código de "meus ingressos" do não sócio) (e `company_access_logs.ticket_id`) |
| **Contratos (Assinaturas)** | `signature_contracts` (cópia de trabalho em `content`, número `CTL - N/AA` dado na aprovação, `signature_document_id`), `signature_contract_versions` (cada envio à Gerência), `signature_contract_comments` (observações da Gerência), `signature_contract_events` (histórico), `signature_contract_attachments`, `signature_contract_clauses` (biblioteca, por setor), `signature_contract_models` (modelos, por setor), `signature_contract_parties` (contratadas e fiadores para reaproveitar), `signature_contract_ai_reviews` (revisões automáticas pelo Claude) |
| **WhatsApp** | `contacts`, `conversations`, `messages`, `media_attachments`, `templates`, `webhook_logs` |
| **Telegram** | `contact_telegram` |
| **Conteúdo** | `information`, `data_infos`, `general_settings` |
| **Replay** | `replay_settings`, `replay_layouts`, `replay_layout_items`, `replay_cameras`, `replay_videos`, `replay_member_notifications`, `replay_api_clients` |
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
- `2026_02_23_*` — primeiro módulo de torneios; as tabelas foram apagadas por `2026_10_22_100000_drop_legacy_tournament_tables` (o módulo foi refeito do zero).
- `2026_10_22_110000` — torneios (módulo novo): as 14 tabelas do módulo (`tournaments` e `tournament_*`), com nomes curtos de FK e índice e criação idempotente.
- `2026_10_23_100000` — torneios, chaveamento (F5): `tournament_categories.bracket_published_at` e `tournament_matches.third_place` (idempotente).
- `2026_10_23_110000` — torneios, resultados e relatório (F6/F7): `tournaments.results_code_*` e `tournament_registrations.refunded_amount` (idempotente).
- `2026_10_23_120000` — Contas do site: `member_account_logs` (idempotente). `members.deleted_at` (de 2025) passa a ser usado pelo `SoftDeletes` do `Member`.
- `2026_10_27_100000` — Ingressos: as 7 tabelas do módulo (`ticket_*`, com os cupons, e `tickets`), com nomes curtos de FK e índice e criação idempotente. Ver [ingressos.md](funcionalidades/ingressos.md#modelagem).
- `2026_10_27_110000` — Ingressos no Monitor de Acesso: `company_access_logs.ticket_id` (FK `cal_ticket_fk`, idempotente).
- `2026_10_27_120000` — Ingressos, evento de vários dias: `ticket_event_days`, `ticket_types.ticket_event_day_id` e `ticket_checkins` (entrada por dia; copia as entradas já gravadas), idempotente.
- `2026_10_28_100000` — Ingressos: `ticket_lots.quantity` passa a aceitar nulo (lote sem limite).
- `2026_10_28_110000` — Ingressos: `ticket_guest_access_codes` (código por e-mail de "meus ingressos" do não sócio).
- `2026_10_29_100000` — Contratos (Assinaturas → Contratos): as 8 tabelas `signature_contract*`, nomes curtos de FK e índice, idempotente. Ver [contratos.md](funcionalidades/contratos.md#modelagem).
- `2026_10_29_110000` — Contratos: `signature_contract_ai_reviews` (revisão automática pelo Claude: veredito, resumo, apontamentos, tokens), idempotente.
- `2026_10_29_130000` — Contratos: `signature_contract_ai_reviews.withheld` (JSON, trechos sensíveis não enviados ao Claude), idempotente.
- `2026_05_19_*` — freelancers, funções e serviços.
- `2026_09_17_*` — Replay: configuração de vídeo, layouts de logomarca, câmeras, clipes e a
  permissão antiga `manage replay` (Spatie).
  Ela ficou obsoleta com a reforma do acesso: o que vale é a permissão `replay` do
  catálogo, que o `PermissionCatalogSeeder` cria no deploy.
- `2026_10_14_*` — espaços sociais: campos sociais de `place_groups`/`places`, `place_conflicts`,
  `social_beverages`, `social_bookings` (+ itens e convidados).
- `2026_10_16_100000_*` — mesas e cadeiras (base do espaço e mobiliário no catálogo), duração do
  evento por espaço e prazo de contato da equipe (`social_bookings.contact_due_at` e afins).
- `2026_10_19_100000_add_manual_fields_to_schedule_payments` — baixa manual de pagamento:
  `schedule_payments.is_manual`, `recorded_by`, `manual_notes`.

Para o estado completo, rode `php artisan migrate:status`.

## 12.4. Seeders (`database/seeders/`)

`DatabaseSeeder` orquestra os demais. Seeders disponíveis (24): `AccessRuleSeeder`,
`AccessSeeder`, `CompanyAccessRulesSeeder`, `CompanySeeder`, `CompanyWorkerSeeder`,
`DataInfoSeeder`, `EmployeeSeeder`, `FreelancerSeeder`, `FreelancerServiceSeeder`,
`FunctionFreelancerSeeder`, `InformationSeeder`, `MemberSeeder`, `OuterSeeder`,
`ParkingSeeder`, `PermissionCatalogSeeder` (o seed padrão, do deploy), `PlaceGroupSeeder`,
`PlaceSeeder`, `SectorAccessSeeder`,
`SchedulePaymentSeeder`, `ScheduleRulesSeeder`, `ScheduleSeeder`,
`TimeAdjustmentSeeder`, `TimeEntrySeeder`, `VisitorSeeder`.

> A matriz inicial setor → permissão vem da migration `sync_access_catalog`. O **catálogo** de
> permissões é mantido no banco pelo seed padrão `PermissionCatalogSeeder`, que roda em todo
> deploy: cria as permissões novas do catálogo, sem dar nenhuma a setor e sem alterar o que já
> está configurado. `SectorAccessSeeder` só coloca o usuário 1 na TI (desenvolvimento).

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
