---
modulo: Bot WhatsApp
palavras: bot, robô, WhatsApp, Poli, fluxo, menu do WhatsApp, atendimento automático, mensagem automática, transbordo, transferir para atendente, time, secretaria, fila de espera, encerramento, horário de atendimento, feriado, simulador, template, sem atendente, conversa encerrada
---

# Bot WhatsApp — guia de uso

O **Bot WhatsApp** é o atendimento automático do número de WhatsApp do clube. A Poli continua sendo o canal (é por ela que as mensagens chegam e saem, e é no painel dela que os atendentes conversam), mas quem decide o que o bot responde é O Lara, seguindo os **fluxos** montados na tela **Bot WhatsApp › Fluxos**.

O bot responde as conversas atribuídas ao usuário **"O Lara"** na Poli. Ele pergunta, confere a resposta (opção do menu, placa, data, foto...) e, no fim, passa a conversa para um time de atendentes ou encerra. Também pode registrar o pedido de carro de aplicativo que a portaria acompanha em **Externos › Carros de Aplicativo**.

A tela **Bot WhatsApp › Transferências e encerramentos** mostra por que cada conversa chegou ao O Lara, saiu dele ou foi encerrada — útil para entender reclamações como "fiquei sem resposta" ou "a conversa fechou sozinha".

## Quem faz o quê

| Para | Permissão (como aparece na tela Setores) |
|---|---|
| Criar, editar, testar, ativar e apagar fluxos (**Bot WhatsApp › Fluxos**) | Bot WhatsApp › Editar os fluxos do bot |
| Acompanhar transferências, encerramentos e a fila de espera (**Bot WhatsApp › Transferências e encerramentos**) | Bot WhatsApp › Ver transferências e encerramentos |

Quem libera é a TI ou a Gerência, na tela **Setores** (ou só para uma pessoa, na tela **Usuários**); o coordenador do setor pede a liberação e coloca a equipe no setor pela tela **Meu setor**. As duas são independentes: quem acompanha o atendimento não precisa poder editar fluxo. Salvar um fluxo **ativo** é publicar: ele passa a valer na próxima mensagem dos associados.

## Ver a situação do bot e a lista de fluxos
<!-- telas: poli-bot.index -->

A tela **Bot WhatsApp › Fluxos** ("O que o bot pergunta, como confere cada resposta e o que faz depois") mostra no alto em que **modo** o bot está:

- **Desligado** — "O bot da Poli atende. A Lara só escuta o fluxo do Uber."
- **Modo sombra** — fase de piloto: o bot da Poli segue atendendo a maioria; o bot do Lara conduz só as conversas de teste.
- **No ar** — "A Lara responde os associados. O bot da Poli deve estar desligado."

O modo não é trocado pela tela: quem muda é a TI.

Abaixo vêm os números dos **últimos 7 dias**: **Conversas**, **Mensagens recebidas**, **Respostas enviadas** (ou "Respostas (simuladas)" fora do modo No ar), **Passadas a atendente**, **Pedidos de carro** e **Falhas de envio**.

A tabela lista os fluxos com **Fluxo**, **Começa com** (o gatilho: qualquer primeira mensagem, palavras-chave, "Só chamado por outro fluxo"...), **Passos**, **Situação** e **Última alteração** (quando e quem). A situação é:

- **Ativo** — está valendo;
- **Rascunho** — salvo, mas desligado;
- **Com problemas** — tem erro; passe o mouse para ver qual.

Em cada linha há **Editar** e **Ativar**/**Desativar**. Use a busca para achar um fluxo por nome, gatilho ou situação.

## Criar ou editar um fluxo do bot
<!-- telas: poli-bot.index -->

Em **Bot WhatsApp › Fluxos**, clique em **Novo fluxo** (ou **Editar** num existente). O editor tem o **quadro** no centro e, à direita, as abas **Fluxo**, **Passo** e **Simulador**.

Na aba **Fluxo**:

1. **Nome** (ex.: "Atendimento inicial") e **Identificador** (letras minúsculas, números e hífen; **não muda depois de criado**).
2. **Quando começa**: "Em qualquer primeira mensagem (boas-vindas)", "Quando a mensagem for uma destas palavras" (separe por vírgula; acento e maiúscula não importam), "Quando um setor transferir a conversa para O Lara" ou "Só quando outro fluxo mandar para cá". Só um fluxo ativo pode ser de boas-vindas, e só um pode abrir nas transferências de setor.
3. **Limites**: **Encerrar parada após (min)**, **Erros até passar p/ humano** e o time que recebe quando as tentativas acabam.
4. **Tem horário de atendimento**: horários por dia, **Feriados** (uma data por linha, dd/mm/aaaa) e o passo por onde começar **Fora do horário**.

Monte os passos no quadro: arraste blocos da paleta — **Mensagem**, **Pergunta**, **Menu**, **Template Poli**, **Passar p/ time**, **Pedido de carro**, **Outro fluxo** e **Encerrar** — e ligue um ao outro puxando a bolinha à direita do bloco. Soltar a ligação no vazio cria um bloco já ligado. Para desligar, clique na linha e em ✕. **Organizar** arruma os blocos em colunas.

Clique num bloco para editá-lo na aba **Passo**, em três partes: **1. O bot diz** (texto, menu numerado ou template da Poli; *negrito* e _itálico_ funcionam), **2. O bot espera como resposta** (uma das opções, texto livre, placa, data, número, sim ou não, imagem ou qualquer coisa) e **3. Depois** (passar para um time, encerrar, registrar pedido de carro de aplicativo, continuar em outro fluxo, e o próximo passo).

Salve com **Salvar** (ou Ctrl+S). Marque **Ativo** só depois de testar no Simulador.

### Guardar respostas e usar variáveis

Em **Guardar a resposta como**, dê um nome à resposta (ex.: placa) para usá-la nos textos seguintes como {placa}. {contato} é o primeiro nome do WhatsApp do associado. O bloco **Pedido de carro** usa as respostas guardadas como matricula, nome, local, placa e print.

## Testar um fluxo antes de publicar (Simulador)
<!-- telas: poli-bot.index -->

No editor de **Bot WhatsApp › Fluxos**, abra a aba **Simulador** e clique em **▶ Testar este**. Escreva em **Mensagem…** como se fosse o associado e clique em **Enviar**; também dá para **Enviar uma imagem**. O passo em que a conversa está acende em verde no quadro, e o rodapé mostra o estado (fluxo, passo, erros e respostas guardadas). **Reiniciar** recomeça a conversa.

Regras do simulador:

- **Nada é enviado** a ninguém e nenhum pedido de carro é criado de verdade.
- Ele testa a **versão salva**: salve antes de testar as mudanças. Dá para testar um fluxo ainda **inativo** (rascunho).

O caminho seguro é: salve o fluxo **inativo**, teste no Simulador, e só então marque **Ativo — salvar publica: vale na próxima mensagem dos associados** e salve de novo.

### Versões

Cada gravação fica em **Versões**, com data e quem salvou. **restaurar** só carrega aquela versão na tela ("Versão de ... carregada — confira e salve"): nada muda no bot até você salvar.

## Como o bot conversa com o associado
<!-- telas: poli-bot.index -->

Regras que valem para todos os fluxos de **Bot WhatsApp › Fluxos**:

- **Palavras de escape**, em qualquer ponto da conversa: **menu** recomeça, **sair** encerra e **atendente** (ou **0**, quando o passo não espera um número) passa para um atendente.
- **Resposta que não serve** recebe a mensagem de correção do passo e o menu de novo. Esgotadas as tentativas, a conversa vai para um atendente.
- **Atalho**: se a primeira mensagem já é o nome de uma opção do menu inicial (ex.: "financeiro"), vale como resposta.
- **Menu antigo**: tocar num menu de uma etapa anterior não vale como resposta da etapa atual.
- **Várias mensagens de uma vez** ("Bom dia", "segue meu documento", foto) não geram correção nem gastam tentativa enquanto o associado ainda não viu a pergunta.
- **Fim do fluxo**: depois da última mensagem, se o associado escrever em até alguns minutos recebe o menu; senão a conversa é encerrada.
- **Conversa parada** no meio do fluxo é encerrada depois do tempo de **Encerrar parada após (min)**.
- **Fora do horário**, o fluxo começa pelo passo "fora do horário". O horário é conferido de novo na hora de passar para um time.
- Quando um atendente assume a conversa, o bot **para de responder** nela.

## Entender por que uma conversa foi transferida ou encerrada
<!-- telas: poli-bot.events.index -->

A tela **Bot WhatsApp › Transferências e encerramentos** mostra, para um período (padrão: hoje), cada vez que uma conversa:

- **Recebida** — chegou ao O Lara (pelo bot da Poli, por um atendente, ou porque a Poli devolveu um transbordo);
- **Enviada** — saiu do O Lara para um time, com o **Resultado**: "Com atendente", "Na fila do time", "Voltou para O Lara", "A Poli recusou" ou "Aguardando a Poli";
- **Encerramento** — foi encerrada.

Filtros: busca por **Contato, telefone, atendente ou uuid**, período (até) e **Tipo**. O resumo no alto conta cada motivo no período; clique num motivo para filtrar a lista (e em **Ver todos os motivos** para voltar). Clique no contato (**Ver só este contato**) para ver só o histórico dele.

Colunas: **Quando**, **Contato**, **Tipo e motivo** (com fluxo, passo e o texto de sistema da Poli), **Quem → para onde** e **Resultado**. Em cada transferência aparece quantos atendentes do time estavam disponíveis ("X de N disponíveis (Y online)"), em vermelho quando ninguém estava; clique para ver o status de cada um naquele momento. "Sem a foto do time" quer dizer que a Poli não respondeu na hora.

Motivos de encerramento mais comuns: "Fluxo concluído e o contato não escreveu mais", "O contato parou de responder no meio do fluxo", "O contato pediu para sair", "Passo \"encerrar\" do fluxo", "Respostas inválidas demais", "Fila de espera: o horário de atendimento acabou", "Encerrado por um atendente" e "Encerrado pela Poli".

O registro começou em 16/10/2026: dias anteriores aparecem vazios.

## Fila de espera e conversas sem atendente
<!-- telas: poli-bot.events.index -->

Quando o bot passa a conversa para um time e **ninguém está disponível**, a Poli devolve a conversa para O Lara. Em vez de reabrir o menu, a conversa entra na **fila de espera**:

- O associado já ouviu "Vou te encaminhar para…"; **nenhuma mensagem nova** é enviada.
- A cada poucos minutos (normalmente 3), O Lara tenta o mesmo time de novo. Cada tentativa aparece em **Transferências e encerramentos** como "Nova tentativa da fila de espera".
- Se alguém pega a conversa, ela sai da fila e chega ao atendente **marcada como não lida**.
- Se o associado escreve na fila, a mensagem fica na conversa para o atendente; o bot não responde. Só **sair** encerra.
- No **fim do horário de atendimento**, o associado recebe o aviso de que o horário terminou e a conversa é encerrada ("Fila de espera: o horário de atendimento acabou").

O quadro **Na fila agora**, na tela **Bot WhatsApp › Transferências e encerramentos**, mostra quem espera, para qual time, desde quando, quantas tentativas, a próxima e até quando.

**Conversa sem atendente**: às vezes uma conversa aberta fica "Sem atendente" no painel da Poli. Dentro do horário de atendimento, O Lara confere isso sozinho a cada poucos minutos e, se a conversa continua sem atendente na verificação seguinte, manda para a **Secretaria**, sem mensagem ao associado. No diário aparece com o motivo "Conversa estava sem atendente".

## Dúvidas comuns

### "O fluxo tem problemas e não foi salvo"
<!-- telas: poli-bot.index -->

O editor mostra "Não foi salvo. Corrija:" com a lista do que falta, passo a passo, e o bloco com problema fica em vermelho no quadro. Os mais comuns: "próximo passo ... não existe" (ligação para um bloco apagado), "menu sem opções", "opção sem rótulo", "mensagem sem texto", "template sem template_uuid" (escolha o template da Poli), "O fluxo não tem gatilho" (escolha em **Quando começa**) e "Horário de atendimento ligado, mas o passo \"fora do horário\" não existe" (escolha o passo em **Fora do horário, começar pelo passo**). Corrija e salve de novo; nada foi publicado.

### "Já existe um fluxo com este identificador."
<!-- telas: poli-bot.index -->

Cada fluxo precisa de um **Identificador** diferente. Escolha outro, só com letras minúsculas, números e hífen (ex.: carro-de-aplicativo). Depois de criado, o identificador não muda.

### Não consigo apagar o fluxo: "Este fluxo é chamado por: ..."
<!-- telas: poli-bot.index -->

Outro fluxo manda a conversa para este (bloco **Outro fluxo**). Abra os fluxos citados, troque ou remova essa ligação e salve; depois apague. Ao apagar, as versões do fluxo também somem.

### Não consigo ativar o fluxo
<!-- telas: poli-bot.index -->

Ao ativar, O Lara confere o fluxo de novo e recusa se houver erro (a lista aparece no alto da tela). Um caso comum é já existir outro fluxo **ativo** de boas-vindas, ou outro ativo que abre "Quando um setor transferir a conversa para O Lara": só pode haver um de cada. Desative o outro antes.

### "Não foi possível consultar a Poli agora" ou os templates não carregam
<!-- telas: poli-bot.index -->

O editor não conseguiu buscar os templates e os times na Poli (também aparece como "Não foi possível carregar os templates." ou "Sem conexão para carregar templates e times da Poli."). Os campos continuam editáveis; clique em **Recarregar da Poli** para tentar de novo. Se persistir, a Poli pode estar fora do ar — avise a TI.

### Mudei o fluxo e o associado continua vendo o texto antigo
<!-- telas: poli-bot.index -->

Confira se você **salvou** (o editor avisa "● Alterações não salvas" enquanto houver mudança pendente) e se o fluxo está **Ativo**: rascunho não vale para os associados. Veja também o modo no alto da tela **Bot WhatsApp › Fluxos**: com o bot **Desligado**, quem responde é o bot da Poli.

### O associado diz que ficou sem resposta
<!-- telas: poli-bot.events.index -->

Procure o telefone ou o nome em **Bot WhatsApp › Transferências e encerramentos**. Se a última linha for uma **Enviada** com resultado "Na fila do time", ele está na fila esperando um atendente (veja o quadro **Na fila agora**). Se aparecer "0 de N disponíveis", ninguém do time estava disponível naquele momento. Se a conversa já estava com um atendente, o bot não responde nela.

### A conversa foi encerrada sozinha
<!-- telas: poli-bot.events.index -->

Veja o motivo em **Bot WhatsApp › Transferências e encerramentos**. "O contato parou de responder no meio do fluxo" e "Fluxo concluído e o contato não escreveu mais" são encerramentos automáticos por inatividade; "Fila de espera: o horário de atendimento acabou" é quando ninguém pegou até o fim do expediente; "Encerrado por um atendente" ou "Encerrado pela Poli" não foram feitos pelo bot.

### Não vejo o menu Bot WhatsApp
Cada tela tem permissão própria (Bot WhatsApp › Editar os fluxos do bot e Bot WhatsApp › Ver transferências e encerramentos). Fale com o coordenador do seu setor: ele coloca você num setor que já tem a permissão (tela **Meu setor**) ou pede à TI ou à Gerência que a liberem (tela **Setores**).
