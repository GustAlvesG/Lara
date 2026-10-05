# Bot WhatsApp — resumo para quem vai mexer

Documentação completa (regras, modos, rollback): [docs/funcionalidades/bot-whatsapp.md](../funcionalidades/bot-whatsapp.md).

## Em uma frase

A Poli é o canal do WhatsApp; a Lara decide o que responder nas conversas atribuídas ao usuário
**O Lara** da Poli (`poli.bot.user_uuid`), seguindo **fluxos** (JSON em `bot_flows`) editados na
tela **Bot WhatsApp** (`poli-bot.*`, permissão `bot-whatsapp`).

## Caminho de uma mensagem

1. `POST /api/webhooks/whatsapp` → `UberAccessRequestWebhookController::handle`
   - indexa menu enviado, chama `BotEngine::observe()` (estado: humano assumiu, encerrado, ACK);
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
- Nada no motor lança: erro vira log. A trava por contato é de quem chama (não é reentrante).

## Testes

- `tests/Feature/Poli/PoliBotEngineTest.php` (motor com `DefaultFlows`, Http fake; envios
  devolvem `out-1`, `out-2`…), `PoliBotWebhookTest.php` (ponta a ponta pelo webhook),
  `PoliBotEditorTest.php` (tela), `tests/Unit/PoliMessageParserTest.php`.
- Sem `RefreshDatabase`: as migrations necessárias rodam no `setUp`. Confira o isolamento SQLite
  do `phpunit.xml` antes de rodar. Rode só a pasta: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Poli`.
- Para simular a hora em que o contato escreveu, passe `criadaEm:` ao helper `msg()` (ou use `textoEscritoEm`).

## Comandos

`poli:bot-fluxos` (listar/validar/instalar), `poli:bot-simular`, `poli:bot-expirar`
(agendado), `poli:bot-reconciliar` (`--devolver` é o rollback).
