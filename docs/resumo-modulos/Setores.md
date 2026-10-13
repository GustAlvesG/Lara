# Setores — resumo para quem vai mexer

Cobre também **Meu setor**, a tela do coordenador. A gestão de pessoas está em
[Usuários](Usu%C3%A1rios.md).

Documentação completa: [usuarios-e-permissoes.md](../funcionalidades/usuarios-e-permissoes.md)
(matriz inicial, telas, implantação) e
[04-autenticacao-e-permissoes.md §4.4](../04-autenticacao-e-permissoes.md#44-acesso-do-painel-setores-e-permissões)
(mecanismo).

## Em uma frase

O acesso a todo o painel do Lara é decidido por **setor**: cada setor recebe permissões do
catálogo (para todos os membros ou só para os coordenadores), três setores têm **acesso total**
(Gerência, Diretoria, TI) e a pessoa ainda pode ter permissões individuais. Telas `sectors.*`
(permissão `setores.gerenciar`), `sectors.audit` (histórico) e `my-sector.*` (qualquer
coordenador, Gate `coordinate-sector`). Ficam no menu da conta, não nos módulos.

## De onde vem o acesso (`AccessResolver` → `UserAccess`)

1. `sectors.full_access` → todas as permissões do catálogo.
2. `sector_permission` → por setor, com `coordinators_only`.
3. `model_has_permissions` → individual.

`User::access()` calcula uma vez por requisição (três consultas, sem cache entre requisições):
tirar alguém do setor corta o acesso no clique seguinte. `UserAccess::sourcesOf()` responde de
onde veio cada permissão (usado no painel "Acesso efetivo" da tela de Usuários).

## Como incluir uma permissão nova (regra do CLAUDE.md)

1. Constante + linha em `CATALOG` de `App\Authorization\Permissions` (grupo = nome do módulo no
   menu, rótulo legível).
2. Rota com `->middleware('can:' . P::NOVA)`; item do menu em `App\View\Navigation::links()` com a
   **mesma** permissão; view com `@can`.
3. Nada de migration nem de setor no código: `PermissionCatalogSeeder` (rodado por
   `deploy_prod.sh` e `deploy_hml.sh` depois das migrations) cria o que falta, sem tocar no que
   já está configurado e sem apagar o que saiu do catálogo. Os setores pertinentes são marcados
   na tela de Setores.

## Regras que costumam pegar

- Só se checa com `can()` (rota, view, menu). O `Gate::before` do `AppServiceProvider` responde
  **apenas** nomes do catálogo; o do Spatie está desligado (`register_permission_check_method =
  false`) e as roles não decidem nada.
- **Cargo não é permissão.** Gates de cargo (`validate-freelancer-contracts`,
  `manage-freelancer-director`, `coordinate-sector`, `view-comp-time`, níveis de ordem de compra,
  reabrir mapa) ficam fora do catálogo e o acesso total não passa por cima. Nunca dê a um Gate de
  cargo um nome que exista no catálogo — `tests/Unit/Authorization` vigia.
- Toda mudança passa por `AccessManager` (grava `access_audit_logs`) e ele recusa esvaziar setor
  de acesso total e tirar de si mesmo o acesso a Usuários/Setores (`AccessChangeRejected`).
- `LegacyPermissionMap` é só para o comando `acesso:diferenca` (de-para das permissões antigas);
  não decide acesso.
- Menu e layout: nunca método do model que consulte banco (quebra testes com `User` mockado) —
  use `can()`.

## Meu setor

`MySectorController`, restrito aos setores que a pessoa **coordena**: colocar usuário existente
como colaborador, cadastrar gente nova já colaboradora (se e-mail, matrícula ou CPF existirem,
oferece a pessoa existente) e tirar colaborador. Não promove, não rebaixa coordenador, não mexe
no que o setor alcança. `throttle` na criação de usuário (10/min).

## Peças

| Arquivo | Papel |
|---|---|
| `app/Authorization/Permissions.php` | Catálogo (fonte única dos nomes). |
| `app/Authorization/AccessResolver.php`, `UserAccess.php` | Cálculo do acesso. |
| `app/Authorization/AccessManager.php`, `AccessChangeRejected.php` | Mudanças com trava e auditoria. |
| `app/Authorization/LegacyPermissionMap.php` | De-para do legado. |
| `app/Http/Controllers/SectorController.php`, `MySectorController.php` | Telas. |
| `app/Models/Sector.php`, `AccessAuditLog.php` | Dados. |
| `database/seeders/PermissionCatalogSeeder.php` | Seed padrão do deploy. |
| `resources/views/sector/*`, `resources/views/my-sector/*` | Telas. |

Comandos: `acesso:diferenca [--so-perdas]`, `acesso:aplicar-de-para [--aplicar]`,
`acesso:limpar-legado --confirmar`.

## Testes

`tests/Feature/Authorization/*` (resolver, manager, comandos, menu por setor, migration do
catálogo), `tests/Unit/Authorization/CatalogGateTest.php`, `tests/Feature/PermissionCatalogSeederTest.php`,
`tests/Feature/Rebrand/PainelContaScreensTest.php`. O `User` está preso à conexão `mysql`: use
teste Unit com mock ou Feature com `SharesSqliteWithUserConnection`, e confira o isolamento SQLite
do `phpunit.xml` antes de rodar.
