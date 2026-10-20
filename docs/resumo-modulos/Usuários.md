# Usuários — resumo para quem vai mexer

O mecanismo de acesso (catálogo, setores, acesso total, como criar permissão) está em
[Setores](Setores.md). Documentação completa:
[usuarios-e-permissoes.md](../funcionalidades/usuarios-e-permissoes.md).

## Em uma frase

Cadastro das contas do painel (`/users`, rotas `users.*`, permissão `usuarios.gerenciar`, no menu
da conta): dados da conta, em quais setores a pessoa está (colaborador ou coordenador),
permissões individuais e o painel **Acesso efetivo**, que diz de onde vem cada permissão — é
onde se responde "por que fulano leva 403".

## O que a conta guarda (`App\Models\User`)

- `name`, `email`, `password`, `matricula` (até 5 caracteres, única), `cpf`, `phone`,
  `status_id`, `last_login_at`, `nav_preferences` (favoritos e ordem do menu, JSON).
- `pin`: **6 dígitos, cast `hashed`**. Vale como assinatura no kiosk dos freelancers e na
  validação de contratos/lotes. Em branco na edição mantém o atual. A própria pessoa troca no
  Perfil (`profile.pin.update`), e lá pede a senha atual de propósito.
- Login do painel por **e-mail ou matrícula** (`LoginRequest::identifierField()`, aba escolhida no
  formulário).
- A matrícula também casa a conta com o **funcionário** de mesma matrícula
  ([Funcionários](Funcion%C3%A1rios.md)): ao criar o usuário ou trocar a matrícula, o
  `UserController` chama `EmployeeSectorSync::syncUser`, que coloca a pessoa no setor do
  funcionário (colaborador; só acrescenta). O contrário não existe.
- **O model está preso à conexão `mysql`.** Tabelas em outra conexão não têm FK para `users`
  (guardam id e nome como retrato), e testes precisam de mock ou de
  `tests/Concerns/SharesSqliteWithUserConnection.php`.

## Regras que costumam pegar

- Setores e permissões individuais mudam **só** pelo `AccessManager`
  (`syncUserSectors`, `syncUserPermissions`), que audita em `access_audit_logs` e recusa
  esvaziar setor de acesso total ou tirar de si mesmo o acesso às telas de gestão.
- **Excluir é soft delete**, e antes tira a pessoa de todos os setores pelo `AccessManager` (o
  vínculo de um excluído continuaria contando para a trava). Ninguém exclui a própria conta.
- Criar usuário redireciona para a edição, para conferir o acesso efetivo.
- Coordenadores também criam contas, pela tela **Meu setor** (ver [Setores](Setores.md)) — com
  checagem de e-mail, matrícula e CPF duplicados.
- Login de usuário do sistema pela API do Telegram: `POST /api/telegram/user/login`
  (`UserAuthController`, por matrícula), restrito a quem tem `telegram.login`.
- Teste de configuração de e-mail no Perfil (`profile.email-test`) também é de
  `usuarios.gerenciar`; só abre conexão SMTP, não envia nada.

## Peças

| Arquivo | Papel |
|---|---|
| `app/Http/Controllers/UserController.php` | Lista, criação, edição, setores, permissões, exclusão. |
| `app/Http/Controllers/ProfileController.php` | Perfil da própria pessoa (dados, PIN, exclusão). |
| `app/Http/Requests/Auth/LoginRequest.php` | Login por e-mail ou matrícula. |
| `app/Models/User.php` | `access()`, `hasAccess()`, `hasFullAccess()`, `isCoordinator()`, cargos. |
| `resources/views/user/*` | Telas. |

## Testes

`tests/Feature/Authorization/AccessManagerTest.php`, `tests/Unit/LoginIdentifierTest.php`,
`tests/Feature/ProfileTest.php`, `tests/Feature/Rebrand/PainelContaScreensTest.php` (usuários com
busca). Confira o isolamento SQLite do `phpunit.xml` antes de rodar.
