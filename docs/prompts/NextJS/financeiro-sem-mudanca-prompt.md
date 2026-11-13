# Prompt — site Next.js (Reservas, Torneios, Ingressos): módulo Financeiro no Lara

**Sistema externo:** NextJS (site de locação do clube — reservas, torneios, ingressos, dashboards).
**Data:** 13/11/2026.

---

Você é o agent Claude Code do site Next.js do clube. O backend é O Lara (Laravel). Em 13/11/2026
O Lara ganhou um módulo **Financeiro** no painel interno, que juntou telas que estavam espalhadas
(pagamentos das reservas, financeiro dos torneios e dos ingressos, caixa, pagamento dos
freelancers) e unificou o estorno pela eRede num serviço só.

**Nenhuma API pública mudou.** Continuam iguais, no caminho, no corpo e na resposta:

- `POST /api/schedule/payment` (pagamento de reserva de quadra e de espaço social);
- `POST /api/tournaments/.../payment` (inscrição de torneio);
- `POST /api/tickets/guest/payment` e `POST /api/tickets/orders/{id}/payment` (ingressos);
- `/api/dashboard/*` (dashboards do site).

O que você precisa fazer:

1. **Nada no fluxo de compra.** Não altere chamadas, payloads nem telas por causa desta mudança.
2. Confira se o site tem algum **link fixo para telas internas do Lara** com os endereços antigos
   (`/payments`, `/torneios/financeiro`, `/ingressos/financeiro`, `/ingressos/caixa`,
   `/freelancer-services/financeiro`). Não deveria haver — o navegador do sócio não alcança O Lara.
   Se houver, eles continuam funcionando (O Lara responde 301 para `/financeiro/...`), mas troque
   pelo endereço novo ou, melhor, remova.
3. Mensagens de estorno que o site mostre a partir das respostas do Lara continuam as mesmas.

Não precisa responder a este prompt com código; só registre no histórico do site que a conferência
foi feita.
