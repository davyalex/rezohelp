<?php

namespace App\Providers;

use App\Models\Abonnement;
use App\Models\Alerte;
use App\Models\AppelIa;
use App\Models\Categorie;
use App\Models\Commande;
use App\Models\Commentaire;
use App\Models\Commune;
use App\Models\Credit;
use App\Models\Entreprise;
use App\Models\ImportVeille;
use App\Models\MiseEnAvant;
use App\Models\Parametre;
use App\Models\Pays;
use App\Models\Plan;
use App\Models\Produit;
use App\Models\Publication;
use App\Models\Reponse;
use App\Models\Signalement;
use App\Models\SourceVeille;
use App\Models\Utilisateur;
use App\Services\CacheFil;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CacheFil::class);
    }

    public function boot(): void
    {
        $horsProduction = ! $this->app->isProduction();

        Model::preventLazyLoading($horsProduction);
        Model::preventSilentlyDiscardingAttributes($horsProduction);

        // Alias français des relations polymorphes (titulaires, médias, journal, notifications).
        Relation::enforceMorphMap([
            'utilisateur' => Utilisateur::class,
            'entreprise' => Entreprise::class,
            'publication' => Publication::class,
            'commentaire' => Commentaire::class,
            'reponse' => Reponse::class,
            'signalement' => Signalement::class,
            'alerte' => Alerte::class,
            'categorie' => Categorie::class,
            'commune' => Commune::class,
            'pays' => Pays::class,
            'parametre' => Parametre::class,
            'source_veille' => SourceVeille::class,
            'import_veille' => ImportVeille::class,
            'appel_ia' => AppelIa::class,
            'plan' => Plan::class,
            'produit' => Produit::class,
            'abonnement' => Abonnement::class,
            'commande' => Commande::class,
            'credit' => Credit::class,
            'mise_en_avant' => MiseEnAvant::class,
        ]);

        // Envois de formulaires de publication (CONTEXTE §11.5) : max_envois_par_heure par compte.
        RateLimiter::for('publication', fn (Request $request): Limit => Limit::perHour(Parametre::entier('max_envois_par_heure'))
            ->by('publication:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        Carbon::setLocale($this->app->getLocale());
        CarbonImmutable::setLocale($this->app->getLocale());
    }
}
