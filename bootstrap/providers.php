<?php

return [
    App\Providers\AppServiceProvider::class,
    // App\Providers\CompTimeServiceProvider não existe em app/Providers — era
    // uma referência morta, ignorada em silêncio no boot. O Gate do Banco de
    // Horas mora em AppServiceProvider, junto com os outros.
    App\Providers\LoginToken::class,
];
