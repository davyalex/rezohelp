<?php

use App\Http\Middleware\ExigerChangementMotDePasse;
use App\Http\Middleware\VerifierCompteActif;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            VerifierCompteActif::class,
            ExigerChangementMotDePasse::class,
        ]);

        // Webhook des passerelles de paiement : authentifié par sa signature, pas par un jeton CSRF.
        $middleware->validateCsrfTokens(except: ['paiements/notification/*']);

        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
