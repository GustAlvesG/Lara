# Freelancers

## O que é

Cadastro de **prestadores de serviço (freelancers)**, das **funções** que podem exercer e dos
**serviços prestados**. É consumido por integrações externas (bot do Telegram) via API.

## Para quem

Integrações externas autenticadas por token de API (`api_token`) — tipicamente um bot/painel
de Telegram. As rotas ficam sob o prefixo `/api/telegram/freelancer`.

## Pré-requisitos

- Token de API válido (header `Authorization: Bearer <API_TOKEN>`).

## Entidades

1. **Freelancer** — a pessoa.
2. **Função (FunctionFreelancer)** — o catálogo de funções (ex.: garçom, segurança), com
   nome, descrição e preço.
3. **Serviço (FreelancerService)** — um trabalho prestado por um freelancer numa função,
   com período, valor, total de horas e status.

## Fluxo passo a passo

### Cadastrar freelancer — `POST /api/telegram/freelancer/freelancer`
Campos obrigatórios: `name`, `cpf` (até 11 dígitos), `rg`, `email`, `nacionality`,
`civil_status`, `address`, `telephone`. Retorna **HTTP 201** com os dados criados (ou 400 em
caso de erro de validação).

### Consultar freelancer por CPF — `GET /api/telegram/freelancer/freelancer/{cpf}`
Retorna os dados do freelancer (HTTP 200) ou erro (400) se o CPF for inválido/não encontrado.

### Listar funções — `GET /api/telegram/freelancer/functions`
Retorna todas as funções disponíveis (HTTP 200).

### Criar função — `POST /api/telegram/freelancer/function`
Cadastra uma nova função (nome, descrição, preço).

### Registrar serviço — `POST /api/telegram/freelancer/service`
Registra um serviço prestado, vinculando freelancer + função e os dados do trabalho
(período, valor, horas, status).

## Campos principais

| Entidade | Campos |
|----------|--------|
| Freelancer | `name`, `cpf`, `rg`, `email`, `nacionality`, `civil_status`, `address`, `telephone` |
| Função | `name`, `description`, `price` |
| Serviço | `freelancer_id`, `function_freelancer_id`, `start_date`, `end_date`, `price`, `total_hours`, `status_id` |

## Regras de negócio

- O cadastro de freelancer valida os campos obrigatórios via `StoreFreelancerRequest`.
- A consulta é feita pelo **CPF** (identificador do freelancer).
- Um serviço sempre referencia um freelancer e uma função existentes.

## Referência técnica

- Controllers: [`FreelancerController`, `FunctionFreelancerController`, `FreelancerServiceController`](../controllers.md#freelancercontroller)
- Service: [`FreelancerService`](../services.md#freelancerservice)
- Models: [`Freelancer`, `FunctionFreelancer`, `FreelancerService`](../models.md#freelancers)
