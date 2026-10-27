<?php

/*
 * Integração com a API do Claude (Anthropic). Ver docs/funcionalidades/claude-api.md.
 *
 * Sem `ANTHROPIC_API_KEY`, nada que usa o Claude aparece nas telas: o Lara
 * segue funcionando igual.
 */
return [

    'api_key' => env('ANTHROPIC_API_KEY'),

    // Segundos por tentativa; o SDK repete erros passageiros (429, 5xx, rede).
    'timeout' => (float) env('CLAUDE_TIMEOUT', 90),
    'max_retries' => (int) env('CLAUDE_MAX_RETRIES', 1),

    // Revisão automática de contratos (Assinaturas → Contratos), pela Gerência.
    'contract_review' => [
        'model' => env('CLAUDE_CONTRACT_REVIEW_MODEL', 'claude-haiku-5-5'),
        'max_tokens' => (int) env('CLAUDE_CONTRACT_REVIEW_MAX_TOKENS', 16000),
        // low | medium | high — quanto o modelo pensa antes de responder.
        'effort' => env('CLAUDE_CONTRACT_REVIEW_EFFORT', 'medium'),
    ],

];
