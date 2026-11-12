# Dashboards do site (área /painel do site Next.js)

## O que é

Área de **dashboards** dentro do site externo em Next.js (o mesmo das Reservas, Torneios e
Ingressos), pedida em 12/11/2026. Painéis:

- **Ingressos**: vendas de ingressos por evento;
- **Vendas** (MultiVendas, 12/11/2026): o Grafana "Vendas Hoje" levado para o site, com
  período de/até e filtro de lojas;
- **MultiClubes**: fase futura, ainda não feita.

O Lara é o backend: calcula os números e responde pela API `/api/dashboard/*`. O site
mostra os gráficos.

## Quem acessa

- O login é **o mesmo do sócio no site**: CPF e senha, `POST /api/login`, token no header
  `Session`. Não há login de funcionário nem senha separada.
- A conta tem um **perfil no site** (`members.site_role`): `usuario` (padrão, todo sócio) ou
  `administrador`. **Só o administrador** abre os dashboards.
- O perfil é definido no Lara, em **Reservas › Contas do site** → conta → **Perfil no site**,
  por quem tem a permissão `reservas.contas-site.perfil` (ver
  [contas-do-site.md](contas-do-site.md)).
- Com dois níveis, **todo administrador vê todos os painéis**. Se um dia alguém precisar ver
  só um deles, troque o perfil por uma lista de painéis por conta. O login não muda.
- Sem restrição de rede: o administrador entra de qualquer lugar.

## Como o acesso é conferido

Grupo de rotas dentro de `api_token` + `login_token`, com o middleware **`member_admin`**
(`App\Http\Middleware\EnsureMemberSiteAdmin`):

1. o `login_token` valida o JWT do sócio e deixa o payload em `user` (o `username` é o CPF);
2. o `member_admin` carrega a conta que o login usa para esse CPF
   (`Member::loginAccount`, a ativa mais antiga) e exige `isSiteAdmin()`: perfil
   `administrador` e conta não desativada;
3. senão, **403** `{"error": "Sem acesso aos dashboards."}`.

O perfil **não vai dentro do token**. Ele é lido do banco a cada requisição. Por isso:

- rebaixar em Contas do site corta o acesso na hora, com o mesmo token;
- os tokens emitidos antes de existir o perfil continuam valendo.

Sem `Session`: **400** (`Not Found Login Token`, resposta do `login_token` de sempre). Token
inválido ou vencido: **401**. Sem o `api_token`: **401**. Limite: 120 chamadas por minuto por
rota.

O `POST /api/login` já devolve `user.site_role`, para o site decidir se mostra o link do
painel. Quem protege é a API, não o link.

## Endpoints

| Método e rota | O que devolve |
|---|---|
| `GET /api/dashboard/access` | `{admin: true, member: {name, title}, panels: [{key, label, path}]}`. Monta o menu do painel. |
| `GET /api/dashboard/tickets/events` | `{default_event_id, events: [{id, title, venue, starts_at, ends_at, status, status_label, sold, net}]}` |
| `GET /api/dashboard/tickets/events/{id}/summary` | `{event, kpis: [{key, label, value, format}], generated_at}` |
| `GET /api/dashboard/tickets/events/{id}/sales-by-day` | `{event, series: [{date, tickets, amount}]}` |
| `GET /api/dashboard/tickets/events/{id}/by-lot` | `{event, rows: [{type, lot, capacity, member, non_member, courtesies, total, net}]}` |
| `GET /api/dashboard/tickets/events/{id}/by-channel` | `{event, origin: [{key, label, count, amount}], buyer: [...]}` |
| `GET /api/dashboard/tickets/events/{id}/by-method` | `{event, rows: [{method, count, paid, refunded, received}]}` |

Regras dos números (`App\Services\Ticket\TicketDashboardService`, na régua do relatório
financeiro, `TicketFinanceService`):

- **Eventos:** entram publicados, encerrados e cancelados. Rascunho fica de fora e responde
  **404**, igual a evento inexistente ou apagado. A lista vai do mais novo para o mais antigo.
- **Evento que abre primeiro** (`default_event_id`): o publicado mais próximo de acontecer,
  contando o que já começou e ainda não acabou. Se não houver nenhum à frente, o publicado
  mais recente. Se não houver publicado, o primeiro da lista.
- **Vendido** = ingresso válido ou usado. **Cortesia** conta como ingresso, com valor zero.
  Cancelado fica fora das vendas e aparece só nos cards (`cancelled`, `refunded`).
- **Cards** (`kpis`): `sold`, `used` (check-in), `courtesies`, `cancelled` (`format: int`);
  `face`, `discount`, `net`, `received`, `refunded` (`format: money`); com capacidade no
  evento, também `capacity` (`int`) e `occupancy` (`percent`, vendidos ÷ capacidade).
- **Vendas por dia:** o dia é o do pagamento do pedido (`paid_at`). Cortesia, sem pagamento,
  conta pela emissão. Os dias sem venda entre o primeiro e o último vêm com zero. A série tem
  no máximo 400 dias (os mais recentes).
- **Por lote:** todo lote de todo tipo aparece, mesmo sem venda. `capacity` nulo = lote sem
  limite. Ingresso sem lote cai numa linha "Sem lote".
- **Por canal:** `origin` = `site`, `counter` (secretaria), `courtesy`. `buyer` = `member` e
  `non_member`, pelo preço cobrado no ingresso (`price_kind`).
- **Por forma de pagamento:** eRede e cada forma de baixa manual. `received` = pago menos
  estornado.
- **Nenhum dado pessoal:** nada de nome, CPF, e-mail ou telefone de comprador ou titular. Só
  números agregados.

## Painel Vendas (MultiVendas)

Reproduz o dashboard do Grafana **"Vendas Hoje"**, que lê a view **`vw_DashboardVendas`**.
Ela fica no servidor do MultiVendas/MultiClubes, no banco `FUNCSIDERURG` **de lá**. Não
confunda com o `FUNCSIDERURG` do Questor (`questor_sqlsrv`, outro servidor), onde a view não
existe.

**Conexão** `vendas_sqlsrv` (`config/database.php`). Por padrão usa host, porta, usuário e
senha do MultiClubes (`DB_MC_*`) e o banco `FUNCSIDERURG`. As variáveis `DB_VENDAS_HOST`,
`DB_VENDAS_PORT`, `DB_VENDAS_DATABASE`, `DB_VENDAS_USERNAME` e `DB_VENDAS_PASSWORD`
sobrescrevem cada valor (vazia = usa a do MultiClubes). Basta um usuário só leitura, como o
do Grafana.

**Conferir no servidor:** `php artisan dashboards:vendas-check [--data=AAAA-MM-DD]`. É só
leitura. Mostra o banco, se a view existe com as colunas do painel, as lojas e o total do
dia. Em 12/11/2026 a máquina de desenvolvimento não alcançava esse servidor (login do
`lara` recusado), então rode o comando no servidor do Lara depois do deploy.

**Colunas usadas:** `Data_Venda` (date), `Data_Hora_Venda`, `ID_Venda`, `Loja`, `Produto`,
`Valor_Liquido`, `Metodos_Pagamento`, `Vendedor`. Cada linha da view é um item vendido; uma
venda é um `ID_Venda` distinto.

| Método e rota | O que devolve |
|---|---|
| `GET /api/dashboard/sales/stores` | `{stores: ["Gastrobar Piscina", …]}`: as lojas do filtro (`SELECT DISTINCT Loja`) |
| `GET /api/dashboard/sales?from=&to=&stores[]=` | Todos os blocos do painel numa resposta (abaixo) |

Filtro:

- `from` e `to` vêm no formato `AAAA-MM-DD` e são inclusivos, sobre `Data_Venda`. Sem os
  dois, o período é **hoje**. Sem `to`, vai até hoje (ou até o próprio `from`, se ele
  estiver no futuro).
- O período tem no máximo 366 dias. `to` antes de `from` responde **422**.
- `stores[]`: nomes exatos de `/stores` (também aceita `stores=a,b`). Vazio = todas. Loja
  desconhecida responde **422**.
- **O filtro de loja vale para todos os gráficos.** No Grafana, Vendedores ignorava o filtro.
  Aqui não ignora (decisão de 12/11/2026).

Resposta de `/api/dashboard/sales`, painel a painel do Grafana:

| Campo | Grafana | Regra |
|---|---|---|
| `kpis` | Total Faturamento, Ticket Médio, Vendas | `net` = `SUM(Valor_Liquido)`; `sales` = `COUNT(DISTINCT ID_Venda)`; `average_ticket` = `net ÷ sales` |
| `by_store` | Vendas por Loja | soma por loja; as lojas com "Gastrobar" no nome viram uma barra só, "Gastrobar" (`STORE_GROUPS`) |
| `top_products` | Produtos mais vendidos | top 10 por valor |
| `by_hour` | Venda por Hora | `DATEPART(HOUR, Data_Hora_Venda)`, somando todos os dias do período; da primeira à última hora com venda, com zero entre elas |
| `by_day` | (novo, por causa do período) | todos os dias do período, com `amount` e `sales`, zero onde não vendeu |
| `payment_methods` | Métodos de Pagamento | soma por `Metodos_Pagamento`; vazio = "Não informado" |
| `sellers` | Vendedores | soma por vendedor; vazio = "Não Identificado" (como o `ISNULL` do Grafana) |

Também vêm `period` (`from`, `to`, `days`), `stores` (`selected`, `all`) e `generated_at`.
As listas vêm do maior para o menor valor. Os rótulos vazios viram "Sem loja" e "Sem nome".

- **Cache:** 60 s quando o período inclui hoje, 10 min quando está todo no passado. A lista
  de lojas também fica 10 min. O servidor é o do caixa, e o painel não pode sobrecarregar a
  view.
- **MultiVendas fora do ar:** **503** `{"error": "O MultiVendas não respondeu. Tente de novo
  em instantes."}`. O erro do driver (que traz host e usuário) vai só para o log.
- **Dado pessoal:** os vendedores aparecem pelo nome, porque o painel é só do sócio
  administrador. Nenhum dado de cliente sai daqui.
- **SQL:** só SELECT, pelo query builder, com período e lojas sempre como binding
  (`SalesDashboardRepository`). O teste `tests/Unit/SalesDashboardSqlTest.php` confere o SQL
  de SQL Server sem conectar.

## Painel novo (MultiClubes)

1. Crie o service e as rotas com prefixo próprio (`/api/dashboard/<painel>/*`) no mesmo
   grupo `member_admin`.
2. Acrescente o painel em `DashboardApiController::PANELS`. O menu do site vem de lá.
3. Siga o formato `kpis` + `series`/`rows`, só com números agregados.
4. Atualize este documento e gere o prompt para o agent do site (`docs/prompts/NextJS/`).

## Site (Next.js)

O que o site precisa fazer está no prompt
[dashboards-site-prompt.md](../prompts/NextJS/dashboards-site-prompt.md): área `/painel` com a
sessão do sócio, link visível só para o administrador, dados buscados pelo servidor do Next
(o navegador não alcança O Lara), seletor de evento, cards e quatro gráficos. O painel Vendas
está em [dashboards-vendas-prompt.md](../prompts/NextJS/dashboards-vendas-prompt.md).

## Peças

| Arquivo | Papel |
|---|---|
| `app/Http/Middleware/EnsureMemberSiteAdmin.php` (alias `member_admin`) | Exige o perfil administrador, relido do banco. |
| `app/Http/Controllers/Api/DashboardApiController.php` | Rotas `api.dashboard.*`, `PANELS`. |
| `app/Services/Ticket/TicketDashboardService.php` | Números dos ingressos. |
| `app/Models/Member.php` | `ROLE_*`, `isSiteAdmin()`, `loginAccount()`. |
| migration `2026_11_12_100000_add_site_role_to_members` | Coluna `site_role` (idempotente). |
| `app/Services/Members/SiteAccountService.php` (`setRole`), `SiteAccountController::role` | Troca do perfil em Contas do site. |
| `tests/Feature/Dashboards/SiteDashboardApiTest.php` | Acesso (perfil, rebaixar com o mesmo token, conta desativada) e números. |
| `app/Http/Controllers/Api/SalesDashboardApiController.php` | Rotas `api.dashboard.sales.*`. |
| `app/Services/MultiVendas/SalesDashboardService.php`, `SalesFilter`, `SalesDashboardUnavailable` | Filtro, cache, agrupamentos e forma da resposta do painel Vendas. |
| `app/Services/MultiVendas/SalesDashboardRepository.php` | Consultas à `vw_DashboardVendas` (conexão `vendas_sqlsrv`). |
| `app/Console/Commands/DashboardSalesCheck.php` | `dashboards:vendas-check`. |
| `tests/Unit/SalesDashboardSqlTest.php`, `tests/Feature/Dashboards/SalesDashboardApiTest.php` | SQL sem conectar; API com a view em memória (acesso, filtro, gráficos, cache, 503). |
| `tests/Feature/SiteAccountTest.php` | Perfil na tela, histórico, filtro, permissão própria. |
