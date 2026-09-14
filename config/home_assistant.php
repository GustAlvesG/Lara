<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Duração padrão do comando manual
    |--------------------------------------------------------------------------
    |
    | Quanto vale, em minutos, um "liga a quadra 1" que chega pela API sem
    | `duration_minutes`. Passado o prazo, o contator volta sozinho a seguir
    | agendamentos e reservas — ninguém precisa lembrar de desligar.
    |
    | O painel não usa este valor: lá o botão manual vale até o fim do dia.
    |
    */
    'manual_default_minutes' => (int) env('HA_MANUAL_DEFAULT_MINUTES', 120),

    /*
    |--------------------------------------------------------------------------
    | Duração máxima aceita
    |--------------------------------------------------------------------------
    |
    | Teto do `duration_minutes` na validação (12 horas). Acima disso o pedido
    | é recusado: um comando manual muito longo é quase sempre engano, e quem
    | quer algo permanente cria um agendamento no painel.
    |
    | De todo jeito o comando nunca passa da meia-noite — ver
    | ManualCommandService::expiryFor().
    |
    */
    'manual_max_minutes' => (int) env('HA_MANUAL_MAX_MINUTES', 720),

];
