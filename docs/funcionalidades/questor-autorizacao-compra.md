# Autorização de Ordem de Compra (Questor)

> **Estado atual: leitura e simulação.** Esta versão do módulo **não altera nenhum
> registro** no Questor. Ela lista a fila de ordens pendentes, abre o detalhe com os itens
> e, no lugar de aprovar, mostra o comando que *seria* enviado ao ERP com o antes/depois e
> a contagem de linhas que ele pegaria. A gravação real é a próxima versão.

## O que é

O **Questor** (banco `FUNCSIDERURG`, SQL Server) é o ERP de compras. A tela nativa de
autorização dele não será usada: quem passa a coletar as aprovações é a Lara, que ao final
do processo carimba a ordem no Questor em nome de um **usuário técnico** dedicado.

A razão de existir do módulo é que o Questor só tem lugar para *um* autorizador
(`CD_USUARIO_AUTORIZOU` + `DT_AUTORIZACAO`). Fluxo com vários níveis, alçada por valor e
histórico de quem aprovou o quê não cabem lá — vão morar na Lara, e o ERP recebe só o
resultado final.

## Onde fica

| Item | Caminho |
|---|---|
| Tela | **Ordens de Compra** no menu lateral · `/questor/ordens-compra` |
| Permissão | `authorize purchase orders` |
| Controller | [PurchaseOrderController.php](../../app/Http/Controllers/Questor/PurchaseOrderController.php) |
| Leitura | [QuestorPurchaseOrders.php](../../app/Services/Questor/QuestorPurchaseOrders.php) |
| Simulação da escrita | [QuestorAuthorizationWriter.php](../../app/Services/Questor/QuestorAuthorizationWriter.php) |
| Travas | [QuestorGate.php](../../app/Services/Questor/QuestorGate.php) |
| Configuração | [config/questor.php](../../config/questor.php) |
| Conexão | `questor_sqlsrv` em [config/database.php](../../config/database.php) |
| Diagnóstico | `php artisan questor:testar` |

## Configuração

No `.env`:

```dotenv
DB_QUESTOR_HOST=
DB_QUESTOR_PORT=1433
DB_QUESTOR_DATABASE=FUNCSIDERURG
DB_QUESTOR_USERNAME=
DB_QUESTOR_PASSWORD=

QUESTOR_ENABLED=false        # ligue depois de conferir a conexão
QUESTOR_DRY_RUN=true         # NÃO desligue: a escrita não está implementada
QUESTOR_USUARIO_TECNICO=     # CD_CODUSUARIO do usuário técnico no Questor
QUESTOR_FILIAIS=             # ex.: 1,3 — vazio = todas
QUESTOR_ORDENS_DESDE=        # ex.: 2026-01-01 — vazio = todo o histórico
```

Enquanto o módulo estiver em simulação, o login do banco **só precisa de `SELECT`**. Dar
`UPDATE` agora não traz benefício e aumenta o estrago possível de um erro.

`QUESTOR_ORDENS_DESDE` existe por um motivo prático: há ~2.292 ordens paradas em PENDENTE
há anos, que nunca passaram por aprovação nenhuma. Sem uma data de corte, a tela abre com
esse passivo inteiro e a fila real se perde no meio.

### Conferir a integração

```bash
php artisan questor:testar                  # conexão, escopo, usuário técnico, 5 ordens
php artisan questor:testar --ordens=20
php artisan questor:testar --simular=40975  # simula a autorização — não grava
```

O comando é todo `SELECT`. `--simular` chama o mesmo serviço da tela.

## Como a fila é definida

Uma ordem está "pendente de autorização" quando **as três condições** valem:

```sql
CD_STATUS = 1               -- PENDENTE
AND CD_USUARIO_AUTORIZOU IS NULL
AND CD_USUARIO_REPROVOU IS NULL
```

Só o status não basta, e esse é o ponto que mais confunde nesta integração: **autorizar no
Questor não muda o status** — foi confirmado em teste ao vivo (ordem 40.975) que os únicos
campos alterados são `CD_USUARIO_AUTORIZOU` e `DT_AUTORIZACAO`. A ordem segue PENDENTE
depois de autorizada, e segue seu ciclo normal (faturamento etc.) já com o carimbo. Quem
filtrar só por `CD_STATUS = 1` traz de volta tudo o que já foi aprovado.

`DT_ATUALIZACAO` também **não** muda na autorização — não serve para detectar decisões.
A sincronização incremental (quando existir) usa `DT_CADASTRO` para achar ordens novas; não
há CDC nem fila de eventos no banco, então é polling.

## O que a simulação mostra

Ao clicar em *Simular autorização* ou *Simular reprovação*, a tela devolve:

- **O SQL exato** que seria enviado, com os parâmetros na ordem dos `?`.
- **Antes / depois** dos campos que o comando tocaria. Os demais ficam intactos.
- **Quantas linhas seriam afetadas** — a única parte que consulta o banco de verdade. Ela
  roda um `SELECT COUNT(*)` com o *mesmo* predicado do `UPDATE`, então responde a pergunta
  que importa: o comando pegaria a ordem certa, ou zero linhas porque alguém já decidiu
  pela tela nativa entre a abertura da página e o clique?
- **Impedimentos** — o que faria a gravação real falhar (usuário técnico ausente, inativo,
  sem `X_AUTORIZA_ORDEM_COMPRA`, ordem fora da fila). Essa lista precisa estar vazia antes
  de se cogitar liberar a escrita.

### Aprovação

```sql
UPDATE FUNCSIDERURG.dbo.TBL_COMPRAS_ORDEM_COMPRA
   SET CD_USUARIO_AUTORIZOU = ?,      -- usuário técnico
       DT_AUTORIZACAO = GETDATE()
 WHERE CD_ORDEM_COMPRA = ?
   AND CD_STATUS = 1                  -- proteção
   AND CD_USUARIO_AUTORIZOU IS NULL;  -- proteção
```

As duas condições extras do `WHERE` não são enfeite: são o que impede sobrescrever uma
autorização que já existe e o que faz a operação ser segura de repetir.

### Reprovação

```sql
UPDATE FUNCSIDERURG.dbo.TBL_COMPRAS_ORDEM_COMPRA
   SET CD_STATUS = 5,                 -- REPROVADO
       CD_STATUS_ANTERIOR = 1,
       CD_USUARIO_REPROVOU = ?,
       DT_REPROVACAO = GETDATE(),
       DS_MOTIVO_REPROVADO = ?        -- varchar(100), truncado
 WHERE CD_ORDEM_COMPRA = ?
   AND CD_STATUS = 1
   AND CD_USUARIO_REPROVOU IS NULL;
```

> ⚠️ Este caminho foi montado a partir do padrão de 28 casos históricos, **sem teste ao
> vivo**. Antes de liberar a gravação de reprovação é preciso reprovar uma ordem de teste
> pela tela nativa do Questor e comparar a linha campo a campo, atrás de três coisas:
> `CD_STATUS_ANTERIOR` é mesmo preenchido? `DT_ATUALIZACAO` muda neste caso (diferente da
> autorização)? Algum outro campo é tocado? A simulação exibe essa ressalva na tela.

## O que o módulo nunca toca

- `VL_FRETE`, `VL_FRETEAP`, `VL_OUTRAS` — são os únicos campos com trigger na tabela
  (`TRG_TBL_COMPRAS_ORDEM_COMPRA_REGATUALIZA`, recálculo de custo). Nenhuma instrução
  daqui os inclui.
- `TBL_ORDEM_COMPRA_PERMISSAO_DEPARTAMENTO` — a fila de múltiplos níveis do Questor está
  vazia nesta base e não é escrita nem lida pelo fluxo real (conferido antes e depois de
  uma autorização de verdade).
- `TBL_COMPRAS_ORDEM_COMPRA_ITENS`, `TBL_ENTIDADES`, `TBL_USUARIOS` — só leitura.
- `NR_REGISTRO_BLOQUEADO_EDICAO` — aparece com `1` em ordens fechadas há anos; não é lock
  em tempo real e não é usado como tal.

## O usuário técnico

Crie-o **pela tela de administração de usuários do Questor**, não por `INSERT` direto:
`TBL_USUARIOS` tem dezenas de colunas com regras de preenchimento e um formato de hash em
`DS_SENHA` que não está documentado. Depois de criado, pegue o código:

```sql
SELECT CD_CODUSUARIO, DS_USUARIO, DS_LOGIN, X_ATIVO,
       X_AUTORIZA_ORDEM_COMPRA, X_REPROVA_ORDEM_COMPRA
FROM FUNCSIDERURG.dbo.TBL_USUARIOS
WHERE DS_LOGIN = 'LARA';
```

Ele precisa de `X_ATIVO = 1`, `X_AUTORIZA_ORDEM_COMPRA = 1` e `X_REPROVA_ORDEM_COMPRA = 1`.
Ninguém loga como ele — é um carimbo de sistema. Ponha o `CD_CODUSUARIO` em
`QUESTOR_USUARIO_TECNICO` e confira com `php artisan questor:testar`.

Não tente casar usuários do Questor por e-mail: `DS_EMAIL` não é único nesta base (vários
setores compartilham caixa).

## O que ainda falta (próximas versões)

1. **O fluxo de aprovação da Lara** — níveis, alçada por valor, quem aprova o quê. Não há
   nada para herdar do Questor: a autorização praticamente nunca foi usada lá, e
   `TBL_USUARIOS.VL_ORDEM_COMPRA` está zerado em quase todos os aprovadores.
2. **A trilha de auditoria** — tabela própria com ordem, etapa, quem decidiu, quando,
   decisão e justificativa. É a Lara que passa a ser a fonte de verdade disso; o Questor só
   mostra o usuário técnico.
3. **A gravação de verdade** — hoje bloqueada em
   `QuestorAuthorizationWriter::assertWritesReleased()`. Desligar `QUESTOR_DRY_RUN` sozinho
   **não** libera nada: o serviço recusa e explica o que falta. Para destravar: usuário
   técnico criado, teste ao vivo de reprovação feito, `UPDATE` concedido ao login do banco.
4. **Polling de ordens novas** por `DT_CADASTRO`.
5. **A política das ordens antigas** — as ~2.292 pendentes entram retroativamente no fluxo
   ou só valem as novas a partir de uma data? Hoje isso é `QUESTOR_ORDENS_DESDE`, mas a
   decisão de negócio ainda não foi tomada.

## Testes

- [QuestorPendingOrdersQueryTest](../../tests/Unit/QuestorPendingOrdersQueryTest.php) — a
  consulta da fila: as três condições, a qualificação das tabelas, os filtros e o escopo.
- [QuestorAuthorizationSimulationTest](../../tests/Unit/QuestorAuthorizationSimulationTest.php)
  — o comando montado para cada decisão e, principalmente, que **nenhum `UPDATE` chega ao
  banco**: a única instrução executada na simulação é o `SELECT COUNT(*)`.

Ambos rodam sem banco — a conexão do Questor é substituída por um duble.
