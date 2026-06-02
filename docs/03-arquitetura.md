# 3. Arquitetura

## Camadas

O projeto segue uma arquitetura em camadas, mantendo os controllers finos e concentrando
a lógica de negócio em **Services**:

```
Rota (routes/*.php)
   │
   ▼
Middleware  (api_token, login_token, auth, permission, role)
   │
   ▼
Controller  (app/Http/Controllers)  ── valida via Form Request
   │
   ▼
Service     (app/Services)          ── regra de negócio, transações, integrações
   │
   ▼
Model       (app/Models)            ── Eloquent ORM (MySQL / SQL Server)
   │
   ▼
Resource / View / JSON              ── resposta ao cliente
```

- **Controllers** recebem a requisição, aplicam validação (Form Requests) e delegam ao Service.
- **Services** encapsulam regras de negócio, transações de banco e chamadas a APIs externas.
- **Models** representam as entidades e relacionamentos Eloquent.
- **Form Requests** centralizam regras de validação e autorização.
- **Policies** definem autorização por entidade (Spatie + Gate).
- **Resources** transformam models em JSON (ex.: `UserResource`).

## Conexões de banco de dados

A aplicação trabalha com **múltiplas conexões**:

| Conexão | Driver | Uso |
|---------|--------|-----|
| `mysql` | MySQL | Banco principal da aplicação (agendamentos, espaços, usuários, etc.). |
| `mc_sqlsrv` | SQL Server | Base externa MultiClubes (sócios, acessos, visitantes — leitura). |
| `mc_sqlsrv_image` | SQL Server | Base externa para blobs de fotos de sócios. |

Os models que apontam para o SQL Server definem `protected $connection`:
- `Access` → `mc_sqlsrv` (tabela `Analytics.RealizedAccesses`)
- `Visitor` → `mc_sqlsrv` (tabela `dbo.Visitors`)
- Consultas de `Member` usam `MemberService::queryMember()` e `getPhotoBlob()` via SQL Server.

## Registro de middleware (bootstrap/app.php)

Os aliases de middleware são registrados em `bootstrap/app.php`:

| Alias | Classe | Função |
|-------|--------|--------|
| `api_token` | `App\Http\Middleware\APIToken` | Valida o token estático da API (header Authorization). |
| `login_token` | `App\Http\Middleware\JwtMiddleware` | Valida o token JWT do sócio (header `Session`). |
| `role` | `Spatie\...\RoleMiddleware` | Exige papel. |
| `permission` | `Spatie\...\PermissionMiddleware` | Exige permissão. |
| `role_or_permission` | `Spatie\...\RoleOrPermissionMiddleware` | Exige papel OU permissão. |

Providers carregados: `JwtServiceProvider` e `AppServiceProvider`.

## Padrões e convenções

- **Soft Deletes:** `Place`, `PlaceGroup`, `User`, `ScheduleRules`, `Information`, `DataInfo`,
  `Employee`, entre outros, usam exclusão lógica (`deleted_at`).
- **Global Scopes:**
  - `Schedule` exclui automaticamente registros com `status_id = 4` (expirados).
  - `Place` ordena por `name` ascendente por padrão.
  - `ScheduleRules` faz eager load de `weekdays` por padrão.
- **Status flexível:** a tabela `status` centraliza os estados das entidades (em vez de enums fixos), com tradução em `portuguese`.
- **Sistema de pivôs:** dias da semana (`weekdays`) ligam-se a `schedule_rules`, `place_groups` e `company_access_rules`; regras de horário ligam-se a `places`.
- **Injeção de dependência:** Services são injetados nos controllers via construtor (ex.: `__construct(protected PlaceService $placeService)`).

## Organização de pastas (`app/`)

```
app/
├── Console/            (Kernel padrão; sem comandos customizados)
├── Http/
│   ├── Controllers/    (raiz + Auth/, Company/, Tournament/)
│   ├── Middleware/      (APIToken, JwtMiddleware, AdminAccessMiddleware)
│   ├── Requests/        (pares Store*/Update* por entidade)
│   └── Resources/       (UserResource)
├── Jobs/                (ProcessWhatsAppWebhook)
├── Listeners/           (UpdateLastLoginAt)
├── Mail/                (ContactMail)
├── Models/              (raiz + Company/, Tournament/, WhatsApp/)
├── Notifications/       (ResetPasswordNotification)
├── Policies/            (uma por entidade)
├── Providers/           (AppServiceProvider, JwtServiceProvider, Services/JwtService)
├── Services/            (camada de regras de negócio)
└── View/Components/      (AppLayout, GuestLayout)
```
