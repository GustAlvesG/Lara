# Placas de Carro / Estacionamento

## O que é

Ferramenta de **identificação de veículos** pela placa. A partir de uma placa (e
opcionalmente uma data), o sistema mostra os acessos do veículo naquele dia, a foto do carro,
e tenta identificar o **condutor mais provável** com base no histórico de acessos daquela
placa. Cruza a leitura da câmera (banco local) com três fontes de "quem estava no carro":

- **Associados** — acessos das catracas (SQL Server externo — MultiClubes);
- **Externos** — terceirizados, freelancers e liberações pontuais registrados na portaria;
- **Carros de aplicativo** — pedidos feitos pelo WhatsApp para aquela placa.

## Para quem

Operadores autorizados (portaria/segurança). Exige a **permissão `siv.busca` (Busca de placas)** além de
estar autenticado.

## Pré-requisitos

- Usuário autenticado **com a permissão `siv.busca` (Busca de placas)**.

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
A tela de resultado é dividida em quatro partes:

**a) Cartões do veículo**
- Placa pesquisada;
- Cor do carro (ou "Não encontrado");
- Quantidade de acessos do dia (total de entradas).

**b) Pedidos de carro de aplicativo**
Só aparece quando há pedido para a placa: os **feitos no dia** e os **liberados no dia**. Cada
cartão mostra quem pediu, matrícula/CPF, situação do pedido (aguardando, concluído, expirado),
hora do pedido, hora em que a entrada foi liberada, local no clube, WhatsApp, a conferência do
sócio e o print da corrida. A seção aparece **mesmo sem leitura da câmera** — o pedido casa pela
placa. Quem tem a permissão *Carros de aplicativo e fila da portaria* vê o atalho para a tela
de pedidos de Externos, já filtrada pela placa.

**c) Condutores mais prováveis (top 3)**
Cards com **nome**, **telefone** e **percentual de frequência**. O cálculo usa os **últimos 10
acessos** daquela placa (de todos os dias): conta quantas vezes cada pessoa aparece e calcula
`ocorrências / total × 100`. Os três mais frequentes são exibidos. Entram associados e
externos **liberados** (o card do externo traz o tipo: Terceirizado, Freelancer ou Liberação
pontual). Carro de aplicativo fica de fora: quem pede é o passageiro, não quem dirige.

**d) Histórico de acessos do dia**
Lista de cada entrada do veículo no dia escolhido (mais recente primeiro). Para cada acesso:
- Cabeçalho com data/hora e a contagem de pessoas ligadas àquela leitura;
- Foto do carro, que abre em tamanho cheio ao clicar. Sem foto, aparece o aviso "Sem foto
  deste acesso" (ver [Foto do acesso](#foto-do-acesso-ftp-das-câmeras));
- Uma tabela só, com **Tipo · Nome · Vínculo · Telefone · Horário**:

| Tipo | Nome (e linha de baixo) | Vínculo |
|------|-------------------------|---------|
| Associado | Nome do sócio | Matrícula |
| Terceirizado | Nome · cargo | Empresa |
| Freelancer | Nome | — |
| Liberação pontual | Nome · motivo | — |
| Carro de aplicativo | Quem pediu · local no clube | Matrícula/CPF informado |

O externo cujo registro foi **negado** aparece com o selo "Negado": ele estava na portaria
naquele instante, mas não foi liberado.

## Como funciona a correlação com os acessos

Cada leitura da câmera (`Parking`) é ligada às pessoas por três caminhos. Os dois últimos ficam
em `App\Services\ParkingAccessCorrelationService`.

| Quem | Fonte | Como liga | Janela |
|------|-------|-----------|--------|
| Associado | Catracas do MultiClubes (`mc_sqlsrv`) | Horário + portaria (A/B) | ±15 s |
| Terceirizado, freelancer, liberação pontual | `company_access_logs` | Só horário | ±60 s |
| Carro de aplicativo | `uber_access_requests` | **Placa** + leitura mais próxima da liberação | até 30 min |

**Associados.** `AccessController::findAccessByTime($entry_date, $gate)` consulta os acessos de
catraca numa janela de ±15 segundos em torno da leitura, na portaria correspondente. Sem
correspondência de cadastro, o nome aparece como "Visitante" e o telefone como "Sem Telefone".

**Externos.** O registro da portaria (Monitor de Acesso) não guarda placa nem portaria, então a
ligação é só pelo horário: entra quem foi registrado até 60 segundos antes ou depois da leitura.
A janela é maior que a das catracas porque o registro é manual. Ela foi medida nos acessos de
Uber de set/2026, que têm placa dos dois lados: ±15 s pega 64% dos casos e ±60 s pega 89%.
A mesma pessoa registrada duas vezes na janela aparece uma vez (o registro liberado vence o
negado). Terceirizado excluído do cadastro continua aparecendo com o nome.

> **Limite conhecido:** por ser só horário, um externo que passou a pé pela portaria no mesmo
> minuto aparece junto do carro. É o mesmo tipo de ruído dos associados; o card de condutores
> mais prováveis, que soma dez acessos, é o que separa coincidência de padrão.

**Carros de aplicativo.** O pedido tem a placa (`vehicle_plate`), então a ligação é exata — a
placa buscada é normalizada (maiúsculas, só letras e números) antes de comparar. O horário só
decide a qual leitura do dia o pedido pertence: cada pedido **liberado** vai para a leitura mais
próxima do `accessed_at`, desde que a até 30 minutos. Pedido sem liberação (aguardando ou
expirado), ou liberado longe de qualquer leitura, não entra em nenhum acesso, mas continua na
seção de pedidos. O registro manual de motorista de aplicativo (`app_drivers`) não entra.

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
| Nome, telefone, matrícula do associado | Catracas do MultiClubes (SQL Server, somente leitura). |
| Terceirizado, freelancer, liberação pontual | `company_access_logs` e os cadastros ligados (`company_workers`, `companies`, `freelancers`, `one_off_accesses`). Ver [Controle de Acesso de Empresas](../company-access-control.md). |
| Pedido de carro de aplicativo | `uber_access_requests`. |
| Imagem do carro | FTP das câmeras, com cópia local em `public/storage/img_car` (via `FtpController::getImage`). |

## Regras de negócio

- **Data padrão:** se nenhuma data for informada, busca o dia atual.
- **Janela de ±15s (associados):** absorve pequenas diferenças entre o relógio do
  estacionamento e o das catracas.
- **Janela de ±60s (externos):** o registro na portaria é manual e não guarda placa.
- **Carro de aplicativo:** casa pela placa; o pedido liberado vai para a leitura mais próxima,
  a até 30 minutos.
- **Condutor provável:** baseado nos últimos 10 acessos anteriores da placa, somando associados
  e externos liberados.
- **Sem dados:** placa não encontrada exibe cor "Não encontrado" e zero acessos — os pedidos de
  carro de aplicativo da placa aparecem assim mesmo.
- **Quem vê:** a busca mostra os externos e os pedidos a quem tem `siv.busca`, sem exigir as
  permissões da área de Externos. Só o atalho para a tela de pedidos depende da permissão de lá.

## Mensagens e validações

- A placa é obrigatória; a data é opcional.
- Acesso negado (HTTP 403) se o usuário não tiver a permissão `siv.busca` (Busca de placas).

## Referência técnica

- Controllers: [`ParkingController`](../controllers.md#parkingcontroller), [`AccessController`](../controllers.md#accesscontroller), [`FtpController`](../controllers.md#ftpcontroller-ftpcontrollerphp)
- Service: [`ParkingAccessCorrelationService`](../services.md#parkingaccesscorrelationservice)
- Testes: `tests/Feature/ParkingCarImageTest.php` (foto), `tests/Feature/ParkingAccessCorrelationTest.php`
  (externos e carros de aplicativo), `tests/Feature/Rebrand/SivScreensTest.php` (tela)
- Integração FTP: [Integrações](../integracoes.md#119-ftp-flysystem)
- Models: [`Parking`, `Access`](../models.md#status-acesso-e-estacionamento)
- Integração SQL Server: [Integrações](../integracoes.md#114-sql-server--multiclubes)
