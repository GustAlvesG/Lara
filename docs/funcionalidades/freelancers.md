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

1. **Freelancer** (`freelancers`) — a pessoa. CPF é único; `pix_key` assume o CPF quando não
   informada, e o **tipo** dela é lido da própria chave já normalizada (ver *Conferência da chave PIX*).
   Tem **foto de identificação** (`image`) — ver *Foto de identificação*.
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

### O que o contrato diz: dia e valor (e não o horário)

O corpo do contrato ajusta **dia** e **valor**. O horário do turno **não aparece em documento
nenhum** — nem no contrato, nem no termo aditivo, nem no termo de comissão.

**Internamente nada mudou:** `start_time`, `end_time`, `total_hours` e os blocos de 15 minutos
continuam gravados e são eles que calculam o valor, abrem a janela da portaria, alimentam as telas
de operação (listagens, montagem de lote, aprovação, financeiro e a prévia do aditivo no tablet) e
as relações impressas. O que saiu foi a **menção ao período de horas no texto do instrumento**, que
descrevia como contratação por hora um ajuste que é por dia.

Onde isso apareceu, e como ficou:

| Documento | Antes | Agora |
|---|---|---|
| Contrato (cláusula 2) | "R$ X, por dia, no horário de 19:00 ás 22:00" | "R$ X, por dia, previamente acordado" |
| Termo aditivo (cláusula 2) | horário antigo → horário novo, duração e "acréscimo de 3h" | o período foi alterado (datas só quando mudam); a repercussão é no valor, cláusula 4 |
| Termo aditivo (cláusula 4) | sempre "passa a ser R$ X" | diz **permanece** quando o valor não muda |
| Termo de comissão (cláusula 2) | "no período do dia 05/08, das 14:00 às 22:00" | "na prestação de serviços do dia 05/08" |

Duas exceções deliberadas:

- a **cláusula 7** do contrato (refeição na prestação superior a 6 horas) continua como está: é a
  condição de um benefício que o clube concede, não a descrição do período contratado. O aditivo
  passou a citá-la por número ("o fornecimento de refeição previsto na cláusula 7") em vez de
  repetir as 6 horas;
- o **Anexo I** do termo de comissão continua trazendo o período da **apuração** das vendas. Ali o
  horário não descreve a contratação: é o recorte que define quais vendas entraram na conta que o
  freelancer está conferindo, e sem ele o anexo deixa de ser verificável.

### O documento é congelado na assinatura

O corpo do contrato é montado na hora de exibi-lo: a **redação** vem dos templates e os **dados das
partes**, do cadastro. As duas coisas mudam depois de o contrato ser assinado — o jurídico reavalia
uma cláusula, o freelancer corrige o endereço, alguém renomeia uma função. Sem congelá-las, revisar
um texto reescreveria, retroativamente, **todo contrato já assinado**, inclusive os pagos e
arquivados — e uma varredura feita depois leria o texto novo em documentos firmados sob o antigo.

Por isso a **primeira assinatura**, de qualquer das partes, fecha o documento:

| O que congela | Onde fica | O que garante |
|---|---|---|
| A redação das cláusulas | `freelancer_services.contract_version` | o contrato continua sendo impresso com o texto que as partes leram |
| A qualificação do freelancer e o nome da função | `freelancer_services.signed_snapshot` (JSON) | o preâmbulo continua citando quem ele era no dia — nome, CPF, RG, nacionalidade, estado civil e endereço |
| A chave PIX do pagamento | `freelancer_services.pix_key` | ver *Conferência da chave PIX* |

Enquanto **ninguém assinou**, o contrato acompanha a redação vigente e o cadastro vivo — é o que ele
vai assinar. A partir da assinatura, `contractParty()` e `contractFunctionName()` param de olhar o
cadastro. O bloco é tomado **inteiro**, e não campo a campo: misturar um RG congelado com um endereço
vivo produziria uma qualificação que nunca existiu.

**A redação é versionada em arquivo, não copiada para o banco.** O texto de cada versão mora em
`resources/views/freelancer/services/partials/contract/vN/` — quatro parciais: `original-clauses`,
`amendment-clauses`, `commission-clauses` e `pix-clause`. Guardar o HTML renderizado de cada contrato
seria fiel, mas não responderia à pergunta da varredura ("quais contratos estão na redação antiga?"),
nem deixaria o jurídico comparar duas redações por diff. Com a versão em arquivo, a varredura é uma
consulta por uma coluna indexada, e o histórico está no git.

O **Anexo I** do termo de comissão fica **fora** do versionamento: ele imprime o `sales_report` já
gravado, é anexo de dados apurados e não redação jurídica.

#### Revisar uma cláusula: cria-se a redação seguinte

| Passo | O quê |
|---|---|
| 1 | copie `contract/vN` para `contract/vN+1` e edite o texto — **nunca** edite uma versão em uso |
| 2 | acrescente a entrada em `FreelancerService::CONTRACT_VERSIONS` (rótulo, vigência, o que mudou) e suba `CONTRACT_VERSION_CURRENT` |
| 3 | registre o sha256 dos arquivos da vN em `tests/Unit/FreelancerContractVersionTest.php` |

O passo 3 é o **lacre**: um teste confere o hash de cada arquivo das redações já em uso e quebra o
build se algum mudar. Sem ele, a regra "não edite a versão antiga" seria só um comentário — e o jeito
mais provável de a garantia se perder é alguém "corrigindo" uma vírgula na v1.

Contratos assinados **antes** destas colunas ficam com `signed_snapshot` nulo e caem no cadastro do
freelancer — que é exatamente o que o documento deles citava antes. Mesma decisão tomada para a
`pix_key` legada. Os assinados ganham `contract_version = 1` na migration: a redação 1 é a única que
existiu até aqui.

Na tela do contrato aparece qual redação ele firmou e se a qualificação é a congelada. Quando o
cadastro muda depois da assinatura (`contractPartyDivergesFromFreelancer()`), a tela avisa — o
documento cita o dado antigo, que é o **correto**, e sem o aviso pareceria erro. Na listagem, o
filtro **Redação** varre por versão, com a opção *ainda não congelada* para os que ninguém assinou.

#### O documento é montado num lugar só

O texto vivia em **dois** lugares: os parciais Blade do painel e funções JavaScript que o tablet usava
para montar o documento na tela. Com o versionamento, cada revisão teria de ser escrita duas vezes — e
no dia em que as duas divergissem, o freelancer assinaria no tablet um texto diferente do que o painel
imprime, sendo o do tablet o que ele de fato leu.

O tablet **não monta mais o documento**. Ele o busca pronto em
`GET /kiosk/service/{id}/document?role=freelancer|coordinator`, que renderiza o mesmo
`partials/contract-document.blade.php` do painel. O parcial recebe:

- `layout` — `print` (painel: cabeçalho e rodapé em `thead`/`tfoot`, que o navegador repete a cada
  página impressa) ou `tablet` (rola numa tela só);
- `signing` — `freelancer`, `coordinator` ou nulo: **qual dos dois campos recebe o canvas** da
  assinatura. O painel não assina, então passa nulo e nenhum campo tem canvas.

Saíram do JavaScript do kiosk `buildDocument()`, `originalClauses()`, `amendmentClauses()`,
`commissionClauses()`, `pixClause()` e `salesAnnex()` — e, com elas, os campos do payload que só
existiam para alimentá-las (texto das cláusulas, dados do contrato aditado, chave citada e o
relatório do Anexo I).

**Corrida entre ler e assinar.** O documento chega com a redação que exibe, e o tablet a reenvia na
assinatura. Publicada uma redação nova enquanto a tela estava aberta, o servidor responde `409` com
`contract_version_changed` e o documento é recarregado — o mesmo desenho do `pix_key_changed`. O
campo é **opcional**: uma tela aberta desde antes do deploy não o envia, e recusar a assinatura por
isso seria pior que a corrida que ele protege. A trava só existe na assinatura do **freelancer**: o
coordenador sempre assina um contrato que o freelancer já assinou, e portanto de redação já congelada.

### Conferência da chave PIX (etapa que antecede a assinatura)
No tablet, **toda** assinatura do freelancer — contrato, aditivo de horário e comissão — passa
antes por uma tela que mostra a chave PIX do cadastro e pergunta se é a dele. Existe porque uma
chave errada já mandou o pagamento para a conta de outra pessoa, e porque o documento que ele
assina em seguida **cita essa chave**.

- A tela mostra o **tipo** da chave e a chave **formatada** (CPF pontuado, telefone em
  `+55 (24) 99999-8888`). Quando a chave é igual ao CPF — o padrão de quem nunca informou outra —
  o aviso diz isso, para o operador não confirmar no automático.
- Dizendo que está errada, o operador abre o formulário de correção: escolhe o **tipo**
  (CPF, telefone, e-mail ou chave aleatória) e digita a nova chave. O tipo é **escolhido, não
  deduzido**: 11 dígitos tanto são um CPF quanto um celular com DDD, e o palpite errado manda o
  Pix para outro domicílio bancário. `PUT /kiosk/freelancer/{id}/pix-key`; a troca fica em log com
  autor, horário e as chaves mascaradas.
- Validação e normalização moram no model (`Freelancer::pixKeyError()` e `normalizePixKey()`), para
  valerem igual no tablet, no painel e na API: CPF vira só dígitos, telefone vira `+55DDNNNNNNNNN`,
  e-mail vira minúsculas.
- Ao assinar, o tablet **reenvia a chave conferida** junto com o PIN e o traço. Se ela não for mais
  a do cadastro (alteração no painel, outra sessão, tela esquecida aberta), o servidor responde
  `409` com `pix_key_changed` e a conferência recomeça — o documento à frente do freelancer citava
  a chave antiga.

**A chave é copiada para o contrato na assinatura** (`freelancer_services.pix_key` +
`pix_key_confirmed_at`, gravados por `signAsFreelancer()`). O cadastro guarda a chave que vale
hoje; o contrato assinado guarda a que estava à vista quando ele assinou. Sem essa cópia, editar o
cadastro reescreveria, retroativamente, o texto de todo contrato já assinado.

- Contratos anteriores a esta etapa, e os assinados pela **API** (que não tem tela de conferência),
  ficam com `pix_key_confirmed_at` nulo. Os assinados pela API a partir de agora ganham a cópia da
  chave, sem o carimbo de conferência; os antigos caem no cadastro do freelancer, que é o que o
  documento citava antes.
- **O pagamento continua saindo para a chave do cadastro**, não para a cópia do contrato: se o
  freelancer trocou de chave depois de assinar, é a nova que ele quer receber. Quando as duas
  divergem (`pixKeyDivergesFromFreelancer()`), a tela do contrato e a tabela do Financeiro avisam,
  com as duas chaves à vista, antes de qualquer baixa.

O documento traz a cláusula **DA FORMA DE PAGAMENTO** logo abaixo da cláusula do valor — `2.1` no
contrato, `4.1` nos dois aditivos. É sub-item de propósito: acrescentar um item na numeração
corrida deslocaria as cláusulas do modelo, que os outros documentos citam pelo número. O texto está
em `services/partials/contract/vN/pix-clause.blade.php`, um lugar só — ver *O documento é congelado
na assinatura*.

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

### Redação 2: a coordenação valida, a diretoria assina

A partir da **redação 2** do contrato (`FreelancerService::CONTRACT_VERSIONS[2]`), quem assina o
documento pelo **CONTRATANTE** é o **diretor**, e não mais o coordenador:

```
freelancer assina no tablet → [08h do dia seguinte] → coordenador do Comercial VALIDA pela web
    → lote → gerência aprova → e-mail à diretoria → diretor dita o código de aprovação
        → a assinatura do diretor (imagem cadastrada) entra no documento
```

| | Redação 1 | Redação 2 |
|---|---|---|
| Passo da coordenação | assinatura desenhada **no tablet** | **validação pela web**, com PIN, contrato a contrato |
| Quem assina pelo CONTRATANTE no documento | o coordenador (traço) | o diretor (imagem do cadastro + "Assinado digitalmente por {nome} em {data}") |
| Quando o CONTRATANTE fica assinado | na assinatura do coordenador | na aprovação do lote pela diretoria |

**Quem decide o fluxo é a redação congelada, não a data de hoje.** A redação é gravada na primeira
assinatura do freelancer (ver *O documento é congelado na assinatura*), e cada entrada de
`CONTRACT_VERSIONS` diz quem assina pelo CONTRATANTE (`contractor_signature`: `coordinator` ou
`director`, lido por `usesDirectorSignature()`). Por isso os dois fluxos convivem na transição: o que o
freelancer assinou sob a redação 1 termina como começou, com o traço do coordenador no tablet; o que
ele assina depois do deploy segue a redação 2. A fila do tablet (`scopeAwaitingCoordinator`) passou a
mostrar só a redação 1 e se esvazia sozinha.

**A validação grava as mesmas colunas da assinatura** (`coordinator_signed_at` / `_by`). Nas duas
redações elas registram o mesmo fato — a coordenação confirmou o serviço — e é por elas que lote,
financeiro e acompanhamento andam; criar colunas novas obrigaria a mexer em todas essas travas e
deixaria duas fontes para a mesma coisa. Na redação 2 nada disso vai ao documento:
`coordinator_signature_path` fica vazio e a marca "assinado eletronicamente" dos contratos antigos não
aparece. O que muda são os rótulos: "Aguardando validação da coordenação", "Validado por…".

#### Validação pela web (um contrato por vez)

Aba **Validação** (`/freelancer-services/validacao`), só do **coordenador do setor Comercial** (Gate
`validate-freelancer-contracts`) — o mesmo cargo que assinava no tablet.

- A **fila** traz os contratos da redação 2 assinados pelo freelancer, já liberados (08h do dia
  seguinte ao turno, a mesma espera da assinatura no tablet) e sem validação
  (`scopeAwaitingCoordinatorValidation`), os mais antigos primeiro. **Não tem seleção nem ação em
  massa**: cada linha só abre o contrato.
- A **tela do contrato** mostra o documento inteiro — o mesmo parcial e o mesmo CSS da impressão
  (`partials/contract-document-styles.blade.php`) — e, ao pé dele, o bloco da validação: PIN (o
  `users.pin`, o mesmo do tablet) e o botão **Validar contrato**. O bloco chega **travado** e só é
  liberado quando a rolagem alcança o fim do documento.
- **No servidor**, o POST recebe **um** contrato, pela rota, e exige a **marca de abertura** que a tela
  gravou na sessão para aquele contrato — nova a cada abertura, consumida na tentativa (acertando ou
  errando o PIN). Validar em série sem abrir as páginas esbarra nela.
- A rolagem até o fim é conferida no navegador; o servidor não tem como prová-la. O que ele garante é
  que cada validação passou pela tela daquele contrato, um de cada vez, com o PIN digitado.
- Validado, o contrato fica disponível para a montagem de lote. Não há "recusar": o que não estiver
  certo se resolve como hoje (aditivo, cancelamento antes da assinatura).
- Regra em `FreelancerService::coordinatorValidationBlockReason()` (cancelado, redação 1, freelancer
  sem assinar, já validado, turno não liberado) e em
  `App\Services\FreelancerService::validateAsCoordinator()` (mais a trava de cadastro incompleto).
  `signAsCoordinator()` e o tablet recusam a redação 2 com `409`.

#### A assinatura do diretor

- É aplicada por `FreelancerBatchService::applyDirectorPin()` quando o código ditado é o de
  **aprovação**, e só nos documentos da redação 2 (`awaitsDirectorSignature()`). Grava
  `freelancer_services.freelancer_director_id` e `director_signed_at` — colunas próprias, e não
  `director_approved_at` (ver o contrato base, abaixo).
- **É a assinatura de quem recebeu o e-mail.** O envio grava no lote o cadastro do destinatário
  (`freelancer_service_batches.freelancer_director_id`); se a gerência mudar o cadastro entre o envio e
  a digitação do código, o documento continua levando a assinatura de quem de fato decidiu.
- **Vale para contrato, aditivo e comissão.** Comissão e aditivo vão a lote e são assinados na
  aprovação dele. O **contrato base que ganhou aditivo de horário** não vai a lote — quem paga é o
  aditivo —, então é assinado **pela aprovação do aditivo que o substituiu**
  (`documentsReplacedByThis()`, que sobe a cadeia inclusive no aditivo do aditivo). Por isso a
  assinatura não usa `director_approved_at`: marcar o base como aprovado o faria parecer pagável. A
  comissão **não** sobe a cadeia: o contrato do turno continua indo a lote e é assinado na aprovação
  dele.
- **Sem a imagem, o lote não segue.** `notifyDirector()` recusa enviar um lote que tenha contrato da
  redação 2 aprovado pela gerência enquanto o cadastro da diretoria estiver sem a imagem da
  assinatura — senão a aprovação não teria o que aplicar, e os contratos ficariam aprovados com o
  CONTRATANTE em branco. Lote só com redação 1 segue sem imagem.
- O e-mail avisa o diretor de que o código de aprovação também **assina**, e quantos documentos.
- No documento, a imagem entra como **data URI** (`FreelancerDirector::signatureDataUri()`): o mesmo
  HTML é impresso pelo painel, exibido no tablet e convertido em PDF, e a assinatura de uma pessoa não
  tem URL pública. O arquivo mora no disco **privado** (`local`).

### Assinaturas
Cada serviço tem duas assinaturas independentes (na redação 2, o passo do coordenador é a
**validação** pela web, gravada nas mesmas colunas — ver a seção anterior):

| Assinatura | Campos | Quem registra |
|---|---|---|
| Freelancer | `freelancer_signed_at`, `freelancer_signed_by`, `freelancer_signature_path` | **Kiosk** (traço desenhado no tablet, com o operador identificado) ou **API** (bot do Telegram) |
| Coordenador | `coordinator_signed_at`, `coordinator_signed_by`, `coordinator_signature_path` | Redação 1: **kiosk apenas** (traço desenhado, só o coordenador do setor **Comercial**). Redação 2: **validação pela web**, sem traço (`coordinator_signature_path` vazio). Nas duas, **a partir das 08h do dia seguinte ao turno** — ver *Liberação para a coordenação* |
| Diretoria (redação 2) | `freelancer_director_id`, `director_signed_at` | Aplicada na aprovação do lote pela diretoria — ver *Redação 2* |

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

### Liberação para a coordenação (08h do dia seguinte)
O freelancer assina no **começo** do serviço, e o turno ainda muda depois disso: estica, encurta,
troca de local, ganha comissão de venda. Cada uma dessas mudanças é um **aditivo**, e o aditivo só
existe enquanto o dia corre.

Por isso o contrato **não segue para a contraparte no mesmo dia**. Ele espera até as
**08h da manhã seguinte ao dia do turno** (`FreelancerService::RELEASE_HOUR`), e só então:

- pode ser **assinado pelo coordenador** — antes disso ele nem aparece na fila do tablet
  (`scopeAwaitingCoordinator`), e uma tentativa direta é recusada com `409`;
- pode **entrar num lote** — a lista de disponíveis não o oferece (`scopeAvailableForBatch`) e
  `addServices()` o ignora.

O que a regra **não** impede é o aditivo: é justamente para ele que a espera existe. Registrar
contrato, assinar como freelancer, aditivar e lançar comissão continuam funcionando no dia.

**O dia de referência é `start_date`**, o dia do turno em todo o módulo: um turno 22:00→02:00 do
dia 5 pertence ao dia 5 e é liberado às 08h do dia 6 — não do dia 7. A contrapartida é o caso raro
do turno que termina **depois** das 08h da manhã seguinte (um 20:00→10:00): ele é liberado enquanto
ainda corre. A regra é de data, e esticá-la para cobrir esse caso exigiria comparar hora no SQL.

A conta vive em dois lugares e é de propósito que sejam a mesma:

| Onde | O quê |
|---|---|
| `hasBeenReleased()` / `releasesAt()` | decide por registro, em PHP |
| `scopeReleased()` / `lastReleasedDate()` | filtra no banco, comparando **só datas** |

A comparação do banco é por data porque somar horas em SQL muda de MySQL para SQLite — e uma tela
que lista o que o servidor depois recusa é pior que a trava não existir. `lastReleasedDate()`
traduz a hora para uma data: antes das 08h, o último dia liberado é o de **anteontem**; a partir
das 08h, o de **ontem**. Um teste confere as duas implementações uma contra a outra numa matriz de
horários e datas.

Na tela de **Acompanhamento** esses contratos têm fila própria — *Aguardando o fim do dia* — com o
horário exato da liberação em cada linha. É diferente de *Aguardando assinaturas*: aqui não falta
ninguém assinar, falta o relógio, e não há a quem cobrar.

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
- A linha do freelancer mostra a **foto de identificação** dele, como a do terceirizado; sem foto,
  a inicial do nome.

### Foto de identificação
Capturada no cadastro do freelancer (novo e edição), com a mesma câmera/importação do cadastro de
terceirizado. Existe para o porteiro reconhecer quem está entrando — é a foto que o Monitor de Acesso
exibe.

- Toda foto sai do navegador **quadrada, 600px, em JPEG**: a importada é recortada no centro, para
  uma foto crua de celular não subir com vários MB.
- Gravada como arquivo em `public/images/freelancer_<uuid>.<ext>` (coluna `freelancers.image`
  guarda só o nome), no mesmo lugar das fotos de terceirizado. O tipo é conferido pelos **bytes**, e
  não pelo cabeçalho do data URL — conteúdo que não é imagem é recusado.
- **Salvar sem foto nova mantém a atual.** A foto anterior não é apagada do disco: a migrada é o
  mesmo arquivo do cadastro de terceirizado.
- Conversão em `FreelancerService::withStoredImage()` — vale para o painel, o tablet e a API do bot,
  que passam todos por `create()` / `updateFreelancer()`.

**Fotos que já existiam.** Antes deste campo, quem precisava ser reconhecido na portaria era
cadastrado também como terceirizado, e é lá que as fotos estavam. O comando abaixo aponta cada
freelancer para a foto do terceirizado de **mesmo CPF** (comparado só pelos dígitos — há documento
de terceirizado gravado com máscara):

```
php artisan migrate
php artisan freelancers:migrar-fotos --dry-run   # confere a lista, não grava
php artisan freelancers:migrar-fotos
```

- Freelancer que **já tem foto não é tocado** (a menos de `--sobrescrever`) — rodar de novo não
  desfaz uma foto tirada depois pelo formulário.
- Mesmo CPF em mais de um terceirizado: vale o cadastro **ativo** antes do excluído e o **mais
  recente** entre eles; o comando lista esses casos. Terceirizado excluído ainda serve quando é o
  único — a foto continua sendo da pessoa.
- Foto cujo **arquivo não está** em `public/images` não é gravada (seria imagem quebrada na
  portaria) e sai listada.
- Grava direto na tabela, sem mexer em `updated_at`/`updated_by`: ninguém editou o cadastro.

### Jantar do turno noturno
O freelancer que cumpre **6 horas ou mais** e está em serviço em **algum momento da janela do
jantar** (**17:30 às 18:30**) tem direito à refeição. O direito vem da regra; o que o sistema guarda
é a **resposta** do freelancer — a cozinha dimensiona quantos pratos preparar por ela, e um direito
que ninguém confirmou não vira prato.

São **dois critérios, e valem juntos**:

1. duração do turno de **360 minutos ou mais** (`FreelancerService::DINNER_MIN_MINUTES`);
2. **cruzar a janela do jantar** — não é preciso cobri-la inteira. **Meia janta é janta:** quem sai
   às 18:00 pegou meia hora de jantar e come, e o mesmo vale para quem entra às 18:00.

O que **não** conta é encostar na borda: sair às **17:30 em ponto** é sair quando o jantar começa, e
entrar às **18:30 em ponto** é chegar quando ele acabou — nenhum dos dois esteve ali em minuto
nenhum da janela.

**A partir de 31/08/2026** (`FreelancerService::DINNER_STARTS_ON`) — é o dia em que a cozinha passa
a servir o jantar. Antes disso nenhum turno dá direito, por mais que cumpra os dois critérios: não
havia refeição para oferecer, e perguntar seria prometer prato que a cozinha não faz. O corte é pelo
**dia do jantar**, não pela data da assinatura: um turno do dia 30 assinado no dia 31 continua sem
jantar, e um turno que entra 22:00 do dia 30 e sai no dia 31 **tem** — ele janta no dia 31.

| Turno | Pergunta? | Por quê |
|---|---|---|
| 14:00 → 20:00 | **Sim** | 6h e atravessa a janela |
| 16:00 → 22:00 | **Sim** | 6h e atravessa a janela |
| 12:00 → 18:00 | **Sim** | 6h e pega meia janta (17:30 → 18:00) |
| 18:00 → 00:30 | **Sim** | 6h30 e pega meia janta (18:00 → 18:30) |
| 16:00 → 20:00 | Não | 4h — menos de 6h |
| 19:00 → 03:00 | Não | 8h, mas entrou depois das 18:30 |
| 11:30 → 17:30 | Não | sai no minuto em que o jantar começa |
| 14:00 → 20:00 **em 30/08/2026** | Não | antes da estreia do jantar |

**Quando a pergunta é feita.** No tablet, na tela **imediatamente seguinte à da assinatura** —
"Vai jantar?", com **Sim** e **Não**. Antes da assinatura não se pergunta: o turno ainda pode ser
corrigido, e resposta dada sobre horário que mudou não vale nada. O PIN **não** é pedido de novo:
quem responde é o freelancer, sobre a própria refeição, e o PIN do operador acabou de ser conferido
na assinatura.

Quem decide se a pergunta aparece é o **servidor** (`needsDinnerAnswer()`), não a tela. Se o
freelancer sair antes de responder, o contrato continua na lista de contratos dele com o botão
**Jantar** — a pergunta não se perde por o atendimento ter sido encerrado.

**A resposta é registrada uma vez só.** Mudou de ideia depois de responder, resolve-se com a
cozinha: uma resposta que vai e volta durante a tarde é um prato a mais ou a menos sem que ninguém
saiba.

**O dia do jantar não é sempre `start_date`.** Um turno que vira a meia-noite pode alcançar a janela
do dia **seguinte** (22:00 → 20:00 janta no dia seguinte), e é nesse dia que a cozinha precisa vê-lo.
Por isso a resposta grava `dinner_date`, e é por ela — nunca pela data de início do contrato — que a
[API da cozinha](#get-apifreelancerdinners) consulta.

**Fora da regra:**

- **Comissão de venda** não pede jantar, ainda que copie o horário do turno: ela não é período
  trabalhado (`total_hours` é zero), é o pagamento das vendas daquele mesmo turno. Sem essa
  exclusão, o turno do garçom pediria dois jantares.
- **Cancelado** e **aditivado** saem da lista da cozinha. No aditivado, quem responde pelo turno é o
  aditivo — e é ele que recebe a pergunta quando for assinado; a resposta antiga foi dada sobre um
  horário que não existe mais.

**Onde fica:** regra em `FreelancerService::isDinnerEligible()` / `dinnerDate()` /
`needsDinnerAnswer()` / `scopeDinnerConfirmedOn()`; gravação em
`App\Services\FreelancerService::recordDinnerAnswer()`; colunas `dinner_wanted`, `dinner_date`,
`dinner_answered_at` e `dinner_answered_by` em `freelancer_services`. O painel mostra a resposta na
tela do contrato, sem poder alterá-la.

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
a data em que foi firmado, diz que o período foi alterado e qual o local que passa a valer, declara
que o novo valor **substitui** o anterior (e não se soma a ele) e ratifica todas as demais
cláusulas — natureza autônoma, ausência de vínculo, descontos, refeição (cláusula 7), forma de
foro — e traz a própria cláusula da forma de pagamento (`4.1`). **O horário não entra no texto**
(ver *O que o contrato diz*):
quem conta a história é a cláusula do valor, que diz "R$ X em substituição a R$ Y" — ou, quando o
valor não muda, que ele **permanece**.
O texto vive em `partials/contract/vN/amendment-clauses.blade.php`, num lugar só — o tablet exibe o
mesmo documento que o painel imprime (ver *O documento é congelado na assinatura*).

### Comissão de venda (o segundo tipo de aditivo)
Há **dois tipos de aditivo**, distinguidos por `freelancer_services.amendment_type`, e eles fazem
coisas **opostas** com o dinheiro:

| Tipo | O que muda | Efeito no contrato base |
|---|---|---|
| `schedule` | horário e local | **substitui**: o base para de ir ao financeiro (`amended_at`) |
| `commission` | nada do turno — acrescenta a comissão sobre vendas | **acresce**: o base continua sendo pago normalmente |

Confundir os dois é pagar o turno duas vezes ou não pagá-lo. Por isso a comissão **nunca** marca
`amended_at`, e o texto do termo diz, na cláusula do valor, que a comissão *acresce* — o oposto
exato da cláusula equivalente do aditivo de horário.

**Quem recebe.** Só funções com `function_freelancers.allows_sales_commission` — hoje, o **Garçom**.
A permissão é uma **caixa na tela de Funções**, e não o nome "Garçom" no código: nomes mudam (os
cadastrados hoje incluem um com quebra de linha e dois "TI"), e estender para outra função não deve
exigir deploy.

**Os dois critérios** (`FreelancerService::COMMISSION_METHODS`), escolhidos no tablet a cada
comissão:

| Critério | Conta | R$ 999 | R$ 1.000 | R$ 1.900 | R$ 12.400 |
|---|---|---|---|---|---|
| `block` | R$ 50 a cada R$ 1.000 vendidos | R$ 0 | R$ 50 | R$ 50 | R$ 600 |
| `percent` | 5% do total vendido | R$ 49,95 | R$ 50 | R$ 95 | R$ 620 |

`block` conta **blocos fechados**, arredondando para baixo — mesma lógica dos blocos de 15 minutos.
É o que separa os dois critérios: fosse proporcional, R$ 50 por R$ 1.000 seriam os mesmos 5% e
escolher o método não mudaria nada.

**Quando pode** (`commissionBlockReason()`): a função recebe comissão, o **freelancer já assinou** o
contrato do turno (é o que prova que o turno aconteceu), o contrato não está cancelado nem foi
substituído por aditivo de horário — a comissão se faz sobre o documento **vigente** — e o turno
ainda não tem comissão.

> **O que NÃO bloqueia:** lote enviado, aprovação da gerência ou da diretoria, e até o pagamento do
> contrato do turno. É a diferença de natureza entre os dois aditivos — o de horário mexe no
> contrato que a gerência está analisando, enquanto a comissão nasce como documento novo e segue
> sozinha para o lote seguinte. Isso importa porque o valor de venda pode chegar depois.

**Uma comissão por turno**, e a pergunta é sobre o **turno**, não sobre a linha: com um aditivo de
horário no meio, a comissão pode estar pendurada no documento anterior. A checagem tem duas
velocidades — `hasCommissionChild()` (barata, filhos diretos, usa a relação carregada) nas telas, e
`shiftHasCommission()` (varre a cadeia inteira) na **gravação**, onde precisa ser exata. No caso raro
em que discordam, o botão aparece e o servidor recusa com o motivo.

**No tablet** (`POST /kiosk/service/{id}/commission`), em *Meus contratos*: botão **Comissão de
venda** → critério → valor vendido (teclado em centavos) → prévia com a conta demonstrada → gera e
abre o documento para assinar. Assinada ao **final do expediente**, quando se sabe quanto foi
vendido. O tablet só oferece a comissão de turnos dos últimos **7 dias**
(`KioskController::COMMISSION_WINDOW_DAYS`) — a regra não expira, mas sem essa janela todo contrato
de garçom ficaria para sempre na lista oferecendo comissão.

#### Apuração das vendas no MultiVendas
O valor vendido é apurado no **MultiVendas**, pela conexão **`mv_sqlsrv`** — mesma instância e
mesmas credenciais do `mc_sqlsrv` (reaproveita as variáveis `DB_MC_*`), trocando só o banco:
`DB_MV_DATABASE`, com padrão `MultiVendas`.

A consulta é o **cupom de fechamento por vendedor e período** que a operação escreveu e conferiu,
preservada em `App\Services\MultiVendasSalesReport` com os `DECLARE` alimentados por binding. As
únicas mudanças no SQL são `Secao` e `Ordem` no SELECT final: sem elas, achar "o total" no resultado
dependeria de comparar textos acentuados, e um acento a mais mudaria em silêncio a base da comissão.
A base é a linha **`Sales.Total`** (seção TOTAIS, `Ordem = 5`), em `COMMISSION_BASE` — trocar para o
líquido dos itens é mudar uma constante.

- **Login e período vêm pré-preenchidos** com o **CPF** do freelancer e o horário do próprio turno, e
  os dois são **editáveis**: `Users.UserName` no MultiVendas é de fato o CPF na maioria dos casos,
  mas há logins curtos, e o caixa pode ter fechado fora do horário do contrato.
- O operador toca em **Consultar vendas**, confere o resumo (quantidade, líquido, recebido,
  diferença de fechamento e a base) e segue. O total apurado **pré-preenche** o valor da comissão, que
  continua editável.
- **A apuração não é obrigatória.** Com o MultiVendas fora do ar, a tela avisa e o operador informa o
  valor manualmente — o documento diz que foi assim.

**O relatório é gravado junto com o documento** (`sales_report`, mais `sales_login` e o período),
e não apenas consultado: ele é anexo de um termo assinado, e o MultiVendas continua vivo — vendas
podem ser canceladas ou corrigidas depois. Quem grava é o **servidor**, refazendo a consulta com os
mesmos parâmetros na hora de criar a comissão; um relatório vindo do navegador seria um anexo escrito
pelo cliente.

`sales_source` não é escolhido por quem chama: é `system` quando há relatório **e** o valor
considerado é o que ele apurou, e `manual` quando o número foi digitado ou corrigido. Corrigido com
relatório anexo, o documento mostra as duas coisas — o Anexo I com o valor apurado e a cláusula 2
dizendo que o CONTRATANTE ajustou.

##### Alterar o valor apurado exige justificativa

O valor apurado **continua editável** — e tem de continuar: o caixa pode ter fechado fora do horário
do contrato, e uma venda pode ter sido cancelada depois. O que passou a existir é a contrapartida:
quem muda o número do relatório de origem **diz por quê** (`sales_adjustment_reason`).

**Não é log de auditoria, é cláusula.** O texto é impresso na **cláusula 2** do termo, logo depois da
diferença que ele explica ("ajustado em relação ao total constante do Anexo I (R$ X), pela seguinte
justificativa: …"), e ao lado do próprio Anexo I. O documento declara uma divergência em relação ao
seu próprio anexo; sem o motivo ali, o freelancer assina um número que ninguém explicou.

| Aspecto | Como é |
|---|---|
| Quando é exigida | há relatório **e** o valor considerado difere dele (`FreelancerService::salesAdjustmentIsRequired()`) — o mesmo critério que faz `sales_source` voltar a `manual` |
| Quando **não** é | sem relatório (MultiVendas fora do ar): não existe valor de origem a alterar, e o termo já diz que o número foi informado pelo CONTRATANTE |
| Tamanho mínimo | `SALES_ADJUSTMENT_REASON_MIN` = **10 caracteres** — "ajuste" e "ok" não explicam nada, e uma justificativa que não explica é pior que nenhuma, porque dá aparência de controle |
| Quem confere | o **servidor**, com o relatório que ele mesmo acabou de refazer na gravação; a tela pede o campo no momento da edição, mas quem sabe se o número realmente difere não pode ser o navegador |
| Onde a trava mora | `FreelancerService::createSalesCommission()` (invariante, `409`) **e** `KioskController::storeCommission()` (`422` com o campo em `errors`, porque ali é campo a preencher numa tela aberta) |

**No tablet** o campo aparece na **prévia da comissão**, junto do aviso "corrigido; apurado: R$ X" —
o momento em que o operador confirma o que vai gerar. Ele é limpo a cada nova comissão: o motivo de
um turno não explica o ajuste de outro.

Além do documento, a justificativa aparece na tela do contrato e no **cartão da análise da
gerência** — é o que permite julgar o ajuste sem abrir o PDF.

**O documento traz o relatório como `ANEXO I`**, com cabeçalho (vendedor, período, lojas), itens,
recebimentos por forma de pagamento, totais e cancelamentos — é o que permite ao freelancer conferir
de onde saiu o número que está assinando. Texto em `partials/sales-report-annex.blade.php` — fora da
pasta das redações de propósito: é anexo de dados apurados, não redação jurídica.

**O documento** é o *Termo Aditivo de Comissão sobre Vendas*: cita o contrato original, o dia da
prestação, as vendas apuradas (com o login e o Anexo I, mais a justificativa quando o valor foi
alterado), o critério, a conta demonstrada e o valor, declara que **acresce** ao contrato, que a
comissão não descaracteriza a prestação autônoma e ratifica a forma de pagamento por PIX. Texto em
`partials/contract/vN/commission-clauses.blade.php`, num lugar só.

**No financeiro** a comissão entra como linha própria, com seu valor e `total_hours = 0`: ela paga
vendas, não horas.

> **Aditivo não tem prazo de assinatura.** `isSignedAfterStart()` e `isUnsignedAfterStart()` ignoram
> aditivos: o de horário nasce durante o turno e a comissão é assinada ao final dele. Sem isso, todo
> aditivo apareceria como "assinatura em atraso" — e a comissão, 100% das vezes.

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
Limite de **2 serviços por freelancer por semana de calendário** (`FreelancerService::WEEKLY_LIMIT`),
contados por `start_date`. A semana é um bloco fixo de **segunda a domingo**: a segunda-feira zera a
contagem, mesmo que o freelancer tenha trabalhado sábado/domingo anteriores. O 3º (e cada um depois
dele) **não é bloqueado**, mas só é gravado com
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

**Semana fixa, segunda a domingo.** A janela **não é** "os 7 dias anteriores ao contrato": é o bloco
de calendário que vai da segunda-feira ao domingo daquela semana. Lançar um contrato numa data
anterior a outros já registrados na mesma semana aperta igual — a ordem de lançamento não importa
dentro da mesma semana —, mas um contrato de sábado/domingo **não** aperta a segunda-feira seguinte,
que já pertence à próxima semana. `countInWeeklyWindow()` devolve a contagem dentro desse bloco.

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

**O lote é a unidade de pagamento.** A diretoria aprova um bloco de contratos, e é esse bloco que o
financeiro quita — por isso a aba abre pela **lista de lotes**, não por uma lista solta de
contratos. São cinco telas:

| Tela | Rota | O que é |
|---|---|---|
| Lista de lotes | `/freelancer-services/financeiro` | lotes aprovados pela diretoria, separados em *Aguardando pagamento* e *Quitados* |
| Lote | `/financeiro/lote/{batch}` | os contratos pagáveis do lote; **é aqui que a baixa acontece** |
| Impressão | `/financeiro/lote/{batch}/impressao` | relação do lote para conferência em papel |
| Sem lote | `/financeiro/avulsos` | contratos pagáveis fora de qualquer lote aprovado |
| Todos | `/financeiro/todos` | a lista plana de sempre, para busca transversal |

- Só entram contratos **assinados pelas duas partes e aprovados pela gerência E pela diretoria**,
  não cancelados e não aditivados (`scopeAwaitingFinance`). Contratos parcialmente assinados não
  aparecem — é a assinatura do coordenador que confirma o serviço prestado.
- **O estado de pagamento do lote é derivado**, nunca gravado: *A pagar*, *Parcialmente pago* ou
  *Quitado* saem da contagem de `paid` dos contratos (`FreelancerServiceBatch::financeStatusLabel()`).
  Nada é denormalizado no lote de propósito — a baixa do Pix é escrita de forma assíncrona pelo job
  e pela reconciliação, e um campo espelho ali teria duas fontes de verdade e derivaria em silêncio.
- ⚠️ **O total do lote no financeiro pode ser menor que o que a diretoria aprovou.** O que a
  gerência recusou continua com o `batch_id` gravado, mas não é pagável e fica de fora da tela e da
  soma. Conferir o total contra o e-mail da diretoria sem saber disso assusta à toa.
- **Contratos sem lote** não deveriam existir — `availableForBatch` impede que um contrato já
  aprovado seja reloteado. A tela *Sem lote* existe como rede de segurança para dado antigo ou para
  o caso de um lote apagado (`batch_id` é `nullOnDelete`): dinheiro nunca some da vista por não
  estar agrupado. Um aviso na lista de lotes aponta para ela quando há algum.
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
- **Baixa em massa:** as caixas de seleção marcam vários contratos pendentes (com total selecionado
  em tempo real) e a barra no rodapé dá baixa em todos de uma vez. Contratos que deixaram de estar
  aptos enquanto a tela estava aberta são ignorados, e o aviso informa quantos ficaram de fora.
- **Pagar o lote inteiro:** dentro de um lote, o botão marca de uma vez todos os contratos
  pendentes e a barra do rodapé confirma o envio. Ele **seleciona**, não dispara: a confirmação com
  o valor total continua sendo o último passo, e com o Pix ligado sai **um Pix por contrato** — a
  transferência é por chave do freelancer, nunca um pagamento único do lote.
- **Imprimir relação:** abre em aba nova a relação do lote em paisagem, com uma linha por contrato
  — nome, função, evento/local, CPF, RG, estado civil, início e fim, valor, chave PIX e as três
  aprovações com data/hora (coordenação, gerência e diretoria) — mais o resumo geral do lote. Cada
  linha é autossuficiente, inclusive quanto à diretoria, para que uma folha solta continue provando
  o que aprovou aquele pagamento. A impressão dispara sozinha ao abrir.
- **Tabela reduzida:** o interruptor no topo enxuga a lista para nome, período, chave PIX, valor e o
  botão de baixa, mostrando só os pendentes — o formato de folha de pagamento. A preferência fica
  guardada no navegador.
- Depois da baixa, a tela volta para **o lote de onde ela partiu** (o formulário manda o id do lote,
  nunca uma URL — o destino é resolvido no servidor).
- A baixa também aparece na tela do contrato, junto às assinaturas.

### Acompanhamento (a tela do Comercial)
`/freelancer-services/acompanhamento` — **só web e só leitura**. Quem registra o contrato é quem o
freelancer procura para saber "e o meu pagamento?", e responder isso exigia abrir a aba de Lotes (só
o coordenador vê), a de Aprovação (só a Gerência) e a de Financeiro (só a Contabilidade). Esta tela
reúne as quatro etapas num eixo só.

**Quem acessa:** vínculo com o setor **`Comercial`** em qualquer papel — colaborador ou coordenador
— **ou** quem responde pelo financeiro dos freelancers (`Contabilidade` ou `Gerência`), pelo Gate
`track-freelancer-batches` (`User::canTrackFreelancerBatches()`). Como o Financeiro, é atribuição de
setor e não permissão: a role `admin` não dá acesso, e **não é preciso ter `manage freelancers`** —
por isso a rota fica fora daquele grupo de middleware.

> **Por que o Financeiro também acompanha.** Quem paga é perguntado pelo mesmo motivo que o
> Comercial: "e o contrato #N?". A regra reaproveita `canManageFreelancerPayments()` em vez de
> repetir os nomes dos setores, para que "quem é o Financeiro" continue definido num lugar só — se
> um terceiro setor entrar no financeiro, o acompanhamento acompanha. A tela continua **só leitura**,
> então isso não dá a ninguém o poder de aprovar nem de pagar.

**Nada de ação.** Não há botão que mude estado: aprovar continua sendo da Gerência e pagar, do
Financeiro. Os links para a tela do contrato e para a do lote só são desenhados para quem passaria
na autorização de lá — um link que dá 403 é pior que link nenhum.

A tela tem três partes:

1. **Resumo por etapa** — um cartão por fila (aguardando assinaturas, aguardando lote, gerência,
   diretoria, pagamento, pago), com a contagem e o valor parado nela. Só o cartão de **pagos**
   respeita o filtro de período: os demais são filas abertas, e uma fila não fica menos aberta por
   ser antiga.
2. **Lotes**, cada um com a **linha do tempo das quatro etapas** (assinaturas → gerência →
   diretoria → pagamento), marcando o cumprido, o atual e o recusado. Abrindo o lote, a lista dos
   contratos dele com a etapa de cada um. Lote em trâmite aparece sempre, mesmo fora do período —
   é ele que trava a fila.
3. **Ainda fora de lote** — o que espera assinatura e o que já está assinado esperando o
   coordenador montar o lote. É o começo da fila, e onde um contrato costuma ficar esquecido.

**Onde a etapa é decidida:** `FreelancerService::trackingStage()` e
`FreelancerServiceBatch::trackingStage()`, sobre o vocabulário de
`FreelancerService::TRACKING_STAGES`. É a mesma leitura de `signatureLabel()` e `approvalLabel()`,
mas inteira e num eixo só — e é a única que distingue *aprovado, esperando o dinheiro* de *pago*.
Os contadores usam escopos SQL espelhados (`awaitingSignature`, `awaitingManagerReview`,
`awaitingDirectorReview`, `awaitingPayment`, `paidServices`); um teste soma as filas e cobra que
cada contrato apareça em **uma e só uma**, porque contador e rótulo discordando é o jeito de a tela
mentir sem ninguém perceber.

### Barra de abas (Contratos · Lotes · Aprovação · Acompanhamento · Financeiro)
As cinco frentes do fluxo dividem a mesma barra de abas
(`resources/views/freelancer/services/partials/tabs.blade.php`), presente em todas elas e também na
tela de um lote — de qualquer uma se chega a qualquer outra, sem voltar ao menu.

**A aba só aparece para quem a rota deixa entrar** — a condição do Blade é a mesma do middleware,
para que nenhuma aba leve a um 403:

| Aba | Rota | Quem vê |
|---|---|---|
| Contratos | `freelancer-services.index` | `manage freelancers` |
| Validação | `freelancer-validation.index` | `manage freelancers` **e** coordenador do setor `Comercial` (Gate `validate-freelancer-contracts`) |
| Lotes | `freelancer-batches.index` | `manage freelancers` **e** coordenador de algum setor |
| Aprovação | `freelancer-batches.queue` | `manage freelancers` **e** coordenador do setor `Gerência` |
| Diretoria | `freelancer-director.edit` | `manage freelancers` **e** coordenador do setor `Gerência` (Gate `manage-freelancer-director`) |
| Acompanhamento | `freelancer-services.tracking` | membro do setor `Comercial`, `Contabilidade` ou `Gerência` (qualquer papel) |
| Financeiro | `freelancer-services.finance` (e `finance.*`) | membro do setor `Contabilidade` **ou** `Gerência` |

A aba Financeiro cobre também as telas de lote, avulsos e lista plana (`freelancer-services.finance.*`),
para continuar acesa ao navegar dentro do financeiro. A tela de um lote **no financeiro** é própria
(`/financeiro/lote/{batch}`) e não a de `freelancer-batches.show`: quem está só na Contabilidade não
é o gerente nem o criador do lote e levaria 403 lá.

Consequências práticas: quem só está na Contabilidade navega no Financeiro sem ver uma aba
Contratos quebrada; quem tem **só** `manage freelancers` e não coordena nada fica com uma aba única
e a barra some. A tela de um lote destaca *Aprovação* para o gerente e *Lotes* para o coordenador
que o montou (`$activeTab`).

### Lote de aprovação (gerência e diretoria)
Depois das duas assinaturas, o contrato **não vai direto para o financeiro**: ele precisa passar
por **dois níveis de aprovação**, e isso acontece em **lote**.

```
freelancer assina (início do serviço) → [o dia corre: cabe aditivo]
    → 08h do dia seguinte: o contrato é liberado
        → coordenador assina no tablet (redação 1) ou valida pela web (redação 2)
            → coordenador monta o lote → envia
                → coordenador da Gerência aprova (ou recusa) contrato a contrato
                    → e-mail automático à diretoria, com dois PINs
                        → diretor dita o PIN, gerência digita
                            (redação 2: a assinatura do diretor entra no documento)
                                → financeiro paga
```

**Montagem (coordenador, web ou tablet).** Cada coordenador mantém **um rascunho por vez**
(`freelancer_service_batches.status = 'draft'`). Ele inclui e retira contratos à vontade e, quando
fecha, envia. Entram no rascunho os contratos assinados pelas duas partes, não cancelados, ainda
não aprovados, **já liberados** (08h do dia seguinte ao turno — ver *Liberação para a coordenação*)
e fora de qualquer lote em aberto (`FreelancerService::scopeAvailableForBatch`).
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

**Comissão de venda no lote.** Um termo de comissão entra no lote como qualquer outro documento,
mas ele repete o **nome, a data e o período** do contrato do turno, com **outro valor** — parece
lançamento duplicado, e não é. Por isso todas as telas por onde ele passa dizem o que ele é:

| Onde | O que aparece |
|---|---|
| Montagem do lote (`batches/index`) | selo **Comissão de venda** ao lado do nome, com a frase "acresce ao contrato #N do mesmo turno"; o resumo do rascunho conta os termos de comissão e soma-os à parte |
| Fila da gerência (`batches/queue`) | "Inclui N termo(s) de comissão de venda" no cartão do lote |
| Análise (`batches/show`) | selo no cabeçalho do lote com a contagem e o total, e uma tarja verde por contrato com o critério, o login apurado no MultiVendas, o período e o aviso quando o valor de vendas foi ajustado à mão |
| E-mail da diretoria | destaque verde antes da relação ("não são lançamentos repetidos") + selo e explicação em cada linha da tabela |
| PDF anexo ao e-mail | mesmo destaque no topo e rótulo na coluna do freelancer |
| Financeiro (`finance-table`, `finance-print`) | selo e frase na linha, onde a baixa de pagamento acontece |

O texto dos selos vem de `FreelancerService::kindLabel()` e `kindNote()` — um único lugar, para
que lote, aprovação, e-mail e financeiro nunca digam a mesma coisa com palavras diferentes. Na
comissão a **duração some** das relações de pagamento: o valor não é calculado por hora, e mostrar
"8h" ao lado dele convida a uma conta que não existe.

**O número do documento (`#ID`) é visível em todo o sistema** — listagem de contratos, cadastro do
freelancer, montagem do lote, análise, e-mail e PDF da diretoria, relação do financeiro e cartões do
tablet. É por ele que coordenação, gerência e financeiro se referem a um contrato quando falam por
telefone ou WhatsApp. A única exceção é o **corpo do contrato**: ali o documento é o instrumento
firmado entre as partes, e um número interno de banco não tem o que fazer no texto.

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

**Cadastro da diretoria (aba Diretoria).** O destinatário não mora mais no `.env`: o **coordenador da
Gerência** (Gate `manage-freelancer-director`) cadastra em `/freelancer-services/diretoria` o **nome** do
diretor, o **e-mail** que recebe os códigos e a **imagem da assinatura** (PNG, disco privado). Um
diretor só, sem cópia.

- **Os registros não são editados** (`freelancer_directors`): cada gravação cria uma linha nova, e vale
  a mais recente (`FreelancerDirector::current()`). Trocar só o e-mail repete a imagem anterior. É a
  mesma razão do versionamento da redação — contratos aprovados apontam para a linha que os assinou, e
  editar no lugar reescreveria a assinatura de todo documento já aprovado. A tela mostra o histórico.
- A migration `create_freelancer_directors_table` importou o `FREELANCER_DIRECTOR_NAME` /
  `FREELANCER_DIRECTOR_EMAIL` do `.env` para o primeiro registro, **sem imagem**, para o destinatário de
  hoje continuar recebendo no dia do deploy. Depois disso as variáveis não são mais lidas.
- Sem diretor cadastrado o envio é recusado; sem a imagem, é recusado para lote com contrato da
  redação 2 — a tela do lote avisa e aponta para a aba Diretoria.

### Kiosk (tablet)
`/kiosk` é uma tela de toque **fora da sessão web**: entra-se com **matrícula + PIN de 6 dígitos**
(`users.pin`, definido na tela de Usuários) e a sessão fica guardada do lado do servidor. O PIN é
**reconfirmado a cada assinatura**.

Dois modos, decididos pelo que o usuário é — quem acumula os dois papéis escolhe ao entrar:

| Modo | Quem entra | O que faz | Limite da sessão |
|---|---|---|---|
| `operator` | permissão `manage freelancers` | localiza/cadastra freelancer, registra contrato, faz o **aditivo** quando o turno muda e colhe a assinatura do freelancer | 30 min **ou** 5 contratos |
| `coordinator` | **coordenador do setor `Comercial`** (`user_sector.role = 'coordinator'`) | assina os contratos que aguardam a contraparte e monta/envia o lote para a gerência | 30 min (sem teto de contratos) |

- Antes de **toda** assinatura do freelancer entra a tela de **conferência da chave PIX**, com a
  opção de corrigi-la ali mesmo — ver *Conferência da chave PIX*.
- **Depois** da assinatura, quando o turno dá direito à refeição, entra a pergunta **"Vai jantar?"**
  (Sim / Não) — ver *Jantar do turno noturno*. Sem direito, a tela nem aparece.
- A fila do coordenador traz os **50 mais antigos** primeiro — são os que travam o financeiro — e
  recarrega a cada assinatura.
- O coordenador assina o **mesmo documento** que o freelancer assinou, já com o traço da outra
  parte à vista, no campo do CONTRATANTE. A assinatura é definitiva e libera o contrato para a
  aba Financeiro.
- **Só a redação 1 é assinada no tablet.** A fila do coordenador não mostra a redação 2, e a
  assinatura (e o documento aberto com `role=coordinator`) de um contrato da redação 2 responde `409`:
  ele é validado pela web e assinado pela diretoria — ver *Redação 2*. Montar e enviar lote continua
  no tablet como antes.
- O papel é **reconferido a cada requisição**: retirar a permissão ou o vínculo de coordenação no
  painel derruba na hora a sessão aberta no tablet.

#### Buscar freelancer por função (modo atendimento)

Existe para o fim de semana, quando falta gente e quem está na operação não sabe quem chamar. Na tela
de localizar freelancer, o botão **Buscar por função** leva a duas perguntas — **para quando** (hoje ou
amanhã) e **qual função** — e a uma lista de quem já atuou nela
(`GET /kiosk/functions/{função}/freelancers?date=`).

| Aspecto | Como é |
|---|---|
| Quem aparece | freelancers com ao menos um turno **já trabalhado** na função: não cancelado, não aditivo, com `start_date` até hoje. Turno marcado para depois ainda não é experiência de ninguém |
| Ordem | mais atuações na função primeiro; no empate, quem trabalhou por último |
| O que mostra | nome, telefone (toque para discar), quantas vezes atuou na função e a data do último serviço nela |
| Bloqueio | quem já tem `WEEKLY_LIMIT` (2) serviços na semana do dia escolhido vai para a seção **Bloqueados · limite semanal**, **sem o telefone** |

- **O bloqueio é do servidor.** O telefone do bloqueado não sai no payload — esconder só na tela
  deixaria o número a um "inspecionar" de distância. Ele continua na lista para ninguém achar que o
  cadastro sumiu.
- **A semana é a do dia para o qual se está chamando**, e é a semana fixa de segunda a domingo do resto
  do módulo, com a mesma conta de `countInWeeklyWindow()` (cancelado e aditivo não contam, qualquer
  função conta). Por isso a pergunta "para quando": no domingo, chamar para a segunda é chamar para a
  semana seguinte. O histórico não muda com o dia — conta sempre até hoje.
- **Bloquear na busca não proíbe o contrato.** Um terceiro serviço na semana continua possível pelo
  caminho de sempre, com a liberação do coordenador do Comercial no registro; a busca é que não sugere
  esse freelancer.
- O telefone é texto livre no cadastro: `Freelancer::phoneFormatted()` formata o que tem DDD e número
  (tirando o 55 quando veio junto) e mostra o resto como foi gravado; `phoneDigits()` dá o número do
  `tel:`.
- Regra em `App\Services\FreelancerService::searchByFunction()`, sobre
  `FreelancerService::functionHistory()` e `weeklyCountsFor()` (uma consulta agregada cada, e não uma
  por freelancer). Testes em `tests/Feature/FreelancerFunctionSearchTest.php`.

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
- **Acompanhar o trâmite** (aba Acompanhamento, só leitura) é vínculo com o setor **`Comercial`**
  em **qualquer papel**, **ou** o mesmo vínculo que dá o Financeiro (`Contabilidade` / `Gerência`) —
  Gate `track-freelancer-batches`, que delega a segunda metade para
  `canManageFreelancerPayments()`. Como o Financeiro, não é permissão e a role `admin` não vale; e,
  ao contrário das três primeiras abas, **não exige** `manage freelancers`: quem só acompanha
  enxerga no menu apenas essa entrada.
- Cancelar **pelo painel** exige, além disso, ser **coordenador de algum setor**
  (`user_sector.role = 'coordinator'`) — verificado por `User::isCoordinator()`.
- Assinar como coordenador existe **só no kiosk** e é mais restrito: só o coordenador do setor
  **Comercial** (`User::isCoordinatorOfSectorNamed('Comercial')`). Não há rota web equivalente. Vale
  para a redação 1; na redação 2 o mesmo coordenador **valida pela web** (Gate
  `validate-freelancer-contracts`, aba Validação).
- **Cadastrar a diretoria** (nome, e-mail dos códigos e imagem da assinatura) é do **coordenador da
  Gerência** (Gate `manage-freelancer-director`, aba Diretoria) — o mesmo que envia o lote ao diretor.
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

As rotas exigem o header `Authorization: Bearer <API_TOKEN>` (valor de `API_TOKEN` no `.env`). Sem
ele: `401`. **A exceção é `GET /api/freelancer/dinners`**, que é aberta — ver a seção da rota.

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
| `GET` | `/api/freelancer/dinners` | Confirmações de jantar de um dia (cozinha) — **sem token** |

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

### `GET /api/freelancer/dinners`

**Para que serve.** Responder à cozinha uma pergunta só: **quem confirmou o jantar de um
determinado dia**. É a lista que dimensiona quantos pratos preparar. Ver a regra em
[Jantar do turno noturno](#jantar-do-turno-noturno).

**Autenticação: nenhuma.** É a exceção do módulo — a rota fica **fora** do grupo `api_token`, por
decisão de quem opera: o painel da cozinha consulta sem carregar o token. Por isso o **CPF não vai
no payload**: para servir o prato basta o nome, e uma lista aberta não é lugar de documento de
ninguém. Quem precisar cruzar com o cadastro usa o `freelancer_id`, que só serve dentro do sistema.

**Parâmetros (query string)**

| Campo | Regras |
|---|---|
| `date` | opcional, data (`YYYY-MM-DD`). Omitida, assume **hoje** |

A data consultada é a do **jantar** (`dinner_date`), **não** a de início do contrato: um turno que
entra 22:00 e sai 20:00 do dia seguinte janta no dia seguinte, e é nesse dia que ele aparece.

Datas anteriores a **31/08/2026** — a estreia do jantar — voltam sempre `total: 0`: não havia
refeição servida, e portanto ninguém a confirmar.

**O que entra na lista.** Só os **"sim"**. Quem respondeu "não" e quem ainda não foi perguntado não
viram prato — uma lista que misturasse os três obrigaria a cozinha a filtrar o que já é resposta.
Ficam de fora, também, os contratos **cancelados** e os **aditivados** (o turno mudou de horário
depois da assinatura; quem responde por ele é o aditivo). A ordenação é por **nome**, porque a
lista é lida em voz alta na cozinha, conferindo quem chega.

**Exemplo**

```
GET /api/freelancer/dinners?date=2026-09-03
```

```json
{
  "ok": true,
  "date": "2026-09-03",
  "window": "17:30 às 18:30",
  "total": 2,
  "dinners": [
    {
      "service_id": 412,
      "freelancer_id": 87,
      "name": "Ana Souza",
      "function": "Garçom",
      "location": "Salão de festas",
      "shift_date": "2026-09-03",
      "start_time": "14:00",
      "end_time": "20:00",
      "crosses_midnight": false,
      "duration": "6h",
      "duration_minutes": 360,
      "answered_at": "2026-09-03T14:07:22-03:00"
    },
    {
      "service_id": 415,
      "freelancer_id": 91,
      "name": "Bruno Lima",
      "function": "Garçom",
      "location": "Churrasqueira",
      "shift_date": "2026-09-03",
      "start_time": "16:00",
      "end_time": "22:00",
      "crosses_midnight": false,
      "duration": "6h",
      "duration_minutes": 360,
      "answered_at": "2026-09-03T16:04:10-03:00"
    }
  ]
}
```

**Campos da resposta**

| Campo | O que é |
|---|---|
| `date` | o dia consultado, normalizado (`YYYY-MM-DD`) |
| `window` | a janela do jantar vigente — hoje, `17:30 às 18:30` |
| `total` | quantos confirmaram; é o número de pratos |
| `dinners[].service_id` | id do contrato, para conferência no painel |
| `dinners[].name` | quem vai jantar |
| `dinners[].freelancer_id` | id do cadastro, para cruzar com o painel |
| `dinners[].function` / `location` | função e local do turno |
| `dinners[].shift_date` | dia de **início do turno** — difere de `date` quando o turno vira a meia-noite |
| `dinners[].start_time` / `end_time` | horário do turno (`HH:MM`) |
| `dinners[].crosses_midnight` | `true` quando o término é no dia seguinte |
| `dinners[].duration` / `duration_minutes` | duração do turno, formatada e em minutos |
| `dinners[].answered_at` | quando a resposta foi registrada no tablet (ISO-8601) |

**Respostas:** `200` sempre que a consulta é válida — dia sem ninguém volta `total: 0` e
`dinners: []`, e não `404` · `422` quando `date` não é uma data. Não há `401`: a rota é aberta.

**Controller:** `app/Http/Controllers/Freelancer/DinnerApiController.php`.

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
- **Controllers da API:** `app/Http/Controllers/{FreelancerController,FreelancerServiceController,FunctionFreelancerController}`,
  `Freelancer/DinnerApiController` (jantar, consumida pela cozinha) e `Auth/UserAuthController`.
- **Service:** `app/Services/FreelancerService.php` — concentra cálculo de preço, assinaturas,
  cancelamento e auditoria; é o mesmo objeto usado pelo painel e pela API, então as regras não
  divergem entre as duas frentes.
- **Models:** `Freelancer` (default e tipo do PIX), `FunctionFreelancer`, `FreelancerService`
  (`isSigned()`, `canBeUpdated()`, `canBeCancelled()`, `canBeDeleted()`, `signatureLabel()`,
  `exceedsWeeklyLimit()`, `flagExcessWithinCollection()`), `User::isCoordinator()`.
- **Chave PIX:** `Freelancer::PIX_KEY_TYPE_LABELS` / `PIX_KEY_INPUT_TYPES` / `pixKey()` /
  `pixKeyTypeFor()` / `formatPixKey()` / `normalizePixKey()` / `pixKeyError()` (regra, formatação e
  validação, num lugar só), `FreelancerService::pixKey()` / `pixKeyWasConfirmed()` /
  `pixKeyDivergesFromFreelancer()` (a cópia congelada no contrato),
  `App\Http\Requests\UpdateFreelancerPixKeyRequest` + `KioskController::updatePixKey()` +
  `App\Services\FreelancerService::updatePixKey()` (a correção no tablet, com log mascarado), e a
  cláusula em `services/partials/contract/vN/pix-clause.blade.php`. Colunas em
  `2026_08_05_120000_add_pix_key_to_freelancer_services_table`. Testes em
  `tests/Unit/FreelancerPixKeyTest.php`.
- **Congelamento do documento:** `FreelancerService::CONTRACT_VERSION_CURRENT` / `CONTRACT_VERSIONS`
  (o histórico das redações), `contractVersion()` / `contractIsFrozen()` / `contractViewNamespace()`
  (qual texto imprimir), `contractParty()` / `contractFunctionName()` / `buildContractSnapshot()` /
  `contractPartyDivergesFromFreelancer()` (a qualificação congelada), `contractFreezeAttributes()`
  (o que gravar na assinatura, consumido por `signAsFreelancer()` e `signAsCoordinator()`) e
  `contractVersionFilters()` / `scopeContractVersionFilter()` (a varredura). O documento, num lugar
  só, em `services/partials/contract-document.blade.php` + a pasta da redação
  `services/partials/contract/vN/`; servido ao tablet por `KioskController::document()`
  (`GET /kiosk/service/{id}/document`), com a trava de redação em `signService()`. Colunas em
  `2026_08_12_140000_add_contract_freeze_to_freelancer_services_table`. Testes — inclusive o **lacre**
  dos arquivos de cada redação — em `tests/Unit/FreelancerContractVersionTest.php`.
- **Redação 2 (validação web + assinatura da diretoria):** `FreelancerService::CONTRACTOR_SIGNS_*` /
  `contractorSignature()` / `usesDirectorSignature()` / `versionsSignedBy()` / `scopeContractorSignedBy()`
  (quem assina pelo CONTRATANTE, por redação), `scopeAwaitingCoordinatorValidation()` e
  `coordinatorValidationBlockReason()` (a validação), `hasDirectorSignature()` /
  `awaitsDirectorSignature()` / `documentsReplacedByThis()` (a assinatura do diretor);
  `App\Services\FreelancerService::validateAsCoordinator()`; `FreelancerBatchService::notifyDirector()` /
  `applyDirectorPin()` / `signedByDirector()`; `Freelancer\ValidationController` (fila e tela da
  validação, com a marca de abertura na sessão) e `Freelancer\DirectorController` +
  `App\Services\FreelancerDirectorService` + `App\Models\FreelancerDirector` (cadastro da diretoria).
  Views em `freelancer/validation/` e `freelancer/director/`. Colunas em
  `2026_09_11_100000_create_freelancer_directors_table` e
  `2026_09_11_100100_add_director_signature_to_freelancer_services_table`. Testes em
  `tests/Feature/FreelancerDirectorSignatureFlowTest.php` e `tests/Unit/FreelancerContractVersionTest.php`.
- **Justificativa da alteração do valor apurado:** `FreelancerService::salesAdjustmentIsRequired()` /
  `SALES_ADJUSTMENT_REASON_MIN` (regra), `App\Services\FreelancerService::createSalesCommission()`
  (invariante na gravação), `KioskController::storeCommission()` (o `422` com o campo) e
  `StoreSalesCommissionRequest` (formato). Coluna em
  `2026_08_12_093000_add_sales_adjustment_reason_to_freelancer_services_table`. Testes em
  `tests/Unit/FreelancerSalesCommissionTest.php`.
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
