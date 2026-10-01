<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'api_token' => \App\Http\Middleware\APIToken::class,
            // Aprovador externo de ordem de compra (site em DMZ). JWT com
            // escopo próprio — não confunde com o token de sócio.
            'approval_token' => \App\Http\Middleware\ApprovalToken::class,
            'login_token' => \App\Http\Middleware\JwtMiddleware::class,
            // Aviso de leitura obrigatória pendente desvia a navegação para a
            // tela de ciência (ver o middleware e routes/web.php).
            'avisos_obrigatorios' => \App\Http\Middleware\EnsureMandatoryAvisosAcknowledged::class,
            // Sem os aliases `role`/`permission` do Spatie: o acesso do painel
            // é `can:<permissão do catálogo>` — ver App\Authorization.
            // Sanctum não registra esses aliases automaticamente — usados
            // pela API do Placar Clube (ver routes/api.php).
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
