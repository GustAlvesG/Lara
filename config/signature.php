<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Onde os arquivos ficam
    |--------------------------------------------------------------------------
    |
    | Disco PRIVADO, sempre. PDF, PNG da assinatura e foto do signatário são
    | prova documental ligada ao CPF de uma pessoa: nada disso vira URL.
    | Tudo sai por rota autenticada, com Policy.
    |
    | Não use o disco `public` nem `placar`: os dois apontam para dentro de
    | `public/` e serviriam os arquivos como estático, sem passar por
    | autorização nenhuma.
    |
    */

    'disk' => env('SIGNATURE_DISK', 'local'),

    'paths' => [
        'documents' => 'signature/documents',
        'signatures' => 'signature/signatures',
        'photos' => 'signature/photos',
    ],

    /*
    |--------------------------------------------------------------------------
    | Liberação por QR Code
    |--------------------------------------------------------------------------
    |
    | Dois prazos diferentes, e é importante que sejam diferentes:
    |
    | `qr_ttl_seconds` é o tempo entre o atendente mostrar o QR e o tablet
    | lê-lo — coisa de segundos na prática. Cinco minutos já é folga; um QR que
    | fica valendo é um QR que alguém fotografa por cima do ombro.
    |
    | `session_ttl_minutes` começa na LEITURA e cobre o atendimento inteiro:
    | ler o documento, conferir o CPF, assinar. Quinze minutos é o suficiente
    | para um termo de uma página lido com calma.
    |
    | `session_warning_seconds` é quando o tablet avisa que vai encerrar. Sem o
    | aviso, a pessoa perde o que estava fazendo sem entender por quê.
    |
    */

    'qr_ttl_seconds' => (int) env('SIGNATURE_QR_TTL_SECONDS', 300),
    'session_ttl_minutes' => (int) env('SIGNATURE_SESSION_TTL_MINUTES', 15),
    'session_warning_seconds' => (int) env('SIGNATURE_SESSION_WARNING_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Modo sem HTTPS (caminho degradado)
    |--------------------------------------------------------------------------
    |
    | `getUserMedia` só funciona em origem segura. Em HTTP comum, o tablet não
    | lê QR e não tira foto — a tela abre e não faz nada.
    |
    | `manual_code` liga um código CURTO, ditado pelo atendente e digitado no
    | tablet, ao lado do QR. É a mesma liberação, com as mesmas travas: uso
    | único, mesmo prazo, mesmo vínculo com um documento. O que muda é só como
    | o segredo chega ao aparelho — e aí entra a diferença que importa: um
    | código ditado passa por uma pessoa, e uma pessoa pode repeti-lo a quem
    | não devia. Por isso ele é mais curto de vida e nasce DESLIGADO.
    |
    | `photo.skip_without_camera` deixa o tablet concluir a assinatura sem a
    | foto quando a câmera não existe. Não é o mesmo que "o modelo não pedia
    | foto": a evidência fica registrada como AUSENTE, com o motivo, e o
    | manifesto imprime isso. Também nasce desligado, porque ligar é abrir mão
    | de uma evidência que o modelo declarou necessária.
    |
    | Os dois juntos são o modo de operação possível enquanto não há HTTPS —
    | não um modo equivalente.
    |
    */

    'manual_code' => [
        'enabled' => (bool) env('SIGNATURE_MANUAL_CODE_ENABLED', false),
        // Metade do prazo do QR, por padrão: um código ditado em voz alta no
        // balcão é ouvido por quem estiver na fila.
        'ttl_seconds' => (int) env('SIGNATURE_MANUAL_CODE_TTL_SECONDS', 150),
    ],

    /*
    | Prazo do documento inteiro: liberado e esquecido, é encerrado pelo
    | comando `signature:expire`. Conta a partir do congelamento.
    */

    'document_ttl_hours' => (int) env('SIGNATURE_DOCUMENT_TTL_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Restrição de rede (opcional)
    |--------------------------------------------------------------------------
    |
    | Faixas de IP que podem CONSUMIR um token de QR, em CIDR ou IP solto,
    | separadas por vírgula. Vazio = sem restrição.
    |
    | É uma trava a mais, não a principal: o token de uso único já é a
    | proteção. Serve para o caso de alguém fotografar o QR e tentar abri-lo
    | fora da rede do clube.
    |
    | Exemplo: SIGNATURE_ALLOWED_IPS=192.168.10.0/24,10.0.0.5
    |
    */

    'allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SIGNATURE_ALLOWED_IPS', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Atendimento
    |--------------------------------------------------------------------------
    |
    | Local que vai para o manifesto: onde a assinatura aconteceu.
    |
    */

    'location' => env('SIGNATURE_LOCATION', 'Balcão de atendimento — CFCSN'),

    /*
    |--------------------------------------------------------------------------
    | Evidências
    |--------------------------------------------------------------------------
    |
    | `min_stroke_points` recusa a "assinatura" de um toque só. Um traço de
    | verdade, mesmo de quem assina rápido, passa fácil de 30 pontos.
    |
    | Os limites de upload valem para o que o tablet manda, e o tipo é
    | conferido pelos BYTES, não pelo cabeçalho do data URL.
    |
    */

    'evidence' => [
        'min_stroke_points' => (int) env('SIGNATURE_MIN_STROKE_POINTS', 30),
        'max_signature_kb' => (int) env('SIGNATURE_MAX_SIGNATURE_KB', 2048),
        'max_photo_kb' => (int) env('SIGNATURE_MAX_PHOTO_KB', 4096),

        /*
         | Concluir sem foto quando a câmera não existe (ambiente sem HTTPS).
         | Ver o bloco "Modo sem HTTPS" acima: a ausência é REGISTRADA com o
         | motivo e impressa no manifesto, e não silenciada.
         */
        'skip_photo_without_camera' => (bool) env('SIGNATURE_SKIP_PHOTO_WITHOUT_CAMERA', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retenção
    |--------------------------------------------------------------------------
    |
    | Prazo de guarda padrão, em meses, para modelos que não definem o seu.
    | A EXCLUSÃO FÍSICA NÃO ESTÁ IMPLEMENTADA: o campo é gravado desde já para
    | que a decisão de retenção exista antes de haver o que apagar.
    |
    */

    'retention_months' => (int) env('SIGNATURE_RETENTION_MONTHS', 60),

    /*
    |--------------------------------------------------------------------------
    | Envio da via ao signatário
    |--------------------------------------------------------------------------
    |
    | O WhatsApp nasce DESLIGADO: o gateway da Poli responde 200 para envios
    | que não entrega, então um "enviado" na tela não provaria entrega. Ligue
    | sabendo disso.
    |
    */

    'delivery' => [
        'email' => (bool) env('SIGNATURE_DELIVERY_EMAIL', true),
        'whatsapp' => (bool) env('SIGNATURE_DELIVERY_WHATSAPP', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Lacre com certificado (PAdES) — não implementado
    |--------------------------------------------------------------------------
    |
    | A entrega de hoje é assinatura eletrônica AVANÇADA (Lei 14.063/2020),
    | baseada em evidências. Assinar o PDF final com o certificado A1 do clube
    | lacraria o arquivo, mas não é o que dá valor jurídico à assinatura da
    | pessoa — por isso ficou fora.
    |
    | A flag existe para o ponto de extensão (SignaturePdfSealer) ter onde ser
    | ligado no dia em que o certificado for adquirido.
    |
    */

    'pades' => [
        'enabled' => (bool) env('SIGNATURE_PADES_ENABLED', false),
        'certificate_path' => env('SIGNATURE_PADES_CERTIFICATE'),
        'certificate_password' => env('SIGNATURE_PADES_PASSWORD'),
    ],

];
