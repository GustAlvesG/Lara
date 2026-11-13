---
modulo: Financeiro
palavras: financeiro, pagamento, pagamentos, recebimento, recebido, caixa, fechamento de caixa, estorno, estornar, devolução, baixa manual, eRede, Rede, cartão, Pix, dinheiro, maquininha, relatório financeiro, quanto entrou, faturamento, CSV, planilha, freelancer, pagar freelancer, lote, contabilidade
---

# Financeiro — guia de uso

O módulo **Financeiro** junta num lugar só o dinheiro que entra e o que sai pelo Lara: os pagamentos de **reservas** (quadras e espaços sociais), de **inscrições de torneio** e de **ingressos** — pelo site (cartão ou Pix, pela eRede) ou recebidos no balcão (baixa manual) — e o **pagamento dos freelancers**.

Até 13/11/2026 essas telas ficavam espalhadas: Reservas › Pagamentos, Torneios › Financeiro, Ingressos › Financeiro e Caixa, Freelancers › Financeiro. Agora estão todas aqui. Links e favoritos antigos continuam funcionando: levam para a tela nova.

O menu **Financeiro** tem: **Visão geral**, **Recebimentos**, **Caixa**, **Reservas**, **Torneios**, **Ingressos** e **Freelancers**. Cada um só aparece para quem tem a permissão.

A **baixa manual** (marcar como pago o que foi recebido no balcão) continua sendo feita na tela de origem: no agendamento ou na reserva social, nas inscrições do torneio, no pedido de ingresso.

## Quem faz o quê

Quem libera é a TI ou a Gerência, na tela **Setores** (ou só para uma pessoa, na tela **Usuários**); o coordenador do setor pede a liberação e coloca a equipe no setor pela tela **Meu setor**. As permissões abaixo ficam no grupo **Financeiro** da tela Setores.

| Para | Permissão (como aparece na tela Setores) |
|---|---|
| Ver a Visão geral e a lista de Recebimentos de todas as origens | Financeiro › Visão geral e Recebimentos (Reservas, Torneios e Ingressos juntos) |
| Ver o caixa de todas as pessoas e o bloco do site | Financeiro › Caixa de todas as pessoas e de todas as origens, com o site (fechamento) |
| Ver os pagamentos das reservas e consultar na Rede | Financeiro › Reservas: pagamentos (lista, detalhe e extração) |
| Estornar pagamento de reserva | Financeiro › Reservas: estornar pagamento |
| Relatório financeiro dos torneios | Financeiro › Torneios: relatório financeiro |
| Relatório financeiro dos ingressos | Financeiro › Ingressos: relatório financeiro |
| Pagar os freelancers (dar baixa nos lotes) | Financeiro › Freelancers: pagamento dos lotes e baixa |

O **próprio caixa** aparece para quem recebe no balcão: quem dá baixa manual em reserva, inscrição ou pedido, e quem vende ou emite cortesia de ingresso.

## Ver o resumo do período
<!-- telas: finance.index -->

Na tela **Financeiro › Visão geral**, escolha **De** e **Até** e clique em **Ver** (sem datas, vale o mês corrente; a data é a do pagamento).

- No alto: **Recebido**, **Estornado**, **Líquido** (recebido menos estornado), quanto entrou pelo **Site (eRede)** e quanto por **Baixa manual** (recebido no balcão).
- **Por origem**: Reservas, Torneios e Ingressos, cada um com pagamentos, site, baixa manual, recebido, estornado e líquido, e a linha de total.
- **Por forma de pagamento**: cartão de crédito, débito, Pix, dinheiro, maquininha, transferência…
- **Freelancers**: quanto foi **pago no período** e quanto está **a pagar hoje** (contratos aprovados pela diretoria e ainda sem baixa), com o link para os lotes.

Clique numa origem, numa forma ou nos valores de site/baixa manual para abrir a lista de **Recebimentos** já filtrada. **CSV dos recebimentos** baixa a planilha do período.

## Consultar todos os recebimentos
<!-- telas: finance.receipts.index -->

A tela **Financeiro › Recebimentos** lista **todos** os pagamentos de Reservas, Torneios e Ingressos juntos, do mais novo ao mais antigo.

- **Busca**: nome do pagador, CPF (com ou sem pontos), nome do local/torneio/evento ou o TID da Rede.
- **Filtros**: origem, forma (inclusive "Site / eRede (todas)" e "Baixa manual (todas)"), situação (**Pago**, **Estornado em parte**, **Estornado**) e período **De**/**Até** (padrão: o mês corrente).
- No alto, os totais do que está filtrado: recebido, estornado, líquido e site × balcão.
- Cada linha mostra data, origem, pagador, a **referência** (o local, o torneio ou o evento), forma — com "Baixa manual" e quem registrou —, situação, valor pago e estornado.
- Clicar na referência abre a tela de origem, onde ficam o detalhe e o **estorno**: o pagamento da reserva, o financeiro do torneio ou o pedido de ingresso (só para quem tem acesso a essa tela).
- **Exportar CSV** baixa o que a tela está filtrando, uma linha por pagamento, para abrir no Excel.

## Fechar o caixa do balcão
<!-- telas: finance.cash.index -->

A tela **Financeiro › Caixa** mostra o que foi recebido **no balcão** — reservas, inscrições de torneio e ingressos —, por **dia**, **pessoa** e **forma de pagamento** (dinheiro, Pix, maquininha, transferência, outro).

1. Escolha **De** e **Até** (iguais para um dia só; até 62 dias) e clique em **Ver**.
2. No topo: **Recebido no balcão**, o total de cada forma, **Cortesias** de ingresso (só a quantidade) e ingressos **Cancelados**.
3. Abaixo, cada dia com uma linha por pessoa: quanto recebeu em cada forma, total, recebimentos e ingressos, cortesias e cancelados.
4. Em **Lançamentos**, cada recebimento, cortesia e cancelamento, com a origem e o link para a tela dele.
5. Baixe o **PDF** (com espaço para a assinatura de quem entrega e de quem confere) ou o **CSV**.

**Quem vê o quê:** cada pessoa vê **só o próprio caixa** ("Você vê só o seu caixa"). Quem tem a permissão do caixa de todas as pessoas escolhe a **Pessoa** e vê também o bloco **Site (eRede)**, por origem — o que foi pago pelo site, que não passa pelo caixa de ninguém.

**Em qual caixa entra:** no de quem **registrou o pagamento** (quem deu a baixa manual ou emitiu o ingresso pago por fora), na data do pagamento informada.

**Cancelados** (ingressos) só informam: se houve devolução, ela foi feita fora do Lara e **não é descontada** do total.

## Pagamentos das reservas e estorno
<!-- telas: payment.index -->

A tela **Financeiro › Reservas** lista os pagamentos das reservas (quadras e espaços sociais): os feitos no site (cartão, pela Rede) e as baixas manuais.

- **Buscar sócio** por nome ou CPF.
- **Filtros:** status e método (Cartão de crédito, Cartão de débito, "Baixa manual (todas)" ou uma forma de baixa manual específica).
- **Período:** **De** e **Até**, pela **Data do pagamento** (o padrão, para o fechamento) ou pela **Data do horário** (o que foi usado no período, pago antes ou depois).
- A lista mostra o sócio, o **local e horário** (até dois; o resto em **Detalhes**), método, data, status, valor pago e estornado.
- **Exportar CSV** baixa o que a tela está filtrando: uma linha por horário pago; o **Valor pago** e o **Estornado** aparecem só na primeira linha de cada pagamento, para a soma dar certo.

Na tela do pagamento (**Detalhes**):

- valor, método, data, ID da transação na Rede e os **Agendamentos Vinculados**;
- **Consultar transação na Rede** busca na hora portador, final do cartão, status, NSU e TID (não existe para baixa manual);
- **Estornar Pagamento** (só com a permissão de estorno): informe o valor ou deixe em branco para estornar tudo o que está disponível, clique em **Estornar** e confirme. Pode ser parcial.

O estorno aqui devolve o dinheiro, mas **não cancela** a reserva. Para cancelar e estornar juntos, use o cancelamento no agendamento ou na reserva social (módulo Reservas).

## Relatório financeiro dos torneios
<!-- telas: tournaments.finance.index -->

Na tela **Financeiro › Torneios** aparecem os torneios publicados com **Recebido**, **Estornado**, **Líquido** e **A receber**, e os totais no topo. O filtro **Ano** limita pelo ano de início.

Clique no nome do torneio para ver o detalhe: **Por categoria** (pagas, recebido pela **eRede** e **Manual** separados, estornado, líquido e a receber) e a lista de **Pagamentos** com data, pessoa, categorias, forma, valor, estorno e situação. **CSV dos pagamentos** baixa a lista para o Excel. O mesmo detalhe abre pelo botão **Financeiro** na tela do torneio.

A conta é por inscrição: um pagamento pode cobrir várias categorias. O estorno de inscrição é feito no cancelamento dela, em Torneios › Inscrições.

## Relatório financeiro dos ingressos
<!-- telas: tickets.finance.index -->

Na tela **Financeiro › Ingressos**, escolha o ano e clique em **Ver**. Aparecem os eventos com ingressos **vendidos**, **cortesias**, **descontos** de cupom, valor **estornado** e **recebido** (site e baixa manual, menos os estornos).

Clique num evento para o detalhe: os totais, **Por tipo e lote** (sócio e não sócio), **Por forma de pagamento**, **Cupons** usados e o botão **CSV dos ingressos** (uma linha por ingresso, com **Vendido por** e **Recebido por**). O atalho **Financeiro** também aparece na tela de cada evento.

Use este relatório para quanto o evento arrecadou; para o dinheiro que cada pessoa recebeu no balcão, use **Financeiro › Caixa**.

## Pagar os freelancers
<!-- telas: freelancer-services.finance -->

A tela **Financeiro › Freelancers** abre pela lista de **lotes aprovados pela diretoria**, separados em **Aguardando pagamento** e **Quitados**, com os totais **A pagar** e **Pago** no topo.

1. Clique num lote para abrir os contratos dele.
2. Confira cada linha: freelancer, período, **Chave PIX** (com botão de copiar) e **Valor**. O aviso "⚠ Diferente da chave assinada" indica que o freelancer trocou a chave depois de assinar: o pagamento vai para a chave **atual do cadastro**; confirme com ele antes.
3. Para pagar um contrato, clique em **Dar baixa** na linha e confirme.
4. Para vários, marque as caixas (o total selecionado aparece) e use **Dar baixa nos selecionados** no rodapé. **Selecionar o lote inteiro** marca todos os pendentes.

A baixa é **definitiva** e fica registrada com o seu nome; não há desfazer pelo painel.

Outras opções: **Imprimir relação** (o lote em papel, com as três aprovações), **Tabela reduzida** (só pendentes, formato folha de pagamento), **Ver todos os contratos** e o aviso de **contrato(s) sem lote**, quando houver.

## Dúvidas comuns

### Onde foi parar Reservas › Pagamentos (ou o Financeiro e o Caixa dos ingressos)?

Mudaram para o módulo **Financeiro** em 13/11/2026: Reservas › Pagamentos virou **Financeiro › Reservas**; Torneios › Financeiro, **Financeiro › Torneios**; Ingressos › Financeiro, **Financeiro › Ingressos**; Ingressos › Caixa, **Financeiro › Caixa** (agora com reservas e torneios também); Freelancers › Financeiro, **Financeiro › Freelancers**. As permissões são as mesmas de antes: quem tinha acesso continua tendo.

### Não vejo a Visão geral nem os Recebimentos
<!-- telas: finance.index, finance.receipts.index -->

Essas duas telas juntam todas as origens e têm permissão própria ("Visão geral e Recebimentos"). Peça ao coordenador do seu setor: ele pede à TI ou à Gerência para liberar ao setor na tela **Setores**.

### Por que só vejo o meu caixa?
<!-- telas: finance.cash.index -->

Cada pessoa vê o próprio caixa. Ver o de todas as pessoas, escolher a pessoa e ver o bloco Site (eRede) é da permissão do caixa de todas as pessoas, normalmente do Financeiro.

### Como estorno um pagamento de baixa manual?

Não há estorno pelo Lara: o dinheiro não passou pela Rede. Se houver devolução, ela é feita fora do sistema (dinheiro, Pix do clube).

### Por que o total do lote dos freelancers é menor que o aprovado?
<!-- telas: freelancer-services.finance -->

Os contratos que a **Gerência recusou** continuam ligados ao lote, mas não são pagáveis e ficam fora da tela e da soma. Também saem os contratos substituídos por aditivo (paga-se o aditivo). Use **Imprimir relação** para conferir contrato a contrato.

### Por que a soma da Visão geral é diferente da planilha de Reservas?
<!-- telas: finance.index, payment.index -->

A Visão geral e os Recebimentos contam **um pagamento por linha**, pela data do pagamento. A planilha de **Financeiro › Reservas** tem uma linha por **horário** (o valor pago fica só na primeira) e pode ser filtrada pela data do horário. Compare pelo mesmo período e pela data do pagamento.
