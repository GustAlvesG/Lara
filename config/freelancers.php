<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Diretoria
    |--------------------------------------------------------------------------
    |
    | O diretor não acessa a plataforma: a rede é interna e ele pode estar fora.
    | Ao aprovar um lote, a gerência dispara um e-mail para cá com dois PINs —
    | um aprova, outro recusa o lote inteiro. O diretor dita o PIN escolhido
    | para a gerência, que o digita no sistema.
    |
    | `cc` aceita vários endereços separados por vírgula (secretaria, cópia).
    |
    */

    'director' => [
        'name' => env('FREELANCER_DIRECTOR_NAME', 'Diretoria'),
        'email' => env('FREELANCER_DIRECTOR_EMAIL'),
        'cc' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('FREELANCER_DIRECTOR_CC', ''))
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limite semanal
    |--------------------------------------------------------------------------
    |
    | Passar de 2 serviços em 7 dias exige a liberação do coordenador do setor
    | Comercial. Presencialmente ele digita o próprio PIN; ausente, recebe por
    | e-mail um código de 6 dígitos e o dita para quem está registrando.
    |
    | O prazo é curto de propósito: o código atravessa uma ligação telefônica,
    | não um processo. E as tentativas são limitadas — seis dígitos não
    | resistem a chutes ilimitados.
    |
    */

    'weekly_limit' => [
        'code_ttl_minutes' => (int) env('FREELANCER_WEEKLY_CODE_TTL_MINUTES', 15),
        'code_max_attempts' => (int) env('FREELANCER_WEEKLY_CODE_MAX_ATTEMPTS', 5),
    ],

];
