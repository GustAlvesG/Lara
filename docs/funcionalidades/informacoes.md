# Sistema de Informações

## O que é

O **Sistema de Informações** (InfoClube) é um gerenciador de conteúdo do clube. Cada
"informação" é um item de catálogo (uma atividade, serviço ou oferta) que pode conter
imagem, descrição rica, preços para sócios e não-sócios, dias/horários, responsáveis e
localização. O sistema mantém **histórico de versões**: cada edição cria uma nova versão sem
apagar a anterior.

## Para quem

Administradores/operadores do clube (painel web). Todas as telas exigem usuário autenticado
(middleware `auth`).

## Pré-requisitos

- Estar logado no painel administrativo.

## Modelo de dados (visão funcional)

Uma **Informação** (`information`) é o "container" e agrupa várias versões de **Dados da
Informação** (`data_infos`). A versão exibida é sempre a mais recente.

- `information`: `id`, `privacy` (reservado), `created_by`, `created_at`, `deleted_at`.
- `data_infos` (cada versão): `name`, `description` (HTML), `fee` (taxa de matrícula),
  `category`, `image`, `slots` (vagas), `location`, `status`, e os campos múltiplos abaixo.

### Campos múltiplos

Estes campos aceitam várias entradas; internamente são armazenados como uma string com
itens separados por `;`:

| Campo | Conteúdo |
|-------|----------|
| `name_price` / `price_associated` / `price_not_associated` | Cada "pacote" de preço: título, valor sócio e valor não-sócio. |
| `day_hour` | Cada faixa de horário no formato `dia,hora_início,hora_fim`. |
| `responsible` / `responsible_contact` | Cada responsável: nome e telefone (com link para WhatsApp). |

## Fluxo passo a passo

### 1. Listar informações — `GET /information`
Exibe um grid de cards (busca em tempo real por nome no topo). Cada card mostra imagem, nome,
responsável (com link de WhatsApp) e o preço mensal para sócio. Botão **"Nova Informação"** no
canto superior.

### 2. Criar informação — `GET /information/create` → `POST /information`
Formulário em duas colunas:
- **Obrigatórios:** `name` (até 255 caracteres) e `description` (editor rico/CKEditor).
- **Opcionais (ativados por checkbox):** imagem, taxa de matrícula, pacotes de preço
  (sócio/não-sócio), dias e horários, categoria, responsáveis, vagas, status e localização.

Ao salvar, os campos múltiplos são concatenados com `;` e é criada a primeira versão em
`data_infos`. Redireciona para a listagem.

### 3. Visualizar — `GET /information/{information}`
Mostra todos os dados da versão atual (campos somente leitura). Preços aparecem como
"Título — R$ X,XX (Sócio) | R$ Y,YY (Não Sócio)" e horários como "Dia — HH:MM–HH:MM". Há
botões de **Editar**, **Excluir** e **Ver histórico**.

### 4. Editar — `GET /information/{information}/edit` → `PUT /information/{information}`
Mesmo formulário da criação, pré-preenchido. **Importante:** a edição **não sobrescreve** a
versão anterior — cria uma **nova versão** em `data_infos`. Redireciona para a visualização.

### 5. Excluir — `DELETE /information/{information}`
Faz *soft delete* da informação (deixa de aparecer na lista, mas permanece no banco).

### 6. Histórico — `GET /information/{id}/history`
Lista todas as versões, da mais recente para a mais antiga (paginação de 5 por página). Cada
versão mostra os dados daquele momento, **quem criou** e **quando**, além de um botão para
**tornar aquela versão a atual** (cria uma nova versão copiando os dados da antiga).

## Regras de negócio

- **Versionamento:** toda edição gera uma nova linha em `data_infos`; nada é sobrescrito.
  Por isso `data_infos` não usa `updated_at`.
- **Campos múltiplos:** sempre serializados/desserializados pelo delimitador `;`.
- **Listagem:** a descrição tem as tags HTML removidas e é truncada (~250 caracteres) nos cards.
- **Privacidade:** o campo `privacy` (Público/Setor/Privado) existe no banco mas ainda **não
  está ativo** — atualmente todas as informações são tratadas como públicas.
- **`before_data`:** campo reservado para comparação futura entre versões.

## Mensagens e validações

- `name` e `description` são obrigatórios.
- Imagem deve ser um arquivo de imagem válido (jpeg, png, jpg, gif, svg).
- Após criar/editar/excluir, o sistema redireciona com mensagem de sucesso.

## Referência técnica

- Controllers: [`InformationController`, `DataInfoController`](../controllers.md#informationcontroller)
- Models: [`Information`, `DataInfo`](../models.md#conteúdo-informativo)
- Rotas: [seção Web](../rotas.md#81-rotas-web-routeswebphp)
