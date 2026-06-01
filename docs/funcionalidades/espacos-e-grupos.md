# Espaços e Grupos (Administração)

## O que é

Configuração da estrutura que sustenta os agendamentos: **grupos de espaços** (categorias),
**espaços** individuais e **regras de disponibilidade**. É o "cadastro base" que o sócio
consome ao agendar.

## Para quem

Administradores (painel web, autenticado).

## 1. Grupos de Espaços (PlaceGroup)

Um grupo agrupa espaços semelhantes (ex.: "Quadras de Tênis") e concentra as regras comuns de
horário e reserva.

| Campo | Função |
|-------|--------|
| `name`, `category` | Identificação e tipo (esportiva/social). |
| `image_vertical`, `image_horizontal` | Imagens de destaque. |
| `icon`, `vertices` | Ícone e dados de mapa/localização. |
| `duration` | Duração de cada slot (ex.: 01:00). |
| `interval` | Intervalo entre slots. |
| `daily_limit` | Máximo de reservas por sócio, por dia, no grupo. |
| `minimum_antecedence` / `maximum_antecedence` | Antecedência mínima/máxima (em dias). |
| `start_time` / `end_time` | Horário de funcionamento. |
| `start_time_sales` / `end_time_sales` | Janela de vendas para o mesmo dia. |
| `weekdays` | Dias da semana em que o grupo funciona. |

**Telas:** `GET /place-group` (lista), `…/create`, `…/{id}/edit`. O serviço
`PlaceGroupService` salva o grupo e sincroniza os dias da semana (relação N:N com `Weekday`).

## 2. Espaços (Place)

Um espaço é um local específico dentro de um grupo (ex.: "Quadra 1").

| Campo | Função |
|-------|--------|
| `name` | Nome do espaço. |
| `place_group_id` | Grupo ao qual pertence. |
| `price` | Preço por slot/hora. |
| `status_id` | Ativo/inativo. |
| `image` | Foto do espaço. |

**Telas:** criados/editados a partir do grupo — `place-group.createPlace`,
`place-group.storePlace`, `…editPlace`, `…updatePlace`, `…destroyPlace`. Um espaço pode ser
vinculado a regras de agendamento específicas.

> Observação: os espaços são sempre listados em ordem alfabética (scope global do model
> `Place`).

## 3. Regras de Agendamento (ScheduleRules)

Regras ajustam os horários disponíveis de um ou mais espaços.

| Tipo (`type`) | Efeito |
|---------------|--------|
| `include` | **Libera** horários adicionais (ex.: abrir um período extra no fim de semana). |
| `exclude` | **Bloqueia** horários (ex.: manutenção das 12h às 13h). |

Campos: `name`, `type`, `start_date`/`end_date` (vigência), `start_time`/`end_time` (faixa),
`weekdays` (dias) e `places` (espaços afetados), além de `status_id`.

**Telas:** criadas/editadas a partir do grupo — `place-group.createScheduleRule`,
`schedule-rules.store`, `…editScheduleRule`, `…updateScheduleRule`, `…destroyScheduleRule`.

## Como as configurações afetam o sócio

Ao consultar horários, o sócio recebe a grade gerada a partir de:
`slots base (duration)` → filtro de **dias** → **janela de vendas** → **antecedência** →
**inclusões/exclusões** → **colisões**. Veja o detalhe no [guia de Agendamento](agendamento.md).

## Fluxo recomendado de configuração

1. Criar o **grupo** (horários, antecedência, limite diário, dias da semana, imagens).
2. Criar os **espaços** do grupo (nome, preço, status, imagem).
3. Criar as **regras** de inclusão/exclusão conforme necessário.
4. Validar abrindo a tela de agendamento e conferindo os horários gerados.

## Referência técnica

- Controllers: [`PlaceGroupController`, `PlaceController`, `ScheduleRulesController`](../controllers.md#placegroupcontroller)
- Services: [`PlaceGroupService`, `PlaceService`, `ScheduleRulesService`](../services.md#placegroupservice)
- Models: [`PlaceGroup`, `Place`, `ScheduleRules`, `Weekday`](../models.md#espaços)
