# Termo de Menores (autoatendimento nos eventos)

## O que é

A autorização de entrada de **menor de idade** nos eventos do Clube, assinada pelo **responsável**
num **tablet de autoatendimento** — sem atendente e sem papel. Exclusivo para **sócios**: responsável
e menor são pessoas do **mesmo título** no MultiClubes.

Fica dentro do módulo de Assinaturas (menu **Assinaturas → Termo de Menores**). Cada termo assinado é
um documento comum do módulo — congelado, com hash, manifesto, trilha de auditoria, validação pública
(`/validar/{codigo}`), via por e-mail e cópia no servidor de arquivos (FTP). Ver
[Assinatura eletrônica](assinatura-eletronica.md).

## Para quem

| Quem | O que faz | Permissão |
|---|---|---|
| Organização do evento | Cadastra o termo do evento (modelo + vigência) | `assinatura.termo-menores.gerenciar` |
| Quem monta o tablet | Pareia o tablet (QR na tela do computador) | `assinatura.termo-menores.parear` |
| Quem põe a pulseira | Consulta o histórico quando a tela verde já saiu | `assinatura.termo-menores.historico` |
| Sócio | Assina no tablet | — (rota pública, só no tablet pareado) |

As permissões nascem **sem setor** (o `PermissionCatalogSeeder` do deploy as cria); configure em
**Setores**. O item do menu aparece para quem tem qualquer uma das três (Gate `acessar-termo-menores`).

## Fluxo no tablet

```
título → responsável → CPF completo → [e-mail/RG que faltam] → menor → [CPF/RG que faltam]
       → leitura do termo → aceite + autorização de imagem → traço → foto → TELA VERDE
```

1. **Título** — o sócio digita o código do título (o mesmo do cadastro no app).
2. **Responsável** — a tela lista as pessoas do título com **18 anos ou mais** na data. Toca no nome.
3. **CPF** — o CPF **completo** do responsável, conferido no servidor. 5 tentativas por título neste
   tablet a cada 15 minutos (recomeçar pelo título não zera); esgotou, o atendimento acaba.
4. **Dados que faltam do responsável** — se o MultiClubes não tem **e-mail** ou **RG**, o tablet pede.
   Dá para **pular**: o termo sai com "não informado" e, sem e-mail, sem via.
5. **Menor** — a tela lista as pessoas do título com **menos de 18 anos** que tenham **ao menos um
   sobrenome em comum** com o responsável (sem acento, minúsculas, ignorando o primeiro nome e as
   partículas "de, da, do, das, dos, e"). Quem já tem termo assinado neste evento aparece como
   **"Já autorizado ✓"** e não assina de novo — tocar nele só diz que está tudo certo.
6. **Dados que faltam do menor** — **CPF** ou **RG** vazios no MultiClubes: o tablet pede, dá para pular.
7. **Documento** — o servidor gera o documento com os dados do MultiClubes (e o que foi digitado),
   congela, libera e abre a sessão do tablet. **Um documento por menor.**
8. **Leitura, aceite, traço e foto** — as telas do quiosque de sempre. A identidade não é pedida de
   novo (o CPF completo já foi conferido no passo 3). A **foto é do responsável**. A via vai por e-mail
   **automaticamente** quando há e-mail.
9. **Tela verde** — "**Apresente ao Representante do Clube**", com nome e idade do menor, nome e foto do
   responsável, evento e hora. Só sai no toque:
   - **Autorizar outro menor** — volta à lista com o mesmo responsável, sem pedir o CPF de novo;
   - **Concluir** — volta ao início e esquece o atendimento.

Inatividade de **2 minutos** nas telas de antes do documento devolve o tablet ao início (o próximo
sócio não vê os nomes do anterior). Na tela verde, não — ela fica até alguém tocar.

## O termo do evento

**Assinaturas → Termo de Menores → Termos dos eventos.** Cada evento tem um termo: **nome** (aparece
no tablet, na tela verde e no histórico), **modelo** e **vigência** (do primeiro ao último dia,
inclusive — o período em que o tablet aceita termos novos).

- **Um vigente por vez**: o cadastro recusa período que cruza o de outro termo ativo. Por isso o tablet
  nunca pergunta "qual evento".
- **Desativar** tira o termo do tablet antes do fim da vigência.
- O termo aponta para a **raiz** do modelo: revisar o modelo (Assinaturas → Modelos) durante o evento
  vale para os termos **seguintes**; os já assinados não mudam (são documentos congelados).
- Sem termo vigente, o tablet mostra "Não há termo de menores disponível hoje".

### O modelo

O texto do evento (nome, local, datas, portaria) fica escrito no **modelo**. As variáveis que o
autoatendimento preenche (todas do tipo **Texto**):

| Variável | Origem |
|---|---|
| `[[nome_responsavel]]` | `dbo.Members.Name` |
| `[[e_mail_responsavel]]` (ou `[[email_responsavel]]`) | `dbo.Members.Email`, ou o digitado no tablet |
| `[[cpf_responsavel]]` | `dbo.Members.DocumentUnmasked` (formatado) |
| `[[rg_responsavel]]` | `dbo.Members.Rg`, ou o digitado |
| `[[endereco_responsavel]]` | endereço do **título** (`dbo.Titles`: rua, número, complemento, bairro, cidade/UF, CEP) |
| `[[nome_menor]]` | `dbo.Members.Name` |
| `[[idade_menor]]` | calculada de `dbo.Members.BirthDate` na data |
| `[[cpf_menor]]` | `dbo.Members.DocumentUnmasked`, ou o digitado |
| `[[rg_menor]]` | `dbo.Members.Rg`, ou o digitado |

As chaves são as que o importador de Word gera a partir dos rótulos do termo em papel
(`[[NOME RESPONSAVEL]]` → `nome_responsavel`). Dado ausente e pulado sai como **"não informado"** — por
isso os campos precisam ser **Texto** (um tipo CPF ou e-mail apagaria esse texto). A data vai num campo
do tipo **"Data da assinatura"** (por extenso: "8 de outubro de 2026").

O cadastro do termo **recusa** modelo que: não tem `[[nome_responsavel]]` e `[[nome_menor]]`; tem um
desses campos com tipo diferente de Texto; tem campo **obrigatório** que ninguém preenche (não há
atendente); não exige foto; confere identidade por código no e-mail; exige visto; declara partes. A
tela mostra o motivo de cada modelo bloqueado (`MinorTermFields::templateProblems`).

**Modelo pronto:** o do OKTOBERPET 2026, com o texto do termo em papel:

```bash
php artisan db:seed --class=MinorTermTemplateSeeder
```

Idempotente (mesmo nome é mantido) e **fora do deploy**, como o `SignatureTemplateSeeder`. Para o
próximo evento, revise o texto em Assinaturas → Modelos ou crie outro modelo.

## Pareamento do tablet

O autoatendimento é rota pública (`/assinatura/kiosk/menores`) e **só responde a tablet pareado**.

1. No tablet, abra `/assinatura/kiosk/menores` — sem pareamento, ele mostra o leitor de QR.
2. No computador, **Assinaturas → Termo de Menores → Tablet → Gerar QR de pareamento** (nome do tablet
   opcional).
3. Aponte a câmera do tablet para o QR. A tela do computador confirma e o tablet vai para o início.

- O QR vale **5 minutos** e é de **uso único**; o banco guarda só o sha256.
- O tablet fica pareado por **12 horas** (cookie `lara_minor_device`, httpOnly, SameSite=Strict, só no
  caminho `/assinatura/kiosk/menores`; banco com o sha256). Depois disso, pareie de novo.
- **Desparear** na lista da mesma tela corta o tablet na requisição seguinte.
- No modo sem HTTPS (`SIGNATURE_MANUAL_CODE_ENABLED=true`), a tela mostra também um código de 8
  caracteres para digitar no tablet.

## Histórico

**Assinaturas → Termo de Menores → Histórico.** Para quem põe a pulseira, quando a tela verde já saiu:
cada cartão repete a tela verde (menor, idade, responsável, foto do responsável, evento, título, hora).
Filtros: evento (padrão: o vigente, ou o mais recente), "Só os autorizados" / "Todos", e busca por
nome do menor, do responsável ou número do título. A foto é servida por rota
(`minor-terms.history.photo`), do disco privado, só para quem tem a permissão do histórico. "Abrir
documento" aparece para quem alcança a tela de documentos.

"Autorizado" = documento **Assinado** ou **Finalizado**. Termo recusado, expirado ou cancelado não conta.

## Regras que valem a pena saber

- **Fora da revisão interna.** `SignatureDocument::isMinorTerm()`: a fila de Revisão não lista, a aba
  diz "Não se aplica" e `SignatureReviewService::blockReason` recusa.
- **Gerado por:** `created_by_name = "Autoatendimento — <nome do tablet>"` (no manifesto, na tela e no
  `/validar`). Não há usuário: `created_by` fica nulo.
- **Signatário** com papel "Responsável legal" (`ROLE_GUARDIAN`), CPF e e-mail do responsável.
- **Trilha:** evento `self_service_started` (termo, tablet — sem nome nem CPF) e
  `identity_confirmed` com `modo = cpf_autoatendimento`. As tentativas de CPF erradas não têm documento
  ainda: vão para o log (`Termo de Menores: CPF do responsável não conferiu.`, sem o CPF).
- **Documento abandonado** (a pessoa saiu antes de assinar): ao gerar outro para o mesmo menor no mesmo
  termo, o anterior é **cancelado** ("Substituído por novo atendimento no autoatendimento"). Os demais
  expiram pelo `signature:expire`.
- **Nada do que o tablet digita volta ao MultiClubes.** E o que o MultiClubes já tem não é
  sobrescrito pelo tablet.
- **MultiClubes fora do ar** não vira "título não encontrado": o tablet diz que o cadastro não respondeu
  (503).
- **Estado do atendimento** (título, responsável confirmado) fica no **cache do servidor**, por tablet,
  só com ids — os dados são relidos do MultiClubes a cada passo. Morre com 10 minutos de inatividade ou
  no "Concluir".
- **Sem `queue:work`** o termo para em "Assinado" (sem PDF final nem via) — conta como autorizado mesmo
  assim.

## Configuração

`config/signature.php`, bloco `minor_terms`:

| Variável | Padrão | O que faz |
|---|---|---|
| `SIGNATURE_MINOR_DEVICE_TTL_HOURS` | `12` | Horas que o tablet fica pareado |
| `SIGNATURE_MINOR_PAIRING_TTL_SECONDS` | `300` | Validade do QR de pareamento |
| `SIGNATURE_MINOR_FLOW_TTL_MINUTES` | `10` | Inatividade que encerra o atendimento no servidor |
| `SIGNATURE_MINOR_MAX_CPF_ATTEMPTS` | `5` | Tentativas de CPF por título, por tablet, a cada 15 min |

A maioridade (18) é `minor_terms.adult_age`, sem variável de ambiente.

## Integrações

- **MultiClubes (SQL Server, `mc_sqlsrv`)** — só leitura: `dbo.Titles` (título ativo, fora dos tipos
  especiais, e o endereço) e `dbo.Members` (pessoas ativas do título). Mesmo recorte do cadastro de
  sócio no app. Ver [Integrações](../integracoes.md#114-sql-server--multiclubes).

## Referência técnica

| Camada | Arquivos |
|---|---|
| Migration | `2026_10_12_100000_create_signature_minor_terms` (`signature_minor_terms`, `signature_minor_authorizations`, `signature_kiosk_devices`) |
| Models | `SignatureMinorTerm`, `SignatureMinorAuthorization`, `SignatureKioskDevice`; `SignatureDocument::minorAuthorization()` / `isMinorTerm()` |
| Services | `app/Services/Signature/MinorTerms/` — `MinorTermService` (o fluxo), `MinorTermRules` (idade, sobrenome), `MinorTermFields` (variáveis e validação do modelo), `MinorTermMemberDirectory` (MultiClubes), `KioskDeviceService` (pareamento) |
| Controllers | `MinorTermKioskController` (tablet), `MinorTermController` (computador); trait `Signature\Concerns\RespondsWithKioskSession` (resposta de sessão e cookie `lara_sign`, comum ao quiosque) |
| Middleware | `EnsureMinorTermDevice` (alias `signature_minor_device`) |
| Rotas | `quiosque.menores.*` (público, `/assinatura/kiosk/menores`), `minor-terms.*` (`/assinatura/termo-menores`) |
| Telas | `resources/views/quiosque/index.blade.php` no modo `menores` + `quiosque/partials/menores-telas` e `menores-js`; `resources/views/signature/minor-terms/` |
| Seeder | `MinorTermTemplateSeeder` (fora do deploy) |
| Testes | `SignatureMinorTermKioskTest`, `SignatureMinorTermPanelTest`, `Unit\MinorTermRulesTest` |
