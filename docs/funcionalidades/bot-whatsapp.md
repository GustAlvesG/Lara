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

## Modos (`POLI_BOT_MODE`)

| Modo | O que acontece |
|---|---|
| `off` | Nada muda: o bot da Poli atende; a Lara só escuta o fluxo do Uber. |
| `shadow` | O bot da Poli atende. A Lara processa cada conversa e **registra** o que responderia, sem enviar nada nem criar pedido. É o ensaio com tráfego real. |
| `on` | A Lara responde de verdade. **Desligue o bot da Poli antes**, senão os dois respondem. |

`POLI_BOT_LIVE_CONTACTS` (telefones com DDI ou contact_uuid) faz esses contatos receberem as
respostas de verdade mesmo em `shadow` — é o piloto. Eles recebem as do bot da Poli também.

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

- **Silêncio:** o bot não responde quando um atendente assumiu (mensagem de atendente ou
  conversa redirecionada) e volta quando o atendimento fecha — com teto de 12 h
  (`POLI_BOT_HUMAN_TIMEOUT_HOURS`). Também não responde em atendimento **aberto pela empresa**
  (cobrança, aviso de chegada do Uber) nem em atendimento encerrado — a mesma regra que o bot da
  Poli segue (medida em produção em 25/09/2026).
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
  30 min do fluxo escutado. Só cria pedido no modo `on`.
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
distribuir). Webhook de entrada: `POST /api/webhooks/whatsapp` (o mesmo do fluxo do Uber).

## Referência técnica

- Motor: `app/Services/PoliBot/BotEngine.php`; validação de resposta: `AnswerValidator`;
  formato do fluxo: `FlowDefinition` (docblock descreve o JSON); envio: `BotOutbox`.
- Tela: `app/Http/Controllers/PoliBot/*`, `resources/views/poli-bot/*`, rotas `poli-bot.*`.
- Terminal: `php artisan poli:bot-fluxos`, `poli:bot-simular`, `poli:teste-envio`.
- Configuração: bloco `bot` de `config/poli.php`.
