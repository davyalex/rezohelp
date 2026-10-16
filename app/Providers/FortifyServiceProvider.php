<?php

namespace App\Providers;

use App\Actions\Auth\AuthentifierParTelephone;
use App\Actions\Auth\CreerCompte;
use App\Http\Controllers\Auth\MotDePasseOublieController;
use App\Http\Responses\ReponseInscription;
use App\Services\TelephoneService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;

/**
 * Authentification par téléphone + mot de passe (CONTEXTE §9) sur Laravel Fortify.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RegisterResponse::class, ReponseInscription::class);
    }

    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.connexion'));
        Fortify::registerView(fn () => view('auth.inscription'));
        Fortify::createUsersUsing(CreerCompte::class);
        Fortify::authenticateUsing(fn (Request $request) => app(AuthentifierParTelephone::class)->executer($request));

        // 5 tentatives par minute pour un même numéro (normalisé) depuis une même IP.
        RateLimiter::for('connexion', fn (Request $request): Limit => Limit::perMinute(5)->by(self::cleLimiteur($request)));
        RateLimiter::for('otp', fn (Request $request): Limit => Limit::perMinute(5)->by(self::cleLimiteur($request)));
    }

    /**
     * Clé « téléphone E.164 | IP » ; un membre connecté est identifié par son compte.
     */
    public static function cleLimiteur(Request $request): string
    {
        $utilisateur = $request->user();

        if ($utilisateur !== null) {
            return 'compte:'.$utilisateur->getAuthIdentifier().'|'.$request->ip();
        }

        $saisie = $request->input('telephone');
        $pays = $request->input('pays_code');
        $enSession = $request->hasSession() ? $request->session()->get(MotDePasseOublieController::CLE_SESSION) : null;

        $telephone = app(TelephoneService::class)->normaliser(
            is_string($saisie) ? $saisie : null,
            is_string($pays) ? $pays : null,
        ) ?? (is_string($saisie) ? $saisie : (is_string($enSession) ? $enSession : ''));

        return 'telephone:'.$telephone.'|'.$request->ip();
    }
}
