# RePET — Vídeos das Quadras

> **Nome:** no sistema (menu, títulos, catálogo de permissões e e-mail) o módulo se chama
> **RePET** desde 13/10/2026. No código ele continua `Replay`: namespaces, rotas `replay.*`,
> permissão `replay`, tabelas `replay_*` e a API `/api/replay`.

## O que é

Nas quadras há uma câmera e um botão. Quem aperta o botão guarda os **últimos segundos** de jogo. A captura, o corte e a queima das logomarcas são feitos por **outro sistema**, que grava o clipe no **servidor de arquivos (FTP)**, na pasta `Lara/Repet`; o Lara é quem **manda nas regras** (formato, duração, logomarcas), **registra e serve os vídeos** a partir do FTP e **entrega ao sócio**.

## Para quem

- **Marketing e TI** (painel web, permissão `replay` do catálogo de acesso): configuram formato, layouts de logomarca e câmeras; conferem e baixam os vídeos.
- **Sócio** (site de locação): recebe o e-mail e coleta os vídeos da reserva dele.
- **Visitante do site de locação**: vê a galeria de qualquer quadra.
- **Sistema de captura** (integração): consulta a configuração, grava os clipes no FTP e avisa o Lara do caminho de cada um.

## Pré-requisitos

- Permissão `replay` concedida ao setor na tela de **Setores** (ou à pessoa na tela de Usuários). TI alcança pelo acesso total; o Marketing precisa recebê-la. Ver [Usuários e Permissões](usuarios-e-permissoes.md).
- Câmera cadastrada com o **identificador** que o sistema de captura usa para o equipamento.
- `REPLAY_PORTAL_URL` no `.env` — é para onde o e-mail do sócio aponta.
- Pasta `Lara/Repet` no FTP, com a conta do Lara podendo ler e apagar nela (`REPLAY_FTP_ROOT`; servidor e conta são os do SIV, salvo `REPLAY_FTP_*`). Ver [docs/replay-api.md](../replay-api.md), seção 9.

---

## No menu

Grupo **RePET** (área do Placar), com as quatro telas como páginas, mais **Transmissões** (desde 06/11/2026, permissão própria). Na navegação por **Módulos**, a capa da área mostra como abas as que a pessoa alcança. O módulo não tem mais abas próprias.

## 1. Configuração de Vídeo

Tela: **RePET › Configuração de Vídeo**.

| Campo | Função |
|---|---|
| `orientation` | `vertical` (9:16) ou `horizontal` (16:9). Só a orientação — resolução e FPS ficam na câmera. |
| `clip_seconds` | Duração do clipe, de **5 a 60** segundos. Sempre os segundos **anteriores** ao aperto do botão; não há pós-roll. |

A configuração vale por **esporte** (PlaceGroup) ou por **quadra** (Place), e a herança é:

```
configuração da quadra  >  configuração do esporte  >  padrão do sistema (horizontal, 30s)
```

A tela mostra, em cada quadra, a configuração **efetiva** e de onde ela veio (`própria`, `herdado do esporte`, `padrão do sistema`). "Voltar a herdar" apaga a exceção da quadra — não desliga o RePET dela.

> Quem resolve a herança é o `ReplayResolver`, e é o **mesmo** que a API usa. É isso que garante que o que a tela mostra é o que a câmera recebe.

## 2. Layouts de Logomarca

Tela: **RePET › Layouts de Logomarca**.

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

Tela: **RePET › Câmeras**.

| Campo | Função |
|---|---|
| `external_id` | **Identificador do equipamento no sistema de captura.** É por ele que toda a API endereça a câmera. Pegue com quem instalou — inventar um aqui deixa a câmera gravando sem conseguir enviar. |
| `name`, `position` | Identificação para gente ler. `position` existe para campo de futebol ("Lado A"/"Lado B"). |
| `active` | Câmera inativa some da API. |
| `last_seen_at` | Último *heartbeat*. Vermelho depois de uma hora sem contato — é o que denuncia equipamento mudo antes de o sócio reclamar. |

Limite de **2 câmeras por quadra** (campo de futebol usa uma por metade).

A tela mostra também **como cada câmera está gravando** (orientação, duração e layout em vigor): é onde se responde "por que esta quadra está gravando em pé?" sem trocar de aba.

## 4. Vídeos

Tela: **RePET › Vídeos**.

Galeria com player, filtros (esporte, quadra, data, com/sem reserva), download e exclusão manual. Cada card mostra quantos dias faltam para o vídeo expirar.

A exclusão manual apaga o clipe **do FTP** e a cópia do Lara. Com o FTP fora do ar, nada é apagado: a tela avisa e é só tentar de novo.

## 5. Transmissões (ao vivo no YouTube)

Tela: **RePET › Transmissões** (`replay.broadcasts.index`), desde 06/11/2026. Permissão própria
`replay.transmissao` ("Transmissões ao vivo no YouTube: ligar, desligar e controlar o placar"),
separada da `replay`: ligar publica vídeo para o público, e quem opera o placar na quadra não
precisa mexer em câmera nem layout. Nasce sem setor.

Aqui o sentido da integração é o inverso dos clipes: **o Lara chama o servidor do rePET**
(`RepetClient`, `REPET_API_URL` + `REPET_API_TOKEN`, Bearer). O Lara não guarda estado nenhum: a
tela consulta `GET /status` do servidor a cada 3 s e mostra um cartão por quadra.

Dois modos, **excludentes na mesma quadra**:

- **Com placar** (`/broadcast/{q}/*`): o servidor desenha nomes das duplas, pontos e sets sobre o
  vídeo. Botões `+1 ponto`, `−1 ponto`, `+1 set` (zera os pontos dos dois lados; pede confirmação),
  `−1 set` (não mexe nos pontos). Em "Nomes, correção do placar e volume": trocar nomes, gravar o
  placar exato (campo vazio não muda), zerar e volume (0–400 %, 100 = original).
- **Sem placar / direta** (`/live/{q}`): a câmera empurra o vídeo ao YouTube sozinha. Só liga e desliga.

Regras do servidor que a tela reflete:

- Quadra sem stream key (`tem_stream_key: false`) não liga — os botões ficam desabilitados.
- Quadras que dividem a mesma stream key: com uma no ar, a outra não liga (aviso no cartão).
- No máximo 2 transmissões com placar ao mesmo tempo (o servidor recusa a 3ª com 409; a mensagem
  dele aparece no cartão).
- A transmissão desliga sozinha no prazo máximo do servidor (2 h por padrão); o cartão mostra
  "desliga sozinho em…". O servidor reinicia às 06:00 e relança as transmissões com placar.
- "Jogo novo" (padrão ao ligar) = `reset: true`: placar 0 × 0 **e volume 100 %**. Desmarcado,
  religa o mesmo jogo sem zerar.
- Nomes viram maiúsculas com até 14 caracteres no vídeo (normalização do servidor).

**Ponto, set e volume não são idempotentes.** O `RepetClient` nunca repete uma chamada; a tela
trava os botões da quadra enquanto uma ação está em curso e, depois de timeout, pede para conferir
o placar antes de repetir. Ligar e desligar vão para o log (`RePET: ligou/desligou a transmissão`,
com `quadra`, `modo` e `user_id`).

O `quadra_id` (ex.: `loc1-quadra1`) é a chave do servidor; o que a tela mostra é o `nome` — e os
dois **não batem** (em 06/11/2026, `loc1-quadra1` é a "Quadra 2"). Os cartões são ordenados pelo nome.

Fora do escopo, de propósito: o replay manual (`POST /replay/{q}`) e a exclusão de clipe no servidor.

Nenhuma mudança na aplicação externa: o Lara só consome a API do rePET como ela está.

---

## Onde os vídeos ficam

O **original** de cada clipe fica no FTP, onde o sistema de captura gravou. Como o FTP não serve
vídeo para navegador, o Lara guarda uma **cópia** em `public/storage/replay/videos`, feita no
momento em que o clipe é avisado (é o mesmo download que confere se o arquivo é MP4 ou WebM). É
essa cópia que a tela e o site de locação tocam.

Se a cópia sumir, o link do vídeo passa por `/replay/arquivo/{uuid}`, que traz o clipe de novo
do FTP e segue para a cópia. Com o FTP fora do ar, esse link dá erro até o servidor voltar; os
vídeos que já têm cópia continuam tocando normalmente.

Os vídeos recebidos antes de 13/10/2026, que tinham chegado por upload, foram descartados na
troca para o FTP (migration `2026_10_13_100100`).

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

**7 dias contados da gravação**, sem exceção. O comando `replay:prune` roda às 03:30 e apaga o clipe no FTP, a cópia e o registro — arquivo primeiro, registro depois, porque registro órfão alguém vê e resolve, arquivo órfão ocupa disco em silêncio. Pelo mesmo motivo, com o FTP fora do ar o expurgo para, sai com falha e deixa o resto para a noite seguinte.

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

Os clipes de demonstração **não vão ao FTP** (é o mesmo servidor das câmeras de verdade): existem
só como cópia local, e o `file_path` deles (`demo-videos/...`) não existe lá — o expurgo os
conta como "já sem arquivo no FTP" e segue.

## Regras de negócio, em uma lista

1. Duração entre 5 e 60 segundos, sempre anteriores ao botão.
2. Quadra vence esporte; sem nenhum dos dois, vale horizontal/30s.
3. Layout é por orientação; trocar a orientação de um layout exige criar outro.
4. Máximo de 2 câmeras por quadra; `external_id` é único em todo o clube.
5. Só reserva paga vincula sócio.
6. Um e-mail por reserva, com link para o login do portal.
7. Reenvio do mesmo `external_id` de clipe, ou do mesmo caminho no FTP, não duplica nada.
8. Expiração conta da gravação, não do envio.
9. A galeria da quadra é pública; "meus vídeos" exige login.
10. Nenhuma listagem pública revela quem é o sócio de uma reserva.

## Integrações

- **Sistema de captura das quadras** — pull, Sanctum, ability `replay:operate`. Contrato completo em [docs/replay-api.md](../replay-api.md).
- **Servidor de arquivos (FTP)** — disco `replay_ftp`, pasta `Lara/Repet`: o original dos clipes.
- **Site de locação de espaços** — galeria pública com `api_token`; área do sócio com o JWT de sempre.
- **E-mail** — `EmailService` + `ContactMail` (tipo `replay.available`).
- **Servidor do rePET (transmissão ao vivo)** — o Lara chama, Bearer `REPET_API_TOKEN`. Rotas usadas: `/status`, `/broadcast/*`, `/live/*`. Contrato vivo em `{REPET_API_URL}/openapi.json`.

## Referência técnica

- Models: `App\Models\Replay\{Setting, Layout, LayoutItem, Camera, Video, MemberNotification, ApiClient}`
- Services: `ReplayResolver` (herança), `OverlayRenderer` (composição), `MediaService` (mídia), `VideoIntakeService` (recepção e expurgo), `RemoteVideoStore` (FTP e cópia local), `RepetClient` (transmissão ao vivo)
- Controllers: `App\Http\Controllers\Replay\{Web,Api}` (inclui `Web\BroadcastController`, a aba Transmissões) e `Replay\VideoFileController` (refaz a cópia)
- Comandos: `replay:token`, `replay:prune`, `replay:demo-videos` (só desenvolvimento)
- Job: `SendReplayVideosMail`
- Testes: `tests/Feature/Replay` (transmissões: `ReplayBroadcastTest`, com `Http::fake` — nenhum teste fala com o servidor real)
