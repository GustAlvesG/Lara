# Freelancers

## O que é

Cadastro de **freelancers**, das **funções** que podem exercer e dos **serviços/contratos** a
serem realizados, com controle de **assinaturas** (freelancer e coordenador) e **cancelamento**.

O módulo tem duas frentes:

| Frente | Onde | O que faz |
|---|---|---|
| **Painel interno** | `/freelancers`, `/freelancer-functions`, `/freelancer-services` | CRUD de freelancers, funções e serviços; assinatura do coordenador; cancelamento |
| **API** | `/api/telegram/*` | Consumida por um bot do Telegram: consulta/cadastra freelancer, consulta e atualiza serviços, registra a assinatura do freelancer |

## Entidades

1. **Freelancer** (`freelancers`) — a pessoa. CPF é único; `pix_key` assume o CPF quando não informada.
2. **Função** (`function_freelancers`) — catálogo de funções (garçom, segurança...), com **preço
   por bloco de 15 minutos**.
3. **Serviço / Contrato** (`freelancer_services`) — um trabalho de um freelancer numa função, num
   evento/local, com data, horário de início e término, status e as duas assinaturas.

## Regras de negócio

### Período trabalhado, duração e preço

O turno é informado por **data + horário de início e término** (`start_date`, `start_time`,
`end_time`). Tudo o mais é **derivado no servidor**:

| Campo derivado | Como é calculado |
|---|---|
| `end_date` | `start_date`, ou `start_date + 1 dia` quando o turno vira a meia-noite |
| `total_hours` | blocos pagos × 0,25 |
| `price` | blocos pagos × preço da função |

**Virada de dia:** quando `end_time` é **anterior** a `start_time`, entende-se que o turno
atravessa a meia-noite e termina no dia seguinte (ex.: `22:00 → 02:00` = 4h). Contratos são
sempre de um dia, então `end_date` nunca avança mais de 1 dia.

**Blocos de 15 minutos, arredondados para baixo:** o valor da função é por bloco de 15 min, e
**só bloco integralmente cumprido é pago**. Ex.: `19:00 → 22:10` (3h10) paga 12 blocos = 3h00.

Consequências dessa regra, validadas na entrada:

- `end_time` **igual** a `start_time` é recusado (evita gravar 0h ou 24h por erro de digitação);
- período **menor que 15 minutos** é recusado (arredondado para baixo daria 0 bloco e R$ 0,00).

`total_hours`, `end_date` e `price` **não são aceitos como entrada** — se enviados, são ignorados
e recalculados. Isso vale para o painel e para a API.

### Assinaturas
Cada serviço tem duas assinaturas independentes:

| Assinatura | Campos | Quem registra |
|---|---|---|
| Freelancer | `freelancer_signed_at` | **API** (bot do Telegram) |
| Coordenador | `coordinator_signed_at`, `coordinator_signed_by` | **Painel**, por usuário que seja coordenador de algum setor |

- **Um contrato com qualquer assinatura não pode mais ser alterado** — vale para painel e API.
  No painel o formulário fica somente leitura; na API o `PUT` responde `409`.
- As duas assinaturas são independentes: assinar uma não impede a outra de assinar depois. O que
  a assinatura bloqueia é a **edição dos dados** e o **cancelamento**.
- Um contrato **cancelado** não pode ser assinado nem alterado.

Estados possíveis (`signatureLabel()`): `Não assinado` → `Aguardando coordenador` /
`Aguardando freelancer` → `Assinado`; ou `Cancelado`.

### Cancelamento
- Só é possível **enquanto não houver nenhuma assinatura**.
- Feito **apenas pelo painel**, por um **coordenador de setor** (mesma regra da assinatura do
  coordenador).
- Marca `status_id = 0` (`cancelled`) e grava `cancelled_at` / `cancelled_by`. O registro é
  mantido no histórico (não é apagado).

### Exclusão
Um serviço **assinado não pode ser excluído** (o caminho é cancelar, e cancelar exige que ainda
não haja assinatura). Freelancers e funções com serviços vinculados também não podem ser excluídos.

### Alerta de limite semanal
Limite recomendado de **2 serviços por freelancer a cada 7 dias** (`FreelancerService::WEEKLY_LIMIT`),
contados por `start_date`. O 3º **não é bloqueado**, mas exige confirmação:

- no cadastro pelo painel, o aviso aparece **antes de gravar**: o formulário volta preenchido com
  a mensagem e o botão passa a ser "Confirmar e registrar" (`confirm_weekly_limit`);
- na edição e na importação em massa, o aviso continua saindo depois de salvar;
- selo ⚠️ no index de Serviços e no index de Freelancers.

Contratos cancelados não entram nessa contagem.

Na **API (bot)** o mesmo aviso vem antes de gravar: o `POST` de serviço responde `409` pedindo
confirmação e, no reenvio, exige `confirm_weekly_limit` **mais a senha do usuário logado** (ver
seção da API).

### Importação em massa por planilha
As telas **Novo Freelancer** e **Novo Serviço** trazem um bloco "Importar por planilha", com o
arquivo modelo `.xlsx` para download e o envio do arquivo preenchido.

- **Tudo-ou-nada:** as linhas são conferidas antes de qualquer gravação. Havendo um único erro,
  nada é importado e a tela lista os problemas por número de linha. Isso evita o meio-termo em que
  metade dos registros entra e o reenvio duplica o restante.
- **Mesmas regras do cadastro individual:** os importadores reaproveitam `StoreFreelancerRequest` e
  `StoreFreelancerServiceRequest`, então planilha e formulário não divergem.
- **Cabeçalho tolerante, colunas obrigatórias:** o rótulo é normalizado (acentos, caixa, `*`) e
  aceita sinônimos, mas toda coluna do modelo precisa existir.
- **CPF:** normalizado para 11 dígitos, recompondo os zeros à esquerda que o Excel corta ao tratar
  a célula como número. Na planilha de freelancers, o CPF repetido **dentro do próprio arquivo**
  também é barrado — a regra `unique` só enxergaria o banco.
- **Serviços:** o vínculo com o freelancer é feito **pelo CPF**, que já deve estar cadastrado; a
  função é casada pelo nome (ignorando caixa). Data aceita `dd/mm/aaaa` ou `aaaa-mm-dd`, horários
  `HH:MM`, e células formatadas como data/hora no Excel são convertidas automaticamente.
  `total_hours`, `end_date` e `price` continuam sendo derivados no servidor, e o alerta de limite
  semanal aparece resumido ao final da importação.

### Financeiro (baixa de pagamento)
A tela **Serviços / Contratos** tem duas abas: *Contratos* e *Financeiro*.

- A aba Financeiro (`/freelancer-services/financeiro`) lista **apenas os contratos assinados pelo
  freelancer e pelo coordenador** e que não foram cancelados. Contratos parcialmente assinados não
  aparecem: é a assinatura do coordenador que confirma o serviço prestado.
- Pendentes vêm primeiro, com os totais a pagar e já pagos no topo, e a **chave PIX** do freelancer
  na tabela (com botão de copiar).
- O botão **Dar baixa** grava, no próprio contrato, `paid = true`, `paid_at` (data/hora) e `paid_by`
  (usuário que efetuou a baixa). A baixa é definitiva — não há desfazer pelo painel.
- **Baixa em lote:** as caixas de seleção marcam vários contratos pendentes (com total selecionado
  em tempo real) e a barra no rodapé dá baixa em todos de uma vez. Contratos que deixaram de estar
  aptos enquanto a tela estava aberta são ignorados, e o aviso informa quantos ficaram de fora.
- **Tabela reduzida:** o interruptor no topo enxuga a lista para nome, período, chave PIX, valor e o
  botão de baixa, mostrando só os pendentes — o formato de folha de pagamento. A preferência fica
  guardada no navegador.
- A baixa também aparece na tela do contrato, junto às assinaturas.

### Permissões
- Todo o painel exige a permissão `manage freelancers`.
- A aba Financeiro e a baixa de pagamento exigem a permissão própria
  `manage freelancer payments` (criada apenas para o papel `admin`; os demais recebem pela tela de
  permissões). Quem tem só essa permissão enxerga no menu apenas a aba Financeiro.
- Assinar como coordenador e cancelar exigem, além disso, ser **coordenador de algum setor**
  (`user_sector.role = 'coordinator'`) — verificado por `User::isCoordinator()`.

## API

Todas as rotas exigem o header `Authorization: Bearer <API_TOKEN>` (valor de `API_TOKEN` no
`.env`). Sem ele: `401`.

| Método | Rota | Ação |
|--------|------|------|
| `POST` | `/api/telegram/user/login` | Login de usuário do sistema por matrícula (restrito ao papel `comercial`) |
| `GET` | `/api/telegram/freelancer/freelancer/{cpf}` | Consulta freelancer por CPF |
| `POST` | `/api/telegram/freelancer/freelancer` | Cadastra freelancer |
| `GET` | `/api/telegram/freelancer/functions` | Lista funções |
| `GET` | `/api/telegram/freelancer/freelancer/{cpf}/services` | Lista serviços do freelancer |
| `POST` | `/api/telegram/freelancer/service` | Registra serviço |
| `PUT` | `/api/telegram/freelancer/service/{id}` | Atualiza serviço |
| `POST` | `/api/telegram/freelancer/service/{id}/sign` | Assinatura do freelancer |

### Fluxo típico do bot

```
1. GET  /freelancer/{cpf}            -> 404? então cadastra:
2. POST /freelancer                   (com os dados da pessoa)
3. GET  /freelancer/{cpf}/services   -> lista contratos, pega o id
4. PUT  /service/{id}                -> ajusta (409 se já assinado)
5. POST /service/{id}/sign           -> freelancer assina (trava a edição)
```

### `POST /api/telegram/user/login`

| Campo | Regras |
|---|---|
| `matricula` | obrigatório |
| `password` | obrigatório, senha em texto puro (conferida com `Hash::check`) |

Respostas: `200` com os dados do usuário · `401` credenciais inválidas · `403` usuário sem o
papel `comercial`. Atualiza `last_login_at`. Não retorna token.

### `GET /api/telegram/freelancer/freelancer/{cpf}`

`200` com o freelancer, ou **`404`** (`{"error": "Freelancer não encontrado"}`) quando o CPF não
existe — é esse 404 que indica ao bot que deve cadastrar.

### `POST /api/telegram/freelancer/freelancer`

| Campo | Regras |
|---|---|
| `name` | obrigatório |
| `cpf` | obrigatório, até 11 caracteres, **único** |
| `pix_key` | opcional — se omitida, assume o valor do `cpf` |
| `email` | opcional (quando enviado, precisa ser um e-mail válido) |
| `rg`, `nacionality`, `civil_status`, `address`, `telephone` | obrigatórios |
| `created_by` | opcional, id de `users` (ver Auditoria) |

`201` com o registro criado · `422` em erro de validação (ex.: CPF já cadastrado).

### `GET /api/telegram/freelancer/freelancer/{cpf}/services`

`200` com a lista de serviços (mais recentes primeiro), ou `404` se o CPF não existir. Cada item:

```json
{
  "id": 2,
  "freelancer_id": 5,
  "function_freelancer_id": 12,
  "function": "Garçom",
  "location": "Festa de Confraternização - Salão Nobre",
  "start_date": "2026-07-22",
  "start_time": "22:00",
  "end_date": "2026-07-23",
  "end_time": "02:00",
  "crosses_midnight": true,
  "duration_minutes": 240,
  "total_hours": "4.00",
  "price": "200.00",
  "status_id": 1,
  "status": "confirmed/active",
  "freelancer_signed_at": "2026-07-21 17:31:51",
  "created_by": 7,
  "created_by_name": "João Comercial",
  "freelancer_signed_by": 7,
  "freelancer_signed_by_name": "João Comercial",
  "coordinator_signed_at": null,
  "cancelled_at": null,
  "signature_status": "Aguardando coordenador",
  "can_be_updated": false,
  "can_be_signed_by_freelancer": false
}
```

Os campos `can_be_updated` e `can_be_signed_by_freelancer` dizem ao bot o que ainda é possível
fazer, evitando uma chamada que resultaria em `409`.

### `POST /api/telegram/freelancer/service` e `PUT /api/telegram/freelancer/service/{id}`

| Campo | Regras |
|---|---|
| `freelancer_id` | obrigatório, existe em `freelancers` |
| `function_freelancer_id` | obrigatório, existe em `function_freelancers` |
| `location` | obrigatório — evento/local |
| `start_date` | obrigatório, data (dia em que o turno começa) |
| `start_time` | obrigatório, `H:i` ou `H:i:s` |
| `end_time` | obrigatório, `H:i` ou `H:i:s`; se anterior a `start_time`, vira o dia |
| `status_id` | opcional, existe em `status` (default `1`) |
| `created_by` | **obrigatório no POST** — id do usuário logado no bot, que auxilia o preenchimento |
| `updated_by` | opcional, id de `users` |
| `confirm_weekly_limit` / `password` | só no POST, e só quando o limite semanal for excedido (abaixo) |

**Não envie `price`, `total_hours` nem `end_date`** — são derivados no servidor e qualquer valor
enviado é ignorado.

Respostas: `201`/`200` · `422` validação (inclui `created_by` ausente no POST) ·
**`409` contrato já assinado ou cancelado, ou limite semanal a confirmar** · `401` senha inválida ·
`400` outras falhas.

#### Confirmação do limite semanal (POST)

Quando o serviço faria o freelancer passar do limite de 7 dias, o POST **não grava** e responde
`409`, com o que o bot precisa para perguntar ao atendente:

```json
{
  "error": "Limite semanal recomendado excedido",
  "requires_confirmation": true,
  "confirmation_field": "confirm_weekly_limit",
  "requires_password": true,
  "weekly_limit": 2,
  "services_in_window": 2,
  "services_after_save": 3,
  "message": "Com este registro, Maria da Silva passa a ter 3 serviços numa janela de 7 dias..."
}
```

O bot reenvia o **mesmo payload** acrescido de `confirm_weekly_limit: true` e `password` — a senha
do usuário de `created_by`. Senha errada devolve `401`; correta, grava normalmente (`201`).

### `POST /api/telegram/freelancer/service/{id}/sign`

Registra a assinatura do freelancer. Exige a reconfirmação da senha do usuário logado no bot:

| Campo | Regras |
|---|---|
| `user_id` | obrigatório, id de `users` — o login que está conduzindo a assinatura |
| `password` | obrigatório — senha desse usuário |

`200` com o serviço atualizado · `422` campos ausentes · `401` senha inválida · `409` se já
assinado pelo freelancer ou se o contrato estiver cancelado. O usuário confirmado fica gravado em
`freelancer_signed_by`.

## Auditoria (`created_by` / `updated_by`)

As três entidades guardam quem criou e quem alterou por último (FK nullable para `users`):

- **Painel:** preenchido com o usuário logado; qualquer valor enviado no formulário é ignorado.
- **API:** não há sessão, então o valor vem do payload (`created_by` / `updated_by`). Use
  `POST /api/telegram/user/login` para obter o `id` do usuário. Se omitido, fica `null`.
- Na criação, `updated_by` recebe o mesmo valor de `created_by`.

Serviços registram ainda `coordinator_signed_by` (quem assinou como coordenador), `cancelled_by`
(quem cancelou) e `freelancer_signed_by` — o login que **auxiliou o freelancer na assinatura pelo
bot** (quem assina continua sendo o freelancer; o campo diz quem conduziu o atendimento e
reconfirmou a senha). Junto com `created_by`, é o par que mostra quem ajudou no preenchimento e na
assinatura.

## Referência técnica

- **Rotas:** `routes/web.php` (grupo `permission:manage freelancers`) e `routes/api.php`
  (grupo `api_token`, prefixo `telegram`).
- **Controllers do painel:** `app/Http/Controllers/Freelancer/{FreelancerController,FunctionController,ServiceController}`.
- **Controllers da API:** `app/Http/Controllers/{FreelancerController,FreelancerServiceController,FunctionFreelancerController}`
  e `Auth/UserAuthController`.
- **Service:** `app/Services/FreelancerService.php` — concentra cálculo de preço, assinaturas,
  cancelamento e auditoria; é o mesmo objeto usado pelo painel e pela API, então as regras não
  divergem entre as duas frentes.
- **Models:** `Freelancer` (default do PIX), `FunctionFreelancer`, `FreelancerService`
  (`isSigned()`, `canBeUpdated()`, `canBeCancelled()`, `canBeDeleted()`, `signatureLabel()`,
  `exceedsWeeklyLimit()`, `flagExcessWithinCollection()`), `User::isCoordinator()`.
- **Importação:** `app/Imports/` — `SpreadsheetImport` (base tudo-ou-nada e geração do modelo),
  `FreelancerImport`, `FreelancerServiceImport`, `XlsxReader` (leitura crua do `.xlsx`) e
  `ImportValues` (normalização de CPF, data e hora). Formulário de envio em
  `resources/views/freelancer/partials/import-card.blade.php`; upload validado por
  `App\Http\Requests\ImportSpreadsheetRequest`. Depende de `phpoffice/phpspreadsheet`.
- **Reconfirmação de senha (API):** `App\Http\Controllers\Concerns\ConfirmsUserPassword` +
  `App\Http\Requests\SignFreelancerServiceRequest`. Como a API não tem sessão, o usuário é
  identificado pelo id no payload e reautenticado pela senha a cada ação sensível.
- **Exceptions:** `App\Exceptions\FreelancerServiceLockedException` — sinaliza contrato travado;
  vira `409` na API e mensagem de erro no painel. `SpreadsheetImportException` (planilha inteira
  inválida) e `ImportRowException` (problema em uma linha) atendem a importação.
  `PasswordConfirmationException` vira `401` na API quando a senha de confirmação não confere.
- **Trait:** `App\Http\Requests\Concerns\ValidatesServiceSchedule` — regras dos horários
  (formato, horários iguais e período mínimo de um bloco), compartilhada entre criação e edição;
  as duas últimas ficam em `FreelancerService::scheduleError()`, reaproveitada pela importação.
- **Cálculo do período:** concentrado em `FreelancerService::minutesBetween()`,
  `crossesMidnight()` e `billedBlocks()` — a virada de dia e o arredondamento para baixo existem
  em um lugar só, reaproveitados por painel, API e validação.
