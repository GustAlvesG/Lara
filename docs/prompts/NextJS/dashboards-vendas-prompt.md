# Prompt para o agent do site Next.js: painel Vendas (MultiVendas) em /painel

**Sistema externo:** NextJS (o site das Reservas, Torneios e Ingressos)

**Antes, leia** [dashboards-site-prompt.md](dashboards-site-prompt.md). A área `/painel`, o login, o perfil administrador, a proteção e a tabela de erros vêm de lá e continuam valendo. Este prompt acrescenta um painel.

## O que mudou no Lara (12/11/2026)

O menu do painel ganhou o painel **Vendas**. Ele reproduz no site o dashboard do Grafana **"Vendas Hoje"**, com os números das lojas do clube que saem do MultiVendas. Mudam duas coisas em relação ao Grafana:

- **período de/até** no lugar de "só hoje";
- **filtro de lojas que vale para todos os gráficos**, inclusive Vendedores.

`GET /api/dashboard/access` agora devolve:

```json
{ "admin": true, "member": { "name": "…", "title": "…" },
  "panels": [ { "key": "ingressos", "label": "Ingressos", "path": "tickets" },
              { "key": "vendas", "label": "Vendas", "path": "sales" } ] }
```

## Regra principal (a mesma de sempre)

O navegador não alcança O Lara. Tudo passa pelo servidor do Next, com o `api_token` e o `Session` do sócio. Os parâmetros do filtro (datas e lojas) ficam na URL da página do site, e o servidor do Next os repassa ao Lara.

## Endpoints (`GET`, com `api_token` + `Session`)

Os erros são os do prompt anterior (400, 401, 403, 429), com dois a mais:

| Status | Quando | O que o site faz |
|---|---|---|
| 422 `{"message": "…", "errors": {"from"\|"to"\|"stores": ["…"]}}` | data inválida, `to` antes de `from`, mais de 366 dias, loja desconhecida | mostrar a mensagem do Lara junto do filtro, sem perder os gráficos anteriores |
| 503 `{"error": "O MultiVendas não respondeu. Tente de novo em instantes."}` | o servidor do MultiVendas está fora do ar | aviso no lugar dos gráficos, com botão "Tentar de novo" |

### `GET /api/dashboard/sales/stores`

```json
{ "stores": ["Gastrobar Piscina", "Gastrobar Sede", "Lanchonete"] }
```

É a lista do filtro, já em ordem alfabética. Use os nomes **exatamente** como vêm.

### `GET /api/dashboard/sales?from=AAAA-MM-DD&to=AAAA-MM-DD&stores[]=Loja%20A&stores[]=Loja%20B`

- `from` e `to` são inclusivos. Sem os dois, o período é **hoje**. Sem `to`, vai até hoje.
- Período máximo de 366 dias.
- `stores[]` vazio ou ausente = **todas as lojas**.

```json
{
  "period": { "from": "2026-11-10", "to": "2026-11-12", "days": 3 },
  "stores": { "selected": ["Lanchonete"], "all": false },
  "kpis": [
    { "key": "net", "label": "Faturamento", "value": 1500.5, "format": "money" },
    { "key": "average_ticket", "label": "Ticket médio", "value": 50.02, "format": "money" },
    { "key": "sales", "label": "Vendas", "value": 30, "format": "int" }
  ],
  "by_store": [ { "label": "Gastrobar", "amount": 1200.0 }, { "label": "Lanchonete", "amount": 300.5 } ],
  "top_products": [ { "label": "Chopp", "amount": 800.0 } ],
  "by_hour": [ { "hour": 11, "amount": 100.0 }, { "hour": 12, "amount": 0.0 } ],
  "by_day": [ { "date": "2026-11-10", "amount": 1000.0, "sales": 20 } ],
  "payment_methods": [ { "label": "Pix", "amount": 900.0 } ],
  "sellers": [ { "label": "Maria", "amount": 1300.5 } ],
  "generated_at": "2026-11-12T15:00:00-03:00"
}
```

- As listas já vêm **do maior para o menor** valor. Não reordene.
- `by_store`: as lojas com "Gastrobar" no nome chegam somadas numa barra "Gastrobar", como no Grafana. O filtro continua usando as lojas separadas.
- `by_hour`: vai da primeira à última hora com venda, sem buraco. Num período de vários dias, cada hora é a soma de todos os dias.
- `by_day`: todos os dias do período, com zero onde não houve venda.
- Os números ficam em cache no Lara por 60 s quando o período inclui hoje, e por 10 min quando está todo no passado. Atualizar mais vezes que isso não muda nada.

## O que fazer no site

1. **Menu.** O painel aparece sozinho, porque vem em `panels`. A rota é `/painel/vendas`.
2. **Filtro no topo da página.**
   - **Período** com atalhos "Hoje" (o padrão), "Ontem", "7 dias", "Este mês" e "Personalizado" (de/até, com data máxima hoje e no máximo 366 dias).
   - **Lojas**: seleção múltipla com a lista de `/stores` e a opção "Todas" (não manda `stores[]`).
   - Guarde o filtro na URL (`?de=2026-11-10&ate=2026-11-12&lojas=Lanchonete,Gastrobar%20Sede`) e repasse ao Lara como `from`, `to` e `stores[]`.
3. **Cards (KPIs).** Os três `kpis` na ordem recebida, com o `label` do Lara:
   - `money`: `R$ 1.234,56`;
   - `int`: `1.234`.
4. **Gráficos.** O layout segue o Grafana. Use Recharts ou a biblioteca que o painel de Ingressos já usa.

   | Bloco | Gráfico |
   |---|---|
   | **Vendas por loja** (`by_store`) | barras **horizontais**, valor em R$ na ponta |
   | **Vendas por dia** (`by_day`) | linha ou área de `amount`, com `sales` no tooltip. **Mostre só quando `period.days > 1`** (em um dia é um ponto só) |
   | **Produtos mais vendidos** (`top_products`) | barras horizontais (top 10) |
   | **Venda por hora** (`by_hour`) | colunas, eixo X "08h, 09h…", valor em R$ |
   | **Métodos de pagamento** (`payment_methods`) | pizza ou rosca, com percentual no rótulo. Até 6 fatias; o resto vira "Outros", somado no site |
   | **Vendedores** (`sellers`) | barras com o valor sempre visível |

5. **Distribuição na tela.** No desktop, cards em 3 colunas; loja e dia em largura total; produtos em largura total; hora e pagamento lado a lado; vendedores em largura total. No celular, uma coluna, sem rolagem horizontal da página. Barras com muitos itens rolam dentro do próprio cartão.
6. **Atualização.** Mostre "Atualizado às HH:MM" (de `generated_at`) e um botão **Atualizar**. Se o período inclui hoje, você pode atualizar sozinho a cada 60 s **só com a aba visível** (`document.visibilityState`). Período todo no passado não se atualiza sozinho.
7. **Estados.**
   - Carregando: skeleton.
   - Sem vendas no período (`sales` = 0): "Nenhuma venda neste período", sem gráficos vazios.
   - 503: aviso com "Tentar de novo".
   - 422: mensagem junto do filtro.
8. **Busca dos dados.** As chamadas saem do servidor com `cache: 'no-store'`, porque o cache é do Lara. `/stores` muda pouco e pode ter `revalidate` de 10 min.

## O que não fazer

- Não calcule faturamento, ticket médio nem vendas no site: use os números do Lara (venda = `ID_Venda` distinto, e isso só O Lara sabe).
- Não chame O Lara nem o MultiVendas pelo navegador.
- Não mostre o painel para quem não é administrador. Quem decide é a API (403).
- Não guarde os números no navegador (`localStorage`). São dados financeiros do clube.
