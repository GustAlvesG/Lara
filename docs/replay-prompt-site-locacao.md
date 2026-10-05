# Prompt para o agent do site de locação de espaços

> Cole o conteúdo abaixo (da linha `---` em diante) em um Claude Code aberto no repositório do
> **site de locação de espaços**. Ele descreve a API do Replay no Lara e as telas a construir.

---

Você vai construir, neste site, a área de **Replay**: os vídeos gravados nas quadras do clube.
O back-end já está pronto no Lara (a mesma API que este site já consome para espaços,
horários e reservas). Sua tarefa é a interface e a integração — **nenhuma regra de vídeo é
decidida aqui**.

## Contexto de negócio

Nas quadras há uma câmera e um botão. Quem aperta guarda os últimos segundos de jogo. Esses
clipes vão para o Lara, que os mantém por **7 dias** e depois apaga.

Há dois caminhos até o vídeo, e eles têm regras de acesso diferentes:

1. **Galeria da quadra** — aberta a **qualquer visitante**, sem login. Mostra todos os vídeos
   de uma quadra, inclusive os gravados durante reservas. Decisão de quem opera: o replay é do
   jogo, e o jogo aconteceu em espaço coletivo.
2. **Meus vídeos** — área do sócio logado, com os clipes gravados durante as reservas **pagas**
   dele. É o destino do link que ele recebe por e-mail.

Um mesmo vídeo pode estar nos dois lugares. A galeria pública marca que o vídeo pertence a uma
reserva, mas **nunca diz de quem** — e a sua interface também não pode inferir isso.

## Autenticação

Nada novo:

- **Galeria pública**: o mesmo `Authorization: Bearer <API_TOKEN>` que este site já usa nos
  endpoints do Lara. Reaproveite o cliente HTTP existente.
- **Meus vídeos**: além do token da integração, o header `Session` com o JWT do sócio — o mesmo
  já usado em `/api/schedule/...`.

## Endpoints

Base: `<host do Lara>/api/replay`

| Método | Rota | Para quê |
|---|---|---|
| GET | `/places` | Quadras que **têm vídeo disponível agora** |
| GET | `/places/{place}/videos?date=YYYY-MM-DD&page=N` | Galeria de uma quadra, paginada (24 por página) |
| GET | `/my-videos` | Vídeos do sócio logado (exige `Session`) |

Cada vídeo vem assim:

```jsonc
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
```

O contrato completo está em `docs/replay-api.md` no repositório do Lara. Peça o arquivo.

## Dados para desenvolver

Peça a quem mantém o Lara que rode `php artisan replay:demo-videos` no ambiente que você vai
consumir. Isso popula a galeria com 2 clipes por quadra, em orientações e datas variadas — o
suficiente para montar as telas sem esperar câmera nenhuma. Os clipes de demonstração são
removíveis com `--only-clear` e não disparam e-mail para ninguém.

## Telas a construir

### 1. Galeria pública — lista de quadras

Entrada da área de Replay. Consome `GET /places`. Mostre nome da quadra, esporte,
`videos_count` e quando foi o último vídeo (`last_recorded_at`).

Só vêm quadras **com vídeo disponível** — não invente uma lista completa de quadras a partir de
outro endpoint, ou o visitante vai clicar em quadras vazias.

### 2. Galeria pública — vídeos da quadra

Consome `GET /places/{place}/videos`. Grade de cards com player, data/hora da gravação, duração
e botão de download.

- Filtro por **data** (`?date=`), que é como as pessoas procuram: "o jogo de terça".
- Paginação ou rolagem infinita usando `meta.current_page` / `meta.last_page`.
- Use `orientation` para dar ao player a proporção certa (9:16 ou 16:9). Um vídeo vertical
  dentro de uma moldura 16:9 fica com tarjas pretas enormes.
- `has_member: true` pode virar um selo discreto ("vídeo de reserva"). **Não** transforme isso
  em nome, iniciais ou qualquer pista de quem é o sócio — essa informação não vem na API, e é
  de propósito.

### 3. Meus vídeos (sócio logado)

Consome `GET /my-videos`. Mesma grade, sem filtro de quadra (já vem só o dele), ordenada do mais
recente para o mais antigo. Mostre `days_left` com destaque quando for pequeno.

É para cá que o e-mail manda o sócio: ele chega deslogado, faz login e cai nesta tela. Garanta
que o fluxo "link do e-mail → login → meus vídeos" funcione sem passos extras.

## Sobre os arquivos de vídeo

A `url` é um **arquivo estático**, servido direto pelo servidor do Lara. Isso significa:

- Use a URL direto no `<video src="...">`. **Não** faça proxy nem baixe o arquivo no seu
  back-end: o arquivo estático suporta *range request*, e é isso que permite ao visitante
  avançar o vídeo em vez de assistir do começo.
- Download é um `<a href="{url}" download>` simples.
- `preload="none"` nos cards da grade. São 24 vídeos por página; deixar o navegador buscar
  todos puxaria centenas de MB à toa.

## Os 7 dias

Todo vídeo expira **7 dias depois da gravação** (não do envio), e o arquivo é apagado. Para o
site isso significa:

- **Não guarde a `url` em banco, cache longo ou favoritos.** Ela vira 404.
- Mostre `days_left` ou `expires_at` em toda listagem — quem quiser guardar precisa baixar
  antes.
- Trate 404 no player como "este vídeo expirou", não como erro do site.

## O que NÃO fazer

- Não exibir nome, CPF ou qualquer identificação de sócio na galeria pública.
- Não fazer proxy do arquivo de vídeo pelo seu servidor.
- Não guardar URLs de vídeo de forma persistente.
- Não montar a lista de quadras a partir de outro endpoint que não o `/replay/places`.
- Não pedir login para ver a galeria da quadra — ela é aberta por decisão de negócio.

## Entregue no fim

- As três telas acima, responsivas (o sócio abre isso no celular, na beira da quadra).
- Tratamento explícito dos estados: sem vídeos, vídeo expirado, sessão inválida em "meus
  vídeos".
