<?php

/*
|--------------------------------------------------------------------------
| Poli Digital — envio de mensagens (saída)
|--------------------------------------------------------------------------
|
| O webhook de ENTRADA (App\Services\Poli\PoliMessageParser) não passa por
| aqui: ele só recebe. Este arquivo é o lado que FALA — hoje, um único caso
| de uso, a mensagem que avisa o associado que o carro de aplicativo chegou
| à portaria.
|
| Contrato confirmado com o Postman da Poli:
|
|   POST {base_url}/accounts/{account_uuid}/messages
|   Authorization: Bearer {token}
|
| Tudo que é "formato do payload" mora no bloco `send` abaixo, e não
| espalhado pelo Service. Quando a Poli mudar um nome de campo, é UM arquivo
| para editar.
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

    'base_url' => env('POLI_BASE_URL', 'https://foundation-api.poli.digital/v3'),

    'token' => env('POLI_API_TOKEN'),

    'account_uuid' => env('POLI_ACCOUNT_UUID'),

    /*
    | Canal (número de WhatsApp) usado quando quem chama não informa outro.
    */
    'default_channel_uuid' => env('POLI_CHANNEL_UUID'),

    'http' => [
        'connect_timeout' => (int) env('POLI_CONNECT_TIMEOUT', 10),
        'timeout' => (int) env('POLI_TIMEOUT', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limit
    |--------------------------------------------------------------------------
    |
    | 60 requisições por minuto por APLICAÇÃO — o teto é da conta inteira, não
    | deste fluxo. Por isso o limitador (registrado em AppServiceProvider sob o
    | nome abaixo) é único e sem chave por destinatário: se amanhã outro fluxo
    | passar a enviar pela mesma conta, ele entra no MESMO balde, e não ganha
    | uma cota paralela que estouraria o limite real.
    |
    | Na prática um acesso de Uber por vez fica ordens de grandeza abaixo de
    | 60/min: isto é rede de proteção, não gargalo esperado.
    |
    */

    'rate_limit' => [
        'name' => 'poli-outbound',
        'per_minute' => (int) env('POLI_RATE_LIMIT_PER_MINUTE', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Formato do POST de envio
    |--------------------------------------------------------------------------
    |
    | Espelha o corpo do Postman. O exemplo de lá é de uma mensagem com anexo
    | (`type: MEDIA`); para texto puro o tipo é `CHAT` — é o mesmo valor que a
    | Poli usa no webhook de ENTRADA para mensagens de texto (`value.type`),
    | o que torna CHAT/IMAGE o par simétrico de MEDIA/CHAT na saída.
    |
    */

    'send' => [

        /*
        | `{account_uuid}` é trocado em tempo de execução. Separado do base_url
        | porque é a parte que muda se a Poli versionar a rota.
        */
        'path' => env('POLI_SEND_PATH', '/accounts/{account_uuid}/messages'),

        /*
        | Valores fixos do envelope. `provider` vem do exemplo do Postman
        | ("WHATSAPP"); repare que o webhook de entrada devolve "WABA" no
        | `account_channel.provider` — são vocabulários de lados diferentes da
        | API, e o que vale aqui é o da requisição de saída.
        */
        'provider' => env('POLI_PROVIDER', 'WHATSAPP'),
        'version' => env('POLI_VERSION', 'v3'),
        'text_type' => env('POLI_TEXT_TYPE', 'CHAT'),
        'contact_type' => env('POLI_CONTACT_TYPE', 'PERSON'),

        /*
        | O corpo do Postman identifica o destinatário por DOIS campos:
        | `contact_uuid` e `contact_channel_uid`.
        |
        | O `contact_uuid` é o identificador canônico e nós o temos de primeira
        | mão — é a Poli que nos manda em `value.contact.uuid` no webhook, e ele
        | fica gravado em `uber_access_requests.contact_uuid`. É nele que se
        | deve confiar.
        |
        | O `contact_channel_uid` é o endereço no canal. Nunca o vimos para um
        | CONTATO: o único `uid` observado em payload real é o do NOSSO canal
        | (`account_channel.uid = "5524992510959@c.us"`), daí o sufixo padrão.
        | Por ser inferência, e não contrato confirmado, ele é OPCIONAL: com
        | `include_contact_channel_uid = false` o envio vai só com o uuid, que
        | é o caminho seguro. Ligue se a Poli exigir os dois.
        */
        'include_contact_channel_uid' => (bool) env('POLI_SEND_CONTACT_CHANNEL_UID', false),
        'contact_channel_uid_suffix' => env('POLI_CONTACT_UID_SUFFIX', '@c.us'),

        /*
        | Autor da mensagem. O exemplo do Postman mostra `USER` + `user_uuid`,
        | que é o caso de um atendente humano mandando pela tela. Quem envia
        | aqui é a integração, não uma pessoa — daí `APPLICATION` como padrão.
        |
        | Se a Poli recusar (o 422 dela nomeia o campo), preencha
        | POLI_AUTHOR_USER_UUID com o uuid de um usuário da conta: o tipo vira
        | `USER` sozinho, sem tocar em código.
        */
        'author' => [
            'user_uuid' => env('POLI_AUTHOR_USER_UUID'),
            'name' => env('POLI_AUTHOR_NAME', 'Portaria'),
        ],
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
