# Espaços e Grupos (Administração)

## O que é

Configuração da estrutura que sustenta os agendamentos: **grupos de espaços** (categorias),
**espaços** individuais e **regras de disponibilidade**. É o "cadastro base" que o sócio
consome ao agendar.

## Para quem

Administradores (painel web, autenticado).

## 1. Grupos de Espaços (PlaceGroup)

Um grupo agrupa espaços semelhantes (ex.: "Quadras de Tênis") e concentra as regras comuns de
horário e reserva.

| Campo | Função |
|-------|--------|
| `name`, `category` | Identificação e tipo: `esportiva` (reserva por horário) ou `social` (dia inteiro, com aprovação — ver [Espaços Sociais](espacos-sociais.md)). Grupo sem categoria é tratado como esportivo; a migration de 14/10/2026 preencheu os vazios. |
| `image_vertical`, `image_horizontal` | Imagens de destaque — arquivo enviado ou link web (ver [Imagens](#imagens-arquivo-ou-link)). |
| `icon`, `vertices` | Ícone e dados de mapa/localização. |
| `duration` | Duração de cada slot (ex.: 01:00). |
| `interval` | Intervalo entre slots. |
| `daily_limit` | Máximo de reservas por sócio, por dia, no grupo. |
| `minimum_antecedence` / `maximum_antecedence` | Antecedência mínima/máxima (em dias). |
| `start_time` / `end_time` | Horário de funcionamento. |
| `start_time_sales` / `end_time_sales` | Janela de vendas para o mesmo dia. |
| `weekdays` | Dias da semana em que o grupo funciona. |
| `social_payment_days` | *(social)* Prazo para pagar após a aprovação, em dias (padrão 7). |
| `social_active_limit` | *(social)* Máximo de reservas futuras ativas por sócio no grupo. |
| `social_limit_count` / `social_limit_days` | *(social)* No máximo N reservas a cada X dias por sócio. |
| `social_rules` | *(social)* Texto de regras mostrado no app e nos e-mails. |

No grupo social, o formulário esconde horário, janela de vendas, duração e limite diário (eles
seguem gravados com o dia inteiro) e lê a antecedência como "entre hoje + mínima e hoje +
máxima; máxima 0 = sem limite".

**Telas:** aba **Reservas › Locais e Grupos** (permissão `reservas.configurar`; o botão
Configurações da agenda leva ao mesmo lugar) — `GET /place-group` (lista), `…/create`, `…/{id}/edit`. O serviço
`PlaceGroupService` salva o grupo e sincroniza os dias da semana (relação N:N com `Weekday`).

## 2. Espaços (Place)

Um espaço é um local específico dentro de um grupo (ex.: "Quadra 1").

| Campo | Função |
|-------|--------|
| `name` | Nome do espaço. |
| `place_group_id` | Grupo ao qual pertence. |
| `price` | Preço por slot/hora. |
| `status_id` | Ativo/inativo. |
| `image` | Foto do espaço — arquivo enviado ou link web (ver [Imagens](#imagens-arquivo-ou-link)). |
| `description`, `amenities` | *(social)* Observações/restrições e itens fornecidos, mostrados no app. |
| `capacity` | *(social)* Total de pessoas (sócios + não sócios). |
| `max_guests` | *(social)* Convidados não sócios incluídos no preço. |
| `extra_guests`, `extra_guest_price` | *(social)* Quantos não sócios a mais podem ser contratados e o preço de cada. |
| Conflitos (`place_conflicts`) | "Não pode ser usado no mesmo dia que": par de espaços, válido nos dois sentidos. Uma reserva ativa em um ocupa o outro (espaço combinado, salão que ocupa quadra). |

Espaço de grupo social não tem contator nem autoatendimento de luz: o controller grava os dois
vazios, porque a reserva do dia inteiro deixaria a luz acesa 24 horas.

**Telas:** criados/editados a partir do grupo — `place-group.createPlace`,
`place-group.storePlace`, `…editPlace`, `…updatePlace`, `…destroyPlace`. Um espaço pode ser
vinculado a regras de agendamento específicas.

> Observação: os espaços são sempre listados em ordem alfabética (scope global do model
> `Place`).

## 3. Regras de Agendamento (ScheduleRules)

Regras ajustam os horários disponíveis de um ou mais espaços.

| Tipo (`type`) | Efeito |
|---------------|--------|
| `include` | **Libera** horários adicionais (ex.: abrir um período extra no fim de semana). |
| `exclude` | **Bloqueia** horários (ex.: manutenção das 12h às 13h). |

Campos: `name`, `type`, `start_date`/`end_date` (vigência), `start_time`/`end_time` (faixa),
`weekdays` (dias) e `places` (espaços afetados), além de `status_id`.

**Telas:** criadas/editadas a partir do grupo — `place-group.createScheduleRule`,
`schedule-rules.store`, `…editScheduleRule`, `…updateScheduleRule`, `…destroyScheduleRule`.

## Como as configurações afetam o sócio

Ao consultar horários, o sócio recebe a grade gerada a partir de:
`slots base (duration)` → filtro de **dias** → **janela de vendas** → **antecedência** →
**inclusões/exclusões** → **colisões**. Veja o detalhe no [guia de Agendamento](agendamento.md).

## Imagens: arquivo ou link

Cada imagem (as duas do grupo e a do espaço) tem duas origens, escolhidas no próprio campo:

- **Enviar arquivo** — JPG, PNG, WEBP ou GIF; vai para `public/images` com nome único.
- **Usar link** — o endereço de uma imagem já hospedada (imgur, postimg, site do clube…). O
  campo guarda a URL e as telas e a API a usam como está, sem baixar nada.

Regras do link:

- Tem de começar com `https://` (o site de locação é https; imagem em http não aparece).
- Precisa ser o link **da imagem**, não da página. Por conveniência, as páginas que as pessoas
  mais copiam são convertidas ao salvar: `imgur.com/<id>` vira `i.imgur.com/<id>.jpg`, e a
  página do postimg (`postimg.cc/<id>`) é lida para achar o link direto (`i.postimg.cc/…`).
  Álbum ou galeria do imgur não servem (não são uma imagem só).
- Qualquer outro endereço precisa terminar em `.jpg`, `.jpeg`, `.png`, `.webp` ou `.gif`.
- Até 255 caracteres. Link recusado volta ao formulário com a mensagem no campo.

Arquivo e link no mesmo envio: vale o arquivo. Sem nenhum dos dois, a imagem atual fica. A
prévia mostra a imagem atual e a nova antes de salvar; se o link não carregar, o campo avisa.

Quem lê usa `App\Support\ImageSource::url()` (link como está, arquivo em `asset('images/…')`).
Na API da iluminação (`/api/lighting/groups/{id}/places`), `image` continua cru e `image_url`
traz a URL pronta.

## Fluxo recomendado de configuração

1. Criar o **grupo** (horários, antecedência, limite diário, dias da semana, imagens por arquivo ou link).
2. Criar os **espaços** do grupo (nome, preço, status, imagem por arquivo ou link).
3. Criar as **regras** de inclusão/exclusão conforme necessário.
4. Validar abrindo a tela de agendamento e conferindo os horários gerados.

## Referência técnica

- Controllers: [`PlaceGroupController`, `PlaceController`, `ScheduleRulesController`](../controllers.md#placegroupcontroller)
- Services: [`PlaceGroupService`, `PlaceService`, `ScheduleRulesService`](../services.md#placegroupservice)
- Models: [`PlaceGroup`, `Place`, `ScheduleRules`, `Weekday`](../models.md#espaços)
- Imagens: `App\Support\ImageSource` (`resolve`, `url`, `normalizeLink`); campo de tela
  `resources/views/partials/image-source.blade.php`.
- Testes: `tests/Feature/Locations/PlaceImageSourceTest.php`.
