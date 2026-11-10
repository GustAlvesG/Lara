# Navegação e interface

Como a pessoa chega às telas do painel: a navegação por Módulos, o painel de Módulos, os
favoritos, a busca de páginas, o tema e o balão do HAL. Vale para todas as telas que usam o layout do sistema
(`resources/views/layouts/app.blade.php`).

## De onde vem o menu

O menu é definido num lugar só, `App\View\Navigation`. Cada item tem a rota, o rótulo, a permissão
que o libera e, nos grupos, o módulo (cor e símbolo). `Navigation::build()` devolve, já filtrado
pelo que a pessoa pode abrir:

| Chave | Para que serve |
|---|---|
| `links` | Os grupos e itens do menu, já filtrados (base de `groups` e `index`) |
| `groups` | Os módulos do painel, com cor, símbolo e quantidade de páginas |
| `index` | Lista plana de páginas, usada pela busca, pelos favoritos e pelos recentes |
| `current` | A página atual: módulo, rótulo e as abas das páginas irmãs |

Para incluir uma tela no menu, acrescente o item em `Navigation::links()` com a permissão do
catálogo (`App\Authorization\Permissions`) ou um Gate. O layout renderiza em toda tela, então a
permissão é sempre perguntada por `can()` — nunca por método do model que consulte o banco.

## Capa e página atual

No modo Módulos, toda tela de um módulo abre com a **capa**: nome e cor do módulo, as páginas
irmãs como abas e a estrela de favoritar. É por ela que a pessoa favorita a página, então nenhuma
tela de módulo pode ficar sem capa. `Navigation::current()` descobre a página atual em duas
etapas:

1. **Pela rota**: o nome da rota é o do item do menu, ou casa com o `active` dele.
2. **Pelo endereço**: não casou? Vale o item do menu cujo endereço é o começo **mais longo** do
   endereço atual, em segmentos inteiros. É o que leva criar, editar e detalhe à aba do recurso:
   `/placar/times/create` cai em Times; `/company/one-off-accesses/create` em Liberação Pontual
   (e não em Empresas, que é `/company`); os lotes (`freelancer-batches.*`) caem em Serviços /
   Contratos porque moram em `/freelancer-services/lotes`.

Tela nova dentro do endereço do módulo não precisa de nada. Só use `active` quando a tela mora em
outro endereço e se abre por uma página do módulo — é o caso do papel timbrado (aba Modelos, em
Assinaturas) e das modalidades `place-group` (aba Novo Agendamento, em Reservas).

- **Item solto** do menu (Home Assistant, HAL, Bot WhatsApp) tem capa sem abas, com a estrela.
- **Conta** (Perfil, Documentação, Meu setor, Usuários, Setores) entra como grupo só para a capa:
  as abas são os atalhos da conta que a pessoa alcança. Não aparece nos menus.
- **Fora de qualquer módulo** (a vitrine de componentes, Banco de Horas e Questor, que saíram do
  menu) não há página atual nem capa.
- O **Dashboard** dispensa a capa (`:cover="false"`): é o início, e a saudação faz esse papel.
  Qualquer outra tela que dispense a capa perde também a estrela.

Nas telas de criar e editar, a estrela favorita a aba do recurso (a lista), porque favorito é
sempre um destino do menu.

## Navegação por Módulos

É a única navegação do sistema (`partials/navigation-areas.blade.php`). Desde 10/11/2026 é uma
**ilha**: barra flutuante e translúcida (sticky, solta das bordas) com logo, botão Módulos, "você
está em", busca, sino e conta. A ilha toma a cor do módulo aberto: o "você está em" é um chip em
`--c`/`--ci` da área e um traço na base acende em `--ci` (fora de módulo, a cor do início).

Abaixo da ilha, a **doca** de favoritos e recentes. Ela recolhe quando a página desce mais de 80 px
e volta ao subir (`laraNavBar`, no próprio partial); recolhida, fica `inert` (fora do Tab). O
`laraNavBar` também grava a altura real da barra em `--split-top`, usado pelas colunas presas das
telas de duas colunas — o valor de `app.css` (8.5rem) é só o de partida.

No celular, uma doca flutuante embaixo (Início, Favoritos, Módulos no centro, Recentes, Buscar);
`.nav-tabbar-pad` e `--lara-balao-bottom` (`app.css`) reservam o espaço dela.

Os menus **Lateral** e **Superior** de antes do rebrand, que eram opção no menu da conta, foram
**removidos em 28/10/2026**, junto com o sino flutuante que eles usavam. A chave `laraNavMode` que
ficou no navegador de quem os usava é simplesmente ignorada.

## Balão do HAL (IA)

Quem tem a permissão `lara` vê um balão no canto inferior direito de toda tela. Ele abre o chat
num painel lateral (no celular, em tela cheia, com o balão acima da doca de baixo). Detalhes em
[lara-ia.md](lara-ia.md). Como o layout roda em toda tela, o balão não carrega nada na
renderização: a conversa e o estado da IA só são pedidos quando o painel abre.

## Painel de Módulos

Abre pelo botão **Módulos** (ou pela doca de baixo, no celular). Desde 10/11/2026:

- **Todos os módulos de uma vez, fechados** (desde 11/11/2026, pedido do usuário: ninguém deve
  precisar rolar para achar um módulo). Cada módulo é uma faixa compacta na cor dele: o **nome**
  abre a primeira aba; o botão ao lado (número de páginas + seta) **abre** a lista das abas
  (`groups[].children` de `App\View\Navigation::build`), um módulo por vez (`openMod`). A página
  atual fica marcada (`aria-current`) e o módulo atual ganha um contorno na cor dele. A lista de
  abas abre **sobreposta** (absoluta, sobre os módulos de baixo, sem empurrar a grade); perto do fim
  do painel, sem espaço embaixo, abre para cima (`openUp`, calculado no `toggleMod`). Clicar no
  vão entre os módulos fecha. Esc fecha
  primeiro o módulo aberto, depois o painel. Item solto do menu (Home Assistant) não tem botão.
  Grade: 2 colunas no celular e no md, 3 no lg, 4 no xl.
- **Tela larga (md+):** módulos à esquerda, **Favoritos** e **Recentes** numa coluna à direita,
  tudo ao mesmo tempo. **Celular:** folha que sobe de baixo, com abas Módulos / Favoritos /
  Recentes (`launcherTab`; as classes `md:!grid`/`md:!flex`/`md:!block` vencem o `x-show` na tela
  larga).
- Cabeçalho (título, abas, Fechar) e rodapé (dica do `Ctrl K` e **Organizar menu**) fixos; só o
  miolo rola. Ao abrir, o foco vai para o Fechar; Esc e clique fora fecham.
- A ordem dos módulos é a que a pessoa definiu em **Organizar** (`order` do grid via `navRank`).

Ao acrescentar um módulo ao menu, confira o painel numa tela baixa (notebook de 768 px de
altura): o miolo pode rolar, cabeçalho e rodapé não.

## Favoritos e ordem do menu

A estrela na capa de cada página a guarda nos favoritos; **Organizar** muda a ordem dos módulos.
As duas coisas são gravadas **na conta** (coluna `users.nav_preferences`, JSON com `favorites` e
`order`), pela rota `PUT /nav-preferences` (`NavPreferencesController`). Por isso acompanham a
pessoa em qualquer computador e não somem ao limpar os dados do navegador.

- O navegador guarda uma cópia local. Se a gravação na conta falhar, a cópia segura até a próxima
  mudança.
- Quem nunca salvou nada na conta (`nav_preferences` nulo) sobe, uma vez, o que o navegador tinha.
- Favorito guarda só a chave da rota. Se a pessoa perder a permissão da tela, o favorito
  simplesmente não aparece.
- O layout chama a rota por URL fixa, e não por `route()`: um cache de rotas desatualizado não
  pode derrubar todas as telas.

Os **recentes** (últimas páginas abertas) ficam só no navegador.

## Busca de páginas

A lupa da barra (ou `Ctrl K`) busca qualquer página do menu pelo nome. Usa o `index` do
`Navigation`, então só oferece o que a pessoa pode abrir.

## Tema

Claro, escuro ou o do sistema, escolhido no menu da conta. Fica no navegador (`laraTheme`) e é
aplicado antes do CSS (`partials/theme-script.blade.php`). As cores das telas são tokens
(`resources/css/app.css`) que trocam de valor no tema escuro — por isso as telas não usam classes
`dark:` nem cores fixas.

## Cores dos módulos

Cada módulo tem uma cor de fundo e uma tinta (`--a-<módulo>` e `--a-<módulo>-ink` em
`app.css`). Os componentes aplicam por estilo inline, via `App\View\AreaColor::style()`, e não
por classe montada com variável — o build do Tailwind não enxergaria a classe.

No código, o módulo ainda se chama `area` (propriedade dos componentes, `AreaColor`,
`navigation-areas`). Na tela, o nome é sempre **Módulo**.

## Build

Não há Tailwind por CDN. Toda classe nova só existe depois de `npm run build`; o `deploy_prod.sh`
faz isso a cada deploy.

## Testes

`tests/Feature/Rebrand/ShellTest.php` (menu, capa, página atual em criar/editar, só Módulos),
`NavPreferencesTest.php` (favoritos na conta), `tests/Feature/Authorization/NavigationMenuTest.php`
(o que cada setor enxerga — as páginas chegam no JSON do `laraShell`, por isso o teste compara sem
as barras escapadas) e `tests/Feature/LaraWidgetTest.php` (balão do HAL).
