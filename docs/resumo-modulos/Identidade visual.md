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
10. **Só existe a navegação por Módulos** desde 28/10/2026: os menus Lateral e Superior (que eram
    opção) e o sino flutuante saíram. O título da tela continua existindo além da capa do módulo.
    O canto inferior direito é do **balão do HAL** (IA) — não ponha outro elemento fixo ali.
11. **Na tela, é "Módulo"**; no código o nome ainda é `area` (`AreaColor`, `area=`,
    `navigation-areas`). Não renomeie o código.

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
| `lara` | HAL (IA), Bot WhatsApp | `#FFDDEC` | `#A01E5E` |
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
    <x-page>                                   {{-- largura 1200px; `narrow` = 860px; `wide` = 1680px --}}
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
- **Capa do módulo** (`x-area-cover`): entra **sozinha** pelo layout, no modo Módulos, em toda
  tela que pertence a um módulo — inclusive criar, editar e detalhe, que caem na aba do recurso
  (regras em `docs/funcionalidades/navegacao-e-interface.md`, "Capa e página atual"). Item solto
  do menu (Home Assistant, Bot WhatsApp) tem capa sem abas. A tela não a chama. Ações na capa:
  `<x-slot name="coverActions">`. Para dispensar: `<x-app-layout :cover="false">` — só o
  Dashboard faz isso; dispensar a capa tira também a estrela de favoritar.
- **Tabela**, quando for o caso: dentro de `rounded-card bg-surface shadow-card`, cabeçalho com a
  classe de rótulo de seção, linhas `divide-y divide-line` com `hover:bg-subtle`, números e datas
  em `font-mono`. Paginação: `{{ $lista->links() }}` (já no visual novo, em
  `resources/views/vendor/pagination/tailwind.blade.php`).
- **Página própria** (sem o layout do painel: quiosque, validação pública, leitura obrigatória,
  página inicial): carregue `@include('partials.theme-script')` e `@vite(...)`; se não puder usar o
  CSS do painel (quiosque, `signature/validate`), copie os valores da paleta para variáveis CSS
  locais, com o grená como `--brand`.
- **Telas de fora do painel** (desde 10/11/2026): a página inicial (`welcome`), **Entrar** e
  **Criar conta** usam fotos reais do clube de `public/images/auth/` (cópias recortadas sem a marca
  d'água e comprimidas — não aponte para uploads de `public/images/`, que podem ser apagados pelo
  sistema) e o movimento de `partials/entrada-estilos` (`auth-photo-img`, `auth-enter`,
  `auth-tagline`, `auth-submit`, `auth-link`; tudo para com "reduzir movimento"). A lista de
  módulos da página inicial acompanha o menu (`App\View\Navigation`): módulo novo no menu entra
  lá também. As telas de senha (esqueci, redefinir, confirmar, verificar e-mail) seguem no
  `x-guest-layout` com o cartão.
- **Aviso com confirmação** (desde 23/10/2026): regra que não deve recusar, só avisar, lança
  `App\Exceptions\ConfirmationRequired::unless($force, $avisos)` no serviço. A exceção volta à
  tela de origem e o `partials.alerts` mostra o quadro "Atenção: confira antes de seguir" com
  *Confirmar mesmo assim* (reenvia o mesmo formulário, inclusive `PUT`/`DELETE` e campos em
  lista, com `force=1`) e *Não fazer*. O controller só repassa `$request->boolean('force')`.
  Usado nos Torneios; serve a qualquer tela que inclua os alertas.

## Visual de 11/11/2026 (todos os módulos)

Junto com a barra "ilha", os componentes de uso geral foram refeitos — por isso toda tela de todo
módulo mudou junto, sem mexer na lógica de nenhuma:

- **Capa do módulo** (`x-area-cover`): cartão arredondado (28px) na cor da área, alinhado à
  largura da página (1200px), símbolo grande como marca d'água, título em Unbounded 32px e as abas
  numa barra de pílulas (a ativa em `bg-surface`). Entra uma vez ao abrir (`auth-enter`).
- **Movimento**: `partials/entrada-estilos` agora vem no `layouts/app` (toda tela). Botões
  (`x-primary-button(-a)`, `x-secondary-button(-a)`, `x-danger-button`, `x-green-button`) sobem
  1px no hover e afundam no clique; `x-card` com `href` sobe 2px. Tudo para com "reduzir movimento".
- **Título da tela** (`x-page-title`): título 26px, apoio 15px, voltar com 44px.
- **Busca** (`x-search-bar`): campo de 48px em pílula com sombra; botão Buscar da mesma altura.
- **Lista vazia** (`x-empty-state`): centrada, símbolo num círculo grená claro.
- **Miolo** (`x-page`): respiro maior (`gap-5`, `pt-8` no sm+).
- **Faixa do módulo** (`x-mod-hero`, 11/11/2026): no alto da tela inicial de cada módulo, escura
  nos dois temas, com números que a tela já tem (com `href`, viram atalho; `value` nulo = atalho com
  seta) e, no lg+, o objeto do módulo. `variant` dá a cor, `motif` o objeto (padrão do variant):
  `ext` crachá (Externos), `staff` crachá "Funcionário" (Funcionários), `freela` cartão de ponto
  (Freelancers), `pen` assinatura (Assinaturas, Contratos), `buy` cupom de cotação (Compras),
  `card` cartão do sócio com o logo (Carteirinhas), `score` painel de LED (Placar) ou `motif=bracket`
  chave (Torneios), `tix` ingresso (Ingressos), `rec` vídeo gravando (RePET), `ha` lâmpada (Home
  Assistant), `chat` conversa (Bot WhatsApp), `mail` envelope (Disparo de E-mails). SIV e Reservas
  têm faixa própria (`siv-night`, `res-court`). HAL fica sem faixa: não tem tela, é o painel do
  balão, com cabeçalho escuro próprio (`hal-band`, 11/11/2026) e fora do painel de Módulos.
- **Tablet dos freelancers** (`/kiosk`, 11/11/2026): página fora do layout, com CSS próprio
  (`resources/css/kiosk.css`) nos valores do Lara e a faixa do cartão de ponto (`.k-band`) na
  entrada, na barra de cima, no cartão do freelancer, na soma do lote e na cabeça dos recibos.
- **Camadas da barra de cima:** a ilha leva `z-10` porque o `backdrop-blur` faz da ilha e da doca
  (Favoritos/Recentes) camadas próprias; sem isso a doca tampa o menu da conta e o do sino.
- **Tabelas** (sem componente: camada única, bloco "Tabelas" no fim de `resources/css/app.css` +
  `resources/js/tables.js`). Vale para toda `<table>` com `w-full`, `min-w-full`, `text-sm`,
  `text-xs` ou `text-left` dentro do `<main class="lara-tables">` do `layouts/app` — e ganha das
  classes de cada tela (padding, bordas, cinza antigo), mas não mexe em alinhamento, cor e fonte
  das células. Cabeçalho em versalete miúdo sobre `--canvas`, linhas de 14px×16px, fio grená na
  borda da linha sob o mouse, `tfoot` em negrito, números tabulares; `text-xs` é a versão justa.
  **Todas as colunas à vista:** no celular (< 640px) cada linha vira um cartão "rótulo: valor"
  (1ª coluna = título do cartão, coluna "Ações"/sem título = pé do cartão); na tela larga, tabela
  maior que o espaço rola de lado com a 1ª coluna presa e a borda direita esmaecida enquanto houver
  colunas. Tabela fora de contêiner que rola ganha um (`.lara-table-scroll`), e item de grade/flex
  com tabela ganha `min-width: 0` — antes, colunas sumiam cortadas pelo `overflow-hidden` do cartão.
  Só vira cartão a tabela simples (uma linha de cabeçalho sem colspan, sem rowspan, linha com uma
  célula por coluna ou uma célula só); `data-table="scroll"` impede. E-mails, PDFs e impressões
  não usam o layout e ficam como estão.
  **Linha clicável** (11/11/2026): clicar em qualquer ponto da linha faz o mesmo que o 1º link
  dela (ID, nome) ou, sem link, o 1º botão (`data-row-link`, cursor de mão). Nunca usa botão que
  envia formulário, desabilitado, de `DELETE` ou com texto de excluir/cancelar/remover/recusar etc.;
  nem `mailto:`, `tel:`, WhatsApp ou `#`. Linha com campo (edição em lote) ou com `@click`
  próprio fica de fora. Clique em link, botão ou campo segue normal; texto selecionado não navega;
  Ctrl/⌘ ou botão do meio abre o link em outra aba. Para a linha ser clicável, basta ela ter o link.

Tela nova: use esses componentes e ela já nasce no visual. Classe nova do Tailwind só existe depois
do `npm run build`.

## Componentes (`resources/views/components`)

| Componente | Para quê | Props principais |
|---|---|---|
| `x-page` | Miolo da tela | `narrow` (860px), `wide` (1680px, editor com prévia ao lado) |
| `x-page-title` | Título, apoio (slot) e ações (`actions`) | `title`, `back` (URL da seta) |
| `x-search-bar` | Busca de lista | `mode` (`server`/`client`), `name` (`q`), `target`, `filters`, slot `controls` |
| `x-card` | Cartão de lista | `href`; slots `media` e `footer`; `data-search` para a busca na página |
| `x-media` | Imagem com substituto na cor do módulo | `src`, `alt`, `area`, `icon`, `initials`, `logo`, `ratio` (`wide`/`video`/`short`/`sq`), `zoom` (foto aproxima 3% no hover do `group` em volta) |
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
| `x-auth-card` | Cabeçalho + formulário das telas de entrada. `variant="card"` (padrão): cartão com logo, telas de senha. `variant="flush"`: sem caixa, dentro do `x-auth-layout` | `title`, `lead`, `eyebrow`, `variant` |
| `x-auth-layout` | Casca de **Entrar** e **Criar conta** (desde 10/11/2026): foto do clube à esquerda (faixa curta no celular), formulário à direita | `title`, `photo`, `photo-alt`, `photo-position`, `tagline` |
| `x-password-input` | Campo de senha com mostrar/ocultar e aviso de Caps Lock (script no `x-auth-layout`) | `id`, `autocomplete`, `invalid`, `describedby` |
| `x-dashboard.panel` / `.metric` / `.ha-switch` | Painel inicial (desde 10/11/2026; `section`, `stat-card` e `shortcut` saíram) | `area`, `glyph`, `href`, `tone` |

Ícones (`x-icon name=`): `search plus check clock x grid list home car calendar users user
trophy chat info bag card bell star history doc image pencil trash eye download arrow-right
chevron-down moon sun tag filter monitor logout menu lock globe user-plus sliders bolt bulb ban
money shield mail key eye-off`. Nome desconhecido desenha um círculo, sem quebrar. Ícone novo: acrescente
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
