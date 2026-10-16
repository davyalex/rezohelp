<?php

namespace Database\Seeders;

use App\Models\Commune;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Les 13 communes du district d'Abidjan (CONTEXTE §5).
 */
class CommunesSeeder extends Seeder
{
    public const array COMMUNES = [
        'Cocody',
        'Yopougon',
        'Plateau',
        'Abobo',
        'Marcory',
        'Koumassi',
        'Treichville',
        'Adjamé',
        'Port-Bouët',
        'Attécoubé',
        'Bingerville',
        'Songon',
        'Anyama',
    ];

    public function run(): void
    {
        foreach (self::COMMUNES as $ordre => $nom) {
            Commune::query()->updateOrCreate(
                ['slug' => Str::slug($nom)],
                ['pays_code' => 'CI', 'ville' => 'Abidjan', 'nom' => $nom, 'actif' => true, 'ordre' => $ordre + 1],
            );
        }
    }
}
