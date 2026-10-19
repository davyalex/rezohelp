<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\GererEntreprise;
use App\DataTables\EntreprisesDataTable;
use App\Exceptions\OperationImpossibleException;
use App\Models\Entreprise;
use App\Models\Publication;
use App\Services\Referentiels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * F — Entreprises : fiche, badge « vérifiée », fusion de doublons (CONTEXTE §13.3.F).
 */
class EntrepriseController extends ControleurAdmin
{
    public function __construct(private readonly GererEntreprise $gerer) {}

    public function index(Request $requete, EntreprisesDataTable $table): View|JsonResponse
    {
        return $requete->ajax() ? $table->reponse($requete) : view('admin.entreprises.index', ['table' => $table]);
    }

    public function show(Entreprise $entreprise, Referentiels $referentiels): View
    {
        $entreprise->load('proprietaire:id,nom,prenoms');

        return view('admin.entreprises.show', [
            'entreprise' => $entreprise,
            'commune' => $referentiels->nomCommune($entreprise->commune_id),
            'publications' => Publication::query()->where('entreprise_id', $entreprise->id)->latest('cree_le')->limit(20)->get(['id', 'titre', 'statut', 'cree_le']),
            'abonnements' => $entreprise->abonnements()->with('plan:id,nom')->latest('debut_le')->limit(10)->get(),
        ]);
    }

    public function verifier(Request $requete, Entreprise $entreprise): RedirectResponse
    {
        $this->gerer->basculerVerification($entreprise, $this->admin($requete));

        return back()->with('statut', $entreprise->verifiee ? 'Entreprise vérifiée : le badge apparaît sur ses cartes.' : 'Badge « Entreprise vérifiée » retiré.');
    }

    /**
     * Fusionne cette fiche (doublon) dans l'entreprise choisie, qui est conservée.
     */
    public function fusionner(Request $requete, Entreprise $entreprise): RedirectResponse
    {
        $cible = $requete->validate(['entreprise_conservee_id' => ['required', 'integer', 'exists:entreprises,id']])['entreprise_conservee_id'];
        $conservee = Entreprise::query()->findOrFail($cible);

        try {
            $this->gerer->fusionner($entreprise, $conservee, $this->admin($requete));
        } catch (OperationImpossibleException $exception) {
            return back()->with('erreur', $exception->getMessage());
        }

        return redirect()->route('admin.entreprises.show', $conservee)
            ->with('statut', 'Fiches fusionnées : « '.$entreprise->nom.' » a été rattachée à cette fiche.');
    }
}
