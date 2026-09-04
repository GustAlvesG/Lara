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
7. **Encerrados:** quando a data é hoje, remove os horários que já não dá tempo de pagar
   (ver [Horário em andamento](#horário-em-andamento-e-valor-proporcional)). O horário que
   apenas *começou* continua na grade.
8. **Preço:** cada horário sai com `full_price` (preço cheio do espaço) e `price` (o que
   custa agora), mais `price_factor`, `remaining_minutes` e `in_progress`.

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
- **Calcula o preço** do horário no instante da gravação (proporcional, se já começou);
- **Cria o agendamento** com status confirmado (1) ou pendente (3);
- **Envia e-mail** de confirmação ou de pendência de pagamento.

## Horário em andamento e valor proporcional

Um horário permanece à venda **depois de ter começado**, cobrado proporcionalmente ao tempo
que ainda resta. No horário das 20:00 às 21:00 de uma quadra de R$ 100:

| Momento da reserva | Tempo restante | Valor |
|--------------------|----------------|-------|
| até 20:00 | 60 min | R$ 100,00 |
| 20:15 | 45 min | R$ 75,00 |
| 20:30 | 30 min | R$ 50,00 |
| 20:37 | 23 min | R$ 38,33 |
| 20:49 | 11 min | R$ 18,33 |
| 20:50 em diante | — | fora da grade |

A proporção é **minuto a minuto** (`minutos restantes / duração`), sem piso, arredondada a
duas casas; os segundos não entram na conta — às 20:30:47 ainda são 50%.

A venda fecha em **`fim - ExpirePendingSchedules::HOLD_MINUTES`** (hoje 10 minutos, logo
20:49 é o último minuto vendável). O motivo é o hold: a reserva nasce pendente e só libera o
horário quando expira — vender às 20:55 criaria um pendente vivo até 21:05, depois do fim do
próprio horário. Mudar o hold move a janela de venda junto, sem tocar nesta regra.

Quem calcula é o [`SchedulePricingService`](../services.md#schedulepricingservice), e o preço
é **sempre recalculado no servidor no instante em que a reserva é gravada** —
`SchedulesService::createSchedule()` aplica a proporção sobre o preço cadastrado no `Place`,
tanto no app do sócio quanto no painel. O valor que a tela mostrou minutos antes não vale como
preço; o `Schedule.price` gravado é o que deve ser cobrado (o app deve somar os `price` que
vêm na resposta da criação, não recalcular `nº de horários × preço da quadra`).

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

O e-mail é decidido pelo **status gravado no agendamento**, e não pelo que veio na
requisição — `SchedulesService::notifyScheduleStatus()` é o ponto único de saída:

- Reserva aguardando pagamento → status **3** → e-mail **`schedule.pending`**, informando o
  prazo real do hold (`ExpirePendingSchedules::HOLD_MINUTES`) e o horário-limite.
- Reserva paga → status **1** → e-mail **`schedule.confirm`**, com os dados do pagamento
  (forma, valor pago, data e transação).
- Cancelamento pelo painel → status **0** → e-mail **`schedule.cancel`**, com o motivo digitado
  pelo administrador, a data/hora do cancelamento e o pagamento original.

**Fluxo do app externo:** a reserva nasce em `POST /api/schedule` já como pendente (3) — só o
e-mail de pendência sai nesse momento. A confirmação acontece em
`POST /api/schedule-payment`, e é lá que o e-mail `schedule.confirm` é disparado (fora da
transação; falha de envio não desfaz o pagamento). Antes, o e-mail de confirmação era decidido
apenas na criação e, por isso, nunca chegava ao sócio nesse fluxo.

**Cancelamento pela interface web** (`PUT /schedule/update`, tela de detalhe do agendamento): o
e-mail sai depois da tentativa de estorno, para poder informar o que de fato foi devolvido. O
valor anunciado é a diferença do `refunded_amount` do pagamento antes e depois da chamada à
[RedeItau](../integracoes.md#113-redeitau-gateway-de-pagamento) — se o estorno falhar, o sócio
ainda recebe o aviso de cancelamento, só que sem promessa de devolução (e o erro continua
subindo para a tela do administrador). Cancelamento em lote de sócios diferentes gera um e-mail
por sócio, cada um só com as próprias reservas.

Os valores do e-mail vêm dos agendamentos gravados (soma dos `price` de cada horário), não do
preço enviado pelo cliente — que nesse fluxo sequer é aceito. O destinatário é sempre o e-mail
do sócio; sem endereço válido, o envio é registrado em log e o fluxo segue.

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
