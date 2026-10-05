# Replay — API

API consumida pelo **sistema de captura das quadras** (o que grava, recebe o aperto do botão e corta os últimos segundos) e pelo **site de locação de espaços**.

A integração é por **pull**: o Lara é a fonte de verdade da configuração, e o sistema de captura consulta quando quer. O Lara nunca chama o outro lado — um push dependeria de o servidor das quadras estar de pé no exato momento em que alguém salva uma configuração, e a mudança perdida só apareceria no vídeo, depois do jogo.

Módulo completo em `app/Models/Replay`, `app/Services/Replay`, `app/Http/{Controllers,Requests,Resources}/Replay`. Guia de uso em [docs/funcionalidades/replay.md](funcionalidades/replay.md).

---

## 1. Autenticação

São dois consumidores, com credenciais diferentes.

### Sistema de captura — Sanctum

Token de acesso pessoal, sem sessão/CSRF. Uma ability: `replay:operate` (`App\Support\Replay\ReplayAbilities::OPERATE`).

```
php artisan replay:token captura-producao
```

O nome identifica o **cliente**, não o token: rodar de novo com o mesmo nome reaproveita o cliente e emite um token novo **sem revogar o anterior** — é como se troca a chave sem derrubar a gravação no meio do expediente. O comando imprime:

```
REPLAY_API_TOKEN=1|AbCdEf...
```

Toda requisição:

```
Authorization: Bearer {{token}}
```

Sem token → `401`. Token sem a ability → `403`.

### Site de locação — token da integração e JWT do sócio

- A **galeria pública** usa o mesmo `api_token` estático que o portal já usa nas outras rotas (`Authorization: Bearer {{API_TOKEN}}`).
- **Meus vídeos** exige, além dele, o JWT do sócio no cabeçalho `Session` — o mesmo já usado em `/api/schedule/...`.

---

## 2. Convenções

- Prefixo `/api/replay`.
- Resposta **sem envelope** `{"data": ...}` — `JsonResource::withoutWrapping()` está ativo globalmente. O que está abaixo é o corpo exato.
- Datas em **ISO 8601** (`2026-09-17T14:32:10-03:00`).
- Toda URL de mídia é **absoluta**, resolvida no servidor.
- A câmera é endereçada pelo **`external_id`** — o identificador do equipamento no sistema de captura —, nunca pelo id interno do Lara. Trocar um equipamento de quadra é editar um cadastro no Lara, sem mexer em nada do outro lado.
- A configuração chega **já resolvida**: o Lara aplica a herança (quadra > esporte > padrão) e entrega o resultado. O sistema de captura não decide nada.

### Limites de requisição

| Rota | Limite |
|---|---|
| `GET /replay/cameras`, `/cameras/{id}`, heartbeat | 120/min |
| `POST /replay/cameras/{id}/videos` | 300/min |
| `GET /replay/ping` | 60/min |

O envio é folgado de propósito: o botão é apertado em rajada durante um jogo, e a fila do outro lado despeja o acumulado de uma vez quando a rede volta.

---

## 3. Rotas

| Método | Rota | Quem chama |
|---|---|---|
| GET | `/replay/ping` | captura — diagnóstico de token |
| GET | `/replay/cameras` | captura — configuração de todas as câmeras |
| GET | `/replay/cameras/{external_id}` | captura — configuração de uma câmera |
| POST | `/replay/cameras/{external_id}/heartbeat` | captura — sinal de vida |
| POST | `/replay/cameras/{external_id}/videos` | captura — envio do clipe |
| GET | `/replay/places` | portal — quadras com vídeo disponível |
| GET | `/replay/places/{place}/videos` | portal — galeria da quadra |
| GET | `/replay/my-videos` | portal — vídeos do sócio logado |

---

## 4. Configuração das câmeras

### `GET /replay/cameras`

```jsonc
{
  "config_hash": "9f2c41ab7e0d5c83",
  "cameras": [
    {
      "external_id": "cam-quadra1",
      "name": "Quadra 1",
      "position": "Lado A",
      "place": { "id": 12, "name": "Quadra 1" },
      "place_group": { "id": 3, "name": "Tênis" },
      "orientation": "horizontal",
      "clip_seconds": 30,
      "overlay": {
        "png_url": "https://lara.exemplo/storage/replay/overlays/7-3f1a9c2b.png",
        "animated_url": null,
        "width": 1920,
        "height": 1080,
        "hash": "3f1a9c2b",
        "updated_at": "2026-09-17T09:12:44-03:00"
      },
      "sources": { "settings": "group", "layout": "place" }
    }
  ]
}
```

**`config_hash`** é a impressão digital de toda a configuração publicada. Guarde-o e só reprocesse (baixar overlays, reconfigurar câmeras) quando ele mudar — é o que torna o pull barato. Ele **não** muda por causa de heartbeat.

**`orientation`**: `vertical` (9:16) ou `horizontal` (16:9). É só a orientação — resolução e FPS são decisão da câmera.

**`clip_seconds`**: entre **5 e 60**. São sempre os segundos **anteriores** ao aperto do botão. **Não há pós-roll** no contrato.

**`overlay`**: `null` quando ninguém configurou logomarca para aquela quadra — nesse caso o vídeo sai limpo.

**`sources`**: de onde veio cada coisa (`place`, `group` ou `default`/`none`). É só diagnóstico; não mude comportamento com base nisso.

### `GET /replay/cameras/{external_id}`

Mesma estrutura, com a câmera em `camera` em vez da lista. `404` se o `external_id` não existir ou a câmera estiver inativa.

### `POST /replay/cameras/{external_id}/heartbeat`

Sem corpo. Responde `{"ok": true, "config_hash": "..."}`. Alimenta o "último contato" na tela de câmeras — é o que denuncia equipamento mudo antes de o sócio reclamar que o botão não grava. Chame a cada 1–5 minutos.

---

## 5. Como aplicar o overlay

O Lara entrega o overlay **já composto**: um único arquivo, do tamanho cheio do frame, com fundo transparente. O sistema de captura aplica em **(0,0)**, sem calcular posição de logo nenhuma.

- **`png_url`** — sempre presente quando há layout. É o contrato principal.
- **`animated_url`** — presente **apenas** quando o layout tem alguma logomarca em GIF animado **e** o servidor tem ffmpeg. É um **WebM VP9 com canal alpha** (`yuva420p`), pensado para ser repetido em looping.

Regra de consumo: **use o `animated_url` se souber tratá-lo; caso contrário use o `png_url`.** `animated_url: null` não é erro — é o caso normal.

Se a câmera gravar em resolução diferente de `width`×`height`, **reescale o overlay** para o tamanho do frame. A proporção é a mesma (1080×1920 ou 1920×1080), então o reescalo é exato, sem distorção.

O `hash` entra no nome do arquivo: **URL nova a cada alteração**. Isso dispensa invalidação de cache — se a URL é a mesma, o conteúdo é o mesmo.

Exemplo com ffmpeg:

```bash
# PNG estático
ffmpeg -i clipe.mp4 -i overlay.png \
  -filter_complex "[1:v]scale=1920:1080[ov];[0:v][ov]overlay=0:0" \
  -c:a copy saida.mp4

# WebM animado (quando animated_url vier preenchido)
ffmpeg -i clipe.mp4 -stream_loop -1 -i overlay.webm \
  -filter_complex "[1:v]scale=1920:1080[ov];[0:v][ov]overlay=0:0:shortest=1" \
  -c:a copy saida.mp4
```

---

## 6. Envio do clipe

### `POST /replay/cameras/{external_id}/videos`

`multipart/form-data`:

| Campo | Obrigatório | Descrição |
|---|---|---|
| `file` | sim | O clipe. **MP4 (H.264) ou WebM**, até **256MB**. O tipo é conferido pela assinatura do container, não pelo nome nem pelo `Content-Type`. |
| `recorded_at` | sim | ISO 8601. **Instante do aperto do botão** (fim do clipe). |
| `duration_seconds` | sim | Inteiro, 1 a 120. |
| `external_id` | não, mas **envie** | Id do clipe no sistema de captura. É o que garante idempotência. |

Resposta `201`:

```jsonc
{
  "uuid": "6f1d2c44-9f2e-4a11-9b3e-7c2a51d0e8aa",
  "url": "https://lara.exemplo/storage/replay/videos/2026/09/6f1d2c44-....mp4",
  "place": { "id": 12, "name": "Quadra 1" },
  "recorded_at": "2026-09-17T14:32:10-03:00",
  "expires_at": "2026-09-24T14:32:10-03:00",
  "linked_to_member": true,
  "duplicated": false
}
```

**Idempotência:** reenviar o mesmo `external_id` devolve `200` com `"duplicated": true` e **não** cria um segundo vídeo nem dispara outro e-mail. Sem `external_id` não há idempotência — um retry vira um clipe duplicado. Mande sempre.

**`recorded_at` importa mais do que parece:** dele saem tanto o vínculo com a reserva quanto a expiração. Um clipe que ficou preso na fila por dois dias chega com **cinco** dias de vida, não sete — sete dias é a promessa feita ao sócio sobre o jogo dele, contada da gravação.

**`linked_to_member`** diz se o clipe caiu numa reserva **paga**. Serve para o log do sistema de captura; nenhum dado do sócio sai por aqui.

### Erros

| Código | Quando | O que fazer |
|---|---|---|
| `401` | sem token | conferir `REPLAY_API_TOKEN` |
| `403` | token sem a ability | gerar outro com `replay:token` |
| `404` | `external_id` desconhecido ou câmera inativa | conferir o cadastro de câmeras no Lara |
| `413` / `422` com corpo vazio | arquivo maior que o `post_max_size` do PHP | **ver a seção 9** — é limite de servidor, não da aplicação |
| `422` | formato não aceito, campo faltando, data inválida | ler `message` |
| `429` | limite de requisições | repetir com backoff |

**Recomendação de fila:** guarde o clipe localmente e retente com backoff até receber `201` ou `200`. Um `422` de formato não adianta retentar — registre e siga.

---

## 7. Site de locação

### `GET /replay/places`

```jsonc
{
  "places": [
    {
      "id": 12,
      "name": "Quadra 1",
      "place_group": { "id": 3, "name": "Tênis" },
      "videos_count": 14,
      "last_recorded_at": "2026-09-17T19:40:02-03:00"
    }
  ]
}
```

Só quadras que têm vídeo disponível agora.

### `GET /replay/places/{place}/videos?date=2026-09-17&page=1`

```jsonc
{
  "place": { "id": 12, "name": "Quadra 1", "place_group": { "id": 3, "name": "Tênis" } },
  "videos": [
    {
      "uuid": "6f1d2c44-...",
      "url": "https://lara.exemplo/storage/replay/videos/2026/09/6f1d2c44-....mp4",
      "place": { "id": 12, "name": "Quadra 1" },
      "place_group": { "id": 3, "name": "Tênis" },
      "orientation": "horizontal",
      "duration_seconds": 30,
      "size_bytes": 8421376,
      "recorded_at": "2026-09-17T19:40:02-03:00",
      "expires_at": "2026-09-24T19:40:02-03:00",
      "days_left": 7,
      "has_member": true
    }
  ],
  "meta": { "current_page": 1, "last_page": 3, "total": 62 }
}
```

A galeria é **aberta**: qualquer visitante vê e baixa. `has_member` marca que o vídeo foi gravado durante uma reserva paga — **sem dizer de quem**. Nenhum identificador de sócio sai nesta listagem.

### `GET /replay/my-videos`

Exige o cabeçalho `Session` com o JWT do sócio. Devolve `{"videos": [...]}` no mesmo formato, só com o que está vinculado a ele. O sócio é resolvido **pelo próprio token** — não há id na URL, justamente para que trocar um número na barra de endereços não dê acesso aos vídeos de outro.

### Os arquivos

`url` é um **arquivo estático**, servido pelo próprio servidor web. Isso dá *range request*, e com ele a possibilidade de avançar o vídeo no player em vez de assistir do começo. Use a URL direto no `<video src>` e no botão de download (`<a download>`).

---

## 8. Retenção

Todo clipe vive **7 dias contados da gravação** e é apagado por `replay:prune` (agendado às 03:30). **Não há exceção** — nem para material de campanha, que deve ser baixado antes do prazo.

Para o consumidor isso significa: `url` pode virar 404 a partir de `expires_at`. Não guarde a URL como se fosse permanente.

---

## 9. Requisitos de servidor

Duas mudanças são necessárias e **não** estão no código:

1. **Limite de upload.** O servidor está hoje com `upload_max_filesize` / `post_max_size` = **30M**, o que recusa um clipe de 60s a 1080p **antes** de a requisição chegar à aplicação (o Laravel recebe corpo vazio e devolve 422 sem explicação útil). Subir os dois no `php.ini` para **256M** e reiniciar o Apache. Se houver `LimitRequestBody` configurado no Apache, subir junto (o padrão é ilimitado).

2. **ffmpeg** (opcional, só para overlay animado). O projeto não o tinha por decisão explícita. Sem ele, o módulo funciona inteiro — GIF animado sai parado (primeiro quadro) e `animated_url` vem `null`. Caminho configurável em `REPLAY_FFMPEG_PATH`.

Variáveis de ambiente do Lara:

```
REPLAY_PORTAL_URL=https://locacao.clubedosfuncionarios.com.br
REPLAY_FFMPEG_PATH=ffmpeg
```

---

## 10. Fluxo completo, do começo ao fim

1. Alguém do Marketing define no Lara a orientação e a duração do clipe — por esporte ou por quadra.
2. Monta o layout de logomarcas arrastando as peças sobre o preview. O Lara compõe o PNG (e o WebM, se houver GIF animado).
3. TI cadastra a câmera com o `external_id` combinado com quem instalou.
4. O sistema de captura consulta `GET /replay/cameras`, vê o `config_hash` mudar, baixa os overlays e reconfigura.
5. Alguém aperta o botão na quadra. O sistema corta os últimos `clip_seconds`, queima o overlay e envia por `POST /replay/cameras/{id}/videos`.
6. O Lara arquiva, descobre se havia reserva **paga** naquele instante e, havendo, amarra o clipe ao sócio.
7. Cinco minutos depois do fim da reserva, **um** e-mail leva o sócio ao login do portal — com a contagem de clipes, nunca com o arquivo.
8. O visitante do portal encontra o vídeo na galeria da quadra; o sócio encontra os dele também em "meus vídeos".
9. Sete dias depois da gravação, `replay:prune` apaga arquivo e registro.
