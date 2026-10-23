<?php

use App\Http\Controllers\Admin\AbonnementController;
use App\Http\Controllers\Admin\AdministrateurController;
use App\Http\Controllers\Admin\CacheController;
use App\Http\Controllers\Admin\CategorieController;
use App\Http\Controllers\Admin\CommandeController;
use App\Http\Controllers\Admin\CommuneController;
use App\Http\Controllers\Admin\ConnexionController;
use App\Http\Controllers\Admin\EntrepriseController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\MotDePasseController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ParametreController;
use App\Http\Controllers\Admin\PaysController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\ProduitController;
use App\Http\Controllers\Admin\PublicationController;
use App\Http\Controllers\Admin\RechercheController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SignalementController;
use App\Http\Controllers\Admin\SourceVeilleController;
use App\Http\Controllers\Admin\TableauDeBordController;
use App\Http\Controllers\Admin\UtilisateurController;
use App\Http\Controllers\Admin\VeilleController;
use App\Http\Middleware\AdministrateurActif;
use App\Models\Administrateur;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

/*
| Back-office (CONTEXTE §13, D-066) — préfixe /admin, noms admin.*, middleware web + no-store (bootstrap/app.php).
| Connexion propre à l'équipe (garde « admin ») ; ensuite une permission par module (§13.2).
*/

Route::middleware('guest:'.Administrateur::GARDE)->group(function () {
    Route::get('/connexion', [ConnexionController::class, 'create'])->name('connexion');
    Route::post('/connexion', [ConnexionController::class, 'store'])->middleware('throttle:connexion-admin')->name('connexion.store');
});

Route::middleware(['auth:'.Administrateur::GARDE, AuthenticateSession::class, AdministrateurActif::class])->group(function () {

    Route::post('/deconnexion', [ConnexionController::class, 'destroy'])->name('deconnexion');
    Route::get('/mot-de-passe', [MotDePasseController::class, 'edit'])->name('mot-de-passe.edit');
    Route::put('/mot-de-passe', [MotDePasseController::class, 'update'])->name('mot-de-passe.update');

    // Notifications de l'équipe (publications masquées après signalements…)
    Route::get('/notifications/{notification}', [NotificationController::class, 'ouvrir'])->name('notifications.ouvrir');
    Route::post('/notifications/tout-lire', [NotificationController::class, 'toutLire'])->name('notifications.tout-lire');

    // A — Tableau de bord
    Route::get('/', [TableauDeBordController::class, 'index'])->middleware('can:tableau_de_bord.voir')->name('tableau-de-bord');

    // Recherches Ajax des listes Select2
    Route::prefix('recherche')->name('recherche.')->group(function () {
        Route::get('/utilisateurs', [RechercheController::class, 'utilisateurs'])->middleware('can:utilisateurs.voir')->name('utilisateurs');
        Route::get('/entreprises', [RechercheController::class, 'entreprises'])->middleware('can:utilisateurs.voir')->name('entreprises');
        Route::get('/publications', [RechercheController::class, 'publications'])->middleware('can:publications.moderer')->name('publications');
    });

    // B — Modération
    Route::middleware('can:publications.moderer')->prefix('moderation')->name('moderation.')->group(function () {
        Route::get('/', [ModerationController::class, 'index'])->name('index');
        Route::post('/lot', [ModerationController::class, 'lot'])->name('lot');
        Route::get('/{publication}', [ModerationController::class, 'show'])->name('show');
        Route::post('/{publication}/valider', [ModerationController::class, 'valider'])->name('valider');
        Route::post('/{publication}/rejeter', [ModerationController::class, 'rejeter'])->name('rejeter');
        Route::post('/{publication}/doublon', [ModerationController::class, 'doublon'])->name('doublon');
    });

    // C — Publications
    Route::middleware('can:publications.gerer')->group(function () {
        Route::resource('publications', PublicationController::class)->only(['index', 'edit', 'update', 'destroy']);
        Route::post('/publications/{publication}/depublier', [PublicationController::class, 'depublier'])->name('publications.depublier');
        Route::post('/publications/{publication}/prolonger', [PublicationController::class, 'prolonger'])->name('publications.prolonger');
    });

    // D — Signalements
    Route::middleware('can:signalements.traiter')->group(function () {
        Route::get('/signalements', [SignalementController::class, 'index'])->name('signalements.index');
        Route::get('/signalements/{publication}', [SignalementController::class, 'show'])->name('signalements.show');
        Route::post('/signalements/{publication}/decider', [SignalementController::class, 'decider'])->name('signalements.decider');
    });

    // E — Veille IA
    Route::middleware('can:veille.gerer')->prefix('veille')->name('veille.')->group(function () {
        Route::get('/imports', [VeilleController::class, 'index'])->name('imports.index');
        Route::get('/imports/{import}', [VeilleController::class, 'show'])->name('imports.show');
        Route::post('/imports/{import}/relancer', [VeilleController::class, 'relancer'])->name('imports.relancer');
        Route::post('/importer-maintenant', [VeilleController::class, 'importerMaintenant'])->middleware('throttle:6,1')->name('importer-maintenant');
        Route::post('/reevaluer', [VeilleController::class, 'reevaluer'])->middleware('throttle:6,1')->name('reevaluer');
        Route::resource('sources', SourceVeilleController::class)->except(['show', 'destroy'])->parameters(['sources' => 'source']);
    });

    // F — Utilisateurs et entreprises
    Route::middleware('can:utilisateurs.voir')->group(function () {
        Route::get('/utilisateurs', [UtilisateurController::class, 'index'])->name('utilisateurs.index');
        Route::get('/utilisateurs/{utilisateur}', [UtilisateurController::class, 'show'])->name('utilisateurs.show');

        Route::middleware('can:utilisateurs.suspendre')->group(function () {
            Route::post('/utilisateurs/{utilisateur}/suspendre', [UtilisateurController::class, 'suspendre'])->name('utilisateurs.suspendre');
            Route::post('/utilisateurs/{utilisateur}/reactiver', [UtilisateurController::class, 'reactiver'])->name('utilisateurs.reactiver');
        });

        Route::middleware('can:utilisateurs.gerer')->group(function () {
            Route::get('/utilisateurs/{utilisateur}/modifier', [UtilisateurController::class, 'edit'])->name('utilisateurs.edit');
            Route::put('/utilisateurs/{utilisateur}', [UtilisateurController::class, 'update'])->name('utilisateurs.update');
            Route::delete('/utilisateurs/{utilisateur}', [UtilisateurController::class, 'destroy'])->name('utilisateurs.destroy');
            Route::post('/utilisateurs/{utilisateur}/reinitialiser-mot-de-passe', [UtilisateurController::class, 'reinitialiserMotDePasse'])->name('utilisateurs.reinitialiser-mot-de-passe');
            Route::put('/utilisateurs/{utilisateur}/roles', [UtilisateurController::class, 'roles'])->name('utilisateurs.roles');
            Route::post('/utilisateurs/{utilisateur}/lever-restriction', [UtilisateurController::class, 'leverRestriction'])->name('utilisateurs.lever-restriction');
        });

        Route::get('/entreprises', [EntrepriseController::class, 'index'])->name('entreprises.index');
        Route::get('/entreprises/{entreprise}', [EntrepriseController::class, 'show'])->name('entreprises.show');

        Route::middleware('can:entreprises.verifier')->group(function () {
            Route::post('/entreprises/{entreprise}/verifier', [EntrepriseController::class, 'verifier'])->name('entreprises.verifier');
            Route::post('/entreprises/{entreprise}/fusionner', [EntrepriseController::class, 'fusionner'])->name('entreprises.fusionner');
        });
    });

    // G — Référentiels et paramètres
    Route::middleware('can:referentiels.gerer')->group(function () {
        Route::resource('categories', CategorieController::class)->except(['show', 'destroy'])->parameters(['categories' => 'categorie']);
        Route::post('/categories/{categorie}/deplacer', [CategorieController::class, 'deplacer'])->name('categories.deplacer');
        Route::resource('communes', CommuneController::class)->except(['show', 'destroy'])->parameters(['communes' => 'commune']);
        Route::post('/communes/{commune}/deplacer', [CommuneController::class, 'deplacer'])->name('communes.deplacer');
        Route::resource('pays', PaysController::class)->except(['show', 'destroy'])->parameters(['pays' => 'pays']);
        Route::post('/pays/{pays}/deplacer', [PaysController::class, 'deplacer'])->name('pays.deplacer');
    });

    Route::middleware('can:parametres.gerer')->group(function () {
        Route::get('/parametres', [ParametreController::class, 'edit'])->name('parametres.edit');
        Route::put('/parametres', [ParametreController::class, 'update'])->name('parametres.update');
        Route::post('/cache/vider-fil', [CacheController::class, 'viderFil'])->name('cache.vider-fil');
        Route::post('/cache/vider-referentiels', [CacheController::class, 'viderReferentiels'])->name('cache.vider-referentiels');
    });

    // K — Plans et paiements
    Route::middleware('can:abonnements.gerer')->group(function () {
        Route::resource('plans', PlanController::class)->except(['show'])->parameters(['plans' => 'plan']);
        Route::resource('produits', ProduitController::class)->except(['show', 'destroy'])->parameters(['produits' => 'produit']);
        Route::get('/abonnements', [AbonnementController::class, 'index'])->name('abonnements.index');
        Route::get('/abonnements/offrir', [AbonnementController::class, 'create'])->name('abonnements.create');
        Route::post('/abonnements', [AbonnementController::class, 'store'])->name('abonnements.store');
        Route::post('/abonnements/{abonnement}/prolonger', [AbonnementController::class, 'prolonger'])->name('abonnements.prolonger');
        Route::post('/abonnements/{abonnement}/resilier', [AbonnementController::class, 'resilier'])->name('abonnements.resilier');
    });

    Route::middleware('can:paiements.voir')->group(function () {
        Route::get('/commandes', [CommandeController::class, 'index'])->name('commandes.index');
        Route::get('/commandes/export', [CommandeController::class, 'export'])->name('commandes.export');
        Route::get('/commandes/{commande}', [CommandeController::class, 'show'])->name('commandes.show');
        Route::post('/commandes/{commande}/reverifier', [CommandeController::class, 'reverifier'])->name('commandes.reverifier');
        Route::post('/commandes/{commande}/rembourser', [CommandeController::class, 'rembourser'])->name('commandes.rembourser');
        Route::get('/revenus', [CommandeController::class, 'revenus'])->name('revenus');
        Route::get('/commandes/{commande}/recu/pdf', [CommandeController::class, 'recuPdf'])->name('commandes.recu-pdf');
    });

    // L — Équipe : comptes administrateurs, rôles et permissions (D-066)
    Route::middleware('can:administrateurs.gerer')->group(function () {
        Route::resource('administrateurs', AdministrateurController::class)->except(['show'])->parameters(['administrateurs' => 'administrateur']);
        Route::post('/administrateurs/{administrateur}/reinitialiser-mot-de-passe', [AdministrateurController::class, 'reinitialiserMotDePasse'])->name('administrateurs.reinitialiser-mot-de-passe');
        Route::post('/administrateurs/{administrateur}/suspendre', [AdministrateurController::class, 'suspendre'])->name('administrateurs.suspendre');
        Route::post('/administrateurs/{administrateur}/reactiver', [AdministrateurController::class, 'reactiver'])->name('administrateurs.reactiver');
    });

    Route::middleware('can:roles.gerer')->group(function () {
        Route::resource('roles', RoleController::class)->except(['show'])->parameters(['roles' => 'role']);
    });

});
