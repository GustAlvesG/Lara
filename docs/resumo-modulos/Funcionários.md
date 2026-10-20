# Funcionários — resumo para quem vai mexer

Documentação completa: [docs/funcionalidades/funcionarios.md](../funcionalidades/funcionarios.md).

## Em uma frase

Cadastro do RH (`/funcionarios`, rotas `employees.*`, permissão `funcionarios.gerenciar`) de
quem trabalha no clube — matrícula, CPF e setor — que **autentica** o funcionário no pedido de
carro de aplicativo (WhatsApp) e na frota (SIV). Tem CRUD, férias/afastamentos, soft delete com
restauração e **importação em massa por .xlsx**.

## Regras que costumam pegar

- **Funcionário não é usuário.** Os dois só se ligam quando `users.matricula` =
  `employees.employee_code` (`Employee::user()`, hasOne). Não há coluna de vínculo nem botão de
  vincular. `users.matricula` tem 5 caracteres: matrícula mais longa nunca casa.
- **Setor do funcionário → usuário, nunca o contrário.** `EmployeeSectorSync` coloca o usuário
  de mesma matrícula no setor do funcionário (colaborador), só acrescentando, só para funcionário
  ativo. Chame-o **explicitamente** depois de gravar — não há evento de model, de propósito: o
  `User` mora na conexão `mysql`, e um gancho no `saved` faria todo teste que cria funcionário
  abrir essa conexão. A gravação passa por `AccessManager::addMemberFromEmployee` (auditoria).
- **Apto = `Employee::scopeEligible()` / `blockReason()`.** Bloqueia: excluído, rescisão em vigor,
  férias ou afastamento vigentes. Quem precisa "funcionário que pode usar algo" usa esses dois,
  nunca `active()` sozinho.
- **CPF é gravado só com dígitos** (mutator em `Employee::cpf()`). Compare direto com
  `Cpf::digits($entrada)`; para exibir, `Cpf::format()`.
- **Matrícula é única contando os excluídos** (índice do banco). Recadastrar excluído estoura;
  restaure. A planilha recusa matrícula de excluído pelo mesmo motivo.
- **Datas no SQLite**: compare `date` com `whereDate`, não `where` — no SQLite a data é gravada
  com hora e a comparação de texto erra (já pegou `EmployeeAbsence::scopeCurrent`).
- **Setor com funcionário não se exclui** (`SectorController::destroy` + FK `restrictOnDelete`
  no MySQL quando a migration conseguiu aplicar).
- A importação do Banco de Horas (`CompTimeService::upsertEmployee`) ainda cria/atualiza
  funcionários, mas **não troca o setor** de quem já tem e não dispara a regra do usuário.

## Planilha

`EmployeeImport` (base `SpreadsheetImport`, tudo-ou-nada). Chave = matrícula: nova cria, existente
atualiza; opcional em branco não apaga. Setor pelo nome, precisa existir. Colunas: Matrícula,
Nome, CPF, Setor (obrigatórias), Cargo, Data de admissão. O bloco de tela é o
`freelancer/partials/import-card` reaproveitado.

## Integrações (mexeu aqui, olhe lá)

- **Uber** (`MemberValidator`): status novo `funcionario_bloqueado` com motivo em
  `member_validation_detail` e `employee_id` no pedido. A API da portaria manda os dois campos —
  mudança de contrato com a aplicação da guarita (prompt em `docs/funcionarios-prompt-portaria.md`).
- **Frota** (`FleetService::resolveDriver`): `driver_blocked` (403) e, com
  `FLEET_REQUIRE_EMPLOYEE=true`, `driver_not_employee` (422). `/api/fleet/drivers` só devolve
  aptos e traz `sector`.

## Testes

`tests/Feature/Employees/EmployeeModuleTest.php` (Feature com `MigratesAccessSchema` +
`SharesSqliteWithUserConnection`; migra também notificações e avisos porque as telas desenham o
layout). Rode com `php -d memory_limit=1G vendor/bin/phpunit` — o `artisan test` não repassa o
limite de memória.
