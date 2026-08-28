<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Teto de quilometragem por viagem
    |--------------------------------------------------------------------------
    |
    | Um retorno que ultrapassa este valor quase sempre é dígito trocado
    | (452100 no lugar de 45210). O registro não é recusado de vez: volta como
    | aviso e passa quando o operador confirma com `force`.
    |
    */
    'max_trip_km' => (int) env('FLEET_MAX_TRIP_KM', 9999999),

    /*
    |--------------------------------------------------------------------------
    | Tolerância para o horário informado
    |--------------------------------------------------------------------------
    |
    | O horário do registro é o momento em que ele chega. Um `registered_at`
    | pode ser enviado (fila offline da portaria, lançamento atrasado), mas
    | nunca no futuro além desta folga, em minutos, para o relógio dos dois
    | lados poder divergir um pouco.
    |
    */
    'clock_skew_minutes' => (int) env('FLEET_CLOCK_SKEW_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Viagem aberta há tempo demais
    |--------------------------------------------------------------------------
    |
    | Acima disto a viagem aparece destacada no painel como pendência de baixa.
    | Nada é fechado automaticamente: só quem viu o carro voltar sabe o km.
    |
    */
    'open_trip_alert_hours' => (int) env('FLEET_OPEN_TRIP_ALERT_HOURS', 12),

    /*
    |--------------------------------------------------------------------------
    | Validade sintética na lista da cancela
    |--------------------------------------------------------------------------
    |
    | Carro da frota não tem validade — a autorização dele é ser da empresa.
    | Mas a lista que a câmera baixa para operar offline tem uma coluna de
    | validade, e mudar o formato quebraria o cliente. Então a frota entra com
    | esta folga em anos, recalculada a cada consulta: enquanto a câmera
    | atualizar a lista, a data nunca chega. Quem tira o carro da liberação é a
    | desativação no cadastro, não o calendário.
    |
    */
    'gate_validity_years' => (int) env('FLEET_GATE_VALIDITY_YEARS', 10),

];
