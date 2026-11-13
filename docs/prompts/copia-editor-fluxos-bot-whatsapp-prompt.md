# Prompt — copiar o fluxo e a interface de criação de fluxos do Bot WhatsApp

> Para: um agente Claude Code que vai construir **outro editor de fluxos de conversa** (outro canal,
> outro bot ou outra aplicação) usando o editor do Bot WhatsApp d'O Lara como modelo.
> Origem: O Lara (Laravel + Blade + Alpine.js + Tailwind), módulo **Bot WhatsApp › Fluxos**.
> Gerado em 13/11/2026.

---

## O que você vai copiar

O Lara tem uma tela onde o funcionário **desenha a conversa do bot como um fluxograma**: arrasta
blocos de uma paleta para um quadro, liga as saídas de um bloco à entrada de outro, edita o conteúdo
num painel lateral, testa num simulador e salva. Salvar um fluxo ativo é **publicar** (vale na próxima
mensagem), por isso fluxo inválido não grava e toda gravação vira uma **versão** restaurável.

Copie **o modelo de dados, as regras de gravação e a interface**. **Não** copie o que é específico do
WhatsApp/Poli (webhook, `BotEngine`, fila de espera, transbordo, `PoliClient`), a não ser que o seu
canal seja o mesmo — nesse caso, leia `docs/resumo-modulos/Bot WhatsApp.md` e
`docs/funcionalidades/bot-whatsapp.md` antes.

Se você estiver no repositório d'O Lara, leia estes arquivos antes de escrever qualquer linha:

| Arquivo | Por quê |
|---|---|
| `app/Services/PoliBot/FlowDefinition.php` | Formato do JSON (docblock), `clean()` e `errors()` |
| `app/Services/PoliBot/FlowRepository.php` | Regras entre fluxos (só um "qualquer mensagem", `goto_flow` válido, versão) |
| `app/Http/Controllers/PoliBot/FlowController.php` | CRUD em JSON, esqueleto de fluxo novo, ativar/desativar, apagar |
| `resources/views/poli-bot/editor.blade.php` | A tela inteira (≈1500 linhas: markup + `botFlowEditor()` em Alpine) |
| `resources/views/poli-bot/index.blade.php` | Lista de fluxos com gatilho, passos, erros e última versão |
| `app/Services/PoliBot/BotSimulator.php` + `SimulatorController.php` | Simulador que roda o motor real sem enviar nada |
| `app/Http/Controllers/PoliBot/PoliDataController.php` | Listas externas (templates, times) em cache para os selects |
| `database/migrations/2026_09_25_160000_create_poli_bot_tables.php`, `2026_09_26_100000_create_bot_flow_versions_table.php` | Tabelas |
| `tests/Feature/Poli/PoliBotEditorTest.php` | O que a tela garante |

Se você **não** estiver no repositório d'O Lara, tudo o que precisa está descrito abaixo.

---

## 1. Modelo de dados

Duas tabelas. O fluxo inteiro é **um JSON só** — não faça uma tabela por passo: o fluxo é sempre lido e
gravado inteiro.

```
<prefixo>_flows
  id, slug (unique), name, active (bool), definition (json), timestamps

<prefixo>_flow_versions
  id, <prefixo>_flow_id (FK cascade), name, active, definition (json),
  user_id (sem FK), user_name, created_at
  index(<prefixo>_flow_id, id)
```

- `slug` só aceita `^[a-z0-9]+(?:-[a-z0-9]+)*$` e **não muda depois de criado**: é por ele que outros
  fluxos (`goto_flow`) e as conversas em andamento acham o fluxo.
- `user_name` é gravado junto para o histórico sobreviver a usuário removido. Em O Lara, `users` vive
  em outra conexão, por isso não há FK.
- Nomes de índice/FK curtos (MySQL limita a 64 caracteres) e migration idempotente.
- Para a sessão de conversa (onde cada contato está), o original usa `bot_sessions`
  (`contact_uuid` PK, `state`, `flow_slug`, `step_key`, `data` json, `tentativas`,
  `prompt_message_uuid`, `last_interaction_at`). Copie se o seu motor precisar.

## 2. Formato da definição (o contrato entre a tela e o motor)

```jsonc
{
  "start": "menu",                       // passo inicial
  "triggers": {"any": false, "texts": ["carro"], "only_goto": false, "redirect": true},
  "timeout_minutes": 15,                 // inatividade que encerra
  "max_attempts": 3,                     // respostas inválidas até a ação de estouro
  "on_max_attempts": {"type": "handoff", "team_uuid": "…"},
  "hours": {                             // opcional
    "enabled": true,
    "days": {"1": ["07:00", "19:50"], "6": ["07:00", "18:00"]},   // ISO: 1=seg … 7=dom; dia ausente = fechado
    "holidays": ["2026-12-25"], "holiday": ["07:00", "18:00"],
    "out_of_hours": "fora_do_horario"    // passo de entrada quando fechado
  },
  "steps": {
    "<chave>": {
      "say":    {"type": "text", "text": "Olá, {contato}!"}
              | {"type": "menu", "text": "Escolha:"}
              | {"type": "template", "template_uuid": "…", "params": ["{nome}"], "as_text": false},
      "expect": {"type": "text", "min": 2, "max": 80, "pattern": "…"}
              | {"type": "option"} | {"type": "plate"} | {"type": "date", "past_only": true}
              | {"type": "number", "min": 1, "max": 10} | {"type": "yes_no"} | {"type": "image"} | {"type": "any"},
      "options": [{"label": "Financeiro", "description": "…", "aliases": ["fin"], "next": "fin", "value": "…"}],
      "save_as": "placa",               // ^[a-z0-9_]+$ — vira {placa} nos textos seguintes
      "invalid": "Mensagem de correção",
      "max_attempts": 2,
      "optional": true,                 // resposta que não serve encerra o fluxo sem erro
      "action": {"type": "handoff", "team_uuid": "…"} | {"type": "close"} | {"type": "goto_flow", "flow": "slug"},
      "next": "<chave>"
    }
  },
  "layout": {"menu": {"x": 290, "y": 40}, ":inicio": {"x": 40, "y": 50}, ":fora": {"x": 40, "y": 160}}
}
```

Regras do formato que a cópia deve manter:

- **`layout` é só da tela.** Posição de cada bloco por chave de passo, mais as entradas `:inicio` e
  `:fora`. O motor ignora. Fluxo sem `layout` (criado por seed ou comando) é arrumado automaticamente
  ao abrir.
- **O tipo do bloco não é gravado.** Ele é deduzido do conteúdo (`kind()` na tela): tem `action` → o
  tipo da ação; `say.type = template` → template; tem opções → menu; tem `expect` → pergunta; senão →
  mensagem. Assim o JSON fica limpo e o motor não depende da tela.
- Passo sem `expect` não espera resposta: fala, executa a ação e segue para `next` (ou encerra).
- Ações terminais (`handoff`, `close`, `goto_flow`) não têm `next`.
- Uma opção com `next` vai para o passo dela; sem `next`, cai no `next` do passo.
- Chaves de passo e campos que a tela não conhece **voltam intactos** (`extra` no `stepFromDef`) — não
  perca dados de quem editou o JSON por fora.
- Adapte as listas `SAY_TYPES`, `EXPECT_TYPES` e `ACTION_TYPES` ao seu domínio (O Lara tem também
  `uber_request`, específico dele). Cada tipo novo precisa de: validação em `errors()`, entrada em
  `KINDS`/`BLOCKS` na tela e tratamento no motor.

## 3. Validação (servidor) — duas camadas

**`FlowDefinition::clean($def)`** — tira o que a tela manda vazio (strings em branco, listas sem
item), recursivamente, mas preserva `triggers` inteiro (`any: false` é escolha, não ausência).

**`FlowDefinition::errors()`** — o fluxo isolado. Mensagens em português, começando por
`Passo "<chave>":` quando o erro é de um passo (a tela usa esse prefixo para focar o bloco):

- sem passos; `start` inexistente;
- `say.type` desconhecido; template sem `template_uuid`; texto/menu sem texto;
- `expect.type` desconhecido; menu/opção sem opções; template `as_text` sem opções;
- opção sem rótulo; opção ou `next` apontando para passo inexistente;
- ação desconhecida; `goto_flow` sem destino; `optional` sem `expect`; passo vazio;
- `pattern` que não compila; `save_as` fora de `^[a-z0-9_]+$`;
- **sem gatilho** (nem `any`, nem `texts`, nem `redirect`, nem `only_goto`);
- horário ligado sem passo `out_of_hours` ou com intervalo inválido (`HH:MM`, início < fim).

**`FlowRepository::errors($slug, $def, $ativo, $ignorarId)`** — o conjunto de fluxos:

- `goto_flow` para ele mesmo, ou para slug que não existe;
- só **um** fluxo ativo pode abrir em "qualquer primeira mensagem" (com dois, o segundo nunca seria
  escolhido e ninguém saberia por quê); o mesmo para "transferência de um setor";
- fluxo que é destino de `goto_flow` de outro **não pode ser apagado** (`referencedBy`).

Fluxo inválido **não grava nem inativo**: o editor mantém o trabalho na tela até corrigir.

## 4. Rotas e controller

Grupo protegido por uma permissão própria (em O Lara: `can:bot-whatsapp`):

```
GET    /<modulo>/                     index         lista
GET    /<modulo>/fluxos/novo          flows.create  editor vazio (esqueleto)
POST   /<modulo>/fluxos               flows.store   JSON
GET    /<modulo>/fluxos/{flow}/editar flows.edit
PUT    /<modulo>/fluxos/{flow}        flows.update  JSON (slug não muda)
POST   /<modulo>/fluxos/{flow}/ativo  flows.toggle  valida antes de ativar; grava versão
DELETE /<modulo>/fluxos/{flow}        flows.destroy recusa se referenciado
POST   /<modulo>/simular              simulate      throttle:120,1
GET    /<modulo>/<externo>/templates  (opcional) listas externas em cache 10 min; ?atualizar=1 pula o cache
```

- `store`/`update` respondem **JSON**: sucesso `{ok, message, redirect?, definition, version}`;
  erro **422** `{message, errors: {definition: [...]}}`. No `store`, `redirect` leva ao editor do fluxo
  criado.
- Gravar = `clean` → `errors` → `DB::transaction` (salva + cria versão).
- Fluxo novo nunca começa vazio: esqueleto com um passo `inicio` (`say` texto "Olá, {contato}! Como
  posso ajudar?"), `timeout_minutes: 15`, `max_attempts: 3`.
- O editor recebe do controller, num único `@js([...])`: `flow` (id, name, slug, active, definition),
  `versions` (últimas 30: id, name, active, definition, user, at `d/m/Y H:i`), `otherFlows` (slug,
  name, active — para o select de `goto_flow`), `invalidDefaults` (textos de correção padrão por tipo)
  e `urls` (todas as rotas acima, geradas com `route()`).
- Listas de serviço externo falham com 502 e uma mensagem amigável ("você ainda pode colar o uuid à
  mão"); nunca derrubam a tela.

## 5. A interface (o que mais importa copiar)

Uma tela em tela cheia (`height: calc(100vh - 4rem)`), com um componente Alpine
`x-data="flowEditor(@js([...]))"` e eventos `@pointermove.window`, `@pointerup.window`,
`@keydown.window`. Quatro áreas:

### 5.1 Barra superior
`← Fluxos` · nome do fluxo · selo **Ativo**/**Rascunho** · "● Alterações não salvas" (`dirty`) ·
mensagem de sucesso · "Apagar fluxo" · botão **Salvar** (Ctrl+S, "Salvando…").

### 5.2 Paleta (esquerda, `w-52`)
Dois grupos, **Conversa** (Mensagem, Pergunta, Menu, Template) e **Ações** (Passar p/ time, Outro
fluxo, Encerrar, + as suas). Cada item: ícone colorido, rótulo, dica. **Arrastar** para o quadro
(HTML5 drag com `dataTransfer` prefixado, ex. `'meu-bot:' + tipo`) ou **clicar** (cria no centro, com
deslocamento para não empilhar). Embaixo, uma caixinha de ajuda: como ligar, desligar, mover, atalhos.

Dois objetos governam os blocos:

```js
// Aparência — por tipo deduzido
const KINDS = { mensagem: {label, icon: '💬', color: '#0ea5e9', hint}, pergunta: {...}, menu: {...}, ... };
// O passo que cada item da paleta cria
const BLOCKS = {
  mensagem: {base: 'mensagem', def: {say: {type: 'text', text: ''}}},
  menu:     {base: 'menu', def: {say: {type: 'menu', text: 'Escolha uma opção:'}, expect: {type: 'option'},
             options: [{label: 'Opção 1'}, {label: 'Opção 2'}]}},
  handoff:  {base: 'transferir', def: {action: {type: 'handoff'}}},
  ...
};
```
A chave do passo novo é `base`, `base_2`, `base_3`… (`newKey`).

### 5.3 Quadro (centro) — fluxograma sem biblioteca

Nada de React Flow/jsPlumb: é Alpine + DOM absoluto + **um SVG** para as ligações.

- **Geometria fixa** do bloco, para calcular as ligações **sem medir o DOM**:
  `W = 264, HEAD = 40, BODY = 56, ROW = 26, PAD = 4`; entradas `ENTRY_W = 150, ENTRY_H = 38`;
  grade `GRID = 10` (`snap`). Altura do bloco = `HEAD + BODY + max(1, nº de saídas) * ROW + PAD`.
- **Fundo** com pontinhos (`radial-gradient`), `background-size` e `background-position`
  acompanhando zoom e deslocamento.
- **Mundo** num `div` com `transform: translate(x, y) scale(z)` (`view = {x, y, z}`, zoom 0,25–2,
  roda do mouse dá zoom no ponto do cursor — `zoomAt`). `toWorld(clientX, clientY)` converte tela →
  mundo.
- **Bloco**: cabeçalho com a cor do tipo, ícone, chave (mono) e selos (`início`, `fora`,
  `sem caminho` quando inalcançável, `!` quando tem erro do servidor); corpo com `meta` (ex.: "espera
  placa → {placa} · opcional") e prévia do texto (2 linhas); rodapé com **uma linha por saída**, cada
  uma com a bolinha à direita. Bolinha de entrada à esquerda, na altura do cabeçalho.
- **Saídas** (`ports(s)`): terminal → nenhuma (mostra "■ Encerra" etc.); com opções → uma por opção
  (`1. Rótulo`) + "Opção sem destino" (apagada quando todas as opções têm destino); senão → uma só,
  "Resposta válida" (se espera algo) ou "Em seguida". Saída sem destino mostra "· fim".
- **Entradas** como pílulas arrastáveis: **▶ Início** (liga em `start`) e, com horário ligado,
  **☾ Fora do horário** (liga em `hours.out_of_hours`).
- **Ligações**: curvas de Bézier (`curve(x1,y1,x2,y2)`, controle horizontal ≥ 50 px, 140 quando
  volta para trás). Como `x-for` não funciona dentro de `<svg>`, o SVG é gerado como string e entra
  por `<g x-html="edgesSvg()">`. Cada ligação é **dois paths**: o visível (cinza; opção em índigo;
  entrada em preto; selecionada em verde, mais grossa, com seta própria via `<marker>`) e um
  transparente de 14 px com `pointer-events: stroke` e `data-edge` para o clique.
- **Ligar**: `pointerdown` na bolinha de saída inicia `drag = {type: 'link', ...}`; uma linha
  tracejada segue o cursor; no `pointerup`, `document.elementFromPoint(...).closest('[data-node]')`
  acha o bloco-alvo e `setTarget(dono, porta, key)` grava `next` ou `options[i].next`.
  **Soltar no vazio** abre um menu "Criar e ligar:" com todos os tipos — cria o bloco ali e já liga.
- **Desligar**: clicar na ligação seleciona; aparece um **✕** no meio da curva (ponto médio da
  Bézier, `(x1 + 3c1x + 3c2x + x2) / 8`); Delete também desliga.
- **Arrastar**: um único `drag` com `type` = `pan` | `node` | `link`; só conta como movimento depois de
  3 px (clique no fundo sem mover = desseleciona). Bloco arrastado encaixa na grade.
- **Atalhos**: Ctrl+S salva; Delete/Backspace apaga ligação ou bloco selecionado; Ctrl+D duplica;
  Esc solta tudo. Ignore atalhos quando o foco está em input/textarea/select.
- **Canto inferior**: − / % / + , **Ajustar** (`fit()`, enquadra tudo) e **Organizar**
  (`autoLayout()`: busca em largura a partir de `start` e `out_of_hours`, colunas pela distância,
  `x = X0 + nível * (W + 120)`, empilhando com 40 px de folga; passos soltos viram raízes novas).
  Passos sem posição num fluxo que já tem `layout` vão para baixo do desenho (`placeMissing`).
- **Problemas**: caixa no alto do quadro "Não foi salvo. Corrija:" com a lista do 422; item que cita
  `Passo "x"` é link que centraliza o bloco (`focusKey`). Quadro vazio mostra "Arraste um bloco da
  paleta para começar."

### 5.4 Painel lateral (direita, `w-[24rem]`) — três abas

**Fluxo**: nome (gera o slug automaticamente enquanto ninguém mexeu no slug — `slugify` sem acento),
identificador (desabilitado depois de criado, "Não muda depois de criado"), ativo, **gatilho** como
uma escolha única (`triggerMode`: qualquer primeira mensagem | palavras-chave separadas por vírgula |
transferência de setor | só chamado por outro fluxo), passo inicial, inatividade (min), tentativas,
time do estouro de tentativas, **horário de atendimento** (liga/desliga, por dia da semana aberto +
de/até, feriados em `dd/mm/aaaa` um por linha — convertidos para ISO ao salvar — e intervalo de
feriado, passo "fora do horário") e **histórico de versões** (quem, quando; "Carregar" pede
confirmação, carrega na tela e avisa "confira e salve" — não grava sozinho).

**Passo** (habilitada com um bloco selecionado; duplo clique no bloco abre direto), em três seções
numeradas, na ordem em que a conversa acontece:
1. **O bot diz** — tipo (nada / texto / menu / template); texto com placeholders `{contato}` e as
   variáveis já guardadas (`variables()` lista os `save_as` do fluxo); template: select alimentado pela
   API externa + botão recarregar + "Usar as opções deste template" + parâmetros + "enviar como
   texto numerado".
2. **O bot espera como resposta** — tipo; campos por tipo (mín./máx. caracteres e regex para texto,
   mín./máx. para número, só passadas/só futuras para data); **opções** (rótulo, descrição, apelidos,
   "vai para" select de passos, valor gravado, subir/descer, remover — cada opção é uma saída do bloco
   no quadro); guardar como; mensagem de correção (placeholder com o padrão do tipo); tentativas;
   pergunta opcional.
3. **Depois** — ação (nenhuma / passar para time com select de times / outro fluxo com select de
   `otherFlows` / encerrar) e próximo passo (escondido quando a ação é terminal).
   Mais: renomear a chave (atualiza **todas** as referências: `next`, opções, `start`,
   `out_of_hours` — `replaceRef`), duplicar e apagar (apagar limpa as referências para ele).

**Simulador**: conversa em bolhas, campo de mensagem, botões "Começar este fluxo", "Enviar imagem",
"Reiniciar"; toque em opção de menu envia o rótulo; mostra a sessão (estado, passo, variáveis) e
**destaca no quadro os blocos visitados** (`sim.visited`) e o passo atual (`simStep()`).

### 5.5 Estado e serialização (o coração do JS)

```js
init() {
  this.load(config.flow.definition);   // JSON → modelo da tela
  this.$nextTick(() => {
    this.fit();
    this.$watch(() => JSON.stringify([this.flow, this.settings, this.steps, this.triggerMode, this.entryPos]),
                () => { this.dirty = true; this.message = ''; });
  });
  window.addEventListener('beforeunload', e => { if (this.dirty) { e.preventDefault(); e.returnValue = ''; } });
}
```

- `load(def)` → `settings` (configuração do fluxo, já no formato dos inputs), `steps` (array, cada um
  com `_uid` local, `key`, `pos`, `say`, `expect`, `options` com `aliases` como string, `action`,
  `next`, `extra`) e `entryPos`.
- `toDefinition()` → o JSON do item 2: só grava o que tem significado (campos de `expect` só para o
  tipo certo, `next` só se não for terminal, opções sem rótulo descartadas, `aliases` de volta para
  array, `layout` arredondado).
- `save()`: `fetch` POST (novo) ou PUT com `X-CSRF-TOKEN` e `Accept: application/json`; 422 preenche
  `errors`; `redirect` no fluxo novo; senão adiciona a versão devolvida ao histórico e zera `dirty` no
  `$nextTick` (senão o próprio `$watch` remarca). Falha de rede: "Nada foi perdido: tente de novo."
- Tudo que é derivado (`ports`, `kind`, `edgeList`, `reachable`, `errorKeys`) é **calculado**, nunca
  guardado — a fonte da verdade é `steps` + `settings`.

## 6. Simulador (servidor)

Roda o **motor real** em modo sombra: nada sai para o canal externo e nenhuma ação com efeito
(pedido, gravação de domínio) acontece, seja qual for o `.env`. Cada usuário tem a sua conversa
(`contato = 'painel-' . user_id`). Fluxos **inativos** também valem — é assim que se testa um
rascunho. Ações: `comecar` (fluxo), `enviar` (texto ou imagem simulada), `reiniciar` (apaga sessão e
mensagens do contato simulado). Resposta: `{replies: [{type, text, options?...}], session}`. Restaure
a configuração no `finally`.

## 7. Lista de fluxos (index)

Cartões/tabela com: nome, slug, selo ativo/rascunho, **gatilho descrito em português** (os gatilhos
somam: "Palavras: carro, uber · Transferência de um setor"; "Só chamado por outro fluxo"; "Sem
gatilho"), nº de passos, **erros atuais** do fluxo (`$f->flow()->errors()`), última versão (quem,
quando), botão ativar/desativar (que valida antes de ativar) e "Novo fluxo". Em O Lara há também
números dos últimos 7 dias (conversas, recebidas, respostas, transbordos, falhas) — opcional.

## 8. Regras de O Lara que valem se a cópia for dentro d'O Lara

- Permissão nova: constante + linha em `CATALOG` de `App\Authorization\Permissions` (grupo e rótulo).
  O `PermissionCatalogSeeder` cria no deploy. **Não** crie migration para permissão e não atrele a
  setor no código.
- Item novo no menu (`App\View\Navigation`): o menu **não** pode chamar método de model que consulta
  banco — use Gate. Rota nova: refaça o `route:cache` (um nome que não resolve dá 500 em toda tela).
- `@json` com `route()` com parâmetros quebra o Blade: monte o array em PHP e use `@js(...)`/`Js::from`.
- Classe Tailwind nova só existe depois de `npm run build`.
- Use os tokens do tema (`bg-surface`, `text-ink`, `border-line`, `bg-grena`, `text-danger`,
  `shadow-card`…), que já cobrem o modo escuro; cores fixas só nos tipos de bloco e nas ligações.
- Documente: `docs/funcionalidades/<modulo>.md`, `docs/resumo-modulos/<Nome no menu>.md`,
  `docs/guias/<modulo>.md` (guia do usuário; o `UserGuidesContentTest` confere rotas citadas),
  `docs/rotas.md`, `docs/controllers.md`, `docs/services.md`.
- Testes **sem `RefreshDatabase`** (nunca apague o banco). Rode as migrations necessárias no `setUp`,
  confira o isolamento SQLite do `phpunit.xml` antes de rodar e rode só a pasta do módulo com
  `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/<Pasta>`.
- Sempre "O Lara", nunca "A Lara".

## 9. Testes mínimos

- `FlowDefinition`: cada erro da lista do item 3 (um caso por mensagem) e `clean()` preservando
  `triggers`.
- Repositório: dois fluxos ativos "qualquer mensagem" → recusa; `goto_flow` para inexistente → recusa;
  apagar fluxo referenciado → recusa; gravar cria versão.
- Controller: `store` com slug inválido/duplicado → 422; `update` não muda slug; fluxo inválido não
  grava nem inativo; `toggle` valida ao ativar; quem não tem a permissão recebe 403.
- Tela: `create` e `edit` renderizam (200) com as URLs no `x-data`; o JSON salvo pela tela é aceito
  pelo motor (passe a definição gerada de um fluxo padrão por `errors()`).
- Simulador: não chama o canal externo (`Http::fake` + `assertNothingSent`), funciona com fluxo
  inativo, `reiniciar` limpa a conversa.

## 10. Ordem sugerida

1. Migrations + models (`Flow` com cast `definition: array` e `flow(): FlowDefinition`, `versions()`
   mais recente primeiro).
2. `FlowDefinition` (formato, `clean`, `errors`) + `FlowRepository` + testes.
3. Controller + rotas + permissão + esqueleto.
4. Editor: comece por `load`/`toDefinition` e o painel lateral (já dá para salvar); depois o quadro
   (blocos, saídas, SVG, arrastar, ligar), depois zoom/ajustar/organizar, atalhos e o menu "Criar e
   ligar".
5. Lista de fluxos, histórico de versões, simulador.
6. Documentação e guia do usuário.

Ao terminar, devolva: arquivos criados, o que foi adaptado do original (tipos de passo e ações
novos/removidos), o que ficou de fora e o resultado dos testes.
