# Agendamento de Espaços

## O que é

Permite que um **sócio reserve um horário** em um espaço (quadra, salão, etc.). O sistema
calcula os horários disponíveis aplicando regras, antecedência e limites, detecta conflitos
e, quando há cobrança, gera pagamento e envia e-mails de confirmação/pendência.

## Para quem

- **Sócios** (app móvel), via API autenticada por token JWT (`login_token`).
- **Administradores** (painel web), que visualizam e gerenciam os agendamentos.

## Pré-requisitos

- Espaços, grupos e regras já configurados (ver [Espaços e Grupos](espacos-e-grupos.md)).
- Sócio autenticado (token JWT) para agendar pelo app.

## Fluxo do sócio (app)

### 1. Escolher grupo, espaço e data
O app lista grupos/espaços (`/api/places/group`, `/api/places`) e, para a data escolhida,
busca os horários disponíveis.

### 2. Consultar horários — `POST /api/schedule/time-options`
O serviço `ScheduleRulesService::getTimeOptions()` monta a grade assim:
1. **Slots base:** de `start_time` a `end_time` do grupo, com duração `duration` (ex.: fatias de 1h).
2. **Filtro de dia da semana:** remove horários se o grupo não funciona naquele dia.
3. **Janela de vendas:** se a reserva é para hoje, respeita `start_time_sales`/`end_time_sales`.
4. **Antecedência:** só permite datas entre hoje + `minimum_antecedence` e hoje + `maximum_antecedence`.
5. **Inclusões/Exclusões:** regras `type=include` adicionam horários; `type=exclude` bloqueiam.
6. **Colisão:** marca horários já reservados (ver `checkColide`).
7. **Passados:** remove horários que já ocorreram quando a data é hoje.

Cada horário retorna se está livre, bloqueado por regra, em conflito ou no passado.

### 3. Identificar o sócio
O app busca o sócio por nome/matrícula (`POST /api/member/by-title`) e associa a reserva ao
`member_id`/`cpf`.

### 4. Confirmar — `POST /api/schedule`
`SchedulesService::createSchedule()` executa, para cada horário selecionado:
- **Resolve CPF → sócio** (se necessário);
- **Valida colisão** (`checkColide`) — com outros sócios e com o próprio sócio;
- **Valida o horário** recalculando `getTimeOptions` (garante que o slot ainda é válido);
- **Verifica o limite diário** do sócio no grupo (`countMemberSchedulesInPlaceGroupOnDate`);
- **Cria o agendamento** com status confirmado (1) ou pendente (3);
- **Envia e-mail** de confirmação ou de pendência de pagamento.

## Detecção de colisão (`checkColide`)

Compara apenas as **horas** (ignora a data) dos agendamentos ativos/pendentes do espaço. Dois
horários colidem quando se sobrepõem: `!(fimSlot <= inícioExistente || inícioSlot >= fimExistente)`.
Agendamentos com status **cancelado (0)** ou **expirado (4)** não ocupam o horário. Se o
próprio sócio já tem reserva no horário, retorna "Você já possui um agendamento nesse horário".

## Limite diário

`PlaceGroup.daily_limit` define quantas reservas um sócio pode ter **no grupo, por dia**. O
sistema conta as reservas existentes e recusa quando o limite é atingido, informando o total e
o restante.

## Status do agendamento

| `status_id` | Significado | Observação |
|-------------|-------------|------------|
| 0 | Cancelado | Não ocupa horário. |
| 1 | Confirmado/ativo | Reserva válida. |
| 3 | Pendente | Aguardando pagamento. |
| 4 | Expirado | **Oculto** por um *global scope* do model `Schedule`. |

## Pagamento e e-mails

- Reserva paga → status **1** → e-mail **`schedule.confirm`**.
- Reserva aguardando pagamento → status **3** → e-mail **`schedule.pending`** (com link).
- Cancelamento (admin) → e-mail **`schedule.cancel`**, com possível estorno via
  [RedeItau](../integracoes.md#113-redeitau-gateway-de-pagamento).

## Gerenciamento (admin)

- `GET /schedule` — agenda do dia (status em cores).
- `GET /schedule/{id}` — detalhes do agendamento e demais reservas do sócio no dia.
- `PUT /schedule/update` / `PUT /api/schedule/update-status` — altera status (ex.: cancelar,
  com estorno opcional).
- `DELETE /api/schedule/delete-pending` — remove uma reserva pendente.
- `GET /api/schedule/generate-pdf` — PDF da agenda diária.

## Integrações

- **E-mail:** confirmação/pendência/cancelamento.
- **RedeItau:** cobrança e estorno.
- **Home Assistant:** iluminação automática (ver [guia dedicado](automacao-home-assistant.md)).

## Referência técnica

- Controllers: [`ScheduleController`, `ScheduleRulesController`](../controllers.md#schedulecontroller)
- Services: [`SchedulesService`, `ScheduleRulesService`, `PlaceService`](../services.md#schedulesservice)
- Models: [`Schedule`, `ScheduleRules`](../models.md#núcleo-de-agendamento)
- Rotas: [API de agendamento](../rotas.md#82-rotas-da-api-routesapiphp)
