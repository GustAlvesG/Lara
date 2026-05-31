# 11. Integrações Externas

Resumo das integrações com sistemas externos, suas configurações e os componentes
envolvidos.

---

## 11.1. WhatsApp Cloud API (Meta)

- **Componentes:** `WhatsAppController`, `WhatsAppService`, `ProcessWhatsAppWebhook`,
  models em `app/Models/WhatsApp/`.
- **Configuração (`.env`):** `WHATSAPP_API_URL`, `WHATSAPP_PHONE_ID`, `WHATSAPP_TOKEN`,
  `WHATSAPP_VERIFY_TOKEN`.
- **Fluxo de entrada:**
  1. A Meta valida o webhook em `GET /api/whatsapp/webhook` (challenge × `WHATSAPP_VERIFY_TOKEN`).
  2. Eventos chegam em `POST /api/whatsapp/webhook`; o controller despacha o Job
     `ProcessWhatsAppWebhook` e responde `200 OK` imediatamente.
  3. O Job cria/atualiza `Contact`, `Conversation`, `Message` e baixa mídias
     (`MediaAttachment`) via `WhatsAppService::downloadMedia()`.
- **Fluxo de saída:** `POST /api/whatsapp/send-message` → `WhatsAppService::sendText()`
  (Meta Graph API v18).
- **Janela de 24h:** `Conversation` controla o status (`open`/`closed`) e a última mensagem.

---

## 11.2. Telegram

- **Componentes:** `TelegramContactController`, `TelegramService`, model `TelegramContact`
  (tabela `contact_telegram`).
- **Uso:** cadastro/consulta de contatos para notificações de equipe, expostos sob
  `/api/telegram/*` (middleware `api_token`).

---

## 11.3. RedeItau (gateway de pagamento)

- **Componentes:** `RedeItauService`, usado por `SchedulesService` (estornos) e `TestController`.
- **Configuração (`config/services.php` → `rede`):**
  - `INTERNAL_EREDE_AUTH_URL` → `auth_url`
  - `INTERNAL_EREDE_API_URL` → `base_url`
  - `INTERNAL_EREDE_CLIENT_ID` → `client_id`
  - `INTERNAL_EREDE_SECRET_ID` → `client_secret`
- **Operações:** `authenticate()` (OAuth client credentials), `getTransaction($tid)`,
  `beginRefund($payments_ids)` (estorno de pagamentos de agendamentos cancelados).

---

## 11.4. SQL Server — MultiClubes

Base externa para sócios, acessos físicos e visitantes. Duas conexões em `config/database.php`:

| Conexão | Driver | Uso |
|---------|--------|-----|
| `mc_sqlsrv` | `sqlsrv` | Dados de acesso (`Analytics.RealizedAccesses`), visitantes (`dbo.Visitors`), consultas de sócios. |
| `mc_sqlsrv_image` | `sqlsrv` | Blobs de fotos de sócios. |

- **Componentes:** models `Access`, `Visitor`; `MemberService::queryMember()` e
  `getPhotoBlob()`; `AccessController::queryAccess()`.
- **Observação:** as fotos binárias são convertidas em base64 para entrega via API
  (`MemberAuthController@getImage`).

---

## 11.5. E-mail (SMTP / Mailable)

- **Componentes:** `EmailService`, `ContactMail` (enfileirado), `ResetPasswordNotification`
  e templates em `resources/views/emails/`.
- **Configuração (`.env`):** `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`,
  `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`.
- **Casos de uso:** confirmação/pendência/cancelamento de agendamento
  (`emails/schedule/*`), formulário de contato (`emails/general_contact`), pagamento
  (`emails/payment`) e redefinição de senha.

---

## 11.6. Home Assistant (automação de iluminação)

- **Endpoint:** `GET /api/schedule/home-assistant/automation` (público, sem tokens).
- **Componente:** `ScheduleController@homeAssistantAutomation` → `SchedulesService::homeAssistantAutomation()`.
- **Função:** retorna o estado dos contatores (com base nos agendamentos ativos) para que
  o Home Assistant ligue/desligue a iluminação dos espaços.

---

## 11.7. LDAP

- **Configuração:** `config/ldap.php` presente para integração com diretório corporativo
  (autenticação). Verifique as variáveis de ambiente correspondentes antes de habilitar.

---

## 11.8. PDF (DomPDF)

- **Pacote:** `barryvdh/laravel-dompdf`.
- **Endpoint:** `GET /api/schedule/generate-pdf` → `ScheduleController@generateDailySchedulePDF`.
- **Função:** gera o PDF da agenda diária dos espaços.

---

## 11.9. FTP (Flysystem)

- **Pacote:** `league/flysystem-ftp`.
- **Componente:** `FtpController::getImage($imageName)` — recupera imagens via disco FTP.
