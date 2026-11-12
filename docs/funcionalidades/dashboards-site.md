# Dashboards do site (área /painel do site Next.js)

## O que é

Área de **dashboards** dentro do site externo em Next.js (o mesmo das Reservas, Torneios e
Ingressos), pedida em 12/11/2026. Começa com **gráficos simples das vendas de ingressos por
evento**. Depois entram dados do **MultiVendas** e do **MultiClubes** (fase futura, ainda não
feita).

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

## Painel novo (MultiVendas, MultiClubes)

1. Crie o service e as rotas com prefixo próprio (`/api/dashboard/multivendas/*`) no mesmo
   grupo `member_admin`.
2. Acrescente o painel em `DashboardApiController::PANELS`. O menu do site vem de lá.
3. Siga o formato `kpis` + `series`/`rows`, só com números agregados.
4. Atualize este documento e gere o prompt para o agent do site (`docs/prompts/NextJS/`).

## Site (Next.js)

O que o site precisa fazer está no prompt
[dashboards-site-prompt.md](../prompts/NextJS/dashboards-site-prompt.md): área `/painel` com a
sessão do sócio, link visível só para o administrador, dados buscados pelo servidor do Next
(o navegador não alcança O Lara), seletor de evento, cards e quatro gráficos.

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
| `tests/Feature/SiteAccountTest.php` | Perfil na tela, histórico, filtro, permissão própria. |
