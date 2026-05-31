# 9. Requests, Resources, Middleware e Policies

## 9.1. Form Requests (`app/Http/Requests/`)

Os Form Requests centralizam **autorização** (`authorize()`) e **validação** (`rules()`).
Seguem o padrão `Store{Entidade}Request` / `Update{Entidade}Request`. Muitos `Update*`
e alguns `Store*` ainda têm regras vazias (scaffold).

### Principais regras

| Request | `authorize()` | Regras (`rules()`) |
|---------|---------------|--------------------|
| `StoreCompanyRequest` | `true` | `name` (req, max 255), `telephone` (nullable, max 20), `email` (req, email), `address` (nullable, max 500), `image` (nullable, image, max 2MB), `description` (nullable, max 1000) |
| `StoreFreelancerRequest` | `true` | `name`, `cpf` (max 11), `rg`, `email` (email), `nacionality`, `civil_status`, `address`, `telephone` (todos obrigatórios) |
| `StoreMemberRequest` | `true` | `title` (req, max 8), `cpf` (req), `birthDate` (req, date), `password` (nullable) |
| `UpdateMemberRequest` | `true` | `title` (max 8), `login` (req), `birthDate` (date), `password` (req), `new_password` |
| `StorePlaceGroupRequest` | `true` | `name`, `category` (req, max 255), `image_vertical`, `image_horizontal` (req, image jpeg/png/jpg), `status` (nullable, bool) |
| `StoreInformationRequest` | `true` | `name` (req, max 255), `description` (req), `image` (image jpeg/png/jpg/gif/svg) |
| `LoginRequest` | `true` | `email` (req, email), `password` (req); inclui `authenticate()` e rate limiting. |
| `ProfileUpdateRequest` | — | `name` (req), `email` (req, email, único exceto o próprio usuário). |

### Lista completa de Form Requests

Pares Store/Update existem para: **Access, CompanyAccessRules, Company, CompanyWorker,
DataInfo, Employee, Freelancer, FreelancerService, FunctionFreelancer, Information, Member,
Parking, Place, PlaceGroup, SchedulePayment, Schedule, ScheduleRules, TimeAdjustment,
TimeEntry, Visitor**. Há ainda `StoreAccessRuleRequest`, `Auth/LoginRequest`,
`EmailRequest` e `ProfileUpdateRequest`.

> Requests com `authorize()` retornando `false` bloqueiam a ação por padrão — devem ser
> revisados antes de habilitar os respectivos endpoints.

---

## 9.2. Resources (`app/Http/Resources/`)

### UserResource
`toArray(Request $request): array` — transforma o usuário em array para resposta JSON.
Atualmente delega a `parent::toArray($request)` (serialização padrão dos atributos).

---

## 9.3. Middleware (`app/Http/Middleware/`)

| Classe | Alias | Função |
|--------|-------|--------|
| `APIToken` | `api_token` | Compara o header `Authorization` com `"Bearer " . config('services.api.token')`. `OPTIONS` passa direto; inválido → `401 {"message":"Invalid API Token"}`. |
| `JwtMiddleware` | `login_token` | Lê o token do header `Session`, valida via `JwtService::validateToken()` e injeta o payload em `user`. Ausente → `400`; inválido → `401`. |
| `AdminAccessMiddleware` | — | Placeholder (apenas repassa a requisição); não registrado como alias. |

Detalhes e exemplos em [Autenticação e Permissões](04-autenticacao-e-permissoes.md).

---

## 9.4. Policies (`app/Policies/`)

Há uma Policy por entidade, usada para autorização fina (via `authorize()`/Gate). São elas:

`AccessPolicy`, `AccessRulePolicy`, `CompanyPolicy`, `CompanyAccessRulesPolicy`,
`CompanyWorkerPolicy`, `DataInfoPolicy`, `EmployeePolicy`, `FreelancerPolicy`,
`FreelancerServicePolicy`, `FunctionFreelancerPolicy`, `InformationPolicy`, `MemberPolicy`,
`OuterPolicy`, `ParkingPolicy`, `PlacePolicy`, `PlaceGroupPolicy`, `SchedulePolicy`,
`SchedulePaymentPolicy`, `ScheduleRulesPolicy`, `TimeAdjustmentPolicy`, `TimeEntryPolicy`,
`VisitorPolicy`.

Cada Policy implementa os métodos típicos de CRUD: `viewAny`, `view`, `create`, `update`,
`delete`, `restore`, `forceDelete` — recebendo o `User` autenticado e (quando aplicável) a
instância do model.
