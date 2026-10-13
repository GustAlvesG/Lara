# Prompt para o agent do sistema de captura das quadras

> Cole o conteúdo abaixo (da linha `---` em diante) em um Claude Code aberto no repositório
> do **sistema que grava as quadras** (câmera + botão + corte do clipe). Ele descreve a API
> do RePET (no código, Replay) no Lara e o que construir do lado de lá.

---

Você vai integrar este sistema de captura ao **Lara**, o sistema de gestão do clube. O Lara
passa a ser a **fonte de verdade da configuração** (formato do vídeo, duração do clipe e
logomarcas) e quem **registra e entrega os clipes**, que ficam no servidor de arquivos (FTP)
do clube. A captura, o botão e o corte continuam sendo
responsabilidade deste sistema — nada disso muda.

Sua tarefa tem duas metades: **puxar a configuração** e **gravar o clipe no FTP e avisar o Lara**.

## Contexto de negócio

Em cada quadra há uma câmera e um botão. Quem aperta o botão guarda os últimos segundos de
jogo. Hoje esses arquivos morrem aqui; a partir de agora eles vão para o Lara, que os
entrega ao sócio que alugou a quadra (por e-mail e pelo site de locação) e os apaga sozinho
depois de 7 dias.

O Marketing configura tudo pelo Lara. Este sistema **não decide nada**: recebe o formato já
resolvido e obedece.

## Autenticação

Sanctum, token de acesso pessoal. Peça ao time do Lara que rode:

```
php artisan replay:token captura-producao
```

O comando imprime `REPLAY_API_TOKEN=1|AbCdEf...`. Guarde no `.env` deste sistema e mande em
toda requisição:

```
Authorization: Bearer <REPLAY_API_TOKEN>
```

Sem token → `401`. Token sem a ability `replay:operate` → `403`. Não invente um segundo
caminho de configuração de credencial se já existir um aqui.

## Endpoints

Base: `<host do Lara>/api/replay`

| Método | Rota | Para quê |
|---|---|---|
| GET | `/ping` | Diagnóstico: confirma o token sem depender de dado nenhum |
| GET | `/cameras` | Configuração de todas as câmeras ativas, já resolvida |
| GET | `/cameras/{external_id}` | O mesmo, para uma câmera |
| POST | `/cameras/{external_id}/heartbeat` | Sinal de vida do equipamento |
| POST | `/cameras/{external_id}/videos` | Avisa de um clipe já gravado no FTP |

O contrato completo, com todos os campos e exemplos de corpo, está em `docs/replay-api.md`
no repositório do Lara. Peça o arquivo — ele é a referência, e este prompt é o resumo.

## O identificador da câmera

Toda rota é endereçada pelo **`external_id`**: o identificador que **este sistema** usa para
o equipamento. Ele é cadastrado no Lara, e é assim que uma quadra é reconhecida.

Combine a lista de identificadores com quem cadastra no Lara **antes** de subir. Um
`external_id` que não existe lá devolve `404` no envio, e o clipe fica preso na sua fila.

Campo de futebol tem duas câmeras (uma por metade) — são dois `external_id` distintos, cada
um com sua configuração.

## Metade 1 — puxar a configuração

Consulte `GET /cameras` periodicamente (a cada 1–5 minutos é suficiente). A resposta traz um
**`config_hash`**, que é a impressão digital de toda a configuração publicada.

**Guarde o `config_hash` e só reprocesse quando ele mudar.** Reprocessar significa: baixar os
overlays novos e reconfigurar as câmeras. Enquanto o hash for o mesmo, não há nada a fazer —
é isso que torna o pull barato. O heartbeat **não** altera o hash.

De cada câmera, use:

- **`orientation`** — `vertical` (9:16) ou `horizontal` (16:9). É só a orientação: resolução e
  FPS continuam sendo decisão sua.
- **`clip_seconds`** — de 5 a 60. São os segundos **ANTERIORES** ao aperto do botão. **Não há
  pós-roll.** Se hoje este sistema grava algum tempo depois do botão, remova isso.
- **`overlay`** — ver abaixo. Vem `null` quando não há logomarca; nesse caso o vídeo sai limpo.

Ignore `sources` para tomar decisões — é campo de diagnóstico.

## O overlay

O Lara entrega o overlay **já composto**. Você não posiciona logomarca nenhuma: é um arquivo
único, do tamanho cheio do frame, com fundo transparente, aplicado em **(0,0)**.

- `overlay.png_url` — sempre presente quando há layout.
- `overlay.animated_url` — presente **só** quando o layout tem GIF animado e o servidor do
  Lara tem ffmpeg. É um **WebM VP9 com canal alpha** (`yuva420p`), para ser repetido em
  looping.

**Regra:** use o `animated_url` se souber tratá-lo; senão use o `png_url`. `animated_url: null`
é o caso normal, não é erro.

Se a câmera gravar em resolução diferente de `overlay.width` × `overlay.height`, **reescale o
overlay** para o tamanho do frame. A proporção é a mesma, então não há distorção.

O `hash` do overlay entra no nome do arquivo, então **URL nova significa conteúdo novo** — não
é preciso invalidar cache. Baixe e guarde em disco local; não busque o PNG a cada clipe.

```bash
# PNG estático
ffmpeg -i clipe.mp4 -i overlay.png \
  -filter_complex "[1:v]scale=1920:1080[ov];[0:v][ov]overlay=0:0" \
  -c:a copy saida.mp4

# WebM animado
ffmpeg -i clipe.mp4 -stream_loop -1 -i overlay.webm \
  -filter_complex "[1:v]scale=1920:1080[ov];[0:v][ov]overlay=0:0:shortest=1" \
  -c:a copy saida.mp4
```

## Metade 2 — gravar o clipe no FTP e avisar o Lara

O clipe **não vai pela API**. Grave-o no **servidor de arquivos (FTP)** do clube, na pasta
`Lara/Repet/` (servidor e credenciais: peça ao time do Lara), com a organização de subpastas e
o nome que fizerem sentido aqui. Só depois de o arquivo estar **completo** no FTP, avise:

`POST /cameras/{external_id}/videos`, JSON:

| Campo | Descrição |
|---|---|
| `path` | Caminho do clipe no FTP, relativo a `Lara/Repet/` (ou com ela na frente). O clipe **já com o overlay queimado**, MP4 (H.264) ou WebM. Até 255 caracteres |
| `recorded_at` | ISO 8601 — **instante do aperto do botão** (fim do clipe) |
| `duration_seconds` | Inteiro |
| `external_id` | **Id do clipe neste sistema.** Sempre envie |

Resposta `201` com `uuid`, `url`, `path` e `expires_at`. O Lara baixa o arquivo do FTP nesse
momento para conferir o formato: se ele ainda estiver sendo escrito, o aviso falha (`422`).
Grave com um nome temporário e renomeie no fim, ou só avise depois do upload concluído.

**Depois do `201`, o arquivo é do Lara:** não apague nem mova. O Lara apaga do FTP aos 7 dias
ou quando alguém exclui o vídeo na tela.

### Três coisas que não podem sair erradas

1. **Mande sempre o `external_id` do clipe.** É o que torna o aviso idempotente: reenviar o
   mesmo id (ou o mesmo `path`) devolve `200` com `"duplicated": true` e não cria um segundo
   vídeo.

2. **`recorded_at` é o instante real da gravação, não o do envio.** Dele saem duas coisas no
   Lara: a descoberta de qual sócio alugou a quadra naquele momento e a data de expiração. Um
   clipe que ficou dois dias na sua fila precisa chegar com a data original — ele terá 5 dias
   de vida, não 7, e isso está certo.

3. **Fila com repetição.** Grave o clipe no FTP e só então avise. Retente o aviso com backoff
   até receber `201` ou `200` — um `503` significa que o Lara não alcançou o FTP, e é para
   retentar. Um `422` de formato inválido ou de arquivo não encontrado não adianta retentar
   sem corrigir — registre e siga em frente.

### Erros

| Código | Significa | O que fazer |
|---|---|---|
| `401` / `403` | token ausente, inválido ou sem ability | conferir o `.env`; pedir novo token |
| `404` | `external_id` desconhecido ou câmera inativa no Lara | conferir o cadastro com quem administra |
| `422` | arquivo não encontrado no FTP, caminho inválido, formato, campo faltando ou data inválida | ler `message`; não retentar sem corrigir |
| `429` | limite de requisições | backoff |
| `503` | o Lara não alcançou o FTP | backoff e repetir o aviso |

## Heartbeat

`POST /cameras/{external_id}/heartbeat`, sem corpo, a cada 1–5 minutos por câmera. Ele
alimenta o "último contato" na tela do Lara — é o que permite ao Marketing descobrir uma
câmera muda **antes** de o sócio reclamar que o botão não gravou. Falha de heartbeat não deve
interromper nada: registre e siga.

## O que NÃO fazer

- Não decidir orientação, duração ou logomarca localmente — tudo vem do Lara.
- Não gravar tempo depois do botão: o contrato é só pré-roll.
- Não posicionar logomarcas: o overlay vem composto e vai em (0,0).
- Não guardar a `url` do vídeo devolvida pelo Lara como se fosse permanente — ela expira em 7
  dias.
- Não enviar sem `external_id` de clipe.
- Não mandar o arquivo pela API, nem avisar antes de o upload ao FTP terminar.
- Não apagar nem mover no FTP um clipe que o Lara já aceitou.

## Entregue no fim

- Um comando ou tela de diagnóstico que chame `/ping` e `/cameras` e mostre, por câmera, a
  configuração em vigor e o overlay baixado.
- Log claro de cada envio: `external_id` do clipe, resposta e tentativa.
