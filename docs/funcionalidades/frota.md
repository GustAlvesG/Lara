# Frota — Controle de Quilometragem

## O que é

O registro de quem sai com um veículo da empresa, para onde vai e com qual quilometragem —
e, na volta, a quilometragem de retorno. É o mesmo caderno que a portaria preenchia à mão,
com uma diferença: **saída e retorno são as duas metades de uma viagem só**, não dois
registros soltos que alguém precisa parear depois.

Os horários **não são digitados**: cada metade é carimbada no momento em que o registro
chega. Ninguém informa "saiu às 14h" — o sistema sabe quando recebeu.

## Para quem

- **Portaria** — registra saída e retorno pelo sistema da guarita, que fala com a API do
  Lara (o mesmo que já registra os acessos de terceirizados, freelancers e Uber).
- **Administração** — acompanha o painel, o histórico e a quilometragem rodada, e mantém o
  cadastro dos veículos. Permissão `manage fleet`.

## Pré-requisitos

- Veículos cadastrados (**SIV → Veículos**). O seeder `FleetVehicleSeeder` já cria
  Celta, Caminhão e Polo.
- Token de API (`services.api.token`) no sistema da portaria — o mesmo já usado pelos
  endpoints de estacionamento e webhooks.

## Fluxo passo a passo

```
SAÍDA                                    RETORNO
─────                                    ───────
veículo + motorista + destino + km       veículo + km
        ↓                                        ↓
viagem aberta (status "open")            viagem fechada (status "closed")
carimbada com a hora do registro         km rodados = retorno − saída
                                         carimbada com a hora do registro
```

Enquanto a viagem não é fechada, **o mesmo veículo não sai de novo**: duas viagens abertas
para o mesmo carro significariam que um dos dois retornos fecharia contra a saída errada.
Saída registrada por engano se resolve com o cancelamento, que preserva a linha e libera o
veículo.

## Campos

### Veículo (`fleet_vehicles`)

| Campo | Obrigatório | Descrição |
|---|---|---|
| Nome | Sim | Como a portaria chama o carro ("Celta"). É por ele que a API resolve o veículo. |
| Placa | Não | Se cadastrada, também resolve o veículo na API. |
| Descrição | Não | Modelo, cor, o que ajudar a identificar. |
| Quilometragem atual | Não | Ponto de partida do cadastro. Depois disso quem atualiza são as viagens. |
| Ativo | Sim | Inativo some da lista da portaria sem perder o histórico. |

### Viagem (`fleet_trips`)

| Campo | Preenchido | Descrição |
|---|---|---|
| Veículo | Saída | Resolvido por id, placa ou nome. |
| Motorista | Saída | Nome, matrícula ou CPF. Quando bate com um funcionário, o vínculo (`employee_id`) é guardado. |
| Destino | Saída | Texto livre. |
| Km de saída / retorno | Saída / Retorno | Hodômetro lido no painel. Pela guarita, só os 3 últimos números (ver abaixo); o Lara grava o número inteiro. |
| Horário de saída / retorno | Saída / Retorno | O momento do registro. |
| Operador | Saída / Retorno | Quem registrou, do lado da portaria. |
| Observação | Saída / Retorno | Texto livre. |
| Km rodados | Retorno | `retorno − saída`, gravado no fechamento. |
| Situação | — | `open` (em rota), `closed` (concluída), `canceled`. |

## Regras de negócio

- **Uma viagem aberta por veículo.** Nova saída com o carro na rua responde
  `vehicle_already_out` e devolve quem está com ele.
- **O hodômetro não anda para trás.** Saída menor que a última registrada, retorno menor
  que a saída, ou salto acima de `fleet.max_trip_km` (1500 km por padrão) são **recusados
  com aviso**, não bloqueados: reenviar com `force: true` grava e marca a viagem como
  *km a conferir* (`odometer_alert`), que tem filtro próprio no histórico.
- **Pela guarita, a km vai só com os 3 últimos números** (desde 17/11/2026). Ver
  [Km com 3 dígitos](#km-com-3-dígitos-api-da-portaria). As telas do Lara continuam pedindo
  o número inteiro.
- **O cadastro de [Funcionários](funcionarios.md) autentica o motorista.** Funcionário
  identificado que está desligado, de férias, afastado ou excluído é **recusado** com
  `driver_blocked` e o motivo — por matrícula, CPF, nome ou `employee_id`. O `force` não passa
  por cima. O CPF é comparado só pelos dígitos.
- **Motorista fora da folha entra** — enquanto `FLEET_REQUIRE_EMPLOYEE=false` (padrão). Nome
  digitado que não corresponde a nenhum funcionário é gravado assim mesmo. Com a chave ligada
  (depois da carga do RH), é recusado com `driver_not_employee`.
  A exceção é um **número** (matrícula/CPF) que não encontra ninguém: isso é erro de
  digitação, e é recusado com `driver_not_found`.
- **Homônimo não vincula.** Nome que encontra mais de um funcionário grava o texto sem
  vínculo, em vez de apontar a viagem para a pessoa errada.
- **O horário é o do registro.** `registered_at` pode ser enviado para lançamento atrasado
  (fila offline da portaria), mas nunca no futuro além de `fleet.clock_skew_minutes`.
- **Nada fecha sozinho.** Viagem aberta há mais de `fleet.open_trip_alert_hours` (12h)
  aparece destacada no painel como pendência de baixa — só quem viu o carro voltar sabe o
  km.
- **Veículo com viagem não é excluído**, apenas desativado.
- **Km errada se corrige em SIV › Viagens** (desde 17/11/2026, permissão
  `siv.viagens.corrigir-km`). Ver [Correção de km](#correção-de-km).

## Correção de km

A portaria erra o hodômetro, e a km errada não sai sozinha: um número alto demais vira o
piso das próximas saídas. Em **SIV › Viagens**, quem tem `siv.viagens.corrigir-km` clica
em **Corrigir km** na linha da viagem (`PUT /fleet/trips/{trip}/odometer`,
`fleet.trips.correct`) e informa a km de saída, a de retorno e o motivo.
`FleetService::correctTripOdometer()`:

- Viagem **cancelada** não se corrige (`trip_canceled`). Viagem **em rota** só tem km de
  saída (o retorno enviado é ignorado).
- **Motivo obrigatório** (`reason_required`). **Retorno menor que a saída é recusado**
  (`odometer_below_departure`), sem `force`: quem corrige está conferindo o número.
  Nada mudou → `nothing_changed`.
- O antes e o depois (saída, retorno, km rodados), o motivo e quem corrigiu (`user_id` e
  `user_name`) vão para **`fleet_trip_corrections`**. A tela mostra esse histórico em
  "km corrigida (N)".
- **Km rodados** são recalculados. A **km atual do veículo** volta a ser a da última viagem
  não cancelada (retorno, ou saída se estiver na rua): o espelho só anda para frente nos
  registros, e a correção é o único caminho que o faz descer.
- A viagem **sai de "km a conferir"**, a não ser que ainda não feche com a viagem anterior
  (terminou acima da nova saída) ou com a seguinte (saiu abaixo do novo fim). Nesse caso a
  correção é salva, a viagem continua marcada e a tela mostra um aviso "Atenção" com a
  vizinha a conferir. Não bloqueia, porque numa sequência de erros a vizinha é a próxima
  a corrigir.
- A correção é só pela tela, não pela API da portaria.

## Acesso à cancela da diretoria

Todo veículo **ativo** da frota com placa cadastrada abre a cancela, sem depender da lista
de placas da diretoria. A autorização dele é **ser da empresa**, não uma validade que
alguém precisa lembrar de renovar — por isso a frota é verificada antes da lista, e sem
conferir data.

- `POST /api/parking/check` e `GET /api/parking/check/{placa}` respondem
  `{"valid": true, "name": "<nome do veículo>", "reason": "fleet_vehicle", "expiration_date": null}`.
  O `reason` também vai para o log de acesso (`logAccessAttempt`), separando nos registros
  quem entrou pela frota e quem entrou pela lista da diretoria.
- `GET /api/parking/authorized` — a lista que a câmera baixa para decidir **com a API fora
  do ar** — soma as placas da frota às da diretoria. Como aquele formato tem coluna de
  validade e mudá-lo quebraria o cliente, a frota entra com uma data sintética de
  `fleet.gate_validity_years` (10 anos), recalculada a cada consulta: enquanto a câmera
  atualizar a lista, a data nunca chega.
- Placa presente nas duas origens aparece **uma vez só** na lista, com o registro da
  diretoria.
- **Desativar o veículo tira a liberação** — é o caminho para o carro vendido ou fora de
  uso, e vale nas duas pontas (consulta e lista offline).

A comparação é feita na placa normalizada dos dois lados, então `ABC-1D23` e `abc1d23`
encontram o mesmo veículo.

## Mensagens / erros da API

Toda recusa volta com `ok: false` e um `error` estável — é por ele que o cliente decide o
que fazer, nunca pelo texto da mensagem.

| `error` | HTTP | Significa | O que o cliente faz |
|---|---|---|---|
| `vehicle_required` | 422 | Veículo não informado | Corrige o envio |
| `vehicle_not_found` | 404 | Nome/placa não cadastrados | Mostra a lista devolvida em `vehicles` |
| `vehicle_ambiguous` | 422 | Mais de um veículo corresponde | Pede escolha entre os `vehicles` devolvidos |
| `vehicle_inactive` | 422 | Veículo desativado | Avisa o operador |
| `vehicle_already_out` | 409 | Já há saída em aberto | Mostra a `trip` devolvida e oferece registrar o retorno |
| `driver_required` | 422 | Motorista não informado | Corrige o envio |
| `driver_not_found` | 404 | Matrícula/CPF não encontrados | Pede o nome (`driver_name`) |
| `driver_blocked` | 403 | Funcionário desligado, de férias, afastado ou excluído (`reason` traz o motivo) | Mostra a mensagem; não há "forçar" |
| `driver_not_employee` | 422 | Só com `FLEET_REQUIRE_EMPLOYEE=true`: motorista não está no cadastro | Pede matrícula, CPF ou nome completo |
| `destination_required` | 422 | Destino vazio | Corrige o envio |
| `odometer_required` | 422 | Km ausente | Corrige o envio |
| `odometer_too_many_digits` | 422 | Mais dígitos que `odometer_digits` (ex.: o número inteiro) | Pede só os últimos números |
| `odometer_no_reference` | 422 | Veículo sem km nenhuma no Lara: não há de onde compor | Avisa: cadastrar a km atual em SIV › Veículos |
| `odometer_below_last` | 422 | Km de saída menor que a última | Confirma com `force` |
| `odometer_below_departure` | 422 | Km de retorno menor que o de saída | Confirma com `force` |
| `odometer_jump_too_high` | 422 | Viagem acima do teto de km | Confirma com `force` |
| `no_open_trip` | 409 | Retorno sem saída registrada | Oferece registrar a saída |
| `trip_not_found` / `trip_not_open` | 404 / 409 | Viagem inexistente ou já encerrada | Recarrega a situação da frota |
| `return_before_departure` | 422 | Retorno anterior à saída | Confirma com `force` |
| `future_timestamp` | 422 | `registered_at` no futuro | Corrige o relógio do cliente |

## Integrações

### API (portaria)

Base `/api/fleet`, autenticada pelo header `Authorization: Bearer <token>` (middleware
`api_token`).

| Método | Rota | Para quê |
|---|---|---|
| GET | `/api/fleet/vehicles` | Situação da frota (`?all=1` inclui inativos) |
| GET | `/api/fleet/trips/open` | Viagens aguardando baixa |
| GET | `/api/fleet/trips` | Histórico paginado (`vehicle`, `status`, `driver`, `date_from`, `date_to`, `per_page`) |
| GET | `/api/fleet/drivers?q=` | Autocomplete de motorista (nome, matrícula, CPF). Só funcionários aptos; cada item traz `sector` |
| POST | `/api/fleet/departure` | Registra a saída |
| POST | `/api/fleet/return` | Registra o retorno |
| POST | `/api/fleet/trips/{trip}/cancel` | Cancela uma saída registrada por engano |

### Km com 3 dígitos (API da portaria)

Desde 17/11/2026 o porteiro digita **só os 3 últimos números do hodômetro** — é o que ele
consegue ler e digitar sem errar. O Lara compõe o número inteiro:

- **Referência:** na saída, a última km do veículo (`FleetVehicle::lastOdometer()`); no
  retorno, a km de saída da viagem aberta. A `GET /api/fleet/vehicles` devolve essa
  referência em cada veículo (`odometer_reference`) e o número de dígitos combinado
  (`odometer_digits`).
- **Regra:** o primeiro número, a partir da referência, que termina com os dígitos
  digitados. Referência 45.210 e `"260"` → 45.260; referência 45.980 e `"020"` → 46.020 (o
  milhar virou); `"5"` vale `"005"`.
- **Folga para trás** (`fleet.api_odometer_back_km`, 50 km): a busca começa 50 km antes da
  referência. Saída em 45.210 e retorno digitado `"190"` vira 45.190 e cai no aviso
  `odometer_below_departure` — erro de leitura aparece como erro, e não como uma viagem de
  980 km gravada em silêncio. Consequência: **pela API, uma viagem só vai até 949 km**
  (10³ − 50). Viagem maior se registra pela tela do Lara, com o número inteiro.
- **Recusas próprias:** `odometer_too_many_digits` (mandou o número inteiro — app
  desatualizado) e `odometer_no_reference` (veículo sem km cadastrada).
- **Resposta:** toda saída/retorno aceita, e as recusas de km (`odometer_below_last`,
  `odometer_below_departure`, `odometer_jump_too_high`), trazem `odometer_input`
  (`typed`, `digits`, `reference`, `odometer`) para a guarita mostrar o número completo ao
  porteiro.
- **Saída de emergência:** `FLEET_API_ODOMETER_DIGITS=0` volta a API ao número inteiro (para
  o caso de o app da guarita ainda não ter sido atualizado no deploy).
- As **telas do Lara** (SIV › Frota) seguem com o número inteiro: o serviço só compõe
  quando o `FleetApiController` passa o número de dígitos.

Prompt para o app da guarita:
[docs/prompts/LaraExternos/frota-km-3-digitos-prompt.md](../prompts/LaraExternos/frota-km-3-digitos-prompt.md).

**Saída**

```json
POST /api/fleet/departure
{
  "vehicle": "Celta",
  "driver": "1234",
  "destination": "Banco Itaú - Centro",
  "odometer": "210",
  "obs": "Depósito do dia",
  "operator": "Portaria 1"
}
```

`vehicle` aceita id, placa ou nome (sem acento e em qualquer caixa: `caminhao` acha
`Caminhão`). `driver` aceita nome, matrícula ou CPF. Campos opcionais: `obs`, `operator`,
`registered_at`, `force`, e os explícitos `vehicle_id`, `employee_id`, `driver_name`.

Resposta `201`:

```json
{
  "ok": true,
  "trip": {
    "id": 12,
    "status": "open",
    "vehicle": { "id": 1, "name": "Celta", "plate": null },
    "driver": { "name": "João da Silva", "employee_id": 7, "employee_code": "1234", "document": "12345678909" },
    "destination": "Banco Itaú - Centro",
    "departure": { "odometer": 45210, "at": "2026-08-28T14:03:11-03:00", "at_human": "28/08/2026 14:03", "operator": "Portaria 1", "obs": "Depósito do dia" },
    "return": null,
    "distance_km": null,
    "duration_minutes": 0,
    "odometer_alert": false
  },
  "vehicle": { "id": 1, "name": "Celta", "status": "out", "current_odometer": 45210, "odometer_reference": 45210, "open_trip": { "...": "..." } },
  "odometer_input": { "typed": "210", "digits": 3, "reference": 45210, "odometer": 45210 }
}
```

**Retorno**

```json
POST /api/fleet/return
{ "vehicle": "Celta", "odometer": "260", "operator": "Portaria 2" }
```

O `trip_id` é opcional: informado só o veículo, o sistema fecha a viagem que está
esperando baixa. A resposta `200` traz a viagem com `status: "closed"`, `distance_km` e
`duration_minutes`, mais o veículo já de volta em `available` e o `odometer_input` (aqui,
45.260: referência 45.210 + `"260"`).

### Telas

No menu, dentro de **SIV** — a frota é assunto de portaria e veículo, como a consulta de
placas ao lado dela. Aparecem só para quem tem `manage fleet`.

| Item do menu | Rota | Para quê |
|---|---|---|
| SIV → Frota | `/fleet` | Situação de cada veículo e registro de saída/retorno (caminho de contingência quando o sistema da guarita está fora) |
| SIV → Viagens | `/fleet/trips` | Histórico com filtros de veículo, situação, motorista, período e *km a conferir*; **Corrigir km** com `siv.viagens.corrigir-km` |
| SIV → Veículos | `/fleet/vehicles` | Cadastro |

O registro pelas telas passa pelo **mesmo serviço** da API: duas validações diferentes
acabariam produzindo dois hodômetros diferentes para o mesmo carro.

## Referência técnica

| Camada | Arquivo |
|---|---|
| Serviço | `app/Services/FleetService.php` |
| Cancela | `app/Services/ParkingAuthorizationService.php` (`checkPlate`, `findFleetVehicleByPlate`, `getValidAuthorizations`) |
| Controllers | `app/Http/Controllers/Fleet/FleetApiController.php`, `FleetController.php` |
| Models | `app/Models/Fleet/FleetVehicle.php`, `FleetTrip.php`, `FleetTripCorrection.php` |
| Migrations | `database/migrations/2026_08_28_100000_create_fleet_tables.php`, `2026_11_17_100000_create_fleet_trip_corrections_table.php` |
| Config | `config/fleet.php` (`max_trip_km`, `api_odometer_digits`, `api_odometer_back_km`, `clock_skew_minutes`, `open_trip_alert_hours`, `gate_validity_years`) |
| Seeder | `database/seeders/FleetVehicleSeeder.php` |
| Testes | `tests/Feature/FleetMileageTest.php`, `tests/Feature/FleetApiEndpointsTest.php`, `tests/Feature/ParkingFleetAccessTest.php` |
