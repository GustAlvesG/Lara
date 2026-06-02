# 10. Jobs, Listeners, Notifications, Mail e Providers

## 10.1. Jobs (`app/Jobs/`)

### ProcessWhatsAppWebhook
`implements ShouldQueue` — processa, de forma assíncrona, os eventos recebidos do webhook
do WhatsApp.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `__construct` | `__construct(array $payload)` | Recebe e armazena o payload bruto do webhook. |
| `handle` | `handle(WhatsAppService $whatsappService)` | Registra o payload (log/auditoria); extrai mensagens (texto, mídia, botões); cria/recupera o `Contact` pelo `wa_id`; gerencia a `Conversation` (abertura/fechamento); persiste `Message` (entrada/saída); baixa e armazena mídias (`MediaAttachment`) via `WhatsAppService::downloadMedia()`. |

Disparado por `WhatsAppController@handleWebhook`. Requer um worker de fila ativo
(`php artisan queue:work`).

---

## 10.2. Listeners (`app/Listeners/`)

### UpdateLastLoginAt
| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `handle` | `handle(Login $event): void` | Atualiza `users.last_login_at = now()` para o usuário que acabou de autenticar. |

Registrado em `AppServiceProvider::boot()` via `Event::listen(Login::class, UpdateLastLoginAt::class)`.

---

## 10.3. Notifications (`app/Notifications/`)

### ResetPasswordNotification
Notificação de redefinição de senha (canal e-mail), em Português.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `via` | `via(object $notifiable): array` | Retorna `['mail']`. |
| `toMail` | `toMail($notifiable)` | Monta o e-mail: assunto "Solicitação de redefinição de senha", link com o token de redefinição e texto explicativo. |
| `toArray` | `toArray(object $notifiable): array` | Representação em array (vazia). |

---

## 10.4. Mail (`app/Mail/`)

### ContactMail
`extends Mailable implements ShouldQueue` — e-mail do formulário de contato (enfileirado).

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `__construct` | `__construct(array $data)` | Recebe os dados do contato. |
| `envelope` | `envelope(): Envelope` | Define remetente/assunto. |
| `content` | `content(): Content` | Define a view do corpo do e-mail. |

Enviado por `EmailService::processContactForm()`.

---

## 10.5. Providers (`app/Providers/`)

### AppServiceProvider
| Método | Descrição |
|--------|-----------|
| `register()` | Registro de bindings (padrão). |
| `boot()` | Registra o listener do evento `Login` (`UpdateLastLoginAt`). |

### JwtServiceProvider
| Método | Descrição |
|--------|-----------|
| `register()` | Registra `JwtService` como **singleton** no container. |
| `boot()` | — |

### View Components (`app/View/Components/`)
- `AppLayout` e `GuestLayout` — componentes Blade de layout (Breeze), renderizam
  `layouts.app` e `layouts.guest`.
