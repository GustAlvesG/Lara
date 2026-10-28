---
modulo: Placar Clube
palavras: placar, placar eletrônico, telão, jogo, partida, time, equipe, jogador, atleta, elenco, escalação, competição, campeonato, súmula, scout, categoria, modalidade, futsal, basquete, vôlei, importar jogadores, planilha
---

# Placar Clube — guia de uso

O Placar Clube é o cadastro esportivo que alimenta o placar eletrônico do clube. Aqui o setor de Esporte cadastra **equipes** (os clubes), **times** (uma equipe numa modalidade e categoria, como "Clube Sub-15 de futsal"), **jogadores**, **competições** e **jogos**, e monta a **escalação** de cada partida.

Durante o jogo, quem opera é o placar eletrônico: ele lê do Lara o jogo e os elencos, inicia a partida, registra gols, cestas, pontos, faltas, substituições e encerra. Tudo o que o placar registra volta para O Lara e vira a **súmula** e o **scout**, consultados em **Súmulas (Scout)**.

As modalidades são três: futsal, basquete e vôlei. A ordem natural do cadastro é: Equipe › Time › Jogadores (elenco) › Competição › Jogo › Escalação.

## Quem faz o quê

| Para | Permissão (como aparece na tela Setores) |
|---|---|
| Cadastrar e editar equipes, times, jogadores, elencos, competições, jogos e escalações | Placar Clube › Cadastro (equipes, times, jogadores, jogos) |
| Ver súmulas, imprimir e consultar o scout de jogadores e times | Placar Clube › Súmulas e scout |

São duas permissões separadas: quem só consulta súmulas não precisa poder alterar o cadastro. Elas são liberadas para o setor na tela **Setores** (menu da conta, no canto superior direito), que fica com a TI e a Gerência; o coordenador do setor pede a liberação e coloca as pessoas da equipe no setor pela tela **Meu setor**.

## Como funcionam os botões Cadastrar e Cadastrar e criar outro
<!-- telas: placar.equipes.index, placar.times.index, placar.jogadores.index, placar.competicoes.index, placar.jogos.index -->

Todo formulário de criação do **Placar Clube** (nova equipe, novo time, novo jogador, nova competição, novo jogo) termina com três botões:

- **Cadastrar**: grava e volta para a lista. O aviso de sucesso traz um atalho para o próximo passo: depois da equipe, "Criar um time desta equipe"; depois do time, "Montar o elenco deste time"; depois do jogador, "Abrir a ficha (foto e vídeo)"; depois da competição, "Abrir a competição"; depois do jogo, "Montar a escalação".
- **Cadastrar e criar outro**: grava e reabre o formulário vazio, já com os campos que costumam se repetir. Time volta com equipe, modalidade e categoria; jogador volta com equipe e modalidade; competição com modalidade e temporada; jogo com modalidade, categoria, competição, data e hora e local (os times nunca).
- **Cancelar**: volta para a lista sem gravar.

Apertar **Enter** no formulário equivale a **Cadastrar**.

Não existe tela separada de edição: ao abrir um registro da lista (pelo cartão, **Ver**, **Ver / Editar** ou **Editar**), a própria ficha é o formulário. Altere e clique em **Salvar Alterações**.

## Como cadastrar uma equipe
<!-- telas: placar.equipes.index -->

Equipe é o clube (a agremiação). Uma equipe pode ter times em várias modalidades e categorias.

1. Em **Placar Clube › Equipes**, clique em **Nova equipe**.
2. Preencha **Nome** (obrigatório), **Nome curto** (usado no placar quando o nome completo não cabe) e **Cidade**.
3. Clique em **Cadastrar**.

Na ficha da equipe você envia a **Logo** (jpg, png ou webp, até 8MB) com **Enviar**, ou tira com **Remover logo**. A caixa **Ativa** some com a equipe das listas de escolha sem apagá-la. Em **Times** aparece a lista de times dela e o atalho **+ Novo time**.

**Excluir Equipe** só funciona se ela não tiver times; caso contrário, aparece "Não é possível excluir ... pois possui times vinculados." Nesse caso, desmarque **Ativa**.

Na lista, a busca procura pelo nome, e **Só criadas em campo** mostra as equipes que o placar criou na hora do jogo e que precisam ser revisadas.

## Como cadastrar um time
<!-- telas: placar.times.index -->

Time é o recorte de uma equipe numa modalidade e categoria (por exemplo, a equipe "Clube" no futsal Sub-15).

1. Em **Placar Clube › Times**, clique em **Novo time** (ou use **+ Novo time** na ficha da equipe, que já vem com ela escolhida).
2. Escolha **Equipe** e **Modalidade**.
3. Digite a **Categoria** (vem "Adulto"; a lista sugere as já usadas). Grafias parecidas, como "Sub 15" e "sub15", são unificadas na que já existe.
4. **Nome de exibição** é opcional; se vazio, o nome é montado com a equipe e a categoria.
5. Clique em **Cadastrar**.

Se já existir um time daquela equipe, modalidade e categoria, O Lara não duplica: abre o existente com o aviso "Esta equipe já tem um time de ... nesta modalidade — abrimos o que já existe."

Na ficha do time dá para trocar **Categoria**, **Nome de exibição**, a caixa **Ativo** e a **Logo** (sem logo própria, o time usa a da equipe). **Excluir Time** é recusado quando o time já tem jogos: "Não é possível excluir este time pois possui jogos vinculados."

## Como cadastrar jogadores ou importar uma planilha
<!-- telas: placar.jogadores.index -->

Cada jogador é de uma equipe e uma modalidade, e pode estar em vários times dessa equipe (Sub-15 e Adulto, por exemplo).

1. Em **Placar Clube › Jogadores**, clique em **Novo jogador**.
2. Escolha **Equipe** e **Modalidade**, preencha **Nome completo**, **Nome de exibição** (o nome curto do telão) e **Data de nascimento** (serve para mostrar a idade na montagem do elenco).
3. Clique em **Cadastrar**. Depois, pelo atalho do aviso, abra a ficha para enviar **Foto** (jpg, png ou webp) e **Vídeo de apresentação** (mp4 ou webm, até 28MB, usado pelo telão na entrada em quadra).

### Importar vários de uma vez

Na mesma tela de novo jogador, abra **Importar por planilha**, clique em **Baixar modelo**, preencha uma linha por jogador (colunas Nome, Equipe e Modalidade são obrigatórias; a equipe precisa já existir e é escrita pelo nome) e envie em **Importar**. Preenchendo "Categoria do time", o jogador já entra no elenco daquele time, que é criado se não existir. A importação é tudo ou nada: com qualquer erro, nada é gravado e a tela lista os problemas.

Na lista, **Só pendentes de revisão** mostra jogadores sem equipe ou modalidade (selo "Sem equipe/modalidade"), e **Só criados em campo** os que o placar criou durante um jogo.

## Como montar o elenco de um time
<!-- telas: placar.times.index -->

Em **Placar Clube › Times**, clique em **Ver / Editar** no time. O bloco **Elenco** mostra os jogadores do ano atual.

1. Em **Adicionar ao elenco**, aparecem os jogadores da mesma equipe e modalidade que ainda não estão no elenco, com idade e data de nascimento para conferir a categoria.
2. Use o campo de filtro para achar pelo nome; **Marcar todos** marca os visíveis e **Limpar seleção** desmarca.
3. Ao marcar, a camisa (**nº**) é sugerida sozinha — pode trocar — e há o campo **posição**.
4. Clique em **Adicionar ... ao elenco**.

**Remover** tira um jogador do elenco. Se o time teve elenco no ano anterior, aparece **Copiar elenco de** (ano anterior), que traz os jogadores com os mesmos números.

Se não há ninguém disponível, a tela avisa e oferece **Cadastrar um novo**. Jogador de outra equipe ou modalidade não entra: a mensagem lista quem ficou "Fora da equipe/modalidade deste time".

## Como cadastrar uma competição
<!-- telas: placar.competicoes.index -->

Competição é o campeonato ou torneio a que um jogo pode pertencer (é opcional).

1. Em **Placar Clube › Competições**, clique em **Nova competição**.
2. Preencha **Nome**, escolha a **Modalidade** e informe a **Temporada** (vem o ano atual).
3. Clique em **Cadastrar**.

Em **Ver / Editar** você muda nome, temporada e a caixa **Ativa** (competição inativa não aparece no Novo jogo), e vê os **Jogos** dela. **Excluir Competição** é recusado se houver jogos: "Não é possível excluir ... pois possui jogos vinculados."

## Como cadastrar um jogo
<!-- telas: placar.jogos.index -->

Na tela **Placar Clube › Jogos**, clique em **Novo jogo**.

1. Escolha a **Modalidade**. As listas abaixo passam a mostrar só o que é dela.
2. Escolha a **Categoria** (opcional, mas recomendado): as listas de times mostram só os times dessa categoria. Só aparecem categorias com time ativo na modalidade.
3. Escolha **Time da casa** e **Time visitante**. Cada time aparece com equipe, modalidade e categoria entre parênteses.
4. Se for o caso, escolha a **Competição** (ou deixe "Nenhuma").
5. Preencha **Data e hora** e **Local**.
6. Clique em **Cadastrar** e use o atalho "Montar a escalação".

Os dois times precisam ser da mesma modalidade e da mesma categoria. O jogo nasce com status **agendado**.

Na ficha do jogo (**Ver** na lista) dá para mudar **Data e hora**, **Local** e **Status** (agendado, ao_vivo, encerrado, cancelado). Normalmente quem muda o status é o placar eletrônico ao iniciar e encerrar; ajuste manual só em exceção. O campo **Observações** aparece quando o placar anotou alguma divergência no encerramento. **Excluir Jogo** é recusado se o jogo já tem lances registrados.

## Como montar a escalação de um jogo
<!-- telas: placar.jogos.index -->

Em **Placar Clube › Jogos**, clique em **Ver** no jogo e depois no botão **Escalação**. A tela mostra um painel para cada time, com o elenco do ano do jogo.

1. Marque a caixa ao lado de cada jogador **relacionado** para a partida.
2. Confira ou troque o número (**nº**) da camisa.
3. Marque **Titular** para quem começa jogando e **Capitão**.
4. Clique em **Salvar escalação** no painel daquele time. Cada time é salvo separadamente.

Salvar de novo substitui a escalação anterior daquele time. Se o painel diz "Este time não tem elenco cadastrado na temporada.", monte o elenco primeiro pelo link **Cadastrar elenco**. O placar eletrônico pode ajustar a escalação no momento do jogo.

## Como ver e imprimir a súmula de um jogo
<!-- telas: placar.scout.jogos -->

Na tela **Placar Clube › Súmulas (Scout)** aparecem as partidas, com placar e situação (jogo ao vivo aparece marcado). Filtre por modalidade e status, ou busque por time, equipe, competição ou local, e clique no confronto.

A súmula mostra o placar, as parciais por período (quarter, set ou tempo), os totais de cada jogador (pontos e, fora do vôlei, faltas) e a **Linha do tempo** com o minuto, o lance, o jogador, o time e o período.

- Os botões **Completa** ou o nome de cada time recortam a súmula de um time só.
- **Jogo todo** ou um período específico mostram só aquele trecho; clicar numa parcial do placar faz o mesmo.
- **Imprimir / PDF** abre a versão para imprimir exatamente do recorte escolhido.

Lances marcados como **estornado** foram corrigidos no placar: aparecem na linha do tempo mas não contam nos totais. A súmula também abre pelo botão **Súmula** na ficha do jogo.

## Como consultar o desempenho de um jogador ou de um time
<!-- telas: placar.scout.jogos -->

Em **Placar Clube › Súmulas (Scout)**, abra a súmula e clique no nome de um jogador: aparece a atuação dele na partida, com totais e **Lances minutados** (minuto do cronômetro da partida, não do relógio). De lá, **Ver todas as partidas deste jogador** abre o histórico, que pode ser filtrado por **Temporada**.

Também dá para chegar pelo cadastro: na ficha do jogador há **Ver perfil no scout**, e na ficha do time **Ver painel no scout**, que mostra vitórias, empates, derrotas e a lista de jogos. Esses atalhos só aparecem para quem tem a permissão de súmulas e scout. Não existe ranking de artilharia: o scout é por partida.

## Dúvidas comuns

### Por que o time não aparece no Novo jogo?
<!-- telas: placar.jogos.index -->

A lista só mostra times **ativos** que combinam com a **Modalidade** e a **Categoria** escolhidas. Confira se o time está com **Ativo** marcado na ficha (Placar Clube › Times) e se a modalidade e a categoria são as dele. Escolha "Todas" na categoria para ver todos da modalidade.

### Apareceu "Os dois times precisam ser da mesma categoria"
<!-- telas: placar.jogos.index -->

Um jogo só pode ser entre times da mesma categoria (Sub-15 contra Sub-15, por exemplo) e da mesma modalidade. Escolha a categoria no Novo jogo para a lista mostrar só os times compatíveis. "Este time não é da modalidade informada." tem o mesmo motivo para a modalidade.

### Não consigo trocar a equipe ou a modalidade do jogador
<!-- telas: placar.jogadores.index -->

Quando o jogador já está em algum elenco, esses dois campos ficam travados ("Equipe e modalidade estão travadas porque o jogador já está em elenco."). Remova-o dos elencos na ficha de cada time e então troque.

### Não consigo excluir uma equipe, time, jogador, competição ou jogo
<!-- telas: placar.equipes.index, placar.times.index, placar.jogadores.index, placar.competicoes.index, placar.jogos.index -->

O Lara não apaga registros com histórico: equipe com times, time ou competição com jogos, jogador com elenco, escalação ou lances, e jogo com lances registrados. Em vez de excluir, desmarque **Ativo** (ou **Ativa**) na ficha: o registro some das listas de escolha e o histórico continua.

### A importação de jogadores deu erro e nada foi gravado
<!-- telas: placar.jogadores.index -->

A importação é tudo ou nada. A mensagem "Nenhum jogador foi importado. Corrija a planilha e envie novamente." vem com a lista dos problemas por linha. Os mais comuns: equipe que ainda não está cadastrada ou escrita diferente, colunas do modelo alteradas e campos obrigatórios vazios. Corrija e envie o arquivo inteiro de novo.

### O que é "Criado em campo"?
<!-- telas: placar.equipes.index, placar.times.index, placar.jogadores.index, placar.jogos.index -->

É equipe, time, jogador ou jogo que o placar eletrônico criou na hora, durante um jogo avulso. Use o filtro **Só criados em campo** (ou **Só criadas em campo**) nas listas para revisar nomes, completar dados e, se for o caso, juntar com o cadastro certo.

### O jogo terminou, mas o status continua ao_vivo ou o placar está diferente
<!-- telas: placar.jogos.index, placar.scout.jogos -->

Quem inicia e encerra o jogo é o placar eletrônico. Se o encerramento não chegou, ajuste o **Status** na ficha do jogo em Placar Clube › Jogos e clique em **Salvar Alterações**. Se o placar encerrou com divergência, ela aparece em **Observações**; a súmula sempre conta pelos lances registrados.

### A foto ou o vídeo do jogador não sobe
<!-- telas: placar.jogadores.index -->

Foto e logo aceitam jpg, jpeg, png ou webp, até 8MB ("Formato não aceito — envie jpg, jpeg, png ou webp."). O vídeo de apresentação aceita mp4 ou webm, até 28MB. Reduza ou converta o arquivo e envie de novo.

### Não vejo o Placar Clube ou as Súmulas no menu

As telas de cadastro exigem a permissão "Cadastro (equipes, times, jogadores, jogos)" e as súmulas exigem "Súmulas e scout", ambas do grupo Placar Clube. Fale com o coordenador do seu setor: ele pede à TI ou à Gerência para liberar a permissão ao setor na tela **Setores**, ou a TI dá a permissão só para você em **Usuários**.
