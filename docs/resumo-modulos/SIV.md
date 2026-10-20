# SIV — resumo para quem vai mexer

**SIV** = Sistema de Identificação de Veículos. É a origem do projeto (o repositório ainda se
chama `lpr-app`, de *License Plate Recognition*).

Documentação completa:
[estacionamento-placas.md](../funcionalidades/estacionamento-placas.md) (busca, correlação, foto
do FTP) e [frota.md](../funcionalidades/frota.md) (quilometragem, API da portaria, cancela).
O `frota.md` ainda cita a permissão `manage fleet`: está **desatualizado** — valem as do catálogo
abaixo.

## Em uma frase

As câmeras LPR gravam cada leitura de placa (`parkings`) e a foto no FTP; o Lara **cruza** a
leitura com quem passou na portaria naquele instante para dizer quem estava no carro, **decide
a cancela da diretoria** (lista de placas + frota) e **controla a frota** da empresa (saída e
retorno com hodômetro).

## Telas (grupo SIV do menu)

| Aba | Rota | Permissão | Controller |
|---|---|---|---|
| Busca | `parking.search` / `parking.show` (`POST /parking/find`) | `siv.busca` | `ParkingController` |
| Placas Diretoria | `parking-authorizations.*` (resource) | `siv.placas-diretoria` | `ParkingAuthorizationController` |
| Frota | `fleet.index`, `fleet.departure`, `fleet.return` | `siv.frota` | `Fleet\FleetController` |
| Viagens | `fleet.trips`, `fleet.trips.cancel` | `siv.viagens` | `Fleet\FleetController` |
| Veículos | `fleet.vehicles*` | `siv.veiculos` | `Fleet\FleetController` |

## Busca de placa (quem estava no carro)

`ParkingController::show` monta: cartões do veículo, pedidos de carro de aplicativo da placa,
top 3 condutores prováveis (últimos 10 acessos) e o histórico do dia. A ligação leitura → pessoa:

| Quem | Fonte | Liga por | Janela |
|---|---|---|---|
| Sócio | catracas MultiClubes (`mc_sqlsrv`, só leitura) — `AccessController::findAccessByTime` | horário + portaria A/B | ±15 s |
| Terceirizado, freelancer, liberação pontual | `company_access_logs` | só horário | ±60 s |
| Carro de aplicativo | `uber_access_requests` | **placa** normalizada + leitura mais próxima da liberação | até 30 min |

Os dois últimos estão em `App\Services\ParkingAccessCorrelationService`.

**Foto:** `FtpController::getImage()` procura a cópia em `public/storage/img_car/PLACA/…`
(disco `img_car`), depois o lugar antigo (`storage/app/public/img_car`), depois baixa do FTP
(disco `ftp`, `FTP_ROOT`) via arquivo `.part`. FTP fora → cache de 60 s sem tentar de novo. O
banco às vezes traz `.vehicleBody` no nome que o FTP não tem — `openRemote()` tenta os dois.
URL com `FtpController::imageUrl()` (codifica espaço e `&`). Não há `storage:link`.

## Cancela da diretoria (API da câmera)

Grupo `api_token`: `POST /api/parking/check`, `GET /api/parking/check/{plate}`,
`GET /api/parking/authorized` (lista para a câmera decidir offline).
`ParkingAuthorizationService::checkPlate`: **frota ativa com placa entra sempre**
(`reason: fleet_vehicle`, sem validade), antes da lista `parking_authorizations` (que confere
`expiration_date`). Na lista offline, a frota recebe validade sintética de
`fleet.gate_validity_years`. Placa sempre normalizada (maiúsculas, só letras/números).
Toda consulta da câmera vai ao canal de log `parking_access` — e o log nunca pode derrubar a
resposta.

## Frota

- `App\Services\FleetService` é o **único** caminho de saída/retorno — usado pela API da
  portaria (`/api/fleet/*`, `FleetApiController`, `api_token`) e pelas telas. Não duplique a
  validação.
- Uma viagem aberta por veículo; hodômetro não volta nem salta acima de `fleet.max_trip_km`
  (recusa com `error` estável; `force: true` grava e marca `odometer_alert`).
- Horário é o do registro (`registered_at` só para atraso, nunca futuro além de
  `fleet.clock_skew_minutes`). Nada fecha sozinho; aberta há mais de
  `fleet.open_trip_alert_hours` fica destacada.
- Motorista: nome/matrícula/CPF; número que não acha ninguém é erro (`driver_not_found`);
  homônimo grava texto sem vínculo. Funcionários vêm do módulo [Funcionários](Funcion%C3%A1rios.md):
desligado, de férias ou afastado é recusado (`driver_blocked`, 403) e não aparece na busca;
com `FLEET_REQUIRE_EMPLOYEE=true` quem não está no cadastro também (`driver_not_employee`). CPF é
gravado só com dígitos desde 20/10/2026.
- Veículo com viagem não se exclui, só desativa — e desativar tira a liberação da cancela.

## Peças

| Arquivo | Papel |
|---|---|
| `app/Http/Controllers/ParkingController.php`, `AccessController.php`, `FtpController.php` | Busca, catracas, foto. |
| `app/Services/ParkingAccessCorrelationService.php` | Externos e carros de aplicativo por leitura. |
| `app/Services/ParkingAuthorizationService.php` | Lista da diretoria + frota na cancela. |
| `app/Services/FleetService.php`, `app/Models/Fleet/*` | Viagens e veículos. |
| `config/fleet.php`, `config/filesystems.php` (`ftp`, `img_car`) | Limites da frota e FTP das câmeras. |
| `resources/views/parking/*`, `resources/views/fleet/*` | Telas. |

## Testes

`tests/Feature/ParkingCarImageTest.php`, `ParkingAccessCorrelationTest.php`,
`ParkingFleetAccessTest.php`, `FleetMileageTest.php`, `FleetApiEndpointsTest.php`,
`tests/Feature/Rebrand/SivScreensTest.php`. Confira o isolamento SQLite do `phpunit.xml` antes
de rodar e rode só esses arquivos.

Prompt para o sistema da portaria: [docs/frota-prompt-portaria.md](../frota-prompt-portaria.md).
