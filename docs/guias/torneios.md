---
modulo: Torneios
palavras: torneio, campeonato, beach tennis, tênis, categoria, inscrição, inscrever, balcão, baixa manual, estorno, cancelar inscrição, time, dupla, capitão, sorteio, chaveamento, chave, grupo, mata-mata, resultado, placar, W.O., código de resultados, financeiro, eRede
---

# Torneios — guia de uso

O módulo Torneios organiza os torneios do clube (beach tennis, tênis e outras modalidades de quadra) do cadastro ao resultado final. O setor de Esporte cria o torneio, escolhe as categorias, abre as inscrições, monta os times, gera o chaveamento e lança os resultados.

Os sócios se inscrevem e pagam pelo site de locação do clube (pagamento pela eRede), dentro do período de inscrição. A inscrição é individual: cada pessoa paga a sua parte, mesmo em categorias de dupla ou equipe. Pelo balcão, no Lara, a secretaria também pode inscrever e dar baixa em quem pagou fora do site.

O chaveamento e os resultados aparecem no site depois de divulgados. A equipe que está na quadra pode lançar os resultados pelo site, com um código gerado no Lara.

O menu **Torneios** tem três telas: **Torneios**, **Categorias** e **Financeiro**.

## Quem faz o quê

| Para | Permissão (como aparece na tela Setores) |
|---|---|
| Criar e editar torneios, manter o catálogo de categorias, gerar e divulgar o chaveamento | Torneios › Catálogo de categorias, torneios e chaveamento |
| Ver inscrições, inscrever pelo balcão, cancelar inscrição e montar os times | Torneios › Inscrições e times (consultar, montar, remover, cancelar) |
| Lançar e corrigir resultados, fechar grupos e gerar o código de lançamento pelo site | Torneios › Lançar e corrigir resultados; gerar o código de lançamento pelo site |
| Dar baixa em inscrição paga fora da eRede (dinheiro, Pix, maquininha) | Torneios › Baixa manual de inscrição (pago fora da eRede) |
| Estornar pela eRede o valor de uma inscrição cancelada | Torneios › Estornar pagamento de inscrição |
| Ver o relatório financeiro dos torneios | Torneios › Relatório financeiro dos torneios |

As permissões nascem sem setor e são liberadas na tela **Setores** (menu da conta, no canto superior direito), que fica com a TI e a Gerência; o coordenador do setor pede a liberação e coloca as pessoas da equipe no setor pela tela **Meu setor**. O item **Torneios** e o item **Categorias** do menu exigem a primeira permissão; os botões **Inscrições**, **Times**, **Financeiro** e os de resultado só aparecem para quem tem a permissão correspondente.

## Como cadastrar as categorias de torneio
<!-- telas: tournaments.categories.index -->

O catálogo de categorias é feito uma vez por modalidade e reaproveitado em todos os torneios dela. Na tela **Torneios › Categorias**:

1. Em **Grupo de espaços**, escolha a modalidade (por exemplo, Beach Tennis).
2. No quadro **Nova categoria em ...**, preencha **Nome** (ex.: "Feminina B"), **Pessoas por equipe** (1 = individual, 2 = dupla), **Sexo** (Masculino, Feminino ou Livre), **Idade mínima** e **Idade máxima** (vazio = sem limite).
3. Deixe marcada **Disponível para novos torneios** e clique em **Adicionar**.

Para alterar, clique em **Editar** na linha e depois em **Salvar**; para tirar do catálogo, **Remover**. Mudar ou remover aqui não mexe em torneios já criados: o torneio guarda uma cópia da categoria.

A idade é conferida na data de início do torneio, e o sexo vem do cadastro do sócio no MultiClubes. Se aparecer "Nenhum grupo de espaços esportivo cadastrado", os grupos precisam ser criados antes em **Reservas › Locais e Grupos**.

## Como criar um torneio
<!-- telas: tournaments.index -->

Na tela **Torneios › Torneios**, clique em **Novo torneio**.

1. Preencha **Nome**, escolha o **Grupo de espaços (modalidade)** — as categorias virão do catálogo dele — e, se quiser, **Descrição** e **Regulamento**.
2. Em **Datas**: **Inscrições abrem**, **Inscrições fecham** (data e hora), **Primeiro dia** e **Último dia**.
3. Em **Imagem**, envie um **Arquivo** ou cole um **Ou link da imagem**.
4. Clique em **Criar torneio**.

O torneio nasce em **Rascunho** e ainda não aparece no site. Para alterar depois, abra o torneio e clique em **Editar**. O grupo de espaços não pode ser trocado enquanto houver categorias ligadas.

## Como ligar categorias e publicar o torneio no site
<!-- telas: tournaments.index -->

Em **Torneios › Torneios**, abra o torneio. No bloco **Categorias**, em **Ligar categoria do catálogo**:

1. Escolha a **Categoria**.
2. Informe **Preço por pessoa (R$)** e **Vagas** (vazio = sem limite; a vaga é de quem paga primeiro).
3. Escolha a **Montagem dos times**: **Organizador monta**, **Sorteio** ou **Capitão convida** (o capitão monta a dupla ou equipe pelo site, convidando por CPF ou matrícula).
4. Escolha o **Formato**: **Eliminatória simples** ou **Grupos + mata-mata** (este pede **Número de grupos** e **Classificados por grupo**).
5. Informe **Sets para vencer** e marque **Disputa de 3º lugar**, se houver.
6. Clique em **Ligar ao torneio**.

Para mudar depois, use **Editar preço, vagas e disputa** na linha da categoria. Preço novo vale só para as próximas inscrições. Com tudo pronto, clique em **Publicar no site**: durante o período de inscrição, os sócios já podem se inscrever e pagar.

Categorias novas podem ser ligadas a qualquer momento, até com o torneio em andamento; nesse caso só entra quem for inscrito pelo balcão.

## Como inscrever alguém pelo balcão
<!-- telas: tournaments.index -->

Em **Torneios › Torneios**, abra o torneio e clique em **Inscrições**. No fim da tela, em **Inscrever pelo balcão**:

1. Informe **CPF** e **Matrícula** da pessoa.
2. Marque as **Categorias**.
3. Se for preciso, escreva a **Justificativa** (ex.: cadastro com sexo errado, confirmado com documento). Ela fica registrada na inscrição.
4. Clique em **Inscrever**.

A pessoa precisa ter conta no site de locação; se não tiver, crie em **Reservas › Contas do site**. Os dados de sexo, nascimento e e-mail vêm do MultiClubes.

Pelo balcão a inscrição entra a qualquer momento. Se estiver fora do prazo, sem vaga, ou com sexo ou idade fora da categoria, O Lara mostra o aviso "Atenção: confira antes de seguir"; leia e clique em **Confirmar mesmo assim** ou **Não fazer**. Fora do prazo o site não aceita mais o pagamento: registre a **Baixa manual**.

## Como dar baixa manual ou cancelar uma inscrição
<!-- telas: tournaments.index -->

Em **Torneios › Torneios**, abra o torneio e clique em **Inscrições**. A lista pode ser filtrada por **Categoria** e **Situação** (Aguardando pagamento, Paga, Desistiu, Cancelada) e buscada por nome, CPF ou matrícula.

### Baixa manual (pagou na secretaria)

1. Marque, na coluna **Marcar para baixa**, as inscrições pendentes **de uma mesma pessoa**.
2. No quadro **Baixa manual**, escolha a **Forma de pagamento** (Dinheiro, Pix direto ao clube, Cartão na secretaria (maquininha), Transferência ou depósito, Outro), a data em **Pago em** e o **Comprovante / observação**.
3. Clique em **Registrar pagamento**. O valor é a soma das inscrições marcadas.

### Cancelar

Na linha da inscrição, clique em **Cancelar**, escreva o **Motivo** e clique em **Confirmar cancelamento**. Se a inscrição foi paga pela eRede e você tem a permissão de estorno, aparece a caixa **Estornar ... pela eRede** para devolver o valor ao sócio. Pagamento por baixa manual não é estornado pelo sistema: a devolução é feita por fora.

## Como montar os times de uma categoria
<!-- telas: tournaments.index -->

Em **Torneios › Torneios**, abra o torneio e clique em **Times** na linha da categoria.

- **Sem time**: marque as pessoas, dê um **Nome do time (opcional)** (vazio usa os primeiros nomes) e clique em **Montar time com os marcados**. Ou clique em **Sortear entre os pagos**: o sorteio só fecha times inteiros, com inscrições pagas, e quem sobrar fica sem time para você decidir.
- Em cada time, **Ajustar** permite trocar o **Nome**, o **Capitão** e o **Cabeça de chave** (1, 2, 3… — usado no sorteio do chaveamento), **Incluir atleta sem time**, **Tirar** um atleta e **Desfazer time** (as inscrições continuam, sem time).

Na categoria individual, cada inscrição paga vira um time de uma pessoa. Na de **Capitão convida**, os capitães montam pelo site até o fim das inscrições, e você pode ajustar aqui. Um time aparece como **completo** quando tem todos os atletas e todos pagaram; convites ainda não respondidos aparecem como "convite pendente".

## Como gerar e divulgar o chaveamento
<!-- telas: tournaments.index -->

Em **Torneios › Torneios**, abra o torneio e clique em **Chaveamento** na linha da categoria.

1. Confira o aviso de times **em formação** (faltando gente ou pagamento): eles entram no chaveamento assim mesmo.
2. Clique em **Gerar por sorteio** (os cabeças de chave entram primeiro, na ordem, e os demais por sorteio) ou abra **Montar à mão**: na eliminatória, escolha os dois lados de cada jogo da 1ª rodada ("— passa direto" faz o outro avançar sem jogar); em grupos, escolha o grupo de cada time. Clique em **Gerar com esta montagem**.
3. Confira a chave. **Sortear de novo** e **Desfazer** funcionam a qualquer momento; se já houver resultado lançado, O Lara avisa que os resultados serão apagados.
4. Em cada jogo, **Data e local** marca dia, hora e quadra (é informativo).
5. Clique em **Divulgar no site**. **Recolher do site** tira de lá; gerar de novo também tira, até você divulgar outra vez.

São precisos ao menos 2 times. Se os times mudarem depois da geração, a tela avisa quem está fora da chave: gere de novo para acertar.

## Como lançar ou corrigir o resultado de um jogo
<!-- telas: tournaments.index -->

Em **Torneios › Torneios**, abra o torneio e o **Chaveamento** da categoria. Em cada jogo com os dois times definidos:

1. Abra **Lançar resultado**.
2. Informe os games de cada set de cada lado; **TB** é o tie-break, opcional. Ou escolha em **W.O.** quem venceu.
3. Clique em **Gravar**.

O vencedor vai sozinho para o jogo seguinte. Resultado lançado no Lara **trava** o jogo para o site (selo "Lara"); lançado pelo site, aparece o selo "site".

Para mudar, use **Corrigir resultado**, ou **Limpar resultado** para o jogo voltar a "a jogar" e destravar para o site. Se a correção muda o vencedor e o jogo seguinte já tem resultado, O Lara avisa: confirmando, o resultado seguinte (e os que dependem dele) é apagado e o novo vencedor entra no lugar. Cada jogo tem **Histórico** com quem lançou, corrigiu ou limpou.

## Como fechar um grupo e classificar para o mata-mata
<!-- telas: tournaments.index -->

No formato **Grupos + mata-mata**, em **Torneios › Torneios**, abra o torneio e o **Chaveamento** da categoria. Cada grupo mostra a classificação (jogos, vitórias, saldo de sets e de games).

1. Quando todos os jogos do grupo têm resultado, abre-se **Fechar o grupo**, já na ordem da classificação.
2. Em caso de empate, ajuste a posição de cada time como a organização decidir.
3. Clique em **Fechar e classificar**. Os primeiros colocados, até o número de classificados por grupo, entram no mata-mata.

Dá para fechar antes de todos os jogos terem resultado. Depois de fechado, **Mudar a ordem final** e **Salvar a ordem** ajustam as posições, e **Reabrir o grupo** tira os classificados do mata-mata. Se o mata-mata já tiver resultado com esses times, O Lara avisa que esses resultados serão apagados. Corrigir um jogo de grupo fechado também reabre o grupo; feche de novo depois.

## Como a equipe da quadra lança resultados pelo site
<!-- telas: tournaments.index -->

Em **Torneios › Torneios**, abra o torneio. No bloco **Lançamento de resultados pelo site**, clique em **Gerar código**.

1. Anote o código na hora: ele aparece uma vez só.
2. Passe o código para quem vai lançar os placares; essa pessoa entra na tela da equipe no site com o código e o nome dela.
3. O código vale até o fim do último dia do torneio.

**Gerar outro código** invalida o anterior (use se ninguém anotou ou se o código vazou). **Retirar** corta o lançamento pelo site na hora. Jogo com resultado lançado no Lara fica travado para o site; para o site poder lançar, limpe o resultado no Lara.

## Como iniciar, encerrar ou cancelar um torneio
<!-- telas: tournaments.index -->

Em **Torneios › Torneios**, abra o torneio. O quadro do topo mostra a **Situação** (Rascunho, Publicado, Em andamento, Encerrado, Cancelado) e os botões do próximo passo: **Publicar no site**, **Iniciar torneio**, **Encerrar torneio**, **Voltar para rascunho** ou **Cancelar torneio**.

Depois de encerrado, o site não lança mais resultado (o Lara ainda corrige). Para uma mudança fora do caminho normal, como reabrir um torneio encerrado, escolha a situação na caixa ao lado e clique em **Mudar**; O Lara avisa e pede confirmação.

**Cancelar torneio** não estorna ninguém sozinho: cancele cada inscrição em **Inscrições**, com ou sem estorno.

## Como ver o financeiro dos torneios
<!-- telas: tournaments.finance.index -->

Na tela **Torneios › Financeiro** aparecem os torneios publicados com **Recebido**, **Estornado**, **Líquido** e **A receber**, e os totais no topo. O filtro **Ano** limita pelo ano de início.

Clique no nome do torneio para ver o detalhe: **Por categoria** (pagas, recebido pela **eRede** e **Manual** separados, estornado, líquido e a receber) e a lista de **Pagamentos** com data, pessoa, categorias, forma, valor, estorno e situação. **CSV dos pagamentos** baixa a lista para abrir no Excel. O mesmo detalhe abre pelo botão **Financeiro** na tela do torneio.

A conta é por inscrição: um mesmo pagamento pode cobrir várias categorias.

## Dúvidas comuns

### O sócio não consegue entrar no site ou esqueceu a senha

As contas do site de locação são cuidadas em **Reservas › Contas do site**: lá se cria a conta, troca o e-mail e ajuda com a senha. Sem conta no site, a pessoa também não pode ser inscrita pelo balcão ("ainda não tem cadastro no site de locação").

### Apareceu "Não há sócio de título ativo no MultiClubes com esse CPF nessa matrícula."
<!-- telas: tournaments.index -->

O CPF e a matrícula informados em **Inscrever pelo balcão** não batem com um sócio ativo. Confira os dois números (a matrícula é a do título) e tente de novo.

### Apareceu "Atenção: confira antes de seguir"
<!-- telas: tournaments.index -->

A ação foge da regra normal: inscrição fora do prazo, sem vaga, sexo ou idade fora da categoria, ou mudança que apaga resultados já lançados. Leia os avisos e clique em **Confirmar mesmo assim** para seguir ou em **Não fazer** para desistir.

### O sócio diz que não há vaga para pagar
<!-- telas: tournaments.index -->

A vaga é de quem paga primeiro, não de quem se inscreve primeiro. Se a organização quiser abrir mais vagas, aumente **Vagas** em **Editar preço, vagas e disputa** na categoria, ou inscreva pelo balcão confirmando o aviso.

### Não consigo tirar uma categoria do torneio
<!-- telas: tournaments.index -->

Categoria com inscrição que passou por pagamento guarda o histórico financeiro e não sai do torneio. Cancele (e, se for o caso, estorne) as inscrições e deixe a categoria sem vagas.

### O site diz que o jogo está travado
<!-- telas: tournaments.index -->

O resultado foi lançado no Lara, que tem a palavra final. Corrija no Lara pelo **Chaveamento**, ou use **Limpar resultado** para o site poder lançar.

### O resultado não grava: "O jogo é de ... set(s) para vencer" ou "set está empatado"
<!-- telas: tournaments.index -->

O placar precisa fechar o jogo: todo set tem um vencedor e o vencedor precisa chegar ao número de sets para vencer da categoria. O tie-break, se informado, vai dos dois lados e precisa combinar com quem ganhou o set. Para jogo não disputado, use **W.O.**.

### A equipe da quadra perdeu o código
<!-- telas: tournaments.index -->

Na tela do torneio, clique em **Gerar outro código** e passe o novo. O anterior deixa de valer.

### O estorno deu erro
<!-- telas: tournaments.index -->

Quando aparece "O estorno não funcionou e a inscrição continua ativa", nada foi cancelado. Tire um print da mensagem e envie à TI.

### Não vejo o menu Torneios ou algum botão
<!-- telas: tournaments.index, tournaments.categories.index, tournaments.finance.index -->

Cada parte do módulo tem uma permissão própria (veja "Quem faz o quê"). Os itens **Torneios** e **Categorias** do menu exigem a permissão de catálogo, torneios e chaveamento; **Financeiro** exige a do relatório financeiro. Fale com o coordenador do seu setor: ele pede à TI ou à Gerência para liberar a permissão ao setor na tela **Setores**, ou a TI dá a permissão só para você em **Usuários**.

Atenção: quem tem só a permissão de inscrições e times (ou só a de resultados), sem a de catálogo, torneios e chaveamento, não tem o item **Torneios** no menu e não abre a tela do torneio, onde ficam os botões **Inscrições**, **Times** e **Chaveamento**. Na prática, quem trabalha com inscrições ou resultados precisa também dessa permissão.
