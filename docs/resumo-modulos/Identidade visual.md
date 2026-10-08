# Identidade visual (rebrand v4 "Módulos", grená) — resumo para quem vai mexer

Vale para **toda tela** do O Lara. Antes de criar ou alterar uma tela, leia isto: o visual é
feito de tokens e componentes prontos, e o que foge deles quebra o tema escuro ou some do build.

Documentação relacionada: [navegação e interface](../funcionalidades/navegacao-e-interface.md)
(modos de menu, painel de Módulos, favoritos na conta). Vitrine viva dos componentes, nos dois
temas: rota `design.components` (`/design/componentes`, `resources/views/design/components.blade.php`).

## Princípios

1. **Cor é token, nunca valor.** Use `bg-surface`, `text-ink-2`, `border-line`, `bg-grena`…
   Nunca `bg-white`, `text-gray-500`, `#A00001`, nem `dark:`. Os tokens trocam sozinhos no tema
   escuro; uma cor fixa fica errada em um dos dois temas.
2. **Tema escuro entra junto.** Toda tela nova funciona nos dois temas desde o primeiro dia.
3. **Grená é ação; carmim é só o logo.** Botão principal, link, foco e seleção são grená. O
   carmim (`#A00001`) aparece apenas na marca — nunca em superfície, botão ou faixa. Amarelo e
   vermelho em superfície lembram alerta e erro: ficam reservados a `warn` e `danger`.
4. **Cada módulo tem a sua cor** (capa, símbolo, substituto de imagem). A cor do módulo
   identifica *onde* a pessoa está; ela não é cor de ação.
5. **Toda tela que lista vários registros tem busca.** Lista paginada busca no servidor; lista
   que vem inteira filtra na página (ver `x-search-bar`).
6. **Cartões grandes com imagem** nas listas; tabela só onde comparar linhas importa
   (pagamentos, históricos, mapas de cotação, financeiro).
7. **Estado em texto, não só em cor.** Os selos (`x-pill`) levam rótulo e, nos estados, ícone —
   quem não distingue verde de vermelho continua lendo.
8. **Sem serviço externo de imagem.** Nada de `placehold.co`: quem não tem foto recebe o
   substituto na cor do módulo (ícone ou iniciais), via `x-media`.
9. **Veículos aparecem só pelo nome** (e placa), sem foto. Exceção: a foto da câmera da
   portaria na Busca do SIV.
10. **O menu antigo continua como opção** (Lateral e Superior). Toda tela precisa funcionar nos
    três modos — por isso o título da tela existe além da capa do módulo.
11. **Na tela, é "Módulo"**; no código o nome ainda é `area` (`AreaColor`, `area=`,
    `navigation-areas`, valor `areas` do `laraNavMode`). Não renomeie o código.

## Tipografia

| Uso | Fonte | Classe |
|---|---|---|
| Títulos (tela, seção, capa, cartão) | Unbounded 500–700 | `font-display` (com `tracking-tight`) |
| Texto e interface | Figtree 400–800 | padrão (`font-sans`) |
| Dados: placa, CPF, matrícula, valores, datas, horários, códigos | JetBrains Mono | `font-mono` |

Rótulos de seção pequenos: `text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3`.

## Paleta

Os tokens estão em `resources/css/app.css` como canais RGB (`--grena: 138 21 56`), para a
opacidade funcionar (`bg-grena/20`). `tailwind.config.js` os expõe como classes. Tema escuro =
classe `dark` no `<html>`, ligada por `partials/theme-script.blade.php`.

### Base

| Token | Classe | Claro | Escuro | Uso |
|---|---|---|---|---|
| `canvas` | `bg-canvas` | `#F5F3F3` | `#120C0E` | Fundo da página |
| `surface` | `bg-surface` | `#FFFFFF` | `#1C1417` | Cartões, painéis, campos |
| `subtle` | `bg-subtle` | `#EEEAEA` | `#271D20` | Fundo secundário, hover, trilho de abas |
| `line` | `border-line` | `#E3DDDE` | `#33272B` | Divisórias |
| `line-strong` | `border-line-strong` | `#D0C7C9` | `#47373C` | Borda de campo e botão secundário |
| `ink` | `text-ink` | `#1E1215` | `#F3ECEE` | Texto principal |
| `ink-2` | `text-ink-2` | `#5A4A4E` | `#BFAEB3` | Texto de apoio |
| `ink-3` | `text-ink-3` | `#8C7C80` | `#87767B` | Rótulos, placeholders, metadados |

### Marca e estados

| Token | Claro | Escuro | Uso |
|---|---|---|---|
| `grena` | `#8A1538` | `#B0284F` | Botão principal, seleção, faixa da placa |
| `grena-hover` | `#6F0F2C` | `#C43A62` | Hover do botão principal |
| `grena-ink` | `#8A1538` | `#F28CA8` | Link e texto de ação sobre superfície |
| `grena-tint` | `#F6E3E9` | `#3B1522` | Fundo de destaque leve, anel de foco |
| `carmim` | `#A00001` | — | **Só o logo** |
| `star` | `#C98A00` | `#F2B73A` | Estrela de favorito |
| `ok` / `ok-soft` | `#147A45` / `#DDF3E6` | `#56CF8D` / `#10301F` | Liberado, pago, ativo, ligado |
| `warn` / `warn-soft` | `#935700` / `#FBEFD8` | `#E6AE52` / `#33260F` | Pendente, aguardando, atenção |
| `danger` / `danger-soft` | `#C22B2B` / `#FCE4E4` | `#FF7B7B` / `#3A1616` | Negado, cancelado, erro |

Sobre fundo sólido `bg-ok`, `bg-warn` ou `bg-danger`, o texto é `text-white dark:text-canvas` —
no escuro esses fundos ficam claros.

### Módulos

Cada módulo tem fundo (`--a-<módulo>`) e tinta (`--a-<módulo>-ink`). Aplique **por estilo
inline**, nunca por classe montada com variável (o Tailwind não enxerga `bg-area-{$x}` no build):

```blade
<span style="{{ \App\View\AreaColor::style('reservas') }}">…</span>          {{-- pinta fundo e tinta --}}
<section style="{{ \App\View\AreaColor::style('reservas', paint: false) }}"> {{-- só define --c/--ci --}}
    <span style="background-color: rgb(var(--c)); color: rgb(var(--ci))">…</span>
</section>
```

| Chave (`area`) | Módulo no menu | Fundo claro | Tinta claro |
|---|---|---|---|
| `portaria` | SIV | `#D6ECFF` | `#0B5A8A` |
| `reservas` | Reservas | `#D9F3E2` | `#17693F` |
| `externos` | Externos | `#E8E0FF` | `#5A2DB0` |
| `freela` | Freelancers | `#FFE4D3` | `#9A4312` |
| `placar` | Placar Clube | `#E3F0C0` | `#4A6508` |
| `lara` | Lara (IA), Bot WhatsApp | `#FFDDEC` | `#A01E5E` |
| `info` | InfoClube, Avisos | `#D2F1EE` | `#0C6660` |
| `compras` | Compras | `#ECE5DB` | `#6A4F2F` |
| `cartao` | Carteirinhas, Assinaturas | `#DDE3F0` | `#34426A` |
| `inicio` | Dashboard, Home Assistant | `#F6E3E9` | `#8A1538` |

Os valores do tema escuro estão no bloco `.dark` de `app.css`. Chave desconhecida cai em
`cartao` (`AreaColor::DEFAULT`). Para um módulo novo com cor própria: acrescente os dois tokens
nos dois temas em `app.css`, a chave em `AreaColor::AREAS` e em `AREAS` do `tailwind.config.js`.

### Forma

| Item | Valor | Classe |
|---|---|---|
| Cartão | raio 18px | `rounded-card` |
| Sombra de cartão | leve | `shadow-card` |
| Sombra de painel flutuante | forte | `shadow-pop` |
| Botões, busca, selos, abas | pílula | `rounded-full` |
| Campos de formulário | raio 12px | `rounded-xl` |

## Modelo de tela

```blade
{{-- O que a tela é, em uma ou duas linhas. --}}
<x-app-layout :bootstrap-grid="false">
    <x-page>                                   {{-- largura 1200px; `narrow` = 860px --}}
        <x-page-title title="Veículos" :back="route('fleet.index')">
            Linha de apoio: o que se faz aqui.
            <x-slot:actions>
                <x-primary-button-a href="{{ route('fleet.vehicles.create') }}"><x-icon name="plus" /> Novo veículo</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        @if ($items->isEmpty())
            <x-empty-state icon="car">Nenhum veículo. <a href="…" class="font-bold text-grena-ink hover:underline">Cadastrar</a>.</x-empty-state>
        @else
            <x-search-bar mode="client" target="#veiculos" placeholder="Buscar veículo ou placa" />
            <div id="veiculos" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as $item)
                    <x-card :href="route('…', $item)" data-search="{{ $item->plate }}">
                        <x-slot:media><x-media :src="$item->imageUrl()" area="portaria" icon="car" /></x-slot:media>
                        …
                        <x-slot:footer><x-pill kind="ok">Ativo</x-pill> …</x-slot:footer>
                    </x-card>
                @endforeach
            </div>
        @endif
    </x-page>
</x-app-layout>
```

- **`:bootstrap-grid="false"`** em toda tela nova. O grid do Bootstrap (`row`, `col-*`) só fica
  ligado (padrão `true`) nas telas antigas que ainda dependem dele.
- **Capa do módulo** (`x-area-cover`): entra **sozinha** pelo layout, no modo Módulos, quando a
  rota está no menu e o grupo tem abas. A tela não a chama. Ações na capa: `<x-slot name="coverActions">`.
  Para dispensar: `<x-app-layout :cover="false">`.
- **Tabela**, quando for o caso: dentro de `rounded-card bg-surface shadow-card`, cabeçalho com a
  classe de rótulo de seção, linhas `divide-y divide-line` com `hover:bg-subtle`, números e datas
  em `font-mono`. Paginação: `{{ $lista->links() }}` (já no visual novo, em
  `resources/views/vendor/pagination/tailwind.blade.php`).
- **Página própria** (sem o layout do painel: quiosque, validação pública, leitura obrigatória,
  página inicial): carregue `@include('partials.theme-script')` e `@vite(...)`; se não puder usar o
  CSS do painel (quiosque, `signature/validate`), copie os valores da paleta para variáveis CSS
  locais, com o grená como `--brand`.

## Componentes (`resources/views/components`)

| Componente | Para quê | Props principais |
|---|---|---|
| `x-page` | Miolo da tela | `narrow` |
| `x-page-title` | Título, apoio (slot) e ações (`actions`) | `title`, `back` (URL da seta) |
| `x-search-bar` | Busca de lista | `mode` (`server`/`client`), `name` (`q`), `target`, `filters`, slot `controls` |
| `x-card` | Cartão de lista | `href`; slots `media` e `footer`; `data-search` para a busca na página |
| `x-media` | Imagem com substituto na cor do módulo | `src`, `alt`, `area`, `icon`, `initials`, `logo`, `ratio` (`wide`/`video`/`short`/`sq`) |
| `x-media-tag` | Etiqueta sobre a imagem | `side` |
| `x-pill` | Selo de estado | `kind` (`ok`/`warn`/`danger`/`info`/`off`), `icon` |
| `x-empty-state` | Lista vazia ou busca sem resultado | `icon` |
| `x-icon` | Ícone do sistema | `name` (lista abaixo) |
| `x-plate` | Placa Mercosul (faixa grená) | `plate`, `size` |
| `x-view-switch` | Alternar Cartões/Lista | `key`, `default` |
| `x-primary-button(-a)` | Ação principal, grená | `size` (`md`/`sm`) |
| `x-secondary-button(-a)` | Ação secundária, contorno | `size` |
| `x-danger-button` | Destrutivo, contorno vermelho | `size` |
| `x-green-button` | Confirmação positiva (liberar, aprovar) | `size` |
| `x-text-input`, `x-select-input`, `x-input-label`, `x-input-error` | Formulário | — |
| `x-auth-card` | Cartão das telas de entrada (login, senha) | `title`, `lead` |
| `x-dashboard.section` / `.stat-card` / `.ha-switch` / `.shortcut` | Painel inicial | `area`, `glyph`, `tone` |

Ícones (`x-icon name=`): `search plus check clock x grid list home car calendar users user
trophy chat info bag card bell star history doc image pencil trash eye download arrow-right
chevron-down moon sun tag filter monitor logout menu lock globe user-plus sliders bolt bulb ban
money shield mail key`. Nome desconhecido desenha um círculo, sem quebrar. Ícone novo: acrescente
o path em `icon.blade.php` (traço 2, estilo Heroicons).

## Busca: qual modo usar

| A lista… | Modo | Como |
|---|---|---|
| vem paginada | `server` | `<x-search-bar />` envia `?q=`; o controller filtra (um `scopeSearch`/`scopeBusca` no model) e pagina com `->withQueryString()` |
| vem inteira | `client` | `<x-search-bar mode="client" target="#lista" />` e `data-search="…"` em cada item (o texto visível também conta) |

Cuidados no modo `client`: auto-refresh da tela não recarrega enquanto há texto na busca; ação
em massa ("selecionar todos") só pega os itens visíveis; a barra fica **fora** de formulários
que gravam algo (Enter na busca não pode enviar o formulário).

## Navegação

- O menu inteiro é definido em `App\View\Navigation` (rota, rótulo, permissão, `area`, `glyph`).
  Item novo = uma entrada lá, com permissão do catálogo `App\Authorization\Permissions` ou Gate.
- O layout renderiza em toda tela: permissão sempre por `can()`, nunca por método do model que
  consulte o banco; destinos conferidos com `Route::has` (cache de rotas velho não derruba tudo).
- Painel de Módulos: abas fixas, só a lista rola. Ao acrescentar um módulo, confira numa tela
  baixa. Detalhes em [navegação e interface](../funcionalidades/navegacao-e-interface.md).

## Pegadinhas

- **Sem CDN do Tailwind.** Classe nova só existe depois de `npm run build`. Mudou Blade/CSS? Rode
  o build antes de conferir no navegador — senão a tela aparece quebrada (colunas encolhidas,
  ícones gigantes).
- **Classe montada por variável não entra no build** (`"bg-{$cor}-100"`). Escreva a classe
  inteira, ou use estilo inline com tokens.
- **`@class([...])` dentro de `class="..."` não funciona** — vira texto no atributo. Use o
  `@class` no lugar do `class`, ou um `x-pill`.
- **Campos de formulário**: uma camada base em `app.css` já dá tokens a `input`/`select`/`textarea`;
  não reponha `bg-white`.
- **Gráficos** (Chart.js): leia as cores dos tokens em tempo de execução
  (`getComputedStyle(document.documentElement).getPropertyValue('--grena')`), nunca hex fixo.
- **Converter tela antiga**: trocar classe por classe da paleta antiga para o token equivalente
  (gray→ink/line/subtle, red→grena ou danger conforme o sentido, green→ok, amber→warn,
  indigo/blue→grena) e tirar todo `dark:`. Revise depois: ação vira grená, estado vira
  ok/warn/danger, e nada de `shadow` virar outro nome dentro de PHP ou JS.

## Testes

`tests/Feature/Rebrand/` tem um teste por grupo de telas. O padrão: renderizar a tela com
`RendersScreens` (usuário mock com `UserAccess`, sem banco) e conferir busca, componentes e
**ausência de paleta antiga** no miolo:

```php
$this->assertDoesNotMatchRegularExpression('#\b(?:bg|text|border)-(?:gray|indigo|red|green|amber)-\d{2,3}\b#', $this->miolo($html));
```

Em template Blade inline de teste, quebre linha antes de `<x-slot>`, senão um buffer de saída
fica aberto e o teste vira "risky".
