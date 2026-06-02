# Torneios

## O que é

Gestão de **torneios esportivos**: criação dos torneios, suas **categorias**, os **times**,
as **inscrições** e os **pagamentos** de inscrição.

## Para quem

Administradores (painel web, autenticado).

## Entidades

- **Torneio (Tournament):** o evento, com período de realização e de inscrições.
- **Categoria (Category):** divisão da disputa, com quantidade de membros por time.
- **Vínculo Torneio×Categoria (TournamentCategory):** define o **preço de inscrição** daquela
  categoria no torneio.
- **Time (Team):** equipe inscrita, com um dono (sócio) e seus membros.
- **Inscrição (TournamentSubscription):** registro de um time numa categoria do torneio.
- **Pagamento (TournamentSubscriptionPayment):** pagamento da inscrição.

## Fluxo passo a passo

### Torneios
- `GET /tournaments` — lista (paginada).
- `GET /tournaments/create` → `POST /tournaments` — cria.
- `GET /tournaments/{id}` — detalhes.
- `GET /tournaments/{id}/edit` → `PUT /tournaments/{id}` — edita.
- `DELETE /tournaments/{id}` — remove.

**Campos do torneio:** `title`, `description`, `start_date`, `end_date`,
`start_date_subscription`, `end_date_subscription`, `max_teams`, `status_id`, `group_id`
(local — um grupo de espaços).

### Categorias
- `GET /tournaments/categories` — lista.
- `POST /tournaments/categories` — cria (campos `name`, `member_by_team`).
- `…/categories/{id}/edit` → `PUT …/categories/{id}` — edita.
- `DELETE …/categories/{id}` — remove.

## Campos principais

| Entidade | Campos |
|----------|--------|
| Tournament | `title`, `description`, `start_date`, `end_date`, `start_date_subscription`, `end_date_subscription`, `max_teams`, `status_id`, `group_id` |
| Category | `name`, `member_by_team` |
| TournamentCategory | `tournament_id`, `category_id`, `entry_price` |
| Team | `name`, `member_id` (dono) |
| TournamentSubscription | `team_id`, `tournament_category_id`, `status_id` |
| TournamentSubscriptionPayment | `tournament_subscription_id`, `payment_method`, `paid_amount`, `payment_integration_id`, `paid_at`, `status_id` |

## Regras de negócio

- O torneio é vinculado a um **grupo de espaços** (`group_id`) como local.
- O **preço de inscrição** fica no vínculo Torneio×Categoria (`entry_price`).
- A quantidade de membros por time é definida na **categoria** (`member_by_team`).
- As operações de criação/edição/remoção rodam em **transação** (`TournamentService`).

## Referência técnica

- Controller: [`Tournament\TournamentController`](../controllers.md#tournamentcontroller--apphttpcontrollerstournament)
- Service: [`TournamentService`](../services.md#tournamentservice)
- Models: [Torneios](../models.md#torneios-appmodelstournament)
