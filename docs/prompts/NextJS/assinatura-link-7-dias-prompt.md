# Prompt para o agent do site Next.js: link de assinatura vale 7 dias

**Sistema externo:** NextJS (site das Reservas, área `/assinar`)

Cole o texto abaixo da linha `---` num Claude Code aberto no repositório do site.

---

## O que mudou no Lara (13/11/2026)

O link de assinatura remota agora vale **7 dias a partir do envio**. Antes valia 72 horas. Quando o clube clica em "Enviar novo link", o Lara gera um link novo que vale mais 7 dias a partir desse reenvio, e o link anterior passa a responder `410` com `reason: link_superseded`, como já acontecia.

A API `/api/assinatura/remota/*` não mudou: as rotas, os corpos de requisição, os campos da resposta e os códigos de `reason` são os mesmos. O `expires_at` do link (em `POST /link` e no objeto `link` da sessão) continua vindo em ISO 8601 e passa a ficar até 7 dias à frente.

O que mudou no Lara e não afeta o site: no tablet do balcão, quem não recebe o código de identidade por e-mail pode enviar a foto de um documento de identidade. Isso não chega ao site, porque o link remoto confere o CPF completo, sem código por e-mail.

## O que fazer no site

1. **Cookie do token.** Na primeira carga de `/assinar/[token]`, o cookie httpOnly que guarda o token foi pensado para 3 dias. Faça ele durar até o `expires_at` do link. Como a primeira carga não chama a API (por causa da pré-visualização de link do WhatsApp), grave o cookie com 8 dias. Depois que `POST /link` responder, ajuste o `maxAge` para o `expires_at` recebido.
2. **Textos.** Procure no site qualquer menção fixa a "72 horas", "3 dias" ou "três dias" ligada ao link de assinatura. Troque pela data que vem em `expires_at`, formatada no fuso `America/Sao_Paulo` ("Este link vale até 20/11 às 14:02"). Se o texto precisar de um número, use "7 dias".
3. **Link substituído.** Para `reason: link_superseded`, mostre "Este link foi substituído por um mais recente. Use o último link que você recebeu." Se já existe uma mensagem para esse caso, mantenha.
4. **Testes.** Com a API mockada, teste:
   - o cookie gravado na primeira carga vale 8 dias;
   - depois de `POST /link`, o cookie dura até o `expires_at` recebido;
   - a tela de entrada mostra a data do `expires_at` e não mostra "72 horas" em lugar nenhum;
   - `410 link_superseded` mostra a mensagem acima.

## O que não mudar

- A ordem das etapas (CPF antes do documento), os headers `X-Signer-Ip` e `X-Signer-User-Agent`, a lógica de `pdf_version` e o tratamento dos outros `reason`.
- O site não calcula prazo nenhum. Quem decide se o link vale é o Lara. Use sempre o `expires_at` e o `reason` que vierem.

Referência completa: `docs/funcionalidades/assinatura-remota-prompt-site.md`, no repositório do Lara.
