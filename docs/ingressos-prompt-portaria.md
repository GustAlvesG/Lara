# Prompt de mudança para o agent do sistema da portaria (27/10/2026)

> Cole o conteúdo abaixo (da linha `---` em diante) em um Claude Code aberto no repositório
> do **monitor de acesso da portaria** (o app da guarita). É uma **mudança** sobre a
> integração que já existe (`/api/company-access/*`), causada pelo novo módulo **Ingressos**
> do Lara. Regras completas no Lara: `docs/funcionalidades/ingressos.md`.

---

O Lara passou a vender **ingressos de eventos** do clube (bailes, shows, festas). Quem compra
pode ser sócio ou não sócio, e o ingresso é **nominal** (nome e CPF de quem entra). O não sócio
precisa passar pela portaria, por isso o ingresso entrou na **mesma consulta por CPF** que este
sistema já faz para terceirizado, freelancer e liberação pontual. Nada muda na autenticação nem
nas rotas que já existem. O que muda: um **tipo novo de linha** na resposta, um **formato novo
de alvo** (o código do ingresso) e **uma rota nova** de registro. Nenhuma regra é decidida
aqui: o Lara decide e devolve o motivo pronto.

## Regra de negócio (para entender as mensagens)

- Um ingresso vale **uma entrada**. Registrar o acesso **usa** o ingresso (check-in). Consultar
  não usa.
- A entrada abre algumas horas antes do início do evento (hoje 3 h, configurável no Lara) e
  fecha no fim do evento. Fora disso, o Lara **nega** na portaria.
- Ingresso pendente de pagamento, cancelado ou expirado **não entra**.
- **Evento de vários dias** (atualização de 27/10/2026, sem mudança de formato): o ingresso
  pode ser uma **diária** (entra uma vez, só no dia dela) ou um **passaporte** (entra **uma vez
  em cada dia**, com o mesmo QR). Para o passaporte, "já utilizado" quer dizer "já entrou
  **hoje**"; amanhã ele volta a aparecer liberado. `ticket.type` passa a vir com o dia ou
  "Passaporte" (ex.: `Pista · Sáb 13/02`), e `ticket.window` pode listar vários dias
  separados por `; ` (passaporte). Os `reason` são os mesmos: só mostre os textos como vêm.
- O porteiro confere o nome com um documento com foto: o ingresso é pessoal.

## 1. Alvo novo: o código do ingresso

Além de CPF, `PLACA.Nome.Obs` e nome de empresa, o `target` agora pode ser o **código do
ingresso**: `ING-` seguido de 8 letras/números (ex.: `ING-7KQ2MZ9H`). É o conteúdo do **QR
Code** do ingresso — se a guarita tiver leitor de QR (ou de código de barras 2D) que "digita"
no campo, ele passa a funcionar sem mudança nenhuma. O Lara aceita minúsculas, espaço ou sem o
hífen (`ing 7kq2mz9h`, `ING7KQ2MZ9H`).

Vale para `POST /api/company-access/validate-access` e `POST /api/company-access/register-access`.

- Código encontrado → `{ found: true, type: "ticket", company: "Ingresso", company_id: null, workers: [ <linha do ingresso> ] }`.
- Código que não existe → `{ found: false, reason: "ticket_not_found", workers: [] }`.
  Mostre "Ingresso não encontrado para este código."

## 2. Linha nova em `workers`: `type: "ticket"`

Na busca por **CPF**, os ingressos pagos daquele CPF em eventos com a entrada aberta agora
entram na mesma lista, depois das outras linhas. Quando só há ingresso, o cabeçalho vem com
`type: "ticket"` e `company: "Ingresso"`.

```json
{
  "id": 412,
  "type": "ticket",
  "name": "Carla Visitante",
  "allowed": true,
  "image": null,
  "company_id": null,
  "company": "Ingresso",
  "reason": "ticket_access_granted",
  "ticket": {
    "code": "ING-7KQ2MZ9H",
    "event": "Baile de Verão",
    "type": "Pista",
    "message": "Pode entrar.",
    "window": "13/02 19:00 a 14/02 04:00",
    "used_at": null
  }
}
```

| `reason` | `allowed` | Mostrar |
|---|---|---|
| `ticket_access_granted` | true | "Ingresso válido" / `ticket.message` |
| `ticket_already_used` | false | "Ingresso já utilizado" + `ticket.message` (traz a hora em que entrou) |
| `ticket_not_valid` | false | "Ingresso não está válido" + `ticket.message` |
| `ticket_outside_window` | false | "Fora do horário de entrada" + `ticket.message` (traz a janela) |

O que fazer na tela:

1. Uma etiqueta própria **"Ingresso"** (verde) na linha, como as de Freelancer e Liberação
   Pontual.
2. Abaixo do nome: `ticket.message`, depois `ticket.event · ticket.type`, depois o
   `ticket.code` em fonte monoespaçada e `ticket.window`.
3. `image` vem sempre `null`: mostre a inicial, como nas outras linhas sem foto.
4. Qualquer `reason` desconhecido com `allowed: false` cai num estado neutro de negado
   mostrando `ticket.message`.

## 3. Registro

- **`register-access`** (Enter / registro direto): para a linha de ingresso o Lara **faz o
  check-in** e devolve a linha **atualizada**. Se outro registro usou o ingresso entre a
  consulta e o registro, a linha volta **negada** (`ticket_already_used`) — mostre a linha que
  o registro devolveu, não a da consulta (o mesmo cuidado que já existe para a liberação
  pontual).
- **Rota nova** para o botão "Registrar" de uma linha específica (quando o CPF trouxe mais de
  uma linha):

  `POST /api/company-access/register-ticket-access` com `{ "ticket_id": <id da linha> }`

  Resposta: `{ found: true, type: "ticket", company: "Ingresso", workers: [ <linha atualizada> ] }`;
  ingresso inexistente: **404** `{ found: false, reason: "ticket_not_found" }`.
  Mesmo grupo e mesma autenticação das outras rotas `register-*`.

O `id` da linha de ingresso é de outra tabela: não o mande para `register-worker-access`.

## 4. Histórico

O acesso por ingresso entra no mesmo histórico (`company_access_logs`), com `ticket_id`
preenchido e os `reason` acima. Se este sistema mostra o histórico, use a etiqueta "Ingresso"
e os textos da tabela.

## Teste em homologação

No Lara de homologação, `php artisan ingressos:dados-teste` cria o evento
"[TESTE] Show de Hoje (check-in)" com a entrada aberta e imprime 3 códigos válidos com o CPF
de cada titular, além de um ingresso já usado.
