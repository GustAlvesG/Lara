# Prompt para o agent do site Next.js: área de dashboards (/painel) com as vendas de ingressos

**Sistema externo:** NextJS (o site das Reservas, Torneios e Ingressos)

## O que mudou no Lara (12/11/2026)

O Lara passou a ter **dashboards** para o site, começando pelas **vendas de ingressos por evento**. Mais adiante entram dados do MultiVendas e do MultiClubes, sem mudar o login nem a estrutura descrita aqui.

- O acesso usa **o mesmo login do sócio** que o site já tem: CPF e senha, `POST /api/login`, token no header `Session`. Não crie outro login.
- A conta do sócio agora tem um **perfil**: `usuario` (padrão) ou `administrador`. **Só o administrador** abre os dashboards. O perfil é definido no Lara (Reservas › Contas do site), nunca pelo site.
- A resposta do `POST /api/login` traz `user.site_role` (`"usuario"` ou `"administrador"`). Conta criada pelo `/api/register` pode vir **sem** o campo: trate a ausência como `usuario`.

## Regra principal: o navegador não acessa O Lara

O navegador de quem usa o site **não alcança o endereço do Lara**. Todas as chamadas a `/api/dashboard/*` partem do **servidor do Next** (Server Components, Route Handlers ou Server Actions), com o `api_token` (`Authorization: Bearer …`, como o resto do site) e o `Session` do sócio logado. Nunca exponha o `api_token` ao navegador.

## Endpoints (todos `GET`, com `api_token` + `Session`)

Respostas de erro, iguais em todos:

| Status | Quando | O que o site faz |
|---|---|---|
| 400 | sem `Session` | mandar para o login |
| 401 | `Session` inválido ou vencido (o token vence à meia-noite), ou sem `api_token` | mandar para o login e voltar para `/painel` depois |
| 403 `{"error":"Sem acesso aos dashboards."}` | o sócio não é administrador (ou foi rebaixado agora) | tela "Você não tem acesso aos dashboards" com link para a home |
| 404 | evento inexistente, rascunho ou apagado | "Evento não encontrado" e voltar ao seletor |
| 429 | mais de 120 chamadas por minuto na rota | "Muitas atualizações; tente em instantes" |

O perfil é conferido **a cada chamada**. Um administrador rebaixado recebe 403 já na próxima requisição, com o mesmo token. Por isso não guarde "é admin" em cache além da requisição atual.

### `GET /api/dashboard/access`

```json
{ "admin": true, "member": { "name": "Ana Souza", "title": "1001" },
  "panels": [ { "key": "ingressos", "label": "Ingressos", "path": "tickets" } ] }
```

Monta o menu do painel a partir de `panels`, na ordem recebida. Quando O Lara acrescentar o MultiVendas e o MultiClubes, eles aparecem aqui. Painel com `key` que o site ainda não conhece: não mostre.

### `GET /api/dashboard/tickets/events`

```json
{ "default_event_id": 12,
  "events": [ { "id": 12, "title": "Baile de Carnaval", "venue": "Salão Nobre",
                "starts_at": "2026-12-02T22:00:00-03:00", "ends_at": "2026-12-03T04:00:00-03:00",
                "status": "published", "status_label": "Publicado", "sold": 3, "net": 190.0 } ] }
```

Vêm eventos publicados, encerrados e cancelados (sem rascunho), do mais novo para o mais antigo. `default_event_id` é o evento que abre primeiro, sem filtro de datas: o publicado mais próximo. Pode ser `null` quando não há evento.

### `GET /api/dashboard/tickets/events/{id}/summary`

```json
{ "event": { "id": 12, "title": "…", "venue": "…", "starts_at": "…", "ends_at": "…", "status": "published", "status_label": "Publicado" },
  "kpis": [
    { "key": "sold", "label": "Ingressos vendidos", "value": 3, "format": "int" },
    { "key": "net", "label": "Valor líquido", "value": 190.0, "format": "money" },
    { "key": "occupancy", "label": "Ocupação", "value": 30.0, "format": "percent" }
  ],
  "generated_at": "2026-11-12T17:10:00-03:00" }
```

Mostre os `kpis` na ordem recebida e use o `label` do Lara. O `format` diz como mostrar o número:

- `int`: `1.234`;
- `money`: `R$ 1.234,56`;
- `percent`: `30,0%`.

`capacity` e `occupancy` só vêm quando o evento tem capacidade. As chaves possíveis hoje são `sold`, `used`, `courtesies`, `cancelled`, `face`, `discount`, `net`, `received`, `refunded`, `capacity` e `occupancy`.

### `GET /api/dashboard/tickets/events/{id}/sales-by-day`

```json
{ "event": { … }, "series": [ { "date": "2026-11-10", "tickets": 1, "amount": 90.0 },
                              { "date": "2026-11-11", "tickets": 0, "amount": 0.0 } ] }
```

A série já vem com os dias sem venda zerados, no máximo 400 dias. Pode vir `[]`, e nesse caso mostre "Nenhuma venda ainda".

### `GET /api/dashboard/tickets/events/{id}/by-lot`

```json
{ "event": { … }, "rows": [ { "type": "Pista", "lot": "1º lote", "capacity": 100,
  "member": 0, "non_member": 1, "courtesies": 1, "total": 2, "net": 100.0 } ] }
```

`capacity: null` = lote sem limite.

### `GET /api/dashboard/tickets/events/{id}/by-channel`

```json
{ "event": { … },
  "origin": [ { "key": "site", "label": "Site", "count": 1, "amount": 90.0 },
              { "key": "counter", "label": "Secretaria", "count": 1, "amount": 100.0 },
              { "key": "courtesy", "label": "Cortesia", "count": 1, "amount": 0.0 } ],
  "buyer":  [ { "key": "member", "label": "Sócio", "count": 0, "amount": 0.0 },
              { "key": "non_member", "label": "Não sócio", "count": 3, "amount": 190.0 } ] }
```

### `GET /api/dashboard/tickets/events/{id}/by-method`

```json
{ "event": { … }, "rows": [ { "method": "eRede (site)", "count": 1, "paid": 180.0, "refunded": 90.0, "received": 90.0 },
                            { "method": "Dinheiro", "count": 1, "paid": 100.0, "refunded": 0.0, "received": 100.0 } ] }
```

## O que fazer no site

1. **Área separada `/painel`.** Use layout próprio (cabeçalho "Painel", menu lateral ou abas vindo de `panels`) e `robots: noindex, nofollow`, separado da área do sócio. A sessão é a do sócio que o site já guarda.
2. **Proteção.** Proteja `/painel/*` no `middleware.ts` (ou no layout do servidor):
   - sem sessão, vá ao login de sempre e volte para `/painel` depois de entrar;
   - com sessão, chame `GET /api/dashboard/access` no servidor e aplique a tabela de erros acima.
3. **Link para o painel.** Mostre "Painel" no menu do usuário logado só quando `site_role === "administrador"`. Esse link é só conveniência: quem protege é a API.
4. **`/painel` (início).** Redirecione para o primeiro painel de `panels` (hoje `/painel/ingressos`).
5. **`/painel/ingressos`.**
   - **Seletor de evento** (lista de `/events`, mostrando título, data e `status_label`), aberto em `default_event_id`. Coloque o evento escolhido na URL (`?evento=12`) para dar para compartilhar e recarregar.
   - **Cards** com os `kpis` do `/summary`.
   - **Quatro gráficos** (Recharts, ou a biblioteca de gráficos que o projeto já usar):
     - **Vendas por dia**: linha ou área com `tickets`, e `amount` em um segundo eixo ou num seletor "Quantidade / Valor";
     - **Por lote**: barras empilhadas `member` / `non_member` / `courtesies` por `type · lot`, com a `capacity` como referência quando houver;
     - **Por canal**: rosca de `origin` e rosca de `buyer`;
     - **Por forma de pagamento**: barras de `received`, com `refunded` no tooltip.
   - **Atualização.** Mostre "Atualizado às HH:MM" (de `generated_at`) e um botão **Atualizar** que busca de novo. Não atualize sozinho em laço.
   - **Estados.** Carregando com skeleton; sem evento ("Nenhum evento com vendas"); gráfico vazio com mensagem curta, nunca um eixo sem nada.
6. **Celular.** Os cards ficam em 2 colunas e os gráficos em 1 coluna com largura total, sem rolagem horizontal da página.
7. **Busca dos dados.** As 5 chamadas do evento podem sair em paralelo (`Promise.all`) no servidor, com `cache: 'no-store'`, porque o número tem que ser o de agora.

## O que não fazer

- Não crie login, senha nem cadastro de administrador no site. O perfil é dado no Lara.
- Não chame O Lara pelo navegador e não coloque o `api_token` ou o endereço do Lara no HTML.
- Não guarde o perfil em cookie ou em `localStorage` como prova de acesso. A prova é a resposta da API a cada página.
- Não peça nem mostre dado de comprador: a API não devolve nome, CPF ou e-mail, e o painel é só de números.
