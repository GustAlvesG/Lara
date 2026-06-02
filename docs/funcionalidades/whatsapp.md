# WhatsApp

## O que é

Integração com o **WhatsApp Cloud API (Meta)** para **receber** e **enviar** mensagens,
mantendo contatos, conversas e mídias. O recebimento é assíncrono (via fila).

## Para quem

Atendimento/operação e integrações. O webhook é público (chamado pela Meta); o envio é feito
pela aplicação.

## Pré-requisitos (`.env`)

`WHATSAPP_API_URL`, `WHATSAPP_PHONE_ID`, `WHATSAPP_TOKEN`, `WHATSAPP_VERIFY_TOKEN`. É
necessário um **worker de fila** ativo (`php artisan queue:work`) para processar os eventos.

## Fluxo passo a passo

### 1. Verificação do webhook — `GET /api/whatsapp/webhook`
A Meta envia `hub_mode`, `hub_verify_token` e `hub_challenge`. Se o modo for `subscribe` e o
token bater com `WHATSAPP_VERIFY_TOKEN`, a aplicação devolve o `hub_challenge` (HTTP 200);
caso contrário, **HTTP 403**.

### 2. Recebimento de eventos — `POST /api/whatsapp/webhook`
Responde **`200 OK` imediatamente** e enfileira o processamento no job
`ProcessWhatsAppWebhook`, que:
- registra o payload (auditoria);
- cria/recupera o **Contato** pelo `wa_id`;
- abre/atualiza a **Conversa** (status `open`/`closed`);
- grava a **Mensagem** (entrada/saída);
- baixa e armazena a **mídia** (imagem, áudio, vídeo, documento, etc.) via
  `WhatsAppService::downloadMedia()`.

### 3. Envio de mensagem — `POST /api/whatsapp/send-message`
Envia um texto. O `WhatsAppService::sendText($to, $message)` faz `POST` para
`/{phoneId}/messages` (Meta Graph API v18) e registra a mensagem como `outbound`.

## Entidades

| Entidade | Campos principais |
|----------|-------------------|
| Contact | `wa_id`, `name`, `profile_pic_url` |
| Conversation | `contact_id`, `user_id`, `status`, `last_message_at` |
| Message | `conversation_id`, `wam_id`, `type`, `direction` (`inbound`/`outbound`), `body`, `status` |
| MediaAttachment | `message_id`, `whatsapp_media_id`, `file_type`, `mime_type`, `file_path`, `file_name` |

## Regras de negócio

- O recebimento é **assíncrono** (fila) — o webhook nunca bloqueia a Meta.
- A conversa controla o ciclo de atendimento (`status` + `last_message_at`), respeitando a
  janela de 24h do WhatsApp.
- Mídias são baixadas e salvas no storage; o tipo de arquivo é derivado do MIME.

## Referência técnica

- Controller: [`WhatsAppController`](../controllers.md#whatsappcontroller)
- Service: [`WhatsAppService`](../services.md#whatsappservice) · Job: [`ProcessWhatsAppWebhook`](../jobs-listeners-notifications.md#processwhatsappwebhook)
- Models: [WhatsApp](../models.md#whatsapp-appmodelswhatsapp)
- Integração: [Integrações](../integracoes.md#111-whatsapp-cloud-api-meta)
