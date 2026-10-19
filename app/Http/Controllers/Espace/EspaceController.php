<?php

namespace App\Http\Controllers\Espace;

use App\Enums\StatutPublication;
use App\Http\Controllers\Controller;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Services\FilService;
use App\Services\RenduCarte;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Accueil de « Mon espace » et publications enregistrées.
 */
class EspaceController extends Controller
{
    public const int ENREGISTRES_PAR_PAGE = 12;

    public function index(Request $request): View
    {
        $membre = $this->membre($request);

        return view('espace.index', [
            'membre' => $membre,
            'nbPublications' => Publication::query()->where('auteur_id', $membre->id)->count(),
            'nbAlertes' => $membre->alertes()->where('active', true)->count(),
            'nbNonLues' => $membre->unreadNotifications()->count(),
            'nbEnregistres' => $membre->publicationsEnregistrees()->count(),
        ]);
    }

    public function enregistres(Request $request, RenduCarte $rendu): View
    {
        $membre = $this->membre($request);

        $publications = $membre->publicationsEnregistrees()
            ->select(array_map(fn (string $colonne): string => 'publications.'.$colonne, FilService::COLONNES_CARTE))
            ->orderByPivot('cree_le', 'desc')
            ->paginate(self::ENREGISTRES_PAR_PAGE);

        return view('espace.enregistres', [
            'publications' => $publications,
            'rendu' => $rendu->chargerEtat($membre, collect($publications->items())),
            'statutPublie' => StatutPublication::Publiee,
        ]);
    }

    private function membre(Request $request): Utilisateur
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof Utilisateur, 401);

        return $utilisateur;
    }
}
