# Carteirinhas — resumo para quem vai mexer

Documentação completa (esquema do `layout`, recomendações de imagem, impressão):
[docs/funcionalidades/carteirinhas.md](../funcionalidades/carteirinhas.md). Onde ela fala em
`manage id cards`, leia a permissão do catálogo **`carteirinhas`**.

## Em uma frase

Emissão de carteirinha de funcionário em cartão PVC (impressora tipo SD260): o operador escolhe
um **modelo**, digita os dados, tira a foto pela webcam e imprime pelo diálogo do navegador. Duas
abas: **Emitir Carteirinha** (`id-cards.issue`) e **Modelos** (`card-templates.*`), ambas com
permissão `carteirinhas`.

## Regras que costumam pegar

- **Não há cadastro de pessoa.** Nome, função, matrícula, admissão e foto vivem só no navegador;
  não existe rota de `store` nem tabela para eles. O único dado persistido é o `CardTemplate`.
  Não acrescente persistência sem decisão de quem opera.
- `card_templates.layout` é JSON com **6 caixas fixas** (`front.photo`, `front.name`,
  `front.role`, `back.name`, `back.admission_date`, `back.registration_number`), posição e
  tamanho em **% da imagem**; texto com `font_size`, `bold`, `align`, `color`. As seis são
  obrigatórias na validação (`StoreCardTemplateRequest` / `UpdateCardTemplateRequest`).
- O editor de posicionamento é Alpine puro com eventos `pointer*`, sem biblioteca
  (`resources/views/card-templates/partials/form.blade.php`).
- **Impressão = `window.print()`** da própria pré-visualização: `@page` com o tamanho físico do
  modelo (`card_width_mm` × `card_height_mm`, padrão CR-80 retrato 54 × 85,6 mm) e `@media print`
  escondendo o resto. Nenhuma integração com SDK/driver da impressora.
- Webcam com `getUserMedia` + `canvas`, com fallback de arquivo (mesma técnica do cadastro de
  prestador em Externos).
- Imagens de frente/verso em `public/images` (`storeUploadedImage`, `move(public_path(...))`) —
  não use `Storage::disk('public')`: não há `storage:link`. Até 5 MB, PNG/JPG; trocar um lado
  mantém o outro.
- Só modelos `is_active` aparecem na emissão.

## Peças

| Arquivo | Papel |
|---|---|
| `app/Http/Controllers/CardIssuerController.php` | Tela de emissão (só `create`). |
| `app/Http/Controllers/CardTemplateController.php` | CRUD dos modelos e upload das imagens. |
| `app/Models/CardTemplate.php` | Modelo (`layout` em JSON). |
| `resources/views/card-issuer/create.blade.php` | Emissão, pré-visualização e CSS de impressão. |
| `resources/views/card-templates/*` | Lista e editor. |

## Testes

`tests/Feature/Rebrand/CarteirinhasScreensTest.php` (telas no layout novo). Confira o isolamento
SQLite do `phpunit.xml` antes de rodar.
