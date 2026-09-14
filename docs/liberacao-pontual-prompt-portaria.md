# Prompt para o agent do sistema da portaria — Liberação Pontual

> Cole o conteúdo abaixo (da linha `---` em diante) em um Claude Code aberto no repositório
> do **monitor de acesso da portaria** (`access-monitor-company-lara`). Ele corrige o registro
> por CPF, que hoje manda toda linha para o endpoint de terceirizado, e ensina a tela a
> mostrar a liberação pontual.

---

Você vai corrigir, neste sistema da portaria, o **registro de acesso pelo clique no card** e
passar a exibir um tipo novo de linha, a **liberação pontual**. O back-end já está pronto no
Lara. **Nenhuma regra de acesso é decidida aqui.**

## O problema

A busca por CPF (`validate_path`) devolve em `workers[]` linhas de **três tipos diferentes**,
cada uma com o `id` de uma **tabela diferente** do Lara:

| `type` | O que é | O `id` é de |
|---|---|---|
| `worker` (ou ausente) | Terceirizado de empresa parceira | `company_workers` |
| `freelancer` | Freelancer com contrato no horário | `freelancers` |
| `one_off` | **Liberação pontual** (novo) | `one_off_accesses` |

Hoje `register_access()` / `_post_register()` manda **toda** linha para `register_path`
(`register-worker-access`) com `{"worker_id": item["id"]}`. Para `freelancer` e `one_off` isso
está errado: o número é de outra tabela. O resultado é um de dois:

- o Lara responde `reason: worker_not_found` e a tela mostra **"CPF não encontrado no
  sistema"** — é o que a portaria está vendo agora com a liberação pontual; ou, pior,
- o número coincide com um terceirizado ativo e o acesso é **gravado na pessoa errada**, com
  "REGISTRADO" na tela.

O freelancer tem o mesmo defeito desde que entrou na busca por CPF — corrija os dois juntos.

## A correção: registrar pelo `type` da linha

| `type` da linha | Rota | Corpo |
|---|---|---|
| `worker` ou ausente | `register_path` (sem mudança) | `{ "worker_id": id }` |
| `freelancer` | `register_path` com `-worker` → `-freelancer` | `{ "freelancer_id": id }` |
| `one_off` | `register_path` com `-worker` → `-one-off` | `{ "one_off_access_id": id }` |
| qualquer outro | **não registre** | — |

Ou seja, a partir do padrão `/api/company-access/register-worker-access`:

- `/api/company-access/register-freelancer-access`
- `/api/company-access/register-one-off-access`

Derive as rotas do `register_path` por substituição, **do mesmo jeito que o Uber já faz**
(`_post_register_uber`). Não crie chaves novas no `config.json`: as máquinas instaladas
continuam funcionando sem reconfigurar.

**Tipo desconhecido não cai no terceirizado.** Se chegar um `type` que este código não
conhece, trave o card, mostre "Tipo de acesso não suportado — atualize o Lara Externos" e
registre no `applog` o `type` e o `id`. Cair no `worker_id` por padrão é exatamente o defeito
que está sendo corrigido.

Leve o `type` junto até `_post_register` (hoje ele recebe só o `worker_id`) e use-o também
nas mensagens do `applog` — "Registro recusado para one_off_access_id=7", não "worker_id=7".

## A liberação pontual

É a exceção da portaria: alguém sem empresa, Uber ou contrato, liberado por um usuário do
Lara para **uma única entrada, só no dia**. Na busca ela chega assim:

```json
{
  "found": true,
  "type": "one_off",
  "company_id": null,
  "company": "Liberação Pontual",
  "workers": [{
    "id": 7,
    "type": "one_off",
    "name": "João Técnico",
    "allowed": true,
    "image": "https://<lara>/images/one_off_....jpg",
    "company_id": null,
    "company": "Liberação Pontual",
    "reason": "one_off_access_granted",
    "one_off": {
      "reason": "Pane no elevador social",
      "authorized_by": "Fulana de Tal",
      "created_at": "14:05",
      "used_at": null
    }
  }]
}
```

- `image` e `one_off.authorized_by` podem vir `null`.
- Quando a liberação **já foi usada hoje**, a linha continua vindo, mas com
  `allowed: false`, `reason: "one_off_access_used"` e `one_off.used_at` preenchido — é para a
  portaria saber que a pessoa já entrou com ela. Cancelada ou de outro dia **não vem**.
- O mesmo CPF pode trazer várias linhas ao mesmo tempo (terceirizado negado + liberação
  pontual, por exemplo). Cada card registra pela sua própria rota.

### Registro

```
POST /api/company-access/register-one-off-access
{ "one_off_access_id": 7 }
```

Resposta `200` no mesmo formato da busca, com `workers[0]` atualizado. **Registrar gasta a
liberação.** A busca não gasta — continue nunca registrando automaticamente ao buscar.

Um `200` com `found: true` **não basta** para dizer que entrou: confira `workers[0].allowed`.
Entre a busca e o clique, outro porteiro pode ter usado a mesma liberação; o Lara então grava
o acesso como negado e devolve `allowed: false` com `reason: "one_off_access_used"`.

| Card era | Resposta | O que a tela faz |
|---|---|---|
| Permitido | `allowed: true` | `✓ REGISTRADO` e **trava o card**, como no Uber — é uso único |
| Permitido | `allowed: false` | `⚠ NÃO REGISTRADO` com "Liberação já utilizada às {used_at}" e trava o card |
| Negado (já usada) | — | Mantenha a confirmação "Registrar mesmo assim?" que já existe; se confirmar, o Lara grava como negado, igual ao terceirizado |

`422` significa que o id não existe mais: trate como erro de registro e logue o corpo da
resposta (validação do Laravel não traz `reason`).

### Exibição no card

- Etiqueta **LIBERAÇÃO PONTUAL** (âmbar), para a portaria saber que é exceção.
- O **motivo** (`one_off.reason`) — é a informação que justifica a entrada.
- "Autorizada por {authorized_by} às {created_at}" (sem o nome quando vier `null`).
- Negada: "Já utilizada às {used_at}".

Para o **freelancer**, a linha traz `service` (`window`, `period`, `function`, `location`)
quando está permitida; se couber no card, mostre etiqueta **FREELANCER** e `window`.

### Mensagens

Acrescente ao `REASON_MESSAGES`:

| `reason` | Mensagem |
|---|---|
| `one_off_access_used` | Liberação pontual já utilizada |
| `one_off_access_canceled` | Liberação pontual cancelada |
| `one_off_access_expired` | Liberação pontual vencida |
| `one_off_access_not_found` | Liberação pontual não encontrada |
| `freelancer_no_service` | Freelancer sem serviço neste horário |
| `freelancer_not_found` | Freelancer não encontrado |

E ajuste o `worker_not_found` para "CPF não encontrado (terceirizado, freelancer ou
liberação pontual)". Em `_log_denied_access`, registre o `type` da linha no lugar do
"INDIVIDUAL" fixo.

## O que NÃO fazer

- **Não use `register-access` com o CPF** para o clique no card: aquela rota registra
  **todas** as linhas do CPF de uma vez — gastaria a liberação pontual quando o porteiro quis
  registrar só o terceirizado.
- **Não deduza o tipo pelo nome da empresa** ("Liberação Pontual", "Freelancer"): o texto é
  para exibir e pode mudar. Use `type`.
- **Não crie a liberação por aqui.** Quem cria é um usuário do Lara, em *Externos →
  Liberação Pontual*. Se quiser, quando a busca der `worker_not_found`, mostre o texto
  "Sem cadastro — peça uma liberação pontual no Lara".
- **Não guarde liberação localmente** nem controle o "uso único" aqui: quem decide é o Lara,
  a cada busca.

## Testes

Cubra, no padrão de `tests/`:

- clique em card `worker`, `freelancer` e `one_off` indo cada um para a sua rota, com o campo
  certo no corpo;
- `type` desconhecido não faz POST nenhum e mostra o aviso;
- `one_off` com `200` e `allowed: false` na resposta exibindo "já utilizada" e travando o card;
- `one_off` registrado com sucesso travando o card (segundo clique não faz POST);
- `422` sem `reason` não quebrando a tela.

Depois, publique uma versão nova pelo fluxo de atualização que o app já tem (`version.py` /
`updater.py`) — a correção só chega à guarita com a atualização.

## Referência

A regra completa está em `docs/company-access-control.md`, seção *Liberação Pontual*, no
repositório do Lara. Em qualquer divergência entre este prompt e aquele documento, o documento
vence.
