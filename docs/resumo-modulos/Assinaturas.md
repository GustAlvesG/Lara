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
   5 min para ler, 15 min de sessão. Regerar torna o anterior `superseded`. Um QR por signatário, **em sequência**.
4. **Tablet** (`QuiosqueController` + `resources/views/quiosque/index.blade.php`): formulário de perguntas
   (se houver) → leitura do PDF → identidade (`Cpf::matches`, 5 tentativas) → aceite (+ autorização de imagem
   se pede foto) → traço (≥ 30 pontos) → visto (se exigido) → foto. Gravação numa transação só
   (`SignatureCaptureService`).
5. **Finalizar** (job `FinalizeSignatureDocument`, idempotente): PDF final re-renderizado do mesmo snapshot,
   traços no lugar, manifesto, `final_sha256`. Depois `SendSignatureCopy` (e-mail) e `ArchiveSignatureDocument` (FTP).

**Sem `queue:work` o documento para em "Assinado"** — evidência gravada, mas sem PDF final.

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
| `app/Http/Middleware/EnsureSignatureKioskSession.php` | Alias `signature_kiosk`: resolve o cookie, confere prazo, amarra ao documento (outro id → 403 + evento). |
| Models | `SignatureTemplate` (versionado por linha), `SignatureDocument`, `SignatureSigner`, `SignatureRequest`, `SignatureEvidence`, `SignatureAuditEvent` (recusa update/delete com exceção), `SignatureLayout` (versionado). |
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
