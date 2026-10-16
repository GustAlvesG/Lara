# Prompt de ajuste para o agent do site de locação — Espaços Sociais (mesas, horário, contato)

> Cole o conteúdo abaixo (da linha `---` em diante) em um Claude Code aberto no repositório do
> **site de locação**, que **já implementou** as reservas de espaços sociais
> ([reservas-sociais-prompt-site-locacao.md](reservas-sociais-prompt-site-locacao.md)) e a edição
> da pré-reserva. Aqui está só o que mudou na API em 16/10/2026.

---

# Ajuste: mesas e cadeiras, horário do evento e prazo de contato

O Lara ganhou três coisas nas reservas de espaços sociais. Implemente sobre o que já existe; o
restante do contrato não mudou e nada que o site já manda deixou de funcionar.

## 1. Mesas e cadeiras

Cada espaço inclui uma quantidade base de mesas e cadeiras. O sócio pode contratar mais:
mesa avulsa, cadeira avulsa ou jogo completo (o clube configura os itens e os preços).

### Espaço — campos novos em `GET /api/social/groups/{id}/places`

```jsonc
{ "id": 41, "name": "Churrasqueira da Sauna", …,
  "base_tables": 10,          // mesas incluídas no preço
  "base_chairs": 40 }         // cadeiras incluídas no preço
```

### Novo catálogo: `GET /api/social/furniture`

Mesma autenticação de sempre. Os itens de mobiliário ativos:

```jsonc
[
  { "id": 12, "category": "mobiliario", "name": "Jogo completo (mesa + 4 cadeiras)", "unit": null,
    "price": 25.0, "tables_per_unit": 1, "chairs_per_unit": 4, "max_quantity": 5 },
  { "id": 13, "category": "mobiliario", "name": "Cadeira avulsa", "unit": null,
    "price": 3.0, "tables_per_unit": 0, "chairs_per_unit": 1, "max_quantity": null }
]
```

- `tables_per_unit` / `chairs_per_unit`: quantas mesas e cadeiras **cada unidade** acrescenta.
- `max_quantity`: máximo por reserva; `null` = sem limite.
- `GET /api/social/beverages` continua devolvendo **só bebidas** e ganhou os mesmos campos
  (`category: "bebida"`, `tables_per_unit: 0`, `chairs_per_unit: 0`, `max_quantity`). Ou seja,
  as bebidas agora também podem ter máximo por reserva.

### Pedido e edição

O mobiliário vai **no mesmo array `items`** das bebidas. Use `item_id` (o `beverage_id` antigo
continua aceito, com o mesmo significado):

```jsonc
"items": [
  { "item_id": 3,  "quantity": 2 },   // bebida
  { "item_id": 12, "quantity": 3 }    // jogo completo
]
```

Na edição (`PUT /bookings/{id}`), `items` continua sendo a lista **inteira**, bebidas e
mobiliário juntos: o que não vier sai.

Recusa nova: `item_limit` (422) quando a quantidade passa do `max_quantity` do item
(`context.item_id`, `context.max_quantity`).

### Reserva — campos novos

```jsonc
"base_tables": 10,
"base_chairs": 40,
"tables_total": 13,          // base + mobiliário contratado
"chairs_total": 52,
"items": [
  { "item_id": 12, "beverage_id": 12, "category": "mobiliario", "name": "Jogo completo…",
    "unit_price": 25.0, "quantity": 3, "subtotal": 75.0, "tables_per_unit": 1, "chairs_per_unit": 4 },
  …
]
```

`base_tables`/`base_chairs` da reserva são uma cópia do espaço no momento da pré-reserva. Mostre
os da reserva, não os do espaço.

## 2. Horário do evento

Cada espaço pode ter uma **duração fixa** em horas. Nesse caso o sócio informa o horário de
início e o fim é calculado (início + duração, podendo passar da meia-noite). A data continua
sendo o dia inteiro do espaço: o horário não muda a disponibilidade do calendário.

### Espaço — campos novos

```jsonc
"event_duration_hours": 6,        // null = espaço sem horário (não peça início)
"event_earliest_start": "10:00",  // null = sem limite
"event_latest_start": "20:00"     // null = sem limite
```

### Pedido e edição

```jsonc
// POST /api/social/bookings e PUT /api/social/bookings/{id}
"start_time": "18:00"   // HH:MM
```

- Com `event_duration_hours` preenchido, `start_time` é **obrigatório** na criação e precisa estar
  entre `event_earliest_start` e `event_latest_start` (inclusive), quando existirem.
- Sem duração configurada, `start_time` é ignorado.
- Na edição, `start_time` pode mudar enquanto `can_edit` (como os demais campos; data e espaço
  não).
- Recusa nova: `invalid_time` (422) — início ausente, mal formado ou fora da faixa
  (`context.earliest_start`, `context.latest_start` ou `context.duration_hours`).

### Reserva — campos novos

```jsonc
"start_time": "20:00",      // null quando o espaço não tem duração
"end_time": "02:00",
"duration_hours": 6,
"ends_next_day": true       // o fim cai depois da meia-noite
```

Mostre como "20:00 às 02:00 (dia seguinte)" quando `ends_next_day`.

## 3. Prazo de contato da secretaria

Depois da pré-reserva, a secretaria tem **2 dias úteis** para entrar em contato com o sócio. Isso
é controlado no painel do Lara (aviso para a equipe); **não há campo novo na API do site**. Só
ajuste o texto ao sócio:

- Depois de criar a pré-reserva e no detalhe de reservas em `requested`: *"A secretaria do clube
  vai entrar em contato em até 2 dias úteis para o contrato."*

## O que mudar no site

1. **Vitrine do espaço**: mostre "Inclui X mesas e Y cadeiras" (`base_tables`, `base_chairs`) e,
   quando houver `event_duration_hours`, "Evento de N horas" e a faixa de início.
2. **Pré-reserva e edição**:
   - Seção **"Mesas e cadeiras"** com os itens de `/furniture` (quantidade de 0 até
     `max_quantity`), separada das bebidas, enviando tudo no mesmo `items` com `item_id`.
   - Mostre no resumo o total estimado de mesas e cadeiras (base + Σ quantidade × por unidade).
   - Campo **"Horário de início"** quando o espaço tem duração (obrigatório nesse caso), com o
     fim calculado ao lado ("termina às 02:00, dia seguinte").
   - Respeite `max_quantity` também nas bebidas.
3. **Detalhe da reserva**: horário (`start_time`–`end_time`), mesas/cadeiras totais e os itens de
   mobiliário (pela `category`) separados das bebidas.
4. **Mensagens**: trate `item_limit` e `invalid_time`; o aviso de contato em até 2 dias úteis.

Como sempre: o valor oficial é o `total` da resposta, e mesas/cadeiras oficiais são
`tables_total`/`chairs_total`. No front, só estimativa.
