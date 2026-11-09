# Disparo de E-mails

## O que é

Campanhas de e-mail para listas grandes, montadas e disparadas pelo menu **Disparo de E-mails**
(desde 29/10/2026):

1. a pessoa cria a campanha (nome, assunto, remetente) e **escreve o texto no editor visual**, sem saber HTML; O Lara monta o e-mail (quem tem o código pronto usa o modo HTML);
2. importa a **lista de destinatários** em `.csv` ou `.xlsx`; cada coluna vira uma variável do
   texto (`{{nome}}`, `{{titulo}}`…);
3. manda um **e-mail de teste** e clica em **Enviar** (agora ou agendado).

O envio **não depende da tela aberta**. A tela só muda a situação da campanha; quem envia é o
comando `email-campaigns:send`, que o scheduler roda a cada minuto, no servidor. Pode fechar o
navegador, desligar o computador: a campanha continua, até o último destinatário.

## Para quem

Permissão do catálogo **`disparo-emails`** (grupo "Disparo de E-mails": *Criar campanhas,
importar listas de destinatários, enviar e manter o "Não enviar para"*). Nasce sem setor: dê na
tela de Setores a quem faz as campanhas. Os setores de acesso total (Gerência, Diretoria, TI) já
a alcançam.

Montar a lista com os sócios do MultiClubes pede, além dela, **`disparo-emails.multiclubes`**
(*Importar a lista de sócios do MultiClubes…*, desde 09/11/2026). É separada porque expõe os
e-mails de todo o quadro social. Também nasce sem setor.

## Telas

| Tela | Rota | O que faz |
|---|---|---|
| Campanhas | `GET /disparo-emails` (`email-campaigns.index`) | Lista com situação e "enviados / total". Busca por nome ou assunto, filtro de situação. |
| Nova / Editar | `email-campaigns.create`, `email-campaigns.edit` | Dados do envio e o texto, no editor visual ou em HTML, com prévia ao lado. Editar só em rascunho ou pausada. |
| Campanha | `GET /disparo-emails/{id}` (`email-campaigns.show`) | Andamento (atualiza sozinho a cada 5 s), Enviar/Agendar, e-mail de teste, Pausar/Retomar/Cancelar, Reenviar falhas, Duplicar, importar a lista, lista de destinatários com a situação de cada um, baixar o resultado em CSV e a prévia. |
| Não enviar para | `GET /disparo-emails/nao-enviar` (`email-suppressions.index`) | Quem pediu para sair. Nenhuma campanha manda para esses endereços, nem as que já estão enviando. |

## Situações da campanha

```
draft ──► scheduled ──► sending ──► done
  ▲          │            │ ▲
  └──────────┘            ▼ │
                         paused        (scheduled/sending/paused ► canceled)
```

| Situação | Rótulo | O que pode |
|---|---|---|
| `draft` | Rascunho | Editar tudo, importar/substituir/apagar a lista, testar, Enviar ou Agendar, excluir. |
| `scheduled` | Agendada | Desfazer agendamento (volta a rascunho) ou cancelar. Na hora marcada, o comando a passa a `sending`. |
| `sending` | Enviando | Pausar, cancelar, reenviar as que falharam. Não edita. |
| `paused` | Pausada | Editar texto e remetente, **acrescentar** à lista (não substituir), retomar, cancelar. |
| `done` | Concluída | Reenviar as que falharam (volta a `sending`), duplicar, excluir. |
| `canceled` | Cancelada | Duplicar, excluir. Quem estava na fila não recebe. |

Pausada não substitui nem apaga a lista: parte dela já recebeu, e trocar a lista apagaria esse
histórico e mandaria de novo para quem voltasse no arquivo novo.

## A lista de destinatários

`RecipientListReader` + `RecipientImporter`.

- **Formatos**: `.csv` (separador `;`, `,`, tab ou barra vertical, detectado pela primeira linha;
  UTF-8 com ou sem BOM, ou Windows-1252 — o CSV do Excel em português) e `.xlsx`/`.xls` (primeira
  aba). Até 20 MB. A validação é pela **extensão**: CSV de outros sistemas chega com tipo MIME
  variado, e o arquivo só é lido, nunca guardado.
- **Coluna de e-mail** obrigatória, reconhecida por vários nomes (E-mail, Email, Endereço de
  e-mail, Mail… ou qualquer cabeçalho com "email"). **Sem cabeçalho** também vale: se a primeira
  linha já tem um e-mail, a lista é lida sem cabeçalho (a coluna do e-mail vira `email` e as
  outras `coluna_2`, `coluna_3`…).
- **Coluna de nome** opcional (Nome, Name, Nome completo, Sócio, Destinatário…).
- **As outras colunas** viram variáveis pelo slug do cabeçalho: "Título" → `{{titulo}}`, "Nome do
  Evento" → `{{nome_do_evento}}`. Ficam em `email_campaign_recipients.data`.
- **Não é tudo-ou-nada** (diferente das importações de cadastro): a linha sem e-mail ou com e-mail
  inválido (inclusive sem ponto no domínio) fica de fora e volta na tela, com as 20 primeiras;
  repetido (na planilha ou já na lista) é ignorado e contado; o que passar de
  `EMAIL_CAMPAIGNS_MAX_RECIPIENTS` fica de fora e é avisado.
- Célula com **vários e-mails** (`a@x.com; b@y.com`) vira um destinatário para cada, com o mesmo
  nome e as mesmas colunas.
- E-mails gravados **em minúsculas**; único por campanha (índice `ecr_campaign_email_unique`).
- **Acrescentar** ou **Substituir** a lista atual (substituir só em rascunho). Modelo para baixar
  em `.csv` e `.xlsx` (`email-campaigns.list-template`).

## Lista a partir dos sócios do MultiClubes

Desde 09/11/2026. Na tela da campanha, bloco **Importar sócios do MultiClubes** (só com
`disparo-emails.multiclubes` e campanha em rascunho ou pausada). A lista do MultiClubes passa pelo
**mesmo `RecipientImporter::importList()`** da planilha: e-mail inválido volta como recusa, repetido é
ignorado, vale o teto por campanha, o acrescentar/substituir e o "Não enviar para" no envio.

**Somente leitura** no MultiClubes (`mc_sqlsrv`): só `SELECT`, parâmetros ligados. A tela da campanha
**não consulta o MultiClubes ao abrir**: os tipos de título e a contagem vêm por rotas JSON sob demanda;
fora do ar, essas rotas dão 503 com aviso e a importação volta com erro — a planilha continua valendo.

### Filtros (`App\Services\MultiClubes\MemberMailingFilter`, validados por `MemberMailingRequest`)

| Campo | Coluna | Valores | Vazio |
|---|---|---|---|
| `tipos[]` | `Titles.TitleType` | ids de `dbo.TitleTypes` | todos, **menos** `TitleMemberLookup::EXCLUDED_TITLE_TYPES` (funcionários, escolinha não sócio, terceirizados…); os especiais só entram marcados |
| `situacao_titulo[]` | `Titles.Status` | 0 Ativo · 1 Bloqueado · 2 Cancelado · 3 Transferido · 4 Vencido | qualquer (a tela abre com Ativo) |
| `situacao_socio[]` | `Members.Status` | 0 Ativo · 1 Desativado · 2 Excluído · 3 Vencido | qualquer (a tela abre com Ativo) |
| `vinculo` | `Members.Titular` | `todos` · `titular` (1) · `dependente` (0) | obrigatório |
| `sexo` | `Members.Sex` | 0 Masculino · 1 Feminino | os dois |
| `idade_min`, `idade_max` | `Members.BirthDate` | 0–130, anos completos hoje | sem limite |

Os códigos não têm tabela de domínio no MultiClubes; foram conferidos com `GROUP BY` na base em
09/11/2026 (título 0–3, sócio 0–2, sexo 0/1 em uso). **A idade vira faixa de nascimento**:
"N ou mais" ⇔ `BirthDate < (hoje − N anos) + 1 dia`; "até M" ⇔ `BirthDate >= (hoje − (M+1) anos) + 1 dia`
(`subYearsNoOverflow`). Dá a mesma conta do `dbo.GetYearDiff`, sem função por linha. Sem data de
nascimento, quem filtra por idade fica de fora. Há um clube só em `dbo.Clubs`; não há filtro de clube.

### A lista (`MemberMailingDirectory` → `MultiClubesRecipientSource`)

- **E-mail**: `dbo.Members.Email` (o mesmo de torneios e ingressos). Sócio **sem e-mail não vira recusa**
  (seriam milhares): é contado e avisado ("N sócio(s) sem e-mail no MultiClubes ficaram de fora").
- **Ordem**: título, titular primeiro. Titular e dependente com o **mesmo e-mail** → fica o primeiro (o
  titular) e o outro conta como repetido.
- **Nome**: social quando houver (`COALESCE(NULLIF(SocialName, ''), Name)`), e o nome em maiúsculas vira
  nome próprio ("JOÃO DA SILVA" → "João da Silva"; `MultiClubesRecipientSource::properName`), porque vai
  na saudação. Nome já misto fica como está.
- **Colunas/variáveis**: `titulo` (`Titles.Code`), `tipo_titulo`, `vinculo` (Titular/Dependente),
  `parentesco` (`Parentages.Description`; "Titular" para o titular), `idade` (anos completos na
  importação) e `sexo`. Recusa de e-mail inválido aparece como "Título 001234 (Nome): …".
- **Nome da lista** (`list_name`): o resumo dos filtros (`MemberMailingFilter::describe`), ex.
  "MultiClubes: EFETIVO; título ativo; sócio ativo; só titulares; 18 anos ou mais".
- A lista é uma **foto** na hora da importação; não sincroniza depois.

Números de referência (09/11/2026, filtro padrão): 10.255 sócios, 6.513 com e-mail, 5.856 e-mails
diferentes; a consulta completa leva ~0,6 s.

## O conteúdo

Dois modos (`email_campaigns.editor`), em abas na mesma tela, com prévia ao vivo (iframe sem
script), largura de computador ou celular e botão Expandir. **O envio só conhece `html`**: o modo
decide de onde ele vem.

### Editor visual (`visual`, o padrão da campanha nova)

Para quem não sabe HTML. Um `contenteditable` com barra de formatação (Texto, Título, Subtítulo,
negrito, itálico, sublinhado, listas, alinhamento, Link, Botão, Imagem, Divisória, Limpar
formatação). Texto colado entra **sempre como texto puro** (`pastePlain`), com os parágrafos
separados: a formatação do Word quebra o e-mail.

`App\Services\EmailCampaign\EmailComposer` faz a conversão, em duas etapas:

- **`sanitize()`**: o que fica em `email_campaigns.body` e reabre no editor. Lista de tags permitidas
  (`p`, `br`, `h2`, `h3`, `strong`, `em`, `u`, `a`, `ul`, `ol`, `li`, `img`, `hr`), sem estilo; só
  sobrevivem o alinhamento, `href` (`http`, `https`, `mailto`, `tel` ou variável), `src`/`alt` (`http`,
  `https`) e a classe `email-button` (o link que vira botão). Script e afins somem com o conteúdo; o
  resto é desembrulhado, ficando o texto.
- **`compose()`**: o e-mail de verdade. Põe o estilo dentro de cada tag e encaixa em
  `resources/email-campaigns/layout.html` (faixa com o nome do remetente, rodapé com `{{email}}` e o
  aviso de descadastro).

Ao salvar, o controller grava `body` limpo e `html` composto. A prévia vem do mesmo `compose()`, pela
rota `email-campaigns.compose`: **a prévia é o e-mail que vai sair**. O Enviar confere o conteúdo pelo
`body`, porque o `html` sempre tem o layout.

### HTML (`html`, avançado)

Editor de código, com **Modelo inicial** (`resources/email-campaigns/modelo-inicial.html`),
**Título**, **Parágrafo**, **Botão**, **Divisória**, **Imagem** e **Abrir arquivo .html**.

**Passar para HTML** leva o e-mail composto para o código. O caminho de volta **não converte**: retoma
o texto do editor visual, e a tela avisa que as mudanças no código se perdem. No modo HTML, o `body`
fica como estava. As campanhas anteriores ao editor visual ficam em `html` (padrão da coluna).

### Regras dos dois modos

- **Variáveis**: `{{coluna}}` no assunto e no HTML. Sempre existem `{{email}}`, `{{nome}}` e
  `{{primeiro_nome}}`. Valor que o destinatário não tem sai vazio — nunca sai `{{nome}}` escrito.
  No HTML o valor é **escapado** (a lista vem de fora). Variável usada no texto que a lista não
  tem **bloqueia o Enviar** (`CampaignRenderer::unknownVariables`): sairia vazia para todos.
- **Script** é retirado no envio e na prévia; a prévia ainda roda num iframe `sandbox` sem
  `allow-scripts`, e a rota `previa` manda `Content-Security-Policy: script-src 'none'`.
- **Imagens**: o botão Imagem sobe o arquivo (PNG, JPG, GIF, WEBP; até
  `email_campaigns.image_max_kb`, 1 MB) para `storage/app/email-campaigns/images/{uuid}.ext` e
  insere o `<img>` com a URL da rota `email-campaigns.images.show` (só logado, para a prévia). **No
  envio a imagem vai embutida no e-mail** (`cid:`): O Lara não é alcançável da internet, e um link
  para cá chegaria quebrado ao destinatário. Imagem com endereço público (`https://…` de outro
  site) fica como link. As imagens não pertencem a uma campanha: duplicar e colar o HTML noutra
  continua funcionando.
- **Versão em texto** gerada do HTML (`CampaignRenderer::text`): sem ela o e-mail ganha ponto de
  spam. Links viram "texto (url)".
- Limite de 1 MB de HTML (imagem em base64 colada no HTML estoura: use o botão Imagem).

## Remetente

- **E-mail do remetente**: só os endereços de `EMAIL_CAMPAIGNS_FROM_ADDRESSES` (vazio = o
  `MAIL_FROM_ADDRESS`). Ponha ali só endereço de **domínio verificado no provedor SMTP**
  (SPF/DKIM); outro endereço cai no spam ou é recusado. A campanha que já tem um remetente que
  saiu da configuração continua aceitando-o ao editar.
- **Nome do remetente** e **Responder para** livres.
- Cabeçalho **`List-Unsubscribe: <mailto:{responder para}?subject=Descadastrar>`**: é o
  "cancelar inscrição" do Gmail e do Outlook. O pedido chega por e-mail a quem cuida das
  campanhas, que lança o endereço em **Não enviar para**. Não há link de descadastro clicável
  porque O Lara não tem página pública.

## O envio (`email-campaigns:send`)

`routes/console.php`: a cada minuto, `withoutOverlapping(10)` e `runInBackground()`. Chama
`CampaignSender::run()`:

1. agendadas com `scheduled_at` vencido passam a `sending`;
2. percorre as campanhas `sending` por ordem de início e manda os `pending` em lotes de 50, até
   **`EMAIL_CAMPAIGNS_PER_MINUTE`** e-mails na rodada (somando todas as campanhas) ou ~50 s;
3. antes de cada lote **relê a campanha**: pausar ou cancelar na tela vale em segundos;
4. quem está em **Não enviar para** na hora vira `skipped` (não conta no limite);
5. sem `pending`, a campanha vira `done`.

**Por que o scheduler e não a fila?** O worker de produção é um só (`lara-queue`, fila
`default`) e atende o bot do WhatsApp, avisos e importações. Milhares de jobs de e-mail ali
atrasariam o atendimento; um worker dedicado exigiria mudar o Supervisor nos dois deploys. O
cron do `schedule:run` já é instalado por `deploy_prod.sh` e `deploy_hml.sh`, e o
`withoutOverlapping` garante uma rodada por vez — o mesmo e-mail nunca sai duas vezes.

**Ritmo**: 120 por minuto (padrão) = 7.200 por hora; uma lista de 20 mil leva ~3 h. Suba
`EMAIL_CAMPAIGNS_PER_MINUTE` só até o que o plano do provedor SMTP aceita (o SMTP2GO recusa e
pode suspender a conta acima do limite). A tela mostra a estimativa.

### Falhas

| Falha | Exemplo | O que acontece |
|---|---|---|
| Recusa definitiva (SMTP 5xx) | `550 Mailbox does not exist` | O destinatário vira **Falhou** na hora, com a mensagem do servidor. |
| Recusa temporária (4xx) ou erro do e-mail | `452 Mailbox full` | Conta tentativa; tenta de novo na rodada seguinte; na `max_attempts`ª (3) vira **Falhou**. |
| Servidor SMTP fora (sem resposta) | `Connection could not be established` | **Não** conta tentativa do destinatário; a rodada para e a campanha mostra "Servidor de e-mail indisponível…" em `last_error`. A rodada seguinte tenta de novo; quando volta a enviar, o aviso some. |
| Recusas seguidas | 5 (`max_consecutive_failures`) em sequência | A campanha é **pausada automaticamente** com o motivo: costuma ser o remetente (domínio não verificado, conta suspensa), não a lista. Corrija e clique em Retomar. |

**Reenviar as que falharam** devolve os `failed` à fila (tentativas zeradas) e, na campanha
concluída, volta a enviar. Caixa inexistente falha de novo — serve para depois de corrigir um
problema geral.

## E-mail de teste

Sai **na hora** (fora do scheduler), com `[TESTE]` no assunto e os dados do **primeiro
destinatário** da lista (ou de um exemplo, sem lista). Não muda a situação de ninguém. O erro do
servidor volta na tela.

## Resultado

**Baixar resultado (.csv)**: e-mail, nome, situação, tentativas, enviado em, erro e as colunas da
lista, separado por `;` e com BOM (abre direto no Excel).

## Configuração (`config/email_campaigns.php`)

| Chave | `.env` | Padrão |
|---|---|---|
| `per_minute` | `EMAIL_CAMPAIGNS_PER_MINUTE` | 120 |
| `max_recipients` | `EMAIL_CAMPAIGNS_MAX_RECIPIENTS` | 50000 |
| `from_addresses` | `EMAIL_CAMPAIGNS_FROM_ADDRESSES` (vírgula) | `MAIL_FROM_ADDRESS` |
| `mailer` | `EMAIL_CAMPAIGNS_MAILER` | o padrão (`MAIL_MAILER`) |
| `max_attempts` | — | 3 |
| `max_consecutive_failures` | — | 5 |
| `image_max_kb` | — | 1024 |

`EMAIL_CAMPAIGNS_MAILER` aponta um mailer de `config/mail.php`; serve para mandar as campanhas
por outra conta SMTP sem mexer nos e-mails do sistema (crie o mailer lá antes).

**Ambiente local**: sem cron, rode `php artisan schedule:work` (ou `php artisan
email-campaigns:send` à mão) e use `MAIL_MAILER=log` para não mandar de verdade.

## Banco

| Tabela | O que guarda |
|---|---|
| `email_campaigns` | O e-mail (assunto, remetente, `editor`, `body` do editor visual, `html` que é enviado), situação, datas, `columns` (slug => rótulo da última lista), `list_name`, `last_error`, `created_by` (sem FK). |
| `email_campaign_recipients` | Um por destinatário: `email`, `name`, `data` (colunas), `status` (`pending`, `sent`, `failed`, `skipped`), `attempts`, `error`, `sent_at`. Único por campanha + e-mail. Apaga junto com a campanha. |
| `email_suppressions` | "Não enviar para": `email` único em minúsculas, `reason`, `created_by`. |

Migrations: `2026_10_30_110000_create_email_campaign_tables.php` e `2026_10_30_120000_add_visual_editor_to_email_campaigns.php` (idempotentes).

## Integrações

- Servidor SMTP (`config/mail.php`).
- **MultiClubes** (SQL Server, `mc_sqlsrv`), **somente leitura**, para a lista de sócios (seção acima).

Nenhuma aplicação externa consome API deste módulo, e o MultiClubes é lido direto no banco: não há
prompt para outro agent.

## Referência técnica

- Rotas: [rotas.md](../rotas.md#disparo-de-e-mails-email-campaigns-email-suppressions-candisparo-emails)
- Controllers: `EmailCampaign\EmailCampaignController`, `EmailCampaign\EmailSuppressionController`,
  `EmailCampaign\MultiClubesRecipientController` (lista do MultiClubes) · Request `MemberMailingRequest`
- MultiClubes (`app/Services/MultiClubes/`): `MemberMailingFilter`, `MemberMailingDirectory`; e
  `EmailCampaign\MultiClubesRecipientSource`
- Services (`app/Services/EmailCampaign/`): `RecipientListReader`, `RecipientImporter`,
  `EmailComposer`, `CampaignRenderer`, `CampaignSender`, `EmailCampaignService`, `CampaignImageStore`
- Models (`app/Models/EmailCampaign/`): `EmailCampaign`, `EmailCampaignRecipient`,
  `EmailSuppression`
- Mail: `App\Mail\EmailCampaignMail` · Comando: `email-campaigns:send` (`SendEmailCampaigns`)
- Testes: `tests/Feature/EmailCampaigns/EmailCampaignPanelTest.php`, `EmailComposerTest.php`,
  `EmailCampaignSenderTest.php` (transporte SMTP de mentira registrado como mailer),
  `EmailCampaignMultiClubesTest.php` (MultiClubes de mentira) e
  `tests/Unit/MultiClubes/MemberMailingFilterTest.php` (SQL e faixa de idade, sem banco)
- Guia do usuário: [docs/guias/disparo-emails.md](../guias/disparo-emails.md)
