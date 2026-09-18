# Replay — Vídeos das Quadras

## O que é

Nas quadras há uma câmera e um botão. Quem aperta o botão guarda os **últimos segundos** de jogo. A captura, o corte e a queima das logomarcas são feitos por **outro sistema**; o Lara é quem **manda nas regras** (formato, duração, logomarcas), **guarda os vídeos** e **entrega ao sócio**.

## Para quem

- **Marketing e TI** (painel web, permissão `manage replay`): configuram formato, layouts de logomarca e câmeras; conferem e baixam os vídeos.
- **Sócio** (site de locação): recebe o e-mail e coleta os vídeos da reserva dele.
- **Visitante do site de locação**: vê a galeria de qualquer quadra.
- **Sistema de captura** (integração): consulta a configuração e envia os clipes.

## Pré-requisitos

- Permissão `manage replay` concedida na tela de Permissões (a migration a cria e concede só ao `admin`).
- Câmera cadastrada com o **identificador** que o sistema de captura usa para o equipamento.
- `REPLAY_PORTAL_URL` no `.env` — é para onde o e-mail do sócio aponta.
- Limite de upload do servidor em 256M (ver [docs/replay-api.md](../replay-api.md), seção 9).

---

## 1. Configuração de Vídeo

Tela: **Replay › Configuração de Vídeo**.

| Campo | Função |
|---|---|
| `orientation` | `vertical` (9:16) ou `horizontal` (16:9). Só a orientação — resolução e FPS ficam na câmera. |
| `clip_seconds` | Duração do clipe, de **5 a 60** segundos. Sempre os segundos **anteriores** ao aperto do botão; não há pós-roll. |

A configuração vale por **esporte** (PlaceGroup) ou por **quadra** (Place), e a herança é:

```
configuração da quadra  >  configuração do esporte  >  padrão do sistema (horizontal, 30s)
```

A tela mostra, em cada quadra, a configuração **efetiva** e de onde ela veio (`própria`, `herdado do esporte`, `padrão do sistema`). "Voltar a herdar" apaga a exceção da quadra — não desliga o Replay dela.

> Quem resolve a herança é o `ReplayResolver`, e é o **mesmo** que a API usa. É isso que garante que o que a tela mostra é o que a câmera recebe.

## 2. Layouts de Logomarca

Tela: **Replay › Layouts de Logomarca**.

Um layout pertence a um esporte **ou** a uma quadra, e vale para **uma orientação** — um desenho pensado para 9:16 não serve para 16:9. A herança é a mesma da configuração.

Fluxo:

1. Criar o layout (nome, dono, orientação).
2. Enviar as logomarcas (**PNG ou GIF**, até 8MB cada, quantas quiser).
3. Arrastar e redimensionar sobre o preview; ajustar opacidade e ordem no painel lateral.
4. **Salvar posições** — o Lara compõe o overlay na hora.

O que é publicado:

- Um **PNG transparente** do tamanho cheio do frame (1080×1920 ou 1920×1080), sempre.
- Um **WebM com canal alpha**, quando houver GIF animado no layout **e** ffmpeg no servidor.

O sistema de captura aplica o arquivo em (0,0), sem calcular nada. As posições são guardadas em **porcentagem do frame**, então o layout continua correto se a câmera mudar de resolução.

> Sem ffmpeg instalado, a tela avisa: o GIF vai sair **parado** (primeiro quadro) e o PNG continua sendo gerado normalmente.

## 3. Câmeras

Tela: **Replay › Câmeras**.

| Campo | Função |
|---|---|
| `external_id` | **Identificador do equipamento no sistema de captura.** É por ele que toda a API endereça a câmera. Pegue com quem instalou — inventar um aqui deixa a câmera gravando sem conseguir enviar. |
| `name`, `position` | Identificação para gente ler. `position` existe para campo de futebol ("Lado A"/"Lado B"). |
| `active` | Câmera inativa some da API. |
| `last_seen_at` | Último *heartbeat*. Vermelho depois de uma hora sem contato — é o que denuncia equipamento mudo antes de o sócio reclamar. |

Limite de **2 câmeras por quadra** (campo de futebol usa uma por metade).

A tela mostra também **como cada câmera está gravando** (orientação, duração e layout em vigor): é onde se responde "por que esta quadra está gravando em pé?" sem trocar de aba.

## 4. Vídeos

Tela: **Replay › Vídeos**.

Galeria com player, filtros (esporte, quadra, data, com/sem reserva), download e exclusão manual. Cada card mostra quantos dias faltam para o vídeo expirar.

---

## Vínculo com o sócio

Quando um clipe chega, o Lara procura uma reserva **paga** (`status_id = 1`) daquela quadra que cobria o instante da gravação:

- **Achou** → o vídeo é amarrado à reserva e ao sócio. Ele aparece em "meus vídeos" no portal e gera o e-mail.
- **Não achou** (quadra livre, aula, torneio, reserva pendente ou cancelada) → o vídeo existe do mesmo jeito e fica só na galeria da quadra.

Reserva **pendente de pagamento não vincula**: entregar o vídeo de um jogo a quem não pagou é o erro que não se pode cometer nessa direção.

## O e-mail

**Um e-mail por reserva**, disparado 5 minutos depois do fim dela — nunca um por clipe: uma hora de quadra rende dezenas de apertos no botão.

O e-mail **não leva o vídeo nem link direto**. Leva a contagem de clipes e um botão para o **login do site de locação**. A trava que garante o aviso único é a tabela `replay_member_notifications`, com `unique` em `schedule_id`.

Se o sócio não tiver e-mail válido, o envio é registrado no log e o fluxo segue — o vídeo continua no portal do mesmo jeito.

## Retenção

**7 dias contados da gravação**, sem exceção. O comando `replay:prune` roda às 03:30 e apaga arquivo e registro — arquivo primeiro, registro depois, porque registro órfão alguém vê e resolve, arquivo órfão ocupa disco em silêncio.

Material para campanha deve ser **baixado antes do prazo**.

## Dados de demonstração (desenvolvimento)

Para desenvolver o site de locação sem depender das câmeras nem de alguém apertar o botão:

```
php artisan replay:demo-videos              # 2 clipes por quadra
php artisan replay:demo-videos --per-place=4 --place=12 --seconds=20
php artisan replay:demo-videos --only-clear # remove tudo que ele criou
```

Gera clipes com o ffmpeg (padrão de barras, matiz diferente por quadra), **respeitando a
orientação configurada** de cada uma — é o que faz o site ser desenvolvido contra os formatos
que existem de verdade no cadastro. As gravações ficam espalhadas em dias diferentes, para o
filtro de data ter o que filtrar.

O que ele **não** faz, de propósito: não dispara e-mail (um seed que avisasse sócio de verdade
por causa de dado de teste seria um problema) e não cria nem altera reserva. Se já existir uma
reserva paga cobrindo o horário sorteado, o vídeo é amarrado a ela — mesma regra da produção.

Todo clipe nasce com `external_id` prefixado por `demo-`, e é só isso que o `--only-clear`
alcança: ele nunca toca em um vídeo vindo da câmera. Em produção o comando exige `--force`.

## Regras de negócio, em uma lista

1. Duração entre 5 e 60 segundos, sempre anteriores ao botão.
2. Quadra vence esporte; sem nenhum dos dois, vale horizontal/30s.
3. Layout é por orientação; trocar a orientação de um layout exige criar outro.
4. Máximo de 2 câmeras por quadra; `external_id` é único em todo o clube.
5. Só reserva paga vincula sócio.
6. Um e-mail por reserva, com link para o login do portal.
7. Reenvio do mesmo `external_id` de clipe não duplica nada.
8. Expiração conta da gravação, não do envio.
9. A galeria da quadra é pública; "meus vídeos" exige login.
10. Nenhuma listagem pública revela quem é o sócio de uma reserva.

## Integrações

- **Sistema de captura das quadras** — pull, Sanctum, ability `replay:operate`. Contrato completo em [docs/replay-api.md](../replay-api.md).
- **Site de locação de espaços** — galeria pública com `api_token`; área do sócio com o JWT de sempre.
- **E-mail** — `EmailService` + `ContactMail` (tipo `replay.available`).

## Referência técnica

- Models: `App\Models\Replay\{Setting, Layout, LayoutItem, Camera, Video, MemberNotification, ApiClient}`
- Services: `ReplayResolver` (herança), `OverlayRenderer` (composição), `MediaService` (mídia), `VideoIntakeService` (recepção e expurgo)
- Controllers: `App\Http\Controllers\Replay\{Web,Api}`
- Comandos: `replay:token`, `replay:prune`, `replay:demo-videos` (só desenvolvimento)
- Job: `SendReplayVideosMail`
- Testes: `tests/Feature/Replay`
