# Prompt: integração do placar eletrônico (Node) com a API do Placar Clube

> Pacote autocontido — cole isto inteiro na sessão do Claude Code que trabalha no
> repositório do placar (Node/Express/Socket.IO). Não depende de acesso ao repositório
> Laravel: toda a referência necessária (regras de negócio, modelo de dados, contrato da
> API) está reproduzida abaixo, não só linkada.

---

## 1. Contexto e responsabilidade

Você está trabalhando no placar eletrônico ao vivo — Node.js + Express + Socket.IO. Este
projeto **não tem estado de negócio próprio**: quem é a fonte de verdade sobre equipes,
times, jogadores, jogos e o histórico de eventos é uma API Laravel que já existe, pronta,
testada e documentada, rodando dentro de outro repositório (`lpr-app`), módulo "Placar
Clube".

Sua responsabilidade é fazer este app consumir e operar essa API corretamente — nunca
duplicar essas regras em memória do Node além do necessário para a tela ao vivo, e nunca
inventar um formato de payload diferente do que a API espera. As seções 2 e 3 abaixo são
a cópia integral da documentação de referência do lado Laravel (`docs/placar-clube-api.md`
e a seção "Placar Clube" de `docs/models.md`); a seção 4 é a orientação prática de como
usar isso no Node.

---

## 2. Modelo de dados (referência — `docs/models.md`, seção "Placar Clube")

API + telas para o placar eletrônico do Node (futsal, basquete, vôlei). O Node não tem banco
próprio de cadastro — consome esta API.

### Modalidade
- **Tabela:** `modalidades` — referência fixa (3 linhas, seed em `ModalidadeSeeder`)
- **`$fillable`:** `nome`, `slug`, `ativo` · **`$casts`:** `ativo` → `boolean`
- **Relacionamentos:** `times()` hasMany Time · `competicoes()` hasMany Competicao · `jogos()` hasMany Jogo
- `slug` (`futsal`/`basquete`/`volei`) é o que o Node usa no campo `esporte` do gameState.

### Equipe
- **Tabela:** `equipes` · **SoftDeletes**
- **`$fillable`:** `nome`, `nome_curto`, `logo_path`, `cidade`, `criado_em_campo`, `ativo`
- **Relacionamentos:** `times()` hasMany Time
- **`logoUrl()`** resolve a URL absoluta da logo própria (ou `null`) — é o fallback de `Time::logoUrl()`.

### Time
- **Tabela:** `times` · **SoftDeletes** · `UNIQUE (equipe_id, modalidade_id, categoria)`
- **`$fillable`:** `equipe_id`, `modalidade_id`, `categoria`, `nome_exibicao`, `logo_path`, `criado_em_campo`, `ativo`
- **Relacionamentos:** `equipe()` belongsTo Equipe · `modalidade()` belongsTo Modalidade ·
  `elencos()` hasMany Elenco · `escalacoes()` hasMany Escalacao · `jogosEmCasa()`/`jogosFora()`
  hasMany Jogo (`time_casa_id`/`time_fora_id`)
- Recorte de uma equipe por modalidade + categoria — não confundir com Equipe. Um mesmo
  jogador pode estar em times diferentes da mesma equipe (ex.: Sub-17 → Adulto), com número de
  camisa próprio em cada um (ver Elenco).
- **`nomeExibicaoResolvido()`**: se `nome_exibicao` for nulo, monta a partir de
  `equipe.nome_curto ?: equipe.nome` + `categoria`. **`logoUrl()`**: própria, senão herda da equipe.

### Jogador
- **Tabela:** `jogadores` · **SoftDeletes**
- **`$fillable`:** `nome`, `nome_exibicao`, `foto_path`, `data_nascimento`, `documento`, `criado_em_campo`, `ativo`
- **`$casts`:** `data_nascimento` → `date`
- **Relacionamentos:** `elencos()` hasMany Elenco · `escalacoes()` hasMany Escalacao ·
  `eventos()` hasMany JogoEvento · `times()` belongsToMany Time (pivô `elencos`, com `temporada`/`numero`/`posicao`/`ativo`)
- **`nomeExibicaoResolvido()`**: nome curto do telão, ou `nome`. **`fotoUrl()`**: URL absoluta ou `null`.

### Elenco
- **Tabela:** `elencos` — vínculo jogador ↔ time por temporada · `UNIQUE (time_id, jogador_id, temporada)`
- **`$fillable`:** `time_id`, `jogador_id`, `temporada`, `numero`, `posicao`, `ativo`
- **Relacionamentos:** `time()` belongsTo Time · `jogador()` belongsTo Jogador

### Competicao
- **Tabela:** `competicoes`
- **`$fillable`:** `nome`, `modalidade_id`, `temporada`, `ativo`
- **Relacionamentos:** `modalidade()` belongsTo Modalidade · `jogos()` hasMany Jogo

### Jogo
- **Tabela:** `jogos` · **SoftDeletes**
- **`$fillable`:** `competicao_id`, `modalidade_id`, `time_casa_id`, `time_fora_id`, `data_hora`,
  `local`, `status`, `placar_casa`, `placar_fora`, `sets_casa`, `sets_fora`, `periodos_jogados`,
  `criado_em_campo`, `observacoes`
- **`$casts`:** `data_hora` → `datetime`
- **Relacionamentos:** `competicao()` belongsTo Competicao (nullable) · `modalidade()` belongsTo
  Modalidade · `timeCasa()`/`timeFora()` belongsTo Time · `escalacoes()` hasMany Escalacao ·
  `eventos()` hasMany JogoEvento (ordenado por `sequencia`)
- **`status`**: `agendado` | `ao_vivo` | `encerrado` | `cancelado` (const `STATUSES`).
  `placar_casa`/`placar_fora`/`sets_*` são **cache** derivado de `jogo_eventos` — a verdade é
  sempre o log de eventos.

### Escalacao
- **Tabela:** `escalacoes` — quem foi relacionado NESTE jogo (distinto do elenco da temporada) ·
  `UNIQUE (jogo_id, jogador_id)`
- **`$fillable`:** `jogo_id`, `time_id`, `jogador_id`, `numero`, `titular`, `capitao`
- **Relacionamentos:** `jogo()` belongsTo Jogo · `time()` belongsTo Time · `jogador()` belongsTo Jogador

### JogoEvento
- **Tabela:** `jogo_eventos` — **log append-only, o coração do scout.** `UPDATED_AT = null`.
  `UNIQUE (jogo_id, sequencia)` · índices em `jogador_id` e `tipo`
- **`$fillable`:** `uuid`, `jogo_id`, `sequencia`, `tipo`, `time_id`, `jogador_id`, `valor`,
  `periodo`, `cronometro_ms`, `ocorrido_em`, `payload`
- **`$casts`:** `ocorrido_em` → `datetime:Y-m-d H:i:s.v` (precisão de ms) · `payload` → `array`
- **Relacionamentos:** `jogo()` belongsTo Jogo · `time()`/`jogador()` belongsTo (nullable)
- **Nunca sofre UPDATE nem DELETE pela API.** `uuid` é gerado pelo Node (idempotência contra
  reenvio de fila offline); `sequencia` é o contador incremental do jogo, também gerado pelo
  Node. Correção de um evento é um evento novo `estorno` referenciando o `uuid` original no
  `payload`. `tipo` ∈ const `TIPOS` (`inicio_jogo`, `fim_jogo`, `ponto`, `falta`, `set`,
  `periodo`, `crono_play`, `crono_pause`, `crono_set`, `substituicao`, `timeout`, `cartao`,
  `estorno`).

---

## 3. Contrato da API (referência — `docs/placar-clube-api.md`)

API consumida pelo Node do placar eletrônico ao vivo (Express + Socket.IO), rodando dentro
do Laravel — não é um serviço separado. O Node nunca guarda estado de negócio: ele lê o
jogo daqui, manda o log de eventos conforme a partida acontece, e o backend Laravel é a
fonte de verdade do que foi jogado.

### Autenticação

Sanctum, token de acesso pessoal, sem sessão/CSRF. Uma única ability, `placar:operar`
(`App\Support\Placar\PlacarAbilities::OPERAR`) — não há granularidade menor porque é
sempre o mesmo Node falando com a API.

Gerar um token (do lado Laravel):

```
php artisan placar:token node-producao
```

O nome (`node-producao` no exemplo) identifica o `ApiCliente`, não o token — rodar de novo
com o mesmo nome reaproveita o cliente e emite um token novo, sem revogar o anterior. O
comando imprime:

```
LARAVEL_API_TOKEN=1|AbCdEf...
```

Cole em `LARAVEL_API_TOKEN` no `.env` do Node. Toda requisição:

```
Authorization: Bearer {{token}}
```

Sem token, ou token sem a ability `placar:operar` → `401`/`403`. Rate limit:
**300 requisições/minuto** por token (`throttle:300,1`) — generoso de propósito, porque o
Node manda eventos em lote, não um por request.

### Convenções

- Todas as rotas ficam sob `/api/placar`.
- Resposta sem envelope `{"data": ...}` — `JsonResource::withoutWrapping()` está ativo
  globalmente. O que os exemplos abaixo mostram é o corpo exato da resposta.
- `logo_url` e `foto_url` são **sempre URLs absolutas**, resolvidas no servidor — nunca
  base64, nunca path relativo. Um time sem logo própria herda a da equipe (`Time::logoUrl()`);
  se nenhuma existir, o campo vem `null`.
- `criado_em_campo` (boolean) marca todo registro criado pelo modo avulso — é o que a tela
  web usa para destacar "revisar depois".
- Datas de entrada/saída em ISO 8601 (`data_hora`, `ocorrido_em` nas respostas) ou
  `"Y-m-d H:i:s.v"` (`ocorrido_em` nos eventos enviados, com milissegundo).
- Modalidades são 3 linhas fixas: `futsal`, `basquete`, `volei`. Qualquer campo
  `modalidade` na entrada aceita o **slug ou o id**.

### Modos de operação

- **Planejado**: equipes, times, jogadores, elencos e o jogo já existem (cadastrados pela
  tela web ou por outra chamada da API). O Node só lê e opera.
- **Avulso**: o jogo não foi planejado. O Node cria equipe → time → jogador → jogo em até
  quatro chamadas (seção "Criação em campo" abaixo) e segue direto para o ciclo de vida do
  jogo. Tudo criado nesse modo nasce com `criado_em_campo = true` e validação mínima —
  alguém revisa/completa depois pela tela web.

### Rotas

| Método | Rota | Descrição |
|---|---|---|
| GET | `/placar/ping` | Diagnóstico — confirma token válido, sem depender de dado nenhum |
| GET | `/placar/modalidades` | As 3 modalidades fixas |
| GET | `/placar/equipes` | Lista equipes (`busca`, `modalidade`) |
| POST | `/placar/equipes` | Cria equipe em campo (avulso) |
| POST | `/placar/equipes/{equipe}/logo` | Envia/substitui a logo |
| DELETE | `/placar/equipes/{equipe}/logo` | Remove a logo |
| GET | `/placar/times` | Lista times (`modalidade`, `equipe_id`, `categoria`, `busca`) |
| GET | `/placar/times/{time}/elenco` | Elenco da temporada (`temporada`) |
| POST | `/placar/times` | Cria time em campo (avulso) |
| POST | `/placar/times/{time}/logo` | Envia/substitui a logo |
| DELETE | `/placar/times/{time}/logo` | Remove a logo |
| POST | `/placar/jogadores` | Cria jogador em campo (avulso) |
| POST | `/placar/jogadores/{jogador}/foto` | Envia/substitui a foto |
| DELETE | `/placar/jogadores/{jogador}/foto` | Remove a foto |
| GET | `/placar/jogos` | Lista jogos (`status`, `data`, `modalidade`, `competicao_id`) |
| GET | `/placar/jogos/{jogo}` | Payload completo — gameState do Node |
| POST | `/placar/jogos` | Cria jogo em campo (avulso) |
| POST | `/placar/jogos/{jogo}/iniciar` | Marca `ao_vivo` (idempotente) |
| POST | `/placar/jogos/{jogo}/escalacao` | Substitui a escalação de um time neste jogo |
| POST | `/placar/jogos/{jogo}/eventos` | **O endpoint mais importante** — lote de eventos, idempotente |
| POST | `/placar/jogos/{jogo}/encerrar` | Marca `encerrado`, recalcula e fecha o placar (idempotente) |
| GET | `/placar/jogos/{jogo}/sumula` | Súmula agregada do jogo |
| GET | `/placar/scout/artilharia` | Ranking de pontos (`modalidade`, `competicao_id`, `temporada`, `time_id`) |
| GET | `/placar/scout/jogadores/{jogador}` | Perfil agregado do jogador (`temporada`) |

### `GET /placar/jogos/{jogo}` — exemplo completo

O Node monta a tela do placar com uma chamada só: dados do jogo + elenco operacional dos
dois times (a escalação, se já foi feita; senão o elenco da temporada corrente —
`Jogo::elencoOperacionalDoTime()`).

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
        "logo_url": "http://localhost:8000/storage/placar/equipes/1/logo.webp",
        "elenco": [
            {
                "jogador_id": 1,
                "numero": "10",
                "nome_exibicao": "Carlos Souza",
                "foto_url": "http://localhost:8000/storage/placar/jogadores/1/foto.webp",
                "titular": true,
                "capitao": true
            }
        ]
    },
    "time_fora": {
        "id": 4,
        "nome_exibicao": "Associação Vila Nova Adulto",
        "logo_url": null,
        "elenco": []
    }
}
```

`POST /placar/jogos` (criação em campo) devolve exatamente este mesmo formato — o Node
segue direto para o jogo recém-criado, sem uma segunda chamada.

### Ciclo de vida do jogo

1. **`POST /jogos/{jogo}/iniciar`** — `{ "operador"?: string }`. Idempotente: se já está
   `ao_vivo`, só devolve o estado atual. Resposta inclui `ultima_sequencia` (a maior
   `sequencia` já registrada) para o Node retomar o cronômetro/contador depois de uma
   queda de conexão.
2. **`POST /jogos/{jogo}/escalacao`** — `{ time_id, jogadores: [{ jogador_id, numero,
   titular?, capitao? }] }`. `time_id` precisa ser um dos dois times do jogo. **Substitui
   por completo** a escalação anterior daquele time (não faz merge).
3. **`POST /jogos/{jogo}/eventos`** — ver seção dedicada abaixo.
4. **`POST /jogos/{jogo}/encerrar`** — `{ placar_casa, placar_fora, sets_casa?, sets_fora?,
   periodos_jogados? }`. Idempotente: jogo já `encerrado` só devolve o estado atual, sem
   reprocessar. Na primeira vez, recalcula o placar a partir do log de eventos
   (`Jogo::calcularPlacar()`) e, se divergir do que foi enviado, registra a divergência em
   `observacoes` — **o log de eventos manda**, mas a divergência não passa em branco.

### O endpoint de eventos (`POST /jogos/{jogo}/eventos`)

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
            "cronometro_ms": 542000,
            "ocorrido_em": "2026-08-06 19:34:12.500",
            "payload": null
        }
    ]
}
```

| Campo | Origem | Observação |
|---|---|---|
| `uuid` | **gerado pelo Node** | chave de idempotência — nunca gerado aqui |
| `sequencia` | **gerado pelo Node** | contador incremental do jogo; garante ordem mesmo com relógios divergentes entre dispositivos |
| `tipo` | um de `JogoEvento::TIPOS` | `inicio_jogo`, `fim_jogo`, `ponto`, `falta`, `set`, `periodo`, `crono_play`, `crono_pause`, `crono_set`, `substituicao`, `timeout`, `cartao`, `estorno` |
| `time_id` | opcional | precisa ser um dos dois times do jogo |
| `jogador_id` | opcional | precisa existir |
| `valor` | obrigatório em `ponto` | `1` em futsal/vôlei; `1`, `2` ou `3` em basquete (`ModalidadeRegras::valorValidoDePonto()`) |
| `periodo` | opcional | tempo/quarto/set em jogo, conforme a modalidade |
| `cronometro_ms` | opcional | tempo do **cronômetro da partida**, não o relógio de parede |
| `ocorrido_em` | obrigatório | qualquer formato que `Carbon::parse()` aceite |
| `payload` | opcional, livre | usado por `estorno` (ver abaixo) |

**Regras por modalidade** (`ModalidadeRegras`, único ponto de verdade): `set` só é válido
em vôlei; `falta` não existe em vôlei; o valor de `ponto` varia conforme a tabela acima.

**Resposta:**

```json
{
    "aceitos": ["b1e6a1b1-4b8a-4b1a-9c3a-000000000002"],
    "duplicados": [],
    "rejeitados": [
        { "uuid": "...", "motivo": "valor de ponto inválido para 'futsal'" }
    ]
}
```

- **Idempotência por `uuid`**: reenviar o mesmo lote (retry de rede, fila offline que
  sincronizou de novo) devolve os mesmos uuids em `duplicados` — nada é inserido de novo.
  Resolvido tentando o `INSERT` e capturando a violação de unique (não pré-checando), o
  que também cobre a corrida de dois envios quase simultâneos do mesmo lote.
- **Um evento inválido não derruba o lote**: cada evento é validado individualmente; o que
  falhar entra em `rejeitados` com o motivo, os demais seguem normalmente.
- **Sem limite de tamanho de lote** — decisão explícita, mesmo a especificação original
  sugerindo até 200: o Node pode precisar mandar lotes maiores (reenvio de fila offline
  acumulada, por exemplo).
- Um lote com `ponto`/`set`/`estorno` aceito recalcula e grava o cache
  `placar_casa`/`placar_fora`/`sets_casa`/`sets_fora` em `jogos` (lotes só com
  `crono_play`/`timeout`/etc. não tocam nesse cache).

#### Corrigindo um evento (`estorno`)

O log é **append-only** — nunca há `UPDATE`/`DELETE` num evento já gravado. Para corrigir
(ponto lançado errado, falta no jogador errado etc.), manda-se um evento novo do tipo
`estorno` referenciando o uuid original:

```json
{
    "uuid": "b1e6a1b1-4b8a-4b1a-9c3a-000000000005",
    "sequencia": 5,
    "tipo": "estorno",
    "ocorrido_em": "2026-08-06 19:36:00.000",
    "payload": { "evento_uuid": "b1e6a1b1-4b8a-4b1a-9c3a-000000000002", "motivo": "ponto lançado para o jogador errado" }
}
```

O evento original some do placar, dos totais e do scout, mas **continua aparecendo na
súmula/timeline**, marcado como estornado — é histórico, não é apagado.

### Scout (`ScoutService`)

Camada de leitura agregada — súmula, artilharia, perfil do jogador — que lê **só** de
`jogo_eventos`, nunca de campo denormalizado nem de tabela de estatística.

- **`GET /jogos/{jogo}/sumula`** — placar por período/set, timeline cronológica completa
  (com jogador, **número**, foto, e a marca `estornado`), totais de pontos/faltas por
  jogador de cada time (com o número de cada um). O número é o da escalação deste jogo,
  se já foi feita; senão o do elenco da temporada corrente.
- **`GET /scout/artilharia?modalidade=&competicao_id=&temporada=&time_id=`** — ranking de
  pontos por jogador, jogos disputados e média. `time_id` filtra pelo **time de quem
  marcou o ponto** (o evento), não pelos jogos em que o time apareceu — senão o artilheiro
  do adversário entraria no "ranking do time" também. **`numero`** só vem preenchido quando
  `time_id` é informado (do elenco daquele time); sem esse filtro vem `null` — o mesmo
  jogador pode ter pontuado por times diferentes, sem um número único correto.
- **`GET /scout/jogadores/{jogador}?temporada=`** — jogos disputados (qualquer evento seu,
  escalação é opcional), pontos, média, faltas, distribuição de pontos por período.
  **`numero`** é o do elenco ativo mais recente do jogador (na temporada filtrada, se
  houver).

### Criação em campo (modo avulso)

Quatro chamadas, cada uma tolerante (validação mínima) e sempre `criado_em_campo = true`:

1. **`POST /equipes`** — `{ nome, nome_curto?, cidade? }`.
2. **`POST /times`** — `{ equipe_id? | equipe_nome?, modalidade, categoria? }`. Um dos dois
   (`equipe_id` OU `equipe_nome`) é obrigatório; sem `equipe_id`, a equipe é criada junto
   (`firstOrCreate` por nome — não marca `criado_em_campo` se ela já existia). `categoria`
   default `"Adulto"`. `firstOrCreate` também no time: reenviar a mesma criação não
   duplica.
3. **`POST /jogadores`** — `{ nome, nome_exibicao?, time_id?, numero?, temporada? }`. Sem
   foto, data de nascimento ou documento — nada disso é essencial para entrar em quadra.
   Com `time_id`, já cria o vínculo em `elencos` na mesma transação.
4. **`POST /jogos`** — `{ modalidade, time_casa_id, time_fora_id, data_hora?, local?,
   competicao_id? }`. `data_hora` default agora. A modalidade do jogo precisa bater com a
   dos dois times (validado antes de gravar). Devolve o mesmo payload de
   `GET /jogos/{id}` — o Node segue direto para o jogo, sem chamada extra.

### Imagens (logo / foto)

`POST` em `/equipes/{equipe}/logo`, `/times/{time}/logo` e `/jogadores/{jogador}/foto`.
Aceita **um dos dois**, nunca ambos:

- `arquivo` — multipart, `jpg`/`jpeg`/`png`/`webp`, até 8MB.
- `arquivo_base64` — data URL (`data:image/png;base64,...`).

Processado por `ImagemService` (GD puro, sem dependência nova) — o formato real dos bytes é
detectado pelos magic bytes, não pelo `Content-Type` nem pelo prefixo declarado no base64:

- **Logo** (equipe/time): redimensiona **proporcionalmente** até 512×512 — não corta.
- **Foto** (jogador): redimensiona para 512×512 com **recorte central quadrado**.

Enviar uma nova imagem substitui a anterior (o arquivo antigo é removido do disco). `DELETE`
remove sem substituir. Resposta é sempre o Resource do recurso (equipe/time/jogador) com
`logo_url`/`foto_url` já atualizada.

### Erros

- **401** — sem token, ou token inválido/revogado.
- **403** — token válido sem a ability `placar:operar`.
- **404** — id inexistente na rota (`{equipe}`, `{time}`, `{jogo}`, `{jogador}`).
- **422** — validação de request falhou (corpo malformado, campo obrigatório ausente,
  `exists:` não satisfeito, mimetype de imagem inválido etc.). **Não é assim** que um
  evento individual inválido dentro de um lote é reportado — isso vai em `rejeitados` na
  resposta `200`, ver acima.
- **429** — mais de 300 requisições/minuto para o mesmo token.

### Massa de demonstração e Postman

- `php artisan db:seed --class=PlacarDemoSeeder` — duas equipes, cada uma com time de
  futsal adulto, basquete adulto e basquete Sub-15 (10 jogadores cada), uma competição e
  três jogos agendados (um por combinação de modalidade/categoria). Idempotente — pode
  rodar de novo sem duplicar.
- `docs/postman/placar-clube.postman_collection.json` (do lado Laravel) — collection
  completa: diagnóstico, leitura, ciclo de vida do jogo (planejado), modo avulso de ponta
  a ponta e upload de imagens. Peça esse arquivo ao time se quiser validar payloads na mão
  antes de codar — não está reproduzido aqui porque não é um `.md`, mas os exemplos de
  payload desta seção 3 já cobrem os mesmos fluxos.

---

## 4. Orientação prática para este repositório (Node)

Sua responsabilidade é fazer este app consumir e operar a API acima corretamente — nunca
duplicar essas regras em memória do Node além do necessário para a tela ao vivo, e nunca
inventar um formato de payload diferente do documentado nas seções 2 e 3.

### Configuração

- `LARAVEL_BASE_URL` + `LARAVEL_API_TOKEN` em env — nunca hardcode. Base efetiva das
  chamadas: `{{LARAVEL_BASE_URL}}/api/placar`.
- Peça o token de teste (`php artisan placar:token node-dev`, do lado Laravel) e a URL do
  ambiente de homologação antes de codar.
- Rode (ou peça para rodarem) `php artisan db:seed --class=PlacarDemoSeeder` do lado
  Laravel para ter dados de exemplo prontos, em vez de criar tudo em modo avulso só para
  testar a tela.

### Os dois modos de operação — decida qual o app precisa suportar

- **Planejado**: equipe/time/jogador/jogo já existem. O Node só lê (`GET /jogos/{id}`) e
  opera o ciclo de vida.
- **Avulso**: nada existe ainda. O Node cria em sequência `POST /equipes` → `POST /times`
  → `POST /jogadores` → `POST /jogos` antes de operar. `POST /jogos` já devolve o mesmo
  payload de `GET /jogos/{id}` — não precisa de chamada extra para seguir pro jogo.

Confirme com produto/usuário se a tela inicial do placar precisa oferecer os dois fluxos
(selecionar jogo existente vs. "criar agora") ou só um deles nesta fase.

### Fluxo de uma partida — o que o Node precisa chamar, em ordem

1. `GET /jogos/{jogo}` — monta a tela.
2. `POST /jogos/{jogo}/escalacao` (opcional, um POST por time) — **substitui por completo**
   a escalação anterior daquele time. Se a UI permitir edição incremental, mande o array
   completo a cada save, não só o diff.
3. `POST /jogos/{jogo}/iniciar` — idempotente. Guarde `ultima_sequencia` da resposta: é o
   ponto de retomada do contador de eventos depois de uma queda de conexão/reload.
4. `POST /jogos/{jogo}/eventos` — em lote, ver regras específicas abaixo. É o endpoint que
   o Node vai chamar o tempo todo durante a partida.
5. `POST /jogos/{jogo}/encerrar` — idempotente. Se a API reportar divergência em
   `observacoes` versus o placar que o Node mandou, trate como alerta de conferência
   humana, não como erro fatal (o log de eventos é quem manda).

### O endpoint de eventos — o que o Node precisa implementar (a API não faz isso por ele)

- **`uuid`**: gerado no Node (v4), nunca no backend. É a chave de idempotência. Gere o
  uuid **no momento em que o evento acontece na UI**, não no momento do envio, para que um
  retry reenvie exatamente o mesmo uuid.
- **`sequencia`**: contador incremental **por jogo**, gerado no Node. Mantenha em memória
  (ou storage local, se o app puder cair/reabrir no meio de uma partida) e retome a partir
  de `ultima_sequencia` — nunca reinicie do zero se a partida já tinha eventos.
- **Envie em lote**, não um POST por evento — a API foi desenhada para isso (inclusive
  reenvio de fila offline acumulada) e não tem limite de tamanho.
- Trate `rejeitados` item a item na UI (mostre o `motivo`) — um evento inválido no lote
  não invalida os demais.
- **Corrigir um evento já enviado = mandar um `estorno` novo, nunca editar/reenviar o
  mesmo uuid.** Na UI, ofereça "desfazer" como uma ação que gera esse evento, nunca como
  DELETE de algo já enviado (não existe esse endpoint).
- Se o Node mantém um placar local otimista para responsividade da tela, **reconcilie com
  a resposta da API** em vez de confiar só no cálculo local, especialmente depois de
  reconectar.

### Papel do Socket.IO

A API Laravel não sabe nada de WebSocket — é o Node que:
1. Recebe o evento da UI do operador.
2. Aplica otimisticamente no estado local (placar, cronômetro) e emite via Socket.IO para
   telões/telas espectadoras — **antes** de esperar a resposta HTTP, para não travar a
   experiência ao vivo.
3. Manda o `POST /jogos/{jogo}/eventos` (em lote, com debounce/buffer curto se fizer
   sentido) como fonte de verdade persistida.
4. Se a API rejeitar o evento ou a chamada falhar, reconcilia: desfaz o efeito otimista ou
   avisa o operador — decida a UX, mas não deixe o estado do Node divergir silenciosamente
   do que ficou gravado no backend.
5. Numa reconexão/reload no meio do jogo: chame `GET /jogos/{jogo}` de novo e use
   `ultima_sequencia` para retomar o contador — não confie em estado em memória perdido.

### Outros endpoints (leitura, sem regra de negócio para o Node reimplementar)

| Rota | Uso no Node |
|---|---|
| `GET /placar/ping` | health-check do token |
| `GET /placar/modalidades` | as 3 fixas — prefira ler daqui em vez de fixar no Node |
| `GET /placar/equipes`, `/times`, `/times/{time}/elenco` | telas de seleção "escolher jogo existente" |
| `GET /placar/jogos?status=&data=&modalidade=&competicao_id=` | lista de jogos do dia/agenda |
| `GET /placar/jogos/{jogo}/sumula` | súmula completa — útil para um resumo pós-jogo |
| `GET /placar/scout/artilharia`, `/scout/jogadores/{jogador}` | provavelmente fora do escopo do placar ao vivo, mas disponível se a UI quiser destaque |
| `POST .../logo\|foto` | upload — multipart (`arquivo`) ou `arquivo_base64`, nunca os dois juntos |

### Erros — como o Node deve reagir

- **401** → token ausente/inválido: pare tudo, avise operador, não deixe a UI parecer
  "funcionando offline".
- **403** → token sem a ability certa: erro de configuração, não é algo para retry.
- **404** → jogo/time/jogador/equipe inexistente: normalmente bug de estado local do Node
  (ids dessincronizados), não do usuário.
- **422** → payload malformado da própria chamada (não confundir com `rejeitados` de um
  evento dentro do lote, que vem em `200`).
- **429** → 300 req/min excedido: sinal de que o Node está mandando eventos um a um em vez
  de em lote — corrija o buffer, não o limite.
