# Prompt: integração do placar eletrônico (Node) com a API do Placar Clube

> Pacote autocontido — cole isto inteiro na sessão do Claude Code que trabalha no
> repositório do placar (Node/Express/Socket.IO). Não depende de acesso ao repositório
> Laravel: toda a referência necessária (o que mudou, modelo de dados, contrato da API)
> está reproduzida abaixo, não só linkada.
>
> **Se o Node já consome esta API, comece pela seção 1 — há mudanças que quebram o
> que está no ar hoje.**

---

## 1. O que mudou agora — ações necessárias no Node

Oito mudanças no backend. As marcadas ⚠️ exigem alteração no Node **antes** do próximo
jogo; as demais são correções e novidades que o Node passa a poder usar.

### 1.1 `cronometro_ms` virou obrigatório em `ponto` e `falta` ⚠️ QUEBRA

**O que mudou:** o scout deixou de medir artilharia (ranking entre jogos) e passou a
medir **atuação numa partida específica**. Para isso, todo `ponto` e toda `falta`
precisam carregar o minuto do jogo em que aconteceram.

**Efeito no Node:** um `ponto`/`falta` enviado sem `cronometro_ms` agora volta em
`rejeitados`, com o motivo `"'ponto' exige cronometro_ms (minutagem na partida)"`. O
lote inteiro **não** é derrubado — os outros eventos entram normalmente —, mas o lance
rejeitado **não é gravado** e some do placar e da súmula.

**O que fazer:**

1. Garanta que o cronômetro da partida esteja disponível no momento em que o operador
   registra o lance, e envie-o em `cronometro_ms` (inteiro, **milissegundos**, ≥ 0).
2. É o tempo do **cronômetro do jogo**, não o relógio de parede nem o tempo desde o
   início da requisição. Se o cronômetro está pausado, use o valor congelado.
3. Trate `rejeitados` na UI. Se o Node hoje ignora esse campo, este é o momento de
   parar de ignorar: um ponto rejeitado silenciosamente vira divergência de placar
   que só aparece no fim do jogo.
4. Os demais tipos (`crono_play`, `crono_pause`, `timeout`, `substituicao`,
   `inicio_jogo`, …) **continuam sem exigir** o campo.

```js
// antes
{ uuid, sequencia, tipo: 'ponto', time_id, jogador_id, valor: 1, periodo: 1, ocorrido_em }

// agora
{ uuid, sequencia, tipo: 'ponto', time_id, jogador_id, valor: 1, periodo: 1,
  cronometro_ms: 754000,   // 12min34s de partida — OBRIGATÓRIO em ponto e falta
  ocorrido_em }
```

### 1.2 `GET /scout/artilharia` foi removido ⚠️ QUEBRA

**O que mudou:** o endpoint não existe mais (responde `404`). Ranking de pontos
agregado entre jogos deixou de ser o eixo do scout.

**O que fazer:** se o Node chamava esse endpoint (ex.: para um painel de destaques),
remova a chamada. Os substitutos, ambos por partida:

- `GET /placar/jogos/{jogo}/jogadores/{jogador}/atuacao` — ficha minutada do jogador
  naquela partida.
- `GET /placar/scout/jogadores/{jogador}` — as partidas em que ele atuou (mudou de
  formato: era perfil agregado com média, agora é lista de partidas).

### 1.3 As URLs de mídia agora funcionam ✅ CORREÇÃO

**O que mudou:** `logo_url` e `foto_url` estavam apontando para um caminho que o
servidor web não alcançava — **todo link de imagem do Placar estava quebrado**. A
mídia passou a ser gravada e servida em `public/storage/placar/…`, como arquivo
estático.

**O que fazer:** provavelmente nada além de **remover qualquer gambiarra** que o Node
tenha criado para contornar isso (reescrita de URL, fallback fixo, imagem
placeholder). Continue usando `logo_url`/`foto_url`/`video_url` exatamente como vêm no
payload: são sempre URLs absolutas, ou `null`.

Se alguma imagem antiga ainda não aparecer, é porque o ambiente precisa rodar uma vez
`php artisan placar:migrar-midia` no lado Laravel — peça para quem administra.

### 1.4 Vídeo do jogador ✨ NOVO

**O que mudou:** o jogador agora pode ter um vídeo curto de apresentação, além da
foto. O payload do jogo traz **`video_url`** ao lado de `foto_url`, no elenco de cada
time — sem chamada extra.

**O que fazer:** use `foto_url` e `video_url` nos momentos que o telão pedir (a ideia é
foto na escalação/súmula, vídeo na entrada em quadra). `video_url` é `null` quando o
jogador não tem vídeo — **sempre trate esse caso**, a maioria não terá.

O arquivo é servido estaticamente, com *range request* nativo: dá para dar seek
normalmente num `<video>`, sem baixar tudo antes.

### 1.5 Jogador agora tem equipe e modalidade ⚠️ QUEBRA (se cria jogador sem `time_id`)

**O que mudou:** o jogador passou a pertencer a **uma equipe e uma modalidade**. Ele pode
estar em vários times daquela equipe (Sub-15 e Adulto), mas nunca em time de outra equipe
ou modalidade.

**Efeito no Node, em `POST /placar/jogadores`:**

- **Com `time_id`** (o caminho normal em campo): nada muda. Equipe e modalidade são
  herdadas do time automaticamente.
- **Sem `time_id`**: `equipe_id` e `modalidade` viraram **obrigatórios**. Sem eles, `422`.
- Mandar `equipe_id`/`modalidade` que **divirjam** do `time_id` agora é `422`, em vez de
  a API escolher um dos dois em silêncio.

```js
// continua funcionando — equipe e modalidade vêm do time
{ nome: 'Carlos', time_id: 12, numero: '10' }

// antes funcionava, agora dá 422
{ nome: 'Carlos' }

// forma correta sem time
{ nome: 'Carlos', equipe_id: 3, modalidade: 'futsal' }
```

Se o Node cria jogador avulso sem saber o time (ex.: cadastro rápido antes de montar o
elenco), passe a coletar a equipe na UI — ou crie o time primeiro e use `time_id`.

### 1.6 `documento` saiu do jogador; entrou `idade` ⚠️ QUEBRA (se o Node lê esse campo)

**O que mudou:** o cadastro do jogador guarda como dado pessoal apenas **nome e data de
nascimento**. A coluna `documento` foi apagada do banco, sumiu do cadastro web e da
planilha de importação, e **não vem mais no payload** do jogador. No lugar dela, o
Resource passou a devolver `idade` (anos completos, ou `null` se a data de nascimento não
foi informada) — já calculada no servidor.

```js
// antes
{ id: 1, nome: 'Carlos Souza', data_nascimento: '2004-03-15', documento: 'MG1234567', ... }

// agora
{ id: 1, nome: 'Carlos Souza', data_nascimento: '2004-03-15', idade: 22, ... }
```

**O que fazer:** remova qualquer leitura de `documento` (ela virá `undefined`). Se alguma
tela do telão mostra idade, use `idade` direto em vez de calcular a partir de
`data_nascimento`. `POST /jogadores` nunca aceitou `documento`, então nada muda no envio.

### 1.7 Súmula por time ✨ NOVO

**O que mudou:** `GET /jogos/{jogo}/sumula` passou a aceitar **`?time_id=`**. Sem ele, nada
muda — a resposta é a súmula completa de sempre, agora com `recorte: null`. Com ele, a
**mesma** súmula recortada naquele time, que é o que cada equipe leva embora:

```js
const completa = await api.get(`/jogos/${jogoId}/sumula`)
const daCasa   = await api.get(`/jogos/${jogoId}/sumula`, { params: { time_id: timeCasaId } })

daCasa.recorte            // { time_id: 7, lado: 'casa', nome_exibicao: 'CF Adulto' }
daCasa.eventos            // só os lances desse time + os marcos sem time
daCasa.totais_por_jogador // { time_casa: [...], time_fora: [] }
daCasa.placar_por_periodo // completo — é o placar da partida, não do recorte
```

Detalhes que importam: os **marcos sem time** (`inicio_jogo`, `fim_jogo`, `periodo`, `set`)
continuam na linha recortada, senão ela perde a referência de quando cada coisa aconteceu;
a chave do lado excluído continua existindo em `totais_por_jogador`, vazia, então não é
preciso testar se ela existe; e `estornado` é apurado antes do recorte, então um lance
revertido não volta a valer na súmula individual. `time_id` de um time que não joga a
partida responde **`422`**, nunca a súmula completa em silêncio.

**O que fazer:** nada é obrigatório. Se o Node exporta/exibe súmula, passe a oferecer as
três saídas (completa, mandante, visitante) — é exatamente o que a tela web do Laravel faz.

### 1.8 Categoria e criação de partida 📋 REGRA NOVA

Se o Node cria times/jogos em campo (modo avulso):

- **Categoria é normalizada no servidor.** Mandar `"Sub 15"` quando já existe
  `"Sub-15"` agora cai no time existente em vez de criar um duplicado. A resposta é
  `200` (reaproveitou) em vez de `201` (criou) — **não trate `200` como erro**.
- **Partida só entre times da mesma categoria.** Criar jogo Sub-15 × Adulto agora
  responde `422` com erro em `time_fora_id`. Se a UI do Node deixa escolher os dois
  times livremente, filtre pela categoria do primeiro escolhido.

---

## 2. Contexto e responsabilidade

Você está trabalhando no placar eletrônico ao vivo — Node.js + Express + Socket.IO. Este
projeto **não tem estado de negócio próprio**: quem é a fonte de verdade sobre equipes,
times, jogadores, jogos e o histórico de eventos é uma API Laravel que já existe, pronta,
testada e documentada, rodando dentro de outro repositório (`lpr-app`), módulo "Placar
Clube".

Sua responsabilidade é fazer este app consumir e operar essa API corretamente — nunca
duplicar essas regras em memória do Node além do necessário para a tela ao vivo, e nunca
inventar um formato de payload diferente do que a API espera.

---

## 3. Modelo de dados (referência)

### Modalidade
- **Tabela:** `modalidades` — referência fixa (3 linhas)
- `slug` (`futsal`/`basquete`/`volei`) é o que o Node usa no campo `esporte` do gameState.

### Equipe
- **Tabela:** `equipes` · **`$fillable`:** `nome`, `nome_curto`, `logo_path`, `cidade`, `criado_em_campo`, `ativo`
- `logoUrl()` resolve a URL absoluta da logo própria (ou `null`) — é o fallback de `Time::logoUrl()`.

### Time
- **Tabela:** `times` · `UNIQUE (equipe_id, modalidade_id, categoria)`
- **`$fillable`:** `equipe_id`, `modalidade_id`, `categoria`, `nome_exibicao`, `logo_path`, `criado_em_campo`, `ativo`
- Recorte de uma equipe por modalidade + categoria — não confundir com Equipe. Um mesmo
  jogador pode estar em times diferentes da mesma equipe (ex.: Sub-17 → Adulto), com número
  de camisa próprio em cada um (ver Elenco).
- `categoria` é normalizada no servidor; um jogo só existe entre times da mesma categoria.
- `nomeExibicaoResolvido()`: se `nome_exibicao` for nulo, monta a partir de
  `equipe.nome_curto ?: equipe.nome` + `categoria`. `logoUrl()`: própria, senão herda da equipe.

### Jogador
- **Tabela:** `jogadores`
- **`$fillable`:** **`equipe_id`**, **`modalidade_id`**, `nome`, `nome_exibicao`, `foto_path`, **`video_path`**, `data_nascimento`, `criado_em_campo`, `ativo`
- `fotoUrl()`/`videoUrl()`: URL absoluta ou `null`. Foto e vídeo são independentes.
- `idade()`: anos completos a partir de `data_nascimento`, ou `null` se ela não foi
  informada. Vai pronta no payload (`idade`) — o Node não faz essa conta.
- **Pertence a UMA equipe e UMA modalidade.** Pode estar em vários times daquela equipe
  (Sub-15 e Adulto), nunca em time de outra equipe ou modalidade.

### Elenco
- **Tabela:** `elencos` — vínculo jogador ↔ time por temporada · `UNIQUE (time_id, jogador_id, temporada)`
- **`$fillable`:** `time_id`, `jogador_id`, `temporada`, `numero`, `posicao`, `ativo`
- **O número da camisa é daqui** (do vínculo), não do jogador.

### Competicao
- **Tabela:** `competicoes` · **`$fillable`:** `nome`, `modalidade_id`, `temporada`, `ativo`

### Jogo
- **Tabela:** `jogos`
- **`$fillable`:** `competicao_id`, `modalidade_id`, `time_casa_id`, `time_fora_id`, `data_hora`,
  `local`, `status`, `placar_casa`, `placar_fora`, `sets_casa`, `sets_fora`, `periodos_jogados`,
  `criado_em_campo`, `observacoes`
- **`status`**: `agendado` | `ao_vivo` | `encerrado` | `cancelado`.
  `placar_casa`/`placar_fora`/`sets_*` são **cache** derivado de `jogo_eventos` — a verdade é
  sempre o log de eventos.

### Escalacao
- **Tabela:** `escalacoes` — quem foi relacionado NESTE jogo (distinto do elenco da temporada) ·
  `UNIQUE (jogo_id, jogador_id)` · **`$fillable`:** `jogo_id`, `time_id`, `jogador_id`, `numero`, `titular`, `capitao`

### JogoEvento
- **Tabela:** `jogo_eventos` — **log append-only, o coração do scout.**
  `UNIQUE (jogo_id, sequencia)` · `UNIQUE (uuid)`
- **`$fillable`:** `uuid`, `jogo_id`, `sequencia`, `tipo`, `time_id`, `jogador_id`, `valor`,
  `periodo`, `cronometro_ms`, `ocorrido_em`, `payload`
- **Nunca sofre UPDATE nem DELETE.** `uuid` e `sequencia` são gerados pelo Node. Correção é um
  evento novo `estorno` referenciando o `uuid` original no `payload`.
- `tipo` ∈ `inicio_jogo`, `fim_jogo`, `ponto`, `falta`, `set`, `periodo`, `crono_play`,
  `crono_pause`, `crono_set`, `substituicao`, `timeout`, `cartao`, `estorno`.
- **`ponto` e `falta` exigem `cronometro_ms`.**

---

## 4. Contrato da API

### Autenticação

Sanctum, token de acesso pessoal, sem sessão/CSRF. Uma única ability, `placar:operar`.

```
Authorization: Bearer {{LARAVEL_API_TOKEN}}
```

Token gerado no lado Laravel com `php artisan placar:token node-producao`. Sem token, ou
sem a ability → `401`/`403`. Rate limit: **300 req/min** por token — generoso porque o
Node manda eventos **em lote**, não um por request.

Base: `{{LARAVEL_BASE_URL}}/api/placar` (configure em env, não hardcode).

### Convenções

- Resposta **sem envelope `{"data": ...}`** — o corpo já é o recurso.
- `logo_url`, `foto_url` e `video_url` são **sempre URLs absolutas** ou `null` — nunca
  base64, nunca path relativo. Podem ir direto num `<img>`/`<video>`.
- Datas de saída em ISO 8601; `ocorrido_em` de entrada aceita `"Y-m-d H:i:s.v"`.
- Campos `modalidade` de entrada aceitam **slug ou id**.
- `criado_em_campo` marca o que nasceu no modo avulso.

### Rotas

| Método | Rota | Descrição |
|---|---|---|
| GET | `/placar/ping` | Diagnóstico — confirma token válido |
| GET | `/placar/modalidades` | As 3 modalidades fixas |
| GET | `/placar/equipes` | Lista equipes (`busca`, `modalidade`) |
| POST | `/placar/equipes` | Cria equipe em campo |
| POST/DELETE | `/placar/equipes/{equipe}/logo` | Envia/remove a logo |
| GET | `/placar/times` | Lista times (`modalidade`, `equipe_id`, `categoria`, `busca`) |
| GET | `/placar/times/{time}/elenco` | Elenco da temporada (`temporada`) |
| POST | `/placar/times` | Cria time em campo |
| POST/DELETE | `/placar/times/{time}/logo` | Envia/remove a logo |
| POST | `/placar/jogadores` | Cria jogador em campo |
| POST/DELETE | `/placar/jogadores/{jogador}/foto` | Envia/remove a foto |
| POST/DELETE | `/placar/jogadores/{jogador}/video` | **Envia/remove o vídeo** |
| GET | `/placar/jogos` | Lista jogos (`status`, `data`, `modalidade`, `competicao_id`) |
| GET | `/placar/jogos/{jogo}` | **Payload completo — gameState do Node** |
| POST | `/placar/jogos` | Cria jogo em campo |
| POST | `/placar/jogos/{jogo}/iniciar` | Marca `ao_vivo` (idempotente) |
| POST | `/placar/jogos/{jogo}/escalacao` | Substitui a escalação de um time |
| POST | `/placar/jogos/{jogo}/eventos` | **O endpoint mais importante** — lote, idempotente |
| POST | `/placar/jogos/{jogo}/encerrar` | Marca `encerrado` (idempotente) |
| GET | `/placar/jogos/{jogo}/sumula?time_id=` | Súmula do jogo — completa ou recortada em um time |
| GET | `/placar/jogos/{jogo}/jogadores/{jogador}/atuacao` | **Ficha minutada do jogador na partida** |
| GET | `/placar/scout/jogadores/{jogador}` | Partidas em que o jogador atuou |

> `GET /placar/scout/artilharia` **não existe mais**.

### `GET /placar/jogos/{jogo}` — o gameState

Uma chamada monta a tela: dados do jogo + elenco operacional dos dois times (a escalação,
se já foi feita; senão o elenco da temporada corrente).

```json
{
    "jogo": {
        "id": 12,
        "esporte": "futsal",
        "status": "agendado",
        "data_hora": "2026-08-07T19:30:00-03:00",
        "local": "Quadra Poliesportiva do Clube",
        "competicao": { "id": 3, "nome": "Copa Clube 2026" }
    },
    "time_casa": {
        "id": 1,
        "nome_exibicao": "Clube dos Funcionários Adulto",
        "logo_url": "http://servidor/storage/placar/equipes/1/logo.webp",
        "elenco": [
            {
                "jogador_id": 1,
                "numero": "10",
                "nome_exibicao": "Carlos Souza",
                "foto_url": "http://servidor/storage/placar/jogadores/1/foto.webp",
                "video_url": "http://servidor/storage/placar/jogadores/1/video.mp4",
                "titular": true,
                "capitao": true
            }
        ]
    },
    "time_fora": { "id": 4, "nome_exibicao": "Vila Nova Adulto", "logo_url": null, "elenco": [] }
}
```

`POST /placar/jogos` devolve exatamente este mesmo formato.

### Ciclo de vida do jogo

1. **`POST /jogos/{jogo}/iniciar`** — `{ operador? }`. Idempotente. A resposta traz
   `ultima_sequencia` — **guarde**: é o ponto de retomada do contador após queda.
2. **`POST /jogos/{jogo}/escalacao`** — `{ time_id, jogadores: [{ jogador_id, numero,
   titular?, capitao? }] }`. **Substitui por completo** a escalação daquele time (não faz
   merge) — mande sempre o array completo.
3. **`POST /jogos/{jogo}/eventos`** — abaixo.
4. **`POST /jogos/{jogo}/encerrar`** — `{ placar_casa, placar_fora, sets_casa?, sets_fora?,
   periodos_jogados? }`. Idempotente. O backend recalcula o placar a partir do log; se
   divergir do enviado, grava a divergência em `observacoes` — **o log manda**, mas não
   trate isso como erro fatal.

### O endpoint de eventos

```json
{
    "eventos": [
        {
            "uuid": "b1e6a1b1-4b8a-4b1a-9c3a-000000000002",
            "sequencia": 2,
            "tipo": "ponto",
            "time_id": 1,
            "jogador_id": 1,
            "valor": 1,
            "periodo": 1,
            "cronometro_ms": 754000,
            "ocorrido_em": "2026-08-06 19:34:12.500",
            "payload": null
        }
    ]
}
```

| Campo | Origem | Observação |
|---|---|---|
| `uuid` | **gerado pelo Node** | chave de idempotência — nunca gerado no backend |
| `sequencia` | **gerado pelo Node** | contador incremental do jogo |
| `tipo` | lista acima | |
| `time_id` | opcional | precisa ser um dos dois times do jogo |
| `jogador_id` | opcional | precisa existir |
| `valor` | obrigatório em `ponto` | `1` em futsal/vôlei; `1`, `2` ou `3` em basquete |
| `periodo` | opcional | tempo/quarto/set |
| `cronometro_ms` | **obrigatório em `ponto` e `falta`** | cronômetro da **partida**, em ms, ≥ 0 |
| `ocorrido_em` | obrigatório | |
| `payload` | opcional | usado por `estorno` |

**Regras por modalidade:** `set` só em vôlei; `falta` não existe em vôlei; valor de `ponto`
conforme a tabela.

**Resposta:**

```json
{
    "aceitos": ["b1e6a1b1-..."],
    "duplicados": [],
    "rejeitados": [ { "uuid": "...", "motivo": "'ponto' exige cronometro_ms (minutagem na partida)" } ]
}
```

- **Idempotência por `uuid`**: reenviar o mesmo lote devolve os uuids em `duplicados`, sem
  inserir de novo.
- **Um evento inválido não derruba o lote** — os demais entram; o que falhou vem em
  `rejeitados` com o motivo.
- **Sem limite de tamanho de lote.**

#### Corrigindo um evento (`estorno`)

O log é append-only. Para desfazer:

```json
{
    "uuid": "<novo-uuid>", "sequencia": 5, "tipo": "estorno",
    "ocorrido_em": "2026-08-06 19:36:00.000",
    "payload": { "evento_uuid": "<uuid-original>", "motivo": "ponto do jogador errado" }
}
```

O evento original sai do placar e dos totais, mas **continua na súmula/timeline**, marcado.

### Scout — sempre por partida

- **`GET /jogos/{jogo}/sumula?time_id=`** — placar por período/set, timeline completa (com
  jogador, número, **`minuto`** e a marca `estornado`) e totais por jogador de cada time.
  Com `time_id`, a mesma súmula recortada naquele time (ver 1.7): `recorte` identifica
  qual, `eventos` traz só os lances dele mais os marcos sem time, e o lado de fora do
  recorte vem `[]` em `totais_por_jogador`. Placar e cabeçalho continuam completos.
- **`GET /jogos/{jogo}/jogadores/{jogador}/atuacao`** — a ficha do jogador nesta partida:

```json
{
    "jogo": { "id": 12, "esporte": "futsal", "placar_casa": 3, "placar_fora": 1 },
    "jogador": { "id": 1, "numero": "10", "nome_exibicao": "Carlos Souza", "foto_url": "...", "time_id": 1 },
    "totais": { "pontos": 2, "faltas": 1, "lances": 3 },
    "lances": [
        { "sequencia": 2, "tipo": "ponto", "valor": 1, "periodo": 1,
          "cronometro_ms": 754000, "minuto": "12:34", "ocorrido_em": "...", "estornado": false }
    ]
}
```

- **`GET /scout/jogadores/{jogador}?temporada=`** — lista de partidas em que atuou, com
  `pontos`/`faltas` em cada. Sem lances, `partidas` vem `[]`.

### Criação em campo (modo avulso)

1. **`POST /equipes`** — `{ nome, nome_curto?, cidade? }`.
2. **`POST /times`** — `{ equipe_id? | equipe_nome?, modalidade, categoria? }`. `categoria`
   default `"Adulto"` e **normalizada** (ver 1.8): `201` se criou, `200` se reaproveitou.
3. **`POST /jogadores`** — `{ nome, nome_exibicao?, time_id?, equipe_id?, modalidade?, numero?, temporada? }`.
   Com `time_id`, equipe e modalidade vêm do time. **Sem `time_id`, `equipe_id` e
   `modalidade` são obrigatórios** (ver 1.5).
4. **`POST /jogos`** — `{ modalidade, time_casa_id, time_fora_id, data_hora?, local?,
   competicao_id? }`. Valida modalidade **e categoria** iguais nos dois times (`422`).

### Mídia

- **Logo/foto:** `POST` com `arquivo` (multipart, jpg/png/webp, até 8MB) **ou**
  `arquivo_base64` (data URL) — um dos dois, nunca ambos. Logo cabe em 512×512 sem cortar;
  foto vira 512×512 com recorte central.
- **Vídeo:** `POST /placar/jogadores/{jogador}/video`, multipart no campo **`video`**,
  **mp4 ou webm**, até **28MB**. **Não aceita base64.** O tipo é validado pelos bytes reais
  do container — um `.mp4` que não seja vídeo é recusado com `422`.
- Enviar novo substitui o anterior; `DELETE` remove. Foto e vídeo são independentes.
- Tudo é servido como arquivo estático (`public/storage/placar/…`), com range request.

### Erros

- **401** — sem token ou token inválido: pare, avise o operador, não finja estar online.
- **403** — token sem a ability: erro de configuração, não faça retry.
- **404** — id inexistente (ou `/scout/artilharia`, que foi removido).
- **422** — payload malformado da chamada, ou regra de negócio (modalidade/categoria
  divergente, vídeo inválido). **Não confundir** com `rejeitados` dentro de um `200`.
- **429** — 300 req/min excedido: sinal de que está mandando evento um a um em vez de em
  lote.

---

## 5. Orientação prática para este repositório (Node)

### Configuração
- `LARAVEL_BASE_URL` + `LARAVEL_API_TOKEN` em env, nunca hardcode.
- Peça um token de teste e a URL de homologação antes de codar.
- Peça para rodarem `php artisan db:seed --class=PlacarDemoSeeder` para ter dados de
  exemplo.

### Fluxo de uma partida
1. `GET /jogos/{jogo}` — monta a tela (foto **e** vídeo já vêm aqui).
2. `POST /jogos/{jogo}/escalacao` (opcional, um por time) — sempre o array completo.
3. `POST /jogos/{jogo}/iniciar` — guarde `ultima_sequencia`.
4. `POST /jogos/{jogo}/eventos` — em lote, com `cronometro_ms` em ponto/falta.
5. `POST /jogos/{jogo}/encerrar` — divergência é alerta, não erro fatal.

### O que o Node precisa implementar (a API não faz por ele)
- **`uuid`** v4 gerado **no momento em que o lance acontece na UI**, não no envio — assim
  um retry reenvia o mesmo uuid e a idempotência funciona.
- **`sequencia`** incremental **por jogo**, persistida localmente, retomada de
  `ultima_sequencia` — nunca reiniciada do zero se a partida já tinha eventos.
- **`cronometro_ms`** capturado do cronômetro do jogo no instante do lance.
- **Envio em lote**, com buffer curto — não um POST por evento.
- **Tratamento de `rejeitados`** item a item, mostrando o motivo ao operador.
- **Desfazer = mandar `estorno`**, nunca editar ou reenviar o mesmo uuid (não existe DELETE
  de evento).
- **Reconciliação**: se mantém placar local otimista, confira contra a resposta da API,
  principalmente depois de reconectar.

### Papel do Socket.IO
A API Laravel não sabe nada de WebSocket — é o Node que:
1. Recebe o lance da UI do operador.
2. Aplica otimisticamente no estado local e emite via Socket.IO para os telões, **antes**
   de esperar o HTTP, para não travar a experiência ao vivo.
3. Manda o `POST .../eventos` (em lote) como fonte de verdade persistida.
4. Se vier em `rejeitados` ou a chamada falhar, reconcilia: desfaz o efeito otimista ou
   avisa o operador — nunca deixe o estado do Node divergir em silêncio do backend.
5. Em reconexão/reload no meio do jogo: `GET /jogos/{jogo}` de novo e retome o contador de
   `ultima_sequencia`.

### Checklist de migração
- [ ] `cronometro_ms` enviado em todo `ponto` e `falta`
- [ ] `rejeitados` tratado e visível na UI
- [ ] chamadas a `/scout/artilharia` removidas
- [ ] gambiarras de URL de imagem removidas
- [ ] `video_url` consumido, com fallback para `null`
- [ ] `200` em `POST /times` tratado como sucesso (não como erro)
- [ ] seleção de times filtrada por categoria ao criar jogo
- [ ] `POST /jogadores` sem `time_id` passou a mandar `equipe_id` + `modalidade`
- [ ] leituras de `jogador.documento` removidas (o campo não existe mais)
- [ ] idade lida de `jogador.idade`, sem calcular a partir de `data_nascimento`
- [ ] súmula oferecida nas três saídas (completa, mandante, visitante) via `?time_id=`
