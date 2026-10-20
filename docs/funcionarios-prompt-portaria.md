# Prompt de mudança para o agent do sistema da portaria (20/10/2026)

> Cole o conteúdo abaixo (da linha `---` em diante) em um Claude Code aberto no repositório
> do **monitor de acesso da portaria**. É uma **mudança** sobre integrações que já existem
> (carro de aplicativo e frota), causada pelo novo cadastro de **Funcionários** do Lara.

---

O Lara ganhou um cadastro de funcionários mantido pelo RH. Ele passou a **autenticar** o
funcionário em duas integrações que este sistema já consome: o **carro de aplicativo** pedido
pelo WhatsApp e a **frota** da empresa. Nada muda na autenticação da API (mesmo token Bearer)
nem nas rotas. O que muda são valores novos nas respostas. Adapte a tela; **nenhuma regra é
decidida aqui** — o Lara decide e devolve o motivo pronto para mostrar.

## Regra de negócio (para você entender as mensagens)

Um funcionário fica **bloqueado** quando está desligado (rescisão em vigor), de férias, afastado
ou teve o cadastro excluído. O Lara manda o motivo em português, já formatado, por exemplo:
"Desligado em 10/10/2026", "Férias até 25/10/2026", "Afastamento (sem data de retorno)".

## 1. Carro de aplicativo (Uber)

Onde este sistema lê os pedidos (validação do motorista na cancela e a fila de espera), cada
pedido traz o resultado da conferência de quem pediu. Até hoje `member_validation` tinha três
valores: `validado`, `nao_encontrado`, `indisponivel`. Agora:

| Campo | Novo? | Conteúdo |
|---|---|---|
| `member_validation` | valor novo | `funcionario_bloqueado` — nome e matrícula/CPF conferem com um funcionário, mas ele está bloqueado |
| `member_validation_label` | já existia | para o valor novo vem `"Funcionário bloqueado"` |
| `member_validation_type` | já existia | `funcionario` também no bloqueado |
| `member_validation_detail` | **novo** | o motivo do bloqueio (string) ou `null` |
| `employee_id` | **novo** | id do funcionário que conferiu (apto ou bloqueado) ou `null` |

O que fazer:

1. Trate `funcionario_bloqueado` como um estado próprio, **com destaque de alerta (vermelho)**,
   mais forte que `nao_encontrado` (amarelo). Mostre `member_validation_label` e, logo abaixo,
   `member_validation_detail`.
2. **Não bloqueie a liberação** por conta disso: como nos outros estados, a conferência é
   informação para o porteiro, que decide. Só deixe o motivo impossível de passar despercebido.
3. Qualquer valor de `member_validation` desconhecido deve cair num estado neutro mostrando o
   `member_validation_label` — não quebre se o Lara criar outro valor no futuro.
4. `employee_id` é só para referência/log; não precisa aparecer na tela.

## 2. Frota

### Saída (`POST /api/fleet/departure`)

Dois erros novos, no mesmo formato de erro que você já trata (`ok: false`, `error`, `message`):

| `error` | HTTP | Quando | Corpo extra |
|---|---|---|---|
| `driver_blocked` | 403 | O motorista foi identificado (matrícula, CPF, nome ou `employee_id`) e está bloqueado | `reason` (o motivo) e `employee_id` |
| `driver_not_employee` | 422 | Só quando o Lara estiver configurado para exigir funcionário cadastrado e o motorista digitado não for um | — |

O que fazer:

1. Mostre o `message` do Lara (já vem pronto, ex.: "João da Silva não pode retirar veículo da
   frota: Férias até 25/10/2026.") e **não ofereça "registrar mesmo assim"** para esses dois
   erros: diferentemente de `odometer_below_last`, o `force` não passa por cima deles.
2. Se este sistema tem fila offline de saídas, uma saída recusada com `driver_blocked` ou
   `driver_not_employee` ao sincronizar deve sair da fila e ser mostrada ao operador como
   recusada — reenviar não muda o resultado.
3. Hoje um nome digitado sem cadastro ainda é aceito. Não fixe isso: quando o Lara ligar a
   exigência, o mesmo envio passa a voltar `driver_not_employee`.

### Busca de motorista (`GET /api/fleet/drivers?q=`)

- Agora devolve **só funcionários aptos**. Quem está bloqueado não aparece no autocomplete —
  é esperado.
- Cada item ganhou o campo **`sector`** (nome do setor ou `null`). Mostre-o junto do nome no
  autocomplete, no lugar de `department` se você já mostrava esse (o `department` continua
  vindo, mas é o texto cru do espelho de ponto e pode estar vazio).
- A busca por CPF passou a funcionar com e sem máscara.

## Conferência

- Pedido de carro de funcionário de férias aparece com o selo vermelho e o motivo.
- Saída de veículo com a matrícula de um funcionário desligado é recusada com a mensagem do Lara,
  sem botão de forçar.
- Autocomplete de motorista mostra o setor e não lista quem está afastado.
