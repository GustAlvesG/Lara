# Placar Clube — API

API consumida pelo Node do placar eletrônico ao vivo (Express + Socket.IO), rodando dentro deste mesmo Laravel — não é um serviço separado. O Node nunca guarda estado de negócio: ele lê o jogo daqui, manda o log de eventos conforme a partida acontece, e este backend é a fonte de verdade do que foi jogado.

Módulo completo em `app/Models/Placar`, `app/Services/Placar`, `app/Http/{Controllers,Requests,Resources}/Placar`. Modelo de dados documentado em [docs/models.md](models.md) (seção "Placar Clube").

## Autenticação

Sanctum, token de acesso pessoal, sem sessão/CSRF. Uma única ability, `placar:operar` (`App\Support\Placar\PlacarAbilities::OPERAR`) — não há granularidade menor porque é sempre o mesmo Node falando com a API.

Gerar um token:

```
php artisan placar:token node-producao
```

O nome (`node-producao` no exemplo) identifica o `ApiCliente`, não o token — rodar de novo com o mesmo nome reaproveita o cliente e emite um token novo, sem revogar o anterior. O comando imprime:

```
LARAVEL_API_TOKEN=1|AbCdEf...
```

Cole em `LARAVEL_API_TOKEN` no `.env` do Node. Toda requisição:

```
Authorization: Bearer {{token}}
```

Sem token, ou token sem a ability `placar:operar` → `401`/`403`. Rate limit: **300 requisições/minuto** por token (`throttle:300,1`) — generoso de propósito, porque o Node manda eventos em lote, não um por request.

## Convenções

- Todas as rotas ficam sob `/api/placar` (prefixo `api` vem do `bootstrap/app.php`, `placar` do grupo de rotas).
- Resposta sem envelope `{"data": ...}` — `JsonResource::withoutWrapping()` está ativo globalmente (`AppServiceProvider::boot()`). O que os exemplos abaixo mostram é o corpo exato da resposta.
- `logo_url`, `foto_url` e `video_url` são **sempre URLs absolutas**, resolvidas no servidor (`ImagemService::url()`) — nunca base64, nunca path relativo. Um time sem logo própria herda a da equipe (`Time::logoUrl()`); se nenhuma existir, o campo vem `null`.
- Toda a mídia é **arquivo estático** em `public/storage/placar/…`, servido pelo próprio servidor web sem passar por PHP. Isso importa para o telão: além de ser mais rápido, dá *range request* nativo, que é o que permite buscar/seekar o vídeo do jogador. Ver "Mídia" no fim deste documento.
- `criado_em_campo` (boolean) marca todo registro criado pelo modo avulso — é o que a tela web usa para destacar "revisar depois".
- Datas de entrada/saída em ISO 8601 (`data_hora`, `ocorrido_em` nas respostas) ou `"Y-m-d H:i:s.v"` (`ocorrido_em` nos eventos enviados, com milissegundo).
- Modalidades são 3 linhas fixas: `futsal`, `basquete`, `volei` (`Modalidade::FUTSAL/BASQUETE/VOLEI`). Qualquer campo `modalidade` na entrada aceita o **slug ou o id** (`Modalidade::resolver()`).

## Modos de operação

- **Planejado**: equipes, times, jogadores, elencos e o jogo já existem (cadastrados pela tela web ou por outra chamada da API). O Node só lê e opera.
- **Avulso**: o jogo não foi planejado. O Node cria equipe → time → jogador → jogo em até quatro chamadas (seção "Criação em campo" abaixo) e segue direto para o ciclo de vida do jogo. Tudo criado nesse modo nasce com `criado_em_campo = true` e validação mínima — alguém revisa/completa depois pela tela web.

## Rotas

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
| GET | `/placar/jogos/{jogo}/jogadores/{jogador}/atuacao` | Ficha minutada do jogador nesta partida |
| GET | `/placar/scout/jogadores/{jogador}` | Partidas em que o jogador atuou (`temporada`) |
| POST | `/placar/jogadores/{jogador}/video` | Envia/substitui o vídeo de apresentação |
| DELETE | `/placar/jogadores/{jogador}/video` | Remove o vídeo |

## `GET /placar/jogos/{jogo}` — exemplo completo

O Node monta a tela do placar com uma chamada só: dados do jogo + elenco operacional dos dois times (a escalação, se já foi feita; senão o elenco da temporada corrente — `Jogo::elencoOperacionalDoTime()`).

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

`POST /placar/jogos` (criação em campo) devolve exatamente este mesmo formato — o Node segue direto para o jogo recém-criado, sem uma segunda chamada.

## Ciclo de vida do jogo

1. **`POST /jogos/{jogo}/iniciar`** — `{ "operador"?: string }`. Idempotente: se já está `ao_vivo`, só devolve o estado atual. Resposta inclui `ultima_sequencia` (a maior `sequencia` já registrada) para o Node retomar o cronômetro/contador depois de uma queda de conexão.
2. **`POST /jogos/{jogo}/escalacao`** — `{ time_id, jogadores: [{ jogador_id, numero, titular?, capitao? }] }`. `time_id` precisa ser um dos dois times do jogo. **Substitui por completo** a escalação anterior daquele time (não faz merge).
3. **`POST /jogos/{jogo}/eventos`** — ver seção dedicada abaixo.
4. **`POST /jogos/{jogo}/encerrar`** — `{ placar_casa, placar_fora, sets_casa?, sets_fora?, periodos_jogados? }`. Idempotente: jogo já `encerrado` só devolve o estado atual, sem reprocessar. Na primeira vez, recalcula o placar a partir do log de eventos (`Jogo::calcularPlacar()`) e, se divergir do que foi enviado, registra a divergência em `observacoes` — **o log de eventos manda**, mas a divergência não passa em branco.

## O endpoint de eventos (`POST /jogos/{jogo}/eventos`)

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
| `cronometro_ms` | **obrigatório em `ponto` e `falta`**, opcional nos demais | tempo do **cronômetro da partida**, não o relógio de parede |
| `ocorrido_em` | obrigatório | qualquer formato que `Carbon::parse()` aceite |
| `payload` | opcional, livre | usado por `estorno` (ver abaixo) |

**Regras por modalidade** (`ModalidadeRegras`, único ponto de verdade): `set` só é válido em vôlei; `falta` não existe em vôlei; o valor de `ponto` varia conforme a tabela acima.

**Minutagem obrigatória** (`JogoEvento::TIPOS_COM_MINUTAGEM`): `ponto` e `falta` **exigem `cronometro_ms`** — sem o instante da partida o lance não é aproveitável pelo scout, que mede atuação por partida. Evento sem minutagem entra em `rejeitados` com o motivo (`"'ponto' exige cronometro_ms (minutagem na partida)"`) e **não derruba o resto do lote**. Valor negativo também é rejeitado. Os demais tipos (`crono_play`, `timeout`, `substituicao`, …) seguem sem exigir o campo.

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

- **Idempotência por `uuid`**: reenviar o mesmo lote (retry de rede, fila offline que sincronizou de novo) devolve os mesmos uuids em `duplicados` — nada é inserido de novo. Resolvido tentando o `INSERT` e capturando a violação de unique (não pré-checando), o que também cobre a corrida de dois envios quase simultâneos do mesmo lote.
- **Um evento inválido não derruba o lote**: cada evento é validado individualmente; o que falhar entra em `rejeitados` com o motivo, os demais seguem normalmente.
- **Sem limite de tamanho de lote** — decisão explícita, mesmo a especificação original sugerindo até 200: o Node pode precisar mandar lotes maiores (reenvio de fila offline acumulada, por exemplo).
- Um lote com `ponto`/`set`/`estorno` aceito recalcula e grava o cache `placar_casa`/`placar_fora`/`sets_casa`/`sets_fora` em `jogos` (lotes só com `crono_play`/`timeout`/etc. não tocam nesse cache).

### Corrigindo um evento (`estorno`)

O log é **append-only** — nunca há `UPDATE`/`DELETE` num evento já gravado. Para corrigir (ponto lançado errado, falta no jogador errado etc.), manda-se um evento novo do tipo `estorno` referenciando o uuid original:

```json
{
    "uuid": "b1e6a1b1-4b8a-4b1a-9c3a-000000000005",
    "sequencia": 5,
    "tipo": "estorno",
    "ocorrido_em": "2026-08-06 19:36:00.000",
    "payload": { "evento_uuid": "b1e6a1b1-4b8a-4b1a-9c3a-000000000002", "motivo": "ponto lançado para o jogador errado" }
}
```

O evento original some do placar, dos totais e do scout, mas **continua aparecendo na súmula/timeline**, marcado como estornado — é histórico, não é apagado.

## Scout (`ScoutService`)

Camada de leitura que lê **só** de `jogo_eventos`, nunca de campo denormalizado nem de tabela de estatística. Único ponto de leitura dessas visões, reaproveitado pela API e pelas telas web.

> **A unidade do scout é a partida, não o ranking entre partidas.** Não existe endpoint de artilharia — `GET /scout/artilharia` foi **removido**. O que se mede é a atuação de um jogador num jogo específico, e por isso **todo `ponto` e toda `falta` exigem `cronometro_ms`** (ver a seção de eventos).

- **`GET /jogos/{jogo}/sumula`** — placar por período/set, timeline cronológica completa (com jogador, **número**, foto, e a marca `estornado`), totais de pontos/faltas por jogador de cada time (com o número de cada um). O número é o da escalação deste jogo, se já foi feita; senão o do elenco da temporada corrente — mesma prioridade de `Jogo::elencoOperacionalDoTime()`.
- **`GET /jogos/{jogo}/jogadores/{jogador}/atuacao`** — **a visão central do scout**: a ficha do jogador nesta partida. Traz `totais` (`pontos`, `faltas`, `lances`) e a lista `lances`, cada um com `minuto` ("MM:SS"), `cronometro_ms` cru, `periodo`, `valor` e `estornado`. Lance estornado **continua na ficha**, marcado, mas fora dos totais.

  ```json
  {
      "jogo": { "id": 12, "esporte": "futsal", "placar_casa": 3, "placar_fora": 1, "...": "..." },
      "jogador": { "id": 1, "numero": "10", "nome_exibicao": "Carlos Souza", "foto_url": "...", "time_id": 1 },
      "totais": { "pontos": 2, "faltas": 1, "lances": 3 },
      "lances": [
          { "sequencia": 2, "tipo": "ponto", "valor": 1, "periodo": 1, "cronometro_ms": 754000, "minuto": "12:34", "ocorrido_em": "...", "estornado": false }
      ]
  }
  ```

- **`GET /scout/jogadores/{jogador}?temporada=`** — as partidas em que o jogador atuou, da mais recente para a mais antiga, com `pontos` e `faltas` dele em cada uma. É a porta para a ficha minutada acima, não um ranking. Sem lances, `partidas` vem `[]`.

## Criação em campo (modo avulso)

Quatro chamadas, cada uma tolerante (validação mínima) e sempre `criado_em_campo = true`:

1. **`POST /equipes`** — `{ nome, nome_curto?, cidade? }`.
2. **`POST /times`** — `{ equipe_id? | equipe_nome?, modalidade, categoria? }`. Um dos dois (`equipe_id` OU `equipe_nome`) é obrigatório; sem `equipe_id`, a equipe é criada junto (`firstOrCreate` por nome — não marca `criado_em_campo` se ela já existia). `categoria` default `"Adulto"`. `firstOrCreate` também no time: reenviar a mesma criação não duplica.

   **A categoria é normalizada** (`CategoriaService`): `"Sub 15"`, `"sub15"` e `"SUB-15"` caem no time que já existe como `"Sub-15"` em vez de criar duplicatas — a grafia **já cadastrada vence**, e só categoria realmente nova recebe a grafia canônica. Sem isso, cada variação furava o `UNIQUE (equipe_id, modalidade_id, categoria)`. Resposta é `201` quando cria e `200` quando reaproveita.
3. **`POST /jogadores`** — `{ nome, nome_exibicao?, time_id?, equipe_id?, modalidade?, numero?, temporada? }`. Sem foto, data de nascimento ou documento — nada disso é essencial para entrar em quadra. Com `time_id`, já cria o vínculo em `elencos` na mesma transação.

   **O jogador pertence a uma equipe e uma modalidade.** Com `time_id`, as duas são herdadas do time (o caminho normal em campo). Sem `time_id`, **`equipe_id` e `modalidade` passam a ser obrigatórios** (`422` se faltarem) — senão o cadastro nasceria incompleto e o jogador não poderia entrar em time nenhum. Informar `equipe_id`/`modalidade` divergentes do `time_id` também é `422`, em vez de escolher um dos dois em silêncio.
4. **`POST /jogos`** — `{ modalidade, time_casa_id, time_fora_id, data_hora?, local?, competicao_id? }`. `data_hora` default agora. Devolve o mesmo payload de `GET /jogos/{id}` — o Node segue direto para o jogo, sem chamada extra.

   Duas validações de cruzamento, ambas `422`:
   - a modalidade do jogo precisa bater com a dos **dois** times;
   - os dois times precisam ser da **mesma categoria** — Sub-15 não joga contra Adulto. Comparado pela chave normalizada, então um `"Sub 15"` legado de um lado e `"Sub-15"` do outro **não** são bloqueados (são a mesma categoria).

## Mídia (onde os arquivos ficam)

Toda a mídia do Placar vive em **`public/storage/placar/…`** e é servida como arquivo estático pelo servidor web.

Isso **não** usa o `storage:link` do Laravel, de propósito: neste projeto `public/storage` já é um diretório real com arquivos de outras áreas do sistema, e o symlink nunca existiu — rodar `storage:link --force` apagaria esse conteúdo. Enquanto o Placar gravava em `storage/app/public` (disco `public`), toda logo e foto ia para um lugar que nenhuma URL alcançava e **o link quebrava**. O disco `placar` (`config/filesystems.php`) resolve isso apontando para dentro de `public/`.

Duas consequências que interessam ao telão: a mídia não passa por PHP a cada requisição, e o servidor web responde *range request* nativamente — que é o que permite o vídeo do jogador ser buscado/seekado.

A URL é montada por `ImagemService::url()` com `asset()`, e **não** com `Storage::url()`: o disco `public` monta a URL a partir de `APP_URL` fixo no `.env`; servido em qualquer outro host/porta, todo link sairia errado. `asset()` resolve pela request em curso.

Se algum ambiente já recebeu upload antes dessa correção, rode uma vez:

```
php artisan placar:migrar-midia          # --dry-run para só listar
```

Idempotente, nunca sobrescreve arquivo existente no destino e só apaga a origem depois de confirmar a cópia.

## Vídeo do jogador

`POST /placar/jogadores/{jogador}/video`, multipart no campo **`video`**. O telão usa foto e vídeo em momentos diferentes (foto na escalação/súmula, vídeo na entrada em quadra), então os dois convivem e `video_url` vem junto de `foto_url` no payload do jogo — sem segunda chamada.

- **mp4 ou webm**, até **28MB**.
- Não há transcodificação (exigiria ffmpeg, que o projeto não tem): por isso o formato é restrito ao que todo navegador toca nativamente.
- O tipo é detectado pelos **bytes reais** do container (box `ftyp` do ISO BMFF; magic EBML do Matroska) — nunca pela extensão do nome nem pelo `Content-Type`. Um `.mp4` que não seja vídeo é recusado com `422`.
- **Não aceita base64** (ao contrário das imagens): base64 infla ~33% e estouraria o `post_max_size` (30M) bem antes do limite útil.
- Enviar outro vídeo substitui o anterior, inclusive trocando de formato, sem deixar arquivo órfão. `DELETE /placar/jogadores/{jogador}/video` remove sem substituir — e não mexe na foto.

Para aceitar vídeos maiores é preciso subir `upload_max_filesize` e `post_max_size` no `php.ini` do servidor (hoje ambos em 30M) e, depois, `VideoService::TAMANHO_MAXIMO_BYTES`.

## Imagens (logo / foto)

`POST` em `/equipes/{equipe}/logo`, `/times/{time}/logo` e `/jogadores/{jogador}/foto`. Aceita **um dos dois**, nunca ambos:

- `arquivo` — multipart, `jpg`/`jpeg`/`png`/`webp`, até 8MB.
- `arquivo_base64` — data URL (`data:image/png;base64,...`).

Processado por `ImagemService` (GD puro, sem dependência nova) — o formato real dos bytes é detectado pelos magic bytes, não pelo `Content-Type` nem pelo prefixo declarado no base64:

- **Logo** (equipe/time): redimensiona **proporcionalmente** até 512×512 — não corta.
- **Foto** (jogador): redimensiona para 512×512 com **recorte central quadrado**.

Enviar uma nova imagem substitui a anterior (o arquivo antigo é removido do disco). `DELETE` remove sem substituir. Resposta é sempre o Resource do recurso (equipe/time/jogador) com `logo_url`/`foto_url` já atualizada.

## Erros

- **401** — sem token, ou token inválido/revogado.
- **403** — token válido sem a ability `placar:operar`.
- **404** — id inexistente na rota (`{equipe}`, `{time}`, `{jogo}`, `{jogador}`).
- **422** — validação de request falhou (corpo malformado, campo obrigatório ausente, `exists:` não satisfeito, mimetype de imagem inválido etc.). **Não é assim** que um evento individual inválido dentro de um lote é reportado — isso vai em `rejeitados` na resposta `200`, ver acima.
- **429** — mais de 300 requisições/minuto para o mesmo token.

## Cadastro de jogadores (tela web)

O jogador pertence a **uma equipe e uma modalidade**. Ele pode estar em vários times daquela equipe — Sub-15 e Adulto, por exemplo —, mas nunca em time de outra equipe ou de outra modalidade. A ficha do time só oferece jogadores elegíveis, e o vínculo é recusado se a regra não bater.

Equipe e modalidade ficam travadas na edição enquanto o jogador estiver em algum elenco: trocá-las deixaria para trás vínculos com times da equipe/modalidade antiga. Basta removê-lo dos elencos para poder alterar.

### Importação em massa por planilha

Em **Placar Clube → Jogadores → Novo Jogador**, o bloco "Importar por planilha" traz o botão **Baixar modelo** (`modelo-importacao-jogadores.xlsx`), gerado na hora a partir do próprio código — então nunca fica dessincronizado das colunas aceitas.

| Coluna | Obrigatória | Observação |
|---|---|---|
| Nome | sim | |
| Equipe | sim | pelo **nome** ou nome curto; precisa já estar cadastrada |
| Modalidade | sim | `futsal`, `basquete` ou `volei` (aceita o nome também) |
| Nome no telão | não | se vazio, usa o nome completo |
| Data de nascimento | não | `dd/mm/aaaa` ou `aaaa-mm-dd` |
| Documento | não | não pode repetir dentro do arquivo |
| Categoria do time | não | **preenchida, já coloca o jogador no elenco** desse time |
| Número da camisa | não | exige "Categoria do time" preenchida |
| Posição | não | exige "Categoria do time" preenchida |
| Temporada | não | default: ano corrente |

- **Tudo-ou-nada**: havendo erro em qualquer linha, nada é gravado e a tela lista todos os problemas com o número da linha. Assim ninguém importa meio elenco e reenvia o arquivo duplicando o resto.
- O **time é criado** se ainda não existir para aquela equipe/modalidade/categoria — importar um elenco inteiro não exige cadastrar o time antes, à mão.
- A categoria passa pelo `CategoriaService`: `"Sub 15"` na planilha cai no `"Sub-15"` que já existe.
- **Imagem e vídeo ficam de fora** de propósito: são upload, não célula de planilha, e continuam sendo enviados pela ficha do jogador.

## Massa de demonstração e Postman

- `php artisan db:seed --class=PlacarDemoSeeder` — duas equipes, cada uma com time de futsal adulto, basquete adulto e basquete Sub-15 (10 jogadores cada), uma competição e três jogos agendados (um por combinação de modalidade/categoria). Idempotente — pode rodar de novo sem duplicar.
- [`docs/postman/placar-clube.postman_collection.json`](postman/placar-clube.postman_collection.json) — collection completa: diagnóstico, leitura, ciclo de vida do jogo (planejado), modo avulso de ponta a ponta e upload de imagens. Configure `{{base_url}}` e `{{token}}` nas variáveis da collection (ou crie um Environment) antes de rodar.
