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

Quem tem a permissão **`manage whatsapp bot`**. Salvar um fluxo ativo é **publicar**: ele vale na
próxima mensagem dos associados.

## Pré-requisitos

- Tabelas: `php artisan migrate` (bot_flows, bot_sessions, poli_messages, bot_flow_versions).
- Permissão: `php artisan db:seed --class=RolesAndPermissionsSeeder` (o papel `admin` recebe todas)
  e atribuir `manage whatsapp bot` a quem vai editar.
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
2. Nome e identificador (o identificador não muda depois: é por ele que outros fluxos o chamam).
3. **Quando começa:** em qualquer primeira mensagem (boas-vindas — só um fluxo ativo pode), por
   palavras-chave, ou só quando outro fluxo mandar para ele.
4. Monte os passos. Para menus da Poli, escolha o template e clique em **Usar as opções deste
   template** — as opções aceitas vêm dele, e em cada uma se escolhe para onde a conversa vai.
5. Use **Guardar a resposta como** para reaproveitar respostas nos textos seguintes
   (`{placa}`, `{nome}`; `{contato}` é o primeiro nome do WhatsApp).
6. Salve **inativo** e teste no **Simulador** (▶ Testar este). O simulador nunca envia nada e
   aceita rascunhos.
7. Marque **Ativo** e salve.

O **Mapa do fluxo** mostra para onde cada passo leva e marca **sem caminho** o passo que nenhum
outro alcança. **Versões** guarda cada gravação, com quem salvou; restaurar carrega na tela para
conferir e salvar.

## Regras de negócio

- **Silêncio:** chegou mensagem ou transferência com outro atendente (humano pelo painel, ou o
  próprio transbordo da Lara), a conversa vira `human` e a Lara para de responder.
- **Transbordo:** o horário é conferido **na hora do transbordo** — fora dele a conversa vai para o
  passo "fora do horário" (do fluxo atual, ou do fluxo de boas-vindas se o atual não tem
  horário). Depois do distribute, um job confere em ~10 s (`POLI_BOT_CONFIRMAR_TRANSBORDO_S`)
  se o atendimento saiu mesmo do O Lara; se não saiu, o contato é avisado e a falha vai para o log.
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
- **Conversa parada** expira pelo tempo do fluxo; quem estava no meio recebe um aviso.
- **Horário de atendimento:** fora dele o fluxo começa pelo passo "fora do horário". O padrão
  segue os templates da Poli (seg–sex 07:00–19:50; sáb, dom e feriados 07:00–18:00 — o template
  *HorarioAtendimento* diz 07:10 no fim de semana; vale o *Opções*, que é o que o bot envia).
- **Pedido de carro de aplicativo** (ação `uber_request`): usa as respostas `matricula`, `nome`,
  `local`, `placa` e `print`, e passa pela mesma conferência de sócio/funcionário e validade de
  30 min do fluxo escutado. Cria o pedido em toda conversa do O Lara (nunca no simulador); a escuta do
  Uber ignora as conversas do O Lara, para não duplicar.
- **Histórico:** tudo que o bot recebe e envia fica em `poli_messages`, com CPF e datas
  mascarados.

## Mensagens e erros

- *"O fluxo tem problemas e não foi salvo"* — a lista abaixo aponta cada passo: próximo passo
  inexistente, menu sem opções, template sem uuid, formato (regex) inválido, horário malformado,
  dois fluxos de boas-vindas ativos, goto para fluxo que não existe.
- *"Não foi possível consultar a Poli agora"* — templates e times não carregaram; os campos
  continuam editáveis e a tela tenta de novo no ↻.
- Fluxo chamado por outro não pode ser apagado: tire a referência primeiro.

## Integrações

API v3 da Poli — `App\Services\Poli\PoliClient` (envio, templates, times, encerrar,
distribuir, encaminhar, atendimento atual, chats atribuídos). Webhook de entrada:
`POST /api/webhooks/whatsapp` (o mesmo do fluxo do Uber). Reenvio da Poli
(`X-Webhook-Attempt` > 1) faz a Lara conferir o dono na API antes de agir.

## Rollback

Religar as transferências da Poli para os times humanos, `POLI_BOT_MODE=off` e então
`php artisan poli:bot-reconciliar --devolver` (distribui todas as conversas abertas com O Lara
para a Secretaria; `--devolver=<uuid>` escolhe outro time, `--simular` só lista).

## Referência técnica

- Motor: `app/Services/PoliBot/BotEngine.php`; validação de resposta: `AnswerValidator`;
  formato do fluxo: `FlowDefinition` (docblock descreve o JSON); envio: `BotOutbox`; aviso do
  Uber: `UberArrivalHandover`.
- Jobs: `ProcessPoliBotRedirect` (transferência para O Lara), `ConfirmPoliBotHandoff`.
- Tela: `app/Http/Controllers/PoliBot/*`, `resources/views/poli-bot/*`, rotas `poli-bot.*`.
- Terminal: `php artisan poli:bot-fluxos`, `poli:bot-simular`, `poli:teste-envio`,
  `poli:bot-expirar` (agendado a cada minuto), `poli:bot-reconciliar` (a cada 10 minutos).
- Configuração: bloco `bot` de `config/poli.php`.
