# Prompt para o agent do Lara Externos: token próprio da portaria e token no Monitor de Acesso

**Sistema externo:** Lara Externos (app da guarita / Monitor de Acesso em Python, câmeras de placa e frota)

## Contexto

A portaria chama O Lara em três grupos de rotas:

- `/api/parking/*` (placas) e `/api/fleet/*` (saída e retorno da frota): hoje com `Authorization: Bearer <API_TOKEN>`, o mesmo token de todos os sistemas.
- `/api/company-access/*` (validar acesso, registrar entradas de prestador, freelancer, liberação pontual, ingresso, Uber, fila "Aguardando acesso do motorista"): hoje **sem token nenhum**.

Desde 17/11/2026 O Lara gera **um token por instalação**, com escopo e validade, na tela Administração › Integrações. O token da portaria tem o escopo **`portaria`** e alcança os três grupos.

## O que muda no app

1. Ache onde o app guarda o token que manda em `/api/parking/*` e `/api/fleet/*` e troque o valor pelo token que a TI vai passar (formato `lara_` seguido de 48 caracteres). Cada instalação (cada guarita ou computador, produção e homologação) recebe o próprio token, se a TI separar assim.
2. **Passe a mandar o mesmo header `Authorization: Bearer <token>` em todas as chamadas a `/api/company-access/*`.** Hoje elas saem sem header.
3. URLs, corpo e respostas continuam iguais.
4. Não registre o token em log.

### Ordem de liberação (para não parar a guarita)

- Hoje O Lara **não barra** `/api/company-access/*` sem token: só registra quem chama sem. Pode publicar o app com o header a qualquer momento.
- Depois que todas as guaritas estiverem mandando o token, a TI liga a exigência (`API_TOKEN_PORTARIA_ENFORCED=true`). A partir daí, chamada sem token em `/api/company-access/*` recebe **401**.
- O token antigo em `/api/parking/*` e `/api/fleet/*` continua aceito até a TI desligar.

## Respostas novas

| HTTP | `message` | Quando | O que fazer |
|---|---|---|---|
| 401 | `Invalid API Token` | Token errado, ausente (company-access depois de ligada a exigência) ou o antigo depois de desligado | Mostre ao porteiro "Sem comunicação com O Lara — avise a TI" e registre no log (sem o token) |
| 401 | `Token revogado, vencido ou de integração desativada` | Token vencido ou revogado | Idem |
| 403 | `Este token não tem acesso a esta API` | Token de outro sistema, sem o escopo `portaria` | Configuração errada: confira a variável |

Não trate 401/403 como "acesso negado à pessoa": é falha de sistema. O porteiro não pode entender que a pessoa está bloqueada.

## Como conferir

Em homologação, registre uma entrada de prestador, consulte uma placa e faça uma saída de frota. A TI confere em Administração › Integrações que o token da portaria mostra **Último uso** recente e que o IP da guarita sumiu de "Chamadas sem token da tela" (tipos "sem token" e "token antigo").
