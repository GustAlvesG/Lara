# RePET — resumo para quem vai mexer

**Nome:** no menu, nos títulos, no catálogo de permissões e no e-mail o módulo é **RePET**
(desde 13/10/2026). No código continua **Replay** — namespaces `App\...\Replay`, rotas
`replay.*`, permissão `replay`, tabelas `replay_*`, API `/api/replay`. Não renomeie o código:
a API é contrato com o sistema de captura e com o site de locação.

Documentação completa (regras, e-mail, retenção): [docs/funcionalidades/replay.md](../funcionalidades/replay.md).
Contrato da API: [docs/replay-api.md](../replay-api.md).

## Em uma frase

Câmera + botão em cada quadra: um sistema **externo** grava os últimos segundos, corta, queima as
logomarcas e **grava o clipe no FTP** (`Lara/Repet`); o Lara **manda nas regras** (formato,
duração, layout), **registra o clipe pelo caminho**, guarda uma cópia para servir e **entrega ao
sócio** (portal do site de locação + e-mail). Telas no menu **RePET** (`replay.*`, permissão
`replay` do catálogo).

## Caminho de um clipe

1. O sistema de captura lê a configuração: `GET /api/replay/cameras[/{external_id}]`
   (Sanctum, ability `replay:operate` — `ReplayAbilities::OPERATE`; token por `replay:token`).
   A resposta sai do `ReplayResolver`, o mesmo que a tela usa para mostrar o "efetivo".
2. Grava o clipe no FTP e avisa: `POST /api/replay/cameras/{external_id}/videos` com `path`
   (sem arquivo) → `VideoIntakeService`. Idempotente pelo `external_id` **e** pelo `path`.
   O `RemoteVideoStore` normaliza o caminho (com ou sem `Lara/Repet/` na frente; recusa `..`),
   baixa o clipe para o disco `replay`, confere o tipo pelos bytes e a cópia fica como cache.
   FTP fora → `503`; arquivo ausente/formato errado → `422`.
3. Vínculo: reserva **paga** (`status_id = 1`) da quadra cobrindo o instante da gravação →
   amarra reserva e sócio. Sem reserva paga, o clipe fica só na galeria da quadra.
4. `SendReplayVideosMail`: **um** e-mail por reserva, 5 min após o fim, com link para o login do
   portal (nunca o vídeo). Trava: `replay_member_notifications.schedule_id` único.
5. `replay:prune` (03:30, `routes/console.php`) apaga do FTP, a cópia e o registro do que passou
   de 7 dias **da gravação**. FTP fora → para e sai com falha; o resto fica para a noite seguinte.

## Onde mora o arquivo

- `replay_videos.file_path` = caminho **no FTP**, relativo a `REPLAY_FTP_ROOT` (disco `replay_ftp`).
- `replay_videos.local_path` = cópia em `public/storage/replay/videos/AAAA/MM/{uuid}.ext`
  (disco `replay`), servida estática (range request).
- `Video::url()`: cópia presente → URL estática; ausente → rota pública
  `replay.videos.file` (`/replay/arquivo/{uuid}`, `VideoFileController`), que refaz a cópia do
  FTP e redireciona. Vencido → 404.
- O FTP marcado como fora do ar fica em cache por 60 s (`replay:ftp-offline`), como no SIV.
- Disco `replay_ftp`: mesmo servidor/conta das `FTP_*` do SIV, salvo `REPLAY_FTP_*`.
  A pasta raiz precisa existir; a conta precisa ler e apagar.

## Peças

| Arquivo | Papel |
|---|---|
| `app/Services/Replay/ReplayResolver.php` | Herança quadra > esporte > padrão (horizontal, 30 s), para configuração e layout. |
| `app/Services/Replay/OverlayRenderer.php` | Compõe o overlay do layout (PNG; WebM animado se houver ffmpeg). |
| `app/Services/Replay/VideoIntakeService.php` | Recepção pelo caminho, vínculo com a reserva e expurgo. |
| `app/Services/Replay/RemoteVideoStore.php` | FTP dos clipes: normaliza caminho, baixa para a cópia, apaga; traduz falha em `RemoteStorageUnavailable`. |
| `app/Services/Replay/MediaService.php` | Arquivos de mídia locais (logos, overlays, cópia dos clipes). |
| `app/Models/Replay/*` | `Setting`, `Layout`, `LayoutItem`, `Camera`, `Video`, `MemberNotification`, `ApiClient`. |
| `app/Http/Controllers/Replay/Web/*` | As quatro telas (Configuração, Layouts, Câmeras, Vídeos). |
| `app/Http/Controllers/Replay/Api/*` | Captura (`Camera`, `Video`) e portal (`Portal`: galeria pública com `api_token`, "meus vídeos" com JWT). |
| `app/Http/Controllers/Replay/VideoFileController.php` | Rota pública que refaz a cópia local a partir do FTP. |
| `app/Support/Replay/Orientation.php` | Orientações e limites de duração (5–60 s). |

## Acesso e menu (depois do rebrand)

- Permissão do catálogo `App\Authorization\Permissions::REPLAY` (`replay`), grupo **RePET**.
  O `PermissionCatalogSeeder` a cria no deploy; ela nasce sem setor — TI alcança pelo acesso
  total, o Marketing recebe na tela de Setores.
- `manage replay` (Spatie, migration `2026_09_17_100700`) é **legado**: não decide nada. Está no
  `LegacyPermissionMap` só para o `acesso:diferenca`.
- Rotas web: `->middleware('can:' . P::REPLAY)`. Menu: grupo em `App\View\Navigation::links()`
  (área `placar`); as abas entre as quatro telas vêm da capa da área — não recrie abas na view.
- A rota `replay.videos.file` é a exceção: pública (fora do grupo `auth`), porque a galeria do
  site de locação aponta para ela.

## Regras que costumam pegar

- Layout é **por orientação**: trocar a orientação exige outro layout.
- Máx. 2 câmeras por quadra; `external_id` único no clube.
- Reserva pendente **não** vincula sócio.
- Nenhuma listagem pública revela o sócio da reserva.
- Sem ffmpeg o módulo funciona; só não há overlay animado nem `replay:demo-videos`.
- Depois do `201`, o arquivo no FTP é do Lara: a captura não apaga nem move.
- Exclusão manual e expurgo nunca apagam o registro se o FTP não respondeu (arquivo primeiro).
- Os vídeos anteriores ao FTP (sem `local_path`) foram descartados pela migration
  `2026_10_13_100100` — decisão de quem opera.

## Testes

- `tests/Feature/Replay/*` (API, resolver, overlay, recepção pelo FTP, portal, comando de
  demonstração), com o schema mínimo de `tests/Concerns/CreatesReplaySchema.php` (sem
  `RefreshDatabase`). O FTP é `Storage::fake('replay_ftp')`; FTP fora do ar é um mock de disco
  que lança `UnableToConnectToFtpHost` (`Storage::set`).
- `tests/Feature/Rebrand/ReplayScreensTest.php`: as quatro telas no layout novo, abas da capa e
  menu condicionado à permissão.
- Confira o isolamento SQLite do `phpunit.xml` antes de rodar:
  `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Replay`.

## Comandos

`replay:token {nome}` (token da captura), `replay:prune` (agendado), `replay:demo-videos`
(só desenvolvimento; gera só a cópia local, nunca grava no FTP; `--only-clear` remove o que ele
criou).
