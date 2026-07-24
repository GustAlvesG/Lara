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

];
