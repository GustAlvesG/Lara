# Prompt para o agent do site — Assinatura remota de documentos

> Cole o conteúdo abaixo (da linha `---` em diante) num Claude Code aberto no repositório do
> **site externo em Next.js** (o mesmo que hospeda as reservas e a área `/aprovacao` de Compras).
> Ele descreve a API de assinatura remota do Lara e a área a construir.
>
> **Estado:** implementado no Lara na branch `assinatura-remota` (21/10/2026), com os anexos enviados
> pela própria pessoa. O contrato abaixo é o do código
> (`App\Http\Controllers\Api\RemoteSignatureApiController`, rotas `api.assinatura-remota.*`). Se a API
> mudar, ajuste aqui antes de colar e gere um prompt de ajuste para o site.
>
> Esta versão substitui a primeira (do mesmo dia): ela acrescenta a etapa de anexos e deixa explícito
> que a área é separada do site de reservas, como a `/aprovacao`.

---

Você vai construir, neste projeto, uma **área separada de assinatura de documentos a distância**. Hoje
os documentos do clube (termos, contratos, fichas) são assinados num tablet no balcão. A partir de
agora, o atendente também pode mandar à pessoa um **link especial por e-mail**, e ela assina por esta
área, no celular ou no computador.

**Esta área não faz parte do site de reservas.** Ela só divide o servidor e o visual, como a área de
aprovação de compras (`/aprovacao`), se ela existir neste repositório. Não tem relação com login,
sócio, reserva ou qualquer outra parte do site: quem chega aqui chegou pelo link do e-mail, e só por
ele.

O back-end é o Lara. Ele já faz tudo: guarda o documento, confere a identidade, recebe os anexos, grava
a assinatura, monta o PDF final. Sua tarefa é a interface e a integração. **Nenhuma regra é decidida
aqui:** prazos, tentativas, exigências e mensagens vêm da API.

## Antes de escrever qualquer código

Explore o projeto e me diga o que encontrou:

1. Como as rotas estão organizadas (App Router ou Pages) e **como a área `/aprovacao` foi isolada**
   do resto do site (grupo de rotas, layout próprio, sessão própria), se ela existir.
2. **Se as chamadas ao Lara saem do servidor do Next** (Route Handler, Server Action, `fetch` em
   Server Component) **ou do navegador.** Verifique; não presuma.
3. O sistema de design: botões, campos, cartões, avisos, upload de arquivo, tema escuro.
4. Se já há algum componente de assinatura com o dedo (canvas), de câmera ou de PDF.js.

Só depois proponha o plano.

## Restrições não negociáveis

**1. Área isolada, fora do site de reservas.**
- Grupo de rotas e **layout próprios**, sem o cabeçalho, o menu, o rodapé de navegação, os links nem
  o estado de login do site de reservas. Só a marca do clube e o conteúdo da assinatura.
- **Nenhuma ligação com a sessão de sócio**: não leia nem escreva o cookie de login de sócio, não
  ofereça "entrar", não use dado de sócio. Um sócio logado no site não pula nenhuma etapa; quem assina
  não vira sócio logado.
- **Não indexável e não descobrível:** nenhum link para ela em parte nenhuma do site; fora do
  `sitemap`; `<meta name="robots" content="noindex, nofollow">` e o header
  `X-Robots-Tag: noindex, nofollow` em todas as respostas da área; `/assinar` como `Disallow` no
  `robots.txt`. A raiz `/assinar` sem link válido mostra só "abra o link que você recebeu por e-mail".
- Sem analytics, pixels ou scripts de terceiros na área.

**2. O navegador nunca fala com o Lara.** O site roda na DMZ, e o Lara não é publicado na internet.
Toda chamada sai do servidor do Next. Se outra área chamar o Lara pelo navegador, **não copie esse
padrão**: relate e siga pelo servidor.

**3. Credenciais nunca chegam ao navegador.**
- `LARA_API_URL` e `LARA_SIGNATURE_API_TOKEN` são variáveis **sem** `NEXT_PUBLIC_`.
- O token desta API é **outro**: não é o `API_TOKEN` das reservas nem o da aprovação. Não reaproveite.
- O token do link (o que vem no e-mail) e o segredo da sessão ficam em **cookies httpOnly**,
  `secure`, `sameSite=lax`, com `path=/assinar`. Nunca em `localStorage`, `sessionStorage`,
  IndexedDB, estado de React ou URL depois da primeira carga.

**4. O IP e o navegador de quem assina vão para o Lara.** Em toda chamada, repasse:
- `X-Signer-Ip`: o IP do cliente que chegou ao Next (o `X-Forwarded-For` do proxy da frente,
  primeiro item, se houver proxy; senão o IP da conexão). Confira como este projeto está publicado
  antes de escolher.
- `X-Signer-User-Agent`: o `User-Agent` do navegador da pessoa.

Esses valores vão para a evidência jurídica da assinatura e para o manifesto do PDF. Sem eles, o Lara
registraria o IP do servidor do site como se fosse o da pessoa.

**5. O front não decide nada.** Não calcule prazo, não conte tentativas, não esconda etapa por conta
própria. Use o que a API devolve (`rules`, `form`, `attachments`, `requirements`, `attempts_left`). Se
a API recusar, mostre o `error` que ela devolveu: as mensagens são escritas em português, para quem
está assinando.

**6. Nada fica no aparelho.** A pessoa pode estar num computador compartilhado. Não guarde o PDF, os
anexos, a foto, a assinatura nem as respostas em cache do navegador, service worker ou armazenamento
local. As respostas da área vão com `Cache-Control: no-store`.

## O endereço

O e-mail leva `https://<este site>/assinar/<token>`. O token tem 64 caracteres `[A-Za-z0-9]`.
Desde 30/10/2026 o atendente também pode copiar o mesmo endereço e encaminhar por outro canal, como o WhatsApp.
Por isso, a tela não deve dizer que o link veio por e-mail, e a primeira carga precisa aguentar a pré-visualização
de link do aplicativo: nada de abrir sessão (`abrir`) nem consumir o link sem um clique da pessoa.

Na primeira carga de `/assinar/[token]`:
1. no servidor, guarde o token num cookie httpOnly (`path=/assinar`, validade de 3 dias);
2. redirecione para `/assinar` (sem o token), para ele sair do histórico e da barra de endereço;
3. sirva a área com `Referrer-Policy: no-referrer`, `Cache-Control: no-store` e o `X-Robots-Tag`.

Abrir um link novo (outro e-mail) troca o cookie.

## A API

Base: `LARA_API_URL` (rede interna). Todas as chamadas:

```
Authorization: Bearer <LARA_SIGNATURE_API_TOKEN>
Accept: application/json
X-Signer-Ip: <ip de quem assina>
X-Signer-User-Agent: <user agent de quem assina>
```

As rotas com sessão pedem também `X-Signature-Session: <session_token>`.

**Toda recusa** responde `{ "error": "mensagem pronta", "reason": "código" }`. Erros de validação de
campo (o Laravel) vêm como `{ "message": ..., "errors": { campo: [...] } }`, com 422.

| `reason` | Status | Tela |
|---|---|---|
| `invalid_link` | 404 | "Link inválido" — confira se abriu o link inteiro |
| `link_expired` | 410 | "Link vencido — peça um novo ao clube" |
| `link_superseded` | 410 | "Existe um link mais recente — use o último e-mail" |
| `link_revoked` | 410 | "Link cancelado pelo clube" |
| `link_used` | 409 | "Você já respondeu a este documento" |
| `link_locked` | 423 | "CPF não conferiu nas tentativas — peça um novo link" |
| `document_unavailable` | 409 | Documento cancelado, já assinado por outro caminho etc. (use o `error`) |
| `session_ended` | 419 (ou 409) | A sessão caiu: volte à tela de entrada (pode abrir de novo, se o link valer) |
| `identity_required` | 409 | Chamou PDF, perguntas ou anexos antes do CPF — bug do front; volte à etapa do CPF |
| `identity_failed` | 422 | CPF errado: mostre o `error` e `attempts_left` |
| `attachments_missing` | 409 | Tentou assinar com anexo obrigatório faltando: volte à etapa de anexos |
| `attachment_refused` | 422 / 403 / 404 | Anexo recusado (tipo, item, não é seu): mostre o `error` |
| `invalid` / `conflict` / `too_many_attempts` | 422 / 409 / 429 | Mostre o `error` |
| `unauthorized` | 401 / 403 | Configuração do site (token ou IP): log no servidor, mensagem genérica na tela |

Limites: 20 chamadas/min em `link` e `abrir`, 120/min no resto, por IP de quem assina. Uma resposta
429 sem `reason` é esse limite.

### `POST /api/assinatura/remota/link` — página de entrada (não abre sessão)

Corpo: `{ "token": "<64 caracteres>" }` (o token vai **no corpo**, nunca na URL).

```json
{
  "document": { "title": "Contrato de locação — Salão Nobre", "validation_code": "K7M2Q9XA" },
  "signer": { "name": "Maria de Souza", "role": "Contratante" },
  "sent_by": "Ana (atendimento)",
  "sent_at": "2026-10-21T14:02:11-03:00",
  "expires_at": "2026-10-24T14:02:11-03:00",
  "requirements": {
    "identity": "cpf_full", "photo": true, "initials": false, "form": true,
    "attachments": 2, "attachments_required": 1
  },
  "identity_attempts_left": 5,
  "server_time": "2026-10-21T15:10:00-03:00"
}
```

Mostre: título, para quem é, quem enviou, até quando vale e o que vai ser pedido (CPF completo, foto;
visto se `initials`; perguntas se `form`; quantos anexos, se `attachments` > 0). Botão **Começar**.

**Antes de chamar `abrir`, confira a câmera** (`navigator.mediaDevices?.getUserMedia`). Sem câmera,
avise já na entrada que é preciso um aparelho com câmera e não deixe começar: a foto é obrigatória e o
Lara recusa sem ela.

### `POST /api/assinatura/remota/abrir` — começa

Corpo: `{ "token": "..." }`. Abre uma sessão (30 min) e devolve o segredo **só nesta resposta**.
Guarde `session_token` em cookie httpOnly (`path=/assinar`). Abrir de novo (outra aba, outro aparelho)
derruba a sessão anterior.

```json
{
  "session_token": "<64 caracteres>",
  "document": { "id": 812, "title": "...", "pdf_url": null, "pdf_version": "3fa9c01b7d2e" },
  "form": {
    "answered": false,
    "fields": [
      { "key": "telefone", "question": "Qual o seu telefone?", "type": "phone", "required": true, "options": [], "value": "" }
    ]
  },
  "signer": { "name": "Maria de Souza", "role": "Contratante", "has_email": true },
  "rules": { "identity_check": "full", "requires_photo": true, "requires_initials": false, "identity_confirmed": false },
  "session": { "remaining_seconds": 1800, "warning_seconds": 60 },
  "server_time": "2026-10-21T15:10:02-03:00",
  "photo_consent_text": "Autorizo a captura da minha imagem (foto) para anexo ao contrato. ...",
  "attachments": null,
  "link": { "expires_at": "2026-10-24T14:02:11-03:00", "identity_attempts_left": 5 }
}
```

`form` é `null` quando não há perguntas (ou quando alguém já assinou e o texto não muda mais).
`attachments` é `null` até o CPF ser confirmado; depois, vem como em `GET /anexos`.

### Rotas com sessão (`X-Signature-Session`)

| Rota | Corpo | Resposta |
|---|---|---|
| `GET /api/assinatura/remota/sessao` | — | O mesmo objeto do `abrir`, sem `session_token` |
| `POST /api/assinatura/remota/identidade` | `{ "cpf": "123.456.789-09" }` (com ou sem máscara) | `{ "ok": true }`; errado: 422 `identity_failed` + `attempts_left`; quinta errada: 423 `link_locked` |
| `POST /api/assinatura/remota/respostas` | `{ "answers": { "<key>": valor } }` | O objeto da sessão; erro de campo: 422 `{ error, reason: "invalid_answers", errors: { key: "mensagem" } }` |
| `GET /api/assinatura/remota/anexos` | — | A lista de anexos (abaixo) |
| `POST /api/assinatura/remota/anexos` | **multipart**: `file` + `key` | 201 com a lista atualizada |
| `DELETE /api/assinatura/remota/anexos/{id}` | — | A lista atualizada |
| `GET /api/assinatura/remota/pdf` | — | `application/pdf` (os bytes que a pessoa assina) |
| `POST /api/assinatura/remota/visualizado` | `{ "read_seconds": 95, "scrolled_to_end": true }` | `{ "ok": true }` |
| `POST /api/assinatura/remota/assinar` | ver abaixo | `{ ok, message, signed_at, validation_code }` |
| `POST /api/assinatura/remota/recusar` | `{ "reason": "texto opcional, até 255" }` | `{ ok, message }` |
| `POST /api/assinatura/remota/encerrar` | — | `{ ok }` (sair sem assinar; o link continua valendo) |

`pdf`, `respostas`, `visualizado` e `anexos` só funcionam **depois do CPF** (senão 409
`identity_required`).

#### Anexos

```json
{
  "items": [
    {
      "key": "doc_identidade", "label": "Identidade", "required": true,
      "files": [
        { "id": 55, "type": "pdf", "size_label": "PDF, 312 KB", "sent_at": "2026-10-21T15:12:40-03:00", "mine": true, "can_remove": true }
      ]
    },
    { "key": "doc_comprovante", "label": "Comprovante", "required": false, "files": [] }
  ],
  "missing_required": [],
  "max_kb": 10240,
  "accepted_types": ["pdf", "jpg", "png"]
}
```

- Envie **um arquivo por vez**, sempre para um `key` da lista (não há anexo avulso). O Next repassa o
  multipart ao Lara (stream, sem gravar em disco). Aceite no `<input>` `application/pdf,image/jpeg,image/png`
  e confira o tamanho contra `max_kb` antes de enviar, só para avisar cedo: quem decide é o Lara, que
  confere o tipo **pelo conteúdo**.
- No celular, ofereça tirar a foto do documento na hora (`capture="environment"`) ou escolher um arquivo.
- `mine`: foi a pessoa que enviou. `can_remove`: ela pode apagar (só o que ela enviou, e só até
  assinar). Arquivos com `mine: false` foram enviados pelo clube: mostre como "já recebido pelo clube",
  sem botão de apagar.
- **Trocar** = apagar o dela e enviar outro.
- Não há download do anexo pela API: mostre só o tipo, o tamanho e a data.
- `missing_required` lista os rótulos dos obrigatórios que faltam. Enquanto não estiver vazia, o
  `assinar` responde 409 `attachments_missing`: não deixe a pessoa passar da etapa de anexos.

#### Corpo do `assinar` (tudo numa requisição só; o Lara grava tudo ou nada)

```json
{
  "signature": "data:image/png;base64,...",
  "strokes": [ { "points": [ { "x": 12.5, "y": 40.1, "t": 0 }, { "x": 13.0, "y": 41.2, "t": 16 } ] } ],
  "initials": "data:image/png;base64,...",
  "initials_strokes": [ { "points": [ ... ] } ],
  "photo": "data:image/jpeg;base64,...",
  "photo_consent": true,
  "accepted": true,
  "wants_copy": true,
  "read_seconds": 95,
  "scrolled_to_end": true,
  "viewport": { "w": 390, "h": 844, "orientation": "portrait" }
}
```

- `signature`: PNG do traço, fundo transparente ou branco. `strokes`: cada traço com os pontos (`x`,
  `y` no canvas, `t` em ms desde o início). **Mínimo de 30 pontos** somando os traços (um toque não é
  assinatura).
- `initials` / `initials_strokes`: só quando `rules.requires_initials` (rubrica, mínimo 8 pontos).
- `photo`: JPEG da câmera frontal. O tablet usa um quadrado de 600×600 a qualidade 0,82; até 4 MB.
- `photo_consent`: a caixa "autorizo a captura da imagem", com o texto `photo_consent_text`
  **exatamente** como veio. Sem ela, 422.
- `accepted`: a caixa de aceite dos termos.
- `wants_copy`: "quero receber a via assinada por e-mail" (o e-mail é o do cadastro; não mostre).
- Os tipos de imagem são conferidos pelos bytes; não adianta trocar o cabeçalho.

## As etapas da tela

A ordem é esta, e é a do Lara. O CPF vem **antes** do documento, de propósito:

1. **Entrada** (`link`): o que é, para quem, até quando, o que vai ser pedido. Confere a câmera.
   Botão **Começar** → `abrir`.
2. **CPF**: campo com máscara, teclado numérico. `identidade`. Mostre `attempts_left` quando errar.
   Em `link_locked`, tela final "peça um novo link".
3. **Perguntas** (só se `form` não for `null`): um campo por item de `form.fields`, com `question`
   como título, `value` já preenchido. Tipos e o que mandar em `answers`:
   - `text`, `textarea`, `email`, `number`, `money` (`1.234,56`), `cpf`, `cnpj`, `phone`, `cep`: o
     texto digitado (máscara opcional; o Lara normaliza);
   - `date` / `date_long`: `AAAA-MM-DD`; `time`: `HH:MM`;
   - `radio`: uma das `options`, igual; `checkbox`: lista das `options` marcadas;
   - `yes_no`: `"sim"` ou `"nao"`.
   Envie em `respostas`. Erros por campo vêm em `errors[key]`. Depois de responder, o PDF muda: busque
   de novo (o `document.pdf_version` muda junto).
4. **Anexos** (só se `attachments.items` não estiver vazia): um cartão por item, com o rótulo,
   "obrigatório"/"opcional", o que já foi enviado e o botão de enviar/trocar. **Continuar** só com
   `missing_required` vazia.
5. **Leitura**: o PDF (via `pdf`, repassado pelo Next) em PDF.js, página a página, largura do celular.
   Conte o tempo com o documento na tela e se rolou até o fim. Ao sair da leitura, `visualizado`.
   Botão **Recusar** visível em qualquer etapa a partir daqui (com motivo opcional e confirmação:
   recusar encerra o documento para todos).
6. **Aceite**: caixa "Li e aceito os termos", caixa de autorização da foto com `photo_consent_text`,
   caixa da via por e-mail (se `signer.has_email`).
7. **Assinatura**: canvas grande (pointer events, `touch-action: none`), botões **Limpar** e
   **Continuar**; habilite só com 30 pontos ou mais.
8. **Visto** (se `rules.requires_initials`): o mesmo canvas, limpo, pedindo a rubrica (8 pontos).
9. **Foto**: câmera frontal ao vivo; a pessoa toca em **Tirar foto**, contagem de 3 s, captura.
   Desligue a câmera logo depois.
10. **Envio** → `assinar`. Sucesso: "Assinatura registrada em <data>", o `validation_code` e, se
    pediu a via, "a via chega por e-mail quando o documento for finalizado". Limpe os cookies do link
    e da sessão.

**Sessão:** a cada ~15 s chame `sessao` (só com a aba visível). Mostre um aviso quando
`remaining_seconds` < `warning_seconds`. Em 419/`session_ended`, volte à entrada: se o link ainda
valer, a pessoa começa de novo (as tentativas de CPF continuam contando; os anexos já enviados
continuam lá). Use `server_time` para qualquer data na tela; não confie no relógio do aparelho.

**Falha de rede no `assinar`:** a assinatura pode ter entrado mesmo sem a resposta chegar. Antes de
pedir para assinar de novo, chame `link` com o token: `link_used` quer dizer que entrou (mostre a tela
de sucesso); link válido quer dizer que não entrou (reenvie a mesma requisição, se a sessão ainda
valer, ou recomece pela entrada).

## O que vale saber

- **Acessibilidade:** fonte legível, contraste, botões grandes; a pessoa pode ter pouca familiaridade
  com o celular. Teste em tela de 360 px.
- **Nada de dado pessoal na URL, em log de servidor ou em ferramenta de terceiros** (CPF, foto,
  assinatura, anexos, token, `session_token`).
- **Upload:** ajuste o limite de corpo do Next/proxy para caber `max_kb` (10 MB por padrão) nas rotas
  de anexo, e só nelas.
- **Testes:** cubra os estados de recusa da tabela acima com a API mockada, o bloqueio sem câmera, a
  etapa de anexos (enviar, trocar, obrigatório travando), a ordem das etapas, os headers de não
  indexação e a limpeza dos cookies no fim.
- **Documento com mais de um texto (desde 29/10/2026):** um modelo pode exigir outro junto (modelo
  vinculado). O PDF que a API entrega já traz os dois textos, um depois do outro, e o formulário de
  `respostas` já traz os campos dos dois. Nada muda no contrato: trate como um PDF mais longo e um
  formulário maior. Campo marcado no Lara como "Só o atendente responde" nunca chega ao formulário.
  A conferência das respostas pelo atendente é só do tablet do balcão: a sessão remota não tem a
  chave `answer_check` e não espera aprovação.
- Variáveis novas no `.env` do site: `LARA_SIGNATURE_API_TOKEN` (o mesmo valor de
  `SIGNATURE_REMOTE_API_TOKEN` no Lara). No Lara, a TI também preenche `SIGNATURE_REMOTE_SITE_URL`
  com o endereço público deste site e `SIGNATURE_REMOTE_ALLOWED_IPS` com o IP deste servidor.

Ao terminar, me diga o que foi feito, o que ficou de fora e como testar de ponta a ponta contra a
homologação do Lara.
