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
    ->withMiddleware(function (Middleware $middleware): void {

        // CORS en premier — avant tout middleware d'auth
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);

        // Alias des middlewares
        $middleware->alias([
            'auth'                => \App\Http\Middleware\AuthJWT::class,
            'etablissement.scope' => \App\Http\Middleware\EtablissementScope::class,
            'permission'          => \App\Http\Middleware\CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
