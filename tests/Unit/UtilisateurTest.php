<?php

use App\Enums\ConfianceUtilisateur;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

it('est le modèle d’authentification, sur la table utilisateurs', function () {
    expect(config('auth.providers.users.model'))->toBe(Utilisateur::class)
        ->and((new Utilisateur)->getTable())->toBe('utilisateurs');
});

it('hache le mot de passe dans la colonne mot_de_passe et authentifie avec', function () {
    $utilisateur = Utilisateur::query()->create([
        'nom' => 'Koné',
        'prenoms' => 'Awa',
        'telephone' => '+2250708091011',
        'pays_code' => 'CI',
        'mot_de_passe' => 'mot-de-passe-solide',
    ])->refresh();

    expect($utilisateur->getAuthPasswordName())->toBe('mot_de_passe')
        ->and(Hash::check('mot-de-passe-solide', $utilisateur->getAuthPassword()))->toBeTrue()
        ->and($utilisateur->confiance)->toBe(ConfianceUtilisateur::Nouveau)
        ->and($utilisateur->cree_le)->not->toBeNull()
        ->and(Auth::validate(['telephone' => '+2250708091011', 'password' => 'mot-de-passe-solide']))->toBeTrue()
        ->and(Auth::validate(['telephone' => '+2250708091011', 'password' => 'faux']))->toBeFalse();
});

it('ne sérialise jamais le téléphone ni le mot de passe', function () {
    $utilisateur = new Utilisateur(['nom' => 'Koné', 'telephone' => '+2250708091011', 'mot_de_passe' => 'secret123']);

    expect($utilisateur->toArray())->not->toHaveKeys(['telephone', 'mot_de_passe']);
});
