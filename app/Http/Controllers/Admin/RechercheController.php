<?php

namespace App\Http\Controllers\Admin;

use App\Models\Entreprise;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Services\TelephoneService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recherches Ajax des listes Select2 (CONTEXTE §13.1) : 20 résultats au plus, format Select2.
 */
class RechercheController extends ControleurAdmin
{
    private const int RESULTATS_MAX = 20;

    public function utilisateurs(Request $requete, TelephoneService $telephones): JsonResponse
    {
        $terme = $this->terme($requete);

        $utilisateurs = Utilisateur::query()
            ->when($terme !== '', fn ($r) => self::rechercherUtilisateur($r, $terme, $telephones))
            ->orderBy('nom')
            ->limit(self::RESULTATS_MAX)
            ->get(['id', 'nom', 'prenoms', 'telephone', 'pays_code']);

        return $this->resultats($utilisateurs->map(fn (Utilisateur $u): array => [
            'id' => $u->id,
            'text' => $u->nomAffiche().($u->telephone !== null ? ' · '.$telephones->afficher($u->telephone, 'CI') : ''),
        ])->all());
    }

    public function entreprises(Request $requete): JsonResponse
    {
        $terme = $this->terme($requete);

        $entreprises = Entreprise::query()
            ->when($terme !== '', fn ($r) => $r->where('nom', 'like', '%'.self::echapper($terme).'%'))
            ->orderBy('nom')
            ->limit(self::RESULTATS_MAX)
            ->get(['id', 'nom']);

        return $this->resultats($entreprises->map(fn (Entreprise $e): array => ['id' => $e->id, 'text' => $e->nom.' (n° '.$e->id.')'])->all());
    }

    public function publications(Request $requete): JsonResponse
    {
        $terme = $this->terme($requete);

        $publications = Publication::query()
            ->when(ctype_digit($terme), fn ($r) => $r->whereKey((int) $terme), fn ($r) => $r->where('titre', 'like', '%'.self::echapper($terme).'%'))
            ->latest('cree_le')
            ->limit(self::RESULTATS_MAX)
            ->get(['id', 'titre']);

        return $this->resultats($publications->map(fn (Publication $p): array => ['id' => $p->id, 'text' => $p->titre.' (n° '.$p->id.')'])->all());
    }

    /**
     * Recherche par téléphone normalisé (0708091011, 07 08 09 10 11 et +225… trouvent le même
     * compte, §13.3.F) ou par nom et prénoms.
     *
     * @param  Builder<Utilisateur>  $requete
     * @return Builder<Utilisateur>
     */
    public static function rechercherUtilisateur(Builder $requete, string $terme, TelephoneService $telephones): Builder
    {
        $telephone = $telephones->normaliser($terme, 'CI');

        return $requete->where(function ($r) use ($terme, $telephone): void {
            if ($telephone !== null) {
                $r->where('telephone', $telephone);
            }

            $motif = '%'.self::echapper($terme).'%';
            $r->orWhere('nom', 'like', $motif)->orWhere('prenoms', 'like', $motif)
                ->orWhereRaw("concat(coalesce(prenoms, ''), ' ', nom) like ?", [$motif]);
        });
    }

    public static function echapper(string $terme): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $terme);
    }

    private function terme(Request $requete): string
    {
        return mb_substr(trim($requete->string('q')->toString()), 0, 100);
    }

    /**
     * @param  list<array{id: int, text: string}>  $resultats
     */
    private function resultats(array $resultats): JsonResponse
    {
        return response()->json(['results' => $resultats]);
    }
}
