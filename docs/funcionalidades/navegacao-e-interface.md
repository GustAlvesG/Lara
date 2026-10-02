# Navegação e interface

Como a pessoa chega às telas do painel: os três modos de navegação, o painel de Módulos, os
favoritos, a busca de páginas e o tema. Vale para todas as telas que usam o layout do sistema
(`resources/views/layouts/app.blade.php`).

## De onde vem o menu

O menu é definido num lugar só, `App\View\Navigation`. Cada item tem a rota, o rótulo, a permissão
que o libera e, nos grupos, o módulo (cor e símbolo). `Navigation::build()` devolve, já filtrado
pelo que a pessoa pode abrir:

| Chave | Para que serve |
|---|---|
| `links` | Os grupos e itens do menu (lateral, superior e painel de Módulos) |
| `groups` | Os módulos do painel, com cor, símbolo e quantidade de páginas |
| `index` | Lista plana de páginas, usada pela busca, pelos favoritos e pelos recentes |
| `current` | A página atual: módulo, rótulo e as abas das páginas irmãs |

Para incluir uma tela no menu, acrescente o item em `Navigation::links()` com a permissão do
catálogo (`App\Authorization\Permissions`) ou um Gate. O layout renderiza em toda tela, então a
permissão é sempre perguntada por `can()` — nunca por método do model que consulte o banco.

## Modos de navegação

A pessoa escolhe no menu da conta, em **Navegação**:

| Modo | O que mostra |
|---|---|
| **Módulos** (padrão) | Barra no alto com o botão Módulos, "você está em", busca, sino e conta; abaixo, favoritos e recentes |
| **Lateral** | O menu lateral de antes do rebrand |
| **Superior** | O menu no alto de antes do rebrand |

A escolha fica no navegador (`localStorage`, chave `laraNavMode`) e é aplicada no `<html>`
(`data-nav`) antes da primeira pintura, para a tela não piscar com o menu errado.

## Painel de Módulos

Abre pelo botão **Módulos** (ou pela barra de baixo, no celular) e tem três abas: **Todos os
módulos**, **Favoritos** e **Recentes**.

- As abas ficam sempre à vista e não têm rolagem própria. Elas dividem a largura do painel em
  três; em tela estreita, o rótulo da primeira encurta para "Módulos".
- O rodapé (dica do `Ctrl K` e "Organizar") também é fixo.
- Só a lista do meio rola, e só quando não cabe na altura da tela. Os blocos dos módulos são
  compactos (símbolo ao lado do nome) justamente para caberem sem rolagem na maioria das telas.
- A ordem dos módulos é a que a pessoa definiu em **Organizar**.

Ao acrescentar um módulo ao menu, confira o painel numa tela baixa (notebook de 768 px de
altura): a lista pode rolar, as abas não.

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

`tests/Feature/Rebrand/ShellTest.php` (menu, capa e modos), `NavPreferencesTest.php` (favoritos
na conta) e `tests/Feature/Authorization/NavigationMenuTest.php` (o que cada setor enxerga).
