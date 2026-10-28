# Contratos (Assinaturas → Contratos)

Desde 27/10/2026. Branch `contratos`.

O contrato é **redigido no Lara em cláusulas**, **validado pela Gerência no Lara** e, aprovado, vira um
**documento de assinatura em rascunho** do módulo Assinaturas. Antes, a compradora imprimia o contrato,
o gerente devolvia com observações à mão e ela redigitava, várias vezes. Os dois contratos reais usados
como referência (CT 175/26, prestação de serviços; CT 135.26, locação) tinham os erros que o auxiliar
evita: remissão para o item errado ("cláusula penal do item 7.1", que era o 6.1), termo digitado errado
("CONTRADATA", "CONTRATADO", "O LOCATÁRIA") e nome de outro fornecedor esquecido no texto.

Guia do usuário: seção 13 do guia de Assinaturas (`resources/views/signature/guide/content.blade.php`).
Resumo para agentes: [docs/resumo-modulos/Assinaturas.md](../resumo-modulos/Assinaturas.md#contratos-27102026).

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
| Biblioteca e modelos | Cláusulas e modelos completos, **por setor**; qualquer usuário do módulo cria. |
| Aditivo | Nasce do contrato original, herdando partes e variáveis. |
| Word | Só a plataforma para redigir (sem importar Word), mas **exporta Word** (para o fornecedor revisar). |
| Tablet | A Gerência valida também no **tablet do balcão**, com matrícula + PIN; lá só lê, comenta e decide. |

Decisões tomadas na implementação (não perguntadas — revisar se o usuário discordar):

- O número sai **na aprovação**, não na criação: a sequência não fica com buracos de contrato desistido.
  Ano com 2 dígitos (`CTL - 12/26`), como os contratos atuais. O aditivo repete o número do contrato com
  `A1`, `A2`… (como o "CT-09-22 A1" do exemplo).
- O **presidente** é o titular vigente da área da diretoria chamada `SIGNATURE_CONTRACT_PRESIDENT_AREA`
  (padrão "Presidência") em Assinaturas → Diretoria. Sem titular, o contrato não vai à Gerência.
- O **cadastro de contratadas** é do Lara inteiro (não por setor): é o mesmo fornecedor para todos.
- A Gerência enxerga a biblioteca e os modelos de todos os setores, como enxerga os contratos.

## Fluxo

```
rascunho ─enviar→ em validação ─devolver→ devolvido ─enviar→ em validação …
                               └aprovar→ aprovado (número + documento de assinatura em RASCUNHO)
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

## Validação no tablet do balcão (Gerência)

Decisão do usuário (27/10/2026): a Gerência também valida pelo **tablet de assinatura do balcão**, numa
versão mobile, entrando com **matrícula + PIN** (como a Assinatura da Diretoria). No tablet só se **lê,
comenta e decide** — ajustar o texto continua no computador.

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
- Rotas `quiosque.gerencia.*` (públicas, throttle por rota): `index`, `login`, `pending` (batimento),
  `show`, `decide`, `attachment`, `logout`. Controller `Signature\ContractKioskController`.
- Testes: `tests/Feature/SignatureContractKioskTest.php` (usuários reais com `SharesSqliteWithUserConnection`).

## Revisão automática pelo Claude

Pedido do usuário (27/10/2026): o gerente manda o contrato para o Claude (modelo **Claude Haiku 5.5**), que
confere o básico e escreve um breve resumo das inconsistências com sugestões de alteração. A integração
com a API está em [claude-api.md](claude-api.md).

- **Quem:** só a Gerência (`SignatureContractPolicy::aiReview`), com o contrato em rascunho, devolvido ou em
  validação. Sem `ANTHROPIC_API_KEY`, o cartão nem aparece.
- **Onde:** cartão "Revisão automática (Claude)" na tela do contrato, logo abaixo da validação. Botão
  "Analisar com o Claude" → `POST signature-contracts.ai-review` (6 por minuto) → volta para `#revisao-ia`.
- **O que vai:** `ContractAiReviewer::plainText()`, o contrato como na prévia (qualificação, considerandos,
  cláusulas numeradas, fecho e signatários, com as variáveis e o extenso resolvidos), **menos os trechos
  sensíveis** (abaixo). Junto vão o tipo, o título, o setor, os nomes dos anexos, a lista do que ficou de
  fora e a conferência do Lara (`problems()`), para o Claude não repetir o que já está apontado.
- **Trechos sensíveis ficam de fora por padrão** (pedido de 28/10/2026): o contrato é dividido em trechos
  (cada parágrafo da qualificação, cada considerando, cada cláusula com seus itens, as assinaturas). O trecho
  com **valores** (R$, "reais", 1.234,56), **CPF**, **documentos** (CNPJ, RG, CNH, inscrição estadual…) ou
  **endereço** (rua, avenida, CEP, "residente", "com sede"…) não vai: no lugar segue `[não enviado: contém …]`,
  e a cláusula mantém o título, para a numeração continuar certa. Título, número e fecho vão sempre. A tela lista
  esses trechos com caixas **desmarcadas**; o gerente marca um a um o que quer incluir (`include[]`, as chaves
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
- **Só orienta:** não aprova, não devolve, não altera o texto, e não aparece no tablet do balcão. Fica no
  histórico como "Revisão automática pelo Claude".
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
  caracteres). O editor busca por nome ou documento (`signature-contracts.parties`).
- **Biblioteca** (`signature_contract_clauses`, por setor): "Salvar na biblioteca" no editor (JSON) ou a tela
  da biblioteca. Inserir copia com **uid novo**.
- **Modelos** (`signature_contract_models`, por setor): "Salvar como modelo do setor" na tela do contrato —
  sem dados das partes, sem signatários, variáveis sem valor. Contrato novo a partir do modelo ganha uid
  novo em tudo (`ContractContent::freshUids`, que também troca as remissões internas).
- **Aditivo**: copia o conteúdo do original (partes, variáveis, signatários, estabelecimento), título
  "Termo aditivo ao contrato {número}" e uma cláusula em branco. Aditivo de aditivo vira aditivo do original.

## Word

`ContractDocxExporter`: WordprocessingML mínimo com `ZipArchive` (sem dependência nova), dos mesmos
`blocks()`. Parágrafos, negrito/itálico/sublinhado, listas, recuo de subitem, linhas de assinatura em
branco. É cópia de trabalho: o que vale é o documento assinado no Lara.

## Acesso

| Quem | O quê |
|---|---|
| `assinatura.contratos` + membro do setor do contrato | Vê, redige (rascunho/devolvido), envia, anexos, reabre, cancela, aditivo |
| Membro do setor **Gerência** (`User::isManagementMember()`) | Vê todos; ajusta em validação; aprova/devolve (`decide`); pede a revisão automática pelo Claude (`aiReview`) |

- Permissão nova só no catálogo (`Permissions::ASSINATURA_CONTRATOS`); o `PermissionCatalogSeeder` cria no
  deploy, **sem setor** — configure em Setores.
- Validar é **cargo** (setor Gerência), não permissão. O menu usa só a permissão do catálogo (a Gerência
  tem acesso total e a alcança): Gate de cargo no menu consultaria o banco em toda tela.
- `User::sectorIds()` e `isManagementMember()` são memorizados por requisição; nos testes, mocke os dois.

## Modelagem

Migration `2026_10_29_100000_create_signature_contracts` (idempotente, nomes curtos). Sem FK para
`users` nem `sectors` (id solto + nome em retrato).

| Tabela | Conteúdo |
|---|---|
| `signature_contracts` | setor, `kind`, `parent_id`, `addendum_seq`, `number_seq`/`number_year`/`number` (únicos), `title`, `status`, `content` (JSON), `current_version`, `approved_version_id`, `signature_document_id`, retratos |
| `signature_contract_versions` | `version`, `content`, envio (`note`, `by_reviewer`), decisão (`decision`, `decision_note`) |
| `signature_contract_comments` | `clause_uid` (nulo = geral), `body`, `resolved_at` |
| `signature_contract_events` | histórico (só inserção) |
| `signature_contract_attachments` | arquivo no disco `signature.disk` em `signature/contracts/{id}/anexos/`, `sha256` |
| `signature_contract_clauses` | biblioteca por setor (`items` JSON), SoftDeletes |
| `signature_contract_models` | modelos por setor (`content` JSON), SoftDeletes |
| `signature_contract_parties` | contratadas/fiadores por `document` |
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

## Testes

`tests/Feature/SignatureContractTest.php` (numeração, remissão que acompanha, extenso, problemas, ciclo
completo com e-mails, número único entre setores, ajuste da Gerência, aditivo, reabrir, anexos, cadastro
de contratadas, Word, modelos, acesso por setor, telas e biblioteca), `tests/Unit/NumeroPorExtensoTest.php` e
`tests/Feature/SignatureContractAiReviewTest.php` (pedido à API por HTTP falso, erros traduzidos, revisão guardada
e reaproveitada, só a Gerência). Nenhum teste chama a API de verdade.
Esquema por `Tests\Concerns\CreatesContractSchema` (inclui `sectors` e o presidente de teste).
`ContractRecipients` e `DirectorAreaService` são trocados no container (consultam `users`, conexão mysql).

```
php -d memory_limit=1G vendor/bin/phpunit --filter "SignatureContractTest|SignatureContractAiReviewTest|NumeroPorExtensoTest"
```
