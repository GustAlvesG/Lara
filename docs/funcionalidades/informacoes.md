# Sistema de Informações

## O que é

O **Sistema de Informações** (InfoClube) é um gerenciador de conteúdo do clube. Cada
"informação" é um item de catálogo (uma atividade, serviço ou oferta) que pode conter
imagem, descrição rica, preços para sócios e não-sócios, dias/horários, responsáveis e
localização. O sistema mantém **histórico de versões**: cada edição cria uma nova versão sem
apagar a anterior.

## Para quem

Administradores/operadores do clube (painel web). Todas as telas exigem usuário autenticado
(middleware `auth`) e a permissão Spatie correspondente:

| Ação | Permissão |
|------|-----------|
| Listar, visualizar, ver histórico | `view information` |
| Criar | `create information` |
| Editar (gera nova versão) | `edit information` |
| Restaurar versão do histórico | `edit information` |
| Excluir | `delete information` |

## Pré-requisitos

- Estar logado no painel administrativo com a permissão necessária para a ação.

## Modelo de dados (visão funcional)

Uma **Informação** (`information`) é o "container" e agrupa várias versões de **Dados da
Informação** (`data_infos`). A versão exibida é sempre a mais recente.

- `information`: `id`, `privacy` (reservado), `created_by`, `created_at`, `deleted_at`.
- `data_infos` (cada versão): `name`, `description` (HTML), `fee` (taxa de matrícula),
  `image`, `slots` (vagas), `location`, `status`, e os campos múltiplos abaixo.
- `data_info_tag`: as **tags** da versão (ver abaixo).

### Tags

Substituíram o antigo campo único `category`. Usam a mesma tabela `tags` dos avisos — uma
tag "natação" é a mesma nos dois módulos — e os nomes são normalizados por `Tag::normalize()`
(minúsculas, sem espaços nas pontas), então "Natação" e "natação " são a mesma tag.

O vínculo é **por versão** (`data_info_id`), não pela informação: como cada edição grava uma
linha nova em `data_infos`, o histórico preserva quais eram as tags à época. Restaurar uma
versão pelo histórico restaura também as tags daquela versão.

**São obrigatórias: no mínimo 3 tags distintas por informação.**

A coluna `category` não foi removida: a migration `data_info_tag` converte cada valor
existente em tag, e a coluna continua no banco (e ainda é varrida pela busca da API) por
causa dos registros antigos. Versões novas não a preenchem.

### Campos múltiplos

Estes campos aceitam várias entradas; internamente são armazenados como uma string com
itens separados por `;`:

| Campo | Conteúdo |
|-------|----------|
| `name_price` / `price_associated` / `price_not_associated` | Cada "pacote" de preço: título, valor sócio e valor não-sócio. |
| `day_hour` | Cada faixa de horário no formato `dia,hora_início,hora_fim`. |
| `responsible` / `responsible_contact` | Cada responsável: nome e telefone (com link para WhatsApp). |

#### A ordem importa

**O primeiro item de cada lista é o que aparece no card da listagem** — o primeiro preço
(valor de sócio) e o primeiro responsável (nome + link de WhatsApp). Por isso o formulário
tem setas ↑/↓ para reordenar as linhas, e a primeira linha de preços e de responsáveis leva
o selo *"Exibido no card"*.

Isso funciona sem nada no backend: a ordem das linhas no formulário é a ordem dos inputs no
DOM, que é a ordem em que os valores são concatenados com `;` — ou seja, reordenar na tela
reordena de fato no banco.

## Fluxo passo a passo

### 1. Listar informações — `GET /information?q=&page=`
Grid responsivo de cards (1 a 4 colunas conforme a largura), **paginado em 12 por página**.
Cada card mostra imagem (ou placeholder gerado), nome, tags, prévia da descrição,
vagas/status/local e, no rodapé, responsável (link de WhatsApp) e o primeiro preço de sócio.

A **busca é feita no servidor** (parâmetro `q`), varrendo nome, descrição, responsável,
status, localização e tags. Precisa ser no servidor: com paginação, um filtro só no navegador
encontraria apenas o que está na página aberta. O `q` é preservado nos links de paginação
(`withQueryString()`).

A consulta traz **só a versão vigente** de cada informação, via subconsulta
`MAX(id) GROUP BY information_id`. Antes o agrupamento era feito em PHP — carregava todas as
versões de todas as informações para descartar quase tudo em seguida, o que impedia paginar.

Todos os cards de uma linha têm a mesma altura: o grid usa `items-stretch` e o card é
`flex-col` com o rodapé preso por `mt-auto`, então conteúdos de tamanhos diferentes não
alteram a altura final.

### 2. Criar informação — `GET /information/create` → `POST /information`
Formulário em **duas colunas** (60% / 40%):
- **Esquerda:** imagem (apenas exibição — atualiza ao escolher um arquivo), `name` e
  `description` (editor de texto rico próprio, ver abaixo).
- **Direita:** tags e os demais campos.

Obrigatórios: `name` (até 255 caracteres), `description` e **no mínimo 3 tags**.
Opcionais (ativados por switch): imagem, taxa de matrícula, pacotes de preço
(sócio/não-sócio), dias e horários, responsáveis, vagas, status e localização.

Ao salvar, os campos múltiplos são concatenados com `;`, a `description` passa pelo
`App\Support\HtmlSanitizer` (allow-list de tags HTML — ver abaixo), as tags são criadas/
vinculadas e é criada a primeira versão em `data_infos`. Redireciona para a listagem.

### Editor de texto (campo `description`)

Componente próprio (`<x-rich-editor>`, `resources/views/components/rich-editor.blade.php` +
`public/js/information/editor.js`), sem dependência externa. Suporta **negrito**, *itálico*,
sublinhado, inserir tabela e cor de fundo do texto selecionado. O HTML gerado é sempre
filtrado no servidor por `App\Support\HtmlSanitizer::clean()` antes de gravar — tags fora da
allow-list (`p`, `br`, `strong`/`b`, `em`/`i`, `u`, `span`, `div`, `table`, `thead`, `tbody`,
`tr`, `td`, `th`) são desembrulhadas (mantém o texto, descarta a tag); tags perigosas
(`script`, `iframe`, `form`, etc.) são removidas com o conteúdo. Em `style`, só
`background-color` sobrevive. Isso vale tanto para conteúdo novo quanto para o HTML mais rico
gravado antigamente pelo CKEditor (títulos, listas etc. viram texto simples ao serem
reexibidos).

### 3. Visualizar — `GET /information/{information}`
Mostra todos os dados da versão atual (campos somente leitura). Preços aparecem como
"Título — R$ X,XX (Sócio) | R$ Y,YY (Não Sócio)" e horários como "Dia — HH:MM–HH:MM". Há
botões de **Editar**, **Excluir** e **Ver histórico**.

### 4. Editar — `GET /information/{information}/edit` → `POST /information`
Mesmo formulário da criação, pré-preenchido, com o `information_id` num campo oculto. O form
**submete para a mesma rota `POST /information` do `store()`** (não para a `PUT`) — é a
presença de `information_id` no payload que faz o controller tratar como edição em vez de
criação. **Importante:** a edição **não sobrescreve** a versão anterior — cria uma **nova
versão** em `data_infos`. Redireciona para a visualização.

A rota `PUT /information/{information}` (`update()`) existe para outro fluxo: o botão
**"Tornar versão atual"** da tela de histórico (item 6).

### 5. Excluir — `DELETE /information/{information}`
Faz *soft delete* da informação (deixa de aparecer na lista). `show`, `edit` e `history`
passam a responder 404 para uma informação excluída — não é só a listagem que esconde.

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

## Mensagens e validações

- `name` (máx. 255) e `description` são obrigatórios; os demais campos (`fee`, `category`,
  `slots`, `status`, `location`, pacotes de preço, responsáveis, dias/horários) são validados
  em `StoreInformationRequest` quando presentes.
- Imagem deve ser jpeg/png/jpg/gif (svg não é aceito — pode conter script embutido) e no
  máximo 4 MB.
- Se o salvamento falhar (ex.: erro inesperado no servidor), o formulário volta com os dados
  preenchidos e uma mensagem de erro — o erro é registrado no log, não exibido na tela.

## Referência técnica

- Controllers: [`InformationController`](../controllers.md#informationcontroller)
- Models: [`Information`, `DataInfo`](../models.md#conteúdo-informativo)
- Rotas: [seção Web](../rotas.md#81-rotas-web-routeswebphp)
- Sanitização de HTML: `App\Support\HtmlSanitizer`
- Editor: `resources/views/components/rich-editor.blade.php`, `public/js/information/editor.js`, `public/css/information/editor.css`
- Formulário (criar/editar): `resources/views/information/partials/form.blade.php` +
  `public/js/information/form.js` (componente Alpine) + `public/css/information/form.css`
- Leitura (detalhe/histórico): `resources/views/information/partials/details.blade.php`

> As telas usam Tailwind + Alpine puro. O grid do Bootstrap (`.row`/`.col-*`) foi removido
> destas views: as margens negativas do `.row` dentro de cards com padding eram a causa dos
> elementos sobrepostos, e as classes de seção compartilhadas com os inputs (`.responsible`,
> `.day_hour`) faziam o jQuery esconder campos que deveriam continuar visíveis.
