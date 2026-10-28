# Torneios — resumo para quem vai mexer

Documentação completa: [torneios.md](../funcionalidades/torneios.md) (regras decididas,
modelagem e fases).

## Em uma frase

O Lara é o backend dos torneios vendidos no **site de locação** (o mesmo Next.js das
reservas) — catálogo de categorias por grupo de espaços, inscrição individual do sócio com
pagamento eRede, formação de times, chaveamento, resultados (Lara e site) e financeiro.
Refeito do zero em 22–23/10/2026 (F0 a F8). Guia do Esporte:
[guias/torneios.md](../guias/torneios.md).

## Onde está

| Fase | Situação |
|---|---|
| F0–F1: conferir e apagar o módulo antigo | feita no código em 22/10/2026 |
| F2: tabelas, models, permissões, catálogo de categorias, busca no MultiClubes | feita em 22/10/2026 |
| F3: cadastro do torneio, inscrição individual, pagamento eRede, baixa manual, cancelamento com estorno, e-mails, API do site | feita em 22/10/2026 |
| F4: times (organizador, sorteio, capitão com convite por e-mail, individual automático) | feita em 23/10/2026 |
| F5: chaveamento (eliminatória, grupos + mata-mata, sorteio ou à mão, divulgação no site) | feita em 23/10/2026 |
| F6: resultados no Lara e no site (código de lançamento por torneio), fechar grupo, histórico | feita em 23/10/2026 |
| F7: andamento por categoria, relatório financeiro e CSV, menu | feita em 23/10/2026 |
| F8: guia, prompt do site consolidado, suíte | feita em 23/10/2026 |

A "F9" do plano (senha e cadastro manual de sócios) **não é deste módulo**: é Reservas ›
Contas do site ([resumo](Contas%20do%20site.md)).

Não confunda com o primeiro módulo (apagado): `TournamentController`, `TournamentService`, as
tabelas `categories`/`teams`/`tournament_subscription*` e a permissão `torneios` não existem
mais. O namespace `App\Models\Tournament` foi reaproveitado pelos models novos.

## Peças

| Arquivo | Papel |
|---|---|
| `database/migrations/2026_10_22_110000_create_tournament_tables.php` | As 14 tabelas (idempotente, FKs com nome curto). |
| `app/Models/Tournament/*` | Models com as situações em constantes; elegibilidade em `TournamentCategory::ineligibilityReason()`. |
| `app/Services/MultiClubes/MemberProfileLookup.php`, `MemberProfile.php` | Sócio no MultiClubes por CPF (lista) ou matrícula; `find(cpf, matrícula)`; regra do sexo. |
| `app/Http/Controllers/Tournament/CategoryTemplateController.php`, `resources/views/tournaments/categories.blade.php` | Torneios › Categorias. |
| `app/Services/Tournament/TournamentRegistrationService.php` | Inscrição, pagamento eRede, baixa manual, desistência, cancelamento com estorno (trava na categoria). |
| `app/Services/Tournament/TournamentSetupService.php` | Situação do torneio (`TRANSITIONS`) e categorias ligadas. |
| `app/Services/Tournament/TournamentNotifier.php`, `resources/views/emails/tournament/status.blade.php` | E-mails `tournament.*` (mapeados no `ContactMail`). |
| `app/Exceptions/TournamentException.php` | Recusas com `reason` estável para o site. |
| `app/Services/Tournament/TournamentTeamService.php`, `TournamentInviteService.php` | Times: organizador e sorteio; capitão, busca com `ref` cifrada, convite, aceite. |
| `app/Http/Controllers/Tournament/TournamentTeamController.php`, `resources/views/tournaments/teams.blade.php` | Tela de times da categoria. |
| `app/Services/Tournament/TournamentBracketService.php` | Chaveamento: gerar (sorteio/à mão), desfazer, divulgar, classificação do grupo, `advance()`; contas estáticas da chave (`seedOrder`, `crossRanks`). |
| `app/Http/Controllers/Tournament/TournamentBracketController.php`, `resources/views/tournaments/bracket.blade.php`, `partials/match-card.blade.php` | Tela de chaveamento da categoria. |
| `app/Services/Tournament/TournamentResultService.php`, `TournamentResultCodeService.php` | Resultados (placar, W.O., limpar, fechar/reabrir grupo, histórico) e código de lançamento pelo site. |
| `app/Http/Controllers/Tournament/TournamentResultController.php` | Ações de resultado e de código no painel (`tournaments.results.*`). |
| `app/Http/Controllers/Api/TournamentStaffApiController.php`, `app/Services/Tournament/TournamentBracketPresenter.php` | API da equipe no site (`/api/tournaments/{id}/staff/*`, header `Tournament-Code`) e o JSON do chaveamento (público e equipe). |
| `app/Services/Tournament/TournamentFinanceService.php`, `TournamentFinanceController.php`, `resources/views/tournaments/finance/*` | Relatório financeiro e CSV (`torneios.financeiro`). |
| `app/Http/Controllers/Tournament/TournamentController.php`, `TournamentCategoryController.php`, `TournamentRegistrationController.php`, `resources/views/tournaments/*` | Painel: torneios, categorias ligadas, inscrições. |
| `app/Http/Controllers/Api/TournamentApiController.php` | `/api/tournaments/*` do site. |
| `app/View/Navigation.php` | Entrada **Torneios** (área `placar`): Torneios e Categorias. |
| `app/Authorization/Permissions.php` | `TORNEIOS_*` (6 permissões, grupo Torneios; `financeiro` desde a F7). |

## Regras que costumam pegar

- **No Lara, tudo é permitido mediante aviso** (23/10/2026): regra de prazo, situação,
  vagas, sexo/idade, chaveamento com resultado e ordem dos resultados lança
  `App\Exceptions\ConfirmationRequired` (`unless($force, $avisos)`) em vez de recusar; a
  tela volta com *Confirmar mesmo assim* (reenvia com `force=1`, quadro em
  `partials/alerts`). O **site** continua recebendo a `TournamentException` com o `reason`.
  Serviço novo que tenha regra assim: junte os avisos, receba `bool $force` e chame
  `ConfirmationRequired::unless` antes de gravar; nunca dentro do caminho do site. Tabela
  completa em `torneios.md` › *No Lara, só aviso*.
- Vaga conta **pagas**; a inscrição pendente não segura vaga. O pagamento reconfere a vaga.
- `RedeItauService::refund()` virou público para os torneios: o estorno é gravado no
  `TournamentPayment` pelo service, não pelo `refundPayment` (que é do `SchedulePayment`).
- O sócio da API sai do JWT (`Member::where('cpf', username)`), como nos espaços sociais.
- O balcão exige cadastro no site (o `member_id` é obrigatório na inscrição). `register()`
  com `$override` (`[User, motivo|null]`) é o balcão: avisos em vez de recusas; sem ele, é o
  site.
- `register()` devolve coleção do Eloquent (o controller da API faz `load()` nela).
- `TournamentRegistrationService` depende do `TournamentTeamService` (pagar → `afterPaid`,
  sair → `detach`); o `TournamentInviteService` depende dos dois. Não inverta, senão vira
  dependência circular.
- Time completo = cheio **e** todos pagos. Convite pendente ocupa vaga do time.
- Chaveamento: entram **todos os times não desfeitos** (também os em formação); gerar de novo
  ou desfazer com resultado (encerrado/W.O.; "passa direto" não conta) pede confirmação e
  apaga; gerar de novo tira do site (`bracket_published_at` nulo). Mudar o formato com
  chaveamento pede confirmação e desfaz o chaveamento (`TournamentSetupService` depende do
  `TournamentBracketService`).
- No mata-mata vindo de grupos, os lados nascem só com a origem (`*_source_group_id/position`);
  o time entra quando o grupo fecha (F6). O 3º lugar é o jogo com `third_place`; `advance()`
  leva o vencedor por `next_match_id` e o perdedor da semifinal para ele.
- Resultado: o **Lara trava** (`locked_by_lara`); o site recebe `match_locked`. Só o Lara
  limpa. Correção que muda vencedor com o jogo seguinte jogado, ou jogo de grupo fechado:
  o site é recusado (`next_match_played`, `group_closed`); o Lara confirma e o serviço
  reabre o grupo e limpa em cascata (`wipe` com `cascade` no histórico).
- Tirar categoria: com inscrição que passou por pagamento é recusado (histórico
  financeiro); sem pagamento, confirma e apaga as inscrições (FK de
  `tournament_registrations` não cascateia).
- O site da equipe **não tem login**: autoriza pelo código do torneio (sha256 em
  `tournaments.results_code_hash`, vence no fim do último dia). O nome de quem lança
  (`operator_name`) vai para o histórico, `result_user_id` fica nulo.
- Financeiro conta **por inscrição**: o estorno de cada inscrição fica em
  `tournament_registrations.refunded_amount` (gravado no `cancel`), porque um pagamento
  cobre várias categorias.
- Comparações de id usam `===`: `TournamentMatch` e `TournamentGroupTeam` têm cast `integer`
  nas chaves — mantenha ao acrescentar colunas de id.
- Nos testes, o falso do MultiClubes (`BuildsTournaments`) cobre `byCpf` **e** `byTitle`:
  o `mc_sqlsrv` dos testes aponta para o MultiClubes real.

## Dados de teste

`php artisan torneios:dados-teste` (`app/Console/Commands/TournamentTestData.php`): todos os
cenários com prefixo `[TESTE]`, idempotente, recusa produção; `--so-limpar`; `--socio=CPF`
para testar o site com uma conta real. Detalhes em `torneios.md`.

## Testes

`tests/Feature/Tournament/*` (catálogo, fluxo de inscrição e pagamento, API com JWT, telas do
painel, times, capitão, chaveamento, resultados e API da equipe, financeiro), sobre `Tests\Concerns\CreatesTournamentSchema` e `Tests\Concerns\BuildsTournaments`
(MultiClubes e eRede em memória); `tests/Unit/Tournament/CategoryEligibilityTest.php`,
`tests/Unit/MultiClubes/MemberProfileLookupTest.php`, `tests/Unit/Tournament/BracketMathTest.php`, `tests/Unit/Tournament/ScoreValidationTest.php`. Confira o isolamento SQLite antes de rodar.

## Integração com o site

Contrato e prompt para o agente do Next.js:
[torneios-site-prompt.md](../funcionalidades/torneios-site-prompt.md). Toda mudança na API
atualiza esse arquivo.

## O que não pode ser esquecido

- **Só sócios** se inscrevem por enquanto (titular e dependentes). Não sócios estão em
  "Para implementação futura" no `torneios.md`.
- **Sexo**: `dbo.Members.Sex` do MultiClubes, **0 = masculino, 1 = feminino** (regra fixada,
  em `MemberProfileLookup::genderFromSex`). O Lara não guarda sexo em `members`; use o
  `MemberProfileLookup` (o `MemberController::getByTitle` atual concatena o texto no SQL —
  não copie).
- **CPF** no MultiClubes: há CPF de preenchimento (`00000000000`) e o mesmo CPF em mais de um
  título. Nunca pegue "o primeiro" de `byCpf()` para o sócio logado: use `find(cpf, matrícula)`.
- **Idade** na data de início do torneio.
- **Matrícula** é o título da família: traz vários membros; o convite guarda o **CPF**.
- **Pagamento** individual pela eRede, só dentro do período de inscrição; siga o desenho do
  `SchedulePaymentController::store` (posse pela sessão, idempotência pelo tid) e use o
  `RedeItauService` para consulta e estorno.
- **Resultado**: sets e games, sem empate; o **Lara prevalece** sobre o site; a equipe do
  site entra com o **código por torneio** (decidido em 23/10/2026, no lugar do login Sanctum
  previsto no plano).
- **Nomes de tabela** com prefixo `tournament_`; dê nome curto a índice e FK (limite de 64
  do MySQL) e faça as migrations idempotentes.
- **Permissões** novas entram no catálogo sem setor; o setor Esporte recebe pela tela de
  Setores.
- O módulo conversa com o Next.js: toda mudança na API gera um prompt para o agente do site.
