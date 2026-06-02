# Usuários e Permissões

## O que é

Administração dos **usuários do painel** e do controle de acesso baseado em **papéis e
permissões** (Spatie Laravel Permission).

## Para quem

Administradores com a permissão **`manage users`**.

## Pré-requisitos

- Usuário autenticado com a permissão `manage users`.

## Fluxo passo a passo

### Usuários
- `GET /users` — lista usuários com seus papéis e permissões.
- `GET /users/{id}/edit` — formulário de edição, com a lista de papéis disponíveis.
- `PUT /users/{id}` — atualiza o usuário.

**Atualização (`PUT /users/{id}`):**
1. Atualiza `name`, `email` e `status` (ativo/inativo).
2. Se uma nova `password` for informada, ela é redefinida.
3. Sincroniza o **papel** do usuário (`syncRoles`).

### Papéis e permissões
- `GET /roles-permission` — lista os papéis e suas permissões.
- A edição de um papel sincroniza as permissões selecionadas (`syncPermissions`).

## Conceitos (Spatie)

- **Permissão:** uma ação específica (ex.: `search parking`, `manage users`).
- **Papel (role):** um conjunto de permissões atribuído a usuários.
- O model `User` usa a trait `HasRoles`.
- Os middlewares `permission`, `role` e `role_or_permission` protegem rotas (ver
  [Autenticação e Permissões](../04-autenticacao-e-permissoes.md)).

## Permissões usadas nas rotas

| Permissão | Onde é exigida |
|-----------|----------------|
| `manage users` | Telas de usuários e de papéis/permissões. |
| `search parking` | Busca de placas/estacionamento. |

## Configuração inicial

Os papéis e permissões iniciais são criados pelo seeder `RolesAndPermissionsSeeder`; o seeder
`SetUserAsRoleUser` associa usuários a papéis.

## Referência técnica

- Controllers: [`UserController`, `PermissionController`](../controllers.md#usercontroller)
- Autenticação: [Autenticação e Permissões](../04-autenticacao-e-permissoes.md#44-papéis-e-permissões-spatie)
- Model: [`User`](../models.md#sócios-e-usuários)
