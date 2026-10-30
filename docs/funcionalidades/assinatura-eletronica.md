# Assinatura Eletrônica Presencial (tablet do balcão)

## O que é

Termos de responsabilidade, fichas cadastrais e contratos de locação de espaço passam a ser
assinados num **tablet no balcão de atendimento**, em vez de impressos. O atendente prepara o
documento no computador, mostra um **QR Code** na tela, o associado lê esse código com o tablet,
lê o documento, confirma quem é, aceita os termos e assina com o dedo.

Quem não vem ao balcão assina por um **link enviado por e-mail**, numa tela do site do clube (ver
[Assinatura remota](#assinatura-remota-link-por-e-mail)), ou pelo gov.br.

O nível jurídico alvo é a **assinatura eletrônica avançada** da Lei 14.063/2020: ela não usa
certificado ICP-Brasil, e sim **evidências** que ligam a assinatura àquela pessoa e àquele
conteúdo — identidade conferida, aceite explícito, traço da mão, foto, hora do servidor, IP e
uma trilha de auditoria encadeada.

> **Não confundir com o `/kiosk` dos freelancers.** São dois tablets e dois assuntos, e os
> endereços são parecidos: o deste módulo é **`/assinatura/kiosk`**. Lá o **operador** entra com
> matrícula e PIN e atende vários contratos seguidos; aqui o tablet não entra em lugar nenhum —
> ele lê um QR que libera **um** documento para **uma** assinatura e volta à tela de espera.
>
> O endereço antigo, `/quiosque`, redireciona para o novo. Os nomes das rotas continuam
> `quiosque.*` — são internos.

## Para quem

| Perfil | O que faz | Permissão |
|---|---|---|
| Gerência / jurídico | Escreve e revisa os modelos de documento | `assinatura.modelos` |
| Atendimento | Cria documentos, congela e libera para o tablet | `assinatura.documentos` |
| Consulta | Abre documentos assinados e baixa o PDF | `assinatura.consultar` |
| Auditoria | Vê a foto e o traço do signatário | `assinatura.evidencias` |
| Revisão | Revisa os documentos assinados: do dia seguinte em diante, só os que não acompanhou | `assinatura.revisar` |
| Coordenação da revisão | Revisa antes do prazo e os próprios documentos — **dê em Setores só aos coordenadores** | `assinatura.revisar-coordenacao` |
| Cadastro da diretoria | Cadastra as áreas da diretoria e quem assina por cada uma (titular e substitutos) — ver [Assinatura Diretoria](#assinatura-diretoria) | `assinatura.diretoria` |
| Quem redige contratos | Redige contratos do próprio setor, que a Gerência valida e que viram documento de assinatura — ver [Contratos](contratos.md) | `assinatura.contratos` (validar: setor Gerência) |

As quatro permissões são criadas no banco pelo seed padrão `PermissionCatalogSeeder`, que o
script de deploy roda. Elas nascem **sem setor**: quem as recebe é configurado na tela de
Setores (ou na de Usuários, para um caso nominal). Gerência, Diretoria e TI as alcançam por
serem setores de acesso total.

São quatro e não uma porque são quatro acessos de peso diferente: escrever o texto de um termo é
ato jurídico, atender no balcão é operação, e ver a foto de uma pessoa não é nem uma coisa nem
outra. São permissões do catálogo de acesso (`App\Authorization\Permissions`): os setores de
acesso total (Gerência, Diretoria, TI) já as têm, e quem mais precisar recebe pela tela de
Setores (o setor inteiro ou só os coordenadores) ou, individualmente, pela tela de Usuários.

## A regra central: liberação única por QR Code

O tablet **não é pareado** e não guarda token de longa duração. Cada documento exige uma
liberação nova:

1. O atendente clica em **Liberar para assinatura**; a tela mostra um QR exclusivo daquele
   signatário, com contagem regressiva.
2. O tablet lê o código e abre **somente aquele documento**.
3. Terminado o fluxo — assinado, recusado, cancelado ou expirado — a sessão morre e o tablet
   volta à tela de espera.

O que sustenta isso:

| Garantia | Como |
|---|---|
| Token imprevisível | 64 caracteres via `Str::random` (que usa `random_bytes`) |
| Token nunca em claro | O banco guarda só o `sha256` — quem lê o banco não abre sessão |
| Uso único | A primeira leitura consome; a segunda é recusada **e auditada** (`qr_reuse_blocked`), no mesmo aparelho ou em outro |
| Prazo curto | 5 min para ler o QR; 15 min de sessão depois de lido (configurável) |
| Sessão amarrada | O cookie vale para **aquele** documento; outro id responde 403 e vira evento |
| Regeneração | O QR anterior vira `superseded` e para de valer |
| Cancelamento | Derruba o tablet na requisição seguinte |
| Formato próprio | O conteúdo é `LARA-SIGN:v1:<token>`; o leitor **ignora** qualquer outro QR e nunca navega para URL lida |
| Rede | Faixas de IP opcionais para o consumo (`SIGNATURE_ALLOWED_IPS`) |
| Rate limiting | `throttle:10,1` no consumo — a única rota em que um token pode ser adivinhado |

### Por que um cookie próprio, e não a sessão web

A sessão do tablet é o cookie **`lara_sign`** (httpOnly, `SameSite=Strict`, sem `expires`), cujo
valor também é guardado só como hash. Três razões:

- `SameSite=Strict` pôde ser cravado nele sem mexer na configuração de sessão do sistema inteiro;
- o estado que vale é o do **banco**, então cancelar pelo painel mata a sessão na requisição
  seguinte — é assim que o "tempo real" acontece sem broadcasting;
- o tablet não carrega nada da sessão de quem estiver logado naquele navegador.

## Fluxo do atendente

O **título** de um documento novo é, por padrão, "Modelo - Primeiro signatário"
(`SignatureDocumentService::titleFor`): numa lista de vinte documentos do mesmo modelo, o título
igual em todos não distingue nenhum. Vale enquanto o atendente não escrever outro — título em
branco ou igual ao nome do modelo, que é como o formulário o traz. Título digitado é respeitado;
documento enviado pronto fica de fora (ali o título é sempre digitado); e na correção do rascunho
o título não é refeito.

1. **Escolher o modelo** → informar os signatários, buscando o associado por nome, título ou CPF
   (ou digitando um visitante) → preencher os dados do modelo.

   A tela é dividida em **passos**, um cartão por vez: **Documento** (título) →
   **Signatários** → **Dados do documento**; no envio de PDF pronto, **Arquivo e regras** →
   **Documento** → **Signatários**. "Continuar" só avança com os campos obrigatórios do passo
   preenchidos, e o botão de gravar aparece no último. Na edição do rascunho os passos são livres
   e dá para gravar de qualquer um. Depois de um envio recusado, a tela reabre no primeiro passo
   com erro.

   Os passos são só apresentação (`documents/partials/steps.blade.php`): o formulário continua um
   só, enviado de uma vez e conferido pelo servidor. Um cartão vira passo com `data-step="Nome"`;
   `data-step-keys` diz que erros de validação são dele; a linha de botões da tela leva
   `data-step-actions`. Sem JavaScript, os cartões aparecem todos juntos.

   Cada signatário é um cartão com título — "Signatário 1 - Contratante" —, feito da posição na
   lista e da parte escolhida em **Assina como**, que é o primeiro campo
   do cartão quando o modelo tem partes.

   **A busca de associado** (`DocumentController::members`) olha dois lugares:
   - a tabela local `members`, por nome, título ou CPF — ela só tem quem se cadastrou no
     aplicativo;
   - o **MultiClubes**, quando o termo tem cara de código de título (`SignatureMemberDirectory`):
     traz o **titular e os dependentes** ativos do título, o titular primeiro, marcados como tal
     na lista. Quem está nos dois lugares aparece uma vez, com o vínculo local e o contato do
     aplicativo. Dependente sem CPF no cadastro vem com o CPF em branco, para o atendente digitar.
     Se o MultiClubes não responder, a busca segue só com a tabela local (o erro vai ao log).

   Busca por **nome** ou **CPF** continua só na tabela local: dependente que não usa o aplicativo
   é achado pelo título.

   Os signatários vêm **antes** dos dados porque quase sempre o nome, o CPF e o contato que o texto
   pede são os de quem assina. Os campos de texto, CPF, e-mail e telefone aproveitam isso de dois
   jeitos:
   - a lista **Usar dados do signatário…**, embaixo de cada campo, com os dados de cada pessoa da
     lista que cabem naquele tipo (campo de CPF só oferece CPF; texto oferece os quatro);
   - o botão **Preencher com os dados dos signatários**, que preenche de uma vez os campos
     **vazios** cujo rótulo deixa claro de quem é o dado: "Nome do contratante" recebe o nome de
     quem assina como Contratante; "Nome", "CPF do associado" recebem os do primeiro signatário.
     Rótulo que aponta outra pessoa ou outra coisa ("CPF do cônjuge", "Nome do evento") fica em
     branco, para o atendente. O que foi preenchido aparece destacado, para conferência.

   É só uma cópia feita na tela (script de `documents/partials/form.blade.php`): o servidor
   recebe e confere os campos como se tivessem sido digitados, e nada liga o campo ao signatário
   depois — corrigir o signatário não corrige o campo.
2. **Congelar** — o ato central. A partir dele:
   - o texto vira `body_snapshot` e para de olhar para o modelo;
   - o PDF é gerado e o **`original_sha256`** gravado;
   - o documento ganha código público de validação;
   - **nada mais pode ser editado.** Para corrigir algo, cancela-se e emite-se outro.
3. **Liberar** para o tablet e acompanhar (o QR sai sempre preto sobre **fundo branco fixo**, com
   margem branca — em qualquer tema da tela, porque quem o lê é a câmera; com o fundo do cartão, o
   tema escuro deixava o código ilegível): aguardando leitura → tablet conectado → documento
   visualizado → identidade confirmada → assinado.
4. **Regerar** o QR ou **cancelar** a qualquer momento.
5. Com vários signatários, um QR por pessoa, **em qualquer ordem**: com mais de um pendente, o
   painel mostra **Quem vai assinar agora** (vem marcado o primeiro da lista), e "Gerar outro código"
   regera para a mesma pessoa. `SignatureSigner::releaseBlockReason` não confere mais a posição.

### Arquivar da lista (28/10/2026)

Botão **Arquivar** no topo da tela do documento (permissão `assinatura.documentos`, policy `hide`):
tira o documento da lista de **Documentos**. É só exibição:

- não muda a situação, não cancela, não derruba QR nem link remoto — documento aguardando
  assinatura continua assinável, no tablet, pelo link ou pelo gov.br;
- não entra na trilha de auditoria nem no manifesto (não é ato sobre o documento);
- vale em qualquer situação (rascunho esquecido, cancelado, finalizado…);
- o documento continua abrindo pelo endereço, pelo `/validar` e pela fila de revisão; na lista,
  aparece só com o filtro **Arquivados** (`?arquivados=1`, que mostra **só** os arquivados e combina
  com os outros filtros);
- **Desarquivar**, na mesma tela, devolve à lista. A tela mostra quando e quem arquivou.

Colunas `signature_documents.hidden_at`, `hidden_by`, `hidden_by_name` (retrato, sem FK — migration
`2026_10_29_120000`). **Não confundir com `archived_at`/`archive_path`**, que são a cópia no FTP
(seção "Arquivo no servidor de arquivos"). Leitura: `SignatureDocument::isHidden()` e
`scopeHidden($arquivados)`. Rotas `POST`/`DELETE .../{doc}/arquivar` → `signature-documents.hide`/`unhide`.
Teste: `SignatureDocumentHideTest`.

## Fluxo do tablet

Tela de espera → leitura do QR → formulário, se o modelo pergunta algo a quem assina (ver
"Perguntas a quem assina") → documento no PDF.js (botão travado até a rolagem chegar ao fim)
→ conferência de identidade → aceite explícito → assinatura no canvas → visto, se o modelo exige
→ foto → conclusão.

Na **foto**, a câmera abre e mostra a imagem, mas a contagem não começa sozinha: a pessoa se ajeita
e toca em **Tirar foto**; aí o tablet conta 3 segundos e fotografa. O botão vale um toque só, e
encerrar o atendimento no meio da contagem cancela a foto. Desde 26/10/2026 a foto não é enviada na
hora: aparece uma **prévia** com **Tirar nova foto** (volta à câmera, que segue ligada) e **Usar esta
foto** (só este envia a assinatura).

**Sempre há "Voltar"** (26/10/2026). Identidade e aceite voltam ao documento (já desenhado; a identidade
conferida não é pedida de novo); o traço volta ao aceite; o visto volta ao traço; a foto volta ao visto
(ou ao traço, sem visto). No primeiro passo — o formulário, ou o documento quando não há perguntas — voltar
é **sair do documento**: o tablet pede confirmação e encerra a sessão (`quiosque.leave`). Não é recusa: o
documento continua pendente; no balcão, o atendente gera outro QR; na diretoria, volta à lista.

- **Nada fica no aparelho**: sem `localStorage`, sem `IndexedDB`. O estado vive em memória e é
  zerado ao fim de cada atendimento — o tablet é do balcão e é compartilhado.
- **Todo horário é do servidor.** Um tablet de balcão passa meses sem sincronizar o relógio, e a
  hora da assinatura é justamente o que precisa ser confiável.
- **Recusar** está disponível em qualquer etapa, com motivo opcional.
- **Inatividade**: aviso 60 segundos antes de encerrar.
- **Erro de rede** nunca deixa o atendimento "meio assinado": a gravação é uma transação só.

### Quando o tablet diz que falhou

O tablet distingue três casos, e o texto na tela diz qual foi:

| Mensagem | O que houve | Onde olhar |
|---|---|---|
| **Sem resposta do servidor…** | O pedido nem chegou: Wi-Fi do tablet, rede, servidor fora do ar | Rede do tablet |
| **Falha na comunicação com o servidor (código N)** | Respondeu algo que não é o Lara falando: `500` erro interno, `413` corpo grande demais, `429` limite de pedidos, `502`/`504` proxy | `storage/logs/laravel.log` (500) e log de erro do Apache (413/502/504), pelo horário |
| Uma frase do Lara (ex.: "Não foi possível gravar a assinatura no servidor") | O Lara recusou com motivo | A própria mensagem; a de gravação vai ao log com o caminho |

**O 429 ao salvar (corrigido em 08/10/2026):** o `throttle:N,1` do Laravel contava por IP para
TODAS as rotas juntas. O batimento do tablet (`/sessao`, a cada 5 s) somava no mesmo contador
do `/assinar` (10/min), e depois de um minuto lendo o documento a assinatura levava 429. O alias
`throttle` agora é `ThrottleRequestsPerRoute`, que conta por rota — ver [Rotas](../rotas.md).

**Gravação das evidências:** o disco `local` tem `throw => false`, então um `put` numa pasta sem
permissão devolvia `false` calado — a assinatura era registrada sem o traço/foto e a finalização
quebrava depois. `SignatureCaptureService::capture` agora confere cada gravação: se uma falhar,
apaga as que gravou, registra `Assinatura no tablet: não foi possível gravar a evidência no disco`
no log e responde `500` com mensagem; a pessoa continua no traço e pode tentar de novo. A causa
típica é dono errado em `storage/app/signature` (comandos `artisan` rodados como outro usuário que
não `www-data`): corrija com `chown -R www-data:www-data storage/app/signature`.

### O que o servidor decide, e o tablet não

| Regra | Onde |
|---|---|
| CPF confere? | `Cpf::matches()`, no servidor. O CPF cadastrado **nunca** vai para a tela, nem na mensagem de erro |
| Código do e-mail confere? | HMAC do código vivo da liberação, dentro do prazo — ver "Conferência por código enviado por e-mail" |
| Teto de tentativas | 5 por solicitação; estourou, a sessão morre (quatro dígitos não resistem a chutes ilimitados) |
| O traço é uma assinatura? | Mínimo de 30 pontos vetoriais — um toque na tela não é assinatura |
| O arquivo é imagem? | Conferido pelos **bytes**, não pelo cabeçalho do data URL |
| Pode assinar? | Estado do documento e do signatário, reconferidos a cada requisição |

## Evidências e auditoria

Cada assinatura grava, na **mesma transação**: PNG do traço, traços vetoriais (pontos com tempo
relativo), foto, IP, user agent, tempo de leitura, se a tela informou rolagem até o fim, o aceite
e a hora do servidor.

### Rastreio: quem gerou o documento e quem gerou o QR Code

O documento assinado diz, pelo **nome**, quais usuários do sistema atuaram nele:

| Quem | Onde fica gravado | Onde aparece |
|---|---|---|
| Quem **gerou o documento** (criou o rascunho) | `signature_documents.created_by` + `created_by_name` | Manifesto ("Documento gerado por"), tela do documento ("Gerado por"), evento `created` da trilha (`gerado_por`) |
| Quem **gerou o QR Code** de cada signatário | `signature_requests.created_by` + `created_by_name` | Manifesto, no bloco do signatário ("QR Code gerado por … em …"), tela do documento, evento `qr_issued` / `qr_reissued` da trilha (`gerado_por`) |

- **É um retrato do nome**, tirado na hora, e não uma consulta ao cadastro: o manifesto precisa
  dizer quem atuou sem depender de o usuário existir — ou ter o mesmo nome — anos depois. O `id`
  fica ao lado, para quem precisar chegar ao cadastro.
- **Podem ser pessoas diferentes**: uma prepara o documento, outra atende no balcão e gera o QR.
  E cada signatário tem o seu — num contrato de duas partes, dois QR Codes, talvez dois atendentes.
- **O manifesto cita o QR Code pelo qual a pessoa assinou** (`SignatureSigner::signingRequest()`).
  Os que expiraram, foram cancelados ou substituídos ficam só na trilha, cada um com quem o gerou.
- Documentos e liberações anteriores a esse registro aparecem como "não registrado".

### Autorização da captura da imagem

Quando o modelo pede foto, a tela de aceite do tablet tem uma **segunda caixa de marcação**,
obrigatória para continuar, logo abaixo do "Li e concordo":

> Autorizo a captura da minha imagem (foto) para anexo ao contrato. A imagem será armazenada pelo
> Clube dos Funcionários da CSN por tempo indeterminado, sem fins comerciais, sendo utilizada
> apenas para fins relacionados ao documento que está sendo assinado.

- **O servidor confere**: foto sem a autorização é recusada (`422`), como a assinatura sem o
  aceite dos termos. Se dependesse só do tablet, a exigência seria uma sugestão.
- **Fica na evidência o fato e o texto**: `signature_evidences.photo_consent` e
  `photo_consent_text`. O texto mora em `SignatureEvidence::PHOTO_CONSENT_TEXT`, e cada
  assinatura guarda a cópia do que estava na tela — mudar a redação não reescreve as antigas.
- **Vai ao manifesto** do documento assinado ("Autorização da captura da imagem: sim — …") e à
  trilha (`autorizou_imagem`).
- **Sem foto, sem caixa**: modelo que não pede foto, ou tablet sem câmera no modo sem HTTPS (a
  foto é dispensada e declarada ausente), não mostra a autorização — não há o que autorizar.
- Quem não autoriza não assina no tablet: o caminho é **Recusar**, e o atendente decide o que
  fazer (um modelo sem foto, por exemplo).

A tabela `signature_audit_events` é **somente inserção**, em três camadas:

1. o model recusa `update` e `delete` — com exceção, não com `return false` silencioso
   (`actor_type` diz quem agiu: `user` — usuário do Lara —, `kiosk` — o tablet — ou `system`);
2. cada linha guarda o hash da anterior **daquele documento**, o que torna adulteração por fora
   da aplicação **detectável** (`SignatureAuditor::verify()`);
3. em produção, o usuário do banco não deve ter `UPDATE` nem `DELETE` nessa tabela:

```sql
REVOKE UPDATE, DELETE ON lara.signature_audit_events FROM 'usuario_da_aplicacao'@'%';
```

O GRANT é um passo de **operação**, não uma migration: a migration rodaria com uma credencial que
pode não ter privilégio para concedê-lo, e o SQLite da suíte não reproduz permissão de tabela.

> A rolagem até o fim é conferida no **navegador**; o servidor não tem como prová-la. O que ele
> registra é o que recebeu, com o horário dele — e o manifesto diz isso com todas as letras.

## Finalização e manifesto

Após a última assinatura, o job `FinalizeSignatureDocument` monta o PDF de entrega: o documento
com o traço no lugar do campo, mais uma **página de manifesto** com signatário (CPF mascarado),
data e hora do servidor, o usuário que gerou o documento e o que gerou cada QR, IP e dispositivo, tempo de leitura, miniatura da
foto, o `original_sha256`, a trilha de eventos e o **QR de validação**.

O `original_sha256` do manifesto é o do documento **antes** das assinaturas, e o manifesto avisa que
não é o hash do PDF assinado: um arquivo não pode trazer o próprio hash. O hash que o `validar.iti.gov.br`
mostra é o do arquivo final (`final_sha256`), exibido no `/validar` como "impressão digital da via
assinada". O fim do atendimento no tablet entra na trilha como **"Atendimento no tablet encerrado"**
(`session_completed`); antes de 08/10/2026 aparecia, por engano, como um segundo "QR lido pelo tablet".

**Tamanho do traço no documento:** o tablet manda a tela de desenho inteira, quase toda vazia. O
`SignatureDocumentRenderer::signatureImages` recorta a imagem ao traço (`PngTrimmer`, o mesmo do
PDF enviado pronto) e a imprime no maior tamanho que cabe em **280×100px**
(`SIGNATURE_BOX_WIDTH`/`HEIGHT`), sem distorcer. Até 08/10/2026 a tela inteira saía espremida em
68px de altura, e o traço ficava pequeno. A evidência gravada não muda: o recorte é só para
imprimir. Documentos já finalizados ficam como estão: o PDF final deles foi gravado e tem hash.

> **Documento assinado pelo gov.br: o manifesto é um PDF à parte.** O PDF final é o arquivo que
> voltou do gov.br (`govbr_check_id`), conferido contra o hash da conferência antes de ser gravado.
> Re-renderizar, carimbar ou acrescentar página quebraria as assinaturas; o **lacre** (seção abaixo)
> não quebra, porque só acrescenta uma revisão nova ao fim do arquivo. Por isso o que seria a página
> de manifesto sai como **relatório de validação**
> (`documents/{id}/relatorio-govbr.pdf`, colunas `report_path` / `report_sha256`, view
> `signature/pdf/govbr-report.blade.php`, `SignatureDocumentRenderer::govbrReport`): os dois hashes
> (original e assinado), a conferência do arquivo e de cada assinatura, os signatários (CPF
> mascarado), os convites enviados, a trilha, o QR de validação e a indicação do validador oficial
> (`validar.iti.gov.br`). O hash do relatório vai ao evento `finalized` (`relatorio_sha256`). O
> relatório segue o final em todo lugar:
>
> - **na via por e-mail**, como segundo anexo (`relatorio-govbr-{codigo}.pdf`); o texto da via aponta
>   o validador oficial;
> - **no servidor de arquivos**, ao lado do PDF assinado (`… - relatorio gov.br.pdf`), com a mesma
>   conferência de hash e tamanho;
> - **na tela do documento**, no botão **Relatório gov.br** (`…/pdf?versao=relatorio`).
>
> A página de validação mostra "pelo gov.br" ao lado de cada assinatura.

> **O PDF final é re-renderizado, não carimbado.** O dompdf não edita PDF pronto. As duas versões
> saem do mesmo `body_snapshot`, então o texto é o mesmo; o que prova qual arquivo a pessoa leu é
> o `original_sha256`, calculado antes de qualquer assinatura e impresso no manifesto.

O job é idempotente (reentrega da fila não gera um segundo arquivo) e falha sem perder evidência:
assinatura, traço, foto e trilha já estão gravados — falta só o arquivo, que pode ser refeito.

## Lacre do clube (e-CNPJ) e carimbo de tempo

Com `SIGNATURE_PADES_ENABLED=true`, **todo PDF que sai finalizado** recebe uma assinatura digital do
**clube**, com o certificado A1 (e-CNPJ ICP-Brasil, `.pfx`): o final do tablet, o final do gov.br e
o relatório do gov.br. O lacre **não assina pela pessoa**. Prova outra coisa: que o arquivo não mudou
depois de emitido pelo clube, e quando. Qualquer pessoa confere em `validar.iti.gov.br`, **sem
acesso ao Lara**, que não é acessível de fora. É a resposta para os links `/validar` do e-mail e do
manifesto, que só abrem de dentro da rede.

| Peça | Papel |
|---|---|
| `SignaturePdfSealer` | Abre o `.pfx` (`certificate()`: arquivo, senha, validade), monta o CMS destacado (`openssl_cms_sign`, com a cadeia do `.pfx`) e, com ACT, acrescenta o carimbo de tempo |
| `Pki\PdfSignatureWriter` | Escreve a assinatura por **atualização incremental**: catálogo com o campo no `/AcroForm` (`/SigFlags 3`), widget invisível na 1ª página, `/Sig` com `/Contents` reservado (`SIGNATURE_PADES_RESERVE_BYTES`), `/ByteRange`, tabela xref clássica e trailer com `/Prev`. Nada do que existia é reescrito |
| `Pki\TimestampClient` | Pedido RFC 3161 à ACT (`SIGNATURE_TSA_URL`, autenticação básica opcional, nonce, `certReq`). Confere status, resumo, nonce e a assinatura do carimbo antes de usá-lo |
| `Pki\Der` | Escritor DER mínimo (pedido de carimbo e o atributo não assinado no CMS) |

- **Formato:** `/SubFilter adbe.pkcs7.detached` (o mesmo do gov.br), sha256. O carimbo vai como
  atributo **não assinado** `id-aa-signatureTimeStampToken` (`1.2.840.113549.1.9.16.2.14`) sobre o
  valor da assinatura — ele não muda o que a assinatura cobre.
- **No PDF do gov.br**, o lacre é mais uma assinatura: as das pessoas continuam válidas. O DocMDP que
  o gov.br grava (`/P 2`) permite acrescentar assinaturas. Conferido com o pyHanko na amostra real em
  08/10/2026: a assinatura da pessoa segue íntegra (`docmdp_ok`), o lacre cobre o arquivo inteiro e o
  carimbo é válido.
- **`final_sha256` é o do arquivo lacrado.** A página `/validar` aceita também o arquivo que a pessoa
  baixou do gov.br, sem o lacre (`govbrFinalCheck->file_sha256`): é a mesma via assinada.
- **Falha alto:** ligado sem certificado, com senha errada, certificado vencido, ACT fora do ar ou
  carimbo de outro resumo → exceção; o job tenta de novo (`backoff`) e, esgotado, registra
  `finalization_failed`. O documento fica "Assinado", com toda a evidência. Um lacre que
  silenciosamente não acontece é pior que nenhum.
- **Limites:** só PDF com tabela xref clássica e sem criptografia (o dompdf, a FPDI e o gov.br gravam
  assim). xref stream → recusado, com a mensagem dizendo isso.
- O evento `finalized` leva `lacrado` e `carimbo_de_tempo`. O manifesto, o relatório, a via por e-mail
  e o guia citam o lacre e o `validar.iti.gov.br` quando ele está ligado.
- **Conferido no ITI (08/10/2026):** um PDF do tablet lacrado com o e-CNPJ do clube foi enviado ao
  `validar.iti.gov.br`: **"Assinatura aprovada"**, selo **"Assinatura eletrônica qualificada"** (MP
  2.200-2/01 e Lei 14.063/20), titular e CNPJ do clube, CPF do representante mascarado. O CMS não leva
  identificador de política (DOC-ICP-15) nem `signingCertificateV2`, e o validador não reprovou por
  isso. Se algum dia for exigida uma política (AD-RB etc.), o ponto é o `cms()` do `SignaturePdfSealer`.
- **Sem a marca ICP-Brasil.** O Selo de Homologação (o logotipo "ICP Brasil" com a chave e o número
  HHHH-AA-XXXX/YY) é só de sistema **homologado pelo ITI** (DOC-ICP-10, item 4), e o Lara não é — nem
  precisa ser: a homologação só é obrigatória para quem integra a ICP-Brasil (AC, AR), e a validade da
  assinatura vem do certificado, não da plataforma. Em tela, PDF, e-mail e guia, diga "certificado
  ICP-Brasil" e "validador oficial (validar.iti.gov.br)"; nunca "homologado" nem o logotipo. Conferido
  em 08/10/2026: nenhuma tela usa a marca.

### Instalar o certificado (passo a passo)

Precisa de um **e-CNPJ A1 do clube** (`.pfx`/`.p12` + senha). e-CPF de uma pessoa lacraria tudo no
nome dela; o `seal-check` mostra o titular. A ACT é opcional (sem ela, lacre sem carimbo).

**1. Conferir o arquivo** (na sua máquina, com o OpenSSL do Git Bash):

```bash
openssl pkcs12 -in certificado.pfx -nokeys -clcerts | openssl x509 -noout -subject -issuer -enddate
```

Se der `unsupported` / `RC2-40-CBC`, o `.pfx` usa criptografia antiga (comum em A1 de certificadora),
que o OpenSSL 3 do PHP não abre. Converta **uma vez** e use o arquivo novo, com a mesma senha:

```bash
openssl pkcs12 -legacy -in certificado.pfx -nodes -out tmp.pem
openssl pkcs12 -export -in tmp.pem -out clube.pfx -certpbe AES-256-CBC -keypbe AES-256-CBC -macalg sha256
rm tmp.pem     # tem a chave privada SEM senha: apague na hora
```

O `seal-check` também reconhece esse caso e mostra os comandos.

**Embutir a cadeia**, se `openssl pkcs12 -in clube.pfx -nokeys | grep -c "BEGIN CERTIFICATE"` der 1.
As ACs estão no pacote oficial do ITI
(`http://acraiz.icpbrasil.gov.br/credenciadas/CertificadosAC-ICP-Brasil/ACcompactado.zip`). Use **http**: em
08/10/2026 o HTTPS do `acraiz` mandava a cadeia TLS incompleta (sem a intermediária Let's Encrypt), e o
curl do Linux recusa (erro 60). Pelo HTTP, a garantia são as impressões digitais abaixo — confira sempre. O e-CNPJ
do clube (emitido em 2026, vence em 21/07/2027) é da **AC SAFEWEB RFB v5** (sha256 `7B:3A:4B:E4…E0:53:EA`), emitida pela
**AC Secretaria da Receita Federal do Brasil v4** (sha256 `2C:7A:8A:D7…31:5E:21`), sob a Raiz v5:

```bash
unzip -j ACcompactado.zip AC_SAFEWEB_RFB_v5.crt AC_Secretaria_da_Receita_Federal_do_Brasil_v4.crt
cat AC_SAFEWEB_RFB_v5.crt AC_Secretaria_da_Receita_Federal_do_Brasil_v4.crt > cadeia.pem
openssl pkcs12 -in clube.pfx -nodes -out tmp.pem
openssl pkcs12 -export -in tmp.pem -certfile cadeia.pem -out clube-com-cadeia.pfx -certpbe AES-256-CBC -keypbe AES-256-CBC -macalg sha256
shred -u tmp.pem
```

Na renovação, a AC pode mudar: veja o `issuer` do certificado novo e pegue a AC correspondente no pacote.

**2. Copiar para o servidor**, para fora de `public/` e do git (`storage/certificates/` já é ignorado):

```bash
scp clube.pfx usuario@servidor:/tmp/
# no servidor:
sudo mkdir -p /var/www/html/Lara/storage/certificates
sudo mv /tmp/clube.pfx /var/www/html/Lara/storage/certificates/
sudo chown www-data:www-data /var/www/html/Lara/storage/certificates/clube.pfx
sudo chmod 600 /var/www/html/Lara/storage/certificates/clube.pfx
```

(Homologação: `/home/administrator/Lara`.) O `deploy_prod.sh` refaz `chown` da pasta toda, mas não
mexe no `chmod 600` do arquivo.

**3. Preencher o `.env` do servidor** (a senha nunca vai para o git, chat ou ticket):

```dotenv
SIGNATURE_PADES_ENABLED=true
SIGNATURE_PADES_CERTIFICATE=/var/www/html/Lara/storage/certificates/clube.pfx
SIGNATURE_PADES_PASSWORD="a senha do pfx"
SIGNATURE_TSA_URL=
```

**4. Recarregar e conferir**, como o usuário do Apache (é ele que lê o arquivo na finalização):

```bash
cd /var/www/html/Lara
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan signature:seal-check
```

Saída esperada: titular (o clube), emissor, validade, cadeia ≥ 1 e o aviso "Sem ACT". Anote a
validade: vencido, a finalização para (ver Pendências no resumo do módulo).

**5. Reiniciar o worker da fila**, que guarda a configuração antiga em memória:
`sudo supervisorctl restart 'lara-queue:*'`.

**6. Conferir um documento:** finalize um documento de teste, baixe o PDF assinado e envie em
`validar.iti.gov.br`. O lacre aparece como assinatura do clube.

Se algo der errado, desligue com `SIGNATURE_PADES_ENABLED=false` + `config:cache` + reiniciar a
fila. Documento que ficou em "Assinado" por falha do lacre finaliza na próxima tentativa do job.

Exporte o `.pfx` **com a cadeia** da AC (o `seal-check` avisa quando não há): sem ela, o validador de
quem abre o PDF precisa buscá-la sozinho.

## Revisão interna

Todo documento **concluído** passa por uma revisão interna: outra pessoa, que **não acompanhou** a
assinatura, confere se os processos internos daquele atendimento foram feitos. Não mexe no
documento assinado (nem PDF, nem hash): é registro interno, com evento na trilha.

**Situação na tela (30/10/2026):** enquanto a revisão não dá "Tudo em ordem", o documento aparece como
**`Assinado*`**; depois, como **Finalizado**. É só exibição (`SignatureDocument::statusLabel`, a partir de
`awaitsReview`): no banco o status continua `finalized` desde a emissão do PDF, e a via, o arquivamento e a fila
de revisão seguem dependendo dele. Termo de menores e contrato não passam pela revisão
(`SignatureDocument::skipsReview`) e mostram "Finalizado" direto. Documentos
antigos sem revisão também passam a mostrar `Assinado*`. O filtro da lista usa `?status=aguardando-revisao` e
`?status=finalized`. O evento `finalized` da trilha aparece como "Via final emitida"; o nome interno não mudou.

| Regra | Como |
|---|---|
| **O que conferir** | Os **Itens da revisão** do modelo (`signature_templates.review_items`, chave `rev_…` a partir do rótulo — `SignatureReviewService::normalize`). Modelo sem itens: a revisão registra só resultado e observação |
| **Quando** | A partir do **dia seguinte** à conclusão (`SignatureDocument::reviewAvailableAt()` = `finalized_at` + 1 dia, 00:00) |
| **Quem não revisa** | Quem acompanhou: gerou o documento (`created_by`), gerou algum QR (`signature_requests.created_by`), enviou convite (`signature_govbr_invites.sent_by`) ou conferiu arquivo do gov.br (`signature_govbr_checks.checked_by`) — `SignatureDocument::involvedUserIds()` |
| **Coordenação** | Com `assinatura.revisar-coordenacao`, as duas regras acima caem; a revisão grava `early` (antes do prazo) e `own` (de quem acompanhou), e a tela mostra |
| **Resultado** | "Tudo em ordem" exige **todos** os itens feitos; "Com pendência" exige **observação**. Pendência mantém o documento na fila; uma revisão em ordem encerra (não há outra depois) |
| **Registro** | `signature_reviews` (itens com `done`, observação, quem, flags) + `signature_documents.review_status`/`reviewed_at` (retrato da última) + evento `reviewed` (resultado, itens pendentes pelo rótulo, quem — **sem** a observação, que é texto livre) |

**Onde:** menu **Assinaturas → Revisão** (`signature-reviews.index`, Gate `acessar-revisao-assinatura`) lista
"A revisar" (concluídos sem revisão em ordem, o mais antigo primeiro, com o motivo quando a pessoa ainda
não pode revisar) e "Revisados em ordem". A revisão é registrada na aba **Revisão** do documento
(`?aba=revisao`, rota `signature-documents.review`), que mostra também o histórico. Quem revisa abre o
documento mesmo sem `assinatura.documentos`/`consultar` (`SignatureDocumentPolicy::viewAny`).

Migration `2026_10_11_100400_create_signature_reviews`. Testes: `SignatureReviewTest`.

**Termo de Menores fica fora da revisão** (decisão do clube): `SignatureDocument::isMinorTerm()` — a
fila não lista, a aba mostra "Não se aplica" e `blockReason` recusa. Ver [Termo de Menores](termo-de-menores.md).

## Termo de Menores (autoatendimento)

Autorização de entrada de menor nos eventos, assinada pelo **próprio sócio** num tablet de
autoatendimento pareado (`/assinatura/kiosk/menores`): título → responsável → CPF completo → menor →
documento gerado pelo próprio tablet → leitura, aceite, traço e foto do quiosque de sempre → tela
verde. Cada termo é um documento comum deste módulo. Menu **Assinaturas → Termo de Menores** (termos
dos eventos, pareamento do tablet, histórico). Documentação completa:
[Termo de Menores](termo-de-menores.md).

## Assinatura Diretoria

Diretores do clube assinam documentos sem passar pelo atendente: no tablet do balcão, em
**Assinatura da Diretoria**, entram com a **matrícula e o PIN do Lara** e veem todos os documentos
pendentes da assinatura deles.

### Áreas, titular e substitutos

Menu **Assinaturas → Diretoria** (`signature-directors.*`, permissão `assinatura.diretoria`):

- **Área** (`signature_director_areas`): "Diretoria Financeira", "Diretoria Social". Não se apaga
  (os documentos a citam); desativa-se. Renomear não muda documento já emitido.
- **Mandato** (`signature_director_area_holders`): um usuário numa área, como **titular** (um por
  vez) ou **substituto** (quantos houver). Não se edita: **Encerrar** grava `ended_at`; incluir um
  titular novo encerra o anterior. Vigente = `ended_at` nulo. O histórico fica na tela.
- **Quem pode:** usuário **ativo**, com **matrícula**, do setor **Diretoria** (`User::isDirector()`,
  o mesmo critério da aprovação de compras). O CPF vem de `users.cpf` ou, sem ele, do cadastro de
  funcionário pela matrícula; sem CPF válido a pessoa aparece no documento mas não pode ser escolhida.

Regras em `App\Services\Signature\Directors\DirectorAreaService`.

### O diretor no documento

- No formulário do documento, cada signatário tem **Diretor do clube** (área → titular/substitutos).
  Escolhido, nome, CPF, e-mail e telefone saem do **cadastro do Lara** — o
  `StoreSignatureDocumentRequest::prepareForValidation` resolve no servidor e descarta o que o
  formulário mandar nesses campos (e `user_id`/`director_*` soltos).
- **O atendente escolhe a pessoa ao criar o documento** (decisão do usuário, 20/10/2026): o PDF
  original imprime o nome e o CPF dela. Se ela não puder assinar, cancela-se e emite-se outro com
  quem assume. A tela da área lista os documentos pendentes de quem saiu do mandato.
- No modelo, uma parte pode ser **Assinada pela diretoria** (`parties[].director_area_id`): o
  documento nasce com o titular da área escolhido, e o atendente pode trocar por um substituto.
- O signatário guarda `user_id`, `director_area_id` e o retrato `director_area_name`/`director_role`.
  Sob o nome sai "Parte — Diretoria Financeira" ou "Diretoria Financeira (substituto)"
  (`SignatureSigner::capacityLabel`).

### Congelamento: prazo e aviso

- Documento com diretor recebe prazo de `SIGNATURE_DIRECTOR_TTL_DAYS` (7 dias), e não as horas do
  balcão: o diretor assina quando passar pelo tablet.
- O job `NotifySignatureDirector` (fila, depois do commit) manda ao diretor o e-mail
  `SignatureDirectorPendingMail` — título, quem gerou, prazo, "entre com matrícula e PIN". **Sem
  link** (o Lara é fechado). Grava `director_notified_at` e o evento `director_notified` (e-mail
  mascarado). Não reenvia; não avisa de documento que já foi ao gov.br.
- **Diretoria depois de quem é de fora dela** (decisão do usuário, 26/10/2026). Com algum signatário que
  não é diretor ainda pendente, o documento **não chegou à diretoria**: não aparece na lista do diretor,
  não abre no tablet ("Este documento vai para a diretoria depois da assinatura de…"), não gera o
  e-mail de aviso, não recebe convite gov.br para o diretor e não entra no filtro "Aguardando diretoria"
  do painel. O aviso sai quando o último de fora assina (`NotifySignatureDirector::forReleased`, chamado no
  congelamento e na captura). Regra em `SignatureDocument::pendingNonDirectors()`/`releasedToDirectors()`
  e `scopeReleasedToDirectors`. O cartão do diretor, no painel, diz de quem o documento espera.

### No tablet

Rotas públicas `/assinatura/kiosk/diretoria/*` (`quiosque.diretoria.*`, `DirectorKioskController`,
regras em `DirectorKioskService`):

1. **Entrar** — matrícula + PIN (`User::checkPin`). Limite de
   `SIGNATURE_DIRECTOR_MAX_LOGIN_ATTEMPTS` (5) tentativas **por matrícula** a cada 15 min (o tablet
   divide um IP). Uma resposta só para matrícula inexistente, usuário inativo, sem PIN e PIN errado;
   "não é da Diretoria" (403) só depois do PIN certo. Abre a entrada do diretor: cookie
   `lara_director` (httpOnly, SameSite=Strict, só no caminho do tablet), hash no cache, prazo de
   `SIGNATURE_DIRECTOR_SESSION_MINUTES` (10) renovado a cada uso. Cada passo reconfere usuário
   ativo e do setor.
2. **Lista** — signatários pendentes do diretor em documentos aguardando assinatura, no prazo,
   **fora do gov.br** e que **já chegaram à diretoria** (nenhum signatário de fora dela pendente). Sem toque por `SIGNATURE_DIRECTOR_IDLE_SECONDS` (90 s), o tablet sai sozinho.
3. **Abrir** — emite e consome a liberação no servidor (`issue` + `consumeIssued`, como o Termo de
   Menores) e devolve o `lara_sign` de sempre. A identidade fica confirmada pelo PIN:
   `signature_requests.identity_method = matricula_pin` e evento `identity_confirmed` com
   `{modo: matricula_pin, matricula}`. **Sem CPF e sem código por e-mail.**
4. Leitura, aceite, traço, visto (se o documento pedir) e **foto, sempre obrigatória** para o
   diretor, mesmo que o modelo dispense (`SignatureSigner::requiresPhoto`). Terminado, volta à lista.

**Vários de uma vez** (26/10/2026). Na lista, cada documento tem uma caixa de marcar (e "Marcar todos");
"Assinar selecionados" abre o lote (`POST lote`, até `DirectorKioskService::BATCH_MAX` = 20):

- cada documento é aberto como na assinatura avulsa (liberação emitida e consumida, `matricula_pin`,
  `identity_confirmed`, data automática). Se um não abrir, os já abertos são cancelados;
- o tablet mostra **um documento de cada vez** ("Documento 1 de N", PDF por `lote/{signer}/pdf`) e
  registra a leitura de cada um (`lote/{signer}/visualizado`, evento `viewed` com `em_lote`);
- depois, **um** aceite ("Li e concordo com os termos destes N documentos"), **um** traço, **um** visto
  (se algum documento pedir — vai só para os que pedem) e **uma** foto;
- `lote/assinar` grava **uma captura por documento** (`SignatureCaptureService::capture`, o evento
  `signed` leva `em_lote` = N). Um documento que falhe não desfaz os outros: o resultado diz qual entrou;
- fora do lote: documento com **perguntas a responder** (cada um tem o seu formulário) — a lista avisa
  "assine sozinho". No lote não há via por e-mail nem recusa (recusa é de um documento);
- "Voltar" no primeiro documento desiste do lote (`lote/cancelar`); um marcado só segue o fluxo avulso.

Tudo pelo cookie `lara_director`, sem `lara_sign`: a liberação viva de cada documento é achada no banco
(`DirectorKioskService::batchRequest`: deste diretor, `matricula_pin`, consumida, no prazo).

**Já assinados** (26/10/2026). Segunda aba da lista: o que o diretor assinou nos últimos 90 dias (até 50),
com a situação — quem ainda falta, "via final sendo gerada", "Concluído", recusado. Só acompanhamento,
sem abrir o PDF (o tablet é do balcão).

O manifesto diz "Documento aberto pelo próprio diretor no tablet" e "Conferência de identidade:
Matrícula e PIN do Lara".

**Diretor não recebe QR do atendente** (decisão do usuário, 21/10/2026): `SignatureSigner::releaseBlockReason`
recusa a liberação de um signatário-diretor, salvo a emitida pela entrada dele
(`signature_requests.identity_method = matricula_pin`, gravado já na emissão por
`SignatureRequestService::issue(..., $identityMethod)`); a mesma conferência vale na leitura e em cada
requisição do tablet (`EnsureSignatureKioskSession`). O painel não oferece QR para o diretor e diz
que ele assina em "Assinatura da Diretoria".

### Pelo gov.br

Documento preparado para o gov.br sai da lista do tablet: o diretor assina pelo mecanismo do gov.br
(convite + aba), como qualquer signatário — a conferência é pelo CPF dele.

Migration `2026_10_20_120100_create_signature_director_areas`. Testes: `SignatureDirectorTest`.

## Assinatura remota (link por e-mail)

Para quem não vem ao balcão e não quer usar o gov.br. O atendente envia, pela tela do documento, um
**link especial por e-mail**. O link abre a tela de quem assina numa **área separada** do servidor
Next.js da DMZ (o mesmo que hospeda as reservas e a `/aprovacao` de Compras), **sem login**: layout
próprio, sem relação com sócio nem reserva, não indexada e sem link a partir do site. A pessoa lê o documento, confere o CPF, aceita, assina com o
dedo ou o mouse e tira uma foto. O PDF final é montado do mesmo jeito que o do tablet.

Decisões do usuário (21/10/2026): o link mais o **CPF completo**, com 5 tentativas; validade de
**72 horas**; **qualquer documento** pode ir pelo link (sem opção no modelo); **foto sempre
obrigatória**. Diretoria e Termo de Menores ficam fora.

### O Lara continua fechado

O navegador da pessoa fala só com o site. O **servidor** do site chama a API
`/api/assinatura/remota/*` do Lara, como o site de aprovação de Compras já faz. A API tem **token
próprio** (`SIGNATURE_REMOTE_API_TOKEN`), que não é o `API_TOKEN` das reservas, e pode ser limitada
ao IP do servidor do site (`SIGNATURE_REMOTE_ALLOWED_IPS`).

O site repassa o IP e o navegador de quem assina nos headers `X-Signer-Ip` e
`X-Signer-User-Agent`. São esses valores que vão para a evidência, a trilha e o manifesto. Sem
eles, ficaria gravado o IP do servidor do site como se fosse o da pessoa. IP inválido ou ausente
fica "não registrado".

### Na tela do documento

O cartão **Assinatura remota** fica abaixo da liberação no tablet e só aparece com
`SIGNATURE_REMOTE_SITE_URL` preenchido. Ele lista cada signatário pendente, menos o diretor, com
o e-mail do cadastro já preenchido (pode ser corrigido; o informado passa a ser o do signatário,
como no convite do gov.br) e o botão **Enviar link**. O cartão mostra também o estado do último
link: para quem foi, por quem, até quando vale, quantas vezes foi aberto e quantas vezes o CPF
errou. O botão **Revogar link** está ali também.

- **Copiar o link (30/10/2026):** na volta do envio, o cartão mostra o endereço com **Copiar link**, para o
  atendente encaminhar por outro canal (WhatsApp, por exemplo). Aparece uma vez só, porque o banco guarda o
  `sha256` do token e não há como remontar o link. Ele vem de `SignatureRemoteLink::plainUrl` (não é coluna) e
  passa pela sessão cifrado (`Crypt`), porque `SESSION_DRIVER=database`. Para ver de novo, **Enviar novo link**. O
  e-mail continua obrigatório, e falha no envio não gera link.
- Mesma permissão de liberar o QR (`release` = `assinatura.documentos`). Sem permissão nova.
- **Enviar de novo substitui o anterior** (`superseded`).
- O e-mail sai **na hora, dentro da transação**: SMTP fora do ar → nada fica registrado.
- O prazo do documento (24 h do balcão) é **estendido até o fim do link**, se for menor. Um
  prazo maior (gov.br, diretoria) fica como está.
- Recusas: site não configurado, diretor, Termo de Menores, documento fora de "aguardando",
  preparado para o gov.br, signatário que já respondeu, CPF inválido no cadastro.

### Na API

| Rota | Sessão | O que faz |
|---|---|---|
| `POST /api/assinatura/remota/link` `{token}` | não | Página de entrada: título, nome, até quando vale, exigências. Não abre sessão |
| `POST /api/assinatura/remota/abrir` `{token}` | não | Abre a sessão e devolve `session_token` + o estado (o mesmo do tablet) |
| `GET /sessao` | sim | Estado da sessão (contagem, perguntas, regras) |
| `GET /pdf` | sim | O PDF original que a pessoa lê, os mesmos bytes do hash |
| `POST /respostas` `{answers}` | sim | Perguntas a quem assina, antes da leitura |
| `POST /visualizado` | sim | Tempo de leitura e rolagem até o fim |
| `POST /identidade` `{cpf}` | sim | CPF completo; devolve `attempts_left` quando erra |
| `GET /anexos` | sim | Itens de anexo pedidos, arquivos já enviados, o que falta (também vem no estado da sessão) |
| `POST /anexos` (multipart `file` + `key`) | sim | A pessoa envia o arquivo de um item pedido |
| `DELETE /anexos/{id}` | sim | A pessoa apaga um arquivo que ela mesma enviou, antes de assinar |
| `POST /assinar` | sim | Traço, visto, foto (obrigatória), aceite: tudo numa requisição |
| `POST /recusar` `{reason}` | sim | Recusa: o documento inteiro vai para recusado |
| `POST /encerrar` | sim | Sai sem assinar; o link continua valendo |

A sessão vai no header `X-Signature-Session`. O token do e-mail vai sempre no **corpo**, nunca na
URL da API, para não acabar em log de acesso. Toda recusa responde `{error, reason}`, com
`reason` estável: `invalid_link`, `link_expired`, `link_superseded`, `link_revoked`, `link_used`,
`link_locked`, `document_unavailable`, `session_ended`, `identity_required`, `identity_failed`,
`attachments_missing`, `attachment_refused`, `too_many_attempts`,
`invalid`, `conflict`, `unauthorized`. O contrato completo, com exemplos, está no prompt do site:
[assinatura-remota-prompt-site.md](assinatura-remota-prompt-site.md).

### Regras

- **O link abre várias vezes** até a pessoa assinar ou recusar, dentro das 72 h. Cada abertura é
  uma liberação nova (`signature_requests`, `identity_method = link_email`,
  `signature_remote_link_id`) com sessão de `SIGNATURE_REMOTE_SESSION_MINUTES` (30 min),
  emitida e consumida na hora. Abrir de novo derruba a sessão anterior (outra aba, outro aparelho).
- **CPF completo, sempre**, qualquer que seja a regra do modelo (`SignatureCaptureService::confirmIdentity`).
- **O CPF vem antes do documento.** Sem a identidade confirmada, `pdf`, `respostas` e `visualizado`
  respondem 409 `identity_required`: quem tem o e-mail tem o link, e o documento não abre só com ele.
  Ordem no site: CPF → perguntas → anexos → leitura → aceite → traço → visto → foto. (No tablet a ordem é
  outra, porque o atendente vê quem pega o aparelho.)
- **Anexos pela própria pessoa** (decisões do usuário, 21/10/2026): ela envia os itens pedidos pelo modelo
  e pelo documento (nunca avulso), com as mesmas regras do atendente (PDF, JPG ou PNG pelo conteúdo,
  `SIGNATURE_ATTACHMENT_MAX_KB`, SHA-256, trilha sem o nome do arquivo). **No remoto, o obrigatório trava a
  ASSINATURA** (409 `attachments_missing`), não só a conclusão: não há atendente para correr atrás do
  arquivo. Vale arquivo de quem quer que seja, inclusive o enviado pelo atendente. A pessoa **troca ou
  apaga só o que ela mesma enviou** (`signature_attachments.uploaded_by_signer_id`), e só até assinar. O
  que o atendente enviou aparece para ela, sem poder apagar. Na tela do documento e no manifesto, o
  anexo sai como enviado por "Nome (signatário, pelo link)"; a trilha leva o IP de quem assina. Os itens
  são do documento, não de cada signatário: com dois signatários remotos, o primeiro a assinar precisa
  dos obrigatórios completos.
- **As 5 tentativas contam no link**, somadas entre as aberturas. A quinta errada **bloqueia** o
  link (`locked`, evento `remote_link_locked`). Para recomeçar, o atendente envia um novo link.
- **Foto sempre obrigatória** (`SignatureSigner::requiresPhoto($via)`). O "sem câmera" do modo sem
  HTTPS não vale aqui: o site é HTTPS.
- **Tablet e link no mesmo documento**: pode. A captura é a mesma, e quem assinar primeiro encerra
  o outro caminho. A abertura do link não derruba o QR que está na tela do atendente.
- **gov.br continua exclusivo**: preparar o documento para o gov.br **revoga** os links ativos
  (`GovbrCheckService::prepare`, `links_remotos_revogados` no evento).
- **A sessão remota não abre o tablet**, nem o contrário: o `lara_sign` recusa liberação remota,
  e a API remota recusa liberação do tablet.
- Limites por IP de quem assina + rota (`signature-remote`, 120/min; `signature-remote-open`,
  20/min — `AppServiceProvider`), porque tudo chega do mesmo IP do site.

### Trilha e manifesto

Eventos novos: `remote_link_sent` (e-mail mascarado, validade, prazo do documento, quem enviou),
`remote_link_revoked`, `remote_link_locked` e `remote_opened` (no lugar do "QR lido pelo tablet").
O manifesto imprime "Assinatura remota, pelo link enviado a m\*\*\*a@… por <atendente> em <data>;
aberto pelo signatário em <data>". A conferência sai como "CPF completo, digitado por quem abriu o
link de assinatura remota enviado por e-mail", e a origem dos eventos como **Link remoto** (e não
Tablet), com o IP de quem assinou.

### Onde fica o quê

| Peça | Papel |
|---|---|
| `App\Services\Signature\RemoteSignatureService` | Enviar, revogar, resolver o token, abrir a sessão, CPF com tentativas no link, assinar e recusar |
| `App\Models\SignatureRemoteLink` (`signature_remote_links`) | O link: hash do token, e-mail, validade, status, tentativas, aberturas |
| `App\Http\Controllers\Signature\RemoteLinkController` | Painel: `signature-documents.remote-link.store` / `.destroy` |
| `App\Http\Controllers\Api\RemoteSignatureApiController` | API `api.assinatura-remota.*` |
| `EnsureSignatureRemoteClient` (`signature_remote_api`) / `EnsureSignatureRemoteSession` (`signature_remote_session`) | Token e origem do site; sessão em header |
| `App\Mail\SignatureRemoteLinkMail` + `emails/signature-remote-link` | O e-mail com o botão |
| `signature/documents/partials/remote.blade.php` | O cartão na tela do documento |

Migration `2026_10_21_120000_create_signature_remote_links`. Testes: `SignatureRemoteLinkTest`.

## Página pública de validação

`/validar/{codigo}` — sem login, porque quem chega veio do QR impresso. Mostra título, data,
situação e signatários com **CPF mascarado**, e aceita o **upload de um PDF** para conferir o
hash. Não mostra foto, traço, contato, nem entrega o documento: quem tem o código tem o papel.

Código inexistente e rascunho respondem a mesma coisa — "não encontrado" —, para a página não
confirmar a existência de documentos alheios. O arquivo enviado é lido do diretório temporário,
hasheado e descartado.

**Para onde o QR aponta** (`SignatureValidationLink`, desde 09/10/2026). O `/validar` só abre dentro
da rede do clube, então o PDF que vai para a casa da pessoa aponta para o validador oficial quando
ele consegue conferir o arquivo:

| PDF | QR, rodapé e linha carimbada | Por quê |
|---|---|---|
| Final, com o lacre ligado | `https://validar.iti.gov.br` + "envie este PDF" + o código | O lacre é assinatura digital: o ITI confere de qualquer lugar |
| Final assinado pelo gov.br (com ou sem lacre) | `https://validar.iti.gov.br` | As assinaturas das pessoas estão no arquivo |
| Final do tablet, sem lacre | `/validar/{codigo}` | Sem assinatura digital, o ITI diria "não assinado" |
| Original (o que o tablet mostra) | `/validar/{codigo}` | Não é lacrado; o hash dele é conferido no Lara |

O botão "Validar documento" do e-mail da via segue a mesma regra. O código de validação continua
impresso em todos: dentro da rede, o `/validar` segue funcionando. O endereço do `/validar` vem do
`APP_URL` do ambiente que gera o PDF; em produção, ele precisa ser o endereço que os computadores
do clube usam.

## Envio da via

A pessoa marca **"quero receber uma via"** na tela de aceite, e o pedido chega junto com a
assinatura — na mesma requisição e na mesma transação. Foi assim, e não numa pergunta depois,
porque a sessão do tablet morre no instante em que a assinatura entra.

O e-mail leva o PDF final e o código de validação. O atendente pode reenviar pelo painel; o
reenvio grava na trilha **quem pediu** (`actor_type = user` e `reenvio_pedido_por` no evento
`copy_sent`). A via que sai sozinha na finalização fica como do sistema.

> **Sem "Local do atendimento".** O campo existia no documento e ia ao manifesto, mas o atendimento
> é sempre no mesmo lugar. Saiu do formulário, da tela, do manifesto, da validação e da
> configuração (`SIGNATURE_LOCATION`); a migration `2026_10_11_100200` tira a coluna. O que
> identifica o atendimento é **quem gerou o documento** (`created_by_name`, na tela, no manifesto,
> no relatório gov.br e na página de validação), **quem gerou cada QR**, **quem enviou cada
> convite do gov.br** (`sent_by_name`) e **quem pediu o reenvio da via**.

**WhatsApp não entra nesta entrega.** O gateway da Poli responde 200 para envios que não entrega;
gravar "enviado" com base nisso colocaria informação falsa na trilha de auditoria. A flag existe
em `config/signature.php` para o dia em que houver confirmação real.

## Documento pronto, enviado em PDF

Um modelo reproduz **texto**: a importação do Word descarta imagens, fontes e diagramação, e o
documento sai com a aparência padrão do sistema. Para um documento que precisa ir **como está** —
com fotos, plantas, logotipo de terceiro, layout próprio — o caminho é enviá-lo pronto:

1. **Assinaturas → Documentos → Novo documento → Enviar documento pronto (PDF)**.
2. Informar título, signatários e as regras que num modelo viriam do modelo: conferência de
   identidade, foto e visto.
3. Na página do documento, em **Lugar das assinaturas**, escolher a pessoa e clicar em cima da
   linha onde ela assina. Quem ficar sem lugar assina numa **folha de assinaturas** ao fim.
4. Congelar, liberar e assinar como qualquer outro documento.

O PDF é aproveitado **na íntegra**: as páginas são importadas como estão (FPDI) e nada é
remontado. O sistema só carimba por cima (`SignaturePdfStamper`):

| O que entra | Onde |
|---|---|
| Linha de validação (endereço e código) | Pé de cada página, miúda, à esquerda |
| Caixas de visto, se exigido | Canto de baixo à direita de cada página |
| Assinatura de quem tem lugar marcado | No ponto marcado — ele é o meio da base da assinatura |
| Folha de assinaturas | Página acrescentada ao fim, para quem não tem lugar marcado |
| Manifesto | Ao fim do PDF final, como nos demais documentos |

O que sustenta isso:

- **O arquivo enviado nunca é alterado.** Fica guardado como veio (`source_path`), e o hash dele
  (`source_sha256`) vai à trilha e ao manifesto — ao lado do `original_sha256`, que é o hash do
  que a pessoa leu no tablet (o enviado + os carimbos em branco).
- **O PDF é conferido no envio**, e não no balcão: protegido por senha, danificado ou num formato
  que o sistema não aproveita, é recusado ali, com a orientação do que fazer.
- **O lugar da assinatura é fração da página** (0 a 1), e não pixel nem milímetro: vale para
  qualquer tamanho de folha. Muda só no rascunho; congelado, não muda mais.
- **Ninguém fica sem lugar**: lugar numa página que o PDF não tem também cai na folha de
  assinaturas.
- **As regras moram num modelo de uso único** (`single_use`), criado junto com o documento e fora
  da lista de modelos. O resto do módulo lê as regras do modelo e não precisa saber que este
  documento não veio de um.
- Não leva papel timbrado: o documento já é a arte de quem o enviou. No arquivo de rede vai para
  a pasta **Documentos avulsos**.

Limites, para saber antes:

- **PDF com estrutura compactada (object streams / xref streams) não é aceito.** É uma limitação
  da versão livre da FPDI. A saída é reimprimir o arquivo em PDF ("Imprimir → Salvar como PDF" do
  navegador gera um formato aceito) — a mensagem de erro diz isso ao atendente.
- **O visto e a linha de validação ocupam o pé da página.** Se o PDF tiver conteúdo colado na
  margem de baixo, eles ficam por cima. Vale deixar uns 2 cm livres no rodapé.
- Só PDF. Word precisa ser salvo como PDF antes.
- Editar os signatários de um rascunho recria a lista e **apaga os lugares marcados**.

## Anexos do documento

Documento que precisa de identidade, comprovante de residência, laudo — arquivos que ficam guardados com
ele. Funciona como as perguntas: **o que se pede** é uma lista de itens, cada um com rótulo e
**obrigatório ou opcional**, em dois lugares:

| Onde | Coluna | Chave |
|---|---|---|
| No **modelo** — vale para todo documento dele, versionado com o modelo | `signature_templates.attachments` | `mod_<rótulo>` |
| No **documento** — só nele, no passo "Anexos" do formulário | `signature_documents.attachment_requirements` | `doc_<rótulo>` |

A chave sai do rótulo (`SignatureAttachmentService::normalize`), com o prefixo de quem pediu, para as
duas listas não colidirem. `SignatureDocument::attachmentRequirements()` junta as duas (modelo antes).

Na **assinatura remota**, a própria pessoa também envia os itens pedidos, pela tela do link, e lá o
obrigatório trava a assinatura — ver [Assinatura remota](#assinatura-remota-link-por-e-mail).

**Quem envia é o atendente, na tela do documento** (cartão "Anexos"): um envio por item — um item pode
receber mais de um arquivo (frente e verso) — e **Outro anexo**, avulso, com o nome digitado.

- **Vários arquivos de uma vez** (26/10/2026): o campo aceita seleção múltipla (`arquivos[]`, até 10 —
  `AttachmentController::MAX_FILES_PER_UPLOAD`). Cada arquivo vira um anexo próprio, com hash e evento
  próprios, como se enviado um por um; um arquivo recusado (tipo) não impede os outros, e o aviso
  diz qual foi. O envio de um arquivo só (`arquivo`) continua aceito.
- **Posição na tela:** com o documento congelado, o cartão "Anexos" fica **acima do texto do
  documento**; no rascunho, abaixo dele (26/10/2026).

| Situação do documento | Enviar | Remover |
|---|---|---|
| Rascunho | Sim | Sim (arquivo e registro; a trilha guarda que existiu) |
| Aguardando assinatura | Sim | **Não** — o que entrou fica |
| Assinado (todos assinaram, esperando anexo) | Sim | Não |
| Finalizado, cancelado, expirado, recusado | Não | Não |

- **Obrigatório trava a CONCLUSÃO, não o congelamento nem a assinatura.** O anexo pode chegar a
  qualquer momento antes de o documento concluir. Com todos assinados e obrigatório faltando
  (`SignatureDocument::missingAttachments`), o `FinalizeSignatureDocument` sai sem finalizar: o
  documento fica `signed`, à espera. A tela mostra **"Pendente envio de anexo"**, em amarelo
  (`SignatureDocument::awaitsAttachments`, só exibição), e diz o que falta. O envio que completa a lista
  despacha a finalização (`SignatureAttachmentService::store`). Não é falha: não gera evento nem
  retentativa.
- **Tipos:** PDF, JPG e PNG, pelo **conteúdo** (`finfo`), nunca pela extensão. Até
  `SIGNATURE_ATTACHMENT_MAX_KB` (10 MB) cada.
- **Guarda:** disco privado do módulo (`documents/{id}/anexos/`), servido só por rota
  (`…/anexos/{id}`, quem vê o documento), com o **SHA-256** de cada arquivo.
- **Trilha:** `attachment_added` / `attachment_removed`, com item, tipo, tamanho e hash — **sem o nome
  do arquivo**, que costuma trazer o nome da pessoa.
- **Manifesto e relatório do gov.br** listam cada anexo com o hash. Os anexos **não entram no PDF
  assinado**: ficam à parte, ligados a ele pelo hash.
- **Servidor de arquivos:** vão ao lado do PDF assinado (`… - anexo 1 - Documento de identidade.jpg`),
  conferidos contra o hash.
- **Via por e-mail:** os anexos **não** vão. Identidade e comprovante de um signatário não são para os
  outros.
- **Permissão:** enviar e remover pedem `assinatura.documentos` (policy `attach`); não há permissão nova.

| Peça | Papel |
|---|---|
| `SignatureAttachmentService` | Itens pedidos (`normalize`), regras de estado, envio e remoção |
| `SignatureAttachment` (tabela `signature_attachments`) | Cada arquivo: item, rótulo no envio, nome original, tipo, tamanho, hash, quem enviou |
| `AttachmentController` | `POST …/{doc}/anexos`, `GET …/{doc}/anexos/{anexo}`, `DELETE …/{doc}/anexos/{anexo}` |
| `signature/partials/attachment-requirements.blade.php` | A lista de itens pedidos, a mesma no modelo e no documento |
| `documents/partials/attachments.blade.php` | O cartão "Anexos" da tela do documento |

## Assinatura pelo gov.br

Para quem não vem ao balcão. A pessoa assina o PDF do documento no portal do gov.br
(`assinador.iti.br`), com a própria conta gov.br (prata ou ouro), e o devolve ao atendente. O
atendente envia o arquivo na aba **Assinatura gov.br** da tela do documento; o Lara confere e,
aprovado, **registra a assinatura** — o signatário passa a "Assinou", e quando todos assinaram o
documento fecha e o arquivo que voltou vira o PDF final.

A API de assinatura do gov.br (em que o sistema pede a assinatura em nome da pessoa) só é liberada
para órgão público — por isso o caminho é a pessoa assinar por fora e devolver o arquivo.

**Certificado ICP-Brasil na mesma aba.** Quem tem e-CPF (A1, A3 ou em nuvem) pode assinar o mesmo PDF
no programa do certificado (qualquer um que faça atualização incremental, como o Adobe Reader ou o
assinador da AC) e devolvê-lo do mesmo jeito. A conferência é a mesma; muda a âncora (as raízes da
ICP-Brasil) e o tipo, que a aba, o relatório e a trilha mostram (`kind`):

| `kind` | Âncora | Lei 14.063/2020 |
|---|---|---|
| `govbr` | AC Raiz do Governo Federal do Brasil v1 (`resources/certs/govbr`) | Assinatura **avançada** |
| `icp-brasil` | AC Raiz ICP-Brasil v5, v6, v7, v10 a v13 (`resources/certs/icp-brasil`) | Assinatura **qualificada** |

Nada mais muda: mesma aba, mesma "um de cada vez", mesmo CPF no otherName `2.16.76.1.3.1` (o e-CPF
usa o mesmo leiaute). Gov.br e ICP-Brasil podem se somar no mesmo arquivo.

### O fluxo

1. **Congelar** o documento, como sempre.
2. **Preparar para o gov.br** (botão na aba). Isso:
   - aplica a **data automática** ao texto, se o modelo tiver o campo — refaz o PDF original e o
     hash, com o evento `signing_date_set`, como a leitura do QR faria. Por isso o PDF é baixado
     **depois** de preparar;
   - muda o prazo do documento para `SIGNATURE_GOVBR_TTL_DAYS` (7 dias): o vaivém por e-mail leva
     dias, e o prazo do balcão é de horas;
   - cancela o QR Code aberto e faz o tablet **parar de liberar** este documento
     (`SignatureSigner::releaseBlockReason`);
   - **modelo com visto:** retira o visto **deste documento** (`signature_documents.initials_waived_at`,
     `SignatureSigningDataService::waiveInitials`) e refaz o PDF original e o hash sem a faixa do visto,
     com o evento `initials_waived` (e `visto_dispensado` no `govbr_prepared`). A assinatura do gov.br
     cobre o arquivo inteiro, e o visto por página só existe no tablet. O modelo não muda; quem lê o
     visto pergunta ao documento (`SignatureDocument::requiresInitials()`). O relatório gov.br e o
     `/validar` dizem "Visto dispensado". Um PDF baixado **antes** de preparar ainda tinha a faixa e não
     é aceito (não é prefixo do original novo).
   Pode ser repetido: renova o prazo. Não tem volta para o tablet — para isso, cancela-se e emite-se
   outro documento.
3. **Enviar por e-mail** (botão na aba, ao lado de cada pendente, em qualquer ordem — o diretor só depois de quem é de
   fora da diretoria): o Lara manda o PDF a assinar e o passo a passo. A pessoa assina no gov.br e **entrega o arquivo
   no atendimento do clube** — o retorno **não é por e-mail** (decisão do usuário, 26/10/2026). (Ou o atendente envia o
   PDF por conta própria.)
4. O atendente envia o arquivo entregue na aba. Aprovado, o Lara registra a assinatura.
5. Com mais signatários, a próxima pessoa — qualquer uma — recebe **o arquivo com as assinaturas
   anteriores** — o e-mail do convite já o anexa, e a aba o oferece para baixar. As assinaturas se
   acumulam no mesmo PDF, e é o último que vira o final. A ordem é livre, mas é **um de cada vez**:
   duas pessoas assinando o mesmo arquivo ao mesmo tempo não se somam — a segunda a voltar é recusada
   ("não traz a assinatura de…") e assina de novo, sobre o arquivo da primeira.
6. Todos assinaram: o documento passa a "Assinado", a finalização roda, a via vai por e-mail a quem
   tem e-mail cadastrado e a cópia vai ao FTP, como no tablet.

### O convite por e-mail

O botão **Enviar por e-mail** (`GovbrInviteService::send`) aparece ao lado de **cada signatário
pendente**, com o e-mail dele preenchido e editável — o e-mail informado passa a ser o do signatário, que é para onde
a via final vai depois.

| O que vai | Detalhe |
|---|---|
| O PDF a assinar, anexado | O original — ou, a partir do segundo signatário, o arquivo devolvido pelo anterior (`SignatureDocument::govbrFileToSign`) |
| O passo a passo | Salvar sem mexer, assinar em `assinador.iti.br`, baixar o arquivo assinado, **entregar esse arquivo ao atendimento do clube** |
| O prazo | O do documento preparado para o gov.br |

- **Sem link para o Lara, e sem devolução por e-mail.** O Lara não é acessível de fora, e o arquivo
  assinado volta **pelo atendimento do clube**, nunca respondendo ao e-mail (26/10/2026 — até então o
  texto pedia "Responda a este e-mail"). O e-mail ainda sai com `Reply-To` = o e-mail do usuário que
  clicou, só para dúvidas.
- **Diretor espera** quem é de fora da diretoria: enquanto houver um pendente, o botão do diretor fica
  desligado e `GovbrInviteService::blockReason` recusa ("O convite ao diretor sai depois da assinatura de…").
- **Envio na hora, dentro da transação:** se o SMTP falhar, nada fica registrado como enviado, e o
  atendente vê o erro no mesmo clique.
- **Reenviar** manda o e-mail de novo (pode ser para outro endereço) e registra outro convite.
- O convite gera o evento `govbr_invite_sent`, com o e-mail **mascarado** — nunca o e-mail inteiro.

A aba lista os convites enviados: para quem, e-mail mascarado, quando e por quem.

### Quando o gov.br não serve

`SignatureDocument::govbrBlockReason()` — a mesma resposta na tela e no servidor:

| Situação | Por quê |
|---|---|
| Documento com campo marcado em **Perguntar ao signatário** | As respostas entram no tablet, antes da leitura; o gov.br assina o PDF como está. A marcação é do documento, não do modelo: o mesmo modelo vai ao gov.br quando o atendente preenche todos os campos |
| **Alguém já assinou no tablet** | A assinatura do tablet vai num PDF re-renderizado e a do gov.br no arquivo original — nenhum arquivo carrega as duas. Um documento é assinado por um caminho só |

Modelo com **visto** NÃO barra mais (desde 20/10/2026): o visto é dispensado no documento ao preparar
(ver "O fluxo", passo 2).

**Por que não dá para misturar, em nenhum sentido:**

- **gov.br e depois tablet:** a assinatura do gov.br está nos bytes do PDF. Pôr o traço do tablet
  exige re-renderizar (o dompdf não edita PDF pronto), e re-renderizar desfaz a do gov.br.
- **Tablet e depois gov.br:** o traço do tablet só entra no PDF na finalização. O gov.br assinaria o
  original, sem o traço; e a finalização, ao desenhá-lo, desfaria a do gov.br.

O segundo sentido é o único viável no futuro: gerar um PDF intermediário com os traços do tablet já
desenhados, o gov.br assinar ESSE arquivo e a finalização não re-renderizar (relatório à parte, como
no gov.br puro). O primeiro exigiria mudar o conteúdo da página depois da assinatura, o que o DocMDP
do gov.br (`/P 2`) proíbe: a atualização incremental que o módulo escreve (lacre) só acrescenta
assinatura.
| Documento não congelado, ou fora de "Aguardando assinatura" | Não há o que assinar |

**Foto e conferência de CPF na tela não existem no gov.br.** Um modelo que pede foto pode ser
assinado por lá: a identidade é a da conta gov.br, provada pelo certificado com o CPF. O documento
fica sem foto, e a aba e a trilha dizem por onde a pessoa assinou.

### Quando um arquivo aprovado não registra assinatura

A conferência aprova o ARQUIVO; registrar a assinatura tem regras a mais (`GovbrCheckService::conclude`).
É tudo ou nada: se uma regra não fecha, ninguém é dado como assinado, e o motivo aparece na aba.

| Regra | Motivo na tela |
|---|---|
| O documento foi preparado para o gov.br | "não foi preparado para o gov.br" — sem preparar, o envio só confere e registra |
| Assinado sobre o **original** | Assinado sobre o PDF final do tablet: o documento já está fechado |
| Quem já assinou pelo gov.br **continua no arquivo** | "não traz a assinatura de Fulano, que já assinou" — a próxima pessoa assinou o original em vez do arquivo da anterior |
| Há assinatura nova | "Nenhuma assinatura nova" — o mesmo arquivo enviado de novo |

Registrada, a assinatura grava: `signed_at` = a hora **declarada na assinatura do gov.br** (a do
Lara fica no evento), `govbr_check_id` = a conferência, `wants_copy` = tem e-mail. O evento `signed`
leva `via: gov.br`, o número de série e o emissor do certificado.

### O que é conferido

| Conferência | Como | Reprova quando |
|---|---|---|
| É este documento | O arquivo devolvido **começa, byte a byte**, pelo PDF do Lara: o original (`original_sha256`) ou o final do tablet (`final_sha256`). O arquivo de referência é conferido contra o hash gravado antes de servir | A pessoa assinou outro documento, ou um PDF que foi salvo de novo |
| Tem assinatura digital | Há ao menos um `/ByteRange` | Devolveram o PDF sem assinar, ou impresso e escaneado |
| A assinatura cobre o documento inteiro | O primeiro trecho do `ByteRange` começa no byte 0 e contém todo o PDF de referência | A assinatura cobre só parte |
| A assinatura confere com o conteúdo | `openssl_cms_verify` dos bytes do `ByteRange` contra o CMS | Alguém mexeu no arquivo depois de assinado |
| Certificado do gov.br ou da ICP-Brasil | Cadeia subida à mão, elo por elo (`openssl_x509_verify`), até uma raiz guardada no repositório (gov.br ou ICP-Brasil), com a validade de cada elo conferida **na hora da assinatura e na da conferência**. Elo que falta é buscado no AIA do certificado | Certificado de outra AC; elo que não confere; certificado vencido |
| Assinada por um signatário deste documento | CPF do certificado (otherName `2.16.76.1.3.1`) comparado com o CPF dos signatários — **nunca o nome** | Quem assinou não está na lista |
| Assinada depois de o documento ser congelado | `signingTime` do CMS (gov.br) ou, sem ele, o `/M` do dicionário da assinatura (PAdES proíbe `signingTime`) ≥ `frozen_at`, com 5 min de folga de relógio. A tela diz de onde veio a hora | Hora anterior ao congelamento |
| Nada foi alterado depois da última assinatura | A última assinatura cobre o arquivo até o último byte | Bytes acrescentados depois dela |
| Certificado não revogado (por assinatura) | LCR de cada certificado da cadeia, assinada pela AC de cima e em vigor — ver "Revogação" | Certificado na lista, **qualquer que seja a data** (a hora da assinatura é declarada, não carimbada). Lista indisponível: "não conferido", sem reprovar — a menos que `SIGNATURE_PKI_REVOCATION_REQUIRED` |

Com vários signatários, cada um assina **o arquivo devolvido pelo anterior**: as assinaturas se
acumulam no mesmo PDF, e o Lara confere todas, na ordem em que foram feitas. A ordem dos signatários
na lista não importa.

### O que a Fase 0 provou (amostra real, 07/10/2026)

Antes de escrever o validador, um PDF do Lara foi assinado no gov.br e inspecionado. Os fatos que o
desenho usa:

- **Atualização incremental.** O assinador **acrescenta** a assinatura ao fim do PDF e não reescreve
  o resto — o original é prefixo exato do assinado. Vale também sobre o PDF final do tablet.
- **Formato.** `/SubFilter adbe.pkcs7.detached`, sha256 + RSA, `signingTime` como atributo assinado,
  **sem** carimbo de tempo e sem `/DSS`. O CMS vem em **BER com comprimento indefinido** — por isso o
  `Asn1` é um leitor BER, e o enchimento de zeros do `/Contents` não pode ser cortado "pelos zeros do
  fim".
- **CPF.** No subjectAltName, otherName `2.16.76.1.3.1`, um OCTET STRING de 45 dígitos no leiaute
  ICP-Brasil: nascimento (8) + **CPF (11)** + NIS (11) + RG (15). O subject só tem o nome.
- **Certificado.** Emitido para a conta e reaproveitado (validade de 3 anos na amostra), cadeia
  Raiz → Intermediária → AC Final do Governo Federal do Brasil v1, política `2.16.76.3.2.1.1`.
- **Revogação.** Lista pública em `http://repo.iti.br/lcr/public/acf/LCRacfGovBr.crl` (~3 MB, ~71
  mil seriais, atualizada a cada 2 h). Consultada desde 08/10/2026 (ver "Revogação").

As amostras **não** estão no repositório: têm CPF e e-mail reais.

### A cadeia de confiança

`resources/certs/govbr/cadeia-govbr.pem` é a cadeia oficial, baixada de
`https://repo.iti.br/docs/Cadeia_GovBr-der.p7b` (sha256 do `.p7b`:
`dbf22f7c15ace9c37e6b4141271695a17dc445b5a04c003ced94322ad905879f`). É a **única** âncora: um
certificado que não sobe até a raiz deste arquivo é recusado, e um certificado embutido na assinatura
pode servir de elo, nunca de âncora. Os três certificados vencem em **junho de 2033** — antes disso, o
arquivo precisa ser trocado pela cadeia nova do ITI.

| Certificado | SHA-256 |
|---|---|
| Autoridade Certificadora Raiz do Governo Federal do Brasil v1 | `16:3B:D0:03:BC:0D:F2:BE:AB:88:17:4B:6D:5B:45:0B:6E:1D:C9:73:A6:4B:2D:C2:A3:32:18:54:4F:49:EF:4E` |
| AC Intermediaria do Governo Federal do Brasil v1 | `C9:6F:8B:6D:E0:22:11:97:17:DF:D2:AE:B6:33:4E:98:03:6A:AE:B5:C3:81:8B:4D:84:BE:1D:26:79:26:D2:E7` |
| AC Final do Governo Federal do Brasil v1 | `96:BB:5C:41:85:9F:C5:1D:88:F3:05:51:7B:E6:41:D9:EF:4A:5D:60:1B:1B:B2:35:2E:71:61:E9:7F:F8:B8:52` |

**Raízes da ICP-Brasil** (`resources/certs/icp-brasil/raizes-icp-brasil.pem`, config
`signature.icp_brasil.trust_bundle`): v5, v6, v7, v10, v11, v12 e v13, baixadas de
`https://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasil<versao>.crt` em 08/10/2026. A v2 venceu
em 2023 e ficou de fora. As impressões digitais e as datas de vencimento estão no cabeçalho do próprio
arquivo (a v5 vence em **março de 2029**). Raiz nova do ITI → acrescente ao arquivo. As ACs
intermediárias (são dezenas) não ficam no repositório: vêm embutidas na assinatura ou são baixadas do
endereço **AIA** (`caIssuers`) do certificado (`Pki\PkiRepository::issuers`, guardadas
`SIGNATURE_PKI_ISSUER_CACHE_DAYS` dias). Elo baixado só serve se a chave conferir, e a âncora
continua sendo só o repositório.

Os dois `.pem` são exceção explícita ao `*.pem` do `.gitignore` (só certificados públicos de AC).

### Revogação

`Pki\RevocationChecker` confere cada certificado da cadeia (menos a raiz) na **LCR** que ele declara
(CRL Distribution Points):

1. `Pki\PkiRepository::crl` devolve a lista guardada (disco das assinaturas, `signature/pki/lcr/`)
   enquanto `nextUpdate` não passou; senão baixa (`SIGNATURE_PKI_TIMEOUT_SECONDS`). Download falhou →
   devolve a guardada, mesmo vencida, e quem decide é o checker.
2. A lista só vale se a **assinatura dela confere com a chave da AC de cima** e se está em vigor.
3. `Pki\CertificateRevocationList` lê a LCR (DER, com o `Govbr\Asn1`) e indexa as séries uma vez —
   a do gov.br (~71 mil entradas) em ~0,4 s e ~28 MB, com cursor, sem montar todos os nós.

| Resultado | Quando |
|---|---|
| `true` | Todos os certificados com LCR declarada conferidos em lista válida, e nenhum nela |
| `false` | Algum certificado está na lista — **reprova**, e o detalhe diz a data da revogação |
| `null` | Certificado do signatário sem LCR declarada, lista fora do ar sem cópia em vigor, lista assinada por outra chave, consulta desligada. Não reprova — com `SIGNATURE_PKI_REVOCATION_REQUIRED=true`, reprova |

- **Por que reprovar mesmo revogado depois da assinatura:** a hora da assinatura é declarada (pelo
  serviço do gov.br, ou pelo programa de quem assinou), não vem de carimbo de tempo independente. Quem
  teve o certificado revogado assina de novo.
- **Rede:** a conferência é a única parte do módulo que sai para a internet (LCR e AIA, HTTP do ITI e
  das ACs). `SIGNATURE_PKI_NETWORK=false` onde o servidor não sai: a conferência segue com o que já
  foi baixado. A suíte de testes roda com ela desligada (`phpunit.xml`).
- **`signature:crl`** (de hora em hora, e no deploy) renova as listas do gov.br
  (`signature.pki.crl_urls`) e todas as já consultadas (`signature/pki/lcr/indice.json`), só as
  vencidas; `--forcar` baixa todas. Assim a aba não espera o download de 3 MB. Medido em 08/10/2026
  com a amostra real: conferência com download em ~1,2 s.

### Onde fica o quê

| Peça | Papel |
|---|---|
| `GovbrSignatureValidator` | Confere o PDF contra o documento e devolve um `GovbrValidationResult`. Não grava nada |
| `GovbrValidationResult` | As conferências (`ok` = sim / não / **null = não conferido**) do arquivo e de cada assinatura; válido quando nada reprovou |
| `Asn1` | Leitor BER mínimo: CPF do otherName e `signingTime` do CMS, que a extensão OpenSSL do PHP não entrega |
| `GovbrCheckService` | `prepare()` (preparar para o gov.br) e `check()`: guarda o arquivo (`documents/{id}/govbr/`, disco privado), grava a conferência e o evento `govbr_checked` e, aprovado, conclui a assinatura (`conclude()`) pela máquina de estados |
| `SignatureGovbrCheck` (tabela `signature_govbr_checks`) | Cada envio, **aprovado ou recusado**, com o resultado inteiro (CPF mascarado), quem enviou e `conclusion` (quem assinou, se fechou o documento, ou o motivo de não concluir) |
| `signature_documents.govbr_sent_at` / `govbr_check_id` | Quando foi preparado para o gov.br / a conferência cujo arquivo é o PDF final |
| `signature_signers.govbr_check_id` | A conferência pela qual a pessoa assinou (nulo = tablet) |
| `GovbrInviteService` + `SignatureGovbrInvite` (tabela `signature_govbr_invites`) | O convite por e-mail: para quem, quando e por quem |
| `SignatureGovbrInviteMail` (`emails/signature-govbr-invite`) | O e-mail do convite, com o PDF anexo e `Reply-To` do atendente (só para dúvidas: o arquivo volta pelo atendimento) |
| `GovbrController` | `POST …/{doc}/govbr/preparar`, `POST …/{doc}/govbr` (envio) — ambos `throttle:20,1` —, `POST …/{doc}/govbr/signatarios/{s}/convite` (`throttle:10,1`) e `GET …/{doc}/govbr/{conferencia}/pdf` (o arquivo, preso ao documento) |
| `documents/partials/govbr.blade.php` | A aba, aberta por `?aba=govbr` |
| `Pki\Certificates`, `Pki\CertificateRevocationList`, `Pki\PkiRepository`, `Pki\RevocationChecker` | PEM/DER/PKCS#7, endereços de LCR e AIA, leitura da LCR, download com cache, consulta de revogação |
| `resources/certs/govbr`, `resources/certs/icp-brasil` | As âncoras de confiança (públicas, versionadas) |

- **Quem prepara e envia:** `assinatura.documentos` (policy `checkGovbr`) — é a mesma operação de
  quem libera o tablet. **Quem vê** o resultado e baixa o arquivo: quem vê o documento.
- **Recusa e "não concluiu" voltam como "Atenção"**, e não como erro: o aviso de erro do sistema
  manda procurar a TI, e aqui o que resolve é pedir à pessoa o arquivo certo.
- **Só documento congelado:** antes disso não existe PDF para assinar.
- **A trilha não vaza dado:** o evento `govbr_checked` guarda o veredito, o hash, os ids dos
  signatários aprovados e as **chaves** do que reprovou — sem nome nem CPF.
- **Arquivo recusado também é guardado:** é o registro do que chegou e de por que não serviu.

## Arquivo no servidor de arquivos (FTP)

Depois de finalizado, o PDF assinado ganha uma **cópia** no servidor de arquivos, organizada para
quem procura um documento sem abrir o sistema:

```
Lara/DocumentosAssinados/
  Contrato de Locacao de Espaco para Evento/      ← tipo: o modelo
    Maria de Souza/                               ← pessoa: o primeiro signatário
      2026-10-03 - Maria de Souza e Joao Pereira - 6W5YTTTJGRCU.pdf
  Documentos avulsos/                             ← os enviados prontos, em PDF
    Empresa X/
      2026-10-03 - Contrato de patrocinio - Empresa X - 8KQ2M4.pdf
  Freelancers/                                    ← contratos de freelancer (ver freelancers.md)
    Joao Antonio da Conceicao/
      2026-10-03 - Contrato - C1234.pdf
      2026-10-03 - Termo aditivo - C1240.pdf
```

A organização é **tipo → pessoa**, a mesma para os documentos do balcão e para os contratos de
freelancer. As regras de nome moram num lugar só, `App\Support\ArchivePath`:

- **Tipo primeiro**: é a pergunta que vem antes ("cadê os contratos de locação?"). Documento
  enviado pronto não tem modelo, então todos ficam em **Documentos avulsos** — e, como ali a
  pasta não diz o que o documento é, o **título** entra no nome do arquivo.
- **Pessoa**: o **primeiro signatário**, que é de quem o documento trata. Tudo o que uma pessoa
  assinou de um tipo fica junto.
- **Data da assinatura** abrindo o nome do arquivo, em ano-mês-dia: dentro da pasta, os
  documentos ficam em ordem de data sozinhos.
- **Quantidade de signatários**, nos nomes: um, o nome; dois, os dois; três ou mais, os dois
  primeiros e "e mais N".
- **Código de validação** no fim — torna o nome único e liga o arquivo à página
  `/validar/{código}` e ao registro no sistema.
- **CPF não entra** em nome de pasta nem de arquivo. O preço: duas pessoas de nome idêntico
  dividem a mesma pasta (os arquivos não se confundem, por causa do código).
- **Sem acento** e sem os caracteres que Windows e FTP recusam: servidor FTP antigo troca "ç" por
  lixo, e pasta com nome quebrado ninguém acha.

> A organização anterior era modelo → ano → mês. Documento arquivado antes da mudança **fica
> onde está** (o `archive_path` dele continua apontando para lá); só os novos seguem a regra
> nova. Para refazer um antigo no lugar novo: limpe o `archived_at` dele e rode
> `php artisan signature:archive` — a cópia antiga precisa ser apagada à mão.

O arquivo de verdade continua no disco privado do módulo — é dele o `final_sha256`, e é dele que
o painel baixa. O FTP é cópia, e por isso é conferida: o que sai tem de bater com o hash gravado,
e o tamanho do que chegou é conferido depois do envio.

| Peça | Papel |
|---|---|
| `ArchiveSignatureDocument` (job) | Despachado pela finalização; 4 tentativas com espera crescente |
| `signature:archive` (agendado, de hora em hora) | Reenvia o que ficou pendente — FTP fora do ar, ou documento finalizado antes de o arquivamento existir |
| `signature:archive --testar` | Confere a conexão e cria a pasta-raiz, sem enviar nada |
| `archived_at` / `archive_path` | Quando e onde a cópia foi gravada; aparecem na tela do documento |
| Eventos `archived` / `archive_failed` | O arquivamento e a falha dele ficam na trilha |

Uma falha do FTP **não perde nem trava nada**: o documento continua finalizado, o PDF continua
no disco do módulo, e a cópia fica pendente até o servidor voltar.

Vem **desligado** (`SIGNATURE_ARCHIVE_ENABLED=false`): a máquina de desenvolvimento tem as
credenciais do FTP, e um teste local não deve criar "documento assinado" na pasta de produção.
Para ligar no servidor:

```bash
# .env
SIGNATURE_ARCHIVE_ENABLED=true

php artisan signature:archive --testar   # confere a conexão
php artisan signature:archive            # envia os já finalizados
```

O disco é o `signature_archive` (`config/filesystems.php`), que por padrão usa o mesmo servidor
e a mesma conta das variáveis `FTP_*`; as `SIGNATURE_FTP_*` só são necessárias se for outro.

Os **contratos de freelancer** usam o mesmo disco e a mesma pasta-raiz, com chave própria para
ligar (`FREELANCER_ARCHIVE_ENABLED`) e comando próprio (`freelancers:archive`) — ver
[Freelancers](freelancers.md#arquivo-no-servidor-de-arquivos-ftp).

> FTP comum trafega sem criptografia, e esses PDFs têm nome, CPF mascarado e assinatura. Se o
> servidor aceitar FTPS, ligue `SIGNATURE_FTP_SSL=true`.

A cópia é **só de ida**: renomear, mover ou apagar um arquivo no FTP não muda nada no sistema, e
o sistema não apaga nada lá.

## Modelos de documento

O modelo é **versionado por linha**: salvar uma revisão não altera a linha em uso, cria a versão
seguinte e desativa a anterior. Um documento aponta para a linha exata que gerou o texto que a
pessoa leu — revisar o modelo nunca reescreve, retroativamente, o que já foi assinado.

> É a mesma garantia das redações de contrato de freelancer, por outro caminho: lá o texto mora
> em arquivo (`contract/vN/`), versionado no git e lacrado por teste de sha256, porque quem
> revisa é o jurídico, por commit. Aqui quem escreve é a gerência, por tela — não há commit para
> versionar, então a versão mora no banco.

No corpo, `[[nome_da_variavel]]` marca um dado preenchido pelo atendente e `[[assinatura]]` marca
onde ficam os campos de assinatura (sem o marcador, eles vão para o fim). O HTML é saneado na
gravação pelo `HtmlSanitizer`, com título e listas liberados só para este módulo.

### Escrever o modelo no Word

Ninguém precisa escrever HTML. O caminho da tela é **enviar o documento do Word**:

1. No `.docx`, onde entra um dado que muda a cada atendimento, escreva o nome do campo entre
   colchetes duplos: `[[Data do evento]]`, `[[Valor total]]`. O que está escrito ali **é o rótulo**
   que o atendente vai ver; a chave (`data_do_evento`) é derivada pelo sistema.
2. Onde a pessoa assina, escreva `[[assinatura]]` numa linha só dela. Nome e CPF do signatário
   não são campo — entram sozinhos na área de assinatura.
3. Em **Assinaturas → Modelos → Novo modelo**, clique em **Escolher arquivo .docx**. A tela
   preenche o texto, monta a lista de campos e mostra a prévia. Nada é gravado até salvar.

Na lista de **Assinaturas → Modelos**, cada linha tem **Emitir Documento** (só em modelo ativo;
abre a emissão já com o modelo escolhido) e **Ver**. O **Revisar** fica dentro do **Ver**, na
tela do modelo.

Revisar um modelo é o mesmo gesto: corrige-se o `.docx` (sem o original em mãos, baixe a base em
Word — ver abaixo), envia-se de novo em **Revisar** e
salva-se — nasce a versão seguinte. Os rótulos e a obrigatoriedade já ajustados na tela são
mantidos para os campos que continuam existindo.

O `.docx` é **convertido**, e não guardado: o modelo continua sendo o `body_html`, e o Word é só o
jeito de escrevê-lo (`DocxTemplateImporter`). Manter o Word como origem exigiria um conversor de
escritório no servidor e um segundo caminho de renderização — a divergência entre "o que o tablet
mostra" e "o que o PDF imprime" que o `SignatureDocumentRenderer` existe para impedir.

| Atravessa a conversão | Fica de fora |
|---|---|
| Parágrafos, títulos (estilos Título 1, 2, 3) | Fonte, cor, tamanho e alinhamento |
| Negrito, itálico, sublinhado | Cabeçalho e rodapé do Word |
| Marcadores e tabelas | Imagens e caixas de texto (a tela avisa) |
| Numeração automática, como **texto** ("3.1.") | Células mescladas |

A numeração vira texto de propósito: num contrato as cláusulas numeradas são intercaladas por
parágrafos comuns, e uma lista HTML recomeçaria do 1 a cada interrupção. Texto **removido** em
controle de alterações não entra; o inserido entra.

Documento em **PDF** ou **.doc**: abrir no Word e salvar como `.docx`. PDF não tem estrutura de
texto para converter com segurança.

### Baixar a base em Word (28/10/2026)

Como o `.docx` não é guardado, a base em Word é **gerada** do texto do modelo
(`DocxTemplateExporter`, rota `signature-templates.docx`, `GET assinatura/modelos/{modelo}/word`,
permissão `assinatura.modelos`). Botões: **Baixar Word** no topo da tela do modelo, **Word** em cada
linha do histórico de versões (baixa aquela versão) e **Baixar o texto atual em Word** dentro de
**Revisar**, ao lado de "Escolher arquivo .docx". Serve também para modelo escrito direto na tela,
que nunca teve Word.

O arquivo é feito para **voltar**: edita-se no Word e envia-se em **Revisar**. Por isso:

- campo `[[chave]]` sai como `[[Rótulo]]` quando o rótulo, reimportado, dá a mesma chave
  (`DocxTemplateImporter::keyFor`); senão sai `[[chave]]`. A tela casa os campos importados com os
  do formulário **pela chave** — mantida a chave, tipo, pergunta, opções e obrigatoriedade continuam;
- `[[assinatura:parte]]` sai `[[assinatura: Rótulo]]` (mesma regra), sempre numa linha só dele;
  `[[assinatura]]` sai igual;
- títulos usam os estilos "heading 1…3" (o `<h2>` vira Título 1), lista com marcador vira lista do
  Word, tabela vira tabela; lista numerada sai com o número como texto (é o que o importador faria),
  citação sai recuada, `<hr>` vira linha.

O Word é cópia de trabalho: o modelo continua sendo o `body_html`. A escrita do XML é do
`App\Support\DocxWriter`, o mesmo do Word do contrato (`ContractDocxExporter`). Teste:
`SignatureTemplateDocxExportTest` (ida e volta pelo importador).

Um `[[campo]]` que esteja no texto e não na lista de campos é declarado sozinho na gravação
(obrigatório). Sem isso ele não viraria pergunta para o atendente e sairia impresso, com
colchetes, no documento que a pessoa assina. O HTML continua editável em **Editar o texto em HTML
(avançado)**.

Cada modelo define ainda: **conferência de identidade** (4 dígitos / CPF completo / código enviado
por e-mail / nenhuma), **foto obrigatória** e **prazo de guarda**.

### Conferência por código enviado por e-mail

Opção `email` da conferência de identidade (`SignatureTemplate::IDENTITY_EMAIL`). Na etapa de
identidade, o tablet pede o envio (`POST /assinatura/kiosk/documento/{id}/identidade/codigo`) e o
servidor manda **um código de 6 números ao e-mail do signatário**; a pessoa o digita no mesmo
teclado do CPF (`SignatureCaptureService::sendIdentityCode` / `confirmIdentity`).

- **O e-mail é exigido no congelamento:** com essa opção, signatário sem e-mail trava o congelar
  ("Informe o e-mail de: …").
- **O endereço não vai à tela do tablet**, só o modo. A mensagem diz "o seu e-mail cadastrado".
- **O banco guarda só o HMAC** do código (chave do app + id da liberação), nas colunas
  `signature_requests.identity_code_*` — 6 números em sha256 puro se descobrem em segundos.
- **Prazo:** `SIGNATURE_IDENTITY_CODE_TTL_MINUTES` (10). Usado ou substituído, o código deixa de valer.
- **Reenvio:** até 3 envios por liberação, com 1 minuto entre eles; cada reenvio troca o código.
- **Tentativas:** as mesmas 5 da conferência por CPF; código errado ou vencido gasta uma.
- **E-mail na hora, fora da fila**, dentro da transação: SMTP fora do ar responde 503 ao tablet
  ("chame o atendente") e nada conta como enviado. O código em claro nunca vai para a tabela de jobs.
- **Trilha:** `identity_code_sent`, com o e-mail mascarado e o número do envio — nunca o código. O
  manifesto diz "Conferência de identidade: Código enviado por e-mail (m***a@…)".

> A exclusão por retenção **não está implementada**. O campo registra a decisão para que ela
> exista antes de haver o que apagar.

### Tipos de campo

Cada campo do modelo tem um **tipo**, escolhido na tela do modelo. O tipo decide como o valor é
conferido, como é guardado (forma canônica, sem máscara) e como sai escrito no documento:

| Tipo | Aceita | Sai no documento |
|---|---|---|
| Texto / Texto longo | livre (500 / 5.000 caracteres) | como digitado |
| Número | `150`, `2,5` | `150`, `2,5` |
| Valor em reais | `1.500,00`, `R$ 80` | `R$ 1.500,00` |
| CPF | com ou sem máscara, dígitos conferidos | `123.456.789-09` |
| CNPJ | numérico ou alfanumérico, dígitos conferidos | `11.222.333/0001-81` |
| E-mail | endereço válido | em minúsculas |
| Telefone | DDD + número (com ou sem `+55`) | `(24) 99999-1234` |
| CEP | 8 números | `27255-125` |
| Data abreviada | seletor de data | `03/10/2026` |
| Data por extenso | seletor de data | `3 de outubro de 2026` |
| Hora | `14:30` | `14:30` |
| Opção única | uma das opções do modelo | a opção |
| Múltipla escolha | várias das opções do modelo | `Piscina, Academia e Quadra` |
| Sim ou não | sim / não | `Sim` / `Não` |
| Data da assinatura (abreviada ou por extenso) | **ninguém preenche** | a data do servidor no ato |

Na importação do Word o tipo é **sugerido pelo nome** do campo (`[[CPF do responsável]]` → CPF,
`[[Data do evento]]` → data, `[[Valor total]]` → reais, `[[Data da assinatura]]` → automática).
É só um palpite: a tela mostra e deixa trocar. Modelo gravado antes dos tipos continua valendo —
campo sem tipo é texto.

> O valor em reais já sai com `R$`. Um modelo que escreva `R$ [[valor]]` no texto e marque o campo
> como "Valor em reais" imprime `R$ R$ 1.500,00` — tire o `R$` do texto.

Tudo isso mora em `SignatureFieldTypes`, que é o único lugar que sabe o que é um CPF válido: o
formulário do atendente e o do tablet passam pela mesma conferência.

### Perguntas a quem assina

**Quem responde cada campo é decidido no DOCUMENTO, não no modelo.** O modelo só declara os
campos (tipo, obrigatoriedade, opções e, se quiser, o texto da **Pergunta no tablet**). No
preenchimento, o atendente vê todos os campos, e cada um tem a caixa **Perguntar ao
signatário**: marcada, o controle some, o que estiver digitado nele é ignorado, e o campo vira
uma pergunta que a pessoa responde no tablet (o texto escrito no modelo ou, em branco, o nome do
campo). A obrigatoriedade de um campo perguntado é conferida no tablet, não no congelamento.

**A tela do atendente usa a mesma pergunta (13/10/2026).** No formulário do documento
(`documents/partials/field.blade.php`), o título de cada campo é a `question` — a mesma frase que
o tablet mostra —, para o atendente preencher lendo a pergunta para a pessoa. Quando a pergunta é
diferente do nome do campo, o nome aparece embaixo, em letra pequena, só para conferir com o
modelo. O "Usar dados do signatário" continua usando o nome do campo (`data-fill-label`) para
adivinhar o dado. Teste: `test_formulario_do_atendente_mostra_a_pergunta_do_tablet`.

- Gravado em `signature_documents.signer_field_keys` (JSON, lista de chaves), pelo
  `StoreSignatureDocumentRequest::signerFieldKeys()` — só chaves de campos do modelo que alguém
  preenche; automático e chave inventada não entram.
- `SignatureDocument::fieldDefinitions()` junta os campos do modelo com essa escolha
  (`ask_signer` por campo); `attendantFields()`, `signerFields()` e `automaticFields()` do
  DOCUMENTO são o que o renderer, o congelamento, o tablet, a tela e o manifesto leem. O modelo
  tem só `manualFields()` (todos os não automáticos) e `automaticFields()`.
- **Por que mudou (08/10/2026):** com a marcação no modelo, um modelo com perguntas nunca ia ao
  gov.br. Agora qualquer modelo vai, desde que o atendente preencha tudo (o visto, se houver, é
  dispensado no documento ao preparar — desde 20/10/2026).
- **Documentos antigos:** a migration `2026_10_11_100300` criou a coluna e copiou para cada
  documento o que o modelo dele marcava (`ask_signer` no JSON das variáveis, fora os
  automáticos). O `ask_signer` que sobrou no JSON dos modelos não é mais lido, e a tela do
  modelo não o grava mais.

O fluxo do tablet ganha uma etapa, **antes** da leitura:

1. Leitura do QR.
2. **Formulário**: as perguntas, uma abaixo da outra, com o controle de cada tipo.
3. O servidor confere as respostas, refaz o PDF com elas e grava o hash novo.
4. Leitura do documento — já com as respostas — e daí em diante como sempre.

Na tela do documento há o botão **Corrigir respostas**: a pessoa viu um erro no que digitou,
volta, corrige, e o documento é refeito. Vale **até a primeira assinatura**; dali em diante o
texto não muda mais, nem para o signatário seguinte (que não vê formulário).

### Campo restrito ao atendente (29/10/2026)

No modelo, cada campo (não automático) tem **Só o atendente responde** (`attendant_only` no JSON
de `signature_templates.variables`, versionado com o modelo). Campo restrito nunca vai a quem assina:

- a tela do documento (`documents/partials/field.blade.php`) não mostra a caixa **Perguntar ao
  signatário** e diz "Só o atendente responde (regra do modelo)";
- o `StoreSignatureDocumentRequest` recusa a marca forçada (`data.<chave>`);
- `SignatureDocument::fieldDefinitions()` nunca marca `ask_signer` num campo restrito, nem que a
  chave esteja em `signer_field_keys` — vale também para o link remoto, que lê `signerFields()`.

Documento já criado aponta para a versão dele do modelo: restringir um campo numa revisão não
muda documento antigo.

### Conferência das respostas pelo atendente (29/10/2026)

No **tablet do balcão**, documento com pergunta a quem assina só é assinado depois de o
atendente conferir as respostas (decisão do usuário: vale para **todo** documento com pergunta).
Ficam de fora o link remoto (não há atendente ao lado), a Diretoria (o próprio diretor abre, com
matrícula e PIN) e o Termo de Menores (autoatendimento). Peças: `SignatureAnswerCheckService`,
`SignatureAnswerCheck` (tabela `signature_answer_checks`), `AnswerCheckController`,
`documents/partials/answer-check.blade.php`.

1. **Ao vivo, campo a campo.** A cada campo alterado (600 ms depois da última tecla), o tablet manda o
   rascunho a `quiosque.answers-draft`. Ele fica **só no cache do servidor**, ligado à liberação
   (TTL da sessão): não entra no documento, não vai à trilha e o tablet não guarda nada. O polling
   do painel (`signature-documents.status`, chave `answer_check`) o mostra no cartão
   **Conferência das respostas**.
2. **"Continuar"** grava as respostas como sempre (`SignatureSigningDataService::answer`: PDF e hash
   refeitos, evento `form_answered`) e a conferência fica **pendente**. A pessoa já pode ler o
   documento e confirmar a identidade; o tablet segura o **aceite** ("Aguardando o atendente
   conferir as suas respostas") até a aprovação chegar pelo batimento (`answer_check` no `/sessao`).
3. O atendente (`assinatura.documentos`, policy `checkAnswers`):
   - **Aprovar** (`signature-documents.answers.approve`): libera o aceite e o traço. Evento `answers_approved`.
   - **Devolver com observação** (`answers.return`): o tablet volta ao formulário com o recado. O
     reenvio grava `signing_answered_at` depois da devolução e a conferência volta a pendente. Evento
     `answers_returned` **sem** a observação (o manifesto é impresso); a observação fica só na tabela.
   - **Corrigir** (`answers.correct`): o atendente edita as respostas, `SignatureSigningDataService::correct`
     confere pelos tipos, refaz PDF e hash (evento `form_corrected`, só as chaves alteradas), e a
     correção **já aprova**. O tablet percebe o `pdf_version` novo e manda a pessoa ler de novo.

**Quem garante é o servidor:** `SignatureCaptureService::capture` chama
`SignatureAnswerCheckService::assertApproved` (409 enquanto não aprovado). A aprovação vale para o
`document_sha256` em que foi dada: resposta mudada depois (a pessoa voltou em **Corrigir respostas**)
é outro `original_sha256`, e a conferência volta a ser exigida sozinha — nada é apagado. Depois da
primeira assinatura (`signingDataIsOpen()` fecha) não há mais conferência, e os próximos
signatários não passam por ela. A chave `answer_check` **não existe** na resposta da API do link
remoto: a API do site não muda.

| Situação (`SignatureAnswerCheckService::status`) | Quando |
|---|---|
| `filling` | a pessoa ainda não tocou em "Continuar" (`signing_answered_at` nulo) |
| `pending` | respostas gravadas, sem aprovação válida |
| `returned` | última ação foi devolver e a pessoa ainda não reenviou |
| `approved` | última ação foi aprovar, para o `original_sha256` de agora |

### Modelo vinculado (29/10/2026)

"Quem assina X assina também Y", **num sentido só**: um documento de X traz Y junto; um documento
só de Y continua só Y. O texto de Y entra **no mesmo documento** de X (mesmo PDF, um código de
validação, um hash, uma via), em página nova (`page-break-before`), e o traço único de quem assina
é carimbado no lugar de assinatura de cada parte (decisões do usuário, 29/10/2026).

**No ato de vincular** (tela do modelo, **Modelos exigidos junto**), a tela compara os campos e as
partes dos dois (`signature-templates.link-info` devolve o composto de Y) e grava em
`signature_templates.linked_templates`, versionado com X:

- `fields`: campo de Y ⇒ campo de X **do mesmo tipo**, mesmo com chave diferente (`nome_completo` ⇒
  `nome`), ou `null` (campo próprio). Sem entrada, a regra padrão: chave e tipo iguais = mesmo campo.
  Campo próprio com chave já usada ganha sufixo (`telefone_2`). Campo ligado: preenchido uma vez,
  sai nos dois textos; obrigatório e restrito somam (o mais exigente).
- `parties`: parte de Y (ou `_todos`, o `[[assinatura]]` de Y) ⇒ parte de X, `_todos` (todos que
  assinam X) ou `_nova` (parte nova: **soma-se** às de X e pede signatário no documento — o
  congelamento cobra). Sem entrada: parte de mesma chave = a mesma; senão, nova; o genérico vai a todos.

O `StoreSignatureTemplateRequest::validateLinks` recusa: modelo inexistente ou inativo, o próprio
modelo, repetido, **ciclo** (Y que já exige X, direta ou indiretamente —
`LinkedTemplateComposer::reachableRoots`) e campo ligado a outro tipo. Correspondência de campo
que Y não tem é descartada.

**Composição em memória** (`LinkedTemplateComposer`): `SignatureTemplate::composed()` devolve um
`SignatureTemplate` **composto** (nunca gravado: `saving` devolve false quando `composite`) com o
texto de X + Y (chaves e marcadores de Y reescritos), campos, partes, anexos e itens de revisão
unidos, foto e visto se qualquer um pedir, a conferência de identidade mais forte (`none` < `partial`
< `full` < `email`) e o maior prazo de guarda. Y que exige Z leva Z junto; cada modelo entra uma vez.

**No documento:** a criação grava `signature_documents.linked_templates` (raiz ⇒ versão de cada
exigido, `LinkedTemplateComposer::versionMap`). **Toda regra do documento é lida de
`SignatureDocument::effectiveTemplate()`** — campos, partes, foto, visto, identidade, anexos,
revisão, texto. `template` direto só para o nome e a versão "do documento" (X). Revisar Y depois
não muda documento já criado. Y desativado trava documento **novo** de X
(`compositionProblems`, mostrado no formulário e recusado no request) e o aviso aparece ao desativar.
Y que entrou em documento só por vínculo conta como em uso (`SignatureTemplate::inUse`, busca a
raiz em `linked_templates`) e não é excluído. Manifesto, relatório gov.br e tela do documento dizem
"Inclui também: Y (versão n)". Termo de Menores recusa modelo com vínculo (`MinorTermFields`).
PDF pronto e contratos usam modelo `single_use`, sem vínculo.

### O que isso muda no congelamento

O congelamento do atendente continua fixando o texto (`body_snapshot`) e os dados dele, e esse
snapshot **nunca é regravado**. Ele guarda os marcadores dos campos de quem assina e dos
automáticos; os valores ficam em `signing_data`, e o `SignatureDocumentRenderer` os resolve na
hora de montar o documento (lacuna `________________` enquanto não há valor).

O que muda é o **PDF original e o `original_sha256`**: eles são refeitos quando a data
automática entra (na leitura do QR) e quando o formulário é respondido. A garantia central
continua de pé por outro caminho — o hash gravado é o do arquivo que a pessoa leu, e ela só lê
depois que as respostas entraram.

| Garantia | Como |
|---|---|
| Ninguém assina texto com lacuna | `SignatureCaptureService` recusa a assinatura enquanto o formulário não foi respondido |
| O hash é o do documento lido | o PDF é refeito **antes** da leitura; a URL do PDF muda com o hash |
| Toda troca de hash é rastreável | eventos `signing_date_set` e `form_answered`, com hash anterior e novo |
| A trilha não vaza resposta | o evento guarda as **chaves** respondidas, nunca os valores |
| Depois de assinado, não muda | `signingDataIsOpen()` fecha na primeira assinatura |
| Resposta não vira estrutura | valores entram escapados e sem colchetes — `[[assinatura]]` digitado é texto |
| A origem de cada dado fica dita | o manifesto lista o que veio do signatário e o que veio do sistema |

A **data da assinatura** é a do servidor no momento em que o tablet abre o documento. Documento
congelado ontem e aberto hoje sai com a data de hoje. Com vários signatários, vale a data do
primeiro: é o texto que ele leu e assinou.

### Partes que assinam (Contratante, Contratado)

Um termo de uma pessoa só usa `[[assinatura]]`, e todos os signatários assinam ali, um abaixo do
outro. Um contrato declara **partes**:

1. No Word, onde cada parte assina: `[[assinatura: Contratante]]` e `[[assinatura: Contratado]]`.
2. A importação reconhece as partes e a tela do modelo as lista em **Quem assina** — o nome ali é
   o que sai impresso sob o nome de quem assina.
3. Ao preparar o documento, cada signatário tem um **Assina como**. O documento novo já nasce com
   uma linha por parte.

Regras de posicionamento (`SignatureDocumentRenderer::placeSignatures`):

- Cada parte assina no marcador dela. Uma parte pode ter mais de um signatário (dois contratantes):
  saem empilhados no mesmo lugar.
- Marcadores **vizinhos** — em linhas seguidas, ou nas células de uma tabela do Word — saem **lado
  a lado**, dois por linha. A tabela do Word que só tem assinaturas é tratada como layout: não vira
  tabela com borda.
- Signatário **sem parte** (uma testemunha) vai para o `[[assinatura]]` genérico; sem ele, para o
  fim do documento.
- O congelamento é recusado enquanto alguma parte não tiver signatário.

Um QR por pessoa, em qualquer ordem. Quem assina
pelo clube assina no tablet, como qualquer signatário — não há assinatura pré-cadastrada neste
módulo. Quando quem assina pelo clube é um **diretor**, a parte pode ser ligada a uma área da
diretoria ("Assinada pela diretoria"): o documento já nasce com o titular da área escolhido, e o
diretor assina só no tablet, com matrícula e PIN (sem QR) — ver [Assinatura Diretoria](#assinatura-diretoria).

### Visto em todas as páginas

Com **Visto em todas as páginas** marcado no modelo:

- No tablet, depois de assinar, a mesma tela é limpa e pede o **visto** (a rubrica). Assinatura e
  visto seguem numa requisição só — o servidor grava os dois ou nenhum.
- O servidor exige o visto (mínimo de `SIGNATURE_MIN_INITIALS_POINTS` pontos, padrão 8), recorta a
  imagem ao desenho e guarda os traços vetoriais, como faz com a assinatura.
- O **PDF original** mostra, no pé de cada página, uma caixa em branco por signatário — a pessoa lê
  o documento vendo onde o visto dela vai ficar.
- O **PDF final** traz a rubrica de cada um em todas as páginas do documento. As páginas do
  **manifesto não levam visto**: são anexo gerado depois, e ninguém as rubricou. O manifesto
  registra que o visto foi desenhado no tablet e aplicado pelo sistema.

O visto é desenhado direto na página depois de o PDF estar montado (`page_script` do dompdf), e
não como bloco fixo do HTML — um bloco fixo se repetiria também no manifesto. Por isso o PDF
final de um modelo com visto é montado duas vezes: uma só para saber em que página o manifesto
começa.

**Pelo gov.br, o visto é dispensado no documento** (não no modelo): ao preparar, o original é refeito
sem a faixa e sem as caixas, e `SignatureDocument::requiresInitials()` passa a responder `false`. Toda
leitura do visto (geometria da página, caixas do PDF enviado pronto, cobrança na captura, regras do
tablet) passa por esse método — nunca direto por `template->requires_initials`.

### Papel timbrado (cabeçalho e rodapé)

Em **Assinaturas → Modelos → Cabeçalho e rodapé** (permissão `assinatura.modelos`):

| Ajuste | O que faz |
|---|---|
| Imagem do cabeçalho | Substitui o cabeçalho de texto padrão (nome do clube + título) |
| Imagem do rodapé | Fica na base da página, abaixo do código de validação |
| Altura (mm) | A imagem é reduzida para caber na altura e na largura do texto, sem deformar |
| Posição | Esquerda, centro ou direita |
| Faixa de borda a borda | A imagem ocupa a largura inteira do papel; a altura sai da proporção dela |
| Texto do rodapé | Uma linha (razão social, CNPJ, endereço) em todas as páginas |

O código de validação **continua no rodapé** de toda página — o papel timbrado não o substitui.
**Ver exemplo em PDF** monta uma página pela mesma view dos documentos de verdade.

O papel timbrado é **um só** para o módulo e é **versionado por linha**, como os modelos: salvar
cria a linha seguinte (`signature_layouts`), e as imagens nunca são sobrescritas no disco. O
documento guarda a linha vigente no congelamento (`signature_layout_id`); um documento congelado
antes de existir papel timbrado continua com o cabeçalho padrão. É o que faz o PDF final, montado
depois, sair com a mesma cara do original que a pessoa leu.

As medidas da página — margens, cabeçalho, rodapé, faixa do visto — moram em
`SignaturePageGeometry`, que o CSS do documento e o desenho do visto consultam.

## Guia do usuário

O passo a passo para quem escreve os modelos e para quem atende fica **dentro do sistema**, em
**Assinaturas → Guia** (`/assinatura/guia`), com botão **Baixar em PDF**. Abre para quem tem
qualquer uma das permissões `assinatura.documentos`, `assinatura.consultar` ou
`assinatura.modelos`.

A página e o PDF saem da mesma view (`resources/views/signature/guide/content.blade.php`): mudou
o texto, mudaram os dois, e não existe cópia em arquivo para ficar desatualizada. O endereço da
tela do tablet que o guia cita vem da rota. O CSS da view é o que o dompdf entende — tabelas e
blocos, sem flex nem grid.

## Configuração

Tudo em `config/signature.php`, com as variáveis documentadas no `.env.example`:

| Variável | Padrão | O que faz |
|---|---|---|
| `SIGNATURE_DISK` | `local` | Disco **privado** dos PDFs, traços e fotos |
| `SIGNATURE_QR_TTL_SECONDS` | `300` | Validade do QR na tela do atendente |
| `SIGNATURE_SESSION_TTL_MINUTES` | `15` | Sessão do tablet, a partir da leitura |
| `SIGNATURE_SESSION_WARNING_SECONDS` | `60` | Aviso antes de encerrar |
| `SIGNATURE_DOCUMENT_TTL_HOURS` | `24` | Documento congelado e esquecido |
| `SIGNATURE_ALLOWED_IPS` | vazio | Faixas (CIDR) que podem consumir um QR |
| `SIGNATURE_MIN_STROKE_POINTS` | `30` | Mínimo de pontos do traço |
| `SIGNATURE_MIN_INITIALS_POINTS` | `8` | Mínimo de pontos do visto (rubrica) |
| `SIGNATURE_RETENTION_MONTHS` | `60` | Guarda padrão (sem exclusão automática) |
| `SIGNATURE_DELIVERY_EMAIL` | `true` | Envio da via por e-mail |
| `SIGNATURE_ARCHIVE_ENABLED` | `false` | Cópia do PDF assinado no servidor de arquivos (FTP) |
| `SIGNATURE_ARCHIVE_ROOT` | `Lara/DocumentosAssinados` | Pasta-raiz do arquivo, criada se não existir |
| `SIGNATURE_FTP_SSL` | `false` | FTPS na conexão do arquivo |
| `SIGNATURE_DELIVERY_WHATSAPP` | `false` | Não implementado |
| `SIGNATURE_PADES_ENABLED` | `false` | Lacre dos PDFs finalizados com o e-CNPJ do clube (ver "Lacre do clube") |
| `SIGNATURE_PADES_CERTIFICATE` / `SIGNATURE_PADES_PASSWORD` | vazio | Caminho absoluto do `.pfx`/`.p12` e a senha |
| `SIGNATURE_PADES_REASON` | `Lacre do documento emitido pelo Lara` | `/Reason` do lacre (o código de validação é acrescentado) |
| `SIGNATURE_PADES_RESERVE_BYTES` | `32768` | Espaço reservado no PDF para o CMS do lacre |
| `SIGNATURE_TSA_URL` | vazio | ACT (RFC 3161) do carimbo de tempo; vazio = lacre sem carimbo |
| `SIGNATURE_TSA_USER` / `SIGNATURE_TSA_PASSWORD` / `SIGNATURE_TSA_POLICY` | vazio | Autenticação básica e OID de política, se a ACT pedir |
| `SIGNATURE_TSA_TIMEOUT_SECONDS` | `30` | Espera pela ACT |
| `SIGNATURE_PKI_NETWORK` | `true` | Conferência pode baixar LCR e AC intermediária |
| `SIGNATURE_PKI_REVOCATION` | `true` | Consultar a LCR |
| `SIGNATURE_PKI_REVOCATION_REQUIRED` | `false` | Lista indisponível reprova |
| `SIGNATURE_PKI_TIMEOUT_SECONDS` | `30` | Espera por LCR/AC |
| `SIGNATURE_PKI_ISSUER_CACHE_DAYS` | `30` | Por quanto tempo a AC baixada pelo AIA fica guardada |
| `SIGNATURE_GOVBR_MAX_UPLOAD_KB` | `20480` | Tamanho máximo do PDF assinado pelo gov.br enviado na aba |
| `SIGNATURE_GOVBR_TTL_DAYS` | `7` | Prazo do documento preparado para o gov.br, contado do "Preparar" |
| `SIGNATURE_ATTACHMENT_MAX_KB` | `10240` | Tamanho máximo de cada anexo do documento (PDF, JPG ou PNG) |
| `SIGNATURE_IDENTITY_CODE_TTL_MINUTES` | `10` | Validade do código da conferência de identidade por e-mail |
| `SIGNATURE_MINOR_DEVICE_TTL_HOURS` | `12` | Termo de Menores: horas que o tablet fica pareado |
| `SIGNATURE_MINOR_PAIRING_TTL_SECONDS` | `300` | Termo de Menores: validade do QR de pareamento |
| `SIGNATURE_MINOR_FLOW_TTL_MINUTES` | `10` | Termo de Menores: inatividade que encerra o atendimento |
| `SIGNATURE_MINOR_MAX_CPF_ATTEMPTS` | `5` | Termo de Menores: tentativas de CPF por título, por tablet, a cada 15 min |
| `SIGNATURE_DIRECTOR_TTL_DAYS` | `7` | Assinatura Diretoria: prazo do documento com diretor, contado do congelamento |
| `SIGNATURE_DIRECTOR_SESSION_MINUTES` | `10` | Assinatura Diretoria: inatividade que encerra a entrada do diretor no tablet |
| `SIGNATURE_DIRECTOR_MAX_LOGIN_ATTEMPTS` | `5` | Assinatura Diretoria: tentativas de matrícula + PIN por matrícula, a cada 15 min |
| `SIGNATURE_DIRECTOR_IDLE_SECONDS` | `90` | Assinatura Diretoria: segundos sem toque na lista que fazem o tablet encerrar a entrada do diretor |
| `SIGNATURE_REMOTE_SITE_URL` | vazio | Assinatura remota: endereço do site; o link é `{url}/assinar/{token}`. Vazio = cartão escondido |
| `SIGNATURE_REMOTE_API_TOKEN` | vazio | Assinatura remota: token próprio do site para `/api/assinatura/remota/*`. Vazio = API fechada |
| `SIGNATURE_REMOTE_ALLOWED_IPS` | vazio | Assinatura remota: IP (ou CIDR) do servidor do site; vazio = qualquer origem com o token |
| `SIGNATURE_REMOTE_TTL_HOURS` | `72` | Assinatura remota: validade do link (o prazo do documento é estendido até lá) |
| `SIGNATURE_REMOTE_SESSION_MINUTES` | `30` | Assinatura remota: sessão de cada abertura do link |

Nunca aponte `SIGNATURE_DISK` para `public` ou `placar`: os dois servem arquivo estático, sem
passar por autorização nenhuma.

O comando `signature:expire` roda **a cada minuto** (`routes/console.php`) e encerra QR não lido,
sessão parada e documento não assinado. O `signature:crl` roda **de hora em hora** (e no deploy) e
renova as listas de revogação. O `signature:seal-check` (manual) confere o certificado do lacre e a ACT.

## Colocando para funcionar

```bash
php artisan migrate                                   # 28 migrations do módulo
php artisan db:seed --class=SignatureTemplateSeeder   # opcional: 3 modelos iniciais
php artisan db:seed --class=MinorTermTemplateSeeder   # opcional: modelo do Termo de Menores (OKTOBERPET 2026)
php artisan queue:work                                # OBRIGATÓRIO — ver abaixo
```

**A fila não é detalhe.** `QUEUE_CONNECTION=database`: sem um worker rodando, o documento
assinado **para em "Assinado" e nunca vira "Finalizado"** — não existe PDF final, não existe
manifesto, não existe `final_sha256` e a via não é enviada. As evidências ficam todas gravadas,
então nada se perde; o que falta é o arquivo, e ele sai assim que o worker subir.

O seeder cria três modelos — termo de responsabilidade, ficha cadastral e contrato de locação de
espaço — e é **idempotente**: rodar de novo não duplica nem sobrescreve um modelo já revisado
pelo jurídico. Ele **não** está registrado no `DatabaseSeeder` de propósito: são textos com
efeito jurídico, e um `db:seed` de rotina não deve criar documento que ninguém aprovou. Se
preferir escrever os seus do zero, pule o seeder e use **Assinaturas → Modelos → Novo Modelo**.

As quatro permissões nascem só no papel `admin` (a migration `120600` as concede). Quem for
testar com outro usuário precisa recebê-las em **Usuários → Papéis e Permissões**.

O `signature:expire` depende do scheduler do Laravel já configurado no servidor — em
desenvolvimento, `php artisan schedule:work`.

## Instalação do tablet

**Com HTTPS o módulo funciona inteiro.** `getUserMedia` — que abre a câmera para o leitor de QR e
para a foto — só existe em origem segura. Origem considerada segura também vale (`localhost`),
mas não é montagem de balcão. **Sem HTTPS, veja o modo degradado logo abaixo.**

Configuração recomendada, com **Fully Kiosk Browser** (Android):

| Ajuste | Valor |
|---|---|
| Start URL | `https://<host>/assinatura/kiosk` |
| Kiosk Mode | ligado (trava o aparelho no navegador) |
| Keep Screen On | ligado |
| Camera permission | concedida ao app |
| Screensaver / Daydream | desligados |
| Auto-reload on idle | desligado — recarregar no meio do atendimento faz a tela retomar do começo do documento |

A tela funciona em retrato e paisagem, com alvos de toque de 72px.

## Modo sem HTTPS (degradado)

Enquanto não houver certificado, duas flags tornam o módulo operável. **As duas nascem
desligadas**, e ligá-las é abrir mão de alguma coisa — por isso a decisão é explícita:

```dotenv
SIGNATURE_MANUAL_CODE_ENABLED=true
SIGNATURE_SKIP_PHOTO_WITHOUT_CAMERA=true
```

### Código digitado no lugar do QR

Com a primeira flag, cada liberação também gera um **código de 8 caracteres** (alfabeto sem
`0/O` e `1/I/L`, porque ele é ditado em voz alta). A tela do atendente o mostra abaixo do QR; o
tablet ganha o botão **Digitar código**, com teclado próprio.

É a **mesma liberação**, com as mesmas travas: uso único, vínculo com um único documento, faixa
de IP, mesmo rate limiting. O que muda é como o segredo chega ao aparelho — e é aí que está a
perda: um código ditado passa por uma pessoa, e uma pessoa pode repeti-lo a quem não devia. Por
isso ele **vence antes do QR** (150s contra 300s, configurável): o QR da mesma liberação
continua valendo depois que o código morre.

A trilha de auditoria registra por onde o tablet entrou (`via: codigo_digitado`) — é o que,
meses depois, explica um atendimento sem foto.

### Assinatura sem foto

Com a segunda flag, um modelo que exige foto ainda assim conclui quando o aparelho **não tem
câmera**. A ausência não é silenciada:

- fica gravada na evidência (`photo_skipped_reason = camera_unavailable`);
- entra na trilha de auditoria;
- é **impressa no manifesto**: "Foto não capturada: câmera indisponível no dispositivo (conexão
  sem HTTPS)".

O motivo é uma lista fechada de um item só. Qualquer outro valor é recusado — se fosse texto
livre, bastaria o cliente mandar uma string qualquer para transformar a exigência de foto em
sugestão. E, com a flag desligada, nem o motivo certo passa: quem decide é o servidor.

### O que você perde

| Com HTTPS | Sem HTTPS |
|---|---|
| Segredo vai do computador ao tablet sem passar por ninguém | Segredo é falado em voz alta no balcão |
| Foto do signatário como evidência | Sem foto, com a ausência declarada no documento |
| Nenhuma configuração extra | Duas flags ligadas, e um manifesto que diz que faltou evidência |

É um modo de operação possível, não um modo equivalente. Vale enquanto o certificado não sai —
e, quando sair, basta desligar as duas flags: nada mais muda.

## Modelos de exemplo (teste manual)

```
php artisan db:seed --class=SignatureExampleTemplatesSeeder
```

Cria sete modelos com o nome começando por **[Exemplo]**, um para cada combinação de recursos:

| Modelo | O que exercita |
|---|---|
| 1. Termo simples | Texto fixo, sem campos: o fluxo básico do tablet (4 dígitos do CPF, foto) |
| 2. Reserva de espaço | Campos do atendente obrigatórios e opcionais de vários tipos; perguntas no tablet obrigatórias (opção única, telefone, sim/não) e opcionais (e-mail, múltipla escolha, texto longo); data da assinatura automática |
| 3. Cadastro de dependente | Anexos: dois obrigatórios e um opcional; CPF completo |
| 4. Contrato com partes e visto | Contratante e Contratado lado a lado, visto em todas as páginas (texto longo, mais de uma folha) |
| 5. Autorização com código por e-mail | Conferência de identidade por código no e-mail (o signatário precisa ter e-mail), sem foto, anexo opcional |
| 6. Termo para o gov.br | Sem perguntas e sem visto (aceito pelo gov.br), data automática, anexo obrigatório |
| 7. Pesquisa de satisfação | Só perguntas opcionais, sem foto |

Idempotente (modelo com o mesmo nome não é recriado), **recusado em produção**, e fora do
`DatabaseSeeder` e dos scripts de deploy. Para tirar da lista depois, desative os "[Exemplo]" na tela
de Modelos. `SignatureExampleTemplatesSeederTest` confere que cada um monta um documento sem marcador
sobrando.

## Testes

```
php -d memory_limit=1G vendor/bin/phpunit --filter Signature
```

> Pelo `phpunit` direto: o `artisan test` roda a suíte em outro processo, que não herda o
> `-d memory_limit`, e o módulo inteiro estoura os 128 MB padrão.

A suíte do módulo cobre, entre outras coisas: consumo do token (sucesso, expirado, releitura
bloqueada, regenerado, cancelado), sessão tentando alcançar outro documento (403), transições
inválidas na máquina de estados, assinatura vazia, imutabilidade do hash depois do congelamento,
trilha sem update/delete e com cadeia verificável, job de finalização com manifesto e
`final_sha256`, e a página de validação com hash certo e errado.

A conferência do gov.br (`SignatureGovbrCheckTest`, `GovbrAsn1Test`) assina PDFs com uma **AC de
teste** gerada na hora (`Tests\Concerns\BuildsGovbrSignedPdf`), no mesmo formato da amostra real e
com o CPF no mesmo otherName. Cobre: válido, sem assinatura, CPF de quem não é signatário, outro
documento, conteúdo alterado, bytes acrescentados, outra AC, dois signatários em sequência,
assinatura sobre o PDF final do tablet, assinatura anterior ao congelamento, revogação como "não
conferido" (sem reprovar), rascunho, arquivo que não é PDF e as permissões. Fica de fora o
certificado **vencido na hora da assinatura**: a extensão OpenSSL do PHP só emite certificado válido
a partir de agora, e a AC de teste não consegue produzir um no passado.
`SignatureGovbrRevocationTest` cobre a rede (sempre com `Http::fake` e `preventStrayRequests`): LCR com
o certificado (reprova), sem ele (passa), fora do ar (não conferido; reprova se exigido), assinada por
outra AC, vencida, guardada sem novo download, o `signature:crl`; certificado ICP-Brasil como
qualificada (e a aba), AC intermediária baixada pelo AIA, sem rede, embutida na assinatura, e a hora
pelo `/M` quando não há `signingTime`. `SignaturePdfSealTest` cobre o lacre: PDF do tablet lacrado
(CMS confere, cobre até o fim, hash, evento), lacre desligado, lacre sobre lacre, PDF do gov.br lacrado
mantendo a assinatura da pessoa (e o relatório lacrado, e o `/validar` aceitando o arquivo sem lacre),
carimbo de tempo de uma ACT de teste (atributo não assinado, sem alterar a assinatura), carimbo de outro
resumo, ACT fora do ar, senha errada, xref stream, o `signature:seal-check` e o aviso no manifesto.
`SignatureGovbrSigningTest` cobre a conclusão: preparar (prazo, QR cancelado, tablet bloqueado),
modelo com visto (dispensa no documento, original e hash novos, PDF antigo recusado), o bloqueio da
assinatura no tablet, um signatário até o PDF final e a via, dois em
sequência, o segundo assinando o original, ordem livre (o 2º da lista assinando antes), reenvio do mesmo arquivo, sem preparar,
prazo vencido e as telas. `SignatureGovbrInviteTest` cobre o convite: anexo e `Reply-To` do atendente, e-mail
sem link para o Lara, sem preparar, qualquer pendente em qualquer ordem, falha de SMTP sem registro, reenvio e o segundo
recebendo o arquivo do primeiro.
`SignatureGovbrFinalizationTest` cobre a finalização: final igual ao arquivo do gov.br, relatório à
parte com hash na trilha, conteúdo do relatório (hashes, CPF só mascarado, validador oficial), via com
os dois anexos, servidor de arquivos com o relatório ao lado e o botão da tela. `SignatureReleaseQrThemeTest` trava o fundo branco
do QR do atendente. `SignatureSigningOrderTest` e `SignatureQrTokenTest` cobrem a liberação no tablet
fora da ordem e a escolha de quem assina no painel. `SignatureAttachmentTest` cobre os anexos: itens no modelo (e na revisão) e no
documento, as telas, obrigatório que não trava o congelamento e segura a conclusão até o envio que
completa a lista, hash e trilha sem o nome do arquivo, tipo
pelo conteúdo, item inexistente e avulso sem nome, remover só em rascunho, documento encerrado,
arquivo preso ao documento, permissão, manifesto com o hash e o servidor de arquivos.
`SignatureIdentityEmailCodeTest` cobre o código por e-mail: a opção no modelo, envio e confirmação
(sem o endereço na tela, sem o código no banco nem na trilha), CPF no lugar do código, código errado
gastando tentativa, código vencido, espera e teto de reenvio, código substituído, falha de SMTP, modelo
de CPF que não envia, signatário sem e-mail travando o congelamento e o manifesto.
`SignatureMinorTermKioskTest` cobre o Termo de Menores no tablet: pareamento (uso único, prazo do QR,
12 h, revogação), tablet sem pareamento, só adultos como responsável, menor como responsável recusado,
CPF errado contando por título, MultiClubes fora do ar, sem termo vigente, menores por sobrenome,
dados que faltam (digitados, inválidos e pulados), fluxo completo até assinado com a via, "já
autorizado", documento abandonado cancelado, "Concluir", histórico com a foto e a revisão de fora.
`SignatureMinorTermPanelTest` cobre as telas do computador (permissões, cadastro, vigência que cruza,
modelo que não serve, desativar, QR de pareamento) e `Unit\MinorTermRulesTest`, sobrenome e maioridade.
`SignatureRemoteLinkTest` cobre a assinatura remota: envio (só o hash no banco, e-mail corrigido,
prazo do documento estendido, trilha sem o token), site não configurado, SMTP fora do ar, token
próprio e origem da API, fluxo completo (CPF completo mesmo em modelo de quatro dígitos, foto
obrigatória mesmo em modelo sem foto, IP de quem assina na evidência, link usado), tentativas
somadas entre aberturas até o bloqueio, link substituído, vencido e inválido, revogação derrubando
a sessão, sessão remota que não abre o tablet, recusa, gov.br revogando o link, diretor fora, o
manifesto e o cartão do painel; e os anexos pelo link (obrigatório travando a assinatura até o envio,
trilha com o IP de quem assina, troca do próprio anexo sem poder apagar o do atendente, CPF antes,
item não pedido e tipo pelo conteúdo).

`SignatureLinkedTemplateTest` cobre o campo restrito (gravação, tela sem a caixa, marca forçada
recusada, documento que não pergunta) e o modelo vinculado (composição de texto, campos ligados por
chave diferente, sufixo do campo próprio, regras somadas, composto que não grava, parte ligada e parte
nova cobrada no congelamento, genérico de Y para todos, versões presas ao documento, assinatura nas
duas partes, exigido desativado, cadastro com correspondência, tipo diferente, a si mesmo, ciclo, em
uso por vínculo, telas e Termo de Menores). `SignatureAnswerCheckTest` cobre a conferência (rascunho no
painel sem tocar o documento, assinatura travada até aprovar, devolver com recado e reenvio, correção
que refaz o PDF e aprova, aprovação que não vale para resposta mudada, aprovar antes da resposta,
documento sem pergunta, remoto e diretoria de fora, segundo signatário sem conferência e a tela).

> Rode a suíte **sem** `SignatureContractAiReviewTest` e ele à parte: o `ContractAiReviewer` chama
> `set_time_limit(120)` e, depois dele, o processo inteiro do phpunit morre 120 s adiante
> ("Maximum execution time of 120 seconds exceeded"):
> `php -d memory_limit=1G vendor/bin/phpunit --filter '/^(?!.*SignatureContractAiReviewTest).*Signature/'`.

> As tabelas são criadas pelo trait `Tests\Concerns\CreatesSignatureSchema`, que aplica as
> migrations **de verdade** — a cadeia completa de migrations não roda na suíte, e nenhuma tabela
> deste módulo declara foreign key para `users`, cuja model fixa a conexão `mysql`.

## Referência técnica

| Camada | Arquivos |
|---|---|
| Models | `SignatureTemplate`, `SignatureDocument`, `SignatureSigner`, `SignatureRequest`, `SignatureEvidence`, `SignatureAuditEvent`, `SignatureGovbrCheck`, `SignatureGovbrInvite`, `SignatureAttachment`, `SignatureMinorTerm`, `SignatureMinorAuthorization`, `SignatureKioskDevice` |
| Services | `app/Services/Signature/` — `SignatureStateMachine`, `SignatureAuditor`, `SignatureDocumentService`, `SignatureDocumentRenderer`, `SignatureRequestService`, `SignatureCaptureService`, `SignatureQrCode`, `SignaturePdfSealer`, `SignatureAttachmentService`; `app/Services/Signature/Govbr/` — `GovbrSignatureValidator`, `GovbrValidationResult`, `GovbrCheckService`, `Asn1`; `app/Services/Signature/Pki/` — `Certificates`, `CertificateRevocationList`, `PkiRepository`, `RevocationChecker`, `TimestampClient`, `PdfSignatureWriter`, `Der`; `app/Services/Signature/MinorTerms/` — Termo de Menores |
| Controllers | `app/Http/Controllers/Signature/` — `TemplateController`, `DocumentController`, `ReleaseController`, `QuiosqueController`, `ValidationController`, `GovbrController`, `AttachmentController`, `MinorTermController`, `MinorTermKioskController`; trait `Concerns\RespondsWithKioskSession` |
| Middleware | `EnsureSignatureKioskSession` (alias `signature_kiosk`), `EnsureMinorTermDevice` (alias `signature_minor_device`) |
| Jobs | `FinalizeSignatureDocument`, `SendSignatureCopy` |
| Comando | `signature:expire`, `signature:archive`, `signature:crl`, `signature:seal-check` |
| Telas | `resources/views/signature/` (painel, guia e PDF), `resources/views/quiosque/index.blade.php` (tablet, servido em `/assinatura/kiosk`) |
| Policy | `SignatureDocumentPolicy` |

### Máquina de estados

```
documento:   draft → awaiting_signature → signed → finalized
                  ↘ canceled     ↘ refused     ↘ expired
solicitação: pending → consumed → completed
                    ↘ expired ↘ canceled ↘ superseded
signatário:  pending → signed | refused | canceled | expired
```

Toda transição passa por `SignatureStateMachine`, que valida o caminho e grava o evento de
auditoria **na mesma transação**. Nenhum outro ponto do módulo faz `update(['status' => ...])`.

### Bibliotecas

- `bacon/bacon-qr-code` (composer) — gera o QR do manifesto **no servidor**, em PHP puro, sem
  requisição nenhuma;
- `html5-qrcode`, `pdf.js` e `qrcodejs` por **CDN**, como o `webcam.js` e o `chart.js` que o
  projeto já carrega assim. O tablet e o computador do atendente precisam alcançar o CDN.
- **Nenhuma biblioteca de PKI ou PDF assinado:** LCR, carimbo de tempo e assinatura incremental são
  código do módulo (`Pki\`) sobre a extensão OpenSSL do PHP e o `Govbr\Asn1`. As opções em PHP eram
  comerciais (SetaPDF) ou só assinam o PDF que elas mesmas geram (TCPDF).
