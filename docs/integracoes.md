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
| `mc_sqlsrv_image` | `sqlsrv` | Configurada, sem uso no momento (era usada para blobs de fotos de sócios). |

- **Componentes:** models `Access`, `Visitor`; `MemberService::queryMember()`;
  `AccessController::queryAccess()`.

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

---

## 11.10. Lara — agente de IA (Ollama)

- **Componente:** `App\Services\Lara\LaraClient`.
- **Endpoints consumidos:** `POST /perguntar` `{usuario_id, mensagem}` → `{resposta, transferir}`,
  `POST /reiniciar` `{usuario_id}` → `{status}` e `GET /health` (2xx, sem passar pelo modelo).
- **Autenticação:** nenhuma — o endpoint é protegido só pela rede (a porta 3000 da VM da IA
  aceita apenas o IP do portal). Por isso `LARA_BASE_URL` mora no `.env` do servidor e não no
  repositório.
- **Timeout:** `LARA_TIMEOUT` (60s), sem retry. O serviço da IA desiste sozinho em 22s; a folga
  cobre a rede. Acima de ~50s é preciso subir também o `fastcgi_read_timeout` do nginx.
- **`transferir: true`** é fallback de sistema da IA (timeout, limite de 3 chamadas simultâneas
  ou erro do modelo) e vira `status = fallback`. Com `false`, é resposta de negócio e vira `ok`.
- **Indisponibilidade:** nunca propaga exceção; devolve a frase de `LARA_FALLBACK_MESSAGE`
  marcada com `status = erro` em `lara_messages`.
- **Guia de uso:** [Lara — Assistente de IA](funcionalidades/lara-ia.md).

---

## 11.11. Sicoob — Pix Pagamentos e Conta Corrente

> **Única integração do sistema que MOVE DINHEIRO.** O botão "Dar baixa" do financeiro de
> freelancers transfere o valor do contrato via Pix. Leia
> [Pix automático (Sicoob)](funcionalidades/pix-sicoob.md) antes de mexer.

- **Componentes:** `SicoobAuthService`, `SicoobPixPagamentoService`,
  `SicoobContaCorrenteService`, Job `SendFreelancerPixPayment`, comando
  `sicoob:pix-reconciliar`, model `PixPayment`, exceptions em `app/Exceptions/Sicoob/`.
- **Configuração:** `config/sicoob.php` (chave geral `SICOOB_PIX_ENABLED`, **default `false`**).
  Instalação passo a passo: [Pix Sicoob — instalação](pix-sicoob-instalacao.md).
- **Autenticação:** OAuth2 `client_credentials` **sobre mTLS** (certificado ICP Brasil em PEM).
  Token de ~300s, cacheado por 240s **por conjunto de escopos** — Pix e Conta Corrente pedem
  escopos diferentes. Todas as chamadas levam `Authorization: Bearer` **e** `client_id`.
- **Escopos:** `pixpagamentos_escrita`, `pixpagamentos_consulta` (Pix); `cco_consulta` (CC).
- **Endpoints (spec oficial "Pagamentos PIX 2.0.24.6"):**

  | Operação | Endpoint | Move dinheiro? |
  |----------|----------|----------------|
  | Iniciação DICT | `POST /pagamentos` | Não — devolve `endToEndId` e o titular da chave |
  | Efetivação | `POST /pagamentos/confirmacao` | **Sim** |
  | Consulta | `GET /pagamentos/{endToEndId}` | Não |
  | Saldo | `GET /saldo` (API Conta Corrente v4) | Não |

- **Idempotência:** o `endToEndId` gerado pelo banco na iniciação é a chave da transação, com
  índice único em `pix_payments`. Uma linha por tentativa, nunca sobrescrita.
- **Retry:** o Job tem `tries = 1` **de propósito** — reenviar uma confirmação transfere de
  novo. Quem fecha o ciclo é `sicoob:pix-reconciliar` (agendado a cada minuto), que só
  consulta.
- **Armadilhas de formato:** `valor` é **string com vírgula** (`"1234,50"`); o estado
  `NÃO_REALIZADO` vem **acentuado**.
- **Sandbox:** é um mock estático — `/pagamentos/confirmacao` sempre responde 400. Serve para
  validar mTLS/token, não o caminho feliz.
- **Log:** canal `sicoob` (`storage/logs/sicoob-*.log`, 180 dias). Nunca registra token nem
  senha de certificado.
- **Guia de uso:** [Pix automático (Sicoob)](funcionalidades/pix-sicoob.md).
