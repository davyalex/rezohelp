<?php

namespace App\Services;

use App\Actions\Publication\AnalyserRisquePublication;
use App\Enums\TypePublication;
use App\Models\Alerte;
use App\Models\Publication;
use Illuminate\Database\Eloquent\Builder;

/**
 * Règles de correspondance entre une alerte et une publication (CONTEXTE §10).
 * Un critère vide accepte tout ; une alerte sur une catégorie couvre ses sous-catégories ;
 * tous les mots-clés doivent apparaître (début de mot) dans le titre ou le contenu.
 */
class CorrespondanceAlertes
{
    public function __construct(private readonly Referentiels $referentiels) {}

    /**
     * Alertes actives dont les critères structurés correspondent (les mots-clés sont vérifiés à part).
     *
     * @return Builder<Alerte>
     */
    public function candidates(Publication $publication): Builder
    {
        $categories = $this->categoriesCouvrantes($publication->categorie_id);

        return Alerte::query()
            ->where('active', true)
            ->where(fn (Builder $requete) => $requete->whereNull('type')->orWhere('type', $publication->type->value))
            ->where(fn (Builder $requete) => $requete->whereNull('categorie_id')->orWhereIn('categorie_id', $categories))
            ->where(fn (Builder $requete) => $requete->whereNull('commune_id')->orWhere('commune_id', $publication->commune_id))
            ->where(fn (Builder $requete) => $requete->whereNull('type_contrat')->orWhere('type_contrat', $publication->type_contrat?->value))
            ->when($publication->auteur_id !== null, fn (Builder $requete) => $requete->where('utilisateur_id', '!=', $publication->auteur_id))
            // Comptes actifs : jamais suspendus, ou dont la suspension temporaire est terminée.
            ->whereHas('utilisateur', fn (Builder $utilisateur) => $utilisateur->where(fn (Builder $actif) => $actif
                ->whereNull('suspendu_le')
                ->orWhere(fn (Builder $fini) => $fini->whereNotNull('suspendu_jusqu_au')->where('suspendu_jusqu_au', '<=', now()))))
            ->whereNotExists(fn ($deja) => $deja->selectRaw('1')
                ->from('alertes_publications')
                ->whereColumn('alertes_publications.alerte_id', 'alertes.id')
                ->where('alertes_publications.publication_id', $publication->id));
    }

    public function motsClesCorrespondent(Alerte $alerte, Publication $publication): bool
    {
        $motsCles = trim((string) $alerte->mots_cles);

        if ($motsCles === '') {
            return true;
        }

        $texte = AnalyserRisquePublication::normaliser($publication->titre.' '.$publication->contenu);
        $mots = preg_split('/[^\p{L}\p{N}]+/u', AnalyserRisquePublication::normaliser($motsCles), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($mots as $mot) {
            if (preg_match('/\b'.preg_quote($mot, '/').'/u', $texte) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * La catégorie de la publication et sa catégorie parente.
     *
     * @return list<int>
     */
    private function categoriesCouvrantes(?int $categorieId): array
    {
        if ($categorieId === null) {
            return [];
        }

        $parent = $this->referentiels->categories()[$categorieId]['parent_id'] ?? null;

        return array_values(array_filter([$categorieId, $parent]));
    }

    /**
     * URL du fil correspondant aux critères d'une alerte (« Voir les résultats »).
     */
    public function urlFil(Alerte $alerte): string
    {
        $parametres = array_filter([
            'commune' => $this->referentiels->communes()[$alerte->commune_id]['slug'] ?? null,
            'categorie' => $alerte->categorie_id !== null ? ($this->referentiels->categories()[$alerte->categorie_id]['slug'] ?? null) : null,
            'contrat' => $alerte->type_contrat?->value,
            'q' => $alerte->mots_cles,
        ]);

        return match ($alerte->type) {
            TypePublication::Emploi => route('fil.emplois', $parametres),
            TypePublication::Service => route('fil.services', $parametres),
            TypePublication::Formation => route('fil.formations', $parametres),
            null => route(isset($parametres['q']) ? 'fil.recherche' : 'fil.index', $parametres),
        };
    }
}
