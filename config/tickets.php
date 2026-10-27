<?php

/*
| Ingressos (docs/funcionalidades/ingressos.md).
*/
return [
    // Por quantos minutos o pedido pendente segura o estoque. Passado o
    // prazo, `ingressos:expirar-pedidos` (a cada minuto) o expira. O
    // pagamento que chega depois ainda é aceito se houver estoque.
    'hold_minutes' => (int) env('TICKETS_HOLD_MINUTES', 10),

    // Endereço do site de locação (Next.js). O e-mail do não sócio leva o
    // link {site_url}/ingressos/pedido/{id}?token={token}; vazio = o e-mail
    // sai sem o link (os códigos dos ingressos vão no corpo de qualquer jeito).
    'site_url' => env('TICKETS_SITE_URL', ''),

    // Check-in: a entrada abre estas horas antes do início do evento e
    // fecha no fim dele. Vale para o Lara (fora dela, pede confirmação) e
    // para o Monitor de Acesso da portaria (fora dela, nega).
    'checkin_hours_before' => (int) env('TICKETS_CHECKIN_HOURS_BEFORE', 3),
];
