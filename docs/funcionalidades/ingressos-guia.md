# Ingressos — guia de uso

Para quem organiza eventos com venda de ingressos, para a secretaria que vende e emite, para
quem faz a entrada e para o financeiro. As regras completas estão em
[ingressos.md](ingressos.md).

## Quem faz o quê

Cada parte pede uma permissão, dada ao setor na tela de **Setores** (ou à pessoa em
**Usuários**). O menu é **Ingressos**.

| Para | Permissão (Setores › Ingressos) |
|---|---|
| Criar eventos, tipos de ingresso e lotes; desenhar os mapas de mesas | Eventos, tipos de ingresso e lotes |
| Ver a ocupação dos mapas e trocar o lugar de um ingresso | Pedidos e ingressos |
| Ver pedidos, reenviar e-mail, trocar titular, cancelar | Pedidos e ingressos |
| Emitir ingressos pagos por fora; dar baixa em pedido pendente | Baixa manual de pedido |
| Emitir cortesias | Emitir cortesia |
| Devolver o dinheiro na eRede ao cancelar | Estornar pagamento de ingresso |
| Criar cupons de desconto | Cupons de desconto |
| Fazer a entrada no evento | Check-in na entrada do evento |
| Relatório financeiro | Relatório financeiro dos ingressos |
| Ver o próprio caixa (o que você recebeu no balcão) | Pedidos e ingressos, Baixa manual ou Emitir cortesia |
| Ver o caixa de todos, para o fechamento | Relatório de caixa de todos os vendedores |
| Marcar mesas cativas | Eventos, tipos de ingresso e lotes |

## 1. Criar o evento

1. **Ingressos › Eventos › Novo evento**.
2. Preencha nome, local, descrição e as regras/informações que aparecem no site.
3. **Datas do evento** (início e fim) e **das vendas** (quando o site começa e para de vender).
   O site para de vender no fim do evento, mesmo que o fechamento das vendas seja depois.
4. **Lotação** (opcional): o máximo de pessoas somando todos os tipos de ingresso. Vazio =
   só as quantidades dos lotes limitam.
5. **Ingressos por pedido**: quantos o **não sócio** pode comprar de uma vez (para ele e
   outras pessoas). O **sócio** não tem esse limite: compra para todas as pessoas do título
   dele (titular e dependentes), uma vez cada.
6. Imagem: arquivo ou link (imgur, postimg).

O evento nasce em **rascunho** e não aparece no site.

### Evento de vários dias (diária e passaporte)

Se o evento dura mais de um dia (um festival de sábado e domingo, por exemplo), cadastre os
**dias** na tela do evento, em **Dias do evento** → **Novo dia**: início e fim de cada dia (o
dia pode passar da meia-noite) e, se quiser, um nome ("Sábado"). Os dias ficam dentro do
período do evento e um não cruza com o outro.

Com os dias cadastrados, cada tipo de ingresso tem o campo **Vale para**:

- **Diária · {dia}**: o ingresso vale só naquele dia. Crie um tipo por dia (ex.: "Pista" no
  sábado e "Pista" no domingo), cada um com os seus lotes e preços.
- **Passaporte (todos os dias)**: o ingresso vale em todos os dias, com uma entrada por dia.
  Só existe se você criar um tipo assim; se o evento não tem passaporte, não crie.

A **lotação** passa a valer **por dia**: cada dia aceita esse número de pessoas, contando as
diárias do dia e os passaportes. A mesma pessoa pode ter a diária de sábado e a de domingo,
mas não a diária junto com o passaporte. Depois que um tipo vende, o dia dele não muda (crie
outro tipo); dia com diária não pode ser apagado.

Evento de uma noite só, mesmo que passe da meia-noite, **não** precisa de dias.

## 2. Tipos de ingresso

Na tela do evento, em **Novo tipo de ingresso**: Pista, Mesa, Camarote, Infantil… A
**ordem na vitrine** decide a ordem no site. Um tipo pode ser **desativado** (sai do site);
tipo que já vendeu não pode ser apagado.

### Mesas, bistrôs e camarotes (lugar marcado)

Quando quem compra precisa escolher **a mesa certa** (ou o bistrô, o camarote), use um
**mapa**. A mesa é **só a mesa**: cada pessoa compra a entrada dela num tipo comum (Pista,
por exemplo). O ingresso da mesa fica no nome do **responsável** e não serve para entrar.

1. Na tela do evento, **Mapas › Novo mapa**: dê o nome (Mesas, Bistrôs, Camarotes). Se o
   salão já foi desenhado em outro evento, escolha **Copiar lugares e áreas de** e ele vem
   pronto. Pode pôr a **planta do salão** como imagem de fundo.
2. **Desenhar** o mapa:
   - **Área de orientação**: arraste no mapa para desenhar o palco, a pista, a piscina, o bar
     e os banheiros. Dê o nome e a cor ao lado. As áreas só orientam quem escolhe: não se
     vendem.
   - **Lugar**: escolha em **Novo lugar** o prefixo do rótulo (M para mesa, B para bistrô),
     a forma, o tamanho, quantas pessoas cabem e, se for o caso, a **categoria**; depois
     clique no mapa — cada clique põe um lugar com o próximo número (M1, M2…).
   - **Grade de lugares**: para muitas mesas de uma vez — fileiras, colunas e o espaço entre
     elas; clique em **Colocar grade no mapa** e depois no ponto onde fica a primeira mesa.
   - **Selecionar e mover**: clique num lugar ou área para editar ao lado; arraste para
     mover; Shift + clique (ou arrastar no vazio) seleciona vários, para mudar a categoria
     de todos, alinhar na fileira ou espaçar por igual. As setas do teclado movem; Delete
     apaga.
   - **Salvar desenho** no fim. Enquanto não salvar, nada muda no site.
3. Na tela do evento, crie o **tipo** (ex.: "Mesa") e, em **Lugar marcado**, escolha o
   mapa. Cadastre os lotes do tipo como em qualquer outro: o preço é do lote.

**Preço diferente no mesmo mapa** (mesa da frente do palco mais cara): dê aos lugares da
frente a categoria **Vip** e aos outros **Comum**; crie o tipo "Mesa Vip" com a categoria
Vip e o tipo "Mesa" com a categoria Comum, os dois no mesmo mapa. Quem compra vê um mapa só,
e cada mesa mostra o preço dela.

**Evento de vários dias**: o lugar é **por dia**. Crie uma "Mesa" para cada dia (diária),
todas no mesmo mapa: a Mesa 12 de sábado e a de domingo são vendidas separadas. Não existe
passaporte de mesa.

O Lara **nunca vende o mesmo lugar duas vezes no mesmo dia**: se duas pessoas escolhem a
mesma mesa ao mesmo tempo, a segunda recebe "esse lugar acabou de ser reservado" e escolhe
outro. O lugar fica guardado para quem escolheu pelo prazo de pagamento (10 minutos); se não
pagar, volta para o mapa.

Mesa que já foi vendida (cadeado no desenho) pode ser movida, mas não muda de rótulo nem de
categoria e não pode ser apagada — **desative** (desmarque **Ativo**) para tirar de venda.

**Ocupação**: no mapa, ou no tipo de mesa na tela do evento (**Ver ocupação**), veja o mapa
com cada lugar livre, reservado ou vendido. Passe o mouse para ver o responsável; clique para
abrir o pedido.

### Mesa cativa

A **mesa cativa** é a mesa guardada para um sócio: só alguém daquela **matrícula** (o titular
ou um dependente do título) compra. Se ele não comprar até o prazo, a mesa vai para a venda
geral.

1. Em **Mapas**, no mapa, clique em **Mesas cativas**.
2. **Liberação**: informe quantos **dias antes do evento** as cativas não compradas viram
   mesa comum (ex.: 7 = à 0h, 7 dias antes do início) e **Salvar prazo**. Vale para todas as
   cativas do evento. Em branco: a cativa só sai quando você tirar.
3. **Guardar lugar para uma matrícula**: escolha a mesa, digite a matrícula e **Guardar**. O
   nome do titular vem do cadastro do clube.
4. A lista mostra cada cativa: **aguardando a compra**, **comprada pela matrícula**,
   **liberada** (o prazo passou) ou **vendida a outra pessoa** (depois de liberada).
   **Tirar cativa** solta uma mesa na hora; **Tirar todas** solta as do mapa.

A cativa vale para todos os dias do evento. No site, ela aparece como **reservada** para todo
mundo, e **livre** para o sócio da matrícula, logado. No balcão, a mesa cativa só é emitida
para um sócio daquela matrícula; para vender a outra pessoa antes do prazo, tire a cativa
primeiro. No desenho, a cativa tem uma ★.

**Copiar o mapa** de outro evento traz as cativas junto: o Lara avisa quantas vieram —
confira a lista (tire as que não valem mais) e o prazo do evento novo.

## 3. Lotes

Cada tipo tem os seus lotes. Em **Novo lote em …**:

| Campo | Para quê |
|---|---|
| Nome e ordem | O site vende o lote de **menor ordem** que estiver disponível. |
| Quantidade | Quantos ingressos este lote tem. **Vazio = sem limite**: o lote não esgota e só fecha pela data ou por **Virar lote agora** (a lotação do evento, se houver, continua valendo). |
| Preço sócio | Obrigatório. |
| Preço não sócio | Vazio = este lote é **só para sócios**. |
| Abre em / Fecha em | Opcionais. Vazios = vale a janela de vendas do evento. |

**Como o lote vira:** quando o lote **esgota** ou **passa da data de fechamento**, o próximo
(pela ordem) abre na hora — se a data de abertura dele já tiver chegado. Exemplo:

| Ordem | Lote | Quantidade | Fecha em | Sócio | Não sócio |
|---|---|---|---|---|---|
| 1 | 1º lote | 100 | 10/12 23:59 | R$ 40 | R$ 70 |
| 2 | 2º lote | 150 | 20/12 23:59 | R$ 50 | R$ 90 |
| 3 | 3º lote | 100 | — | R$ 60 | R$ 110 |

Se os 100 do 1º lote acabarem no dia 5, o 2º lote abre no dia 5. Se sobrarem ingressos do
1º lote no dia 10, eles saem de venda e o 2º lote abre.

**Virar lote agora.** Para virar antes da hora (por decisão da organização, ou num lote sem
limite), use o botão **Virar lote agora**, acima dos tipos. Em **cada tipo**, o lote à venda
fecha na hora e o seguinte abre — mesmo que a data de abertura dele fosse mais tarde. O Lara
mostra antes o que vai virar (com os preços novos) e pede confirmação. Tipo que está no último
lote continua nele. Quem já está pagando um pedido mantém o preço do lote em que entrou.

Na tabela de lotes, **Ocupados** conta os pagos **e** os pedidos que ainda estão no prazo de
pagamento (10 minutos): enquanto alguém paga, o lugar fica guardado. A linha verde é o lote
à venda agora.

Mudar o preço de um lote que já vendeu vale só para as próximas vendas. Baixar a
quantidade abaixo do que já está ocupado não cancela ninguém: o lote só fica esgotado. Nos
dois casos o Lara avisa e pede **Confirmar mesmo assim**.

## 4. Cupons de desconto

Na tela do evento, **Cupons**:

1. **Código**: o que a pessoa digita no site (letras, números, hífen e sublinhado; maiúsculas
   e minúsculas valem igual). Ex.: `FESTA10`.
2. **Desconto em**: **Porcentagem** (do preço do lote, até 100) ou **Valor fixo** (em reais).
   O desconto vale **por ingresso** e nunca deixa o ingresso abaixo de zero.
3. **Público**: sócios e não sócios, só sócios ou só não sócios.
4. **Tipos de ingresso**: marque os que o cupom cobre; nenhum marcado = todos.
5. **Limite de ingressos** (opcional): quantos ingressos podem levar o desconto, somando
   todos os pedidos. Pedido que não foi pago no prazo devolve o uso.
6. **Vale a partir de / até** (opcional).

Exemplos: `SOCIO20` — 20%, só sócios, todos os tipos; `MESA50` — R$ 50, só o tipo Mesa,
limite 30.

Para parar um cupom, desmarque **Ativo**. Cupom já usado não pode ser apagado. Cupom de
**100%** deixa o ingresso de graça (o pedido já sai pago).

## 5. Publicar

**Publicar no site** exige ao menos um tipo ativo com lote ativo. Depois:

- **Encerrar evento** quando ele acabar (sai da venda; quem comprou continua vendo).
- **Cancelar evento**: o Lara avisa se há ingressos pagos — **cancelar não estorna sozinho**:
  cancele os ingressos em **Pedidos**, com ou sem estorno.
- Outras mudanças (voltar a rascunho, reabrir) ficam em **Mudar**, com aviso.

## 6. Pedidos

Na tela do evento, **Pedidos**: busque por nome, CPF, código do ingresso (`ING-…`) ou número
do pedido. No pedido:

- **Reenviar e-mail** com os ingressos (ou o link, se ainda não pagou).
- **PDF** dos ingressos pagos — para imprimir ou mandar por WhatsApp.
- **Imprimir (térmica)**: o ingresso físico, na impressora de cupom (80 mm) — ver
  *Ingresso físico* abaixo. **Imprimir só este** imprime um ingresso do pedido.
- **Trocar titular**: a pessoa não vai e passa o ingresso para outra (nome e CPF). O código
  continua o mesmo. Cada CPF só tem um ingresso no evento (no evento de vários dias, um por
  dia: a diária de cada dia ou o passaporte).
- **Trocar lugar** (mesa, bistrô, camarote): abre o mapa; clique num lugar livre do mesmo
  tipo e confirme. O valor e o código não mudam.
- **Baixa manual**: o pedido do site ficou pendente e a pessoa pagou na secretaria. Escolha a
  forma, a data e uma observação. Os ingressos saem por e-mail.
- **Cancelar ingressos**: marque quais, escreva o motivo e, se o pagamento foi pela eRede e
  você tem a permissão, marque **Estornar na eRede** — o Lara devolve a soma dos marcados de
  uma vez. Pagamento por baixa manual não tem estorno pelo Lara: a devolução é feita fora.
  Ingresso já usado não é cancelado.
- **Histórico** (no fim da tela): quem fez o quê e quando — quem emitiu, quem recebeu o
  pagamento e em qual forma, trocas de titular e de lugar, cancelamentos, estornos,
  reenvios e impressões. O que veio do site aparece como **Site**.

Na lista de **Pedidos**, o filtro **Vendido por** mostra os pedidos que uma pessoa emitiu ou
recebeu no balcão (ou só os do **Site**).

### Ingresso físico (impressora térmica)

No pedido pago, **Imprimir (térmica)** abre uma aba com os ingressos no tamanho do cupom
(80 mm), um por corte, com o QR e o código — o mesmo do PDF, que vale na entrada. A janela de
impressão abre sozinha; escolha a térmica e imprima. Se imprimir de novo, o ingresso sai com
**2ª VIA** (3ª, 4ª…), e cada impressão fica no histórico do pedido.

**Preparar o computador da impressora** (uma vez, com o TI):

1. Instale o driver da impressora térmica e deixe-a como **impressora padrão** do Windows.
2. Nas preferências do driver: papel **80 mm** (rolo), margens zero e **cortar no fim da
   página** (ou do documento).
3. Para imprimir **direto, sem a janela**, abra o Lara por um atalho do Chrome com
   `--kiosk-printing` no fim do destino (ex.: `"C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk-printing https://endereço-do-lara/ingressos`).
   Feche todas as janelas do Chrome antes de abrir pelo atalho.
4. Sem o atalho, na janela de impressão: destino = a térmica, **Margens: nenhuma**, sem
   cabeçalhos e rodapés.

## 7. Emitir ingressos pelo Lara

Em **Pedidos › Emitir ingressos** (ou **Emitir**, na tela do evento):

1. **Emissão**:
   - **Pagamento recebido por fora** — a pessoa pagou na secretaria (dinheiro, Pix,
     maquininha, transferência). Informe a forma, a data e, se quiser, uma observação e um
     cupom.
   - **Cortesia (sem cobrança)** — ingresso com valor zerado, para convidados da organização.
     Escolha no bloco **Cortesias**:
     - **Uma por pessoa** — a lista de ingressos de sempre. Para **não sócio**, o nome e o CPF
       ficam opcionais (sem nome, sai "Cortesia");
     - **Em quantidade, sem nome nem CPF** — ex.: **30 cortesias** para um patrocinador.
       Escolha o tipo (de entrada), a quantidade e, se quiser, um nome para todos. Só para
       não sócio; em **Quem compra**, o nome pode ser o da empresa ou da ação e o CPF fica em
       branco.
2. **Quem compra**:
   - **Sócio**: o CPF de quem compra (e a matrícula, se o Lara pedir). Os dados e o e-mail vêm
     do cadastro do clube. Depois, um ingresso por pessoa do título (tipo e **CPF**; o nome vem
     do cadastro; deixe o CPF vazio para o próprio sócio), com **Mais um ingresso**. Preço de
     sócio. Alguém de fora do título: o Lara avisa e pede confirmação.
   - **Não sócio**: nome, CPF, e-mail (para receber os ingressos) e telefone; depois um
     ingresso por pessoa (tipo, nome e CPF), com **Mais um ingresso**.
   Para **mesa, bistrô ou camarote**, clique em **Escolher no mapa** na linha do ingresso e
   escolha um lugar livre (o titular da linha é o responsável pela mesa). A pessoa que vai
   sentar na mesa precisa também do ingresso de entrada: ponha outra linha com o tipo de
   entrada.
3. **Emitir**. O valor é o do lote à venda agora (a tela mostra), menos o cupom. Fora do
   período de vendas, sem estoque ou acima do limite por pedido, o Lara avisa e pede
   confirmação.

O pedido já sai pago e os ingressos vão para o e-mail de quem comprou. Sem e-mail, baixe o
**PDF** ou use **Imprimir (térmica)** no pedido.

A **cortesia sem CPF** entra pelo **código** (QR) no check-in e na portaria, uma vez cada:
como não tem CPF, não aparece na busca por CPF. Quem estiver com o ingresso na mão, entra.

## 8. Check-in na entrada

**Ingressos › Check-in**:

1. Escolha o **evento** (aparecem os que estão com a entrada aberta e os próximos).
2. Com o cursor no campo, **leia o QR** do ingresso com o leitor (ele digita o código e dá
   Enter) ou **digite** o código `ING-…` ou o **CPF** de quem está entrando. No celular, o
   botão **Ler QR** usa a câmera (quando o navegador permite).
3. **Registrar entrada** (Enter) grava a entrada; **Só consultar** não grava.
4. O quadro fica **verde** (entrada liberada) ou **vermelho** (não entra) com o nome, o tipo
   e o motivo: já entrou (com a hora), ingresso pendente ou cancelado, fora do horário, ou de
   outro evento. **Confira o documento com foto**: o ingresso é pessoal.

Cada ingresso entra **uma vez**. A entrada abre 3 horas antes do início e fecha no fim do
evento; fora disso, o Lara pergunta se quer registrar mesmo assim. O contador mostra quantos
já entraram.

No evento de vários dias, o **passaporte** entra uma vez **em cada dia** e a **diária** só no
dia dela. A entrada de cada dia abre 3 horas antes do início do dia, e o contador mostra o dia
aberto (ou o próximo): diárias do dia mais passaportes.

O ingresso de **mesa, bistrô ou camarote** não é entrada: lido aqui, aparece em vermelho com
o lugar e o responsável ("não é entrada"). A pessoa entra com o ingresso de entrada dela.
Pelo CPF, só aparecem as entradas.

**Na portaria do clube** (Externos › Monitor de Acesso), o porteiro digita o CPF como sempre:
se a pessoa tiver ingresso de um evento com a entrada aberta, aparece a linha **Ingresso**.
O código `ING-…` também funciona no campo. Registrar o acesso ali também conta como a entrada
no evento — quem passou pela portaria com o ingresso não entra de novo com ele.

## 9. Financeiro

**Ingressos › Financeiro**: os eventos do ano com vendidos, cortesias, descontos, estornado e
**recebido** (pagamentos pela eRede e por baixa manual, menos estornos). No evento: os totais,
a divisão por tipo e lote (sócio e não sócio), por forma de pagamento e por cupom, e o
**CSV dos ingressos** (um por linha, abre no Excel), com **Vendido por** e **Recebido por**.

## 10. Caixa (fechamento)

**Ingressos › Caixa**: o que foi recebido **no balcão**, por **dia**, **pessoa** e **forma
de pagamento** (dinheiro, Pix, maquininha, transferência, outro), juntando todos os eventos.

- Escolha o dia (**De** e **Até** iguais) ou um período e **Ver**.
- Em cima, o total recebido e cada forma; depois, cada dia com uma linha por pessoa: quanto
  recebeu em cada forma, o total, vendas e ingressos, **cortesias** (só a quantidade) e
  **cancelados**.
- **Cancelados**: ingressos pagos no balcão e cancelados no período. Só informa — o
  dinheiro, se foi devolvido, saiu por fora do Lara, então **não é descontado** do total.
- **Lançamentos**: cada venda, cortesia e cancelamento, com o link do pedido.
- **PDF** (com espaço para a assinatura de quem entrega e de quem confere) e **CSV**.

Cada pessoa vê **só o próprio caixa**. Quem tem **Relatório de caixa de todos os
vendedores** (o financeiro) vê todo mundo, escolhe a **Pessoa** e vê também o bloco **Site
(eRede)** — o que foi pago no cartão pelo site, que não passa pelo caixa de ninguém.

Conta no caixa de quem **registrou o pagamento**: na emissão com pagamento por fora, quem
emitiu; na baixa manual de um pedido do site, quem deu a baixa. A data é a do pagamento
informada na baixa (padrão: a hora em que foi registrada).

## Como o site vende

- **Sócio**: entra com o login de sócio (titular ou dependente) e compra para **as pessoas do
  título dele** — ele, o titular e os dependentes —, pelo preço de sócio. Para alguém de fora
  do título, a pessoa compra como não sócio (ou pelo próprio login, se for sócia).
- **Não sócio**: compra **sem cadastro**, informando nome e CPF de cada pessoa. Recebe por
  e-mail os ingressos e o link do pedido. CPF de sócio não entra nesse pedido: o sócio compra
  pelo próprio login.
- Os dois podem usar **cupom** no checkout.
- **Não sócio que quer ver os ingressos de novo**: no site, em **Meus ingressos**, informa o
  CPF e o e-mail da compra e recebe um **código de 6 dígitos** no e-mail; com ele, vê todos os
  pedidos que fez. Se o código não chegar, confira se o e-mail do pedido está certo (tela do
  pedido) — a troca do e-mail é com a equipe.
- Cada **CPF** tem **um ingresso** por evento — ou, no evento de vários dias, um por dia (a
  diária do dia ou o passaporte).
- O pedido guarda o lugar por **10 minutos** para o pagamento.
- Cada ingresso tem um **código** (`ING-XXXXXXXX`) e um **QR Code**, mostrados no site, no
  e-mail e no PDF.
