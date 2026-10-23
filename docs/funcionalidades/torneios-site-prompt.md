# Prompt de implementação — torneios no site de locação (Next.js)

> Documento de entrega para um agente Claude Code trabalhando **no repositório do
> site de locação (Next.js)**, não neste. Copie a partir de "Início do prompt" até o fim.
>
> **Versão completa (23/10/2026)**, para implementar do zero: nada de torneios existe
> ainda no site. Cobre inscrição e pagamento, times, chaveamento e o lançamento de
> resultados pela equipe do clube. O contrato é o que o Lara expõe hoje (`routes/api.php`,
> prefixo `tournaments`); se a API mudar, este arquivo muda junto. As regras por trás dele
> estão em [torneios.md](torneios.md).

---

## Início do prompt

Você vai implementar **do zero** a área de **torneios** deste site — nada dela existe ainda.
O backend é o Lara (a mesma API das reservas de quadra e dos espaços sociais): o site não
guarda nada de torneio, só mostra e chama a API. Antes de começar, leia como o site já
faz **login do sócio**, **chamadas ao Lara** e **cobrança na eRede** das reservas de
quadra: os torneios usam exatamente os mesmos mecanismos.

O site precisa de:

1. listar torneios e mostrar um torneio (sem login);
2. inscrever o sócio nas categorias e receber o pagamento pela eRede (login do sócio);
3. times: mostrar e, no modo capitão, criar e convidar (login do sócio);
4. mostrar o chaveamento e os resultados quando divulgados (sem login);
5. uma tela para a **equipe do clube lançar resultados**, com o código de lançamento do
   torneio (sem login de sócio).

### Como funciona

- Só **sócios** se inscrevem (titular e dependentes), logados como já acontece nas reservas.
- A inscrição é **individual**: a pessoa escolhe uma ou mais categorias do torneio e paga a
  sua parte. O time se forma depois.
- Cada categoria pode ter regra de **sexo** e **idade** (idade completa no primeiro dia do
  torneio). O Lara confere pelo cadastro do clube; o site só mostra o resultado.
- Inscrever-se **e pagar** só dentro do período de inscrição (`registration_starts_at` a
  `registration_ends_at`). A inscrição nasce **aguardando pagamento** e não segura vaga: as
  **vagas** são de quem paga primeiro.
- O pagamento é pela **eRede**, do mesmo jeito que o site já cobra as reservas de quadra.
  Depois de aprovado na eRede, o site avisa o Lara com o `tid`.
- Desistir pelo site só **antes de pagar**. Inscrição paga: a pessoa fala com a organização.
- O Lara manda os e-mails (inscrição recebida, pagamento confirmado, convite de time,
  cancelamento). O site **não** manda e-mail.
- O chaveamento só aparece depois que a organização o **divulga** no Lara.
- Resultados: a equipe do clube lança no site com um **código** que a organização gera no
  Lara. O que a organização lança no Lara **prevalece** e trava o jogo para o site.

### Ordem sugerida

1. Lista e detalhe do torneio (sem login).
2. Elegibilidade, inscrição, pagamento eRede, minhas inscrições e desistência.
3. Times: mostrar no detalhe e em "minhas inscrições"; modo capitão e convites.
4. Chaveamento público.
5. Tela da equipe para lançar resultados.

Cada etapa funciona sozinha; teste uma antes de passar à seguinte.

### Referência rápida dos endpoints

| Método | Rota | Auth | Seção |
|---|---|---|---|
| GET | `/api/tournaments` (`?place_group_id=`) | T | Inscrição |
| GET | `/api/tournaments/{id}` | T | Inscrição |
| GET | `/api/tournaments/{id}/eligibility` | T + S | Inscrição |
| POST | `/api/tournaments/{id}/registrations` | T + S | Inscrição |
| POST | `/api/tournaments/payment` | T + S | Inscrição |
| GET | `/api/tournaments/my-registrations` | T + S | Inscrição |
| DELETE | `/api/tournaments/registrations/{id}` | T + S | Inscrição |
| GET | `/api/tournaments/my-teams` | T + S | Times |
| POST | `/api/tournaments/categories/{categoryId}/teams` | T + S | Times |
| GET | `/api/tournaments/member-lookup` (`?title=` ou `?cpf=`) | T + S | Times |
| POST | `/api/tournaments/teams/{teamId}/invites` | T + S | Times |
| DELETE | `/api/tournaments/invites/{inviteId}` | T + S | Times |
| GET | `/api/tournaments/my-invites` | T + S | Times |
| POST | `/api/tournaments/invites/{inviteId}/accept` | T + S | Times |
| POST | `/api/tournaments/invites/{inviteId}/decline` | T + S | Times |
| GET | `/api/tournaments/{id}/categories/{categoryId}/bracket` | T | Chaveamento |
| POST | `/api/tournaments/{id}/staff/check` | T + código | Equipe |
| GET | `/api/tournaments/{id}/staff/matches` | T + código | Equipe |
| PUT | `/api/tournaments/{id}/staff/matches/{matchId}/result` | T + código | Equipe |

T = `api_token`; S = JWT do sócio no header `Session`; código = código de lançamento.

### Autenticação

A mesma das rotas de espaços sociais:

- Toda chamada leva `Authorization: Bearer <api_token>`. Chame o Lara do jeito que o site
  já chama (pelo servidor do Next.js): o `api_token` **nunca** vai para o navegador.
- As de sócio levam também o JWT do login no header `Session`. Sem o header: 400; token
  inválido ou vencido: 401; JWT válido de um CPF sem conta: 401 `{"message": "Sessão
  inválida."}` (sem `reason`). O sócio é **sempre** o dono do JWT: nunca mande `member_id`
  ou `cpf` no corpo (são ignorados).
- Erro de validação de campo (corpo malformado) vem no formato padrão do Laravel: 422
  `{ "message": "...", "errors": { "campo": ["..."] } }`, **sem** `reason`. Recusa de regra
  vem com `reason` (tabela de erros mais abaixo).

### Endpoints

#### `GET /api/tournaments` — lista (sem login)

Filtro opcional `?place_group_id=`. Vêm os torneios publicados, em andamento e encerrados
(rascunho e cancelado não aparecem), do mais recente para o mais antigo.

```json
[
  {
    "id": 3,
    "title": "Copa de Verão",
    "description": "…",
    "image_url": "https://…",
    "place_group": { "id": 2, "name": "Beach Tennis" },
    "status": "published",
    "registration_starts_at": "2026-11-01T08:00:00-03:00",
    "registration_ends_at": "2026-11-20T23:59:00-03:00",
    "registration_open": true,
    "starts_on": "2026-11-28",
    "ends_on": "2026-11-29",
    "categories": [
      {
        "id": 10,
        "name": "Feminina B",
        "summary": "Duplas · Feminino · 30+ anos",
        "team_size": 2,
        "gender": "female",
        "min_age": 30,
        "max_age": null,
        "price": 90,
        "max_registrations": 32,
        "paid_registrations": 12,
        "vacancies_left": 20,
        "team_formation": "draw",
        "format": "groups_knockout",
        "sets_to_win": 2
      }
    ]
  }
]
```

- `status`: `published` | `in_progress` | `finished`. Só ofereça inscrição com
  `registration_open: true`.
- `gender`: `male` | `female` | `any`. `max_registrations` e `vacancies_left` nulos = sem
  limite. Use `summary` para descrever a categoria.

#### `GET /api/tournaments/{id}` — detalhe (sem login)

O mesmo objeto, com `rules` (regulamento, texto com quebras de linha). 404 com
`reason: "tournament_not_found"` para rascunho, cancelado ou inexistente.

#### `GET /api/tournaments/{id}/eligibility` — o que o sócio pode escolher (login)

```json
{
  "tournament_id": 3,
  "registration_open": true,
  "categories": [
    { "category_id": 10, "eligible": true, "reason": null, "registration": null },
    { "category_id": 11, "eligible": false, "reason": "gender", "registration": null }
  ]
}
```

- `reason`: `gender` (categoria de outro sexo), `gender_unknown` (cadastro do clube sem
  sexo), `birth_date_unknown`, `too_young`, `too_old`.
- `registration`: a inscrição ativa nessa categoria, se já existir (mesmo formato de
  "minhas inscrições"). Categoria com inscrição ativa não pode ser escolhida de novo.
- 422 `member_not_found`: o login não corresponde a um sócio de título ativo. Mostre
  "Procure a secretaria".

#### `POST /api/tournaments/{id}/registrations` — inscrever (login)

Corpo: `{ "category_ids": [10, 12] }`. Ou entram todas, ou nenhuma.

201:

```json
{
  "registrations": [ { "id": 55, "status": "pending_payment", "amount": 90, "…": "…" } ],
  "total_due": 180,
  "pay_until": "2026-11-20T23:59:00-03:00"
}
```

Categoria de preço zero já volta `paid` e não entra no `total_due`.

#### `POST /api/tournaments/payment` — avisar o pagamento (login)

Depois da aprovação na eRede:

```json
{
  "registration_ids": [55, 56],
  "payment_method": "credit",
  "paid_amount": 180,
  "payment_integration_id": "<tid da eRede>",
  "paid_at": "2026-11-05T14:32:00-03:00"
}
```

- `paid_amount` tem de ser **exatamente** a soma dos `amount` das inscrições. Diferente: 422
  `payment_mismatch`, com `context.expected`.
- 201 na primeira vez. **Reenviar** o mesmo `tid` para as mesmas inscrições devolve 200 com o
  mesmo pagamento: pode repetir com segurança se a resposta se perder. O mesmo `tid` para
  outras inscrições: 409.
- Resposta (201 ou 200):

```json
{
  "message": "Pagamento registrado.",
  "payment": { "id": 7, "paid_amount": 180, "paid_at": "2026-11-05T14:32:00-03:00", "payment_integration_id": "<tid>" },
  "registrations": [ { "id": 55, "status": "paid", "…": "formato de minhas inscrições" } ]
}
```

- `payment_method`: o mesmo valor que o site manda hoje no pagamento das reservas de
  quadra (ex.: `credit`, `debit`).
- 409 `category_full`: a vaga acabou entre a inscrição e o pagamento. **Cobre na eRede só
  depois** de conferir a vaga (releia o torneio) e, se a eRede aprovar e o Lara recusar,
  trate como estorno pelo fluxo que o site já usa nas reservas.
- 422 `registration_closed`: o período acabou.
- **Inscrição feita pelo balcão fora do período** (desde 23/10/2026 o Lara inscreve a
  qualquer momento): ela aparece em *minhas inscrições* como `pending_payment`, mas com o
  torneio em `registration_open: false` o pagamento é recusado. Nesse caso **não mostre o
  botão de pagar**: mostre "Pagamento na secretaria do clube". O mesmo vale para torneio
  reaberto pelo organizador (a situação pode voltar, ex.: de encerrado para em andamento) —
  o site decide sempre pelos campos que o Lara devolve, nunca por uma ordem fixa.

#### `GET /api/tournaments/my-registrations` — minhas inscrições (login)

```json
[
  {
    "id": 55,
    "status": "paid",
    "status_label": "Paga",
    "amount": 90,
    "paid_at": "2026-11-05T14:32:00-03:00",
    "category": { "id": 10, "name": "Feminina B" },
    "tournament": { "id": 3, "title": "Copa de Verão", "starts_on": "2026-11-28", "registration_ends_at": "…" },
    "payment": { "id": 7, "manual": false, "status": "paid" },
    "team": { "id": 4, "name": "As Feras", "status": "complete" }
  }
]
```

Este é o **formato da inscrição** em todas as respostas (inscrever, pagar, desistir,
aceitar convite). `payment` e `team` podem ser `null`.

`status`: `pending_payment` (mostre o botão de pagar até `registration_ends_at`), `paid`,
`withdrawn`, `cancelled`. `payment.manual: true` = pago na secretaria.

#### `DELETE /api/tournaments/registrations/{id}` — desistir (login)

Só inscrição pendente → 200 com a inscrição (`status: "withdrawn"`). Paga: 422
`paid_contact_organizer` ("fale com a organização do torneio"). Inexistente ou de outro
sócio: 404 `not_owner`.

### Erros (todos os endpoints)

Recusa de regra: `{ "message": "...", "reason": "...", "context": { … } }` (`context` só
quando há). Decida o que mostrar pelo `reason`, nunca pelo texto (que pode mudar); a
`message` vem em português e pode ser exibida como está.

| reason | Status | Quando |
|---|---|---|
| `tournament_not_found` | 404 | torneio inexistente, em rascunho ou cancelado |
| `registration_closed` | 422 | fora do período de inscrição (inscrever, pagar, montar time, convidar, aceitar) |
| `member_not_found` | 422 | sem sócio de título ativo para o login ("procure a secretaria") |
| `category_not_found` | 404/422 | categoria que não é do torneio |
| `ineligible` | 422 | sexo ou idade fora da categoria (`context.category_id`, `context.detail` = `gender`, `gender_unknown`, `birth_date_unknown`, `too_young`, `too_old`) |
| `already_registered` | 409 | já inscrito na categoria |
| `category_full` | 409 | sem vaga |
| `invalid_status` | 422 | a inscrição não está na situação certa (ex.: já paga) |
| `not_owner` | 404/422 | inscrição de outro sócio |
| `payment_mismatch` | 422 | valor diferente do total (`context.expected`) |
| `paid_contact_organizer` | 422 | desistir de inscrição paga |
| `wrong_formation` | 422 | ação de capitão numa categoria que não é de capitão |
| `team_not_found` | 404 | time inexistente, desfeito ou de outra categoria |
| `not_captain` | 403 | só o capitão pode |
| `team_full` | 409 | time sem vaga (contando convites pendentes) |
| `already_in_team` | 409 | a pessoa já está num time da categoria, ou já foi convidada |
| `not_registered` | 422 | criar time sem estar inscrito na categoria |
| `invite_not_found` | 404 | convite inexistente, de outra pessoa ou já respondido |
| `bracket_not_published` | 404 | chaveamento ainda não divulgado |
| reasons da equipe | — | ver "Lançamento de resultados" |

Texto sugerido para os motivos de elegibilidade: `gender` "Categoria de outro sexo";
`gender_unknown` / `birth_date_unknown` "Seu cadastro no clube está incompleto: procure a
secretaria"; `too_young` / `too_old` "Fora da faixa de idade da categoria".

### Telas sugeridas (inscrição)

1. **Torneios**: cartões com imagem, nome, modalidade, datas e o selo "inscrições abertas".
2. **Torneio**: descrição, regulamento, categorias (resumo, preço, vagas restantes) e, logado,
   a seleção de categorias pela `eligibility` (as não elegíveis aparecem desabilitadas com o
   motivo em português).
3. **Pagamento**: total, prazo (`pay_until`) e o checkout eRede de sempre.
4. **Minhas inscrições**: situação de cada uma, pagar as pendentes e desistir antes de pagar.

## Times

Cada categoria diz como os times se formam em `team_formation`:

- `organizer` ou `draw`: a organização monta ou sorteia no Lara. O site só **mostra** o time.
- `captain`: o sócio monta pelo site, como abaixo.
- Categoria com `team_size: 1` é individual: cada inscrito pago já é o seu "time".

Um time está `complete` quando tem `team_size` pessoas e **todas pagaram**; senão,
`forming`. Depois do fim das inscrições, só a organização mexe nos times (o site recebe
`registration_closed`).

### Times nos endpoints de consulta

- `GET /api/tournaments/{id}` traz `categories[].teams`:
  `[{ "id", "name", "status", "members": ["Ana Souza", "Carla Dias"] }]`.
- `GET /api/tournaments/my-registrations` traz `team` em cada inscrição:
  `{ "id", "name", "status" }` ou `null`.

### Modo capitão (todos com login)

1. **Criar o time** — `POST /api/tournaments/categories/{categoryId}/teams` com
   `{ "name": "As Feras" }` (opcional, até 60 caracteres; vazio = primeiro nome). 201 com o time. Precisa estar inscrito na
   categoria (`not_registered`) e não estar em outro time (`already_in_team`). Categoria que
   não é de capitão: `wrong_formation`. Volta o time (formato abaixo), com `is_captain: true`.
2. **Achar quem convidar** — `GET /api/tournaments/member-lookup?title=1002` (a família do
   título) ou `?cpf=...`:
   `[{ "ref": "<opaco>", "name": "Bruno Lima", "cpf_masked": "***.***.***-57", "titular": true }]`.
   O CPF nunca volta; use a `ref`. Sem ninguém: `[]`. Mostre a lista para o capitão
   escolher a pessoa (uma matrícula traz a família toda).
3. **Convidar** — `POST /api/tournaments/teams/{teamId}/invites` com `{ "ref": "..." }`. O
   Lara confere sexo e idade do convidado (`ineligible`), se o time tem vaga contando os
   convites pendentes (`team_full`) e se a pessoa já está noutro time ou já foi convidada
   (`already_in_team`). Só o capitão (`not_captain`, 403). O convidado recebe e-mail.
   201 com o convite (formato de `my-invites`).
4. **Retirar convite** — `DELETE /api/tournaments/invites/{inviteId}` (capitão, só pendente).
   200 `{ "message": "Convite cancelado." }`.

`GET /api/tournaments/my-teams` — os times do sócio:

```json
[
  {
    "id": 4, "name": "As Feras", "status": "forming", "status_label": "Em formação",
    "team_size": 2,
    "category": { "id": 10, "name": "Duplas Livre", "team_formation": "captain" },
    "tournament": { "id": 3, "title": "Copa de Verão" },
    "is_captain": true,
    "members": [ { "name": "Ana Souza", "paid": true, "captain": true } ],
    "pending_invites": [ { "id": 9, "name": "Carla Dias" } ]
  }
]
```

`pending_invites` só vem preenchido para o capitão.

### Do lado do convidado

- `GET /api/tournaments/my-invites` — convites pendentes recebidos:
  `[{ "id", "status", "name", "invited_by", "team": { "id", "name", "members" },
  "category": { "id", "name", "price" }, "tournament": { "id", "title", "registration_ends_at" } }]`.
- `POST /api/tournaments/invites/{id}/accept` — entra no time. Se ainda não estava inscrito
  na categoria, a inscrição é criada ali (pode vir `ineligible`, `category_full`,
  `member_not_found`) e ele entra **já**, aguardando pagamento. Resposta:
  `{ "registration": {…, "status": "pending_payment", "team": {…}}, "team": {…} }`. Leve a
  pessoa para pagar como qualquer inscrição pendente.
- `POST /api/tournaments/invites/{id}/decline` — recusa. 200 `{ "message": "Convite recusado." }`.
- Convite de outra pessoa ou já respondido: 404 `invite_not_found`.

### Telas sugeridas (times)

- No detalhe do torneio, os times de cada categoria.
- Em "Minhas inscrições", o time de cada uma; numa categoria de capitão sem time, o botão
  **Criar time**.
- Tela do time (capitão): membros, quem pagou, convites pendentes, **Convidar** (busca por
  matrícula ou CPF, escolha da pessoa) e retirar convite.
- Aviso de **convites recebidos**, com aceitar e recusar.

## Chaveamento

O Lara gera o **chaveamento** de cada categoria (eliminatória simples ou grupos +
mata-mata). O organizador gera, confere e **divulga**; só então o site pode mostrar. Gerar de
novo tira do site até nova divulgação. Os resultados aparecem no mesmo endpoint conforme são
lançados (no Lara ou pela equipe no site): recarregue a página ou atualize a cada minuto
durante o torneio.

### Indicador nas categorias

- Toda categoria (em `GET /api/tournaments` e `GET /api/tournaments/{id}`) traz
  `bracket_published: true|false`. Mostre o link **Chaveamento** só quando for `true`.

### `GET /api/tournaments/{id}/categories/{categoryId}/bracket` — chaveamento (sem login)

Só o `api_token`. Antes de divulgar: 404 `bracket_not_published`; categoria de outro torneio:
404 `category_not_found`; torneio fora do site: 404 `tournament_not_found`.

```json
{
  "tournament_id": 3, "category_id": 10, "category_name": "Feminina B",
  "format": "groups_knockout", "sets_to_win": 2,
  "published_at": "2026-11-25T10:00:00-03:00",
  "stages": [
    {
      "type": "group",
      "groups": [
        {
          "id": 1, "name": "Grupo A", "closed": false,
          "standings": [
            { "position": 1, "final": false, "team": { "id": 4, "name": "As Feras" },
              "played": 0, "wins": 0, "losses": 0,
              "sets_won": 0, "sets_lost": 0, "games_won": 0, "games_lost": 0 }
          ],
          "matches": [ { "…": "formato do jogo abaixo" } ]
        }
      ]
    },
    {
      "type": "knockout",
      "rounds": 2,
      "matches": [ { "…": "formato do jogo abaixo" } ]
    }
  ]
}
```

Na eliminatória simples há uma fase só (`knockout`); em grupos + mata-mata, duas
(`group` e depois `knockout`).

**Jogo:**

```json
{
  "id": 31, "round": 1, "round_label": "Semifinal", "position": 1,
  "third_place": false,
  "status": "pending", "status_label": "A jogar",
  "home": {
    "team": null,
    "source": { "group": "Grupo A", "position": 1, "label": "1º do Grupo A" },
    "sets": null
  },
  "away": {
    "team": { "id": 7, "name": "Ana / Bruno", "members": ["Ana Souza", "Bruno Lima"] },
    "source": null,
    "sets": null
  },
  "winner_team_id": null,
  "sets": [],
  "next_match_id": 33,
  "scheduled_at": "2026-11-28T09:00:00-03:00",
  "location": "Quadra 2"
}
```

Jogo **encerrado** (2 sets a 1, com tie-break no 3º):

```json
{
  "id": 32, "round": 1, "round_label": "Semifinal", "position": 2,
  "third_place": false,
  "status": "finished", "status_label": "Encerrado",
  "home": { "team": { "id": 8, "name": "Carla / Davi", "members": ["Carla Dias", "Davi Rocha"] }, "source": null, "sets": 2 },
  "away": { "team": { "id": 9, "name": "Eva / Fábio", "members": ["Eva Nunes", "Fábio Reis"] }, "source": null, "sets": 1 },
  "winner_team_id": 8,
  "sets": [
    { "number": 1, "home_games": 6, "away_games": 4, "home_tiebreak": null, "away_tiebreak": null },
    { "number": 2, "home_games": 3, "away_games": 6, "home_tiebreak": null, "away_tiebreak": null },
    { "number": 3, "home_games": 7, "away_games": 6, "home_tiebreak": 7, "away_tiebreak": 5 }
  ],
  "next_match_id": 33,
  "scheduled_at": null,
  "location": null
}
```

Mostre como "6-4 3-6 7-6 (7-5)". No W.O. (`status: "walkover"`) os `sets` vêm vazios e
`home.sets`/`away.sets` trazem `sets_to_win` × 0: mostre "W.O.".

- Lado com `team: null` e `source` preenchido: ainda não se sabe quem é ("1º do Grupo A").
  Com os dois nulos: *a definir* (vencedor do jogo anterior).
- `status`: `pending` (A jogar), `finished` (Encerrado), `walkover` (W.O.), `bye` (Passou
  direto — um lado avança sem jogar; mostre discretamente ou esconda).
- `round_label` já vem pronto ("1ª rodada", "Quartas de final", "Semifinal", "Final",
  "Disputa de 3º lugar"); no grupo, "1ª rodada", "2ª rodada"…
- `third_place: true` é o jogo do 3º lugar (mesmo `round` da final).
- `next_match_id` diz para qual jogo o vencedor segue — use para desenhar as linhas da chave.
- Classificação do grupo: já vem na ordem certa; `final: true` (e `closed: true` no grupo)
  quando o organizador já fechou a classificação — só então os classificados aparecem no
  mata-mata.

### Telas sugeridas (chaveamento)

- No detalhe do torneio, por categoria com `bracket_published`, um link **Chaveamento**.
- Grupos: um cartão por grupo com a tabela (J, V, sets, games) e os jogos por rodada.
- Mata-mata: colunas por rodada, ligadas por `next_match_id`; o 3º lugar abaixo da final.
- Em cada jogo: data, hora e local, quando houver.
- Destaque o time do sócio logado, se ele estiver no chaveamento (compare com os times de
  `my-registrations`).

## Lançamento de resultados pela equipe do clube

Uma tela **para a equipe do clube** (não para o sócio) lançar placares durante o torneio. Não
usa o login do sócio: a organização gera no Lara um **código de lançamento** do torneio (8
letras e números, mostrado como `ABCD-EFGH`) e passa para quem vai lançar. O código vale até
o fim do último dia do torneio e pode ser retirado ou trocado a qualquer momento.

Todas as chamadas levam o `api_token` de sempre. O código vai no header
**`Tournament-Code`** (no `check`, no corpo). Maiúsculas, espaços e hífen não importam. Código
errado, vencido ou retirado: **401 `invalid_code`** — volte para a tela do código.

#### `POST /api/tournaments/{id}/staff/check` — entrar

Corpo `{ "code": "ABCD-EFGH" }` (limite de 10 tentativas por minuto). 200:

```json
{ "tournament": { "id": 3, "title": "Copa de Verão", "status": "in_progress" }, "valid_until": "2026-11-29T23:59:59-03:00" }
```

Guarde o código só na sessão do navegador (não em armazenamento permanente).

#### `GET /api/tournaments/{id}/staff/matches` — os jogos

Header `Tournament-Code`. Todas as categorias **com chaveamento gerado** (divulgado ou não):

```json
{
  "tournament": { "id": 3, "title": "Copa de Verão", "status": "in_progress" },
  "categories": [ { "category_id": 10, "category_name": "Feminina B", "format": "…", "sets_to_win": 2, "stages": [ "…" ] } ]
}
```

Cada categoria no mesmo formato do `…/bracket` público e, em cada jogo, três campos a mais:

- `editable`: pode lançar agora (tem os dois times, não é "passa direto", não está travado
  e o grupo não foi fechado);
- `locked`: lançado pela organização no Lara — **mostre o placar, sem editar**;
- `result_origin`: `lara`, `site` ou `null`.

Cada grupo traz `closed: true` quando a organização já fechou a classificação (os jogos do
grupo deixam de ser editáveis).

#### `PUT /api/tournaments/{id}/staff/matches/{matchId}/result` — lançar ou corrigir

Header `Tournament-Code`.

```json
{
  "operator_name": "Carlos (quadra 2)",
  "sets": [
    { "home_games": 6, "away_games": 4 },
    { "home_games": 3, "away_games": 6 },
    { "home_games": 7, "away_games": 6, "home_tiebreak": 7, "away_tiebreak": 5 }
  ]
}
```

ou W.O.: `{ "operator_name": "Carlos", "walkover_winner_team_id": 7 }`.

- `operator_name` é obrigatório: é quem aparece no histórico do jogo. Peça o nome uma vez
  ao entrar e reaproveite.
- Regras do placar (o Lara confere; mostre a mensagem dele): todo set tem vencedor (sem
  empate), o vencedor do jogo chega a `sets_to_win` da categoria, não há set depois de
  decidido, tie-break é opcional e vai dos dois lados.
- 200 com o jogo atualizado (formato de jogo, com `editable`/`locked`; o `source` dos lados
  pode vir sem o nome do grupo). **Recarregue** `…/staff/matches` depois: o vencedor já
  aparece no jogo seguinte.
- Campos malformados (sem `operator_name`, games fora de 0–99): 422 de validação do Laravel
  (`errors`).
- Pode corrigir o que o próprio site lançou. Recusas:

| reason | Quando |
|---|---|
| `invalid_score` | placar inválido; `context.detail`: `no_sets`, `tied_set`, `undecided`, `extra_set`, `tiebreak`, `games`, `walkover_winner` |
| `match_locked` (409) | a organização lançou no Lara: só ela altera |
| `match_not_ready` | o jogo ainda não tem os dois times |
| `next_match_played` (409) | mudaria o vencedor, mas o jogo seguinte já tem resultado |
| `group_closed` (409) | o grupo já foi fechado pela organização |
| `results_closed` | torneio que não aceita resultado pelo site (só publicado ou em andamento) |
| `match_not_found` (404) | jogo de outro torneio |
| `invalid_code` (401) | código errado, vencido ou retirado (também torneio inexistente) |

### Telas sugeridas (equipe)

- **Entrar**: código + "seu nome", em rota própria (ex.: `/torneios/{id}/equipe`), fora do
  login do sócio.
- **Jogos**: filtro por categoria e "a jogar"; cada jogo com os dois times, data e local e o
  botão **Lançar** (ou o placar travado, com o selo "lançado pela organização").
- **Lançar**: uma linha por set (até `2 × sets_to_win − 1`), tie-break opcional, opção W.O.
  Mostre a mensagem do Lara quando recusar.
- Pensada para **celular**: é usada na beira da quadra.

## Pronto quando

- [ ] Lista e detalhe mostram só o que a API manda (rascunho e cancelado nunca aparecem).
- [ ] O sócio logado vê quais categorias pode escolher, inscreve-se em várias de uma vez,
      paga na eRede e o Lara registra (inclusive no reenvio do mesmo `tid`).
- [ ] "Minhas inscrições" mostra situação, time, pagar pendente e desistir antes de pagar.
- [ ] Modo capitão: criar time, buscar por matrícula ou CPF, convidar, retirar convite;
      convidado aceita (e paga) ou recusa.
- [ ] Chaveamento público só aparece com `bracket_published: true`, com grupos, mata-mata,
      placares e W.O.
- [ ] Tela da equipe: código + nome, lista de jogos, lançar e corrigir placar e W.O.,
      jogo travado pelo Lara sem edição, mensagens de recusa do Lara.
- [ ] Nenhuma chamada ao Lara sai do navegador com o `api_token`.
- [ ] Toda recusa é decidida pelo `reason`, nunca pelo texto.
