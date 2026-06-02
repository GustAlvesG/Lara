# Sócios

## O que é

Gerencia o **acesso e a autenticação dos sócios** do clube no app móvel. Os dados de cadastro
e a foto vêm da base externa MultiClubes (SQL Server); a autenticação usa token JWT.

## Para quem

- **Sócios** (app móvel), via API autenticada por token de API (`api_token`) e, nas rotas
  protegidas, token JWT (`login_token`).

## Pré-requisitos

- Token de API válido (header `Authorization: Bearer <API_TOKEN>`).
- Conexões com a base MultiClubes (`mc_sqlsrv` e `mc_sqlsrv_image`) configuradas.

## Fluxo passo a passo

### Registro — `POST /api/register`
Campos: `cpf`, `title` (código do título no MultiClubes), `birthDate` e `password` (opcional).

1. Verifica se o sócio já existe localmente (tabela `members`).
2. Se não, consulta a base MultiClubes por `title` + `cpf` + `birthDate`, exigindo título
   **ativo** e **excluindo títulos especiais** (lista fixa de códigos).
3. Cria o registro local, salvando nome, contato, código de barras e a **foto convertida em
   base64**. Se não houver senha, usa o **SHA256 do CPF** como senha padrão.
4. Retorna (HTTP 201) os dados do sócio e um **token JWT** (`login_token`).

### Login — `POST /api/login`
Campos: `login` (CPF) e `password`.

1. Busca por `cpf` + `password` na tabela `members`.
2. Se não encontrar → **HTTP 401** (credenciais inválidas).
3. Se encontrar → retorna dados do sócio + token JWT.

### Verificação e consultas
- `POST /api/check-member` — verifica se um sócio existe (por `title`/`cpf`/`birthDate`).
- `POST /api/member/by-title` — busca sócios pelo título/nome (usado no agendamento).
- `PUT /api/member/update` — atualiza dados do sócio autenticado.
- `PUT /api/change-password` — altera a senha.

### Foto do sócio — `GET /api/image/{member_id}`
Retorna a imagem do sócio (base64), com cache. A foto é extraída da base de imagens do
MultiClubes (`mc_sqlsrv_image`) no momento do cadastro.

## Token JWT (`login_token`)

Gerado por `LoginTokenService::generate()`. Contém o CPF (`username`) e **expira no fim do dia
atual** (`exp = now()->endOfDay()`). É enviado pelo app no header **`Session`** e validado
pelo middleware `login_token`. Veja [Autenticação e Permissões](../04-autenticacao-e-permissoes.md).

## Campos da tabela `members`

`id`, `title`, `cpf`, `birth_date`, `Name`, `Barcode`, `telephone`, `Email`, `image`
(base64), `Password` (hash). Os campos `image` e `password` ficam ocultos nas respostas.

## Regras de negócio

- **Origem dos dados:** o cadastro depende da existência do sócio na base MultiClubes.
- **Títulos especiais** são bloqueados no registro.
- **Senha padrão** = SHA256 do CPF quando não informada.
- **Token diário:** o JWT precisa ser renovado a cada dia (expira à meia-noite).

## Integrações

- **SQL Server / MultiClubes** — dados e foto do sócio. Ver [Integrações](../integracoes.md#114-sql-server--multiclubes).

## Referência técnica

- Controllers: [`MemberController`, `MemberAuthController`](../controllers.md#membercontroller)
- Service: [`MemberService`](../services.md#memberservice)
- Model: [`Member`](../models.md#sócios-e-usuários)
