# Freelancers

## O que é

Cadastro de **freelancers**, das **funções** que podem exercer e dos **serviços/contratos** a
serem realizados, com controle de **assinaturas** (freelancer e coordenador) e **cancelamento**.

O módulo tem duas frentes:

| Frente | Onde | O que faz |
|---|---|---|
| **Painel interno** | `/freelancers`, `/freelancer-functions`, `/freelancer-services` | CRUD de freelancers, funções e serviços; lotes e aprovações; cancelamento. **Não assina** |
| **Kiosk (tablet)** | `/kiosk` | Interface de toque: atendimento ao freelancer (cadastro, contrato e assinatura), fila de assinatura do coordenador e montagem/envio de lotes |
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
| Freelancer | `freelancer_signed_at`, `freelancer_signed_by`, `freelancer_signature_path` | **Kiosk** (traço desenhado no tablet, com o operador identificado) ou **API** (bot do Telegram) |
| Coordenador | `coordinator_signed_at`, `coordinator_signed_by`, `coordinator_signature_path` | **Kiosk apenas** (traço desenhado, só o coordenador do setor **Comercial**) |

Os campos `*_signature_path` guardam a imagem PNG do traço no disco público. O documento do
contrato mostra o traço quando há.

> **O painel não assina.** A assinatura eletrônica do coordenador pela web foi retirada: todo
> contrato novo só é assinado com o traço desenhado no tablet. A marca "assinado eletronicamente"
> continua no documento apenas para os contratos **antigos**, assinados pelo painel antes da
> mudança — esses ficaram sem imagem e não são reassinados.

- **Um contrato com qualquer assinatura não pode mais ser alterado** — vale para painel e API.
  No painel o formulário fica somente leitura; na API o `PUT` responde `409`.
- As duas assinaturas são independentes: assinar uma não impede a outra de assinar depois. O que
  a assinatura bloqueia é a **edição dos dados** e o **cancelamento**.
- Um contrato **cancelado** não pode ser assinado nem alterado.

Estados possíveis (`signatureLabel()`): `Não assinado` → `Aguardando coordenador` /
`Aguardando freelancer` → `Assinado`; ou `Cancelado`.

### Cancelamento
- Só é possível **enquanto não houver nenhuma assinatura**.
- Feito **apenas pelo painel**, por um **coordenador de setor** (de qualquer setor — a restrição ao
  Comercial vale só para a assinatura).
- Marca `status_id = 0` (`cancelled`) e grava `cancelled_at` / `cancelled_by`. O registro é
  mantido no histórico (não é apagado).

### Exclusão
Um serviço **assinado não pode ser excluído** (o caminho é cancelar, e cancelar exige que ainda
não haja assinatura). Freelancers e funções com serviços vinculados também não podem ser excluídos.

### Limite semanal e liberação pelo coordenador
Limite de **2 serviços por freelancer a cada 7 dias** (`FreelancerService::WEEKLY_LIMIT`),
contados por `start_date`. O 3º (e cada um depois dele) **não é bloqueado**, mas só é gravado com
**aviso + liberação do coordenador do setor Comercial**, que informa a **própria matrícula** e o
**próprio PIN** de 6 dígitos. Quem registra o contrato não se autoriza sozinho: no painel a
liberação não é o login da sessão, e no tablet não é o PIN do operador.

A regra vive em `AuthorizesCommercialCoordinator` — fonte única do painel e do kiosk. Recusa quando
a matrícula não existe, o usuário está inativo, não é coordenador do Comercial, não tem PIN, ou o
PIN está errado.

- **Painel web** (`POST /freelancer-services`): o 1º envio volta com o formulário preenchido, a
  mensagem de aviso e o bloco "Liberação do coordenador do setor Comercial". O 2º envio leva
  `confirm_weekly_limit` + `coordinator_matricula` + `coordinator_pin`. O PIN não é repopulado na
  tela nem guardado na sessão.
- **Kiosk** (`POST /kiosk/service`): o 1º toque responde `409` com a mensagem; a tela então pede,
  em dois passos, a matrícula e o PIN do coordenador. O reenvio leva os mesmos três campos. Erro
  devolve `401` com `step` (`matricula` ou `pin`), e a tela volta ao passo certo.
- Quem liberou fica gravado em `weekly_limit_authorized_by` / `weekly_limit_authorized_at`.

**Janela de 7 dias.** Vale **qualquer** intervalo de 7 dias que contenha a data do serviço, não só
os 6 dias anteriores — lançar um contrato numa data anterior a outros já registrados aperta a mesma
semana e também exige liberação. `countInWeeklyWindow()` devolve a contagem da janela mais cheia.

Contratos cancelados não entram nessa contagem.

Ainda **sem** essa exigência (só aviso depois de salvar): a **edição** de um serviço já gravado e a
**importação em massa** por planilha. Na **API (bot)** o `POST` continua pedindo
`confirm_weekly_limit` + a senha do usuário de `created_by` (ver seção da API) — ou seja, ali quem
confirma ainda é o próprio atendente, não o coordenador.

Selo ⚠️ no index de Serviços e no index de Freelancers marca quem está acima do limite.

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

- A aba Financeiro (`/freelancer-services/financeiro`) lista **apenas os contratos assinados pelas
  duas partes e aprovados pela gerência E pela diretoria**, que não foram cancelados. Contratos
  parcialmente assinados não aparecem (é a assinatura do coordenador que confirma o serviço
  prestado), e os aprovados só chegam aqui depois de passar por um lote com os dois avais — ver
  *Lote de aprovação* e *Aprovação da diretoria*.
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

### Lote de aprovação (gerência e diretoria)
Depois das duas assinaturas, o contrato **não vai direto para o financeiro**: ele precisa passar
por **dois níveis de aprovação**, e isso acontece em **lote**.

```
freelancer assina → coordenador assina → coordenador monta o lote → envia
    → gerente aprova (ou recusa) contrato a contrato
        → e-mail automático à diretoria, com dois PINs
            → diretor dita o PIN, gerência digita → financeiro paga
```

**Montagem (coordenador, web ou tablet).** Cada coordenador mantém **um rascunho por vez**
(`freelancer_service_batches.status = 'draft'`). Ele inclui e retira contratos à vontade e, quando
fecha, envia. Entram no rascunho os contratos assinados pelas duas partes, não cancelados, ainda
não aprovados e fora de qualquer lote em aberto (`FreelancerService::scopeAvailableForBatch`).
Descartar o rascunho solta os contratos de volta para a fila — nada se perde.

**Envio.** O lote passa a `sent` e **congela**: nem o coordenador mexe mais nele, nem os contratos
entram em outro lote. No tablet o envio pede o PIN, como as assinaturas.

**Análise (gerente, só na web).** O gerente é um usuário com a role **`admin`** do Spatie. Ele abre
o lote e decide **contrato a contrato** — tudo começa marcado como aprovar, e recusar exige um
motivo. Concluída a análise:

- **aprovado** → grava `manager_approved_at` / `manager_approved_by`; o contrato segue para a
  diretoria;
- **recusado** → grava `manager_rejected_at` / `_by` / `_reason`, e o contrato **volta para a fila
  do coordenador**, que o vê com o motivo da recusa e pode incluí-lo num lote seguinte.

Se sobrou ao menos um contrato aprovado, o lote vai para `awaiting_director` e **o e-mail à
diretoria dispara na hora**. Se a gerência recusou tudo, o lote encerra em `closed` e nada é
enviado.

O tablet **não tem tela de aprovação**, por decisão de processo: monta e envia, só.

### Aprovação da diretoria (por PIN ditado)
O diretor **não acessa a plataforma** — a rede é interna e ele pode estar fora. Como nenhum link
para o sistema o alcançaria, o retorno vem por um caminho humano com prova de origem:

1. A gerência aprova o lote → o sistema gera **dois PINs de 6 dígitos** (um aprova, outro recusa) e
   envia um e-mail à diretoria com os dois códigos e o **PDF da relação de contratos** em anexo.
2. O diretor lê o e-mail, decide e **dita o código escolhido** para a gerência (telefone, WhatsApp,
   pessoalmente — tanto faz).
3. A gerência digita o código na tela do lote. **O próprio código diz qual foi a decisão** — não há
   botão "aprovar" separado.

O PIN é o que prova que a decisão partiu de quem recebeu o e-mail: ele **não aparece em lugar nenhum
da interface**, só no corpo da mensagem.

| Aspecto | Como é |
|---|---|
| Armazenamento | **Cifrado** (`encrypted`), não hash — o reenvio precisa repetir os mesmos números, senão o e-mail que o diretor tem em mãos deixaria de valer |
| Geração | Uma vez por lote (`ensureDirectorPins`); reenviar **não** troca os códigos |
| Comparação | `hash_equals`, tempo constante |
| Força bruta | `throttle:10,1` na rota, e a mensagem de erro não diz qual dos dois códigos falhou |
| Serialização | `$hidden` no model, para não vazar em JSON por acidente |

**A decisão vale para o lote inteiro** (o diretor não tem tela para escolher item a item):

- **aprovado** → `director_approved_at` em cada contrato aprovado pela gerência; o lote vira
  `director_approved` e os contratos aparecem no Financeiro;
- **recusado** → `director_rejected_at`; o lote vira `director_rejected` e **todos os contratos
  voltam para a fila do coordenador**, refazendo o trâmite desde o início.

Fica registrado quem digitou (`director_decided_by` — o gerente, não o diretor), quando, para qual
e-mail a mensagem foi, e uma observação livre ("informado por telefone em 24/07").

**O envio é síncrono, não enfileirado.** Com fila, uma falha de SMTP ficaria invisível e o lote
travaria em silêncio. Aqui, se o e-mail não sai, a aprovação da gerência **é gravada assim mesmo**,
a tela avisa o erro e oferece **"Reenviar à diretoria"**.

Destinatário em `config/freelancers.php`, via `.env`:

```
FREELANCER_DIRECTOR_NAME="Diretoria"
FREELANCER_DIRECTOR_EMAIL=diretor@clubedosfuncionarios.com.br
FREELANCER_DIRECTOR_CC=secretaria@clubedosfuncionarios.com.br
```

Sem `FREELANCER_DIRECTOR_EMAIL` a tela avisa que falta configurar e desabilita o envio.

### Kiosk (tablet)
`/kiosk` é uma tela de toque **fora da sessão web**: entra-se com **matrícula + PIN de 6 dígitos**
(`users.pin`, definido na tela de Usuários) e a sessão fica guardada do lado do servidor. O PIN é
**reconfirmado a cada assinatura**.

Dois modos, decididos pelo que o usuário é — quem acumula os dois papéis escolhe ao entrar:

| Modo | Quem entra | O que faz | Limite da sessão |
|---|---|---|---|
| `operator` | permissão `manage freelancers` | localiza/cadastra freelancer, registra contrato e colhe a assinatura do freelancer | 30 min **ou** 5 contratos |
| `coordinator` | **coordenador do setor `Comercial`** (`user_sector.role = 'coordinator'`) | assina os contratos que aguardam a contraparte e monta/envia o lote para a gerência | 30 min (sem teto de contratos) |

- A fila do coordenador traz os **50 mais antigos** primeiro — são os que travam o financeiro — e
  recarrega a cada assinatura.
- O coordenador assina o **mesmo documento** que o freelancer assinou, já com o traço da outra
  parte à vista, no campo do CONTRATANTE. A assinatura é definitiva e libera o contrato para a
  aba Financeiro.
- O papel é **reconferido a cada requisição**: retirar a permissão ou o vínculo de coordenação no
  painel derruba na hora a sessão aberta no tablet.

### Permissões
- Todo o painel exige a permissão `manage freelancers`.
- A aba Financeiro e a baixa de pagamento exigem a permissão própria
  `manage freelancer payments` (criada apenas para o papel `admin`; os demais recebem pela tela de
  permissões). Quem tem só essa permissão enxerga no menu apenas a aba Financeiro.
- Cancelar **pelo painel** exige, além disso, ser **coordenador de algum setor**
  (`user_sector.role = 'coordinator'`) — verificado por `User::isCoordinator()`.
- Assinar como coordenador existe **só no kiosk** e é mais restrito: só o coordenador do setor
  **Comercial** (`User::isCoordinatorOfSectorNamed('Comercial')`). Não há rota web equivalente.
- **Liberar um serviço acima do limite de 7 dias** (painel e kiosk) também é exclusivo do
  coordenador do setor **Comercial**, e por matrícula + PIN dele, não pela sessão de quem registra.
- **Montar e enviar lote** exige ser coordenador de setor (web) ou estar no modo `coordinator` do
  kiosk (setor Comercial).
- **Aprovar lote** exige a role **`admin`**, e só existe na web. Hoje nada impede que um admin que
  também seja coordenador aprove o próprio lote — se a segregação for necessária, é uma checagem a
  acrescentar em `BatchController::review`.
- **Registrar a decisão da diretoria** também exige a role `admin`: quem digita o código é a
  gerência. O controle não é de permissão e sim de conhecimento — só quem recebeu o e-mail sabe o
  código.

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

Quando o serviço passa do limite de 7 dias, `weekly_limit_authorized_by` / `weekly_limit_authorized_at`
guardam qual coordenador do Comercial liberou e quando — sem isso a autorização não deixaria rastro.

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
