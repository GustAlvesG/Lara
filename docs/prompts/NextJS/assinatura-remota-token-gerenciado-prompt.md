# Prompt para o agent do site Next.js: token gerenciado da assinatura remota

**Sistema externo:** NextJS (área `/assinar`: assinatura remota por link do e-mail)

## Contexto

O site chama O Lara em `/api/assinatura/remota/*` com um token **próprio da assinatura**, separado
do token geral do site (reservas, ingressos, torneios…). Até 18/11/2026 esse token era um valor
fixo combinado com a TI (`SIGNATURE_REMOTE_API_TOKEN` no Lara). Desde então ele é gerado na tela
Administração › Integrações do Lara, como os outros: **um por instalação** (produção e homologação
têm tokens diferentes), com validade e último uso registrado, e com o escopo
**`assinatura-remota`**.

O site passa a ter **dois tokens do Lara**, em duas variáveis diferentes:

| Variável (nome provável) | Escopo | Usada em |
|---|---|---|
| `LARA_API_TOKEN` | `site` | Todo o resto (login, reservas, ingressos, torneios…) |
| `LARA_SIGNATURE_API_TOKEN` | `assinatura-remota` | Só `/api/assinatura/remota/*` |

Os dois têm o formato `lara_` + 48 caracteres e **não são intercambiáveis**: o token `site` recebe
**403** na assinatura, e o da assinatura recebe 403 no resto.

O token antigo da assinatura continua aceito por um tempo. O Lara registra cada uso dele, e a TI
desliga quando o site parar de usá-lo.

## O que muda no site

**Só o valor do token da assinatura.** Header, URLs, corpo e respostas continuam iguais.

1. Ache a variável que o site manda no `Authorization: Bearer` das chamadas a
   `/api/assinatura/remota/*` (pela documentação, `LARA_SIGNATURE_API_TOKEN`, sem `NEXT_PUBLIC_`).
2. **Confira se ela é mesmo uma variável separada** da usada no resto do site. Se o código usa a
   mesma variável (ou o mesmo cliente HTTP com o mesmo token) para a assinatura e para o resto,
   separe: o cliente da assinatura lê só `LARA_SIGNATURE_API_TOKEN`.
3. Troque o valor pelo token de escopo `assinatura-remota` que a TI vai passar para cada ambiente.
   Não copie o de produção na homologação, nem o contrário.
4. Não mexa no `LARA_API_TOKEN` (token `site`).
5. O token nunca vai para o navegador nem para log (nem mascarado pela metade).

## Respostas de erro

Continuam no formato que o site já trata: `{ "error": "...", "reason": "unauthorized" }`.

| HTTP | Quando |
|---|---|
| 401 | Token ausente, errado, revogado, vencido, de integração desativada, ou o antigo depois de desligado |
| 403 | Token sem o escopo `assinatura-remota` (provavelmente o token `site` na variável errada) ou IP fora da lista |

Para quem assina, siga mostrando a mensagem genérica ("O site não conseguiu se comunicar com o
clube…"). No **log do servidor** do Next, registre o status HTTP e o `reason` que O Lara devolveu
(sem o token), para a TI distinguir 401 de 403. Hoje um 502 no console do navegador não diz qual
dos dois foi.

## Como conferir

- Em homologação, abra um link de assinatura recebido por e-mail e vá até a tela do documento
  (chama `link`, `abrir` e `sessao`).
- Peça à TI para conferir em Administração › Integrações: o token da assinatura de homologação
  precisa mostrar **Último uso** recente numa rota `api.assinatura-remota.*`, e o IP do servidor do
  site não pode mais aparecer em "Chamadas sem token da tela" com o escopo "Assinatura remota (site)"
  e o tipo "token antigo".
