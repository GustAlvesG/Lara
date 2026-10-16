# Placar Clube — resumo para quem vai mexer

Documentação completa: [docs/placar-clube-api.md](../placar-clube-api.md) (contrato da API,
eventos, scout, mídia) e [docs/placar-clube-integracao-node.md](../placar-clube-integracao-node.md)
(o lado do Node). Modelo de dados em [docs/models.md](../models.md) (seção "Placar Clube").

## Em uma frase

Cadastro esportivo (equipes, times, jogadores, competições, jogos) e **log de eventos** das
partidas: o **placar eletrônico** (Node, Express + Socket.IO) lê o jogo daqui, manda os lances em
lote e o Lara é a fonte da verdade; as telas web cadastram e mostram súmula e scout.

## Telas e permissões

| Aba | Rotas | Permissão |
|---|---|---|
| Equipes, Times (+ elenco), Jogadores (+ importação), Competições, Jogos (+ escalação) | `placar.equipes.*`, `placar.times.*`, `placar.jogadores.*`, `placar.competicoes.*`, `placar.jogos.*` | `placar.cadastro` |
| Súmulas (Scout) | `placar.scout.*` (lista, súmula, impressão, atuação, jogador, time) | `placar.scout` |

Cadastro escreve, scout só lê — por isso duas permissões. Cuidado com a ordem das rotas:
`jogadores/import` e `jogos/{jogo}/escalacao` ficam **antes** do resource, e `->parameters()`
corrige o singular (`jogadores` → `{jogador}`, `competicoes` → `{competicao}`).

Não há rota `edit`: o `show` de cada cadastro **é** o formulário de edição. Por isso o `store`
não redireciona para o `show`. Todo `store` web termina em
`RedirecionaAposCadastro::aposCadastrar()` (`Placar/Web/Concerns`), que lê `acao` do request:
`salvar` (ou ausente) volta para a lista, e `continuar` reabre o `create` com
`withInput($manter)`. Nunca use `withInput()` sem campos, porque devolveria o nome do registro
criado. O atalho do próximo passo vai em `success_link` (`['url', 'label']`), que o
`partials/alerts` mostra embaixo do aviso de sucesso. Os botões são o partial
`placar/partials/botoes-cadastro` ("Cadastrar" vem primeiro no HTML para o Enter acioná-lo).
Guia do usuário: [funcionalidades/placar-clube.md](../funcionalidades/placar-clube.md).

Novo Jogo: `JogoController::create` monta `$categorias` (agrupadas por
`CategoriaService::chave`, cada uma com suas modalidades). A tela filtra times e competições
com `<template x-if>` em volta de cada `<option>`, não com `x-show`, que o Safari ignora em
`<option>`. O rótulo do time é `Time::rotuloParaEscolha()`, que sempre traz a categoria. O
campo `categoria` do formulário é só filtro: não é validado nem gravado (a regra de mesma
categoria continua no `store`).

## API para o Node (`/api/placar/*`)

- Sanctum, token pessoal do `ApiCliente`, ability única `placar:operar`
  (`PlacarAbilities::OPERAR`), `throttle:300,1`. Token: `php artisan placar:token {nome}`.
- Respostas **sem envelope** (`JsonResource::withoutWrapping()` global). Mídia sempre em URL
  absoluta (`ImagemService::url()`), arquivo estático em `public/storage/placar/…` (range request
  para o vídeo do jogador).
- Dois modos: **planejado** (tudo cadastrado na web) e **avulso** (o Node cria equipe → time →
  jogador → jogo; nasce `criado_em_campo = true` para revisão).
- Ciclo: `iniciar` (idempotente, devolve `ultima_sequencia`) → `escalacao` (substitui a do time)
  → `eventos` → `encerrar` (idempotente; recalcula pelo log e anota divergência em `observacoes`).

## Eventos — as regras que mais pegam

- `POST /jogos/{jogo}/eventos` (`JogoEventoLoteService`): `uuid` e `sequencia` vêm do Node.
  **Idempotente por `uuid`** (tenta o INSERT e captura o unique). Evento inválido vai para
  `rejeitados` e **não derruba o lote**. Sem limite de tamanho de lote, de propósito.
- `ponto` e `falta` **exigem `cronometro_ms`** (o scout mede a partida). `timeout` e
  `substituicao` exigem `time_id` e `periodo`; substituição exige `payload.sai_jogador_id` /
  `entra_jogador_id`.
- Regras por esporte num lugar só: `ModalidadeRegras` (valor de ponto, `set` só no vôlei, vôlei
  sem falta, limites de tempo/substituição — **informativos**, a API não recusa). Rótulos em
  `Vocabulario` (Gol, Cesta de 3, quarter, set).
- **Log append-only**: corrige-se com evento `estorno` apontando o uuid; o original continua na
  súmula marcado como estornado e sai dos totais.
- Quem está em quadra: `SituacaoDaPartidaService` (titulares + substituições na ordem). Não
  recalcule em outro lugar.
- Scout (`ScoutService`) lê **só** `jogo_eventos`. A unidade é a partida: não existe ranking de
  artilharia (foi removido). Súmula recorta por `time_id` e/ou `periodo`; `estornado` é apurado
  antes do recorte.
- Modalidades são três linhas fixas (`futsal`, `basquete`, `volei`); entrada aceita slug ou id.
- Categoria de time é texto livre normalizado por `CategoriaService` (a grafia já existente
  vence) — `times` tem UNIQUE (`equipe_id`, `modalidade_id`, `categoria`).

## Peças

| Arquivo | Papel |
|---|---|
| `app/Models/Placar/*` | `Equipe`, `Time`, `Jogador`, `Elenco`, `Competicao`, `Jogo`, `Escalacao`, `JogoEvento`, `Modalidade`, `ApiCliente`. |
| `app/Services/Placar/*` | Lote de eventos, regras, vocabulário, situação, scout, escalação, categoria, imagem e vídeo. |
| `app/Http/Controllers/Placar/Api/*`, `Placar/Web/*` | API do Node e telas. |
| `app/Support/Placar/PlacarAbilities.php` | Ability do token. |
| `resources/views/placar/*` | Telas (súmula impressa inclusive). |

Comandos: `placar:token {nome}`, `placar:migrar-midia [--dry-run]`.

## Testes

`tests/Feature/Placar/Api/*` (autenticação, eventos em lote, substituição/tempo, scout, mídia,
criação em campo), `tests/Feature/Placar/Web/*`, `tests/Feature/Placar/*` (súmula por período e
por time, importação, elenco), `tests/Feature/Placar/Web/CadastroEmSequenciaTest.php` (os dois
botões do cadastro), `tests/Unit/Placar/PlacarGatesTest.php`,
`tests/Feature/Rebrand/FreelancersPlacarScreensTest.php`. Confira o isolamento SQLite do
`phpunit.xml` antes de rodar e rode só a pasta: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Placar`.
