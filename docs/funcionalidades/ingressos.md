# Ingressos

> Módulo novo, feito em 27/10/2026 (fases F1 a F8). Este documento registra as decisões e o
> funcionamento; o passo a passo para quem opera está no [guia](../guias/ingressos.md), o
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
| 4 | **Ingresso nominal**: nome e CPF de quem entra — salvo a **cortesia do não sócio** emitida no Lara (decisão 17), que pode sair sem CPF. |
| 5 | **Sem meia-entrada** por enquanto (ver *Para implementação futura*). |
| 6 | **Check-in pelo Lara e pelo Monitor de Acesso** dos terceirizados. |
| 7 | **O Lara não confere o tid na eRede**: confia no que o site envia, como nos torneios. |
| 8 | **Cupom de desconto** que abate valor fixo ou porcentagem do ingresso. |
| 9 | **Emissão pelo Lara com pagamento por fora** (baixa manual no ato). |
| 10 | **Cortesia** com valor zerado. |
| 11 | **Evento de vários dias** (27/10/2026): ingresso **diário** ou **passaporte** para todos os dias. Nem todo evento de vários dias tem passaporte, mas tem de ser possível. |
| 12 | **Lugar marcado** (28/10/2026): mesas, bistrôs e camarotes escolhidos **num mapa**, sem venda dupla, no site e no Lara. A mesa é **só a mesa** (cada pessoa compra a entrada à parte); **lugar por dia**; mapas por evento e por tipo (mesas, bistrôs, camarotes) com **áreas de orientação** desenhadas (palco, piscina, banheiros); preço diferente no mesmo mapa por **categoria**; a compra da mesa **não exige** ter entrada. |
| 13 | **Ingresso físico** (28/10/2026) na impressora térmica USB de **80 mm**, pelo navegador (driver do Windows), sem programa instalado. |
| 14 | **Rastreio** (28/10/2026): quem emitiu, cobrou, trocou, cancelou e imprimiu cada pedido. "Pela web" = a venda no Lara, pela equipe; a do site aparece como "Site". |
| 15 | **Mesa cativa** (28/10/2026): o lugar guardado para uma **matrícula**; só um sócio desse título compra; **liberada para a venda geral N dias antes** do evento (N **por evento**); vale para **todos os dias**; copiar o mapa **leva as cativas junto, com aviso**; dá para tirar a cativa pela tela. |
| 16 | **Relatório de caixa** (28/10/2026): as vendas do balcão por dia, por pessoa e por forma de pagamento, para o fechamento do financeiro. **Só o relatório** (sem "fechar caixa"). Cada funcionário vê **o próprio**; o financeiro vê **o de todos**. |
| 17 | **Cortesia sem CPF e em quantidade** (28/10/2026): a cortesia não precisa de CPF e sai em grande quantidade de uma vez ("Emitir 30 cortesias"); sem CPF, o PDF é baixado ou o ingresso vai para a térmica. |

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

### Lugar marcado: mesas, bistrôs, camarotes (desde 28/10/2026)

Alguns tipos são de **lugar marcado**: quem compra escolhe no mapa a mesa, o bistrô ou o
camarote exato.

- **Mapa** (`TicketMap`, tela **Mapas** do evento): uma área de desenho de `width × height`
  unidades, com imagem de fundo opcional (a planta do salão), os **lugares** (`TicketSeat`:
  rótulo, categoria, forma redonda/quadrada/retangular, posição, tamanho, quantas pessoas
  cabem, ativo) e as **áreas de orientação** (palco, pista, piscina, bar, banheiros — só
  desenho, não clicáveis nem vendidas). Um evento pode ter vários mapas (Mesas, Bistrôs,
  Camarotes). Dá para **copiar** o mapa de outro evento (lugares, áreas, tamanho e fundo).
- **Tipo de lugar marcado**: o tipo que aponta para um mapa (`ticket_map_id`) vende os
  lugares dele — todos, ou só os da **categoria** (`seat_category`). Assim o mesmo mapa tem
  preços diferentes: "Mesa Vip" vende a categoria Vip, "Mesa" a Comum. Os preços e a
  quantidade continuam nos lotes do tipo; o tipo esgota quando acabam os lugares livres no
  dia **ou** o lote.
- **A mesa é só a mesa**: o ingresso do lugar não é entrada. Fica **fora** do "um por CPF por
  dia", da **lotação** do evento e da **portaria** (lido pelo código, aparece negado
  `ticket_seat_only`; na busca por CPF nem aparece). O titular do ingresso é o **responsável**
  pelo lugar e pode ter a entrada dele no mesmo pedido. A compra da mesa não exige entrada.
- **Lugar por dia**: no evento de vários dias, o tipo de lugar é sempre diária (não há
  passaporte de mesa); "Mesa Sábado" e "Mesa Domingo" usam o mesmo mapa e a mesma Mesa 12 é
  vendida uma vez em cada dia.
- Dois tipos não vendem o mesmo lugar no mesmo dia (mesmo mapa e categorias que se cruzam;
  categoria vazia cruza com todas): o cadastro recusa. O mapa e a categoria do tipo **não
  mudam depois de vendido**.
- **Limite por pedido**: `max_per_order` vale separado para entradas e para lugares; nos
  lugares, também para o sócio (que não tem aqui o limite natural do título).
- **Sem venda dupla** — duas proteções:
  1. a trava da linha do evento (`lockForUpdate`), a mesma do estoque;
  2. o **índice único** `(ticket_seat_id, day_key)` de `ticket_seat_claims` (`day_key` = dia
     da diária, 0 no evento sem dias). `TicketSeatService::claim()` grava a linha; se ela já é
     de outro ingresso que ainda ocupa, recusa `seat_taken`.
- **Ocupa o lugar** quem ocupa estoque (`Ticket::occupying`): pago, ou pendente no prazo. O
  pendente vencido solta o lugar na hora, mesmo antes de `ingressos:expirar-pedidos`: a linha
  morta é apagada quando outra pessoa pede o lugar. Desistência, expiração e cancelamento
  apagam a linha na hora (`release`).
- **Pagamento tardio**: o `lockPayable` prende o lugar de novo; se outra pessoa já o pegou,
  recusa `hold_expired` (com `context.seat_ids`) — **sempre**, também na baixa manual
  confirmada: nem o balcão vende o mesmo lugar duas vezes.
- No balcão (Emitir), lugar ocupado **recusa** (não é aviso); lugar desativado é aviso.
- **Trocar lugar** (tela do pedido, `ingressos.vendas`): outro lugar livre do mesmo tipo, no
  mesmo dia; tipo, lote, valor e código não mudam. A troca não guarda o lugar anterior.
- **Desenho**: lugar que já teve ingresso fica com cadeado — não é apagado e não muda de
  rótulo nem de categoria (o rótulo está nos ingressos e no PDF); desative. Mapa usado por
  tipo, ou com lugar que teve ingresso, não é apagado.
- O site recarrega o mapa a cada `refresh_after` (15 s) enquanto a pessoa escolhe; a
  garantia é a do pedido (`seat_taken`), não a tela. Não há pré-reserva ao clicar: o lugar só
  fica preso quando o pedido é criado (o mesmo prazo de 10 min do estoque).

### Mesa cativa (`TicketCaptiveService`, desde 28/10/2026)

- **O que é**: um lugar do mapa guardado para uma **matrícula** (`ticket_seats.captive_title`,
  com o nome do titular em `captive_name`, do MultiClubes). Até a liberação, só um **sócio
  desse título** compra o lugar — qualquer pessoa do título, no login dela, como na decisão 2.
  Vale para **todos os dias** do evento.
- **Liberação por evento**: `ticket_events.captive_release_days` = N. As cativas viram lugar
  comum à **0h do dia, N dias antes de `starts_at`** (`TicketEvent::captiveReleaseAt()`).
  Nulo = não liberam sozinhas (só tirando a cativa). Calculado na hora, sem rotina agendada:
  `TicketSeat::captiveFor($event)` devolve a matrícula enquanto vale, `null` depois.
- **Venda**: `pickSeat` recusa **sempre** (`seat_captive`, 422, `context.seat_id`,
  `context.released_at`) quem não é da matrícula — site e balcão, até com "Confirmar mesmo
  assim". Para vender a outra pessoa antes do prazo, tira-se a cativa. A troca de lugar
  também recusa a cativa de outra matrícula. A matrícula compara sem zeros à esquerda
  (`TicketSeat::sameTitle`). Não sócio nunca compra cativa antes da liberação.
- **No mapa**: a situação `captive` aparece para todo mundo, menos para quem é da matrícula
  (`GET /api/tickets/events/{id}/maps/{mapId}/member`, com o JWT), que a vê `free` com
  `captive_mine: true`. O painel mostra a matrícula e o nome; no balcão a equipe pode
  escolher a cativa — o servidor confere a matrícula de quem compra.
- **Tela "Mesas cativas"** (por mapa, `ingressos.gerenciar`): o prazo de liberação do evento,
  guardar um lugar para uma matrícula (o MultiClubes confere e traz o nome; fora do ar, fica
  o nome digitado), a lista com a situação (comprada pela matrícula, aguardando, liberada,
  vendida a outra pessoa) e **Tirar cativa** (uma ou todas).
- **Cópia do mapa**: as cativas vêm junto; a tela avisa quantas vieram, para conferir.
- O ★ no editor marca a cativa.

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

**Cortesia sem CPF e em quantidade** (desde 28/10/2026). Na cortesia para **não sócio**:

- o CPF de quem recebe é opcional (pode ser "Patrocinador X") e cada ingresso pode sair sem
  CPF; nome vazio vira **"Cortesia"** (`TicketOrderService::ANONYMOUS_NAME`);
- formato **"Em quantidade"** (`courtesy_format=batch`): N ingressos de um tipo de **entrada**
  de uma vez (até `MAX_COURTESY_BATCH` = 500; `batchItems`), sem nome nem CPF (ou com um nome
  para todos). Mesa não sai em quantidade (cada lugar se escolhe no mapa). O limite por pedido
  (`max_per_order`) não vale para a cortesia de não sócio; estoque e lotação continuam;
- o ingresso sem CPF fica fora do "um por CPF", não aparece na busca por CPF do check-in nem
  da portaria e **entra pelo código** (QR), uma vez;
- sem e-mail, nada é enviado: **PDF** para baixar ou **Imprimir (térmica)** no pedido;
- `tickets.holder_cpf` e `ticket_orders.buyer_cpf` aceitam nulo **só** por isso: venda (pelo
  site ou paga no balcão) continua exigindo CPF (`invalid_holder`).

### Histórico do pedido (`TicketOrderLog`, desde 28/10/2026)

`ticket_order_logs` guarda **quem, quando e o quê** de cada pedido: criado (no site ou no
Lara, cortesia, quantos, total), pago pela eRede, baixa manual (forma, valor, observação),
titular trocado (de → para, CPF mascarado), lugar trocado (de → para), ingresso cancelado
(titular, valor, estorno, motivo), estorno, desistência, expiração, e-mail reenviado e
ingresso impresso (via). Usuário nulo = o site ou a rotina de expirar. A tela do pedido
mostra o **Histórico**; a lista de pedidos filtra por **Vendido por** (quem emitiu ou recebeu
a baixa; "Site"); o CSV do financeiro tem as colunas **Vendido por** e **Recebido por**.
Pedidos de antes de 28/10/2026 não têm histórico (o `created_by`/`recorded_by` deles continua
valendo).

### Ingresso físico na impressora térmica (`TicketPrintService`, desde 28/10/2026)

Botão **Imprimir (térmica)** no pedido pago (todos os válidos) e **Imprimir só este** por
ingresso. É um POST que abre numa aba nova a página de 80 mm
(`tickets/print/thermal.blade.php`): preto no branco, 72 mm de largura útil, **um ingresso
por página** (o driver corta no fim de cada página), com evento, data, tipo, lugar (mesa:
"LUGAR — NÃO É ENTRADA"), nome, CPF (se houver), o QR e o código `ING-` (o mesmo do PDF: o
check-in não muda). A janela de impressão abre sozinha e a aba fecha depois.

Cada impressão conta a via (`tickets.print_count`, `printed_at`): da segunda em diante sai
**"2ª VIA"**, e toda impressão vai para o histórico. Só ingresso válido de pedido pago.

**No computador da impressora** (uma vez): instalar o driver da térmica, deixá-la como
**impressora padrão**, papel **80 mm** (rolo) com margem zero e "cortar no fim da página"
(ou do documento) nas preferências do driver. Para imprimir **sem a janela de impressão**,
abrir o Lara por um atalho do Chrome com `--kiosk-printing` (ex.:
`chrome.exe --kiosk-printing https://…/ingressos`): a impressão vai direto para a padrão.
No Chrome normal, na janela: destino = a térmica, margens = nenhuma, sem cabeçalho e rodapé.

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
`2026_10_28_110000_create_ticket_guest_access_codes` (códigos de "meus ingressos") e
`2026_10_28_120000_create_ticket_maps` (lugar marcado) e
`2026_10_28_140000_add_ticket_logs_print_captive` (histórico, vias impressas, mesa cativa,
CPF nulo na cortesia), idempotentes, FKs com nome curto; models em `app/Models/Ticket/`.

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
| `ticket_maps` | Mapa de lugares do evento: `name`, `background` (`ImageSource`), `width`/`height` (unidades da área de desenho), `areas` (JSON: `label`, `shape` rect/ellipse, `x`, `y`, `w`, `h`, `color`), `sort_order`. |
| `ticket_seats` | Lugar do mapa: `label` (único no mapa), `category`, `shape` (circle/square/rect), `x`/`y` (centro), `w`/`h`, `places`, `active`. |
| `ticket_seat_claims` | O lugar preso a um ingresso num dia: `ticket_seat_id`, `ticket_id` (único), `day_key` (dia ou 0). Único `(ticket_seat_id, day_key)`. |
| `ticket_types.ticket_map_id` / `seat_category` | O tipo de lugar marcado (mapa e, se for só parte, a categoria). |
| `tickets.ticket_seat_id` | O lugar do ingresso de lugar marcado. |
| `company_access_logs.ticket_id` | O ingresso conferido no Monitor de Acesso (nulo nos outros acessos). |
| `ticket_order_logs` | Histórico do pedido: `ticket_order_id`, `ticket_id` (opcional), `user_id` (nulo = site ou rotina), `action`, `details` (JSON), `created_at`. Índice `(user_id, created_at)`. |
| `tickets.print_count` / `printed_at` | Vias impressas na térmica e a última. |
| `ticket_seats.captive_title` / `captive_name` | Mesa cativa: a matrícula e o nome do titular. |
| `ticket_events.captive_release_days` | Dias antes do início em que as cativas liberam (nulo = não liberam sozinhas). |
| `tickets.holder_cpf` / `ticket_orders.buyer_cpf` | Nulos só na cortesia de não sócio sem CPF. |

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
| `ingressos.caixa.todos` | Relatório de caixa de **todas** as pessoas, com o site (eRede) e o filtro por pessoa — o fechamento do financeiro. Sem ela, quem tem `vendas`, `pagamentos.baixa-manual` ou `cortesias` vê só o próprio caixa (Gate `ver-caixa-ingressos`). |

A lista de eventos e a tela do evento abrem para quem tem **qualquer** uma delas (Gate
`acessar-ingressos`, no `AppServiceProvider`); o que cada um faz lá dentro segue a própria
permissão.

## Telas

Menu **Ingressos**: **Eventos** (`acessar-ingressos`), **Check-in** (`ingressos.checkin`),
**Financeiro** (`ingressos.financeiro`), **Caixa** (`ver-caixa-ingressos`).

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
- **Mapas** (`/ingressos/{id}/mapas`, `gerenciar`): lista, novo mapa (em branco ou copiando
  outro), apagar. **Desenhar** (`/ingressos/{id}/mapas/{mapa}`): dados do mapa (nome,
  tamanho, imagem de fundo, "Ajustar o tamanho à imagem") e o editor — ferramentas
  Selecionar e mover (Shift soma, arrastar no vazio seleciona vários, setas movem, Delete
  apaga), Lugar (clique põe um lugar com o próximo rótulo do prefixo), Área de orientação
  (arrastar desenha) e Grade de lugares (fileiras × colunas); painel com rótulo, categoria,
  forma, tamanho, lugares, ativo, alinhar e espaçar; zoom. O desenho vai inteiro em "Salvar
  desenho" (`public/js/tickets/seat-map.js`, SVG sem biblioteca).
- **Ocupação** (`/ingressos/{id}/mapas/{mapa}/ocupacao?dia=`, `vendas`): o mapa com a
  situação de cada lugar, o responsável ao passar o mouse e o pedido ao clicar; atualiza
  sozinho. Na tela do evento, o tipo de lugar mostra os lugares livres e o link.
- Na **Emissão**, o tipo de lugar ganha o botão "Escolher no mapa"; no **Pedido**, o lugar
  aparece no ingresso e há **Trocar lugar** (mapa).
- **Mesas cativas** (`/ingressos/{id}/mapas/{mapa}/cativas`, `gerenciar`): prazo de
  liberação, guardar lugar para matrícula, lista com a situação, tirar cativa (uma ou todas).
- **Caixa** (`/ingressos/caixa?de=&ate=&pessoa=`, `ver-caixa-ingressos`): ver *Relatório de
  caixa*; PDF e CSV.
- No **Pedido**: **Imprimir (térmica)** e **Histórico**; nos **Pedidos**, filtro **Vendido
  por**; na **Emissão**, o bloco **Cortesias** (uma por pessoa ou em quantidade).

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
O CSV traz também **Vendido por** (quem emitiu no Lara; "Site") e **Recebido por** (quem
registrou a baixa manual).

## Relatório de caixa (`TicketCashService`, desde 28/10/2026)

O que cada pessoa recebeu **no balcão**, por **dia** e **forma de pagamento**, juntando todos
os eventos — para o fechamento de caixa do financeiro. Período de um dia (padrão: hoje) até
62 dias.

| Lançamento | Na conta de | Quando | Valor |
|---|---|---|---|
| **Venda** = baixa manual (emissão com pagamento por fora, ou pedido do site pago na secretaria) | quem registrou (`recorded_by`) | `paid_at` | `paid_amount`, na forma (dinheiro, Pix, maquininha, transferência, outro) |
| **Cortesia** emitida no Lara | quem emitiu (`created_by`) | `paid_at` | só a quantidade |
| **Cancelamento** de ingresso pago por baixa manual | quem cancelou (`cancelled_by`) | `cancelled_at` | só informa: a devolução, se houve, foi **por fora** (baixa manual não tem estorno pelo Lara), então **não sai** do total recebido |
| **Site (eRede)** | — (bloco à parte, só na visão de todos) | `paid_at` | pago e estornado; não passa pelo caixa de ninguém |

Tela: totais do período por forma; por dia, uma linha por pessoa (cada forma, total, vendas ·
ingressos, cortesias, cancelados); o bloco do site; os **lançamentos** um a um (com link do
pedido). **PDF** com as linhas de assinatura ("quem entrega o caixa" / "quem confere") e
**CSV** (`;`, BOM). Sem `ingressos.caixa.todos`, a pessoa vê só o próprio caixa (o filtro
`pessoa` é ignorado) e não vê o bloco do site. **É só relatório**: não há "fechar caixa" nem
trava do dia.

## API do site (`Api\TicketApiController`)

Todas pedem o `api_token` (`Authorization: Bearer`). **L** = JWT do sócio no header
`Session`. **G** = token do pedido do não sócio no header `Ticket-Order-Token`. **A** = acesso
temporário de "meus ingressos" no header `Ticket-Access-Token`. Recusas de
regra: `{message, reason, context?}` (`App\Exceptions\TicketException`).

| Método | Rota | | O que faz |
|---|---|---|---|
| GET | `/api/tickets/events` | | Publicados e encerrados, com tipos ativos, lote vigente (`price_member`, `price_non_member`, `members_only`, `remaining` — `null` = lote sem limite), `next_lot`, `capacity_left`, `sales_open`; `multi_day` e `days` (`id`, `name`, `label`, `starts_at`, `ends_at`, `capacity_left`); no tipo, `validity` (`event`/`day`/`passport`) e `day_id`. |
| GET | `/api/tickets/events/{id}` | | O mesmo, com `rules`. Rascunho e cancelado: 404 `event_not_found`. Desde 28/10/2026, no evento `has_map` e `maps` (`id`, `name`); no tipo `kind` (`entry`/`seat`), `map_id`, `seat_category`, `seats_free`. |
| GET | `/api/tickets/events/{id}/maps/{mapId}` | | Lugar marcado: o mapa (`width`, `height`, `background_url`, `areas`), os tipos que vendem os lugares no dia (com o lote) e cada lugar (`id`, `label`, `category`, `shape`, `x`, `y`, `w`, `h`, `places`, `status` `free`/`held`/`sold`/`unavailable`, `type_id`), `refresh_after`. `?day_id=` obrigatório no evento de vários dias (`day_required`); mapa que nenhum tipo ativo usa: 404 `map_not_found`. 120/min. |
| POST | `/api/tickets/events/{id}/coupon-check` | | `{code, buyer_kind: member\|guest}` → o cupom (`kind`, `value`, `label`, `audience`, `type_ids`, `ends_at`); só informa. 20/min. |
| GET | `/api/tickets/events/{id}/maps/{mapId}/member` | L | O mesmo mapa visto pelo sócio do JWT (desde 28/10/2026): a mesa cativa da matrícula dele sai `free` com `captive_mine: true`; as outras cativas, `captive`. Sem `Session`: 400; JWT inválido: 401. 120/min. |
| GET | `/api/tickets/my-title` | L | Pessoas do título do sócio logado: `{title, people: [{ref, name, cpf (mascarado), titular, is_me, has_ticket}]}`; `?event_id=` marca quem já tem ingresso no evento (`has_ticket`) e, no evento de vários dias, em quais dias (`days_taken`; o passaporte cobre todos). 30/min. |
| POST | `/api/tickets/events/{id}/orders` | L | Sócio: `{tickets: [{type_id, holder_ref?, seat_id?}], coupon_code?}` → 201 pedido (sem `holder_ref` = o próprio sócio; `seat_id` no tipo de lugar marcado). `{type_id}` sozinho continua aceito (um ingresso, para ele). |
| GET | `/api/tickets/my-orders` | L | Pedidos do sócio (inclusive os emitidos pela secretaria). |
| GET | `/api/tickets/orders/{id}` | L | Pedido do sócio (alheio: 404 `order_not_found`). |
| POST | `/api/tickets/orders/{id}/payment` | L | `{payment_method, paid_amount, payment_integration_id, paid_at?}` → 201 (200 no reenvio do mesmo tid). |
| DELETE | `/api/tickets/orders/{id}` | L | Desiste (só pendente). |
| GET | `/api/tickets/orders/{id}/pdf` | L | PDF dos ingressos (só pago; senão 409 `invalid_status`). |
| POST | `/api/tickets/events/{id}/guest-orders` | | Não sócio: `{buyer: {cpf, name, email, phone?}, tickets: [{type_id, holder_name, holder_cpf, seat_id?}], coupon_code?}` → 201 pedido **com `access_token`** (a única vez que ele sai). 10/min. |
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
diária) e `entries` (cada entrada, `day_id` e `checked_in_at`); desde 28/10/2026, `kind`
(`entry`/`seat`) e `seat` (`id`, `label`, `category`, `places`, `map`). O ingresso sai com `code` e `qr_code` (SVG em data
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
`already_used`, `ticket_not_valid`, `outside_window`, `other_event`; lugar marcado:
`map_not_found` (404), `day_required`, `seat_required` (`context.index`, `context.type_id`),
`seat_not_found` (`context.index`, `context.seat_id`), `seat_wrong_type`, `seat_taken` (409,
`context.seat_ids`), `seat_not_entry` (check-in de ingresso de mesa); mesa cativa (desde
28/10/2026): `seat_captive` (422, `context.index`, `context.seat_id`, `context.released_at`).

Mapa (desde 28/10/2026): cada lugar traz também `captive` (cativa ainda guardada) e
`captive_mine`, e a situação ganha `captive`; o mapa traz `captive_release_at` (quando as
cativas viram lugar comum; `null` = só tirando).

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
| Réveillon (lugar marcado) | Mapas Mesas (Vip V1–V12 e Comum M1–M21, preços diferentes no mesmo mapa; palco, pista, piscina, bar, banheiros), Bistrôs (B1–B18) e Camarotes (C1–C6); V1 e V2 vendidas, V7 reservada (pendente por 60 min), B1 vendido, M5 com pedido expirado (livre); V3 e V4 mesas cativas das matrículas 900001 e 900002 (liberam 10 dias antes); 20 cortesias sem CPF para "Patrocinador Teste" |
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
| F13 | Lugar marcado: mapas (lugares, áreas, cópia), tipo de lugar por mapa e categoria, sem venda dupla (trava + índice único), escolha no mapa na emissão, troca de lugar, ocupação, fora da portaria (prompt do site v7, nota no prompt da portaria) | feita (28/10/2026) |
| F14 | Rastreio: histórico do pedido (`ticket_order_logs`), filtro "Vendido por", colunas no CSV | feita (28/10/2026) |
| F15 | Relatório de caixa (tela, PDF, CSV; próprio × todos, `ingressos.caixa.todos`) | feita (28/10/2026) |
| F16 | Ingresso físico na térmica de 80 mm (vias, "2ª VIA"); cortesia sem CPF e em quantidade | feita (28/10/2026) |
| F17 | Mesa cativa (matrícula, liberação N dias antes por evento, tela, cópia com aviso, `seat_captive`; prompt do site v8, nota no prompt da portaria) | feita (28/10/2026) |

## Depois do deploy

1. As migrations e o seed de permissões rodam pelo `deploy_hml.sh` / `deploy_prod.sh`.
2. Tela de **Setores**: dar `ingressos.*` aos setores pertinentes (quem organiza eventos:
   `gerenciar`, `vendas`, `cupons`, `cortesias`; secretaria: `vendas`, `pagamentos.baixa-manual`;
   portaria/entrada: `checkin`; financeiro: `financeiro`, `pagamentos.estornar`,
   `caixa.todos`).
3. `.env`: `TICKETS_SITE_URL` (e `TICKETS_HOLD_MINUTES`, `TICKETS_CHECKIN_HOURS_BEFORE`, se
   não forem os padrões); depois `php artisan config:cache`.
4. Conferir que o scheduler roda (`ingressos:expirar-pedidos`).
5. Entregar o [prompt do site](ingressos-site-prompt.md) ao agente do Next.js e o
   [prompt da portaria](../ingressos-prompt-portaria.md) ao agente do app da guarita
   (lugar marcado: a atualização v7 do site e a de 28/10/2026 da portaria; mesa cativa: a v8
   do site e a nota "cortesia sem CPF" da portaria).
6. Em homologação, `php artisan ingressos:dados-teste` para testar o site e a portaria.
7. No computador da impressora térmica: driver, impressora padrão, papel 80 mm e, se quiser
   imprimir sem a janela, o atalho do Chrome com `--kiosk-printing` (ver *Ingresso físico*).

## Para implementação futura

- **Meia-entrada.** A Lei 12.933/2013 obriga meia-entrada (estudante, idoso, PcD, jovem de
  baixa renda) em eventos culturais e esportivos com cobrança, com cota de 40%. Decidido em
  27/10/2026 deixar de fora por enquanto; se entrar, vira um tipo de ingresso com comprovação
  no check-in e controle da cota.
- **Estorno em lote do evento inteiro** (ao cancelar o evento): hoje é pedido a pedido, na
  tela do pedido. Para muitos pedidos, o caminho é um job em fila, um estorno por pedido.
- ~~**Histórico de troca de titular**~~: feito em 28/10/2026 (histórico do pedido).
- **Fechar caixa** (a pessoa informa o dinheiro contado, o Lara mostra a diferença e trava o
  dia): decidido em 28/10/2026 que, por ora, é só o relatório.
- **Impressão direta por ESC/POS** (QZ Tray), com corte por comando e sem a janela: fica
  para se o `--kiosk-printing` não atender.
