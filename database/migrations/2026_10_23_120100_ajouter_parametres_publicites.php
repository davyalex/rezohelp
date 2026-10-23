<?php

use App\Enums\TypeParametre;
use App\Models\Parametre;
use Illuminate\Database\Migrations\Migration;

/**
 * Réglages des publicités (D-074) : présents dès la migration, car le site public les lit à chaque page.
 * Un déploiement (migrate --force) suffit donc, sans relancer le seeder. Passer par le modèle déclenche
 * ReferentielObserver, qui vide le cache « ref:parametres » : la nouvelle liste est lue tout de suite.
 */
return new class extends Migration
{
    /** @var array<string, array{0: TypeParametre, 1: string, 2: string}> */
    private const array PARAMETRES = [
        'publicites_actives' => [TypeParametre::Booleen, '1', 'Publicités affichées sur le site (bannières et « Annoncez ici »)'],
        'publicite_contact' => [TypeParametre::Texte, '', 'Lien du bouton « Annoncez ici » : mailto:adresse, https://page ou /chemin (vide = page Tarifs)'],
    ];

    public function up(): void
    {
        foreach (self::PARAMETRES as $cle => [$type, $valeur, $libelle]) {
            $parametre = Parametre::query()->firstOrNew(['cle' => $cle]);
            if (! $parametre->exists) {
                $parametre->fill(['type' => $type, 'libelle' => $libelle]);
                $parametre->valeur = $valeur;
                $parametre->save();
            }
        }
    }

    public function down(): void
    {
        Parametre::query()->whereIn('cle', array_keys(self::PARAMETRES))->get()->each->delete();
    }
};
