<?php

use App\Contracts\FournisseurSms;
use App\Models\Parametre;
use Database\Seeders\CategoriesSeeder;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\PaysSeeder;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesEtPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FournisseurSmsEnMemoire;
use Tests\TestCase;

require_once __DIR__.'/Support/donnees-publication.php';

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

// Recherche FULLTEXT : InnoDB n'indexe les lignes qu'au COMMIT, donc pas de transaction ici.
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Recherche');

/**
 * Remplace le fournisseur de SMS par un double en mémoire et le renvoie.
 */
function fauxSms(): FournisseurSmsEnMemoire
{
    $faux = new FournisseurSmsEnMemoire;
    app()->instance(FournisseurSms::class, $faux);

    return $faux;
}

/**
 * Référentiels, paramètres et plans nécessaires pour publier.
 */
function referentielsPublication(): void
{
    test()->seed([
        PaysSeeder::class,
        CommunesSeeder::class,
        CategoriesSeeder::class,
        RolesEtPermissionsSeeder::class,
        ParametresSeeder::class,
        PlansSeeder::class,
    ]);
}

/**
 * Change la valeur d'un paramètre (le cache ref:parametres est invalidé par l'observer).
 */
function parametre(string $cle, string $valeur): void
{
    Parametre::query()->whereKey($cle)->firstOrFail()->update(['valeur' => $valeur]);
}

/**
 * Pays, rôles et paramètres nécessaires à l'inscription.
 */
function referentielsAuth(): void
{
    test()->seed([PaysSeeder::class, RolesEtPermissionsSeeder::class, ParametresSeeder::class]);
}
