# Financeiro

## O que é

Módulo do menu (desde 13/11/2026) que junta num lugar só tudo o que era financeiro e estava
espalhado pelos módulos:

| Antes | Agora (Financeiro ›) | Rota (nome não mudou) |
|---|---|---|
| — | **Visão geral** | `finance.index` |
| — | **Recebimentos** | `finance.receipts.index`, `finance.receipts.csv` |
| Ingressos › Caixa (só ingressos) | **Caixa** (Reservas, Torneios e Ingressos) | `finance.cash.index`, `.pdf`, `.csv` |
| Reservas › Pagamentos (`/payments`) | **Reservas** | `payment.index`, `payment.show`, `payment.export`, `payment.refund` |
| Torneios › Financeiro (`/torneios/financeiro`) | **Torneios** | `tournaments.finance.*` |
| Ingressos › Financeiro (`/ingressos/financeiro`) | **Ingressos** | `tickets.finance.*` |
| Freelancers › Financeiro (`/freelancer-services/financeiro`) | **Freelancers** | `freelancer-services.finance*`, `freelancer-services.pay` |

Decisões do usuário (13/11/2026): o módulo cobre **recebimentos e o pagamento dos freelancers**
(o Dashboard de Vendas do MultiVendas fica fora); as abas antigas **saíram** dos módulos; a
**tabela única de pagamentos fica para depois** — as três tabelas continuam e a visão conjunta é
uma consulta.

## Para quem

A Contabilidade/Financeiro (Visão geral, Recebimentos, fechamento do Caixa, pagamento dos
freelancers) e quem recebe no balcão (o próprio Caixa).

## Permissões

Os nomes antigos continuam — os setores já configurados não perdem nada. Mudaram só o **grupo**
(agora "Financeiro" na tela Setores) e o rótulo. O `PermissionCatalogSeeder` acerta a descrição no
deploy.

| Permissão | Abre |
|---|---|
| `financeiro.recebimentos` (**nova**, nasce sem setor) | Visão geral e Recebimentos (as três origens juntas) |
| `ingressos.caixa.todos` | Caixa de todas as pessoas, com o bloco Site (eRede) e o filtro por pessoa |
| Gate `ver-caixa-financeiro` (ANY_OF) | O próprio Caixa: baixa manual de Reservas, Torneios ou Ingressos, `ingressos.vendas`, `ingressos.cortesias` ou `ingressos.caixa.todos`. Substituiu `ver-caixa-ingressos`. |
| `reservas.pagamentos` / `reservas.pagamentos.estornar` | Financeiro › Reservas / estorno nele |
| `torneios.financeiro` | Financeiro › Torneios |
| `ingressos.financeiro` | Financeiro › Ingressos |
| `freelancers.financeiro` | Financeiro › Freelancers (lotes, baixa) |

Ficam nos módulos de origem, porque acontecem nas telas deles: `reservas.pagamentos.baixa-manual`,
`torneios.pagamentos.baixa-manual`, `torneios.pagamentos.estornar`,
`ingressos.pagamentos.baixa-manual`, `ingressos.pagamentos.estornar`.

## Peças

### `App\Services\Finance\PaymentMethods`
A lista de formas (as da baixa manual `manual_*` e as do site `credit`, `debit`, `pix`…).
`SchedulePayment::MANUAL_METHODS` e `::METHOD_LABELS` continuam como apelido.

### `App\Models\Concerns\FinancePayment` (trait)
Em `SchedulePayment`, `TournamentPayment` e `TicketPayment`: `isOnline()`, `methodLabel()`,
`refundableAmount()` e `recordRefund()`. Cada model diz como marca o estorno no status
(`markRefundStatus`): Reservas põe `status_id = 0` no estorno total; Torneios e Ingressos usam
`refunded` / `partially_refunded`.

### `App\Services\Finance\PaymentRefunder`
O estorno pela eRede, **um só** (antes eram três cópias). Recusa baixa manual e pagamento sem
`payment_integration_id`; chama a Rede e só então grava. `clamp: true` estorna até o disponível
(cancelamento de inscrição/ingressos); sem ele, valor acima do disponível é recusado (tela de
Reservas). `RedeItauService::refundPayment()` continua existindo e delega a ele.

### `App\Services\Finance\FinanceLedger`
UNION ALL de `schedule_payments`, `tournament_payments` e `ticket_payments` com as mesmas colunas:
`origin, id, paid_at, payment_method, is_manual, paid_amount, refunded_amount, recorded_by, tid,
payer_name, payer_cpf, reference, reference_id`.

- Pagador e referência por **subconsulta** (não join): um pagamento de Reservas cobre vários
  horários e um de Torneios várias inscrições; o join multiplicaria a soma.
- `reference`: grupo do local (Reservas), título do torneio, título do evento.
- `reference_id`: o pagamento (Reservas → `payment.show`), o torneio
  (`tournaments.finance.show`), o pedido (`tickets.orders.show`).
- Reservas: só pagamentos com `paid_at`.
- Situação: pago (sem estorno), estornado em parte, estornado (estorno ≥ pago).
- `query($filters)` (período pelo `paid_at`, origem, forma — inclusive `manual`/`online` —,
  situação, `recorded_by`, busca por pagador, CPF, referência ou TID) e `summary($filters)` (total,
  por origem e por forma, eRede × manual).

### `App\Services\Finance\CashReport`
O antigo `TicketCashService`, agora com as três origens: recebimento = baixa manual (pelo ledger,
na conta de `recorded_by`); cortesia e cancelamento continuam só dos ingressos; o bloco Site (eRede)
sai por origem. Período máximo de 62 dias.

### Controllers (`App\Http\Controllers\Finance`)
`FinanceOverviewController` (Visão geral, com o pago e o a pagar dos freelancers pelo scope
`awaitingFinance`), `FinanceReceiptController` (lista + CSV), `FinanceCashController` (tela, PDF,
CSV) e `LegacyFinanceRedirectController`.

## Endereços

Tudo sob `/financeiro` (`routes/web.php`, bloco "Financeiro"). Os endereços antigos respondem
**301** para o novo, com a mesma query string: `/payments/*`, `/torneios/financeiro`,
`/torneios/{id}/financeiro[/csv]`, `/ingressos/financeiro`, `/ingressos/{id}/financeiro[/csv]`,
`/ingressos/caixa[/pdf|/csv]`, `/freelancer-services/financeiro/*`.

Ações POST mudaram de endereço sem redirecionamento (os formulários usam `route()`): estorno de
reserva em `/financeiro/reservas/{id}/estornar`, baixa dos freelancers em
`/financeiro/freelancers/pagar`. A baixa manual de reserva continua em `POST /payments/manual`
(`payment.manual`), porque é ação de Reservas.

Mudou rota: rode `php artisan route:cache` no deploy (os scripts já rodam).

## Integrações externas

Nenhuma API mudou: o site Next.js continua pagando por `/api/schedule/payment`, `/api/tournaments/…`
e `/api/tickets/…`, e a portaria/Lara Externos não usa estas telas. Prompt de registro em
`docs/prompts/NextJS/financeiro-sem-mudanca-prompt.md` e
`docs/prompts/LaraExternos/financeiro-sem-mudanca-prompt.md`.

## Testes

`tests/Feature/Finance/FinanceModuleTest.php` (estorno único, ledger, resumo, telas, permissões,
menu e endereços antigos) e `tests/Feature/Tickets/TicketTraceCashCaptiveTest.php` (o Caixa). Os
testes que passam pelo ledger usam `tests/Concerns/CreatesFinanceSchema` para ter as tabelas das
três origens.

## Guia do usuário

[docs/guias/financeiro.md](../guias/financeiro.md).
