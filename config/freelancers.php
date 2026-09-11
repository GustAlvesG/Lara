<?php

return [

    /*
    | A diretoria (nome, e-mail que recebe os códigos do lote e a imagem da
    | assinatura) NÃO mora mais aqui: é cadastrada pela gerência na tela
    | Serviços / Contratos → Diretoria, tabela `freelancer_directors`. As
    | variáveis FREELANCER_DIRECTOR_* do `.env` só foram lidas uma vez, pela
    | migration que criou a tabela, para o destinatário de hoje continuar
    | recebendo no dia do deploy.
    */

    /*
    |--------------------------------------------------------------------------
    | Limite semanal
    |--------------------------------------------------------------------------
    |
    | Passar de 2 serviços em 7 dias exige a liberação do coordenador do setor
    | Comercial. Presencialmente ele digita o próprio PIN; ausente, recebe por
    | e-mail um código de 6 dígitos e o dita para quem está registrando.
    |
    | O prazo de 2 horas dá folga para o coordenador ver o e-mail e responder
    | sem que o atendimento tenha de pedir um código novo. Quem segura o risco
    | de um prazo mais longo é o resto do desenho: o código vale para UM
    | contrato, UMA vez, e o número de tentativas é limitado — seis dígitos não
    | resistem a chutes ilimitados.
    |
    */

    'weekly_limit' => [
        'code_ttl_minutes' => (int) env('FREELANCER_WEEKLY_CODE_TTL_MINUTES', 120),
        'code_max_attempts' => (int) env('FREELANCER_WEEKLY_CODE_MAX_ATTEMPTS', 5),
    ],

];
