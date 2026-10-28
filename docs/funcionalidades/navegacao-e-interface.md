# Navegação e interface

Como a pessoa chega às telas do painel: a navegação por Módulos, o painel de Módulos, os
favoritos, a busca de páginas, o tema e o balão da Lara. Vale para todas as telas que usam o layout do sistema
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

- **Item solto** do menu (Home Assistant, Lara, Bot WhatsApp) tem capa sem abas, com a estrela.
- **Conta** (Perfil, Documentação, Meu setor, Usuários, Setores) entra como grupo só para a capa:
  as abas são os atalhos da conta que a pessoa alcança. Não aparece nos menus.
- **Fora de qualquer módulo** (a vitrine de componentes, Banco de Horas e Questor, que saíram do
  menu) não há página atual nem capa.
- O **Dashboard** dispensa a capa (`:cover="false"`): é o início, e a saudação faz esse papel.
  Qualquer outra tela que dispense a capa perde também a estrela.

Nas telas de criar e editar, a estrela favorita a aba do recurso (a lista), porque favorito é
sempre um destino do menu.

## Navegação por Módulos

É a única navegação do sistema (`partials/navigation-areas.blade.php`): barra no alto com o botão
Módulos, "você está em", busca, sino e conta; abaixo, favoritos e recentes; no celular, uma barra
de abas embaixo (Início, Favoritos, Módulos, Buscar).

Os menus **Lateral** e **Superior** de antes do rebrand, que eram opção no menu da conta, foram
**removidos em 28/10/2026**, junto com o sino flutuante que eles usavam. A chave `laraNavMode` que
ficou no navegador de quem os usava é simplesmente ignorada.

## Balão da Lara (IA)

Quem tem a permissão `lara` vê um balão no canto inferior direito de toda tela. Ele abre o chat
num painel lateral (no celular, em tela cheia, com o balão acima da barra de baixo). Detalhes em
[lara-ia.md](lara-ia.md). Como o layout roda em toda tela, o balão não carrega nada na
renderização: a conversa e o estado da IA só são pedidos quando o painel abre.

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

`tests/Feature/Rebrand/ShellTest.php` (menu, capa, página atual em criar/editar, só Módulos),
`NavPreferencesTest.php` (favoritos na conta), `tests/Feature/Authorization/NavigationMenuTest.php`
(o que cada setor enxerga — as páginas chegam no JSON do `laraShell`, por isso o teste compara sem
as barras escapadas) e `tests/Feature/LaraWidgetTest.php` (balão da Lara).
