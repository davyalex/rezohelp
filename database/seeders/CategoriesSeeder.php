<?php

namespace Database\Seeders;

use App\Enums\TypePublication;
use App\Models\Categorie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Catégories et sous-catégories (métiers) des trois types de publication.
 */
class CategoriesSeeder extends Seeder
{
    /**
     * type => [nom => [icône, [sous-catégories]]]
     *
     * @var array<string, array<string, array{0: string, 1: list<string>}>>
     */
    private const array ARBRE = [
        'emploi' => [
            'Informatique et numérique' => ['💻', []],
            'Commerce et vente' => ['🛒', []],
            'Finance et comptabilité' => ['📊', []],
            'BTP et génie civil' => ['🏗️', []],
            'Santé' => ['🩺', []],
            'Éducation et enseignement' => ['📚', []],
            'Hôtellerie et restauration' => ['🍽️', []],
            'Transport et logistique' => ['🚚', []],
            'Administration et secrétariat' => ['🗂️', []],
            'Marketing et communication' => ['📣', []],
            'Industrie et production' => ['🏭', []],
            'Agriculture et agroalimentaire' => ['🌾', []],
            'Sécurité et gardiennage' => ['🛡️', []],
            'Autres métiers' => ['💼', []],
        ],
        'service' => [
            'Bâtiment et réparations' => ['🔧', ['Plomberie', 'Électricité', 'Maçonnerie', 'Peinture', 'Menuiserie', 'Climatisation', 'Serrurerie']],
            'Maison et famille' => ['🏠', ['Ménage', 'Nounou', 'Cuisine à domicile', 'Jardinage', 'Lessive et repassage']],
            'Transport et livraison' => ['🚗', ['Chauffeur', 'Déménagement', 'Livraison']],
            'Auto et moto' => ['🛠️', ['Mécanique', 'Lavage auto', 'Tôlerie']],
            'Beauté et mode' => ['✂️', ['Couture', 'Coiffure', 'Esthétique']],
            'Électronique et informatique' => ['📱', ['Réparation de téléphones', 'Dépannage informatique', 'Électroménager']],
            'Événementiel' => ['🎉', ['Traiteur', 'Décoration', 'Photographie et vidéo']],
            'Cours et soutien' => ['✏️', ['Répétiteur', 'Cours de langues']],
        ],
        'formation' => [
            'Informatique et numérique' => ['💻', []],
            'Langues' => ['🗣️', []],
            'Gestion, comptabilité et finance' => ['📊', []],
            'Métiers techniques' => ['🔩', []],
            'Santé et social' => ['🩺', []],
            'Commerce et marketing' => ['📣', []],
            'Agriculture' => ['🌾', []],
            'Développement personnel' => ['🌱', []],
        ],
    ];

    public function run(): void
    {
        foreach (self::ARBRE as $type => $categories) {
            $ordre = 0;
            foreach ($categories as $nom => [$icone, $sousCategories]) {
                $parent = $this->enregistrer(TypePublication::from($type), $nom, $icone, ++$ordre, null);

                foreach ($sousCategories as $position => $sousNom) {
                    $this->enregistrer(TypePublication::from($type), $sousNom, $icone, $position + 1, $parent->id);
                }
            }
        }
    }

    private function enregistrer(TypePublication $type, string $nom, string $icone, int $ordre, ?int $parentId): Categorie
    {
        return Categorie::query()->updateOrCreate(
            ['slug' => Str::slug($type->value.' '.$nom)],
            ['type' => $type, 'nom' => $nom, 'icone' => $icone, 'ordre' => $ordre, 'parent_id' => $parentId, 'actif' => true],
        );
    }
}
