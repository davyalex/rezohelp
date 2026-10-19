<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\GererAbonnement;
use App\DataTables\AbonnementsDataTable;
use App\Models\Abonnement;
use App\Models\Entreprise;
use App\Models\Plan;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * K — Abonnements : liste, offrir, prolonger, résilier (CONTEXTE §14.10).
 */
class AbonnementController extends ControleurAdmin
{
    public function __construct(private readonly GererAbonnement $gerer) {}

    public function index(Request $requete, AbonnementsDataTable $table): View|JsonResponse
    {
        return $requete->ajax() ? $table->reponse($requete) : view('admin.abonnements.index', [
            'table' => $table,
            'plans' => Plan::query()->orderBy('cible')->orderBy('ordre')->pluck('nom', 'id')->all(),
        ]);
    }

    public function create(): View
    {
        return view('admin.abonnements.offrir', [
            'plans' => Plan::query()->where('est_defaut', false)->orderBy('cible')->orderBy('ordre')->get(['id', 'nom', 'cible', 'duree_jours']),
        ]);
    }

    public function store(Request $requete): RedirectResponse
    {
        $donnees = $requete->validate([
            'titulaire' => ['required', Rule::in(['utilisateur', 'entreprise'])],
            'utilisateur_id' => ['required_if:titulaire,utilisateur', 'nullable', 'integer', 'exists:utilisateurs,id'],
            'entreprise_id' => ['required_if:titulaire,entreprise', 'nullable', 'integer', 'exists:entreprises,id'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'jours' => ['required', 'integer', 'min:1', 'max:3660'],
            'motif' => ['required', 'string', 'max:255'],
        ]);

        $titulaire = $donnees['titulaire'] === 'entreprise'
            ? Entreprise::query()->findOrFail($donnees['entreprise_id'])
            : Utilisateur::query()->findOrFail($donnees['utilisateur_id']);

        return $this->appliquer(
            fn () => $this->gerer->offrir($titulaire, Plan::query()->findOrFail($donnees['plan_id']), (int) $donnees['jours'], (string) $donnees['motif'], $this->admin($requete)),
            'Abonnement offert : les droits sont actifs immédiatement.',
        );
    }

    public function prolonger(Request $requete, Abonnement $abonnement): RedirectResponse
    {
        $jours = (int) $requete->validate(['jours' => ['required', 'integer', 'min:1', 'max:3660']])['jours'];

        return $this->appliquer(fn () => $this->gerer->prolonger($abonnement, $jours, $this->admin($requete)), "Abonnement prolongé de {$jours} jours.");
    }

    public function resilier(Request $requete, Abonnement $abonnement): RedirectResponse
    {
        return $this->appliquer(fn () => $this->gerer->resilier($abonnement, $this->admin($requete)), 'Abonnement résilié.');
    }
}
