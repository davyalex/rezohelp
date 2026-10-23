<?php

namespace App\Providers;

use App\Models\Abonnement;
use App\Models\Administrateur;
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
use App\Models\Publicite;
use App\Models\Reponse;
use App\Models\Signalement;
use App\Models\SourceVeille;
use App\Models\Utilisateur;
use App\Services\CacheFil;
use App\Support\PermissionsAdmin;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

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
            'administrateur' => Administrateur::class,
            'role' => Role::class,
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
            'publicite' => Publicite::class,
        ]);

        // Le super administrateur a tous les droits, y compris ceux ajoutés après le seeder (D-066).
        Gate::before(fn (mixed $utilisateur): ?bool => $utilisateur instanceof Administrateur
            && ! $utilisateur->estSuspendu()
            && $utilisateur->hasRole(PermissionsAdmin::ROLE_SUPER) ? true : null);

        // Connexion au back-office : 5 essais par minute pour un même nom d'utilisateur depuis une IP,
        // et 20 par minute au total depuis une IP (essais en série sur plusieurs noms d'utilisateur).
        RateLimiter::for('connexion-admin', fn (Request $request): array => [
            Limit::perMinute(5)->by('compte:'.mb_strtolower((string) $request->input('nom_utilisateur')).'|'.$request->ip()),
            Limit::perMinute(20)->by('ip:'.$request->ip()),
        ]);

        // Envois de formulaires de publication (CONTEXTE §11.5) : max_envois_par_heure par compte.
        RateLimiter::for('publication', fn (Request $request): Limit => Limit::perHour(Parametre::entier('max_envois_par_heure'))
            ->by('publication:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        Carbon::setLocale($this->app->getLocale());
        CarbonImmutable::setLocale($this->app->getLocale());
    }
}
