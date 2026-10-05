<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        /*
         * FTP das câmeras da portaria (fotos do SIV — busca de placa).
         *
         * `timeout` curto de propósito: a foto é baixada dentro da tela de
         * resultado, e o padrão do driver (90s) deixaria a busca presa com o
         * servidor fora do ar. Ver FtpController::getImage().
         *
         * `root` é a pasta onde as câmeras gravam, uma subpasta por placa. A
         * coluna `parkings.file` guarda só `PLACA/arquivo.jpg`; sem a raiz o
         * arquivo é procurado no topo do servidor e nunca é achado.
         */
        'ftp' => [
            'driver' => 'ftp',
            'host' => env('FTP_HOST'),
            'username' => env('FTP_USERNAME'),
            'password' => env('FTP_PASSWORD'),
            'port' => (int) env('FTP_PORT', 21),
            'root' => env('FTP_ROOT', 'Lara/lpr'),
            'passive' => (bool) env('FTP_PASSIVE', true),
            'timeout' => (int) env('FTP_TIMEOUT', 10),
            // Falha vira exceção: é o que deixa o motivo no log.
            'throw' => true,
        ],

        /*
         * Cópia local das fotos do SIV, trazidas do disco `ftp` acima.
         *
         * Aponta para dentro de `public/`, como o disco `placar` abaixo e pelo
         * mesmo motivo: a tela lê em `/storage/img_car/…`, e `public/storage`
         * é um diretório real, não o symlink do `storage:link`. Pelo disco
         * `public` (storage/app/public) a foto ia para onde nenhuma URL
         * alcança.
         */
        'img_car' => [
            'driver' => 'local',
            'root' => public_path('storage/img_car'),
            'url' => '/storage/img_car',
            'visibility' => 'public',
            'throw' => true,
        ],

        /*
         * Arquivo dos documentos assinados (assinatura eletrônica).
         *
         * Disco próprio, e não o `ftp` acima — aquele é o das imagens das
         * câmeras. Por padrão aponta para o MESMO servidor e a mesma conta;
         * as variáveis SIGNATURE_FTP_* trocam isso sem mexer no outro.
         *
         * Sem `root` de propósito: o driver exige que a raiz já exista, e a
         * pasta do arquivo (config/signature.php → archive.root) é criada
         * pelo próprio sistema no primeiro envio.
         */
        'signature_archive' => [
            'driver' => 'ftp',
            'host' => env('SIGNATURE_FTP_HOST', env('FTP_HOST')),
            'username' => env('SIGNATURE_FTP_USERNAME', env('FTP_USERNAME')),
            'password' => env('SIGNATURE_FTP_PASSWORD', env('FTP_PASSWORD')),
            'port' => (int) env('SIGNATURE_FTP_PORT', env('FTP_PORT', 21)),
            'ssl' => (bool) env('SIGNATURE_FTP_SSL', false),
            'passive' => (bool) env('SIGNATURE_FTP_PASSIVE', true),
            'timeout' => 30,
            // Falha vira exceção: o job precisa saber que não gravou.
            'throw' => true,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        /*
         * Mídia do Placar Clube (logos, fotos e vídeos de jogador).
         *
         * Aponta para dentro de `public/` de propósito, e não para
         * `storage/app/public` + `storage:link`: este projeto nunca usou o
         * symlink do Laravel (`public/storage` já é um diretório real, com
         * arquivos de outras áreas), e o telão precisa que a mídia seja
         * servida como arquivo estático pelo servidor web — sem passar por
         * PHP a cada imagem, e com suporte nativo a range request, que é o
         * que permite buscar/seekar o vídeo do jogador.
         *
         * Grava por aqui; a URL sai por ImagemService::url().
         */
        'placar' => [
            'driver' => 'local',
            'root' => public_path('storage'),
            'url' => '/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        /*
         * Mídia do Replay (logomarcas, overlays compostos e os clipes das
         * quadras).
         *
         * Mesma decisão do disco `placar`, e pelos mesmos dois motivos: este
         * projeto nunca usou o `storage:link` (o `public/storage` já é um
         * diretório real, com arquivos de outras áreas), e o clipe precisa ser
         * servido como arquivo estático pelo servidor web — sem PHP no caminho
         * e com *range request* nativo, que é o que permite ao sócio avançar o
         * vídeo no player em vez de só assistir do começo.
         *
         * Disco separado do `placar` apesar da raiz igual: são módulos
         * diferentes, e um `replay:prune` distraído nunca deve alcançar a
         * mídia do Placar.
         *
         * Grava por aqui; a URL sai por MediaService::url().
         */
        'replay' => [
            'driver' => 'local',
            'root' => public_path('storage'),
            'url' => '/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
