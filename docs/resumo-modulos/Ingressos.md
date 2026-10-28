# Ingressos — resumo para quem vai mexer

Documentação completa: [ingressos.md](../funcionalidades/ingressos.md) (regras decididas,
modelagem, API e fases). Guia: [guias/ingressos.md](../guias/ingressos.md).
Prompts: site [ingressos-site-prompt.md](../funcionalidades/ingressos-site-prompt.md) e
portaria [ingressos-prompt-portaria.md](../ingressos-prompt-portaria.md).

## Em uma frase

O Lara é o backend da venda de ingressos de eventos no **site de locação** (o mesmo Next.js
das reservas e dos torneios): evento → tipos → lotes (quantidade, janela, preço sócio e não
sócio) e cupons; o sócio (titular ou dependente) compra com o login para as pessoas do título
dele, o não sócio compra sem conta para várias pessoas; evento de vários dias com diária
(tipo preso a um dia) e, se cadastrado, passaporte (tipo sem dia); **lugar marcado** (mesa,
bistrô, camarote escolhidos num mapa, sem venda dupla; o ingresso do lugar não é entrada);
**mesa cativa** (guardada para uma matrícula até N dias antes); pagamento eRede; o Lara
também emite pago por fora e cortesia (até sem CPF, em quantidade), imprime o ingresso
físico na térmica de 80 mm, guarda o histórico de quem fez o quê, faz o relatório de caixa,
o check-in (tela própria e Monitor de Acesso) e o financeiro. Ingresso nominal com código
`ING-` e QR (a cortesia sem CPF entra só pelo código).

## Onde está

F1–F17 feitas em 27–28/10/2026 (ver a tabela de fases em `ingressos.md`). F13 = lugar marcado;
F14 = histórico; F15 = caixa; F16 = térmica e cortesia sem CPF; F17 = mesa cativa.

## Peças

| Arquivo | Papel |
|---|---|
| `database/migrations/2026_10_27_100000_create_ticket_tables.php` | As 7 tabelas (idempotente, FKs com nome curto). |
| `database/migrations/2026_10_27_110000_add_ticket_id_to_company_access_logs.php` | `company_access_logs.ticket_id` (monitor). |
| `database/migrations/2026_10_27_120000_add_ticket_event_days.php` | `ticket_event_days`, `ticket_types.ticket_event_day_id`, `ticket_checkins` (entrada por dia). |
| `database/migrations/2026_10_28_100000_make_ticket_lot_quantity_nullable.php` | `ticket_lots.quantity` nulo = lote sem limite. |
| `database/migrations/2026_10_28_120000_create_ticket_maps.php` | Lugar marcado: `ticket_maps`, `ticket_seats`, `ticket_seat_claims` (único lugar + dia), `ticket_types.ticket_map_id`/`seat_category`, `tickets.ticket_seat_id`. |
| `app/Models/Ticket/TicketMap.php`, `TicketSeat.php`, `TicketSeatClaim.php` | Mapa (áreas em JSON), lugar (`soldBy(type)`), reserva do lugar por dia. `TicketType::isSeated()`/`kind()`/`dayKey()`/`sellableSeats()`, `Ticket::isSeat()`/`typeLabel()`/`scopePeople`. |
| `app/Services/Ticket/TicketSeatService.php` | `claim` (prende o lugar; reserva morta sai; `seat_taken`), `release`, `changeSeat`, `layout` (situação de cada lugar num dia), `taken`. |
| `app/Services/Ticket/TicketMapService.php` | Criar/copiar/apagar mapa, `saveLayout` (lugares e áreas, trava o lugar com ingresso). |
| `app/Http/Controllers/Ticket/TicketMapController.php`, `resources/views/tickets/maps/*`, `public/js/tickets/seat-map.js` | Mapas no painel: lista, editor (SVG), ocupação, JSON do mapa para a emissão, troca de lugar. JS sem build (`asset()`). |
| `database/migrations/2026_10_28_140000_add_ticket_logs_print_captive.php` | `ticket_order_logs`, `tickets.print_count`/`printed_at`, `ticket_seats.captive_title`/`captive_name`, `ticket_events.captive_release_days`, `holder_cpf`/`buyer_cpf` nulos. |
| `app/Models/Ticket/TicketOrderLog.php` | Histórico do pedido: `record()`, `label()`, `summary()`. `TicketOrder::logs()`. |
| `app/Services/Ticket/TicketCashService.php`, `app/Http/Controllers/Ticket/TicketCashController.php`, `resources/views/tickets/cash/*` | Relatório de caixa: vendas (baixa manual, por `recorded_by`), cortesias (`created_by`), cancelamentos, site à parte; tela, PDF, CSV. |
| `app/Services/Ticket/TicketPrintService.php`, `resources/views/tickets/print/thermal.blade.php` | Ingresso na térmica de 80 mm: conta a via (`print_count`), grava no histórico, página que abre a impressão. |
| `app/Services/Ticket/TicketCaptiveService.php`, `app/Http/Controllers/Ticket/TicketCaptiveController.php`, `resources/views/tickets/maps/captives.blade.php` | Mesa cativa: guardar (MultiClubes confere a matrícula), tirar, prazo de liberação, lista com situação. |
| `app/Models/Ticket/*` | `TicketEvent`, `TicketEventDay`, `TicketType` (`validity()`, `fullName()`, `daysOverlap()`), `TicketLot`, `TicketCoupon`, `TicketOrder`, `Ticket`, `TicketCheckin`, `TicketPayment`; situações em constantes. |
| `app/Services/Ticket/TicketInventory.php` | Ocupados por lote e por dia (`occupiedByDay`, `capacityOverflow`, `capacityLeftByDay`), lote vigente (`currentLot`), próximo lote, resumo por tipo. |
| `app/Services/Ticket/TicketOrderService.php` | Pedido (site e balcão) com cupom, `issueAtCounter` (pago por fora / cortesia), pagamento, baixa manual, desistência, expiração, `cancelTickets` (um estorno só), `changeHolder`, `checkCoupon`, reenvio do link. |
| `app/Services/Ticket/TicketGuestAccessService.php`, `app/Http/Controllers/Api/TicketGuestAccessController.php`, `app/Models/Ticket/TicketGuestAccessCode.php` | "Meus ingressos" do não sócio: código por e-mail (HMAC, 10 min, 5 tentativas, 5/h) → acesso de 2 h (token cifrado, header `Ticket-Access-Token`) → pedidos com o token de cada um. |
| `app/Services/Ticket/TicketBuyer.php` | Quem compra: sócio (do MultiClubes) ou não sócio. |
| `app/Services/Ticket/TicketSetupService.php` | Situação do evento (`TRANSITIONS`), salvar/apagar dia, tipo e lote, `turnLots` (Virar lote agora), avisos. |
| `app/Services/Ticket/TicketCheckinService.php` | Busca por código/CPF (`relevantFirst`), `evaluate`, `checkIn` (linha em `ticket_checkins`, única por ingresso e dia), janela por evento/dia (`windowLabel`), contagem por dia. Usado pelo Lara e pelo `CompanyService`. |
| `app/Services/Ticket/TicketFinanceService.php` | Totais, por lote, por forma, por cupom, linhas do CSV. |
| `app/Services/Ticket/TicketPresenter.php` | JSON do site (evento, lote, cupom, pedido, ingresso com QR). |
| `app/Services/Ticket/TicketPdf.php`, `resources/views/tickets/pdf.blade.php` | PDF dos ingressos pagos. |
| `app/Services/Ticket/TicketNotifier.php`, `resources/views/emails/tickets/status.blade.php` | E-mails `tickets.*` (mapeados no `ContactMail`). |
| `app/Exceptions/TicketException.php` | Recusas com `reason` estável para o site. |
| `app/Http/Controllers/Ticket/*`, `resources/views/tickets/*` | Painel: eventos, tipos, lotes, cupons, pedidos, emissão, check-in, financeiro. |
| `app/Http/Controllers/Api/TicketApiController.php` | `/api/tickets/*`. |
| `app/Services/CompanyService.php` (`ticketAccessEntries`, `logTicketAccess`, `registerTicketAccess`) | O ingresso no Monitor de Acesso. |
| `app/Console/Commands/ExpireTicketOrders.php`, `TicketTestData.php` | `ingressos:expirar-pedidos` (a cada minuto) e `ingressos:dados-teste`. |
| `config/tickets.php` | `hold_minutes`, `site_url`, `checkin_hours_before`. |
| `app/View/Navigation.php` | Entrada **Ingressos** (área `reservas`, ícone `ticket`): Eventos, Check-in, Financeiro, Caixa. |
| `app/Providers/AppServiceProvider.php` | Gate `acessar-ingressos` (qualquer permissão do módulo abre lista e tela do evento) e `ver-caixa-ingressos` (vendas, baixa manual, cortesias ou `caixa.todos`). |
| `app/Authorization/Permissions.php` | `INGRESSOS_*` (9 permissões, grupo Ingressos; `ingressos.caixa.todos` desde 28/10/2026). |

## Regras que costumam pegar

- **Ocupa lugar** = pago (`valid`, `used`) + pendente de pedido **dentro do prazo**
  (`Ticket::occupying`). O pendente vencido não ocupa mesmo antes de o comando expirar; não
  dependa do status gravado para contar estoque. Os usos do cupom seguem a mesma regra.
- **Pagamento tardio** é aceito se ainda couber (`lockPayable` reconfere lote, lotação, CPF e
  cupom, excluindo o próprio pedido); senão `hold_expired` — o site estorna.
- A trava é na **linha do evento** (`TicketEvent::lockForUpdate`), em pedido e pagamento.
- **Lote sem limite**: `quantity` nulo. `TicketInventory::remaining()` devolve `null` e
  `hasRoom()` é o teste de "tem sobra" — não compare `remaining() > 0` direto.
- **Virar lote agora** fecha o vigente com `ends_at` = agora − 1 s (no instante exato o fim
  ainda está na janela) e traz o `starts_at` do seguinte para agora.
- **Vários dias**: o dia é do **tipo** (`ticket_types.ticket_event_day_id`), não do ingresso;
  dia nulo = todos os dias (passaporte, ou o evento sem dias). "Cruza" =
  `TicketType::daysOverlap()`; vale para o "um por CPF" (`assertNoActiveTicket` recebe
  `[cpf, dia]`), para CPF repetido no pedido (`normalizeHolders`) e para a lotação por dia. O
  dia do tipo não muda depois de vendido (`saveType`).
- **Sócio**: compra para as pessoas do título (`titleHolders` + `titleMembers`, por
  `byTitle(matrícula)`); cada item é um CPF do título (vazio = o comprador), e o nome sai do
  MultiClubes (o digitado é ignorado). No site a pessoa vem pela `ref` de `my-title`
  (`Crypt` de `cpf|matrícula`; a matrícula da ref tem de ser a de quem compra). O sócio sai do
  JWT (`Member::where('cpf', username)`) e passa pelo `MemberProfileLookup::find(cpf,
  matrícula)` — nunca "o primeiro" de `byCpf()`. No balcão, `byCpf()` com a matrícula para
  desempatar. `max_per_order` não vale para o sócio. Chave de array por CPF vira inteiro no
  PHP: volte a texto com `str_pad(..., 11, '0')` antes de comparar ou consultar.
- **Não sócio**: `access_token` cifrado (cast `encrypted`, fora do fillable e do JSON); só
  sai na criação do pedido, no link do e-mail e na lista de "meus ingressos" (depois do código
  por e-mail). Comparação com `hash_equals`. O pedido do código responde sempre igual — não
  revele se o CPF comprou.
- **Cupom** é aplicado ingresso a ingresso depois de escolher o lote (`applyCoupon`, por
  referência nas alocações); cortesia ignora o cupom. O `discount_amount` fica no ingresso e o
  `discount_total` no pedido.
- `issueAtCounter` faz `create` + `recordManual` com `notify: false` numa transação e manda um
  e-mail só. Não chame `create` com `$counter` sem tratar `ConfirmationRequired`.
- `holder_is_member` consulta o MultiClubes por CPF; erro de conexão **não** trava a venda.
- `code`/`qr_code` só saem em ingresso pago. CPF sempre mascarado na API e no CSV.
- No balcão, regras viram `ConfirmationRequired`; nunca no caminho do site. CPF repetido no
  evento recusa também no balcão; pessoa de fora do título no pedido de sócio é aviso.
- `cancelTickets` estorna a soma uma vez (eRede antes da transação) e divide o estorno pelos
  ingressos na proporção.
- **Check-in**: a garantia de uma entrada é o índice único `(ticket_id, day_key)` de
  `ticket_checkins` (`day_key` 0 no evento sem dias), não o status. `used` = entrou ao menos
  uma vez; o passaporte continua entrando nos outros dias. Fora da janela: Lara confirma
  (`$force`), monitor nega. O monitor nunca passa `$force` nem usuário.
- O `CompanyService` só procura ingresso se a tabela `tickets` existe (`ticketsAvailable`, por
  instância): os testes de Externos sobem um banco sem ela. Não troque por `static`.
- Rotas de tipo, lote, cupom e mapa usam `scopeBindings()`: de outro evento dá 404.
- **Lugar marcado**: tipo com `ticket_map_id` vende os lugares do mapa (só os da
  `seat_category`, se preenchida); dois tipos não vendem o mesmo lugar no mesmo dia (recusa no
  `saveType`); no evento com dias o tipo de lugar é diária. O ingresso do lugar (`ticket_seat_id`)
  fica fora do "um por CPF", da lotação, da busca por CPF do check-in e da portaria — use
  `Ticket::people()` em toda conta de pessoas. A garantia contra venda dupla é o índice único
  `(ticket_seat_id, day_key)` de `ticket_seat_claims` mais a trava do evento; quem decide se o
  lugar está ocupado é `Ticket::occupying` (o claim de quem não ocupa mais é apagado no
  próximo `claim`). Lugar ocupado recusa sempre, até no balcão e na baixa manual com força.
  `max_per_order` vale separado para entradas e lugares (no lugar, também para o sócio).
- `@json(...)` do Blade quebra com vírgula dentro do argumento (array, `route()` com
  parâmetros): monte a variável num `@php` e use `Js::from()` ou `json_encode`.
- Sem `Session`, o `JwtMiddleware` responde 400 (não 401).
- **Histórico**: toda ação nova sobre pedido grava `TicketOrderLog::record($order, ação,
  $user, detalhes, $ticket)` dentro da transação dela. Usuário nulo = site ou rotina. O caixa
  não usa o histórico: lê `ticket_payments.recorded_by`, `ticket_orders.created_by` e
  `tickets.cancelled_by` (vale também para pedidos de antes do histórico).
- **Cortesia sem CPF**: só `courtesy` + balcão + não sócio (`$anonymous` no `create`,
  `normalizeHolders(..., $anonymous)`). `holder_cpf`/`buyer_cpf` podem ser nulos: toda conta
  de "um por CPF" e toda busca por CPF tem de pular o nulo; a tela mostra "sem CPF". Venda
  sem CPF recusa `invalid_holder`.
- **Mesa cativa**: `TicketSeat::captiveFor($event)` = matrícula enquanto vale, `null` depois
  (liberação calculada na hora por `captive_release_days`, sem rotina). A recusa
  `seat_captive` é **sempre** (balcão também), no `pickSeat` e no `changeSeat`. Compare
  matrícula com `TicketSeat::sameTitle` (sem zeros à esquerda). `layout(..., title:)` deixa a
  cativa da matrícula livre; o painel recebe `captive_title`/`captive_name`. Cópia de mapa
  leva as cativas.
- **Impressão**: só pedido pago e ingresso válido; é POST (contar a via não pode ser efeito
  de um GET recarregado). O QR é o mesmo código do PDF.

## Testes

`tests/Feature/Tickets/*`: `TicketOrderFlowTest` (regras do pedido, lotes, estoque,
pagamento, expiração, estorno), `TicketApiTest` (site, sócio e não sócio),
`TicketPanelTest` (eventos, tipos, lotes), `TicketCouponAndCounterTest` (cupom, emissão,
cortesia, cancelamento em lote, troca de titular), `TicketOrdersPanelTest` (cupons, pedidos,
emissão e permissões), `TicketCheckinTest` (Lara e Monitor de Acesso), `TicketMultiDayTest`
(dias, diária, passaporte, lotação e entrada por dia), `TicketLotTurnTest` (lote sem limite,
Virar lote agora), `TicketGuestAccessTest` (meus ingressos do não sócio), `TicketFinanceTest`
(relatório, CSV, dados de teste), `TicketSeatMapTest` (lugar marcado: venda dupla, índice único,
lugar por dia, pendente vencido, pagamento tardio, desenho, cópia, API, telas, check-in),
`TicketTraceCashCaptiveTest` (histórico, filtro por vendedor, caixa próprio × todos,
impressão e vias, cortesia sem CPF e em quantidade, mesa cativa, liberação, cópia) — sobre `Tests\Concerns\CreatesTicketSchema` e
`Tests\Concerns\BuildsTickets` (MultiClubes e eRede em memória). Confira o isolamento SQLite
antes de rodar.

## O que não pode ser esquecido

- O módulo conversa com o **Next.js**: toda mudança na API atualiza
  `ingressos-site-prompt.md` e gera um prompt para o agente do site.
- Mapas e lugar marcado também estão na API do site (`/events/{id}/maps/{mapId}`, `seat_id` no
  pedido, `kind`/`seat` no ingresso): prompt v7 no mesmo arquivo. Mesa cativa (`captive`,
  `/maps/{mapId}/member`, `seat_captive`): prompt v8.
- O check-in mexe no **Monitor de Acesso** (`CompanyService`, API sem token usada pelo app
  Python da guarita): mudou a resposta, atualize `docs/ingressos-prompt-portaria.md` e gere o
  prompt para o agente da guarita.
- Permissões novas entram no catálogo sem setor.
- Nomes de tabela com prefixo `ticket_`; índice e FK com nome curto (64 do MySQL).
