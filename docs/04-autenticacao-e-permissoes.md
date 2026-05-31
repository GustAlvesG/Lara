# 4. Autenticação e Permissões

A aplicação possui **três mecanismos de autenticação** distintos, conforme o consumidor:

| Mecanismo | Para quem | Como funciona |
|-----------|-----------|---------------|
| **Sessão (Breeze)** | Usuários administrativos (painel web) | Login por e-mail/senha, sessão e cookies. Middleware `auth`. |
| **Token de API** | Integrações externas (Telegram, etc.) | Token estático no header `Authorization: Bearer`. Middleware `api_token`. |
| **JWT de sócio** | App móvel de sócios | Token JWT no header `Session`. Middleware `login_token`. |

## 4.1. Sessão web (Laravel Breeze)

O scaffolding do Breeze fornece os controllers em `app/Http/Controllers/Auth/` e as rotas
em `routes/auth.php` (registro, login, recuperação de senha, verificação de e-mail,
confirmação de senha e logout). O painel administrativo protege rotas com o middleware
`auth` (e `verified` no dashboard).

Eventos: ao efetuar login, o listener `UpdateLastLoginAt` atualiza `users.last_login_at`
(ver [Jobs, Listeners e Notifications](jobs-listeners-notifications.md)).

## 4.2. Token de API — `APIToken` (`api_token`)

`app/Http/Middleware/APIToken.php`

```php
public function handle(Request $request, Closure $next): Response
```

- Requisições `OPTIONS` são respondidas imediatamente com `200` (suporte a CORS/preflight).
- Lê o token via `$request->bearerToken()` e compara com `config('services.api.token')`
  (variável `API_TOKEN`).
- Se ausente ou inválido → `401 {"error": "Unauthorized"}`.

Aplicado ao grupo de rotas da API em `routes/api.php` (`Route::middleware('api_token')`).

## 4.3. JWT de sócio — `JwtMiddleware` (`login_token`)

`app/Http/Middleware/JwtMiddleware.php`

```php
public function __construct(JwtService $jwtService)
public function handle(Request $request, Closure $next)
```

- Requisições `OPTIONS` retornam diretamente (passam ao próximo).
- Lê o token do header **`Session`**. Se ausente → `400 {"error": "Not Found Login Token"}`.
- Valida via `JwtService::validateToken()`. Em caso de falha → `401 {"error": "Invalid Login Token"}`.
- Em sucesso, injeta o payload decodificado na requisição via `$request->merge(['user' => $payload])`.

Algumas rotas dentro do grupo `login_token` removem essa exigência via
`->withoutMiddleware(['login_token'])` (ex.: criação de agendamento, `member/by-title`,
`time-options`).

### `JwtService` — `app/Providers/Services/JwtService.php`

Serviço registrado como singleton por `JwtServiceProvider`. Usa `firebase/php-jwt` com
algoritmo **HS256**.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `generateToken` | `generateToken(array $payload): string` | Gera um token JWT assinado a partir do payload. |
| `validateToken` | `validateToken(string $token): array` | Decodifica e valida o token, retornando o payload como array. Lança exceção se inválido/expirado. |

> ⚠️ **Atenção de segurança:** a chave secreta está **fixa no código**
> (`$this->secretKey = 'a1b2c3d4e5f6g7h8i9j0'`). Recomenda-se movê-la para uma variável
> de ambiente (`.env`) e gerar um valor forte.

### `LoginTokenService` — `app/Services/LoginTokenService.php`

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `generate` | `static generate($member)` | Cria o payload (`username` = CPF do sócio, `exp` = fim do dia atual) e retorna o JWT via `JwtService::generateToken()`. |
| `validate` | `static validate(Request $request)` | Retorna `[true]` (a validação real ocorre no middleware `login_token`). |

### `LoginTokenController` — `app/Http/Controllers/Auth/LoginTokenController.php`

| Método | Assinatura | Descrição | Rota |
|--------|-----------|-----------|------|
| `generate` | `static generate($member)` | Gera um token JWT (validade de fim de dia) para o sócio. | — (uso interno) |
| `validate` | `validate(Request $request)` | Valida o token atual (passou pelo middleware) e responde sucesso. | `GET /api/verify-token` (`api_token` + `login_token`) |

## 4.4. Papéis e permissões (Spatie)

A aplicação usa `spatie/laravel-permission`. O model `User` aplica a trait `HasRoles`.

- **Middleware:** `role`, `permission`, `role_or_permission` (registrados em `bootstrap/app.php`).
- **Uso em rotas:** ex. `->middleware('permission:manage users')` (gestão de usuários) e
  `->middleware('permission:search parking')` (busca de estacionamento).
- **Seeder:** `RolesAndPermissionsSeeder` cria os papéis e permissões iniciais;
  `SetUserAsRoleUser` associa usuários a papéis.
- **Gestão:** `UserController` (atribuição de papéis ao salvar usuário) e
  `PermissionController` (tela de papéis e permissões).

## 4.5. Policies

Cada entidade possui uma Policy em `app/Policies/` (ex.: `MemberPolicy`, `SchedulePolicy`,
`PlacePolicy`, `FreelancerPolicy`, etc.), usadas para autorização fina via Gate/`authorize()`.
Veja a lista completa em [Requests, Resources e Middleware](requests-resources-middleware.md).

## 4.6. Redefinição de senha

A notificação `ResetPasswordNotification` (canal `mail`) envia o e-mail de redefinição de
senha em português ("Solicitação de redefinição de senha"), com link contendo o token.
