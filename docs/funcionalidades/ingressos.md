# Ingressos

> Módulo novo, feito em 27/10/2026 (fases F1 a F8). Este documento registra as decisões e o
> funcionamento; o passo a passo para quem opera está no [guia](ingressos-guia.md), o
> contrato para o site no [prompt do Next.js](ingressos-site-prompt.md) e a mudança da
> guarita no [prompt da portaria](../ingressos-prompt-portaria.md).

## O que é

Venda de **ingressos de eventos** do clube (bailes, shows, festas) em **lotes**. O Lara é o
backend com todas as regras e configurações; a venda acontece no **site de locação** (o
mesmo Next.js das reservas e dos torneios), com pagamento pela **eRede**. O Lara também
emite ingressos pagos por fora e cortesias, e faz o check-in.

```
Evento ─┬─ Tipo de ingresso (Pista, Mesa, Infantil…)
        │      └─ Lotes: quantidade + janela de datas + preço sócio + preço não sócio
        ├─ Cupons de desconto (porcentagem ou valor fixo por ingresso)
        └─ Pedidos ─┬─ Ingressos (um por pessoa, nominal: nome + CPF, código + QR)
                    └─ Pagamento (eRede, baixa manual ou nenhum: cortesia/grátis)
```

## Para quem

- **Sócio** (site, login do sócio — titular ou dependente): compra para **as pessoas do título
  dele** (ele, o titular e os dependentes), um ingresso para cada, com preço de sócio.
- **Não sócio** (site, **sem conta**): compra para uma ou mais pessoas, com preço de não
  sócio; acessa o pedido pelo link que recebe por e-mail.
- **Equipe do clube** (painel do Lara): cadastra evento, tipos, lotes e cupons; acompanha os
  pedidos; **emite ingressos pagos por fora** e **cortesias**; dá baixa manual; troca
  titular; cancela com estorno; vê o financeiro.
- **Portaria**: faz o check-in na tela do Lara ou no **Monitor de Acesso** dos terceirizados.

## Regras decididas (27/10/2026)

| # | Decisão |
|---|---|
| 1 | **Lote por tipo**: cada tipo de ingresso tem os seus lotes; cada lote tem quantidade, janela de datas, **preço sócio** e **preço não sócio**. |
| 2 | **O sócio compra para todos do título dele** (mudado em 27/10/2026; antes era só para ele): qualquer pessoa do título — titular ou dependente — compra, no login dela, para qualquer pessoa do mesmo título, pelo preço de sócio. Ninguém de fora do título entra no pedido de sócio. |
| 3 | **Não sócio compra sem conta** (CPF, nome, e-mail, telefone); o pedido é acessado pelo token do link. |
| 4 | **Ingresso nominal**: nome e CPF de quem entra, sempre. |
| 5 | **Sem meia-entrada** por enquanto (ver *Para implementação futura*). |
| 6 | **Check-in pelo Lara e pelo Monitor de Acesso** dos terceirizados. |
| 7 | **O Lara não confere o tid na eRede**: confia no que o site envia, como nos torneios. |
| 8 | **Cupom de desconto** que abate valor fixo ou porcentagem do ingresso. |
| 9 | **Emissão pelo Lara com pagamento por fora** (baixa manual no ato). |
| 10 | **Cortesia** com valor zerado. |
| 11 | **Evento de vários dias** (27/10/2026): ingresso **diário** ou **passaporte** para todos os dias. Nem todo evento de vários dias tem passaporte, mas tem de ser possível. |

Decorrências fixadas na implementação:

- **Um ingresso ativo por CPF por dia** (pendente no prazo, válido ou usado): no evento sem
  dias, um no evento; no de vários dias, uma diária por dia **ou** o passaporte (que cruza com
  todos os dias). A mesma pessoa pode ter diárias de dias diferentes, até no mesmo pedido.
- **Pessoas do título** = titular e dependentes de título ativo no MultiClubes
  (`MemberProfileLookup::byTitle(matrícula de quem compra)`). O nome do ingresso é o do
  cadastro do clube. O site escolhe as pessoas pela `ref` de `GET /api/tickets/my-title`
  (CPF e matrícula cifrados, como nos convites dos torneios); sem `ref`, o ingresso é do
  próprio comprador. Pessoa de fora do título: `holder_not_in_title`. Se o MultiClubes estiver
  fora do ar, o título fica só com o comprador. `max_per_order` não vale para o sócio (o
  limite é o tamanho do título, um por CPF).
- **CPF de sócio ativo não entra em pedido de não sócio** (`holder_is_member`): o sócio compra
  com o login, pelo preço de sócio. Se o MultiClubes estiver fora do ar, a conferência é
  pulada (fica no log): o pior caso é um sócio pagar o preço mais caro.
- **Lote sem preço de não sócio** = só para sócios (`members_only`).
- Pedido de **valor zero** (lote grátis, cupom de 100%, cortesia) já nasce pago.

### Evento de vários dias: diária e passaporte

O evento de um dia (ou de uma noite que passa da meia-noite) não muda nada: não tem dias
cadastrados e o ingresso vale o evento. Para o evento de **vários dias**:

- A equipe cadastra os **dias** (`TicketEventDay`): nome opcional ("Sábado"; vazio = "Sáb
  13/02"), início e fim — o dia pode passar da meia-noite. O dia fica dentro do período do
  evento e não cruza com outro dia.
- Cada **tipo** de ingresso tem **"Vale para"**: um dia (a **diária** daquele dia) ou nenhum
  (o **passaporte**, todos os dias). O passaporte só existe se a equipe criar um tipo sem dia:
  o evento pode ter só diárias. Cada diária e o passaporte têm os próprios lotes e preços
  (ex.: "Pista" no sábado, "Pista" no domingo e "Passaporte"). `TicketType::validity()`:
  `event` (evento sem dias), `day`, `passport`.
- O dia de um tipo **não muda depois de vendido** (mudaria quando vale o que já foi pago):
  crie outro tipo. Dia com diária não é apagado; o evento não encolhe deixando um dia de fora.
- **Lotação por dia**: com `capacity`, cada dia comporta `capacity` pessoas, contando as
  diárias dele **e** os passaportes. O passaporte só cabe se couber em todos os dias.
- **Um por CPF por dia** (acima). Diária de sábado + diária de domingo para a mesma pessoa
  pode; diária + passaporte, não.
- **Entrada**: a diária entra uma vez, no dia dela; o passaporte entra **uma vez em cada dia**.
  A janela de cada dia abre `TICKETS_CHECKIN_HOURS_BEFORE` antes do início do dia e fecha no fim
  dele.

### Lote vigente

O site vende, para cada tipo, o lote de **menor ordem** (`sequence`) que está **ativo**,
**dentro da janela** (datas do lote; vazias = a janela do evento) e **com sobra**. O lote
fecha pela data ou quando esgota, o que vier primeiro, e o seguinte abre na hora — se a
janela dele já começou. Se não, o tipo aparece sem lote à venda e com `next_lot` (o próximo
que vai abrir pela data).

**Lote sem limite** (`quantity` vazio, desde 28/10/2026): não esgota; fecha pela data ou
pelo **Virar lote agora**. A lotação do evento continua valendo.

**Virar lote agora** (`TicketSetupService::turnLots`, botão na tela do evento, permissão
`ingressos.gerenciar`): em cada tipo ativo com lote à venda, o lote vigente ganha `ends_at` =
agora e abre o seguinte — o primeiro de ordem maior que está ativo, não acabou e tem sobra; se
ele só abriria mais tarde, o `starts_at` vem para agora. Tipo no último lote continua nele;
sem nenhum tipo para virar, recusa. Sempre pede confirmação, listando o que vira e os preços
novos. Pedidos pendentes não mudam (o ingresso guarda o lote em que coube).

Um pedido com vários ingressos do mesmo tipo pode **atravessar a virada**: cada ingresso
leva o lote e o preço em que coube (ex.: último do 1º lote a R$ 80 + um do 2º lote a R$ 100).

### Estoque e reserva temporária

Ocupa lugar o ingresso **pago** (válido ou usado) e o **pendente de pedido dentro do prazo**
(`Ticket::occupying`). O pedido nasce `pending_payment` com `hold_until` = agora +
`TICKETS_HOLD_MINUTES` (padrão 10). Passado o prazo, o lugar volta à venda na hora (mesmo
antes do comando), e `ingressos:expirar-pedidos` (a cada minuto) grava `expired`.

Pagamento que chega **depois do prazo** ainda é aceito se o lote, a lotação, o "um por CPF"
e os usos do cupom ainda comportarem; se não, recusa `hold_expired` e **o site estorna** na
eRede.

A **lotação** (`capacity`) do evento soma todos os tipos; vazia = sem limite. No evento de
vários dias, vale **por dia** (diárias do dia + passaportes). A linha do
evento é travada (`lockForUpdate`) em pedido e pagamento: dois compradores não levam o
último lugar.

### Preço

Sempre do lote vigente, calculado **no servidor**; o que o site manda não vale. Fica
congelado no ingresso: `list_price` (preço do lote), `discount_amount` (cupom) e `amount`
(o cobrado), com `price_kind` (`member`/`non_member`). Mudar o preço do lote vale só para as
próximas vendas.

### Cupom de desconto (`TicketCoupon`)

- Do evento, com **código** único no evento (guardado em maiúsculas, sem espaços; letras,
  números, hífen e sublinhado).
- **Porcentagem** (`percent`, até 100) do preço do lote, ou **valor fixo** (`fixed`) em
  reais — abatido de **cada ingresso** a que se aplica, nunca abaixo de zero.
- Vale para os **tipos** escolhidos (nenhum = todos) e para um **público**: sócios e não
  sócios, só sócios ou só não sócios.
- **Prazo** opcional (início e fim) e **limite de usos** opcional, contado em **ingressos com
  desconto** que ocupam lugar (pagos ou pendentes no prazo). Pendente vencido devolve o uso.
- No site, cupom inexistente, desativado ou fora do prazo → `coupon_invalid`; que não vale
  para nenhum ingresso do pedido → `coupon_not_applicable`; sem uso que cubra o pedido →
  `coupon_exhausted` (`context.remaining`). No Lara, desativado, fora do prazo ou sem uso
  viram aviso; inexistente e "não se aplica" recusam.
- O pedido guarda `ticket_coupon_id`, `coupon_code` (cópia) e `discount_total`.
- Cupom usado em pedido não é apagado: é desativado.

### Emissão pelo Lara (`TicketOrderService::issueAtCounter`)

Tela **Emitir ingressos** (a partir dos Pedidos do evento). Dois modos, numa transação só e
com um e-mail no fim:

| Modo | Permissão | O que acontece |
|---|---|---|
| **Pagamento recebido por fora** (`external`) | `ingressos.vendas` + `ingressos.pagamentos.baixa-manual` | Cria o pedido pelo preço do lote vigente (com cupom, se houver) e dá a **baixa manual** na hora: forma (dinheiro, Pix, cartão na maquininha, transferência, outro), data e observação. |
| **Cortesia** (`courtesy`) | `ingressos.vendas` + `ingressos.cortesias` | Ingressos a **valor zero**, já pagos, **sem** pagamento gravado e sem cupom. `ticket_orders.courtesy` = true; `list_price` guarda o valor de face para o financeiro. |

Quem compra: **sócio** pelo CPF (dados e e-mail do MultiClubes; com o CPF em mais de um
título, pede a matrícula), com um ingresso para cada pessoa do título informada pelo CPF (o
nome vem do cadastro; CPF vazio = o próprio sócio), ou **não sócio** (nome, CPF, e-mail,
telefone e um titular por ingresso). O pedido sai com `origin = secretaria` e quem emitiu
(`created_by`).

### No Lara, só aviso

Como nos torneios, o balcão do Lara não recusa: avisa e pede **Confirmar mesmo assim**
(`ConfirmationRequired`).

| Regra | Site | Lara |
|---|---|---|
| Evento em rascunho/cancelado | `event_not_found` | aviso |
| Fora da janela de vendas | `sales_closed` | aviso |
| Acima de `max_per_order` | `limit_per_order` | aviso |
| Sem lote à venda | `sold_out` | aviso; entra no último lote ativo, além da quantidade |
| Lotação | `capacity_full` | aviso |
| CPF de sócio em pedido de não sócio | `holder_is_member` | aviso |
| Cupom desativado, fora do prazo ou sem uso | `coupon_*` | aviso |
| CPF já com ingresso no evento (no mesmo dia, ou passaporte) | `already_has_ticket` | **recusa** |
| Pessoa de fora do título no pedido de sócio | `holder_not_in_title` | aviso (preço de sócio para alguém de fora) |
| Baixa manual de pedido vencido sem estoque | — | aviso |
| Troca de titular de ingresso de sócio, ou para CPF de sócio em pedido de não sócio | — | aviso |
| Check-in fora da janela de entrada | — | aviso (o monitor nega) |
| Quantidade do lote abaixo do ocupado | — | aviso (nada é cancelado; o lote só esgota) |
| Mudar preço de lote com venda | — | aviso |
| Situação fora do caminho normal, cancelar com venda, voltar a rascunho com venda | — | aviso |

Recusa de verdade no painel: **apagar tipo, lote ou cupom já usado** (desative) e
**publicar evento sem tipo ativo com lote ativo**.

### Cancelamento e estorno

- Pelo site, só **desistência de pedido pendente** (`withdrawn`). Pago: `paid_contact_organizer`.
- A equipe cancela os **ingressos marcados** do pedido (ou todos os válidos) —
  `cancelTickets` — com ou sem estorno pela eRede. O estorno é **um só**, pela soma dos
  cancelados (`RedeItauService::refund`), **antes** de gravar: se a eRede recusar, nada muda.
  O valor se divide pelos ingressos na proporção de cada um (`tickets.refunded_amount`); o
  pagamento acumula em `ticket_payments.refunded_amount`. Baixa manual não tem estorno pelo
  Lara.
- Sem ingresso válido ou usado no pedido, o pedido vira `cancelled`.
- Ingresso **usado** não é cancelado.
- Cancelar o evento **não estorna sozinho** (o Lara avisa).

### Troca de titular

Ingresso válido ou pendente pode passar para outra pessoa (nome e CPF), mantendo tipo, lote,
preço e **código**. O "um por CPF por dia" vale para o novo titular. Num pedido de sócio,
passar para alguém de fora do título é aviso; CPF de sócio num pedido de não sócio, também.

### Check-in (`TicketCheckinService`)

- **Uma entrada por ingresso por dia**: cada entrada é uma linha de `ticket_checkins`
  (`day_key` = id do dia, 0 no evento sem dias) com índice único (ingresso, dia) — dois
  registros ao mesmo tempo não passam os dois. O ingresso vira `used` na primeira entrada;
  `checked_in_at`, `checked_in_by` (nulo no monitor) e `checkin_source` (`lara`/`monitor`)
  guardam a **última**. O ingresso do evento sem dias e a diária entram uma vez; o
  **passaporte**, uma vez em cada dia ("já entrou hoje" no segundo registro do mesmo dia).
- **Janela de entrada**: de `TICKETS_CHECKIN_HOURS_BEFORE` (padrão 3) horas antes do início
  até o fim — do evento, ou do dia (a diária no dia dela; o passaporte no dia que estiver
  aberto). Fora dela, o Lara pede confirmação (passaporte forçado fora de qualquer dia conta
  no dia mais próximo); o monitor nega (`ticket_outside_window`).
- Pelo **CPF**, quando a pessoa tem mais de um ingresso no evento (diárias), vem só o mais
  relevante: o que entra agora; senão o já usado; senão o fora da janela.
- Busca pelo **código** `ING-…` (o QR; aceita minúsculas, espaço, sem hífen) ou pelo **CPF**
  do titular.
- **Tela do Lara** (`/ingressos/checkin`, `ingressos.checkin`): escolhe o evento (os com a
  entrada aberta, mais os próximos); campo com foco para leitor de QR ou digitação; Enter
  registra, "Só consultar" não grava; contador "X de Y entraram" (no evento de vários dias,
  do dia aberto ou do próximo: diárias do dia + passaportes); botão **Ler QR** com a
  câmera onde o navegador tem `BarcodeDetector`. Código de outro evento: avisa e não registra.
- **Monitor de Acesso** (`CompanyService`): o ingresso entra na busca por **CPF** como mais
  uma linha (`type: ticket`, etiqueta "Ingresso", só eventos com a entrada aberta); o
  **código** `ING-…` também é alvo (`validate-access`, `register-access`); o botão usa
  `POST /api/company-access/register-ticket-access` (`ticket_id`). Registrar **faz o
  check-in**; a linha volta atualizada (negada se outro registro passou na frente). Tudo cai
  no histórico (`company_access_logs.ticket_id`, etiqueta "Ingresso").

| `reason` (monitor e histórico) | Texto |
|---|---|
| `ticket_access_granted` | Ingresso válido |
| `ticket_already_used` | Ingresso já utilizado |
| `ticket_not_valid` | Ingresso não está válido (pendente, cancelado ou expirado) |
| `ticket_outside_window` | Ingresso fora do horário de entrada do evento |
| `ticket_not_found` | Código de ingresso que não existe |

### Situações

```
Pedido:   pending_payment ──(eRede | baixa manual)──► paid ──(todos cancelados)──► cancelled
              ├──(prazo)──► expired ──(pagamento tardio com estoque)──► paid
              └──(desistência)──► withdrawn
          (valor zero: cortesia, lote grátis, cupom 100% → nasce paid)
Ingresso: pending ──► valid ──(check-in)──► used
              └──► expired          └──► cancelled
Evento:   draft ──► published ──► finished      (cancelled a partir de qualquer um)
```

## Modelagem

Migrations `2026_10_27_100000_create_ticket_tables` (as 7 tabelas),
`2026_10_27_110000_add_ticket_id_to_company_access_logs` e
`2026_10_27_120000_add_ticket_event_days` (dias, dia do tipo, entradas; copia para
`ticket_checkins` as entradas já gravadas no ingresso) e
`2026_10_28_100000_make_ticket_lot_quantity_nullable` (lote sem limite) e
`2026_10_28_110000_create_ticket_guest_access_codes` (códigos de "meus ingressos"), idempotentes, FKs com nome curto;
models em `app/Models/Ticket/`.

| Tabela | Papel |
|---|---|
| `ticket_events` | `title`, `description`, `rules`, `image` (`ImageSource`), `venue`, `starts_at`/`ends_at`, `sales_starts_at`/`sales_ends_at`, `capacity` (nulo = sem limite), `max_per_order`, `status` (`draft`, `published`, `finished`, `cancelled`). SoftDeletes. |
| `ticket_event_days` | Dias do evento de vários dias: evento, `name` (opcional), `starts_at`/`ends_at`. |
| `ticket_types` | Evento, `ticket_event_day_id` (diária; nulo = passaporte no evento com dias, ou o ingresso do evento sem dias), `name`, `description`, `sort_order`, `active`. |
| `ticket_lots` | Tipo, `name`, `sequence`, `quantity` (nulo = sem limite), `starts_at`/`ends_at` (nulos = janela do evento), `price_member`, `price_non_member` (nulo = só sócios), `active`. |
| `ticket_coupons` | Evento, `code` (único no evento), `description`, `kind` (`percent`/`fixed`), `value`, `audience` (`all`/`member`/`non_member`), `ticket_type_ids` (JSON; nulo = todos), `max_uses`, `starts_at`/`ends_at`, `active`, `created_by`. |
| `ticket_orders` | Evento, `buyer_kind` (`member`/`guest`), `member_id`, cópia de CPF/nome/e-mail/telefone/matrícula de quem compra, `total`, `ticket_coupon_id`/`coupon_code`/`discount_total`, `status`, `hold_until`, `access_token` (cifrado, só não sócio), `origin` (`site`/`secretaria`), `courtesy`, `created_by`, carimbos de pago/expirado/desistência/cancelamento. |
| `ticket_payments` | Um por pedido: eRede (tid em `payment_integration_id`) ou baixa manual (`is_manual`, `recorded_by`, `manual_notes`, fora do fillable), estorno. |
| `tickets` | Pedido, evento, tipo, lote, `price_kind`, `list_price`, `discount_amount`, `amount`, `holder_name`, `holder_cpf` (só dígitos), `code` (único, `ING-XXXXXXXX`), `status`, check-in (`checked_in_at`, `checked_in_by`, `checkin_source`), `refunded_amount`, cancelamento. |
| `ticket_checkins` | Cada entrada: ingresso, `ticket_event_day_id`, `day_key` (dia ou 0; único com o ingresso), `checked_in_at`, `checked_in_by`, `source`. |
| `ticket_guest_access_codes` | Código de "meus ingressos" do não sócio: `cpf`, `email` (minúsculo), `code_hash` (HMAC), `attempts`, `expires_at`, `used_at`, `ip`. |
| `company_access_logs.ticket_id` | O ingresso conferido no Monitor de Acesso (nulo nos outros acessos). |

O **código** do ingresso tem 8 caracteres de um alfabeto sem 0/O e 1/I/L (é digitado na
portaria); `Ticket::normalizeCode()` aceita minúsculas, espaço, hífen e sem o `ING`.

## Permissões

No catálogo `App\Authorization\Permissions`, grupo **Ingressos**, sem setor: depois do
deploy, dê aos setores pertinentes pela tela de Setores.

| Permissão | O que libera |
|---|---|
| `ingressos.gerenciar` | Eventos, tipos de ingresso e lotes (criar, editar, situação) |
| `ingressos.vendas` | Pedidos e ingressos: consultar, reenviar e-mail, PDF, trocar titular, cancelar; abre a emissão |
| `ingressos.pagamentos.baixa-manual` | Baixa manual de pedido pendente e emissão com pagamento por fora |
| `ingressos.cortesias` | Emissão de cortesia |
| `ingressos.pagamentos.estornar` | Estornar na eRede ao cancelar |
| `ingressos.cupons` | Cupons de desconto do evento |
| `ingressos.checkin` | Tela de check-in do Lara (o Monitor de Acesso não pede permissão) |
| `ingressos.financeiro` | Relatório financeiro e CSV |

A lista de eventos e a tela do evento abrem para quem tem **qualquer** uma delas (Gate
`acessar-ingressos`, no `AppServiceProvider`); o que cada um faz lá dentro segue a própria
permissão.

## Telas

Menu **Ingressos**: **Eventos** (`acessar-ingressos`), **Check-in** (`ingressos.checkin`),
**Financeiro** (`ingressos.financeiro`).

- **Lista de eventos** (`/ingressos`) com busca e filtro de situação; vendidos e lotação.
- **Novo/editar evento** (`/ingressos/novo`, `/ingressos/{id}/editar`): nome, local,
  descrição, regras, datas do evento e das vendas, lotação, ingressos por pedido, imagem.
- **Tela do evento** (`/ingressos/{id}`): situação, vendas, lotação (por dia, no evento de
  vários dias), pedidos pendentes; botões de Pedidos, Emitir, Cupons, Check-in, Financeiro e
  Editar conforme a permissão. **Dias do evento** (criar, editar, apagar; só `gerenciar`). Por
  tipo: a etiqueta da validade (diária do dia ou passaporte), "Vale para" no cadastro, botão
  **Virar lote agora** (só `gerenciar`), o
  lote à venda (restantes e os dois preços) ou o próximo a abrir; a tabela de lotes; editar e
  apagar lote e tipo, novo lote, novo tipo (só `gerenciar`).
- **Cupons** (`/ingressos/{id}/cupons`): lista com desconto, público, tipos, prazo e usos;
  novo, editar (com ativo), apagar (só sem uso).
- **Pedidos** (`/ingressos/{id}/pedidos`): busca por nome, CPF, código `ING-` ou número do
  pedido; filtro de situação.
- **Pedido** (`/ingressos/pedidos/{id}`): quem comprou, valor, cupom, origem; ingressos com
  código, valor (de/por), situação e entrada; trocar titular; pagamento; baixa manual do
  pendente ou expirado; cancelar os marcados (com estorno, se permitido); reenviar e-mail; PDF.
- **Emitir ingressos** (`/ingressos/{id}/emitir`): modo (pago por fora ou cortesia), sócio
  ou não sócio, titulares, forma de pagamento e cupom; preços do lote à venda.
- **Check-in** (`/ingressos/checkin`) e **Financeiro** (`/ingressos/financeiro`,
  `/ingressos/{id}/financeiro`, CSV).

## Relatório financeiro (`TicketFinanceService`)

Conta **por ingresso**: vendidos = válidos + usados; o cancelado entra só com o estorno dele.

| Bloco | Conteúdo |
|---|---|
| Totais | vendidos, entraram, cortesias, valor de face (sem cortesias), descontos, pago, estornado, **recebido** (pago − estornado), cancelados |
| Por tipo e lote | tipo, lote, público (sócio/não sócio), ingressos, cortesias, descontos, cobrado |
| Por forma de pagamento | eRede (site) e cada forma de baixa manual: pedidos, pago, estornado |
| Cupons | ingressos com desconto e total abatido, por código |
| CSV | um ingresso por linha (pagos, usados e cancelados), separador `;`, com BOM para o Excel; CPF do titular mascarado |

A lista (`/ingressos/financeiro`) mostra os eventos do ano (`?ano=`), menos os rascunhos.

## API do site (`Api\TicketApiController`)

Todas pedem o `api_token` (`Authorization: Bearer`). **L** = JWT do sócio no header
`Session`. **G** = token do pedido do não sócio no header `Ticket-Order-Token`. **A** = acesso
temporário de "meus ingressos" no header `Ticket-Access-Token`. Recusas de
regra: `{message, reason, context?}` (`App\Exceptions\TicketException`).

| Método | Rota | | O que faz |
|---|---|---|---|
| GET | `/api/tickets/events` | | Publicados e encerrados, com tipos ativos, lote vigente (`price_member`, `price_non_member`, `members_only`, `remaining` — `null` = lote sem limite), `next_lot`, `capacity_left`, `sales_open`; `multi_day` e `days` (`id`, `name`, `label`, `starts_at`, `ends_at`, `capacity_left`); no tipo, `validity` (`event`/`day`/`passport`) e `day_id`. |
| GET | `/api/tickets/events/{id}` | | O mesmo, com `rules`. Rascunho e cancelado: 404 `event_not_found`. |
| POST | `/api/tickets/events/{id}/coupon-check` | | `{code, buyer_kind: member\|guest}` → o cupom (`kind`, `value`, `label`, `audience`, `type_ids`, `ends_at`); só informa. 20/min. |
| GET | `/api/tickets/my-title` | L | Pessoas do título do sócio logado: `{title, people: [{ref, name, cpf (mascarado), titular, is_me, has_ticket}]}`; `?event_id=` marca quem já tem ingresso no evento (`has_ticket`) e, no evento de vários dias, em quais dias (`days_taken`; o passaporte cobre todos). 30/min. |
| POST | `/api/tickets/events/{id}/orders` | L | Sócio: `{tickets: [{type_id, holder_ref?}], coupon_code?}` → 201 pedido (sem `holder_ref` = o próprio sócio). `{type_id}` sozinho continua aceito (um ingresso, para ele). |
| GET | `/api/tickets/my-orders` | L | Pedidos do sócio (inclusive os emitidos pela secretaria). |
| GET | `/api/tickets/orders/{id}` | L | Pedido do sócio (alheio: 404 `order_not_found`). |
| POST | `/api/tickets/orders/{id}/payment` | L | `{payment_method, paid_amount, payment_integration_id, paid_at?}` → 201 (200 no reenvio do mesmo tid). |
| DELETE | `/api/tickets/orders/{id}` | L | Desiste (só pendente). |
| GET | `/api/tickets/orders/{id}/pdf` | L | PDF dos ingressos (só pago; senão 409 `invalid_status`). |
| POST | `/api/tickets/events/{id}/guest-orders` | | Não sócio: `{buyer: {cpf, name, email, phone?}, tickets: [{type_id, holder_name, holder_cpf}], coupon_code?}` → 201 pedido **com `access_token`** (a única vez que ele sai). 10/min. |
| GET | `/api/tickets/guest-orders/{id}` | G | Pedido (token errado ou ausente: 404 `order_not_found`). |
| POST | `/api/tickets/guest-orders/{id}/payment` | G | Igual ao do sócio. |
| DELETE | `/api/tickets/guest-orders/{id}` | G | Desiste (só pendente). |
| GET | `/api/tickets/guest-orders/{id}/pdf` | G | PDF dos ingressos. |
| POST | `/api/tickets/guest-access/code` | | "Meus ingressos" do não sócio: `{cpf, email}` → manda um código de 6 dígitos ao e-mail, **só** se houver pedido de não sócio com esse CPF e e-mail (resposta sempre igual, com `expires_in_minutes`). 5/min. |
| POST | `/api/tickets/guest-access/verify` | | `{cpf, email, code}` → `{access_token, expires_at}` (acesso de 2 h). Recusas: `access_code_invalid` (`context.attempts_left`), `access_code_expired`. 10/min. |
| GET | `/api/tickets/guest-access/orders` | A | Pedidos de não sócio daquele CPF + e-mail, do mais novo ao mais antigo (até 50), **cada um com o `access_token` do pedido** — ver, pagar, desistir e PDF seguem pelas rotas `/guest-orders/{id}`. Sem acesso ou vencido: 401 `access_expired`. |
| POST | `/api/tickets/guest-orders/resend-link` | | `{cpf, email}` → reenvia o link dos pedidos ativos (até 5, de eventos não acabados). Resposta sempre igual. 5/min. |

O pedido traz `total`, `discount_total`, `coupon_code`, `courtesy`, `origin` e
`event.days`; cada ingresso, `list_price`, `discount_amount`, `amount`, `validity`, `day` (a
diária) e `entries` (cada entrada, `day_id` e `checked_in_at`). O ingresso sai com `code` e `qr_code` (SVG em data
URI) **só quando pago**; o CPF do titular e de quem compra sai mascarado.

Reasons: `event_not_found` (404), `sales_closed`, `type_not_found` (404), `sold_out` (409,
`context.type_id`), `capacity_full` (409, `context.left`, `context.day_id`), `members_only`,
`limit_per_order` (`context.max`), `already_has_ticket` (409, `context.cpf`,
`context.day_id`), `invalid_holder` (`context.index`,
`context.field`), `holder_is_member` (`context.index`), `holder_not_in_title`
(`context.index`), `member_not_found`,
`order_not_found` (404), `invalid_status` (409), `hold_expired` (409, `context.detail`),
`payment_mismatch` (`context.expected`), `paid_contact_organizer`, `coupon_invalid`,
`coupon_not_applicable`, `coupon_exhausted` (409, `context.remaining`), `access_code_invalid`
(`context.attempts_left`), `access_code_expired`, `access_expired` (401), `ticket_not_found`,
`already_used`, `ticket_not_valid`, `outside_window`, `other_event`.

Sem o header `Session`, o `JwtMiddleware` responde **400**; com JWT inválido, **401**.

### "Meus ingressos" do não sócio (`TicketGuestAccessService`, desde 28/10/2026)

O não sócio não tem conta. Para ver todos os pedidos dele no site:

1. Informa **CPF e e-mail** da compra. Se houver pedido de não sócio com os dois (e-mail sem
   diferença de maiúsculas), o Lara manda um **código de 6 dígitos** ao e-mail (template
   `emails.tickets.access-code`). A resposta ao site é sempre a mesma: quem testa CPFs não
   descobre quem comprou.
2. O código vale **10 minutos**, **uma vez**, com **5 tentativas**; pedir outro invalida o
   anterior; no máximo **5 códigos por hora** por CPF + e-mail. Guardado só como HMAC
   (`ticket_guest_access_codes`).
3. Código certo → **acesso de 2 horas**: token cifrado (CPF, e-mail, validade), sem tabela,
   no header `Ticket-Access-Token`. Ele só lista os pedidos; cada pedido sai com o próprio
   `access_token`, e o resto segue pelas rotas `/guest-orders/{id}`.

Por que o código: o QR do ingresso é a entrada no evento — saber CPF e e-mail não basta, a
pessoa tem de provar que lê o e-mail. Quem é só titular (comprou outra pessoa) não acha o
ingresso por aqui: o acesso é de quem comprou.

## E-mails (`TicketNotifier`, template `emails.tickets.status`)

| Tipo | Quando |
|---|---|
| `tickets.paid` | Pedido pago (eRede, baixa manual, emissão pelo Lara, valor zero, cortesia): lista os ingressos com os códigos, o desconto do cupom ("Cortesia" no total, se for) e, para o não sócio, o botão **Ver meus ingressos** com o link `{TICKETS_SITE_URL}/ingressos/pedido/{id}?token=…`. |
| `tickets.cancelled` | Ingressos cancelados pela equipe, com motivo e valor estornado (um e-mail por cancelamento). |
| `tickets.link` | "Perdi o link" ou reenvio pelo painel de pedido ainda pendente. |
| `tickets.access_code` | Código de 6 dígitos de "meus ingressos" do não sócio (template próprio, `emails.tickets.access-code`). |

Vai para o e-mail de quem comprou (o do MultiClubes para o sócio, ou o do cadastro do site).
Sem `TICKETS_SITE_URL`, o e-mail sai sem o link. Na emissão pelo Lara sem e-mail, entregue o
PDF (botão no pedido).

## Configuração (`config/tickets.php`)

| Variável | Padrão | Uso |
|---|---|---|
| `TICKETS_HOLD_MINUTES` | 10 | Minutos em que o pedido pendente segura o estoque. |
| `TICKETS_SITE_URL` | vazio | Endereço do site de locação, para o link do pedido do não sócio. |
| `TICKETS_CHECKIN_HOURS_BEFORE` | 3 | Horas antes do início do evento em que a entrada abre (fecha no fim). |

Agendado: `ingressos:expirar-pedidos` a cada minuto (`routes/console.php`).

## Dados de teste (`php artisan ingressos:dados-teste`)

Para homologação. **Recusa produção** (ambiente `production` ou banco `lara`). Grava direto
nas tabelas: não manda e-mail nem chama eRede ou MultiClubes. Tudo leva o prefixo
`[TESTE]`; rodar de novo apaga o anterior e recria; `--so-limpar` só apaga (com tipos, lotes,
cupons, pedidos e ingressos dos eventos `[TESTE]`).

| Evento | O que tem |
|---|---|
| Baile de Verão (vendas abertas) | Pista com o 1º lote quase esgotado (vira para o 2º), Mesa só para sócios, Infantil grátis; cupons `TESTE10` (10%), `MESA30` (R$ 30, só sócios, só Mesa) e `ESGOTADO` (uso gasto); pedidos pagos (eRede, com cupom, Pix no balcão, cortesia, grátis), pendente, expirado e um cancelado em parte com estorno |
| Show de Hoje (check-in) | Entrada aberta agora; 3 ingressos válidos (códigos e CPFs impressos no fim) e 1 já usado — para testar o check-in no Lara e no Monitor de Acesso |
| Festival de Dois Dias | Evento de vários dias: entrada do Dia 1 aberta agora, Dia 2 amanhã; diária de cada dia e passaporte (lotação 300 por dia); um passaporte pago (código impresso no fim — entra hoje e de novo amanhã) e uma diária de cada dia |
| Rascunho, Encerrado, Cancelado | Um de cada (o encerrado com ingressos usados) |

`--socio=<CPF de uma conta do site>` dá a essa pessoa um pedido pendente (prazo de 60 min)
para pagar pelo site; `--usuario=<id>` escolhe quem aparece nas emissões e baixas.

## Fases

| Fase | Conteúdo | Situação |
|---|---|---|
| F1 | Migration, models, permissões, `TicketException`, lote vigente e estoque (`TicketInventory`) | feita (27/10/2026) |
| F2 | Painel: eventos, tipos, lotes, situação, menu | feita (27/10/2026) |
| F3 | `TicketOrderService` (pedido com reserva, limites, preço), expiração agendada, API do site (sócio e não sócio) | feita (27/10/2026) |
| F4 | Pagamento eRede, baixa manual, códigos, QR, PDF, e-mails | feita (27/10/2026) |
| F5 | Cupons; pedidos no painel (busca, detalhe, baixa manual, troca de titular, cancelamento com um estorno só); emissão pelo Lara (pagamento por fora e cortesia) | feita (27/10/2026) |
| F6 | Check-in no Lara (leitor de QR, câmera) e no Monitor de Acesso (CPF e código `ING-`, histórico) | feita (27/10/2026) |
| F7 | Financeiro e CSV, `ingressos:dados-teste` | feita (27/10/2026) |
| F8 | Fechamento: docs, guia, prompt do site (v2, com cupom), prompt da portaria, suíte | feita (27/10/2026) |
| F9 | Sócio compra para todo o título (prompt v3) | feita (27/10/2026) |
| F10 | Evento de vários dias: dias, diária e passaporte, lotação e entrada por dia (prompt do site v4, nota no prompt da portaria) | feita (27/10/2026) |
| F11 | Lote sem limite de ingressos e "Virar lote agora" (prompt do site v5) | feita (28/10/2026) |
| F12 | "Meus ingressos" do não sócio no site: CPF + e-mail + código por e-mail (prompt do site v6) | feita (28/10/2026) |

## Depois do deploy

1. As migrations e o seed de permissões rodam pelo `deploy_hml.sh` / `deploy_prod.sh`.
2. Tela de **Setores**: dar `ingressos.*` aos setores pertinentes (quem organiza eventos:
   `gerenciar`, `vendas`, `cupons`, `cortesias`; secretaria: `vendas`, `pagamentos.baixa-manual`;
   portaria/entrada: `checkin`; financeiro: `financeiro`, `pagamentos.estornar`).
3. `.env`: `TICKETS_SITE_URL` (e `TICKETS_HOLD_MINUTES`, `TICKETS_CHECKIN_HOURS_BEFORE`, se
   não forem os padrões); depois `php artisan config:cache`.
4. Conferir que o scheduler roda (`ingressos:expirar-pedidos`).
5. Entregar o [prompt do site](ingressos-site-prompt.md) ao agente do Next.js e o
   [prompt da portaria](../ingressos-prompt-portaria.md) ao agente do app da guarita.
6. Em homologação, `php artisan ingressos:dados-teste` para testar o site e a portaria.

## Para implementação futura

- **Meia-entrada.** A Lei 12.933/2013 obriga meia-entrada (estudante, idoso, PcD, jovem de
  baixa renda) em eventos culturais e esportivos com cobrança, com cota de 40%. Decidido em
  27/10/2026 deixar de fora por enquanto; se entrar, vira um tipo de ingresso com comprovação
  no check-in e controle da cota.
- **Estorno em lote do evento inteiro** (ao cancelar o evento): hoje é pedido a pedido, na
  tela do pedido. Para muitos pedidos, o caminho é um job em fila, um estorno por pedido.
- **Histórico de troca de titular**: a troca não guarda o titular anterior.
