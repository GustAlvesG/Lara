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

> **Não confundir com o `/kiosk` dos freelancers.** São dois tablets e dois assuntos. Lá o
> **operador** entra com matrícula e PIN e atende vários contratos seguidos; aqui o tablet não
> entra em lugar nenhum — ele lê um QR que libera **um** documento para **uma** assinatura e
> volta à tela de espera.

## Para quem

| Perfil | O que faz | Permissão |
|---|---|---|
| Gerência / jurídico | Escreve e revisa os modelos de documento | `manage signature templates` |
| Atendimento | Cria documentos, congela e libera para o tablet | `manage signature documents` |
| Consulta | Abre documentos assinados e baixa o PDF | `view signed documents` |
| Auditoria | Vê a foto e o traço do signatário | `view signature evidences` |

São quatro e não uma porque são quatro acessos de peso diferente: escrever o texto de um termo é
ato jurídico, atender no balcão é operação, e ver a foto de uma pessoa não é nem uma coisa nem
outra. Todas nascem só no papel `admin` (migration `2026_09_22_120600`); quem mais precisar
recebe pela tela de permissões.

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

1. **Escolher o modelo** → buscar o associado por nome, título ou CPF (ou digitar um visitante)
   → preencher as variáveis do modelo.
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

Tela de espera → leitura do QR → documento no PDF.js (botão travado até a rolagem chegar ao fim)
→ conferência de identidade → aceite explícito → assinatura no canvas → foto → conclusão.

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

Cada modelo define ainda: **conferência de identidade** (4 dígitos / CPF completo / nenhuma),
**foto obrigatória** e **prazo de guarda**.

> A exclusão por retenção **não está implementada**. O campo registra a decisão para que ela
> exista antes de haver o que apagar.

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
| `SIGNATURE_RETENTION_MONTHS` | `60` | Guarda padrão (sem exclusão automática) |
| `SIGNATURE_DELIVERY_EMAIL` | `true` | Envio da via por e-mail |
| `SIGNATURE_DELIVERY_WHATSAPP` | `false` | Não implementado |
| `SIGNATURE_PADES_ENABLED` | `false` | Lacre A1/PAdES — **não implementado**; ligar falha alto |

Nunca aponte `SIGNATURE_DISK` para `public` ou `placar`: os dois servem arquivo estático, sem
passar por autorização nenhuma.

O comando `signature:expire` roda **a cada minuto** (`routes/console.php`) e encerra QR não lido,
sessão parada e documento não assinado.

## Colocando para funcionar

```bash
php artisan migrate                                   # 10 migrations do módulo
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

**HTTPS é obrigatório.** `getUserMedia` — que é o que abre a câmera para o leitor de QR e para a
foto — só funciona em origem segura. Em HTTP comum, o tablet abre a tela e não consegue ler nada.
Origem considerada segura também vale (`localhost`), mas não é montagem de balcão.

Configuração recomendada, com **Fully Kiosk Browser** (Android):

| Ajuste | Valor |
|---|---|
| Start URL | `https://<host>/quiosque` |
| Kiosk Mode | ligado (trava o aparelho no navegador) |
| Keep Screen On | ligado |
| Camera permission | concedida ao app |
| Screensaver / Daydream | desligados |
| Auto-reload on idle | desligado — recarregar no meio do atendimento faz a tela retomar do começo do documento |

A tela funciona em retrato e paisagem, com alvos de toque de 72px.

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
| Telas | `resources/views/signature/` (painel e PDF), `resources/views/quiosque/index.blade.php` (tablet) |
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
