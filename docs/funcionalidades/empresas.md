# Empresas e Controle de Acesso

## O que é

Cadastro de **empresas terceirizadas**, seus **trabalhadores** e as **regras de acesso**
(dias e horários permitidos/bloqueados). Inclui um endpoint para **validar o acesso** de um
trabalhador na portaria.

## Para quem

- **Administradores** (painel web) — cadastro de empresas, trabalhadores e regras.
- **Integração de portaria** (API pública) — validação de acesso.

## Entidades

- **Empresa (Company):** razão social, contato e logo.
- **Trabalhador (CompanyWorker):** funcionário da empresa, com foto.
- **Regra de Acesso (CompanyAccessRule):** janela de acesso (`include`/`exclude`) por
  data/hora/dias da semana, aplicável à empresa toda ou a um trabalhador.

## Fluxo passo a passo (admin)

### Empresas
- `GET /company` — lista.
- `GET /company/create` → `POST /company` — cria (campos `name`, `address`, `email`,
  `telephone`, `description`, `image`).
- `GET /company/{company}` — detalhes (com trabalhadores e regras).

### Trabalhadores
- `GET /company/{company}/worker/create` → `POST /company/{company}/worker` — cria.
- `DELETE /company/{company}/worker/{worker}` — remove.

**Foto do trabalhador:** enviada em **base64** e salva como arquivo de imagem; o banco guarda
apenas o nome do arquivo.

### Regras de acesso
- `GET /company/{company}/rules/create` → `POST /company/{company}/rules` — cria.

Campos: `type` (`include`/`exclude`), `start_date`/`end_date`, `start_time`/`end_time`,
`description`, dias da semana e, opcionalmente, `company_worker_id` (se vazio, vale para toda
a empresa).

## Validação de acesso — `POST /api/company-access/validate-access`

Recebe um `target` que pode ser:
- um **CPF** (11 dígitos) → localiza o trabalhador e sua empresa;
- um **nome de empresa** (busca parcial);
- com **curinga `*`** (ex.: `*1234` ou `1234*`) → amplia a busca entre os trabalhadores.

Para cada trabalhador da empresa, aplica as regras:
- **Inclusão (`include`):** se nenhuma regra de inclusão for satisfeita → acesso **negado**.
- **Exclusão (`exclude`):** se alguma regra de exclusão for satisfeita → acesso **negado**.

**Retorno (JSON):** lista de trabalhadores com `name`, `response` (true/false), `image` e
`id`.

> O CPF é validado (11 dígitos, dígitos verificadores, rejeita sequências repetidas).

## Campos principais

| Entidade | Campos |
|----------|--------|
| Company | `name`, `address`, `email`, `telephone`, `description`, `image` |
| CompanyWorker | `company_id`, `name`, `email`, `position`, `document`, `telephone`, `image` |
| CompanyAccessRule | `company_id`, `company_worker_id`, `type`, `start_date`, `end_date`, `start_time`, `end_time`, `description` |

## Regras de negócio

- Regras podem valer para a **empresa inteira** ou para um **trabalhador específico**.
- A lógica de `include`/`exclude` combina data, hora e dias da semana.
- Empresas e trabalhadores usam *soft delete*.

## Referência técnica

- Controllers: [`Company\*`](../controllers.md#company--apphttpcontrollerscompany)
- Service: [`CompanyService`](../services.md#companyservice)
- Models: [Empresas](../models.md#empresas-appmodelscompany)
