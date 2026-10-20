# Funcionários

## O que é

O cadastro de quem trabalha no clube, mantido pelo RH no menu **Funcionários**. É o que
**autentica** o funcionário em dois lugares:

- no **pedido de carro de aplicativo** pelo WhatsApp (Bot WhatsApp / Externos): matrícula ou
  CPF + primeiro nome;
- na **frota** (SIV): quem retira um veículo da empresa.

Funcionário **não é usuário do painel**. Tem matrícula, CPF e setor; só passa a ser "a mesma
pessoa" que um usuário quando as **matrículas casam** (`users.matricula` =
`employees.employee_code`). Não há vínculo manual.

## Para quem

Permissão do catálogo **`funcionarios.gerenciar`** (grupo Funcionários). Nasce sem setor: dê ao
setor **RH** na tela de Setores. Os setores de acesso total (Gerência, Diretoria, TI) já a
alcançam.

## Telas

| Tela | Rota | O que faz |
|---|---|---|
| Lista | `GET /funcionarios` (`employees.index`) | Busca por nome, matrícula ou CPF; filtros de setor e situação (todos, aptos, de férias/afastados, desligados, excluídos). Paginada. |
| Novo | `GET /funcionarios/novo` (`employees.create`) | Formulário de um funcionário e o bloco **Importar por planilha**. |
| Ficha | `GET /funcionarios/{id}` (`employees.show`) | Situação, usuário de mesma matrícula, edição do cadastro e da rescisão, férias e afastamentos, excluir. Abre também o excluído, para restaurar. |

Os endereços antigos `/comp-time/employees[/{id}]` (aba do RH dentro do Banco de Horas)
redirecionam para cá.

## Campos

| Campo | Coluna | Regra |
|---|---|---|
| Matrícula * | `employee_code` | Até 20 caracteres (letras, números, ponto, hífen). Única **contando os excluídos** — excluído se restaura, não se recadastra. |
| Nome * | `name` | No pedido de carro, a pessoa é conferida pelo **primeiro nome**. |
| CPF * | `cpf` | Validado pelos dígitos verificadores. **Gravado só com dígitos** (o model tira a máscara). Único entre os não excluídos. |
| Setor * | `sector_id` | Um setor da tela de Setores. |
| Cargo | `position` | Opcional. |
| Admissão | `admission_date` | Opcional. |
| Rescisão / motivo | `termination_date`, `termination_reason` | Só na ficha. Limpar a data limpa o motivo. |
| Origem | `source` | `import` (espelho de ponto), `manual` (tela) ou `planilha`. Só auditoria. |
| Estrutura no ponto | `department` | Texto cru do espelho de ponto; não decide nada. |

## Situação: quem está apto

A regra é uma só (`Employee::blockReason()` e, em SQL, `Employee::scopeEligible()`), usada pela
lista, pelo Uber e pela Frota. O funcionário fica **bloqueado** quando:

| Situação | Texto mostrado |
|---|---|
| Cadastro excluído (soft delete) | "Cadastro excluído" |
| Rescisão com data de hoje ou anterior | "Desligado em dd/mm/aaaa" |
| Férias vigentes | "Férias até dd/mm/aaaa" ou "Férias (sem data de retorno)" |
| Afastamento vigente | "Afastamento até …" ou "Afastamento (sem data de retorno)" |

Rescisão com data futura (aviso prévio) ainda não bloqueia. Férias e afastamento sem data de fim
valem dali em diante até alguém fechar o período.

## Setor do funcionário → setor do usuário

Regra do RH: **o setor do funcionário coloca o usuário de mesma matrícula nesse setor**, como
colaborador. O contrário não existe — mudar os setores do usuário não mexe no funcionário.

- Acontece ao cadastrar, ao mudar setor/matrícula/rescisão, ao restaurar, ao importar a
  planilha, e na tela de Usuários quando o usuário ganha ou troca a matrícula
  (`App\Services\Employees\EmployeeSectorSync`).
- **Só acrescenta.** Quem já está no setor fica com o papel que tem (coordenador continua
  coordenador). Mudar o funcionário de setor **não** tira o usuário do setor antigo: o acesso
  pode ter sido dado ali de propósito. Tire na tela de Setores, se for o caso.
- Funcionário **desligado ou excluído** não leva ninguém a setor nenhum. Férias e afastamento não
  impedem.
- Cuidado: setor dá acesso ao painel. Funcionário lotado num setor de **acesso total** (Gerência,
  Diretoria, TI) leva o usuário de mesma matrícula a ter acesso total.
- Toda entrada fica em `access_audit_logs` (`sector.member_added`, `details.via = funcionarios`),
  visível no histórico de Setores e de Usuários. Passa por `AccessManager::addMemberFromEmployee`.
- A importação do espelho de ponto (Banco de Horas) **não** dispara essa regra.

## Importar por planilha

No **Novo funcionário**, bloco "Importar por planilha": baixe o modelo
(`GET /funcionarios/import/template`), preencha e envie (`POST /funcionarios/import`).

| Coluna | Obrigatória | Observação |
|---|---|---|
| Matrícula | sim | É a **chave**: nova cria, existente atualiza. |
| Nome | sim | |
| CPF | sim | Com ou sem máscara; zeros à esquerda cortados pelo Excel são recompostos. |
| Setor | sim | Pelo **nome** (sem diferença de maiúsculas). Precisa já existir — a planilha não cria setor. |
| Cargo | não | Em branco não apaga o gravado. |
| Data de admissão | não | dd/mm/aaaa ou aaaa-mm-dd. Em branco não apaga o gravado. |

Regras (`App\Imports\EmployeeImport`):

- **Tudo-ou-nada**: qualquer linha com erro e nada é gravado; a lista de erros volta para a
  tela, com o número da linha.
- Matrícula ou CPF repetidos **no próprio arquivo** são erro.
- CPF que já pertence a **outro** funcionário ativo é erro.
- Matrícula de funcionário **excluído** é erro: restaure-o na ficha antes.
- Rescisão, férias e afastamento ficam de fora: são eventos com data e motivo, registrados na
  ficha.
- Depois de gravar, cada linha passa pela regra de setor do usuário.

## Excluir, desligar

- **Desligar** = registrar a rescisão na ficha. É o caminho para quem saiu: o histórico fica e o
  funcionário passa a constar como bloqueado.
- **Excluir** é soft delete: some das listas e da autenticação, mas guarda banco de horas,
  viagens e pedidos de carro. A ficha do excluído continua abrindo (filtro "Excluídos") e tem o
  botão **Restaurar**. Não há exclusão definitiva.
- **Setor com funcionário não pode ser excluído** (`SectorController::destroy`, contando os
  excluídos). No MySQL a chave `employees.sector_id` também passa a `restrictOnDelete`.

## Integrações

### Carro de aplicativo (Uber)

`MemberValidator` consulta o `EmployeeLookup`, que devolve cada funcionário com o motivo do
bloqueio:

- funcionário **apto** que confere → `member_validation = validado`, tipo `funcionario`;
- funcionário **bloqueado** que confere → `member_validation = funcionario_bloqueado` (selo
  vermelho "Funcionário bloqueado"), com o motivo em `member_validation_detail`;
- o funcionário que conferiu fica em `uber_access_requests.employee_id`.

Com matrícula (que tem o mesmo formato do código de título), a ordem é: funcionário apto → sócio
no MultiClubes → funcionário bloqueado → não confere. Assim um sócio com o mesmo número não é
afetado pelas férias de um funcionário. Se o MultiClubes estiver fora, o bloqueado ainda aparece
como bloqueado.

A conferência **não trava** o pedido — como antes, é informação para a portaria, que decide. A
API da portaria (`CompanyService`) passou a mandar `member_validation_detail` e `employee_id`.

### Frota

`FleetService::resolveDriver`:

- motorista identificado (matrícula, CPF, nome único ou `employee_id`) e **bloqueado** → recusa
  com `driver_blocked` (HTTP 403 na API), mensagem com o motivo e `reason` no corpo;
- busca de motorista (`/api/fleet/drivers` e tela) só devolve **aptos**, e agora traz `sector`;
- CPF compara só dígitos (antes, o CPF gravado com máscara nunca era encontrado);
- **`FLEET_REQUIRE_EMPLOYEE`** (`config('fleet.require_employee')`, padrão `false`): ligado, quem
  não está no cadastro também é recusado (`driver_not_employee`, 422). Deixe desligado até o RH
  terminar a carga pela planilha.

## Implantação

1. `php artisan migrate`:
   - `2026_10_20_100000_prepare_employees_for_registry` — CPF só com dígitos; cargo, estrutura e
     admissão passam a aceitar nulo; coluna `source`; no MySQL, `sector_id` vira NOT NULL com
     `restrictOnDelete` **se nenhum funcionário estiver sem setor**. Havendo algum, a coluna fica
     como está e um aviso vai para o log (`prepare_employees_for_registry: N funcionário(s) sem
     setor`). Corrija-os na tela e aplique a trava com uma migration nova.
   - `2026_10_20_100100_add_employee_to_uber_access_requests` — `employee_id` e
     `member_validation_detail` no pedido de carro.
2. `PermissionCatalogSeeder` cria `funcionarios.gerenciar` (os scripts de deploy já rodam).
3. `route:cache` (os scripts de deploy já rodam).
4. Na tela de **Setores**, dar `funcionarios.gerenciar` ao RH.
5. Atualizar a aplicação da portaria (prompt em
   [funcionarios-prompt-portaria.md](../funcionarios-prompt-portaria.md)).
6. Depois da carga do RH, avaliar ligar `FLEET_REQUIRE_EMPLOYEE=true`.

## Referência técnica

- Controller: `App\Http\Controllers\EmployeeController`; validação `App\Http\Requests\EmployeeRequest`
- Importação: `App\Imports\EmployeeImport` (base `SpreadsheetImport`)
- Regra de setor: `App\Services\Employees\EmployeeSectorSync`, `AccessManager::addMemberFromEmployee`
- Models: `Employee` (`blockReason`, `isEligible`, `scopeEligible`, `scopeActive`, `scopeTerminated`), `EmployeeAbsence`
- Uber: `App\Services\MemberValidation\{EmployeeLookup, MemberValidator, MemberValidationResult}`
- Frota: `App\Services\FleetService::resolveDriver`, `searchDrivers`
- Testes: `tests/Feature/Employees/EmployeeModuleTest.php`, `tests/Unit/MemberValidatorTest.php`,
  `tests/Feature/UberAccessRequestWebhookTest.php`, `tests/Feature/FleetMileageTest.php`
