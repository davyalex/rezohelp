<?php

use App\Http\Controllers\Auth\ChangementMotDePasseController;
use App\Http\Controllers\Auth\MotDePasseOublieController;
use App\Http\Controllers\Auth\VerificationTelephoneController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'accueil')->name('accueil');

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
