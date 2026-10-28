# Prompt de implementação — ingressos no site de locação (Next.js)

> Documento de entrega para um agente Claude Code trabalhando **no repositório do
> site de locação (Next.js)**, não neste. Copie a partir de "Início do prompt" até o fim.
>
> **Versão 4 (27/10/2026)**, para implementar do zero: nada de ingressos existe ainda no
> site. Cobre a vitrine, a compra do sócio **para as pessoas do título dele**, a compra do
> não sócio sem conta, o **cupom de desconto**, o **evento de vários dias (diária e
> passaporte)**, o pagamento eRede, "meus ingressos" com QR e PDF. (Versões do mesmo dia: a 1
> não tinha cupom — mudanças marcadas **[v2]**; a 2 deixava o sócio comprar só para ele —
> mudanças marcadas **[v3]**; a 3 não tinha evento de vários dias — mudanças marcadas
> **[v4]**; a 4 não tinha lote sem limite — mudança marcada **[v5]**; a 5 não tinha "meus
> ingressos" do não sócio — mudança marcada **[v6]**; a 6 não tinha lugar marcado (mesas,
> bistrôs, camarotes) — ver a atualização v7; a 7 não tinha mesa cativa — ver a atualização v8. Se o agente do site já começou por uma
> delas, siga as marcas.) **O site já implementou a v3**: para ele, entregue só os prompts de
> atualização no fim deste arquivo — o [v4](#prompt-de-atualização--v4-evento-de-vários-dias)
> e o [v5](#prompt-de-atualização--v5-lote-sem-limite) (se ainda não foram) e o
> [v6](#prompt-de-atualização--v6-meus-ingressos-do-não-sócio) e o
> [v7](#prompt-de-atualização--v7-lugar-marcado-mesas-bistrôs-camarotes) e o
> [v8](#prompt-de-atualização--v8-mesa-cativa) (28/10/2026). O contrato é o que o Lara expõe hoje
> (`routes/api.php`, prefixo `tickets`); se a API mudar, este arquivo muda junto. As regras
> por trás dele estão em [ingressos.md](ingressos.md). O check-in **não** é do site (é feito
> no Lara e no Monitor de Acesso da portaria).

---

## Início do prompt

Você vai implementar **do zero** a área de **ingressos de eventos** deste site — nada dela
existe ainda. O backend é o Lara (a mesma API das reservas de quadra, dos espaços sociais e
dos torneios): o site não guarda nada de ingresso, só mostra e chama a API. Antes de começar,
leia como o site já faz **login do sócio**, **chamadas ao Lara** (o `api_token` no
`Authorization: Bearer`) e **cobrança na eRede** das reservas e dos torneios: os ingressos
usam os mesmos mecanismos. A novidade é a **compra sem login**, do não sócio.

O site precisa de:

1. **Vitrine** de eventos e página do evento (sem login).
2. **Compra do sócio** (com o login de sócio): **[v3]** um ingresso para cada pessoa do
   título dele que ele escolher (ele, o titular, os dependentes).
3. **Compra do não sócio** (sem login e sem cadastro): um ou mais ingressos, um por pessoa.
4. **Pagamento** na eRede e aviso ao Lara.
5. **Meus ingressos**: QR Code, código e PDF — para o sócio (logado) e para o não sócio
   (pelo link do e-mail).
6. **"Perdi o link"** para o não sócio. **[v6]** E **"Meus ingressos" do não sócio**: CPF +
   e-mail + código recebido no e-mail, para ver todos os pedidos dele.
7. **[v4]** **Evento de vários dias**: escolher a **diária** de cada dia ou o **passaporte**.

### Como funciona

- O evento tem **tipos de ingresso** (Pista, Mesa, Infantil…). Cada tipo é vendido em
  **lotes**; a API já devolve o **lote à venda agora** de cada tipo (`lot`), com dois preços:
  `price_member` (sócio) e `price_non_member` (não sócio). `members_only: true` (ou
  `price_non_member: null`) = esse lote é só para sócios.
- O lote vira sozinho (esgotou ou passou a data): o site **não calcula** lote nem preço,
  sempre mostra o que a API devolve e **recarrega** o evento antes de abrir o checkout.
  Tipo sem lote à venda: `available: false`; se houver `next_lot`, mostre "Próximo lote a
  partir de {starts_at}" com os preços dele.
- O **ingresso é nominal**: nome e CPF de quem vai entrar. **Um ingresso por CPF por
  evento** — **[v4]** no evento de vários dias, **um por CPF por dia** (veja abaixo).
- **[v4] Evento de vários dias.** `multi_day: true` e a lista `days` (cada dia com `id`,
  `label` — "Sábado (13/02)" ou "Sáb 13/02" —, horário e `capacity_left`). Cada tipo traz
  `validity`: `"day"` = **diária** do dia `day_id`; `"passport"` = **passaporte**, vale todos os
  dias (uma entrada por dia); `"event"` = evento de um dia só (o normal, sem dias). Cada diária
  e o passaporte têm os próprios lotes e preços. **Nem todo evento de vários dias tem
  passaporte**: se nenhum tipo vier com `"passport"`, não mostre essa opção. A mesma pessoa
  pode comprar diárias de dias diferentes (até no mesmo pedido), mas **não** diária junto com
  passaporte, nem duas no mesmo dia (`already_has_ticket` / `invalid_holder`). A lotação é por
  dia: um dia lotado recusa a diária dele e o passaporte (`capacity_full` com
  `context.day_id`).
- **[v3] Sócio** (logado — titular **ou** dependente): compra para **as pessoas do título
  dele** (ele mesmo, o titular e os dependentes), pelo preço de sócio. O site busca a lista
  em `GET /api/tickets/my-title` e o sócio marca para quem quer comprar, escolhendo o tipo de
  cada um. Ninguém de fora do título entra no pedido de sócio (`holder_not_in_title`): essa
  pessoa compra como não sócio ou, se for sócia de outro título, pelo login dela. O nome no
  ingresso é o do cadastro do clube (o sócio não digita). O limite `max_per_order` **não** vale
  para o sócio.
- **Não sócio** (sem login): informa os próprios dados (CPF, nome, e-mail, telefone) e, para
  cada ingresso, o nome e o CPF de quem vai usar, até `max_per_order` ingressos. Preço de
  não sócio. Se um dos CPFs for de **sócio**, o Lara recusa (`holder_is_member`): mostre que
  o sócio compra o próprio ingresso entrando com o login de sócio.
- O pedido **guarda o lugar por alguns minutos** (`hold_until`, hoje 10). Mostre um contador
  no checkout. Pagamento que chega depois do prazo ainda pode ser aceito se houver lugar; se
  não houver, o Lara recusa com `hold_expired` e **o site estorna a cobrança na eRede**.
- O **preço é sempre o do Lara**: cobre na eRede exatamente o `total` do pedido.
- O Lara manda os e-mails (ingressos confirmados, cancelamento, link do pedido). O site
  **não** manda e-mail.
- Desistir pelo site só **antes de pagar**. Pago: a pessoa fala com a organização.
- **[v2] Cupom de desconto.** O evento pode ter cupons. O cupom abate de **cada ingresso a
  que se aplica** uma porcentagem do preço do lote ou um valor fixo (nunca abaixo de zero), e
  pode valer só para alguns tipos, só para sócios ou só para não sócios, com prazo e limite de
  usos. O site oferece um campo "Cupom" no checkout, confere antes (`coupon-check`) e manda o
  código ao criar o pedido (`coupon_code`). **Quem calcula o desconto é o Lara**: o total e o
  desconto de cada ingresso vêm no pedido. Cupom de 100% deixa o pedido já pago (sem eRede).
- Pedidos emitidos pela secretaria (`origin: "secretaria"`, pago por fora ou **cortesia**)
  aparecem em "meus ingressos" do sócio como qualquer outro; `courtesy: true` → mostre
  "Cortesia" no lugar do valor.

### Ordem sugerida

1. Vitrine e página do evento.
2. Compra do sócio + pagamento + "meus ingressos" do sócio.
3. Compra do não sócio + pagamento + página do pedido pelo link.
4. Cupom no checkout.
5. PDF, "perdi o link", estados de erro.
6. **[v4]** Evento de vários dias (dias, diária, passaporte).

### Autenticação

| Quem | Como |
|---|---|
| Todos | `Authorization: Bearer {API_TOKEN}` (o mesmo das reservas). |
| Sócio | + header `Session: {JWT do login}`, como nas reservas e torneios. |
| Não sócio | + header `Ticket-Order-Token: {access_token}` nas rotas `/guest-orders/{id}`. |

O `access_token` do não sócio é devolvido **uma única vez**, na criação do pedido. Guarde-o
(cookie httpOnly ou sessão do servidor do Next.js, associado ao id do pedido) para o
checkout e a página de confirmação. Depois, quem abre o pedido é o **link do e-mail**:
`{site}/ingressos/pedido/{id}?token={access_token}` — crie essa página: ela lê `id` e
`token` da URL e chama `GET /api/tickets/guest-orders/{id}` com o header. Não exponha o token
em logs nem em URLs de terceiros.

Sem o header `Session` nas rotas de sócio, o Lara responde **400**; com JWT inválido ou
vencido, **401** (mande para o login).

### Endpoints

#### Vitrine (só `api_token`)

`GET /api/tickets/events` — eventos publicados e encerrados.
`GET /api/tickets/events/{id}` — o mesmo, com `rules` (texto livre, quebras de linha).
Rascunho ou cancelado: 404 `event_not_found`.

```json
{
  "id": 3,
  "title": "Baile de Carnaval",
  "description": "…",
  "image_url": "https://…",
  "venue": "Salão Nobre",
  "status": "published",
  "starts_at": "2027-02-13T22:00:00-03:00",
  "ends_at": "2027-02-14T04:00:00-03:00",
  "sales_starts_at": "…", "sales_ends_at": "…",
  "sales_open": true,
  "max_per_order": 4,
  "capacity_left": 312,
  "multi_day": false,
  "days": [],
  "types": [
    {
      "id": 7, "name": "Pista", "description": null,
      "validity": "event", "day_id": null,
      "available": true,
      "lot": { "id": 21, "name": "1º lote", "price_member": 50, "price_non_member": 80,
               "members_only": false, "starts_at": null, "ends_at": "2027-01-31T23:59:00-03:00",
               "remaining": 43 },
      "next_lot": null
    }
  ],
  "rules": "…"
}
```

- `sales_open: false` → mostre o evento sem botão de compra ("Vendas a partir de…" ou
  "Vendas encerradas"). `status: "finished"` → evento encerrado.
- `capacity_left: null` = sem lotação; número = lugares que ainda cabem no evento.
- `remaining` baixo pode virar um selo "últimos ingressos". **[v5]** `remaining: null` = lote
  **sem limite** de ingressos: não mostre contagem nem selo.
- **[v4]** Evento de vários dias:

```json
{
  "multi_day": true,
  "capacity_left": 40,
  "days": [
    { "id": 11, "name": "Sábado", "label": "Sábado (13/02)", "starts_at": "2027-02-13T18:00:00-03:00", "ends_at": "2027-02-14T02:00:00-03:00", "capacity_left": 40 },
    { "id": 12, "name": null, "label": "Dom 14/02", "starts_at": "2027-02-14T18:00:00-03:00", "ends_at": "2027-02-14T23:00:00-03:00", "capacity_left": 95 }
  ],
  "types": [
    { "id": 20, "name": "Pista", "validity": "day", "day_id": 11, "lot": { "…": "…" } },
    { "id": 21, "name": "Pista", "validity": "day", "day_id": 12, "lot": { "…": "…" } },
    { "id": 22, "name": "Passaporte", "validity": "passport", "day_id": null, "lot": { "…": "…" } }
  ]
}
```

  Agrupe os tipos por dia (`day_id` → `days`) e mostre o passaporte à parte ("vale todos os
  dias"). `capacity_left` do evento = o do dia mais cheio (o que cabe de passaporte); o de
  cada dia limita a diária daquele dia. O nome do tipo pode se repetir entre dias: mostre
  sempre com o `label` do dia.

#### [v2] Conferir cupom (só `api_token`)

`POST /api/tickets/events/{id}/coupon-check` — `{ "code": "festa10", "buyer_kind": "member" | "guest" }`
(limite 20 por minuto). Só informa, para o checkout mostrar o desconto antes; o desconto
que vale é o do pedido, que confere tudo de novo.

```json
{ "code": "FESTA10", "description": "Desconto de lançamento", "kind": "percent", "value": 10,
  "label": "10%", "audience": "all", "type_ids": null, "ends_at": null }
```

- `kind`: `percent` (porcentagem do preço do lote) ou `fixed` (valor em reais por ingresso).
- `audience`: `all`, `member` (só sócio) ou `non_member` (só não sócio).
- `type_ids`: tipos em que vale (`null` = todos). Mostre a prévia só nos tipos da lista.
- Recusas: `coupon_invalid` (inexistente, desativado ou fora do prazo), `coupon_not_applicable`
  (não vale para esse público em nenhum tipo), `coupon_exhausted` (409).

#### Compra do sócio (com `Session`)

**[v3]** `GET /api/tickets/my-title?event_id={id}` — as pessoas do título do sócio logado
(limite 30 por minuto):

```json
{
  "title": "1001",
  "people": [
    { "ref": "eyJpdiI6…", "name": "Ana Souza", "cpf": "390.***.**7-05", "titular": true,  "is_me": true,  "has_ticket": false },
    { "ref": "eyJpdiI6…", "name": "Bia Souza", "cpf": "111.***.**7-35", "titular": false, "is_me": false, "has_ticket": true }
  ]
}
```

- `ref` identifica a pessoa no pedido (CPF e matrícula cifrados; o site nunca vê o CPF). Ela
  é longa e opaca: só guarde e devolva.
- `has_ticket: true` (com `event_id`) = a pessoa já tem ingresso neste evento: mostre
  desabilitada, com "já tem ingresso".
- **[v4]** `days_taken` (com `event_id`): os ids dos dias em que a pessoa já tem ingresso (o
  passaporte cobre todos). No evento de vários dias, use isso em vez de `has_ticket`: a
  diária do dia X fica desabilitada para quem tem X em `days_taken`; o passaporte, para quem
  tem qualquer dia. No evento sem dias, `days_taken` vem vazio e vale `has_ticket`.
- `is_me` vem primeiro. Sem MultiClubes, a lista pode vir só com o próprio sócio.

`POST /api/tickets/events/{id}/orders` — **[v3]** corpo:

```json
{
  "tickets": [
    { "type_id": 7 },
    { "type_id": 7, "holder_ref": "eyJpdiI6…" },
    { "type_id": 9, "holder_ref": "eyJpdiI6…" }
  ],
  "coupon_code": "FESTA10"
}
```

Um item por pessoa (**[v4]** ou por pessoa **e dia**: a mesma `holder_ref` pode vir em
diárias de dias diferentes); sem `holder_ref`, o ingresso é do próprio sócio. `coupon_code` opcional
(**[v2]**). → **201** com o pedido. A forma antiga, `{ "type_id": 7 }` (um ingresso, para
ele), continua aceita. `ref` adulterada ou de outro título: 422 `invalid_holder`
(`context.index`, `context.field: "holder_ref"`).

`GET /api/tickets/my-orders` — todos os pedidos do sócio.
`GET /api/tickets/orders/{id}` — um pedido (de outro sócio: 404 `order_not_found`).
`POST /api/tickets/orders/{id}/payment` — ver *Pagamento*.
`DELETE /api/tickets/orders/{id}` — desiste (só pendente).
`GET /api/tickets/orders/{id}/pdf` — PDF dos ingressos (só pago).

#### Compra do não sócio (sem `Session`)

`POST /api/tickets/events/{id}/guest-orders` (limite: 10 por minuto por IP):

```json
{
  "buyer": { "cpf": "529.982.247-25", "name": "Carla Visitante", "email": "carla@email.com", "phone": "61999990000" },
  "tickets": [
    { "type_id": 7, "holder_name": "Carla Visitante", "holder_cpf": "52998224725" },
    { "type_id": 7, "holder_name": "João Visitante", "holder_cpf": "11144477735" }
  ]
}
```

→ **201** com o pedido **e** `access_token`. Quem compra não precisa ser um dos titulares.
Aceita também `"coupon_code": "FESTA10"` (opcional, **[v2]**).
CPF com ou sem máscara.

Com o header `Ticket-Order-Token` (token errado ou ausente: 404 `order_not_found`):

`GET /api/tickets/guest-orders/{id}` · `POST /api/tickets/guest-orders/{id}/payment` ·
`DELETE /api/tickets/guest-orders/{id}` · `GET /api/tickets/guest-orders/{id}/pdf`.

`POST /api/tickets/guest-orders/resend-link` — `{ "cpf": "…", "email": "…" }` → sempre
**200** com a mesma mensagem (exista pedido ou não; limite 5 por minuto). Mostre
"Se houver pedido com esse CPF e e-mail, enviamos o link."

**[v6]** "Meus ingressos" do não sócio (código por e-mail): `POST /api/tickets/guest-access/code`,
`POST /api/tickets/guest-access/verify` e `GET /api/tickets/guest-access/orders` (header
`Ticket-Access-Token`). Detalhes no [prompt de atualização v6](#prompt-de-atualização--v6-meus-ingressos-do-não-sócio).

#### O pedido (mesmo formato para sócio e não sócio)

```json
{
  "id": 58,
  "status": "pending_payment",
  "status_label": "Aguardando pagamento",
  "buyer_kind": "guest",
  "buyer": { "name": "Carla Visitante", "email": "carla@email.com", "cpf": "529.***.**7-25" },
  "total": 160,
  "discount_total": 0,
  "coupon_code": null,
  "courtesy": false,
  "hold_until": "2027-01-10T15:42:00-03:00",
  "paid_at": null,
  "origin": "site",
  "event": { "id": 3, "title": "…", "venue": "…", "starts_at": "…", "ends_at": "…", "image_url": "…" },
  "payment": null,
  "tickets": [
    {
      "id": 101, "status": "pending", "status_label": "Aguardando pagamento",
      "holder_name": "Carla Visitante", "holder_cpf": "529.***.**7-25",
      "type": { "id": 7, "name": "Pista" }, "lot": { "id": 21, "name": "1º lote" },
      "price_kind": "non_member", "list_price": 80, "discount_amount": 0, "amount": 80,
      "validity": "event", "day": null,
      "code": null, "qr_code": null, "checked_in_at": null, "entries": []
    }
  ],
  "access_token": "…só na criação do pedido do não sócio…"
}
```

- Os ingressos de um pedido podem ser de **lotes diferentes** (o pedido atravessou a virada):
  mostre o preço de cada um.
- **[v2]** Com cupom: `list_price` é o preço do lote, `discount_amount` o abatido e `amount`
  o cobrado. Mostre "de R$ 80,00 por R$ 72,00" e, no resumo, "Desconto (cupom FESTA10):
  − R$ 8,00" com `discount_total` e `coupon_code`.
- `code` e `qr_code` só vêm com o ingresso **pago** (`valid`). `qr_code` é uma imagem SVG
  em data URI: use direto em `<img src>`. O código (`ING-XXXXXXXX`) também deve aparecer em
  texto, grande, para ser digitado na portaria.
- `checked_in_at` preenchido = ingresso já usado na entrada (`status: "used"`; é a última
  entrada).
- **[v4]** `validity` e `day` (`{id, name, label, starts_at, ends_at}` na diária; `null` no
  passaporte e no evento sem dias): mostre "Diária · Sáb 14/02" ou "Passaporte · todos os
  dias" no ingresso. `event.days` vem no pedido. `entries` lista cada entrada
  (`{day_id, checked_in_at}`): o **passaporte** usado num dia continua valendo nos outros —
  mostre "Entrou em Sáb 13/02" e não trate como esgotado. O mesmo QR serve para todos os dias.

| `status` do pedido | O que mostrar |
|---|---|
| `pending_payment` | Valor, contador até `hold_until`, botão Pagar, botão Desistir |
| `paid` | Ingressos com QR, código, botão PDF |
| `expired` | "O prazo para pagar acabou." Botão para comprar de novo (novo pedido) |
| `withdrawn` | "Você desistiu deste pedido." |
| `cancelled` | "Pedido cancelado pela organização." (+ `payment.refunded_amount` se houver) |

| `status` do ingresso | Texto |
|---|---|
| `pending` | Aguardando pagamento |
| `valid` | Válido |
| `used` | Utilizado |
| `expired` | Expirado |
| `cancelled` | Cancelado |

#### Pagamento

Depois de aprovado na eRede, exatamente como o site já faz nas reservas e torneios:

`POST …/payment` com
`{ "payment_method": "credit", "paid_amount": 160.00, "payment_integration_id": "{tid da eRede}", "paid_at": "opcional, ISO 8601" }`

- **201** `{ message, order }` — pago; mostre os ingressos.
- **200** — o mesmo `tid` reenviado para o mesmo pedido (retentativa segura). Faça
  retentativa com o **mesmo tid** se a resposta não chegar.
- `paid_amount` diferente do `total`: 422 `payment_mismatch` (`context.expected`). Não deve
  acontecer se você cobrar o `total`.
- **409 `hold_expired`**: o prazo venceu e os lugares acabaram. **Estorne na eRede** e
  mostre "Os ingressos esgotaram enquanto o pagamento era feito. O valor foi estornado."
- 409 `invalid_status`: pedido já pago/desistido/cancelado, ou o tid já foi usado para outro
  pedido.

### Recusas (`{ message, reason, context? }`)

Decida pelo `reason`, nunca pelo texto. `message` é um texto em português que pode ser
mostrado como está.

| `reason` | HTTP | Quando | Sugestão no site |
|---|---|---|---|
| `event_not_found` | 404 | Evento inexistente, rascunho ou cancelado | Página 404 |
| `sales_closed` | 422 | Fora da janela de vendas | "Vendas fechadas" e recarregar o evento |
| `type_not_found` | 404 | Tipo inexistente/inativo | Recarregar o evento |
| `sold_out` | 409 | O tipo esgotou (`context.type_id`) | Recarregar; mostrar próximo lote, se houver |
| `capacity_full` | 409 | Lotação do evento cheia (`context.left`; **[v4]** `context.day_id` = o dia lotado no evento de vários dias) | "Evento esgotado" (ou "{dia} esgotado") |
| `members_only` | 422 | Lote só para sócios, pedido de não sócio | Convidar a entrar com login de sócio |
| `limit_per_order` | 422 | Não sócio com mais que `max_per_order` (`context.max`) | Limitar no formulário |
| `already_has_ticket` | 409 | Algum CPF já tem ingresso no evento (`context.cpf` mascarado; **[v4]** no mesmo dia, ou passaporte × diária — `context.day_id` = o dia do ingresso que já existe, `null` se for passaporte) | Destacar o titular |
| `invalid_holder` | 422 | Nome/CPF inválido ou CPF repetido no mesmo dia (`context.index`, `context.field`) | Marcar o campo |
| `holder_is_member` | 422 | CPF de sócio no pedido sem login (`context.index`) | "Sócio compra pelo login de sócio" |
| `holder_not_in_title` | 422 | **[v3]** Pessoa de fora do título no pedido de sócio (`context.index`) | Não deve acontecer usando `my-title`; recarregar a lista |
| `member_not_found` | 422 | Login de sócio sem cadastro ativo no clube | "Procure a secretaria" |
| `order_not_found` | 404 | Pedido alheio, ou token errado | 404 / pedir o link de novo |
| `invalid_status` | 409 | Pedido não está na situação certa; PDF de pedido não pago | Recarregar o pedido |
| `hold_expired` | 409 | Ver *Pagamento* | Estornar e avisar |
| `payment_mismatch` | 422 | Valor ≠ total | Recarregar o pedido |
| `paid_contact_organizer` | 422 | Desistir de pedido pago | "Fale com a organização" |
| `coupon_invalid` | 422 | **[v2]** Cupom inexistente, desativado ou fora do prazo | Marcar o campo do cupom; permitir seguir sem ele |
| `coupon_not_applicable` | 422 | **[v2]** O cupom não vale para nenhum ingresso do pedido (tipo ou público) | Idem |
| `coupon_exhausted` | 409 | **[v2]** Usos acabaram ou não cobrem o pedido (`context.remaining`) | "Cupom esgotado" (ou "vale para mais N ingresso(s)") |

Erros de validação de formato (campo faltando, e-mail inválido) vêm no padrão do Laravel:
**422** `{ message, errors: { campo: [..] } }`.

### Telas

1. **/ingressos** — cartões dos eventos (imagem, título, data, local, "a partir de R$ x").
2. **/ingressos/{id}** — detalhes, regras, tipos com o lote vigente e os dois preços (realce
   o preço que vale para quem está vendo: sócio logado → sócio; senão → não sócio, com um
   aviso "Sócio? Entre para pagar R$ y"). Botão Comprar. **[v4]** Evento de vários dias: um
   bloco por dia com as diárias dele (e o horário do dia) e, se houver, o passaporte em
   destaque ("todos os dias").
3. **Checkout do sócio** — **[v3]** lista das pessoas do título (`my-title` com o
   `event_id`), cada uma com uma caixa "comprar" e o tipo de ingresso; quem já tem ingresso
   aparece desabilitado; **[v4]** no evento de vários dias, para cada pessoa: os dias (diária
   de cada um) ou o passaporte, desabilitando pelo `days_taken`; campo de cupom **[v2]**; resumo com o nome e o preço de cada pessoa;
   pagamento.
4. **Checkout do não sócio** — dados de quem compra; para cada ingresso, tipo + nome + CPF
   (validar CPF no cliente também; **[v4]** o mesmo CPF pode repetir só em diárias de dias
   diferentes); campo de cupom **[v2]**; resumo com o total do Lara;
   contador; pagamento.
5. **/ingressos/pedido/{id}?token=…** — pedido do não sócio (também a confirmação depois
   do pagamento).
6. **Meus ingressos** (área do sócio) — `GET /api/tickets/my-orders`.
7. **"Perdi o link"** — CPF + e-mail.
8. **[v6]** **Meus ingressos do não sócio** — CPF + e-mail → código → lista dos pedidos.

Mostre os horários no fuso de Brasília. Valores em reais com vírgula.

### O que o site não faz

- Não calcula preço, lote, estoque nem limite — tudo vem do Lara.
- Não manda e-mail.
- Não faz check-in (é na portaria, pelo Lara).
- **[v4]** Não decide se uma diária cruza com outra nem a lotação de cada dia: o Lara confere
  no pedido.
- Não cancela pedido pago (é com a organização, no Lara).

## Fim do prompt

---

## Prompt de atualização — v4 (evento de vários dias)

> Para o agente do site que **já implementou a v3** (vitrine, compra do sócio pelo título,
> compra do não sócio, cupom, pagamento, meus ingressos). Copie de "Início da atualização v4"
> até "Fim da atualização v4". É só o que muda; o resto do contrato continua igual e a API é
> compatível: evento sem dias responde como antes, com campos novos que podem ser ignorados.

### Início da atualização v4

O Lara passou a suportar **eventos de vários dias** (um festival de sábado e domingo, por
exemplo). Nesses eventos, cada tipo de ingresso é uma **diária** (vale um dia) ou o
**passaporte** (vale todos os dias, com uma entrada por dia). **Nem todo evento de vários
dias tem passaporte**: só existe se a organização cadastrou. Evento de um dia só (mesmo
passando da meia-noite) não muda nada. Adapte o que já existe de ingressos no site:

#### 1. O que muda na API (só campos novos)

`GET /api/tickets/events` e `GET /api/tickets/events/{id}` ganham:

```json
{
  "multi_day": true,
  "capacity_left": 40,
  "days": [
    { "id": 11, "name": "Sábado", "label": "Sábado (13/02)", "starts_at": "2027-02-13T18:00:00-03:00", "ends_at": "2027-02-14T02:00:00-03:00", "capacity_left": 40 },
    { "id": 12, "name": null, "label": "Dom 14/02", "starts_at": "2027-02-14T18:00:00-03:00", "ends_at": "2027-02-14T23:00:00-03:00", "capacity_left": 95 }
  ],
  "types": [
    { "id": 20, "name": "Pista", "validity": "day", "day_id": 11, "available": true, "lot": { "…": "…" }, "next_lot": null },
    { "id": 21, "name": "Pista", "validity": "day", "day_id": 12, "available": true, "lot": { "…": "…" }, "next_lot": null },
    { "id": 22, "name": "Passaporte", "validity": "passport", "day_id": null, "available": true, "lot": { "…": "…" }, "next_lot": null }
  ]
}
```

- `multi_day`: `false` e `days: []` no evento de um dia; aí todo tipo vem com
  `validity: "event"` e `day_id: null` — siga como hoje.
- `validity`: `"day"` = diária do dia `day_id`; `"passport"` = todos os dias; `"event"` =
  evento sem dias.
- `days[].label` é o texto pronto do dia ("Sábado (13/02)" ou "Dom 14/02"). O **nome do tipo
  pode se repetir** entre dias ("Pista" no sábado e no domingo): mostre sempre com o `label`.
- Lotação **por dia**: `days[].capacity_left` limita a diária daquele dia; o
  `capacity_left` do evento é o do dia mais cheio (o que cabe de passaporte). `null` = sem
  lotação.
- Lotes, preços (`price_member`/`price_non_member`), `members_only`, `remaining` e `next_lot`
  continuam por tipo, como hoje: cada diária e o passaporte têm os próprios.

`GET /api/tickets/my-title?event_id={id}` ganha, em cada pessoa, `days_taken`: os ids dos dias
em que ela já tem ingresso (o passaporte cobre todos). `has_ticket` continua (tem algum
ingresso no evento).

```json
{ "ref": "eyJpdiI6…", "name": "Bia Souza", "cpf": "111.***.**7-35", "titular": false, "is_me": false, "has_ticket": true, "days_taken": [11] }
```

No **pedido** (sócio e não sócio), o evento ganha `event.days` (mesmo formato, sem
`capacity_left`) e cada ingresso ganha:

```json
{
  "validity": "day",
  "day": { "id": 11, "name": "Sábado", "label": "Sábado (13/02)", "starts_at": "…", "ends_at": "…" },
  "entries": [ { "day_id": 11, "checked_in_at": "2027-02-13T19:12:00-03:00" } ]
}
```

- `day` só na diária; `null` no passaporte e no evento sem dias.
- `entries`: cada entrada registrada na portaria. O passaporte tem até uma por dia.
- `checked_in_at` (que já existia) passa a ser a **última** entrada.

Criação de pedido: **o corpo não muda**. O `type_id` já diz o dia (diária) ou o passaporte.
O que muda é a regra:

- **Um ingresso por CPF por dia** (antes: por evento). A mesma pessoa pode ter a diária de
  sábado e a de domingo — **inclusive no mesmo pedido** (o mesmo `holder_ref`, ou o mesmo
  CPF no não sócio, em itens de dias diferentes). Não pode: duas no mesmo dia, nem diária +
  passaporte.

#### 2. Recusas (mesmos `reason`, contexto novo)

| `reason` | O que mudou |
|---|---|
| `already_has_ticket` (409) | Agora é "já tem ingresso **no mesmo dia**" (ou passaporte × diária). `context.day_id` = o dia do ingresso que já existe (`null` se for passaporte). |
| `invalid_holder` (422) | Também quando a mesma pessoa aparece duas vezes **no mesmo dia** do pedido (ou diária + passaporte). `context.index`, `context.field: "holder_cpf"`. |
| `capacity_full` (409) | `context.day_id` = o dia que lotou (no evento de vários dias). Mostre "{label do dia} esgotado" e recarregue o evento. |

#### 3. Telas

1. **Vitrine e página do evento**: no evento de vários dias, mostre as datas como
   "13/02 a 14/02" e, na página, **um bloco por dia** (label e horário) com as diárias dele;
   o passaporte, se houver, num bloco à parte em destaque ("Vale todos os dias"). Se nenhum
   tipo vier `passport`, não mencione passaporte. "A partir de R$ x" continua sendo o menor
   preço vigente entre todos os tipos.
2. **Checkout do sócio**: para cada pessoa do título, em vez de um único tipo, deixe escolher
   **os dias** (uma diária por dia marcado) **ou** o passaporte — são excludentes. Desabilite
   pelo `days_taken`: a diária do dia X para quem tem X; o passaporte para quem tem qualquer
   dia. Cada escolha vira um item `{type_id, holder_ref}` (a mesma `holder_ref` em vários
   itens, um por dia).
3. **Checkout do não sócio**: o mesmo CPF pode repetir **só** em diárias de dias diferentes;
   valide no cliente para diária + passaporte e dois no mesmo dia (o Lara confere de novo).
   `max_per_order` conta itens (cada diária é um item).
4. **Resumo do pedido, confirmação e meus ingressos**: em cada ingresso, mostre
   "Diária · {day.label}" ou "Passaporte · todos os dias" (para `validity: "event"`, como
   hoje). O mesmo QR/código serve para todos os dias do passaporte.
5. **Situação do ingresso**: `status: "used"` no **passaporte** não quer dizer que acabou —
   ele entra de novo nos outros dias. Mostre "Entrou em {label}" para cada item de `entries`
   (o `day_id` aponta para `event.days`) e mantenha o QR visível enquanto o evento não
   terminar. Na diária e no evento sem dias, `used` continua sendo "Utilizado".
6. **Contador e cópias**: os textos de "esgotado" passam a citar o dia quando
   `capacity_full` trouxer `day_id`.

#### 4. O que o site continua sem fazer

- Não calcula se uma diária cruza com outra, nem a lotação do dia: o Lara confere no pedido.
- Não faz check-in (o passaporte é lido na portaria, uma vez por dia).

### Fim da atualização v4

---

## Prompt de atualização — v5 (lote sem limite)

> Para o agente do site, depois da v4 (ou junto com ela). Copie de "Início da atualização v5"
> até "Fim da atualização v5". Mudança pequena e compatível.

### Início da atualização v5

O Lara passou a aceitar **lote sem limite de ingressos**. Para o site, muda um campo:

- Em `GET /api/tickets/events` e `GET /api/tickets/events/{id}`, `types[].lot.remaining`
  pode vir **`null`** = o lote não tem quantidade (não esgota por número; fecha pela data ou
  quando a organização vira o lote). Hoje o site provavelmente trata `remaining` como número
  (contagem "restam N", selo "últimos ingressos", desabilitar com 0): com `null`, **não mostre
  contagem nem selo** e trate o tipo como disponível (o que manda é `available`).
- Confira que nenhum cálculo do site quebra com `null` (ex.: `remaining <= 10`, `Math.min`,
  formatação de número).

A organização também ganhou no Lara um botão **"Virar lote agora"**: o lote à venda de cada
tipo fecha e o seguinte abre, com o preço novo. Nada a implementar além do que já existe: o
site já recarrega o evento antes do checkout e o preço que vale é sempre o do pedido. Quem
estava no meio de um pedido mantém o preço do lote em que entrou.

### Fim da atualização v5

---

## Prompt de atualização — v6 (meus ingressos do não sócio)

> Para o agente do site, depois das atualizações anteriores (ou junto com elas). Copie de
> "Início da atualização v6" até "Fim da atualização v6".

### Início da atualização v6

Hoje o não sócio (que compra sem conta) só volta aos ingressos pelo link do e-mail de cada
pedido, ou pelo "Perdi o link". Crie no site uma área **"Meus ingressos" para quem não é
sócio**, em que a pessoa se identifica com **CPF + e-mail da compra + um código de 6 dígitos**
que o Lara manda para esse e-mail, e vê **todos os pedidos** dela. O código existe porque o QR
Code do ingresso é a entrada no evento: saber CPF e e-mail não basta, a pessoa prova que lê o
e-mail.

#### Fluxo e API (todas com o `Authorization: Bearer {API_TOKEN}` de sempre, sem login)

**1. Pedir o código** — `POST /api/tickets/guest-access/code` (limite 5 por minuto)

```json
{ "cpf": "529.982.247-25", "email": "carla@email.com" }
```

→ **200**, **sempre igual**, exista compra ou não:

```json
{ "message": "Se houver compra com esse CPF e e-mail, enviamos um código para o e-mail.", "expires_in_minutes": 10 }
```

- Mostre a mensagem como vem e passe para a tela do código. **Não** diga "CPF não
  encontrado" — o Lara não revela isso de propósito.
- CPF inválido ou e-mail mal formado: **422** no padrão do Laravel (`errors.cpf`,
  `errors.email`). Valide o CPF no cliente também.
- O e-mail não diferencia maiúsculas. O e-mail tem de ser o **mesmo informado na compra**.
- Limites do Lara: o código vale **10 minutos** e **uma vez**; pedir outro invalida o
  anterior; no máximo **5 códigos por hora** para o mesmo CPF + e-mail (acima disso o e-mail
  simplesmente não sai). Ofereça "Reenviar código" só depois de ~60 s.

**2. Conferir o código** — `POST /api/tickets/guest-access/verify` (limite 10 por minuto)

```json
{ "cpf": "529.982.247-25", "email": "carla@email.com", "code": "481920" }
```

→ **200**:

```json
{ "access_token": "eyJpdiI6…", "expires_at": "2027-01-10T17:42:00-03:00" }
```

Recusas (`{ message, reason, context? }`, decida pelo `reason`):

| `reason` | HTTP | Quando | No site |
|---|---|---|---|
| `access_code_invalid` | 422 | Código errado, ainda com tentativas (`context.attempts_left`) | "Código incorreto. Restam N tentativas." e manter o campo |
| `access_code_expired` | 422 | Vencido, já usado, tentativas esgotadas (5) ou nenhum código pedido | "Este código não vale mais." + botão "Enviar novo código" |

**3. Listar os pedidos** — `GET /api/tickets/guest-access/orders` com o header
`Ticket-Access-Token: {access_token}` (limite 60 por minuto)

```json
{
  "buyer": { "name": "Carla Visitante", "cpf": "529.***.**7-25", "email": "carla@email.com" },
  "orders": [
    { "id": 58, "status": "paid", "…": "…o mesmo formato de pedido de sempre…", "access_token": "…token deste pedido…" }
  ]
}
```

- `orders`: os pedidos de não sócio daquele CPF + e-mail, **do mais novo ao mais antigo**
  (até 50), em **todas as situações** (pago, aguardando pagamento, expirado, desistido,
  cancelado). É o mesmo formato de pedido que o site já usa, com o `access_token` **de cada
  pedido**.
- Para abrir, pagar, desistir ou baixar o PDF de um pedido, use as rotas que o site **já tem**
  (`/api/tickets/guest-orders/{id}…` com o header `Ticket-Order-Token: {access_token do
  pedido}`). Nada novo aí.
- Sem o header, token adulterado ou vencido: **401** `access_expired` → volte para a tela de
  CPF + e-mail.

#### Guardar o acesso

- O `access_token` vale **2 horas** (`expires_at`). Guarde-o como os outros tokens de
  ingresso: cookie **httpOnly** ou sessão do servidor do Next.js — nunca em `localStorage`,
  na URL ou em log. Ao vencer (ou no 401), peça CPF + e-mail de novo.
- Os tokens de pedido que vêm na lista seguem a mesma regra.

#### Telas

1. **Entrada** — em `/ingressos/meus` (ou onde ficar melhor na navegação; um link "Meus
   ingressos" visível para quem não está logado como sócio, e também nas páginas de pedido do
   não sócio). Campos CPF e e-mail, botão "Enviar código". Texto curto: "Comprou sem ser sócio?
   Informe o CPF e o e-mail usados na compra."
2. **Código** — "Enviamos um código para {e-mail mascarado, ex.: c***@email.com}". Campo de 6
   dígitos (`inputmode="numeric"`, `autocomplete="one-time-code"`), "Entrar", "Reenviar
   código" (após ~60 s), "Trocar CPF/e-mail".
3. **Lista** — "Olá, {buyer.name}". Agrupe por situação: primeiro os pagos de eventos que
   ainda não acabaram (com os ingressos, QR e código, como na página do pedido), depois os
   aguardando pagamento (com "Pagar" e o contador do `hold_until`), depois o histórico
   (eventos passados, expirados, desistidos, cancelados). Cada pedido abre a página de pedido
   que já existe. Botão "Sair" apaga o acesso.
4. Lista vazia (pode acontecer: o e-mail da compra era outro) → "Nenhum pedido encontrado para
   este CPF e e-mail." e um link para tentar de novo.

#### O que não muda

- "Perdi o link" e o link do e-mail de cada pedido continuam funcionando.
- O sócio continua vendo os ingressos pelo login dele (`/api/tickets/my-orders`); esta área é
  só para quem comprou sem ser sócio.
- Quem só é **titular** de um ingresso (outra pessoa comprou para ela) não acha o ingresso
  aqui: o acesso é de quem comprou. Se for o caso, mostre "Os ingressos ficam com quem fez a
  compra".

### Fim da atualização v6

---

## Prompt de atualização — v7 (lugar marcado: mesas, bistrôs, camarotes)

> Para o agente do site, depois das atualizações anteriores. Copie de "Início da atualização
> v7" até "Fim da atualização v7". Regras no Lara: [ingressos.md](ingressos.md), seção
> "Lugar marcado".

### Início da atualização v7

Alguns tipos de ingresso agora são de **lugar marcado**: mesa, bistrô, camarote. Quem compra
escolhe **no mapa** o lugar exato, num passo novo da compra. O Lara garante que o mesmo lugar
não é vendido duas vezes no mesmo dia; o site mostra o mapa, deixa escolher e trata a recusa
quando duas pessoas escolhem o mesmo lugar ao mesmo tempo.

Regras que o site precisa saber:

- **O ingresso do lugar não é entrada.** A mesa é só a mesa: cada pessoa compra a própria
  entrada num tipo comum (Pista etc.). Deixe isso claro na vitrine e no carrinho ("Mesa para
  4 pessoas — as entradas são compradas à parte").
- O ingresso do lugar é **nominal no responsável** (o titular do ingresso). Para o sócio, o
  responsável é alguém do título, pela mesma `holder_ref` de sempre (vazio = ele mesmo); para
  o não sócio, `holder_name` e `holder_cpf` como nos outros ingressos. O responsável **pode**
  ter também a entrada dele no mesmo pedido (o "um ingresso por CPF por dia" não vale para o
  lugar).
- **Lugar por dia**: no evento de vários dias, o tipo de lugar é sempre a diária de um dia
  (`day_id` preenchido); a mesma mesa é vendida separadamente em cada dia. Não existe
  passaporte de mesa.
- Vários tipos podem usar **o mesmo mapa** com preços diferentes (ex.: "Mesa Vip" vende as
  mesas da frente, "Mesa" as do fundo): cada lugar do mapa diz qual tipo o vende no dia
  (`type_id`).
- O limite `max_per_order` do evento vale **separado** para entradas e para lugares (até N
  entradas e até N lugares por pedido) — e, nos lugares, vale também para o sócio.
- O preço continua sendo o do lote vigente do tipo, calculado pelo Lara.

#### 1. O que muda na vitrine (`GET /api/tickets/events` e `/events/{id}`)

No evento:

```json
{ "has_map": true, "maps": [ { "id": 3, "name": "Mesas" }, { "id": 4, "name": "Bistrôs" } ] }
```

`maps` são os mapas que algum tipo ativo usa. Em cada tipo (`types[]`):

| Campo | O que é |
|---|---|
| `kind` | `entry` (entrada, como sempre) ou `seat` (lugar marcado) |
| `map_id` | o mapa onde o lugar é escolhido (`null` em `entry`) |
| `seat_category` | a categoria dos lugares que o tipo vende (`null` = todos os do mapa); só informativo |
| `seats_free` | lugares livres agora no dia do tipo (`null` em `entry`). Com 0, o tipo vem `available: false` |

#### 2. O mapa — `GET /api/tickets/events/{id}/maps/{mapId}?day_id={dia}`

`day_id` é **obrigatório no evento de vários dias** (use o `day_id` do tipo); sem ele, **422**
`day_required`. Mapa inexistente ou que nenhum tipo ativo usa: **404** `map_not_found`.
Limite: 120 por minuto.

```json
{
  "id": 3, "event_id": 12, "name": "Mesas",
  "width": 1000, "height": 640,
  "background_url": "https://…/planta.png",
  "day": null,
  "refresh_after": 15,
  "areas": [
    { "label": "Palco", "shape": "rect", "x": 350, "y": 20, "w": 300, "h": 80, "color": "stage" },
    { "label": "Piscina", "shape": "ellipse", "x": 760, "y": 470, "w": 210, "h": 140, "color": "water" }
  ],
  "types": [
    { "id": 21, "name": "Mesa Vip", "seat_category": "Vip", "day_id": null, "available": true,
      "lot": { "id": 40, "name": "Lote único", "price_member": 400, "price_non_member": 600, "members_only": false, "starts_at": null, "ends_at": null, "remaining": 10 } }
  ],
  "seats": [
    { "id": 301, "label": "V1", "category": "Vip", "shape": "circle", "x": 200, "y": 280, "w": 50, "h": 50, "places": 4, "status": "sold", "type_id": 21 },
    { "id": 302, "label": "V2", "category": "Vip", "shape": "circle", "x": 300, "y": 280, "w": 50, "h": 50, "places": 4, "status": "free", "type_id": 21 }
  ]
}
```

Como desenhar (as coordenadas são nas unidades do mapa: use `<svg viewBox="0 0 {width} {height}">`):

- **Fundo**: `background_url` (pode ser `null`) como `<image>` de `0,0` a `width×height`, com
  `preserveAspectRatio="xMidYMid meet"`.
- **Áreas** (`areas`): só orientação, **não clicáveis**. `x`/`y` = canto superior esquerdo;
  `shape` `rect` ou `ellipse`; escreva o `label` no centro. `color` é um nome; sugestão:
  `stage` grená, `water` azul, `green` verde, `service` cinza, `warn` amarelo, com
  preenchimento claro e borda.
- **Lugares** (`seats`): `x`/`y` = **centro**; `w`/`h` = tamanho; `shape` `circle` (elipse),
  `square` ou `rect`. Escreva o `label` dentro. `places` = quantas pessoas cabem (mostre no
  detalhe: "Mesa V2 · 4 lugares").
- `status`: `free` (pode escolher), `held` (reservado — alguém está pagando), `sold`
  (vendido), `unavailable` (desativado, nenhum tipo vende no dia ou o tipo não tem lote à
  venda). Só `free` é clicável. Não há dado de quem comprou (nem deve haver).
- `type_id`: o tipo que vende o lugar no dia; o preço é o do `lot` desse tipo em `types`
  (sócio `price_member`, não sócio `price_non_member`; `members_only` = não sócio não
  compra). Mostre o preço ao tocar no lugar.
- **Atualize** o mapa a cada `refresh_after` segundos enquanto a tela estiver aberta e
  visível (pare em segundo plano). Se o lugar que a pessoa escolheu deixou de ser `free`,
  desmarque e avise: "Esse lugar acabou de ser reservado. Escolha outro."
- No celular: zoom e arrasto (pinça) no SVG; o mapa pode ser grande.
- Legenda com as quatro situações e o lugar escolhido.

#### 3. O pedido

O item do tipo `seat` leva o **`seat_id`** escolhido; nos outros tipos ele é ignorado.

Sócio — `POST /api/tickets/events/{id}/orders`:

```json
{ "tickets": [
  { "type_id": 10, "holder_ref": "…" },
  { "type_id": 21, "seat_id": 302 }
] }
```

Não sócio — `POST /api/tickets/events/{id}/guest-orders`:

```json
{ "buyer": { "cpf": "…", "name": "…", "email": "…" },
  "tickets": [
    { "type_id": 10, "holder_name": "Carla Visitante", "holder_cpf": "529.982.247-25" },
    { "type_id": 21, "seat_id": 302, "holder_name": "Carla Visitante", "holder_cpf": "529.982.247-25" }
  ] }
```

O lugar fica preso ao pedido pelo mesmo prazo de pagamento (`hold_until`, 10 min); pago, é
de vez. Desistiu ou venceu o prazo, o lugar volta para o mapa.

No pedido devolvido (e em `my-orders`, `guest-orders/{id}`, `guest-access/orders`), cada
ingresso ganha:

```json
{ "kind": "seat", "seat": { "id": 302, "label": "V2", "category": "Vip", "places": 4, "map": { "id": 3, "name": "Mesas" } } }
```

(`kind: "entry"` e `seat: null` nas entradas.) O ingresso do lugar também tem código e QR
quando pago — serve para a recepção conferir o responsável, **não** para entrar. Na página do
pedido e em "meus ingressos", mostre o lugar em destaque e o aviso "Não é entrada".

#### 4. Recusas novas (`{ message, reason, context? }`)

| `reason` | HTTP | Quando | No site |
|---|---|---|---|
| `seat_required` | 422 | Item de tipo `seat` sem `seat_id` (`context.index`, `context.type_id`) | Leve de volta ao mapa |
| `seat_not_found` | 422 | Lugar que não existe, de outro evento ou desativado (`context.index`, `context.seat_id`) | Recarregue o mapa e peça outro lugar |
| `seat_wrong_type` | 422 | O lugar não é vendido por esse tipo (outra categoria ou mapa) | Recarregue o mapa |
| `seat_taken` | 409 | Alguém reservou o lugar antes (`context.seat_ids`); também lugar repetido no pedido | Recarregue o mapa, marque esses lugares como "acabou de ser reservado", **mantenha** o resto do carrinho e peça outro lugar |
| `limit_per_order` | 422 | Mais lugares que `max_per_order` (`context.max`) | "Até N lugares por pedido" |
| `day_required` | 422 | Mapa pedido sem `day_id` num evento de vários dias | (erro de integração) |
| `map_not_found` | 404 | Mapa inexistente ou que nenhum tipo à venda usa | Volte para o evento |

`hold_expired` (pagamento depois do prazo) pode trazer `context.seat_ids`: o lugar foi
reservado por outra pessoa enquanto o pedido estava vencido. O tratamento é o de sempre: o
site estorna na eRede e avisa.

#### 5. Telas

1. **Evento**: os tipos `seat` aparecem num bloco próprio ("Mesas e bistrôs"), com o preço do
   lote, "N lugares livres" (`seats_free`) e o aviso de que não é entrada.
2. **Escolher o lugar**: ao adicionar um tipo `seat`, abra o mapa dele (`map_id`, `day_id`
   do tipo). Escolher um lugar livre adiciona ao carrinho "Mesa V2 · 4 lugares · R$ 600".
   Pode escolher mais de um (até `max_per_order`). Tire do carrinho pelo próprio mapa
   (tocar de novo) ou pela lista.
3. **Responsável**: no checkout, o ingresso do lugar pede o responsável (sócio: alguém do
   título, padrão ele mesmo; não sócio: nome e CPF, padrão o comprador).
4. **Pedido / meus ingressos**: o lugar em destaque e "Não é entrada". O PDF do Lara já sai
   com o lugar.

#### 6. O que não muda

- Os tipos de entrada, os lotes, os cupons (o cupom pode valer para o tipo de lugar, se a
  organização quiser), o pagamento e o "meus ingressos" do não sócio seguem iguais.
- O site não cria nem edita mapas: o desenho é feito no Lara.

### Fim da atualização v7

---

## Prompt de atualização — v8 (mesa cativa)

> Para o agente do site, depois da v7. Copie de "Início da atualização v8" até "Fim da
> atualização v8". Regras no Lara: [ingressos.md](ingressos.md), seção "Mesa cativa".
> (28/10/2026.) As outras novidades do mesmo dia no Lara — histórico de quem vendeu,
> relatório de caixa, impressão térmica e cortesia sem CPF — **não mudam nada no site**: a
> cortesia sem CPF é emitida só no balcão, para não sócio sem conta, e não aparece em
> "meus ingressos" do site.

### Início da atualização v8

Algumas mesas (ou bistrôs, camarotes) agora podem ser **cativas**: guardadas para uma
**matrícula** (título de sócio). Até a data de liberação, só um sócio daquele título — o
titular ou um dependente, no login dele — compra o lugar. Depois da liberação, a mesa cativa
não comprada vira um lugar comum e qualquer um compra. A liberação é a mesma para todas as
cativas do evento; a cativa vale para todos os dias do evento.

O site **não** precisa saber de quem é a cativa: o Lara decide e diz, em cada lugar, se é
cativa e se é da matrícula de quem está logado.

#### 1. O mapa ganha campos (`GET /api/tickets/events/{id}/maps/{mapId}`)

No mapa:

| Campo | O que é |
|---|---|
| `captive_release_at` | Quando as cativas não compradas viram lugar comum (ISO 8601). `null` = não há prazo (só a organização tira a cativa). Só informativo. |

Em cada lugar (`seats[]`):

| Campo | O que é |
|---|---|
| `status` | Ganha o valor **`captive`**: mesa cativa de outro sócio, ainda não liberada. Não se escolhe. |
| `captive` | `true` se o lugar é cativa ainda guardada (de quem for). |
| `captive_mine` | `true` se a cativa é da matrícula de quem está logado (só na rota do sócio, abaixo); nesse caso o `status` vem `free`. |

Na rota pública (sem login), toda cativa ainda guardada vem `status: "captive"`,
`captive: true`, `captive_mine: false`. Mostre-a como **"Mesa cativa — reservada para um
sócio"** (cor própria, não clicável), com a dica "libera em {data}" quando
`captive_release_at` vier preenchido e ainda não tiver passado.

#### 2. Mapa visto pelo sócio logado — rota nova

`GET /api/tickets/events/{id}/maps/{mapId}/member?day_id={dia}` — com o `api_token` e o JWT
do sócio no header `Session`, como as outras rotas do sócio. Mesmo formato do mapa público;
a diferença é que **a cativa da matrícula dele** vem `status: "free"` com
`captive_mine: true` (as de outras matrículas continuam `captive`). Sem o header `Session`: 400; JWT inválido ou vencido: 401 (como nas outras rotas do sócio).
Mesmo limite (120 por minuto) e mesmas recusas (`day_required`, `map_not_found`).

- Sócio logado: **use esta rota** para desenhar o mapa (e no refresh de `refresh_after`).
- Mostre a cativa dele em destaque: "Sua mesa cativa — garanta até {captive_release_at}".
- Visitante e não sócio: a rota pública de sempre.

#### 3. Recusa nova no pedido

| `reason` | HTTP | Quando | No site |
|---|---|---|---|
| `seat_captive` | 422 | O lugar é cativa de outra matrícula e ainda não liberou (`context.index`, `context.seat_id`, `context.released_at` — `null` sem prazo) | Recarregue o mapa, marque o lugar como "mesa cativa" e peça outro. Não revele de quem é. |

Também acontece com o não sócio (nunca compra cativa antes da liberação) e com o sócio de
outro título. O pedido do sócio da matrícula certa passa normalmente
(`POST /api/tickets/events/{id}/orders` com `seat_id`, como na v7).

#### 4. O que não muda

- `seat_taken`, `seat_required`, o limite por pedido, o lugar por dia e o "não é entrada"
  seguem iguais.
- O site não marca nem tira cativas: isso é feito no Lara.

### Fim da atualização v8
