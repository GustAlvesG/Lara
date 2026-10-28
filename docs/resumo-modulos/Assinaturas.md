# Assinaturas — resumo para quem vai mexer

Documentação completa (regras, garantias, configuração, modo sem HTTPS): [docs/funcionalidades/assinatura-eletronica.md](../funcionalidades/assinatura-eletronica.md).
Guia do usuário: dentro do sistema, em **Assinaturas → Guia** (`resources/views/signature/guide/content.blade.php`
gera a página e o PDF — mudou o fluxo, mude o guia).

## Em uma frase

Assinatura eletrônica **avançada** (Lei 14.063/2020, sem ICP-Brasil) num **tablet do balcão**: o atendente
prepara e **congela** um documento no computador, gera um **QR de uso único** por signatário, o tablet
(`/assinatura/kiosk`, público) lê o QR, mostra o PDF, confere a identidade, colhe aceite, traço, visto e
foto; a fila monta o PDF final com **manifesto** e QR de validação pública (`/validar/{codigo}`).
Quem não vem ao balcão assina por um **link enviado por e-mail**, numa tela do site externo (Next.js,
o mesmo das reservas), sem login — ver "Assinatura remota" abaixo — ou pelo gov.br.

> **Não é o `/kiosk` dos freelancers.** Aquele tem login de operador (matrícula + PIN) e mora no módulo
> Freelancers (`app/Http/Controllers/Freelancer/KioskController.php`). O item "Assinatura (Tablet)" do menu
> de Freelancers é ele, não este módulo.

## Menu e permissões

Menu **Assinaturas** (`app/View/Navigation.php`): Documentos, Contratos, Revisão, Modelos, Termo de Menores, Diretoria, Guia.

| Permissão (`App\Authorization\Permissions`) | Uso |
|---|---|
| `assinatura.modelos` (`ASSINATURA_MODELOS`) | Modelos e papel timbrado |
| `assinatura.documentos` (`ASSINATURA_DOCUMENTOS`) | Criar, congelar, liberar, cancelar |
| `assinatura.consultar` (`ASSINATURA_CONSULTAR`) | Ver assinados e baixar PDF |
| `assinatura.evidencias` (`ASSINATURA_EVIDENCIAS`) | Ver foto e traço do signatário |
| `assinatura.revisar` (`ASSINATURA_REVISAR`) | Revisão interna: do dia seguinte em diante, só o que não acompanhou |
| `assinatura.revisar-coordenacao` (`ASSINATURA_REVISAR_COORDENACAO`) | Revisa antes do prazo e os próprios (dar só a coordenadores, em Setores) |
| `assinatura.termo-menores.gerenciar` (`ASSINATURA_TERMO_MENORES_GERENCIAR`) | Termo de Menores: cadastrar o termo de cada evento |
| `assinatura.termo-menores.parear` (`ASSINATURA_TERMO_MENORES_PAREAR`) | Termo de Menores: parear o tablet de autoatendimento |
| `assinatura.termo-menores.historico` (`ASSINATURA_TERMO_MENORES_HISTORICO`) | Termo de Menores: histórico (quem põe a pulseira) |
| `assinatura.diretoria` (`ASSINATURA_DIRETORIA`) | Diretoria: áreas, titulares e substitutos (escolher o diretor no documento é de `assinatura.documentos`) |
| `assinatura.contratos` (`ASSINATURA_CONTRATOS`) | Contratos: redigir os do próprio setor, biblioteca e modelos. **Validar não é permissão**: é ser do setor Gerência |

- Gates compostos em `AppServiceProvider`: `acessar-documentos-assinatura` (documentos **ou** consultar) e
  `acessar-guia-assinatura` (qualquer das três, mais o Termo de Menores), `acessar-termo-menores`
  (qualquer das três do Termo de Menores). O menu usa Gate, nunca método de model.
- O recorte fino por documento (editar só rascunho, ver evidência, baixar) é da `SignatureDocumentPolicy`.
- Permissão nova: só no catálogo (`CATALOG`); o `PermissionCatalogSeeder` cria no deploy, sem setor.
  (A migration `2026_09_22_120600` é histórica — não repita o padrão.)

## Rotas (`routes/web.php`)

| Prefixo / nome | Lado | Observação |
|---|---|---|
| `assinatura/documentos` → `signature-documents.*` | atendente (`auth`) | `novo`, `enviar` (PDF pronto), `associados` (busca) declaradas **antes** de `/{signatureDocument}` |
| `.../{doc}/congelar`, `cancelar`, `posicoes`, `pdf`, `arquivar` (POST arquiva, DELETE desarquiva) | atendente | `DocumentController` |
| `.../{doc}/status`, `signatarios/{s}/liberar`, `signatarios/{s}/via`, `liberacoes/{r}` (DELETE) | atendente | `ReleaseController`; `status` é polling (não há broadcasting) |
| `.../{doc}/govbr/preparar`, `.../{doc}/govbr` (POST), `.../{doc}/govbr/signatarios/{s}/convite`, `.../{doc}/govbr/{conferencia}/pdf` → `signature-documents.govbr.*` | atendente | `GovbrController`: aba **Assinatura gov.br** (`?aba=govbr`); preparar, enviar e convidar pedem `checkGovbr` (`assinatura.documentos`) |
| `assinatura/modelos` → `signature-templates.*` | `assinatura.modelos` | `PUT` cria **nova versão**; `importar-docx` converte e não grava; `{modelo}/word` (`docx`) baixa a base em Word gerada do texto |
| `assinatura/papel-timbrado` → `signature-layout.*` | `assinatura.modelos` | Cabeçalho/rodapé versionados |
| `assinatura/guia` → `signature-guide.*` | `acessar-guia-assinatura` | Página e PDF |
| `assinatura/kiosk` → `quiosque.*` | **público** | `consumir` (throttle 10/min) dá o cookie `lara_sign`; o resto passa pelo middleware `signature_kiosk` |
| `assinatura/kiosk/menores` → `quiosque.menores.*` | **público**, só tablet pareado | Termo de Menores no tablet: `parear` dá o cookie `lara_minor_device`; o resto passa por `signature_minor_device`; `documento` dá o `lara_sign` |
| `assinatura/termo-menores` → `minor-terms.*` | atendente | Termos (`gerenciar`), tablets (`parear`), histórico e foto (`historico`) |
| `/validar/{codigo}` → `signature.validate(.verify)` | **público** | Mostra pouco; PDF enviado só é hasheado e descartado |
| `assinatura/kiosk/diretoria` → `quiosque.diretoria.*` | **público** | `entrar` (matrícula + PIN → cookie `lara_director`), `pendentes` (pendentes + `signed`), `abrir/{signer}` (devolve o `lara_sign`), `lote/*` → `batch.*` (assinatura em lote), `sair`. `DirectorKioskController` |
| `assinatura/diretoria` → `signature-directors.*` | `assinatura.diretoria` | Áreas e mandatos. `DirectorAreaController` |
| `.../{doc}/signatarios/{s}/link-remoto` (POST), `.../{doc}/links-remotos/{link}` (DELETE) → `signature-documents.remote-link.*` | atendente (`release`) | Enviar e revogar o link de assinatura remota. `RemoteLinkController` |
| `/api/assinatura/remota/*` → `api.assinatura-remota.*` | **servidor do site** (token próprio) | `link`, `abrir` (token no corpo); `sessao`, `pdf`, `respostas`, `visualizado`, `identidade`, `assinar`, `recusar`, `encerrar` (header `X-Signature-Session`). `Api\RemoteSignatureApiController` |

`/quiosque` redireciona para `/assinatura/kiosk`; os **nomes** `quiosque.*` ficaram. Mudou rota → refaça o
`route:cache` (um nome que não resolve no menu derruba todas as telas).

## Listas do painel (21/10/2026)

- **Modelos:** abas **Ativos** / **Inativos** pelo endereço (`?aba=inativos`, `TemplateController::index`); a busca de
  cada aba é na página (`x-search-bar mode="client"`).
- **Documentos:** busca no servidor por `SignatureDocument::scopeSearch` — cada palavra (até 8) precisa casar em
  algum campo: título, nome do modelo, código de validação (pedaço), `created_by_name`, nome/e-mail do signatário;
  palavra só de dígitos e pontuação (≥ 3 dígitos) procura pedaço do CPF. Filtros: `status` (inclui `diretoria`),
  `modelo` (= `root_id`, todas as versões; `avulso` = `single_use`), `de`/`ate` (`created_at`, data inválida é
  ignorada) e `meus` (`created_by`). Teste: `SignatureDocumentSearchTest`.
- **Arquivar (28/10/2026):** botão na tela do documento (policy `hide` = `assinatura.documentos`) grava
  `hidden_at`/`hidden_by`/`hidden_by_name` e tira da lista padrão; filtro `arquivados=1` mostra só eles; "Desarquivar"
  limpa. **Só exibição**: sem status, sem trilha, documento aberto continua assinável. **Não é o `archived_at`** (cópia
  no FTP). `SignatureDocument::isHidden()`/`scopeHidden()`, rotas `signature-documents.hide`/`unhide`
  (`POST`/`DELETE .../{doc}/arquivar`), migration `2026_10_29_120000` (no `CreatesSignatureSchema`). Teste:
  `SignatureDocumentHideTest`.

## Ciclo de vida

```
documento:   draft → awaiting_signature → signed → finalized
                  ↘ canceled     ↘ refused     ↘ expired
solicitação: pending → consumed → completed
                    ↘ expired ↘ canceled ↘ superseded
signatário:  pending → signed | refused | canceled | expired
```

1. **Rascunho** (`SignatureDocumentService`): modelo + signatários (busca local em `members` e, por título,
   no MultiClubes via `SignatureMemberDirectory`) + dados dos campos. Ou **PDF pronto** (upload), cujas regras
   moram num modelo `single_use` criado junto.
2. **Congelar**: `body_snapshot` + PDF original + `original_sha256` + código de validação +
   `signature_layout_id`. Depois disso **nada se edita**; corrige-se cancelando e emitindo outro.
3. **Liberar** (`SignatureRequestService`): token de 64 chars, banco guarda só `sha256`, QR `LARA-SIGN:v1:<token>`,
   5 min para ler, 15 min de sessão. Regerar torna o anterior `superseded`. Um QR por signatário, **em qualquer ordem** (o painel tem "Quem vai assinar agora"; `releaseBlockReason` não olha a posição).
4. **Tablet** (`QuiosqueController` + `resources/views/quiosque/index.blade.php`): formulário de perguntas
   (se houver) → leitura do PDF → identidade (`Cpf::matches`, 5 tentativas) → aceite (+ autorização de imagem
   se pede foto) → traço (≥ 30 pontos) → visto (se exigido) → foto (botão "Tirar foto" inicia a contagem de 3 s; não é
   automática; depois vem a **prévia**, com "Tirar nova foto" e "Usar esta foto" — só o "Usar" envia). Gravação numa
   transação só (`SignatureCaptureService`).
   **Toda etapa tem "Voltar" (26/10/2026):** identidade e aceite voltam ao documento (a identidade já conferida não é
   pedida de novo, `S.identidadeOk`), traço volta ao aceite, visto volta ao traço, foto volta ao visto (ou ao traço).
   No primeiro passo (formulário, ou documento sem formulário) voltar é **sair do documento**: confirmação
   (`#sheetSair`) e `quiosque.leave` — a liberação cai, o documento segue pendente (não é recusa). No balcão o
   atendente gera outro QR; na diretoria volta à lista.
5. **Finalizar** (job `FinalizeSignatureDocument`, idempotente): PDF final re-renderizado do mesmo snapshot,
   traços no lugar, manifesto, `final_sha256`. Depois `SendSignatureCopy` (e-mail) e `ArchiveSignatureDocument` (FTP).

**Sem `queue:work` o documento para em "Assinado"** — evidência gravada, mas sem PDF final.

## Conferência de identidade por código no e-mail

Opção `email` do modelo (`SignatureTemplate::IDENTITY_EMAIL`; enum migrado em `2026_10_11_100100`). O tablet chama
`quiosque.identity-code` ao abrir a etapa; `SignatureCaptureService::sendIdentityCode` gera 6 dígitos, grava **HMAC**
(app key + id da liberação) em `signature_requests.identity_code_*`, manda `SignatureIdentityCodeMail` **síncrono, na
transação** (falha → 503, nada contado). `confirmIdentity` aceita `codigo` nesse modo. 3 envios/liberação, 60 s entre
eles, validade `signature.identity_code_ttl_minutes`; mesmas 5 tentativas do CPF. Sem e-mail, o `freeze` trava. Endereço
nunca vai ao tablet; trilha `identity_code_sent` com e-mail mascarado (`App\Support\EmailMask`). Testes:
`SignatureIdentityEmailCodeTest`.

## Anexos do documento

Identidade, comprovante: itens pedidos (rótulo + obrigatório) no **modelo** (`signature_templates.attachments`,
chave `mod_…`, versionado) e no **documento** (`signature_documents.attachment_requirements`, chave `doc_…`, passo
"Anexos" do formulário). Chave sai do rótulo em `SignatureAttachmentService::normalize`. O atendente envia na tela do
documento (`AttachmentController`, policy `attach` = `assinatura.documentos`); arquivos em `signature_attachments` +
disco privado `documents/{id}/anexos/`, com SHA-256.
- Rascunho: envia e remove. Aguardando assinatura e Assinado: só envia. Finalizado/encerrado: nada.
- **Vários arquivos de uma vez** (26/10/2026): o formulário manda `arquivos[]` (`multiple`, até
  `AttachmentController::MAX_FILES_PER_UPLOAD` = 10); cada arquivo vira um anexo próprio (hash e evento próprios) e um
  recusado não segura os outros (flash `warning` com o nome do arquivo). `arquivo` (um só) continua aceito.
- **Na tela do documento**, congelado, o cartão de anexos fica ACIMA do texto do documento; no rascunho, abaixo
  (26/10/2026).
- Assinatura remota: a própria pessoa envia pela tela do link e troca o que ela enviou; lá o obrigatório
  trava a assinatura — ver "Assinatura remota".
- Obrigatório trava a **conclusão** (não o `freeze`): `FinalizeSignatureDocument` sai cedo se
  `missingAttachments()` não está vazio, e o documento fica `signed`; o envio que completa a lista despacha o job.
- Tipo pelo conteúdo (`finfo`): PDF, JPG, PNG; `signature.attachments.max_kb`.
- Trilha `attachment_added`/`attachment_removed` **sem o nome do arquivo**. Manifesto e relatório gov.br imprimem o
  hash; FTP leva os arquivos ao lado do PDF; a via por e-mail **não** leva anexo.
- Índice composto com nome curto (`sig_attachments_doc_key_idx`): o automático passa de 64 caracteres no MySQL — o
  SQLite dos testes não pega isso.
- Testes: `SignatureAttachmentTest`.

## Assinatura pelo gov.br

Caminho para quem não vem ao balcão: o atendente **prepara** o documento para o gov.br, a pessoa assina o PDF
do Lara em `assinador.iti.br` e o devolve, e o atendente envia na aba **Assinatura gov.br**. Aprovado, o
envio **registra a assinatura** (signatário → `signed`, pela máquina de estados) e, com todos assinados, fecha
o documento: o PDF final é **o arquivo que voltou, byte a byte** — sem re-render (quebraria as assinaturas). O
manifesto vira um **relatório de validação em PDF à parte** (`report_path`/`report_sha256`,
`SignatureDocumentRenderer::govbrReport`, view `signature/pdf/govbr-report`), que segue o final na via (2º anexo),
no FTP (`… - relatorio gov.br.pdf`) e na tela (`?versao=relatorio`).

- **Um caminho por documento.** Preparado para o gov.br (`govbr_sent_at`), o tablet não libera mais
  (`SignatureSigner::releaseBlockReason`); com assinatura no tablet, o gov.br não é aceito
  (`SignatureDocument::govbrBlockReason`, que também barra documento com campo marcado para perguntar a quem assina
  — `signerFields()` do documento). **Modelo com visto NÃO barra** (desde 20/10/2026): ver o próximo item.
- **Preparar** (`GovbrCheckService::prepare`): aplica a data automática (refaz original + hash), prazo de
  `signature.govbr.ttl_days`, cancela QR vivo. Baixar o PDF **depois** de preparar.
- **Visto dispensado no gov.br:** modelo com visto, ao preparar, ganha `signature_documents.initials_waived_at`
  (`SignatureSigningDataService::waiveInitials`, evento `initials_waived`, original e hash refeitos sem a faixa).
  O modelo não muda. **Toda leitura do visto passa por `SignatureDocument::requiresInitials()`** (renderer/geometria,
  stamper, captura, payload do tablet) — nunca `template->requires_initials` direto (o `MinorTermFields` valida o
  modelo, e esse fica). Relatório gov.br e `/validar` mostram "Visto dispensado". Migration `2026_10_20_120000`.
- **Concluir** (`GovbrCheckService::conclude`), tudo ou nada: documento preparado; assinado sobre o original;
  quem já assinou pelo gov.br continua no arquivo (o próximo assina o arquivo do anterior). **Ordem livre**, mas um de
  cada vez: dois assinando o mesmo arquivo em paralelo → o segundo é recusado pela regra anterior. O motivo de não
  concluir fica em `signature_govbr_checks.conclusion` e aparece na aba.
- **Convite por e-mail** (`GovbrInviteService`, um botão por pendente na aba, qualquer ordem — salvo o diretor, que
  espera quem é de fora da diretoria): PDF a assinar anexado
  (`SignatureDocument::govbrFileToSign` — original, ou o arquivo do anterior) + passo a passo. **Sem link**: o Lara
  não é acessível de fora. **O arquivo assinado NÃO volta por e-mail** (decisão do usuário, 26/10/2026): o texto diz
  "Entregue esse arquivo ao atendimento do clube", e o atendente o envia na aba. O `Reply-To` (atendente que
  enviou) ficou só para dúvidas; o parâmetro `replyGoesToAttendant` do mailable saiu. E-mail **síncrono, dentro da transação** (falhou → nada registrado). `signature_govbr_invites`
  só registra para quem/quando/por quem. A migration `2026_10_10_100300` tirou da homologação as colunas do link e
  o valor `signer` do enum da trilha (uma versão anterior tinha link público).
- `signed_at` = hora declarada na assinatura do gov.br; `signature_signers.govbr_check_id` diz que foi pelo
  gov.br; `signature_documents.govbr_check_id` aponta o arquivo final (o `FinalizeSignatureDocument` o confere
  contra o hash da conferência).

- `app/Services/Signature/Govbr/GovbrSignatureValidator.php` — o núcleo. "É este documento" = o arquivo
  **começa byte a byte** pelo `original` ou `final` do Lara (o gov.br só acrescenta, atualização incremental);
  "pessoa certa" = CPF do otherName `2.16.76.1.3.1` (posições 8–18 dos 45 dígitos) igual ao de um signatário,
  certificado subindo até uma raiz do repositório — `resources/certs/govbr/cadeia-govbr.pem` (vence 2033) ou
  `resources/certs/icp-brasil/raizes-icp-brasil.pem` (v5…v13; v5 vence 2029) —, validade na hora da assinatura
  **e** na da conferência. O resultado diz o tipo: `kind` = `govbr` (avançada) ou `icp-brasil` (qualificada,
  e-CPF assinado no programa do certificado e devolvido pela mesma aba). Elo que falta: AIA
  (`PkiRepository::issuers`). Hora: `signingTime` ou, sem ele, `/M` do dicionário (`declaredTime`).
- **Revogação** (por assinatura, chave `revogacao`): `Pki\RevocationChecker` confere cada elo na LCR declarada,
  assinada pela AC de cima e em vigor. Na lista → **reprova** (qualquer data). Indisponível → `null`
  (não reprova), salvo `SIGNATURE_PKI_REVOCATION_REQUIRED`. LCR guardada em `signature/pki/lcr/` até o
  `nextUpdate`; `signature:crl` (hora em hora + deploy) renova. A do gov.br tem ~71 mil séries:
  `CertificateRevocationList` indexa com cursor (não troque por `Asn1::children`, passa de 50 MB).
- `Asn1.php` — leitor **BER**: o CMS do gov.br tem comprimento indefinido; não troque por leitor DER nem
  corte os zeros do `/Contents`.
- `GovbrCheckService` grava arquivo (também os recusados) + conferência + evento; o evento não leva nome nem CPF.
- Recusa e "não concluiu" voltam como flash `warning` ("Atenção"), não `error` (que manda procurar a TI).
- Testes: `SignatureGovbrCheckTest` (conferência), `SignatureGovbrSigningTest` (conclusão), `SignatureGovbrInviteTest`
  (convite) e `SignatureGovbrFinalizationTest` (relatório e entrega), com AC de teste. Rode com
  `php -d memory_limit=1G vendor/bin/phpunit --filter Signature` (o `artisan test` não passa o limite ao subprocesso)
  (`Tests\Concerns\BuildsGovbrSignedPdf`). As amostras reais ficam fora do git (`storage/app/govbr-amostras/`,
  com o inspetor da Fase 0) — têm CPF real.
- Não há API do gov.br envolvida: ela é só para órgão público. A rede da conferência é só LCR e AIA
  (`SIGNATURE_PKI_NETWORK=false` desliga; a suíte roda desligada pelo `phpunit.xml` — teste que precisa
  liga a config e usa `Http::fake` + `preventStrayRequests`). Testes: `SignatureGovbrRevocationTest`.
- Helper de testes ampliado (`BuildsGovbrSignedPdf`): `icpConfiaEm`, `govbrAcIntermediaria`, `govbrLcr`
  (LCR assinada pela AC de teste), extensões no certificado (LCR/AIA) e `govbrAssina(..., $semAtributos, $cadeia)`.

## Lacre do clube (e-CNPJ) + carimbo de tempo

- `SIGNATURE_PADES_ENABLED`: `FinalizeSignatureDocument` passa pelo `SignaturePdfSealer` o final do tablet,
  o final do gov.br **e** o relatório. `final_sha256` = arquivo lacrado; o `/validar` também aceita o arquivo
  da conferência gov.br sem lacre.
- `Pki\PdfSignatureWriter`: assinatura por **atualização incremental** (só xref clássica, sem criptografia),
  campo invisível na 1ª página. Preserva as assinaturas do gov.br (DocMDP `/P 2` permite). Conferido com
  pyHanko em 08/10/2026 nas amostras reais.
- `Pki\TimestampClient`: RFC 3161 na ACT (`SIGNATURE_TSA_*`); o token entra como atributo NÃO assinado.
  Confere resumo, nonce e assinatura do carimbo.
- Falha alto (sem .pfx, senha, vencido, ACT fora) → o job tenta de novo. `php artisan signature:seal-check`
  confere tudo sem finalizar nada. Evento `finalized`: `lacrado`, `carimbo_de_tempo`.
- Testes: `SignaturePdfSealTest` (ACT de teste que lê o pedido e devolve carimbo assinado).
- **Não use a marca/selo ICP-Brasil** em tela, PDF ou e-mail: o selo é só de sistema homologado no ITI
  (DOC-ICP-10, item 4), e o Lara não é. Diga "certificado ICP-Brasil", nunca "homologado".

## Peças

| Arquivo | Papel |
|---|---|
| `app/Services/Signature/SignatureStateMachine.php` | **Única** porta de mudança de status; valida o caminho e grava o evento na mesma transação. Nunca faça `update(['status' => …])`. |
| `SignatureAuditor.php` | Trilha `signature_audit_events` encadeada por hash, só inserção; `verify()` confere a cadeia. |
| `SignatureDocumentService.php` | Criar/editar rascunho, título padrão (`titleFor`), congelar. |
| `SignatureRequestService.php` | Emitir/consumir/cancelar QR e código digitado; sessão do tablet. |
| `SignatureCaptureService.php` | Identidade, aceite, traço, visto, foto, recusa (`refuse`, comum ao tablet e ao link remoto). Recusa assinar com formulário pendente. |
| `RemoteSignatureService.php` | Assinatura remota por link — ver a seção dela. |
| `SignatureSigningDataService.php` | Respostas do signatário e data automática (`signing_data`); refaz PDF e hash antes da leitura. |
| `SignatureDocumentRenderer.php` | HTML do documento a partir do snapshot; resolve `[[campos]]`, posiciona assinaturas por parte (`placeSignatures`). Fonte única do que o tablet mostra e do que o PDF imprime. |
| `SignatureFieldTypes.php` | Tipos de campo: validação, forma canônica, formatação. Único lugar que sabe o que é CPF/CNPJ válido. |
| `DocxTemplateImporter.php` | `.docx` → `body_html` + campos (`[[Rótulo]]`, `[[assinatura: Parte]]`). |
| `DocxTemplateExporter.php` | O caminho de volta (28/10/2026): `body_html` → `.docx` ("Baixar Word" no modelo e em Revisar). Feito para reimportar com as **mesmas chaves** (`[[Rótulo]]` só se `keyFor(rótulo)` = chave; senão `[[chave]]`), porque o formulário casa campos e partes pela chave. XML pelo `App\Support\DocxWriter` (compartilhado com o `ContractDocxExporter`). Teste de ida e volta: `SignatureTemplateDocxExportTest`. |
| `SignaturePdfStamper.php` | Carimba por cima do PDF enviado pronto (FPDI): validação, vistos, assinaturas, folha final. |
| `SignaturePdfSealer.php` | Lacre PAdES com o e-CNPJ do clube + carimbo de tempo (ver seção acima). |
| `Pki/*` | `Certificates`, `CertificateRevocationList`, `PkiRepository`, `RevocationChecker`, `TimestampClient`, `PdfSignatureWriter`, `Der`. |
| `SignaturePageGeometry.php` | Margens, cabeçalho, rodapé e faixa do visto — CSS e desenho do visto leem daqui. |
| `SignatureArchiver.php` + `App\Support\ArchivePath` | Cópia no FTP: tipo → pessoa, nome com data e código. |
| `SignatureQrCode.php` | QR do manifesto no servidor (`bacon/bacon-qr-code`). |
| `SignatureValidationLink.php` | Para onde apontam o QR, o rodapé, a linha carimbada e o botão da via: ITI no final lacrado ou do gov.br; `/validar` no resto. |
| `Govbr/GovbrSignatureValidator.php`, `Govbr/GovbrCheckService.php`, `Govbr/GovbrInviteService.php`, `Govbr/Asn1.php` | Assinatura pelo gov.br — ver a seção acima. |
| `app/Http/Middleware/EnsureSignatureKioskSession.php` | Alias `signature_kiosk`: resolve o cookie, confere prazo, amarra ao documento (outro id → 403 + evento). |
| Models | `SignatureTemplate` (versionado por linha), `SignatureDocument`, `SignatureSigner`, `SignatureRequest`, `SignatureEvidence`, `SignatureAuditEvent` (recusa update/delete com exceção), `SignatureLayout` (versionado), `SignatureGovbrCheck` (conferências gov.br), `SignatureDirectorArea` e `SignatureDirectorAreaHolder` (Assinatura Diretoria). |
| `Directors/DirectorAreaService.php`, `Directors/DirectorKioskService.php` | Assinatura Diretoria — ver a seção dela. |
| Exceções | `InvalidSignatureTransitionException`, `SignatureDocumentLockedException`, `SignatureFormException`, `SignatureSessionException`. |
| Views | `resources/views/signature/` (`documents`, `templates`, `layout`, `guide`, `pdf`, `validate`). Os passos do formulário são só apresentação (`documents/partials/steps.blade.php`). |
| `config/signature.php` | Prazos, mínimos de pontos, disco, flags (arquivo FTP, modo sem HTTPS, e-mail). Variáveis no `.env.example`. |

## Regras que costumam pegar

- **Imutabilidade:** congelado não muda; modelo e papel timbrado revisados criam linha nova — o documento
  aponta para a linha exata. Não reescreva snapshot nem linhas antigas.
- **Hash do que foi lido:** `original_sha256` pode mudar após o congelamento só por data automática (na
  leitura do QR) e respostas do formulário — sempre **antes** da leitura e com evento
  (`signing_date_set`, `form_answered`). Na primeira assinatura `signingDataIsOpen()` fecha.
- **O servidor decide:** CPF nunca vai à tela; imagem conferida pelos bytes; motivo de foto ausente é lista
  fechada (`camera_unavailable`) e só vale com a flag ligada; foto sem `photo_consent` → 422.
- **Hora é sempre do servidor**; o tablet não guarda nada (`localStorage`/`IndexedDB` proibidos).
- **Encerramento da sessão do tablet** (`SignatureRequestService::complete`) grava `session_completed`
  ("Atendimento no tablet encerrado"). Até 08/10/2026 gravava `qr_consumed`, e o manifesto mostrava "QR lido
  pelo tablet" depois de "Assinado". Documentos antigos ficam assim (trilha só-inserção).
- **Hash no manifesto ≠ hash do PDF:** o manifesto imprime o `original_sha256` (antes das assinaturas). O hash
  que um validador (ITI) mostra é o do arquivo final (`final_sha256`, "impressão digital da via assinada" no
  `/validar`) — um PDF não pode trazer o próprio hash. O manifesto diz isso desde 08/10/2026.
- **Trilha não vaza dado:** eventos guardam chaves respondidas, nunca valores; CPF mascarado no manifesto e
  na validação; nome de quem gerou documento/QR é retrato (`created_by_name`).
- **Disco privado:** `SIGNATURE_DISK` nunca `public`/`placar`; imagens e PDFs servidos por rota com policy.
- **Validação pública:** código inexistente e rascunho respondem igual ("não encontrado").
- **PDF pronto:** posição da assinatura é fração da página (0–1); editar signatários do rascunho apaga as
  posições; PDF com object/xref streams é recusado (limite da FPDI livre).
- **QR do atendente:** fundo `bg-white` fixo e cores fixas no `QRCode` (`partials/release.blade.php`). Não troque
  por token do tema (`bg-surface`): no escuro o código fica ilegível para a câmera. `SignatureReleaseQrThemeTest` trava isso.
- **WhatsApp não entrega a via** de propósito (a Poli responde 200 sem entregar). Não ligue sem confirmação real.
- **"Sem conferência" (`IDENTITY_NONE`)**: o tablet pula a etapa de identidade, e o `SignatureCaptureService::capture`
  confirma a liberação do balcão ali mesmo (evento `identity_confirmed`, `modo = none`). Até 21/10/2026 a assinatura
  parava em "Confirme a identidade" (documento 31 de produção). O remoto nunca usa `none` (exige CPF completo).
  Teste: `test_modelo_sem_conferencia_assina_sem_a_etapa_de_identidade`.
- **Visto** é desenhado via `page_script` do dompdf, fora do manifesto — por isso o PDF final com visto é
  montado duas vezes.

## Comandos e agenda (`routes/console.php`)

- `signature:expire` — a cada minuto: QR não lido, sessão parada, documento esquecido.
- `signature:archive` — de hora em hora: reenvia cópias pendentes ao FTP; `--testar` só confere a conexão.
  Desligado por padrão (`SIGNATURE_ARCHIVE_ENABLED=false`).
- `signature:crl` — de hora em hora e no deploy: renova as LCR (gov.br + as já consultadas); `--forcar`.
- `signature:seal-check` — manual: certificado do lacre e carimbo de teste na ACT.
- `SignatureTemplateSeeder` — 3 modelos iniciais, idempotente, **fora** do `DatabaseSeeder` de propósito.

## Testes

- Feature: `tests/Feature/Signature*Test.php` (QR, sessão do tablet, captura, congelamento, finalização,
  validação, upload de PDF, partes/visto/papel timbrado, formulário, importação .docx, arquivo, via, guia,
  acesso ao painel, sem HTTPS, busca de associado). Unit: `SignatureStateMachineTest`, `SignatureAuditChainTest`,
  `SignatureFieldTypesTest`.
- Esquema pelo trait `Tests\Concerns\CreatesSignatureSchema` (aplica as migrations reais do módulo);
  usuário por `Tests\Concerns\MocksSignatureUser`. Nenhuma tabela do módulo tem FK para `users`
  (o model `User` fixa a conexão `mysql`).
- **Sem `RefreshDatabase`.** Antes de rodar, confira o isolamento SQLite do `phpunit.xml`. Rode só o módulo:
  `php -d memory_limit=1G vendor/bin/phpunit --filter Signature`.

## Ao adicionar algo

- Novo status ou transição → `SignatureStateMachine` (mapa de caminhos) + evento de auditoria + teste Unit.
- Novo tipo de campo → `SignatureFieldTypes` (e o palpite por nome na importação do Word).
- Nova evidência → coluna em `signature_evidences`, gravação no `SignatureCaptureService` (mesma transação),
  linha no manifesto (`resources/views/signature/pdf/manifest.blade.php`) e na trilha.
- Nova config → `config/signature.php` + `.env.example` + tabela "Configuração" da doc completa.
- Sempre: doc completa, guia do usuário e este resumo.

## Modelos de exemplo

`php artisan db:seed --class=SignatureExampleTemplatesSeeder` cria 7 modelos "[Exemplo] …" (campos obrigatórios/opcionais com pergunta pronta para o tablet — a marcação é no documento —, anexos, partes+visto, código por e-mail, gov.br). Idempotente, recusado em produção, fora do deploy.

## Sem local do atendimento (08/10/2026)

- O campo `location` saiu de todo o circuito (formulário, request, service, tela, manifesto, `/validar`,
  `config('signature.location')`, `SIGNATURE_LOCATION`). Migration `2026_10_11_100200` derruba a coluna. Não recrie.
- O que identifica o atendimento são os usuários: `signature_documents.created_by_name` ("Gerado por" na tela,
  no manifesto, no relatório gov.br e no `/validar`), `signature_requests.created_by_name` (QR),
  `signature_govbr_invites.sent_by_name` (convite) e, no reenvio da via, `SendSignatureCopy($id, $userId, $userName)`
  grava `actor_type = user` e `payload.reenvio_pedido_por` no evento `copy_sent`.

## Falhas ao salvar no tablet (08/10/2026)

- `api()` do quiosque: erro de rede vira "Sem resposta do servidor…"; resposta não-ok sem `error` no JSON vira
  "Falha na comunicação com o servidor (código N)". Respostas do Lara sempre levam `error` (SignatureSessionException).
- `SignatureCaptureService::capture` confere o retorno de cada `put` (disco `local` tem `throw => false`): falhou →
  apaga o já gravado, `Log::error` e `SignatureSessionException(…, 500)`. Teste: `test_evidencia_que_nao_grava_recusa_a_assinatura`.

## Quem responde cada campo é do documento (08/10/2026)

- O modelo NÃO marca mais "perguntar a quem assina": declara os campos (+ `question` opcional). No preenchimento,
  cada campo tem "Perguntar ao signatário" (`ask_signer[]` no form) → `signature_documents.signer_field_keys`.
- Leia sempre do documento: `$document->fieldDefinitions()/attendantFields()/signerFields()/automaticFields()`.
  No modelo só existem `manualFields()` e `automaticFields()`. `SignatureDocumentRenderer::body($document)` e
  `missingVariables($document)` recebem o documento.
- Migration `2026_10_11_100300` backfill: documentos existentes herdam o `ask_signer` legado do JSON do modelo.
- Efeito: qualquer modelo pode ir ao gov.br, se o atendente não marcar nenhum campo para o tablet (o visto, se houver, é dispensado no documento ao preparar — 20/10/2026).
- **Título do campo na web = pergunta do tablet (13/10/2026):** `field.blade.php` mostra `$campo['question']`
  (que já cai no rótulo quando está em branco — `SignatureTemplate`); o rótulo vai miúdo embaixo só se for
  diferente. Não volte a mostrar o rótulo como título. `data-fill-label` segue com o rótulo (palpite do dado).

## Throttle por rota (08/10/2026)

- `throttle:N,M` é contado POR ROTA (`App\Http\Middleware\ThrottleRequestsPerRoute`, alias em `bootstrap/app.php`).
  Antes, um contador só por IP/usuário: o batimento do tablet (`/sessao` a cada 5 s) estourava o `/assinar` (10/min) → 429.
  Ao criar rota nova com polling, o limite dela não afeta mais as outras.

## Tamanho do traço no PDF (08/10/2026)

- `SignatureDocumentRenderer::signatureImages()` devolve `{src, width, height}`: PNG recortado (`PngTrimmer`) e
  encaixado em 280×100px (`SIGNATURE_BOX_*`); `signature-area.blade.php` põe o tamanho no `style`. Antes era a tela
  inteira com `height: 68px` (traço minúsculo). O PDF enviado pronto (`SignaturePdfStamper`) já recortava.

## Revisão interna (08/10/2026)

- `SignatureReviewService` (`blockReason`, `review`), `SignatureReview` (tabela `signature_reviews`),
  `signature_documents.review_status` (null|ok|issues) + `reviewed_at`, `signature_templates.review_items`.
- Regras: só `finalized`; a partir de `finalized_at`+1 dia 00:00; não quem acompanhou (`involvedUserIds()`: criador,
  QR, convite e conferência gov.br). `assinatura.revisar-coordenacao` dispensa as duas e grava `early`/`own`.
- "ok" exige todos os itens; "issues" exige observação e mantém na fila; "ok" encerra. Evento `reviewed` sem a observação.
- Fila `GET assinatura/revisao` (`ReviewController@index`), registro `POST .../{doc}/revisao` (aba `?aba=revisao`).
  Quem revisa vê o documento (`viewAny` inclui as duas permissões). Teste: `SignatureReviewTest`.

## Termo de Menores (08/10/2026)

Autorização de entrada de menor em evento, assinada pelo **próprio sócio** num tablet de autoatendimento.
Doc completa: [docs/funcionalidades/termo-de-menores.md](../funcionalidades/termo-de-menores.md).

- **Tablet:** `/assinatura/kiosk/menores` = a MESMA view `quiosque.index` com `$modo = 'menores'`. Telas
  novas em `quiosque/partials/menores-telas` + `menores-js` (incluídos dentro do `@verbatim`/IIFE com
  `@endverbatim … @verbatim`). Ganchos no JS do quiosque, todos atrás de `CFG.menores`: `prefixoEntrada`/
  `rotaEntrada`/`aoEntrar` (no modo menores o QR PAREIA o tablet), `identity_confirmed` pula a identidade,
  via automática, `menoresTelaVerde` no sucesso, `menoresDepoisDoAtendimento` no fim. O balcão não muda.
- **Fluxo** (`MinorTerms\MinorTermService`): título → adultos (18+) → CPF **completo** (RateLimiter por
  tablet+título, 5/15 min) → menores do título com sobrenome em comum (`MinorTermRules`) → `createDocument`:
  `SignatureDocumentService::create` + `freeze` + `SignatureMinorAuthorization` + `issue` →
  `SignatureRequestService::consumeIssued` (novo; abre a sessão sem QR) → `identity_confirmed_at` +
  evento `identity_confirmed` (`modo = cpf_autoatendimento`). Estado do fluxo em `Cache`, chave por tablet,
  só ids; MultiClubes relido a cada passo.
- **Resposta de sessão comum:** trait `Signature\Concerns\RespondsWithKioskSession` (saiu do
  `QuiosqueController`): `sessionPayload` (agora com `rules.identity_confirmed`), cookie `lara_sign`.
- **Pareamento:** `KioskDeviceService` + `SignatureKioskDevice` (QR `LARA-PAIR:v1:`, 5 min, uso único;
  cookie `lara_minor_device` 12 h, path `/assinatura/kiosk/menores`; só hashes no banco). Middleware
  `EnsureMinorTermDevice` (alias `signature_minor_device`) → 401 `device_unpaired`.
- **Termo do evento:** `SignatureMinorTerm` (raiz do modelo + `starts_on`/`ends_on`, um ativo por vez —
  `overlapping()`); o documento usa `currentTemplate()`. Modelo validado por
  `MinorTermFields::templateProblems` (campos Texto com as chaves do importador de Word, sem campo
  obrigatório órfão, foto obrigatória, sem código por e-mail, sem visto, sem partes). Dado ausente e
  pulado = "não informado" (`MinorTermFields::data`).
- **Autorizado** = documento `signed`/`finalized` (`SignatureMinorAuthorization::scopeAuthorized`).
  Gerar de novo para o mesmo menor/termo cancela o documento aberto anterior.
- **Fora da revisão:** `SignatureDocument::isMinorTerm()` em `awaitsReview`, `reviewStatusLabel`,
  `SignatureReviewService::blockReason`; `ReviewController` filtra com `whereDoesntHave('minorAuthorization')`.
  Por isso a migration nova entrou no `CreatesSignatureSchema` (sem ela a fila de revisão quebra nos testes).
- **MultiClubes:** `MinorTerms\MinorTermMemberDirectory::title()` — lança `MinorTermDirectoryUnavailable`
  (não devolve vazio como a busca do atendente). Nos testes, substitua no container.
- **Modelo do OKTOBERPET 2026:** `MinorTermTemplateSeeder` (idempotente, fora do deploy).
- Testes: `SignatureMinorTermKioskTest`, `SignatureMinorTermPanelTest`, `Unit\MinorTermRulesTest`.
## Assinatura Diretoria (20/10/2026)

Diretor do clube assina no tablet do balcão entrando com **matrícula + PIN do Lara** e vê a lista dos documentos
pendentes dele. Doc completa: seção "Assinatura Diretoria" de `docs/funcionalidades/assinatura-eletronica.md`.

- **Áreas e mandatos:** `SignatureDirectorArea` (`signature_director_areas`) e `SignatureDirectorAreaHolder`
  (`signature_director_area_holders`, `role` = `primary`|`substitute`, vigente = `ended_at` nulo; titular novo
  encerra o anterior; não se edita nem apaga). Regras em `Services\Signature\Directors\DirectorAreaService`
  (elegível = ativo + matrícula + setor Diretoria via `User::isDirector()`; CPF de `users.cpf` ou do funcionário).
  `users.cpf` se edita em **Usuários → Editar/Novo** (`UserController::cpfRules`: só dígitos, válido, único) —
  campo criado em 21/10/2026; antes não havia tela que gravasse a coluna. Teste: `UserCpfTest`.
- **Decisões do usuário:** login por **matrícula + PIN** (`users.pin`), não senha do painel nem `approval_password`;
  **sem ordem**; identidade = PIN (sem CPF/código), **foto sempre obrigatória** (`SignatureSigner::requiresPhoto`);
  e-mail de aviso **sem link**; **o atendente escolhe a pessoa (titular ou substituto) ao criar o documento** —
  o original imprime nome e CPF dela; se faltar, cancela e emite outro (não troque signatário de congelado).
- **No documento:** `signers[i][director]` = "área:usuário"; `StoreSignatureDocumentRequest::prepareForValidation`
  resolve no servidor (`DirectorAreaService::signerData`) e descarta nome/CPF/`user_id`/`director_*` do formulário.
  Signatário ganha `user_id`, `director_area_id`, retrato `director_area_name`/`director_role`, `director_notified_at`
  (`isDirector()`, `directorLabel()`, `capacityLabel()`). O `role` continua `signer` (enum intocado).
  Parte do modelo pode ter `director_area_id` (JSON `parties`): o formulário já traz o titular.
- **Congelar:** prazo `signature.directors.ttl_days`; job `NotifySignatureDirector` (fila, `afterCommit`) manda
  `SignatureDirectorPendingMail`, grava `director_notified_at` e o evento `director_notified`.
- **Tablet:** `DirectorKioskService` — limite por matrícula (`RateLimiter`, 15 min), sessão do diretor em `Cache`
  (hash do cookie `lara_director`), lista = pendentes do diretor, aguardando, no prazo e **sem `govbr_sent_at`**;
  `open` faz `issue` + `consumeIssued`, grava `identity_confirmed_at` e `signature_requests.identity_method =
  matricula_pin` (o manifesto imprime "Matrícula e PIN do Lara" e "aberto pelo próprio diretor"). JS em
  `quiosque/partials/diretoria-telas` + `diretoria-js` (só no modo balcão; gancho `diretoriaDepoisDoAtendimento`
  em `encerraAtendimento`, `diretoriaAtiva()` nos textos).
- **Sem QR para diretor** (21/10/2026): `SignatureSigner::releaseBlockReason($directorLogin)` recusa liberar diretor
  pelo atendente; só a liberação com `identity_method = matricula_pin` (gravado na emissão:
  `SignatureRequestService::issue(..., $identityMethod)`) passa — na emissão, na leitura (`open`) e no middleware
  `EnsureSignatureKioskSession`. `partials/release` tira o diretor da lista "Quem vai assinar agora".
- **gov.br:** documento preparado sai da lista; o diretor assina pelo mecanismo do gov.br (CPF dele).
- **Diretoria depois de quem é de fora dela (26/10/2026):** com signatário não-diretor pendente, o documento NÃO chegou
  à diretoria — fora da lista do tablet (`pending` usa `SignatureDocument::scopeReleasedToDirectors`), não abre
  (`SignatureSigner::releaseBlockReason` diz de quem espera), sem e-mail de aviso, sem convite gov.br ao diretor
  (`GovbrInviteService::blockReason`) e fora do filtro "Aguardando diretoria". Fonte única:
  `SignatureDocument::pendingNonDirectors()` / `releasedToDirectors()`. O aviso: `NotifySignatureDirector::forReleased`
  (no `freeze` e no fim de cada `capture` de não-diretor que não fechou o documento); o job também confere e sai
  sem marcar nada se ainda falta alguém. Painel: o cartão do diretor diz "Vai para a diretoria depois da assinatura de…".
- **Assinatura em lote (26/10/2026):** na lista o diretor marca vários (só os sem perguntas a responder —
  `DirectorKioskService::batchable`; o visto não impede) e toca "Assinar selecionados": lê um por um ("Documento 1 de N"),
  um aceite, um traço, um visto (se algum pedir), uma foto, e o servidor grava **uma captura por documento** (mesmo
  `SignatureCaptureService::capture`, evento `signed` com `em_lote` = N; leitura com `em_lote` no `viewed`). Sem via
  por e-mail e sem recusa no lote (recusa é de um documento: abre-se sozinho). Rotas `quiosque.diretoria.batch.*`
  (`lote`, `lote/{signer}/pdf`, `lote/{signer}/visualizado`, `lote/assinar`, `lote/cancelar`), todas pelo cookie
  `lara_director` — sem `lara_sign` e sem batimento. Nada no cache: a liberação viva de cada documento é achada no
  banco (`batchRequest`: do diretor, `matricula_pin`, consumida, no prazo). Abrir é tudo-ou-nada (cancela o que abriu);
  assinar é documento a documento (o que falhar volta no resultado). Máximo `BATCH_MAX` = 20. Um marcado só = fluxo avulso.
- **"Já assinados" (26/10/2026):** aba na lista do tablet (`signedPayload`: últimos 90 dias, até 50) com a situação
  (falta quem, gerando via final, concluído, recusado…). Só leitura, sem PDF.
- **Painel:** tela Assinaturas → Diretoria (áreas, mandatos, histórico, pendentes de quem saiu do mandato); filtro
  "Aguardando diretoria" (`status=diretoria`) na lista de documentos; aviso no cartão do signatário-diretor.
- Config: `SIGNATURE_DIRECTOR_TTL_DAYS`, `SIGNATURE_DIRECTOR_SESSION_MINUTES`, `SIGNATURE_DIRECTOR_MAX_LOGIN_ATTEMPTS`,
  `SIGNATURE_DIRECTOR_IDLE_SECONDS` (inatividade da lista no tablet; vai à tela como `CFG.diretoria.ociosoSegundos`).
- Testes: `SignatureDirectorTest` (usuários reais com `SharesSqliteWithUserConnection` + `MigratesAccessSchema`).

## Assinatura remota (21/10/2026)

Link por e-mail → tela de quem assina no **site externo** (Next.js na DMZ, o mesmo das reservas), sem
login. Doc completa: seção "Assinatura remota" de `docs/funcionalidades/assinatura-eletronica.md`.
Prompt do site: `docs/funcionalidades/assinatura-remota-prompt-site.md`. **Mudou a API → atualize o
prompt e gere um novo para o agente do site** (regra do CLAUDE.md).

- **Decisões do usuário:** CPF **completo** sempre (qualquer regra do modelo), 5 tentativas **no link**
  (somadas entre aberturas; a quinta bloqueia), link de **72 h**, **qualquer documento** (sem opção no
  modelo), **foto sempre obrigatória**. Diretoria e Termo de Menores fora. O Lara segue fechado: o
  navegador fala com o site; o servidor do site chama a API.
- `RemoteSignatureService`: `send` (substitui o anterior, estende `expires_at` do documento até o fim do
  link, e-mail síncrono na transação), `revoke`, `revokeForDocument` (chamado pelo
  `GovbrCheckService::prepare`), `resolve`/`summary` (página de entrada), `open` (emite **e** consome uma
  `SignatureRequest` com `identity_method = link_email` + `signature_remote_link_id`; sessão de
  `remote.session_minutes`; abrir de novo derruba a anterior), `confirmIdentity` (leva as tentativas ao
  link), `sign`/`refuse` (marcam o link `used`).
- **CPF antes do documento:** na API, `pdf`, `respostas`, `visualizado` e `anexos` pedem a identidade
  confirmada (409 `identity_required`). O tablet não tem essa trava (o atendente vê quem assina).
- **Anexos pela pessoa** (decisões do usuário, 21/10): `GET/POST /anexos`, `DELETE /anexos/{id}`.
  `SignatureAttachmentService::store(..., via: $request, context: ...)` grava
  `uploaded_by_signer_id` e o nome "Fulano (signatário, pelo link)"; `removeOwn` só para o que ela
  enviou e antes de assinar. **No remoto o obrigatório trava a assinatura**
  (`RemoteSignatureService::sign`, 409 `attachments_missing`); no balcão continua travando só a conclusão.
  `RemoteSignatureService::attachments()` monta a lista (vem também no estado da sessão, depois do CPF).
  Itens são do documento, não por signatário.
- Modelo `SignatureRemoteLink` (`signature_remote_links`, só o sha256 do token; vencido = `active` com
  `expires_at` passado, `isExpired()`), status `active|used|revoked|superseded|locked`.
- Ganchos no código comum, todos por `SignatureRequest::isRemote()`: `SignatureRequestService::issue`
  (não derruba o QR do tablet, sem evento `qr_issued`), `open` (sem a faixa de IP do clube, evento
  `remote_opened`), `SignatureCaptureService::confirmIdentity` (modo `full`, "peça um novo link"),
  `capture` (foto obrigatória, sem `camera_unavailable`), `SignatureSigner::requiresPhoto($via)`,
  `RespondsWithKioskSession::sessionPayload` (`pdf_url` nulo, `pdf_version`, `identity_check = full`),
  `EnsureSignatureKioskSession` (sessão remota não abre o tablet), manifesto (texto e origem "Link remoto").
- A recusa saiu do `QuiosqueController` para `SignatureCaptureService::refuse` (comum aos dois).
- API: middlewares `signature_remote_api` (Bearer `SIGNATURE_REMOTE_API_TOKEN`, origem
  `SIGNATURE_REMOTE_ALLOWED_IPS`, contexto de quem assina de `X-Signer-Ip`/`X-Signer-User-Agent` —
  **nunca use `$request->ip()` nessa API**, é o do site) e `signature_remote_session`. Limitadores
  nomeados `signature-remote`/`signature-remote-open` por IP de quem assina + rota. Recusas
  `{error, reason}` (`SignatureRemoteLinkException::reason`).
- `App\Support\IpRange` (saiu do `SignatureRequestService`): faixas de IP do tablet e do site.
- Painel: cartão `partials/remote.blade.php`, só com `SIGNATURE_REMOTE_SITE_URL`; o status do
  acompanhamento leva `request.remote` ("Assinando a distância, pelo link").
- Config `signature.remote.*` e `SIGNATURE_REMOTE_*` no `.env.example`. Migration `2026_10_21_120000`
  (no `CreatesSignatureSchema`). Testes: `SignatureRemoteLinkTest`.

## Contratos (27/10/2026)

Contrato redigido no Lara em cláusulas, validado pela Gerência e, aprovado, transformado em documento de
assinatura **em rascunho** (sem congelar). Doc completa: [docs/funcionalidades/contratos.md](../funcionalidades/contratos.md).
Guia do usuário: seção 13.

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
- **Numeração não se guarda:** é calculada da posição. Remissão é `[[ref:uid]]` (uid de cláusula/item). Ao copiar
  cláusula (biblioteca, modelo, aditivo), `ContractContent::freshUids` dá uid novo e troca as remissões internas.
- **Documento gerado:** uma parte por signatário (`p1…`, marcadores `[[assinatura:pN]]` vizinhos), presidente
  primeiro (diretor), `requires_initials`, data `[[data_da_assinatura]]` (`DATE_SIGNING_LONG`), `created_by` = quem
  redigiu. Diretor sem mandato vira aviso e fica de fora (o `freeze` cobra a parte). Gerado DEPOIS do commit da
  aprovação; falhou ou foi cancelado → botão "Gerar documento de assinatura".
- **Reabrir** só enquanto o documento não foi congelado (cancela o rascunho dele; o número fica).
- **Menu** usa só `P::ASSINATURA_CONTRATOS` (a Gerência tem acesso total). Nada de Gate de cargo no menu.
- `User::isManagementMember()` e `User::sectorIds()` (memorizados) — mocke nos testes.
- **CSS:** as telas usam classes Tailwind novas; em dev, `npm run build` depois de puxar (sem isso o editor sai em
  colunas de 1/5). O deploy já roda o build.
- Migration `2026_10_29_100000`. Config `signature.contracts.*` / `SIGNATURE_CONTRACT_*`. Testes:
  `SignatureContractTest`, `Unit\NumeroPorExtensoTest`, esquema `CreatesContractSchema`.
- **Validação no tablet do balcão** (decisão do usuário): botão "Validação de contratos (Gerência)" na espera do
  quiosque (`CFG.gerencia`, só no balcão) → página própria `/assinatura/kiosk/gerencia` (`quiosque/gerencia.blade.php`).
  Matrícula + PIN, cookie `lara_management`, `ContractKioskService` (desenho do `DirectorKioskService`: hash no cache,
  limite por matrícula, setor Gerência reconferido a cada chamada). Lê, comenta tocando na cláusula, aprova/devolve
  pelo `ContractService`; não edita texto. Rotas `quiosque.gerencia.*`. Testes: `SignatureContractKioskTest`.
- **Revisão automática pelo Claude** (Gerência, 27/10/2026): cartão na tela do contrato manda o texto inteiro ao
  Claude Haiku 5.5 (`ContractAiReviewer` → `App\Services\Claude\ClaudeClient`, SDK `anthropic-ai/sdk`) e guarda
  veredito, resumo e apontamentos por cláusula em `signature_contract_ai_reviews` (mesmo texto = mesma revisão,
  pelo `sha256`). "Levar para a observação" preenche o formulário de devolução. Só orienta. Sem `ANTHROPIC_API_KEY`,
  some. Rota `signature-contracts.ai-review`, policy `aiReview`. Testes: `SignatureContractAiReviewTest`.
  Ver [claude-api.md](../funcionalidades/claude-api.md).
- Não mexe em API externa nossa: nenhum prompt para outro agente (a Anthropic é serviço de terceiro).

## Pendências (atualizado em 09/10/2026)

Em aberto, por ordem de impacto. Ao resolver uma, tire daqui e atualize a doc completa.

Resolvidas em 08/10/2026: lacre instalado em produção (e-CNPJ A1 do clube, AC SAFEWEB RFB v5, `.pfx`
convertido do formato RC2 antigo e com a cadeia embutida, em `storage/certificates/clube.pfx`) e PDF
lacrado conferido no `validar.iti.gov.br`: "Assinatura aprovada", classificada como **assinatura
eletrônica qualificada**, sem queixa da falta de política ICP-Brasil (DOC-ICP-15).
Resolvida em 09/10/2026: QR, rodapé, linha carimbada e botão da via apontam para o `validar.iti.gov.br`
quando o PDF final é lacrado ou assinado pelo gov.br (`SignatureValidationLink`); o original segue no `/validar`.

| # | Pendência | Situação / o que fazer |
|---|---|---|
| 1 | **Carimbo de tempo (ACT) não contratado** | Decisão do usuário (08/10): seguir **só com o `.pfx` A1** por enquanto. O lacre sai sem carimbo, com a hora do servidor. Sem carimbo, depois que o A1 vencer (1 ano), um validador pode marcar o lacre como não verificável. Ao contratar uma ACT credenciada na ICP-Brasil (em geral a própria certificadora vende): preencher `SIGNATURE_TSA_URL`/`USER`/`PASSWORD`, `config:cache`, `signature:seal-check`. Não muda código |
| 2 | **Sem aviso de vencimento do A1** (e-CNPJ do clube vence em **21/07/2027**) | Vencido, a finalização **falha** (de propósito) e os documentos param em "Assinado". Falta um aviso com antecedência (ex.: o `signature:seal-check` agendado avisando 30 dias antes). Hoje: anotar a data que o `seal-check` mostra |
| 3 | **Âncoras com prazo** | Raiz ICP-Brasil v5 vence em **03/2029**; a cadeia do gov.br, em **06/2033**. Trocar ou acrescentar antes (`resources/certs/`) |
| 4 | **Flag `SIGNATURE_GOVBR_ENABLED`** (estava no plano do gov.br) | Não criada: pergunta em aberto ao usuário. Hoje a aba existe sempre |
| 5 | **Tablet → gov.br no mesmo documento** | Não feito. Seria viável com um PDF intermediário com os traços do tablet; hoje é proibido nos dois sentidos |
| 6 | **Teste de certificado vencido na hora da assinatura** | Fora da suíte: a extensão OpenSSL não emite certificado no passado |
| 7 | Retenção sem exclusão física; via por WhatsApp desligada | Conhecidos e de propósito (ver doc completa) |
