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
    | Placeholders disponíveis: :nome, :placa, :local. Cada linha é separada
    | porque os dados são de preenchimento livre no WhatsApp — quando o
    | associado não informou o local, a linha do destino simplesmente não
    | entra, em vez de sair um "Destino: " vazio na mensagem dele.
    |
    | Asteriscos são negrito no WhatsApp.
    |
    | Com `close_after` ligado, a conversa é encerrada na Poli logo depois do
    | aviso aceito — o aviso é a última coisa que o associado precisa, e o
    | atendimento aberto por ele ficaria pendurado no painel. O `rodape` só
    | entra nesse caso: é ele que diz ao associado que o atendimento acabou.
    |
    */

    'messages' => [
        'uber_arrival' => [
            'saudacao' => env('POLI_MSG_UBER_SAUDACAO', 'Olá, *:nome*!'),
            'corpo' => env(
                'POLI_MSG_UBER_CORPO',
                '🚗 Seu carro de aplicativo chegou à portaria e o acesso foi liberado.'
            ),
            'placa' => env('POLI_MSG_UBER_PLACA', '*Placa:* :placa'),
            'local' => env('POLI_MSG_UBER_LOCAL', '*Destino:* :local'),
            'rodape' => env(
                'POLI_MSG_UBER_RODAPE',
                'Este atendimento foi encerrado. Se precisar de algo, é só mandar uma nova mensagem.'
            ),
            'close_after' => (bool) env('POLI_UBER_ARRIVAL_CLOSE', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bot de atendimento na Lara
    |--------------------------------------------------------------------------
    |
    | Substitui o bot da Poli: os fluxos ficam em bot_flows e quem conduz a
    | conversa é App\Services\PoliBot\BotEngine. Três modos:
    |
    |   off     nada muda — o bot da Poli atende, a Lara só escuta o Uber.
    |   shadow  a Lara processa cada mensagem e REGISTRA o que responderia
    |           (poli_messages.shadow = true), sem enviar nada nem criar
    |           pedido. O bot da Poli continua atendendo. É o ensaio.
    |   on      a Lara responde de verdade. Desligue o bot da Poli ANTES, senão
    |           os dois respondem. A escuta do Uber (UberAccessRequestFlow)
    |           para: o pedido passa a ser criado pelo próprio fluxo do bot.
    |
    | `live_contacts` é o piloto: em modo shadow, estes contatos (contact_uuid
    | ou telefone com DDI, separados por vírgula) recebem as respostas de
    | verdade. Eles vão receber as do bot da Poli também.
    |
    */

    'bot' => [
        'mode' => env('POLI_BOT_MODE', 'off'),

        'live_contacts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('POLI_BOT_LIVE_CONTACTS', ''))
        ))),

        'default_timeout_minutes' => (int) env('POLI_BOT_TIMEOUT_MINUTES', 15),
        'default_max_attempts' => (int) env('POLI_BOT_MAX_ATTEMPTS', 3),

        /*
        | Time que recebe quem esgota as tentativas ou pede atendente, quando o
        | fluxo não diz outro. Vazio: o bot silencia e a conversa fica na fila
        | geral da conta.
        */
        'fallback_team_uuid' => env('POLI_BOT_FALLBACK_TEAM'),

        /*
        | Rede de segurança do silêncio: se o fim do atendimento humano nunca
        | chegar pelo webhook, o bot volta a responder depois deste prazo.
        */
        'human_timeout_hours' => (int) env('POLI_BOT_HUMAN_TIMEOUT_HOURS', 12),

        /*
        | Autor (author.uuid) com que a Poli publica as mensagens que a Lara
        | envia pela API. Mensagem de USER com uuid é lida como "atendente
        | assumiu"; um autor desta lista nunca é. Ainda não medido — confira
        | num evento `sent` de um envio da API e preencha se precisar.
        */
        'own_author_uuids' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('POLI_BOT_OWN_AUTHOR_UUIDS', ''))
        ))),

        /*
        | Palavras que valem em qualquer ponto da conversa (comparadas sem
        | acento e sem caixa). O "0" não vale quando o passo espera um número.
        */
        'escape' => [
            'menu' => ['menu', 'inicio', 'voltar', 'recomecar'],
            'sair' => ['sair', 'encerrar', 'finalizar', 'tchau'],
            'atendente' => ['atendente', 'humano', 'pessoa', 'falar com atendente', '0'],
        ],

        'messages' => [
            'invalid' => [
                'option' => 'Não entendi. Escolha uma das opções (pode tocar nela ou digitar o número).',
                'plate' => 'Essa placa não parece válida. Envie no formato ABC1D23 ou ABC-1234.',
                'date' => 'Não reconheci a data. Envie no formato dia/mês/ano, por exemplo 25/09/1980.',
                'number' => 'Envie só números, por favor.',
                'yes_no' => 'Responda *sim* ou *não*, por favor.',
                'text' => 'Não consegui entender. Pode enviar de novo?',
                'image' => 'Preciso de uma imagem. Envie a foto ou a captura de tela, por favor.',
                'media' => 'Por aqui eu só consigo ler mensagens de texto. Pode escrever, por favor?',
            ],
            'stale_menu' => 'Esse menu é de uma etapa anterior. Responda, por favor, à última pergunta:',
            'handoff' => 'Certo! Vou te passar para um de nossos atendentes. Aguarde um instante, por favor.',
            'too_many_attempts' => 'Não consegui entender suas respostas. Vou te passar para um atendente.',
            'goodbye' => 'Atendimento encerrado. Sempre que precisar, é só chamar!',
            'expired' => 'Sua conversa anterior ficou parada e foi encerrada. Vamos recomeçar:',
        ],
    ],

];
