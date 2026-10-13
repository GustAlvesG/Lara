# InfoClube — resumo para quem vai mexer

Documentação completa (campos, editor, histórico): [docs/funcionalidades/informacoes.md](../funcionalidades/informacoes.md).
A tabela de permissões Spatie (`view information`, `create information`…) que aparece lá é
**antiga**: hoje o acesso é o descrito abaixo.

## Em uma frase

Mural interno do clube com duas abas: **Informações** (catálogo versionado de atividades,
serviços e ofertas — `information.*`) e **Avisos** (comunicados com privacidade, lembretes e
leitura obrigatória — `avisos.*`). Ler é de todo mundo logado; escrever informação exige
`infoclube.editar`.

## Informações

- **Modelo:** `information` é só o agrupador (id, `privacy`, autor, soft delete); o conteúdo
  mora em `data_infos`, **uma linha por versão**. A versão vigente é a de maior `id` de cada
  `information_id` (subconsulta `MAX(id) GROUP BY information_id` em `InformationController::index`).
- **Editar = criar versão.** O formulário de edição posta em `POST /information` (o mesmo
  `store()`) com `information_id` oculto; é a presença dele que transforma criação em edição.
  `PUT /information/{id}` (`update()`) é o **"Tornar versão atual"** do histórico: copia a versão
  antiga para uma linha nova, com as tags daquela época.
- **Parâmetro de rota enganoso:** em `show`, `edit` e `update` o `{information}` é resolvido
  como **`DataInfo`** (id de versão), não `Information`. `destroy` recebe `Information`;
  `history` recebe o `information_id` cru.
- **Campos múltiplos** (preços, responsáveis, dias/horários) são strings separadas por `;`.
  O `store()` troca `;` por `,` dentro de cada valor; a ordem das linhas no formulário é a ordem
  no banco, e o **primeiro item** é o que aparece no card.
- **Tags:** tabela `tags` compartilhada com Avisos, nome normalizado por `Tag::normalize()`,
  vínculo **por versão** (`data_info_tag`). Mínimo de **3 tags** por informação. A coluna antiga
  `category` continua no banco e na busca da API só por causa dos registros antigos.
- **Descrição:** editor próprio `<x-rich-editor>` (sem CDN); todo HTML passa por
  `App\Support\HtmlSanitizer::clean()` (allow-list; em `style` só `background-color`).
- **Imagem:** gravada em `public/images` com `hashName()` — **não** use `Storage::disk('public')`
  (não há `storage:link` neste projeto). Sem arquivo novo, a versão herda a imagem da anterior.
- **Permissão:** a leitura (`index`, `show`, `history`) não tem `can:`. Criar/editar/excluir
  estão no grupo `can:infoclube.editar`, e o `store()` confere de novo (`abort_unless`).
- `privacy` existe mas não é usado: tudo é público.
- **API:** `GET /api/information/search?q=` (`InformationSearchController`, grupo `api_token`)
  ranqueia por peso (nome 3, tag/categoria 2, descrição 1), só na versão vigente.

## Avisos

- `Aviso` com privacidade `publico`, `setor` (quem divide setor com o criador), `pessoa`
  (só o criador) ou `grupo` (usuários escolhidos, `aviso_user`). A régua é
  `Aviso::scopeVisibleTo()`; a notificação usa a mesma (`AvisoController::notifyUsers`).
- `expires_at` separa ativos de expirados; `Lembrete` agenda notificações.
  `avisos:process-notifications` (a cada minuto) envia lembretes vencidos e o aviso de expiração
  do dia (`expiry_notified`). Notificações: `AvisoCreated`, `AvisoReminder`, `AvisoExpiring`
  (sino do layout, `notifications.*`).
- `AvisoView` registra cada abertura; a tela mostra quem leu.
- **Leitura obrigatória:** só coordenador de setor (`User::isCoordinator()`) marca `mandatory`;
  de outra pessoa o campo é ignorado. O middleware `avisos_obrigatorios`
  (`EnsureMandatoryAvisosAcknowledged`), aplicado a **todo o painel**, desvia para
  `avisos.pending` até a pessoa dar ciência (`AvisoAcknowledgement`, com IP). Se a consulta
  falhar, a navegação segue e o erro vai ao log — o middleware não pode derrubar o sistema.
- Imagem do aviso em `public/images/avisos`.

## Peças

| Arquivo | Papel |
|---|---|
| `app/Http/Controllers/InformationController.php` | Lista paginada (12) com busca no servidor, criação/versão, histórico. |
| `app/Http/Controllers/InformationSearchController.php` | Busca ranqueada da API. |
| `app/Http/Controllers/AvisoController.php` | CRUD de avisos, ciência, sincronia de tags/lembretes/usuários. |
| `app/Models/Information.php`, `DataInfo.php`, `Tag.php` | Agrupador, versões e tags. |
| `app/Models/Aviso.php`, `Lembrete.php`, `AvisoView.php`, `AvisoAcknowledgement.php` | Avisos e escopos de visibilidade. |
| `app/Http/Middleware/EnsureMandatoryAvisosAcknowledged.php` | Desvio para a leitura obrigatória. |
| `app/Console/Commands/ProcessAvisoNotifications.php` | Lembretes e expiração (agendado). |
| `resources/views/information/*`, `resources/views/avisos/*` | Telas (formulário em `information/partials/form.blade.php`). |

## Testes

- `tests/Feature/Rebrand/InfoClubeScreensTest.php` (telas no layout novo),
  `tests/Feature/InformationSearchTest.php` (API), `tests/Feature/MandatoryAvisoReadingTest.php`
  e `tests/Unit/MandatoryAvisoInterceptionTest.php` (leitura obrigatória).
- Confira o isolamento SQLite do `phpunit.xml` antes de rodar e rode só os arquivos do módulo.
