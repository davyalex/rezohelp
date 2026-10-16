<?php

namespace App\Actions\Publication;

use App\Enums\StatutPublication;
use App\Models\Parametre;
use App\Models\Publication;
use App\Models\Utilisateur;
use Illuminate\Validation\ValidationException;

/**
 * Anti-doublon (CONTEXTE §11.5) : même auteur et titre très proche
 * (similarité ≥ seuil_similarite_doublon) dans les jours_doublon derniers jours.
 */
class DetecterDoublon
{
    public function trouver(Utilisateur $auteur, string $titre, ?int $saufPublicationId = null): ?Publication
    {
        $seuil = Parametre::entier('seuil_similarite_doublon');
        $titreNormalise = self::normaliser($titre);

        return Publication::query()
            ->where('auteur_id', $auteur->id)
            ->whereNotIn('statut', [StatutPublication::Brouillon->value, StatutPublication::Rejetee->value])
            ->where('cree_le', '>=', now()->subDays(Parametre::entier('jours_doublon')))
            ->when($saufPublicationId !== null, fn ($requete) => $requete->whereKeyNot($saufPublicationId))
            ->get(['id', 'titre', 'slug'])
            ->first(function (Publication $publication) use ($titreNormalise, $seuil): bool {
                similar_text($titreNormalise, self::normaliser($publication->titre), $pourcentage);

                return $pourcentage >= $seuil;
            });
    }

    /**
     * @throws ValidationException
     */
    public function verifierOuEchouer(Utilisateur $auteur, string $titre, ?int $saufPublicationId = null): void
    {
        $doublon = $this->trouver($auteur, $titre, $saufPublicationId);

        if ($doublon !== null) {
            throw ValidationException::withMessages([
                'titre' => 'Vous avez déjà publié « '.$doublon->titre.' » il y a moins de '.Parametre::entier('jours_doublon')
                    .' jours. Prolongez cette annonce depuis « Mes publications » plutôt que d’en publier une nouvelle.',
            ]);
        }
    }

    private static function normaliser(string $titre): string
    {
        return trim((string) preg_replace('/\s+/', ' ', AnalyserRisquePublication::normaliser($titre)));
    }
}
