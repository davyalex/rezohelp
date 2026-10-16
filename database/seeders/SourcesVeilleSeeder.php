<?php

namespace Database\Seeders;

use App\Enums\NiveauConfiance;
use App\Models\SourceVeille;
use Illuminate\Database\Seeder;

/**
 * Sources de la veille IA et leur niveau de confiance (§12, §13.E).
 * La source « Inconnue » (sans domaine) reçoit les lignes non rattachées.
 */
class SourcesVeilleSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['Inconnue', null, NiveauConfiance::Bas, false],
            ['Emploi.ci', 'emploi.ci', NiveauConfiance::Haut, true],
            ['Educarrière', 'educarriere.ci', NiveauConfiance::Haut, true],
            ['Agence Emploi Jeunes', 'agenceemploijeunes.ci', NiveauConfiance::Haut, true],
            ['Orange Digital Center', 'orangedigitalcenters.com', NiveauConfiance::Haut, true],
            ['Novojob', 'novojob.com', NiveauConfiance::Moyen, false],
            ['LinkedIn', 'linkedin.com', NiveauConfiance::Moyen, false],
            ['Facebook', 'facebook.com', NiveauConfiance::Bas, false],
        ];

        foreach ($sources as [$nom, $domaine, $confiance, $publicationAuto]) {
            SourceVeille::query()->firstOrCreate(
                ['nom' => $nom],
                ['domaine' => $domaine, 'niveau_confiance' => $confiance, 'publication_auto' => $publicationAuto, 'actif' => true],
            );
        }
    }
}
