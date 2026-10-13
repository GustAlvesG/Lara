# Compras — resumo para quem vai mexer

Documentação completa:
[cotacao-mapa.md](../funcionalidades/cotacao-mapa.md) (mapa de cotação, armadilhas do schema,
XLSX), [questor-autorizacao-compra.md](../funcionalidades/questor-autorizacao-compra.md)
(autorização de ordem de compra, modos de gravação) e
[questor-frontend-prompt.md](../funcionalidades/questor-frontend-prompt.md) (site externo de
aprovação). Onde esses textos falam em `authorize purchase orders`, Gate `acessar-cotacao` ou
"setor Contabilidade dá acesso", leia a permissão do catálogo **`compras`** — o resto (cargos,
estado do mapa) continua valendo.

## Em uma frase

Duas metades sobre o **Questor** (ERP, SQL Server `FUNCSIDERURG`, conexão `questor_sqlsrv`):
o **Mapa de Cotação** (somente leitura no ERP; a cotação vive no banco do Lara) e a
**Autorização de Ordem de Compra** em três níveis (o único caminho do projeto que **grava** no
ERP). No menu só aparece o mapa; Ordens de Compra e Centros de Custo estão comentados em
`App\View\Navigation` enquanto a gravação está em simulação — as rotas existem.

## Telas e acesso

| Item | Rotas | Porta | Além da porta |
|---|---|---|---|
| Mapas de Cotação / Nova Cotação (buscar SC) | `cotacao.mapas.*` | `compras` | `CotacaoMapaPolicy`: mapa fechado/cancelado é só leitura para todos; **reabrir** é do coordenador da Contabilidade |
| Ordens de Compra (fora do menu) | `questor.purchase-orders.*` | `compras` | cada nível é **cargo**: 1 = coordenador da Contabilidade, 2 = coordenador da Gerência, 3 = Diretoria |
| Centros de Custo (fora do menu) | `questor.cost-centers.*` | `compras` | sugere os diretores do nível 3 |
| Aprovação externa (site Next.js em DMZ) | `/api/aprovacao/*` | JWT próprio (`approval_token`, matrícula + senha de aprovação) | **não** usa o `api_token` compartilhado, de propósito |

O acesso total não passa por cima de cargo nem do estado do mapa (as abilities da policy não
estão no catálogo, então o `Gate::before` não as responde).

## Mapa de Cotação

- **O Questor é SOMENTE LEITURA aqui**, mesmo com `QUESTOR_DRY_RUN=false`. Três camadas: login
  do banco só com `SELECT` (DBA), repositórios herdando `QuestorReadRepository` (só `table()`,
  `select()`, `selectCached()`), e `select()` recusa SQL que não comece por `SELECT`/`WITH`. Todo
  parâmetro por binding. Consultas numeradas em `docs/sql/mapa_cotacao_questor_queries.sql`.
- Fluxo: buscar SC → prévia (com última compra e fornecedores que já venderam) → gerar (nenhum
  fornecedor marcado por padrão; máx. `QUESTOR_COTACAO_MAX_FORNECEDORES`) → preço **salvo por
  célula** (`throttle:240,1`) → vencedor por item sem reload (a resposta traz a matriz
  recalculada) → exportar / fechar.
- **Armadilhas do schema:** item da SC sem `CD_MATERIAL` é normal (sem histórico, não quebra a
  tela); `CD_STATUS` da NF não tem FK → sempre `LEFT JOIN` e cancelada em
  `QUESTOR_STATUS_NF_CANCELADA` (4 em produção; `cotacao:descobrir-config` descobre); "conta
  como compra" = `ISNULL(X_ATUALIZA_DT_ULTIMA_COMPRA, 1) = 1`.
- O retrato `ult_compra_*` é gravado na importação e congelado; "Atualizar histórico" regrava
  com log.
- Contas em `MapaCalculoService` (puro). O XLSX tem dois layouts (`classico`, o papel impresso;
  `completo`, análise) e o teste compara as **fórmulas do Excel** com o serviço.
- Tabelas `cotacao_*` sem FK para `users` (o `User` está preso à conexão `mysql`); um mapa aberto
  por SC validado na aplicação (`MapaImportService::mapaAbertoDe`).

## Autorização de Ordem de Compra

- Fila = `CD_STATUS = 1 AND CD_USUARIO_AUTORIZOU IS NULL AND CD_USUARIO_REPROVOU IS NULL` —
  autorizar **não** muda o status no Questor.
- `PurchaseOrderApprovalService`: três níveis, cada um uma decisão própria (mesma pessoa decide
  três vezes), qualquer reprovação encerra, nível 3 escolhido pela Gerência a partir da sugestão
  por centro de custo. Só o fim do nível 3 chama `QuestorAuthorizationWriter`.
- `QUESTOR_DRY_RUN=true` simula (mostra SQL, antes/depois e `COUNT(*)` com o mesmo predicado);
  `false` grava — reprovação ainda tem a trava própria `QUESTOR_REPROVACAO_LIBERADA`.
  `UPDATE` com 0 linhas **não é sucesso**. Depois de gravar, relê a ordem.
- Trilha em `questor_order_decisions` (quem decidiu de fato; no ERP aparece sempre o usuário
  técnico `QUESTOR_USUARIO_TECNICO`). Nunca toque `VL_FRETE*`/`VL_OUTRAS` (trigger).
- Comandos: `questor:testar [--simular=N]`, `questor:senha-aprovacao`.

## Peças

| Arquivo | Papel |
|---|---|
| `app/Http/Controllers/Cotacao/*` | Mapa, preço, fornecedor. |
| `app/Services/Cotacao/*` (`MapaImportService`, `MapaCalculoService`, `MapaExportService`, `Export/*`) | Importação, contas, XLSX. |
| `app/Policies/CotacaoMapaPolicy.php` | Estado do mapa e reabrir. |
| `app/Services/Questor/*` | Repositórios de leitura, ordens, centros de custo, aprovação, escrita, `QuestorGate` (travas). |
| `app/Http/Controllers/Questor/*`, `Api/PurchaseApprovalController.php` | Painel e site externo. |
| `app/Models/Cotacao*.php`, `PurchaseOrderApproval*.php`, `CostCenterApprover.php`, `QuestorOrderDecision.php` | Dados. |
| `config/questor.php`, `.env.example` (`QUESTOR_*`, `DB_QUESTOR_*`) | Configuração. |
| `resources/views/cotacao/*`, `resources/views/questor/*` | Telas. |

## Testes

`tests/Feature/Cotacao/*` (somente leitura, SQL das queries, layouts, decisão sem reload, escopo do
preço), `tests/Unit/Cotacao/*` (policy, cálculo), `tests/Unit/PurchaseOrderApprovalFlowTest.php`,
`QuestorAuthorizationSimulationTest.php`, `QuestorCostCentersTest.php`,
`QuestorPendingOrdersQueryTest.php`, `tests/Feature/Rebrand/ComprasScreensTest.php`. Nenhum teste
fala com o Questor real. Confira o isolamento SQLite do `phpunit.xml` antes de rodar.
