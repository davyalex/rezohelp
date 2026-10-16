<?php

use App\Http\Controllers\Auth\ChangementMotDePasseController;
use App\Http\Controllers\Auth\MotDePasseOublieController;
use App\Http\Controllers\Auth\VerificationTelephoneController;
use App\Http\Controllers\FilController;
use App\Http\Controllers\InteractionController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\ReferencementController;
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
