# Controle de Acesso de Empresas Parceiras

Sistema para validar e registrar acessos de funcionários de empresas terceirizadas, com interface web.

---

## Sumário

- [Visão Geral](#visão-geral)
- [Cadastro de Empresas](#cadastro-de-empresas)
  - [Empresa](#empresa)
  - [Funcionários](#funcionários)
  - [Regras de Acesso](#regras-de-acesso)
- [Interface Web](#interface-web)
  - [Monitor de Acesso](#monitor-de-acesso)
  - [Histórico de Acessos](#histórico-de-acessos)
---

## Visão Geral

O módulo funciona é bem simples

| Camada | Finalidade |
|---|---|
| **Interface Web** | Porteiros / operadores realizam consultas e registros em tempo real |

A validação sempre verifica as **regras de acesso** cadastradas para a empresa e/ou o funcionário específico antes de liberar ou negar.

---

## Cadastro de Empresas

**Navegação:** Parceiros → Empresas (`lara.clubedosfuncionarios.com.br/company`)

O módulo é organizado em três níveis: **Empresa → Funcionários → Regras de Acesso**.  
Uma empresa pode ter regras gerais (valem para todos os funcionários) e cada funcionário pode ter regras individuais que sobrescrevem as da empresa.

---

### Empresa

#### Listar empresas

Página inicial do módulo. Exibe todas as empresas cadastradas em cards com nome, e-mail e telefone.

**URL:** `lara.clubedosfuncionarios.com.br/company`

---

#### Cadastrar empresa

**URL:** `lara.clubedosfuncionarios.com.br/company/create`  
**Botão:** "Nova Empresa" na listagem

| Campo | Obrigatório | Descrição |
|---|---|---|
| Nome | Sim | Nome da empresa parceira |
| E-mail | Não | E-mail de contato |
| Telefone | Não | Telefone de contato |
| Endereço | Não | Endereço físico |
| Descrição | Não | Observações gerais |
| Imagem / Logo | Não | Foto ou logo da empresa |

Após salvar, redireciona para a listagem com mensagem de sucesso.

---

#### Perfil da empresa

**URL:** `lara.clubedosfuncionarios.com.br/company/{id}`

Exibe o perfil completo com duas abas:

- **Funcionários** — lista todos os funcionários cadastrados com avatar, nome e cargo. Cada item tem atalhos para ver o perfil ou excluir.
- **Regras de Acesso** — lista todas as regras gerais da empresa (válidas para todos os funcionários). Cada regra exibe tipo, vigência, dias da semana ativos e horário (se definido).

Ações disponíveis no cabeçalho:
- **Editar Dados** → abre o formulário de edição
- **Excluir** → remove a empresa (solicita confirmação)

---

#### Editar empresa

**URL:** `lara.clubedosfuncionarios.com.br/company/{id}/edit`

Mesmo formulário do cadastro, com campos pré-preenchidos. Salva via `PUT /company/{id}` e retorna ao perfil.

---

### Funcionários

#### Cadastrar funcionário

**URL:** `lara.clubedosfuncionarios.com.br/company/{id}/worker/create`  
**Botão:** "Adicionar Funcionário" na aba Funcionários do perfil da empresa

| Campo | Obrigatório | Descrição |
|---|---|---|
| Nome | Sim | Nome completo |
| E-mail | Sim | E-mail do funcionário |
| Cargo | Sim | Função/cargo na empresa |
| CPF (Documento) | Não | Usado para identificação no monitor de acesso |
| Telefone | Não | Telefone de contato |
| Foto | Não | Imagem em base64 (capturada via câmera ou upload) |

> O CPF é o campo-chave para buscas no Monitor de Acesso. Sem ele, o funcionário só pode ser localizado via nome da empresa.

Após salvar, redireciona para o perfil da empresa.

---

#### Perfil do funcionário

**URL:** `lara.clubedosfuncionarios.com.br/company/{company_id}/worker/{worker_id}`

Exibe dados do funcionário (foto, nome, cargo, e-mail, telefone, CPF) e a seção **Regras de Acesso Individuais**.

Se não houver regras individuais, o funcionário segue as regras gerais da empresa (mensagem informativa exibida).

Ações disponíveis:
- **Editar** → abre o formulário de edição
- **Excluir** → remove o funcionário (solicita confirmação)
- **Nova Regra Individual** → cria uma regra exclusiva para este funcionário

---

#### Editar funcionário

**URL:** `lara.clubedosfuncionarios.com.br/company/{company_id}/worker/{worker_id}/edit`

Mesmo formulário do cadastro, com campos pré-preenchidos. Salva via `PUT` e retorna ao perfil do funcionário.

---

### Regras de Acesso

As regras determinam quando um funcionário (ou todos da empresa) pode ou não acessar o local.

#### Criar regra para a empresa

**URL:** `lara.clubedosfuncionarios.com.br/company/{id}/rules/create`  
**Botão:** "Nova Regra" na aba "Regras de Acesso" do perfil da empresa  
A regra criada aqui vale para **todos os funcionários** da empresa.

#### Criar regra individual para um funcionário

**URL:** `lara.clubedosfuncionarios.com.br/company/{company_id}/worker/{worker_id}/rules/create`  
**Botão:** "Nova Regra Individual" no perfil do funcionário  
A regra criada aqui vale **somente para aquele funcionário** e sobrescreve as regras da empresa.

---

#### Campos do formulário de regra

| Campo | Obrigatório | Descrição |
|---|---|---|
| Tipo | Sim | `Inclusão` (permite) ou `Exclusão` (bloqueia) |
| Data de Início | Sim | Data a partir da qual a regra entra em vigor |
| Data de Término | Não | Deixar vazio para regra por tempo indeterminado |
| Dias da semana | Não | Marque os dias em que a regra é válida (padrão: todos marcados) |
| Horário de início | Não | Hora a partir da qual o acesso é válido |
| Horário de término | Não | Hora limite para o acesso |
| Observações | Não | Descrição/motivo da regra |

> Se nenhum horário for informado, a regra vale o dia inteiro nos dias selecionados.

---

#### Como as regras são avaliadas

```
Para cada funcionário no momento do acesso:

1. Existe alguma regra de INCLUSÃO ativa agora?
   → Não → Acesso NEGADO

2. Existe alguma regra de EXCLUSÃO ativa agora?
   → Sim → Acesso NEGADO

→ Acesso PERMITIDO
```

**Prioridade de regras:**

1. Se o funcionário tem **regras individuais** → somente elas são usadas
2. Se **não tem regras individuais** → as regras gerais da empresa são usadas

Uma regra está "ativa agora" quando a data atual está dentro do período de vigência, o dia da semana está marcado, e o horário atual está dentro da faixa (se definida).

---

#### Excluir uma regra

Cada regra exibe um ícone de lixeira. Ao clicar, uma confirmação é solicitada e a regra é removida permanentemente. Históricos de acessos já registrados **não são afetados**.

---

## Interface Web

### Monitor de Acesso

**URL:** `lara.clubedosfuncionarios.com.br/company/access-monitor`  
**Navegação:** Parceiros → Monitor de Acesso

#### Como usar

1. Digite no campo de busca um **CPF** (com ou sem formatação) ou **nome parcial da empresa**
2. Escolha a ação:

| Botão | Comportamento |
|---|---|
| **Consultar** | Valida o acesso e exibe o resultado, mas **não registra** no histórico |
| **Registrar Acesso** | Valida e **grava** no histórico de acessos |
| **Enter** | Equivale a "Registrar Acesso" |

#### Busca por CPF

Retorna o funcionário correspondente. Se `Registrar Acesso` for acionado, o registro é gravado imediatamente com o status (permitido/negado).

```
Campo: 123.456.789-09
         ↓
Resultado: João Silva — ✓ Permitido
```

#### Busca por Nome de Empresa

Retorna **todos os funcionários** da empresa. Para registrar, cada funcionário possui um botão individual **"Registrar"** — o registro é por pessoa, não em massa.

```
Campo: Acme Serviços
         ↓
Resultado: 3 funcionários
  João Silva  ✓ Permitido  [Registrar]
  Maria Costa ✗ Negado     [Registrar]
  Pedro Alves ✓ Permitido  [Registrar]
```

Após clicar "Registrar" em um funcionário, o botão muda para `✓ Registrado` (verde) ou `✗ Registrado` (vermelho) e fica desabilitado.

#### Indicadores visuais

| Barra de cor | Significado |
|---|---|
| Verde | Todos os funcionários do resultado estão permitidos |
| Amarelo | Resultado misto (alguns permitidos, alguns negados) |
| Vermelho | Todos negados |

#### Tags de status

| Tag | Cor | Significa |
|---|---|---|
| Registrado | Índigo | Acesso gravado no histórico |
| Selecione o funcionário | Amarelo | Busca por empresa — escolha quem registrar |
| Apenas Consulta | Cinza | Resultado sem gravação |

#### Consultas da sessão

No rodapé da página, as últimas 10 consultas da sessão atual são exibidas em ordem cronológica reversa, com:
- Alvo consultado
- Empresa encontrada
- Horário
- Se foi consulta (`cons`) ou registro (`reg`)

---

### Histórico de Acessos

**URL:** `lara.clubedosfuncionarios.com.br/company/access-logs`  
**Navegação:** Parceiros → Histórico

Exibe todos os acessos registrados com filtros e estatísticas.

#### Cards de estatísticas (dia atual)

| Card | Dado |
|---|---|
| Registros hoje | Total de entradas criadas no dia |
| Permitidos hoje | Total com `allowed = true` |
| Negados hoje | Total com `allowed = false` |

#### Filtros disponíveis

| Filtro | Campo | Tipo |
|---|---|---|
| Empresa | `company_id` | Select com todas as empresas cadastradas |
| Status | `status` | Todos / Permitido / Negado |
| Data de | `date_from` | Data (YYYY-MM-DD) |
| Data até | `date_to` | Data (YYYY-MM-DD) |

Ao aplicar filtros, o botão `✕` aparece para limpar todos de uma vez.

#### Colunas da tabela

| Coluna | Descrição |
|---|---|
| Data / Hora | Timestamp do registro |
| Alvo | CPF ou nome que foi pesquisado (fonte monoespaçada) |
| Empresa | Nome com link para a página da empresa (pode ser `—` se foi deletada) |
| Funcionário | Avatar + nome com link para o perfil (pode ser `—` se foi deletado) |
| Status | Badge verde `Permitido` ou vermelho `Negado` |
| Motivo | Texto traduzido do código interno |

#### Tradução dos motivos

| Código interno | Exibição |
|---|---|
| `access_granted` | Acesso liberado pelas regras |
| `access_denied` | Bloqueado pelas regras |
| `worker_not_found` | Funcionário não encontrado |
| `company_not_found` | Empresa não encontrada |
| `app_driver_access` | Motorista de aplicativo |

Acessos de **motorista de aplicativo** aparecem nesta mesma tabela: a coluna *Empresa* exibe a tag `Motorista de App`, a coluna *Funcionário* mostra o nome do motorista, e a *Obs* (quando informada) aparece abaixo do motivo.

---

## Registro de Acesso via API (endpoint único)

Endpoint único usado por integrações (câmera/LPR, totem etc.) para registrar acessos. O **tipo de registro é decidido pelo conteúdo do `target`** enviado no corpo — não há rotas separadas por tipo.

**Rota:** `POST /api/company-access/register-access`

#### Detecção pelo corpo (`target`)

| Formato do `target` | Detecção | Tipo de registro |
|---|---|---|
| CPF (ex: `123.456.789-09`) | regex de CPF (11 dígitos) | **Terceirizado** — funcionário da empresa parceira |
| `PLACA.Nome.Obs` (ex: `ABC1D23.João Silva.Entrega`) | 1º segmento é placa válida (Mercosul `ABC1D23` ou antiga `ABC1234`) + há nome | **Motorista de aplicativo** |
| Texto livre (ex: `Acme Serviços`) | qualquer outro caso | **Empresa** — todos os funcionários da empresa |

> A ordem de detecção é: placa → senão CPF/empresa. Como o 1º segmento de um CPF (`123`) nunca é uma placa válida, não há colisão entre CPF e o formato de motorista.

#### Motorista de aplicativo

Ao receber `PLACA.Nome.Obs`, o sistema **cadastra o veículo automaticamente** (caso a placa ainda não exista) e **registra o acesso** naquele momento.

- A **placa** é normalizada (maiúsculas, sem espaços/símbolos) e é a chave única do veículo.
- O **nome** é obrigatório.
- A **Obs** é opcional, um campo de descrição livre gravado no acesso (não no veículo).

```
target: ABC1D23.João Silva.Entrega iFood
         ↓
veículo ABC1D23 cadastrado (se novo) + acesso registrado com obs "Entrega iFood"
```

Resposta (motorista):
```json
{ "found": true, "created": true, "driver": { "id": 1, "plate": "ABC1D23", "name": "João Silva", "obs": "Entrega iFood" } }
```
`created` indica se o veículo foi cadastrado agora (`true`) ou já existia (`false`).

#### Armazenamento (motorista de aplicativo)

- **`app_drivers`** (tabela dedicada): registro do veículo/motorista — `plate` (única), `name`.
- **`company_access_logs`**: o evento de acesso é gravado junto com os acessos de empresas (colunas `app_driver_id` e `obs`), por isso aparece no mesmo Histórico de Acessos.

---