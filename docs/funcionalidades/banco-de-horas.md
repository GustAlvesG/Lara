# Banco de Horas (CompTime)

## O que é

Importa, processa e gerencia o **saldo de horas** dos funcionários a partir de relatórios de
ponto (espelho de ponto em HTML). Calcula créditos e débitos, compensa horas e mostra o saldo
por funcionário e por dia.

## Para quem

Administradores/RH (painel web, autenticado).

## Pré-requisitos

- Estar logado no painel.
- Ter o arquivo de **espelho de ponto** (HTML/XLS/TXT) exportado do sistema de ponto.

## Fluxo passo a passo

### 1. Tela de upload — `GET /comp-time/upload`
Mostra a lista de **estruturas/departamentos** (extraídos dos funcionários já importados) e o
formulário de upload do arquivo.

### 2. Importar arquivo — `POST /comp-time/upload`
O `CompTimeService::importFile()`:
1. Faz o *parsing* do HTML (Symfony DomCrawler), localizando os blocos de cada funcionário.
2. Extrai dados do funcionário (nome, cargo, matrícula, estrutura, CPF, admissão) e
   cria/atualiza em `employees` pela matrícula (`employee_code`).
3. Para cada dia, extrai os apontamentos, o **crédito** e o **débito** (HH:MM), ignorando
   "Feriado" e "DSR".
4. Calcula o saldo e registra as compensações.

### 3. Filtrar — `POST /comp-time/filter`
Filtros: `structure`, `employee_name` (parcial), `employee_code` (exato), `period_start`,
`period_end` e `status` (`with_balance`, `without_balance`, `credit_only`, `debit_only`).

### 4. Detalhes do funcionário — `POST /comp-time/details`
Mostra os lançamentos do funcionário, os ajustes relacionados e um painel com créditos,
débitos, **saldo líquido** e os próximos lançamentos a expirar.

### 5. Detalhes do dia — `POST /comp-time/details/day`
Mostra os registros e ajustes de um dia específico.

### 6. Recalcular saldos — `GET /comp-time/recalculate`
Zera os ajustes, restaura os saldos brutos e recalcula todas as compensações do zero
(ordenado por data).

## Como o saldo é calculado

- Cada lançamento tem **minutos brutos** (`amount_minutes`) e **minutos líquidos**
  (`balance_minutes`).
- A compensação confronta crédito × débito: se o crédito cobre o débito, deduz do crédito;
  caso contrário, zera o crédito e reduz o débito.
- Cada compensação gera um **ajuste** (`TimeAdjustment`) registrando os minutos transferidos e
  os saldos antes/depois.
- **Validade:** lançamentos expiram em **180 dias** (`due_date = entry_date + 180 dias`).

## Campos principais

| Tabela | Campos |
|--------|--------|
| `employees` | `employee_code` (único), `name`, `cpf`, `admission_date`, `position`, `department` |
| `time_entries` | `employee_id`, `entry_date`, `reference_time`, `entry_times`, `type`, `amount_minutes`, `balance_minutes`, `due_date`, `status_id` |
| `time_adjustments` | `entry_time_to_adjust_id`, `entry_time_adjusted_id`, `amount_minutes`, `before_adjustment_minutes`, `after_adjustment_minutes`, `reason` |

## Regras de negócio

- Funcionários são identificados pela **matrícula** (`employee_code`).
- "Feriado" e "DSR" são ignorados na importação.
- O recálculo é **destrutivo** (apaga os ajustes e refaz tudo) — use com atenção.

## Referência técnica

- Controller: [`CompTimeController`](../controllers.md#comptimecontroller)
- Service: [`CompTimeService`](../services.md#comptimeservice)
- Models: [`Employee`, `TimeEntry`, `TimeAdjustment`](../models.md#banco-de-horas)
