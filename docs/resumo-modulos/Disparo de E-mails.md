# Disparo de E-mails — resumo para quem vai mexer

Documentação completa: [docs/funcionalidades/disparo-emails.md](../funcionalidades/disparo-emails.md).

## Em uma frase

Campanhas de e-mail (`/disparo-emails`, rotas `email-campaigns.*` e `email-suppressions.*`,
permissão `disparo-emails`): lista importada de `.csv`/`.xlsx`, HTML com editor e prévia,
variáveis por coluna, teste, envio agora ou agendado — **mandado pelo scheduler**
(`email-campaigns:send`, a cada minuto), nunca pela tela nem pela fila.

## Regras que costumam pegar

- **A tela não envia.** Os botões só mudam `email_campaigns.status`; o `CampaignSender::run()`
  obedece na rodada seguinte e relê a situação a cada lote de 50. Nada de `Mail::queue` nem Job
  aqui: o worker de produção é um só e atende o bot do WhatsApp. Se precisar mudar o motor, mude
  `CampaignSender` e mantenha o `withoutOverlapping` do agendamento (é ele que impede e-mail
  duplicado).
- **Teto por minuto** (`EMAIL_CAMPAIGNS_PER_MINUTE`) soma todas as campanhas. Quem está em
  "Não enviar para" vira `skipped` e não conta.
- **Três falhas diferentes** (`CampaignSender::failure`): `UnexpectedResponseException` 5xx →
  `failed` na hora; 4xx/outros → tentativa (até `max_attempts`); `TransportException` sem resposta
  → não conta tentativa, encerra a rodada e grava `last_error`. 5xx/4xx seguidos
  (`max_consecutive_failures`) **pausam** a campanha (`last_error` "Pausada automaticamente").
- **Pausada só acrescenta à lista** (`RecipientImporter` recusa `replace`; `clearRecipients` só
  em rascunho): parte já recebeu.
- **Variáveis** `{{slug}}` (regex em `CampaignRenderer::VARIABLE_PATTERN`): `email`, `nome`,
  `primeiro_nome` + as colunas da lista (`campaign.columns`, slug via `XlsxReader::slug`). Valor
  escapado no HTML; faltando, vazio. Variável desconhecida **bloqueia o start**.
- **Imagens do editor** ficam em `storage/app/email-campaigns/images` e são servidas por rota
  autenticada só para a prévia; no envio o `CampaignRenderer::embedImages` troca a URL (com ou
  sem domínio) por `cid:` e o `EmailCampaignMail::build()` embute. O Lara não é público — não
  troque por link.
- **Blade × `{{`**: o editor e a tela mostram variáveis com `&#123;&#123;` e o JS monta as chaves
  com `String.fromCharCode`/`\x7B`. Chave dupla literal num `.blade.php` vira echo do Blade e quebra
  a view (ParseError). O modelo inicial mora fora do Blade, em
  `resources/email-campaigns/modelo-inicial.html`.
- **Lista não é tudo-ou-nada** (≠ `SpreadsheetImport`): o inválido fica de fora e volta na sessão
  (`import_rejected`). Upload validado por `extensions:` (não `mimes:`), porque CSV real chega com
  MIME variado.
- `created_by` sem FK: `users` está na conexão `mysql`.

## Configuração

`config/email_campaigns.php`: `per_minute`, `max_recipients`, `from_addresses` (só domínio
verificado no SMTP), `mailer` (outro mailer de `config/mail.php`), `max_attempts`,
`max_consecutive_failures`, `image_max_kb`. `.env.example` documenta as quatro primeiras.

## Integrações

Só o SMTP. Nenhum sistema externo consome API deste módulo — não há prompt para outro agent.

## Testes

`tests/Feature/EmailCampaigns/` com o trait `CreatesEmailCampaignSchema` (acesso + layout + a
migration real do módulo, `SharesSqliteWithUserConnection`). O `EmailCampaignSenderTest` registra
um transporte Symfony de mentira (`Mail::extend`) como mailer da campanha: simula 5xx, 4xx e
servidor fora, e confere o e-mail como sai (cabeçalhos, `cid:`). Rode com
`php -d memory_limit=1G vendor/bin/phpunit tests/Feature/EmailCampaigns`.
