# Dashboard — resumo para quem vai mexer

## Em uma frase

Primeira tela de todo mundo depois do login (`GET /dashboard`, rota `dashboard`,
`DashboardController::index`): avisos não lidos e alguns números do dia de cada módulo, um
bloco por módulo, cada bloco só para quem tem a permissão dele. Sem permissão própria.

## O que aparece

| Bloco | Quem vê | De onde vem |
|---|---|---|
| Avisos novos | todos | `Aviso::visibleTo()->active()->notViewedBy()`, até 3 |
| Home Assistant | `home-assistant` | `ContactorStateResolver` — interruptores com o estado **de agora** (sem cache) |
| SIV | `siv.busca` | veículos que entraram hoje, placas da diretoria e as que vencem em 30 dias |
| Reservas | `reservas.agendamentos` | reservas de hoje, futuras, receita do mês e as 5 próximas |
| Externos | todos | empresas, prestadores, acessos permitidos/negados hoje |
| InfoClube | todos | total de informações (uma por `information_id`, sem as excluídas) |

## Regras de desempenho (não desfaça)

- **Sem gráficos e sem CDN.** Havia três consultas agrupadas de 14 dias e o Chart.js baixado
  a cada abertura; saíram de propósito.
- **Números do clube em cache por 60 s** (`Cache::remember("dashboard:{bloco}")`): são iguais
  para todo mundo, um cálculo serve a todos. O que é por pessoa (avisos) ou é controle (Home
  Assistant) e a lista das próximas reservas (models) ficam fora do cache.
- **Filtro de data por intervalo** (`whereBetween` com início/fim do dia), não `whereDate`, para
  usar o índice. Contagem sempre no banco.
- Blocos novos: calcule só dentro do `if ($user->can(...))` e na view use `@can` com a
  **mesma** permissão do catálogo.

## Peças

| Arquivo | Papel |
|---|---|
| `app/Http/Controllers/DashboardController.php` | Monta os blocos; `numbers()` é o cache. |
| `resources/views/dashboard.blade.php` | A tela. |
| `resources/views/components/dashboard/*` | `section`, `stat-card`, `shortcut`, `ha-switch` (interruptor diz o estado em texto). |

A rota passa por `auth`, `verified` e `avisos_obrigatorios`: com aviso obrigatório pendente,
o dashboard desvia para a tela de ciência (ver [InfoClube](InfoClube.md)).

## Testes

`tests/Feature/Rebrand/PainelContaScreensTest.php` — painel leve por módulo, contagem no banco
com cache de um minuto e interruptor com estado em texto. Confira o isolamento SQLite do
`phpunit.xml` antes de rodar.
