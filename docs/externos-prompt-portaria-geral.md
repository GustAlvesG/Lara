# Prompt geral para o app da guarita — Lara Externos (28/10/2026)

> Cole o conteúdo abaixo (da linha `---` em diante) em um Claude Code aberto no repositório do
> **monitor de acesso da portaria** (o app da guarita, "Lara Externos", em Python). Ele junta
> num só texto tudo o que mudou na API do Lara para a guarita e estava espalhado em prompts
> separados:
>
> | Prompt de origem | Data | Assunto |
> |---|---|---|
> | [frota-prompt-portaria.md](frota-prompt-portaria.md) | 28/08/2026 | Frota: saída e retorno de veículo |
> | [company-access-control.md](company-access-control.md) (sem prompt próprio) | 11/09 e 22/09/2026 | Liberação pontual; fila do carro de aplicativo pelo id |
> | [funcionarios-prompt-portaria.md](funcionarios-prompt-portaria.md) | 20/10/2026 | Funcionário bloqueado no Uber e na frota |
> | [ingressos-prompt-portaria.md](ingressos-prompt-portaria.md) | 27–28/10/2026 | Ingresso de evento, mesa e cortesia sem CPF |
>
> Os prompts de origem continuam valendo como referência detalhada. Daqui para a frente, mudou a
> API da portaria: atualize **este** arquivo (e o de origem do módulo) e gere o prompt da mudança.

---

# Atualização geral: integração do app da guarita com o Lara

O Lara acumulou várias mudanças na API que este app consome, e nem todas foram implementadas
aqui. Sua tarefa é **deixar o app em dia com o contrato abaixo**. Antes de escrever código:

1. **Levante o que já existe.** Para cada seção (A a D), confira no código se a parte já está
   feita, feita pela metade ou ausente. Monte uma lista curta e implemente só o que falta ou
   está divergente. Não reescreva o que já funciona conforme o contrato.
2. **Reaproveite** o cliente HTTP, a configuração do host do Lara, o padrão de telas e o padrão
   de testes que já existem neste repositório.

## Regras gerais (valem para tudo)

- **Nenhuma regra de negócio é decidida aqui.** O Lara decide se a pessoa entra, se o km é
  plausível, se o funcionário está bloqueado — e devolve o motivo pronto para mostrar. Regra
  duplicada na guarita vira regra divergente.
- **Trate pelo código, mostre o texto.** Decida o comportamento da tela pelos campos estáveis
  (`reason`, `error`, `type`, `member_validation`), e mostre ao porteiro a mensagem que veio
  (`message`, `ticket.message`, `member_validation_label`…). A mensagem pode mudar; o código não.
- **Valor desconhecido não quebra a tela.** Qualquer `reason`/`type`/`member_validation` novo
  cai num estado neutro (negado, se `allowed: false`) mostrando o texto que veio.
- **Depois de registrar, mostre o que o registro devolveu**, não a linha da consulta: entre a
  consulta e o registro outra guarita pode ter usado a liberação ou o ingresso.
- **Não guarde estado local** de veículos, pedidos, ingressos ou liberações. Tudo vem da API a
  cada consulta.
- **Autenticação:** as rotas `/api/company-access/*` **não exigem token** (o grupo é aberto de
  propósito — não comece a depender de token nelas). As rotas `/api/fleet/*` exigem
  `Authorization: Bearer <token>` (o token de API do Lara, `services.api.token` do lado de lá).
  Uma configuração só para o host e o token.

---

## A. Consulta e registro de acesso (`/api/company-access/*`)

### A.1 O alvo (`target`)

`POST /api/company-access/validate-access` (consulta, não gasta nada) e
`POST /api/company-access/register-access` (registra) recebem `{ "target": "..." }`. O Lara
decide o tipo pelo formato:

| `target` | Tipo |
|---|---|
| CPF (com ou sem máscara) | soma de **terceirizado**, **freelancer**, **liberação pontual** e **ingresso** daquele CPF — várias linhas em `workers` |
| `ING-XXXXXXXX` (8 letras/números) | **ingresso** daquele código — é o conteúdo do QR Code |
| `PLACA.Nome.Obs` | motorista de aplicativo avulso (cadastra e registra na hora) |
| placa de pedido de Uber | pedido de carro de aplicativo aguardando acesso |
| texto livre | nome de empresa → todos os prestadores |

**O campo de busca precisa aceitar o código do ingresso**: `ING-7KQ2MZ9H`, e também minúsculo,
com espaço ou sem hífen (`ing 7kq2mz9h`, `ING7KQ2MZ9H`) — o Lara normaliza. Se a guarita tiver
leitor de QR que "digita" no campo, ele passa a funcionar sem mais nada; se o app tiver captura
própria de QR, ela deve preencher o campo com o código lido e disparar a consulta.

### A.2 Tipos de linha em `workers`

Cada linha tem `id`, `type`, `name`, `allowed`, `reason`, `image` (pode ser `null`: mostre a
inicial), `company`, `company_id`. Etiqueta por `type`:

| `type` | Etiqueta | Registro pelo botão da linha |
|---|---|---|
| `worker` (terceirizado) | nome da empresa | `POST register-worker-access` (como já existe) |
| `freelancer` | "Freelancer" | `POST register-freelancer-access` (como já existe) |
| `one_off` | "Liberação Pontual" (âmbar) | `POST register-one-off-access` com `{ "one_off_access_id": <id> }` |
| `ticket` | "Ingresso" (verde) | `POST register-ticket-access` com `{ "ticket_id": <id> }` |

**Os `id` são de tabelas diferentes**: nunca mande o id de uma linha `one_off` ou `ticket` para
`register-worker-access`. Quando o CPF traz mais de uma linha, cada linha tem o seu botão
"Registrar", com a rota do seu tipo. Rotas de registro respondem `404` com
`{ found: false, reason: ... }` quando o id não existe.

### A.3 Liberação pontual (`type: "one_off"`)

Autorização extraordinária criada no Lara para um CPF: **uma entrada, só no dia**. Consultar não
gasta; o primeiro registro gasta. A linha traz `company: "Liberação Pontual"`, `reason`
`one_off_access_granted` (permitido: "Uma entrada, válida só hoje.") ou outro
`one_off_access_*` (negado; já usada: "Já utilizada às HH:MM.", com o `used_at`), e o bloco
`one_off: { reason, authorized_by, created_at, used_at }` — mostre o motivo (`one_off.reason`)
e quem autorizou. Se o registro voltar negado, foi gasta por outro registro no meio-tempo —
mostre a linha devolvida. Id inexistente no registro → `404` `one_off_access_not_found`.

### A.4 Ingresso de evento (`type: "ticket"`)

O Lara vende ingressos **nominais** de eventos (bailes, shows). Um ingresso vale **uma entrada**;
registrar faz o **check-in**. A entrada abre algumas horas antes do início (hoje 3 h) e fecha no
fim do evento. Pendente, cancelado ou expirado não entra.

Pelo código, quando encontrado: `{ found: true, type: "ticket", company: "Ingresso", company_id: null, workers: [<linha>] }`.
Código inexistente: `{ found: false, reason: "ticket_not_found", workers: [] }` → "Ingresso não
encontrado para este código." Pelo CPF, a linha de ingresso vem junto das outras, depois delas.

```json
{
  "id": 412, "type": "ticket", "name": "Carla Visitante", "allowed": true, "image": null,
  "company_id": null, "company": "Ingresso", "reason": "ticket_access_granted",
  "ticket": {
    "code": "ING-7KQ2MZ9H", "event": "Baile de Verão", "type": "Pista", "seat": null,
    "message": "Pode entrar.", "window": "13/02 19:00 a 14/02 04:00", "used_at": null
  }
}
```

| `reason` | `allowed` | Mostrar |
|---|---|---|
| `ticket_access_granted` | true | "Ingresso válido" + `ticket.message` |
| `ticket_already_used` | false | "Ingresso já utilizado" + `ticket.message` (traz a hora em que entrou) |
| `ticket_not_valid` | false | "Ingresso não está válido" + `ticket.message` |
| `ticket_outside_window` | false | "Fora do horário de entrada" + `ticket.message` (traz a janela) |
| `ticket_seat_only` | false | "Ingresso de mesa — não é entrada" + `ticket.message` |

Na linha: `ticket.message`; abaixo, `ticket.event · ticket.type`; depois `ticket.code` em fonte
monoespaçada e `ticket.window`.

Casos que a tela precisa suportar (o formato é o mesmo, só muda o conteúdo):

- **Evento de vários dias.** Diária: entra uma vez, só no dia dela. Passaporte: entra uma vez
  **em cada dia**, com o mesmo QR — "já utilizado" quer dizer "já entrou hoje". `ticket.type`
  vem com o dia ou "Passaporte" (ex.: `Pista · Sáb 13/02`) e `ticket.window` pode listar vários
  dias separados por `; `. Não quebre a linha nem trunque esses textos.
- **Ingresso de mesa (lugar marcado).** Não é entrada: cada pessoa entra com o ingresso de
  entrada dela. Pelo CPF ele não aparece; pelo código vem negado com `ticket_seat_only`, e
  registrar **não** faz check-in (vai ao histórico negado). `ticket.seat` traz o lugar (`"V2"`)
  e `ticket.type` já vem com ele (`"Mesa Vip · V2"`). Pode usar a etiqueta "Ingresso de mesa".
- **Cortesia sem CPF.** Só é achada pelo **código**; `name` costuma ser "Cortesia" (ou o nome do
  patrocinador). Funciona como qualquer ingresso de entrada. Se a tela orienta "confira o
  documento", nesse caso troque por "Cortesia: entra com o ingresso" — não há documento a
  conferir.

`register-access` com uma linha de ingresso faz o check-in e devolve a linha atualizada; se ela
voltar `ticket_already_used`, outra guarita usou o ingresso no meio-tempo.

### A.5 Histórico

Se este app mostra histórico, os acessos de liberação pontual e de ingresso entram no mesmo
histórico do Lara com os `reason` acima: use as mesmas etiquetas e textos.

---

## B. Carro de aplicativo pedido pelo WhatsApp (`/api/company-access/*`)

### B.1 Fila "Aguardando acesso do motorista" — liberar pelo id

A placa é o campo que mais chega errado do WhatsApp, e com a placa errada nenhuma consulta acha
o pedido. Por isso a fila trabalha **pelo id do pedido**:

- `GET /api/company-access/uber-waiting` →
  `{ generated_at, counts: { waiting, expired, filling }, requests: [...], filling: [...] }`.
  `requests` traz os pedidos liberáveis, válidos e vencidos juntos (campo `expired`), cada um
  com `id`, `expires_at`, `expires_in_seconds`, `requester_name`, `matricula`, `contact_phone`,
  `contact_name_whatsapp`, `club_location`, `vehicle_plate`, `screenshot_url` (print da corrida),
  `member_validation*`, `employee_id`, `requested_at`. `filling` são conversas ainda em
  andamento no WhatsApp (só para exibir, não liberáveis).
- `POST /api/company-access/register-uber-request-access` com `{ "uber_access_request_id": <id> }`
  libera o pedido escolhido. **Vencido também libera** (o Lara grava a diferença no histórico);
  não bloqueie na tela, no máximo destaque o vencido. Pedido que já não está aguardando →
  `409` com `reason: "uber_request_not_waiting"` (ou `uber_request_not_found`): avise e recarregue.
- `POST /api/company-access/uber-request-plate` com `{ "uber_access_request_id": <id>, "plate": "ABC1D23" }`
  corrige a placa de um pedido ainda aguardando. Resposta `{ found: true, plate, previous_plate }`;
  placa inválida → `422` com `reason: "invalid_plate"`.

Na tela: lista com o print da corrida, contagem regressiva por `expires_in_seconds`, botão
"Liberar" por linha e ação "Corrigir placa". Recarregue a lista periodicamente e após cada ação.
A liberação manda sozinha o aviso de chegada ao sócio pelo WhatsApp — nada a fazer aqui.

### B.2 Conferência de quem pediu (`member_validation`)

| `member_validation` | Destaque | Observação |
|---|---|---|
| `validado` | verde | |
| `nao_encontrado` | amarelo | |
| `indisponivel` | neutro | conferência fora do ar |
| `funcionario_bloqueado` | **vermelho**, mais forte que o amarelo | **novo** (20/10/2026) |

Mostre `member_validation_label` e, abaixo, **`member_validation_detail`** (novo: o motivo, já
formatado — "Desligado em 10/10/2026", "Férias até 25/10/2026", "Afastamento (sem data de
retorno)"; ou `null`). `employee_id` (novo) é só referência/log. **Não bloqueie a liberação** por
causa da conferência: é informação para o porteiro, que decide — só faça o bloqueado ser
impossível de passar despercebido. Valor desconhecido → estado neutro com o label.

Vale em todo lugar onde o pedido aparece: na fila (B.1) e na resposta da consulta pela placa.

---

## C. Frota da empresa (`/api/fleet/*`, com token Bearer)

Se a tela de frota ainda não existe, construa-a inteira; se existe, confira contra este contrato
e aplique as mudanças de 20/10 (C.4).

### C.1 Negócio

Saída e retorno são as duas metades de **uma viagem**: a saída abre (quem, qual veículo, destino,
km do painel), o retorno fecha e o Lara calcula os km rodados. O **horário é carimbado pelo
Lara**: não peça hora ao operador nem envie hora local (exceto fila offline, C.6). A lista de
veículos vem da API — não fixe nomes (hoje: Celta, Caminhão, Polo).

### C.2 Endpoints

| Método | Rota | Para quê |
|---|---|---|
| GET | `/api/fleet/vehicles` | Cada veículo com `status` (`available` \| `out`), `current_odometer` e, se na rua, `open_trip` |
| GET | `/api/fleet/trips/open` | Viagens aguardando baixa |
| GET | `/api/fleet/trips` | Histórico paginado (`vehicle`, `status`, `driver`, `date_from`, `date_to`, `per_page`) |
| GET | `/api/fleet/drivers?q=` | Autocomplete de motorista (nome, matrícula ou CPF com/sem máscara; mín. 2 caracteres) |
| POST | `/api/fleet/departure` | Saída: `vehicle_id` (ou `vehicle` por nome/placa), `employee_id` (ou `driver` texto), `destination`, `odometer`, `obs`, `operator`; opcionais `registered_at`, `force`, `driver_name` → `201 { ok, trip, vehicle }` |
| POST | `/api/fleet/return` | Retorno: `vehicle`/`vehicle_id`, `odometer`, `obs`, `operator` → `200`, viagem `closed` com `distance_km` e `duration_minutes` |
| POST | `/api/fleet/trips/{id}/cancel` | Cancela saída registrada por engano (`reason` opcional) |

`operator` = usuário logado na guarita. **Não guarde o id da viagem entre saída e retorno**:
informado o veículo, o Lara fecha a viagem aberta (quem dá o retorno pode ser outro porteiro,
outro turno, outra máquina). `trip_id` só quando o operador escolheu uma viagem numa lista
recém-carregada.

### C.3 Tela

1. Tela "Frota" no padrão visual do monitor, **um cartão por veículo** a partir de `GET /vehicles`.
2. `available` → formulário de **saída** (motorista, destino, km, observação), km pré-preenchido
   com `current_odometer`. `out` → mostra `open_trip` (motorista, destino, hora e km de saída) e
   o formulário de **retorno** (km, observação), mais **Cancelar saída** com confirmação.
3. Km sempre numérico (teclado numérico em tela de toque).
4. Autocomplete de motorista com debounce; escolhido alguém, envie `employee_id`, senão o texto
   em `driver`. Mostre o **`sector`** de cada item ao lado do nome (no lugar de `department`, que
   continua vindo mas é texto cru e pode estar vazio). O autocomplete só lista funcionários
   **aptos** — bloqueado não aparece, é esperado.
5. Após qualquer registro, recarregue `GET /vehicles` e confirme com o que voltou (km rodados no
   retorno, por exemplo).

### C.4 Erros — trate pelo `error`

Toda recusa vem com `ok: false`, `error` estável e `message` pronta para exibir.

| `error` | HTTP | O que a tela faz |
|---|---|---|
| `vehicle_already_out` | 409 | Mostra a `trip` devolvida e oferece registrar o **retorno** |
| `no_open_trip` | 409 | Avisa e oferece registrar a **saída** |
| `odometer_below_last` | 422 | Mostra o aviso e o botão **Confirmar assim mesmo** → repete com `force: true` |
| `odometer_below_departure` | 422 | Idem |
| `odometer_jump_too_high` | 422 | Idem |
| `driver_not_found` | 404 | Pede o **nome** e reenvia em `driver_name` |
| `driver_blocked` | 403 | **Novo (20/10).** Motorista identificado e bloqueado; corpo traz `reason` e `employee_id`. Mostre a `message`, **sem** opção de forçar |
| `driver_not_employee` | 422 | **Novo (20/10).** Só quando o Lara exigir funcionário cadastrado. Mostre a `message`, **sem** opção de forçar |
| `vehicle_not_found` | 404 | Mostra a lista devolvida em `vehicles` |
| `vehicle_ambiguous` | 422 | Pede para escolher entre os `vehicles` devolvidos |
| `vehicle_inactive` | 422 | Avisa que o veículo está desativado |
| `future_timestamp` | 422 | Relógio da máquina adiantado: logue e avise |
| — | 401 | Token inválido: erro de configuração, sem retry |

**`force` é sempre um segundo envio explícito, disparado por clique do operador** — nunca no
primeiro POST, nunca automático, e nunca para `driver_blocked`/`driver_not_employee` (o `force`
não passa por cima deles). Hoje um nome sem cadastro ainda é aceito; não fixe isso.

### C.5 O que não fazer

Não valide km localmente, não calcule km rodados, não crie tabela local de veículos/motoristas/
viagens (cache só de sessão, revalidado antes de registrar), não invente campos.

### C.6 Fila offline (só se o app já tiver uma)

Guarde o instante do registro e envie em `registered_at` (ISO 8601) quando a chamada sair; o
Lara aceita passado e recusa futuro. Saída recusada com `driver_blocked`/`driver_not_employee` na
sincronização sai da fila e aparece ao operador como recusada — reenviar não muda o resultado.
Sem fila, não envie `registered_at`.

---

## D. Testes e conferência

Cubra, no padrão de testes deste repositório (com a API do Lara simulada):

- CPF com várias linhas (terceirizado + liberação pontual + ingresso): cada botão chama a rota
  do seu tipo com o id certo.
- Código `ING-…` digitado em minúsculo e sem hífen chega à consulta; `ticket_not_found` mostra a
  mensagem; `ticket_seat_only` e `reason` desconhecido caem em negado com `ticket.message`.
- Registro que volta negado (`ticket_already_used`, liberação já usada) substitui a linha da
  consulta.
- Fila do Uber: liberar pelo id, `409 uber_request_not_waiting` recarrega a lista, corrigir placa
  com `422 invalid_plate`; `funcionario_bloqueado` em vermelho com o `member_validation_detail`, e
  o botão Liberar continua disponível.
- Frota: ciclo saída → `out` → retorno → `available`; `vehicle_already_out` leva ao retorno;
  `odometer_below_departure` com confirmação e segundo envio com `force: true`;
  `driver_not_found` pede o nome; `driver_blocked` sem botão de forçar; falha de rede e 401 sem
  quebrar a tela.

Conferência manual em homologação (no Lara de homologação, `php artisan ingressos:dados-teste`
cria o evento "[TESTE] Show de Hoje (check-in)" com entrada aberta e imprime 3 códigos válidos
com o CPF do titular e um ingresso já usado; cria também o "[TESTE] Réveillon (lugar marcado)"
com mesas e 20 cortesias sem CPF, cujos códigos aparecem na tela do pedido no Lara):

- QR de ingresso válido → "Ingresso válido"; registrar → segunda leitura dá "já utilizado".
- Código de mesa → "não é entrada".
- Pedido de carro de funcionário de férias → selo vermelho com o motivo.
- Saída de veículo com matrícula de funcionário desligado → recusada, sem botão de forçar.

Ao terminar, entregue a lista do que já existia, do que foi implementado e do que ficou
divergente do contrato (com o motivo), para o time do Lara conferir.
