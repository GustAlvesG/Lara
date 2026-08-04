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
   evento/local, com data, horário de início e término, status e as duas assinaturas. Tem ainda uma
   **descrição/justificativa** (`description`) livre e opcional, só para esclarecimentos: não entra
   no contrato nem no cálculo, e existe para tirar do campo *evento/local* o texto de justificativa
   que vinha sendo colocado ali indevidamente. Está em todos os caminhos de criação — registro
   individual, registro em massa pelo painel (`/freelancer-services/em-massa`), importação por
   planilha e o tablet (`/kiosk`) — e aparece nas listagens de serviços e do freelancer, nos cards
   de aprovação da gerência, no e-mail de aprovação da diretoria e no PDF do lote. A mesma tabela
   guarda os **aditivos**: são contratos
   com `parent_service_id` apontando para o contrato que alteram (ver *Aditivo*).

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
  No painel o formulário fica somente leitura; na API o `PUT` responde `409`. Quando o turno muda
  mesmo assim, o caminho é o **aditivo** (ver seção própria), não a edição.
- As duas assinaturas são independentes: assinar uma não impede a outra de assinar depois. O que
  a assinatura bloqueia é a **edição dos dados** e o **cancelamento**.
- Um contrato **cancelado** não pode ser assinado nem alterado.

Estados possíveis (`signatureLabel()`): `Não assinado` → `Aguardando coordenador` /
`Aguardando freelancer` → `Assinado`; ou `Cancelado`.

### Assinatura fora do prazo
O contrato existe para ser assinado **antes de o turno começar**, com tolerância de
**30 minutos** (`FreelancerService::SIGNATURE_TOLERANCE_MINUTES`). Um turno que começa 16:00 pode
ser assinado até 16:30 sem marcação; 16:31 já é fora do prazo.

- A conta é sobre a **assinatura do freelancer** (`isSignedAfterStart()`): é ela que acontece no
  momento do serviço. A do coordenador é sempre posterior — ele assina em fila, pelo tablet —, e
  cobrá-la pelo mesmo prazo acusaria praticamente todo contrato.
- **Só aparece na web**, e não bloqueia nada: é informativo. Tarja no topo da tela do contrato
  (com o horário do início, o da assinatura e o atraso) e selo na listagem de Serviços. O kiosk não
  mostra — é lá que a assinatura acontece, e apontar o atraso naquele momento não muda mais nada.
- O mesmo prazo marca o contrato que o turno já começou e **ninguém assinou**
  (`isUnsignedAfterStart()`, selo ⏳ e tarja própria). É o caso mais grave dos dois: sem assinatura
  o serviço foi prestado sem contrato firmado, e o contrato não entra em lote nem é pago. Ambos são
  filtráveis na listagem — ver *Listagem de contratos*.
- O tempo exibido é o atraso em relação ao **início do turno**, não à tolerância: assinar 16:31 num
  turno de 16:00 mostra "31min".

### Entrada na portaria (Monitor de Acesso)
O contrato é o que **autoriza o freelancer a entrar no clube**. Não há regra de acesso cadastrada
para ele como há para os terceirizados: ter serviço registrado para aquele momento é o que libera a
portaria.

A janela abre **30 minutos antes** do início do turno (`FreelancerService::ACCESS_EARLY_MINUTES`) e
fecha no **horário de término** do contrato — serviço às 08:00 entra a partir das 07:30 até as
12:00. Turno que vira a meia-noite acompanha o término no dia seguinte.

- A consulta é a **mesma do terceirizado**: o porteiro digita o CPF no Monitor de Acesso
  (`/company/access-monitor`) e o sistema procura nos dois cadastros. Se o CPF responder pelos dois,
  as duas linhas aparecem, cada uma com seu status. Ver
  [Controle de Acesso de Empresas Parceiras](../company-access-control.md#freelancer).
- **Cancelado** não libera. **Aditivado** também não: quem responde pelo período corrigido é o
  aditivo, e o base pode ter horário que não vale mais.
- A **assinatura não é exigida** — ela é colhida no tablet, dentro do clube, depois de o freelancer
  já ter passado pela portaria. Exigi-la aqui deixaria todo freelancer do lado de fora.
- Regra em `FreelancerService::allowsAccessAt()` / `accessOpensAt()` / `scopeAroundAccessWindow()`;
  a consulta e o registro ficam em `App\Services\CompanyService`.

### Aditivo (o turno mudou depois da assinatura)
Contrato tem curso: o turno é esticado, encurtado ou muda de local depois de o
freelancer já ter assinado. Como **contrato assinado não pode ser alterado**, o caminho é o
**aditivo** — um contrato novo, preso ao contrato base por `parent_service_id`, que repete tudo
dele e muda **apenas horário de início, horário de término e local**.

Freelancer, função e data **não são aceitos** na criação do aditivo: vêm do base. `end_date`,
`total_hours` e `price` continuam derivados no servidor, pelas mesmas regras (virada de dia e
blocos de 15 min) — o aditivo vale pelo **turno inteiro**, não pela diferença.

**O contrato base continua vivo — o que o aditivo tira dele é o pagamento.** Ao criar o aditivo, o
base recebe `amended_at` / `amendment_service_id`:

| O que acontece com o contrato base | Por quê |
|---|---|
| **Continua na fila de assinatura e é assinado pelas duas partes** | é um documento firmado entre as partes; assinar até o fim é o que o mantém válido |
| **Não é cancelado** — fica no histórico como o documento assinado | ele registra o que foi acordado no início do turno |
| Não entra em lote (`scopeAvailableForBatch`, `canBeBatched()`) | quem vai à aprovação é o aditivo, com o período já corrigido |
| Não aparece no Financeiro (`scopeAwaitingFinance`, `isPayable()`) | senão o mesmo turno seria pago duas vezes |

`signatureLabel()` do base segue contando a história das **assinaturas** (`Aguardando coordenador`,
`Assinado`); quem avisa que o dinheiro mudou de lugar é o `approvalLabel()`, que passa a dizer
**`Pago pelo aditivo`**. Na fila do tablet e no índice do painel o contrato aditivado aparece
marcado, para ninguém estranhar estar assinando um contrato que não será pago.

O aditivo, daí em diante, é um contrato como outro qualquer: é assinado pelas duas partes, entra
em lote, passa por gerência e diretoria e é pago.

**Quando cabe aditivo** (`FreelancerService::canBeAmended()`; o motivo da recusa vem em
`amendmentBlockReason()`, mesma frase no tablet, no painel e na exceção):

- o contrato precisa ter **ao menos uma assinatura** — sem assinatura ele ainda é editável, e
  aditivar seria criar um segundo documento onde bastava corrigir o primeiro;
- **não** pode estar cancelado, já aditivado, já pago, já aprovado pela gerência ou diretoria;
- **não** pode estar preso a um lote em tramitação — retire-o do lote antes, senão o aditivo
  trocaria o conteúdo de um lote que a gerência já está analisando.

Um aditivo **também pode ser aditivado** (o turno mudou duas vezes): ele referencia o anterior e o
documento se numera sozinho — `Termo Aditivo`, `2º Termo Aditivo`… (`amendmentOrder()`).

**Limite de 7 dias:** o aditivo **não conta**. Ele não acrescenta um dia de trabalho, remenda um
turno já contado quando o base foi criado — contá-lo faria o segundo documento do mesmo dia
estourar o limite sozinho. Por isso o registro do aditivo também não pede liberação do coordenador.

**Desfazer.** Enquanto ninguém assinou o aditivo, cancelá-lo ou excluí-lo **devolve o pagamento ao
contrato base** (limpa `amended_at`). Sem isso, um aditivo criado por engano deixaria o turno sem
nenhum contrato pagável.

**No tablet** (`POST /kiosk/service/{id}/amendment`): em *Meus contratos*, o contrato assinado
ganha o botão **Fazer aditivo** ao lado de *Assinar*. São três passos — novo início, novo término,
local (já preenchido com o do base) — e uma prévia que mostra **antes → depois** com o valor que
passa a valer. Gerado o aditivo, a tela **abre direto o documento para assinatura**: o freelancer
está ali, e aditivo sem assinatura não vale mais que o contrato que ele substituiu.

**No painel** o aditivo não é criado (a mudança acontece no balcão, com a pessoa presente), mas
aparece: a tela do contrato liga os dois documentos nos dois sentidos, e o índice marca as linhas
como *Aditivo* e *Aditivado · pago no aditivo*. Os **documentos continuam separados** — cada um com
seu PDF e suas assinaturas —, ligados pelos atalhos nas duas telas.

**O documento** é um **Termo Aditivo**, não uma segunda via do contrato: cita o contrato original e
a data em que foi firmado, diz o horário e o local que estavam valendo e os que passam a valer,
declara que o novo valor **substitui** o anterior (e não se soma a ele) e ratifica todas as demais
cláusulas — natureza autônoma, ausência de vínculo, descontos, refeição acima de 6 horas e foro.
O texto vive em `partials/amendment-clauses.blade.php` (painel) e em `amendmentClauses()`
(kiosk) — os dois precisam mudar juntos, como já acontece com o contrato original.

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
a matrícula não existe, o usuário está inativo, não é coordenador do Comercial, ou o segredo
digitado não bate.

- **Painel web** (`POST /freelancer-services`): o 1º envio volta com o formulário preenchido, a
  mensagem de aviso e o bloco "Liberação do coordenador do setor Comercial". O 2º envio leva
  `confirm_weekly_limit` + `coordinator_matricula` + `coordinator_pin`. O segredo não é repopulado
  na tela nem guardado na sessão.
- **Kiosk** (`POST /kiosk/service`): o 1º toque responde `409` com a mensagem; a tela então pergunta
  **como** o coordenador vai liberar (presente, com PIN; ou por código de e-mail) e pede o segredo.
  Erro devolve `401` com `step` (`matricula` ou `pin`), e a tela volta ao passo certo.
- Quem liberou fica gravado em `weekly_limit_authorized_by` / `weekly_limit_authorized_at`.

#### Nenhum coordenador presente: código por e-mail
`coordinator_pin` aceita **duas coisas**, ambas de 6 dígitos: o **PIN** de um coordenador
(presencial, acompanhado da matrícula dele) ou o **código enviado por e-mail** (à distância, **sem
matrícula**). É o mesmo desenho do PIN da diretoria — o código não aparece em tela nenhuma do
sistema, e é isso que prova que a liberação veio de quem tem acesso à caixa de um coordenador.

- **Um único código vai para TODOS os coordenadores do Comercial** ativos e com e-mail cadastrado.
  Não se escolhe destinatário: quem registra não precisa saber quem está de plantão, e qualquer um
  deles pode ditar o número. Cada um recebe a própria mensagem, nominal — em cópia, um veria o
  e-mail dos outros sem necessidade.
- Pedido: `POST /freelancer-services/weekly-limit-code` (web, botão dentro do próprio formulário
  via `formaction`, então nada do que já foi digitado se perde) e
  `POST /kiosk/service/weekly-limit-code` (tablet). Ambos com `throttle:6,1` — disparam e-mail para
  caixas de terceiros — e **nenhum dos dois pede matrícula**. A resposta devolve só os endereços
  mascarados e o horário de validade; nunca o código.
- Basta **uma** mensagem sair para o código servir. Uma caixa recusada é registrada no log (sem o
  código) e não derruba o pedido; só quando **nenhuma** sai é que o envio falha.
- **Sem dono, sem atribuição:** liberado por código, `weekly_limit_authorized_by` fica **nulo** —
  só `weekly_limit_authorized_at` é gravado, e a tarja do contrato diz "liberado por código enviado
  aos coordenadores do setor Comercial". Nomear um coordenador ali seria inventar: o código vale
  para todos. Só o caminho do PIN identifica uma pessoa.
- O código é preso ao par **freelancer + data do serviço**: não serve para liberar outro contrato.
  Vale **uma vez só**, expira em `FREELANCER_WEEKLY_CODE_TTL_MINUTES` (**120 min = 2h** por padrão)
  e aceita no máximo `FREELANCER_WEEKLY_CODE_MAX_ATTEMPTS` (5) tentativas. Fica guardado em hash na
  tabela `freelancer_weekly_limit_codes` (`coordinator_id` nulo; `sent_to` lista quem recebeu).
  O prazo dá folga para o coordenador ver o e-mail e responder; quem segura o risco de uma janela
  mais longa é o resto do desenho — um contrato, um uso, tentativas contadas.
- Pedir um código novo invalida o anterior, para não haver dois números válidos ao mesmo tempo.
- Na conferência, o **PIN é tentado antes do código** quando há matrícula informada — assim o uso
  normal do PIN não gasta as tentativas do código pendente.
- O envio é **síncrono** (sem fila), como o da diretoria: falha de SMTP tem de aparecer na hora
  para quem está no balcão, e não sumir numa fila.

**Janela de 7 dias.** Vale **qualquer** intervalo de 7 dias que contenha a data do serviço, não só
os 6 dias anteriores — lançar um contrato numa data anterior a outros já registrados aperta a mesma
semana e também exige liberação. `countInWeeklyWindow()` devolve a contagem da janela mais cheia.

Contratos cancelados não entram nessa contagem.

Ainda **sem** essa exigência (só aviso depois de salvar): a **edição** de um serviço já gravado e a
**importação em massa** por planilha. Na **API (bot)** o `POST` continua pedindo
`confirm_weekly_limit` + a senha do usuário de `created_by` (ver seção da API) — ou seja, ali quem
confirma ainda é o próprio atendente, não o coordenador.

Selo ⚠️ no index de Serviços e no index de Freelancers marca quem está acima do limite.

### Registro em massa pelo painel
Tela **Serviços → Em massa** (`/freelancer-services/em-massa`): várias linhas na própria página,
sem planilha. Cada linha é **freelancer** (select), **função** (select), **evento/local**, **data**,
**início** e **término**. Duração, horas pagas e valor aparecem calculados na linha e somados no
topo, mas quem calcula de verdade continua sendo o servidor.

- **Tudo-ou-nada**, como a importação por planilha: havendo um erro, nada é gravado e a tela lista
  os problemas numerados pela linha. A gravação corre em transação.
- "Adicionar linha" repete função, local, data e horários da linha anterior e deixa só o freelancer
  em branco — o caso comum é o mesmo evento com várias pessoas. Máximo de **100 linhas** por envio
  (`StoreFreelancerServicesBulkRequest::MAX_ROWS`).
- Mesmas travas do registro individual: freelancer com **cadastro incompleto** fica desabilitado no
  select e é recusado no servidor; turno precisa ter ao menos um bloco de 15 min; turno que vira a
  meia-noite é sinalizado na linha.
- **Limite de 7 dias:** as linhas do mesmo envio **contam umas com as outras**
  (`FreelancerService::rowsExceedingWeeklyLimit()`), somadas ao que já está no banco — três linhas
  do mesmo freelancer na mesma semana estouram ainda que ele não tenha nada gravado. Estourando, o
  lote inteiro só grava com a liberação do coordenador do Comercial, pedida **uma vez** para o
  envio; `weekly_limit_authorized_by` fica gravado **só nas linhas que a exigiram**.
- A liberação aqui é **pelo PIN**. O código por e-mail é preso a um contrato (coordenador +
  freelancer + data) e não cobre um lote com várias linhas — para esse caminho, use o registro
  individual.

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
  `HH:MM`, e células formatadas como data/hora no Excel são convertidas automaticamente. A coluna
  *Descrição / Justificativa* é opcional. `total_hours`, `end_date` e `price` continuam sendo
  derivados no servidor, e o alerta de limite semanal aparece resumido ao final da importação.

### Listagem de contratos: busca, filtros e ordenação
A tela **Serviços / Contratos** (`/freelancer-services`) filtra e ordena **no servidor**, por
parâmetros na URL — o endereço filtrado é compartilhável e sobrevive ao F5.

| Campo | Parâmetro | O que faz |
|---|---|---|
| Busca | `q` | nome ou **CPF** do freelancer e evento/local, em um campo só. O CPF é comparado só pelos dígitos, então `123.456` acha o que está gravado sem pontuação |
| Freelancer | `freelancer_id` | seleção direta, para quem já sabe de quem procura |
| Serviço | `function_id` | a função exercida (garçom, segurança...) |
| Assinatura | `signature` | `unsigned`, `awaiting_coordinator`, `awaiting_freelancer`, `signed`, `cancelled` |
| Registro | `issue` | falhas do registro: `any`, `none` ou uma das três (abaixo) |
| Período | `from` / `to` | pelo **dia do turno** (`start_date`), não pela data de cadastro |

**Registros com falha** (`FreelancerService::ISSUE_FILTERS`) são os desvios que a tela marca com
selo, agora procuráveis — nenhum deles bloqueia nada na hora, e sem filtro a única forma de ir
atrás deles era varrer a lista com o olho:

| Valor | Selo | Regra |
|---|---|---|
| `weekly` | ⚠️ | freelancer acima do limite de 7 dias (`flagExcessWithinCollection()`) |
| `late` | 🕒 | assinado depois do início do turno (`isSignedAfterStart()`) |
| `unsigned_late` | ⏳ | turno começou e o freelancer **nunca assinou** (`isUnsignedAfterStart()`) |
| `any` | — | qualquer uma das três |
| `none` | — | nenhuma delas — complemento exato de `any` (as duas somadas dão a lista inteira) |

`unsigned_late` é o vizinho de `late`, e mais grave: lá o contrato ao menos existe assinado, aqui o
serviço foi prestado sem contrato firmado. Usa a **mesma tolerância de 30 minutos**, para as duas
marcas aparecerem a partir do mesmo instante, e a mesma conta sobre a assinatura **do freelancer**.
Contrato cancelado não é marcado: saiu do fluxo antes de qualquer assinatura. Os dois estados são
excludentes — ou o contrato foi assinado com atraso, ou não foi assinado.

Este filtro é aplicado **em memória**, depois da consulta (`ServiceController::onlyWithIssue()`),
porque as regras já existem em PHP. Reescrevê-las em SQL criaria uma segunda versão das mesmas
regras, fadada a divergir da que a tela mostra.

**Paginação:** **20 contratos por página** (`ServiceController::PER_PAGE`), com os filtros e a
ordenação preservados na URL das páginas seguintes. Reordenar volta à primeira página — cair na
página 3 de uma ordem que acabou de mudar não leva a lugar nenhum. Filtrando por `issue`, a
paginação é feita **em memória** (`paginateCollection()`), porque a peneira das falhas roda em PHP:
paginar no banco e peneirar depois devolveria páginas de tamanhos aleatórios.

**Ordenação:** `sort=date` (padrão) ou `sort=name`, com `dir=asc|desc`. O padrão é **data
decrescente** — o contrato de ontem interessa mais que o do mês passado. Clicar no cabeçalho já
ordenado inverte a direção; clicar num novo usa a direção natural dele (data decrescente, nome de
A a Z), e os filtros ativos viajam junto na URL. Sem `dir` explícito, `name` assume `asc` e `date`
assume `desc`. Valor desconhecido em `sort`, `dir` ou `signature` é ignorado e cai no padrão.

Os estados de assinatura vivem em `FreelancerService::SIGNATURE_FILTERS` e são traduzidos para SQL
em `scopeSignatureStatus()` — mesma leitura de `signatureLabel()`, para o filtro não oferecer um
estado que a coluna não mostra.

> **O selo ⚠️ de limite semanal não é filtrado junto.** Ele é calculado sobre **todos** os serviços
> dos freelancers listados (`ServiceController::excessFlagsFor()`), e não sobre o resultado do
> filtro: filtrar por uma data esconderia justamente os outros contratos da semana que fazem o selo
> existir, e o aviso sumiria quando é mais necessário.

**A linha inteira abre o contrato** — clicar em qualquer lugar dela leva à tela do contrato (com
`Enter` pelo teclado, que a linha recebe por `tabindex`). Cliques em link, botão ou formulário
continuam sendo deles, e um clique que apenas selecionou texto não navega. O link *Ver/Editar*
permanece na coluna de ações.

Na tela do contrato, o **nome do freelancer é um link para o cadastro dele** — é de lá que se
corrige um dado que o contrato apenas reproduz.

### Financeiro (baixa de pagamento)
A tela **Serviços / Contratos** tem quatro abas — *Contratos*, *Lotes*, *Aprovação* e *Financeiro* —,
descritas em *Barra de abas* logo abaixo.

- A aba Financeiro (`/freelancer-services/financeiro`) lista **apenas os contratos assinados pelas
  duas partes e aprovados pela gerência E pela diretoria**, que não foram cancelados. Contratos
  parcialmente assinados não aparecem (é a assinatura do coordenador que confirma o serviço
  prestado), e os aprovados só chegam aqui depois de passar por um lote com os dois avais — ver
  *Lote de aprovação* e *Aprovação da diretoria*.
- Pendentes vêm primeiro, com os totais a pagar e já pagos no topo, e a **chave PIX** do freelancer
  na tabela (com botão de copiar).
- O botão **Dar baixa** grava, no próprio contrato, `paid = true`, `paid_at` (data/hora) e `paid_by`
  (usuário que efetuou a baixa). A baixa é definitiva — não há desfazer pelo painel.
- ⚠️ **Com o Pix automático ligado** (`SICOOB_PIX_ENABLED=true`), o mesmo botão passa a se chamar
  **Pagar via Pix** e **transfere o valor de verdade** para a chave do freelancer. Nesse modo o
  clique NÃO marca o contrato como pago: ele enfileira a transferência, o contrato aparece como
  *em processamento*, e a baixa só é gravada quando o banco confirma. Contrato com Pix em
  andamento sai da seleção e não aceita um segundo envio. Fluxo completo, estados e riscos em
  [Pix automático (Sicoob)](pix-sicoob.md).
- **Baixa em lote:** as caixas de seleção marcam vários contratos pendentes (com total selecionado
  em tempo real) e a barra no rodapé dá baixa em todos de uma vez. Contratos que deixaram de estar
  aptos enquanto a tela estava aberta são ignorados, e o aviso informa quantos ficaram de fora.
- **Tabela reduzida:** o interruptor no topo enxuga a lista para nome, período, chave PIX, valor e o
  botão de baixa, mostrando só os pendentes — o formato de folha de pagamento. A preferência fica
  guardada no navegador.
- A baixa também aparece na tela do contrato, junto às assinaturas.

### Barra de abas (Contratos · Lotes · Aprovação · Financeiro)
As quatro frentes do fluxo dividem a mesma barra de abas
(`resources/views/freelancer/services/partials/tabs.blade.php`), presente em todas elas e também na
tela de um lote — de qualquer uma se chega a qualquer outra, sem voltar ao menu.

**A aba só aparece para quem a rota deixa entrar** — a condição do Blade é a mesma do middleware,
para que nenhuma aba leve a um 403:

| Aba | Rota | Quem vê |
|---|---|---|
| Contratos | `freelancer-services.index` | `manage freelancers` |
| Lotes | `freelancer-batches.index` | `manage freelancers` **e** coordenador de algum setor |
| Aprovação | `freelancer-batches.queue` | `manage freelancers` **e** coordenador do setor `Gerência` |
| Financeiro | `freelancer-services.finance` | membro do setor `Contabilidade` **ou** `Gerência` |

Consequências práticas: quem só está na Contabilidade navega no Financeiro sem ver uma aba
Contratos quebrada; quem tem **só** `manage freelancers` e não coordena nada fica com uma aba única
e a barra some. A tela de um lote destaca *Aprovação* para o gerente e *Lotes* para o coordenador
que o montou (`$activeTab`).

### Lote de aprovação (gerência e diretoria)
Depois das duas assinaturas, o contrato **não vai direto para o financeiro**: ele precisa passar
por **dois níveis de aprovação**, e isso acontece em **lote**.

```
freelancer assina → coordenador assina → coordenador monta o lote → envia
    → coordenador da Gerência aprova (ou recusa) contrato a contrato
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

**Análise (gerência, só na web).** Quem aprova é o **coordenador do setor `Gerência`**
(`user_sector.role = 'coordinator'`, verificado por `User::isManagementCoordinator()`) — e mais
ninguém: a role `admin` **não** dá acesso à aprovação. Responder pelo lote é um cargo, não um nível
de acesso ao sistema. Ele abre o lote e decide **contrato a contrato** — tudo começa marcado como
aprovar, e recusar exige um motivo. Concluída a análise:

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
| `operator` | permissão `manage freelancers` | localiza/cadastra freelancer, registra contrato, faz o **aditivo** quando o turno muda e colhe a assinatura do freelancer | 30 min **ou** 5 contratos |
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
- **A aba Financeiro e a baixa de pagamento são vínculo de setor, não permissão:** acessa quem
  estiver no setor **`Contabilidade`** ou **`Gerência`**, em **qualquer papel** — colaborador ou
  coordenador. É o Gate `manage-freelancer-payments` (`AppServiceProvider`), apoiado em
  `User::canManageFreelancerPayments()`.
  - A role `admin` **não** dá acesso. Como na aprovação do lote, administrar o sistema e responder
    pelo dinheiro são coisas separadas — entra no setor quem de fato paga.
  - Não há mais a permissão `manage freelancer payments`; a migration
    `drop_manage_freelancer_payments_permission` a apagou, para não sobrar na tela de permissões
    uma linha que não concede nada. O vínculo é feito na tela de **Setores**.
  - Enquanto ninguém estiver vinculado a Contabilidade nem a Gerência, **o Financeiro fica sem
    dono**: a aba não aparece para ninguém e nenhuma baixa é possível.
  - Quem está só nesses setores, sem `manage freelancers`, enxerga no menu apenas o Financeiro.
- Cancelar **pelo painel** exige, além disso, ser **coordenador de algum setor**
  (`user_sector.role = 'coordinator'`) — verificado por `User::isCoordinator()`.
- Assinar como coordenador existe **só no kiosk** e é mais restrito: só o coordenador do setor
  **Comercial** (`User::isCoordinatorOfSectorNamed('Comercial')`). Não há rota web equivalente.
- **Liberar um serviço acima do limite de 7 dias** (painel e kiosk) também é exclusivo do
  coordenador do setor **Comercial** — por matrícula + PIN dele, ou pelo código enviado a todos os
  coordenadores do setor —, nunca pela sessão de quem registra.
- **Montar e enviar lote** exige ser coordenador de setor (web) ou estar no modo `coordinator` do
  kiosk (setor Comercial).
- **Aprovar lote** exige ser **coordenador do setor `Gerência`**
  (`User::isManagementCoordinator()`), e só existe na web. A role `admin` não vale: administrar o
  sistema e responder pela aprovação do lote são coisas separadas. Se ninguém estiver vinculado
  como coordenador da Gerência, **a fila de aprovação fica sem dono** e os lotes enviados param —
  o vínculo é feito na tela de Setores.
- Nada impede que o coordenador da Gerência monte o próprio lote e depois o aprove (montar lote é
  de coordenador de qualquer setor). Se a segregação for necessária, é uma checagem a acrescentar
  em `BatchController::review`.
- **Registrar a decisão da diretoria** também é do coordenador da Gerência: quem digita o código é
  a gerência. O controle não é de permissão e sim de conhecimento — só quem recebeu o e-mail sabe
  o código.

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
- **Aditivo:** `FreelancerService::canBeAmended()` / `amendmentBlockReason()` / `isAmended()` /
  `amendmentOrder()` / `documentTitle()` (regra), `App\Services\FreelancerService::createAmendment()`
  (criação + marcação do base, em transação) e `deleteService()` / `cancelService()` (desfazer),
  `StoreFreelancerServiceAmendmentRequest` (só local e horários),
  `KioskController::storeAmendment()`. Colunas em
  `2026_07_31_140000_add_amendment_to_freelancer_services_table`.
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
- **Entrada na portaria:** `FreelancerService::ACCESS_EARLY_MINUTES` / `accessOpensAt()` /
  `allowsAccessAt()` / `formattedAccessWindow()` / `scopeAroundAccessWindow()` (a janela),
  `App\Services\CompanyService::registerFreelancerAccess()` e os métodos privados
  `freelancerAccessEntries()` / `freelancerAccessEntry()` (a consulta por CPF do Monitor de Acesso).
  Colunas do log em `2026_08_03_130000_add_freelancer_to_company_access_logs`.
  Testes em `tests/Unit/FreelancerAccessWindowTest.php`.
- **Cálculo do período:** concentrado em `FreelancerService::minutesBetween()`,
  `crossesMidnight()` e `billedBlocks()` — a virada de dia e o arredondamento para baixo existem
  em um lugar só, reaproveitados por painel, API e validação.
