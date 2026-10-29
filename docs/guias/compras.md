---
modulo: Compras
palavras: compras, cotação, mapa de cotação, cotar, orçamento, fornecedor, loja, preço, solicitação de compra, SC, Questor, frete, prazo de entrega, condição de pagamento, desconto, vencedor, menor preço, economia, última compra, planilha, Excel, XLSX, fechar mapa, reabrir, atualizar itens, item novo na SC, SC alterada
---

# Compras — guia de uso

O módulo Compras traz o **Mapa de Cotação**: a comparação de preços entre fornecedores que antes era feita numa planilha de Excel. O mapa nasce de uma **Solicitação de Compra (SC)** do Questor (o sistema de gestão do clube): os itens da SC viram as linhas e os fornecedores consultados viram as colunas.

O comprador digita preço, frete, prazo e condição de pagamento de cada fornecedor, o Lara calcula os totais, mostra o menor preço de cada item e a economia em relação à última compra, e o comprador escolhe de quem comprar cada item. No fim, o mapa é exportado para Excel (no mesmo layout da planilha impressa) e fechado.

O Lara só **lê** o Questor: nada do que é feito no mapa é gravado lá. Toda a cotação fica guardada no Lara, com histórico de quem mudou o quê.

Quem usa: o setor de compras / Contabilidade (Financeiro).

## Quem faz o quê

O módulo inteiro usa uma permissão só, do grupo **Compras**. Quem libera é a TI ou a Gerência, na tela **Setores** (ou só para uma pessoa, na tela **Usuários**); o coordenador do setor pede a liberação e coloca a equipe no setor pela tela **Meu setor**.

| Para | Permissão (como aparece na tela Setores) |
|---|---|
| Ver os mapas, criar mapa novo, digitar preços, escolher vencedor, exportar e fechar | Mapas de cotação e ordens de compra |
| Reabrir um mapa já fechado | Não é permissão: é cargo de **coordenador do setor Contabilidade** |

Duas regras valem para todo mundo, inclusive para quem tem acesso total ao sistema:

- **Mapa fechado ou cancelado é só leitura.** Ninguém altera preço, coluna ou decisão depois de fechar.
- **Reabrir** é só do coordenador da Contabilidade, e fica registrado no histórico do mapa.

## Criar um mapa a partir de uma solicitação de compra
<!-- telas: cotacao.mapas.previa -->

Na tela **Compras › Nova Cotação (buscar SC)** (ou no botão **Nova cotação** da lista de mapas):

1. **Ache a SC.**
   - **Sei o número**: digite em **Nº da solicitação (SC)** e clique em **Buscar solicitação**.
   - **Não sei o número**: preencha **De**, **Até** e/ou **Texto** (solicitante, observação ou item — a busca procura também na descrição dos itens) e clique em **Procurar**. Na lista de resultados, clique no número da SC.
2. **Confira a prévia**: solicitante, departamento, filial, data e os itens, com a **Última compra** de cada um (valor, data e fornecedor). Item marcado **sem cadastro** foi digitado como texto livre na SC e não tem histórico — é normal.
3. Em **Gerar o mapa**, ajuste o **Título** (vai no cabeçalho da planilha exportada) e o **Comprador**.
4. Em **A quem pedir cotação?**, marque os fornecedores que vão virar colunas. A lista traz quem já vendeu aqueles itens, com quantos itens atende e a data da última compra. **Nenhum vem marcado**; use **Filtrar fornecedores** para achar um nome.
5. Clique em **Gerar mapa com N itens**.

O mapa abre pronto para cotar. Fornecedores que não estavam na lista (inclusive quem não está no cadastro do Questor) podem ser acrescentados depois, na tela do mapa.

Se já existe um mapa aberto para a mesma SC, a tela avisa "Já existe mapa para a SC …" e oferece **Abrir o mapa existente**, para não duplicar o trabalho.

## Encontrar um mapa de cotação
<!-- telas: cotacao.mapas.index -->

A tela **Compras › Mapas de Cotação** lista todos os mapas, com: número do **Mapa**, **SC**, **Título**, **Solicitante**, **Comprador**, quantidade de **Itens** e de **Colunas** (fornecedores), **Data** e **Situação**.

**Para achar um mapa:**

1. Digite na busca o número da SC, o título, o solicitante ou o comprador.
2. Se quiser, filtre pela situação: **Rascunho**, **Em cotação**, **Fechado** ou **Cancelado**.
3. Clique no número ou no título para abrir o mapa.

**Situações:**

- **Rascunho** e **Em cotação** — o mapa está aberto: dá para digitar preços, mexer nas colunas e escolher vencedores.
- **Fechado** — a cotação terminou e o mapa virou o documento da compra. Só leitura.
- **Cancelado** — só leitura.

A lista abre mesmo quando o Questor está fora do ar: os mapas já criados ficam guardados no Lara e continuam abrindo e editáveis. Só **criar mapa novo** e **consultar o histórico de compras** dependem do Questor; quando a integração está desligada, a tela mostra o aviso "Integração com o Questor desligada".

Para começar uma cotação nova, clique em **Nova cotação**.

## Acrescentar fornecedores e definir as condições
<!-- telas: cotacao.mapas.index -->

Na tela do mapa (**Compras › Mapas de Cotação**, clique no mapa), abaixo da grade:

**Acrescentar fornecedor**

1. Em **Buscar no Questor**, digite nome, fantasia ou CNPJ e escolha da lista — ou digite o nome direto, sem buscar: o fornecedor **não precisa estar cadastrado** no Questor.
2. Confira **Nome na coluna** (obrigatório) e, se quiser, **CNPJ**, **Telefone**, **Contato** e **E-mail**.
3. Clique em **Acrescentar coluna**.

Cada mapa aceita até 10 fornecedores (o limite da planilha impressa).

**Condições por fornecedor**

Para cada coluna preencha **Frete** (ex.: CIF), **Prazo** (ex.: 3DU, CONFIRMAR), **Pagamento** (ex.: À VISTA, 28 D), **Frete R$** e **Desc. R$**. São campos livres, com sugestões. Depois clique em **Salvar todas as condições** — um clique salva todas as colunas de uma vez. Enquanto houver mudança não salva, a tela avisa "Há alterações não salvas nas condições".

O **frete em reais** e o **desconto** entram no **TOTAL** do fornecedor (não no subtotal). Esquecer de salvar muda o resultado da comparação.

**Remover coluna** apaga o fornecedor **e os preços já digitados nele**; o Lara pede confirmação.

**Acrescentar item avulso**: para algo que não veio na SC mas entra na mesma cotação, preencha **Descrição**, **Unidade** e **Qnt.** e clique em **Acrescentar**. O item aparece com o selo **avulso**.

## Digitar os preços na grade
<!-- telas: cotacao.mapas.index -->

Na tela do mapa (**Compras › Mapas de Cotação**, clique no mapa), a grade tem os itens nas linhas e os fornecedores nas colunas, com as condições (frete, prazo, pagamento) no topo de cada coluna.

**Como digitar:**

1. Clique na célula do item × fornecedor e digite o **preço unitário**.
2. **O preço é salvo sozinho**, célula a célula, logo que você para de digitar. Não existe botão "salvar tudo" para os preços — dá para cotar ao longo de vários dias sem perder nada.
3. Ao lado do preço, a situação da célula: **R$** (cotado), **NT** (o fornecedor não trabalha com o item) ou **—** (sem resposta).

**Andar pela grade com o teclado:**

- **Enter**: mesma loja, item de baixo (bom para quem está ao telefone com uma loja, descendo a lista).
- **Tab**: mesmo item, próxima loja; na última loja, vai para a primeira loja do item seguinte.
- **Shift+Tab**: o caminho ao contrário.

**O que a grade mostra:**

- o **menor preço** de cada item em destaque (empate destaca os dois);
- a **variação** em relação à última compra, em verde (mais barato) ou vermelho (mais caro);
- a cobertura de cada fornecedor ("cotou 4 de 6 itens");
- **Últ. compra** de cada item; clique na descrição do item para ver o **Histórico do item** (as compras anteriores).

**Célula vazia nunca conta como zero.** "NT" e "sem resposta" ficam fora das somas; quem não cotou um item não aparece como mais barato por isso.

## Ler os totais e escolher de quem comprar
<!-- telas: cotacao.mapas.index -->

Na tela do mapa (**Compras › Mapas de Cotação**, clique no mapa):

**Escolher o vencedor de cada item**

Em cada célula há a opção **escolher**. Clique na do fornecedor de quem vai comprar aquele item; ela passa a **escolhido ✕**. A compra pode ser dividida entre lojas. Para **desfazer**, clique de novo no escolhido: o item volta a ficar sem decisão. A decisão é salva na hora, sem recarregar a página.

**Os quadros do topo**

- **Melhor combinação**: o menor preço item a item (compra dividida), e o mesmo valor com os fretes somados. "parcial" = há item sem nenhuma cotação.
- **Melhor fornecedor único**: o mais barato entre quem cotou **todos** os itens.
- **Economia projetada**: quanto se economiza em relação à última compra (pode ser negativa).
- **Decisão registrada**: o total do que você escolheu, com mercadoria e frete/desconto separados e em quantas lojas.

**O rodapé da grade**

- **SUBTOTAL DA LOJA**: tudo o que a loja cotou, sem frete.
- **TOTAL**: subtotal + frete − desconto. Em cinza quando a loja **não cotou tudo** — aí o total não é comparável com o de quem cotou o mapa inteiro.
- **SUBTOTAL DOS ESCOLHIDOS** e **PEDIDO A ESTA LOJA**: só os itens que a loja ganhou, e esse valor com frete e desconto — é o valor do pedido àquela loja.
- **TOTAL GERAL DO PEDIDO** (melhor preço item a item) e **TOTAL DECIDIDO** (o que a sua escolha vai gastar).

Dividir a compra entre várias lojas paga um frete por loja; compare o TOTAL DECIDIDO com a melhor combinação antes de fechar.

## Atualizar o histórico de compras
<!-- telas: cotacao.mapas.index -->

Na tela do mapa (**Compras › Mapas de Cotação**, clique no mapa), a coluna **Últ. compra** mostra a última compra de cada item: o valor unitário, e ao passar o mouse, a data, o fornecedor e o número da nota.

**Esse valor é tirado no momento em que o mapa é criado e fica guardado.** Ele não muda sozinho, para que a base de comparação não mude no meio da cotação.

**Para ver as compras anteriores de um item:** clique na descrição do item. Abre o **Histórico do item**, com as últimas entradas daquele material (até dois anos para trás).

**Para atualizar a última compra de todos os itens:**

1. Clique em **Atualizar histórico**, no topo do mapa.
2. O Lara consulta o Questor de novo e regrava a última compra de cada item.
3. A mensagem diz quantos itens mudaram de referência ("Histórico atualizado: N item(ns) mudaram de referência").

Use quando uma compra nova entrou no Questor depois da criação do mapa. Lembre que isso **muda a base de comparação**: a variação percentual e a economia projetada são recalculadas. A atualização fica registrada no histórico de alterações do mapa.

**Itens sem cadastro** (digitados como texto livre na SC, com o selo **sem cadastro**) não têm histórico por código: a coluna fica com "—" e o histórico mostra "item sem cadastro — sem histórico". Isso é normal e não impede a cotação.

## Atualizar os itens quando a SC mudou
<!-- telas: cotacao.mapas.index -->

O mapa copia os itens da SC no momento em que é criado. Se depois disso o solicitante **acrescentar itens** (ou mudar a quantidade) na SC lá no Questor, o mapa não muda sozinho.

**Para trazer as mudanças da SC para o mapa:**

1. Abra o mapa (**Compras › Mapas de Cotação**, clique no mapa).
2. Clique em **Atualizar itens da SC**, no topo do mapa.
3. O Lara consulta a SC no Questor de novo e:
   - **inclui no fim da grade** os itens novos, já com a última compra de cada um;
   - **atualiza descrição, unidade e quantidade** dos itens que já estavam no mapa.
4. A mensagem diz quais itens foram incluídos e quantos foram atualizados.

O que **não** muda:

- Os **preços já digitados** e o **vencedor** escolhido de cada item continuam como estavam. Se a quantidade mudou, os totais são recalculados com a quantidade nova.
- Um item que **saiu da SC** não é apagado do mapa (ele pode ter preço digitado). A tela avisa em amarelo quais itens não estão mais na solicitação; remova-os você mesmo se não forem mais cotados.
- Um item que **você removeu do mapa** não volta. (Itens removidos antes de 29/10/2026 podem voltar uma vez — a mensagem mostra quais entraram; é só remover de novo.)
- Itens **avulsos** (acrescentados à mão no mapa) não são afetados.

O botão só aparece com o mapa em aberto (rascunho ou em cotação). A atualização fica registrada no histórico de alterações do mapa como "Itens atualizados da SC".

O botão só aparece com o mapa aberto (rascunho ou em cotação) e depende do Questor estar no ar.

## Exportar o mapa para Excel
<!-- telas: cotacao.mapas.index -->

Na tela do mapa (**Compras › Mapas de Cotação**, clique no mapa), clique em **Exportar XLSX** e escolha o layout:

- **Clássico (igual à planilha impressa)** — o padrão. É o layout que a compra imprime, assina e arquiva: mesmas linhas e colunas da planilha de sempre, com os totais em fórmula e uma aba de histórico a mais.
- **Completo (análise e decisão)** — quatro abas para decidir: a grade com última compra e economia por item, o resumo comparando comprar dividido × comprar de um só, o histórico e a decisão registrada.

**Bom saber:**

- Os totais da planilha são **fórmulas**: se alguém corrigir um preço no Excel, o total muda junto.
- No Excel, "NT" sai escrito e "sem resposta" sai em branco; nenhum dos dois conta como zero.
- No Excel, os empates de menor preço aparecem os dois em verde; quem foi escolhido está na aba de decisão (layout completo).
- Os arquivos têm nomes diferentes (o completo termina em _COMPLETO), então um não sobrescreve o outro.

A exportação pode ser feita a qualquer momento, inclusive com o mapa fechado, e fica registrada no histórico do mapa.

## Fechar e reabrir um mapa
<!-- telas: cotacao.mapas.index -->

Na tela do mapa (**Compras › Mapas de Cotação**, clique no mapa):

**Fechar**

Quando a cotação terminou e os vencedores estão escolhidos, clique em **Fechar mapa** e confirme. A partir daí o mapa é **somente leitura** para todos: a tela mostra "Mapa fechado — somente leitura". Isso protege o documento que sustenta a decisão de compra — um preço corrigido depois, sem registro, transformaria o mapa em rascunho. Exportar continua possível.

**Reabrir**

Só o **coordenador do setor Contabilidade** vê o botão **Reabrir** num mapa fechado. Reabrir devolve o mapa à situação **Em cotação**, para editar de novo. Fica registrado no histórico do mapa.

**Histórico de alterações**

No fim da tela, **Histórico de alterações** lista quem criou, importou, mudou qual preço, escolheu qual vencedor, mudou a situação e exportou — com data e hora. É ali que se responde "quem mudou este preço, e quando?".

## Dúvidas comuns

### Por que não consigo digitar preço nem escolher o vencedor?
<!-- telas: cotacao.mapas.index -->

O mapa está **fechado** ou **cancelado** — a tela mostra "Mapa … — somente leitura" e a mensagem "O mapa #N está fechado e não aceita alterações". Peça ao coordenador da Contabilidade para **Reabrir**, se for mesmo preciso mudar.

### A busca da SC deu "Solicitação … não encontrada no Questor"
<!-- telas: cotacao.mapas.previa -->

Confira o número. Se não lembra, use **Não sei o número** com o período e uma palavra da observação ou de um item. Se aparecer "A solicitação … não tem itens. Não há o que cotar", a SC está vazia no Questor e precisa ser corrigida lá.

### Apareceu "Não deu para consultar" ou "Integração com o Questor desligada"
<!-- telas: cotacao.mapas.previa, cotacao.mapas.index -->

O Lara não conseguiu falar com o Questor. Os mapas já criados continuam abrindo e editáveis; só criar mapa novo, buscar SC e consultar histórico ficam indisponíveis. Tente de novo mais tarde ou avise a TI.

### Tentei gerar o mapa e fui levado para outro mapa
<!-- telas: cotacao.mapas.previa -->

Já existia um mapa aberto para a mesma SC ("Já existe um mapa (#N, …) para a solicitação …. Você foi levado a ele."). Só pode haver um mapa aberto por SC, para duas pessoas não cotarem a mesma coisa. Continue no mapa existente.

### Não consigo acrescentar mais um fornecedor
<!-- telas: cotacao.mapas.index -->

O mapa já tem o número máximo de colunas ("O mapa já tem 10 colunas de fornecedor, que é o limite configurado"). Remova uma coluna que não vai ser usada (os preços dela vão junto) e acrescente a nova.

### O fornecedor que eu quero não aparece na busca
<!-- telas: cotacao.mapas.index -->

Não precisa estar no Questor: digite o nome direto em **Nome na coluna** e clique em **Acrescentar coluna**. Na criação do mapa, a lista só sugere quem já vendeu aqueles itens.

### Por que o fornecedor mais barato não é o "Melhor fornecedor único"?
<!-- telas: cotacao.mapas.index -->

O "Melhor fornecedor único" só considera quem cotou **todos** os itens. Um fornecedor que cotou 2 de 6 itens tem um total pequeno, mas que não é comparável — por isso o TOTAL dele aparece em cinza.

### Por que a última compra de um item está vazia?
<!-- telas: cotacao.mapas.index -->

Ou o item está **sem cadastro** (foi digitado como texto livre na SC, sem código de material) e não tem histórico, ou nunca houve compra dele. Nenhum dos dois impede a cotação. Se a compra é recente, use **Atualizar histórico**.

### O total da planilha exportada é diferente do da tela

Confira se as condições (frete e desconto) foram salvas com **Salvar todas as condições** antes de exportar, e se ninguém mexeu nos preços dentro do Excel (os totais lá são fórmulas e mudam junto). Exporte de novo depois de salvar.

### Não vejo o menu Compras

O menu pede a permissão "Mapas de cotação e ordens de compra". Se não aparece, fale com o coordenador do seu setor: ele coloca você num setor que já tem a permissão (tela **Meu setor**) ou pede à TI ou à Gerência que a liberem (tela **Setores**).
