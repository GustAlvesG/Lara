# Freelancers — resumo para quem vai mexer

Documentação completa (regras, porquês, API, telas): [docs/funcionalidades/freelancers.md](../funcionalidades/freelancers.md).
Pix automático: [docs/funcionalidades/pix-sicoob.md](../funcionalidades/pix-sicoob.md). Portaria:
[docs/company-access-control.md](../company-access-control.md#freelancer).

## Em uma frase

Cadastro de **freelancers**, das **funções** (preço por bloco de 15 min) e dos **contratos** de um dia
(`freelancer_services`), que o freelancer assina num **tablet** (`/kiosk`, login matrícula + PIN), a
coordenação do Comercial valida, entram em **lote**, passam pela **gerência** (web) e pela **diretoria**
(código ditado por e-mail), e o **financeiro** paga (baixa manual ou Pix Sicoob). O contrato também
**abre a portaria** e decide o **jantar**.

> **Não é o módulo Assinaturas.** O `/kiosk` daqui não tem QR, manifesto nem `SignatureStateMachine`;
> o `/assinatura/kiosk` de lá não sabe de freelancer. Os dois só dividem o disco/pasta do FTP
> (`config('signature.archive.*')`) e o `App\Support\ArchivePath`.

## Menu e permissões

Menu **Freelancers** (`app/View/Navigation.php`): Freelancers, Funções, Serviços / Contratos,
Assinatura (Tablet), Acompanhamento, Financeiro.

| Permissão (`App\Authorization\Permissions`) | Uso |
|---|---|
| `freelancers.cadastro` (`FREELANCERS_CADASTRO`) | Cadastro de freelancers + importação |
| `freelancers.funcoes` (`FREELANCERS_FUNCOES`) | Funções (preço, caixa "recebe comissão") |
| `freelancers.servicos.listar` (`FREELANCERS_SERVICOS_LISTAR`) | Só a lista de contratos, **sem valores** |
| `freelancers.servicos.gerenciar` (`FREELANCERS_SERVICOS_GERENCIAR`) | Valores, documento, registro (individual/massa/planilha), lotes, validação, diretoria, cancelar |
| `freelancers.assinatura` (`FREELANCERS_ASSINATURA`) | Entrar no `/kiosk` como operador |
| `freelancers.acompanhamento` (`FREELANCERS_ACOMPANHAMENTO`) | Aba Acompanhamento (só leitura) |
| `freelancers.financeiro` (`FREELANCERS_FINANCEIRO`) | Financeiro e baixa/Pix |

**Cargos, não permissões** (o "acesso total" de setor NÃO dá; vêm de `user_sector.role = 'coordinator'`):

| Ato | Quem | Onde |
|---|---|---|
| Validar contrato (redação 2) / assinar no tablet (redação 1) / liberar limite semanal | coordenador do **Comercial** | Gate `validate-freelancer-contracts`, `AuthorizesCommercialCoordinator` |
| Montar e enviar lote | coordenador de **qualquer** setor | `BatchController`, modo `coordinator` do kiosk (só Comercial) |
| Analisar lote, digitar código da diretoria, cadastrar diretoria | coordenador da **Gerência** | `User::isManagementCoordinator()`, Gate `manage-freelancer-director` |
| Cancelar pelo painel | coordenador de qualquer setor | `User::isCoordinator()` |

> A doc completa ainda fala em `manage freelancers` e em "vínculo de setor" para Financeiro/Acompanhamento:
> isso é anterior ao catálogo (`sync_access_catalog`). **Vale o que está em `routes/web.php`**
> (`can:` + constante `P::FREELANCERS_*`). Permissão nova: só no `CATALOG`; o `PermissionCatalogSeeder` cria no deploy.

## Rotas

| Prefixo / nome | Lado | Observação |
|---|---|---|
| `freelancers.*`, `freelancer-functions.*` | web | CRUD + `import/template`, `import` (antes de `/{id}`) |
| `freelancer-services.index` | web, `servicos.listar` | Filtros na URL (`q`, `signature`, `issue`, `from/to`, `sort/dir`), 20/página |
| `freelancer-services.*` (create, `em-massa`, `em-massa/weekly-limit-code`, import, show, `document`, `signature/{party}`, cancel, `weekly-limit-code`) | web, `servicos.gerenciar` | Rotas fixas **antes** de `/{freelancerService}` |
| `freelancer-batches.*` (`lotes`, `lotes/aprovacao`, `lotes/{batch}/analise`, `/diretoria/email`, `/diretoria`) | web, `servicos.gerenciar` + cargo | Código da diretoria: `throttle:10,1` |
| `freelancer-validation.*` (`freelancer-services/validacao`) | web, Gate Comercial | Marca de abertura na sessão, uma validação por tela |
| `freelancer-director.*` (`freelancer-services/diretoria`) | web, Gate Gerência | Cadastro versionado + imagem da assinatura |
| `freelancer-services.tracking` | `freelancers.acompanhamento` | Só leitura |
| `freelancer-services.finance(.*)`, `freelancer-services.pay` | `freelancers.financeiro` | Lote é a unidade de pagamento |
| `/kiosk/*` → `kiosk.*` | **sessão própria** (matrícula + PIN) | Fora do `auth`; papel reconferido a cada requisição |
| `/api/telegram/*` | `api_token` | Bot: freelancer, funções, serviço, assinatura do freelancer |
| `GET /api/freelancer/dinners` | **aberta** | Lista da cozinha; sem CPF no payload |

Mudou rota → refaça o `route:cache` (o menu chama `route()` em toda tela).

## Ciclo de vida do contrato

```
registro (painel, massa, planilha, tablet, bot)
  → freelancer assina no tablet (congela redação + qualificação + chave PIX)
     [no dia: aditivo de horário, comissão de venda, jantar]
  → 08h do dia seguinte ao start_date: liberado
  → redação 1: coordenador assina no tablet | redação 2: coordenador VALIDA pela web (PIN)
  → lote (rascunho → sent) → gerência aprova/recusa item a item
  → e-mail à diretoria com 2 códigos → gerência digita o ditado
     (redação 2: a imagem do diretor entra no documento)
  → financeiro: baixa manual ou Pix Sicoob → freelancers:archive manda o PDF ao FTP
```

Saídas: **cancelado** (sem nenhuma assinatura, coordenador, painel), **falta** (`cancel_reason = no_show`,
operador do tablet, dia já chegou, sem assinatura, não-aditivo — `canBeMarkedNoShow()`), **excluído**
(sem assinatura). Assinado = não edita, não cancela; mudança de turno é **aditivo**.

Não há máquina de estados central: o estado é **derivado** das colunas (`*_signed_at`, `manager_*`,
`director_*`, `paid`, `amended_at`, `status_id`, `cancel_reason`). Leia pelos métodos do model
(`signatureLabel()`, `approvalLabel()`, `trackingStage()`), nunca recomponha à mão.

## Regras que costumam pegar

- **Preço é do servidor.** `price`, `total_hours`, `end_date` nunca são entrada. Blocos de 15 min
  arredondados **para baixo**; `end_time < start_time` = vira a meia-noite (no máx. +1 dia).
  Valor fixo: `pricing_mode = fixed` + `fixed_price` (até `MAX_FIXED_PRICE`); `fixed_price` sozinho é ignorado;
  `PUT` sem `pricing_mode` mantém o que já era.
- **Dia de referência é `start_date`** em tudo (liberação, limite, portaria), exceto o jantar (`dinner_date`).
- **Congelamento na 1ª assinatura:** `contract_version` (texto em `resources/views/freelancer/services/partials/contract/vN/`),
  `signed_snapshot` (qualificação inteira, não campo a campo) e `pix_key`. **Nunca edite uma `vN` em uso**:
  copie para `vN+1`, suba `CONTRACT_VERSION_CURRENT`/`CONTRACT_VERSIONS` e registre os sha256 em
  `tests/Unit/FreelancerContractVersionTest.php` (o teste quebra se um arquivo antigo mudar).
- **Documento num lugar só:** `partials/contract-document.blade.php` serve painel (`layout=print`),
  tablet (`layout=tablet`, via `GET /kiosk/service/{id}/document`) e PDF (`layout=pdf`,
  `FreelancerContractPdf`). O JS do kiosk não monta texto de contrato — não reintroduza.
- **Corridas na assinatura:** tablet reenvia `pix_key` e `contract_version`; divergiu → `409`
  (`pix_key_changed` / `contract_version_changed`) e a tela recarrega.
- **Redação decide o fluxo** (`usesDirectorSignature()`), não a data de hoje. A validação da redação 2
  grava nas mesmas colunas `coordinator_signed_*` (sem traço).
- **Liberação 08h:** duas implementações espelhadas — `hasBeenReleased()` (PHP) e `scopeReleased()` /
  `lastReleasedDate()` (SQL, só datas). Mudou uma, mude a outra; há teste cruzando as duas.
- **Aditivo de horário substitui** (base ganha `amended_at`, sai de lote/financeiro, mas segue sendo
  assinado); **comissão acresce** (nunca marca `amended_at`, uma por turno — `shiftHasCommission()` na
  gravação). Aditivo não conta no limite semanal nem tem prazo de assinatura.
- **Limite semanal:** 2 por semana **seg–dom** (`WEEKLY_LIMIT`); o 3º grava só com PIN do coordenador
  do Comercial ou código por e-mail (preso a freelancer + data, uso único, hash). Cancelado/falta não contam.
  No registro em massa, **um código só cobre o lote**: `issueForContracts()` grava uma linha por
  contrato acima do limite com o mesmo hash, e `consumeAll()` só queima se servir para todos. No trait,
  `authorizeCommercialCoordinatorFor($matricula, $segredo, $contratos)` é a forma em lote, e
  `authorizeCommercialCoordinator()` delega para ela com um contrato.
- **Selects montados por `x-for` precisam de `:selected`** na `<option>`: o `x-model` aplica o valor
  antes de as opções existirem, e a linha devolvida pelo servidor voltava desmarcada (bug do registro
  em massa).
- **Assinatura do diretor** é a imagem do cadastro de **quem recebeu o e-mail** (`freelancer_service_batches.freelancer_director_id`),
  em data URI, disco privado. Sem imagem, lote com redação 2 não vai à diretoria.
- **Códigos da diretoria** cifrados (não hash: o reenvio repete os mesmos), `hash_equals`, `$hidden`.
- **E-mails síncronos** (diretoria, código semanal): falha de SMTP aparece na hora; não enfileire.
- **Traço do freelancer/coordenador** fica no disco `public` mas é servido por rota (`signature/{party}`);
  não existe `storage:link` neste projeto.
- **Financeiro:** estado do lote é derivado (nunca grave espelho); com `SICOOB_PIX_ENABLED` o botão vira
  Pix de verdade e a baixa só entra quando o banco confirma.
- **Filtro de falhas (`issue`) e sua paginação são em memória** (`ServiceController::onlyWithIssue()`), de propósito.
- **Menu/layout:** use Gate, nunca método de model que consulta banco (testes com `User` mockado).

## Peças

| Arquivo | Papel |
|---|---|
| `app/Models/FreelancerService.php` | O coração: constantes (limites, horários, redações), regras de estado (`can*`, `*BlockReason`), escopos espelhados, rótulos (`kindLabel`, `signatureLabel`, `approvalLabel`, `trackingStage`) |
| `app/Services/FreelancerService.php` | Criação/edição (`withSchedule`), assinaturas (`signAsFreelancer`, `signAsCoordinator`, `validateAsCoordinator`), aditivo, comissão, falta, cancelamento, jantar, PIX, busca por função. Painel, tablet e API usam o mesmo |
| `app/Services/FreelancerBatchService.php` | Lote: rascunho, envio, análise, códigos e e-mail da diretoria, `applyDirectorPin()` |
| `app/Services/FreelancerDirectorService.php` + `Models/FreelancerDirector.php` | Cadastro da diretoria (linha nova a cada gravação; `current()`) |
| `app/Services/FreelancerContractPdf.php` / `FreelancerContractArchiver.php` | PDF (dompdf) e cópia no FTP com `archive_sha256`; comando `freelancers:archive` (hora em hora, `FREELANCER_ARCHIVE_ENABLED`) |
| `app/Services/MultiVendasSalesReport.php` | Apuração de vendas da comissão (conexão `mv_sqlsrv`) |
| `app/Services/WeeklyLimitCodeService.php` + `Http/Controllers/Concerns/AuthorizesCommercialCoordinator.php` | Liberação do limite semanal |
| `app/Services/CompanyService.php` | Portaria: janela de acesso do freelancer no Monitor de Acesso |
| `app/Http/Controllers/Freelancer/*` | `ServiceController`, `FreelancerController`, `FunctionController`, `BatchController`, `ValidationController`, `DirectorController`, `TrackingController`, `FinanceController`, `KioskController` (todo o tablet), `DinnerApiController` |
| `app/Http/Controllers/{FreelancerController,FreelancerServiceController,FunctionFreelancerController}` | API do bot do Telegram |
| `app/Imports/*` | Planilhas tudo-ou-nada (`SpreadsheetImport`, `FreelancerImport`, `FreelancerServiceImport`, `ImportValues`) |
| `app/Http/Requests/Concerns/ValidatesServiceSchedule.php` | Horários e `pricingRules()` — compartilhado por todas as entradas |
| `app/Jobs/SendFreelancerPixPayment.php`, `Services/Sicoob/*`, `Console/Commands/ReconcilePixPayments.php` | Pix (desligado; ver memória/entitlement) |
| Views | `resources/views/freelancer/` (`freelancers`, `functions`, `services`, `batches`, `validation`, `director`), `resources/views/kiosk/index.blade.php`, `emails/freelancer/` |
| `config/freelancers.php` | Arquivo FTP e código do limite semanal. Variáveis no `.env.example` |

## Testes

- Unit: `tests/Unit/Freelancer*Test.php` (limite, liberação, aditivo, comissão, jantar, prazo, portaria,
  PIX, redações/lacre dos arquivos, falta, acompanhamento). Feature: `tests/Feature/Freelancer*Test.php`
  (valor fixo, diretoria, financeiro por lote, Pix, arquivo FTP, foto, jantar API, busca por função, falta).
- **Sem `RefreshDatabase`.** Confira o isolamento SQLite do `phpunit.xml` antes. Rode só o módulo:
  `php -d memory_limit=1G vendor/bin/phpunit --filter Freelancer`.
- `User` fica preso à conexão `mysql`: regra com usuário → Unit com mock, ou Feature com
  `SharesSqliteWithUserConnection`.

## Ao adicionar algo

- Texto do contrato → nova redação `vN+1` (nunca editar a antiga) + hash no teste.
- Nova coluna de estado → atualizar os rótulos **e** o escopo SQL espelhado (o teste de acompanhamento cobra
  que cada contrato caia em uma fila só).
- Nova entrada de contrato → passar por `App\Services\FreelancerService` e `ValidatesServiceSchedule`
  (painel, massa, planilha, tablet e API não podem divergir).
- Nova tela → permissão no catálogo, Gate no menu, aba em `services/partials/tabs.blade.php` com a mesma
  condição da rota.
- Sempre: doc completa, este resumo, `.env.example`, `route:cache`.

## Lacre ICP-Brasil nos contratos (análise de 09/10/2026 — não implementado)

Tecnicamente viável reaproveitando o lacre do módulo Assinaturas (`SignaturePdfSealer` + `Pki\PdfSignatureWriter`):
o PDF do contrato é dompdf (xref clássica, sem criptografia), o mesmo tipo que já é lacrado em produção.
Pontos de decisão antes de implementar:

1. **O contrato não tem PDF guardado** — é re-renderizado a cada exibição. O arquivo lacrado precisa virar o
   canônico (disco privado + download na tela), senão o lacre só existe na cópia do FTP e cada re-render
   gera outro arquivo.
2. **Momento:** só com `isFinalDocument()` (as duas assinaturas). Lacrar antes invalida o lacre quando a
   assinatura que falta entrar.
3. **Não depender do FTP** (`FREELANCER_ARCHIVE_ENABLED` é opcional): lacrar e guardar numa etapa, enviar
   ao FTP noutra. Flag própria sugerida, mesmo `.pfx` (`config('signature.pades.*')`).
4. `SignaturePdfSealer::seal()` só usa o `SignatureDocument` para o motivo — aceitar um código (`C{id}`).
5. O lacre prova integridade e emissão pelo clube a partir do momento do lacre; **não** muda a natureza das
   assinaturas (traço com PIN do operador; imagem do diretor por código). Sem selo ICP-Brasil. Não exige
   redação nova (fica fora do texto). Sem ACT, mesma pendência do módulo Assinaturas (A1 vence 21/07/2027).
6. Histórico já arquivado: lacrar retroativamente só prova integridade a partir da data do lacre, e o
   re-render pode não bater com o `archive_sha256` antigo.

## Aplicação externa

Esse módulo se comunica com uma aplicação externa, cada modificação deve acompanhar de um prompt para adaptação da aplicação externa.