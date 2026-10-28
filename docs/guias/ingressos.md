---
modulo: Ingressos
palavras: ingresso, ingressos, evento, festa, show, baile, lote, tipo de ingresso, mesa, bistrô, camarote, mapa de mesas, mesa cativa, cupom, desconto, cortesia, emitir, balcão, check-in, entrada, portaria, QR code, pedido, estorno, baixa manual, caixa, fechamento, térmica, passaporte, diária
---

# Ingressos — guia de uso

O módulo Ingressos vende entradas para os eventos do clube. A venda acontece no site do clube (sócio com o login dele, não sócio sem cadastro), e O Lara é onde a equipe monta o evento, acompanha os pedidos, emite ingressos no balcão, faz a entrada no dia e fecha o caixa.

Cada evento tem **tipos de ingresso** (Pista, Mesa, Infantil…) e cada tipo tem **lotes** com preço de sócio e de não sócio. Mesas, bistrôs e camarotes podem ser vendidos com lugar marcado, num mapa desenhado no Lara. Há cupons de desconto, cortesias e mesas cativas para sócios.

Cada ingresso tem um código (ING-XXXXXXXX) e um QR Code, e entra uma vez no evento — pelo check-in do Lara ou pela portaria do clube.

Quem usa: a organização dos eventos, a Secretaria (que vende e emite no balcão), quem faz a entrada e o Financeiro.

## Quem faz o quê

Cada parte pede uma permissão do grupo **Ingressos**. Quem libera é a TI ou a Gerência, na tela **Setores** (ou só para uma pessoa, na tela **Usuários**); o coordenador do setor pede a liberação e coloca a equipe no setor pela tela **Meu setor**.

| Para | Permissão (como aparece na tela Setores) |
|---|---|
| Criar eventos, tipos de ingresso e lotes; desenhar mapas; marcar mesas cativas | Eventos, tipos de ingresso e lotes |
| Ver pedidos, reenviar e-mail, trocar titular ou lugar, cancelar ingressos, ver ocupação | Pedidos e ingressos (consultar, vender no balcão, trocar titular, cancelar) |
| Emitir ingressos pagos por fora e dar baixa em pedido pendente | Baixa manual de pedido (pago fora da eRede) |
| Emitir cortesias | Emitir cortesia (ingresso sem cobrança) |
| Devolver o dinheiro no cartão ao cancelar | Estornar pagamento de ingresso |
| Criar e desativar cupons | Cupons de desconto (criar, editar, desativar) |
| Fazer a entrada no evento | Check-in na entrada do evento |
| Ver o relatório financeiro | Relatório financeiro dos ingressos |
| Ver o caixa de todo mundo (fechamento) | Relatório de caixa de todos os vendedores (fechamento do financeiro) |

O botão **Emitir** precisa de "Pedidos e ingressos" **e** de "Baixa manual de pedido" ou "Emitir cortesia". Cada pessoa que vende no balcão vê o **próprio caixa**; ver o de todos é do Financeiro.

## Criar um evento
<!-- telas: tickets.index -->

Na tela **Ingressos › Eventos**, clique em **Novo evento**.

1. **Nome**, **Local**, **Descrição** e **Regras e informações (aparecem no site)**.
2. **Datas**: **Início do evento**, **Fim do evento**, **Vendas abrem** e **Vendas fecham**. O site para de vender no fim do evento, mesmo que o fechamento das vendas esteja marcado para depois.
3. **Lotação do evento** (opcional): o máximo de pessoas somando todos os tipos. Vazio = só as quantidades dos lotes limitam.
4. **Ingressos por pedido (não sócio)**: quantos o não sócio compra de uma vez. O sócio não tem esse limite — compra um ingresso para cada pessoa do título dele (titular e dependentes).
5. **Imagem**: envie um **Arquivo** ou cole o **Ou link da imagem**.
6. Salve.

O evento nasce em **rascunho** e não aparece no site. Depois, na tela do evento, cadastre os tipos de ingresso e os lotes e, por fim, clique em **Publicar no site**.

### Evento de vários dias (diária e passaporte)

Para um evento que dura mais de um dia (um festival de sábado e domingo), cadastre os dias na tela do evento, em **Dias do evento › Novo dia**: **Nome (opcional)**, **Início** e **Fim**, e **Criar dia**. O dia pode passar da meia-noite, precisa estar dentro do período do evento e não pode cruzar com outro dia.

Com dias cadastrados, cada tipo de ingresso tem o campo **Vale para**: a **diária** de um dia, ou **Passaporte (todos os dias)**, que entra uma vez por dia. A lotação passa a valer **por dia**. Evento de uma noite só, mesmo passando da meia-noite, **não** precisa de dias.

## Criar tipos de ingresso e lotes
<!-- telas: tickets.index -->

Na tela do evento (**Ingressos › Eventos**, clique no evento), em **Tipos de ingresso e lotes**:

**Tipo de ingresso** — em **Novo tipo de ingresso**, dê o nome (Pista, Mesa, Infantil…), a ordem na vitrine (ordem em que aparece no site) e clique em **Criar tipo**. Desmarque **Ativo (à venda)** para tirar o tipo do site. Tipo que já vendeu não pode ser apagado: desative.

**Lote** — em **Novo lote em …**, preencha:

| Campo | Para quê |
|---|---|
| Nome e Ordem | O site vende o lote de **menor ordem** disponível. |
| Quantidade (vazio = sem limite) | Sem quantidade, o lote não esgota; fecha pela data ou por **Virar lote agora**. |
| Preço sócio | Obrigatório. |
| Preço não sócio | Vazio = lote **só para sócios**. |
| Abre em / Fecha em (opcional) | Vazios = vale a janela de vendas do evento. |

Clique em **Criar lote**. Para alterar, use **Salvar lote**; desmarque **Ativo** para tirar de venda.

**Como o lote vira:** quando o lote **esgota** ou **passa da data de fechamento**, o próximo (pela ordem) abre na hora, se a data de abertura dele já chegou. A linha verde é o lote à venda agora. **Ocupados** soma os pagos e os pedidos ainda no prazo de pagamento (10 minutos).

**Virar lote agora** (acima dos tipos) fecha o lote à venda de cada tipo e abre o seguinte, mesmo antes da data. O Lara mostra o que vai virar e pede confirmação. Quem já está pagando mantém o preço do lote em que entrou.

Mudar o preço de um lote que já vendeu vale só para as próximas vendas; baixar a quantidade abaixo do que já foi vendido não cancela ninguém. Nos dois casos o Lara avisa e pede **Confirmar mesmo assim**.

## Vender mesa, bistrô ou camarote com lugar marcado
<!-- telas: tickets.index -->

Quando quem compra precisa escolher **a mesa certa**, use um mapa. A mesa é **só a mesa**: cada pessoa que senta também compra a entrada dela num tipo comum (Pista, por exemplo). O ingresso da mesa fica no nome do responsável e **não serve para entrar**.

1. Na tela do evento (**Ingressos › Eventos**), clique em **Mapas**. Em **Novo mapa**, dê o nome (Mesas, Bistrôs…). Para aproveitar um salão já desenhado, escolha **Copiar lugares e áreas de**. Clique em **Criar mapa**.
2. Clique em **Desenhar**:
   - **Área de orientação**: arraste para marcar palco, pista, bar, banheiros. Só orientam, não são vendidas.
   - **Novo lugar**: escolha o prefixo (M, B…), forma, tamanho, quantas pessoas e a categoria; cada clique no mapa põe um lugar numerado (M1, M2…).
   - **Grade de lugares**: muitas mesas de uma vez; clique em **Colocar grade no mapa** e depois no ponto da primeira mesa.
   - Clique num lugar para editar; arraste para mover; Shift + clique seleciona vários.
   - Clique em **Salvar desenho**. Sem salvar, nada muda no site.
3. Na tela do evento, crie o tipo (ex.: "Mesa") e, em **Lugar marcado (mesa, bistrô…)**, escolha o mapa. Cadastre os lotes normalmente.

**Preço diferente no mesmo mapa:** dê aos lugares a **Categoria** (Vip, Comum) e crie um tipo para cada categoria, no mesmo mapa. Em evento de vários dias, o lugar é **por dia**: crie uma "Mesa" para cada dia (não existe passaporte de mesa).

O Lara nunca vende o mesmo lugar duas vezes no mesmo dia. Mesa já vendida (cadeado) pode ser movida, mas não muda de rótulo nem de categoria nem é apagada — desative. Para ver o mapa com cada lugar livre, reservado ou vendido, use **Ocupação** (ou **Ver ocupação** no tipo).

## Guardar mesa cativa para um sócio
<!-- telas: tickets.index -->

A **mesa cativa** é a mesa guardada para uma **matrícula**: só o titular ou um dependente daquele título consegue comprá-la. Se ninguém comprar até o prazo, ela vai para a venda geral.

Na tela do evento (**Ingressos › Eventos**), clique em **Mapas** e, no mapa, em **Mesas cativas**.

1. **Liberação**: informe quantos **dias antes do evento** as cativas não compradas viram mesa comum (ex.: 7 = à meia-noite, 7 dias antes do início) e clique em **Salvar prazo**. Vale para todas as cativas do evento. Em branco, a cativa só sai quando alguém tirar.
2. **Guardar lugar para uma matrícula**: escolha a mesa, digite a matrícula e clique em **Guardar**. O nome do titular vem do cadastro do clube.
3. A lista mostra cada cativa: aguardando a compra, comprada pela matrícula, liberada (prazo passou) ou vendida a outra pessoa.
4. **Tirar cativa** solta uma mesa na hora; **Tirar todas** solta todas do mapa.

A cativa vale para todos os dias do evento. No site, ela aparece **reservada** para todo mundo e **livre** só para o sócio daquela matrícula, logado. No balcão, só é emitida para um sócio da matrícula; para vender a outra pessoa antes do prazo, tire a cativa primeiro. No desenho, a cativa tem uma ★.

Ao copiar o mapa de outro evento, as cativas vêm junto: confira a lista e o prazo do evento novo.

## Criar cupons de desconto
<!-- telas: tickets.index -->

Na tela do evento (**Ingressos › Eventos**, clique no evento), clique em **Cupons**. Em **Novo cupom**:

1. **Código**: o que a pessoa digita no site (letras, números, hífen e sublinhado; maiúsculas e minúsculas valem igual). Ex.: SOCIO10.
2. **Desconto em**: porcentagem do preço do lote (até 100) ou valor fixo em reais. O desconto vale **por ingresso** e nunca deixa o ingresso abaixo de zero.
3. **Público**: sócios e não sócios, só sócios ou só não sócios.
4. **Tipos de ingresso (nenhum marcado = todos)**.
5. **Limite de ingressos** (opcional): quantos ingressos podem levar o desconto, somando todos os pedidos. Pedido não pago no prazo devolve o uso.
6. **Vale a partir de / Vale até** (opcionais) e **Descrição** (aparece no site).
7. Clique em **Criar cupom**.

Exemplos: SOCIO20 — 20%, só sócios, todos os tipos; MESA50 — R$ 50, só o tipo Mesa, limite 30.

Para parar um cupom, desmarque **Ativo** e clique em **Salvar cupom**. Cupom já usado não pode ser apagado. Cupom de 100% deixa o ingresso de graça (o pedido já sai pago).

O cupom também pode ser usado na emissão pelo balcão, quando o pagamento foi recebido por fora.

## Publicar, encerrar ou cancelar o evento
<!-- telas: tickets.index -->

Na tela do evento (**Ingressos › Eventos**, clique no evento), o quadro **Situação** mostra se o evento está em rascunho, publicado, encerrado ou cancelado, e os botões para mudar.

- **Publicar no site**: coloca o evento à venda. Exige ao menos um tipo de ingresso ativo com lote ativo.
- **Voltar para rascunho**: tira do site sem encerrar.
- **Encerrar evento**: quando ele acabar. Sai da venda; quem comprou continua vendo os ingressos.
- **Cancelar evento**: o Lara avisa se há ingressos pagos. **Cancelar o evento não devolve o dinheiro sozinho**: cancele os ingressos em **Pedidos**, com ou sem estorno.
- Outras mudanças fora do caminho normal (reabrir um encerrado, por exemplo) ficam na lista ao lado do botão **Mudar**, e o Lara pede confirmação.

A tela também mostra o período de **Vendas** (abertas ou fechadas agora), quantos ingressos por pedido, a **Lotação** livre e quantos pedidos estão aguardando pagamento.

Só quem tem a permissão "Eventos, tipos de ingresso e lotes" vê esses botões e o **Editar** do evento.

## Consultar pedidos e atender quem comprou
<!-- telas: tickets.index -->

Na tela do evento (**Ingressos › Eventos**, clique no evento), clique em **Pedidos**. Busque por nome, CPF, código do ingresso (ING-…) ou número do pedido. O filtro **Vendido por** mostra os pedidos que uma pessoa emitiu ou recebeu no balcão (ou só os do **Site**).

No pedido:

- **Reenviar e-mail**: manda de novo os ingressos (ou o link de pagamento, se ainda não pagou).
- **PDF**: os ingressos pagos, para imprimir ou mandar por WhatsApp.
- **Imprimir (térmica)**: o ingresso físico, na impressora de cupom. **Imprimir só este** imprime um ingresso do pedido.
- **Trocar titular**: a pessoa não vai e passa o ingresso para outra (nome e CPF). O código continua o mesmo. Cada CPF só tem um ingresso no evento (no evento de vários dias, um por dia).
- **Trocar lugar** (mesa, bistrô, camarote): abre o mapa; clique num lugar livre do mesmo tipo e em **Trocar para o lugar escolhido**. Valor e código não mudam.
- **Histórico** (no fim da tela): quem emitiu, quem recebeu o pagamento e em qual forma, trocas, cancelamentos, estornos, reenvios e impressões. O que veio do site aparece como **Site**.

Baixa manual e cancelamento estão na seção "Dar baixa manual, cancelar e estornar".

## Dar baixa manual, cancelar e estornar
<!-- telas: tickets.index -->

Na tela do pedido (**Ingressos › Eventos**, clique no evento, depois em **Pedidos** e no pedido):

**Baixa manual** — o pedido do site ficou pendente e a pessoa pagou na secretaria. No bloco **Baixa manual**, escolha a **Forma de pagamento**, o **Recebido em** e, se quiser, uma **Observação (opcional)**, e clique em **Dar baixa de R$ …**. Os ingressos saem por e-mail. A baixa conta no caixa de quem a registrou. Precisa da permissão "Baixa manual de pedido".

**Cancelar ingressos** — no bloco **Cancelar ingressos**:

1. Marque quais ingressos cancelar.
2. Escreva o motivo.
3. Se o pagamento foi feito no cartão pelo site e você tem a permissão "Estornar pagamento de ingresso", marque **Estornar na eRede**: o Lara devolve de uma vez a soma dos ingressos marcados.
4. Clique em **Cancelar ingressos** e confirme.

Regras importantes:

- Pagamento registrado por **baixa manual** não tem estorno pelo Lara: se houver devolução, ela é feita fora do sistema (e o Caixa só informa o cancelamento, sem descontar do total).
- Ingresso **já usado** (que já entrou) não é cancelado.
- Cancelar o evento inteiro não cancela nem estorna os ingressos: é preciso fazer aqui, pedido a pedido.

## Emitir ingressos no balcão e cortesias
<!-- telas: tickets.index -->

Na tela do evento (**Ingressos › Eventos**, clique no evento), clique em **Emitir** (ou, em **Pedidos**, em **Emitir ingressos**).

1. **Emissão**:
   - **Pagamento recebido por fora** — a pessoa pagou na secretaria (dinheiro, Pix, maquininha, transferência). Informe a forma, a data e, se quiser, uma observação e um cupom.
   - **Cortesia (sem cobrança)** — ingresso com valor zerado. Escolha **Uma por pessoa** (no não sócio, nome e CPF ficam opcionais; sem nome, sai "Cortesia") ou **Em quantidade, sem nome nem CPF** (ex.: 30 cortesias para um patrocinador — só para não sócio).
2. **Quem compra**:
   - **Sócio**: o CPF de quem compra (e a matrícula, se o Lara pedir). Os dados vêm do cadastro do clube. Depois, um ingresso por pessoa do título (tipo e CPF; CPF vazio = o próprio sócio). Preço de sócio.
   - **Não sócio**: nome, CPF, e-mail e telefone; depois um ingresso por pessoa (tipo, nome e CPF). Preço de não sócio.
   - Use **Mais um ingresso** para acrescentar linhas.
   - Mesa, bistrô ou camarote: clique em **Escolher no mapa** na linha. Quem vai sentar precisa também da linha com o ingresso de entrada.
3. Clique em **Emitir**.

O valor é o do lote à venda agora, menos o cupom. Fora do período de vendas, sem estoque, acima do limite por pedido ou com alguém de fora do título do sócio, o Lara avisa e pede **Confirmar mesmo assim**.

O pedido já sai pago e os ingressos vão para o e-mail de quem comprou. Sem e-mail, use **PDF** ou **Imprimir (térmica)** no pedido. A cortesia sem CPF entra pelo código (QR), uma vez cada; ela não aparece na busca por CPF.

## Imprimir o ingresso na impressora térmica
<!-- telas: tickets.index -->

O ingresso físico sai na impressora de cupom (80 mm), com o QR e o código — o mesmo que vale na entrada.

**Como imprimir:** na tela do pedido pago (**Ingressos › Eventos**, clique no evento, em **Pedidos** e no pedido), clique em **Imprimir (térmica)** para todos os ingressos, ou em **Imprimir só este** na linha de um ingresso. Abre uma aba com um ingresso por corte e a janela de impressão aparece sozinha; escolha a impressora térmica e imprima.

**Segunda via:** se imprimir de novo, o ingresso sai marcado **2ª VIA** (3ª, 4ª…), e o botão da linha passa a dizer "Imprimir 2ª via". Cada impressão fica no **Histórico** do pedido.

**Preparar o computador da impressora** (uma vez, com a TI):

1. Instalar o driver da impressora térmica e deixá-la como impressora padrão do Windows.
2. Nas preferências do driver: papel 80 mm (rolo), margens zero e cortar no fim da página.
3. Para imprimir direto, sem a janela de impressão, a TI pode criar um atalho especial do Chrome para abrir O Lara.
4. Sem o atalho, na janela de impressão: destino = a térmica, **Margens: nenhuma** e sem cabeçalhos e rodapés.

Se o ingresso sair cortado ou com margens grandes, quase sempre é a configuração de margens ou de papel na janela de impressão.

## Fazer o check-in na entrada do evento
<!-- telas: tickets.checkin.index -->

Na tela **Ingressos › Check-in**:

1. Escolha o **Evento**. Aparecem os que estão com a entrada aberta e, em "Próximos (entrada ainda fechada)", os que vêm a seguir.
2. Com o cursor no campo **Código do ingresso ou CPF**, leia o QR com o leitor (ele digita o código e dá Enter) ou digite o código ING-… ou o CPF de quem está entrando. No celular, o botão **Ler QR** usa a câmera, quando o navegador permite.
3. **Registrar entrada** (ou Enter) grava a entrada. **Só consultar** mostra a situação sem gravar.
4. O quadro fica **verde** ("Entrada liberada") ou **vermelho** ("Não entra"), com o nome, o tipo e o motivo. **Confira o documento com foto**: o ingresso é pessoal.

**Regras da entrada:**

- Cada ingresso entra **uma vez**.
- A entrada abre **3 horas antes** do início e fecha no fim do evento. Fora disso, o Lara pede confirmação para registrar mesmo assim.
- No evento de vários dias, o **passaporte** entra uma vez em cada dia e a **diária** só no dia dela.
- Ingresso de **mesa, bistrô ou camarote** não é entrada: aparece em vermelho com o lugar e o responsável. Cada pessoa entra com o ingresso de entrada dela.

O contador no topo mostra quantos já entraram (no evento de vários dias, do dia aberto).

**Na portaria do clube** (Externos › Monitor de Acesso), o porteiro digita o CPF como sempre: quem tem ingresso de evento com a entrada aberta aparece na linha **Ingresso**. Registrar ali também conta como a entrada no evento.

## Ver o relatório financeiro dos ingressos
<!-- telas: tickets.finance.index -->

Na tela **Ingressos › Financeiro**, escolha o ano e clique em **Ver**. Aparecem os eventos com:

- ingressos **vendidos**, **cortesias**, **descontos** dados com cupom, valor **estornado**;
- **recebido**: pagamentos no cartão pelo site e por baixa manual, menos os estornos.

Clique num evento para o detalhe:

- os totais do evento;
- **Por tipo e lote**, separando sócio e não sócio;
- **Por forma de pagamento**;
- **Cupons** usados;
- o botão **CSV dos ingressos**: uma linha por ingresso, abre no Excel, com as colunas **Vendido por** e **Recebido por**.

Use o relatório financeiro para a visão do evento inteiro (quanto o evento arrecadou). Para conferir o dinheiro que cada pessoa recebeu no balcão num dia, use **Ingressos › Caixa**.

A tela é de quem tem a permissão "Relatório financeiro dos ingressos". O atalho **Financeiro** também aparece na tela de cada evento para quem tem essa permissão.

## Fechar o caixa dos ingressos
<!-- telas: tickets.cash.index -->

A tela **Ingressos › Caixa** mostra o que foi recebido **no balcão**, por **dia**, **pessoa** e **forma de pagamento** (dinheiro, Pix, maquininha, transferência, outro), juntando todos os eventos.

1. Escolha **De** e **Até** (iguais para um dia só) e clique em **Ver**.
2. No topo: **Recebido no balcão**, o total de cada forma, **Cortesias** (só a quantidade) e **Cancelados**.
3. Abaixo, cada dia com uma linha por pessoa: quanto recebeu em cada forma, total, vendas, ingressos, cortesias e cancelados.
4. Em **Lançamentos**, cada venda, cortesia e cancelamento, com o link do pedido.
5. Baixe o **PDF** (com espaço para a assinatura de quem entrega e de quem confere) ou o **CSV**.

**Quem vê o quê:** cada pessoa vê **só o próprio caixa** ("Você vê só o seu caixa"). Quem tem a permissão "Relatório de caixa de todos os vendedores" vê todo mundo, escolhe a **Pessoa** e vê também o bloco **Site (eRede)** — o que foi pago no cartão pelo site, que não passa pelo caixa de ninguém.

**Em qual caixa entra:** no de quem **registrou o pagamento** — na emissão com pagamento por fora, quem emitiu; na baixa manual de um pedido do site, quem deu a baixa. A data é a do pagamento informada (padrão: a hora do registro).

**Cancelados** só informam: se houve devolução de dinheiro, ela foi feita fora do Lara, então **não é descontada** do total.

## Como o site vende os ingressos

O comprador usa o site do clube; O Lara recebe os pedidos e mostra tudo em **Pedidos**.

- **Sócio**: entra com o login de sócio (titular ou dependente) e compra para as pessoas do título dele, pelo preço de sócio. Para alguém de fora do título, essa pessoa compra como não sócio.
- **Não sócio**: compra sem cadastro, informando nome e CPF de cada pessoa. Recebe por e-mail os ingressos e o link do pedido. CPF de sócio não entra nesse pedido.
- Os dois podem usar **cupom** no pagamento.
- Cada **CPF** tem um ingresso por evento (no evento de vários dias, um por dia).
- O pedido guarda o lugar por **10 minutos** para o pagamento; se não pagar, o lugar volta.
- Cada ingresso tem código (ING-XXXXXXXX) e QR Code, no site, no e-mail e no PDF.
- **Não sócio que quer ver os ingressos de novo**: no site, em **Meus ingressos**, informa o CPF e o e-mail da compra e recebe um código de 6 dígitos no e-mail.

## Dúvidas comuns

### Por que não consigo publicar o evento?
<!-- telas: tickets.index -->

A mensagem "Cadastre ao menos um tipo de ingresso ativo, com lote ativo, antes de publicar" quer dizer que falta um tipo marcado como **Ativo (à venda)** com pelo menos um lote **Ativo**. Crie ou ative o tipo e o lote na tela do evento e clique de novo em **Publicar no site**.

### Por que não consigo apagar um tipo, um lote ou um mapa?
<!-- telas: tickets.index -->

Porque já existe ingresso ligado a ele ("O tipo … já tem ingresso: desative em vez de apagar"). Desmarque **Ativo** para tirar de venda. O mapa com lugar vendido também não pode ser apagado, e um mapa usado por um tipo precisa ser desligado do tipo antes. Lugar que já teve ingresso não muda de rótulo nem de categoria: desative e crie outro.

### Apareceu "Atenção: confira antes de seguir". O que faço?

É um aviso, não um erro: o Lara encontrou algo fora do normal (fora do período de vendas, sem estoque, acima do limite por pedido, preço mudando num lote que já vendeu, alguém de fora do título do sócio). Leia o aviso. Se estiver certo, clique em **Confirmar mesmo assim**; se não, **Não fazer** e corrija.

### O check-in deu "Ingresso não encontrado"
<!-- telas: tickets.checkin.index -->

Não há ingresso **pago** com aquele código ou CPF **neste evento**. Confira se o evento escolhido no topo é o certo, se o código foi lido inteiro e se o pedido foi pago (pedido pendente não entra). Cortesia sem CPF só é encontrada pelo código (QR), nunca pelo CPF.

### O check-in ficou vermelho. Por quê?
<!-- telas: tickets.checkin.index -->

O motivo aparece no quadro: **Ingresso já utilizado** (com a hora em que entrou — inclusive se entrou pela portaria); **Ingresso não está válido** (pendente, cancelado ou expirado); **fora do horário de entrada** (a entrada abre 3 horas antes do início e fecha no fim; o Lara pergunta se quer registrar mesmo assim); ou **Ingresso de mesa/lugar: não é entrada** (a pessoa precisa do ingresso de entrada dela).

### Por que não consigo estornar o pagamento?
<!-- telas: tickets.index -->

Se o pedido foi pago por **baixa manual** (na secretaria), não há estorno pelo Lara: a devolução, se houver, é feita fora do sistema. O estorno no cartão só existe para pagamentos feitos pelo site, e pede a permissão "Estornar pagamento de ingresso" (sem ela, a caixa **Estornar na eRede** não aparece).

### A pessoa trocou de ideia: posso passar o ingresso para outra?
<!-- telas: tickets.index -->

Sim, com **Trocar titular** na tela do pedido: informe nome e CPF da nova pessoa. O código do ingresso continua o mesmo. O novo CPF não pode já ter ingresso no mesmo evento (ou no mesmo dia, no evento de vários dias).

### Não vejo o botão Emitir
<!-- telas: tickets.index -->

Emitir pede a permissão "Pedidos e ingressos" e também "Baixa manual de pedido" ou "Emitir cortesia". Se faltar uma delas, fale com o coordenador do seu setor: ele coloca você num setor que já tem a permissão (tela **Meu setor**) ou pede à TI ou à Gerência que a liberem (tela **Setores**).

### Ao guardar a mesa cativa, deu que a matrícula não tem título ativo
<!-- telas: tickets.index -->

A mensagem "A matrícula … não tem título ativo no cadastro do clube" significa que o número não corresponde a um título ativo. Confira a matrícula com a Secretaria; a mesa cativa só pode ser guardada para título ativo.

### O comprador diz que alguém pegou a mesa que ele estava escolhendo

Quando duas pessoas escolhem o mesmo lugar ao mesmo tempo, a segunda recebe "esse lugar acabou de ser reservado" e escolhe outro. O lugar fica guardado por 10 minutos para quem chegou primeiro; se ele não pagar, volta para o mapa.

### O não sócio não recebe o código de "Meus ingressos"
<!-- telas: tickets.index -->

O código vai para o e-mail informado na compra. Abra o pedido em **Pedidos** e confira se o e-mail está certo (procure também no spam). A troca do e-mail do pedido é feita pela equipe. Você também pode usar **Reenviar e-mail** ou mandar o **PDF**.

### Por que só vejo o meu caixa?
<!-- telas: tickets.cash.index -->

Cada pessoa vê o próprio caixa. Ver o caixa de todos os vendedores, escolher a pessoa e ver o bloco Site (eRede) é da permissão "Relatório de caixa de todos os vendedores", normalmente do Financeiro.
