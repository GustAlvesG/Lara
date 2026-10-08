# Assinaturas — resumo para quem vai mexer

Documentação completa (regras, garantias, configuração, modo sem HTTPS): [docs/funcionalidades/assinatura-eletronica.md](../funcionalidades/assinatura-eletronica.md).
Guia do usuário: dentro do sistema, em **Assinaturas → Guia** (`resources/views/signature/guide/content.blade.php`
gera a página e o PDF — mudou o fluxo, mude o guia).

## Em uma frase

Assinatura eletrônica **avançada** (Lei 14.063/2020, sem ICP-Brasil) num **tablet do balcão**: o atendente
prepara e **congela** um documento no computador, gera um **QR de uso único** por signatário, o tablet
(`/assinatura/kiosk`, público) lê o QR, mostra o PDF, confere a identidade, colhe aceite, traço, visto e
foto; a fila monta o PDF final com **manifesto** e QR de validação pública (`/validar/{codigo}`).

> **Não é o `/kiosk` dos freelancers.** Aquele tem login de operador (matrícula + PIN) e mora no módulo
> Freelancers (`app/Http/Controllers/Freelancer/KioskController.php`). O item "Assinatura (Tablet)" do menu
> de Freelancers é ele, não este módulo.

## Menu e permissões

Menu **Assinaturas** (`app/View/Navigation.php`): Documentos, Modelos, Guia.

| Permissão (`App\Authorization\Permissions`) | Uso |
|---|---|
| `assinatura.modelos` (`ASSINATURA_MODELOS`) | Modelos e papel timbrado |
| `assinatura.documentos` (`ASSINATURA_DOCUMENTOS`) | Criar, congelar, liberar, cancelar |
| `assinatura.consultar` (`ASSINATURA_CONSULTAR`) | Ver assinados e baixar PDF |
| `assinatura.evidencias` (`ASSINATURA_EVIDENCIAS`) | Ver foto e traço do signatário |

- Gates compostos em `AppServiceProvider`: `acessar-documentos-assinatura` (documentos **ou** consultar) e
  `acessar-guia-assinatura` (qualquer das três). O menu usa Gate, nunca método de model.
- O recorte fino por documento (editar só rascunho, ver evidência, baixar) é da `SignatureDocumentPolicy`.
- Permissão nova: só no catálogo (`CATALOG`); o `PermissionCatalogSeeder` cria no deploy, sem setor.
  (A migration `2026_09_22_120600` é histórica — não repita o padrão.)

## Rotas (`routes/web.php`)

| Prefixo / nome | Lado | Observação |
|---|---|---|
| `assinatura/documentos` → `signature-documents.*` | atendente (`auth`) | `novo`, `enviar` (PDF pronto), `associados` (busca) declaradas **antes** de `/{signatureDocument}` |
| `.../{doc}/congelar`, `cancelar`, `posicoes`, `pdf` | atendente | `DocumentController` |
| `.../{doc}/status`, `signatarios/{s}/liberar`, `signatarios/{s}/via`, `liberacoes/{r}` (DELETE) | atendente | `ReleaseController`; `status` é polling (não há broadcasting) |
| `.../{doc}/govbr/preparar`, `.../{doc}/govbr` (POST), `.../{doc}/govbr/signatarios/{s}/convite`, `.../{doc}/govbr/{conferencia}/pdf` → `signature-documents.govbr.*` | atendente | `GovbrController`: aba **Assinatura gov.br** (`?aba=govbr`); preparar, enviar e convidar pedem `checkGovbr` (`assinatura.documentos`) |
| `assinatura/modelos` → `signature-templates.*` | `assinatura.modelos` | `PUT` cria **nova versão**; `importar-docx` converte e não grava |
| `assinatura/papel-timbrado` → `signature-layout.*` | `assinatura.modelos` | Cabeçalho/rodapé versionados |
| `assinatura/guia` → `signature-guide.*` | `acessar-guia-assinatura` | Página e PDF |
| `assinatura/kiosk` → `quiosque.*` | **público** | `consumir` (throttle 10/min) dá o cookie `lara_sign`; o resto passa pelo middleware `signature_kiosk` |
| `/validar/{codigo}` → `signature.validate(.verify)` | **público** | Mostra pouco; PDF enviado só é hasheado e descartado |

`/quiosque` redireciona para `/assinatura/kiosk`; os **nomes** `quiosque.*` ficaram. Mudou rota → refaça o
`route:cache` (um nome que não resolve no menu derruba todas as telas).

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
   automática). Gravação numa transação só
   (`SignatureCaptureService`).
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
  — `signerFields()` do documento — ou modelo com visto).
- **Preparar** (`GovbrCheckService::prepare`): aplica a data automática (refaz original + hash), prazo de
  `signature.govbr.ttl_days`, cancela QR vivo. Baixar o PDF **depois** de preparar.
- **Concluir** (`GovbrCheckService::conclude`), tudo ou nada: documento preparado; assinado sobre o original;
  quem já assinou pelo gov.br continua no arquivo (o próximo assina o arquivo do anterior). **Ordem livre**, mas um de
  cada vez: dois assinando o mesmo arquivo em paralelo → o segundo é recusado pela regra anterior. O motivo de não
  concluir fica em `signature_govbr_checks.conclusion` e aparece na aba.
- **Convite por e-mail** (`GovbrInviteService`, um botão por pendente na aba, qualquer ordem): PDF a assinar anexado
  (`SignatureDocument::govbrFileToSign` — original, ou o arquivo do anterior) + passo a passo. **Sem link**: o Lara
  não é acessível de fora; a pessoa responde ao e-mail (`Reply-To` = atendente que enviou) e o atendente envia o
  arquivo na aba. E-mail **síncrono, dentro da transação** (falhou → nada registrado). `signature_govbr_invites`
  só registra para quem/quando/por quem. A migration `2026_10_10_100300` tirou da homologação as colunas do link e
  o valor `signer` do enum da trilha (uma versão anterior tinha link público).
- `signed_at` = hora declarada na assinatura do gov.br; `signature_signers.govbr_check_id` diz que foi pelo
  gov.br; `signature_documents.govbr_check_id` aponta o arquivo final (o `FinalizeSignatureDocument` o confere
  contra o hash da conferência).

- `app/Services/Signature/Govbr/GovbrSignatureValidator.php` — o núcleo. "É este documento" = o arquivo
  **começa byte a byte** pelo `original` ou `final` do Lara (o gov.br só acrescenta, atualização incremental);
  "pessoa certa" = CPF do otherName `2.16.76.1.3.1` (posições 8–18 dos 45 dígitos) igual ao de um signatário,
  certificado subindo até a raiz em `resources/certs/govbr/cadeia-govbr.pem` (única âncora, vence 2033),
  validade na hora do `signingTime`. Revogação: **não conferida** (`ok = null`, que não aprova nem reprova).
- `Asn1.php` — leitor **BER**: o CMS do gov.br tem comprimento indefinido; não troque por leitor DER nem
  corte os zeros do `/Contents`.
- `GovbrCheckService` grava arquivo (também os recusados) + conferência + evento; o evento não leva nome nem CPF.
- Recusa e "não concluiu" voltam como flash `warning` ("Atenção"), não `error` (que manda procurar a TI).
- Testes: `SignatureGovbrCheckTest` (conferência), `SignatureGovbrSigningTest` (conclusão), `SignatureGovbrInviteTest`
  (convite) e `SignatureGovbrFinalizationTest` (relatório e entrega), com AC de teste. Rode com
  `php -d memory_limit=1G vendor/bin/phpunit --filter Signature` (o `artisan test` não passa o limite ao subprocesso)
  (`Tests\Concerns\BuildsGovbrSignedPdf`). As amostras reais ficam fora do git (`storage/app/govbr-amostras/`,
  com o inspetor da Fase 0) — têm CPF real.
- Não há API do gov.br envolvida: ela é só para órgão público. Nenhuma chamada de rede na conferência.

## Peças

| Arquivo | Papel |
|---|---|
| `app/Services/Signature/SignatureStateMachine.php` | **Única** porta de mudança de status; valida o caminho e grava o evento na mesma transação. Nunca faça `update(['status' => …])`. |
| `SignatureAuditor.php` | Trilha `signature_audit_events` encadeada por hash, só inserção; `verify()` confere a cadeia. |
| `SignatureDocumentService.php` | Criar/editar rascunho, título padrão (`titleFor`), congelar. |
| `SignatureRequestService.php` | Emitir/consumir/cancelar QR e código digitado; sessão do tablet. |
| `SignatureCaptureService.php` | Identidade, aceite, traço, visto, foto, recusa. Recusa assinar com formulário pendente. |
| `SignatureSigningDataService.php` | Respostas do signatário e data automática (`signing_data`); refaz PDF e hash antes da leitura. |
| `SignatureDocumentRenderer.php` | HTML do documento a partir do snapshot; resolve `[[campos]]`, posiciona assinaturas por parte (`placeSignatures`). Fonte única do que o tablet mostra e do que o PDF imprime. |
| `SignatureFieldTypes.php` | Tipos de campo: validação, forma canônica, formatação. Único lugar que sabe o que é CPF/CNPJ válido. |
| `DocxTemplateImporter.php` | `.docx` → `body_html` + campos (`[[Rótulo]]`, `[[assinatura: Parte]]`). |
| `SignaturePdfStamper.php` | Carimba por cima do PDF enviado pronto (FPDI): validação, vistos, assinaturas, folha final. |
| `SignaturePdfSealer.php` | Ponto de troca para lacre real (PAdES não implementado). |
| `SignaturePageGeometry.php` | Margens, cabeçalho, rodapé e faixa do visto — CSS e desenho do visto leem daqui. |
| `SignatureArchiver.php` + `App\Support\ArchivePath` | Cópia no FTP: tipo → pessoa, nome com data e código. |
| `SignatureQrCode.php` | QR do manifesto no servidor (`bacon/bacon-qr-code`). |
| `Govbr/GovbrSignatureValidator.php`, `Govbr/GovbrCheckService.php`, `Govbr/GovbrInviteService.php`, `Govbr/Asn1.php` | Assinatura pelo gov.br — ver a seção acima. |
| `app/Http/Middleware/EnsureSignatureKioskSession.php` | Alias `signature_kiosk`: resolve o cookie, confere prazo, amarra ao documento (outro id → 403 + evento). |
| Models | `SignatureTemplate` (versionado por linha), `SignatureDocument`, `SignatureSigner`, `SignatureRequest`, `SignatureEvidence`, `SignatureAuditEvent` (recusa update/delete com exceção), `SignatureLayout` (versionado), `SignatureGovbrCheck` (conferências gov.br). |
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
- **Trilha não vaza dado:** eventos guardam chaves respondidas, nunca valores; CPF mascarado no manifesto e
  na validação; nome de quem gerou documento/QR é retrato (`created_by_name`).
- **Disco privado:** `SIGNATURE_DISK` nunca `public`/`placar`; imagens e PDFs servidos por rota com policy.
- **Validação pública:** código inexistente e rascunho respondem igual ("não encontrado").
- **PDF pronto:** posição da assinatura é fração da página (0–1); editar signatários do rascunho apaga as
  posições; PDF com object/xref streams é recusado (limite da FPDI livre).
- **QR do atendente:** fundo `bg-white` fixo e cores fixas no `QRCode` (`partials/release.blade.php`). Não troque
  por token do tema (`bg-surface`): no escuro o código fica ilegível para a câmera. `SignatureReleaseQrThemeTest` trava isso.
- **WhatsApp não entrega a via** de propósito (a Poli responde 200 sem entregar). Não ligue sem confirmação real.
- **Visto** é desenhado via `page_script` do dompdf, fora do manifesto — por isso o PDF final com visto é
  montado duas vezes.

## Comandos e agenda (`routes/console.php`)

- `signature:expire` — a cada minuto: QR não lido, sessão parada, documento esquecido.
- `signature:archive` — de hora em hora: reenvia cópias pendentes ao FTP; `--testar` só confere a conexão.
  Desligado por padrão (`SIGNATURE_ARCHIVE_ENABLED=false`).
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
- Efeito: qualquer modelo sem visto pode ir ao gov.br, se o atendente não marcar nenhum campo para o tablet.

## Throttle por rota (08/10/2026)

- `throttle:N,M` é contado POR ROTA (`App\Http\Middleware\ThrottleRequestsPerRoute`, alias em `bootstrap/app.php`).
  Antes, um contador só por IP/usuário: o batimento do tablet (`/sessao` a cada 5 s) estourava o `/assinar` (10/min) → 429.
  Ao criar rota nova com polling, o limite dela não afeta mais as outras.
