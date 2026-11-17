# Prompt para o agent da VM da IA (HAL): token próprio para consultar O Lara

**Sistema externo:** IA (VM do HAL, que consulta O Lara por `/api/information/search`, `/api/avisos/search` e `/api/guias/search`)

## Contexto

A IA consulta O Lara mandando `Authorization: Bearer <API_TOKEN>`, o mesmo token usado pelo site, pela portaria e pelos outros sistemas. Desde 17/11/2026 O Lara gera **um token por instalação**, com escopo e validade, na tela Administração › Integrações. O token da IA tem o escopo **`ia`** e só alcança as três buscas.

O token antigo continua aceito por um tempo, mas vai ser desligado. O Lara registra cada chamada feita com ele, e a TI só desliga quando a IA parar de usá-lo.

## O que muda na IA

**Só o valor do token** usado nas três consultas ao Lara. URL, parâmetros (`q`, `user_id`…) e formato das respostas continuam iguais.

1. Ache onde a VM guarda o token que manda ao Lara (variável de ambiente ou arquivo de configuração das ferramentas de busca).
2. Troque o valor pelo token que a TI vai passar. O formato é `lara_` seguido de 48 caracteres.
3. Não registre o token em log nem o inclua em prompt do modelo.
4. Isto é sobre o token que a **IA manda ao Lara**. As chamadas do Lara para a VM (`/perguntar`, `/reiniciar`, `/health`) não mudaram.

## Respostas novas

| HTTP | `message` | Quando | O que fazer |
|---|---|---|---|
| 401 | `Invalid API Token` | Token errado, ausente, ou o antigo depois de desligado | Erro de configuração: registre no log (sem o token) e responda ao usuário que não conseguiu consultar O Lara agora |
| 401 | `Token revogado, vencido ou de integração desativada` | Token vencido ou revogado | Idem; a TI precisa gerar outro |
| 403 | `Este token não tem acesso a esta API` | Token de outro sistema, sem o escopo `ia` | Configuração errada: confira a variável |

Não trate 401/403 como "nada encontrado": a resposta ao usuário precisa dizer que a consulta falhou, não que a informação não existe.

## Como conferir

Faça uma pergunta que use cada busca (informação do clube, aviso, "como faço…"). A TI confere em Administração › Integrações que o token da IA mostra **Último uso** recente, e que o IP da VM sumiu de "Chamadas sem token da tela".
