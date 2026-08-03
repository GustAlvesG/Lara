<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'api' => [
	    'token' => env('API_TOKEN'),
    ],

    'jwt' => [
        'secret' => env('JWT_SECRET'),
    ],

    'rede' => [
        'auth_url'      => env('INTERNAL_EREDE_AUTH_URL'),
        'base_url'      => env('INTERNAL_EREDE_API_URL'),
        'client_id'     => env('INTERNAL_EREDE_CLIENT_ID'),
        'client_secret' => env('INTERNAL_EREDE_SECRET_ID'),
    ],

    /*
     * Lara — agente de IA (Ollama + base do estatuto) hospedado numa VM
     * própria. O endpoint não tem autenticação: a proteção é de rede (só o IP
     * do portal alcança a porta), por isso a base_url mora no .env do servidor
     * e nunca no repositório.
     *
     * `timeout` é 25s porque o serviço da IA desiste sozinho em 22s e devolve o
     * fallback dele. Os 3s de folga cobrem só a rede: passou disso, o problema
     * não é o modelo demorando, é a VM inalcançável.
     *
     * `history_ttl_hours` acompanha a expiração do histórico do lado da IA (24h
     * de inatividade). Não confundir com a retenção do nosso banco
     * (app:prune-lara-messages, 90 dias): uma é memória de contexto, a outra é
     * auditoria.
     */
    'lara' => [
        'base_url'          => env('LARA_BASE_URL'),
        'enabled'           => env('LARA_ENABLED', false),
        'timeout'           => (int) env('LARA_TIMEOUT', 25),
        'reset_timeout'     => (int) env('LARA_RESET_TIMEOUT', 10),
        'health_timeout'    => (int) env('LARA_HEALTH_TIMEOUT', 3),
        'health_ttl'        => (int) env('LARA_HEALTH_TTL', 30),
        'history_ttl_hours' => (int) env('LARA_HISTORY_TTL_HOURS', 24),
        'max_input_chars'   => (int) env('LARA_MAX_INPUT_CHARS', 1000),
        'fallback_message'  => env('LARA_FALLBACK_MESSAGE', 'Vou te transferir para o setor responsável, só um momento!'),
    ],
];
