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

### SendFreelancerPixPayment

`implements ShouldQueue` — **envia dinheiro**. Executa a tentativa de Pix de um contrato de
freelancer. Guia completo: [Pix automático (Sicoob)](funcionalidades/pix-sicoob.md).

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `__construct` | `__construct(int $pixPaymentId)` | Recebe o id da linha em `pix_payments`. |
| `handle` | `handle(SicoobPixPagamentoService $pix, FreelancerService $freelancers): void` | Recusa pagamentos fora de `pending`/`initiated` (guarda contra `queue:retry` manual); chama `enviar()`; dá baixa no contrato **apenas** se o retorno for `FINALIZADO`. |
| `failed` | `failed(?Throwable $e): void` | Log `critical`. Não tenta consertar estado — a verdade sobre o dinheiro está em `pix_payments`. |

> **`tries = 1`, e não deve ser aumentado.** Reenviar uma confirmação de Pix transfere de
> novo, e o caso em que o retry parece mais útil (timeout) é justamente aquele em que não se
> sabe se o pagamento foi processado. Quem fecha o ciclo é o comando
> `sicoob:pix-reconciliar`, que só consulta.

`timeout = 180` — folga sobre o timeout HTTP de 60s da confirmação: o worker não pode matar o
processo no meio de uma confirmação.

Disparado por `FreelancerService::requestPixForMany()` (botão "Dar baixa" do financeiro).

---

## 10.1.1. Comandos agendados relacionados

### `sicoob:pix-reconciliar`

`app/Console/Commands/ReconcilePixPayments.php` — agendado a cada minuto
(`routes/console.php`, com `withoutOverlapping`).

Consulta no Sicoob o desfecho dos pagamentos em `initiated`, `sent` e `unknown`, atualiza a
linha e dá baixa nos contratos que finalizaram. **Nunca envia pagamento** — só `GET`, e é por
isso que pode rodar de minuto em minuto. É a contrapartida obrigatória de o Job não ter retry.

Também libera **tentativas órfãs**: linhas em `pending` sem `end_to_end_id` há mais de 30
minutos (job perdido entre o commit e a fila) viram `failed`, destravando o contrato. Só esse
estado permite afirmar que nada saiu — sem `end_to_end_id`, a confirmação nunca pôde ter sido
enviada.

Um 404 na consulta é lido conforme o estado: em `initiated` (nunca confirmamos) vira `failed`;
em `sent`/`unknown` **não** conclui nada e o caso segue aberto.

### `sicoob:testar`

`app/Console/Commands/TestSicoobConnection.php` — pré-checagem manual, não agendada.

```bash
sudo -u www-data php artisan sicoob:testar --chave=<chave pix>
```

Confere configuração, leitura dos certificados, token (mTLS + escopos), saldo e consulta da
chave no DICT, apontando em qual etapa quebrou. **Não efetiva pagamento** — a iniciação DICT
só consulta e reserva um identificador. Avisa quando a resposta veio do mock do sandbox.

Rode como o usuário do PHP: testar como `root` pode passar e mascarar um problema de permissão
que só apareceria no worker, na hora do primeiro pagamento.

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

Enviado por `EmailService::processContactForm()` (formulário de contato → endereço
administrativo) e por `EmailService::sendScheduleMail()` (agendamento → e-mail do sócio). O
campo `type` dos dados escolhe a view: `schedule.confirm`, `schedule.pending`, `schedule.cancel`
ou o template de contato.

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
