# Contas do site (Reservas › Contas do site)

## O que é

Tela do Lara para a equipe do clube resolver os problemas de **conta do sócio no site de
locação** (o site das reservas de quadra, espaços sociais e torneios):

- "esqueci a senha de login para reservar quadra";
- "meu login não entra";
- "meu e-mail está errado".

Pedida em 23/10/2026 (começou como "F9" do plano dos torneios, mas não é ligada a eles).
Não muda a API do site: a pessoa continua entrando e trocando a senha pelo site.

Desde 12/11/2026 a tela também define o **perfil no site** da conta (Usuário ou
Administrador). O administrador abre os **dashboards do site** — ver
[dashboards-site.md](dashboards-site.md).

## Para quem

Permissão **`reservas.contas-site`** (grupo Reservas). Nasce sem setor: dê na tela de
Setores a quem atende os sócios (recepção, secretaria, Esporte).

Trocar o perfil no site pede, além dela, **`reservas.contas-site.perfil`** (12/11/2026):
o administrador vê os números financeiros dos ingressos no site, então quem só corrige conta
não promove ninguém. Também nasce sem setor.

## Telas

### Lista (`/contas-site`)

Busca por CPF, matrícula, nome ou e-mail; **Só duplicadas**; **Só administradores**
(`?administradores=1`). Selos: *administrador*, *desativada*, *CPF duplicado*, *sem e-mail*. Um aviso no topo conta os CPFs com mais de uma conta ativa.

**Cadastrar conta para um sócio**: CPF + matrícula. Precisa ser sócio de título ativo no
MultiClubes; nome, nascimento, código de barras, e-mail e telefone vêm de lá. O Lara gera uma
**senha provisória**. CPF que já tem conta (mesmo desativada) é recusado: abra a conta
existente.

### Conta (`/contas-site/{id}`)

- **Diagnóstico** — o que costuma explicar "meu login não entra":
  - o CPF tem **mais de uma conta ativa**: o login entra só na mais antiga (menor id); se a
    senha que a pessoa usa é a da outra conta, ela não entra;
  - sem sócio de título ativo no MultiClubes, ou matrícula diferente;
  - conta sem e-mail;
  - senha ainda no formato antigo (é convertida no próximo login certo);
  - diferenças de nome, e-mail e telefone entre a conta e o MultiClubes.
- **E-mail e telefone**: corrigir à mão, ou **Trazer do MultiClubes** (copia nome, e-mail e
  telefone do cadastro oficial; o que lá estiver vazio não apaga o da conta).
- **Senha**: **Gerar senha provisória** — 8 letras e números minúsculos, sem os que se
  confundem (0/o, 1/l/i). Aparece **uma vez**; passe para a pessoa depois de confirmar quem
  é. Ela entra com o CPF e essa senha e troca no site ("Alterar senha"). A senha anterior
  deixa de valer.
- **Perfil no site** (12/11/2026): **Usuário** (padrão) ou **Administrador**. Com
  `reservas.contas-site.perfil` aparece o seletor e **Salvar perfil** (`PUT
  /contas-site/{id}/perfil`, `site-accounts.role`); sem ela, só o perfil atual. Vale na hora,
  mesmo para quem já está logado no site (o perfil é relido a cada requisição). Conta
  desativada não vira administradora. A troca entra no histórico (`site_role`, antes →
  depois).
- **Contas com este CPF** (quando há mais de uma): cada uma com data de criação, e-mail, o
  que está ligado a ela (reservas, espaços sociais, inscrições em torneios, iluminação) e
  qual o login usa. **Desativar esta** (com motivo) só quando o CPF tem outra conta ativa.
  **Reativar** só quando o CPF não tem outra ativa.
- **Histórico**: quem fez o quê, e quando.

## Regras técnicas

- **Formato da senha.** O site manda a senha já em **SHA256** (é o que o login compara: as
  senhas antigas estão gravadas como SHA256 puro e o login faz `hash_equals` com o que chega).
  Por isso a provisória é gravada como `Hash::make(hash('sha256', provisória))`
  (`SiteAccountService::siteHash`), igual a uma senha nova do site. Conferido em 23/10/2026
  no banco de homologação (168 de 290 contas ainda em SHA256 puro).
- **Desativar = exclusão lógica.** `Member` passou a usar `SoftDeletes` em 23/10/2026 (a
  coluna `deleted_at` existia desde 2025, sem uso e sem nenhuma linha preenchida). Conta
  desativada some do login e de toda busca `Member::where(...)`; reservas e inscrições
  continuam apontando para ela. Nunca apague a linha.
- **Duplicadas.** Em 23/10/2026 havia 4 CPFs com 2 ou 3 contas, todas na mesma matrícula
  (cadastro repetido). O `MemberAuthController::store` recusa CPF existente; as duplicadas
  são de antes ou de corrida.
- **Histórico** em `member_account_logs` (`member_id`, `user_id`, `action`, `details` com
  antes/depois; a senha nunca entra).
- **MultiClubes** pelo `MemberProfileLookup` (que passou a trazer `MobilePhone` e `Barcode`).
- **Perfil no site** em `members.site_role` (`usuario` | `administrador`, padrão `usuario`;
  migration `2026_11_12_100000_add_site_role_to_members`, idempotente). Constantes
  `Member::ROLE_*`, `Member::isSiteAdmin()` (administrador e não desativada) e
  `Member::loginAccount($cpf)` (a conta que o login usa). `SiteAccountService::setRole`.

## Dados de teste

`php artisan torneios:dados-teste` cria, com o prefixo `[TESTE]`, um CPF com duas contas,
uma conta sem e-mail e uma com senha no formato antigo (ver [torneios.md](torneios.md)).

## Peças

| Arquivo | Papel |
|---|---|
| `app/Services/Members/SiteAccountService.php` | Busca, diagnóstico, correção, senha provisória, cadastro manual, desativar/reativar. |
| `app/Http/Controllers/SiteAccountController.php` | Telas e ações (`site-accounts.*`). |
| `resources/views/site-accounts/{index,show}.blade.php` | Lista e conta. |
| `app/Models/MemberAccountLog.php`, migration `2026_10_23_120000_create_member_account_logs_table` | Histórico. |
| `app/Models/Member.php` | `SoftDeletes`, `accountLogs()`. |
| `tests/Feature/SiteAccountTest.php` | Senha provisória que entra no `POST /api/login`, correção, MultiClubes, cadastro, duplicadas, perfil no site (histórico, filtro, conta desativada, permissão própria). |
