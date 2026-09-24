# Prompt de ajuste — acender a luz da quadra (Next.js), 2ª rodada

> Documento de entrega para um agente Claude Code trabalhando **no repositório do
> aplicativo de reservas (Next.js)**, onde a tela de acionamento de luz **já
> existe**. Copie a partir de "Início do prompt" até o fim.
>
> É um documento de **mudança**, não de criação. O contrato completo, para quem
> for implementar do zero, está em
> [iluminacao-autoatendimento-prompt.md](iluminacao-autoatendimento-prompt.md);
> as regras de negócio, em
> [Automação Home Assistant](automacao-home-assistant.md#autoatendimento-do-sócio-fim-de-semana).

---

## Início do prompt

A tela de **acender a luz da quadra** já existe neste projeto. O backend (a
Lara) mudou duas vezes desde que ela foi escrita, e a tela ficou para trás. Você
vai atualizá-la.

**Leia esta seção inteira antes de abrir qualquer arquivo.** Parte das mudanças
quebra em silêncio — sem erro no console, sem tela branca, só comportamento
errado — e você não vai encontrá-las procurando o que está quebrado.

### As três mudanças de conceito

Antes dos campos, entenda o que mudou no produto. Se você ajustar só os nomes de
campo, a tela vai funcionar e continuar errada.

**1. A duração agora é escolha do sócio.**

Antes: acionar dava 2 horas fixas. Agora: o sócio diz quantos minutos quer, até
um teto (2 h) que o servidor informa. A tela precisa de um **seletor de
duração**.

**2. O acionamento pode ser prolongado, por qualquer sócio.**

Este é o ponto central. Um acionamento **não reserva a quadra** e a luz **não é
de ninguém**: acabando o tempo, qualquer sócio presente aciona de novo e a luz
continua acesa sem piscar — inclusive quem não acendeu da primeira vez. É o que
salva o jogo que passou do previsto.

Consequência direta: **quadra acesa deixou de ser motivo de recusa**. Se a tela
hoje desabilita ou esconde quadras acesas, está impedindo justamente o caso de
uso mais comum.

**3. Cada quadra tem o próprio horário.**

Quadra coberta escurece antes e abre mais cedo que a descoberta ao lado, no
mesmo dia. **Não existe mais "o horário do clube" para efeito de decisão**: às
15h de um sábado, a coberta está aberta e as outras não.

Toda a verdade de horário mudou de lugar: saiu da disponibilidade geral e foi
para **a lista de quadras**.

### Primeiro passo: inventário

Não presuma contra qual versão a tela foi escrita — houve duas rodadas de
mudança e a tela pode ter parado em qualquer uma delas. Antes de editar, faça um
levantamento e **me relate o que achou**:

1. Onde ficam os arquivos da funcionalidade (páginas, componentes, hooks, tipos,
   funções de chamada à API).
2. Faça grep por cada um destes nomes e anote onde aparecem:
   `duration_minutes`, `minimum_minutes`, `available_minutes`, `available`,
   `today_window`, `window`, `place_busy`, `lit`, `lit_until`, `extended`,
   `club_window`, `max_minutes`, `step_minutes`, `minutes`.
3. Diga quais dos itens da tabela de mudanças abaixo se aplicam ao que existe.

Só depois proponha o plano.

### Mudanças de contrato, campo a campo

Risco 🔴 = quebra em silêncio (nenhum erro, comportamento errado).
🟡 = quebra visível. 🟢 = só adiciona.

| Antes | Agora | Risco | O que acontece se você ignorar |
|-------|-------|:-----:|--------------------------------|
| `places[].available` (bool) | `places[].lit` (bool, **sentido invertido**) | 🔴 | `available` agora é `undefined` → sempre falso → **todas as quadras aparecem indisponíveis** e ninguém consegue acender nada. |
| `availability.window` | `availability.club_window` | 🟡 | `window` agora é `undefined`; ler `window.start` estoura. |
| `availability.today_window` | `availability.club_window` | 🟡 | idem. |
| `availability.available_minutes` | **mudou de lugar**: `places[].available_minutes` | 🔴 | Lido na disponibilidade vira `undefined` → o seletor de duração nasce sem máximo, ou com máximo zero. |
| `availability.duration_minutes` | `availability.max_minutes` | 🔴 | `undefined` vira "sem limite" ou `NaN` nos cálculos de tempo. |
| `availability.minimum_minutes` | `availability.min_minutes` | 🔴 | idem. |
| `reason: "place_busy"` | **não existe mais** | 🟡 | Tratamento vira código morto; pior, a tela pode estar bloqueando quadra acesa por conta própria. |
| `POST .../activate` respondia sempre `201` | `201` ao acender, **`200` ao prolongar** | 🔴 | `if (status === 201)` trata o prolongamento como falha, e o sócio vê erro depois de uma ação que deu certo. |
| — | `POST .../activate` aceita `{"minutes": 45}` | 🟢 | Sem mandar, o sócio sempre leva o teto. |
| — | resposta traz `extended: true|false` | 🟢 | |
| — | `activation.lit_until` | 🔴 | Ver "A armadilha do `ends_at`", abaixo. |
| — | `availability.step_minutes` | 🟢 | Passo do seletor (15 min). |
| — | `places[].open`, `places[].window`, `places[].next_window` | 🟢 | Sem eles, a tela não sabe o horário de cada quadra. |

**Não mudaram**: autenticação (`Authorization` + `Session`), as URLs, o formato de
data (ISO 8601 com offset de `America/Sao_Paulo`) e o endpoint `groups`.

**`POST /api/lighting/release` mudou de comportamento, não de formato**, e vale
um parágrafo: antes ele sempre apagava a luz. Agora, **se outro sócio prolongou,
a luz continua acesa** até o prazo dele — o `release` só encerra a participação
de quem chamou e libera a cota dele. Olhe o `lit_until` da resposta antes de
anunciar que apagou. O corpo continua sendo `{"activation": { … }}`, com a
ativação já encerrada (`minutes_remaining: 0`, `released_at` preenchido).

O objeto `activation` — devolvido por `availability`, `activate`, `release` e o
histórico — ganhou `lit_until` em todos eles.

### A armadilha do `ends_at`

`activation` agora tem dois prazos, e confundi-los é o erro mais fácil de
cometer:

- `ends_at` / `minutes_remaining` são **do acionamento deste sócio**;
- `lit_until` é **da quadra**, somando todos os sócios que acionaram.

Eles divergem sempre que outra pessoa prolongou depois. Exemplo real: a Ana
acende às 18:00 por 2 h (`ends_at` 20:00); às 19:30 o Bruno prolonga por 2 h. A
luz vai até 21:30, mas o `ends_at` da Ana continua 20:00.

**Para o contador de "quando a luz apaga", use `lit_until`.** Se a tela usar
`ends_at`, vai anunciar às 20:00 um apagão que não vai acontecer — e o sócio vai
sair da quadra achando que acabou.

`ends_at` serve para outra coisa: saber quando o sócio deixa de ocupar a própria
cota (ele só pode ter uma quadra por vez).

### O contrato atual, nos endpoints que mudaram

#### `GET /api/lighting/availability`

```json
{
  "now": "2026-09-26T18:00:00-03:00",
  "open": true,
  "next_window": {
    "date": "2026-09-26", "start": "17:00", "end": "23:00",
    "starts_at": "2026-09-26T17:00:00-03:00",
    "ends_at": "2026-09-26T23:00:00-03:00",
    "source": "weekly", "reason": null
  },
  "club_window": { "...": "o horário geral do clube hoje, ou null" },
  "max_minutes": 120,
  "min_minutes": 15,
  "step_minutes": 15,
  "activation": {
    "id": 1, "place_id": 10,
    "place_name": "Quadra 1", "place_group": "Beach Tennis",
    "starts_at": "2026-09-26T18:00:00-03:00",
    "ends_at": "2026-09-26T20:00:00-03:00",
    "minutes_remaining": 120,
    "lit_until": "2026-09-26T21:30:00-03:00",
    "released_at": null
  }
}
```

- `open` é **agregado**: "há alguma quadra aberta agora?". Use só para decidir se
  mostra o fluxo de escolha. Ele **não** diz que a quadra X está aberta.
- `next_window` é a **primeira** janela a abrir entre todas as quadras.
  `source` é `weekly` ou `date` (feriado liberado no painel); em `date`, `reason`
  traz o nome do feriado, e é um bom texto para mostrar.
- `club_window` serve só para frase genérica. **Não decida nada por ele.**
- `activation` é `null` quando o sócio não tem nada aceso.

#### `GET /api/lighting/groups/{group}/places`

**É aqui que mora a verdade sobre horário agora.**

```json
{
  "places": [
    {
      "id": 10, "name": "Quadra 1 (coberta)", "image": "1712...jpg",
      "open": true,
      "window": { "start": "14:00", "end": "23:00", "source": "weekly", "...": "..." },
      "available_minutes": 120,
      "next_window": { "...": "a próxima desta quadra" },
      "lit": true, "lit_until": "2026-09-26T20:00:00-03:00"
    },
    {
      "id": 20, "name": "Quadra 2", "image": null,
      "open": false, "window": null, "available_minutes": 0,
      "next_window": { "start": "17:00", "...": "..." },
      "lit": false, "lit_until": null
    }
  ]
}
```

- `available_minutes` é **o máximo do seletor de duração** daquela quadra: o teto
  aparado no que resta da janela dela. Às 22:30, numa quadra que fecha às 23:00,
  vem `30`.
- `lit: true` **não** é indisponível — é "acesa, e acionar aqui prolonga".
- `open: false` não some da lista: mostre desabilitada com o `next_window`.

#### `POST /api/lighting/places/{place}/activate`

Corpo opcional: `{"minutes": 45}`. Ausente vale o teto.

- `201` = acendeu. `200` = **prolongou** o que o sócio já tinha naquela quadra.
- Corpo: `{"extended": false, "activation": { … }}`, mesmo formato nos dois casos.
- `minutes` fora de `[min_minutes, max_minutes]` volta `422` de validação comum
  do Laravel (`{"message": "...", "errors": {"minutes": ["..."]}}`), **sem**
  `reason`. Prenda o seletor nos limites e isso não acontece.
- `minutes_remaining` pode vir **menor que o pedido** perto do fechamento. Mostre
  o que veio, não o que foi pedido.
- Prolongar conta **a partir de agora**: "mais 60" faltando 10 minutos dá 60, não 70.

### Recusas — o que mudou na tabela

| `reason` | HTTP | Mudou? |
|----------|------|--------|
| `window_closed` | 422 | **Sim.** Agora é o horário **daquela quadra**, e o `next_window` que vem junto é o dela. "O clube abre às 17:00" virou frase errada. |
| `member_limit` | 409 | **Sim.** Só dispara quando o sócio está em **outra** quadra. Acionar a própria é prolongamento, e responde `200`. |
| `place_busy` | — | **Removido.** Quadra acesa não recusa mais. |
| `window_ending`, `place_reserved`, `place_not_eligible`, `no_activation` | | Iguais. |

Continue escolhendo o **comportamento** pelo `reason` e exibindo o `error` que a
API manda — ele é escrito em português, para o sócio.

### O que a interface precisa ganhar

**1. Seletor de duração.** Montado com `min_minutes` e `step_minutes` da
disponibilidade, e máximo em `available_minutes` **da quadra escolhida**. Nada de
120/15 no código: os três vêm do servidor e mudam sem deploy do app.

**2. Botão "Prolongar".** No estado com quadra acesa, chamando o mesmo
`activate` da quadra atual, com o mesmo seletor. Use `extended` da resposta para
escolher a mensagem ("Luz acesa" × "Mais 45 minutos").

**3. Horário por quadra na lista.** Cada quadra mostra o próprio estado:

- `open: true` → acionável, com o horário dela ("até 23:00");
- `open: false` → **desabilitada, não escondida**, com "abre às 17:00"
  (`next_window`). Sumir não responde a pergunta "cadê a quadra 2?";
- `lit: true` → botão diz "Prolongar", com o `lit_until`.

**4. Contador pelo `lit_until`.** Com uma frase curta quando `ends_at` for
diferente ("outro sócio prolongou até 21:30").

### O que remover

- Qualquer cálculo local de janela, dia da semana, feriado ou duração. A janela
  agora é por quadra e muda no painel da Lara, sem deploy: todo front que
  duplicar a regra vai mentir para o sócio no primeiro feriado.
- O tratamento de `place_busy` e qualquer bloqueio local de quadra acesa.
- Constantes de 2 h, 120, 15 minutos.

### Cuidados que continuam valendo

- **Não invente estado local de "aceso".** A verdade é `activation`. Releia o
  servidor depois de cada ação e periodicamente (30–60 s, só com a aba visível).
  Agora há um motivo a mais: **outra pessoa pode prolongar a luz da quadra dele**.
- **Um `POST` por toque.** Desabilite o botão durante a chamada — com
  prolongamento, dois toques viram tempo de luz gasto sem querer.
- **Siga o padrão de chamada que o app já usa** (servidor ou navegador). Não
  introduza um segundo jeito de falar com a mesma API.
- Limites: `20/min` nas escritas, `120/min` nas leituras.

### Critérios de pronto

1. Sábado 19:00: escolher grupo, quadra e **45 minutos** acende por 45, e a tela
   mostra 45.
2. "Prolongar" por mais 30 responde `200`, é tratado como sucesso, e o contador
   passa a 30 minutos **contados do toque**.
3. Duas quadras com horários diferentes no mesmo dia: às 15:00, a que abre às
   14:00 aparece acionável e a que abre às 17:00 aparece desabilitada com "abre
   às 17:00".
4. O seletor de uma quadra que fecha às 19:00 não oferece mais que 30 minutos às
   18:30.
5. Quadra acesa por **outro** sócio aparece com "Prolongar" e prolongá-la
   funciona — não fica desabilitada.
6. Com outro sócio tendo prolongado, a tela mostra a luz até `lit_until` e **não**
   anuncia apagão no `ends_at`.
7. Com uma quadra acesa, tentar **outra** leva ao estado "acesa"
   (`member_limit`), não a um erro cru.
8. Devolver uma quadra que **outro sócio prolongou** não anuncia que a luz
   apagou: ela segue acesa até o `lit_until`.
8. Nenhuma ocorrência de `available`, `duration_minutes`, `minimum_minutes`,
   `today_window` ou `place_busy` sobrou no código.
9. Grep por `120`, `17:00`, `sábado` não acha regra nenhuma no front.

Ao terminar, me diga quais dos nove você verificou de fato, quais ficaram só no
código, e **se encontrou na tela algum comportamento que este documento não
previu** — a tela foi escrita contra um contrato mais antigo e pode ter coisas
que eu não listei.

## Fim do prompt
