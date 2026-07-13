# Carteirinhas

## O que é

Ferramenta para emitir carteirinhas de funcionário em cartão PVC (impressora tipo SD260),
com foto capturada pela webcam. Tem duas frentes:

- **Modelos (`/card-templates`)** — CRUD dos modelos de carteirinha: upload das imagens de
  frente/verso e um editor visual para posicionar a foto e os campos de texto sobre elas.
- **Emissão (`/id-cards`)** — tela onde o operador escolhe um modelo, digita os dados do
  funcionário, captura a foto e imprime.

Ambas ficam sob a permissão `manage id cards`.

## Sem cadastro de pessoa

Ao contrário de Freelancers/Usuários, **não existe uma entidade "funcionário" persistida**
para essa funcionalidade. Nome, função, matrícula, data de admissão e a foto capturada só
existem no navegador, na hora de gerar o cartão — não há uma rota de `store` para eles, nem
tabela no banco. Isso foi uma decisão deliberada: a carteirinha é impressa e o dado pode ser
descartado em seguida. O único dado persistido é o **modelo** (`CardTemplate`), que é reusado
em várias emissões.

## Entidades

1. **Modelo (`CardTemplate`)** — nome, imagem de frente, imagem de verso, `layout` (posições
   dos campos) e se está ativo (aparece ou não no seletor de emissão).

## Esquema do `layout`

Armazenado como JSON na coluna `card_templates.layout`, com 6 chaves fixas (o editor sempre
desenha essas 6 caixas — não é possível adicionar/remover campos pelo painel):

- **Frente:** `front.photo`, `front.name`, `front.role`
- **Verso:** `back.name`, `back.admission_date`, `back.registration_number`

Cada caixa guarda posição/tamanho em **porcentagem da imagem** (`x`, `y`, `w`, `h` — de 0 a
100), independente da resolução do arquivo enviado. Campos de texto guardam também
`font_size` (px), `bold` (bool), `align` (`left`/`center`/`right`) e `color` (hex). O campo
`front.photo` só tem posição/tamanho (é uma imagem, não texto).

Exemplo com os valores padrão de um modelo novo (orientação retrato, foto centralizada no
topo e textos centralizados abaixo dela):

```json
{
  "front": {
    "photo": { "type": "photo", "x": 15, "y": 6, "w": 70, "h": 45 },
    "name":  { "type": "text", "x": 5, "y": 55, "w": 90, "h": 10, "font_size": 16, "bold": true, "align": "center", "color": "#000000" },
    "role":  { "type": "text", "x": 5, "y": 66, "w": 90, "h": 8, "font_size": 12, "bold": false, "align": "center", "color": "#333333" }
  },
  "back": {
    "name":                { "type": "text", "x": 5, "y": 10, "w": 90, "h": 8, "font_size": 12, "bold": true, "align": "center", "color": "#000000" },
    "admission_date":      { "type": "text", "x": 5, "y": 22, "w": 90, "h": 6, "font_size": 10, "bold": false, "align": "center", "color": "#000000" },
    "registration_number": { "type": "text", "x": 5, "y": 32, "w": 90, "h": 6, "font_size": 10, "bold": false, "align": "center", "color": "#000000" }
  }
}
```

## Editor de posicionamento

Em `/card-templates/create` e `/card-templates/{id}/edit`: depois de enviar as imagens de
frente e verso, cada campo aparece como uma caixa tracejada arrastável sobre a imagem (rótulo
fixo: `FOTO`, `NOME`, `FUNÇÃO`, `NOME`, `DATA DE ADMISSÃO`, `MATRÍCULA`). Arraste para
reposicionar; **todas as caixas** (foto e texto, frente e verso) têm uma alça no canto
inferior direito para redimensionar (largura/altura mínima de 8% do cartão). Um painel abaixo
do editor permite ajustar tamanho de fonte, negrito, alinhamento e cor de cada campo de texto.
Tudo isso é serializado em JSON num campo oculto (`layout`) e enviado junto com o formulário —
implementado em JS puro (Alpine.js, `pointerdown`/`pointermove`/`pointerup`), sem biblioteca
externa de drag-and-drop (`resources/views/card-templates/partials/form.blade.php`).

## Emissão e impressão

Em `/id-cards`: o operador escolhe um modelo ativo, preenche nome/função/matrícula/data de
admissão, e captura a foto pela webcam (mesma técnica de captura usada no cadastro de
funcionário de empresa parceira — `getUserMedia` + `canvas` — com fallback de importar um
arquivo de imagem). A pré-visualização (frente e verso) é renderizada ao vivo com o `layout`
do modelo escolhido.

**Impressão:** ao clicar em "Imprimir", a mesma área de pré-visualização (não há uma segunda
cópia do HTML) é impressa via `window.print()`. CSS `@page` define o tamanho físico exato do
cartão (padrão CR-80 em **retrato**: 54mm de largura × 85,6mm de altura — configurável por
modelo via `card_width_mm`/`card_height_mm`) e uma regra `@media print` esconde todo o resto
da página, deixando só a frente e o verso, uma por página. A impressora (ex.: SD260) precisa
estar instalada como uma impressora comum do Windows — o operador a seleciona no diálogo
nativo de impressão do navegador. Não há integração com SDK/driver da impressora.

## Imagens de frente/verso (recomendações)

A tela de criação/edição de modelo (`/card-templates/create`, `/card-templates/{id}/edit`)
mostra essas recomendações também na interface. Nada disso é validado no backend (só o
tipo/tamanho do arquivo é), são só orientações para o resultado sair nítido e sem corte
indesejado:

- **Orientação:** vertical (retrato) — largura menor que altura, igual ao cartão.
- **Proporção:** 54 : 85,6 (largura : altura, ≈ 0,63:1). A imagem é exibida com
  `object-fit: cover`; fora dessa proporção, o navegador corta as bordas para preencher o
  quadro.
- **Resolução:** ~638 × 1011px (300 DPI), calculado a partir do tamanho físico do cartão.
- **Formato:** PNG ou JPG, até 5 MB por imagem (`StoreCardTemplateRequest`/
  `UpdateCardTemplateRequest`: regra `image`, `max:5120`).

## Regras de negócio

- Só modelos com `is_active = true` aparecem no seletor de emissão; um modelo inativo continua
  editável/visível na listagem de modelos.
- As 6 posições do `layout` são obrigatórias na validação de criação/edição do modelo
  (`StoreCardTemplateRequest`/`UpdateCardTemplateRequest`) — um JSON incompleto é rejeitado
  antes de salvar.
- Trocar só a imagem de um lado (frente **ou** verso) na edição é permitido; a outra mantém a
  anterior.

## Referência técnica

- Rotas: `routes/web.php`, grupo `permission:manage id cards` — ver [`docs/rotas.md`](../rotas.md).
- Controllers: [`CardTemplateController`, `CardIssuerController`](../controllers.md#cardtemplatecontroller).
- Model: [`CardTemplate`](../models.md#carteirinhas).
- Upload de imagem: mesmo padrão de `PlaceGroupController` (`move(public_path('images'), ...)`,
  sem `Storage` facade).
- Captura de webcam: adaptado de `resources/views/companies/workers/partials/form.blade.php`.
