# Prompt para o agent do site (Next.js) — Ingressos: só eventos publicados

> Sistema externo: **NextJS** (site de locação/reservas, o mesmo das Reservas e dos Torneios).
> Origem: O Lara, 12/11/2026. Contrato completo da API: `docs/funcionalidades/ingressos-site-prompt.md`.

---

O Lara mudou o que a vitrine de ingressos devolve. Ajuste o site se ele dependia do comportamento antigo.

## O que mudou na API `/api/tickets/*`

- `GET /api/tickets/events` agora devolve **só eventos com `status: "published"`**. Antes vinham também os encerrados (`status: "finished"`).
- `GET /api/tickets/events/{id}` de um evento **encerrado** passa a dar **404** com `reason: "event_not_found"`, igual ao rascunho e ao cancelado. O mesmo vale para mapa, cupom e criação de pedido desse evento.
- **Não mudou:** "Meus ingressos" do sócio (`/api/tickets/my-orders…`) e o acesso do não sócio pelo código do pedido continuam trazendo os pedidos de eventos encerrados, com o objeto `event` dentro do pedido.

## O que fazer no site

1. Remova da vitrine qualquer tratamento de evento encerrado (selo "Evento encerrado", seção "Eventos passados", filtro por `status === "finished"`). Ele não vem mais.
2. A página de detalhe de um evento que deixou de ser publicado (link antigo, cache, compartilhamento) deve tratar o 404 `event_not_found` com uma mensagem amigável ("Este evento não está mais disponível") e um link para a lista de eventos — não uma tela de erro.
3. Em "Meus ingressos", **não** busque o detalhe do evento por `GET /api/tickets/events/{id}` para montar o cartão do pedido: use o `event` que já vem dentro do pedido. Se hoje o site faz essa busca, ela vai falhar com 404 para eventos encerrados.
4. Se houver cache (ISR/revalidate) das páginas de evento, garanta que um evento que saiu da lista não fique servido em cache como se estivesse à venda.

## Como testar

- Com um evento publicado e outro encerrado no Lara de homologação: a lista mostra só o publicado; a URL do encerrado mostra a mensagem amigável; o pedido do encerrado continua em "Meus ingressos" com o PDF/QR.
