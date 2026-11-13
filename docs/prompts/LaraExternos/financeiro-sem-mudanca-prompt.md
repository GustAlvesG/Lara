# Prompt — Lara Externos (portaria, freelancers): módulo Financeiro no Lara

**Sistema externo:** Lara Externos (app da guarita / tablet dos freelancers).
**Data:** 13/11/2026.

---

Você é o agent Claude Code da aplicação Lara Externos. Em 13/11/2026 O Lara ganhou um módulo
**Financeiro** no painel interno. A tela de pagamento dos freelancers saiu de
`/freelancer-services/financeiro` e foi para `/financeiro/freelancers`, e a baixa em lote foi de
`POST /freelancer-services/pay` para `POST /financeiro/freelancers/pagar`. São telas do painel, com
login de funcionário — **não são API**.

**Nenhuma API usada pela portaria ou pelo tablet mudou** (kiosk, contratos, assinatura, chave PIX,
apuração de vendas, Monitor de Acesso, check-in de ingressos).

O que você precisa fazer:

1. Procure no código algum link ou chamada para `/freelancer-services/financeiro`,
   `/freelancer-services/pay`, `/payments` ou `/ingressos/caixa`. Não deveria haver.
2. Se houver link de navegação, troque pelo endereço novo (`/financeiro/freelancers`,
   `/financeiro/reservas`, `/financeiro/caixa`); os GET antigos redirecionam, mas os POST antigos
   **não existem mais**.
3. Fora isso, nada a mudar.
