<?php

namespace App\Services;

use App\Enums\EmplacementPublicite;
use App\Models\Parametre;
use App\Models\Publicite;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Publicités du site public (D-073, D-074). Interrupteur et lien « Annoncez ici » : paramètres
 * publicites_actives et publicite_contact, réglés dans l'admin.
 *  - Les annonces actives sont gardées en cache (« ref:publicites », invalidé par ReferentielObserver) ;
 *    la période (du / au) est vérifiée à chaque affichage, donc une campagne démarre et s'arrête d'elle-même.
 *  - Une annonce est tirée par emplacement et par page, au prorata de son poids.
 *  - Impressions (bannière réellement vue, signalée par le navigateur) et clics : compteurs différés
 *    en cache, reportés en base toutes les 5 min (publicites:compter).
 */
class Publicites
{
    /** Pages de consultation qui accueillent les marges (jamais formulaires, espace membre, admin). */
    public const array PAGES = ['fil.index', 'fil.emplois', 'fil.services', 'fil.demandes', 'fil.formations', 'fil.recherche', 'publications.show', 'tarifs'];

    public const string COMPTEUR_IMPRESSIONS = 'pub_impressions';

    public const string COMPTEUR_CLICS = 'pub_clics';

    public function __construct(private readonly CacheFil $cache) {}

    /** Liens acceptés pour « Annoncez ici » : jamais de javascript: ni d'autre schéma. */
    public const string LIEN_CONTACT_VALIDE = '#^(mailto:[^\s<>"]+|tel:\+?[0-9 ]+|https?://[^\s<>"]+|/[^\s<>"]*)$#i';

    /**
     * Interrupteur général, réglé dans l'admin (paramètre publicites_actives).
     */
    public function actives(): bool
    {
        return Parametre::booleen('publicites_actives');
    }

    /**
     * Les marges latérales sont-elles affichées sur cette page ?
     */
    public function affichees(Request $requete): bool
    {
        return $this->actives() && $requete->routeIs(...self::PAGES);
    }

    /**
     * Une annonce en cours pour l'emplacement, tirée au prorata du poids ; null s'il n'y en a pas.
     *
     * @return array{id: int, annonceur: string, texte: string, image: string, image_secondaire: ?string}|null
     */
    public function annonce(EmplacementPublicite $emplacement, ?Carbon $aujourdhui = null): ?array
    {
        if (! $this->actives()) {
            return null;
        }

        $jour = ($aujourdhui ?? Carbon::today())->toDateString();
        $candidates = array_values(array_filter(
            $this->catalogue(),
            fn (array $annonce): bool => $annonce['emplacement'] === $emplacement->value
                && ($annonce['debut'] === null || $annonce['debut'] <= $jour)
                && ($annonce['fin'] === null || $annonce['fin'] >= $jour),
        ));

        if ($candidates === []) {
            return null;
        }

        $tirage = random_int(1, (int) array_sum(array_column($candidates, 'poids')));
        $annonce = $candidates[0];
        foreach ($candidates as $candidate) {
            $annonce = $candidate;
            $tirage -= $candidate['poids'];
            if ($tirage <= 0) {
                break;
            }
        }

        return [
            'id' => $annonce['id'],
            'annonceur' => $annonce['annonceur'],
            'texte' => $annonce['texte'],
            'image' => $annonce['image'],
            'image_secondaire' => $annonce['image_secondaire'],
        ];
    }

    /**
     * Impressions vues, envoyées par le navigateur (ids d'annonces existantes seulement).
     *
     * @param  list<int>  $ids
     */
    public function compterImpressions(array $ids): int
    {
        $connues = array_column($this->catalogue(), 'id');
        $comptees = 0;

        foreach (array_unique($ids) as $id) {
            if (in_array($id, $connues, true)) {
                $this->cache->incrementerCompteurDiffere(self::COMPTEUR_IMPRESSIONS, $id);
                $comptees++;
            }
        }

        return $comptees;
    }

    public function compterClic(Publicite $publicite): void
    {
        $this->cache->incrementerCompteurDiffere(self::COMPTEUR_CLICS, $publicite->id);
    }

    /**
     * Lien du bouton « Annoncez ici » (paramètre publicite_contact) ; à défaut, ou s'il est invalide, la page Tarifs.
     */
    public function lienContact(): string
    {
        $contact = trim(Parametre::texte('publicite_contact'));

        return preg_match(self::LIEN_CONTACT_VALIDE, $contact) === 1 ? $contact : route('tarifs');
    }

    /**
     * Annonces actives avec leur image principale, en cache 24 h (invalidé à chaque modification).
     *
     * @return list<array{id: int, emplacement: string, annonceur: string, texte: string, poids: int, debut: ?string, fin: ?string, image: string, image_secondaire: ?string}>
     */
    private function catalogue(): array
    {
        return $this->cache->referentiel('publicites', fn (): array => Publicite::query()
            ->where('active', true)
            ->with('media')
            ->get()
            ->map(fn (Publicite $publicite): array => [
                'id' => $publicite->id,
                'emplacement' => $publicite->emplacement->value,
                'annonceur' => $publicite->annonceur,
                'texte' => $publicite->texte_alternatif,
                'poids' => max(1, $publicite->poids),
                'debut' => $publicite->debut_le?->toDateString(),
                'fin' => $publicite->fin_le?->toDateString(),
                'image' => (string) $publicite->urlImage(Publicite::IMAGE_PRINCIPALE),
                'image_secondaire' => $publicite->urlImage(Publicite::IMAGE_SECONDAIRE),
            ])
            ->filter(fn (array $annonce): bool => $annonce['image'] !== '')
            ->values()
            ->all());
    }
}
