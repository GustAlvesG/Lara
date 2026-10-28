# Lara (IA) — resumo para quem vai mexer

Documentação completa (timeouts, Apache, rollout, consultas de qualidade):
[docs/funcionalidades/lara-ia.md](../funcionalidades/lara-ia.md).

Não confundir: **O Lara** é o sistema; **a Lara** (IA) é o agente do estatuto que este módulo
consulta. O usuário "O Lara" da Poli é do [Bot WhatsApp](Bot%20WhatsApp.md), outro módulo.

## Em uma frase

Chat interno (rotas `lara.*`, permissão `lara`) que repassa a pergunta do funcionário, por HTTP
síncrono, a um agente de IA numa **VM separada** e mostra a resposta. O Lara não hospeda modelo
nenhum. Desde 28/10/2026 o chat é um **balão no canto inferior direito, em toda tela**, que abre
um painel lateral (`partials/lara-widget.blade.php`, incluído no layout por `@can`). Não há tela
própria.

## Caminho de uma pergunta

0. `GET /lara` (`index`) só redireciona para a página anterior (ou o início) com o flash
   `LaraChatController::OPEN_FLASH`, que abre o balão. Mantido pelo menu, pelo Ctrl+K e por links.
1. Ao abrir o painel, `GET /lara/conversa` (`conversation`): health check
   `GET {LARA_BASE_URL}/health` (cache `LARA_HEALTH_TTL`), a conversa vigente de `lara_messages`
   pelo `conversation_uuid` da sessão e `pendente`. Última mensagem mais velha que
   `LARA_HISTORY_TTL_HOURS` → conversa nova (espelha a memória da IA). **Nunca** leve isso para a
   renderização do layout: ele roda em toda tela.
2. `POST /lara/perguntar` (`throttle:15,1`, `AskLaraRequest`): trava por usuário
   (`Cache::lock("lara:ask:{id}")`, 429 se ocupada), grava a pergunta, `LaraClient` chama
   `POST {LARA_BASE_URL}/perguntar` com `{usuario_id: "portal_{id}", mensagem}`, grava a resposta
   com `status` e `latencia_ms`.
3. `POST /lara/reiniciar`: limpa a memória do lado da IA e sorteia `conversation_uuid` novo; o
   histórico fica no banco.

## O que a IA consulta no Lara

O caminho inverso: a IA chama **O Lara** por API (`api_token`) para responder sobre o clube.
Contrato em [docs/funcionalidades/lara-ia-consultas.md](../funcionalidades/lara-ia-consultas.md).

- `GET /api/information/search` — informações do InfoClube (públicas).
- `GET /api/avisos/search` — avisos, **só os dirigidos** ao `user_id` (aceita `portal_{id}`, o
  mesmo valor que o `LaraClient` manda como `usuario_id`). Régua `Aviso::scopeAddressedTo`, não
  `scopeVisibleTo`: acesso total não amplia a busca.
- `GET /api/guias/search` — seções dos guias do usuário (`docs/guias`, ver
  [guias-do-usuario.md](../funcionalidades/guias-do-usuario.md)). Não esconde nada; cada seção
  traz `tem_acesso`, os links das telas que a pessoa abre e `precisa_de`. Acesso pelas anotações
  `<!-- telas: -->` + `Navigation::destinations()`. Busca em PHP (`GuideSearch`), sem acento.
- **Contexto da tela** em `POST /perguntar` (`contexto: {modulo, pagina, caminho, rota}`): o balão
  manda só nomes de rota; `AskLaraRequest::contexto()` valida e traduz pelo menu. Sem tela de
  módulo, o campo não vai.
- As duas herdam de `KeywordSearchRequest` e usam `App\Support\KeywordSearch`. Mudou o contrato?
  Atualize o doc acima e gere o prompt para o agente da VM da IA
  (modelo: `docs/funcionalidades/lara-ia-busca-avisos-prompt.md`).

## Regras que costumam pegar

- **Só `LARA_TIMEOUT` se ajusta.** Trava e navegador usam `+10 s`, `set_time_limit` usa `+30 s`;
  a IA desiste sozinha em 22 s. Encurtar abaixo disso descarta respostas a caminho.
- **A IA nunca "não responde"**: qualquer falha vira `LARA_FALLBACK_MESSAGE`. O que distingue é o
  `status` gravado: `ok`, `fallback` (a IA devolveu `transferir: true`), `erro` (não falamos com
  ela), `desativado`. Não remova o `status` — é a única métrica de qualidade.
- **Sem retry**, de propósito (a VM saturada é a causa mais provável da falha).
- Resposta exibida com `x-text`, **nunca** `x-html` (vem de um modelo de linguagem).
- **Troca de tela no meio da espera:** o `ask()` usa `ignore_user_abort(true)` e grava a resposta
  mesmo com a conexão fechada; na tela seguinte `pendente` faz o balão consultar a cada 3 s. Tirar
  o `ignore_user_abort` faz respostas sumirem quando a pessoa navega.
- O widget chama as rotas por URL fixa (`url('/lara/...')`), não `route()`: o layout não pode
  cair por cache de rotas.
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
| `resources/views/partials/lara-widget.blade.php` | Balão e painel lateral (Alpine), em toda tela. |
| `app/Http/Controllers/InformationSearchController.php`, `AvisoSearchController.php` | APIs que a IA consulta. |

## Testes

`tests/Unit/LaraClientTest.php` (Http fake), `tests/Feature/LaraMessageHistoryTest.php`,
`tests/Feature/LaraWidgetTest.php` (balão, `/lara/conversa`, `pendente`),
`tests/Feature/InformationSearchTest.php` e `tests/Feature/AvisoSearchTest.php`. Confira o
isolamento SQLite do `phpunit.xml` antes de rodar.
