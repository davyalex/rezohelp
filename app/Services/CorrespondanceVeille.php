<?php

namespace App\Services;

use App\Enums\NiveauConfiance;
use App\Enums\TypeContrat;
use App\Enums\TypePublication;
use App\Enums\UniteTarif;
use App\Models\SourceVeille;
use App\Support\UrlSource;
use Illuminate\Support\Str;

/**
 * Rattache une ligne de la veille aux référentiels (CONTEXTE §12) : type depuis la colonne
 * « Catégorie », catégorie fine depuis « Domaine », commune depuis « Lieu », source par domaine
 * (sous-domaines compris, sinon « Inconnue »), et lecture de « Contrat/Durée/Prix ».
 */
class CorrespondanceVeille
{
    public const string SOURCE_INCONNUE = 'Inconnue';

    public function __construct(private readonly Referentiels $referentiels) {}

    /**
     * « Offre d'emploi » → emploi, « Home service / petits métiers » → service, « Formation » → formation.
     */
    public function type(string $categorie): ?TypePublication
    {
        $texte = Str::slug($categorie);

        return match (true) {
            str_contains($texte, 'emploi') || str_contains($texte, 'recrutement') => TypePublication::Emploi,
            str_contains($texte, 'formation') => TypePublication::Formation,
            str_contains($texte, 'service') || str_contains($texte, 'metier') => TypePublication::Service,
            default => null,
        };
    }

    /**
     * Catégorie active du type dont le nom correspond au domaine (exact, puis l'un contient l'autre).
     */
    public function categorie(TypePublication $type, string $domaine): ?int
    {
        $cible = Str::slug($domaine);

        if ($cible === '') {
            return null;
        }

        $candidates = array_filter($this->referentiels->categories(), fn (array $c): bool => $c['type'] === $type->value);

        foreach ($candidates as $categorie) {
            if (Str::slug($categorie['nom']) === $cible) {
                return $categorie['id'];
            }
        }

        foreach ($candidates as $categorie) {
            $nom = Str::slug($categorie['nom']);

            if (str_contains($nom, $cible) || str_contains($cible, $nom)) {
                return $categorie['id'];
            }
        }

        return null;
    }

    /**
     * Catégorie active du type portant exactement ce nom (catégorie fine proposée par l'IA).
     */
    public function categorieParNom(TypePublication $type, string $nom): ?int
    {
        foreach ($this->referentiels->categories() as $categorie) {
            if ($categorie['type'] === $type->value && Str::slug($categorie['nom']) === Str::slug($nom)) {
                return $categorie['id'];
            }
        }

        return null;
    }

    /**
     * Première commune active citée dans le lieu (« Abidjan - Cocody, Riviera » → Cocody).
     */
    public function commune(string $lieu): ?int
    {
        $texte = '-'.Str::slug($lieu).'-';

        foreach ($this->referentiels->communes() as $commune) {
            if (str_contains($texte, '-'.$commune['slug'].'-')) {
                return $commune['id'];
            }
        }

        return null;
    }

    /**
     * Source de veille d'une URL normalisée : domaine exact ou sous-domaine, sinon « Inconnue » (confiance basse).
     */
    public function source(string $urlNormalisee): SourceVeille
    {
        $hote = UrlSource::hote($urlNormalisee);

        $source = SourceVeille::query()
            ->whereNotNull('domaine')
            ->get(['id', 'nom', 'domaine', 'niveau_confiance', 'publication_auto', 'actif'])
            ->filter(fn (SourceVeille $s): bool => $hote === $s->domaine || str_ends_with($hote, '.'.$s->domaine))
            ->sortByDesc(fn (SourceVeille $s): int => strlen((string) $s->domaine))
            ->first();

        return $source ?? SourceVeille::query()->firstOrCreate(
            ['nom' => self::SOURCE_INCONNUE, 'domaine' => null],
            ['niveau_confiance' => NiveauConfiance::Bas, 'publication_auto' => false, 'actif' => true],
        );
    }

    public function typeContrat(string $conditions): ?TypeContrat
    {
        $texte = '-'.Str::slug($conditions).'-';

        return match (true) {
            str_contains($texte, '-cdi-') => TypeContrat::Cdi,
            str_contains($texte, '-cdd-') => TypeContrat::Cdd,
            str_contains($texte, '-stage') => TypeContrat::Stage,
            str_contains($texte, '-interim') => TypeContrat::Interim,
            str_contains($texte, 'freelance') || str_contains($texte, 'consultant') || str_contains($texte, 'mission') => TypeContrat::Freelance,
            trim($conditions) === '' => null,
            default => TypeContrat::Autre,
        };
    }

    /**
     * Montant en FCFA cité dans le texte (« 5 000 FCFA », « 150.000 F ») ; 0 si « gratuit ».
     */
    public function montant(string $conditions): ?int
    {
        if (str_contains(Str::slug($conditions), 'gratuit')) {
            return 0;
        }

        if (preg_match('/(\d{1,3}(?:[\s.\x{00A0}\x{202F}]\d{3})+|\d+)\s*(?:fcfa|f\b|xof)/iu', $conditions, $resultat) === 1) {
            return (int) preg_replace('/\D/', '', $resultat[1]);
        }

        return null;
    }

    public function uniteTarif(string $conditions): ?UniteTarif
    {
        $texte = Str::slug($conditions);

        return match (true) {
            str_contains($texte, 'heure') => UniteTarif::Heure,
            str_contains($texte, 'jour') => UniteTarif::Jour,
            str_contains($texte, 'prestation') || str_contains($texte, 'intervention') => UniteTarif::Prestation,
            str_contains($texte, 'devis') => UniteTarif::Devis,
            default => null,
        };
    }
}
