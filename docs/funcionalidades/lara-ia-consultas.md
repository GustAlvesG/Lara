# Lara (IA) — o que a IA consulta no Lara

O agente de IA (VM separada, ver [lara-ia.md](lara-ia.md)) responde sobre o estatuto pela
própria base, e sobre o dia a dia do clube e o uso do sistema consultando **O Lara** por API.
São três buscas por palavra-chave, todas no grupo `api_token`:

| Endpoint | Rota | O que devolve | Quem pode ver |
|---|---|---|---|
| `GET /api/information/search` | `api.information.search` | Informações do InfoClube (atividades, serviços, horários, preços) | Todos — informação é pública |
| `GET /api/avisos/search` | `api.avisos.search` | Avisos do InfoClube (comunicados, mudanças, prazos) | **Só o que foi dirigido ao `user_id`** |
| `GET /api/guias/search` | `api.guias.search` | Seções dos guias do usuário (como usar O Lara), desde 28/10/2026 | Todos; cada seção diz se o `user_id` **alcança** a tarefa |

E, no sentido inverso, desde 28/10/2026 o Lara manda em `POST /perguntar` o **contexto da tela**
em que a pessoa está (ver "Contexto da tela" no fim).

## Autenticação

`Authorization: Bearer {API_TOKEN}` — o mesmo token das outras integrações
(`config('services.api.token')`, middleware `APIToken`). Sem token ou com token errado: **401**.

## Parâmetros comuns

- `q` (ou `keyword`, ou `palavra_chave`): a frase em linguagem natural, 2 a 100 caracteres.
  A frase é reduzida a palavras-chave (stopwords de `config/search.php` caem fora) e cada termo
  é buscado com OR. O resultado vem ranqueado por `relevance` — soma de pesos por termo e campo —,
  depois pelo mais recente.
- Validação falhou: **422** com `{success: false, message, errors: {campo: [mensagem]}}`.

## Busca de informações

`GET /api/information/search?q=horário da natação[&all_versions=1]`

Pesos: nome 3, tag/categoria 2, descrição 1. Só a versão vigente de cada informação, salvo
`all_versions=1`. Descrição em texto puro. Sem paginação: devolve tudo o que casou. Versão ou
informação apagada (soft delete) nunca volta. Detalhes em [informacoes.md](informacoes.md).

### Campos de cada item

O item é a linha de `data_infos` serializada inteira (não há lista explícita como nos avisos):

| Campo | Significado |
|---|---|
| `name`, `description` | Nome e descrição (texto puro; às vezes é um texto longo colado de planilha). |
| `status` | **Texto livre** digitado por quem cadastra (ex.: "Inscrições abertas"). Não é um código: não existe regra de "inativo/suspenso" no Lara. Informação que deixou de valer é apagada, e aí não volta na busca. |
| `slots` | Número de vagas (texto numérico ou nulo). |
| `fee` | Taxa de matrícula (decimal ou nulo). |
| `location` | Local. |
| `name_price`, `price_associated`, `price_not_associated` | Listas **paralelas** separadas por `;` (sempre com `;` no fim): o pacote *i* tem título `name_price[i]`, valor sócio `price_associated[i]` e não sócio `price_not_associated[i]`. O primeiro é o exibido no card. |
| `responsible`, `responsible_contact` | Listas paralelas por `;`: nome e telefone de cada responsável. |
| `day_hour` | Lista por `;` de `dia,início,fim`. `";"` sozinho = sem horário cadastrado. Registros antigos podem ter menos de três partes. |
| `category` | Legado (substituída pelas tags); só registros antigos têm. |
| `tags` | `[{id, name, pivot}]` — use só `name`. |
| `information` | O agrupador: `{id, privacy, created_by, ...}`. `privacy` não é usado (tudo é público). `information_id` agrupa as versões. |
| `user` | `{id, name}` de quem gravou a versão. |
| `relevance` | Nota da busca (soma dos pesos por termo e campo). Serve só para ordenar; não tem escala fixa. |
| `created_at` | Data da versão, em **UTC** (`...Z`, serialização padrão do Laravel). |

> Atenção às datas: na busca de **informações** o `created_at` vem em UTC (`Z`); na de
> **avisos**, `created_at`/`updated_at` vêm no fuso do Lara (`-03:00`). Leia o offset em vez de
> supor um dos dois.

## Busca de avisos

`GET /api/avisos/search?q=piscina fechada&user_id=portal_12[&include_expired=1]`

### Quem pergunta: `user_id`

Obrigatório. O aviso tem privacidade, então a busca precisa saber em nome de quem responde.

- Aceita `user_id` ou `usuario_id`, com ou sem o prefixo `portal_` — é o **mesmo identificador**
  que o Lara manda para a IA em toda pergunta (`LaraClient`: `usuario_id: "portal_{id}"`). A IA só
  precisa repassar o que recebeu.
- Usuário inexistente ou apagado: **422** `errors.user_id = ["Usuário não encontrado."]`. Não há
  "modo anônimo" que devolva só os públicos: sem saber quem pergunta, a busca não responde.

### Régua de visibilidade

`Aviso::scopeAddressedTo($user)` — a mesma da notificação e da leitura obrigatória:

| Privacidade | Quem recebe na busca |
|---|---|
| `publico` | Todos |
| `setor` | O autor e quem divide ao menos um setor com ele |
| `grupo` | O autor e os usuários escolhidos (`aviso_user`) |
| `pessoa` | Só o autor |

**Não** é `scopeVisibleTo`: na tela, quem tem acesso total (Gerência, Diretoria, TI) enxerga todo
aviso do sistema, mas a IA não repete para essa pessoa o aviso pessoal de outra só porque ela
poderia abri-lo. Na busca, acesso total não amplia nada.

Confiança: o `user_id` vem da VM da IA, que só é alcançável pela rede interna (ver
[lara-ia.md](lara-ia.md)); quem tem o token pode consultar em nome de qualquer usuário. É a mesma
confiança que o Lara já deposita no token para as outras integrações.

### Filtros e pesos

- Pesos: título 3, tag 2, conteúdo 1.
- Por padrão só avisos **ativos** (sem validade ou com validade de hoje em diante).
  `include_expired=1` traz também os vencidos, com `expired: true`.
- Avisos apagados (soft delete) nunca voltam.

### Resposta

```json
{
  "keyword": "piscina fechada",
  "terms": ["piscina", "fechada"],
  "user_id": 12,
  "include_expired": false,
  "total": 1,
  "data": [
    {
      "id": 87,
      "title": "Piscina fechada para manutenção",
      "content": "A piscina fica fechada de 03 a 05/11 para troca do filtro.",
      "privacy": "publico",
      "mandatory": false,
      "expires_at": "2026-11-05",
      "expired": false,
      "created_at": "2026-10-28T09:12:00-03:00",
      "updated_at": "2026-10-28T09:12:00-03:00",
      "author": "Fulana da Manutenção",
      "tags": ["manutenção", "piscina"],
      "url": "https://<lara>/avisos/87",
      "relevance": 6
    }
  ]
}
```

`content` vem em texto puro (o editor grava HTML). A lista de campos é **explícita** no
controller: destinatários, visualizações e ciências do aviso não saem pela API.

## Busca de guias (como usar O Lara)

`GET /api/guias/search?q=como emito uma cortesia&user_id=portal_12[&modulo=Ingressos][&limit=6]`

Busca nos **guias do usuário** (`docs/guias/*.md`, um por módulo do menu, mais "Primeiros
passos"), quebrados em seções pelos títulos `##`/`###`. É a fonte para "como faço…", "onde
fica…", "por que não consigo…".

### Parâmetros

- `q` e `user_id` como nas outras (o `user_id` aceita `portal_{id}`; inexistente → 422).
- `modulo` (opcional): o módulo da tela em que a pessoa está — o `contexto.modulo` que a IA recebe
  em `/perguntar`. Só dá **preferência** às seções daquele módulo; não filtra.
- `limit` (opcional, 1 a 20, padrão 6): quantas seções devolver.

### Ranqueamento

Em PHP, sobre o texto **sem acento** e com radical simples (plural e flexão casam: "inscrições"
acha "inscrição", "busco" acha "buscar"). Pesos por termo: título da seção 4, módulo + título do
guia + `palavras` do cabeçalho 2, título de cima (`###` dentro de um `##`) 1, texto 1; +3 para o
`modulo` pedido. Empate: mais termos diferentes casados, depois a ordem do guia. Palavras de
pergunta de uso ("faço", "consigo", "sistema", "lara"…) e termos de menos de 3 letras são
descartados; pergunta sem nenhum termo útil devolve `total: 0`.

### Acesso — o que muda por `user_id`

Guia não é sigiloso (a tela Documentação abre para qualquer logado): **nenhuma seção é
escondida**. Cada seção diz se a pessoa **alcança** a tarefa:

- a seção cita telas do menu (`<!-- telas: -->` no guia) → `tem_acesso` se a pessoa abre alguma
  delas; `telas` traz os links das que ela abre;
- não cita → vale o módulo do guia inteiro (alguma tela do módulo), sem lista de links;
- guia fora do menu ("Primeiros passos") → todos.

Sem acesso, `precisa_de` diz o que pedir: `"SIV › Busca (permissão: SIV › Busca de placas)"`.
Quem libera permissão é a **TI ou a Gerência** (tela Setores, ou por pessoa em Usuários); o
**coordenador** só coloca a pessoa num setor que já tem a permissão (tela Meu setor).

### Resposta

```json
{
  "keyword": "como emito uma cortesia", "terms": ["emit", "cortesi"],
  "user_id": 12, "modulo": "Ingressos", "total": 2,
  "data": [{
    "guia": "Ingressos — guia de uso",
    "modulo": "Ingressos",
    "secao": "Ingressos — guia de uso › Emitir ingressos no balcão e cortesias",
    "texto": "Na tela Ingressos › Eventos, abra o evento…",
    "url": "https://<lara>/docs/guias/ingressos#emitir-ingressos-no-balcao-e-cortesias",
    "tem_acesso": true,
    "telas": [{"nome": "Ingressos › Eventos", "url": "https://<lara>/ingressos"}],
    "precisa_de": [],
    "relevance": 11
  }]
}
```

`texto` vem em texto puro (sem Markdown), até 3000 caracteres. `url` abre o guia na seção
(âncora). `terms` são os radicais usados.

## Contexto da tela (`POST /perguntar`)

Desde 28/10/2026 o balão da Lara manda a tela atual junto com a pergunta e O Lara repassa à IA:

```json
{
  "usuario_id": "portal_12",
  "mensagem": "como faço isso aqui?",
  "contexto": {"modulo": "Ingressos", "pagina": "Eventos", "caminho": "Ingressos › Eventos", "rota": "tickets.create"}
}
```

- **Opcional**: sem tela de módulo (Dashboard, telas fora do menu) o campo **não vai**.
- `modulo`/`pagina`/`caminho` saem do menu do Lara (`Navigation::destinations()`), não do
  navegador: o balão manda só nomes de rota (`rota` e a `aba` do menu) e `AskLaraRequest::contexto()`
  descarta o que não é rota existente — o navegador não consegue fazer chegar à IA um texto
  qualquer como contexto.
- `rota` é o nome técnico da página exata (`.create` = criando, `.edit` = editando, `.show` =
  vendo um registro). Serve de pista; não é para mostrar ao funcionário.

## Referência técnica

- Controllers: `InformationSearchController`, `AvisoSearchController`, `GuideSearchController`
- Requests: `KeywordSearchRequest` (base: palavra-chave, stopwords, 422 em JSON),
  `SearchInformationRequest`, `SearchAvisoRequest` e `SearchGuideRequest` (os dois resolvem o
  `user_id` pela trait `Concerns\ResolvesAskingUser`); `AskLaraRequest::contexto()`
- Apoio: `App\Support\KeywordSearch` (nota de relevância e remoção de HTML);
  `App\Services\Guides\UserGuides` (lê e recorta os guias, em cache até um arquivo mudar),
  `GuideSearch` (pontua e decide o acesso), `GuideMarkdown` (cabeçalho, anotações, âncoras —
  as mesmas da tela Documentação); `App\View\Navigation::destinations()`
- Testes: `tests/Feature/InformationSearchTest.php`, `tests/Feature/AvisoSearchTest.php`,
  `tests/Feature/GuideSearchTest.php`, `tests/Unit/UserGuidesTest.php`,
  `tests/Feature/UserGuidesContentTest.php` (confere os guias de verdade),
  `tests/Feature/LaraWidgetTest.php` e `tests/Unit/LaraClientTest.php` (contexto)
- Prompts para o agente da VM da IA: [lara-ia-busca-avisos-prompt.md](lara-ia-busca-avisos-prompt.md)
  (avisos) e [lara-ia-guias-prompt.md](lara-ia-guias-prompt.md) (guias e contexto da tela)
- Prompt para o agente da VM da IA: [lara-ia-busca-avisos-prompt.md](lara-ia-busca-avisos-prompt.md)
