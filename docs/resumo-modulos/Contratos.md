# Contratos — resumo para quem vai mexer

Módulo próprio no menu desde 04/11/2026 (antes era Assinaturas → Contratos). Doc completa:
[docs/funcionalidades/contratos.md](../funcionalidades/contratos.md). Guia do usuário:
[docs/guias/contratos.md](../guias/contratos.md) (é dele que o HAL (IA) tira as respostas).

## Em uma frase

O setor redige o contrato em cláusulas, a Gerência valida (no computador ou no tablet do balcão) e, aprovado,
o contrato vira um documento de assinatura **em rascunho** do motor de Assinaturas, assinado na aba
**Contratos › Assinaturas** com o mesmo processo (tablet, link remoto, gov.br, diretoria).

## Separação de Assinaturas (04/11/2026)

- **Separou o produto, não o motor.** Menu, permissões, URLs, guia e resumo são de Contratos; classes
  (`App\Services\Signature\Contracts\*`, `Signature\Contract*Controller`, `SignatureContract*`), tabelas
  `signature_contract*`, config `signature.contracts.*`/`SIGNATURE_CONTRACT_*` e views
  `resources/views/signature/contracts/` continuam com o prefixo de Assinaturas. Não renomeie: não há ganho e
  cria migration em produção.
- **Compartilhado com Assinaturas:** papel timbrado (`SignatureLayout`, tela em Assinaturas → Modelos),
  Diretoria (o presidente = titular de `SIGNATURE_CONTRACT_PRESIDENT_AREA`), o documento de assinatura (o
  contrato gera um `SignatureDocument` de modelo `single_use`) e o tablet do balcão (`/assinatura/kiosk`, onde
  fica o botão da Gerência).
- **Menu** (`app/View/Navigation.php`, grupo "Contratos", `glyph` `doc`): Contratos (`contracts.index`,
  `assinatura.contratos`), Assinaturas (`contract-signatures.index`, `contratos.assinatura`), Biblioteca
  (`contract-library.index`, `acessar-biblioteca-contratos`), Prestadores (`contract-providers.index`,
  `assinatura.contratos`, desde 05/11/2026). Só permissão de catálogo no menu (a Gerência tem
  acesso total): Gate de cargo consultaria o banco em toda tela.
- **Permissões** (grupo "Contratos" no catálogo): `assinatura.contratos` (chave mantida de propósito: os setores
  já configurados não se perdem), `contratos.biblioteca` (cadastrar/editar/excluir na biblioteca e salvar
  modelo — `SignatureContractPolicy::manageLibrary`), `contratos.assinatura` (aba Assinaturas —
  `SignatureDocumentPolicy::conductsContract`: setor do contrato ou Gerência).
- **Rotas:** `/contratos` (`contracts.*`), `/contratos/biblioteca` (`contract-library.*`),
  `/contratos/assinaturas` (`contract-signatures.*`); `/assinatura/contratos/*` redireciona (301,
  `contracts.legacy`). As rotas de UM documento são a closure `$rotasDoDocumento` registrada em
  `signature-documents.*` e em `contract-signatures.*` (+ `EnsureContractDocument`).
- **`App\Support\DocumentRoutes`:** toda view de documento (`resources/views/signature/documents/**`) e os
  redirecionamentos dos controllers de documento pedem a rota por ele. **Rota nova de documento: registre na
  closure e use `DocumentRoutes::route()`**, senão a aba de Contratos leva o usuário para Assinaturas (onde ele
  pode não ter permissão).
- **Tablet da Gerência:** lê, comenta, decide, pede a revisão do Claude e edita o título e o texto das
  cláusulas (`ContractKioskService::applyEdits`, só cláusulas existentes, pelo uid).
- **Visto da Gerência na aprovação (04/11/2026):** obrigatório para aprovar (computador e tablet, quadro
  `partials/initials-pad`), validado e gravado por `ContractApprovalInitials`, guardado na versão aprovada e
  copiado para o documento (`signature_documents.approval_initials_path`/`_label`). O renderer desenha a caixa
  "Visto — Gerência" primeiro na faixa do visto de todas as páginas, já preenchida no original; a faixa existe
  também com o visto dos signatários dispensado (gov.br).
- **Revisão jurídica (04/11/2026):** pedida pela Gerência (computador ou tablet), a revisão do Claude traz
  `legal` = risco baixo/médio/alto, resumo e fraquezas; risco alto força `seek_lawyer` e a tela orienta procurar
  um jurídico especializado. Só orienta (não trava aprovar). `ContractAiReviewer::LEGAL_SYSTEM`/`legal()`,
  `SignatureContractAiReview::legalReview()/seeksLawyer()`.
- **Revisão pelo Claude:** em todo pedido dá para anexar trechos de texto (`extras`, até 5 × 12.000
  caracteres) que vão como material de referência depois da minuta e ficam na revisão.
- Nenhuma API externa: nenhum prompt para outro agente.

## Cabeçalho fixo e aditivos (05/11/2026)

Detalhes em [contratos.md › Cabeçalho fixo e aditivos](../funcionalidades/contratos.md#cabeçalho-fixo-e-aditivos-05112026).

- Cabeçalho FIXO "CONTRATO DE PRESTAÇÃO DE SERVIÇO QUE ENTRE SI FAZEM: CLUBE DOS FUNCIONÁRIOS DA CSN E <PRESTADOR>"
  (`headingText`; o título é só nome interno); aditivo "1º TERMO ADITIVO AO CONTRATO CTL - … DE PRESTAÇÃO…". O PDF
  de contrato não imprime o título do documento; o Word não leva título no cabeçalho de texto.
- Aditivo só de contrato **assinado** (`isSigned`), pelo botão ou pelo Novo contrato (`kind=addendum`). Base =
  último aditivo assinado ou o principal (`addendumBase`). Herda partes, CNPJ, variáveis e só o signatário da
  contratada. `[[original:chave]]` no texto. `content.addendum` é refeito por `withAddendumContext` a cada
  gravação — quem grava `content` por fora tem de passar por ele.
- Bloco de assinaturas: nome em MAIÚSCULAS e só o cargo (diretor = área da Diretoria; demais = `caption`), sem
  o papel. `documentSigners()['cargo']` (prévia/Word) e `SignatureDocumentRenderer::contractCaptions` (PDF e visto).
  Duas colunas em todo lugar (PDF `sig-grid`, prévia `.ct-sigs`, Word `signatureGrid` = tabela sem borda com
  `cantSplit`); no Word, linha + nome + cargo também com `keepNext` para não se separarem entre folhas.
- Marcar/desmarcar como aditivo antes da aprovação (`canChangeKind`, policy `changeKind`, rotas
  `contracts.link-addendum`/`unlink-addendum`); marcar só completa (`completeFrom`).
- Cancelar até a assinatura terminar (`canBeCanceled`; cancela o documento junto) e arquivar qualquer um
  (`archived_at`, só tira das abas; aba `arquivados`), motivo obrigatório nos dois; policies `cancel`/`archive`
  (setor e Gerência); rotas `contracts.cancel`/`archive`/`unarchive`; e-mail `SignatureContractCanceledMail`.
  Toda consulta nova da lista precisa de `archived(false)`.
- Vigência: `content.validity` {months, until}; ao ir para assinado (`SignatureStateMachine::documentTo` →
  `ContractService::documentSigned`) grava `signed_at`/`expires_on`; `setValidity` para os já assinados
  (`contracts.validity`). Aviso a 60/30/15 dias (`contratos:avisar-vencimento`, diário 08:00, `expiry_alerts`),
  aba `vencendo`.
- Notificações (sino + navegador, como os avisos): `ContractNotifier` → `ContractMovement` (database). Consulta
  `users` (mysql): nos testes é um espião (`CreatesSignatureSchema`). Movimentação nova = chame
  `$this->notifier->notify(...)` no ContractService.

## Revisões da apresentação (05/11/2026)

Detalhes em [contratos.md › Revisões da apresentação](../funcionalidades/contratos.md#revisões-da-apresentação-05112026).

- **Variáveis:** campo "Preenchimento" (não "Valor"); `ContractVariables::GUESS`/`guessType()` (tipo pelo nome,
  mesmas regex no editor) e `COMMON` (variáveis comuns); `ContractRenderer::variableUsage()` (onde é usada +
  `[[marcador]]` sem variável, que o editor cria sozinho).
- **Salvar automático:** `content_revision` + `ContractService::save(..., $expectedRevision, $force)` →
  `ContractConflictException`; rota `contracts.autosave` (JSON, 409 `conflict`/`reload`); tablet idem pelo
  `quiosque.gerencia.update`. Mexeu no que grava `content`? Suba a revisão (passe pelo `save`).
- **Telas de duas colunas:** `split-lg`/`split-xl` + `split-pane` (app.css). Editor com abas Prévia | Conferência |
  Claude; a prévia rola até a cláusula em edição (`rolaPrevia`). Revisão do Claude em `<details data-ai-result>`.
- **Prestadores** (`/contratos/prestadores`, `ContractProviderController`, `manageProviders` = quem acessa
  Contratos): mesma tabela de contratadas, `active` (inativa, não exclui), documento único.
- **Recusa na assinatura:** presidente ou contratada recusa (tablet, link, ou o presidente na tela —
  `contracts.refuse-signature`) → contrato devolvido com o motivo, documento encerrado, aprovado de novo gera
  documento novo. Testemunha/fiador só encerram o documento (gera-se outro). `ContractService::signatureRefused`
  (chamado pelo `SignatureCaptureService::refuse`), `refuseByPresident`, `refusalRole`, `presidentSignerFor`.
  O presidente vê o que aguarda a assinatura dele (`view`), mas `manage` não.
- **Andamento:** aba "Em assinatura" (`scopeInSigning`), coluna Assinatura e cartão na tela
  (`signingProgress()`, `partials/signing-progress`).
- **Editar modelo (06/11/2026):** Biblioteca › Modelos › **Editar** (`contract-library.models.edit|update|preview|
  clauses`, `ContractLibraryController`, `manageLibrary` + setor visível). View `signature/contracts/model-edit`,
  que reaproveita as partials do editor com `'modelo' => true` (party: só papel/tipo/termo; signer: só papel,
  cargo e diretor; variable: sem "Preenchimento") e o `editor-js` com `urls.autosave = null` (sem salvar
  automático). Grava por `ContractService::updateModel` → `modelOnly()` (a mesma régua do `saveAsModel`, que
  descarta dados de pessoa, valores e a data fixa da vigência). Conferência: `ContractRenderer::problems($c, true)`
  (não cobra contratada, preenchimento, signatários nem presidente). Contrato já feito não muda (tem cópia).
  Coluna `updated_by_name` (migration `2026_11_06_100000`).

## Detalhes (desde 27/10/2026)

Contrato redigido no Lara em cláusulas, validado pela Gerência e, aprovado, transformado em documento de
assinatura **em rascunho** (sem congelar). Doc completa: [docs/funcionalidades/contratos.md](../funcionalidades/contratos.md).
Guia do usuário: [docs/guias/contratos.md](../guias/contratos.md) (e a seção 13 do guia ilustrado de Assinaturas).

- **Decisões do usuário:** qualquer usuário com `assinatura.contratos` redige, no próprio setor (cada setor só
  vê os seus); **só a Gerência valida** (setor Gerência, qualquer papel; pode validar o próprio; pode ajustar
  direto); e-mail de "para validar" só ao **coordenador** da Gerência; número `CTL - X/AA` único para todos os
  setores; contratante = um dos 2 CNPJs do clube, representado **sempre pelo presidente**; contratada digitada
  (sem Questor) e guardada; data = data automática da assinatura; visto em todas as páginas; anexos seguem;
  biblioteca e modelos por setor; aditivos; exporta Word.
- **Peças:** `app/Services/Signature/Contracts/` — `ContractContent` (forma e normalização do `content`; única
  entrada), `ContractRenderer` (FONTE ÚNICA do texto: `blocks()` → prévia, documento e Word; numeração,
  `[[variável]]`, `[[ref:uid]]`, `problems()`), `ContractVariables` (extenso), `ContractClub` (CNPJs e
  presidente = titular da área `SIGNATURE_CONTRACT_PRESIDENT_AREA`), `ContractService` (ciclo), 
  `ContractDocumentGenerator` (modelo `single_use` + `SignatureDocumentService::create`), `ContractDiff`,
  `ContractDocxExporter` (ZipArchive, sem dependência), `ContractRecipients`. `App\Support\NumeroPorExtenso`.
  Controllers `Signature\ContractController` e `ContractLibraryController`; `SignatureContractPolicy`; 8 models
  `SignatureContract*`; views em `resources/views/signature/contracts/`.
- **Criação em passos (04/11/2026):** Novo contrato = 1. Contrato, 2. Contratado (obrigatório; vira a primeira
  contratada com o termo do modelo e o signatário da contratada — `ContractService::withMainParty`); o editor é em
  passos livres (Contratado, Variáveis, Cláusulas, Quem assina) com o partial `signature.documents.partials.steps`
  (`data-step-start`, `data-step-current`, evento `step:shown`).
- **Modelos-base (04/11/2026):** `ContractBaseModels` (11 modelos — o 11º, atração musical, em 06/11/2026 —
  e 9 cláusulas avulsas tirados dos PDFs do clube) gravados por `php artisan contratos:modelos-base --setor=…` (`ImportContractBaseModels`, idempotente,
  não roda no deploy). Remissão no catálogo: `{ref:chave.N}`. Teste: `SignatureContractBaseModelsTest`.
- **Numeração não se guarda:** é calculada da posição. Remissão é `[[ref:uid]]` (uid de cláusula/item). Ao copiar
  cláusula (biblioteca, modelo, aditivo), `ContractContent::freshUids` dá uid novo e troca as remissões internas.
- **Documento gerado:** uma parte por signatário (`p1…`, marcadores `[[assinatura:pN]]` vizinhos), presidente
  primeiro (diretor), `requires_initials`, data `[[data_da_assinatura]]` (`DATE_SIGNING_LONG`), `created_by` = quem
  redigiu. Diretor sem mandato vira aviso e fica de fora (o `freeze` cobra a parte). Gerado DEPOIS do commit da
  aprovação; falhou ou foi cancelado → botão "Gerar documento de assinatura".
- **Reabrir** só enquanto o documento não foi congelado (cancela o rascunho dele; o número fica).
- **Menu** usa só permissões do catálogo (`P::ASSINATURA_CONTRATOS`, `P::CONTRATOS_ASSINATURA`, `acessar-biblioteca-contratos`; a Gerência tem acesso total). Nada de Gate de cargo no menu.
- `User::isManagementMember()` e `User::sectorIds()` (memorizados) — mocke nos testes.
- **CSS:** as telas usam classes Tailwind novas; em dev, `npm run build` depois de puxar (sem isso o editor sai em
  colunas de 1/5). O deploy já roda o build.
- Migration `2026_10_29_100000`. Config `signature.contracts.*` / `SIGNATURE_CONTRACT_*`. Testes:
  `SignatureContractTest`, `Unit\NumeroPorExtensoTest`, esquema `CreatesContractSchema`.
- **Validação no tablet do balcão** (decisão do usuário): botão "Validação de contratos (Gerência)" na espera do
  quiosque (`CFG.gerencia`, só no balcão) → página própria `/assinatura/kiosk/gerencia` (`quiosque/gerencia.blade.php`).
  Matrícula + PIN, cookie `lara_management`, `ContractKioskService` (desenho do `DirectorKioskService`: hash no cache,
  limite por matrícula, setor Gerência reconferido a cada chamada). Lê, comenta tocando na cláusula, aprova/devolve
  pelo `ContractService`; desde 04/11/2026 também edita o texto (`update`) e pede a revisão do Claude (`ai-review`). Rotas `quiosque.gerencia.*`. Testes: `SignatureContractKioskTest`.
- **Fora da revisão interna (30/10/2026):** `SignatureDocument::isContract()` (relação `contract`, por
  `signature_document_id`) em `skipsReview()`. Por isso `signature_contracts` entrou no `CreatesSignatureSchema`.
- **Busca (30/10/2026):** `SignatureContract::scopeSearch` e `SignatureDocument::scopeSearch` acham número em
  qualquer grafia (`splitNumberSearch`), parte e CNPJ (`parties_search`, mantido pelo `saving`). Filtro de
  documentos `?modelo=contratos`.
- **Revisão automática pelo Claude** (Gerência, 27/10/2026; quem redige, 30/10/2026, policy `aiReview` = `update`;
  no editor, "Salvar e analisar" = `next=ai-review`): cartão na tela do contrato manda o texto ao
  Claude Haiku 5.5 (`ContractAiReviewer` → `App\Services\Claude\ClaudeClient`, SDK `anthropic-ai/sdk`) e guarda
  veredito, resumo e apontamentos por cláusula em `signature_contract_ai_reviews` (mesmo texto e mesma escolha de
  trechos = mesma revisão, pelo `sha256` + `withheld`). Desde 28/10, trecho (parágrafo da qualificação, considerando,
  cláusula, assinaturas) com valores, CPF, documentos ou endereço (`SENSITIVE`) fica de fora, a menos que o gerente
  marque (`include[]`); a revisão grava em `withheld` o que não foi. "Levar para a observação" preenche o formulário
  de devolução. Só orienta. Sem `ANTHROPIC_API_KEY`, some. Rota `contracts.ai-review`, policy `aiReview`.
  Migrations `2026_10_29_110000` e `_130000`. Testes: `SignatureContractAiReviewTest`.
  Ver [claude-api.md](../funcionalidades/claude-api.md).
- Não mexe em API externa nossa: nenhum prompt para outro agente (a Anthropic é serviço de terceiro).
- **Word no papel timbrado (04/11/2026):** `ContractDocxExporter` passa `pages:` ao `DocxWriter::package()`, montado
  por `App\Services\Signature\SignatureLayoutDocx` a partir do `SignatureLayout::current()` (o VIGENTE no download;
  o Word é cópia de trabalho). Tamanhos de `SignaturePageGeometry` (os mesmos do PDF), largura limitada à área de
  texto do Word; "de borda a borda" vira imagem ancorada na borda do papel (`DocxWriter::picture(..., 'top'|'bottom')`).
  Sem imagem de cabeçalho, sai o cabeçalho de texto padrão (nome do Clube + número/título). `DocxWriter` ganhou
  `picture()`, `run()` com `color`/`sz` e cabeçalho/rodapé no pacote (`header1.xml`, `footer1.xml`, `word/media/`).
  O `DocxTemplateExporter` (modelos) segue sem papel timbrado. O PDF de assinatura já usava o papel timbrado (preso no
  congelamento). Testes: `SignatureContractTest::test_word_sai_no_papel_timbrado` e `..._sem_papel_timbrado_...`.
