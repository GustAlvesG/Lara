# 13. Onde procurar erros (guia rápido)

Guia de orientação para quem vai dar manutenção no projeto. Não é sobre um módulo
específico: é o **caminho que a requisição percorre** e o que costuma quebrar em cada
parada. A regra geral é sempre a mesma: **descubra em qual camada o erro acontece antes
de abrir qualquer arquivo.**

O fluxo do sistema (ver [Arquitetura](03-arquitetura.md)):

```
Rota → Middleware → Form Request → Controller → Service → Model → View/JSON
```

---

## Passo 0 — Sempre comece pelo log

```bash
tail -n 100 storage/logs/laravel.log
```

Todo erro 500 cai aí, com a mensagem, o arquivo e a linha. **90% dos casos terminam nesse
passo.** Se o log não tiver nada, o erro não chegou ao PHP: veja o Passo 8 (servidor/deploy)
ou o console do navegador (F12 → Console/Network), porque pode ser JavaScript.

Atenção: em produção o `.env` está com `APP_DEBUG=false`, então o usuário vê só
"Server Error" — a explicação real está **sempre** no log, nunca na tela.

---

## Passo 1 — Traduza o sintoma em camada

| O que o usuário relata | Onde olhar primeiro |
|---|---|
| Erro 404 / "página não existe" | **Rota** (`routes/web.php`, `routes/api.php`) |
| Erro 403 / "não autorizado" | **Middleware** ou **Policy** (permissões) |
| Erro 419 / "página expirou" | Sessão / CSRF (formulário sem `@csrf`) |
| Erro 401 no app / integração | **Token** (JWT ou `api_token`) |
| "Salvei e voltou com erro no formulário" | **Form Request** (validação) |
| Erro 500 / tela branca | **Controller / Service / Model** — vai no log |
| Dado errado, cálculo errado, faltando | **Service** (regra de negócio) |
| Tela quebrada, campo em branco, layout | **View (Blade)** ou build do Vite |
| "Cliquei e não aconteceu nada" | **Fila (queue)** ou JavaScript |
| Só uma tela específica não carrega dados | **Model / conexão de banco** |

---

## Passo 2 — Rota (`routes/`)

Primeiro lugar quando dá 404 ou quando "o botão leva pro lugar errado".

O que verificar:
- A rota existe? `php artisan route:list | grep <nome>`
- O `name()` da rota bate com o `route('...')` usado na view? Nome errado gera erro de
  "Route not defined".
- O verbo está certo? Formulário `POST` batendo em rota `GET` dá 405.
- A rota está dentro do grupo de middleware certo? Ver Passo 3.
- **Depois de mexer em rotas em produção, é obrigatório rodar `php artisan route:cache`**
  (o `deploy_hml.sh` já faz isso). Rota nova sem limpar cache = 404 fantasma.

---

## Passo 3 — Middleware (`app/Http/Middleware/` + `bootstrap/app.php`)

Primeiro lugar quando dá **403 ou 401**. Os aliases estão registrados em
[bootstrap/app.php](../bootstrap/app.php):

| Alias | Usado para | Erro típico |
|---|---|---|
| `auth` | painel web (sessão) | manda pro login sozinho |
| `api_token` | integrações externas | 401 — token do header errado/ausente |
| `login_token` | app do sócio (JWT) | 401 — token expirado |
| `permission` / `role` | Spatie (permissões) | 403 — usuário sem a permissão |

O que verificar:
- Se é 403 no painel: o usuário **tem a permissão**? Confira em Usuários → Permissões
  ([usuarios-e-permissoes.md](funcionalidades/usuarios-e-permissoes.md)).
- Se é 401 numa integração: o token no `.env` do outro sistema bate com o daqui?
- Permissão nova criada no código só funciona depois de rodar o seeder/cadastro dela e de
  `php artisan permission:cache-reset` — o Spatie cacheia permissões.

---

## Passo 4 — Form Request (`app/Http/Requests/`)

Primeiro lugar quando **o formulário volta com erro de validação indevido** ou quando um
campo preenchido "some" ao salvar.

Cada tela tem seu `Store*Request` / `Update*Request` (ex.: `StoreFreelancerRequest`).
O que verificar:
- **`rules()`** — a regra está exigindo algo que não deveria? (`required` a mais,
  `unique` que ignora o próprio registro no update, `max` curto demais).
- **`authorize()`** — se retorna `false`, o usuário leva **403** mesmo com dados corretos.
- **Campo novo no formulário sempre precisa entrar no `rules()`.** Se não entrar, ele é
  descartado silenciosamente e o usuário jura que preencheu. Esse é o erro mais comum
  aqui: o campo aparece na tela, não dá erro nenhum, e o banco salva vazio.

---

## Passo 5 — Controller (`app/Http/Controllers/`)

O controller deve ser **fino**: recebe, valida e delega ao Service. Então o erro nele
costuma ser de *encanamento*, não de regra:

- **Model não encontrado** (`ModelNotFoundException`, erro 404): o `find()` recebeu um ID
  que não existe ou de outra empresa/conexão.
- **`$this->authorize(...)`** falhando → 403. Aí o problema está na **Policy**
  (`app/Policies/`), não no controller.
- **Retorno errado**: rota de API devolvendo `view()`, ou rota web devolvendo JSON.
- **Chamada ao Service com parâmetro na ordem errada** — dá erro de tipo, aparece no log.
- Se você achou uma regra de negócio grande escrita dentro do controller, provavelmente é
  ali que está o bug **e** ali que a arquitetura foi furada. Ver [controllers.md](controllers.md).

---

## Passo 6 — Service (`app/Services/`)

**É aqui que mora quase todo erro de "o sistema calculou errado" ou "não fez o que devia".**
Regra de negócio, transações e chamadas a sistemas externos ficam nos Services.

O que verificar:
- **Ordem das condições / `if` de regra** — datas, horários, conflitos de agendamento,
  valores a pagar.
- **`DB::transaction()`**: se uma parte gravou e outra não, veja se todo o bloco está
  dentro da transação. Services com transação: `SchedulePaymentService`,
  `ScheduleRulesService`, `FreelancerService`, `CompTimeService`, `PlaceGroupService`,
  `TournamentService`.
- **Chamada externa sem tratamento**: se o Service fala com WhatsApp, Telegram, RedeItau ou
  SQL Server e o serviço externo está fora do ar, o erro sobe como 500. Ver
  [integracoes.md](integracoes.md).
- **Fuso horário e formato de data** — causa recorrente em agendamento e banco de horas.

---

## Passo 7 — Model (`app/Models/`)

Olhe aqui quando o dado **existe no banco mas não aparece na tela** (ou o contrário).

O que verificar:
- **`$fillable`** — campo novo não listado ali **não é salvo**, e nenhum erro é exibido.
  Mesmo sintoma do Passo 4; se a validação está ok, o culpado é o `$fillable`.
- **`$casts`** — data vindo como texto, booleano vindo como `0`/`1`, decimal perdendo
  casas. Formatação estranha na tela quase sempre é cast faltando.
- **Soft Delete** (`deleted_at`): registros "sumidos" podem estar apenas excluídos
  logicamente. Para conferir: `->withTrashed()`.
- **Global Scopes** — este projeto tem filtros automáticos que enganam:
  - `Schedule` **esconde** registros com `status_id = 4` (expirados);
  - `Place` sempre ordena por nome;
  - `ScheduleRules` sempre carrega `weekdays`.
  Se uma consulta "não traz tudo", desconfie de global scope antes de culpar o `where`.
- **`$connection`** — alguns models não usam o MySQL: `Access` e `Visitor` apontam para o
  SQL Server (`mc_sqlsrv`). Erro de conexão só nessas telas = problema no SQL Server
  externo, não na aplicação. Ver [models.md](models.md).
- **Relacionamento errado** (`hasMany` × `belongsTo`) ou chave estrangeira com nome fora do
  padrão: dá "campo não existe" ou traz lista vazia.

---

## Passo 8 — View / Blade (`resources/views/`)

Quando a tela quebra mas o log está limpo:
- **Variável não passada pelo controller** → "Undefined variable".
- **`$objeto->campo` com objeto nulo** → "Attempt to read property on null". Use
  `$objeto?->campo` ou um `@if`.
- **Mudou CSS/JS e não refletiu**: falta rodar `npm run build` (o deploy faz). Em
  desenvolvimento, `npm run dev` precisa estar rodando.
- **Formulário POST sem `@csrf`** → erro 419.

---

## Passo 9 — Fila, Jobs e agendamentos

Sintoma clássico: **"salvou, mas a notificação/mensagem nunca chegou"**. Não é erro de
tela — o trabalho ficou parado na fila.

- A fila é `QUEUE_CONNECTION=database`, processada pelo **Supervisor** em produção
  (`lara-queue`). Se o worker caiu, nada é processado e **nenhum erro aparece pro usuário**.
- Jobs falhados ficam na tabela `failed_jobs`: `php artisan queue:failed`.
- Reprocessar: `php artisan queue:retry all`.
- **Depois de todo deploy o worker precisa ser reiniciado** (`php artisan queue:restart`),
  senão ele continua rodando o código antigo. Esse é um erro silencioso e traiçoeiro:
  o código está certo no servidor e o comportamento continua o antigo.
- Componentes assíncronos: `app/Jobs/`, `app/Notifications/`, `app/Console/Commands/`.
  Ver [jobs-listeners-notifications.md](jobs-listeners-notifications.md).

---

## Passo 10 — Configuração, `.env` e deploy

Quando **funcionava e parou de funcionar sem ninguém mexer no código**, o problema quase
sempre está aqui:

- **Variável do `.env` alterada** (senha de banco, token de integração, URL).
- **Cache de configuração desatualizado**: mexeu no `.env` e não rodou
  `php artisan config:cache`. O Laravel continua lendo o valor antigo.
- Sequência de limpeza segura:
  ```bash
  php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan view:clear
  ```
  e, em produção, recriar: `php artisan config:cache && php artisan route:cache`.
- **Permissão de pasta**: erro ao gravar log, PDF ou upload → `storage/` e
  `bootstrap/cache/` precisam ser graváveis pelo `www-data`.
- **Migration não rodada** ("Column not found"): `php artisan migrate:status`.
- O deploy de homologação está em [deploy_hml.sh](../deploy_hml.sh) — ele já faz backup,
  `composer install`, `npm run build`, `migrate` e os caches. Ler esse arquivo é a forma
  mais rápida de entender o que o ambiente espera.

---

## Regra de segurança — testes

> **Nunca rode `php artisan test` sem confirmar que a conexão é SQLite.**

A suíte usa `RefreshDatabase`, que **apaga todas as tabelas** do banco configurado. Isso já
destruiu o banco de homologação duas vezes. Existe uma trava em
[tests/TestCase.php](../tests/TestCase.php) que aborta a execução se o driver não for
SQLite — **não remova essa trava** e não sobrescreva `DB_CONNECTION` ao rodar testes.

---

## Resumo de bolso

1. Leia `storage/logs/laravel.log`.
2. Identifique a camada pelo código do erro (404 rota · 403 permissão · 419 CSRF · 500 código).
3. Salvou vazio → `rules()` do Request e `$fillable` do Model.
4. Cálculo/regra errada → Service.
5. Dado sumido → Soft Delete, Global Scope ou conexão do Model.
6. Nada aconteceu → fila parada (Supervisor / `failed_jobs`).
7. Parou sozinho → `.env` + `config:cache` + `route:cache`.
8. Na dúvida sobre o que a função faz, a referência de código está em
   [controllers.md](controllers.md), [services.md](services.md) e [models.md](models.md).
