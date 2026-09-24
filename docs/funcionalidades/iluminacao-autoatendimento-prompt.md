# Prompt de implementação — acender a luz da quadra (Next.js)

> Documento de entrega para um agente Claude Code trabalhando **no repositório do
> aplicativo de reservas (Next.js)**, não neste. Copie a partir de
> "Início do prompt" até o fim.
>
> ⚠️ **A tela já existe?** Então o documento a entregar não é este, e sim
> [iluminacao-autoatendimento-ajustes-prompt.md](iluminacao-autoatendimento-ajustes-prompt.md),
> que lista o que mudou no contrato desde a primeira versão. Este aqui é para
> implementar do zero, e serve de referência do contrato completo.
>
> O contrato descrito aqui é o que a Lara expõe hoje (`routes/api.php`, prefixo
> `lighting`). Se a API mudar, este arquivo muda junto — ele é a fonte do que o
> front pode esperar. As regras por trás dele estão em
> [Automação Home Assistant](automacao-home-assistant.md#autoatendimento-do-sócio-fim-de-semana).

---

## Início do prompt

Você vai implementar uma **funcionalidade nova** neste aplicativo: o sócio acende
a luz da quadra pelo celular.

### O problema real

No fim de semana **não há reserva de quadra** no clube — o uso é livre, quem
chega joga. O efeito colateral é que a luz não tem dono: à noite, quem quer
jogar precisa achar um funcionário para acender. A funcionalidade dá ao sócio um
botão para isso, nos horários em que o clube aceita.

Duas coisas decorrem disso e moldam a tela inteira:

1. **Acender não reserva a quadra.** Não garante o espaço a ninguém, não bloqueia
   para outros, não cria agendamento. O texto da tela precisa dizer isso: quem
   chegar primeiro joga, a luz é só a luz.
2. **A luz não é de ninguém.** O sócio escolhe quantos minutos quer (até 2 h por
   vez). Acabando, **qualquer sócio presente aciona de novo** e a luz continua
   acesa sem piscar — inclusive quem não acendeu da primeira vez. É o que salva o
   jogo que passou do previsto, e é a parte que a tela mais precisa comunicar
   bem.
3. **Cada quadra tem o próprio horário.** Quadra coberta escurece antes e abre
   mais cedo que a descoberta ao lado, no mesmo dia. Não existe "o horário do
   clube" para efeito de decisão: às 15h de sábado a coberta está aberta e as
   outras não. **Toda a verdade de horário está na lista de quadras**, não na
   disponibilidade geral.

### Antes de escrever qualquer código

Explore o projeto e me diga o que encontrou, em especial:

1. Como as rotas e telas são organizadas (App Router ou Pages, grupos, layouts) e
   onde a área logada do sócio começa.
2. Como a autenticação de sócio funciona hoje: onde o JWT (`Session`) é guardado,
   como as chamadas autenticadas à Lara são montadas, se há um cliente de API
   centralizado, como o app trata um token expirado.
3. **De onde saem as chamadas à Lara: do servidor do Next ou do navegador?** Siga
   o padrão que já existe no app — não invente um segundo jeito de falar com a
   mesma API.
   - Se elas saem do navegador e o `API_TOKEN` está no bundle do cliente,
     **relate isso a mim** em vez de resolver por conta própria: é um problema
     mais antigo e maior que esta tela, e a decisão não é sua.
4. O sistema de design: botão, card, skeleton/loading, modal de confirmação,
   toast/alerta de erro, seletor numérico ou slider, e como o app mostra contagem
   de tempo, se já mostrar em algum lugar.
5. Se existe uma tela de "minhas reservas" ou equivalente — o estado "luz acesa"
   provavelmente merece aparecer lá também, não só na tela nova.

Só depois disso proponha o plano.

### Restrições não negociáveis

**1. O front não decide nada.**

Quem decide se pode acender, por quanto tempo, e se a quadra está livre é a
Lara. O Next renderiza o que ela responde. Concretamente, **não faça** nada
disto:

- calcular se é sábado/domingo ou se está dentro do horário no cliente;
- guardar a lista de feriados no front;
- fixar 2 h, 15 min ou qualquer limite de duração no código;
- assumir que todas as quadras têm o mesmo horário;
- esconder o botão porque "achou" que o sócio já tem quadra acesa, sem ter
  perguntado.

A janela e os limites mudam (feriado liberado, sábado de torneio bloqueado, teto
alterado) e mudam **no servidor**, sem deploy nenhum do front. Todo front que
duplicar essa regra vai mentir para o sócio no primeiro feriado.

**2. Toda recusa de regra vem com `reason`; use o `reason`, não o texto.**

As respostas de erro trazem `error` (texto em português, pronto para exibir) e
`reason` (código estável). Escolha o **comportamento** da tela pelo `reason` e
**exiba** o `error`. Nunca compare strings de mensagem.

**3. Não invente estado local de "aceso".**

A verdade é o campo `activation` do endpoint de disponibilidade. Depois de
acender, de prolongar, de devolver, ou ao voltar para a tela, releia o servidor.
O sócio abre o app em dois aparelhos; **outra pessoa pode ter prolongado a luz
da quadra dele**; o prazo vence sozinho sem ninguém clicar em nada.

**4. Um `POST` por toque.**

Desabilite o botão durante a chamada. Dois toques em sequência viram um
acionamento e um prolongamento logo atrás, gastando tempo de luz sem querer.

### A API

Base: a mesma que o app já usa para reservas. Todos os endpoints são relativos a
ela.

**Autenticação: as duas, em toda chamada.**

```
Authorization: Bearer <API_TOKEN>   ← a aplicação
Session: <JWT do sócio>             ← o sócio logado
```

O sócio é sempre o dono do `Session`. **Nenhum endpoint aceita id ou cpf de sócio
no corpo** — não tente mandar.

Falhas de autenticação, iguais às do resto da API:

| Situação | HTTP | Corpo |
|----------|------|-------|
| Sem/errado `Authorization` | `401` | `{"message":"Invalid API Token"}` |
| Sem header `Session` | `400` | `{"error":"Not Found Login Token"}` |
| `Session` inválido ou expirado | `401` | `{"error":"Invalid Login Token"}` |

> O JWT do sócio **expira à meia-noite** (é assim para todo o app, não é
> particularidade desta tela). Um sócio que acende às 22:00 de sábado pode ter o
> token morto antes do fim do acionamento. Trate o `401` como o app já trata em
> qualquer outra tela — e note que a luz **continua acesa**: o prazo dela não
> depende da sessão.

Todos os horários são ISO 8601 com offset de `America/Sao_Paulo`
(ex.: `2026-09-26T18:00:00-03:00`). Não presuma UTC.

#### `GET /api/lighting/availability`

O endpoint principal. É o que a tela lê ao abrir, depois de cada ação, e
periodicamente enquanto o contador corre.

```json
{
  "now": "2026-09-26T18:00:00-03:00",
  "open": true,
  "next_window": {
    "date": "2026-09-26",
    "start": "17:00",
    "end": "23:00",
    "starts_at": "2026-09-26T17:00:00-03:00",
    "ends_at": "2026-09-26T23:00:00-03:00",
    "source": "weekly",
    "reason": null
  },
  "club_window": { "...": "o horário geral do clube hoje, ou null" },
  "max_minutes": 120,
  "min_minutes": 15,
  "step_minutes": 15,
  "activation": null
}
```

- `open` — **é o agregado: há alguma quadra aberta agora?** Use-o só para decidir
  se mostra o fluxo de escolha. Ele **não** diz que a quadra X está aberta; isso
  está na lista de quadras.
- `next_window` — a **primeira** janela a abrir entre todas as quadras. `source` é
  `weekly` (dia da semana) ou `date` (feriado liberado no painel); em `date`,
  `reason` costuma trazer o nome do feriado, e é um texto bom de mostrar.
- `club_window` — o horário geral do clube hoje, ou `null`. Serve só para texto
  genérico ("o clube abre às 17:00"). **Não decida nada por ele**: uma quadra
  coberta pode abrir às 14:00 e outra ficar fechada o dia inteiro.
- `max_minutes` / `min_minutes` / `step_minutes` — **monte o seletor de duração
  com estes três.** São configuração do servidor; não replique 120/15/15 no
  código. O *máximo* do seletor, porém, é o `available_minutes` **da quadra
  escolhida**, não `max_minutes`.
- `activation` — o acionamento vigente do sócio, ou `null`:

```json
{
  "id": 1,
  "place_id": 10,
  "place_name": "Quadra 1",
  "place_group": "Beach Tennis",
  "starts_at": "2026-09-26T18:00:00-03:00",
  "ends_at": "2026-09-26T20:00:00-03:00",
  "minutes_remaining": 120,
  "lit_until": "2026-09-26T21:30:00-03:00",
  "released_at": null
}
```

**Preste atenção na diferença entre `ends_at` e `lit_until`** — ela é o coração
da funcionalidade:

- `ends_at` / `minutes_remaining` são **do acionamento deste sócio**.
- `lit_until` é **da quadra**, somando todos os sócios que acionaram.

Eles divergem sempre que outra pessoa prolongou depois. No exemplo acima, o
acionamento da Ana acaba às 20:00, mas o Bruno prolongou e a luz vai até 21:30.
**Para o contador da luz use `lit_until`**; `ends_at` serve para saber quando o
sócio deixa de ocupar a própria cota. Se você usar `ends_at` como "quando a luz
apaga", a tela vai anunciar um apagão que não vai acontecer.

Use os campos de data para o contador (contra o relógio local, ancorado em `now`
para não depender de o celular estar na hora certa). `minutes_remaining` é o
valor no instante da resposta — bom para o primeiro render, ruim para contar
sozinho.

#### `GET /api/lighting/groups`

Primeiro passo da escolha. Só vêm grupos que têm ao menos uma quadra liberada.

```json
{ "groups": [ { "id": 1, "name": "Beach Tennis", "icon": "beach", "places": 2 } ] }
```

Se vier vazio, nenhuma quadra está liberada para autoatendimento — mostre um
aviso, não uma tela quebrada.

#### `GET /api/lighting/groups/{group}/places`

Segundo passo.

**É aqui que mora a verdade sobre horário.** Cada quadra traz a própria janela.

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
      "open": false,
      "window": null,
      "available_minutes": 0,
      "next_window": { "start": "17:00", "...": "..." },
      "lit": false, "lit_until": null
    }
  ]
}
```

- `open` / `window` — **desta quadra**, agora. No exemplo são 15:00: a coberta
  abriu às 14:00 e a outra só abre às 17:00.
- `available_minutes` — quanto dá para pedir nesta quadra agora: o teto aparado no
  que resta da janela **dela**. **É este o máximo do seletor de duração.** Zero
  quer dizer fechada.
- `next_window` — quando esta quadra volta a abrir. Use no lugar de esconder a
  quadra: "abre às 17:00" responde a pergunta que sumir não responde.
- `image` é o nome do arquivo, no mesmo formato dos outros endpoints de espaço do
  app — monte a URL como o app já monta.
- **`lit: true` NÃO quer dizer indisponível.** A quadra continua acionável, e
  acionar uma quadra acesa é exatamente como se prolonga a luz. O `lit_until`
  muda o *texto* do botão ("Prolongar — acesa até 20:00"), não a existência dele.
- Este endpoint **não** informa reserva confirmada: ela só aparece na recusa do
  acionamento (`place_reserved`). É raro no fim de semana, que é quando a tela
  funciona.

#### `POST /api/lighting/places/{place}/activate`

Acende, ou prolonga. É o mesmo endpoint para os dois: **não procure um
`/extend`**, ele não existe, e separar os dois só faria a tela adivinhar qual
chamar.

```json
{ "minutes": 45 }
```

- `minutes` é opcional; ausente vale `max_minutes`. Mande sempre o que o sócio
  escolheu.
- Fora de `[min_minutes, max_minutes]` volta `422` de validação comum do Laravel
  (`{"message":"...","errors":{"minutes":["..."]}}`), **sem** `reason`. Prenda o
  seletor nos limites e isso não acontece.

Resposta `201` quando é acionamento novo, **`200` quando prolongou** o que o
sócio já tinha naquela quadra. O corpo é o mesmo nos dois casos:

```json
{ "extended": false, "activation": { "...": "mesmo formato de `activation` acima" } }
```

Use `extended` para escolher a mensagem ("Luz acesa" × "Mais 45 minutos").

`minutes_remaining` pode vir **menor que o pedido**: perto do fechamento a
duração é aparada na janela. Mostre o que veio, não o que foi pedido.

Prolongar conta **a partir de agora**, não do fim do anterior: pedir "mais 60"
faltando 10 minutos dá 60 a partir do toque, não 70.

#### `POST /api/lighting/release`

Sem corpo. Devolve a quadra antes do prazo e libera a cota do sócio para acionar
outra. Sucesso: `200`, com a ativação já encerrada (`minutes_remaining: 0`,
`released_at` preenchido).

**A luz pode continuar acesa depois do release** — se outro sócio prolongou, ela
vale até o prazo dele. Olhe o `lit_until` da resposta antes de anunciar que
apagou.

Não pergunte qual acionamento encerrar — é sempre o vigente do próprio sócio,
porque ele só pode ter um.

#### `GET /api/lighting/activations`

Histórico do sócio, 30 mais recentes, do mais novo para o mais antigo.
Opcional para a primeira versão.

```json
{ "activations": [ { "...": "mesmo formato de `activation`" } ] }
```

### As recusas, e o que a tela faz com cada uma

Corpo de toda recusa de regra: `{"error": "…", "reason": "…", …extras}`.

| `reason` | HTTP | O que aconteceu | O que a tela deve fazer |
|----------|------|-----------------|--------------------------|
| `window_closed` | 422 | Fora do horário **daquela quadra**, ou dia bloqueado | Extra `next_window`, da quadra pedida. Diga quando *ela* abre. Não é erro do sócio. |
| `window_ending` | 422 | Falta menos que `min_minutes` para fechar | Diga que hoje já encerrou e aponte `next_window` (releia a disponibilidade). |
| `member_limit` | 409 | Ele está em **outra** quadra | Extra `active_activation`. Leve-o para o estado "acesa", com a opção de devolver. **Não acontece ao acionar a própria quadra** — ali é prolongamento. |
| `place_reserved` | 409 | Reserva confirmada no período | Extras `reserved_from` / `reserved_until`. Explique que a quadra está reservada e que **a luz dela acende sozinha**. Ofereça outra quadra. |
| `place_not_eligible` | 422 | Quadra não liberada para autoatendimento | Não deveria acontecer pela tela (a lista já filtra). Se acontecer, recarregue a lista. |
| `no_activation` | 404 | `release` sem nada aceso | O estado do front estava velho. Releia a disponibilidade em silêncio. |
| — | 429 | Throttle | `20/min` nas escritas, `120/min` nas leituras. Se você bater nisso, o problema é o seu laço de polling. |

Repare no padrão: **quase nenhuma recusa é culpa do sócio.** Escreva as
mensagens assim — informando, não repreendendo. Prefira reaproveitar o `error`
que a API mandou; ele já foi escrito pensando no sócio.

### A tela

Um fluxo curto, em três passos: **grupo → quadra → quanto tempo**. Use os
componentes de seleção que o app já tem para grupo e espaço; não crie um padrão
visual novo.

**Estado 1 — nenhuma quadra aberta** (`open: false`, sem `activation`)

O passo a passo não aparece. No lugar, um aviso com o `next_window` da
disponibilidade — a primeira quadra a abrir. Se `next_window.source` for `date` e
tiver `reason`, mencione o feriado: é uma informação que o sócio não tem de outro
jeito.

**Estado 2 — aberto, sem quadra acesa** (`open: true`, `activation: null`)

O fluxo de escolha, terminando no seletor de duração, montado com
`min_minutes` / `step_minutes` da disponibilidade e `available_minutes` **da
quadra escolhida**. Deixe explícito, antes de confirmar, que **acender não
reserva a quadra**.

Na lista de quadras, cada uma mostra o próprio estado:

- `open: true` → botão ativo, com o horário dela ("até 23:00") e o seletor limitado
  a `available_minutes`;
- `open: false` → **não esconda**; mostre desabilitada com "abre às 17:00"
  (`next_window`). É a diferença entre o sócio entender o clube e achar que o app
  está quebrado;
- `lit: true` → botão diz "Prolongar", com `lit_until`. Um sócio sem acionamento
  próprio pode prolongar a luz de uma quadra onde já está jogando — esse é o caso
  de uso principal, não uma exceção.

**Estado 3 — com quadra acesa** (`activation` preenchida)

É o estado mais importante, e o que o sócio vai abrir o app para ver. Mostre:

- a quadra;
- **até quando a luz vai** (`activation.lit_until`) — é este o número grande;
- quanto falta do acionamento dele (`ends_at`), se for diferente, com uma frase
  curta explicando ("outro sócio prolongou até 21:30");
- um botão **Prolongar**, com o mesmo seletor de duração;
- um botão **Devolver a quadra**.

O fluxo de escolha de outra quadra some: ele não pode acender uma segunda.

Quando o contador chega a zero, **não conclua nada sozinho** — releia a
disponibilidade e siga o que o servidor disser. É bem possível que alguém tenha
prolongado e não haja nada a fazer.

**Atualização enquanto a tela está aberta**

Reler a disponibilidade a cada 30–60 segundos no estado 3 é suficiente, e só
enquanto a aba está visível (`document.visibilityState`). O contador roda
localmente entre as leituras. Não chegue perto de `120/min`.

### O que fica de fora

- **Não construa tela de administração.** Liberar quadras, definir horários (o
  padrão e o de cada quadra) e cadastrar feriados já existe, no painel da Lara.
- **Não tente ligar outros espaços** (salão, piscina). A API só lista o que está
  liberado; respeite a lista.
- **Não some com a opção quando fechado** — o sócio precisa descobrir que existe,
  e quando volta a funcionar. Um estado vazio bem escrito vale mais que um item
  de menu escondido.

### Critérios de pronto

1. Sábado 19:00, sócio sem nada aceso: escolhe grupo, quadra e 45 minutos,
   confirma, e a tela passa ao estado 3 com contador de 45 minutos.
2. No estado 3, "Prolongar" por mais 30 minutos responde `200`, e o contador
   passa a 30 minutos **contados do toque**, não somados ao que faltava.
3. Sábado 22:30: o seletor não oferece mais que 30 minutos
   (`available_minutes` da quadra).
4. Sábado 22:50: a tela não deixa nem tentar, ou a recusa `window_ending` é
   exibida com a próxima janela.
5. **Quadras com horários diferentes no mesmo dia**: com uma quadra aberta às
   15:00 e outra só às 17:00, às 15:00 a lista mostra a primeira acionável e a
   segunda desabilitada com "abre às 17:00" — e o seletor da primeira respeita o
   `available_minutes` dela.
6. Quarta-feira: estado 1, com a data e a hora da primeira quadra a abrir.
7. Quarta-feira que seja feriado liberado: estado 2, funcionando, com o nome do
   feriado visível.
8. Quadra acesa por **outro** sócio aparece na lista com o horário e o botão
   "Prolongar", e prolongá-la funciona.
9. Com uma quadra acesa, tentar **outra** não é possível pela interface; se
   acontecer, `member_limit` leva ao estado 3 em vez de mostrar erro cru.
10. Com outro sócio tendo prolongado, a tela mostra a luz indo até `lit_until` e
    **não** anuncia apagão no `ends_at` do sócio.
11. Devolver a quadra volta ao estado 2, e acender outra funciona em seguida.
12. Deixar a tela aberta até o prazo vencer volta ao estado 2 sozinha, sem
    recarga manual.
13. Nenhuma regra de horário, duração ou feriado aparece hardcoded no front.
    Grep por `120`, `sábado`, `17:00` não acha nada.

Ao terminar, me diga quais dos treze você conseguiu verificar de fato e quais
ficaram só no código.

## Fim do prompt
