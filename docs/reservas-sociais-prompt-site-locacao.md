# Prompt para o agent do site de locação — Reservas de Espaços Sociais

> Cole o conteúdo abaixo (da linha `---` em diante) em um Claude Code aberto no repositório do
> **site de locação de espaços**. Ele descreve a API de espaços sociais no Lara e as telas a
> construir.
>
> **Estado:** implementado no Lara na branch `espacos-sociais` (14/10/2026). O contrato abaixo
> é o do código (`App\Http\Controllers\Api\SocialBookingApiController`); se a API mudar, ajuste
> aqui antes de colar.
>
> **Depois deste**, cole também [reservas-sociais-ajustes-prompt.md](reservas-sociais-ajustes-prompt.md)
> (16/10/2026: mesas e cadeiras, horário do evento e aviso do prazo de contato).

---

Você vai construir, neste site, a reserva de **espaços sociais** do clube: churrasqueiras,
salões e áreas de festa. O back-end fica no Lara (a mesma API que este site já consome para
quadras, horários e reservas). Sua tarefa é a interface e a integração — **nenhuma regra de
negócio é decidida aqui**: disponibilidade, preço, limites, prazos e capacidade vêm sempre da API.

## Contexto de negócio

A reserva de quadra que o site já faz é imediata: escolhe horários, paga, está confirmada.
O espaço social funciona de outro jeito:

1. **Unidade = dia inteiro.** O sócio escolhe um espaço e uma data; não há horários.
2. **Pré-reserva.** O sócio envia a data, os dados do evento, quantos **convidados extras** quer
   contratar e, se quiser, bebidas do catálogo. A data fica **presa** para ele enquanto a
   secretaria analisa.
3. **Análise.** A secretaria faz o contrato com o sócio **fora do sistema** e, no painel do Lara,
   aprova (podendo dar desconto) ou recusa.
4. **Pagamento liberado.** Aprovada, a reserva mostra o valor final e um **prazo para pagar**. O
   pagamento é online, pela mesma Rede que o site já usa para quadras.
5. **Confirmada** depois do pagamento. Se o prazo vencer sem pagamento, a reserva **expira** e a
   data volta a ficar livre.
6. **Convidados não sócios.** Cada espaço inclui no preço um número de convidados não sócios
   (`max_guests`) e vende até `extra_guests` a mais, a `extra_guest_price` cada. A lista
   nominal (nome + CPF) aceita até `max_guests` + extras contratados. Na data do evento, a
   portaria libera esses CPFs. Sócios não entram na lista.
7. **Capacidade.** `capacity` é o total de pessoas (sócios + não sócios); o "número de pessoas"
   da pré-reserva não pode passar dela. As regras do clube sobre crianças etc. vêm em `rules`
   do grupo — mostre o texto como está.

Exemplo real: Churrasqueira da Sauna — R$ 800,00; 20 não sócios incluídos; até 5 extras a
R$ 15,00; capacidade 80; "10 jogos de mesa com 4 cadeiras, fogão e freezer".

Cancelamento de reserva **paga** não é feito pelo site: o sócio fala com a secretaria, que decide
o estorno. O site só permite desistir **antes de pagar**.

## Autenticação

Nada novo:

- Todas as rotas abaixo exigem o `Authorization: Bearer <API_TOKEN>` de sempre **e** o header
  `Session` com o JWT do sócio — o mesmo já usado em `/api/schedule/...`.
- O sócio é sempre o dono do JWT. **Nunca** mande `member_id`, CPF ou matrícula do sócio no
  corpo: a API ignora e decide pelo token. Reserva de outro sócio responde 404.

## Estágios da reserva (`stage`)

| `stage` | O que a tela mostra | Ações do sócio |
|---|---|---|
| `requested` | "Em análise pela secretaria" | **Editar a pré-reserva** (enquanto `can_edit`); editar convidados; desistir |
| `awaiting_payment` | Valor final, desconto (se houver), prazo `payment_due_at` | Pagar; editar convidados; desistir |
| `confirmed` | "Confirmada" | Editar convidados (até `guests_editable_until`) |
| `refused` | "Recusada" + `refusal_reason` | — |
| `cancelled` | "Cancelada" + `cancel_reason` | — |
| `expired` | "Expirada — prazo de pagamento vencido" | — |

`stage_label` traz o rótulo em português. Use os booleanos da resposta (`can_edit`, `can_pay`,
`can_cancel`, `can_edit_guests`) para mostrar ou esconder botões; não recalcule prazo no front.

`can_edit` fica `true` só enquanto a pré-reserva está em análise **e** a secretaria ainda não
marcou o contrato como ok: o contrato fecha o combinado, e depois dele não há mais trocas.

## Endpoints

Base: `<host do Lara>/api/social`

| Método | Rota | Para quê |
|---|---|---|
| GET | `/groups` | Grupos sociais (Churrasqueiras, Salões…), com `rules` |
| GET | `/groups/{id}/places` | Espaços ativos do grupo |
| GET | `/availability?place_id=ID&month=YYYY-MM` | Calendário do mês do espaço |
| GET | `/beverages` | Catálogo de bebidas ativas |
| GET | `/limits?place_group_id=ID` | Quanto o sócio ainda pode reservar no grupo |
| POST | `/bookings` | Criar pré-reserva |
| GET | `/bookings` | Reservas sociais do sócio (futuras primeiro, depois as passadas) |
| GET | `/bookings/{id}` | Detalhe |
| PUT | `/bookings/{id}` | Editar a pré-reserva (tudo menos data e espaço), enquanto `can_edit` |
| PUT | `/bookings/{id}/guests` | Substituir a lista de convidados |
| DELETE | `/bookings/{id}` | Desistir (só `requested`/`awaiting_payment`) |

As rotas de quadra (`/api/places/group`, `/api/places`, `/api/schedule/time-options`) **não**
listam espaço social — não as use aqui.

### Grupos e espaços

```jsonc
// GET /api/social/groups
[{ "id": 9, "name": "Churrasqueiras e Salões", "image_vertical": "https://…", "image_horizontal": "https://…",
   "rules": "Crianças com idade inferior a 4 anos não são contabilizadas como convidados…",
   "minimum_antecedence": 2, "maximum_antecedence": 180, "places_count": 6 }]

// GET /api/social/groups/9/places
{ "group": { "id": 9, "name": "…", "rules": "…" },
  "places": [{ "id": 41, "name": "Churrasqueira da Sauna", "description": null,
               "amenities": "10 jogos de mesa com 4 cadeiras, fogão e freezer.", "image": "https://…",
               "price": 800.0, "capacity": 80, "max_guests": 20, "extra_guests": 5, "extra_guest_price": 15.0 }] }
```

`description` traz observações/restrições do espaço (ex.: "Proibida a utilização de fogão").

### Calendário

```jsonc
{
  "place_id": 41, "month": "2026-11", "price": 800.0,
  "days": [
    { "date": "2026-11-01", "status": "available" },
    { "date": "2026-11-02", "status": "reserved" },      // ocupada (este espaço ou um que conflita com ele)
    { "date": "2026-11-03", "status": "blocked" },       // regra do clube (manutenção, evento interno)
    { "date": "2026-11-04", "status": "out_of_window" }  // passada, fora da antecedência ou dia fechado
  ]
}
```

Só `available` é clicável. A resposta nunca diz de quem é uma data ocupada.

### Limites

```jsonc
// GET /api/social/limits?place_group_id=9 — cada limite é null quando o grupo não define
{ "active": { "limit": 1, "used": 0, "remaining": 1 }, "window": { "limit": 1, "days": 30 } }
```

`window` = "no máximo `limit` reservas a cada `days` dias". Mostre como aviso; quem decide é o POST.

### Criar pré-reserva

```jsonc
// POST /api/social/bookings
{
  "place_id": 41,
  "date": "2026-11-21",
  "event_type": "Aniversário",
  "expected_guests": 60,            // total de pessoas (sócios + não sócios), opcional, ≤ capacity
  "extra_guests": 2,                // convidados extras contratados, 0..extra_guests do espaço
  "notes": "Chegada da decoração às 9h",
  "items": [ { "beverage_id": 3, "quantity": 2 } ],              // opcional
  "guests": [ { "name": "Ana Souza", "cpf": "12345678909" } ]    // opcional, pode vir depois
}
```

Resposta `201` com a reserva. Recusas de regra vêm com `422` (ou `404`/`409`) e um `reason`
estável — decida a mensagem pelo código, não pelo texto:

| `reason` | Quando |
|---|---|
| `place_not_social` | Espaço inexistente, inativo ou que não é social (404) |
| `date_unavailable` | Data ocupada |
| `date_blocked` | Regra do clube bloqueia o dia |
| `out_of_window` | Data passada, fora da antecedência ou dia em que o grupo não abre |
| `limit_reached` | Limite do sócio no grupo (`context.limit` = `active` ou `window`) |
| `over_capacity` | Extras acima do permitido, pessoas acima da capacidade ou lista maior que o limite |
| `beverage_unavailable` | Bebida fora do catálogo ativo |
| `invalid_guest` | Convidado sem nome, CPF inválido ou repetido (`context.index` aponta a linha) |
| `guests_locked` | Lista pelo app fechada (depois da véspera) (409) |
| `invalid_stage` | Ação que não cabe no estágio (ex.: desistir de reserva paga, editar reserva já aprovada) (409) |
| `contract_closed` | Edição da pré-reserva depois do contrato ok (409) |
| `payment_mismatch` | Pagamento fora do estágio/prazo ou valor diferente do total |
| `not_owner` | Reserva não encontrada para este sócio (404) |

Erro de formato (campo faltando) vem no padrão de validação do Laravel (`422` com `errors`).

### Reserva (formato de resposta)

```jsonc
{
  "id": 87,
  "schedule_id": 15230,           // é este id que vai no pagamento
  "stage": "awaiting_payment",
  "stage_label": "Aguardando pagamento",
  "date": "2026-11-21",
  "place": { "id": 41, "name": "Churrasqueira da Sauna", "capacity": 80, "max_guests": 20 },
  "place_group": { "id": 9, "name": "Churrasqueiras e Salões" },
  "event_type": "Aniversário",
  "expected_guests": 60,
  "notes": "…",
  "base_price": 800.0,
  "extra_guests": 2,
  "extra_guest_price": 15.0,
  "extras_total": 30.0,
  "items": [ { "beverage_id": 3, "name": "Água (fardo)", "unit": "fardo 12 un.", "unit_price": 30.0, "quantity": 2, "subtotal": 60.0 } ],
  "items_total": 60.0,
  "discount": 50.0,
  "total": 840.0,                 // valor a cobrar — use este, não some no front
  "payment_due_at": "2026-10-21T23:59:59-03:00",
  "guest_limit": 22,              // max_guests + extras contratados
  "guests": [ { "id": 1, "name": "Ana Souza", "cpf": "123.***.**0-09" } ],
  "guests_editable_until": "2026-11-20T23:59:59-03:00",
  "can_edit": false,
  "can_pay": true,
  "can_cancel": true,
  "can_edit_guests": true,
  "refusal_reason": null,
  "cancel_reason": null
}
```

Os preços (espaço, extra, bebidas) ficam **congelados** na pré-reserva: mostre os valores da
reserva, não os do catálogo atual.

### Editar a pré-reserva

```jsonc
// PUT /api/social/bookings/{id} — só enquanto can_edit; todo campo é opcional
{
  "event_type": "Formatura",
  "expected_guests": 70,
  "extra_guests": 3,
  "notes": "Bolo às 16h",
  "items": [ { "beverage_id": 5, "quantity": 10 } ],             // lista inteira: o que não vier sai
  "guests": [ { "name": "Beatriz Lima", "cpf": "11144477735" } ]  // lista inteira, opcional
}
```

- **Data e espaço não mudam** (são ignorados se vierem). Para outro dia ou espaço, o sócio
  desiste e faz outra pré-reserva.
- Campo ausente fica como está. `items` e `guests`, quando vêm, **substituem** a lista: para tirar
  todas as bebidas, mande `"items": []`.
- Bebida que já estava na reserva mantém o preço contratado; bebida nova entra pelo preço atual
  do catálogo (e precisa estar ativa).
- Reduzir `extra_guests` abaixo do tamanho da lista dá `over_capacity` — mande a lista menor na
  mesma chamada.
- Resposta `200` com a reserva recalculada (o `total` muda). Mesmos `reason` da criação, mais
  `contract_closed` e `invalid_stage`.

Na tela, ofereça "Editar pré-reserva" reaproveitando o formulário da pré-reserva, com data e
espaço só para leitura.

### Pagamento

Use o fluxo de pagamento que o site já tem para quadras, com três diferenças:

- `POST /api/schedule/payment` com `schedule_ids: [<schedule_id da reserva>]` — **só** ela, sem
  outros agendamentos no mesmo pagamento.
- `paid_amount` **tem de ser igual** ao `total` da reserva, e só é aceito com
  `stage = awaiting_payment` e antes de `payment_due_at`. Fora disso a API responde com
  `reason: payment_mismatch` — mostre o erro e recarregue a reserva.
- Não existe o hold de 10 minutos das quadras aqui: o prazo é o `payment_due_at`.

Aprovado o pagamento, a reserva passa a `confirmed` e o sócio recebe o e-mail de confirmação.

### Convidados

`PUT /bookings/{id}/guests` recebe a **lista inteira** (`{ "guests": [{ name, cpf }] }`, pode ser
vazia) e substitui a anterior. Nome e CPF são obrigatórios (é o que a portaria confere); a
quantidade não pode passar de `guest_limit`. CPFs voltam mascarados. Depois de
`guests_editable_until` a API responde `guests_locked`: oriente o sócio a falar com a secretaria.

## Telas a construir

1. **Espaços sociais** — vitrine dos grupos (`/groups`) e dos espaços (`/groups/{id}/places`),
   com foto, preço da diária, capacidade, convidados incluídos e extras, itens fornecidos,
   observações e as regras do grupo.
2. **Calendário do espaço** — mês a mês, com legenda de status; clicar numa data disponível abre
   a pré-reserva. Mostre os limites (`/limits`) antes de o sócio preencher tudo.
3. **Pré-reserva** — dados do evento (tipo, pessoas, observações), convidados extras (0 até o
   máximo do espaço, com o preço), bebidas (quantidade e subtotal), convidados opcionais e um
   resumo **estimado** do valor, com o aviso: *"A reserva só é confirmada após análise da
   secretaria e pagamento."* O valor oficial é o `total` da resposta.
4. **Minhas reservas sociais** — lista com o estágio de cada uma; no detalhe, o valor
   discriminado, **editar a pré-reserva** (quando `can_edit`), o botão de pagar (quando
   `can_pay`), a gestão de convidados (até `guest_limit`) e a desistência (quando `can_cancel`).

Mantenha o visual e os componentes que o site já usa para as reservas de quadra.

## O que não fazer

- Não calcule preço, desconto, prazo, limite, capacidade ou disponibilidade no front como fonte
  de verdade — estimativa na tela, sim; decisão, nunca.
- Não mande dados do sócio no corpo; o JWT manda.
- Não ofereça cancelamento de reserva paga — oriente o sócio a falar com a secretaria.
- Não mostre de quem é uma data ocupada.
- Não reaproveite a grade de horários (`/api/schedule/time-options`) nem `POST /api/schedule`
  para espaço social (a API recusa).
