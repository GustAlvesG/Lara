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
- Foto do carro, que abre em tamanho cheio ao clicar. Sem foto, aparece o aviso "Sem foto
  deste acesso" (ver [Foto do acesso](#foto-do-acesso-ftp-das-câmeras));
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

## Foto do acesso (FTP das câmeras)

As câmeras gravam a foto num servidor FTP, numa pasta por placa. O registro da entrada
(`parkings.file`) guarda só o caminho relativo, no formato `PLACA/2026-10-01T14-47-28&Branco&B.jpg`
(data e hora, cor e portaria). O sistema não grava nessa tabela nem no FTP: só lê.

Na tela de resultado, para cada acesso do dia, `FtpController::getImage()`:

1. **Procura a cópia local** no disco `img_car` (`public/storage/img_car/PLACA/arquivo.jpg`).
   Se existe, usa e não toca no FTP.
2. **Recupera a cópia antiga**, se a foto foi baixada antes de out/2026: naquela época ela ia
   para `storage/app/public/img_car`, que nenhuma URL alcança. Achando lá, copia para o lugar
   certo.
3. **Baixa do FTP** (disco `ftp`, dentro de `FTP_ROOT`). O download vai para um arquivo
   `.part` e só é renomeado no fim, para um download cortado não virar "foto já baixada".
4. **Sem foto**, devolve `false` e a tela mostra "Sem foto deste acesso".

A tela monta o endereço com `FtpController::imageUrl()`, que codifica espaço e `&` do nome
(`Sem placa/…&Prata&A.jpg` vira `Sem%20placa/…%26Prata%26A.jpg`). A foto é servida como
arquivo estático de `public/storage/img_car`, sem `storage:link`.

**Por que pode não haver foto**

- **Data antiga.** No FTP ficam só as fotos recentes (em 02/10/2026, numa amostra de 60
  pastas, a mais antiga era da véspera). De um acesso antigo só existe foto se alguém buscou
  a placa enquanto ela ainda estava no FTP (a cópia local não expira).
- **FTP fora do ar.** A primeira falha de conexão é registrada no log
  (`SIV: FTP das câmeras fora do ar`) e, por 60 segundos, o sistema nem tenta: as buscas
  seguintes abrem na hora, sem foto nova. As fotos já copiadas continuam aparecendo.
- **Nome diferente no FTP.** Desde 26/08/2026 o banco registra o nome com `.vehicleBody`
  antes da extensão (`…&A.vehicleBody.jpg`), e no FTP o arquivo é só `…&A.jpg`. O sistema
  tenta o nome como veio e, não achando, sem esse trecho. Um novo formato de nome exige
  ajuste em `FtpController::openRemote()`.

**Configuração** (`.env`, disco `ftp` em `config/filesystems.php`)

| Variável | Padrão | Para que serve |
|----------|--------|----------------|
| `FTP_HOST`, `FTP_USERNAME`, `FTP_PASSWORD` | — | Servidor e conta. |
| `FTP_PORT` | `21` | Porta. |
| `FTP_ROOT` | `Lara/lpr` | Pasta onde ficam as subpastas por placa. Errada, nenhuma foto é achada. |
| `FTP_TIMEOUT` | `10` | Segundos de espera pela conexão. |
| `FTP_PASSIVE` | `true` | Modo passivo. |

Depois de mudar o `.env` em produção: `php artisan config:cache`.

**Quando a foto não aparece, confira nesta ordem**

1. `storage/logs/laravel.log`, procurando por `SIV:` — conexão, permissão de escrita e caminho
   inválido ficam registrados ali. "Foto não encontrada no FTP" sai em nível `debug`.
2. Se o arquivo existe no FTP em `FTP_ROOT/PLACA/` com o nome de `parkings.file`.
3. Se `public/storage/img_car` é gravável pelo usuário do servidor web.

## Campos / origem dos dados

| Dado | Origem |
|------|--------|
| Placa, cor, nome do arquivo da foto, horário de entrada | Tabela `parkings` (MySQL local), alimentada pelo sistema das câmeras. |
| Nome, telefone, matrícula do condutor | `Analytics.RealizedAccesses` (SQL Server, somente leitura). |
| Imagem do carro | FTP das câmeras, com cópia local em `public/storage/img_car` (via `FtpController::getImage`). |

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

- Controllers: [`ParkingController`](../controllers.md#parkingcontroller), [`AccessController`](../controllers.md#accesscontroller), [`FtpController`](../controllers.md#ftpcontroller-ftpcontrollerphp)
- Teste: `tests/Feature/ParkingCarImageTest.php`
- Integração FTP: [Integrações](../integracoes.md#119-ftp-flysystem)
- Models: [`Parking`, `Access`](../models.md#status-acesso-e-estacionamento)
- Integração SQL Server: [Integrações](../integracoes.md#114-sql-server--multiclubes)
