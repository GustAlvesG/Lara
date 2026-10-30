# Prompt para o agent do site Next.js: link de assinatura também pelo WhatsApp

**Sistema externo:** NextJS (site das Reservas, área `/assinar`)

## O que mudou no Lara

Depois de enviar o link de assinatura remota por e-mail, o atendente agora pode copiá-lo e encaminhá-lo por outro canal, como o WhatsApp. O endereço é o mesmo: `https://<este site>/assinar/<token>`.

A API `/api/assinatura/remota/*` não mudou: rotas, corpo e códigos de `reason` são os mesmos. Só mudaram dois textos do campo `error`:

| `reason` | Texto novo |
|---|---|
| `invalid_link` | "Link de assinatura inválido. Confira se abriu o link inteiro." |
| `link_superseded` | "Este link foi substituído por um mais recente. Use o último link que você recebeu." |

## O que conferir no site

1. **Pré-visualização de link.** O WhatsApp pode acessar o endereço sozinho para montar a prévia, e a pessoa pode abrir o link dentro do navegador do próprio aplicativo. Confirme que carregar `/assinar/[token]` ou `/assinar` não chama `POST /api/assinatura/remota/abrir` sem um clique da pessoa. `abrir` conta uma abertura no painel do atendente e derruba a sessão anterior. Se a primeira carga chama `abrir`, passe a chamada para o botão de começar.
2. **Prévia sem dados.** A resposta da primeira carga é só o redirecionamento, sem título do documento nem nome de quem assina nas meta tags (`og:title`, `og:description`). O token não pode aparecer em meta tag.
3. **Textos.** Procure na área `/assinar` frases que digam que o link veio por e-mail ("o link do e-mail", "verifique seu e-mail") e troque por texto neutro. Se o site tiver texto próprio para `invalid_link` ou `link_superseded`, alinhe com a tabela acima.
4. **Câmera no navegador do WhatsApp.** A foto é obrigatória. Teste em Android e iPhone abrindo o link pelo WhatsApp. Se a câmera não funcionar no navegador interno, oriente a pessoa a abrir no navegador do celular.

## O que não mudar

- Guardar o token em cookie httpOnly e redirecionar para `/assinar`.
- `Referrer-Policy: no-referrer`, `Cache-Control: no-store` e `X-Robots-Tag`.

Referência completa: `docs/funcionalidades/assinatura-remota-prompt-site.md`, no repositório do Lara.
