---
modulo: SIV
palavras: placa, carro, veículo, câmera, estacionamento, quem estava no carro, condutor, foto do carro, cancela, diretoria, abrir cancela, frota, carro da empresa, viagem, quilometragem, km, hodômetro, saída, retorno, motorista, Celta, caminhão, identificação de veículos
---

# SIV — guia de uso

O **SIV** (Sistema de Identificação de Veículos) reúne o que o Lara sabe sobre os carros que entram no clube e sobre os veículos da própria empresa.

A **Busca** mostra, a partir de uma placa e de uma data, todas as passagens do carro pelas câmeras de leitura de placas, a foto de cada passagem e **quem provavelmente estava no carro** — sócios que passaram na catraca no mesmo instante, terceirizados e freelancers registrados na portaria e pedidos de carro de aplicativo feitos para aquela placa.

As **Placas Diretoria** são a lista de placas que abrem a cancela da diretoria sozinhas. A **Frota**, as **Viagens** e os **Veículos** controlam os carros da empresa: quem saiu, para onde, com qual quilometragem e quando voltou. A portaria normalmente registra saída e retorno pelo sistema da guarita; as telas do Lara servem para acompanhar e, se a guarita estiver fora, para registrar.

## Quem faz o quê

| Para | Permissão (como aparece na tela Setores) |
|---|---|
| Buscar uma placa e ver quem estava no carro (**SIV › Busca**) | SIV › Busca de placas |
| Cadastrar placas liberadas na cancela da diretoria e abrir a cancela pela tela (**SIV › Placas Diretoria**) | SIV › Placas da diretoria |
| Ver a situação da frota e registrar saída e retorno (**SIV › Frota**) | SIV › Frota (saída e retorno) |
| Ver o histórico de viagens e cancelar uma saída registrada por engano (**SIV › Viagens**) | SIV › Viagens |
| Cadastrar, editar, desativar e excluir veículos da frota (**SIV › Veículos**) | SIV › Cadastro de veículos |

Quem libera é a TI ou a Gerência, na tela **Setores** (ou só para uma pessoa, na tela **Usuários**); o coordenador do setor pede a liberação e coloca a equipe no setor pela tela **Meu setor**. Cada tela do SIV tem permissão própria: ter uma não dá acesso às outras, e os atalhos entre elas (como **Histórico** e **Veículos** no painel da Frota) só aparecem para quem tem a permissão de destino.

## Descobrir quem estava num carro pela placa
<!-- telas: parking.search -->

Na tela **SIV › Busca** ("Identificação de veículos"), o alto mostra os números do dia: **Veículos registrados hoje**, **Placas identificadas** e **Sem placa identificada**.

1. Em **Placa do veículo**, digite a placa (a tela coloca em maiúsculas e tira hífen e espaço).
2. Em **Data do acesso**, escolha o dia (vem com hoje).
3. Clique em **Buscar**.

A tela **Dados do acesso** mostra:

- **Placa**, **Cor** e **Acessos no dia**.
- **Pedidos de carro de aplicativo**: aparece quando houve pedido pelo WhatsApp para essa placa no dia, com quem pediu, matrícula/CPF, situação, hora do pedido, hora em que a entrada foi liberada, local no clube, WhatsApp, a conferência do sócio e o link **Abrir print da corrida**. Aparece mesmo que a câmera não tenha lido o carro. Quem tem a permissão de Carros de aplicativo vê o atalho **Ver em Carros de aplicativo**.
- **Condutores mais prováveis**: até três pessoas, com telefone e percentual, calculados sobre os **10 acessos mais recentes** da placa (de qualquer dia). Entram sócios e externos liberados; passageiro de carro de aplicativo não entra.
- **Histórico de acessos**: cada passagem do dia, com horário, foto do carro (clique em **Ampliar**) e a tabela **Tipo · Nome · Vínculo · Telefone · Horário** das pessoas ligadas àquela passagem: Associado (com a matrícula), Terceirizado (com a empresa), Freelancer, Liberação pontual ou Carro de aplicativo.

Use o filtro do histórico para procurar por horário, nome, matrícula, empresa ou telefone. **Nova busca** reabre o formulário sem sair da tela.

### Como o Lara liga a pessoa ao carro

- **Sócio**: quem passou na catraca da mesma portaria até 15 segundos antes ou depois da leitura da placa. Sem cadastro, aparece como "Visitante".
- **Terceirizado, freelancer e liberação pontual**: quem foi registrado no Monitor de Acesso até 60 segundos antes ou depois. Quem foi registrado mas **negado** aparece com o selo **Negado**.
- **Carro de aplicativo**: pela placa informada no pedido, na passagem mais próxima da liberação (até 30 minutos).

Como a ligação é pelo horário, uma pessoa que passou a pé no mesmo minuto pode aparecer junto. O quadro de condutores mais prováveis, que soma dez acessos, ajuda a separar coincidência de padrão.

## Liberar uma placa na cancela da diretoria
<!-- telas: parking-authorizations.index -->

Na tela **SIV › Placas Diretoria** ficam as "placas liberadas na cancela sem depender de cadastro de sócio". A câmera da cancela confere a placa e abre sozinha para as que estão na lista e dentro da validade.

Para cadastrar:

1. Clique em **Nova placa**.
2. Preencha **Placa** (ex.: ABC1D23), **Nome** (o titular) e **Data de Validade** — todos obrigatórios.
3. Salve. Aparece "Placa cadastrada com sucesso.".

A lista mostra **Placa**, **Nome**, **Validade** e **Status**: **Válida** ou **Expirada**. Placa expirada **não abre** a cancela: para renovar, clique no lápis (**Editar**), mude a **Data de Validade** e salve. A lixeira (**Remover**) apaga a placa, com confirmação. Use a busca "Buscar por placa ou nome" e clique nos títulos das colunas para ordenar.

Os **veículos ativos da frota** com placa cadastrada abrem a cancela automaticamente, sem precisar estar nesta lista e sem validade. Para tirar um carro da frota da cancela, desative-o em **SIV › Veículos**.

### Abrir a cancela pela tela (Push manual)

No quadro **Push manual** da mesma tela, informe o **Tempo aberto (seg)** e clique em **Abrir cancela**. O comando vai direto ao equipamento da cancela, sem depender da leitura da placa; o tempo escolhido recebe 5 segundos extras de segurança. A mensagem "Comando enviado ao dispositivo." confirma o envio (a tela não tem como confirmar que a cancela abriu). O comando só chega ao equipamento a partir de um computador na rede do clube.

## Registrar a saída de um veículo da frota
<!-- telas: fleet.index -->

Na tela **SIV › Frota** há um cartão para cada veículo ativo, com o selo **No Clube**, **Em rota** ou **Aguardando baixa**. No alto, os números: **Em rota agora**, **Aguardando baixa**, **Saídas hoje**, **Km hoje** e **Km no mês**.

No cartão de um veículo **No Clube**:

1. **Motorista**: nome, matrícula ou CPF do funcionário.
2. **Destino**: para onde o carro vai.
3. **Km de saída**: a quilometragem do painel (vem com a última registrada).
4. **Observação** (opcional).
5. Clique em **Registrar saída**. Aparece "Saída registrada." e o cartão passa a **Em rota**.

O horário gravado é sempre o do registro: não se digita hora.

Regras importantes:

- **O motorista precisa estar apto.** Funcionário desligado, de férias, afastado ou com cadastro excluído no módulo Funcionários é recusado com o motivo (ex.: "Fulano não pode retirar veículo da frota: Férias até 10/11/2026."). Não há como forçar.
- Uma **matrícula ou CPF que não encontra ninguém** é recusado ("Nenhum funcionário encontrado para ..."). Digitando o nome, quem não está no cadastro de funcionários é registrado assim mesmo.
- **Um veículo só sai de novo depois do retorno.** Se ele já está fora, a mensagem diz com quem e desde quando.
- Quilometragem **menor que a última registrada** é recusada com aviso. Se o número estiver certo, marque **Confirmar mesmo com quilometragem menor que a última registrada** e envie de novo: a viagem fica marcada como "km a conferir".

## Registrar o retorno de um veículo da frota
<!-- telas: fleet.index -->

Na tela **SIV › Frota**, o cartão de um veículo **Em rota** mostra **Motorista**, **Destino**, **Saída** (data, hora, quilometragem e há quanto tempo) e a **Obs** da saída.

1. Em **Km de retorno**, digite a quilometragem do painel.
2. **Observação** (opcional).
3. Clique em **Registrar retorno**. Aparece "Retorno registrado. N km nesta viagem." e o veículo volta a **No Clube**.

O Lara recusa, com aviso, o retorno com quilometragem **menor que a de saída** ou uma viagem acima do limite de quilometragem permitido para uma viagem só. Se o número estiver certo, marque **Confirmar mesmo com quilometragem fora do esperado** e envie de novo: a viagem fica marcada como "km a conferir", para alguém revisar depois.

### Viagem "Aguardando baixa"

Nada fecha sozinho: só quem viu o carro voltar sabe a quilometragem. A viagem aberta há mais tempo que o limite (o painel informa quantas horas, normalmente 12h) fica destacada como **Aguardando baixa** — quase sempre é um retorno que ninguém registrou. Registre o retorno com a quilometragem atual do carro.

### Saída registrada por engano

No cartão do veículo em rota, clique em **Cancelar saída (registro errado)** e confirme. A viagem fica como **Cancelada** no histórico e o veículo volta a ficar disponível. O cancelamento exige também a permissão **SIV › Viagens**.

## Consultar o histórico de viagens da frota
<!-- telas: fleet.trips -->

Na tela **SIV › Viagens** ("Histórico de viagens") ficam todas as saídas e retornos, os registrados pela guarita e os feitos no Lara.

Filtros:

- **Veículo** e **Situação** (Em rota, Concluída ou Cancelada).
- **Motorista**: nome ou matrícula.
- **Saída de** e **Saída até**: período.
- **Somente viagens com quilometragem a conferir**: as viagens em que alguém confirmou uma quilometragem fora do esperado.

Clique em **Filtrar**; **Limpar** tira os filtros.

Colunas: **Veículo** (com a placa), **Motorista** (com a matrícula, quando é funcionário cadastrado), **Destino** (com as observações), **Saída** e **Retorno** (data, hora e quilometragem), **Km** rodados e **Situação**. A etiqueta **km a conferir** aponta as viagens a revisar.

O botão **Painel da frota** leva para **SIV › Frota** (para quem tem essa permissão).

## Cadastrar ou desativar um veículo da frota
<!-- telas: fleet.vehicles -->

Na tela **SIV › Veículos**, clique em **Novo veículo**.

1. **Nome** (obrigatório e único): como a portaria chama o carro, ex.: "Celta". A guarita encontra o carro por esse nome, mesmo sem acento.
2. **Placa** (opcional): também identifica o carro. Com placa, o veículo ativo **abre a cancela da diretoria** sozinho.
3. **Descrição**: modelo, cor, ano.
4. **Quilometragem atual**: só o ponto de partida; depois, quem atualiza são as saídas e os retornos.
5. **Ativo (aparece para a portaria)**: deixe marcado.
6. Salve. Aparece "Veículo cadastrado.".

A lista mostra **Nome**, **Placa**, **Descrição**, **Km atual**, número de **Viagens** e **Situação** (Ativo ou Inativo). A portaria só enxerga os ativos.

Para tirar um carro de uso (vendido, quebrado), clique no lápis, desmarque **Ativo** e salve: ele some da portaria e do painel da Frota, **perde a liberação da cancela** e o histórico fica guardado. A lixeira exclui de vez, mas só funciona para veículo **sem nenhuma viagem**.

## Dúvidas comuns

### Por que a foto do carro não aparece ("Sem foto deste acesso")?
<!-- telas: parking.search -->

As câmeras guardam as fotos por pouco tempo. O Lara copia a foto na primeira vez que alguém busca a placa; de uma passagem antiga só existe foto se alguém buscou enquanto ela ainda estava disponível. A foto também falta quando o servidor das câmeras está fora do ar — nesse caso, tente de novo em alguns minutos. Se nenhuma foto recente aparece, avise a TI.

### A busca não encontra nenhum acesso da placa
<!-- telas: parking.search -->

A tela mostra "Nenhum acesso encontrado para a placa ... em ...". Confira a **Data do acesso** (vem com hoje) e a placa — a câmera pode ter lido um caractere errado. Os pedidos de carro de aplicativo para a placa aparecem mesmo sem leitura da câmera.

### Por que aparece "Visitante" no lugar do nome do sócio?
<!-- telas: parking.search -->

A catraca registrou uma passagem no mesmo instante, mas sem cadastro de sócio correspondente. Também pode ser outra pessoa que passou a pé na mesma hora: a ligação entre carro e catraca é feita pelo horário.

### "Esta placa já está cadastrada."
<!-- telas: parking-authorizations.index -->

A placa já está na lista da diretoria (a comparação ignora hífen, espaço e maiúsculas). Procure-a com a busca da tela **SIV › Placas Diretoria** e edite a existente em vez de criar outra.

### A placa da diretoria está na lista e a cancela não abre
<!-- telas: parking-authorizations.index -->

Veja o **Status**: placa **Expirada** não abre — edite e mude a **Data de Validade**. Se estiver válida, a câmera pode ter lido a placa errado; na urgência, use o **Push manual** da mesma tela para abrir a cancela.

### "Falha ao acionar a cancela. Verifique a conexão com o dispositivo."
<!-- telas: parking-authorizations.index -->

O computador não conseguiu falar com o equipamento da cancela. O Push manual só funciona a partir da rede do clube; confira se você está conectado nela e tente de novo. Persistindo, avise a TI.

### O motorista foi recusado ao registrar a saída da frota
<!-- telas: fleet.index -->

A mensagem traz o motivo. "Não pode retirar veículo da frota: Férias até ...", "Desligado em ..." ou um afastamento vêm do cadastro do módulo Funcionários: o funcionário não está apto naquele dia e não há como forçar. "Nenhum funcionário encontrado para ..." quer dizer que a matrícula ou o CPF digitado não existe — confira o número ou digite o nome do motorista.

### "O veículo ... já saiu em ... e ainda não teve o retorno registrado."
<!-- telas: fleet.index -->

O carro tem uma viagem aberta. Registre o **retorno** no cartão dele em **SIV › Frota** antes da nova saída. Se a saída anterior foi lançada por engano, use **Cancelar saída (registro errado)**.

### "A quilometragem informada é menor que a última registrada" ou "acima do limite"
<!-- telas: fleet.index -->

Confira o hodômetro. Se o número estiver certo (painel trocado, erro num registro anterior), marque a caixa de confirmação do cartão e envie de novo. A viagem fica marcada como "km a conferir" e aparece no filtro **Somente viagens com quilometragem a conferir** de **SIV › Viagens**.

### "Este veículo tem viagens registradas. Desative-o em vez de excluir."
<!-- telas: fleet.vehicles -->

Veículo com histórico não pode ser apagado. Edite-o e desmarque **Ativo (aparece para a portaria)**: ele sai da portaria e da cancela, e as viagens antigas ficam guardadas.

### "Esta placa já está cadastrada em outro veículo."
<!-- telas: fleet.vehicles -->

Outro veículo da frota já usa essa placa. Procure na lista de **SIV › Veículos** e corrija o cadastro duplicado. O **Nome** também não pode se repetir entre veículos.
