<?php

use App\Contracts\FournisseurSms;
use Database\Seeders\PaysSeeder;
use Database\Seeders\RolesEtPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FournisseurSmsEnMemoire;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

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
 * Pays et rôles nécessaires à l'inscription.
 */
function referentielsAuth(): void
{
    test()->seed([PaysSeeder::class, RolesEtPermissionsSeeder::class]);
}
