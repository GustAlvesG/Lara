# Telegram

## O que é

Gerencia **contatos do Telegram** (cadastro, busca e atualização), usados para notificações e
para integrações como o fluxo de freelancers.

## Para quem

Integrações externas autenticadas por token de API (`api_token`).

## Pré-requisitos

- Token de API válido (header `Authorization: Bearer <API_TOKEN>`).

## Fluxo passo a passo

### Buscar contatos — `POST /api/telegram/get-contacts`
Busca por `name`, `chat_id` ou `phone` (parcial). Retorna a lista de contatos.

### Criar contato — `POST /api/telegram/contacts`
Campos: `name` e `chat_id` (obrigatórios, `chat_id` único) e `phone` (opcional). Retorna
**HTTP 201**.

### Atualizar contato — `PUT /api/telegram/contacts/{id}`
Atualiza `name`, `chat_id` (mantendo unicidade) e `phone`.

## Campos da tabela `contact_telegram`

`name`, `chat_id` (único), `phone`.

## Regras de negócio

- `chat_id` é o identificador único do contato no Telegram.
- A busca aceita filtros parciais por nome/telefone.

## Referência técnica

- Controller: [`TelegramContactController`](../controllers.md#telegramcontactcontroller)
- Service: [`TelegramService`](../services.md#telegramservice)
- Model: [`TelegramContact`](../models.md#status-acesso-e-estacionamento)
