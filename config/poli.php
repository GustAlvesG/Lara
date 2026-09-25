<?php

/*
|--------------------------------------------------------------------------
| Poli Digital
|--------------------------------------------------------------------------
|
| Quem fala com a API é App\Services\Poli\PoliClient; o formato das
| requisições mora nele. Contrato comprovado em 25/09/2026 com entrega no
| aparelho (ACK RECEIVED_BY_CLIENT):
|
|   POST {base_url}/contacts/{contact_uuid}/messages
|   POST {base_url}/accounts/{account_uuid}/contacts/{telefone}/messages?include=contact
|   Authorization: Bearer {token}
|
| Cuidado com `POST /accounts/{account_uuid}/messages`: responde 200 com corpo
| vazio para QUALQUER corpo e não entrega nada. Foi o endpoint usado até
| aqui, e é por isso que o aviso do Uber nunca chegou a ninguém.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Chave geral
    |--------------------------------------------------------------------------
    |
    | Desligado, nenhuma mensagem sai: o acesso do Uber continua sendo
    | registrado e concluído exatamente como antes desta integração. É o que
    | permite subir o código sem começar a mandar WhatsApp no mesmo deploy.
    |
    */

    'enabled' => (bool) env('POLI_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Entrada — ordem de processamento
    |--------------------------------------------------------------------------
    |
    | O webhook da Poli entrega fora de ordem. Medido em 7 dias de produção: 8
    | mensagens em 2081 chegaram depois de outra enviada posteriormente a elas,
    | com defasagens de 1 a 12 segundos. Numa máquina de estados isso põe a
    | resposta no campo errado e desloca o pedido inteiro.
    |
    | A compensação tem duas metades. A primeira é `max_delivery_lag`: a
    | mensagem espera antes de ser processada, porque uma irmã atrasada só pode
    | ser levada em conta depois de ter chegado. A segunda é `ordering_wait`:
    | na hora de processar, a mensagem cede a vez se houver outra do mesmo
    | contato com sequência menor ainda pendente.
    |
    | A espera é contada a partir da hora em que a POLI CRIOU a mensagem
    | (`value.metadata.created_at`), nunca da hora em que o webhook chegou.
    | É o que mantém a compensação restrita ao problema que ela resolve:
    |
    |   - o que se compensa é o atraso de ENTREGA, então o teto é o atraso
    |     máximo de entrega — e uma mensagem que já chegou atrasada não espera
    |     de novo, porque o tempo dela já foi gasto no caminho;
    |   - o RITMO DA CONVERSA não entra na conta. O associado pode levar o
    |     tempo que quiser entre uma resposta e outra: cada uma é processada a
    |     este tanto de segundos de quando ele a escreveu, e não a este tanto
    |     de segundos depois da anterior.
    |
    | Ancorar na chegada, como se fazia antes, somava as duas coisas: o atraso
    | da entrega e mais a espera inteira. Com 60s de espera a coleta passou a
    | correr um minuto atrás da conversa, e como o FIM do atendimento chega por
    | um caminho sem espera nenhuma, o atendimento fechava com metade das
    | respostas ainda na fila — pedido perfeito no WhatsApp, pedido vazio no
    | sistema.
    |
    */

    'inbound' => [

        /*
        | Teto do atraso de entrega do webhook. As defasagens medidas em 7 dias
        | de produção vão de 1 a 12 segundos; o resto é margem. Subir este
        | número não atrasa a conversa toda — atrasa cada mensagem em relação à
        | própria criação, e só até o teto.
        */
        'max_delivery_lag_seconds' => (int) env('POLI_INBOUND_MAX_LAG', 20),

        /*
        | Até quando esperar por uma irmã mais antiga. Passado o prazo a
        | mensagem segue sem ela — é a trava que impede uma mensagem presa de
        | travar a conversa inteira do contato.
        */
        'ordering_wait_seconds' => (int) env('POLI_INBOUND_ORDERING_WAIT', 45),

        /* Intervalo entre uma tentativa e outra enquanto se cede a vez. */
        'defer_seconds' => (int) env('POLI_INBOUND_DEFER_SECONDS', 3),

        /*
        | Quanto tempo insistir quando o toque cita um menu ainda não indexado.
        | É contado a partir da CHEGADA da mensagem, e não por tentativas, para
        | não se confundir com as tentativas gastas cedendo a vez.
        */
        'menu_index_grace_seconds' => (int) env('POLI_INBOUND_MENU_GRACE', 45),

        /* Teto de vida do job, para nenhuma espera virar laço infinito. */
        'retry_until_minutes' => (int) env('POLI_INBOUND_RETRY_UNTIL_MINUTES', 5),
    ],

    'base_url' => env('POLI_BASE_URL', 'https://foundation-api.poli.digital/v3'),

    'token' => env('POLI_API_TOKEN'),

    'account_uuid' => env('POLI_ACCOUNT_UUID'),

    /*
    | Canal (número de WhatsApp) de saída. `default_channel_uuid` é o nome
    | antigo, mantido para quem ainda o lê.
    */
    'channel_uuid' => env('POLI_CHANNEL_UUID'),
    'default_channel_uuid' => env('POLI_CHANNEL_UUID'),

    'http' => [
        'connect_timeout' => (int) env('POLI_CONNECT_TIMEOUT', 10),
        'timeout' => (int) env('POLI_TIMEOUT', 20),

        /*
        | Novas tentativas DENTRO da mesma chamada, só para conexão, 5xx e 429.
        | São para soluços de rede: a espera longa continua sendo do Job
        | (SendPoliTextMessage), que reagenda sem segurar um worker.
        */
        'retries' => (int) env('POLI_HTTP_RETRIES', 2),
        'retry_sleep_ms' => (int) env('POLI_HTTP_RETRY_SLEEP_MS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limit
    |--------------------------------------------------------------------------
    |
    | A documentação fala em 60 requisições por minuto por APLICAÇÃO; a API
    | responde `X-Ratelimit-Limit: 5000`. Fica o número da documentação, com
    | margem: 50. O teto é da conta inteira, não de um fluxo, por isso o
    | limitador (registrado em AppServiceProvider sob o nome abaixo) é único e
    | sem chave por destinatário: todo fluxo que enviar pela Poli entra no
    | MESMO balde, e não ganha uma cota paralela que estouraria o limite real.
    |
    */

    'rate_limit_per_minute' => (int) env('POLI_RATE_LIMIT_PER_MINUTE', 50),

    'rate_limit' => [
        'name' => 'poli-outbound',
        'per_minute' => (int) env('POLI_RATE_LIMIT_PER_MINUTE', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Textos
    |--------------------------------------------------------------------------
    |
    | Placeholders disponíveis: :nome, :placa, :local. Cada frase é separada
    | porque os dados são de preenchimento livre no WhatsApp — quando o
    | associado não informou o local, a última frase simplesmente não entra,
    | em vez de sair um "a caminho de ." na mensagem dele.
    |
    */

    'messages' => [
        'uber_arrival' => [
            'saudacao' => env('POLI_MSG_UBER_SAUDACAO', 'Olá, :nome!'),
            'corpo' => env(
                'POLI_MSG_UBER_CORPO',
                'Seu carro de aplicativo, placa :placa, chegou à portaria e o acesso foi liberado.'
            ),
            'local' => env('POLI_MSG_UBER_LOCAL', 'Ele está a caminho de :local.'),
        ],
    ],

];
