<?php

use App\Http\Controllers\AlerteController;
use App\Http\Controllers\Auth\ChangementMotDePasseController;
use App\Http\Controllers\Auth\MotDePasseOublieController;
use App\Http\Controllers\Auth\VerificationTelephoneController;
use App\Http\Controllers\Espace\EspaceController;
use App\Http\Controllers\Espace\NotificationController;
use App\Http\Controllers\Espace\ProfilController;
use App\Http\Controllers\Espace\PublicationController as EspacePublicationController;
use App\Http\Controllers\Espace\ReponseController as EspaceReponseController;
use App\Http\Controllers\FilController;
use App\Http\Controllers\InteractionController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\PublierController;
use App\Http\Controllers\ReferencementController;
use App\Http\Controllers\ReponseController;
use Illuminate\Support\Facades\Route;

// Fil public (CONTEXTE §5, §8)
Route::get('/', [FilController::class, 'index'])->name('fil.index');
Route::get('/emplois', [FilController::class, 'index'])->defaults('type', 'emploi')->name('fil.emplois');
Route::get('/services', [FilController::class, 'index'])->defaults('type', 'service')->name('fil.services');
Route::get('/formations', [FilController::class, 'index'])->defaults('type', 'formation')->name('fil.formations');
Route::get('/recherche', [FilController::class, 'index'])->name('fil.recherche');
Route::get('/fil/suite', [FilController::class, 'suite'])->name('fil.suite');
Route::get('/fil/nouvelles', [FilController::class, 'nouvelles'])->middleware('throttle:30,1')->name('fil.nouvelles');

// Publications
Route::get('/publication/{slug}', [PublicationController::class, 'show'])->name('publications.show');
Route::get('/publication/{slug}/partager', [PublicationController::class, 'partager'])->middleware('throttle:30,1')->name('publications.partager');
Route::get('/p/{code}', [PublicationController::class, 'lienCourt'])->where('code', '[a-z0-9]{6}')->name('publications.court');

Route::middleware(['auth', 'throttle:60,1'])->group(function () {
    Route::post('/publication/{publication}/jaime', [InteractionController::class, 'jaime'])->name('publications.jaime');
    Route::post('/publication/{publication}/enregistrer', [InteractionController::class, 'enregistrer'])->name('publications.enregistrer');
    Route::post('/publication/{publication}/commentaires', [InteractionController::class, 'commenter'])->name('publications.commenter');
    Route::delete('/commentaires/{commentaire}', [InteractionController::class, 'supprimerCommentaire'])->name('commentaires.destroy');
    Route::post('/publication/{publication}/signaler', [InteractionController::class, 'signaler'])->name('publications.signaler');
});

// « Créer une alerte » depuis le fil : un visiteur passe d'abord par l'inscription.
Route::get('/alertes/nouvelle', [AlerteController::class, 'depuisFil'])->name('alertes.depuis-fil');

Route::get('/publication/{slug}/contact', [PublicationController::class, 'contactExterne'])->middleware('throttle:30,1')->name('publications.contact');
Route::post('/publication/{publication:slug}/repondre', [ReponseController::class, 'store'])
    ->middleware(['auth', 'throttle:20,1'])->name('publications.repondre');

// Publication par les membres (CONTEXTE §11.3, §11.9)
Route::middleware('auth')->group(function () {
    $parcours = ['emploi', 'service-offre', 'service-demande', 'formation'];

    Route::get('/publier', [PublierController::class, 'choix'])->name('publier.choix');
    Route::get('/publier/confirmation/{publication}', [PublierController::class, 'confirmation'])->name('publier.confirmation');
    Route::get('/publier/{type}', [PublierController::class, 'formulaire'])->whereIn('type', $parcours)->name('publier.formulaire');
    Route::post('/publier/{type}/apercu', [PublierController::class, 'apercu'])
        ->whereIn('type', $parcours)->middleware('throttle:30,1')->name('publier.apercu');
    Route::post('/publier/{type}', [PublierController::class, 'store'])
        ->whereIn('type', $parcours)->middleware('throttle:publication')->name('publier.store');

    // Alertes (CONTEXTE §10)
    Route::prefix('mon-espace/alertes')->name('alertes.')->group(function () {
        Route::get('/', [AlerteController::class, 'index'])->name('index');
        Route::get('/creer', [AlerteController::class, 'create'])->name('create');
        Route::post('/', [AlerteController::class, 'store'])->middleware('throttle:20,1')->name('store');
        Route::get('/{alerte}/modifier', [AlerteController::class, 'edit'])->name('edit');
        Route::put('/{alerte}', [AlerteController::class, 'update'])->name('update');
        Route::post('/{alerte}/basculer', [AlerteController::class, 'basculer'])->name('basculer');
        Route::delete('/{alerte}', [AlerteController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('mon-espace')->name('espace.')->group(function () {
        Route::get('/', [EspaceController::class, 'index'])->name('index');
        Route::get('/enregistres', [EspaceController::class, 'enregistres'])->name('enregistres');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
        Route::post('/notifications/tout-lu', [NotificationController::class, 'toutLu'])->name('notifications.tout-lu');
        Route::get('/notifications/{notification}', [NotificationController::class, 'ouvrir'])->whereUuid('notification')->name('notifications.ouvrir');

        Route::get('/profil', [ProfilController::class, 'edit'])->name('profil');
        Route::put('/profil', [ProfilController::class, 'update'])->name('profil.update');
        Route::delete('/profil', [ProfilController::class, 'supprimer'])->name('profil.supprimer');
        Route::get('/profil/numero', [ProfilController::class, 'numero'])->name('numero');
        Route::post('/profil/numero', [ProfilController::class, 'envoyerCode'])->middleware('throttle:otp')->name('numero.envoyer');
        Route::post('/profil/numero/confirmer', [ProfilController::class, 'confirmerNumero'])->middleware('throttle:otp')->name('numero.confirmer');

        Route::get('/publications', [EspacePublicationController::class, 'index'])->name('publications.index');
        Route::get('/publications/{publication}/modifier', [EspacePublicationController::class, 'edit'])->name('publications.edit');
        Route::put('/publications/{publication}/modifier', [EspacePublicationController::class, 'update'])
            ->middleware('throttle:publication')->name('publications.update');
        Route::post('/publications/{publication}/cloturer', [EspacePublicationController::class, 'cloturer'])->name('publications.cloturer');
        Route::post('/publications/{publication}/prolonger', [EspacePublicationController::class, 'prolonger'])->name('publications.prolonger');
        Route::post('/publications/{publication}/dupliquer', [EspacePublicationController::class, 'dupliquer'])->name('publications.dupliquer');
        Route::delete('/publications/{publication}', [EspacePublicationController::class, 'destroy'])->name('publications.destroy');
        Route::get('/publications/{publication}/reponses', [EspaceReponseController::class, 'index'])->name('reponses.index');
        Route::patch('/reponses/{reponse}', [EspaceReponseController::class, 'update'])->name('reponses.update');
    });
});

// Référencement
Route::get('/sitemap.xml', [ReferencementController::class, 'sitemap'])->name('referencement.sitemap');
Route::get('/robots.txt', [ReferencementController::class, 'robots'])->name('referencement.robots');

// Authentification : /connexion, /inscription et /deconnexion sont fournies par Fortify.
Route::middleware('guest')->group(function () {
    Route::get('/mot-de-passe-oublie', [MotDePasseOublieController::class, 'demande'])->name('mot-de-passe.oublie');
    Route::post('/mot-de-passe-oublie', [MotDePasseOublieController::class, 'envoyerCode'])
        ->middleware('throttle:otp')->name('mot-de-passe.oublie.envoyer');
    Route::get('/reinitialiser-mot-de-passe', [MotDePasseOublieController::class, 'formulaire'])->name('mot-de-passe.reinitialisation');
    Route::post('/reinitialiser-mot-de-passe', [MotDePasseOublieController::class, 'reinitialiser'])
        ->middleware('throttle:otp')->name('mot-de-passe.reinitialiser');
});

Route::middleware('auth')->group(function () {
    Route::get('/verification-telephone', [VerificationTelephoneController::class, 'afficher'])->name('telephone.verification');
    Route::post('/verification-telephone/code', [VerificationTelephoneController::class, 'envoyer'])
        ->middleware('throttle:otp')->name('telephone.verification.envoyer');
    Route::post('/verification-telephone', [VerificationTelephoneController::class, 'verifier'])
        ->middleware('throttle:otp')->name('telephone.verification.verifier');

    Route::get('/changer-mot-de-passe', [ChangementMotDePasseController::class, 'formulaire'])->name('mot-de-passe.changement');
    Route::put('/changer-mot-de-passe', [ChangementMotDePasseController::class, 'changer'])->name('mot-de-passe.changer');
});
