# Lara (IA) — resumo para quem vai mexer

Documentação completa (timeouts, Apache, rollout, consultas de qualidade):
[docs/funcionalidades/lara-ia.md](../funcionalidades/lara-ia.md).

Não confundir: **O Lara** é o sistema; **a Lara** (IA) é o agente do estatuto que este módulo
consulta. O usuário "O Lara" da Poli é do [Bot WhatsApp](Bot%20WhatsApp.md), outro módulo.

## Em uma frase

Chat interno (`/lara`, rotas `lara.*`, permissão `lara`) que repassa a pergunta do funcionário,
por HTTP síncrono, a um agente de IA numa **VM separada** (Ollama + base do estatuto) e mostra a
resposta. O Lara não hospeda modelo nenhum.

## Caminho de uma pergunta

1. `GET /lara` (`LaraChatController::index`): health check `GET {LARA_BASE_URL}/health` (cache
   `LARA_HEALTH_TTL`) e a conversa vigente de `lara_messages`, pelo `conversation_uuid` da sessão.
   Última mensagem mais velha que `LARA_HISTORY_TTL_HOURS` → conversa nova (espelha a memória da IA).
2. `POST /lara/perguntar` (`throttle:15,1`, `AskLaraRequest`): trava por usuário
   (`Cache::lock("lara:ask:{id}")`, 429 se ocupada), grava a pergunta, `LaraClient` chama
   `POST {LARA_BASE_URL}/perguntar` com `{usuario_id: "portal_{id}", mensagem}`, grava a resposta
   com `status` e `latencia_ms`.
3. `POST /lara/reiniciar`: limpa a memória do lado da IA e sorteia `conversation_uuid` novo; o
   histórico fica no banco.

## Regras que costumam pegar

- **Só `LARA_TIMEOUT` se ajusta.** Trava e navegador usam `+10 s`, `set_time_limit` usa `+30 s`;
  a IA desiste sozinha em 22 s. Encurtar abaixo disso descarta respostas a caminho.
- **A IA nunca "não responde"**: qualquer falha vira `LARA_FALLBACK_MESSAGE`. O que distingue é o
  `status` gravado: `ok`, `fallback` (a IA devolveu `transferir: true`), `erro` (não falamos com
  ela), `desativado`. Não remova o `status` — é a única métrica de qualidade.
- **Sem retry**, de propósito (a VM saturada é a causa mais provável da falha).
- Resposta exibida com `x-text`, **nunca** `x-html` (vem de um modelo de linguagem).
- Desligar sem deploy: `LARA_ENABLED=false` ou `LARA_BASE_URL` vazio — e `php artisan
  config:cache` depois de mexer no `.env` (o deploy deixa a config em cache).
- O endereço da VM **não vai para o repositório**; a segurança é de rede (porta 3000 só para o
  IP do portal), sem token.
- Retenção: `app:prune-lara-messages --days=90` existe mas **não está agendado** em
  `routes/console.php`.

## Peças

| Arquivo | Papel |
|---|---|
| `app/Http/Controllers/LaraChatController.php` | Tela, pergunta, reinício, trava e expiração da conversa. |
| `app/Http/Requests/AskLaraRequest.php` | Tamanho da pergunta (`LARA_MAX_INPUT_CHARS`). |
| `app/Services/Lara/LaraClient.php`, `LaraAnswer.php` | HTTP com a VM e o DTO da resposta. |
| `app/Models/LaraMessage.php` | Histórico (`role`, `conteudo`, `status`, `latencia_ms`, `erro`). |
| `config/services.php` (`lara`) e `.env.example` (`LARA_*`) | Configuração. |
| `resources/views/lara/index.blade.php` | Tela (Alpine). |

## Testes

`tests/Unit/LaraClientTest.php` (Http fake) e `tests/Feature/LaraMessageHistoryTest.php`. Confira o
isolamento SQLite do `phpunit.xml` antes de rodar.
