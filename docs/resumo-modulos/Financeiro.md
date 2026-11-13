# Financeiro — resumo para quem vai implementar

Leia antes: [docs/funcionalidades/financeiro.md](../funcionalidades/financeiro.md).

## O que é

Módulo do menu (13/11/2026) que reúne o financeiro antes espalhado: **Visão geral**,
**Recebimentos**, **Caixa**, e as telas por origem — **Reservas** (pagamentos), **Torneios**,
**Ingressos** e **Freelancers** (pagamento dos lotes). Tudo sob `/financeiro`.

## Regras que não podem quebrar

- **Três tabelas, uma consulta.** `schedule_payments`, `tournament_payments` e `ticket_payments`
  continuam separadas; a visão conjunta é o `App\Services\Finance\FinanceLedger` (UNION ALL). Tabela
  única ficou para depois por decisão do usuário. Nova origem de pagamento = mais um bloco no
  `union()` do ledger, com as mesmas colunas, e mais uma entrada em `FinanceLedger::ORIGINS`.
- **Estorno é um só:** `App\Services\Finance\PaymentRefunder`. Não escreva outro; o model novo usa o
  trait `App\Models\Concerns\FinancePayment` e implementa `markRefundStatus()`.
- **Baixa manual não estorna pelo Lara** (o dinheiro não passou pela Rede).
- **Formas de pagamento:** `App\Services\Finance\PaymentMethods`.
- **Pagador e referência por subconsulta**, nunca join que multiplique linhas (um pagamento cobre
  vários horários/inscrições).
- **Nomes de rota antigos ficaram** (`payment.*`, `tournaments.finance.*`, `tickets.finance.*`,
  `freelancer-services.finance*`), só a URL mudou; endereços antigos dão 301
  (`LegacyFinanceRedirectController`).
- **Permissões:** os nomes antigos continuam (setores não perdem acesso), no grupo "Financeiro" do
  catálogo. Nova só `financeiro.recebimentos`. Caixa: Gate `ver-caixa-financeiro`; ver o de todos =
  `ingressos.caixa.todos`.
- A **baixa manual** e o estorno por cancelamento de Torneios/Ingressos ficam nas telas dos módulos.

## Arquivos

| Arquivo | Papel |
|---|---|
| `app/Services/Finance/FinanceLedger.php` | Consulta conjunta, filtros e resumo |
| `app/Services/Finance/CashReport.php` | Caixa por pessoa/dia/forma (três origens) |
| `app/Services/Finance/PaymentRefunder.php` | Estorno eRede único |
| `app/Services/Finance/PaymentMethods.php` | Formas de pagamento |
| `app/Models/Concerns/FinancePayment.php` | Trait comum dos três pagamentos |
| `app/Http/Controllers/Finance/*` | Visão geral, Recebimentos, Caixa, redirecionamentos |
| `resources/views/finance/*` | Telas (index, receipts, cash/index, cash/pdf, partials/origin-link) |
| `app/View/Navigation.php` | Grupo **Financeiro** (área `compras`, ícone `money`) |
| `routes/web.php` | Bloco `prefix('financeiro')` + redirecionamentos |
| `tests/Feature/Finance/FinanceModuleTest.php` | Testes do módulo |
| `tests/Concerns/CreatesFinanceSchema.php` | Tabelas das três origens para testes que passam pelo ledger |

As telas por origem continuam nos controllers dos módulos (`SchedulePaymentController`,
`Tournament\TournamentFinanceController`, `Ticket\TicketFinanceController`,
`Freelancer\FinanceController`).

## Guia do usuário

[docs/guias/financeiro.md](../guias/financeiro.md).
