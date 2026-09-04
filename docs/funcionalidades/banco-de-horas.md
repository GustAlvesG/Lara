# Banco de Horas (CompTime)

## O que é

Importa, processa e gerencia o **saldo de horas** dos funcionários a partir de relatórios de
ponto (espelho de ponto em HTML). Calcula créditos e débitos, compensa horas e mostra o saldo
por funcionário e por dia. Guarda também o cadastro que o espelho de ponto não traz: setor,
férias, afastamento e rescisão.

## Quem acessa o quê

O módulo tem três públicos, e o recorte é decidido em `CompTimeService::accessFor()`:

| Quem | Enxerga | Importa e administra |
|------|---------|----------------------|
| Setor **RH** (qualquer papel) ou permissão `import comp time` | Todos os funcionários | Sim |
| **Coordenador** de um setor | Os funcionários daquele setor | Não |
| Qualquer usuário com **matrícula** | A própria ficha | Não |
| Sem matrícula e sem setor | Nada | Não |

O vínculo entre usuário e funcionário é a matrícula: `users.matricula` = `employees.employee_code`.
Não há chave estrangeira — o funcionário nasce da importação e o usuário nasce do cadastro do
painel, e um existe sem o outro. **Atenção:** `users.matricula` é `varchar(5)`; matrícula mais
longa do que isso nunca casa.

Os dois Gates estão em `AppServiceProvider`:

- `manage-comp-time` — importar, recalcular e mexer no cadastro (`User::canManageCompTime()`)
- `view-comp-time` — só decide se o menu aparece (`User::canViewCompTime()`)

## Setor × departamento

Cada departamento do espelho de ponto tem um **setor** correspondente, e o funcionário aponta
para ele por `employees.sector_id`. O texto original da "Estrutura" continua em
`employees.department`, para auditoria.

A importação cria o setor que ainda não existir e reaproveita o existente sem diferenciar
maiúsculas nem espaços nas bordas. Os departamentos já importados foram convertidos pela
migration `backfill_sectors_from_employee_departments`.

Antes disso o acesso do coordenador comparava `employees.department` com `sectors.name` como
texto: um acento a mais no sistema de ponto e ele deixava de enxergar a própria equipe, sem
erro nenhum na tela.

## Fluxo de importação

A importação roda em **dois jobs na fila** (`database` — precisa de um `queue:work` vivo, senão
a tela avisa que travou) com uma parada no meio para decisão humana:

```
pending → processing → awaiting_review → (usuário decide) → processing → completed
                    ↘ completed (quando não há duplicata para revisar)
                    ↘ failed
```

### 1. Upload — `POST /comp-time/upload`
O arquivo vai para `storage/app/temp/{uuid}.html`, nasce um `CompTimeImport` com o dono
(`user_id`) e o `DetectCompTimeDuplicatesJob` é despachado. Arquivos e registros com mais de
24h são varridos aqui.

### 2. Detecção — `CompTimeService::detectDuplicates()`
Duas consultas no total: todos os funcionários do arquivo e todos os lançamentos já existentes
no intervalo de datas dele. O confronto é em memória, por `employee_id|data|tipo`.

- **Sem duplicata:** grava na hora e encerra (`completed`).
- **Com duplicata:** para em `awaiting_review` e guarda a lista em `result_data`.

### 3. Revisão — `GET /comp-time/import-preview/{uuid}`
Tabela de valor atual × novo valor, com checkbox por linha. Só o dono da importação abre.

### 4. Confirmação — `POST /comp-time/confirm-import/{uuid}`
Os ids marcados são cruzados com os que a importação de fato ofereceu (o formulário vem do
navegador) e o `ConfirmCompTimeImportJob` grava: lançamento novo entra sempre, duplicata
desmarcada é ignorada.

## Como o espelho de ponto é lido

O relatório repete um bloco de **4 tabelas por funcionário** — a 1ª com os dados dele, a 2ª com
o espelho, as outras duas rodapé. Dentro da linha do dia a leitura é por **posição fixa de
`<td>`** (o relatório não tem cabeçalho legível por máquina); os índices estão nas constantes
`COL_*` de `CompTimeService`.

Regras de linha:

- `Horario` contendo "Feriado" → a linha inteira é ignorada.
- Observação contendo "DSR" → o débito não entra (descanso remunerado não é hora devida).
- Crédito acima de **120 minutos** só entra se a coluna de aval disser "Creditar".
- Dia sem crédito nem débito **não gera lançamento**. (Antes gerava um registro de tipo
  `Padrão` com zero minuto, um por dia trabalhado por funcionário — registros antigos ainda
  existem no banco e as telas continuam sabendo exibi-los.)
- Linhas repetidas do mesmo funcionário/dia/tipo **dentro do mesmo arquivo** colapsam na
  última, antes de qualquer confronto com o banco.

## Como o saldo é calculado

- Cada lançamento tem **minutos brutos** (`amount_minutes`) e **minutos líquidos**
  (`balance_minutes`).
- A compensação confronta crédito × débito do mais antigo para o mais novo (FIFO), dentro da
  janela de validade.
- Cada compensação gera **dois ajustes** (`TimeAdjustment`), um de cada lado, para que as duas
  fichas contem a mesma história.
- **Validade:** 180 dias (`due_date = entry_date + 180`).

A gravação **não** calcula saldo linha a linha: grava tudo e recalcula cada funcionário afetado
uma vez, no fim. Compensação incremental depende da ordem em que o arquivo chega para dar o
resultado certo; a passada única, não.

`recalculateAllBalances()` aceita um id, vários ou nenhum (todos). É **destrutivo por
natureza**: apaga os ajustes do funcionário, devolve cada lançamento ao valor bruto e
recompensa tudo. É o que dá um resultado independente da ordem das importações.

## Rodando o worker da fila

**A importação não anda sem um `queue:work` no ar.** O `artisan serve` não processa fila —
são dois processos separados. Sem worker, o job fica na tabela `jobs` com `attempts = 0`, o
registro não sai de `pending` e a tela mostra o aviso de fila parada depois de 90 segundos
(`CompTimeImport::STALLED_AFTER_SECONDS`).

Num terminal à parte, na raiz do projeto:

```
php -d memory_limit=1G artisan queue:work --tries=1 --sleep=2
```

- `-d memory_limit=1G` — o espelho de ponto é um HTML grande (o de agosto/2026 tinha 2,4 MB) e
  o DomCrawler carrega a árvore inteira. Com os 128 MB padrão do PHP CLI o job morre no meio.
- `--tries=1` — importação não deve ser repetida sozinha. Falhou, vai para `failed_jobs` e o
  usuário reenvia o arquivo depois de entender o motivo.
- O timeout de 600s vem do próprio job (`$timeout`), não precisa de flag.

Para conferir se está mesmo parado:

```
php artisan tinker --execute="echo DB::table('jobs')->count();"
```

Job com `attempts = 0` e `created_at` antigo é worker ausente, não job travado.

## Cadastro de funcionários (aba do RH)

`GET /comp-time/employees` — lista com filtro por setor, busca (nome, matrícula, CPF) e
situação (ativos / desligados / todos). `GET /comp-time/employees/{employee}` abre a ficha.

O que se edita aqui:

- **Setor** — é por ele que o coordenador enxerga a equipe.
- **Rescisão** — data e motivo. Limpar a data limpa o motivo.
- **Férias e afastamento** (`employee_absences`) — data de início obrigatória, data de fim
  **opcional**. Sem fim, o período é lido como **em aberto**: vale dali em diante até alguém
  fechar. É o estado normal de um afastamento recém-registrado.

Os três são **informativos**: não interferem na importação nem no cálculo de saldo, e a ficha
de quem saiu continua inteira. Existem para o cadastro e para o módulo de consulta de
funcionários ativos que vem depois — que deve usar `Employee::scopeActive()` e
`EmployeeAbsence::scopeCurrent()`.

## Campos principais

| Tabela | Campos |
|--------|--------|
| `employees` | `employee_code` (único), `name`, `cpf`, `admission_date`, `position`, `department`, `sector_id`, `termination_date`, `termination_reason` |
| `employee_absences` | `employee_id`, `type` (`vacation`/`leave`), `start_date`, `end_date` (nullable), `notes`, `created_by` |
| `time_entries` | `employee_id`, `entry_date`, `reference_time`, `entry_times`, `type`, `amount_minutes`, `balance_minutes`, `due_date`, `status_id`, `written_off` |
| `time_adjustments` | `entry_time_to_adjust_id`, `entry_time_adjusted_id`, `amount_minutes`, `before_adjustment_minutes`, `after_adjustment_minutes`, `reason` |
| `comp_time_imports` | `uuid`, `user_id`, `status`, `phase`, `temp_file_path`, `result_data`, `error_message`, `dispatched_at` |

## Rotas

| Método | Rota | Acesso |
|--------|------|--------|
| GET | `/comp-time/upload` | autenticado (recorte interno) |
| POST | `/comp-time/filter` | autenticado (recorte interno) |
| POST | `/comp-time/details` | quem enxerga aquele funcionário |
| POST | `/comp-time/details/day` | quem enxerga aquele funcionário |
| POST | `/comp-time/write-off`, `/comp-time/undo-write-off` | RH ou coordenador do setor |
| POST | `/comp-time/upload`, `/comp-time/recalculate` | `can:manage-comp-time` |
| GET | `/comp-time/import-status/{uuid}` (+ `/api`, `/complete`) | dono da importação |
| GET | `/comp-time/import-preview/{uuid}` | dono da importação |
| POST | `/comp-time/confirm-import/{uuid}` | dono da importação |
| GET/PUT | `/comp-time/employees[/{employee}]` | `can:manage-comp-time` |
| POST/DELETE | `/comp-time/employees/{employee}/absences[/{absence}]` | `can:manage-comp-time` |

Dar e desfazer baixa recalcula o saldo do funcionário: o lançamento sai (ou volta) da
compensação, e sem o recálculo a ficha mostraria um total que não corresponde mais aos
lançamentos ativos.

## Testes

- `tests/Feature/CompTimeImportTest.php` — parsing, duplicatas, setor e compensação.
- `tests/Feature/CompTimeAccessScopeTest.php` — o recorte de visibilidade depois de decidido.
- `tests/Unit/EmployeeAbsenceTest.php` — períodos de férias/afastamento e rescisão, sem banco.

Quem **decide** a restrição (`accessFor`) depende do model `User`, preso à conexão `mysql`, e
por isso fica fora da suíte em SQLite — mesma limitação registrada nos outros módulos.

## Referência técnica

- Controllers: [`CompTimeController`](../controllers.md#comptimecontroller), `CompTimeEmployeeController`
- Service: [`CompTimeService`](../services.md#comptimeservice)
- Models: [`Employee`, `EmployeeAbsence`, `TimeEntry`, `TimeAdjustment`, `CompTimeImport`](../models.md#banco-de-horas)
