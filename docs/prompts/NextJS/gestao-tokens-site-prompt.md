# Prompt para o agent do site Next.js: token próprio da API do Lara

**Sistema externo:** NextJS (site de locação: reservas, espaços sociais, torneios, ingressos, iluminação, replay, dashboards e assinatura remota)

## Contexto

Até 17/11/2026 todo sistema que chama O Lara usava o mesmo token (`API_TOKEN`), mandado em `Authorization: Bearer <token>`. Desde então O Lara gera **um token por instalação** (produção e homologação têm tokens diferentes), com escopo e validade, na tela Administração › Integrações. O token do site tem o escopo **`site`**.

O token antigo continua aceito por um tempo, mas vai ser desligado. O Lara registra cada chamada feita com ele (IP e rota), e a TI só desliga quando o site parar de usá-lo.

## O que muda no site

**Só o valor do token.** Header, URLs, corpo e respostas continuam iguais.

1. Ache onde o site guarda o token que manda ao Lara no `Authorization: Bearer` (provavelmente uma variável de ambiente do servidor, sem `NEXT_PUBLIC_`, como `LARA_API_TOKEN`).
2. Troque o valor pelo token que a TI vai passar para cada ambiente. O token tem o formato `lara_` seguido de 48 caracteres. Produção e homologação recebem tokens diferentes: não copie um no outro.
3. **Não** troque o token da assinatura remota (`LARA_SIGNATURE_API_TOKEN` ou equivalente, usado em `/api/assinatura/remota/*`). Ele continua separado e não mudou.
4. O token nunca vai para o navegador: só o servidor do Next chama O Lara. Se encontrar alguma chamada ao Lara saindo do cliente com o token, corrija junto (passe pelo servidor do Next).

## Respostas novas que o site pode receber

| HTTP | `message` | Quando | O que fazer |
|---|---|---|---|
| 401 | `Invalid API Token` | Token errado, ausente ou o antigo depois de desligado | Igual a hoje: erro de configuração, registre no log do servidor (sem o token) |
| 401 | `Token revogado, vencido ou de integração desativada` | O token venceu ou a TI revogou | Registre no log do servidor; a TI precisa gerar outro |
| 403 | `Este token não tem acesso a esta API` | Token de outro sistema (sem o escopo `site`) | Configuração errada: confira a variável |

Nada disso deve aparecer para o sócio como "senha errada": é erro de sistema. Mostre a mensagem genérica de indisponibilidade que o site já usa.

## Rotas que o token `site` alcança

`/api/login`, `/register`, `/check-member`, `/change-password`, `/send-email`, `/verify-token`, `/member/*`, `/places*`, `/place/*`, `/schedule*`, `/social/*`, `/social-showcase*`, `/tournaments*`, `/tickets*`, `/lighting/*`, `/dashboard/*`, `/replay/places*`, `/replay/my-videos`. O login do sócio (header `Session`) continua por cima nas mesmas rotas de antes.

## Como conferir

- Depois de trocar, faça login, abra a agenda e a vitrine de ingressos em homologação.
- Peça à TI para conferir em Administração › Integrações: o token do site de homologação precisa mostrar **Último uso** recente, e o IP do servidor do site não pode mais aparecer em "Chamadas sem token da tela" com o tipo "token antigo".
