# Bot WhatsApp — resumo para quem vai mexer

Documentação completa (regras, modos, rollback): [docs/funcionalidades/bot-whatsapp.md](../funcionalidades/bot-whatsapp.md).

## Em uma frase

A Poli é o canal do WhatsApp; O Lara decide o que responder nas conversas atribuídas ao usuário
**O Lara** da Poli (`poli.bot.user_uuid`), seguindo **fluxos** (JSON em `bot_flows`) editados na
tela **Bot WhatsApp › Fluxos** (`poli-bot.*`, permissão `bot-whatsapp`). A tela **Bot WhatsApp ›
Transferências e encerramentos** (`poli-bot.events.index`, permissão `bot-whatsapp.ocorrencias`)
mostra o diário `bot_attendance_events`: por que cada conversa chegou ao O Lara, saiu dele ou foi
encerrada.

## Caminho de uma mensagem

1. `POST /api/webhooks/whatsapp` → `UberAccessRequestWebhookController::handle`
   - indexa menu enviado, chama `BotEngine::observe()` (estado: humano assumiu, encerrado, ACK;
     e `AttendanceJournal::observe()`, que classifica transferência/encerramento no diário — ANTES
     de o job da transferência existir);
   - grava a linha em `uber_access_request_messages` (dedup por `poli_message_id`);
   - entrada: agenda `ProcessUberAccessRequestMessage` com atraso (`poli.bot.inbound_max_lag_seconds`
     na conversa do O Lara, `poli.inbound.max_delivery_lag_seconds` nas outras), ancorado na
     hora de criação da mensagem;
   - transferência para O Lara: agenda `ProcessPoliBotRedirect`.
2. `ProcessUberAccessRequestMessage`: respeita a ordem por contato (`hasPendingPredecessor`),
   pega a trava do contato (`BotEngine::exclusive`), roda a escuta do Uber (só fora do O Lara) e
   `BotEngine::handleInbound()`. Áudio/documento/figurinha vão por `parseUnsupported` (tipo `unknown`).
3. `BotEngine::process()`: idempotência por `poli_messages.uuid`, decide o dono (`ownerOf`),
   grava a entrada e conversa: escape (`menu`/`sair`/`atendente`) → resposta à pergunta em
   aberto (`answer`) → ou início (`startTriggered`).
4. Tudo que sai passa por `BotOutbox` (síncrono, em ordem; `live=false` = sombra, só registra).

## Peças

| Arquivo | Papel |
|---|---|
| `app/Services/PoliBot/BotEngine.php` | Máquina de estados; todas as regras de conversa. |
| `app/Services/PoliBot/AnswerValidator.php` | Valida resposta por tipo (`option`, `plate`, `date`, `image`…). |
| `app/Services/PoliBot/FlowDefinition.php` | Formato do JSON do fluxo (docblock) e validação ao salvar. |
| `app/Services/PoliBot/DefaultFlows.php` | Fluxos padrão (`poli:bot-fluxos --instalar`); usados nos testes. |
| `app/Services/PoliBot/BotOutbox.php` | Envio e registro em `poli_messages` (com `flow_slug`/`step_key`). |
| `app/Services/PoliBot/TeamAvailability.php` | Foto do time no momento do distribute (`PoliClient::membrosDoTime`). Não lança. |
| `app/Services/PoliBot/AttendanceJournal.php` | Diário `bot_attendance_events`: O Lara anota o que faz antes de pedir à Poli; o webhook confirma ou cria a linha. Não lança. |
| `app/Models/BotAttendanceEvent.php` | Tipos (`recebida`/`enviada`/`encerramento`), motivos e rótulos (`REASONS`), resultados do transbordo. |
| `app/Http/Controllers/PoliBot/AttendanceEventController.php` | Tela do diário (`resources/views/poli-bot/events.blade.php`). |
| `app/Services/Poli/PoliMessageParser.php` / `ParsedPoliMessage` | Payload da Poli → mensagem (texto, contexto do toque, atendente, `createdAt`). |
| `app/Models/BotSession.php` | Estado por contato: `idle`/`flow`/`human`/`ending`, `prompt_message_uuid`, `tentativas`. |
| `config/poli.php` (`bot`) | Modo, prazos, palavras de escape e textos de correção. |

## Regras que costumam pegar

- **Dono:** só conversa com atendente = O Lara e não encerrada é respondida de verdade.
  Mensagem da fase do bot da Poli processada depois da transferência só vai para o histórico (`takenByLara`).
- **Pergunta em aberto:** `prompt_message_uuid` é o menu/pergunta atual. Toque em menu de **outra
  etapa** recebe "etapa anterior"; toque em menu reenviado da **mesma etapa** vale (`isStaleMenuTap`).
- **Rajada:** resposta inválida escrita antes da última fala do bot (ou até
  `poli.bot.burst_grace_seconds`, padrão 5 s, depois) não recebe correção nem gasta tentativa
  (`writtenBeforeLastReply`). Depende de `ParsedPoliMessage::createdAt`; sem ele, comportamento antigo.
- **Correção de menu** reenvia o menu (nova `prompt_message_uuid`).
- **Transbordo:** `distribute()` do motor (todo transbordo passa por ele) grava
  `bot_sessions.handoff_team_uuid`, tira a foto do time (`TeamAvailability::snapshot` →
  `bot_attendance_events.team_size/online/available/snapshot`; disponível = ONLINE + AVAILABLE) e
  faz o distribute. `ConfirmPoliBotHandoff` (~10 s) confere na API. Não saiu do O Lara: fila de
  espera. Saiu: marca a conversa como não lida para o atendente
  (`PoliClient::marcarComoNaoLida`, ação `unread` em `poli_messages`; `poli.bot.mark_unread_on_handoff`).
- **Transbordo devolvido:** a Poli devolve o distribute para O Lara "pelo sistema"
  (`direction = EMPTY`) ~1 s depois quando ninguém do time está disponível. O diário marca a volta
  como `retorno_transbordo` (transbordo `aguardando` do contato há até
  `poli.bot.handoff_return_seconds`); `startFromRedirect` lê isso (`isReturnedHandoff`) e chama
  `handoffReturned` → `enterQueue`. Transferência "por Fulano" (`SYSTEM` com autor) é setor.
- **Fila de espera** (`BotSession::STATE_QUEUED`, `poli.bot.queue`): `toQueue($ate, $proxima)`;
  `queued_until` = `FlowDefinition::closesAt()` do fluxo de boas-vindas (sem horário:
  `queue.max_minutes`). `retryQueue()` (do `poli:bot-expirar`) confere a API e repete o distribute
  com motivo `fila_de_espera`, **sem mensagem**; a volta dessas tentativas só marca a tentativa como
  `devolvido` (sem linha `recebida`), e `startFromRedirect` com sessão na fila só registra. Vencido
  o horário: `messages.queue_expired` + `close('fila_expirou')` — a única fala da fila. Mensagem do
  contato na fila: `whileQueued` (silêncio, só `sair` encerra). Alguém pegou: `humanTookOver` /
  `leaveQueue` → `human`. A reconciliação considera a fila ativa. `queue.enabled=false` → aviso
  `handoff_failed` + `toEnding('transbordo_devolvido')`, como antes.
- **Sem atendente** (`poli.bot.unassigned`, comando `poli:bot-sem-atendente` a cada 3 min):
  `checkUnassigned()` percorre `PoliClient::contatosComConversaAberta()` (todas as abertas da conta)
  + `contatoComAtendimento()`; aberta sem atendente (`isUnassigned`; `INITIATED_BY_BUSINESS` só com
  resposta do contato no webhook gravado) em `sightings` verificações seguidas do mesmo atendimento
  → `directUnassigned()`: `distribute(..., 'sem_atendente')` para a Secretaria, sessão `human` +
  `lara_owned` (a volta vira fila de espera; a conferência marca não lida). Quem foi visto fica no
  cache `poli-bot:sem-atendente` entre as verificações. Só no horário do fluxo de boas-vindas.
- **Transferência velha:** com a sessão em `human`, `startFromRedirect` confere na API
  (`leftLara`) antes de agir; se já saiu do O Lara, só registra. Testes de setor precisam de
  `atendimentoNaApi` com O Lara (`oLaraNaApi()`).
- **Motivo no diário:** `handoff()`/`distribute()` e `close()` do motor exigem o motivo (chave de
  `BotAttendanceEvent::REASONS`). O close diferido usa `bot_sessions.ending_reason`, gravado por
  `toEnding($min, $motivo)`. Motivo novo = constante em `REASONS` + chamada com ele.
- Nada no motor lança: erro vira log. A trava por contato é de quem chama (não é reentrante).

## Testes

- `tests/Feature/Poli/PoliBotEngineTest.php` (motor com `DefaultFlows`, Http fake; envios
  devolvem `out-1`, `out-2`…), `PoliBotWebhookTest.php` (ponta a ponta pelo webhook),
  `PoliBotEditorTest.php` (tela), `PoliBotEventsScreenTest.php` (tela do diário),
  `tests/Unit/PoliMessageParserTest.php`. Os helpers `transferido()` e `encerrado()` do
  `PoliBotEngineTest` montam os eventos de sistema no formato medido em produção.
- Teste que usa `bot_sessions` precisa rodar também as migrations
  `2026_10_16_150000_create_bot_attendance_events_table` (`ending_reason`) e
  `2026_10_19_180000_add_waiting_queue_and_team_availability` (fila e foto do time).
- No `PoliBotEngineTest`, `membrosDoTime` / `timesForaDoAr` controlam o GET `/teams/{uuid}`,
  `distribuicoes()` conta os distribute e `transbordoDevolvido()` deixa a conversa na fila.
  `semAtendente()` monta uma conversa aberta sem atendente (`chatsNaApi`, `contatoNaApi`,
  `atendimentoNaApi`).
- Sem `RefreshDatabase`: as migrations necessárias rodam no `setUp`. Confira o isolamento SQLite
  do `phpunit.xml` antes de rodar. Rode só a pasta: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Poli`.
- Para simular a hora em que o contato escreveu, passe `criadaEm:` ao helper `msg()` (ou use `textoEscritoEm`).

## Comandos

`poli:bot-fluxos` (listar/validar/instalar), `poli:bot-simular`, `poli:bot-sem-atendente` (a cada 3 min), `poli:bot-expirar`
(agendado), `poli:bot-reconciliar` (`--devolver` é o rollback).
