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
| Km de saída / retorno | Saída / Retorno | Hodômetro lido no painel. |
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
- **Motorista fora da folha entra.** Nome digitado que não corresponde a nenhum
  funcionário é gravado assim mesmo — nem todo mundo que leva o carro está no cadastro.
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
| `destination_required` | 422 | Destino vazio | Corrige o envio |
| `odometer_required` | 422 | Km ausente | Corrige o envio |
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
| GET | `/api/fleet/drivers?q=` | Autocomplete de motorista (nome, matrícula, CPF) |
| POST | `/api/fleet/departure` | Registra a saída |
| POST | `/api/fleet/return` | Registra o retorno |
| POST | `/api/fleet/trips/{trip}/cancel` | Cancela uma saída registrada por engano |

**Saída**

```json
POST /api/fleet/departure
{
  "vehicle": "Celta",
  "driver": "1234",
  "destination": "Banco Itaú - Centro",
  "odometer": 45210,
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
  "vehicle": { "id": 1, "name": "Celta", "status": "out", "current_odometer": 45210, "open_trip": { "...": "..." } }
}
```

**Retorno**

```json
POST /api/fleet/return
{ "vehicle": "Celta", "odometer": 45260, "operator": "Portaria 2" }
```

O `trip_id` é opcional: informado só o veículo, o sistema fecha a viagem que está
esperando baixa. A resposta `200` traz a viagem com `status: "closed"`, `distance_km` e
`duration_minutes`, mais o veículo já de volta em `available`.

### Telas

No menu, dentro de **SIV** — a frota é assunto de portaria e veículo, como a consulta de
placas ao lado dela. Aparecem só para quem tem `manage fleet`.

| Item do menu | Rota | Para quê |
|---|---|---|
| SIV → Frota | `/fleet` | Situação de cada veículo e registro de saída/retorno (caminho de contingência quando o sistema da guarita está fora) |
| SIV → Viagens | `/fleet/trips` | Histórico com filtros de veículo, situação, motorista, período e *km a conferir* |
| SIV → Veículos | `/fleet/vehicles` | Cadastro |

O registro pelas telas passa pelo **mesmo serviço** da API: duas validações diferentes
acabariam produzindo dois hodômetros diferentes para o mesmo carro.

## Referência técnica

| Camada | Arquivo |
|---|---|
| Serviço | `app/Services/FleetService.php` |
| Controllers | `app/Http/Controllers/Fleet/FleetApiController.php`, `FleetController.php` |
| Models | `app/Models/Fleet/FleetVehicle.php`, `FleetTrip.php` |
| Migrations | `database/migrations/2026_08_28_100000_create_fleet_tables.php` |
| Config | `config/fleet.php` (`max_trip_km`, `clock_skew_minutes`, `open_trip_alert_hours`) |
| Seeder | `database/seeders/FleetVehicleSeeder.php` |
| Testes | `tests/Feature/FleetMileageTest.php`, `tests/Feature/FleetApiEndpointsTest.php` |
