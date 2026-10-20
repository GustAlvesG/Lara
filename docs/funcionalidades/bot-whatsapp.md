# Bot do WhatsApp (Poli)

## O que é

O bot que atende o número de WhatsApp do clube, rodando **na Lara** em vez de no painel da Poli.
A Poli continua sendo o canal (recebe e entrega as mensagens, guarda templates e times); quem
decide o que responder é a Lara, a partir de **fluxos** editados na tela **Bot WhatsApp**.

Cada fluxo é uma sequência de **passos**. Todo passo tem até três partes:

1. **O bot diz** — um texto, um menu numerado, ou um template da Poli (lista ou botões).
2. **O bot espera** — o tipo de resposta aceita: uma das opções, texto livre (com tamanho e
   formato), placa, data, número, sim/não, imagem ou qualquer coisa. Resposta que não serve
   recebe a mensagem de correção do passo; esgotadas as tentativas, a conversa vai para um
   atendente.
3. **Depois** — uma ação (passar para um time, encerrar o atendimento, registrar pedido de carro
   de aplicativo, continuar em outro fluxo) e o próximo passo.

## Para quem

Quem tem a permissão **`bot-whatsapp`** — na matriz inicial, o coordenador do Atendimento.
 Salvar um fluxo ativo é **publicar**: ele vale na
próxima mensagem dos associados.

A tela **Transferências e encerramentos** tem permissão própria,
**`bot-whatsapp.ocorrencias`**: é de quem acompanha o atendimento, sem precisar editar fluxo.
Nasce sem setor (só o acesso total alcança); dê aos setores pertinentes na tela de Setores.

## Pré-requisitos

- Tabelas: `php artisan migrate` (bot_flows, bot_sessions, poli_messages, bot_flow_versions,
  bot_attendance_events).
- Permissões: `bot-whatsapp` e `bot-whatsapp.ocorrencias`, do catálogo de acesso (ver
  [Usuários, setores e permissões](usuarios-e-permissoes.md)); o deploy cria as que faltam.
- Fluxos iniciais, equivalentes ao bot da Poli de hoje: `php artisan poli:bot-fluxos --instalar`.
- Modo do bot no `.env` (`POLI_BOT_MODE`), ver abaixo.

## Quando a Lara responde

A regra é explícita: **a Lara responde nas conversas atribuídas ao usuário "O Lara" na Poli**
(`POLI_BOT_USER_UUID`) e ainda não encerradas. O bot da Poli continua sendo a porta de entrada:
as opções dele que devem ir para a Lara transferem a conversa para esse usuário. A Lara abre o
fluxo assim que a transferência chega (usando como gatilho o toque no menu da Poli) e, no fim,
distribui para um time humano ou encerra.

Quem entra no piloto é decidido **no fluxo do bot da Poli** (qual opção transfere para O Lara),
não no `.env`.

### Transferência de um setor para O Lara

Um atendente também pode passar para O Lara uma conversa que estava com ele (no painel da Poli,
transferir o atendimento para o usuário O Lara). Se houver um fluxo ativo com o início **Quando
um setor transferir a conversa para O Lara**, é ele que abre — pelo primeiro passo, ou pelo passo
"fora do horário" quando o fluxo tem horário e está fechado. Serve para devolver a conversa ao
bot depois do atendimento: uma pesquisa de satisfação, um "posso ajudar em mais alguma coisa?",
um menu de retorno.

- **Como a Lara sabe que veio de um setor:** ela marca a conversa como "com atendente" quando um
  humano assume (transferência para ele, atendente no atendimento ou mensagem dele). Transferência
  para O Lara de uma conversa nesse estado é de setor; sem humano antes, é a do bot da Poli e
  segue a regra de sempre. A marca vale por `POLI_BOT_HUMAN_TIMEOUT_HOURS` (12 h).
- **Vale para qualquer setor:** o fluxo é um só, não há um por setor.
- **O que o contato disse ao atendente não é gatilho:** nesse início, palavras-chave e o atalho do
  menu não são consultados.
- **Sem fluxo ativo com esse início**, a transferência de um setor abre como antes: pela última
  mensagem do contato ou pelo fluxo de boas-vindas.
- No modo `shadow`, quem não está na lista de teste continua indo para a Secretaria.

### Transbordo devolvido pela Poli

Em 16/10/2026 o transbordo para a Secretaria e o Financeiro voltava para O Lara um segundo
depois do `distribute` ("Contato redirecionado para O Lara **pelo sistema**"), sempre nos horários
sem atendente: abertura, almoço, fim do dia e noite. O Lara não está nos times e a Poli não
mostra a opção de distribuição que explique isso. Antes, a Lara tratava a volta como transferência
de setor e reabria o menu; o contato escolhia o mesmo time e voltava, em ciclo.

A volta **pelo sistema** até `POLI_BOT_DEVOLUCAO_S` (120) segundos depois de um transbordo do
O Lara que ninguém pegou é **devolução**. A conferência do transbordo (~10 s depois) que encontra a
conversa ainda com O Lara também conta como devolução. O menu **não** reabre, e o diário registra o
transbordo com resultado **Voltou para O Lara** e a volta como **A Poli devolveu o transbordo**.

Um **atendente** que pega a conversa e a devolve para O Lara (transferência "por Fulano") continua
sendo transferência de setor, mesmo logo depois.

### Fila de espera

Desde 19/10/2026 a devolução não encerra a conversa: ela entra na **fila de espera**, com O Lara.

- **Em silêncio.** O contato já ouviu *"Vou te encaminhar para…"*; nenhuma mensagem nova sai na
  devolução nem nas tentativas.
- **A cada `POLI_BOT_FILA_INTERVALO_MIN` (3) minutos**, o agendamento (`poli:bot-expirar`, a cada
  minuto) confere o atendimento na API e, ainda com O Lara, faz o `distribute` para o **mesmo
  time** de novo. Cada tentativa vai para o diário (*Nova tentativa da fila de espera*), com a foto
  do time (abaixo).
- **Alguém pegou** (pela tentativa ou pelo painel): a conversa sai da fila, fica com o atendente e é
  marcada como não lida para ele.
- **Fim do horário de atendimento** do fluxo de boas-vindas (no fluxo padrão, 19:50 de segunda a
  sexta; o que vale é o horário configurado na tela do fluxo): o contato
  recebe *"Nosso horário de atendimento terminou antes que um atendente pudesse continuar com
  você…"* e a conversa encerra (*Fila de espera: o horário de atendimento acabou*). Fluxo sem
  horário: a fila espera até `POLI_BOT_FILA_MAX_MIN` (240).
- **O contato escreve na fila:** a mensagem fica na conversa para o atendente; a Lara não responde.
  Só `sair` (e as outras palavras de saída) encerra.
- A reconciliação (`poli:bot-reconciliar`) não encerra conversa na fila.
- `POLI_BOT_FILA=false` volta ao comportamento de 16/10: o contato recebe *"Não consegui te
  transferir para um atendente agora…"* e a conversa encerra em `POLI_BOT_ENCERRAR_APOS_MIN`.

A tela **Transferências e encerramentos** mostra o quadro **Na fila agora**: quem espera, para qual
time, desde quando, quantas tentativas, a próxima e até quando.

### Conversa sem atendente

Às vezes uma conversa aberta fica **"Sem atendente"** no painel da Poli, sem motivo aparente, e se
perde se não houver um supervisor para direcioná-la. Desde 20/10/2026, O Lara confere isso sozinho:

- **A cada 3 minutos** (`poli:bot-sem-atendente`), lista as conversas abertas da conta
  (`/chats?status=OPEN` — de todos os atendentes, não só as do O Lara) e o atendimento atual de
  cada uma.
- A conversa **aberta e sem atendente em 2 verificações seguidas** (`POLI_BOT_SEM_ATENDENTE_VERIFICACOES`),
  no mesmo atendimento, vai para a **Secretaria** (`POLI_BOT_SEM_ATENDENTE_TIME` troca o time). Se
  alguém pegar entre uma verificação e outra, ou o atendimento mudar, a contagem recomeça.
- Vai como um transbordo do O Lara: com a foto de quem estava disponível no time, no diário com o
  motivo **Conversa estava sem atendente** ("Sem atendente desde HH:MM"), e marcada como não lida
  para quem pegar. Se a Poli devolver (ninguém disponível), a conversa cai na **fila de espera**.
  Nenhuma mensagem vai para o contato.
- **Só no horário de atendimento** do fluxo de boas-vindas: fora dele não há quem pegue. A
  conversa que ficar sem atendente de noite é direcionada nas duas primeiras verificações do dia.
- **Conversa aberta pela empresa** (aviso, cobrança — `INITIATED_BY_BUSINESS`) só conta depois que
  o contato responde; sem isso, toda notificação do sistema iria para a Secretaria. A resposta é
  conferida no webhook gravado.
- Os atendimentos "fantasmas" da fase do bot da Poli (status nulo, sem atendente, de semanas atrás)
  não aparecem na lista de conversas abertas da Poli e não são tocados.
- Listagem maior que `POLI_BOT_SEM_ATENDENTE_MAX` (200) aborta a rodada (log); `POLI_BOT_SEM_ATENDENTE=false`
  desliga.

### Quem estava disponível no time

Antes de **cada** `distribute` do O Lara — transbordo do fluxo, pedido de atendente, piloto,
devolução manual e cada nova tentativa da fila, dê certo ou não —, O Lara lê os membros do time na
Poli (`GET /teams/{uuid}?include=users`) e guarda no diário, **para sempre**, a foto daquele
momento: cada membro com `status` (ONLINE/OFFLINE) e `status_of_service` (AVAILABLE/UNAVAILABLE),
e as contagens. **Disponível** = ONLINE **e** AVAILABLE. Se é exatamente esse o critério da
distribuição da Poli não está documentado; a foto guarda os dois campos para conferir. Sem resposta
da Poli, o transbordo sai do mesmo jeito, sem foto ("Sem a foto do time" na tela).

### Transferência velha

O job da transferência para O Lara espera alguns segundos. Se nesse meio tempo o O Lara já passou a
conversa adiante (a mensagem do contato foi processada antes e transbordou), a transferência
chegaria com um menu numa conversa que já está com um atendente. Por isso, quando a conversa está
com atendente, a Lara **confere na API** antes de agir: se o atendimento já não está com O Lara, a
transferência fica só no histórico (`poli_messages`, "ignorada"). Sem resposta da API, vale o
evento.

## Transferências e encerramentos (tela)

**Bot WhatsApp › Transferências e encerramentos** mostra, para um período (padrão: hoje; até 366
dias por consulta — o registro não é apagado), cada vez que uma conversa:

- **chegou ao O Lara** (recebida),
- **saiu dele** (enviada), com o resultado: com atendente, na fila do time, voltou para O Lara,
  a Poli recusou, aguardando,
- **foi encerrada** (encerramento),

com o **motivo**, quem fez, para onde foi, o fluxo e passo, e o texto de sistema da Poli. Cada
enviada mostra *"X de N disponíveis (Y online)"* — em vermelho quando ninguém estava disponível — e,
ao clicar, o time inteiro com o status de cada um naquele momento. O resumo no
topo conta cada motivo no período; clicar num motivo filtra a lista. A busca aceita nome, telefone
(com ou sem máscara), atendente ou uuid; clicar no contato mostra só o histórico dele.

| Tipo | Motivo | Quando |
|---|---|---|
| Recebida | Entrada pela Poli | Transferência "pelo sistema" sem transbordo pendente (bot da Poli, distribuição). |
| Recebida | A Poli devolveu o transbordo | Ver *Transbordo devolvido pela Poli*. |
| Recebida | Um atendente transferiu | Transferência "por Fulano" para O Lara. |
| Recebida | Resgate / Aviso do carro | Forward que o próprio O Lara faz. |
| Recebida | Transferida pela API | Forward da API sem anotação do O Lara (ex.: `poli:teste-envio`). |
| Enviada | Passo de transbordo do fluxo | A opção ou o passo "Passar p/ time". |
| Enviada | O contato pediu atendente | Palavra de escape `atendente`/`0`. |
| Enviada | Respostas inválidas demais | `max_attempts` esgotado. |
| Enviada | Número fora da lista de teste | Piloto (`shadow`). |
| Enviada | Devolução manual | `poli:bot-reconciliar --devolver`. |
| Enviada | Nova tentativa da fila de espera | A cada 3 min, enquanto a conversa espera (ver *Fila de espera*). |
| Enviada | Conversa estava sem atendente | Aberta sem atendente em 2 verificações seguidas (ver *Conversa sem atendente*). |
| Enviada | Tirada do O Lara | Um atendente ou a Poli tirou a conversa no meio do fluxo. |
| Encerramento | Fluxo concluído / pergunta final sem resposta | Encerramento diferido do fim do fluxo. |
| Encerramento | Fila de espera: o horário de atendimento acabou | Ninguém pegou até o fim do horário. |
| Encerramento | Transbordo devolvido / não saiu do O Lara | Só com `POLI_BOT_FILA=false`, depois do aviso ao contato. |
| Encerramento | O contato parou de responder | Abandono no meio do fluxo. |
| Encerramento | O contato pediu para sair · Passo "encerrar" · Respostas inválidas | Close na hora. |
| Encerramento | Reconciliação | `poli:bot-reconciliar`. |
| Encerramento | Depois do aviso do carro | Aviso de chegada do Uber (close logo depois, ou diferido no `on`). |
| Encerramento | Encerrado por um atendente / pela API / pela Poli | Encerramentos da conta que não foram do O Lara. |

Como se sabe quem agiu: o O Lara anota o que ele mesmo faz **antes** de pedir à Poli; a mensagem
de sistema da Poli que chega pelo webhook confirma a mesma linha. O que a Poli ou um atendente faz
entra pelo webhook: `ATTENDANCE_REDIRECTED` com `direction = EMPTY` é a Poli sozinha; com
`SYSTEM`, alguém pediu (autor com uuid = atendente; sem uuid = API). Transferências entre
atendentes, sem O Lara no meio, ficam de fora. Com `POLI_BOT_MODE=off` nada é registrado. O
registro começou em 16/10/2026: dias anteriores aparecem vazios.

## Modos (`POLI_BOT_MODE`)

O Lara é a própria Lara: **toda conversa atribuída a ele é respondida**, em qualquer modo
ligado — não há outro atendente para responder por ela.

| Modo | O que acontece |
|---|---|
| `off` | Chave de emergência: a Lara não fala nem nas conversas do O Lara. A escuta do Uber segue. |
| `shadow` | O piloto. Para os sócios tudo segue como hoje (bot da Poli, escuta do Uber, aviso de chegada que encerra). A Lara responde só nas conversas do O Lara — as que chegam pela opção **Funcionalidade Teste** do menu da Poli —, e nelas tudo roda de verdade, como no `on`, inclusive o pedido do carro. |
| `on` | Como `shadow`, sem a lista de números de teste, e o aviso de chegada do Uber passa a conversa para O Lara em vez de encerrar. |

No `shadow`, só os números de `POLI_BOT_TEST_CONTACTS` têm a conversa do O Lara conduzida pela
Lara. A opção "Funcionalidade Teste" aparece para todos no menu da Poli: quem tocar nela sem
estar na lista recebe um aviso e vai direto para a Secretaria (`POLI_BOT_TEST_OTHERS_TEAM` troca
o time), sem passar por fluxo nem por horário.

Nos dois, as conversas que continuam no bot da Poli passam pela Lara só em sombra, para comparação.

É nas conversas do bot da Poli que a escuta do fluxo do Uber trabalha.

## Fluxo passo a passo (criar um fluxo)

1. **Bot WhatsApp › Novo fluxo.**
2. Na aba **Fluxo** (painel à direita): nome e identificador (o identificador não muda depois:
   é por ele que outros fluxos o chamam).
3. **Quando começa:** em qualquer primeira mensagem (boas-vindas — só um fluxo ativo pode), por
   palavras-chave, quando um setor transferir a conversa para O Lara (também só um fluxo ativo
   pode), ou só quando outro fluxo mandar para ele.
4. Monte os passos no **quadro**: arraste blocos da paleta (Mensagem, Pergunta, Menu, Template
   Poli, Passar p/ time, Pedido de carro, Outro fluxo, Encerrar) e ligue-os puxando a bolinha
   à direita de um bloco até outro. Cada opção de um menu é uma saída própria. Soltar a ligação
   no vazio cria um bloco já ligado. Clique num bloco para editá-lo na aba **Passo**. Para menus
   da Poli, escolha o template e clique em **Usar as opções deste template**.
5. Use **Guardar a resposta como** para reaproveitar respostas nos textos seguintes
   (`{placa}`, `{nome}`; `{contato}` é o primeiro nome do WhatsApp).
6. Salve **inativo** e teste no **Simulador** (▶ Testar este). O simulador nunca envia nada e
   aceita rascunhos.
7. Marque **Ativo** e salve.

O próprio quadro é o mapa do fluxo: marca **sem caminho** o passo que nenhum outro alcança, em
vermelho o passo com problema ao salvar, e em verde o passo em que o **Simulador** está.
**Organizar** redistribui os blocos em colunas, na ordem da conversa. A posição dos blocos é
gravada no fluxo (`layout`), mas o bot não a usa. **Versões** guarda cada gravação, com quem salvou; restaurar carrega na tela para
conferir e salvar.

## Regras de negócio

- **Silêncio:** chegou mensagem ou transferência com outro atendente (humano pelo painel, ou o
  próprio transbordo da Lara), a conversa vira `human` e a Lara para de responder.
- **Transbordo:** o horário é conferido **na hora do transbordo** — fora dele a conversa vai para o
  passo "fora do horário" (do fluxo atual, ou do fluxo de boas-vindas se o atual não tem
  horário). Depois do distribute, um job confere em ~10 s (`POLI_BOT_CONFIRMAR_TRANSBORDO_S`)
  se o atendimento saiu mesmo do O Lara; se não saiu, a conversa vai para a fila de espera. A volta
  "pelo sistema" que costuma chegar antes disso tem a mesma regra (ver *Transbordo devolvido pela
  Poli* e *Fila de espera*).
- **Não lida depois do transbordo:** as mensagens que O Lara manda pela API deixam a conversa como
  lida, e o atendente a receberia como já visualizada. Na mesma conferência, com o transbordo
  feito, O Lara marca a conversa como **não lida** (`POST /contacts/{uuid}/unread`) com o
  `user_uuid` do atendente que a recebeu; se ela ainda estiver na fila do time, sem atendente, vai
  sem usuário. Se a Poli recusar o corpo com usuário (422), O Lara tenta sem corpo. Falha não
  desfaz o transbordo: fica como ação `unread` com ack `FAILED` em `poli_messages` e no log.
  `POLI_BOT_MARCAR_NAO_LIDA=false` desliga. Endpoint tirado da documentação e ainda não medido
  em produção — confira no painel da Poli na primeira transferência.
- **Fim do fluxo:** a conclusão sai e a conversa fica em `ending`. Se o contato escrever em até
  `POLI_BOT_ENCERRAR_APOS_MIN` (10) minutos, recebe o menu; senão a Lara encerra. Encerrar na hora
  prende a próxima mensagem do contato no atendimento fechado (medido em 29/09/2026). A ação
  "encerrar" do fluxo continua existindo para quando se quer fechar na hora (ex.: "Sair").
- **Resgate:** mensagem que cai num atendimento que **a Lara** encerrou há até
  `POLI_BOT_RESGATE_MIN` (30) minutos é trazida de volta: forward para O Lara e o menu.
- **Abandono:** conversa parada no meio do fluxo por mais que o `timeout_minutes` do fluxo é
  encerrada pelo agendamento (a Poli não encerra por inatividade os atendimentos do O Lara).
- **Reconciliação:** a cada 10 minutos, conversas abertas com O Lara sem sessão ativa na Lara são
  encerradas. Cada uma é conferida na API antes; lista maior que `POLI_BOT_RECONCILIAR_MAX` (50)
  aborta a rodada.
- **Aviso de chegada do Uber:** com humano no atendimento, só o aviso. No `on`, a conversa passa
  para O Lara antes do aviso e fecha como no fim do fluxo — a resposta do sócio chega à Lara. Em
  `shadow` e `off`, encerra logo depois do aviso, como hoje.
- **Depois do transbordo:** se ainda chegar mensagem com O Lara como atendente, a Lara confere na
  API; continuando com O Lara, ela responde.
- **Palavras de escape**, em qualquer ponto: `menu` (recomeça), `sair` (encerra), `atendente`
  ou `0` (passa para humano; `0` não vale quando o passo espera um número).
- **Atalho:** se a primeira mensagem já é o nome de uma opção do menu inicial ("financeiro"), ela
  vale como resposta. Pelo número não: quem manda "1" sem ter visto menu não escolheu nada.
- **Menu antigo:** tocar num menu de uma etapa anterior não vale como resposta da etapa atual.
  Menu da **mesma** etapa vale: depois de uma correção o menu é reenviado, e tocar no primeiro
  dos dois menus iguais é escolher (comparado por `flow_slug`/`step_key` da mensagem tocada em
  `poli_messages`).
- **Rajada de abertura:** é comum o contato mandar várias mensagens de uma vez ("Bom dia" >
  "Segue meu documento" > print). A mensagem que **não** é resposta válida e foi escrita antes da
  última fala do bot, ou até `POLI_BOT_RAJADA_S` (5) segundos depois dela, fica no histórico sem
  correção, sem menu de novo e sem gastar tentativa — o contato ainda não tinha visto a pergunta.
  Resposta válida na rajada ("Financeiro") vale normalmente. A régua é a hora em que a Poli criou
  a mensagem (`metadata.created_at`, senão `timestamp`), não a de chegada; evento sem essa hora
  é tratado como antes. Mensagem escrita depois da janela continua recebendo a correção.
- **Conversa parada** expira pelo tempo do fluxo; quem estava no meio recebe um aviso.
- **Horário de atendimento:** fora dele o fluxo começa pelo passo "fora do horário". O padrão
  segue os templates da Poli (seg–sex 07:00–19:50; sáb, dom e feriados 07:00–18:00 — o template
  *HorarioAtendimento* diz 07:10 no fim de semana; vale o *Opções*, que é o que o bot envia).
- **Pedido de carro de aplicativo** (ação `uber_request`): usa as respostas `matricula`, `nome`,
  `local`, `placa` e `print`, e passa pela mesma conferência de sócio/funcionário e validade de
  30 min do fluxo escutado. Cria o pedido em toda conversa do O Lara (nunca no simulador); a escuta do
  Uber ignora as conversas do O Lara, para não duplicar.
- **Histórico:** tudo que o bot recebe e envia fica em `poli_messages`, com CPF e datas
  mascarados. Transferências e encerramentos, com o motivo, ficam em `bot_attendance_events`
  (tela **Transferências e encerramentos**).

## Mensagens e erros

- *"O fluxo tem problemas e não foi salvo"* — a lista abaixo aponta cada passo: próximo passo
  inexistente, menu sem opções, template sem uuid, formato (regex) inválido, horário malformado,
  dois fluxos de boas-vindas ativos, goto para fluxo que não existe.
- *"Não foi possível consultar a Poli agora"* — templates e times não carregaram; os campos
  continuam editáveis e a tela tenta de novo no ↻.
- Fluxo chamado por outro não pode ser apagado: tire a referência primeiro.

## Integrações

API v3 da Poli — `App\Services\Poli\PoliClient` (envio, templates, times, encerrar,
distribuir, encaminhar, marcar como não lida, atendimento atual, chats atribuídos). Webhook de entrada:
`POST /api/webhooks/whatsapp` (o mesmo do fluxo do Uber). Reenvio da Poli
(`X-Webhook-Attempt` > 1) faz a Lara conferir o dono na API antes de agir.

## Rollback

Religar as transferências da Poli para os times humanos, `POLI_BOT_MODE=off` e então
`php artisan poli:bot-reconciliar --devolver` (distribui todas as conversas abertas com O Lara
para a Secretaria; `--devolver=<uuid>` escolhe outro time, `--simular` só lista).

## Referência técnica

- Motor: `app/Services/PoliBot/BotEngine.php`; validação de resposta: `AnswerValidator`;
  formato do fluxo: `FlowDefinition` (docblock descreve o JSON); envio: `BotOutbox`; aviso do
  Uber: `UberArrivalHandover`; diário de transferências e encerramentos: `AttendanceJournal`
  (model `BotAttendanceEvent`, motivos e rótulos em `BotAttendanceEvent::REASONS`).
- Jobs: `ProcessPoliBotRedirect` (transferência para O Lara), `ConfirmPoliBotHandoff`.
- Tela: `app/Http/Controllers/PoliBot/*`, `resources/views/poli-bot/*`, rotas `poli-bot.*`
  (`poli-bot.events.index` = Transferências e encerramentos, `GET /bot-whatsapp/ocorrencias`).
- Terminal: `php artisan poli:bot-fluxos`, `poli:bot-simular`, `poli:teste-envio`,
  `poli:bot-expirar` (agendado a cada minuto: encerramentos e a fila de espera),
  `poli:bot-sem-atendente` (a cada 3 minutos), `poli:bot-reconciliar` (a cada 10 minutos).
- Conversa sem atendente: `BotEngine::checkUnassigned()`; listagem `PoliClient::contatosComConversaAberta`.
- Fila de espera: `BotEngine::retryQueue()`; foto do time: `TeamAvailability`
  (`PoliClient::membrosDoTime`), em `bot_attendance_events.team_*`.
- Configuração: bloco `bot` de `config/poli.php`.
