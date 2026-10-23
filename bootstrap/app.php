<?php

use App\Http\Middleware\EnTetesSecurite;
use App\Http\Middleware\ExigerChangementMotDePasse;
use App\Http\Middleware\PasDeCache;
use App\Http\Middleware\VerifierCompteActif;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Back-office (CONTEXTE §13, D-066) : comptes et connexion séparés (garde « admin »), jamais mis en cache.
        then: fn () => Route::middleware(['web', PasDeCache::class])
            ->prefix('admin')
            ->name('admin.')
            ->group(base_path('routes/admin.php')),
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnTetesSecurite::class,
            VerifierCompteActif::class,
            ExigerChangementMotDePasse::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);

        // Préférences d'affichage écrites par le JS du back-office (barre latérale réduite, groupes repliés) : non sensibles.
        $middleware->encryptCookies(except: ['admin_menu_reduit', 'admin_menu_groupes']);

        // Webhook des passerelles de paiement : authentifié par sa signature, pas par un jeton CSRF.
        $middleware->preventRequestForgery(except: ['paiements/notification/*']);

        // Le back-office renvoie vers sa propre page de connexion, le site vers celle des membres.
        $middleware->redirectGuestsTo(fn (Request $request): string => $request->is('admin', 'admin/*') ? route('admin.connexion') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request): string => $request->is('admin', 'admin/*') ? route('admin.tableau-de-bord') : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
