<?php

namespace App\Services;

use App\Donnees\FiltresFil;
use App\Enums\StatutPublication;
use App\Enums\TypeMiseEnAvant;
use App\Models\MiseEnAvant;
use App\Models\Parametre;
use App\Models\Publication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Visibilité payante dans le fil (CONTEXTE §14.9), toujours hors curseur :
 *
 * - « Sponsorisé » : sur le fil général (sans filtre autre que l'onglet de type), une carte
 *   injectée avant la 1re carte puis toutes les frequence_sponsorise cartes. Le client indique
 *   combien de cartes il affiche déjà (?deja=N) ; la salve paginée n'est jamais modifiée.
 * - « À la une » : jusqu'à max_cartes_une cartes au-dessus d'un fil filtré sur la commune
 *   ou la catégorie de la mise en avant.
 *
 * Une même carte au plus une fois par salve, jamais une carte déjà présente dans la salve ;
 * rotation : la mise en avant affichée le moins récemment passe en premier.
 * Seules les publications en ligne sont mises en avant.
 */
class EmplacementsSponsorises
{
    public function __construct(private readonly CacheFil $cache) {}

    /**
     * Cartes sponsorisées à insérer dans une salve.
     *
     * @param  Collection<int, Publication>  $salve  cartes paginées, dans l'ordre
     * @return array<int, MiseEnAvant> rang dans la salve avant lequel insérer => mise en avant (publication chargée)
     */
    public function pourSalve(FiltresFil $filtres, Collection $salve, int $deja): array
    {
        if (! $this->estFilGeneral($filtres) || $salve->isEmpty()) {
            return [];
        }

        $frequence = max(1, Parametre::entier('frequence_sponsorise'));
        $rangs = [];

        foreach ($salve->keys() as $rang) {
            if (($deja + $rang) % $frequence === 0) {
                $rangs[] = $rang;
            }
        }

        if ($rangs === []) {
            return [];
        }

        $mises = $this->actives(TypeMiseEnAvant::SponsoriseFil, $filtres)
            ->whereNotIn('publication_id', $salve->pluck('id'))
            ->limit(count($rangs))
            ->get()
            ->unique('publication_id')
            ->values();

        $this->compterImpressions($mises);

        return array_combine(array_slice($rangs, 0, $mises->count()), $mises->all());
    }

    /**
     * Bloc « À la une » d'un fil filtré sur une commune ou une catégorie (première salve).
     *
     * @return EloquentCollection<int, MiseEnAvant>
     */
    public function aLaUne(FiltresFil $filtres): EloquentCollection
    {
        if (($filtres->communeId === null && $filtres->categorieIds === []) || $filtres->recherche !== null) {
            return new EloquentCollection;
        }

        $mises = $this->actives(TypeMiseEnAvant::UneCommune, $filtres)
            ->when($filtres->communeId !== null, fn (Builder $requete) => $requete->where('commune_id', $filtres->communeId))
            ->when($filtres->categorieIds !== [], fn (Builder $requete) => $requete
                ->whereHas('publication', fn (Builder $publication) => $publication->whereIn('categorie_id', $filtres->categorieIds)))
            ->limit(max(0, Parametre::entier('max_cartes_une')))
            ->get()
            ->unique('publication_id')
            ->values();

        $this->compterImpressions($mises);

        return $mises;
    }

    public function estFilGeneral(FiltresFil $filtres): bool
    {
        return $filtres->communeId === null
            && $filtres->categorieIds === []
            && $filtres->typeContrat === null
            && ! $filtres->aujourdhui
            && $filtres->recherche === null;
    }

    /**
     * Clic sur une carte mise en avant (cumulé en cache, reporté en base toutes les 5 min).
     */
    public function compterClic(MiseEnAvant $mise): void
    {
        $enCours = $mise->type !== TypeMiseEnAvant::Remontee && $mise->suspendue_le === null && $mise->fin_le?->isFuture();

        if ($enCours) {
            $this->cache->incrementerCompteurDiffere(CacheFil::COMPTEUR_CLICS, $mise->id);
        }
    }

    /**
     * @return Builder<MiseEnAvant>
     */
    private function actives(TypeMiseEnAvant $type, FiltresFil $filtres): Builder
    {
        $avecPublication = fn (Builder $publication) => $publication->where('statut', StatutPublication::Publiee->value)
            ->when($filtres->type !== null, fn (Builder $requete) => $requete->where('type', $filtres->type?->value));

        return MiseEnAvant::query()
            ->where('type', $type->value)
            ->whereNull('suspendue_le')
            ->where('debut_le', '<=', now())
            ->where('fin_le', '>', now())
            ->whereHas('publication', $avecPublication)
            ->with(['publication' => fn ($publication) => $publication->select(FilService::COLONNES_CARTE)])
            ->orderByRaw('derniere_impression_le is null desc')
            ->orderBy('derniere_impression_le')
            ->orderBy('id');
    }

    /**
     * @param  Collection<int, MiseEnAvant>  $mises
     */
    private function compterImpressions(Collection $mises): void
    {
        if ($mises->isEmpty()) {
            return;
        }

        MiseEnAvant::query()->whereKey($mises->pluck('id'))->update(['derniere_impression_le' => now()]);

        foreach ($mises as $mise) {
            $this->cache->incrementerCompteurDiffere(CacheFil::COMPTEUR_IMPRESSIONS, $mise->id);
        }
    }
}
