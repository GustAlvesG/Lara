# Replay — resumo para quem vai mexer

Documentação completa (regras, e-mail, retenção): [docs/funcionalidades/replay.md](../funcionalidades/replay.md).
Contrato da API: [docs/replay-api.md](../replay-api.md).

## Em uma frase

Câmera + botão em cada quadra: um sistema **externo** grava os últimos segundos, corta e queima as
logomarcas; o Lara **manda nas regras** (formato, duração, layout), **recebe e guarda** os clipes
e **entrega ao sócio** (portal do site de locação + e-mail). Telas no menu **Replay**
(`replay.*`, permissão `replay` do catálogo).

## Caminho de um clipe

1. O sistema de captura lê a configuração: `GET /api/replay/cameras[/{external_id}]`
   (Sanctum, ability `replay:operate` — `ReplayAbilities::OPERATE`; token por `replay:token`).
   A resposta sai do `ReplayResolver`, o mesmo que a tela usa para mostrar o "efetivo".
2. Envia o clipe: `POST /api/replay/cameras/{external_id}/videos` → `VideoIntakeService`
   (idempotente pelo `external_id` do clipe; grava no disco `replay`).
3. Vínculo: reserva **paga** (`status_id = 1`) da quadra cobrindo o instante da gravação →
   amarra reserva e sócio. Sem reserva paga, o clipe fica só na galeria da quadra.
4. `SendReplayVideosMail`: **um** e-mail por reserva, 5 min após o fim, com link para o login do
   portal (nunca o vídeo). Trava: `replay_member_notifications.schedule_id` único.
5. `replay:prune` (03:30, `routes/console.php`) apaga o que passou de 7 dias **da gravação**.

## Peças

| Arquivo | Papel |
|---|---|
| `app/Services/Replay/ReplayResolver.php` | Herança quadra > esporte > padrão (horizontal, 30 s), para configuração e layout. |
| `app/Services/Replay/OverlayRenderer.php` | Compõe o overlay do layout (PNG; WebM animado se houver ffmpeg). |
| `app/Services/Replay/VideoIntakeService.php` | Recepção, vínculo com a reserva e expurgo. |
| `app/Services/Replay/MediaService.php` | Arquivos de mídia (logos, overlays, clipes). |
| `app/Models/Replay/*` | `Setting`, `Layout`, `LayoutItem`, `Camera`, `Video`, `MemberNotification`, `ApiClient`. |
| `app/Http/Controllers/Replay/Web/*` | As quatro telas (Configuração, Layouts, Câmeras, Vídeos). |
| `app/Http/Controllers/Replay/Api/*` | Captura (`Camera`, `Video`) e portal (`Portal`: galeria pública com `api_token`, "meus vídeos" com JWT). |
| `app/Support/Replay/Orientation.php` | Orientações e limites de duração (5–60 s). |

## Acesso e menu (depois do rebrand)

- Permissão do catálogo `App\Authorization\Permissions::REPLAY` (`replay`), grupo **Replay**.
  O `PermissionCatalogSeeder` a cria no deploy; ela nasce sem setor — TI alcança pelo acesso
  total, o Marketing recebe na tela de Setores.
- `manage replay` (Spatie, migration `2026_09_17_100700`) é **legado**: não decide nada. Está no
  `LegacyPermissionMap` só para o `acesso:diferenca`.
- Rotas web: `->middleware('can:' . P::REPLAY)`. Menu: grupo em `App\View\Navigation::links()`
  (área `placar`); as abas entre as quatro telas vêm da capa da área — não recrie abas na view.

## Regras que costumam pegar

- Layout é **por orientação**: trocar a orientação exige outro layout.
- Máx. 2 câmeras por quadra; `external_id` único no clube.
- Reserva pendente **não** vincula sócio.
- Nenhuma listagem pública revela o sócio da reserva.
- Sem ffmpeg o módulo funciona; só não há overlay animado nem `replay:demo-videos`.

## Testes

- `tests/Feature/Replay/*` (API, resolver, overlay, recepção, portal, comando de demonstração),
  com o schema mínimo de `tests/Concerns/CreatesReplaySchema.php` (sem `RefreshDatabase`).
- `tests/Feature/Rebrand/ReplayScreensTest.php`: as quatro telas no layout novo, abas da capa e
  menu condicionado à permissão.
- Confira o isolamento SQLite do `phpunit.xml` antes de rodar:
  `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Replay`.

## Comandos

`replay:token {nome}` (token da captura), `replay:prune` (agendado), `replay:demo-videos`
(só desenvolvimento; `--only-clear` remove o que ele criou).
