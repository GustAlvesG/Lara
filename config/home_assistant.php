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

    /*
    |--------------------------------------------------------------------------
    | Autoatendimento de iluminação (app do sócio)
    |--------------------------------------------------------------------------
    |
    | No fim de semana não há reserva de quadra: o uso é livre. Estas regras
    | descrevem a única porta pela qual o sócio acende a luz sozinho, sem
    | passar por ninguém do clube.
    |
    | As janelas são por dia da semana, na numeração do Carbon (0 = domingo,
    | 6 = sábado) — a mesma de `Carbon::dayOfWeek`, e não a da tabela
    | `weekdays` (onde o id 1 é domingo). Dia ausente = fechado.
    |
    | Feriado não entra aqui: é data, não dia da semana, e muda todo ano. Vai
    | na tabela `lighting_self_service_dates`, pelo painel — ver
    | SelfServiceLightingService::windowFor().
    |
    */
    'self_service' => [

        // Teto de cada acionamento. Quem escolhe a duração é o sócio; isto é só
        // o limite de quanto ele pode pedir de uma vez. Sempre aparado também
        // no fim da janela: às 22:30 de sábado o máximo são 30 minutos.
        //
        // Não é teto do dia nem da quadra: acabando o tempo, qualquer sócio
        // presente prolonga por mais um período — a luz não apaga no meio do
        // jogo só porque o relógio de quem acendeu venceu.
        'max_minutes' => (int) env('HA_SELF_SERVICE_MAX_MINUTES', 120),

        // Piso do que dá para pedir. Abaixo disso não vale a ida ao aplicativo,
        // e é também o que define "a janela já está acabando".
        'min_minutes' => (int) env('HA_SELF_SERVICE_MIN_MINUTES', 15),

        // Granularidade sugerida para a escolha na tela (15, 30, 45…). Fica no
        // servidor junto do piso e do teto: são a mesma decisão, e mudá-la não
        // deveria exigir deploy do aplicativo.
        'step_minutes' => (int) env('HA_SELF_SERVICE_STEP_MINUTES', 15),

        'windows' => [
            6 => ['17:00', '23:00'], // sábado
            0 => ['17:00', '21:00'], // domingo
        ],

        // Janela aplicada a um feriado liberado que não traz horário próprio.
        // É a de domingo: feriado no clube tem o movimento de domingo.
        'holiday_window' => ['17:00', '21:00'],
    ],

];
