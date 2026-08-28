# Prompt para o agent do sistema da portaria

> Cole o conteúdo abaixo (da linha `---` em diante) em um Claude Code aberto no repositório
> do **monitor de acesso da portaria**. Ele descreve a API de frota do Lara e o que
> construir do lado da guarita.

---

Você vai implementar, neste sistema da portaria, o **registro de quilometragem da frota da
empresa**. O back-end já está pronto no Lara (a API que este sistema já consome para o
controle de acesso de terceirizados, freelancers e Uber). Sua tarefa é a tela e a
integração — **nenhuma regra de quilometragem é decidida aqui**.

## Contexto de negócio

Hoje, quando alguém sai com um veículo da empresa, a portaria anota no papel: quem levou,
qual veículo, para onde e a quilometragem do painel. Quando o carro volta, anota a
quilometragem de retorno. É isso que passa a ser registrado pelo sistema.

Saída e retorno são **as duas metades de uma viagem só**. A saída abre a viagem; o retorno
a fecha e o Lara calcula os km rodados. O horário de cada metade é carimbado pelo Lara no
momento em que a chamada chega — **não peça hora ao operador e não envie hora local**,
exceto no caso de fila offline descrito adiante.

Frota atual: Celta, Caminhão e Polo. Não fixe esses nomes em lugar nenhum: a lista vem da
API e muda no cadastro do Lara.

## Autenticação

Mesma do resto da integração: header `Authorization: Bearer <token>`, o token de API que
este sistema já usa nos endpoints do Lara. Reaproveite o cliente HTTP e a configuração de
token que já existem aqui — não crie um segundo caminho de configuração.

## Endpoints

Base: `<host do Lara>/api/fleet`

| Método | Rota | Para quê |
|---|---|---|
| GET | `/vehicles` | Situação da frota: cada veículo com `status` (`available` \| `out`), `current_odometer` e, se estiver na rua, `open_trip` |
| GET | `/trips/open` | Só as viagens aguardando baixa |
| GET | `/trips` | Histórico paginado (`vehicle`, `status`, `driver`, `date_from`, `date_to`, `per_page`) |
| GET | `/drivers?q=` | Autocomplete de motorista por nome, matrícula ou CPF (mínimo 2 caracteres) |
| POST | `/departure` | Registra a saída |
| POST | `/return` | Registra o retorno |
| POST | `/trips/{id}/cancel` | Cancela uma saída registrada por engano (`reason` opcional) |

### Saída

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

- `vehicle`: id, placa ou nome. O nome é comparado sem acento e sem caixa (`caminhao`
  encontra `Caminhão`). Se você tiver o id vindo de `GET /vehicles`, mande `vehicle_id` —
  é o caminho sem ambiguidade e o preferido para a tela.
- `driver`: nome, matrícula ou CPF. Quando bate com um funcionário, o Lara vincula
  sozinho. Se você usou o autocomplete, mande `employee_id`.
- `operator`: quem está na guarita. Use o usuário logado neste sistema.
- Opcionais: `obs`, `registered_at`, `force`, `driver_name`.

Resposta `201` com `{ "ok": true, "trip": {...}, "vehicle": {...} }`.

### Retorno

```json
POST /api/fleet/return
{ "vehicle": "Celta", "odometer": 45260, "operator": "Portaria 2" }
```

**Não guarde o id da viagem entre a saída e o retorno.** Informado só o veículo, o Lara
fecha a viagem que está esperando baixa — e é assim que tem que ser: quem registra o
retorno pode ser outro porteiro, em outro turno, em outra máquina. `trip_id` existe, mas
use apenas quando o operador escolheu uma viagem específica numa lista que você acabou de
carregar.

Resposta `200` com a viagem em `status: "closed"`, `distance_km` e `duration_minutes`.

## O que construir

1. **Tela "Frota"**, no mesmo padrão visual do monitor de acesso atual.
2. **Um cartão por veículo**, alimentado por `GET /vehicles`:
   - `available` → formulário de **saída**: motorista, destino, km, observação.
   - `out` → mostra motorista, destino, hora da saída e km de saída (tudo vem em
     `open_trip`), e oferece o formulário de **retorno**: km e observação.
3. **Campo de km sempre numérico**, teclado numérico em tela sensível ao toque. Na saída,
   pré-preencha com `current_odometer` do veículo — o porteiro corrige com o que está no
   painel do carro.
4. **Autocomplete do motorista** via `GET /drivers?q=`, com no mínimo 2 caracteres e debounce.
   Ao escolher alguém, envie `employee_id`. Se ninguém for escolhido, envie o texto digitado
   em `driver` — nome de fora da folha é aceito.
5. **Cancelar saída**, disponível no cartão de um veículo em rota, com confirmação.
6. Após qualquer registro, **recarregue `GET /vehicles`** e mostre a confirmação com o que
   voltou na resposta (km rodados no retorno, por exemplo). Não presuma o novo estado.

## Tratamento de erro — a parte que importa

Toda recusa vem com `ok: false` e um campo **`error`** estável. Trate pelo `error`, nunca
pelo texto da mensagem (a mensagem é para exibir ao operador, e pode mudar).

| `error` | HTTP | O que a tela faz |
|---|---|---|
| `vehicle_already_out` | 409 | Mostra a `trip` que veio na resposta e oferece **registrar o retorno** daquele veículo |
| `no_open_trip` | 409 | Avisa que não há saída em aberto e oferece **registrar a saída** |
| `odometer_below_last` | 422 | "Km menor que o último registrado (X)." Botão **Confirmar assim mesmo** → repete o POST com `force: true` |
| `odometer_below_departure` | 422 | Idem, comparando com o km de saída da viagem |
| `odometer_jump_too_high` | 422 | "Essa viagem fecharia X km." Mesma confirmação com `force` |
| `driver_not_found` | 404 | O número digitado não é de ninguém: peça o **nome** e reenvie em `driver_name` |
| `vehicle_not_found` | 404 | Mostre a lista devolvida em `vehicles` |
| `vehicle_ambiguous` | 422 | Peça para escolher entre os `vehicles` devolvidos |
| `vehicle_inactive` | 422 | Avise que o veículo está desativado no cadastro |
| `future_timestamp` | 422 | Relógio desta máquina adiantado — logue e avise o operador |
| 401 | 401 | Token inválido: erro de configuração, avise sem oferecer retry |

**`force` é sempre um segundo envio explícito, disparado por um clique do operador.** Nunca
reenvie com `force: true` automaticamente e nunca mande `force` no primeiro POST: o aviso
existe justamente para o porteiro conferir o número no painel do carro. A viagem forçada
fica marcada como "km a conferir" para a administração revisar.

## O que NÃO fazer

- **Não valide quilometragem localmente.** Nada de comparar com o último km, calcular km
  rodados ou decidir se o número é plausível. Quem faz isso é o Lara — regra duplicada vira
  regra divergente.
- **Não crie tabela local de veículos, motoristas ou viagens.** A lista vem de
  `GET /vehicles` a cada abertura de tela. Se quiser cache, que seja de sessão e sempre
  revalidado antes de registrar.
- **Não carimbe horário.** Só use `registered_at` no caso da fila offline abaixo.
- **Não invente endpoints** nem campos: o contrato é o desta lista.

## Fila offline (só se este sistema já tiver uma)

Se a guarita já opera com fila para quando a rede cai, e só nesse caso: guarde o instante
do registro e envie em `registered_at` (ISO 8601) quando a chamada finalmente sair. O Lara
aceita horário passado e recusa futuro. Sem fila, não envie o campo — o carimbo do servidor
é mais confiável que o relógio da máquina da portaria.

## Testes

Cubra, no padrão de testes que este repositório já usa:

- ciclo completo: saída → veículo aparece `out` → retorno → veículo volta a `available`;
- `vehicle_already_out` levando a tela para o fluxo de retorno;
- `odometer_below_departure` exibindo a confirmação e o segundo envio indo com `force: true`;
- `driver_not_found` pedindo o nome e reenviando com `driver_name`;
- falha de rede e 401 sem quebrar a tela.

## Referência

A documentação completa do módulo está em `docs/funcionalidades/frota.md`, no repositório
do Lara: regras de negócio, tabela de erros e exemplos de payload. Em qualquer divergência
entre este prompt e aquele documento, o documento vence — peça para conferir antes de
adaptar o cliente.
