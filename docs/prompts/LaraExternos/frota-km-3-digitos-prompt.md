# Prompt para o agent do Lara Externos: quilometragem da frota com 3 dígitos

**Sistema externo:** Lara Externos (app da guarita / portaria, tela de saída e retorno dos veículos da frota)

## Contexto

O porteiro registra a saída e o retorno dos carros da empresa pela API de frota do Lara (`/api/fleet/*`, `Authorization: Bearer <token>`). Muitos porteiros têm pouca familiaridade com tecnologia e erram ao digitar o hodômetro inteiro (45210, 452100, 4521…).

Desde 17/11/2026 o Lara passou a receber **só os 3 últimos números do hodômetro** e monta o número inteiro sozinho. O app precisa mudar para pedir só esses 3 números e mostrar ao porteiro o número completo que foi gravado.

## O que mudou na API do Lara

### 1. `odometer` agora são os 3 últimos números

`POST /api/fleet/departure` e `POST /api/fleet/return`: o campo `odometer` leva **de 1 a 3 dígitos**. Exemplos: painel em 45.260, envia `"260"`; painel em 13.005, envia `"005"` (ou `"5"`, que vale o mesmo).

Envie como **string** para não perder os zeros à esquerda. Número também funciona.

O Lara compõe o número inteiro assim:

- **Saída:** a partir da última quilometragem registrada do carro.
- **Retorno:** a partir da quilometragem de saída da viagem aberta.
- O resultado é o primeiro número, a partir dessa referência, que termina com os 3 dígitos digitados. Se o milhar virar no caminho, o Lara percebe sozinho: saída em 45.980 e retorno `"020"` grava 46.020 (40 km).
- Se o número digitado estiver um pouco abaixo da referência (até 50 km), o Lara entende como leitura errada e devolve o aviso de "km menor". Ele não grava uma viagem de quase 1.000 km. Por isso, **uma viagem só vai até 949 km** pela guarita. Viagem maior que isso se registra pela tela do Lara.

### 2. Recusas novas

| `error` | HTTP | Quando | O que o app faz |
|---|---|---|---|
| `odometer_too_many_digits` | 422 | Veio mais de 3 dígitos (ex.: o número inteiro) | Não deve acontecer se o campo estiver travado. Se acontecer, mostre "Digite só os 3 últimos números do painel". `odometer_digits` vem na resposta. |
| `odometer_no_reference` | 422 | O carro não tem nenhuma quilometragem cadastrada no Lara | Mostre a `message` e oriente a chamar a administração. Quem resolve é a tela SIV › Veículos do Lara. O porteiro não tem como contornar. |

As recusas que já existiam continuam iguais, com os mesmos códigos e status: `odometer_required`, `odometer_below_last`, `odometer_below_departure`, `odometer_jump_too_high`. Todas continuam aceitando o reenvio com `force: true`.

### 3. Campos novos nas respostas

- `GET /api/fleet/vehicles`:
  - `odometer_digits` (raiz da resposta): quantos dígitos o Lara espera. Hoje é `3`. **`0` significa "mande o número inteiro"**: é a chave de emergência do Lara, e o app deve respeitá-la (campo livre, como antes).
  - `vehicles[].odometer_reference`: a quilometragem de onde o Lara vai partir. Para o carro no clube (`status: available`) é a última km. Para o carro em rota (`status: out`) é a km de saída. Pode vir `null` (carro sem km cadastrada; ver `odometer_no_reference`).
- `POST /api/fleet/departure` e `/return`: nas respostas de sucesso e nas recusas de km (`odometer_below_last`, `odometer_below_departure`, `odometer_jump_too_high`) vem
  ```json
  "odometer_input": { "typed": "260", "digits": 3, "reference": 45210, "odometer": 45260 }
  ```
  `odometer` é o número inteiro que o Lara gravou (ou gravaria, nas recusas).

Exemplo de saída:

```json
POST /api/fleet/departure
{ "vehicle": "Celta", "driver": "1234", "destination": "Banco", "odometer": "210", "operator": "Portaria 1" }
```

```json
{
  "ok": true,
  "trip": { "id": 12, "status": "open", "departure": { "odometer": 45210, "...": "..." }, "...": "..." },
  "vehicle": { "id": 1, "name": "Celta", "status": "out", "current_odometer": 45210, "odometer_reference": 45210, "...": "..." },
  "odometer_input": { "typed": "210", "digits": 3, "reference": 45210, "odometer": 45210 }
}
```

## O que fazer no app

1. **Campo de km com 3 posições.** Teclado numérico, no máximo `odometer_digits` dígitos (leia da `GET /api/fleet/vehicles`, não fixe 3 no código). Deixe os números grandes e o campo sozinho na tela. Com `odometer_digits = 0`, volte ao campo livre de antes.
2. **Mostre o começo do número já preenchido.** Use o `odometer_reference` do veículo escolhido para exibir os primeiros números fixos e as 3 casas para digitar: `45.[_ _ _]`. Diga ao porteiro: "Digite os 3 últimos números do painel". O prefixo é só uma ajuda visual. Quem decide o número inteiro (inclusive a virada do milhar) é o Lara. **Não componha o número no app** e não envie o número inteiro.
3. **Envie como string**, mantendo os zeros (`"005"`). Não converta para número antes de enviar.
4. **Confirme com o número inteiro.** No sucesso, mostre em destaque `odometer_input.odometer` formatado (ex.: "Saída registrada: 45.210 km"). No retorno, mostre também `trip.distance_km` ("Retorno registrado: 45.260 km, 50 km rodados").
5. **Avisos de km.** Em `odometer_below_last`, `odometer_below_departure` e `odometer_jump_too_high`, mostre o número que o Lara entendeu (`odometer_input.odometer`) e a referência. Use frases simples, por exemplo: "O painel mostra 45.190? A saída foi em 45.210." Ofereça dois botões grandes: **Corrigir** (volta ao campo) e **Está certo** (reenvia com `force: true`).
6. **Fila offline.** Se o app guarda registros para enviar depois (`registered_at`), guarde os 3 dígitos digitados, e não um número montado no aparelho. O Lara compõe na hora em que recebe.
7. **Mensagens.** Não dependa do texto de `message` para decidir o fluxo. Use sempre o `error`. A `message` pode ser exibida como está.

## Como testar

- Carro com km 45.210 no clube: saída `"210"` → gravada 45.210.
- Retorno `"260"` → 45.260, `distance_km` 50.
- Carro em 45.980: saída `"980"`, retorno `"020"` → 46.020, `distance_km` 40.
- Saída `"209"` com o carro em 45.210 → `odometer_below_last` com `odometer_input.odometer` 45.209; "Está certo" reenvia com `force: true`.
- Enviar `45210` → `odometer_too_many_digits` (o campo travado não deve deixar isso acontecer).
- Carro sem km cadastrada → `odometer_no_reference`.

Use o ambiente de homologação do Lara. Não registre saída nem retorno de teste em produção.
