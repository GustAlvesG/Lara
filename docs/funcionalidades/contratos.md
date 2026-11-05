# Contratos (módulo Contratos)

Desde 27/10/2026. Branch `contratos`. **Módulo próprio no menu desde 04/11/2026** (antes: Assinaturas →
Contratos) — ver [Módulo próprio](#módulo-próprio-04112026).

O contrato é **redigido no Lara em cláusulas**, **validado pela Gerência no Lara** e, aprovado, vira um
**documento de assinatura em rascunho** do módulo Assinaturas. Antes, a compradora imprimia o contrato,
o gerente devolvia com observações à mão e ela redigitava, várias vezes. Os dois contratos reais usados
como referência (CT 175/26, prestação de serviços; CT 135.26, locação) tinham os erros que o auxiliar
evita: remissão para o item errado ("cláusula penal do item 7.1", que era o 6.1), termo digitado errado
("CONTRADATA", "CONTRATADO", "O LOCATÁRIA") e nome de outro fornecedor esquecido no texto.

Guia do usuário: [docs/guias/contratos.md](../guias/contratos.md) (a seção 13 do guia ilustrado de Assinaturas,
`resources/views/signature/guide/content.blade.php`, segue com o passo a passo).
Resumo para agentes: [docs/resumo-modulos/Contratos.md](../resumo-modulos/Contratos.md).

## Módulo próprio (04/11/2026)

Pedido do gerente: separar Contratos de Assinaturas, que seguem partilhando configuração. Decisões do usuário:

| Tema | Decisão |
|---|---|
| Menu | Grupo **Contratos** (ícone de documento, `glyph` `doc`) com três abas: **Contratos**, **Assinaturas** e **Biblioteca**. O item saiu de Assinaturas. |
| Assinatura | Os documentos produzidos pelos contratos têm aba própria de assinatura **dentro de Contratos**, com **o mesmo processo** de Assinaturas (mesmas telas e controllers, rotas novas). |
| Biblioteca | Permissão **separada** já (`contratos.biblioteca`) para cadastrar, editar e excluir. |
| Configuração | Papel timbrado, Diretoria (presidente), CNPJs e o tablet do balcão seguem os de Assinaturas. |

Como foi feito (o "motor" não mudou: classes `Signature\Contract*`, tabelas `signature_contract*`, config
`signature.contracts.*` e `SIGNATURE_CONTRACT_*` ficam onde estavam):

- **URLs e rotas:** `/contratos` (`contracts.*`), `/contratos/biblioteca` (`contract-library.*`),
  `/contratos/assinaturas` (`contract-signatures.*`). Os antigos `signature-contracts.*` e
  `signature-contract-library.*` foram renomeados. `/assinatura/contratos/{caminho?}` (GET) redireciona com 301
  para `/contratos/{caminho}`, com a query (rota `contracts.legacy`): há e-mails com o link antigo.
- **Aba Assinaturas:** as rotas de UM documento foram para uma closure (`$rotasDoDocumento` em `routes/web.php`)
  registrada duas vezes — em `signature-documents.*` e em `contract-signatures.*` (esta com o middleware
  `EnsureContractDocument`: documento que não veio de contrato dá 404). A lista é
  `DocumentController::contractIndex` (mesma view `signature.documents.index` com `$contractsTab`: só
  documentos de contrato do setor — `SignatureContract::visibleTo` —, sem "Novo documento" nem filtro de modelo).
  As views de documento e os redirecionamentos dos controllers (Document, Attachment, Govbr, RemoteLink, Review)
  pedem a rota por `App\Support\DocumentRoutes::route('show', …)`: dentro da aba de Contratos o prefixo é
  `contract-signatures.`, fora dela (Assinaturas, e-mail) é `signature-documents.`. Rotas que só existem em
  Assinaturas (novo documento, PDF pronto, busca de sócios) seguem fixas. A tela do contrato liga o documento
  pela aba de Contratos.
- **Permissões** (catálogo, grupo **Contratos** na tela Setores; o seed cria no deploy, sem setor):
  `assinatura.contratos` (a CHAVE ficou a mesma para não perder os setores configurados; rótulo "Redigir os
  contratos do próprio setor"), `contratos.biblioteca` (nova) e `contratos.assinatura` (nova). Habilidade
  composta `acessar-biblioteca-contratos` (= redigir OU biblioteca) para o menu. `contratos.assinatura`
  também abre o guia ilustrado de Assinaturas (`acessar-guia-assinatura`).
- **`SignatureDocumentPolicy`:** quem tem `contratos.assinatura` e é do setor do contrato (ou da Gerência)
  conduz a assinatura do documento do contrato como o atendente de Assinaturas (`view`, `update`, `release`,
  `checkGovbr`, `attach`, `checkAnswers`, `cancel`, `hide`, `download`), sem precisar de
  `assinatura.documentos`. Não é o `view` do contrato (aquele exige também `assinatura.contratos`).
  Evidências e revisão interna seguem nas permissões de Assinaturas. `viewContractSignatures` abre a lista
  (`contratos.assinatura` ou `assinatura.documentos`).
- **Biblioteca:** `SignatureContractPolicy::viewLibrary` (redigir, biblioteca ou Gerência) e `manageLibrary`
  (`contratos.biblioteca`): `ContractLibraryController::store/update/destroy/destroyModel` e
  `ContractController::saveModel`. "Salvar na biblioteca" (editor), "Salvar como modelo do setor" (tela do
  contrato) e o formulário/Remover da tela da biblioteca só aparecem com ela. Usar a biblioteca no editor
  (`contracts.library`) continua de quem redige.
- **Atenção no deploy:** quem hoje redige perde o cadastro na biblioteca até alguém dar
  `contratos.biblioteca` ao setor; e a aba Assinaturas só aparece para quem receber `contratos.assinatura`.
  Quem tem `assinatura.documentos` continua assinando os contratos por Assinaturas → Documentos.
- Testes: `SignatureContractTest::test_aba_assinaturas_*`, `test_endereco_antigo_de_contratos_redireciona`,
  `test_biblioteca_so_consulta_sem_a_permissao_da_biblioteca`.

## Decisões do usuário (27/10/2026)

| Tema | Decisão |
|---|---|
| Quem redige | Qualquer usuário com `assinatura.contratos`, no **próprio setor**. Cada setor só vê os seus contratos. |
| Quem valida | **Só a Gerência** (qualquer membro do setor Gerência), numa etapa só. Pode ajustar o texto direto ou só devolver. **Pode validar o que ela mesma redigiu.** |
| Aviso | E-mail "contrato para validar" só ao **coordenador** da Gerência; devolução e aprovação, a quem redigiu. |
| Número | Gerado pelo Lara, **uma sequência só para todos os setores**: `CTL - X/AA`. |
| Contratante | Só os dois CNPJs do clube (sede 0001-16 e PET 0005-40); representante **sempre o presidente**. |
| Contratada | Digitada (**sem Questor**) e guardada no Lara para reaproveitar. |
| Signatários | Fornecedor, diretoria e testemunhas, declarados por quem redige ou pela Gerência. |
| Data | A do fecho é a **data automática da assinatura**. |
| Visto | Rubrica em **todas as páginas**. |
| Anexos | Seguem junto para a assinatura. |
| Aprovado | Gera o documento de assinatura **em rascunho, sem congelar** (corrigido pelo usuário). |
| Biblioteca e modelos | Cláusulas e modelos completos, **por setor**; qualquer usuário do módulo cria (desde 04/11/2026, cadastrar pede `contratos.biblioteca`). |
| Aditivo | Nasce do contrato original, herdando partes e variáveis. |
| Word | Só a plataforma para redigir (sem importar Word), mas **exporta Word** (para o fornecedor revisar). |
| Tablet | A Gerência valida também no **tablet do balcão**, com matrícula + PIN; lá lê, comenta e decide (e, desde 04/11/2026, pede a revisão do Claude e edita o texto). |

Decisões tomadas na implementação (não perguntadas — revisar se o usuário discordar):

- O número sai **na aprovação**, não na criação: a sequência não fica com buracos de contrato desistido.
  Ano com 2 dígitos (`CTL - 12/26`), como os contratos atuais. O aditivo repete o número do contrato com
  `A1`, `A2`… (como o "CT-09-22 A1" do exemplo).
- O **presidente** é o titular vigente da área da diretoria chamada `SIGNATURE_CONTRACT_PRESIDENT_AREA`
  (padrão "Presidência") em Assinaturas → Diretoria. Sem titular, o contrato não vai à Gerência.
- O **cadastro de contratadas** é do Lara inteiro (não por setor): é o mesmo fornecedor para todos.
- A Gerência enxerga a biblioteca e os modelos de todos os setores, como enxerga os contratos.

## Fluxo

**Criação em passos (04/11/2026, pedido do usuário):** "Crio o contrato, digo o contratado, depois vou para as
cláusulas, variáveis e afins". `contracts.create` usa o mesmo `signature.documents.partials.steps` do documento
comum: **1. Contrato** (setor, modelo, título) e **2. Contratado** (`party[kind|name|document|address|details|
rep_name|rep_cpf|email|phone|gender]`, com a busca de `contracts.parties`). O `store` exige o contratado (CNPJ/CPF
conferidos por `SignatureFieldTypes`; representante e CPF dele só na empresa — `exclude_if` na pessoa física) e
`ContractService::create(..., $party)` o põe como a primeira contratada **mantendo o termo do modelo**
(`withMainParty`), preenche o signatário `contratada` vago (empresa: representante; pessoa física: a própria) e grava
no cadastro (`rememberParties`). Redireciona para `contracts.edit?passo=1`. O editor também é em passos **livres**
(`data-steps-free`): Contratado (dados gerais + partes), Variáveis, Cláusulas (considerandos + cláusulas) e Quem
assina. O partial de passos ganhou `data-step-start` (passo inicial) e `input[data-step-current]` (o `update`
redireciona com `passo`, e a tela volta no mesmo), e dispara `step:shown` no passo aberto (o editor remede as caixas
`data-autogrow`, que medem zero escondidas). Aditivo continua nascendo do original, sem o passo.

```
rascunho ─enviar→ em validação ─devolver→ devolvido ─enviar→ em validação …
                               └aprovar→ aprovado (número + documento de assinatura em RASCUNHO)
aprovado ─presidente ou contratada recusa a assinatura→ devolvido (documento encerrado; aprovado de novo, documento novo)
aprovado ─reabrir→ rascunho   (só enquanto o documento não foi congelado; o rascunho do documento é cancelado)
rascunho/devolvido/em validação ─cancelar→ cancelado
aprovado ─criar aditivo→ novo contrato (kind = addendum) em rascunho
```

- **Enviar** (`ContractService::submit`): trava se `ContractRenderer::problems()` tem `error`; congela a
  versão (`signature_contract_versions`, retrato de `content`) e avisa o coordenador.
- **Devolver** (`returnToAuthor`): pede ao menos uma observação (geral ou de cláusula, por `uid`).
  Observação de cláusula que não existe vira geral. Quem redige vê cada observação na cláusula do editor
  e marca "Resolvida".
- **Ajuste da Gerência**: em validação, a Gerência pode editar (`policy update`). Na decisão, se o
  conteúdo difere da versão enviada, nasce uma versão `by_reviewer` e a anterior fica `superseded`.
- **Aprovar** (`approve`): confere os problemas de novo, dá o número (com `lockForUpdate`), grava a versão
  aprovada e, **depois do commit**, gera o documento (`generateDocument`). Falha na geração não desfaz a
  aprovação: a tela oferece "Gerar documento de assinatura" de novo. O mesmo botão aparece se o
  documento for cancelado no módulo de assinatura.
- **O que mudou**: a tela compara a cópia atual com a última versão **devolvida** (`ContractDiff`):
  cláusulas e itens casados por `uid` (mover não é apagar+criar), diferença palavra a palavra, mais
  partes, variáveis, considerandos e signatários.

## Revisões da apresentação (05/11/2026)

Pedidos do usuário depois de apresentar o módulo. Decisões dele entre parênteses.

### Variáveis
- "Valor" virou **Preenchimento** no editor (os usuários liam "valor" como dinheiro). O tipo "Valor em reais"
  continua (é dinheiro mesmo). Mensagens da Conferência: "não foi preenchida" / "sem preenchimento".
- **Variável comum**: `ContractVariables::COMMON` (rótulo + tipo), escolhida no select do passo Variáveis.
- **Tipo pelo nome**: `ContractVariables::GUESS` (regex por tipo, sobre o nome sem acento e em minúsculas;
  a primeira que casa vale) e `guessType()`. As mesmas expressões vão para o editor (`CT.guess`), que só palpita
  enquanto o select não foi mexido (`data-touched`; variável já salva nasce "mexida").
- **Marcador vira variável**: `ContractRenderer::variableUsage()` devolve `usage` (chave → "Cláusula 3",
  "Considerando II") e `unknown` (`[[marcador]]` que não é variável nem dado pronto, com chave, rótulo e tipo
  do palpite). A rota `preview` manda os dois e as `variables` (chaves na ordem); o editor cria a linha
  (`data-auto`) e, se ninguém preencheu e o texto deixou de usar, tira de novo.
- **Chave estável**: a linha nova recebe a chave do servidor (prévia ou salvar automático) quando o nome para de
  mudar (`guardaChaves`, pula a linha com foco) — renomear depois não quebra o `[[marcador]]`.
- "Usada em …" / "Ainda não usada no texto" em cada variável e o quadro **Falta preencher**.

### Salvar automático (formulário inteiro; também no tablet da Gerência)
- `signature_contracts.content_revision` (sobe a cada gravação do texto), `content_saved_by_name`,
  `content_saved_at` — migration `2026_11_05_100000`.
- `ContractService::save(..., ?int $expectedRevision, bool $force)`: lê a linha com `lockForUpdate`; revisão
  diferente → `ContractConflictException` (quem e quando). Situação mudou (ex.: enviado) → `ContractException`.
- Rota `PUT contracts.autosave` (`/contratos/{id}/automatico`, JSON): 200 com `revision`, `saved_at`,
  `variables`; 409 com `conflict` (alguém salvou antes) ou `reload` (não dá mais para editar). O formulário
  normal leva `revision` (input `data-revision`) e confere também.
- Editor: 3 s depois da última alteração; "Salvando…", "Salvo automaticamente às HH:MM". No conflito para de
  salvar e mostra o aviso com **Recarregar** e **Gravar o meu texto por cima** (`force`) — decisão do usuário:
  avisar "alguém salvou antes de você". Um envio do formulário com salvar em andamento espera ele terminar
  (senão a própria pessoa causaria o conflito).
- Tablet (`quiosque.gerencia.update`): aceita `revision`/`force`/`auto`; `editable()` manda `revision`; a folha
  salva sozinha 3 s depois e ao fechar; conflito mostra **Gravar o meu texto por cima**.

### Telas
- **Duas colunas rolando cada uma por si** (todas as telas de duas colunas, decisão do usuário):
  classes `split-lg`/`split-xl` (na grade) e `split-pane` (em cada coluna), em `resources/css/app.css`
  (`--split-top` = altura do cabeçalho). Aplicadas no editor, na tela do contrato, na biblioteca, na tela do
  documento de assinatura e na do modelo de Assinaturas.
- **Editor: abas ao lado** — Prévia (primeira), Conferência (contador de problemas) e Claude. Volta na
  Conferência quando o envio é recusado e no Claude quando a URL tem `#revisao-ia`.
- **Prévia acompanha a cláusula**: focar ou digitar numa cláusula rola a coluna até `[data-clause=uid]` e marca
  (`.ct-active`, destaque `.ct-flash` ao trocar de cláusula); depois de cada prévia, rola de novo.
- **Revisão do Claude colapsável** (`partials/ai-review`, `<details data-ai-result>`): fechada mostra quantos
  apontamentos, o risco jurídico e se o texto mudou. `$aberto` abre de saída (editor); `#revisao-ia` abre.

### Prestadores (Contratos → Prestadores)
- `ContractProviderController`, rotas `contract-providers.*` (`/contratos/prestadores`), views em
  `signature/contracts/providers/`. Mesma tabela `signature_contract_parties`, com `active` e `created_by_name`.
- Quem cuida: quem acessa Contratos (`SignatureContractPolicy::manageProviders` = `assinatura.contratos` ou
  Gerência) — sem permissão nova (decisão do usuário). No menu: `P::ASSINATURA_CONTRATOS`.
- Não se exclui, só se inativa (decisão do usuário). Inativo some de `contracts.parties`; salvar contrato não
  reativa (`rememberParties` usa `firstOrNew` e não toca `active`). Documento único (só caracteres).
- A edição lista os contratos que o usam (`parties_search` LIKE documento), filtrados por `can('view')`.

### Recusa na assinatura → volta para revisão
- Só **presidente** e **contratada** devolvem (decisão do usuário): `ContractService::refusalRole()` casa o
  `party` do signatário com `ContractRenderer::documentSigners()` (presidente = `president`; ou diretor da área
  `SIGNATURE_CONTRACT_PRESIDENT_AREA`).
- Tablet e link remoto: `SignatureCaptureService::refuse` chama `ContractService::signatureRefused()` depois do
  commit (falha só vai ao log). Presidente/contratada: o contrato vira **devolvido**, `signature_document_id`
  e a aprovação são limpos, o motivo vira observação geral ("Recusado na assinatura por …"), evento
  `signature_refused`, e-mail `SignatureContractRefusedMail` a quem redigiu. Número fica. Aprovado de novo, gera
  documento **novo** (assinaturas do zero — decisão do usuário). Testemunha/fiador: só o evento; o contrato
  segue aprovado e "Gerar documento de assinatura" e "Reabrir" passam a aceitar documento **recusado**.
- Pela tela do Lara (decisão do usuário: "nos dois"): `contracts.refuse-signature` (motivo obrigatório),
  `SignatureContractPolicy::refuseSignature` = presidente (titular da Presidência) com assinatura pendente
  (`presidentSignerFor`). Documento congelado: signatário e documento → recusado pelo `SignatureStateMachine`
  (ator usuário). Rascunho: o documento é cancelado.
- O presidente **vê** a lista e o contrato que aguardam a assinatura dele (`viewAny`/`view`), sem gerir
  (`manage` deixou de ser igual a `view`). O menu continua só com a permissão do catálogo; sem ela, ele entra
  por `/contratos`. O e-mail do diretor (`signature-director-pending`) explica como recusar quando o documento é
  de contrato (sem link, de propósito).

### Andamento da assinatura dentro de Contratos
- Aba **Em assinatura** (`SignatureContract::scopeInSigning`: aprovado com documento em rascunho, aguardando ou
  assinado) para todos, e coluna **Assinatura** na lista; cartão **Assinatura** na tela do contrato.
  `SignatureContract::signingProgress()` (assinaram/total, quem falta, quem recusou) e o partial
  `partials/signing-progress` (`compact` na lista).

## Visto da Gerência na aprovação (04/11/2026)

Pedido do usuário: o gerente põe um visto por página do documento ao aprovar o contrato.

- **Obrigatório para aprovar**, no computador (quadro "Seu visto" no formulário da validação, com o mouse) e no
  tablet (folha "Aprovar", com o dedo). Devolver não pede. Quadro: `partials/initials-pad.blade.php`
  (`window.laraInitialsPad`, estilos próprios porque o tablet não usa Tailwind), que manda `initials` (PNG em data
  URL) e `initials_strokes` (traços; JSON no formulário, array no tablet).
- **Validação e gravação:** `ContractApprovalInitials::store` — a régua do visto do tablet de assinatura
  (`signature.evidence.min_initials_points`, PNG, `max_signature_kb`, recorte `PngTrimmer`); arquivo em
  `signature/contracts/{id}/visto-aprovacao-{uuid}.png` no disco do módulo. Falhou a aprovação, `discard()`.
  A recusa é `ContractException` ("Faça o seu visto no quadro antes de aprovar…"). A obrigação está nos dois
  controllers (`ContractController::decide`, `ContractKioskController::decide`); `ContractService::approve`
  aceita o caminho como opcional (`$initialsPath`) para servir a testes e comandos.
- **Onde fica:** `signature_contract_versions.approval_initials_path` (a versão aprovada) e, copiado pelo
  `ContractDocumentGenerator` (também no "Gerar documento de assinatura" depois), `signature_documents.
  approval_initials_path` + `approval_initials_label` (o nome de quem aprovou). Migration
  `2026_11_04_110000_add_approval_initials_to_contracts_and_documents` (idempotente). O evento `approved` leva
  `visto: true|false`.
- **No PDF:** `SignatureDocumentRenderer::stampInitials` desenha a caixa da Gerência PRIMEIRO (à esquerda) na
  faixa do visto, em todas as páginas do documento (não no manifesto), JÁ PREENCHIDA no original e no final
  — a aprovação vem antes do documento. Rótulo em duas linhas: "Visto — Gerência" e o nome. A faixa existe se
  `requiresInitials()` OU houver o visto da Gerência (`geometry()`): com o visto dos signatários dispensado pelo
  gov.br, a faixa fica só com a caixa da Gerência (o original refeito na preparação já sai assim, e a assinatura
  do gov.br cobre o arquivo com ela). O manifesto ganha o quadro "Visto da validação do contrato".
- Contratos aprovados antes de 04/11/2026 não têm visto: o documento sai como antes.
- Ajuste junto (o visto da Gerência deixava a faixa mais cheia): o rótulo da caixa de cada signatário passou de
  22 para 18 caracteres, porque invadia o da caixa ao lado.
- Testes: `SignatureContractTest::test_aprovar_exige_o_visto_da_gerencia`,
  `test_visto_da_gerencia_vai_para_o_documento_em_todas_as_paginas`;
  `SignatureContractKioskTest::test_aprovar_pelo_tablet_exige_o_visto`. Visto de teste:
  `CreatesContractSchema::vistoDeTeste()`.

## Validação no tablet do balcão (Gerência)

Decisão do usuário (27/10/2026): a Gerência também valida pelo **tablet de assinatura do balcão**, numa
versão mobile, entrando com **matrícula + PIN** (como a Assinatura da Diretoria). No tablet se **lê,
comenta e decide**. Desde 04/11/2026 (pedido do usuário), também se **pede a revisão pelo Claude** e se
**edita o texto** (abaixo).

- **Entrada:** botão "Validação de contratos (Gerência)" na tela de espera do quiosque (só no balcão, não no
  Termo de Menores) → página própria `/assinatura/kiosk/gerencia` (`resources/views/quiosque/gerencia.blade.php`),
  fora da tela de assinatura.
- **Sessão:** `ContractKioskService` — cookie `lara_management` (httpOnly, SameSite=Strict, caminho do
  quiosque), só o hash no cache, `SIGNATURE_CONTRACT_KIOSK_SESSION_MINUTES` (padrão 20) renovados a cada uso;
  limite de tentativas por MATRÍCULA (`signature.directors.max_login_attempts`, 15 min). A cada chamada o
  servidor confere de novo: usuário ativo e do setor Gerência (`isManagementMember`). Parada sem toque por
  2× `SIGNATURE_DIRECTOR_IDLE_SECONDS`, a página encerra a entrada.
- **Telas:** lista dos contratos em validação (mais antigo primeiro; reenvio e aditivo marcados) → contrato:
  recado de quem enviou, o que impede aprovar, "o que mudou desde a versão N", anexos (rota própria pelo
  cookie) e o texto. **Tocar numa cláusula** abre a observação dela; as observações ficam só na memória da
  página até "Devolver". "Aprovar" fica desligado enquanto houver problema que trava.
- **Decisão:** `ContractService::returnToAuthor` / `approve`, os mesmos da tela web, com o usuário que entrou
  como quem decidiu. Contrato que outro gerente já decidiu responde 409 ("atualize a lista").
- **Revisão do Claude no tablet (04/11/2026):** botão "Revisão do Claude" (some sem `ANTHROPIC_API_KEY`) abre a
  folha com a última revisão (veredito, aviso de texto mudado, resumo, não enviados, trechos anexados) e os
  apontamentos; "Levar para a observação" põe o apontamento na observação da cláusula (pelo `uid` que o
  `ContractKioskService::aiPayload` devolve) ou na observação geral da devolução. Para pedir: os trechos
  sensíveis a incluir (desmarcados), os trechos anexados e "Analisar com o Claude" →
  `POST quiosque.gerencia.ai-review` (`include[]`, `extras[]`; 6/min na rota e o mesmo limite por pessoa do
  computador, `contract-ai-review:{id}`), com o gerente que entrou como quem pediu.
- **Editar no tablet (04/11/2026):** botão "Editar texto" abre a folha com o título do contrato e, por
  cláusula, título e itens (`contenteditable`, barra com negrito/itálico/sublinhado, Item/Subitem, Tirar,
  + Item; colar entra como texto puro). "Salvar texto" → `POST quiosque.gerencia.update` →
  `ContractKioskService::applyEdits`: só o título e título/itens das cláusulas que JÁ existem (pelo uid);
  cláusula nova ou desconhecida é ignorada, o resto do conteúdo fica. Grava pelo `ContractService::save` (que
  aceita contrato em validação) e o HTML passa pelo `ContractContent::normalize` (saneado). Na decisão, o
  ajuste vira a versão "Ajustado pela Gerência na validação" (`reviewerVersion`), como no computador. As
  observações abertas na página continuam (são por uid de cláusula). Partes, variáveis, considerandos e
  signatários seguem só no computador.
- Rotas `quiosque.gerencia.*` (públicas, throttle por rota): `index`, `login`, `pending` (batimento),
  `show` (com `edit` e `ai`), `decide`, `update`, `ai-review`, `attachment`, `logout`. Controller
  `Signature\ContractKioskController`.
- Testes: `tests/Feature/SignatureContractKioskTest.php` (usuários reais com `SharesSqliteWithUserConnection`).

## Revisão automática pelo Claude

Pedido do usuário (27/10/2026; para quem redige, 30/10/2026): o contrato vai para o Claude (modelo **Claude
Haiku 5.5**), que confere o básico e escreve um breve resumo das inconsistências com sugestões de alteração. A
integração com a API está em [claude-api.md](claude-api.md).

- **Quem pede:** quem pode mexer no texto agora (`SignatureContractPolicy::aiReview`, igual a `update`): o setor
  com o contrato em rascunho ou devolvido, e a Gerência também em validação. Quem vê o contrato vê a última
  revisão, em qualquer situação. Sem `ANTHROPIC_API_KEY`, o cartão nem aparece.
- **Onde:** cartão "Revisão automática (Claude)" na tela do contrato, logo abaixo da validação. Botão
  "Analisar com o Claude" → `POST contracts.ai-review` (6 por minuto) → volta para `#revisao-ia`.
- **No editor:** o mesmo cartão fica ao lado da prévia, com o botão **Salvar e analisar**. Ele é do formulário do
  contrato (`form="ctForm"`, `next=ai-review`): salva primeiro, analisa o texto salvo e volta ao editor, sem
  perder o que foi digitado. Trecho novo só aparece para marcar depois de salvo; até lá, se for sensível, fica de
  fora. Limite de 6 análises por minuto por pessoa no `ContractController::runAiReview`, porque esse caminho passa
  pela rota de salvar.
- **O que vai:** `ContractAiReviewer::plainText()`, o contrato como na prévia (qualificação, considerandos,
  cláusulas numeradas, fecho e signatários, com as variáveis e o extenso resolvidos), **menos os trechos
  sensíveis** (abaixo). Junto vão o tipo, o título, o setor, os nomes dos anexos, a lista do que ficou de
  fora e a conferência do Lara (`problems()`), para o Claude não repetir o que já está apontado.
- **Trechos sensíveis ficam de fora por padrão** (pedido de 28/10/2026): o contrato é dividido em trechos
  (cada parágrafo da qualificação, cada considerando, cada cláusula com seus itens, as assinaturas). O trecho
  com **valores** (R$, "reais", 1.234,56), **CPF**, **documentos** (CNPJ, RG, CNH, inscrição estadual…) ou
  **endereço** (rua, avenida, CEP, "residente", "com sede"…) não vai: no lugar segue `[não enviado: contém …]`,
  e a cláusula mantém o título, para a numeração continuar certa. Título, número e fecho vão sempre. A tela lista
  esses trechos com caixas **desmarcadas**; quem pede marca um a um o que quer incluir (`include[]`, as chaves
  de `sensitiveSections()`). O Claude é instruído a não apontar a falta do que foi retirado. A revisão guarda
  em `withheld` o que ficou de fora, e o cartão mostra "Não enviados ao Claude: …". Percentual sozinho
  (ex.: multa de 10%) não conta como valor.
- **O que volta** (JSON por esquema): `veredito` (`sem_ressalvas` | `ajustes_recomendados` |
  `revisar_antes_de_aprovar`), `resumo` (2 a 5 frases) e `apontamentos[]` com `clausula` (número; 0 = geral),
  `trecho`, `gravidade` (alta/media/baixa), `problema` e `sugestao`. O Lara limpa o resultado antes de gravar:
  no máximo 30 apontamentos, cláusula inexistente vira geral, gravidade desconhecida vira média e
  apontamento sem problema é descartado.
- **Na validação:** cada apontamento tem "Levar para a observação da cláusula" (ou "geral"). O botão copia
  o problema e a sugestão para o campo de observação daquela cláusula no formulário de devolução. Nada é
  gravado sem o gerente devolver.
- **Mesmo texto, mesma revisão:** a revisão guarda o `sha256` do texto inteiro do contrato e os trechos
  retidos. Pedir de novo sem mudança e com a mesma escolha de trechos devolve a existente, sem nova chamada.
  Se o texto mudou, o cartão avisa "O texto mudou depois desta revisão" e oferece "Analisar o texto atual".
  Com a revisão em dia, "Analisar de novo escolhendo outros trechos" permite repetir com outra escolha.
- **Revisão jurídica (04/11/2026, pedido do usuário):** quando quem pede é da Gerência
  (`ContractController::runAiReview` passa `isManagementMember()`; o tablet passa sempre `true`), a MESMA chamada
  leva o bloco `LEGAL_SYSTEM` no system prompt e o objeto `juridico` no esquema: `risco` (baixo | medio | alto),
  `resumo` (1 a 3 frases simples), `fraquezas[]` (`clausula`, `ponto`, `sugestao`; até 10) e `procurar_juridico`.
  O Lara limpa (`ContractAiReviewer::legal`): risco desconhecido vira médio, fraqueza sem ponto cai, cláusula
  inexistente vira geral e **risco alto sempre orienta procurar o jurídico** (`seek_lawyer`), diga o modelo o que
  disser. Grava em `signature_contract_ai_reviews.legal` (`{risk, summary, weaknesses, seek_lawyer}`, migration
  `2026_11_04_120000`); o evento `ai_reviewed` leva `juridico`. Tela: bloco "Revisão jurídica" no cartão (pílula do
  risco; vermelho com "Procure um jurídico especializado antes de aprovar este contrato." quando `seeksLawyer()`;
  "Levar para a observação" com o prefixo "Jurídico:"); no tablet, o mesmo na folha e "· risco jurídico alto" no
  botão. **Só orienta:** não trava a aprovação. Reaproveitamento: pedido com a parte jurídica só reaproveita
  revisão que a tenha; quem redige pode reaproveitar a da Gerência (e vê a parte jurídica dela).
- **Trechos anexados (04/11/2026, pedido do usuário):** em todo pedido — tela do contrato, "Salvar e analisar"
  do editor e tablet — quem pede pode anexar até `ContractAiReviewer::MAX_EXTRAS` (5) trechos de texto, com
  título opcional (até 120) e texto até 12.000 caracteres (`extras[N][title]`, `extras[N][text]`; partial
  `partials/ai-extras.blade.php`). `normalizeExtras()` tira os vazios e dá "Trecho N" ao sem título. Vão
  DEPOIS da minuta, como `<material titulo="…">…</material>` sob "Material de referência anexado por quem
  pediu a revisão (não é parte do contrato)"; o system prompt manda usá-los para conferir a minuta e apontar
  divergências. Ficam guardados na revisão (`extras`, migration `2026_11_04_100000`, idempotente) e o cartão
  mostra "Trechos anexados ao pedido". Outros trechos = outro pedido: a revisão só é reaproveitada com o mesmo
  texto, os mesmos retidos E os mesmos anexados. O formulário da tela do contrato aparece sempre (antes sumia
  com a revisão em dia e sem trecho sensível), dentro de "Analisar de novo escolhendo outros trechos ou
  anexando material". Os trechos colados não passam pelo filtro de dado sensível: a tela avisa que vão à
  Anthropic.
- **Só orienta:** não aprova, não devolve, não altera o texto. Fica no histórico como "Revisão automática
  pelo Claude". Desde 04/11/2026 também é pedida no tablet do balcão (acima).
- Erro da API: volta como mensagem na tela (ver a tabela em [claude-api.md](claude-api.md#erros)), e nada é gravado.

## O texto (ContractRenderer)

Fonte única do contrato escrito — prévia, documento de assinatura e Word saem de `blocks()`.

- Cabeçalho à direita: "TÍTULO, QUE ENTRE SI CELEBRAM CLUBE DOS FUNCIONÁRIOS DA CSN E {CONTRATADA}." e o número.
- Qualificação: o clube pelo estabelecimento escolhido (nome, endereço, CNPJ, IE), "representado por seu
  Presidente, {nome}"; cada contratada/fiador (PJ: sede, CNPJ, representante e CPF; PF: qualificação,
  residência, CPF, "inscrito/inscrita" pela concordância escolhida).
- Considerandos opcionais, numerados (i), (ii)…
- Cláusulas: "CLÁUSULA PRIMEIRA – TÍTULO" (ordinal feminino por extenso, `NumeroPorExtenso`), itens `1.1`
  e subitens `1.1.1` (nível 2). Numeração calculada, nunca guardada.
- Fecho: "{cidade}, [[data_da_assinatura]]." e as assinaturas. No documento, cada signatário é uma parte
  `p1`, `p2`… com `[[assinatura:pN]]` vizinhos (saem lado a lado); testemunhas depois de "Testemunhas:".

### Marcadores no texto

| Marcador | Vira |
|---|---|
| `[[contratante]]`, `[[contratada]]` | O termo da parte em negrito (CONTRATANTE, LOCATÁRIA…) |
| `[[contratada_nome]]`, `[[contratada_documento]]`, `[[contratada_endereco]]`, `[[contratada_representante]]` | Dados da primeira contratada |
| `[[clube_nome]]`, `[[clube_cnpj]]`, `[[clube_endereco]]`, `[[presidente]]`, `[[numero]]` | Dados do clube e do contrato |
| `[[chave]]` de variável declarada | O valor formatado (`ContractVariables`): valor `R$ 8.900,00 (oito mil e novecentos reais)`, número `15 (quinze)`, percentual `10% (dez por cento)`, data, data por extenso, CPF, CNPJ, texto |
| `[[ref:uid]]` | O número atual da cláusula (`3`) ou do item (`6.1`) |

O marcador aceita o nome com espaço e acento (`[[Valor global]]` = `[[valor_global]]`). Colchete que sobra
sai escapado: nenhum texto do contrato vira marcador do módulo de assinatura.

### Problemas (`problems()`)

`error` trava o envio e a aprovação: título, estabelecimento, presidente sem titular, contratada sem
nome/documento válido/endereço, PJ sem representante ou CPF dele, variável sem valor ou inválida,
marcador inexistente, remissão quebrada, cláusula sem título ou sem texto, item vazio, ninguém assinando
pela contratada, signatário sem nome/CPF válido/e-mail válido, diretor não escolhido.

`warning` só avisa: palavra em maiúsculas a até 2 letras de um termo de parte (`CONTRADATA`,
`CONTRATADO`, `LOCADORA`), remissão digitada à mão ("item 7.1"), contrato sem testemunhas, signatário
de fora sem e-mail (não recebe o link remoto).

## O documento gerado (ContractDocumentGenerator)

- Modelo **de uso único** (`single_use`, inativo), como o PDF enviado pronto: `body_html` = o contrato em
  modo documento, variável `data_da_assinatura` (`DATE_SIGNING_LONG`), uma parte por signatário (rótulo:
  termo + nome da contratada ou o cargo informado), `requires_initials` (visto), `requires_photo`,
  `identity_check` = `SIGNATURE_CONTRACT_IDENTITY_CHECK` (padrão CPF completo).
- `SignatureDocumentService::create` em **rascunho**, com quem redigiu como `created_by` (é quem conduz a
  assinatura). Título: "CTL - 12/26 — Título — Contratada".
- Signatários: o **presidente** primeiro (diretor, área da Presidência), depois os do clube, contratada,
  fiadores e testemunhas. Diretor passa por `DirectorAreaService::signerData`; mandato encerrado vira
  aviso e fica de fora — o `freeze` recusa até alguém ser escolhido para a parte.
- Anexos do contrato são copiados com `SignatureAttachmentService::store` (sem chave, com o rótulo).
- Daí em diante é o circuito de sempre (balcão, link remoto, gov.br, Assinatura Diretoria — o diretor
  espera quem é de fora da diretoria).

## Reaproveitamento

- **Contratadas** (`signature_contract_parties`): todo `save` grava/atualiza por documento (CNPJ 14 / CPF 11
  caracteres). O editor busca por nome ou documento (`contracts.parties`).
- **Biblioteca** (`signature_contract_clauses`, por setor): "Salvar na biblioteca" no editor (JSON) ou a tela
  da biblioteca. Inserir copia com **uid novo**. Desde 29/10/2026, "+ Cláusula" e "Da biblioteca…" existem no
  topo e no fim da lista (entra no fim) e no pé de cada cláusula (entra logo abaixo dela). O painel da biblioteca é
  um só (`[data-library-panel]`), movido para junto do botão clicado (`[data-library-wrap]`); os botões do painel e
  da busca de contratadas levam só o índice da lista carregada. Antes levavam o JSON num atributo, e as aspas não
  escapadas cortavam o atributo: o clique não inseria nada.
- **Editor (29/10/2026):** `<x-page wide>` (até 1680px) com o formulário em 7/12 e a prévia em 5/12; "Ocultar
  prévia" (`[data-preview-toggle]`, classe `ct-solo`, lembrado no `localStorage`) dá a largura toda ao formulário.
  Caixa de texto (`.ct-rich`) com 9,5rem de altura mínima, fonte 15px, cresce até 70vh e tem `resize: vertical`;
  barra com Limpar (`removeFormat`), desfazer e refazer. Colar passa por `limpaColado()` (só b/strong/i/em/u/p/br/
  ul/ol/li, sem atributos); o servidor saneia de novo. Título do contrato, endereço e qualificação da parte são
  `textarea[data-autogrow]` (crescem, Enter não quebra linha). Todo select leva `pr-8`/`pr-10`: o
  `@tailwindcss/forms` desenha a seta à direita e o `px-*` tirava o espaço dela, cobrindo o fim do texto. Variável em
  duas linhas (nome e valor; tipo embaixo, porque os rótulos dos tipos são longos). O título da cláusula ocupa
  a linha inteira, com as ações acima.
- **Modelos** (`signature_contract_models`, por setor): "Salvar como modelo do setor" na tela do contrato —
  sem dados das partes, sem signatários, variáveis sem valor. Contrato novo a partir do modelo ganha uid
  novo em tudo (`ContractContent::freshUids`, que também troca as remissões internas).
- **Aditivo**: copia o conteúdo do original (partes, variáveis, signatários, estabelecimento), título
  "Termo aditivo ao contrato {número}" e uma cláusula em branco. Aditivo de aditivo vira aditivo do original.
- **Modelos-base (04/11/2026, pedido do usuário):** os contratos reais do clube (PDFs) viraram 10 modelos e
  9 cláusulas avulsas em `App\Services\Signature\Contracts\ContractBaseModels`: obra de engenharia (CT 106/26),
  reforma com medições (CT 145/26), serviço pontual (CT 188/26), mão de obra por diárias (CT 189/26), uniformes
  (CT 194/26), locação de loja/sala com fiador (CT 088/26 e CT 09-22 A1), telões de LED (CT 131, 139 e 184/26),
  permuta (CT 148/26), escolinha esportiva (CT 109/26) e aditivo de contrato feito fora do Lara (CT 145/26 A1 e
  CT 106/26 A2). Cláusulas avulsas: carência para reforma, aluguel escalonado, comissão de agência, amortização de
  investimento, ratificação do contrato-mãe, LGPD, inexistência de vínculo, responsabilidade tributária e
  assinatura eletrônica. O que muda por contrato virou variável (sem valor); preâmbulo, data e assinaturas saíram do
  texto porque o `ContractRenderer` os monta. Remissões no catálogo são `{ref:chave.N}` → `[[ref:uid]]`.
  Ajustes em relação aos PDFs: numeração e remissões erradas; fiança com os artigos do Código Civil de 2002
  (827/828 e 835, outorga do cônjuge), não os de 1916; IVA retirado; nos telões o clube vende o espaço, então os
  termos são CLUBE e ANUNCIANTE (os PDFs tinham CONTRATANTE/CONTRATADA invertidos e CNPJ 35.500… em vez de 32.500…,
  corrigido sozinho pelo estabelecimento); teto das multas (art. 412 do Código Civil); assinatura eletrônica no lugar
  das "duas vias"; LGPD na escolinha (dados de crianças). Gravação: `php artisan contratos:modelos-base
  --setor=Comercial --setor=Manutenção` (setor por id ou nome, repetível; idempotente por nome no setor;
  `--atualizar` regrava, `--sem-clausulas`, `--listar`). Não roda no deploy: o setor é escolha de quem usa.

## Word

`ContractDocxExporter`: WordprocessingML mínimo com `ZipArchive` (sem dependência nova), dos mesmos
`blocks()`. Parágrafos, negrito/itálico/sublinhado, listas, recuo de subitem, linhas de assinatura em
branco. É cópia de trabalho: o que vale é o documento assinado no Lara.

### Papel timbrado no Word (04/11/2026)

Pedido do usuário: o contrato produzido sai com o cabeçalho e o rodapé do papel timbrado do Clube.

- `App\Services\Signature\SignatureLayoutDocx::pages($layout, $titulo)` monta o cabeçalho e o rodapé do Word a
  partir do papel timbrado **vigente** (`SignatureLayout::current()`, o de **Assinaturas › Modelos › Cabeçalho e
  rodapé**) e devolve o `$pages` de `DocxWriter::package()`.
- Medidas: as de `SignaturePageGeometry` (as mesmas do PDF de assinatura: altura em mm, alinhamento). Fora do modo
  "de borda a borda", a largura é limitada à área de texto do Word (A4 com margens 2,5 cm / 2 cm). De borda a
  borda, a imagem fica ancorada na borda de cima/de baixo do papel, atrás do texto, e a linha do rodapé sobe para
  cima da imagem. As margens da página crescem para o texto não encostar na arte.
- Rodapé: a linha de texto do papel timbrado (8 pt) e, embaixo, a imagem. Sem imagem e sem texto, sem rodapé.
- Sem imagem de cabeçalho: o cabeçalho de texto padrão do PDF (nome do Clube em grená e "CTL - X/AA — Título",
  com linha embaixo).
- O documento de assinatura (PDF) já saía no papel timbrado — o que estava vigente no congelamento
  (`signature_layout_id`). Nada mudou nele.

## Acesso

| Quem | O quê |
|---|---|
| `assinatura.contratos` + membro do setor do contrato | Vê, redige (rascunho/devolvido), pede a revisão automática pelo Claude (`aiReview`) enquanto redige, envia, anexos, reabre, cancela, aditivo; consulta e usa a biblioteca |
| `contratos.biblioteca` (04/11/2026) | Cadastra, edita e exclui cláusulas e modelos da biblioteca dos setores dele (`manageLibrary`) |
| `contratos.assinatura` (04/11/2026) + membro do setor do contrato | Aba Assinaturas: conduz a assinatura do documento do contrato (o processo de Assinaturas) |
| Membro do setor **Gerência** (`User::isManagementMember()`) | Vê todos; ajusta em validação; aprova/devolve (`decide`); pede a revisão automática pelo Claude (`aiReview`) também em validação |
| `assinatura.contratos` ou Gerência (05/11/2026) | Prestadores: cadastra, edita, inativa (`manageProviders`) |
| Presidente (titular da Presidência) com assinatura pendente (05/11/2026) | Vê o contrato e o lista; recusa e devolve para revisão (`refuseSignature`). Não gere (`manage`) |

- Permissões só no catálogo (`Permissions::ASSINATURA_CONTRATOS`, `CONTRATOS_BIBLIOTECA`,
  `CONTRATOS_ASSINATURA`, grupo "Contratos"); o `PermissionCatalogSeeder` cria no deploy, **sem setor** —
  configure em Setores.
- Validar é **cargo** (setor Gerência), não permissão. O menu usa só a permissão do catálogo (a Gerência
  tem acesso total e a alcança): Gate de cargo no menu consultaria o banco em toda tela.
- `User::sectorIds()` e `isManagementMember()` são memorizados por requisição; nos testes, mocke os dois.

## Depois da assinatura e busca (30/10/2026)

- **Fora da revisão interna:** documento que veio de contrato (`SignatureDocument::isContract()`, pelo
  `signature_contracts.signature_document_id`) não entra na fila e, assinado, mostra "Finalizado" direto. A
  Gerência já validou o texto. Regra em `SignatureDocument::skipsReview()`, junto com o termo de menores.
- **Busca, nas listas de Contratos e de Documentos:** número em qualquer grafia (`ctl3/26`, `CTL-3/26`, `ctl 3`,
  com os aditivos; `SignatureContract::splitNumberSearch`), nome da contratada e do representante, CNPJ com ou
  sem pontuação. Na lista de Documentos, o filtro **Modelo** ganhou **Contratos**, e a opção **Documento enviado
  pronto (PDF)** deixou de trazer contratos, que também usam modelo de uso único.

## Modelagem

Migration `2026_10_29_100000_create_signature_contracts` (idempotente, nomes curtos). Sem FK para
`users` nem `sectors` (id solto + nome em retrato).

| Tabela | Conteúdo |
|---|---|
| `signature_contracts` | setor, `kind`, `parent_id`, `addendum_seq`, `number_seq`/`number_year`/`number` (únicos), `title`, `status`, `content` (JSON), `parties_search` (migration `2026_10_30_130000`: nome e documento das partes em texto puro, porque o JSON escapa o acento e a busca não acharia), `current_version`, `approved_version_id`, `signature_document_id`, retratos; `content_revision`, `content_saved_by_name`, `content_saved_at` (salvar automático, migration `2026_11_05_100000`) |
| `signature_contract_versions` | `version`, `content`, envio (`note`, `by_reviewer`), decisão (`decision`, `decision_note`) |
| `signature_contract_comments` | `clause_uid` (nulo = geral), `body`, `resolved_at` |
| `signature_contract_events` | histórico (só inserção) |
| `signature_contract_attachments` | arquivo no disco `signature.disk` em `signature/contracts/{id}/anexos/`, `sha256` |
| `signature_contract_clauses` | biblioteca por setor (`items` JSON), SoftDeletes |
| `signature_contract_models` | modelos por setor (`content` JSON), SoftDeletes |
| `signature_contract_parties` | prestadores (contratadas/fiadores) por `document`; `active` e `created_by_name` (migration `2026_11_05_100000`) |
| `signature_contract_ai_reviews` | revisão do Claude (migration `2026_10_29_110000`): `content_hash`, `model`, `verdict`, `summary`, `findings` (JSON), `input_tokens`/`output_tokens`, quem pediu; `withheld` (JSON, migration `2026_10_29_130000`: trechos não enviados, `{key, label, reasons}`; nulo = tudo foi) |

Forma do `content`: ver o docblock de `ContractContent`.

## Configuração (`config/signature.php` → `contracts`)

| Variável | Padrão | Uso |
|---|---|---|
| `SIGNATURE_CONTRACT_NUMBER_PREFIX` | `CTL` | Prefixo do número |
| `SIGNATURE_CONTRACT_PRESIDENT_AREA` | `Presidência` | Área da diretoria cujo titular representa o clube |
| `SIGNATURE_CONTRACT_IDENTITY_CHECK` | `full` | Conferência de identidade do documento gerado |
| `SIGNATURE_CONTRACT_CITY` | `Volta Redonda` | Cidade do fecho |
| `SIGNATURE_CONTRACT_ATTACHMENTS_MAX_KB` | `10240` | Tamanho máximo de anexo |
| `SIGNATURE_CONTRACT_KIOSK_SESSION_MINUTES` | `20` | Minutos sem uso até a entrada da Gerência no tablet cair |

Os dois CNPJs do clube (nome, endereço, IE) ficam em `contracts.establishments`, no próprio arquivo.

A revisão automática usa `config/claude.php` (`ANTHROPIC_API_KEY`, `CLAUDE_CONTRACT_REVIEW_*`): ver
[claude-api.md](claude-api.md#configuração-env).

## Deploy

- `php artisan migrate` (a migration nova) e o `PermissionCatalogSeeder` (cria `assinatura.contratos`) —
  os dois já estão em `deploy_prod.sh`/`deploy_hml.sh`.
- `npm run build` (já no deploy): as telas usam classes Tailwind novas. **Em máquina de desenvolvimento,
  rode `npm run build`** depois de puxar a branch — com o CSS antigo, o editor e a prévia saem
  espremidos em colunas de 1/5 (aconteceu em 27/10/2026).
- `composer install` (já no deploy) instala o SDK `anthropic-ai/sdk`. Para ligar a revisão automática,
  ponha `ANTHROPIC_API_KEY` no `.env` do servidor e rode `php artisan config:cache`.
- Depois do deploy: cadastrar a área "Presidência" com o titular em Assinaturas → Diretoria (se ainda não
  houver) e dar `assinatura.contratos` aos setores que redigem (Setores).
- Deploy de 05/11/2026: migration `2026_11_05_100000` (prestador ativo, revisão do texto), `route:cache`
  (rotas novas: `contracts.autosave`, `contracts.refuse-signature`, `contract-providers.*`) e `npm run build`
  (`split-*` e classes novas). Nenhuma permissão nova.
- Modelos-base (04/11/2026): depois do deploy, `php artisan contratos:modelos-base --setor=<setor>` para cada
  setor que vai usá-los (modelo e cláusula são por setor). Rodar de novo não duplica.
- Deploy de 04/11/2026 (módulo próprio): a migration `2026_11_04_100000` (trechos anexados), o seed cria
  `contratos.biblioteca` e `contratos.assinatura` e atualiza a descrição de `assinatura.contratos` (grupo
  Contratos), `route:cache` (rotas renomeadas). Depois, em **Setores**, dar as duas permissões novas aos setores
  que redigem: sem `contratos.biblioteca` eles só consultam a biblioteca; sem `contratos.assinatura` não veem a
  aba Assinaturas.

## Testes

`tests/Feature/SignatureContractRevisionsTest.php` (05/11/2026): tipo pelo nome, uso das variáveis e marcador
sem variável, editor com "Preenchimento" e abas, salvar automático e conflito, Prestadores (cadastro, documento
único, inativo fora da busca, acesso), recusa do presidente/contratada/testemunha (tablet) e do presidente pela
tela, aba "Em assinatura" com o andamento.

`tests/Feature/SignatureContractTest.php` (numeração, remissão que acompanha, extenso, problemas, ciclo
completo com e-mails, número único entre setores, ajuste da Gerência, aditivo, reabrir, anexos, cadastro
de contratadas, Word, modelos, acesso por setor, telas e biblioteca), `tests/Unit/NumeroPorExtensoTest.php` e
`tests/Feature/SignatureContractAiReviewTest.php` (pedido à API por HTTP falso, erros traduzidos, revisão guardada
e reaproveitada, só a Gerência, trechos anexados). `tests/Feature/SignatureContractBaseModelsTest.php` preenche cada modelo-base e exige zero problemas no
`problems()` (variável inexistente, remissão quebrada ou digitada, termo parecido) e testa o comando.
`tests/Feature/SignatureContractKioskTest.php` cobre o tablet
(entrada, decisão, edição do texto e revisão pelo Claude com trechos anexados). Nenhum teste chama a API de verdade.
Esquema por `Tests\Concerns\CreatesContractSchema` (inclui `sectors` e o presidente de teste).
`ContractRecipients` e `DirectorAreaService` são trocados no container (consultam `users`, conexão mysql).

```
php -d memory_limit=1G vendor/bin/phpunit --filter "SignatureContractTest|SignatureContractAiReviewTest|NumeroPorExtensoTest"
```
