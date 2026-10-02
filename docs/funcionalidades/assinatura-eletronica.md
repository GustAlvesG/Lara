# Assinatura Eletrônica Presencial (tablet do balcão)

## O que é

Termos de responsabilidade, fichas cadastrais e contratos de locação de espaço passam a ser
assinados num **tablet no balcão de atendimento**, em vez de impressos. O atendente prepara o
documento no computador, mostra um **QR Code** na tela, o associado lê esse código com o tablet,
lê o documento, confirma quem é, aceita os termos e assina com o dedo.

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

   A tela é dividida em **passos**, um cartão por vez: **Documento** (título e local) →
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
   lista (a ordem de atendimento) e da parte escolhida em **Assina como**, que é o primeiro campo
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
3. **Liberar** para o tablet e acompanhar: aguardando leitura → tablet conectado → documento
   visualizado → identidade confirmada → assinado.
4. **Regerar** o QR ou **cancelar** a qualquer momento.
5. Com vários signatários, um QR por pessoa, **em sequência** — a testemunha não assina antes de
   quem ela testemunha.

## Fluxo do tablet

Tela de espera → leitura do QR → formulário, se o modelo pergunta algo a quem assina (ver
"Perguntas a quem assina") → documento no PDF.js (botão travado até a rolagem chegar ao fim)
→ conferência de identidade → aceite explícito → assinatura no canvas → visto, se o modelo exige
→ foto → conclusão.

- **Nada fica no aparelho**: sem `localStorage`, sem `IndexedDB`. O estado vive em memória e é
  zerado ao fim de cada atendimento — o tablet é do balcão e é compartilhado.
- **Todo horário é do servidor.** Um tablet de balcão passa meses sem sincronizar o relógio, e a
  hora da assinatura é justamente o que precisa ser confiável.
- **Recusar** está disponível em qualquer etapa, com motivo opcional.
- **Inatividade**: aviso 60 segundos antes de encerrar.
- **Erro de rede** nunca deixa o atendimento "meio assinado": a gravação é uma transação só.

### O que o servidor decide, e o tablet não

| Regra | Onde |
|---|---|
| CPF confere? | `Cpf::matches()`, no servidor. O CPF cadastrado **nunca** vai para a tela, nem na mensagem de erro |
| Teto de tentativas | 5 por solicitação; estourou, a sessão morre (quatro dígitos não resistem a chutes ilimitados) |
| O traço é uma assinatura? | Mínimo de 30 pontos vetoriais — um toque na tela não é assinatura |
| O arquivo é imagem? | Conferido pelos **bytes**, não pelo cabeçalho do data URL |
| Pode assinar? | Estado do documento e do signatário, reconferidos a cada requisição |

## Evidências e auditoria

Cada assinatura grava, na **mesma transação**: PNG do traço, traços vetoriais (pontos com tempo
relativo), foto, IP, user agent, tempo de leitura, se a tela informou rolagem até o fim, o aceite
e a hora do servidor.

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

1. o model recusa `update` e `delete` — com exceção, não com `return false` silencioso;
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
data e hora do servidor, atendente e local, IP e dispositivo, tempo de leitura, miniatura da
foto, o `original_sha256`, a trilha de eventos e o **QR de validação**.

> **O PDF final é re-renderizado, não carimbado.** O dompdf não edita PDF pronto. As duas versões
> saem do mesmo `body_snapshot`, então o texto é o mesmo; o que prova qual arquivo a pessoa leu é
> o `original_sha256`, calculado antes de qualquer assinatura e impresso no manifesto. O ponto de
> troca por um carimbo cirúrgico (FPDI) é o `SignaturePdfSealer`.

O job é idempotente (reentrega da fila não gera um segundo arquivo) e falha sem perder evidência:
assinatura, traço, foto e trilha já estão gravados — falta só o arquivo, que pode ser refeito.

## Página pública de validação

`/validar/{codigo}` — sem login, porque quem chega veio do QR impresso. Mostra título, data,
situação e signatários com **CPF mascarado**, e aceita o **upload de um PDF** para conferir o
hash. Não mostra foto, traço, contato, nem entrega o documento: quem tem o código tem o papel.

Código inexistente e rascunho respondem a mesma coisa — "não encontrado" —, para a página não
confirmar a existência de documentos alheios. O arquivo enviado é lido do diretório temporário,
hasheado e descartado.

## Envio da via

A pessoa marca **"quero receber uma via"** na tela de aceite, e o pedido chega junto com a
assinatura — na mesma requisição e na mesma transação. Foi assim, e não numa pergunta depois,
porque a sessão do tablet morre no instante em que a assinatura entra.

O e-mail leva o PDF final e o código de validação. O atendente pode reenviar pelo painel.

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

Revisar um modelo é o mesmo gesto: corrige-se o `.docx`, envia-se de novo em **Revisar** e
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

Um `[[campo]]` que esteja no texto e não na lista de campos é declarado sozinho na gravação
(obrigatório). Sem isso ele não viraria pergunta para o atendente e sairia impresso, com
colchetes, no documento que a pessoa assina. O HTML continua editável em **Editar o texto em HTML
(avançado)**.

Cada modelo define ainda: **conferência de identidade** (4 dígitos / CPF completo / nenhuma),
**foto obrigatória** e **prazo de guarda**.

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

Um campo marcado como **Perguntar a quem assina, no tablet** sai do formulário do atendente e
vira uma pergunta — escrita no modelo — que a pessoa responde no tablet. Em branco, a pergunta é
o nome do campo.

O fluxo do tablet ganha uma etapa, **antes** da leitura:

1. Leitura do QR.
2. **Formulário**: as perguntas, uma abaixo da outra, com o controle de cada tipo.
3. O servidor confere as respostas, refaz o PDF com elas e grava o hash novo.
4. Leitura do documento — já com as respostas — e daí em diante como sempre.

Na tela do documento há o botão **Corrigir respostas**: a pessoa viu um erro no que digitou,
volta, corrige, e o documento é refeito. Vale **até a primeira assinatura**; dali em diante o
texto não muda mais, nem para o signatário seguinte (que não vê formulário).

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

A ordem de assinatura continua sendo a da lista de signatários, um QR por pessoa. Quem assina
pelo clube assina no tablet, como qualquer signatário — não há assinatura pré-cadastrada neste
módulo.

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
| `SIGNATURE_LOCATION` | balcão | Local impresso no manifesto |
| `SIGNATURE_MIN_STROKE_POINTS` | `30` | Mínimo de pontos do traço |
| `SIGNATURE_MIN_INITIALS_POINTS` | `8` | Mínimo de pontos do visto (rubrica) |
| `SIGNATURE_RETENTION_MONTHS` | `60` | Guarda padrão (sem exclusão automática) |
| `SIGNATURE_DELIVERY_EMAIL` | `true` | Envio da via por e-mail |
| `SIGNATURE_ARCHIVE_ENABLED` | `false` | Cópia do PDF assinado no servidor de arquivos (FTP) |
| `SIGNATURE_ARCHIVE_ROOT` | `Lara/DocumentosAssinados` | Pasta-raiz do arquivo, criada se não existir |
| `SIGNATURE_FTP_SSL` | `false` | FTPS na conexão do arquivo |
| `SIGNATURE_DELIVERY_WHATSAPP` | `false` | Não implementado |
| `SIGNATURE_PADES_ENABLED` | `false` | Lacre A1/PAdES — **não implementado**; ligar falha alto |

Nunca aponte `SIGNATURE_DISK` para `public` ou `placar`: os dois servem arquivo estático, sem
passar por autorização nenhuma.

O comando `signature:expire` roda **a cada minuto** (`routes/console.php`) e encerra QR não lido,
sessão parada e documento não assinado.

## Colocando para funcionar

```bash
php artisan migrate                                   # 16 migrations do módulo
php artisan db:seed --class=SignatureTemplateSeeder   # opcional: 3 modelos iniciais
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

## Testes

```
php -d memory_limit=1G artisan test --filter Signature
```

A suíte do módulo cobre, entre outras coisas: consumo do token (sucesso, expirado, releitura
bloqueada, regenerado, cancelado), sessão tentando alcançar outro documento (403), transições
inválidas na máquina de estados, assinatura vazia, imutabilidade do hash depois do congelamento,
trilha sem update/delete e com cadeia verificável, job de finalização com manifesto e
`final_sha256`, e a página de validação com hash certo e errado.

> As tabelas são criadas pelo trait `Tests\Concerns\CreatesSignatureSchema`, que aplica as
> migrations **de verdade** — a cadeia completa de migrations não roda na suíte, e nenhuma tabela
> deste módulo declara foreign key para `users`, cuja model fixa a conexão `mysql`.

## Referência técnica

| Camada | Arquivos |
|---|---|
| Models | `SignatureTemplate`, `SignatureDocument`, `SignatureSigner`, `SignatureRequest`, `SignatureEvidence`, `SignatureAuditEvent` |
| Services | `app/Services/Signature/` — `SignatureStateMachine`, `SignatureAuditor`, `SignatureDocumentService`, `SignatureDocumentRenderer`, `SignatureRequestService`, `SignatureCaptureService`, `SignatureQrCode`, `SignaturePdfSealer` |
| Controllers | `app/Http/Controllers/Signature/` — `TemplateController`, `DocumentController`, `ReleaseController`, `QuiosqueController`, `ValidationController` |
| Middleware | `EnsureSignatureKioskSession` (alias `signature_kiosk`) |
| Jobs | `FinalizeSignatureDocument`, `SendSignatureCopy` |
| Comando | `signature:expire` |
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
