# Contas do site — resumo para quem vai mexer

Documentação completa: [contas-do-site.md](../funcionalidades/contas-do-site.md).

## Em uma frase

Item **Contas do site** do menu **Reservas** (desde 23/10/2026): a equipe do clube corrige a
conta do sócio no site de locação — senha provisória, e-mail e telefone (à mão ou do
MultiClubes), cadastro manual e conta duplicada por CPF. Permissão `reservas.contas-site`.

## Peças

| Arquivo | Papel |
|---|---|
| `app/Services/Members/SiteAccountService.php` | Toda a regra. |
| `app/Http/Controllers/SiteAccountController.php` | Rotas `/contas-site` (`site-accounts.*`), `{member}` numérico com `withTrashed`. |
| `resources/views/site-accounts/*` | Lista e conta. |
| `app/Models/MemberAccountLog.php` | Histórico (`member_account_logs`). |
| `app/Models/Member.php` | Agora com `SoftDeletes`. |

## Regras que costumam pegar

- **A senha chega do site em SHA256.** Toda senha gravada pelo Lara para o site é
  `Hash::make(hash('sha256', $texto))` — use `SiteAccountService::siteHash()`. Gravar
  `Hash::make($texto)` deixa a pessoa sem conseguir entrar.
- **`Member` tem `SoftDeletes`.** Conta desativada some de `Member::where()`. Para ver
  todas, `withTrashed()`. Testes que criam a tabela `members` precisam de
  `$table->softDeletes()` (os cinco esquemas de teste já têm).
- **Login = conta mais antiga do CPF.** `MemberAuthController::validateCredentials` faz
  `Member::where('cpf')->first()`; duplicada se resolve desativando, nunca apagando (há
  reservas e inscrições ligadas).
- A senha provisória e o código nunca vão para o histórico nem para log.
- Não muda a API do site: não há prompt para o agente do Next.js.

## Testes

`tests/Feature/SiteAccountTest.php` (usa o esquema dos torneios e o MultiClubes falso de
`BuildsTournaments`; acrescenta as colunas de login em `members`).
