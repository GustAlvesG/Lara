# Placas de Carro / Estacionamento

## O que é

Ferramenta de **identificação de veículos** pela placa. A partir de uma placa (e
opcionalmente uma data), o sistema mostra os acessos do veículo naquele dia, a foto do carro,
e tenta identificar o **condutor mais provável** com base no histórico de acessos daquela
placa. Cruza dados do estacionamento (banco local) com a base de acessos/catracas
(SQL Server externo — MultiClubes).

## Para quem

Operadores autorizados (portaria/segurança). Exige a **permissão `search parking`** além de
estar autenticado.

## Pré-requisitos

- Usuário autenticado **com a permissão `search parking`**.

## Fluxo passo a passo

### 1. Tela de busca — `GET /parking/search`
Título: "Sistema de Identificação de Veículos". Mostra um pequeno painel com:
- **Total de veículos que entraram hoje** (`todayParkingCount`);
- **Veículos sem placa** (`todayParkingNoPlate`).

Formulário de busca:
| Campo | Obrigatório | Descrição |
|-------|-------------|-----------|
| `plate` | Sim | Placa do veículo (texto livre; aceita "Sem placa"). |
| `datetime` | Não | Data (input `date`). Se vazio, usa **hoje**. |

### 2. Resultado — `POST /parking/find`
A tela de resultado é dividida em três partes:

**a) Cartões do veículo**
- Placa pesquisada;
- Cor do carro (ou "Não encontrado");
- Quantidade de acessos do dia (total de entradas).

**b) Condutores mais prováveis (top 3)**
Cards com **nome**, **telefone** e **percentual de frequência**. O cálculo usa os **últimos 10
acessos** daquela placa (de todos os dias): conta quantas vezes cada par "nome | telefone"
aparece e calcula `ocorrências / total × 100`. Os três mais frequentes são exibidos.

**c) Histórico de acessos do dia**
Lista de cada entrada do veículo no dia escolhido (mais recente primeiro). Para cada acesso:
- Cabeçalho com data/hora ("Acesso em: DD/MM/AAAA às HH:MM:SS");
- Foto do carro (via FTP; placeholder se não houver);
- Tabela com **Matrícula · Nome · Telefone · Horário** das pessoas que acessaram naquele
  momento.

## Como funciona a correlação com os acessos

Para cada registro de entrada do estacionamento (`Parking`), o sistema chama
`AccessController::findAccessByTime($entry_date, $gate)`, que consulta a base de acessos
(SQL Server, `mc_sqlsrv`, tabela `Analytics.RealizedAccesses`) procurando acessos numa
**janela de ±15 segundos** em torno do horário de entrada, na portaria correspondente. Assim,
relaciona quem passou pela catraca no mesmo instante em que o carro entrou.

Campos retornados de cada acesso incluem nome, telefone, matrícula (título), portaria, local e
horário. Quando não há correspondência, o nome aparece como "Visitante" e o telefone como
"Sem Telefone".

## Campos / origem dos dados

| Dado | Origem |
|------|--------|
| Placa, cor, foto, horário de entrada | Tabela `parkings` (MySQL local). |
| Nome, telefone, matrícula do condutor | `Analytics.RealizedAccesses` (SQL Server, somente leitura). |
| Imagem do carro | Disco FTP (via `FtpController::getImage`). |

## Regras de negócio

- **Data padrão:** se nenhuma data for informada, busca o dia atual.
- **Janela de ±15s:** absorve pequenas diferenças entre o relógio do estacionamento e o das
  catracas.
- **Condutor provável:** baseado nos últimos 10 acessos anteriores da placa.
- **Sem dados:** placa não encontrada exibe cor "Não encontrado" e zero acessos.

## Mensagens e validações

- A placa é obrigatória; a data é opcional.
- Acesso negado (HTTP 403) se o usuário não tiver a permissão `search parking`.

## Referência técnica

- Controllers: [`ParkingController`](../controllers.md#parkingcontroller), [`AccessController`](../controllers.md#accesscontroller)
- Models: [`Parking`, `Access`](../models.md#status-acesso-e-estacionamento)
- Integração SQL Server: [Integrações](../integracoes.md#114-sql-server--multiclubes)
