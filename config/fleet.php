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
    | Km pela API da portaria: só os últimos dígitos
    |--------------------------------------------------------------------------
    |
    | Desde 17/11/2026 o porteiro digita só os últimos números do hodômetro
    | (3 por padrão) e o Lara compõe o número inteiro a partir da última km do
    | carro (saída) ou da km de saída (retorno). 0 volta ao número inteiro —
    | só para o caso de o app da guarita ainda não ter sido atualizado.
    | As telas do Lara continuam pedindo o número inteiro.
    |
    | `api_odometer_back_km` é a folga para trás: digitar um pouco abaixo da
    | referência (erro de leitura) cai no aviso de "km menor", e não numa
    | viagem de quase mil km. Uma viagem só, pela API, fica limitada a
    | 10^dígitos menos essa folga (949 km com os padrões).
    |
    */
    'api_odometer_digits' => (int) env('FLEET_API_ODOMETER_DIGITS', 3),

    'api_odometer_back_km' => (int) env('FLEET_API_ODOMETER_BACK_KM', 50),

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

    /*
    |--------------------------------------------------------------------------
    | Motorista precisa ser funcionário cadastrado
    |--------------------------------------------------------------------------
    |
    | Funcionário desligado, de férias ou afastado é sempre recusado. Esta
    | chave vai além: recusa também quem não está no cadastro de Funcionários
    | (nome digitado sem vínculo). Desligada até o RH terminar a carga dos
    | funcionários pela planilha — ligada antes disso, a portaria deixaria de
    | registrar saídas legítimas.
    |
    */
    'require_employee' => (bool) env('FLEET_REQUIRE_EMPLOYEE', false),

];
