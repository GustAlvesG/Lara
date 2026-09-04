# Autorização de Ordem de Compra (Questor)

> ⚠️ **A aprovação grava no ERP de produção.** Com `QUESTOR_DRY_RUN=false`, clicar em
> *Autorizar no Questor* carimba a ordem de verdade e **não há desfazer pela Lara** — para
> reverter é preciso mexer na ordem pela tela nativa do Questor.
>
> A **reprovação** tem trava separada (`QUESTOR_REPROVACAO_LIBERADA`) e continua em
> simulação até o teste ao vivo dela ser feito. Ver [Reprovação](#reprovação).

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
| Trilha de auditoria | [QuestorOrderDecision.php](../../app/Models/QuestorOrderDecision.php) · tabela `questor_order_decisions` |
| Configuração | [config/questor.php](../../config/questor.php) |
| Conexão | `questor_sqlsrv` em [config/database.php](../../config/database.php) |
| Diagnóstico | `php artisan questor:testar` |
| Senha do aprovador externo | `php artisan questor:senha-aprovacao` |

## Configuração

No `.env`:

```dotenv
DB_QUESTOR_HOST=
DB_QUESTOR_PORT=1433
DB_QUESTOR_DATABASE=FUNCSIDERURG
DB_QUESTOR_USERNAME=
DB_QUESTOR_PASSWORD=

QUESTOR_ENABLED=false            # ligue depois de conferir a conexão
QUESTOR_DRY_RUN=true             # false = a APROVAÇÃO passa a gravar no ERP
QUESTOR_REPROVACAO_LIBERADA=false # trava separada da reprovação
QUESTOR_USUARIO_TECNICO=         # CD_CODUSUARIO do usuário técnico no Questor
QUESTOR_FILIAIS=                 # ex.: 1,3 — vazio = todas
QUESTOR_ORDENS_DESDE=            # ex.: 2026-01-01 — vazio = todo o histórico
```

Enquanto o módulo estiver em simulação, o login do banco **só precisa de `SELECT`**. O
`UPDATE` em `TBL_COMPRAS_ORDEM_COMPRA` só é necessário ao desligar o dry run.

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

### Senha do aprovador externo

```bash
php artisan questor:senha-aprovacao --listar   # quem já pode entrar, e quem falta
php artisan questor:senha-aprovacao 11882      # pede a senha sem ecoar na tela
```

Confere antes de gravar que a pessoa está ativa e é do setor Diretoria — sem
isso a senha é aceita e o login recusa depois, sem dizer por quê. Prefira o modo
interativo a `--senha=`: a opção deixa a senha no histórico do shell.

**Não há recuperação de senha no site externo**, de propósito: um e-mail de
reset vale a conta inteira, enquanto isto aqui exige acesso ao servidor.

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

## Os dois modos

O mesmo botão e a mesma rota servem os dois — quem decide é a configuração, e a tela diz em
qual está com uma faixa no topo (amarela = simulação, vermelha = gravação ativa).

| | `QUESTOR_DRY_RUN=true` | `QUESTOR_DRY_RUN=false` |
|---|---|---|
| Aprovação | simula | **grava no ERP** |
| Reprovação | simula | simula, a menos que `QUESTOR_REPROVACAO_LIBERADA=true` |
| Trilha em `questor_order_decisions` | não escreve | uma linha por gravação |

Na gravação, os impedimentos deixam de ser informativos e viram **recusa**: se o usuário
técnico estiver inativo, sem a permissão do ERP para aquela ação, ou se a ordem já tiver
saído da fila, nada é enviado ao Questor.

E há uma pergunta que a tela responde de propósito com aviso amarelo, não verde: **um
`UPDATE` que afeta zero linhas não é sucesso.** Ele não dá erro nenhum — simplesmente não
encontrou a ordem no estado esperado, quase sempre porque alguém decidiu pela tela nativa
entre a abertura da página e o clique. Tratar isso como "aprovado" seria o erro mais caro
possível nesta integração.

Depois de gravar, o serviço **relê a ordem no Questor** e mostra o resultado como
confirmação. É a prova de que o carimbo entrou, em vez da suposição de que entrou porque o
comando não deu erro.

## O que o resultado mostra

Ao clicar em *Autorizar* ou *Reprovar*, a tela devolve:

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

> ⚠️ **Bloqueada por padrão.** Este caminho foi montado a partir do padrão de 28 casos
> históricos, **sem teste ao vivo** — por isso desligar `QUESTOR_DRY_RUN` não a libera:
> ela tem a trava própria `QUESTOR_REPROVACAO_LIBERADA`.
>
> Para liberar: reprove uma ordem de teste pela tela nativa do Questor e compare a linha
> campo a campo, atrás de três coisas — `CD_STATUS_ANTERIOR` é mesmo preenchido?
> `DT_ATUALIZACAO` muda neste caso (diferente da autorização)? Algum outro campo é tocado?
> Ajuste o `UPDATE` conforme o resultado e só então ligue a trava. Até lá a reprovação
> continua funcionando em simulação, exibindo essa ressalva na tela.

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

## A trilha de auditoria

Tabela `questor_order_decisions`, uma linha por gravação efetiva. Ela existe porque o
Questor **não tem onde guardar isso**: lá cabe um autorizador só, que é sempre o usuário
técnico. Se três pessoas diferentes aprovarem três ordens, as três aparecem no ERP com o
mesmo login — quem de fato decidiu só existe aqui.

Guarda: ordem, filial, ação, `CD_CODUSUARIO` usado no carimbo, usuário da Lara que clicou
(id **e** nome, como retrato), motivo, valor da ordem no momento e `rows_affected`. Nada é
sobrescrito; uma segunda tentativa na mesma ordem é uma segunda linha. O histórico aparece
no rodapé do detalhe da ordem.

`rows_affected = 0` é a linha que mais importa ler: a gravação foi enviada e não pegou
nada.

A escrita da trilha acontece **depois** do `UPDATE` e fora de qualquer transação com ele —
são bancos diferentes, em servidores diferentes, sem transação distribuída. Se a trilha
falhar, o log de erro registra o ocorrido e o usuário ainda vê o resultado real: negar o
sucesso faria ele repetir uma operação que já aconteceu.

## O que ainda falta (próximas versões)

1. **O fluxo de aprovação em vários níveis** — alçada por valor, quem aprova o quê. Hoje
   uma decisão na tela é a decisão final. Não há nada para herdar do Questor: a autorização
   praticamente nunca foi usada lá, e `TBL_USUARIOS.VL_ORDEM_COMPRA` está zerado em quase
   todos os aprovadores.
2. **A gravação de reprovação** — bloqueada até o teste ao vivo da seção 6.2.
3. **Um usuário técnico dedicado.** Se `QUESTOR_USUARIO_TECNICO` apontar para o login de
   uma pessoa real, toda aprovação da Lara aparece no ERP como se aquela pessoa tivesse
   autorizado — inclusive as que outra pessoa clicou. O `LARA`/`APROVACAO_LARA` da
   especificação existe para separar essas duas coisas.
4. **Polling de ordens novas** por `DT_CADASTRO`.
5. **A política das ordens antigas** — as ~2.292 pendentes entram retroativamente no fluxo
   ou só valem as novas a partir de uma data? Hoje isso é `QUESTOR_ORDENS_DESDE`, mas a
   decisão de negócio ainda não foi tomada.

## Testes

- [QuestorPendingOrdersQueryTest](../../tests/Unit/QuestorPendingOrdersQueryTest.php) — a
  consulta da fila: as três condições, a qualificação das tabelas, os filtros e o escopo.
- [QuestorAuthorizationSimulationTest](../../tests/Unit/QuestorAuthorizationSimulationTest.php)
  — o comando montado para cada decisão, que **nenhum `UPDATE` sai em simulação**, que a
  gravação real sai com os parâmetros certos, que um impedimento recusa antes de chegar ao
  ERP e que a reprovação não escapa da trava própria.

Ambos rodam sem banco — a conexão do Questor é substituída por um duble que registra
consultas e gravações separadamente.

> A persistência da linha de auditoria em si não tem teste automatizado: ela exigiria
> `RefreshDatabase`, que está fora de uso neste projeto. A escrita é defensiva (falha vira
> log, não erro ao usuário) e foi conferida manualmente.
