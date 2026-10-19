<?php

namespace App\Services;

use App\Enums\TypePublication;
use App\Models\Parametre;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Support\JetonsCarte;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;

/**
 * Rend les cartes du fil : gabarit partagé en cache (carte:{id}:{modifie_le}),
 * puis remplacement des jetons par les compteurs frais et l'état du visiteur.
 */
class RenduCarte
{
    /**
     * Publications aimées et enregistrées par le visiteur, pour les cartes affichées.
     *
     * @var array{aimees: array<int, true>, enregistrees: array<int, true>}
     */
    private array $etat = ['aimees' => [], 'enregistrees' => []];

    public function __construct(private readonly CacheFil $cache) {}

    /**
     * Charge en une seule requête l'état du visiteur pour les publications visibles.
     *
     * @param  iterable<Publication>  $publications
     */
    public function chargerEtat(?Utilisateur $visiteur, iterable $publications): static
    {
        $this->etat = ['aimees' => [], 'enregistrees' => []];
        $ids = collect($publications)->pluck('id')->filter()->all();

        if ($visiteur === null || $ids === []) {
            return $this;
        }

        $lignes = DB::table('reactions')->select('publication_id', DB::raw("'aimees' as nature"))
            ->where('utilisateur_id', $visiteur->id)->whereIn('publication_id', $ids)
            ->unionAll(
                DB::table('enregistrements')->select('publication_id', DB::raw("'enregistrees' as nature"))
                    ->where('utilisateur_id', $visiteur->id)->whereIn('publication_id', $ids)
            )
            ->get();

        foreach ($lignes as $ligne) {
            $this->etat[$ligne->nature === 'aimees' ? 'aimees' : 'enregistrees'][(int) $ligne->publication_id] = true;
        }

        return $this;
    }

    public function aime(Publication $publication): bool
    {
        return isset($this->etat['aimees'][$publication->id]);
    }

    public function estEnregistree(Publication $publication): bool
    {
        return isset($this->etat['enregistrees'][$publication->id]);
    }

    public function rendre(Publication $publication): string
    {
        $gabarit = $publication->exists && $publication->modifie_le !== null
            ? $this->cache->carte($publication->id, $publication->modifie_le->getTimestamp(), fn (): string => $this->gabarit($publication))
            : $this->gabarit($publication);

        return strtr($gabarit, [
            JetonsCarte::NB_JAIME => (string) $publication->nb_jaime,
            JetonsCarte::NB_COMMENTAIRES => (string) $publication->nb_commentaires,
            JetonsCarte::AIME => isset($this->etat['aimees'][$publication->id]) ? 'true' : 'false',
            JetonsCarte::ENREGISTRE => isset($this->etat['enregistrees'][$publication->id]) ? 'true' : 'false',
            JetonsCarte::DEPUIS => e(($publication->tri_le ?? $publication->publiee_le ?? now())->diffForHumans(['short' => true])),
            JetonsCarte::CSRF => csrf_token(),
            JetonsCarte::PREVENTION => $publication->type === TypePublication::Emploi ? $this->bandeauPrevention() : '',
        ]);
    }

    private function gabarit(Publication $publication): string
    {
        return Blade::render('<x-carte-publication :publication="$publication" />', ['publication' => $publication]);
    }

    private function bandeauPrevention(): string
    {
        return '<p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900"><span aria-hidden="true">⚠️</span> '
            .e(Parametre::texte('texte_bandeau_prevention')).'</p>';
    }
}
