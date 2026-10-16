<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Référentiels, rôles, paramètres, plans et compte administrateur.
     * Les données de démonstration : php artisan db:seed --class=DemoSeeder (local).
     */
    public function run(): void
    {
        $this->call([
            PaysSeeder::class,
            CommunesSeeder::class,
            CategoriesSeeder::class,
            RolesEtPermissionsSeeder::class,
            ParametresSeeder::class,
            PlansSeeder::class,
            SourcesVeilleSeeder::class,
            AdministrateurSeeder::class,
        ]);
    }
}
