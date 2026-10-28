# Lara (IA) — o que a IA consulta no Lara

O agente de IA (VM separada, ver [lara-ia.md](lara-ia.md)) responde sobre o estatuto pela
própria base, e sobre o dia a dia do clube consultando **O Lara** por API. São duas buscas por
palavra-chave, ambas no grupo `api_token`:

| Endpoint | Rota | O que devolve | Quem pode ver |
|---|---|---|---|
| `GET /api/information/search` | `api.information.search` | Informações do InfoClube (atividades, serviços, horários, preços) | Todos — informação é pública |
| `GET /api/avisos/search` | `api.avisos.search` | Avisos do InfoClube (comunicados, mudanças, prazos) | **Só o que foi dirigido ao `user_id`** |

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

## Referência técnica

- Controllers: `InformationSearchController`, `AvisoSearchController`
- Requests: `KeywordSearchRequest` (base: palavra-chave, stopwords, 422 em JSON),
  `SearchInformationRequest`, `SearchAvisoRequest` (resolve o `user_id`)
- Apoio: `App\Support\KeywordSearch` (nota de relevância e remoção de HTML)
- Testes: `tests/Feature/InformationSearchTest.php`, `tests/Feature/AvisoSearchTest.php`
- Prompt para o agente da VM da IA: [lara-ia-busca-avisos-prompt.md](lara-ia-busca-avisos-prompt.md)
